<?php
header('Content-Type: application/json'); // Set content type to JSON for AJAX response

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

// Ensure user is logged in to process sales
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please login.']);
    exit;
}

// Check if it's an AJAX POST request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    // If not AJAX, or not POST, deny direct access or show error
    // For now, just exit or return an error.
    // In a real app, might redirect or show a 403/405 error page.
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$current_user_id = get_current_user_id();
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Get the raw POST data
$rawData = file_get_contents("php://input");
$saleData = json_decode($rawData, true);

if (!$saleData || !isset($saleData['cart']) || empty($saleData['cart'])) {
    $response['message'] = 'Invalid sale data or empty cart.';
    echo json_encode($response);
    exit;
}

// Sanitize top-level data (cart items will be sanitized within loop)
$cart_items_from_client = $saleData['cart'];
$subtotal_from_client = isset($saleData['subtotal']) ? (float)$saleData['subtotal'] : 0;
$discount_total_from_client = isset($saleData['discount_total']) ? (float)$saleData['discount_total'] : 0;
$tax_rate_from_client = isset($saleData['tax_rate']) ? (float)$saleData['tax_rate'] : 0; // This is the percentage
$tax_amount_from_client = isset($saleData['tax_amount']) ? (float)$saleData['tax_amount'] : 0;
$grand_total_from_client = isset($saleData['grand_total']) ? (float)$saleData['grand_total'] : 0;
$payment_method_from_client = isset($saleData['payment_method']) ? sanitize_input($mysqli, $saleData['payment_method']) : 'Unknown';
// $amount_tendered = isset($saleData['amount_tendered']) ? (float)$saleData['amount_tendered'] : null; // Not directly stored in sales table in this schema version
// $customer_id = isset($saleData['customer_id']) ? (int)$saleData['customer_id'] : null; // For future use


// --- Server-side validation and recalculation ---
$server_calculated_subtotal = 0;
$validated_cart_items = [];
$product_ids_in_cart = array_map(function($item){ return (int)$item['id']; }, $cart_items_from_client);

if (empty($product_ids_in_cart)) {
    $response['message'] = 'Cart contains no valid product IDs.';
    echo json_encode($response);
    exit;
}

// Fetch product details from DB for validation
$ids_string = implode(',', $product_ids_in_cart);
$sql_products_check = "SELECT id, name, selling_price, current_stock FROM products WHERE id IN ($ids_string)";
$result_products_check = $mysqli->query($sql_products_check);
$db_products = [];
if ($result_products_check) {
    while ($row = $result_products_check->fetch_assoc()) {
        $db_products[$row['id']] = $row;
    }
    $result_products_check->free();
} else {
    $response['message'] = 'Error fetching product details for validation: ' . $mysqli->error;
    echo json_encode($response);
    exit;
}

foreach ($cart_items_from_client as $client_item) {
    $product_id = (int)$client_item['id'];
    $quantity_demanded = (int)$client_item['quantity'];

    if (!isset($db_products[$product_id])) {
        $response['message'] = "Invalid product ID ($product_id) found in cart.";
        echo json_encode($response);
        exit;
    }
    $db_product = $db_products[$product_id];

    if ($quantity_demanded <= 0) {
        $response['message'] = "Invalid quantity for product: " . htmlspecialchars($db_product['name']);
        echo json_encode($response);
        exit;
    }
    if ($quantity_demanded > $db_product['current_stock']) {
        $response['message'] = "Insufficient stock for product: " . htmlspecialchars($db_product['name']) . ". Available: " . $db_product['current_stock'] . ", Demanded: $quantity_demanded";
        echo json_encode($response);
        exit;
    }

    // Use server-side price
    $price_at_sale = (float)$db_product['selling_price'];
    // $discount_per_item = isset($client_item['discount_per_item']) ? (float)$client_item['discount_per_item'] : 0; // Future enhancement
    $item_total = $quantity_demanded * $price_at_sale; // - ($quantity_demanded * $discount_per_item);

    $validated_cart_items[] = [
        'product_id' => $product_id,
        'quantity' => $quantity_demanded,
        'price_per_item' => $price_at_sale,
        // 'discount_per_item' => $discount_per_item,
        'item_total' => $item_total
    ];
    $server_calculated_subtotal += $item_total;
}

