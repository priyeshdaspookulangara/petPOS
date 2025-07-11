<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
require_once BASE_PATH . '/config/db.php'; // For $conn, escape_string

if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Security check: Cashiers and Admins can access POS
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'cashier'])) {
    $_SESSION['message'] = "Access denied. You do not have permission to access the POS.";
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
$base_module_url .= $script_dir_path . "/index.php?module=pos"; // Base for POS actions

// Actions within POS module (e.g., product search, process sale) will be handled here or via AJAX endpoints.
$sub_action = isset($_GET['sub_action']) ? $_GET['sub_action'] : 'display_interface';

// Fetch necessary settings for POS (tax rate, currency symbol)
$pos_settings = [];
$settings_keys_needed = ['tax_rate_percentage', 'currency_symbol', 'store_name', 'receipt_footer_message', 'store_logo_url', 'store_address', 'store_phone'];
$sql_settings = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('" . implode("','", $settings_keys_needed) . "')";
$res_settings = mysqli_query($conn, $sql_settings);
if ($res_settings) {
    while($row = mysqli_fetch_assoc($res_settings)) {
        $pos_settings[$row['setting_key']] = $row['setting_value'];
    }
    mysqli_free_result($res_settings);
}
// Set defaults if not found in DB
$pos_settings['tax_rate_percentage'] = isset($pos_settings['tax_rate_percentage']) ? floatval($pos_settings['tax_rate_percentage']) : 0.0;
$pos_settings['currency_symbol'] = $pos_settings['currency_symbol'] ?? '$';
$pos_settings['store_name'] = $pos_settings['store_name'] ?? 'POS System';
$pos_settings['receipt_footer_message'] = $pos_settings['receipt_footer_message'] ?? 'Thank you for your business!';
$pos_settings['store_logo_url'] = $pos_settings['store_logo_url'] ?? ''; // Default to no logo if not set
$pos_settings['store_address'] = $pos_settings['store_address'] ?? '';
$pos_settings['store_phone'] = $pos_settings['store_phone'] ?? '';


// AJAX request handling
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');

    if ($sub_action === 'search_product') {
        $query = isset($_GET['query']) ? trim(escape_string($conn, $_GET['query'])) : '';
        $products_found = [];
        if (!empty($query)) {
            // Search by name, SKU, or barcode
            $sql_search = "SELECT id, name, sku, barcode, selling_price, current_stock, image_url
                           FROM products
                           WHERE (name LIKE '%" . $query . "%' OR sku LIKE '%" . $query . "%' OR barcode LIKE '%" . $query . "%')
                           AND current_stock > 0
                           LIMIT 10"; // Limit results for performance
            $res_search = mysqli_query($conn, $sql_search);
            if ($res_search) {
                while ($row = mysqli_fetch_assoc($res_search)) {
                    $products_found[] = $row;
                }
                mysqli_free_result($res_search);
            } else {
                // Log error: mysqli_error($conn)
            }
        }
        echo json_encode(['success' => true, 'products' => $products_found]);
        exit;
    }
    // Add more AJAX actions here (e.g. get_customer, process_sale)

    echo json_encode(['success' => false, 'message' => 'Invalid AJAX action.']);
    exit;
}


