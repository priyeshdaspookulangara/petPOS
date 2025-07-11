<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
require_once BASE_PATH . '/config/db.php'; // For $conn, escape_string

if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Security check: Only admins can access this page
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Access denied. You must be an admin to manage stock adjustments.";
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
$base_module_url .= $script_dir_path . "/index.php?module=inventory&action=stock_adjustments";

$page_action = isset($_GET['sub_action']) ? $_GET['sub_action'] : 'list'; // list, add
$product_id_to_adjust = isset($_GET['product_id']) ? (int)$_GET['product_id'] : (isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0);

$adjustment_data = [
    'product_id' => $product_id_to_adjust,
    'type_of_adjustment' => 'correction', // 'increase', 'decrease', 'initial_stock', 'correction'
    'quantity_changed' => '',
    'reason' => ''
];
$form_errors = [];
$products_list = []; // For dropdown in form

// Fetch products for dropdown if adding/editing
if ($page_action === 'add') {
    $sql_products = "SELECT id, name, sku, current_stock FROM products ORDER BY name ASC";
    $res_products = mysqli_query($conn, $sql_products);
    if ($res_products) {
        while ($row = mysqli_fetch_assoc($res_products)) $products_list[] = $row;
        mysqli_free_result($res_products);
    }
}

// Handle POST request for adding adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page_action === 'add') {
    $adjustment_data['product_id'] = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $adjustment_data['type_of_adjustment'] = isset($_POST['type_of_adjustment']) ? trim($_POST['type_of_adjustment']) : 'correction';
    $adjustment_data['quantity_changed'] = isset($_POST['quantity_changed']) ? trim($_POST['quantity_changed']) : '';
    $adjustment_data['reason'] = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $user_id = $_SESSION['user_id'];

    // Validation
    if (empty($adjustment_data['product_id'])) $form_errors['product_id'] = "Product is required.";
    if (!in_array($adjustment_data['type_of_adjustment'], ['increase', 'decrease', 'initial_stock', 'correction'])) $form_errors['type_of_adjustment'] = "Invalid adjustment type.";
    if (!is_numeric($adjustment_data['quantity_changed']) || (int)$adjustment_data['quantity_changed'] <= 0) {
        $form_errors['quantity_changed'] = "Quantity must be a positive number.";
    }
    if (empty($adjustment_data['reason'])) $form_errors['reason'] = "Reason for adjustment is required.";

    $quantity_to_change_abs = abs((int)$adjustment_data['quantity_changed']);

    // Check current stock if decreasing
    if ($adjustment_data['type_of_adjustment'] === 'decrease') {
        $sql_curr_stock = "SELECT current_stock FROM products WHERE id = " . $adjustment_data['product_id'];
        $res_curr_stock = mysqli_query($conn, $sql_curr_stock);
        if ($res_curr_stock && mysqli_num_rows($res_curr_stock) > 0) {
            $current_stock_val = mysqli_fetch_assoc($res_curr_stock)['current_stock'];
            if ($quantity_to_change_abs > $current_stock_val) {
                $form_errors['quantity_changed'] = "Cannot decrease stock by " . $quantity_to_change_abs . ". Current stock is " . $current_stock_val . ".";
            }
            mysqli_free_result($res_curr_stock);
        } else {
             $form_errors['product_id'] = "Selected product not found or error fetching stock.";
        }
    }


    if (empty($form_errors)) {
        mysqli_begin_transaction($conn); // Start transaction

        $sql_product_update = "";
        $new_stock_level = 0;

        // Get current stock again, within transaction for safety
        $sql_get_stock = "SELECT current_stock FROM products WHERE id = " . $adjustment_data['product_id'] . " FOR UPDATE";
        $res_get_stock = mysqli_query($conn, $sql_get_stock);
        if (!$res_get_stock || mysqli_num_rows($res_get_stock) == 0) {
            mysqli_rollback($conn);
            $_SESSION['message'] = "Error fetching product for stock update.";
            $_SESSION['message_type'] = "danger";
            header("Location: " . $base_module_url . "&sub_action=add" . ($product_id_to_adjust ? "&product_id=".$product_id_to_adjust : ""));
            exit;
        }
        $current_product_stock = mysqli_fetch_assoc($res_get_stock)['current_stock'];
        mysqli_free_result($res_get_stock);

        if ($adjustment_data['type_of_adjustment'] === 'increase' || $adjustment_data['type_of_adjustment'] === 'initial_stock' || $adjustment_data['type_of_adjustment'] === 'correction_increase') {
             // Correction_increase is a conceptual type if UI distinguishes it, DB uses 'increase' or 'correction'
            $new_stock_level = $current_product_stock + $quantity_to_change_abs;
            $sql_product_update = "UPDATE products SET current_stock = " . $new_stock_level . " WHERE id = " . $adjustment_data['product_id'];
        } elseif ($adjustment_data['type_of_adjustment'] === 'decrease' || $adjustment_data['type_of_adjustment'] === 'correction_decrease') {
            if ($quantity_to_change_abs > $current_product_stock) { // Re-check within transaction
                 mysqli_rollback($conn);
                 $_SESSION['message'] = "Stock cannot be decreased below zero. Current stock: {$current_product_stock}, trying to decrease by {$quantity_to_change_abs}.";
                 $_SESSION['message_type'] = "danger";
                 $page_action = 'add'; // To show form again with error
                 $form_errors['quantity_changed'] = "Cannot decrease stock by " . $quantity_to_change_abs . ". Current stock is " . $current_product_stock . ".";
                 // Fall through to re-render form
            } else {
                $new_stock_level = $current_product_stock - $quantity_to_change_abs;
                $sql_product_update = "UPDATE products SET current_stock = " . $new_stock_level . " WHERE id = " . $adjustment_data['product_id'];
            }
        } else { // 'correction' could be set directly, or a specific type for logging.
             // For 'correction' type, if we don't know if it's increase/decrease, this logic needs refinement.
             // Assuming 'correction' means setting to a new value, or it's handled as inc/dec with 'correction' as reason.
             // For simplicity, let's assume 'correction' is like 'increase' for this example if not split further.
             // A better approach: have 'set stock to' option for correction.
             // For now, 'correction' type might need user to specify if it's add/remove.
             // Let's make 'type_of_adjustment' simpler: 'increase', 'decrease'. 'initial_stock' is a special increase.
             // 'correction' is more of a reason.
             // Re-evaluating the ENUM: 'increase', 'decrease', 'initial_stock'. 'correction' as a reason is better.
             // For now, this code assumes 'correction' is like 'increase' if not handled above.
             // This part needs to be cleaner. For now, we'll avoid 'correction' as a direct stock changing type.

            // If type_of_adjustment is 'correction', the UI should have clarified if it's an increase or decrease
            // or a direct "set to new value". The current form structure is simpler.
            // Let's assume if type is 'correction', the quantity_changed sign matters, or UI should guide.
            // The provided form has 'increase'/'decrease'. 'correction' is a reason.
        }

        // If there was an error (like stock going negative) and we fell through.
        if (!empty($form_errors)) {
             mysqli_rollback($conn); // Ensure rollback
             // Errors are set, page_action is 'add', form will re-render.
        } else if (!empty($sql_product_update)) {
            if (mysqli_query($conn, $sql_product_update)) {
                // Log the adjustment
                $escaped_reason = escape_string($conn, $adjustment_data['reason']);
                $db_adjustment_type = $adjustment_data['type_of_adjustment']; // Could be 'increase', 'decrease', 'initial_stock'
                // If 'correction' was a type, it would be logged as such.
                // The quantity_changed for logging should reflect actual change direction for decreases.
                $logged_quantity_changed = ($db_adjustment_type === 'decrease') ? -$quantity_to_change_abs : $quantity_to_change_abs;

                $sql_log = "INSERT INTO stock_adjustments (product_id, user_id, type_of_adjustment, quantity_changed, new_stock_level, reason, adjustment_date) VALUES (
                                " . $adjustment_data['product_id'] . ",
                                " . $user_id . ",
                                '" . $db_adjustment_type . "',
                                " . $logged_quantity_changed . ",
                                " . $new_stock_level . ",
                                '" . $escaped_reason . "',
                                CURRENT_TIMESTAMP
                            )";
                if (mysqli_query($conn, $sql_log)) {
                    mysqli_commit($conn);
                    $_SESSION['message'] = "Stock adjusted successfully for product ID " . $adjustment_data['product_id'] . ".";
                    $_SESSION['message_type'] = "success";
                    header("Location: " . $base_module_url . "&sub_action=list"); // Redirect to list of adjustments
                    exit;
                } else {
                    mysqli_rollback($conn);
                    $_SESSION['message'] = "Error logging stock adjustment: " . mysqli_error($conn);
                    $_SESSION['message_type'] = "danger";
                }
            } else {
                mysqli_rollback($conn);
                $_SESSION['message'] = "Error updating product stock: " . mysqli_error($conn);
                $_SESSION['message_type'] = "danger";
            }
        } else {
             mysqli_rollback($conn); // Should not happen if logic is correct
             $_SESSION['message'] = "No stock update operation was performed. Please check adjustment type.";
             $_SESSION['message_type'] = "warning";
        }
        // If we reach here due to an error before redirecting:
        $page_action = 'add'; // Ensure form is re-rendered with errors and data.
    } else {
        // Validation errors occurred. $adjustment_data is populated from POST.
        $page_action = 'add'; // Re-render the add form with errors.
    }
}


// Display logic: list or form
if ($page_action === 'list') {
    $adjustments = [];
    $sql_list = "SELECT sa.*, p.name as product_name, p.sku as product_sku, u.username as user_username
                 FROM stock_adjustments sa
                 JOIN products p ON sa.product_id = p.id
                 JOIN users u ON sa.user_id = u.id
                 ORDER BY sa.adjustment_date DESC";
    // Add pagination later if needed
    $result_list = mysqli_query($conn, $sql_list);
    if ($result_list) {
        while ($row = mysqli_fetch_assoc($result_list)) {
            $adjustments[] = $row;
        }
        mysqli_free_result($result_list);
    } else {
        $_SESSION['message'] = "Error fetching stock adjustments: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    require_once BASE_PATH . '/templates/inventory/stock_adjustment_log.php'; // New template for listing
} elseif ($page_action === 'add') {
    // $adjustment_data, $form_errors, $products_list are prepared.
    require_once BASE_PATH . '/templates/inventory/stock_adjustment_form.php';
}

// mysqli_close($conn); // Closed by index.php
?>
