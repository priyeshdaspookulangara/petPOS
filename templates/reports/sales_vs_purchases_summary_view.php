<?php
// This template is included by modules/reports/financial.php for 'sales_vs_purchases_summary'
// Access to $report_data (contains total_sales, total_purchases, total_expenses),
// $report_title, $filter_period, $start_date, $end_date, $base_module_url

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
$currency_symbol = '$';
$sql_currency_svps = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_svps = mysqli_query($conn, $sql_currency_svps);
if ($res_currency_svps && mysqli_num_rows($res_currency_svps) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_svps)['setting_value']);
    mysqli_free_result($res_currency_svps);
}

$period_display_text_svps = ucfirst($filter_period);
if ($filter_period === 'custom' && !empty($start_date) && !empty($end_date)) {
    $period_display_text_svps = "Custom Range: " . htmlspecialchars($start_date) . " to " . htmlspecialchars($end_date);
}
elseif ($filter_period === 'daily') { $period_display_text_svps = "Today (" . date('Y-m-d') . ")"; }
elseif ($filter_period === 'weekly') { $period_display_text_svps = "This Week"; }
elseif ($filter_period === 'monthly') { $period_display_text_svps = "This Month (" . date('F Y') . ")"; }

$total_sales_val = $report_data['total_sales'] ?? 0;
$total_purchases_val = $report_data['total_purchases'] ?? 0; // Note: This is total PO value, not COGS
$total_expenses_val = $report_data['total_expenses'] ?? 0;

// Simplified "Profit" (Sales - Purchase Values - Expenses). Not true accounting profit.
$simplified_profit = $total_sales_val - $total_purchases_val - $total_expenses_val;

?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2><?php echo htmlspecialchars($report_title); ?></h2>
            <p>Displaying summary for: <strong><?php echo $period_display_text_svps; ?></strong></p>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#salesVsPurchasesFilterCollapse" aria-expanded="false" aria-controls="salesVsPurchasesFilterCollapse">
                <i class="fas fa-filter"></i> Filter Period
            </button>
        </div>
    </div>

    <div class="collapse mb-3" id="salesVsPurchasesFilterCollapse">
        <div class="card card-body">
            <form action="<?php echo htmlspecialchars($base_module_url); ?>" method="GET" class="form">
                <input type="hidden" name="module" value="reports">
                <input type="hidden" name="action" value="financial">
                <input type="hidden" name="type" value="sales_vs_purchases_summary">
                <div class="row">
                    <div class="col-md-4">
                        <label for="period_svps">Period:</label>
                        <select class="form-control" id="period_svps" name="period">
                            <option value="daily" <?php echo ($filter_period == 'daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo ($filter_period == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo ($filter_period == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="custom" <?php echo ($filter_period == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                     <div class="col-md-6 <?php echo ($filter_period != 'custom') ? 'd-none' : ''; ?>" id="customDateRangeSvps">
                        <div class="row">
                            <div class="col">
                                <label for="start_date_svps">Start Date:</label>
                                <input type="date" class="form-control" id="start_date_svps" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col">
                                <label for="end_date_svps">End Date:</label>
                                <input type="date" class="form-control" id="end_date_svps" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 align-self-end">
                        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-check"></i> Apply</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card text-white bg-success mb-3">
                <div class="card-header">Total Sales Revenue</div>
                <div class="card-body"><h4 class="card-title"><?php echo $currency_symbol . number_format($total_sales_val, 2); ?></h4></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-warning mb-3">
                <div class="card-header">Total Purchases Value</div>
                <div class="card-body"><h4 class="card-title"><?php echo $currency_symbol . number_format($total_purchases_val, 2); ?></h4></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-danger mb-3">
                <div class="card-header">Total Expenses</div>
                <div class="card-body"><h4 class="card-title"><?php echo $currency_symbol . number_format($total_expenses_val, 2); ?></h4></div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center mt-3">
        <div class="col-md-6">
            <div class="card <?php echo ($simplified_profit >=0 ? 'border-success' : 'border-danger'); ?> text-center">
                <div class="card-header <?php echo ($simplified_profit >=0 ? 'bg-success' : 'bg-danger'); ?> text-white">
                    Simplified Profit / Loss (Sales - Purchases Value - Expenses)
                </div>
                <div class="card-body">
                    <h2 class="card-title <?php echo ($simplified_profit >=0 ? 'text-success' : 'text-danger'); ?>">
                        <?php echo $currency_symbol . number_format($simplified_profit, 2); ?>
                    </h2>
                    <p class="card-text text-muted">
                        Note: This is a basic overview and not a formal Profit & Loss statement.
                        "Purchases Value" represents the total value of purchase orders in the period, not the Cost of Goods Sold (COGS).
                        A true P&L requires accurate COGS calculation.
                    </p>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodSelectSvps = document.getElementById('period_svps');
    const customDateRangeSvps = document.getElementById('customDateRangeSvps');

    function toggleCustomDateSvps() {
        if (periodSelectSvps.value === 'custom') {
            customDateRangeSvps.classList.remove('d-none');
        } else {
            customDateRangeSvps.classList.add('d-none');
        }
    }
   if(periodSelectSvps) periodSelectSvps.addEventListener('change', toggleCustomDateSvps);
});
</script>
