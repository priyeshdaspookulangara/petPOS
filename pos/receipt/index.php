<?php
// This page is for displaying a receipt, so public access after sale might be okay,
// or it could be restricted to logged-in users. For now, let's restrict.
$page_title = "Sale Receipt";
$app_base_path = '/';

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_logged_in();

$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$receipt_no_query = isset($_GET['receipt_no']) ? sanitize_input($mysqli, $_GET['receipt_no']) : null;

if (!$sale_id && !$receipt_no_query) {
    $_SESSION['flash_message'] = "No Sale ID or Receipt Number provided.";
    $_SESSION['flash_message_type'] = "danger";
    // Redirect to POS or sales list depending on user role or context
    header("Location: " . site_url('pos', $app_base_path));
    exit;
}

$sale = null;
$sale_items = [];
$store_settings = [];

// Fetch store settings
$settings_keys = ['store_name', 'store_address', 'receipt_footer_message', 'currency_symbol'];
$keys_in_sql = "'" . implode("','", $settings_keys) . "'";
$sql_settings = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($keys_in_sql)";
$res_settings = $mysqli->query($sql_settings);
if($res_settings){
    while($row = $res_settings->fetch_assoc()){
        $store_settings[$row['setting_key']] = $row['setting_value'];
    }
    $res_settings->free();
}
$currency_symbol = $store_settings['currency_symbol'] ?? '$';


// Fetch sale details
$sql_sale_base = "SELECT s.*, u.username as cashier_name
                  FROM sales s
                  JOIN users u ON s.user_id = u.id";

if ($sale_id) {
    $sql_sale = $sql_sale_base . " WHERE s.id = $sale_id";
} elseif ($receipt_no_query) {
    $sql_sale = $sql_sale_base . " WHERE s.receipt_no = '$receipt_no_query'";
}

$result_sale = $mysqli->query($sql_sale);
if ($result_sale && $result_sale->num_rows > 0) {
    $sale = $result_sale->fetch_assoc();
    $result_sale->free();
    // Use the fetched sale_id if query was by receipt_no
    $sale_id = $sale['id'];

    // Fetch sale items
    $sql_items = "SELECT si.*, p.name as product_name, p.sku as product_sku
                  FROM sale_items si
                  JOIN products p ON si.product_id = p.id
                  WHERE si.sale_id = $sale_id";
    $result_items = $mysqli->query($sql_items);
    if ($result_items) {
        while ($row = $result_items->fetch_assoc()) {
            $sale_items[] = $row;
        }
        $result_items->free();
    } else {
        // Error fetching items, but we might still have the main sale record
        $page_error = "Error fetching sale items: " . htmlspecialchars($mysqli->error);
    }
} else {
    $page_error = "Sale not found.";
    if ($sale_id) $page_error .= " (ID: $sale_id)";
    if ($receipt_no_query) $page_error .= " (Receipt #: $receipt_no_query)";
}

