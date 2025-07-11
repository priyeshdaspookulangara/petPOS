<?php
// This template is included by modules/reports/purchases.php for 'summary_by_date'
// Access to $report_data, $report_title, $filter_period, $start_date, $end_date, $base_module_url

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
$currency_symbol = '$';
$sql_currency_psd = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_psd = mysqli_query($conn, $sql_currency_psd);
if ($res_currency_psd && mysqli_num_rows($res_currency_psd) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_psd)['setting_value']);
    mysqli_free_result($res_currency_psd);
}

$period_display_text_psd = ucfirst($filter_period);
if ($filter_period === 'custom' && !empty($start_date) && !empty($end_date)) {
    $period_display_text_psd = "Custom Range: " . htmlspecialchars($start_date) . " to " . htmlspecialchars($end_date);
}
elseif ($filter_period === 'daily') { $period_display_text_psd = "Today (" . date('Y-m-d') . ")"; }
elseif ($filter_period === 'weekly') { $period_display_text_psd = "This Week"; }
elseif ($filter_period === 'monthly') { $period_display_text_psd = "This Month (" . date('F Y') . ")"; }
?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2><?php echo htmlspecialchars($report_title); ?></h2>
            <p>Displaying purchase summary for: <strong><?php echo $period_display_text_psd; ?></strong></p>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#purchasesReportFilterCollapse" aria-expanded="false" aria-controls="purchasesReportFilterCollapse">
                <i class="fas fa-filter"></i> Filter Report
            </button>
        </div>
    </div>

    <div class="collapse mb-3" id="purchasesReportFilterCollapse">
        <div class="card card-body">
            <form action="<?php echo htmlspecialchars($base_module_url); ?>" method="GET" class="form">
                <input type="hidden" name="module" value="reports">
                <input type="hidden" name="action" value="purchases">
                <input type="hidden" name="type" value="summary_by_date">
                <div class="row">
                    <div class="col-md-4">
                        <label for="period_psd">Period:</label>
                        <select class="form-control" id="period_psd" name="period">
                            <option value="daily" <?php echo ($filter_period == 'daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo ($filter_period == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo ($filter_period == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="custom" <?php echo ($filter_period == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                     <div class="col-md-6 <?php echo ($filter_period != 'custom') ? 'd-none' : ''; ?>" id="customDateRangePsd">
                        <div class="row">
                            <div class="col">
                                <label for="start_date_psd">Start Date:</label>
                                <input type="date" class="form-control" id="start_date_psd" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col">
                                <label for="end_date_psd">End Date:</label>
                                <input type="date" class="form-control" id="end_date_psd" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
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

    <?php if (!empty($report_data)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered dataTable">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th class="text-right">No. of POs</th>
                        <th class="text-right">Total PO Value</th>
                        <th class="text-right">Total Amount Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $overall_po_count = 0;
                        $overall_po_value = 0;
                        $overall_amount_paid = 0;
                    ?>
                    <?php foreach ($report_data as $row):
                        $overall_po_count += $row['number_of_pos'];
                        $overall_po_value += $row['total_po_value'];
                        $overall_amount_paid += $row['total_amount_paid'];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($row['purchase_day']))); ?></td>
                            <td class="text-right"><?php echo htmlspecialchars($row['number_of_pos']); ?></td>
                            <td class="text-right"><?php echo $currency_symbol . number_format($row['total_po_value'], 2); ?></td>
                            <td class="text-right"><?php echo $currency_symbol . number_format($row['total_amount_paid'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td>Total</td>
                        <td class="text-right"><?php echo $overall_po_count; ?></td>
                        <td class="text-right"><?php echo $currency_symbol . number_format($overall_po_value, 2); ?></td>
                        <td class="text-right"><?php echo $currency_symbol . number_format($overall_amount_paid, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            No purchase data found for the selected period.
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodSelectPsd = document.getElementById('period_psd');
    const customDateRangePsd = document.getElementById('customDateRangePsd');

    function toggleCustomDatePsd() {
        if (periodSelectPsd.value === 'custom') {
            customDateRangePsd.classList.remove('d-none');
        } else {
            customDateRangePsd.classList.add('d-none');
        }
    }
    if(periodSelectPsd) periodSelectPsd.addEventListener('change', toggleCustomDatePsd);
});
</script>
