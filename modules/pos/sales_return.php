<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
require_once BASE_PATH . '/config/db.php'; // For $conn, escape_string

if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Security check: Cashiers and Admins can process returns
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'cashier'])) {
    $_SESSION['message'] = "Access denied. You do not have permission to process sales returns.";
    $_SESSION['message_type'] = "danger";
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $app_path = dirname($_SERVER['SCRIPT_NAME']);
    if ($app_path === '/' || $app_path === '\\') $app_path = '';
    header("Location: " . $protocol . "://" . $host . $app_path . "/index.php?page=dashboard");
    exit;
}

$base_module_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$script_dir_path = dirname($_SERVER['SCRIPT_NAME']);
if ($script_dir_path === '/' || $script_dir_path === '\\') $script_dir_path = '';
$base_module_url .= $script_dir_path . "/index.php?module=pos&action=sales_return"; // Base for sales return actions

$page_sub_action = $_GET['sub_action'] ?? 'find_sale'; // find_sale, process_return_form, confirm_return

$original_sale = null;
$original_sale_items = [];
$returnable_items_info = []; // To store info about how many of each item can be returned
$form_errors = [];

// --- Find Original Sale (GET or POST for receipt_no) ---
if ($page_sub_action === 'find_sale' || ($page_sub_action === 'process_return_form' && $_SERVER['REQUEST_METHOD'] === 'GET')) {
    $receipt_no_to_find = trim($_REQUEST['receipt_no'] ?? '');

    if (!empty($receipt_no_to_find)) {
        $escaped_receipt_no = escape_string($conn, $receipt_no_to_find);
        $sql_find_sale = "SELECT s.*, c.name as customer_name
                          FROM sales s
                          LEFT JOIN customers c ON s.customer_id = c.id
                          WHERE s.receipt_no = '" . $escaped_receipt_no . "'";
        $res_find_sale = mysqli_query($conn, $sql_find_sale);

        if ($res_find_sale && mysqli_num_rows($res_find_sale) > 0) {
            $original_sale = mysqli_fetch_assoc($res_find_sale);
            mysqli_free_result($res_find_sale);

            if ($original_sale['status'] === 'returned') {
                $_SESSION['message'] = "Sale " . htmlspecialchars($receipt_no_to_find) . " has already been fully returned.";
                $_SESSION['message_type'] = "warning";
                $original_sale = null; // Prevent further processing
            } else {
                // Fetch sale items
                $sql_sale_items = "SELECT si.*, p.name as product_name, p.sku as product_sku
                                   FROM sale_items si
                                   JOIN products p ON si.product_id = p.id
                                   WHERE si.sale_id = " . $original_sale['id'];
                $res_sale_items = mysqli_query($conn, $sql_sale_items);
                if ($res_sale_items) {
                    while ($item_row = mysqli_fetch_assoc($res_sale_items)) {
                        // Check how many already returned for this sale_item_id
                        $sql_already_returned = "SELECT SUM(sri.quantity_returned) as total_returned
                                                 FROM sales_return_items sri
                                                 JOIN sales_returns sr ON sri.sales_return_id = sr.id
                                                 WHERE sr.original_sale_id = " . $original_sale['id'] . " AND sri.original_sale_item_id = " . $item_row['id'];
                        $res_already_returned = mysqli_query($conn, $sql_already_returned);
                        $already_returned_qty = 0;
                        if($res_already_returned && mysqli_num_rows($res_already_returned) > 0){
                            $already_returned_qty = (int)mysqli_fetch_assoc($res_already_returned)['total_returned'];
                            mysqli_free_result($res_already_returned);
                        }

                        $item_row['quantity_returnable'] = $item_row['quantity'] - $already_returned_qty;
                        if ($item_row['quantity_returnable'] > 0) {
                            $original_sale_items[] = $item_row;
                        }
                    }
                    mysqli_free_result($res_sale_items);
                }
                if (empty($original_sale_items) && $original_sale['status'] !== 'returned') {
                     $_SESSION['message'] = "No items available for return for sale " . htmlspecialchars($receipt_no_to_find) . ". They may have all been returned already.";
                     $_SESSION['message_type'] = "info";
                     $original_sale = null; // Prevent further processing
                }
            }
        } else {
            $_SESSION['message'] = "Original sale with receipt number '" . htmlspecialchars($receipt_no_to_find) . "' not found.";
            $_SESSION['message_type'] = "danger";
        }
    } elseif (isset($_REQUEST['receipt_no'])) { // Receipt no was submitted but empty
         $_SESSION['message'] = "Please enter a receipt number to find the sale.";
         $_SESSION['message_type'] = "warning";
    }
    // After finding, if items exist, $page_sub_action might implicitly become 'process_return_form' for display
    if ($original_sale && !empty($original_sale_items)) {
        $page_sub_action = 'process_return_form';
    } else {
        // If no sale found or no items returnable, stay on find_sale or show message
        $page_sub_action = 'find_sale';
    }
}


