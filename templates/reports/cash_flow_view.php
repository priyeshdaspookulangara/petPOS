<?php
// This template is included by modules/reports/cash_flow.php
// It has access to $cash_flow_data and $detailed_transactions, $base_module_url

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
$currency_symbol = '$'; // Placeholder, ideally from settings
$sql_currency_cf = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_cf = mysqli_query($conn, $sql_currency_cf);
if ($res_currency_cf && mysqli_num_rows($res_currency_cf) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_cf)['setting_value']);
    mysqli_free_result($res_currency_cf);
}

function format_cf_amount($amount, $symbol) {
    $color = ($amount >= 0) ? 'text-success' : 'text-danger';
    return '<strong class="' . $color . '">' . $symbol . number_format($amount, 2) . '</strong>';
}
function format_cf_amount_neutral($amount, $symbol) {
    return $symbol . number_format($amount, 2);
}

$period_display_text = ucfirst($cash_flow_data['filter_period']);
if ($cash_flow_data['filter_period'] === 'custom' && !empty($cash_flow_data['start_date']) && !empty($cash_flow_data['end_date'])) {
    $period_display_text = "Custom: " . htmlspecialchars($cash_flow_data['start_date']) . " to " . htmlspecialchars($cash_flow_data['end_date']);
} elseif ($cash_flow_data['filter_period'] === 'daily') {
    $period_display_text = "Today (" . date('Y-m-d') . ")";
} elseif ($cash_flow_data['filter_period'] === 'weekly') {
    $period_display_text = "This Week (" . date('Y-m-d', strtotime('monday this week')) . " to " . date('Y-m-d', strtotime('sunday this week')) . ")";
} elseif ($cash_flow_data['filter_period'] === 'monthly') {
    $period_display_text = "This Month (" . date('F Y') . ")";
}


?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2>Cash Flow Report</h2>
            <p>Displaying cash flow for: <strong><?php echo $period_display_text; ?></strong></p>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
                <i class="fas fa-filter"></i> Change Period
            </button>
        </div>
    </div>

    <div class="collapse mb-3" id="filterCollapse">
        <div class="card card-body">
            <form action="<?php echo htmlspecialchars($base_module_url); ?>" method="GET" class="form-inline">
                <input type="hidden" name="module" value="reports">
                <input type="hidden" name="action" value="cash_flow">

                <label class="my-1 mr-2" for="period">Period:</label>
                <select class="custom-select my-1 mr-sm-2" id="period" name="period">
                    <option value="daily" <?php echo ($cash_flow_data['filter_period'] == 'daily') ? 'selected' : ''; ?>>Daily</option>
                    <option value="weekly" <?php echo ($cash_flow_data['filter_period'] == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                    <option value="monthly" <?php echo ($cash_flow_data['filter_period'] == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                    <option value="custom" <?php echo ($cash_flow_data['filter_period'] == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                </select>

                <div id="customDateRange" class="<?php echo ($cash_flow_data['filter_period'] != 'custom') ? 'd-none' : ''; ?>">
                    <label class="my-1 mr-2" for="start_date">Start Date:</label>
                    <input type="date" class="form-control my-1 mr-sm-2" id="start_date" name="start_date" value="<?php echo htmlspecialchars($cash_flow_data['start_date']); ?>">

                    <label class="my-1 mr-2" for="end_date">End Date:</label>
                    <input type="date" class="form-control my-1 mr-sm-2" id="end_date" name="end_date" value="<?php echo htmlspecialchars($cash_flow_data['end_date']); ?>">
                </div>

                <button type="submit" class="btn btn-primary my-1"><i class="fas fa-check"></i> Apply Filter</button>
            </form>
        </div>
    </div>


    <div class="row">
        <div class="col-md-4">
            <div class="card text-white bg-success mb-3">
                <div class="card-header">Total Inflows (Sales)</div>
                <div class="card-body">
                    <h4 class="card-title"><?php echo format_cf_amount_neutral($cash_flow_data['total_inflows'], $currency_symbol); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-danger mb-3">
                <div class="card-header">Total Outflows (Purchases & Expenses)</div>
                <div class="card-body">
                    <h4 class="card-title"><?php echo format_cf_amount_neutral($cash_flow_data['total_outflows'], $currency_symbol); ?></h4>
                    <p class="card-text mb-0">Purchases: <?php echo format_cf_amount_neutral($cash_flow_data['total_purchase_outflows'], $currency_symbol); ?></p>
                    <p class="card-text">Expenses: <?php echo format_cf_amount_neutral($cash_flow_data['total_expense_outflows'], $currency_symbol); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card <?php echo ($cash_flow_data['net_cash_flow'] >= 0) ? 'bg-info' : 'bg-warning'; ?> text-white mb-3">
                <div class="card-header">Net Cash Flow</div>
                <div class="card-body">
                    <h4 class="card-title"><?php echo format_cf_amount($cash_flow_data['net_cash_flow'], $currency_symbol); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <hr>
    <h4>Detailed Transactions for the Period</h4>

    <div class="row">
        <div class="col-lg-4 mb-3">
            <h5><i class="fas fa-arrow-down text-success"></i> Sales (Inflows)</h5>
            <?php if(!empty($detailed_transactions['sales'])): ?>
            <ul class="list-group">
                <?php foreach($detailed_transactions['sales'] as $sale): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Receipt #<?php echo htmlspecialchars($sale['receipt_no']); ?> <small>(<?php echo date('Y-m-d', strtotime($sale['sale_date'])); ?>)</small>
                    <span class="badge badge-success badge-pill"><?php echo $currency_symbol . number_format($sale['grand_total'], 2); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-muted">No sales recorded for this period.</p>
            <?php endif; ?>
        </div>

        <div class="col-lg-4 mb-3">
            <h5><i class="fas fa-arrow-up text-danger"></i> Purchases (Outflows)</h5>
             <?php if(!empty($detailed_transactions['purchases'])): ?>
            <ul class="list-group">
                <?php foreach($detailed_transactions['purchases'] as $purchase): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    PO #<?php echo htmlspecialchars($purchase['po_number'] ?? 'N/A'); ?> <small>(<?php echo date('Y-m-d', strtotime($purchase['purchase_date'])); ?>)</small>
                    <span class="badge badge-danger badge-pill"><?php echo $currency_symbol . number_format($purchase['total_amount'], 2); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-muted">No purchases recorded as outflow for this period.</p>
            <?php endif; ?>
        </div>

        <div class="col-lg-4 mb-3">
            <h5><i class="fas fa-arrow-up text-danger"></i> Expenses (Outflows)</h5>
            <?php if(!empty($detailed_transactions['expenses'])): ?>
            <ul class="list-group">
                 <?php foreach($detailed_transactions['expenses'] as $expense): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <?php echo htmlspecialchars($expense['description']); ?><br>
                        <small class="text-muted"><?php echo htmlspecialchars($expense['category_name']); ?> (<?php echo date('Y-m-d', strtotime($expense['expense_date'])); ?>)</small>
                    </div>
                    <span class="badge badge-danger badge-pill"><?php echo $currency_symbol . number_format($expense['amount'], 2); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-muted">No expenses recorded for this period.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodSelect = document.getElementById('period');
    const customDateRangeDiv = document.getElementById('customDateRange');

    function toggleCustomDateRange() {
        if (periodSelect.value === 'custom') {
            customDateRangeDiv.classList.remove('d-none');
        } else {
            customDateRangeDiv.classList.add('d-none');
        }
    }

    periodSelect.addEventListener('change', toggleCustomDateRange);
    // toggleCustomDateRange(); // Call on load to set initial state (already handled by PHP class though)
});
</script>
