<?php
// This template is included by modules/reports/sales.php for 'by_user'
// Access to $report_data, $report_title, $filter_period, $start_date, $end_date, $base_module_url,
// $user_filter, $users_for_filter

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
$currency_symbol = '$';
$sql_currency_sbu = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_sbu = mysqli_query($conn, $sql_currency_sbu);
if ($res_currency_sbu && mysqli_num_rows($res_currency_sbu) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_sbu)['setting_value']);
    mysqli_free_result($res_currency_sbu);
}

$period_display_text_sbu = ucfirst($filter_period);
if ($filter_period === 'custom' && !empty($start_date) && !empty($end_date)) {
    $period_display_text_sbu = "Custom Range: " . htmlspecialchars($start_date) . " to " . htmlspecialchars($end_date);
}
elseif ($filter_period === 'daily') { $period_display_text_sbu = "Today (" . date('Y-m-d') . ")"; }
elseif ($filter_period === 'weekly') { $period_display_text_sbu = "This Week"; }
elseif ($filter_period === 'monthly') { $period_display_text_sbu = "This Month (" . date('F Y') . ")"; }

?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2><?php echo htmlspecialchars($report_title); ?></h2>
            <p>Displaying sales by user for: <strong><?php echo $period_display_text_sbu; ?></strong></p>
             <?php if ($_SESSION['user_role'] === 'admin' && !empty($user_filter) && isset($users_for_filter)): ?>
                <?php
                    $filtered_user_name_sbu = "Unknown User";
                    foreach($users_for_filter as $u) { if($u['id'] == $user_filter) $filtered_user_name_sbu = $u['username']; }
                ?>
                <p>Filtered by User: <strong><?php echo htmlspecialchars($filtered_user_name_sbu); ?></strong></p>
            <?php elseif ($_SESSION['user_role'] === 'cashier'): ?>
                 <p>Displaying your sales only.</p>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-right">
             <?php if ($_SESSION['user_role'] === 'admin'): // Only admin can change user filter ?>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#salesByUserFilterCollapse" aria-expanded="false" aria-controls="salesByUserFilterCollapse">
                <i class="fas fa-filter"></i> Filter Report
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($_SESSION['user_role'] === 'admin'): ?>
    <div class="collapse mb-3" id="salesByUserFilterCollapse">
        <div class="card card-body">
            <form action="<?php echo htmlspecialchars($base_module_url); ?>" method="GET" class="form">
                <input type="hidden" name="module" value="reports">
                <input type="hidden" name="action" value="sales">
                <input type="hidden" name="type" value="by_user">
                 <div class="row">
                    <div class="col-md-3">
                        <label for="period_sbu">Period:</label>
                        <select class="form-control" id="period_sbu" name="period">
                            <option value="daily" <?php echo ($filter_period == 'daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo ($filter_period == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo ($filter_period == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="custom" <?php echo ($filter_period == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                     <div class="col-md-4 <?php echo ($filter_period != 'custom') ? 'd-none' : ''; ?>" id="customDateRangeSbu">
                        <div class="row">
                            <div class="col">
                                <label for="start_date_sbu">Start Date:</label>
                                <input type="date" class="form-control" id="start_date_sbu" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col">
                                <label for="end_date_sbu">End Date:</label>
                                <input type="date" class="form-control" id="end_date_sbu" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="user_id_sbu">User (Cashier):</label>
                        <select class="form-control" id="user_id_sbu" name="user_id">
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
                        <th>User ID</th>
                        <th>Cashier Name</th>
                        <th class="text-right">Number of Sales</th>
                        <th class="text-right">Total Sales Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $overall_user_sales_count = 0;
                        $overall_user_sales_value = 0;
                    ?>
                    <?php foreach ($report_data as $row):
                        $overall_user_sales_count += $row['number_of_sales'];
                        $overall_user_sales_value += $row['total_sales_value'];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['user_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['cashier_name']); ?></td>
                            <td class="text-right"><?php echo htmlspecialchars($row['number_of_sales']); ?></td>
                            <td class="text-right"><strong><?php echo $currency_symbol . number_format($row['total_sales_value'], 2); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                 <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td colspan="2">Total</td>
                        <td class="text-right"><?php echo $overall_user_sales_count; ?></td>
                        <td class="text-right"><?php echo $currency_symbol . number_format($overall_user_sales_value, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            No sales data found for the selected user and period.
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodSelectSbu = document.getElementById('period_sbu');
    const customDateRangeSbu = document.getElementById('customDateRangeSbu');

    function toggleCustomDateSbu() {
        if (periodSelectSbu && periodSelectSbu.value === 'custom') {
            if(customDateRangeSbu) customDateRangeSbu.classList.remove('d-none');
        } else {
            if(customDateRangeSbu) customDateRangeSbu.classList.add('d-none');
        }
    }
    if(periodSelectSbu) periodSelectSbu.addEventListener('change', toggleCustomDateSbu);
});
</script>
