<?php
// This template is included by modules/reports/sales.php for 'summary_by_date'
// Access to $report_data, $report_title, $filter_period, $start_date, $end_date, $base_module_url, $user_filter
// $users_for_filter (if admin and report type needs it, though not directly for this summary)

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
$currency_symbol = '$'; // Placeholder
$sql_currency_sbd = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_sbd = mysqli_query($conn, $sql_currency_sbd);
if ($res_currency_sbd && mysqli_num_rows($res_currency_sbd) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_sbd)['setting_value']);
    mysqli_free_result($res_currency_sbd);
}

$period_display_text_sbd = ucfirst($filter_period);
if ($filter_period === 'custom' && !empty($start_date) && !empty($end_date)) {
    $period_display_text_sbd = "Custom Range: " . htmlspecialchars($start_date) . " to " . htmlspecialchars($end_date);
} elseif ($filter_period === 'daily') {
    $period_display_text_sbd = "Today (" . date('Y-m-d') . ")";
} elseif ($filter_period === 'weekly') {
    $period_display_text_sbd = "This Week (" . date('Y-m-d', strtotime('monday this week')) . " to " . date('Y-m-d', strtotime('sunday this week')) . ")";
} elseif ($filter_period === 'monthly') {
    $period_display_text_sbd = "This Month (" . date('F Y') . ")";
}
?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2><?php echo htmlspecialchars($report_title); ?></h2>
            <p>Displaying sales summary for: <strong><?php echo $period_display_text_sbd; ?></strong></p>
            <?php if ($_SESSION['user_role'] === 'admin' && !empty($user_filter) && isset($users_for_filter)): ?>
                <?php
                    $filtered_user_name = "Unknown User";
                    foreach($users_for_filter as $u) { if($u['id'] == $user_filter) $filtered_user_name = $u['username']; }
                ?>
                <p>Filtered by User: <strong><?php echo htmlspecialchars($filtered_user_name); ?></strong></p>
            <?php elseif ($_SESSION['user_role'] === 'cashier'): ?>
                 <p>Displaying your sales only.</p>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#salesReportFilterCollapse" aria-expanded="false" aria-controls="salesReportFilterCollapse">
                <i class="fas fa-filter"></i> Filter Report
            </button>
        </div>
    </div>

    <div class="collapse mb-3" id="salesReportFilterCollapse">
        <div class="card card-body">
            <form action="<?php echo htmlspecialchars($base_module_url); ?>" method="GET" class="form">
                <input type="hidden" name="module" value="reports">
                <input type="hidden" name="action" value="sales">
                <input type="hidden" name="type" value="summary_by_date">
                <div class="row">
                    <div class="col-md-3">
                        <label for="period_sbd">Period:</label>
                        <select class="form-control" id="period_sbd" name="period">
                            <option value="daily" <?php echo ($filter_period == 'daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo ($filter_period == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo ($filter_period == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="custom" <?php echo ($filter_period == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    <div class="col-md-4 <?php echo ($filter_period != 'custom') ? 'd-none' : ''; ?>" id="customDateRangeSbd">
                        <div class="row">
                            <div class="col">
                                <label for="start_date_sbd">Start Date:</label>
                                <input type="date" class="form-control" id="start_date_sbd" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col">
                                <label for="end_date_sbd">End Date:</label>
                                <input type="date" class="form-control" id="end_date_sbd" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                        </div>
                    </div>
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <div class="col-md-3">
                        <label for="user_id_sbd">User (Cashier):</label>
                        <select class="form-control" id="user_id_sbd" name="user_id">
                            <option value="">All Users</option>
                            <?php
                                // Assuming $users_for_filter is populated in the controller for admin
                                $users_list_sbd = [];
                                $sql_users_sbd = "SELECT id, username FROM users WHERE role IN ('admin', 'cashier') ORDER BY username ASC";
                                $res_users_sbd = mysqli_query($conn, $sql_users_sbd);
                                if($res_users_sbd) while($u_row = mysqli_fetch_assoc($res_users_sbd)) $users_list_sbd[] = $u_row;
                                if($res_users_sbd) mysqli_free_result($res_users_sbd);

                                foreach ($users_list_sbd as $user_sbd): ?>
                                <option value="<?php echo $user_sbd['id']; ?>" <?php echo ($user_filter == $user_sbd['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user_sbd['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
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
                        <th class="text-right">No. of Sales</th>
                        <th class="text-right">Subtotal</th>
                        <th class="text-right">Discounts</th>
                        <th class="text-right">Tax Amount</th>
                        <th class="text-right">Grand Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $overall_sales_count = 0;
                        $overall_subtotal = 0;
                        $overall_discount = 0;
                        $overall_tax = 0;
                        $overall_grand_total = 0;
                    ?>
                    <?php foreach ($report_data as $row):
                        $overall_sales_count += $row['number_of_sales'];
                        $overall_subtotal += $row['total_sub_total'];
                        $overall_discount += $row['total_discount'];
                        $overall_tax += $row['total_tax'];
                        $overall_grand_total += $row['total_grand_total'];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($row['sale_day']))); ?></td>
                            <td class="text-right"><?php echo htmlspecialchars($row['number_of_sales']); ?></td>
                            <td class="text-right"><?php echo $currency_symbol . number_format($row['total_sub_total'], 2); ?></td>
                            <td class="text-right"><?php echo $currency_symbol . number_format($row['total_discount'], 2); ?></td>
                            <td class="text-right"><?php echo $currency_symbol . number_format($row['total_tax'], 2); ?></td>
                            <td class="text-right"><strong><?php echo $currency_symbol . number_format($row['total_grand_total'], 2); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td>Total</td>
                        <td class="text-right"><?php echo $overall_sales_count; ?></td>
                        <td class="text-right"><?php echo $currency_symbol . number_format($overall_subtotal, 2); ?></td>
                        <td class="text-right"><?php echo $currency_symbol . number_format($overall_discount, 2); ?></td>
                        <td class="text-right"><?php echo $currency_symbol . number_format($overall_tax, 2); ?></td>
                        <td class="text-right"><?php echo $currency_symbol . number_format($overall_grand_total, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            No sales data found for the selected period and filters.
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodSelectSbd = document.getElementById('period_sbd');
    const customDateRangeSbd = document.getElementById('customDateRangeSbd');

    function toggleCustomDateSbd() {
        if (periodSelectSbd.value === 'custom') {
            customDateRangeSbd.classList.remove('d-none');
        } else {
            customDateRangeSbd.classList.add('d-none');
        }
    }
    if(periodSelectSbd) periodSelectSbd.addEventListener('change', toggleCustomDateSbd);
});
</script>
