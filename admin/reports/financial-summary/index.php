<?php
$page_title = "Financial Summary Report";
$app_base_path = '/';

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/auth.php';

redirect_if_not_admin();
$currency_symbol = ($mysqli->query("SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'")->fetch_assoc()['setting_value']) ?? '$';

// --- Filtering Logic ---
$expense_categories_filter = []; // For expense report filter
$cat_sql_filter = "SELECT id, name FROM expense_categories ORDER BY name ASC";
$cat_res_filter = $mysqli->query($cat_sql_filter);
if ($cat_res_filter) while($row = $cat_res_filter->fetch_assoc()) $expense_categories_filter[] = $row;
if ($cat_res_filter) $cat_res_filter->free();

// Default date range to current month
$default_date_from = date('Y-m-01');
$default_date_to = date('Y-m-t');
$filter_date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? sanitize_input($mysqli, $_GET['date_from']) : $default_date_from;
$filter_date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? sanitize_input($mysqli, $_GET['date_to']) : $default_date_to;
$filter_expense_category_id = isset($_GET['expense_category_id']) && !empty($_GET['expense_category_id']) ? (int)$_GET['expense_category_id'] : null;


// --- Report Queries ---

// 1. Expense Report by Category
$expenses_by_category = [];
$total_expenses_filtered = 0;
$expense_where_clauses = ["e.expense_date BETWEEN '$filter_date_from' AND '$filter_date_to'"];
if ($filter_expense_category_id) {
    $expense_where_clauses[] = "e.expense_category_id = $filter_expense_category_id";
}
$sql_expenses_where = "WHERE " . implode(" AND ", $expense_where_clauses);

$sql_expenses_report = "SELECT
                            ec.name as category_name,
                            SUM(e.amount) as total_amount_in_category
                        FROM expenses e
                        JOIN expense_categories ec ON e.expense_category_id = ec.id
                        $sql_expenses_where
                        GROUP BY ec.id, ec.name
                        ORDER BY total_amount_in_category DESC";
$res_expenses = $mysqli->query($sql_expenses_report);
if($res_expenses) {
    while($row = $res_expenses->fetch_assoc()){
        $expenses_by_category[] = $row;
        $total_expenses_filtered += (float)$row['total_amount_in_category'];
    }
    $res_expenses->free();
}


// 2. Sales vs. Purchases vs. Expenses Summary (for the same date range as expenses)
$summary_date_where = "sale_date BETWEEN '$filter_date_from 00:00:00' AND '$filter_date_to 23:59:59'";
$purchase_date_where = "purchase_date BETWEEN '$filter_date_from' AND '$filter_date_to' AND status != 'Canceled'"; // Exclude canceled
$expense_date_where_summary = "expense_date BETWEEN '$filter_date_from' AND '$filter_date_to'";

$total_sales_summary = 0;
$res_sales_sum = $mysqli->query("SELECT SUM(grand_total) as total FROM sales WHERE $summary_date_where");
if($res_sales_sum) $total_sales_summary = (float)($res_sales_sum->fetch_assoc()['total'] ?? 0);
if($res_sales_sum) $res_sales_sum->free();

$total_purchases_summary = 0;
$res_purch_sum = $mysqli->query("SELECT SUM(total_amount) as total FROM purchases WHERE $purchase_date_where");
if($res_purch_sum) $total_purchases_summary = (float)($res_purch_sum->fetch_assoc()['total'] ?? 0);
if($res_purch_sum) $res_purch_sum->free();

$total_expenses_summary_all_cat = 0; // This is different from $total_expenses_filtered if a category is selected for the expense report
$res_exp_sum = $mysqli->query("SELECT SUM(amount) as total FROM expenses WHERE $expense_date_where_summary");
if($res_exp_sum) $total_expenses_summary_all_cat = (float)($res_exp_sum->fetch_assoc()['total'] ?? 0);
if($res_exp_sum) $res_exp_sum->free();

$basic_profit_loss = $total_sales_summary - $total_purchases_summary - $total_expenses_summary_all_cat;


include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-2 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    <p class="mb-4">Summary of financial activities. Use filters to refine the period.</p>

    <!-- Filter Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Reports (Common for all summaries on this page)</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-4">
                        <label for="date_from">Date From</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="date_to">Date To</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="expense_category_id">Filter Expense Report by Category (Optional)</label>
                        <select name="expense_category_id" id="expense_category_id" class="form-control">
                            <option value="">-- All Expense Categories --</option>
                            <?php foreach ($expense_categories_filter as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php if ($filter_expense_category_id == $cat['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                 <div class="form-row mt-2">
                    <div class="form-group col-md-12">
                        <button type="submit" class="btn btn-info"><i class="fas fa-filter"></i> Generate Summaries</button>
                        <a href="<?php echo site_url('admin/reports/financial-summary', $app_base_path); ?>" class="btn btn-secondary ml-2" title="Reset to Current Month"><i class="fas fa-sync-alt"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Sales vs Purchases vs Expenses Summary -->
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Overall Financial Snapshot (<?php echo htmlspecialchars($filter_date_from) . " to " . htmlspecialchars($filter_date_to); ?>)</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-chart-line"></i> Total Sales Revenue</h5>
                                    <p class="card-text display-4"><?php echo $currency_symbol . number_format($total_sales_summary, 2); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-warning text-dark">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-shopping-cart"></i> Total Purchases Cost</h5>
                                    <p class="card-text display-4"><?php echo $currency_symbol . number_format($total_purchases_summary, 2); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-file-invoice-dollar"></i> Total Expenses</h5>
                                    <p class="card-text display-4"><?php echo $currency_symbol . number_format($total_expenses_summary_all_cat, 2); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <h4>Basic Profit / (Loss) Estimate:
                            <span class="<?php echo ($basic_profit_loss >= 0 ? 'text-success' : 'text-danger'); ?>">
                                <?php echo $currency_symbol . number_format($basic_profit_loss, 2); ?>
                            </span>
                        </h4>
                        <small class="text-muted">(Sales - Purchases - Expenses. Does not account for detailed COGS, inventory changes, etc.)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Expense Report by Category -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Expense Report by Category
                        (<?php echo htmlspecialchars($filter_date_from) . " to " . htmlspecialchars($filter_date_to);
                        if($filter_expense_category_id && !empty($expense_categories_filter)) {
                            $filtered_cat_name = '';
                            foreach($expense_categories_filter as $cat){ if($cat['id'] == $filter_expense_category_id) {$filtered_cat_name = $cat['name']; break;}}
                            if($filtered_cat_name) echo " | Category: ".htmlspecialchars($filtered_cat_name);
                        }
                        ?>)
                        <span class="float-right">Total Filtered Expenses: <?php echo $currency_symbol . number_format($total_expenses_filtered, 2); ?></span>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($expenses_by_category)): ?>
                        <div class="alert alert-info">No expenses found for the selected criteria.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-sm" id="expenseReportTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Expense Category</th>
                                    <th class="text-right">Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expenses_by_category as $exp_cat_summary): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exp_cat_summary['category_name']); ?></td>
                                        <td class="text-right"><?php echo $currency_symbol . htmlspecialchars(number_format($exp_cat_summary['total_amount_in_category'], 2)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th class="text-right">Total (for selection):</th>
                                    <th class="text-right"><?php echo $currency_symbol . number_format($total_expenses_filtered, 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../../templates/footer.php';
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