// For a receipt page, we might not want the full header/navbar when printing.
// So, we'll use a minimal HTML structure.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) . ($sale ? ' - ' . htmlspecialchars($sale['receipt_no']) : ''); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { font-family: 'Courier New', Courier, monospace; background-color: #fff; margin:0; padding:0; }
        .receipt-container { max-width: 400px; margin: 20px auto; padding: 15px; border: 1px solid #ccc; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .receipt-header { text-align: center; margin-bottom: 15px; }
        .receipt-header h4 { margin-bottom: 2px; font-size: 1.1em; }
        .receipt-header p { margin-bottom: 1px; font-size: 0.8em; }
        .receipt-details p, .receipt-items table td, .receipt-items table th { font-size: 0.85em; padding: 2px 0; }
        .receipt-items table { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .receipt-items th { text-align: left; border-bottom: 1px dashed #666; }
        .receipt-items td.qty, .receipt-items td.price, .receipt-items td.total { text-align: right; }
        .receipt-totals table { width: 100%; margin-top:10px; }
        .receipt-totals td:first-child { text-align: right; padding-right: 10px; }
        .receipt-totals td:last-child { text-align: right; font-weight: bold; }
        .receipt-footer { text-align: center; margin-top: 15px; font-size: 0.8em; }
        .print-button-container { text-align: center; margin-top: 20px; }
        @media print {
            body { margin: 0; padding:0; background-color: #fff!important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .receipt-container { margin: 0 auto; border: none; box-shadow: none; max-width: 100%; }
            .print-button-container { display: none; }
            /* Ensure Bootstrap styles don't add unwanted print margins/paddings if used extensively */
            .container, .container-fluid { padding-left:0; padding-right:0; margin-left:0; margin-right:0; }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <?php if (isset($page_error)): ?>
            <div class="alert alert-danger text-center"><?php echo $page_error; ?></div>
            <div class="text-center mt-3">
                <a href="<?php echo site_url('pos', $app_base_path); ?>" class="btn btn-sm btn-primary">Back to POS</a>
            </div>
        <?php elseif ($sale): ?>
            <div class="receipt-header">
                <h4><?php echo htmlspecialchars($store_settings['store_name'] ?? 'Your Store'); ?></h4>
                <?php if(!empty($store_settings['store_address'])): ?>
                    <p><?php echo nl2br(htmlspecialchars($store_settings['store_address'])); ?></p>
                <?php endif; ?>
                <!-- Add phone/email from settings if available -->
                <p>----------------------------------------</p>
            </div>

            <div class="receipt-details">
                <p>Receipt #: <?php echo htmlspecialchars($sale['receipt_no']); ?></p>
                <p>Date: <?php echo htmlspecialchars(date('M d, Y H:i:s', strtotime($sale['sale_date']))); ?></p>
                <p>Cashier: <?php echo htmlspecialchars($sale['cashier_name']); ?></p>
                <p>----------------------------------------</p>
            </div>

            <div class="receipt-items">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="qty">Qty</th>
                            <th class="price">Price</th>
                            <th class="total">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sale_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td class="qty"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td class="price"><?php echo htmlspecialchars(number_format($item['price_per_item'], 2)); ?></td>
                                <td class="total"><?php echo htmlspecialchars(number_format($item['item_total'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                 <p>----------------------------------------</p>
            </div>

            <div class="receipt-totals">
                <table>
                    <tr><td>Subtotal:</td><td><?php echo $currency_symbol; ?><?php echo htmlspecialchars(number_format($sale['sub_total'], 2)); ?></td></tr>
                    <?php if ((float)$sale['discount_amount'] > 0): ?>
                        <tr><td>Discount:</td><td>-<?php echo $currency_symbol; ?><?php echo htmlspecialchars(number_format($sale['discount_amount'], 2)); ?></td></tr>
                    <?php endif; ?>
                    <tr><td>Tax (<?php echo htmlspecialchars(number_format($sale['tax_percentage'], 2)); ?>%):</td><td><?php echo $currency_symbol; ?><?php echo htmlspecialchars(number_format($sale['tax_amount'], 2)); ?></td></tr>
                    <tr style="font-size: 1.1em;"><td><strong>Grand Total:</strong></td><td><strong><?php echo $currency_symbol; ?><?php echo htmlspecialchars(number_format($sale['grand_total'], 2)); ?></strong></td></tr>
                </table>
                <p>----------------------------------------</p>
                <p style="text-align:center;">Payment Method: <?php echo htmlspecialchars($sale['payment_method']); ?></p>
                <?php
                // Calculate change if payment method was Cash and amount_tendered was passed/stored (not in current schema for sales table)
                // For now, this part is illustrative.
                // if ($sale['payment_method'] == 'Cash' && isset($sale['amount_tendered']) && $sale['amount_tendered'] > 0) {
                //    $change_due_receipt = $sale['amount_tendered'] - $sale['grand_total'];
                //    echo "<p>Amount Tendered: " . $currency_symbol . htmlspecialchars(number_format($sale['amount_tendered'], 2)) . "</p>";
                //    echo "<p>Change Due: " . $currency_symbol . htmlspecialchars(number_format($change_due_receipt, 2)) . "</p>";
                // }
                ?>
            </div>

            <?php if(!empty($store_settings['receipt_footer_message'])): ?>
            <div class="receipt-footer">
                <p><?php echo nl2br(htmlspecialchars($store_settings['receipt_footer_message'])); ?></p>
            </div>
            <?php endif; ?>

            <div class="print-button-container">
                <button onclick="window.print();" class="btn btn-primary btn-sm">Print Receipt</button>
                <a href="<?php echo site_url('pos', $app_base_path); ?>" class="btn btn-secondary btn-sm">New Sale</a>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>
<?php
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
