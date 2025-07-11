<?php
// This template is included by modules/reports/sales.php for 'by_product'
// Access to $report_data, $report_title, $filter_period, $start_date, $end_date, $base_module_url,
// $user_filter, $product_filter, $products_for_filter

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
$currency_symbol = '$';
$sql_currency_sbp = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_sbp = mysqli_query($conn, $sql_currency_sbp);
if ($res_currency_sbp && mysqli_num_rows($res_currency_sbp) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_sbp)['setting_value']);
    mysqli_free_result($res_currency_sbp);
}

$period_display_text_sbp = ucfirst($filter_period);
if ($filter_period === 'custom' && !empty($start_date) && !empty($end_date)) {
    $period_display_text_sbp = "Custom Range: " . htmlspecialchars($start_date) . " to " . htmlspecialchars($end_date);
} // ... other period displays same as sales_summary_by_date ...
elseif ($filter_period === 'daily') { $period_display_text_sbp = "Today (" . date('Y-m-d') . ")"; }
elseif ($filter_period === 'weekly') { $period_display_text_sbp = "This Week"; }
elseif ($filter_period === 'monthly') { $period_display_text_sbp = "This Month (" . date('F Y') . ")"; }

?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2><?php echo htmlspecialchars($report_title); ?></h2>
            <p>Displaying sales by product for: <strong><?php echo $period_display_text_sbp; ?></strong></p>
            <?php if (!empty($product_filter) && isset($products_for_filter)): ?>
                <?php
                    $filtered_prod_name = "Unknown Product";
                    foreach($products_for_filter as $p) { if($p['id'] == $product_filter) $filtered_prod_name = $p['name']." (".$p['sku'].")"; }
                ?>
                <p>Filtered by Product: <strong><?php echo htmlspecialchars($filtered_prod_name); ?></strong></p>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#salesByProductFilterCollapse" aria-expanded="false" aria-controls="salesByProductFilterCollapse">
                <i class="fas fa-filter"></i> Filter Report
            </button>
        </div>
    </div>

    <div class="collapse mb-3" id="salesByProductFilterCollapse">
        <div class="card card-body">
            <form action="<?php echo htmlspecialchars($base_module_url); ?>" method="GET" class="form">
                <input type="hidden" name="module" value="reports">
                <input type="hidden" name="action" value="sales">
                <input type="hidden" name="type" value="by_product">
                <div class="row">
                    <div class="col-md-3">
                        <label for="period_sbp">Period:</label>
                        <select class="form-control" id="period_sbp" name="period">
                            <option value="daily" <?php echo ($filter_period == 'daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo ($filter_period == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo ($filter_period == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="custom" <?php echo ($filter_period == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                     <div class="col-md-4 <?php echo ($filter_period != 'custom') ? 'd-none' : ''; ?>" id="customDateRangeSbp">
                        <div class="row">
                            <div class="col">
                                <label for="start_date_sbp">Start Date:</label>
                                <input type="date" class="form-control" id="start_date_sbp" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col">
                                <label for="end_date_sbp">End Date:</label>
                                <input type="date" class="form-control" id="end_date_sbp" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="product_id_sbp">Product:</label>
                        <select class="form-control" id="product_id_sbp" name="product_id">
                            <option value="">All Products</option>
                            <?php foreach ($products_for_filter as $product): ?>
                                <option value="<?php echo $product['id']; ?>" <?php echo ($product_filter == $product['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['name'] . " (" . $product['sku'] . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 align-self-end">
                        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-check"></i> Apply</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($report_data)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered dataTable">
                <thead class="thead-dark">
                    <tr>
                        <th>SKU</th>
                        <th>Product Name</th>
                        <th class="text-right">Total Qty Sold</th>
                        <th class="text-right">Avg. Selling Price</th>
                        <th class="text-right">Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $overall_qty_sold = 0;
                        $overall_revenue = 0;
                    ?>
                    <?php foreach ($report_data as $row):
                        $overall_qty_sold += $row['total_quantity_sold'];
                        $overall_revenue += $row['total_revenue_from_product'];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['product_sku']); ?></td>
                            <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                            <td class="text-right"><?php echo htmlspecialchars($row['total_quantity_sold']); ?></td>
                            <td class="text-right"><?php echo $currency_symbol . number_format($row['avg_selling_price'], 2); ?></td>
                            <td class="text-right"><strong><?php echo $currency_symbol . number_format($row['total_revenue_from_product'], 2); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                 <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td colspan="2">Total</td>
                        <td class="text-right"><?php echo $overall_qty_sold; ?></td>
                        <td></td> <!-- Avg price total doesn't make sense -->
                        <td class="text-right"><?php echo $currency_symbol . number_format($overall_revenue, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            No sales data found for the selected product and period.
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodSelectSbp = document.getElementById('period_sbp');
    const customDateRangeSbp = document.getElementById('customDateRangeSbp');

    function toggleCustomDateSbp() {
        if (periodSelectSbp.value === 'custom') {
            customDateRangeSbp.classList.remove('d-none');
        } else {
            customDateRangeSbp.classList.add('d-none');
        }
    }
   if(periodSelectSbp) periodSelectSbp.addEventListener('change', toggleCustomDateSbp);
});
</script>
