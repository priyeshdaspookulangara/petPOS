<?php
$page_title = "Top Selling Products Report";
$app_base_path = '/';

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/auth.php';

redirect_if_not_admin();

// Filtering Logic
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 50;
$order_by = isset($_GET['order_by']) && $_GET['order_by'] == 'revenue' ? 'total_revenue_per_product' : 'total_quantity_sold';
$default_date_from = date('Y-m-d', strtotime('-30 days'));
$default_date_to = date('Y-m-d');
$filter_date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? sanitize_input($mysqli, $_GET['date_from']) : $default_date_from;
$filter_date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? sanitize_input($mysqli, $_GET['date_to']) : $default_date_to;

$sql_where = "WHERE s.sale_date BETWEEN '$filter_date_from 00:00:00' AND '$filter_date_to 23:59:59'";

// Report Query
$products_report_sql = "SELECT
                            p.id, p.name, p.sku,
                            SUM(si.quantity) as total_quantity_sold,
                            SUM(si.item_total) as total_revenue_per_product
                        FROM sale_items si
                        JOIN sales s ON si.sale_id = s.id
                        JOIN products p ON si.product_id = p.id
                        $sql_where
                        GROUP BY p.id, p.name, p.sku
                        ORDER BY $order_by DESC
                        LIMIT $limit";
$products_res = $mysqli->query($products_report_sql);
$currency_symbol = ($mysqli->query("SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'")->fetch_assoc()['setting_value']) ?? '$';

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-2 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Report</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-3">
                        <label for="date_from">Date From</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="date_to">Date To</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="order_by">Order By</label>
                        <select name="order_by" id="order_by" class="form-control">
                            <option value="quantity" <?php if ($order_by == 'total_quantity_sold') echo 'selected'; ?>>Quantity Sold</option>
                            <option value="revenue" <?php if ($order_by == 'total_revenue_per_product') echo 'selected'; ?>>Revenue</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="limit">Limit</label>
                        <input type="number" name="limit" id="limit" class="form-control" value="<?php echo $limit; ?>" min="1" max="500">
                    </div>
                    <div class="form-group col-md-2">
                        <button type="submit" class="btn btn-info"><i class="fas fa-filter"></i> Generate</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Top <?php echo $limit; ?> Products by <?php echo ($order_by == 'total_quantity_sold' ? 'Quantity' : 'Revenue'); ?></h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Product (SKU)</th>
                            <th class="text-right">Total Quantity Sold</th>
                            <th class="text-right">Total Revenue Generated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($products_res && $products_res->num_rows > 0):
                            $rank = 1;
                            while($row = $products_res->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?> <?php if($row['sku']) echo '('.htmlspecialchars($row['sku']).')'; ?></td>
                                <td class="text-right"><?php echo $row['total_quantity_sold']; ?></td>
                                <td class="text-right"><?php echo $currency_symbol . number_format($row['total_revenue_per_product'], 2); ?></td>
                            </tr>
                        <?php endwhile; $products_res->free(); else: ?>
                            <tr><td colspan="4" class="text-center">No product sales data for this period.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
