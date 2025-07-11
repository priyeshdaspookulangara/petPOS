<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
require_once BASE_PATH . '/config/db.php'; // For $conn, escape_string

if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Security check: Only admins can access purchase management
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Access denied. You must be an admin to manage purchases.";
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
$base_module_url .= $script_dir_path . "/index.php?module=purchases";

$page_action = isset($_GET['sub_action']) ? $_GET['sub_action'] : 'list_po'; // list_po, create_po, edit_po, view_po, receive_goods, etc.
$po_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Data for forms
$po_data = [
    'po_number' => 'PO-' . time(), // Default PO number
    'supplier_id' => null,
    'purchase_date' => date('Y-m-d'),
    'expected_delivery_date' => null,
    'shipping_cost' => '0.00',
    'other_charges' => '0.00',
    'discount_amount' => '0.00', // Overall PO discount
    'notes' => '',
    'status' => 'draft', // Default status
    'items' => [] // For storing purchase items
];
$form_errors = [];
$products_list = []; // For product selection in PO form
$suppliers_list = []; // For supplier selection

// Fetch common data for forms if needed
if ($page_action === 'create_po' || $page_action === 'edit_po') {
    $sql_products = "SELECT id, name, sku, purchase_price FROM products ORDER BY name ASC";
    $res_products = mysqli_query($conn, $sql_products);
    if ($res_products) {
        while ($row = mysqli_fetch_assoc($res_products)) $products_list[] = $row;
        mysqli_free_result($res_products);
    }

    $sql_suppliers = "SELECT id, name FROM suppliers ORDER BY name ASC";
    $res_suppliers = mysqli_query($conn, $sql_suppliers);
    if ($res_suppliers) {
        while ($row = mysqli_fetch_assoc($res_suppliers)) $suppliers_list[] = $row;
        mysqli_free_result($res_suppliers);
    }
}


