<?php
// This template is included by modules/reports/financial.php for 'expense_by_category'
// Access to $report_data, $report_title, $filter_period, $start_date, $end_date, $base_module_url,
// $category_filter_exp, $expense_categories_for_filter

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
$currency_symbol = '$';
$sql_currency_ebc = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_ebc = mysqli_query($conn, $sql_currency_ebc);
if ($res_currency_ebc && mysqli_num_rows($res_currency_ebc) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_ebc)['setting_value']);
    mysqli_free_result($res_currency_ebc);
}

$period_display_text_ebc = ucfirst($filter_period);
if ($filter_period === 'custom' && !empty($start_date) && !empty($end_date)) {
    $period_display_text_ebc = "Custom Range: " . htmlspecialchars($start_date) . " to " . htmlspecialchars($end_date);
}
elseif ($filter_period === 'daily') { $period_display_text_ebc = "Today (" . date('Y-m-d') . ")"; }
elseif ($filter_period === 'weekly') { $period_display_text_ebc = "This Week"; }
elseif ($filter_period === 'monthly') { $period_display_text_ebc = "This Month (" . date('F Y') . ")"; }
?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2><?php echo htmlspecialchars($report_title); ?></h2>
            <p>Displaying expenses by category for: <strong><?php echo $period_display_text_ebc; ?></strong></p>
             <?php if (!empty($category_filter_exp) && isset($expense_categories_for_filter)): ?>
                <?php
                    $filtered_exp_cat_name = "Unknown Category";
                    foreach($expense_categories_for_filter as $ec) { if($ec['id'] == $category_filter_exp) $filtered_exp_cat_name = $ec['name']; }
                ?>
                <p>Filtered by Expense Category: <strong><?php echo htmlspecialchars($filtered_exp_cat_name); ?></strong></p>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#expenseByCategoryFilterCollapse" aria-expanded="false" aria-controls="expenseByCategoryFilterCollapse">
                <i class="fas fa-filter"></i> Filter Report
            </button>
        </div>
    </div>

    <div class="collapse mb-3" id="expenseByCategoryFilterCollapse">
        <div class="card card-body">
            <form action="<?php echo htmlspecialchars($base_module_url); ?>" method="GET" class="form">
                <input type="hidden" name="module" value="reports">
                <input type="hidden" name="action" value="financial">
                <input type="hidden" name="type" value="expense_by_category">
                <div class="row">
                    <div class="col-md-3">
                        <label for="period_ebc">Period:</label>
                        <select class="form-control" id="period_ebc" name="period">
                            <option value="daily" <?php echo ($filter_period == 'daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo ($filter_period == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo ($filter_period == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="custom" <?php echo ($filter_period == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                     <div class="col-md-4 <?php echo ($filter_period != 'custom') ? 'd-none' : ''; ?>" id="customDateRangeEbc">
                        <div class="row">
                            <div class="col">
                                <label for="start_date_ebc">Start Date:</label>
                                <input type="date" class="form-control" id="start_date_ebc" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col">
                                <label for="end_date_ebc">End Date:</label>
                                <input type="date" class="form-control" id="end_date_ebc" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="expense_category_id_ebc">Expense Category:</label>
                        <select class="form-control" id="expense_category_id_ebc" name="expense_category_id">
                            <option value="">All Categories</option>
                            <?php foreach ($expense_categories_for_filter as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo ($category_filter_exp == $category['id']) ? 'selected' : ''; ?>>
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
                        <th>Expense Category</th>
                        <th class="text-right">Number of Expenses</th>
                        <th class="text-right">Total Amount Spent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $overall_exp_count = 0;
                        $overall_exp_amount = 0;
                    ?>
                    <?php foreach ($report_data as $row):
                        $overall_exp_count += $row['number_of_expenses'];
                        $overall_exp_amount += $row['total_amount_spent'];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                            <td class="text-right"><?php echo htmlspecialchars($row['number_of_expenses']); ?></td>
                            <td class="text-right text-danger"><strong><?php echo $currency_symbol . number_format($row['total_amount_spent'], 2); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                 <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td>Total</td>
                        <td class="text-right"><?php echo $overall_exp_count; ?></td>
                        <td class="text-right text-danger"><?php echo $currency_symbol . number_format($overall_exp_amount, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            No expense data found for the selected category and period.
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodSelectEbc = document.getElementById('period_ebc');
    const customDateRangeEbc = document.getElementById('customDateRangeEbc');

    function toggleCustomDateEbc() {
        if (periodSelectEbc.value === 'custom') {
            customDateRangeEbc.classList.remove('d-none');
        } else {
            customDateRangeEbc.classList.add('d-none');
        }
    }
   if(periodSelectEbc) periodSelectEbc.addEventListener('change', toggleCustomDateEbc);
});
</script>
