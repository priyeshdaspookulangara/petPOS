<?php
// This template is included by modules/reports/sales.php for 'by_category'
// Access to $report_data, $report_title, $filter_period, $start_date, $end_date, $base_module_url,
// $user_filter, $category_filter, $categories_for_filter

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
$currency_symbol = '$';
$sql_currency_sbc = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_sbc = mysqli_query($conn, $sql_currency_sbc);
if ($res_currency_sbc && mysqli_num_rows($res_currency_sbc) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_sbc)['setting_value']);
    mysqli_free_result($res_currency_sbc);
}

$period_display_text_sbc = ucfirst($filter_period);
if ($filter_period === 'custom' && !empty($start_date) && !empty($end_date)) {
    $period_display_text_sbc = "Custom Range: " . htmlspecialchars($start_date) . " to " . htmlspecialchars($end_date);
}
elseif ($filter_period === 'daily') { $period_display_text_sbc = "Today (" . date('Y-m-d') . ")"; }
elseif ($filter_period === 'weekly') { $period_display_text_sbc = "This Week"; }
elseif ($filter_period === 'monthly') { $period_display_text_sbc = "This Month (" . date('F Y') . ")"; }
?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2><?php echo htmlspecialchars($report_title); ?></h2>
            <p>Displaying sales by category for: <strong><?php echo $period_display_text_sbc; ?></strong></p>
            <?php if (!empty($category_filter) && isset($categories_for_filter)): ?>
                <?php
                    $filtered_cat_name = "Unknown Category";
                    foreach($categories_for_filter as $c) { if($c['id'] == $category_filter) $filtered_cat_name = $c['name']; }
                ?>
                <p>Filtered by Category: <strong><?php echo htmlspecialchars($filtered_cat_name); ?></strong></p>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#salesByCategoryFilterCollapse" aria-expanded="false" aria-controls="salesByCategoryFilterCollapse">
                <i class="fas fa-filter"></i> Filter Report
            </button>
        </div>
    </div>

    <div class="collapse mb-3" id="salesByCategoryFilterCollapse">
        <div class="card card-body">
            <form action="<?php echo htmlspecialchars($base_module_url); ?>" method="GET" class="form">
                <input type="hidden" name="module" value="reports">
                <input type="hidden" name="action" value="sales">
                <input type="hidden" name="type" value="by_category">
                 <div class="row">
                    <div class="col-md-3">
                        <label for="period_sbc">Period:</label>
                        <select class="form-control" id="period_sbc" name="period">
                            <option value="daily" <?php echo ($filter_period == 'daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo ($filter_period == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo ($filter_period == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="custom" <?php echo ($filter_period == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                     <div class="col-md-4 <?php echo ($filter_period != 'custom') ? 'd-none' : ''; ?>" id="customDateRangeSbc">
                        <div class="row">
                            <div class="col">
                                <label for="start_date_sbc">Start Date:</label>
                                <input type="date" class="form-control" id="start_date_sbc" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col">
                                <label for="end_date_sbc">End Date:</label>
                                <input type="date" class="form-control" id="end_date_sbc" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="category_id_sbc">Category:</label>
                        <select class="form-control" id="category_id_sbc" name="category_id">
                            <option value="">All Categories</option>
                            <?php foreach ($categories_for_filter as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo ($category_filter == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
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
                        <th>Category Name</th>
                        <th class="text-right">Total Qty Sold</th>
                        <th class="text-right">Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $overall_cat_qty = 0;
                        $overall_cat_revenue = 0;
                    ?>
                    <?php foreach ($report_data as $row):
                        $overall_cat_qty += $row['total_quantity_sold'];
                        $overall_cat_revenue += $row['total_revenue_from_category'];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                            <td class="text-right"><?php echo htmlspecialchars($row['total_quantity_sold']); ?></td>
                            <td class="text-right"><strong><?php echo $currency_symbol . number_format($row['total_revenue_from_category'], 2); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td>Total</td>
                        <td class="text-right"><?php echo $overall_cat_qty; ?></td>
                        <td class="text-right"><?php echo $currency_symbol . number_format($overall_cat_revenue, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            No sales data found for the selected category and period.
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodSelectSbc = document.getElementById('period_sbc');
    const customDateRangeSbc = document.getElementById('customDateRangeSbc');

    function toggleCustomDateSbc() {
        if (periodSelectSbc.value === 'custom') {
            customDateRangeSbc.classList.remove('d-none');
        } else {
            customDateRangeSbc.classList.add('d-none');
        }
    }
    if(periodSelectSbc) periodSelectSbc.addEventListener('change', toggleCustomDateSbc);
});
</script>