// --- POST Request Handling for Create/Edit PO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_form_action = $_POST['form_action'] ?? '';

    if ($posted_form_action === 'save_po') {
        // Populate $po_data from POST
        $po_data['po_number'] = trim($_POST['po_number']);
        $po_data['supplier_id'] = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $po_data['purchase_date'] = trim($_POST['purchase_date']);
        $po_data['expected_delivery_date'] = !empty($_POST['expected_delivery_date']) ? trim($_POST['expected_delivery_date']) : null;
        $po_data['shipping_cost'] = isset($_POST['shipping_cost']) ? floatval($_POST['shipping_cost']) : 0.00;
        $po_data['other_charges'] = isset($_POST['other_charges']) ? floatval($_POST['other_charges']) : 0.00;
        $po_data['discount_amount'] = isset($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : 0.00;
        $po_data['notes'] = trim($_POST['notes']);
        $po_data['status'] = trim($_POST['status']); // e.g. draft, ordered
        $posted_po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0; // For edits

        // Items from the form (assuming arrays: product_ids[], quantities[], purchase_prices[])
        $item_product_ids = $_POST['item_product_id'] ?? [];
        $item_quantities = $_POST['item_quantity'] ?? [];
        $item_purchase_prices = $_POST['item_purchase_price'] ?? [];

        $po_data['items'] = [];
        $calculated_sub_total = 0;
        for ($i = 0; $i < count($item_product_ids); $i++) {
            if (!empty($item_product_ids[$i]) && !empty($item_quantities[$i]) && isset($item_purchase_prices[$i])) {
                $qty = (int)$item_quantities[$i];
                $price = floatval($item_purchase_prices[$i]);
                if ($qty > 0 && $price >= 0) {
                    $po_data['items'][] = [
                        'product_id' => (int)$item_product_ids[$i],
                        'quantity_ordered' => $qty,
                        'purchase_price_per_item' => $price,
                        'line_total' => $qty * $price
                    ];
                    $calculated_sub_total += ($qty * $price);
                }
            }
        }

        // --- Validation ---
        if (empty($po_data['po_number'])) $form_errors['po_number'] = "PO Number is required.";
        else { // Check PO Number uniqueness
            $sql_check_po = "SELECT id FROM purchases WHERE po_number = '" . escape_string($conn, $po_data['po_number']) . "'";
            if ($page_action === 'edit_po' && $posted_po_id > 0) $sql_check_po .= " AND id != " . $posted_po_id;
            $res_po_check = mysqli_query($conn, $sql_check_po);
            if ($res_po_check && mysqli_num_rows($res_po_check) > 0) $form_errors['po_number'] = "This PO Number already exists.";
            if($res_po_check) mysqli_free_result($res_po_check);
        }
        if (empty($po_data['supplier_id'])) $form_errors['supplier_id'] = "Supplier is required.";
        if (empty($po_data['purchase_date'])) $form_errors['purchase_date'] = "Purchase date is required.";
        if (empty($po_data['items'])) $form_errors['items'] = "At least one item is required in the purchase order.";
        if (!in_array($po_data['status'], ['draft', 'ordered', 'partially_received', 'received', 'canceled'])) $form_errors['status'] = "Invalid PO status.";
        // More validations for dates, amounts etc.
        // --- End Validation ---

        if (empty($form_errors)) {
            mysqli_begin_transaction($conn);
            $success = true;

            $total_amount = $calculated_sub_total + $po_data['shipping_cost'] + $po_data['other_charges'] - $po_data['discount_amount'];
            $user_id = $_SESSION['user_id'];

            $exp_del_date_sql = $po_data['expected_delivery_date'] ? "'" . escape_string($conn, $po_data['expected_delivery_date']) . "'" : "NULL";

            if ($page_action === 'create_po') {
                $sql_po = "INSERT INTO purchases (po_number, supplier_id, user_id, purchase_date, expected_delivery_date, sub_total, shipping_cost, other_charges, discount_amount, total_amount, status, notes, created_at, updated_at) VALUES (
                                '" . escape_string($conn, $po_data['po_number']) . "',
                                " . (int)$po_data['supplier_id'] . ",
                                " . (int)$user_id . ",
                                '" . escape_string($conn, $po_data['purchase_date']) . "',
                                " . $exp_del_date_sql . ",
                                " . $calculated_sub_total . ",
                                " . $po_data['shipping_cost'] . ",
                                " . $po_data['other_charges'] . ",
                                " . $po_data['discount_amount'] . ",
                                " . $total_amount . ",
                                '" . escape_string($conn, $po_data['status']) . "',
                                '" . escape_string($conn, $po_data['notes']) . "',
                                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                           )";
                if (mysqli_query($conn, $sql_po)) {
                    $current_po_id = mysqli_insert_id($conn);
                } else {
                    $success = false;
                    $_SESSION['message'] = "Error creating PO: " . mysqli_error($conn);
                }
            } elseif ($page_action === 'edit_po' && $posted_po_id > 0) {
                $current_po_id = $posted_po_id;
                // Prevent status changes that don't make sense (e.g., from 'received' back to 'draft' without more logic)
                // For now, allowing status update.
                $sql_po = "UPDATE purchases SET
                                po_number = '" . escape_string($conn, $po_data['po_number']) . "',
                                supplier_id = " . (int)$po_data['supplier_id'] . ",
                                user_id = " . (int)$user_id . ", /* or keep original user? */
                                purchase_date = '" . escape_string($conn, $po_data['purchase_date']) . "',
                                expected_delivery_date = " . $exp_del_date_sql . ",
                                sub_total = " . $calculated_sub_total . ",
                                shipping_cost = " . $po_data['shipping_cost'] . ",
                                other_charges = " . $po_data['other_charges'] . ",
                                discount_amount = " . $po_data['discount_amount'] . ",
                                total_amount = " . $total_amount . ",
                                status = '" . escape_string($conn, $po_data['status']) . "',
                                notes = '" . escape_string($conn, $po_data['notes']) . "',
                                updated_at = CURRENT_TIMESTAMP
                           WHERE id = " . $current_po_id;
                if (!mysqli_query($conn, $sql_po)) {
                    $success = false;
                    $_SESSION['message'] = "Error updating PO: " . mysqli_error($conn);
                } else {
                    // Delete existing items before re-adding (simple approach for edit)
                    // More complex would be to update existing, delete removed, add new.
                    $sql_delete_items = "DELETE FROM purchase_items WHERE purchase_id = " . $current_po_id;
                    if (!mysqli_query($conn, $sql_delete_items)) {
                        $success = false;
                        $_SESSION['message'] = "Error clearing old PO items: " . mysqli_error($conn);
                    }
                }
            } else {
                 $success = false; // Should not happen
                 $_SESSION['message'] = "Invalid PO operation.";
            }

            if ($success) {
                foreach ($po_data['items'] as $item) {
                    $sql_item = "INSERT INTO purchase_items (purchase_id, product_id, quantity_ordered, purchase_price_per_item, line_total) VALUES (
                                    " . $current_po_id . ",
                                    " . $item['product_id'] . ",
                                    " . $item['quantity_ordered'] . ",
                                    " . $item['purchase_price_per_item'] . ",
                                    " . $item['line_total'] . "
                                 )";
                    if (!mysqli_query($conn, $sql_item)) {
                        $success = false;
                        $_SESSION['message'] = "Error adding PO item (" . $item['product_id'] . "): " . mysqli_error($conn);
                        break;
                    }
                }
            }

            if ($success) {
                mysqli_commit($conn);
                $_SESSION['message_type'] = "success";
                $_SESSION['message'] = ($page_action === 'create_po') ? "Purchase Order created successfully!" : "Purchase Order updated successfully!";
                header("Location: " . $base_module_url . "&sub_action=view_po&id=" . $current_po_id);
                exit;
            } else {
                mysqli_rollback($conn);
                $_SESSION['message_type'] = "danger";
                // Error message already set
                // Fall through to re-render form with errors. $po_data is populated.
                $page_action = ($page_action === 'edit_po') ? 'edit_po' : 'create_po';
                $po_id = $posted_po_id; // Keep id for edit form
            }
        } else {
            // Validation errors. $po_data is populated.
            $page_action = ($page_action === 'edit_po') ? 'edit_po' : 'create_po';
            $po_id = $posted_po_id; // Keep id for edit form
        }
    } elseif ($posted_form_action === 'update_po_status' && $po_id > 0) {
        // Handle direct status update from list or view page (e.g., mark as 'ordered' or 'canceled')
        $new_status = $_POST['new_status'] ?? '';
        if (in_array($new_status, ['draft', 'ordered', 'partially_received', 'received', 'canceled'])) {
            // Add more logic here: e.g., cannot cancel if already received.
            // Cannot mark 'received' from here, that's through Goods Receipt process.
            if ($new_status === 'received' || $new_status === 'partially_received') {
                 $_SESSION['message'] = "To mark items as received, please use the 'Receive Goods' process.";
                 $_SESSION['message_type'] = "warning";
            } else {
                $sql_update_status = "UPDATE purchases SET status = '" . escape_string($conn, $new_status) . "', updated_at = CURRENT_TIMESTAMP WHERE id = " . $po_id;
                if (mysqli_query($conn, $sql_update_status)) {
                    $_SESSION['message'] = "PO #" . $po_id . " status updated to '" . ucfirst($new_status) . "'.";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Error updating PO status: " . mysqli_error($conn);
                    $_SESSION['message_type'] = "danger";
                }
            }
        } else {
            $_SESSION['message'] = "Invalid status provided for update.";
            $_SESSION['message_type'] = "danger";
        }
        header("Location: " . $base_module_url . "&sub_action=view_po&id=" . $po_id); // or list_po
        exit;
    }
}


