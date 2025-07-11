<?php
// This template is included by modules/reports/sales.php for 'sales_return_summary'
// Access to $report_data, $report_title, $filter_period, $start_date, $end_date, $base_module_url,
// $user_filter, $users_for_filter

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
$currency_symbol = '$';
$sql_currency_srr = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_srr = mysqli_query($conn, $sql_currency_srr);
if ($res_currency_srr && mysqli_num_rows($res_currency_srr) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_srr)['setting_value']);
    mysqli_free_result($res_currency_srr);
}

$period_display_text_srr = ucfirst($filter_period);
if ($filter_period === 'custom' && !empty($start_date) && !empty($end_date)) {
    $period_display_text_srr = "Custom Range: " . htmlspecialchars($start_date) . " to " . htmlspecialchars($end_date);
}
elseif ($filter_period === 'daily') { $period_display_text_srr = "Today (" . date('Y-m-d') . ")"; }
elseif ($filter_period === 'weekly') { $period_display_text_srr = "This Week"; }
elseif ($filter_period === 'monthly') { $period_display_text_srr = "This Month (" . date('F Y') . ")"; }
?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2><?php echo htmlspecialchars($report_title); ?></h2>
            <p>Displaying sales returns for: <strong><?php echo $period_display_text_srr; ?></strong></p>
            <?php if ($_SESSION['user_role'] === 'admin' && !empty($user_filter) && isset($users_for_filter)): ?>
                <?php
                    $filtered_user_name_srr = "Unknown User";
                    foreach($users_for_filter as $u) { if($u['id'] == $user_filter) $filtered_user_name_srr = $u['username']; }
                ?>
                <p>Filtered by User (Processed by): <strong><?php echo htmlspecialchars($filtered_user_name_srr); ?></strong></p>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-right">
            <?php if ($_SESSION['user_role'] === 'admin'): ?>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#salesReturnFilterCollapse" aria-expanded="false" aria-controls="salesReturnFilterCollapse">
                <i class="fas fa-filter"></i> Filter Report
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($_SESSION['user_role'] === 'admin'): ?>
    <div class="collapse mb-3" id="salesReturnFilterCollapse">
        <div class="card card-body">
            <form action="<?php echo htmlspecialchars($base_module_url); ?>" method="GET" class="form">
                <input type="hidden" name="module" value="reports">
                <input type="hidden" name="action" value="sales">
                <input type="hidden" name="type" value="sales_return_summary">
                <div class="row">
                    <div class="col-md-3">
                        <label for="period_srr">Period:</label>
                        <select class="form-control" id="period_srr" name="period">
                            <option value="daily" <?php echo ($filter_period == 'daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo ($filter_period == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo ($filter_period == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="custom" <?php echo ($filter_period == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                     <div class="col-md-4 <?php echo ($filter_period != 'custom') ? 'd-none' : ''; ?>" id="customDateRangeSrr">
                        <div class="row">
                            <div class="col">
                                <label for="start_date_srr">Start Date:</label>
                                <input type="date" class="form-control" id="start_date_srr" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col">
                                <label for="end_date_srr">End Date:</label>
                                <input type="date" class="form-control" id="end_date_srr" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="user_id_srr">Processed By User:</label>
                        <select class="form-control" id="user_id_srr" name="user_id">
                            <option value="">All Users</option>
                            <?php foreach ($users_for_filter as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo ($user_filter == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
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
    <?php endif; ?>

    <?php if (!empty($report_data)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered dataTable">
                <thead class="thead-dark">
                    <tr>
                        <th>Return Date</th>
                        <th>Return Receipt #</th>
                        <th>Original Sale Receipt #</th>
                        <th>Processed By</th>
                        <th class="text-right">Total Refund Amount</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $overall_refund_total = 0;
                    ?>
                    <?php foreach ($report_data as $row):
                        $overall_refund_total += $row['total_refund_amount'];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($row['return_date']))); ?></td>
                            <td><?php echo htmlspecialchars($row['return_receipt_no']); ?></td>
                            <td><?php echo htmlspecialchars($row['original_sale_receipt']); ?></td>
                            <td><?php echo htmlspecialchars($row['processed_by']); ?></td>
                            <td class="text-right text-danger"><strong><?php echo $currency_symbol . number_format($row['total_refund_amount'], 2); ?></strong></td>
                            <td><?php echo nl2br(htmlspecialchars($row['reason'] ? $row['reason'] : '-')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                 <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td colspan="4" class="text-right">Total Refunded</td>
                        <td class="text-right text-danger"><?php echo $currency_symbol . number_format($overall_refund_total, 2); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            No sales returns found for the selected criteria.
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodSelectSrr = document.getElementById('period_srr');
    const customDateRangeSrr = document.getElementById('customDateRangeSrr');

    function toggleCustomDateSrr() {
        if (periodSelectSrr && periodSelectSrr.value === 'custom') {
           if(customDateRangeSrr) customDateRangeSrr.classList.remove('d-none');
        } else {
           if(customDateRangeSrr) customDateRangeSrr.classList.add('d-none');
        }
    }
   if(periodSelectSrr) periodSelectSrr.addEventListener('change', toggleCustomDateSrr);
});
</script>
