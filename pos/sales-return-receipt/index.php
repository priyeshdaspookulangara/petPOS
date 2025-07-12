<?php
$page_title = "Sales Return Receipt";
$app_base_path = '/';

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_logged_in();

$return_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$return_id) {
    $_SESSION['flash_message'] = "No Return ID provided.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: " . site_url('pos/sales-return', $app_base_path));
    exit;
}

$return_data = null;
$return_items = [];
$store_settings = [];

// Fetch store settings
$settings_keys = ['store_name', 'store_address', 'receipt_footer_message', 'currency_symbol', 'store_logo_url'];
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
$store_logo_url = $store_settings['store_logo_url'] ?? '';


// Fetch return details
$sql_return = "SELECT
                    sr.*,
                    u.username as processed_by_user,
                    orig_s.receipt_no as original_receipt_no
               FROM sales_returns sr
               JOIN users u ON sr.user_id = u.id
               JOIN sales orig_s ON sr.original_sale_id = orig_s.id
               WHERE sr.id = $return_id";

$result_return = $mysqli->query($sql_return);
if ($result_return && $result_return->num_rows > 0) {
    $return_data = $result_return->fetch_assoc();
    $result_return->free();

    // Fetch return items
    $sql_items = "SELECT sri.*, p.name as product_name, p.sku as product_sku
                  FROM sales_return_items sri
                  JOIN products p ON sri.product_id = p.id
                  WHERE sri.sales_return_id = $return_id";
    $result_items = $mysqli->query($sql_items);
    if ($result_items) {
        while ($row = $result_items->fetch_assoc()) {
            $return_items[] = $row;
        }
        $result_items->free();
    } else {
        $page_error = "Error fetching return items: " . htmlspecialchars($mysqli->error);
    }
} else {
    $page_error = "Sales Return not found (ID: $return_id).";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) . ($return_data ? ' - ' . htmlspecialchars($return_data['return_receipt_no']) : ''); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { font-family: 'Courier New', Courier, monospace; background-color: #fff; }
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
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <?php if (isset($page_error)): ?>
            <div class="alert alert-danger text-center"><?php echo $page_error; ?></div>
            <div class="text-center mt-3">
                <a href="<?php echo site_url('pos/sales-return', $app_base_path); ?>" class="btn btn-sm btn-primary">Back to Sales Return</a>
            </div>
        <?php elseif ($return_data): ?>
            <div class="receipt-header">
                 <?php if (!empty($store_logo_url) && file_exists(__DIR__ . '/../../' . $store_logo_url)): ?>
                    <img src="<?php echo site_url($store_logo_url, $app_base_path); ?>" alt="Store Logo" style="max-width: 150px; max-height: 80px; margin-bottom: 10px;">
                <?php endif; ?>
                <h4><?php echo htmlspecialchars($store_settings['store_name'] ?? 'Your Store'); ?></h4>
                <p>--- CREDIT NOTE / RETURN RECEIPT ---</p>
            </div>

            <div class="receipt-details">
                <p>Return #: <?php echo htmlspecialchars($return_data['return_receipt_no']); ?></p>
                <p>Return Date: <?php echo htmlspecialchars(date('M d, Y H:i:s', strtotime($return_data['return_date']))); ?></p>
                <p>Original Receipt #: <?php echo htmlspecialchars($return_data['original_receipt_no']); ?></p>
                <p>Processed By: <?php echo htmlspecialchars($return_data['processed_by_user']); ?></p>
                <p>----------------------------------------</p>
            </div>

            <div class="receipt-items">
                <table>
                    <thead>
                        <tr>
                            <th>Returned Item</th>
                            <th class="qty">Qty</th>
                            <th class="price">Price</th>
                            <th class="total">Refund</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($return_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td class="qty"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td class="price"><?php echo htmlspecialchars(number_format($item['refund_price_per_item'], 2)); ?></td>
                                <td class="total"><?php echo htmlspecialchars(number_format($item['item_total_refund'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                 <p>----------------------------------------</p>
            </div>

            <div class="receipt-totals">
                <p style="text-align:center;">Reason: <?php echo htmlspecialchars($return_data['reason'] ?: 'N/A'); ?></p>
                <table>
                    <tr style="font-size: 1.1em;">
                        <td><strong>Total Refund:</strong></td>
                        <td><strong><?php echo $currency_symbol; ?><?php echo htmlspecialchars(number_format($return_data['total_refund_amount'], 2)); ?></strong></td>
                    </tr>
                </table>
            </div>

            <?php if(!empty($store_settings['receipt_footer_message'])): ?>
            <div class="receipt-footer">
                <p><?php echo nl2br(htmlspecialchars($store_settings['receipt_footer_message'])); ?></p>
            </div>
            <?php endif; ?>

            <div class="print-button-container">
                <button onclick="window.print();" class="btn btn-primary btn-sm">Print Return Receipt</button>
                <a href="<?php echo site_url('pos', $app_base_path); ?>" class="btn btn-secondary btn-sm">New Sale</a>
                <a href="<?php echo site_url('pos/sales-return', $app_base_path); ?>" class="btn btn-info btn-sm">New Return</a>
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