// --- GET Request Logic ---
if ($page_action === 'edit_po' && $po_id > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') { // Only if not POST error recovery
    $sql_get_po = "SELECT * FROM purchases WHERE id = " . $po_id;
    $result_get_po = mysqli_query($conn, $sql_get_po);
    if ($result_get_po && mysqli_num_rows($result_get_po) > 0) {
        $fetched_po = mysqli_fetch_assoc($result_get_po);
        mysqli_free_result($result_get_po);

        // Populate $po_data for the form
        $po_data['po_number'] = $fetched_po['po_number'];
        $po_data['supplier_id'] = $fetched_po['supplier_id'];
        $po_data['purchase_date'] = $fetched_po['purchase_date'];
        $po_data['expected_delivery_date'] = $fetched_po['expected_delivery_date'];
        $po_data['shipping_cost'] = $fetched_po['shipping_cost'];
        $po_data['other_charges'] = $fetched_po['other_charges'];
        $po_data['discount_amount'] = $fetched_po['discount_amount'];
        $po_data['notes'] = $fetched_po['notes'];
        $po_data['status'] = $fetched_po['status'];

        // Fetch PO items
        $sql_get_items = "SELECT pi.*, p.name as product_name, p.sku as product_sku
                          FROM purchase_items pi
                          JOIN products p ON pi.product_id = p.id
                          WHERE pi.purchase_id = " . $po_id;
        $res_get_items = mysqli_query($conn, $sql_get_items);
        if ($res_get_items) {
            while($item_row = mysqli_fetch_assoc($res_get_items)) {
                $po_data['items'][] = $item_row;
            }
            mysqli_free_result($res_get_items);
        }
    } else {
        $_SESSION['message'] = "Purchase Order not found.";
        $_SESSION['message_type'] = "warning";
        header("Location: " . $base_module_url);
        exit;
    }
} elseif ($page_action === 'delete_po' && $po_id > 0) {
    // Only allow deletion of 'draft' POs or as per business rules
    $sql_check_status = "SELECT status FROM purchases WHERE id = " . $po_id;
    $res_check_status = mysqli_query($conn, $sql_check_status);
    if ($res_check_status && mysqli_num_rows($res_check_status) > 0) {
        $current_status = mysqli_fetch_assoc($res_check_status)['status'];
        mysqli_free_result($res_check_status);
        if ($current_status !== 'draft' && $current_status !== 'canceled') { // Example rule
            $_SESSION['message'] = "Cannot delete PO. Status is '" . ucfirst($current_status) . "'. Only draft or canceled POs can be deleted.";
            $_SESSION['message_type'] = "warning";
        } else {
            mysqli_begin_transaction($conn);
            $sql_delete_items = "DELETE FROM purchase_items WHERE purchase_id = " . $po_id;
            if (mysqli_query($conn, $sql_delete_items)) {
                $sql_delete_po = "DELETE FROM purchases WHERE id = " . $po_id;
                if (mysqli_query($conn, $sql_delete_po)) {
                    mysqli_commit($conn);
                    $_SESSION['message'] = "Purchase Order deleted successfully!";
                    $_SESSION['message_type'] = "success";
                } else {
                    mysqli_rollback($conn);
                    $_SESSION['message'] = "Error deleting PO: " . mysqli_error($conn);
                    $_SESSION['message_type'] = "danger";
                }
            } else {
                mysqli_rollback($conn);
                $_SESSION['message'] = "Error deleting PO items: " . mysqli_error($conn);
                $_SESSION['message_type'] = "danger";
            }
        }
    } else {
        $_SESSION['message'] = "PO not found for deletion.";
        $_SESSION['message_type'] = "warning";
    }
    header("Location: " . $base_module_url);
    exit;
}


