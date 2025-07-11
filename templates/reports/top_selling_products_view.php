<?php
// This template is included by modules/reports/sales.php for 'top_selling'
// Access to $report_data, $report_title, $filter_period, $start_date, $end_date, $base_module_url, $limit

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
$currency_symbol = '$';
$sql_currency_tsp = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_tsp = mysqli_query($conn, $sql_currency_tsp);
if ($res_currency_tsp && mysqli_num_rows($res_currency_tsp) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_tsp)['setting_value']);
    mysqli_free_result($res_currency_tsp);
}

$period_display_text_tsp = ucfirst($filter_period);
if ($filter_period === 'custom' && !empty($start_date) && !empty($end_date)) {
    $period_display_text_tsp = "Custom Range: " . htmlspecialchars($start_date) . " to " . htmlspecialchars($end_date);
}
elseif ($filter_period === 'daily') { $period_display_text_tsp = "Today (" . date('Y-m-d') . ")"; }
elseif ($filter_period === 'weekly') { $period_display_text_tsp = "This Week"; }
elseif ($filter_period === 'monthly') { $period_display_text_tsp = "This Month (" . date('F Y') . ")"; }

?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2><?php echo htmlspecialchars($report_title); ?> (Top <?php echo isset($limit) ? htmlspecialchars($limit) : '10'; ?>)</h2>
            <p>Displaying top selling products for: <strong><?php echo $period_display_text_tsp; ?></strong></p>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#topSellingFilterCollapse" aria-expanded="false" aria-controls="topSellingFilterCollapse">
                <i class="fas fa-filter"></i> Filter Report
            </button>
        </div>
    </div>

    <div class="collapse mb-3" id="topSellingFilterCollapse">
        <div class="card card-body">
            <form action="<?php echo htmlspecialchars($base_module_url); ?>" method="GET" class="form">
                <input type="hidden" name="module" value="reports">
                <input type="hidden" name="action" value="sales">
                <input type="hidden" name="type" value="top_selling">
                 <div class="row">
                    <div class="col-md-3">
                        <label for="period_tsp">Period:</label>
                        <select class="form-control" id="period_tsp" name="period">
                            <option value="daily" <?php echo ($filter_period == 'daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo ($filter_period == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo ($filter_period == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="custom" <?php echo ($filter_period == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                     <div class="col-md-4 <?php echo ($filter_period != 'custom') ? 'd-none' : ''; ?>" id="customDateRangeTsp">
                        <div class="row">
                            <div class="col">
                                <label for="start_date_tsp">Start Date:</label>
                                <input type="date" class="form-control" id="start_date_tsp" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col">
                                <label for="end_date_tsp">End Date:</label>
                                <input type="date" class="form-control" id="end_date_tsp" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="limit_tsp">Number of Products (Top N):</label>
                        <input type="number" class="form-control" id="limit_tsp" name="limit" value="<?php echo isset($limit) ? htmlspecialchars($limit) : '10'; ?>" min="1" max="100">
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
                        <th>Rank</th>
                        <th>SKU</th>
                        <th>Product Name</th>
                        <th class="text-right">Total Quantity Sold</th>
                        <th class="text-right">Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank = 1; foreach ($report_data as $row): ?>
                        <tr>
                            <td><?php echo $rank++; ?></td>
                            <td><?php echo htmlspecialchars($row['product_sku']); ?></td>
                            <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                            <td class="text-right"><?php echo htmlspecialchars($row['total_quantity_sold']); ?></td>
                            <td class="text-right"><strong><?php echo $currency_symbol . number_format($row['total_revenue'], 2); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            No sales data found for the selected period to determine top selling products.
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodSelectTsp = document.getElementById('period_tsp');
    const customDateRangeTsp = document.getElementById('customDateRangeTsp');

    function toggleCustomDateTsp() {
        if (periodSelectTsp.value === 'custom') {
            customDateRangeTsp.classList.remove('d-none');
        } else {
            customDateRangeTsp.classList.add('d-none');
        }
    }
   if(periodSelectTsp) periodSelectTsp.addEventListener('change', toggleCustomDateTsp);
});
</script>