// Recalculate discount, tax, grand total based on server values
// For now, trust client discount amount but cap it. A more robust way is to apply discount rules server-side.
$server_discount_total = min($discount_total_from_client, $server_calculated_subtotal);
if ($server_discount_total < 0) $server_discount_total = 0;

$server_taxable_amount = $server_calculated_subtotal - $server_discount_total;

// Fetch tax rate from settings again on server for security
$db_tax_rate_percentage = 0.00;
$setting_tax_sql = "SELECT setting_value FROM settings WHERE setting_key = 'tax_rate_percentage'";
$tax_res = $mysqli->query($setting_tax_sql);
if($tax_res && $tax_row = $tax_res->fetch_assoc()){ $db_tax_rate_percentage = (float)$tax_row['setting_value']; }
if($tax_res) $tax_res->free();

$server_tax_amount = $server_taxable_amount * ($db_tax_rate_percentage / 100);
$server_grand_total = $server_taxable_amount + $server_tax_amount;

// Basic check if client grand total is reasonably close to server calculated one (e.g. due to rounding)
if (abs($grand_total_from_client - $server_grand_total) > 0.05) { // Allow 5 cents difference for rounding
    // $response['message'] = "Data mismatch. Client total: $grand_total_from_client, Server total: $server_grand_total. Please try again.";
    // For now, we will proceed with server calculated values for security
    // $response['message'] = "Totals recalculated by server for accuracy."; // Informative, but might be confusing
}


// --- Database Operations (Transaction) ---
$mysqli->begin_transaction();
try {
    // 1. Generate Receipt Number
    // Simple: 'RCPT-' + timestamp + random number. Needs to be unique.
    // For a robust system, a dedicated sequence or UUID might be better.
    $receipt_no = 'RCPT-' . time() . '-' . mt_rand(100, 999);
    // Check uniqueness (very unlikely collision with time(), but good practice for high volume)
    // $check_receipt_sql = "SELECT id FROM sales WHERE receipt_no = '$receipt_no'";
    // ... loop to regenerate if exists ... (omitted for brevity now)

    // 2. Insert into `sales` table
    $sql_insert_sale = "INSERT INTO sales (receipt_no, user_id, customer_id, sale_date,
                                       sub_total, discount_amount, tax_percentage, tax_amount, grand_total,
                                       payment_method, status)
                        VALUES ('$receipt_no', $current_user_id, NULL, NOW(),
                                $server_calculated_subtotal, $server_discount_total, $db_tax_rate_percentage, $server_tax_amount, $server_grand_total,
                                '$payment_method_from_client', 'Completed')";

    if (!$mysqli->query($sql_insert_sale)) {
        throw new Exception("Error saving sale: " . $mysqli->error);
    }
    $sale_id = $mysqli->insert_id;

    // 3. Insert into `sale_items` table
    foreach ($validated_cart_items as $v_item) {
        $sql_insert_sale_item = "INSERT INTO sale_items (sale_id, product_id, quantity, price_per_item, item_total)
                                 VALUES ($sale_id, {$v_item['product_id']}, {$v_item['quantity']}, {$v_item['price_per_item']}, {$v_item['item_total']})";
        if (!$mysqli->query($sql_insert_sale_item)) {
            throw new Exception("Error saving sale item (Product ID: {$v_item['product_id']}): " . $mysqli->error);
        }

        // 4. Decrement product stock
        $sql_update_stock = "UPDATE products SET current_stock = current_stock - {$v_item['quantity']}
                             WHERE id = {$v_item['product_id']}";
        if (!$mysqli->query($sql_update_stock)) {
            throw new Exception("Error updating stock for product ID {$v_item['product_id']}: " . $mysqli->error);
        }
    }

    $mysqli->commit();
    $response['success'] = true;
    $response['message'] = 'Sale processed successfully!';
    $response['sale_id'] = $sale_id;
    $response['receipt_no'] = $receipt_no;

} catch (Exception $e) {
    $mysqli->rollback();
    $response['message'] = "Transaction failed: " . htmlspecialchars($e->getMessage());
}

if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}

echo json_encode($response);
exit;
?>
