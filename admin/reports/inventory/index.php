<?php
$page_title = "Inventory Reports";
$app_base_path = '/';

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/auth.php';

redirect_if_not_admin();
$currency_symbol = ($mysqli->query("SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'")->fetch_assoc()['setting_value']) ?? '$';

// --- Current Stock Report ---
$products_stock = [];
$total_stock_value = 0;
$sql_stock = "SELECT
                p.id, p.sku, p.name, p.current_stock, p.purchase_price, p.selling_price, p.reorder_level,
                c.name as category_name,
                s.name as supplier_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN suppliers s ON p.supplier_id = s.id
             ORDER BY p.name ASC";
$result_stock = $mysqli->query($sql_stock);
if ($result_stock) {
    while ($row = $result_stock->fetch_assoc()) {
        $products_stock[] = $row;
        $total_stock_value += (float)$row['current_stock'] * (float)$row['purchase_price'];
    }
    $result_stock->free();
} else {
    $_SESSION['flash_message'] = "Error fetching stock data: " . htmlspecialchars($mysqli->error);
    $_SESSION['flash_message_type'] = "danger";
}

// --- (Placeholder) Stock Movement Report Logic ---
// This is complex. For now, we might just link to stock adjustments log or list recent adjustments.
// A true stock movement report for a product would involve:
// - Initial stock (if tracked from a certain date)
// - Purchases (+)
// - Sales (-)
// - Sales Returns (+)
// - Purchase Returns (-)
// - Manual Adjustments (+/-)
// This requires querying multiple tables and ordering by date.
$selected_product_id_for_movement = isset($_GET['product_id_movement']) ? (int)$_GET['product_id_movement'] : null;
$stock_movements = [];
if ($selected_product_id_for_movement) {
    // Example: Fetch only manual stock adjustments for this product for now
    $sql_move = "SELECT sa.adjustment_date as `date`, 'Manual Adjustment' as `type`,
                        CONCAT(sa.adjustment_type, ' (', sa.reason, ')') as `details`,
                        (CASE WHEN sa.adjustment_type = 'In' THEN sa.quantity ELSE -sa.quantity END) as `quantity_change`,
                        u.username as `user`
                 FROM stock_adjustments sa
                 JOIN users u ON sa.user_id = u.id
                 WHERE sa.product_id = $selected_product_id_for_movement
                 ORDER BY sa.adjustment_date DESC LIMIT 100"; // Simplified
    $res_move = $mysqli->query($sql_move);
    if($res_move) while($row = $res_move->fetch_assoc()) $stock_movements[] = $row;
    if($res_move) $res_move->free();
}

// Fetch products for movement report dropdown
$products_for_select = [];
$prod_sql_select = "SELECT id, name, sku FROM products ORDER BY name ASC";
$prod_res_select = $mysqli->query($prod_sql_select);
if ($prod_res_select) while($row = $prod_res_select->fetch_assoc()) $products_for_select[] = $row;
if ($prod_res_select) $prod_res_select->free();


include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-2 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

    <!-- Current Stock Report -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Current Stock Report</h6>
            <span class="text-info font-weight-bold">Total Stock Value (at Purchase Price): <?php echo $currency_symbol . number_format($total_stock_value, 2); ?></span>
        </div>
        <div class="card-body">
            <?php if (empty($products_stock) && !isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-info">No product stock data available.</div>
            <?php elseif (!empty($products_stock)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm" id="currentStockTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Supplier</th>
                            <th class="text-right">Current Stock</th>
                            <th class="text-right">Reorder Lvl</th>
                            <th class="text-right">Purchase Price</th>
                            <th class="text-right">Selling Price</th>
                            <th class="text-right">Stock Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products_stock as $product):
                            $stock_value = (float)$product['current_stock'] * (float)$product['purchase_price'];
                        ?>
                            <tr class="<?php echo ($product['current_stock'] > 0 && $product['reorder_level'] > 0 && $product['current_stock'] <= $product['reorder_level']) ? 'table-warning' : ($product['current_stock'] <= 0 ? 'table-danger' : ''); ?>">
                                <td><?php echo htmlspecialchars($product['sku'] ?: '-'); ?></td>
                                <td>
                                    <a href="<?php echo site_url('admin/manage-product/?id=' . $product['id'], $app_base_path); ?>">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($product['category_name'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($product['supplier_name'] ?: 'N/A'); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($product['current_stock']); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($product['reorder_level']); ?></td>
                                <td class="text-right"><?php echo $currency_symbol . htmlspecialchars(number_format($product['purchase_price'], 2)); ?></td>
                                <td class="text-right"><?php echo $currency_symbol . htmlspecialchars(number_format($product['selling_price'], 2)); ?></td>
                                <td class="text-right"><?php echo $currency_symbol . htmlspecialchars(number_format($stock_value, 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                     <tfoot>
                        <tr>
                            <th colspan="8" class="text-right">Grand Total Stock Value:</th>
                            <th class="text-right"><?php echo $currency_symbol . number_format($total_stock_value, 2); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stock Movement Report (Simplified) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Stock Movement Report (Recent Manual Adjustments)</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="#stockMovementSection" class="mb-3">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-6">
                        <label for="product_id_movement">Select Product for Movement History:</label>
                        <select name="product_id_movement" id="product_id_movement" class="form-control">
                            <option value="">-- Select Product --</option>
                            <?php foreach($products_for_select as $p_select): ?>
                                <option value="<?php echo $p_select['id']; ?>" <?php if($selected_product_id_for_movement == $p_select['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($p_select['name']) . ($p_select['sku'] ? ' ('.htmlspecialchars($p_select['sku']).')' : ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <button type="submit" class="btn btn-info"><i class="fas fa-history"></i> Show History</button>
                    </div>
                </div>
            </form>
            <div id="stockMovementSection">
            <?php if ($selected_product_id_for_movement && empty($stock_movements)): ?>
                <div class="alert alert-info">No manual stock adjustments found for the selected product. (Full movement history from sales/purchases is a more advanced report).</div>
            <?php elseif (!empty($stock_movements)): ?>
                <h6 class="mb-3">Movement for: <strong><?php echo htmlspecialchars(array_values(array_filter($products_for_select, function($p) use ($selected_product_id_for_movement){ return $p['id'] == $selected_product_id_for_movement; }))[0]['name'] ?? 'Selected Product'); ?></strong></h6>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Date</th><th>Type</th><th>Details</th><th class="text-right">Qty Change</th><th>User</th></tr></thead>
                        <tbody>
                            <?php foreach($stock_movements as $move): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', strtotime($move['date'])); ?></td>
                                <td><?php echo htmlspecialchars($move['type']); ?></td>
                                <td><?php echo htmlspecialchars($move['details']); ?></td>
                                <td class="text-right <?php echo ((int)$move['quantity_change'] > 0 ? 'text-success' : ((int)$move['quantity_change'] < 0 ? 'text-danger' : '')); ?>">
                                    <?php echo ((int)$move['quantity_change'] > 0 ? '+' : '') . $move['quantity_change']; ?>
                                </td>
                                <td><?php echo htmlspecialchars($move['user']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($selected_product_id_for_movement): ?>
                 <div class="alert alert-warning">Could not fetch movement data for the selected product.</div>
            <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php
include __DIR__ . '/../../../templates/footer.php';
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
<?php // DataTables for currentStockTable could be added ?>