// --- Display Logic ---
if ($page_action === 'list_po') {
    $purchase_orders = [];
    $sql_list = "SELECT p.*, s.name as supplier_name
                 FROM purchases p
                 JOIN suppliers s ON p.supplier_id = s.id
                 ORDER BY p.purchase_date DESC, p.id DESC";
    $result_list = mysqli_query($conn, $sql_list);
    if ($result_list) {
        while ($row = mysqli_fetch_assoc($result_list)) {
            $purchase_orders[] = $row;
        }
        mysqli_free_result($result_list);
    } else {
        $_SESSION['message'] = "Error fetching purchase orders: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    require_once BASE_PATH . '/templates/purchases/po_list.php';
} elseif ($page_action === 'create_po' || $page_action === 'edit_po') {
    // $po_data, $form_errors, $products_list, $suppliers_list are prepared.
    require_once BASE_PATH . '/templates/purchases/po_form.php';
} elseif ($page_action === 'view_po' && $po_id > 0) {
    // Fetch full PO details for viewing
    $sql_get_po_full = "SELECT p.*, s.name as supplier_name, s.email as supplier_email, s.phone as supplier_phone, s.address as supplier_address, u.username as created_by_username
                        FROM purchases p
                        JOIN suppliers s ON p.supplier_id = s.id
                        JOIN users u ON p.user_id = u.id
                        WHERE p.id = " . $po_id;
    $result_get_po_full = mysqli_query($conn, $sql_get_po_full);
    if ($result_get_po_full && mysqli_num_rows($result_get_po_full) > 0) {
        $po_to_view = mysqli_fetch_assoc($result_get_po_full);
        mysqli_free_result($result_get_po_full);

        $po_to_view['items'] = [];
        $sql_get_items_view = "SELECT pi.*, pr.name as product_name, pr.sku as product_sku
                               FROM purchase_items pi
                               JOIN products pr ON pi.product_id = pr.id
                               WHERE pi.purchase_id = " . $po_id;
        $res_get_items_view = mysqli_query($conn, $sql_get_items_view);
        if ($res_get_items_view) {
            while($item_row_view = mysqli_fetch_assoc($res_get_items_view)) {
                $po_to_view['items'][] = $item_row_view;
            }
            mysqli_free_result($res_get_items_view);
        }
        require_once BASE_PATH . '/templates/purchases/po_view.php'; // New template for viewing
    } else {
        $_SESSION['message'] = "Purchase Order not found for viewing.";
        $_SESSION['message_type'] = "warning";
        header("Location: " . $base_module_url);
        exit;
    }
} elseif ($page_action === 'receive_goods' && $po_id > 0) {
    // Logic for goods receipt page
    // This will be more complex, involving fetching PO items and allowing input for received quantities.
    // For now, a placeholder or redirect if not fully implemented.
    // This will be a separate step in the plan.
    // Logic for displaying goods receipt form
    $po_for_receipt = null;
    $sql_get_po_receipt = "SELECT p.*, s.name as supplier_name
                           FROM purchases p
                           JOIN suppliers s ON p.supplier_id = s.id
                           WHERE p.id = " . $po_id . " AND (p.status = 'ordered' OR p.status = 'partially_received')";
    $res_po_receipt = mysqli_query($conn, $sql_get_po_receipt);
    if ($res_po_receipt && mysqli_num_rows($res_po_receipt) > 0) {
        $po_for_receipt = mysqli_fetch_assoc($res_po_receipt);
        mysqli_free_result($res_po_receipt);

        $po_for_receipt['items'] = [];
        $sql_get_items_receipt = "SELECT pi.*, pr.name as product_name, pr.sku as product_sku, pr.current_stock
                                  FROM purchase_items pi
                                  JOIN products pr ON pi.product_id = pr.id
                                  WHERE pi.purchase_id = " . $po_id;
        $res_items_receipt = mysqli_query($conn, $sql_get_items_receipt);
        if ($res_items_receipt) {
            while($item_row_receipt = mysqli_fetch_assoc($res_items_receipt)) {
                $po_for_receipt['items'][] = $item_row_receipt;
            }
            mysqli_free_result($res_items_receipt);
        }
        require_once BASE_PATH . '/templates/purchases/goods_receipt_form.php';
    } else {
        $_SESSION['message'] = "PO not found, not in correct status for receiving, or error fetching PO for goods receipt.";
        $_SESSION['message_type'] = "warning";
        header("Location: " . $base_module_url . "&sub_action=view_po&id=" . $po_id);
        exit;
    }
    // The goods_receipt_form.php will POST back to a specific action like 'process_goods_receipt'
    // So, need to add that to POST handling section.
    exit; // Exit after loading the form

} elseif ($page_action === 'process_goods_receipt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_po_id_receipt = isset($_POST['po_id_receipt']) ? (int)$_POST['po_id_receipt'] : 0;
    $received_quantities = $_POST['quantity_received'] ?? []; // Array like [item_id => qty_received]
    $received_date = isset($_POST['received_date']) ? trim($_POST['received_date']) : date('Y-m-d');
    $receipt_notes = isset($_POST['receipt_notes']) ? trim($_POST['receipt_notes']) : ''; // Optional notes for this receipt batch

    if ($posted_po_id_receipt <= 0) {
        $_SESSION['message'] = "Invalid PO ID for goods receipt.";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $base_module_url);
        exit;
    }

    // Fetch PO items to validate against
    $po_items_db = [];
    $sql_fetch_po_items = "SELECT id, product_id, quantity_ordered, quantity_received FROM purchase_items WHERE purchase_id = " . $posted_po_id_receipt;
    $res_fetch_po_items = mysqli_query($conn, $sql_fetch_po_items);
    if ($res_fetch_po_items) {
        while ($row = mysqli_fetch_assoc($res_fetch_po_items)) {
            $po_items_db[$row['id']] = $row; // Key by purchase_item_id
        }
        mysqli_free_result($res_fetch_po_items);
    } else {
        $_SESSION['message'] = "Error fetching PO items for validation: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
        header("Location: " . $base_module_url . "&sub_action=receive_goods&id=" . $posted_po_id_receipt);
        exit;
    }

    if (empty($po_items_db)) {
         $_SESSION['message'] = "No items found for this PO to receive.";
         $_SESSION['message_type'] = "warning";
         header("Location: " . $base_module_url . "&sub_action=view_po&id=" . $posted_po_id_receipt);
         exit;
    }

    $all_items_fully_received = true;
    $at_least_one_item_received_this_batch = false;

    mysqli_begin_transaction($conn);
    $success = true;

    foreach ($received_quantities as $purchase_item_id => $qty_received_input) {
        $qty_received_this_time = (int)$qty_received_input;
        if ($qty_received_this_time < 0) $qty_received_this_time = 0; // Cannot receive negative

        if (!isset($po_items_db[$purchase_item_id])) {
            $_SESSION['message'] = "Invalid item ID (" . $purchase_item_id . ") in received quantities.";
            $success = false; break;
        }

        $item = $po_items_db[$purchase_item_id];
        $total_already_received = (int)$item['quantity_received'];
        $quantity_ordered = (int)$item['quantity_ordered'];

        if ($qty_received_this_time > ($quantity_ordered - $total_already_received)) {
            $_SESSION['message'] = "Cannot receive " . $qty_received_this_time . " for item ID " . $purchase_item_id . ". Max receivable: " . ($quantity_ordered - $total_already_received);
            $success = false; break;
        }

        if ($qty_received_this_time > 0) {
            $at_least_one_item_received_this_batch = true;
            $new_total_received_for_item = $total_already_received + $qty_received_this_time;

            // Update purchase_items.quantity_received
            $sql_update_pi = "UPDATE purchase_items SET quantity_received = " . $new_total_received_for_item . " WHERE id = " . $purchase_item_id;
            if (!mysqli_query($conn, $sql_update_pi)) {
                $_SESSION['message'] = "Error updating received quantity for item " . $purchase_item_id . ": " . mysqli_error($conn);
                $success = false; break;
            }

            // Update products.current_stock
            $sql_update_stock = "UPDATE products SET current_stock = current_stock + " . $qty_received_this_time . " WHERE id = " . $item['product_id'];
            if (!mysqli_query($conn, $sql_update_stock)) {
                $_SESSION['message'] = "Error updating stock for product ID " . $item['product_id'] . ": " . mysqli_error($conn);
                $success = false; break;
            }

            // Log this specific stock movement (optional, if a more granular log than stock_adjustments is needed for PO receipts)
            // Stock adjustments table is for manual adjustments. PO receipts update stock directly.
            // A separate goods_receipt_log table could be useful for complex scenarios.
        }
    }

    if ($success && !$at_least_one_item_received_this_batch) {
        // No quantities were entered, but not an error per se.
        $_SESSION['message'] = "No quantities were entered for receiving.";
        $_SESSION['message_type'] = "info";
        mysqli_rollback($conn); // No changes made, so rollback is fine.
        header("Location: " . $base_module_url . "&sub_action=receive_goods&id=" . $posted_po_id_receipt);
        exit;
    }

    if ($success) {
        // Check if all items are now fully received to update PO status
        $total_ordered_qty_overall = 0;
        $total_received_qty_overall = 0;
        $sql_check_all_received = "SELECT quantity_ordered, quantity_received FROM purchase_items WHERE purchase_id = " . $posted_po_id_receipt;
        $res_check_all = mysqli_query($conn, $sql_check_all_received);
        if($res_check_all){
            while($item_status = mysqli_fetch_assoc($res_check_all)){
                $total_ordered_qty_overall += $item_status['quantity_ordered'];
                $total_received_qty_overall += $item_status['quantity_received'];
            }
            mysqli_free_result($res_check_all);
        } else {
            $_SESSION['message'] = "Error checking overall PO receive status: " . mysqli_error($conn);
            $success = false; // Treat as error
        }

        if ($success) { // Re-check success after fetching overall status
            $new_po_status = 'partially_received';
            if ($total_received_qty_overall >= $total_ordered_qty_overall) {
                $new_po_status = 'received';
            } elseif ($total_received_qty_overall == 0 && !$at_least_one_item_received_this_batch) {
                // If nothing was received ever for this PO, status might remain 'ordered'
                // But if items were received in this batch, it must be at least 'partially_received'
                // This logic depends on if we allow receiving 0 items. The current check for $at_least_one_item_received_this_batch handles this.
                 $current_po_status_sql = "SELECT status FROM purchases WHERE id = " . $posted_po_id_receipt;
                 $res_curr_status = mysqli_query($conn, $current_po_status_sql);
                 $current_po_status_db = mysqli_fetch_assoc($res_curr_status)['status'];
                 mysqli_free_result($res_curr_status);
                 $new_po_status = $current_po_status_db; // Keep current status if nothing received in this batch
                 if ($at_least_one_item_received_this_batch && $total_received_qty_overall > 0) {
                     $new_po_status = 'partially_received'; // It has to be at least this if something was received
                 }
            }


            // Update PO status and received_date (only if it's the first receipt for this PO or all items received)
            $sql_update_po = "UPDATE purchases SET status = '" . $new_po_status . "', ";
            // Set received_date to the current batch's date. If partially received before, this updates it.
            // Or, only set received_date if it's NULL (first receipt). This depends on desired logic.
            // For now, always update to the date of the latest receipt batch.
            $sql_update_po .= "received_date = '" . escape_string($conn, $received_date) . "', updated_at = CURRENT_TIMESTAMP
                               WHERE id = " . $posted_po_id_receipt;

            if (!mysqli_query($conn, $sql_update_po)) {
                $_SESSION['message'] = "Error updating PO status/date: " . mysqli_error($conn);
                $success = false;
            }
        }
    }

    if ($success) {
        mysqli_commit($conn);
        $_SESSION['message'] = "Goods received successfully for PO #" . $posted_po_id_receipt . ".";
        $_SESSION['message_type'] = "success";
        header("Location: " . $base_module_url . "&sub_action=view_po&id=" . $posted_po_id_receipt);
        exit;
    } else {
        mysqli_rollback($conn);
        // $_SESSION['message'] is already set with the error
        $_SESSION['message_type'] = "danger";
        header("Location: " . $base_module_url . "&sub_action=receive_goods&id=" . $posted_po_id_receipt);
        exit;
    }

} else {
    // Default to list if action is unknown
    $_SESSION['message'] = "Unknown purchase action. Displaying list.";
    $_SESSION['message_type'] = "info";
    // Fall through to list_po by default if $page_action was reset or unknown.
    // This requires $purchase_orders to be populated or handled in po_list.php
    $purchase_orders = []; // Ensure it's defined
    $sql_list_fallback = "SELECT p.*, s.name as supplier_name FROM purchases p JOIN suppliers s ON p.supplier_id = s.id ORDER BY p.purchase_date DESC, p.id DESC";
    $result_list_fallback = mysqli_query($conn, $sql_list_fallback);
    if ($result_list_fallback) {
        while ($row_fallback = mysqli_fetch_assoc($result_list_fallback)) $purchase_orders[] = $row_fallback;
        mysqli_free_result($result_list_fallback);
    }
    require_once BASE_PATH . '/templates/purchases/po_list.php';

} elseif ($page_action === 'purchase_return') { // Main entry for purchase returns
    $pr_sub_action = $_GET['pr_sub_action'] ?? 'find_po_for_return';
    $original_po_for_return = null;
    $returnable_po_items = [];

    if ($pr_sub_action === 'find_po_for_return' || ($pr_sub_action === 'show_return_form' && $_SERVER['REQUEST_METHOD'] === 'GET')) {
        $po_number_to_find = trim($_REQUEST['po_number_return'] ?? '');
        if (!empty($po_number_to_find)) {
            $escaped_po_number = escape_string($conn, $po_number_to_find);
            $sql_find_po = "SELECT p.*, s.name as supplier_name
                            FROM purchases p
                            JOIN suppliers s ON p.supplier_id = s.id
                            WHERE p.po_number = '" . $escaped_po_number . "' AND (p.status = 'received' OR p.status = 'partially_received' OR p.status = 'ordered')"; // Can only return from received/partially or ordered (if stock was adjusted manually for some reason)
            $res_find_po = mysqli_query($conn, $sql_find_po);
            if ($res_find_po && mysqli_num_rows($res_find_po) > 0) {
                $original_po_for_return = mysqli_fetch_assoc($res_find_po);
                mysqli_free_result($res_find_po);

                // Fetch PO items that have been received (or ordered if status is 'ordered' - assuming some stock might have been added)
                $sql_po_items = "SELECT pi.*, pr.name as product_name, pr.sku as product_sku,
                                        (SELECT SUM(pri.quantity_returned)
                                         FROM purchase_return_items pri
                                         JOIN purchase_returns prtn ON pri.purchase_return_id = prtn.id
                                         WHERE prtn.original_purchase_id = pi.purchase_id AND pri.original_purchase_item_id = pi.id) as total_already_returned
                                 FROM purchase_items pi
                                 JOIN products pr ON pi.product_id = pr.id
                                 WHERE pi.purchase_id = " . $original_po_for_return['id'] . " AND pi.quantity_received > 0"; // Only items with some received quantity
                $res_po_items = mysqli_query($conn, $sql_po_items);
                if ($res_po_items) {
                    while ($item_row = mysqli_fetch_assoc($res_po_items)) {
                        $item_row['total_already_returned'] = $item_row['total_already_returned'] ?? 0;
                        $item_row['quantity_returnable_from_received'] = $item_row['quantity_received'] - $item_row['total_already_returned'];
                        if ($item_row['quantity_returnable_from_received'] > 0) {
                            $returnable_po_items[] = $item_row;
                        }
                    }
                    mysqli_free_result($res_po_items);
                }
                if (empty($returnable_po_items)) {
                     $_SESSION['message'] = "No items with received quantity are available for return for PO " . htmlspecialchars($po_number_to_find) . ".";
                     $_SESSION['message_type'] = "info";
                     $original_po_for_return = null;
                } else {
                    $pr_sub_action = 'show_return_form'; // Update action to show the form
                }
            } else {
                $_SESSION['message'] = "Original PO with number '" . htmlspecialchars($po_number_to_find) . "' not found or not in a returnable status.";
                $_SESSION['message_type'] = "danger";
            }
        } elseif (isset($_REQUEST['po_number_return'])) {
            $_SESSION['message'] = "Please enter a PO number to find.";
            $_SESSION['message_type'] = "warning";
        }
    }
    require_once BASE_PATH . '/templates/purchases/purchase_return_form.php'; // This template handles both find and display form
    exit;

} elseif ($page_action === 'process_purchase_return' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $original_purchase_id_ret = isset($_POST['original_purchase_id_ret']) ? (int)$_POST['original_purchase_id_ret'] : 0;
    $return_quantities_pr = $_POST['return_quantity_pr'] ?? []; // Array like [purchase_item_id => qty_to_return]
    $overall_reason_pr = trim($_POST['overall_reason_pr'] ?? 'N/A');
    $user_id_pr = $_SESSION['user_id'];
    $original_po_number_hidden = $_POST['original_po_number_hidden'] ?? '';

    $form_errors_pr = [];
    if ($original_purchase_id_ret <= 0) $form_errors_pr[] = "Invalid original PO ID.";
    if (empty($return_quantities_pr)) $form_errors_pr[] = "No items selected for return.";

    $items_to_return_for_db_pr = [];
    $total_return_value_calc = 0;
    $at_least_one_item_valid_pr = false;

    // Fetch original PO items again for validation
    $sql_orig_po_items_val = "SELECT pi.id as purchase_item_id, pi.product_id, pi.quantity_received, pi.purchase_price_per_item,
                                    (SELECT SUM(pri.quantity_returned)
                                     FROM purchase_return_items pri
                                     JOIN purchase_returns prtn ON pri.purchase_return_id = prtn.id
                                     WHERE prtn.original_purchase_id = pi.purchase_id AND pri.original_purchase_item_id = pi.id) as total_already_returned
                              FROM purchase_items pi WHERE pi.purchase_id = " . $original_purchase_id_ret;
    $res_orig_po_items_val = mysqli_query($conn, $sql_orig_po_items_val);
    $original_po_items_map = [];
    if ($res_orig_po_items_val) {
        while($row = mysqli_fetch_assoc($res_orig_po_items_val)) {
            $row['total_already_returned'] = $row['total_already_returned'] ?? 0;
            $original_po_items_map[$row['purchase_item_id']] = $row;
        }
        mysqli_free_result($res_orig_po_items_val);
    } else {
        $form_errors_pr[] = "Could not verify original PO items for return.";
    }

    if (empty($form_errors_pr)) {
        foreach ($return_quantities_pr as $purchase_item_id_ret => $qty_to_return_pr) {
            $qty_to_return_pr = (int)$qty_to_return_pr;
            if ($qty_to_return_pr > 0) {
                if (!isset($original_po_items_map[$purchase_item_id_ret])) {
                    $form_errors_pr[] = "Invalid PO item (ID: {$purchase_item_id_ret}) selected for return."; continue;
                }
                $orig_po_item_detail = $original_po_items_map[$purchase_item_id_ret];
                $max_returnable_pr = $orig_po_item_detail['quantity_received'] - $orig_po_item_detail['total_already_returned'];

                if ($qty_to_return_pr > $max_returnable_pr) {
                    $form_errors_pr[] = "Cannot return " . $qty_to_return_pr . " for PO item ID " . $purchase_item_id_ret . ". Max returnable from received: " . $max_returnable_pr; continue;
                }

                $at_least_one_item_valid_pr = true;
                $items_to_return_for_db_pr[] = [
                    'original_purchase_item_id' => $purchase_item_id_ret,
                    'product_id' => $orig_po_item_detail['product_id'],
                    'quantity_returned' => $qty_to_return_pr,
                    'return_price_per_item' => $orig_po_item_detail['purchase_price_per_item'], // Assuming return at original cost
                    'line_return_total' => $qty_to_return_pr * $orig_po_item_detail['purchase_price_per_item']
                ];
                $total_return_value_calc += ($qty_to_return_pr * $orig_po_item_detail['purchase_price_per_item']);
            }
        }
    }
    if (!$at_least_one_item_valid_pr && empty($form_errors_pr)) $form_errors_pr[] = "No valid items/quantities for purchase return.";


    if (empty($form_errors_pr)) {
        mysqli_begin_transaction($conn);
        $success_pr = true;
        $new_purchase_return_id = 0;

        // Fetch supplier_id from original purchase
        $sql_get_supplier = "SELECT supplier_id FROM purchases WHERE id = " . $original_purchase_id_ret;
        $res_get_supplier = mysqli_query($conn, $sql_get_supplier);
        $supplier_id_for_return = null;
        if($res_get_supplier && mysqli_num_rows($res_get_supplier) > 0){
            $supplier_id_for_return = mysqli_fetch_assoc($res_get_supplier)['supplier_id'];
        } else {
            $success_pr = false; $_SESSION['message'] = "Could not find supplier for original PO.";
        }
        mysqli_free_result($res_get_supplier);


        if($success_pr && $supplier_id_for_return) {
            $return_note_no_pr = 'PRTN-' . time() . '-' . rand(100,999);
            $sql_insert_pr_header = "INSERT INTO purchase_returns (original_purchase_id, return_note_no, supplier_id, user_id, return_date, total_return_amount, reason) VALUES (
                                            " . $original_purchase_id_ret . ", '" . $return_note_no_pr . "', " . $supplier_id_for_return . ", " . $user_id_pr . ",
                                            CURRENT_TIMESTAMP, " . $total_return_value_calc . ", '" . escape_string($conn, $overall_reason_pr) . "' )";
            if (mysqli_query($conn, $sql_insert_pr_header)) {
                $new_purchase_return_id = mysqli_insert_id($conn);
            } else {
                $success_pr = false; $_SESSION['message'] = "Error creating purchase return record: " . mysqli_error($conn);
            }

            if ($success_pr) {
                foreach ($items_to_return_for_db_pr as $item_pr_ret) {
                    $sql_insert_pr_item = "INSERT INTO purchase_return_items (purchase_return_id, product_id, original_purchase_item_id, quantity_returned, return_price_per_item, line_return_total) VALUES (
                                                    " . $new_purchase_return_id . ", " . $item_pr_ret['product_id'] . ", " . $item_pr_ret['original_purchase_item_id'] . ",
                                                    " . $item_pr_ret['quantity_returned'] . ", " . $item_pr_ret['return_price_per_item'] . ", " . $item_pr_ret['line_return_total'] . ")";
                    if (!mysqli_query($conn, $sql_insert_pr_item)) {
                        $success_pr = false; $_SESSION['message'] = "Error saving returned purchase item details: " . mysqli_error($conn); break;
                    }

                    // Update product stock (decrease)
                    $sql_update_stock_pr = "UPDATE products SET current_stock = current_stock - " . $item_pr_ret['quantity_returned'] . " WHERE id = " . $item_pr_ret['product_id'];
                    // Add check here if stock would go negative (shouldn't if returning from received stock)
                    if (!mysqli_query($conn, $sql_update_stock_pr)) {
                        $success_pr = false; $_SESSION['message'] = "Error updating stock for returned product ID " . $item_pr_ret['product_id'] . ": " . mysqli_error($conn); break;
                    }
                }
            }
            // Note: Updating original PO status after a purchase return is complex.
            // Does it revert 'received' qty? Or just a financial adjustment?
            // For now, not changing original PO status, only logging the return and adjusting stock.
        }


        if ($success_pr) {
            mysqli_commit($conn);
            $_SESSION['message'] = "Purchase return processed successfully! Return Note No: " . $return_note_no_pr;
            $_SESSION['message_type'] = "success";
            header("Location: " . $base_module_url . "&sub_action=purchase_return&pr_sub_action=find_po_for_return"); // Back to find PO for return
            exit;
        } else {
            mysqli_rollback($conn);
            $_SESSION['message_type'] = "danger"; // Error message is already set
            header("Location: " . $base_module_url . "&sub_action=purchase_return&pr_sub_action=show_return_form&po_number_return=" . urlencode($original_po_number_hidden) . "&error=processing_pr");
            exit;
        }
    } else { // Validation errors for purchase return form
        $_SESSION['message'] = "Purchase return failed. Errors: " . implode(" ", $form_errors_pr);
        $_SESSION['message_type'] = "danger";
        header("Location: " . $base_module_url . "&sub_action=purchase_return&pr_sub_action=show_return_form&po_number_return=" . urlencode($original_po_number_hidden) . "&validation_errors_pr=true");
        exit;
    }
}


// mysqli_close($conn); // Closed by index.php
?>