// --- Process Return Submission (POST) ---
if ($page_sub_action === 'confirm_return' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $original_sale_id = isset($_POST['original_sale_id']) ? (int)$_POST['original_sale_id'] : 0;
    $return_quantities = $_POST['return_quantity'] ?? []; // Array like [sale_item_id => qty_to_return]
    $return_reasons = $_POST['return_reason'] ?? [];     // Array like [sale_item_id => reason]
    $overall_return_reason = trim($_POST['overall_return_reason'] ?? 'N/A');
    $user_id = $_SESSION['user_id'];

    if ($original_sale_id <= 0) {
        $form_errors[] = "Invalid original sale ID.";
    }
    if (empty($return_quantities)) {
        $form_errors[] = "No items selected for return or quantities specified.";
    }

    $items_to_return_db = [];
    $total_refund_amount_calc = 0;
    $at_least_one_item_valid_for_return = false;

    // Fetch original sale items again for validation within transaction
    $sql_orig_items_val = "SELECT si.id as sale_item_id, si.product_id, si.quantity as original_quantity, si.price_per_item_after_discount as effective_price
                           FROM sale_items si WHERE si.sale_id = " . $original_sale_id;
    $res_orig_items_val = mysqli_query($conn, $sql_orig_items_val);
    $original_items_map = [];
    if ($res_orig_items_val) {
        while($row = mysqli_fetch_assoc($res_orig_items_val)) $original_items_map[$row['sale_item_id']] = $row;
        mysqli_free_result($res_orig_items_val);
    } else {
        $form_errors[] = "Could not verify original sale items.";
    }

    if (empty($form_errors)) {
        foreach ($return_quantities as $sale_item_id => $qty_to_return) {
            $qty_to_return = (int)$qty_to_return;
            if ($qty_to_return > 0) {
                if (!isset($original_items_map[$sale_item_id])) {
                    $form_errors[] = "Invalid item (ID: {$sale_item_id}) selected for return.";
                    continue;
                }
                $orig_item_detail = $original_items_map[$sale_item_id];

                // Check how many already returned for this specific sale_item_id from original sale
                $sql_already_ret = "SELECT SUM(sri.quantity_returned) as total_returned
                                    FROM sales_return_items sri
                                    JOIN sales_returns sr ON sri.sales_return_id = sr.id
                                    WHERE sr.original_sale_id = " . $original_sale_id . " AND sri.original_sale_item_id = " . $sale_item_id;
                $res_already_ret = mysqli_query($conn, $sql_already_ret);
                $already_returned_for_item = 0;
                if($res_already_ret) {
                    $already_returned_for_item = (int)mysqli_fetch_assoc($res_already_ret)['total_returned'];
                    mysqli_free_result($res_already_ret);
                }

                $max_returnable_for_item = $orig_item_detail['original_quantity'] - $already_returned_for_item;

                if ($qty_to_return > $max_returnable_for_item) {
                    $form_errors[] = "Cannot return " . $qty_to_return . " for item ID " . $sale_item_id . ". Max returnable: " . $max_returnable_for_item;
                    continue;
                }

                $at_least_one_item_valid_for_return = true;
                $items_to_return_db[] = [
                    'original_sale_item_id' => $sale_item_id,
                    'product_id' => $orig_item_detail['product_id'],
                    'quantity_returned' => $qty_to_return,
                    'refund_price_per_item' => $orig_item_detail['effective_price'], // Price at which it was sold
                    'line_refund_total' => $qty_to_return * $orig_item_detail['effective_price'],
                    'reason' => $return_reasons[$sale_item_id] ?? '' // Individual item reason (optional for this design)
                ];
                $total_refund_amount_calc += ($qty_to_return * $orig_item_detail['effective_price']);
            }
        }
    }

    if (!$at_least_one_item_valid_for_return && empty($form_errors)) {
        $form_errors[] = "No valid items or quantities specified for return.";
    }


    if (empty($form_errors)) {
        mysqli_begin_transaction($conn);
        $success = true;
        $new_sales_return_id = 0;

        // 1. Insert into sales_returns
        $return_receipt_no = 'RTN-' . time() . '-' . rand(100,999);
        $sql_insert_return_header = "INSERT INTO sales_returns (original_sale_id, return_receipt_no, user_id, return_date, total_refund_amount, reason) VALUES (
                                        " . $original_sale_id . ",
                                        '" . $return_receipt_no . "',
                                        " . $user_id . ",
                                        CURRENT_TIMESTAMP,
                                        " . $total_refund_amount_calc . ",
                                        '" . escape_string($conn, $overall_return_reason) . "'
                                     )";
        if (mysqli_query($conn, $sql_insert_return_header)) {
            $new_sales_return_id = mysqli_insert_id($conn);
        } else {
            $success = false;
            $_SESSION['message'] = "Error creating sales return record: " . mysqli_error($conn);
        }

        // 2. Insert into sales_return_items and update stock
        if ($success) {
            foreach ($items_to_return_db as $item_ret) {
                $sql_insert_return_item = "INSERT INTO sales_return_items (sales_return_id, product_id, original_sale_item_id, quantity_returned, refund_price_per_item, line_refund_total) VALUES (
                                                " . $new_sales_return_id . ",
                                                " . $item_ret['product_id'] . ",
                                                " . $item_ret['original_sale_item_id'] . ",
                                                " . $item_ret['quantity_returned'] . ",
                                                " . $item_ret['refund_price_per_item'] . ",
                                                " . $item_ret['line_refund_total'] . "
                                           )";
                if (!mysqli_query($conn, $sql_insert_return_item)) {
                    $success = false; $_SESSION['message'] = "Error saving returned item details: " . mysqli_error($conn); break;
                }

                // Update product stock
                $sql_update_stock = "UPDATE products SET current_stock = current_stock + " . $item_ret['quantity_returned'] . " WHERE id = " . $item_ret['product_id'];
                if (!mysqli_query($conn, $sql_update_stock)) {
                    $success = false; $_SESSION['message'] = "Error updating stock for returned product ID " . $item_ret['product_id'] . ": " . mysqli_error($conn); break;
                }
            }
        }

        // 3. Update original sale status
        if ($success) {
            // Check if all items from original sale are now returned
            $total_original_qty = 0;
            $total_globally_returned_qty = 0;

            $sql_check_orig_sale_status = "SELECT SUM(si.quantity) as total_qty_sold FROM sale_items si WHERE si.sale_id = " . $original_sale_id;
            $res_check_orig = mysqli_query($conn, $sql_check_orig_sale_status);
            if($res_check_orig) $total_original_qty = (int)mysqli_fetch_assoc($res_check_orig)['total_qty_sold'];
            mysqli_free_result($res_check_orig);

            $sql_check_glob_ret_status = "SELECT SUM(sri.quantity_returned) as total_qty_returned
                                          FROM sales_return_items sri
                                          JOIN sales_returns sr ON sri.sales_return_id = sr.id
                                          WHERE sr.original_sale_id = " . $original_sale_id;
            $res_check_glob_ret = mysqli_query($conn, $sql_check_glob_ret_status);
            if($res_check_glob_ret) $total_globally_returned_qty = (int)mysqli_fetch_assoc($res_check_glob_ret)['total_qty_returned'];
            mysqli_free_result($res_check_glob_ret);

            $new_original_sale_status = ($total_globally_returned_qty >= $total_original_qty) ? 'returned' : 'partially_returned';

            $sql_update_orig_sale = "UPDATE sales SET status = '" . $new_original_sale_status . "' WHERE id = " . $original_sale_id;
            if (!mysqli_query($conn, $sql_update_orig_sale)) {
                $success = false; $_SESSION['message'] = "Error updating original sale status: " . mysqli_error($conn);
            }
        }


        if ($success) {
            mysqli_commit($conn);
            $_SESSION['message'] = "Sales return processed successfully! Return Receipt No: " . $return_receipt_no . ". Refund due: " . number_format($total_refund_amount_calc,2);
            $_SESSION['message_type'] = "success";
            // Redirect to a view return page or sales return list
            header("Location: " . $base_module_url . "&sub_action=find_sale"); // For now, back to find sale
            exit;
        } else {
            mysqli_rollback($conn);
            // $_SESSION['message'] is already set with error
            $_SESSION['message_type'] = "danger";
            // Need to re-fetch original sale data to display form again
            $receipt_no_to_find = $_POST['original_receipt_no_hidden'] ?? ''; // Assume this was passed
            $page_sub_action = 'process_return_form'; // To show the form again with errors
            // This requires re-fetching $original_sale and $original_sale_items
            // This part can be tricky without a full page reload or more state management.
            // For now, it might redirect to find_sale and user has to search again if error is complex.
            // Simpler: just display form errors if any.
             header("Location: " . $base_module_url . "&sub_action=process_return_form&receipt_no=" . urlencode($_POST['original_receipt_no_hidden'] ?? '') . "&error=processing");
             exit;
        }

    } else { // Form errors from validation
        $_SESSION['message'] = "Please correct the errors: " . implode(" ", $form_errors);
        $_SESSION['message_type'] = "danger";
        $page_sub_action = 'process_return_form'; // To show the form again
        // This also requires re-fetching $original_sale and $original_sale_items.
        // The current structure re-fetches if receipt_no is in GET for process_return_form.
        header("Location: " . $base_module_url . "&sub_action=process_return_form&receipt_no=" . urlencode($_POST['original_receipt_no_hidden'] ?? '') . "&validation_errors=true");
        exit;
    }
}


// --- Display Logic ---
// This module will mainly use one template: sales_return_form.php
// The template will adapt based on whether $original_sale is found.
require_once BASE_PATH . '/templates/pos/sales_return_form.php';

// mysqli_close($conn); // Closed by index.php
?>