// Standard page display
if ($sub_action === 'display_interface') {
    // Load the main POS interface template
    // $pos_settings will be available in the template
    require_once BASE_PATH . '/templates/pos/pos_interface.php';
} elseif ($sub_action === 'process_sale') {
    // This will handle the full form submission from the POS interface
    // For now, it's a placeholder. Actual logic will involve:
    // 1. Validating cart items, customer info (if any), payment.
    // 2. Inserting into `sales` and `sale_items` tables.
    // 3. Decrementing `products.current_stock`.
    // 4. Generating receipt_no.
    // 5. Returning success/failure message or receipt data.

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Ensure this is not an AJAX request for processing (though it could be)
        // For now, assume a full page POST or an AJAX call that expects JSON

        $cart_items_json = $_POST['cart_items'] ?? '[]';
        $customer_id = $_POST['customer_id'] ?? null; // Optional
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $discount_total = isset($_POST['discount_total']) ? floatval($_POST['discount_total']) : 0.00;
        $notes = $_POST['notes'] ?? '';

        $cart_items = json_decode($cart_items_json, true);

        if (empty($cart_items)) {
            echo json_encode(['success' => false, 'message' => 'Cart is empty. Cannot process sale.']);
            exit;
        }

        // Begin transaction (if your MySQL version/setup supports it and it's deemed necessary)
        // mysqli_begin_transaction($conn);

        $user_id = $_SESSION['user_id'];
        $sale_date = date('Y-m-d H:i:s');
        $receipt_no = 'SALE-' . time() . '-' . rand(100,999); // Simple unique enough receipt number

        $sub_total_calc = 0;
        $total_items_price_after_indiv_discount = 0;

        // Calculate sub_total and check stock availability
        $item_details_for_db = [];
        foreach ($cart_items as $item) {
            $product_id_cart = (int)$item['id'];
            $quantity_cart = (int)$item['quantity'];

            $sql_prod_check = "SELECT selling_price, current_stock FROM products WHERE id = $product_id_cart";
            $res_prod_check = mysqli_query($conn, $sql_prod_check);
            if ($res_prod_check && mysqli_num_rows($res_prod_check) > 0) {
                $product_db = mysqli_fetch_assoc($res_prod_check);
                mysqli_free_result($res_prod_check);

                if ($product_db['current_stock'] < $quantity_cart) {
                    // mysqli_rollback($conn); // Rollback if transaction started
                    echo json_encode(['success' => false, 'message' => 'Insufficient stock for product: ' . htmlspecialchars($item['name']) . '. Available: ' . $product_db['current_stock']]);
                    exit;
                }
                // Use price from DB, not from cart, for security/accuracy
                $price_per_item_db = floatval($product_db['selling_price']);
                $item_discount_cart = isset($item['discount']) ? floatval($item['discount']) : 0.00; // Assuming item discount is a fixed amount per item for now

                $price_after_item_discount = $price_per_item_db - $item_discount_cart;
                if ($price_after_item_discount < 0) $price_after_item_discount = 0; // Cannot be negative

                $line_total_calc = $price_after_item_discount * $quantity_cart;
                $total_items_price_after_indiv_discount += $line_total_calc; // This is the sum of (price-item_discount)*qty

                $item_details_for_db[] = [
                    'product_id' => $product_id_cart,
                    'quantity' => $quantity_cart,
                    'original_price_per_item' => $price_per_item_db,
                    'discount_per_item' => $item_discount_cart,
                    'price_per_item_after_discount' => $price_after_item_discount,
                    'line_total' => $line_total_calc
                ];

            } else {
                // mysqli_rollback($conn);
                echo json_encode(['success' => false, 'message' => 'Product not found or invalid: ' . htmlspecialchars($item['name'])]);
                exit;
            }
        }

        // Sub_total is sum of (original_price * quantity) for all items BEFORE overall discount
        // OR sub_total could be sum of (price_after_item_discount * quantity) if item discounts are applied before overall discount.
        // Let's assume $total_items_price_after_indiv_discount is our subtotal before overall sale discount.
        $sub_total_final = $total_items_price_after_indiv_discount;

        // Apply overall sale discount
        $grand_total_before_tax = $sub_total_final - $discount_total; // $discount_total is overall discount amount
        if ($grand_total_before_tax < 0) $grand_total_before_tax = 0; // Cannot be negative

        // Calculate tax
        $tax_rate = $pos_settings['tax_rate_percentage'] / 100;
        $tax_amount_calc = $grand_total_before_tax * $tax_rate;
        $grand_total_final = $grand_total_before_tax + $tax_amount_calc;

        // Insert into `sales` table
        $customer_id_sql = $customer_id ? (int)$customer_id : "NULL";
        $notes_sql = escape_string($conn, $notes);

        $sql_insert_sale = "INSERT INTO sales (receipt_no, user_id, customer_id, sale_date, sub_total, discount_amount, tax_percentage, tax_amount, grand_total, payment_method, status, notes) VALUES (
                                '" . $receipt_no . "',
                                " . $user_id . ",
                                " . $customer_id_sql . ",
                                '" . $sale_date . "',
                                " . $sub_total_final . ",
                                " . $discount_total . ",
                                " . $pos_settings['tax_rate_percentage'] . ",
                                " . round($tax_amount_calc, 2) . ",
                                " . round($grand_total_final, 2) . ",
                                '" . escape_string($conn, $payment_method) . "',
                                'completed',
                                '" . $notes_sql . "'
                            )";

        if (mysqli_query($conn, $sql_insert_sale)) {
            $sale_id = mysqli_insert_id($conn);

            // Insert into `sale_items` and update stock
            foreach ($item_details_for_db as $item_db) {
                $sql_insert_item = "INSERT INTO sale_items (sale_id, product_id, quantity, original_price_per_item, discount_per_item, price_per_item_after_discount, line_total) VALUES (
                                        " . $sale_id . ",
                                        " . $item_db['product_id'] . ",
                                        " . $item_db['quantity'] . ",
                                        " . $item_db['original_price_per_item'] . ",
                                        " . $item_db['discount_per_item'] . ",
                                        " . $item_db['price_per_item_after_discount'] . ",
                                        " . $item_db['line_total'] . "
                                    )";
                if (!mysqli_query($conn, $sql_insert_item)) {
                    // mysqli_rollback($conn);
                    // Log error: mysqli_error($conn)
                    echo json_encode(['success' => false, 'message' => 'Error saving sale item. Sale rolled back.']);
                    exit;
                }

                // Update stock
                $sql_update_stock = "UPDATE products SET current_stock = current_stock - " . $item_db['quantity'] . " WHERE id = " . $item_db['product_id'];
                if (!mysqli_query($conn, $sql_update_stock)) {
                    // mysqli_rollback($conn);
                    // Log error: mysqli_error($conn)
                    echo json_encode(['success' => false, 'message' => 'Error updating stock. Sale rolled back.']);
                    exit;
                }
            }

            // mysqli_commit($conn); // Commit transaction
            echo json_encode([
                'success' => true,
                'message' => 'Sale processed successfully!',
                'receipt_no' => $receipt_no,
                'sale_id' => $sale_id,
                'grand_total' => round($grand_total_final, 2)
            ]);
            exit;

        } else {
            // mysqli_rollback($conn);
            // Log error: mysqli_error($conn)
            echo json_encode(['success' => false, 'message' => 'Error saving sale. ' . mysqli_error($conn)]);
            exit;
        }
    } else {
        // Not a POST request to process_sale
        $_SESSION['message'] = "Invalid request to process sale.";
        $_SESSION['message_type'] = "warning";
        header("Location: " . $base_module_url);
        exit;
    }

} else {
    // Unknown sub_action
    $_SESSION['message'] = "Unknown POS action.";
    $_SESSION['message_type'] = "warning";
    header("Location: " . $base_module_url);
    exit;
}

// mysqli_close($conn); // Closed by index.php
?>
