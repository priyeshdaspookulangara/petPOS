<?php
$page_title = "Manage Expenses";
$app_base_path = '/';

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

// Fetch expense categories for filter dropdown
$expense_categories_filter = [];
$cat_sql_filter = "SELECT id, name FROM expense_categories ORDER BY name ASC";
$cat_res_filter = $mysqli->query($cat_sql_filter);
if ($cat_res_filter) while($row = $cat_res_filter->fetch_assoc()) $expense_categories_filter[] = $row;
if ($cat_res_filter) $cat_res_filter->free();

// Filtering
$where_clauses = [];
$filter_category_id = isset($_GET['category_id']) && !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$filter_date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? sanitize_input($mysqli, $_GET['date_from']) : null;
$filter_date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? sanitize_input($mysqli, $_GET['date_to']) : null;

if ($filter_category_id) {
    $where_clauses[] = "e.expense_category_id = $filter_category_id";
}
if ($filter_date_from) {
    $where_clauses[] = "e.expense_date >= '$filter_date_from'";
}
if ($filter_date_to) {
    $where_clauses[] = "e.expense_date <= '$filter_date_to'";
}

$sql_where = "";
if (!empty($where_clauses)) {
    $sql_where = "WHERE " . implode(" AND ", $where_clauses);
}

// Fetch expenses
$expenses = [];
$total_expenses_amount = 0;
$sql = "SELECT e.id, e.amount, e.description, e.expense_date, e.receipt_url,
               ec.name as category_name,
               u.username as recorded_by_user,
               e.created_at
        FROM expenses e
        JOIN expense_categories ec ON e.expense_category_id = ec.id
        JOIN users u ON e.user_id = u.id
        $sql_where
        ORDER BY e.expense_date DESC, e.id DESC";

$result = $mysqli->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
        $total_expenses_amount += (float)$row['amount'];
    }
    $result->free();
} else {
    $_SESSION['flash_message'] = "Error fetching expenses: " . htmlspecialchars($mysqli->error);
    $_SESSION['flash_message_type'] = "danger";
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
        <a href="<?php echo site_url('admin/manage-expense', $app_base_path); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Expense
        </a>
    </div>

    <!-- Filter Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Expenses</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="<?php echo site_url('admin/expenses', $app_base_path); ?>">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="category_id">Category</label>
                        <select name="category_id" id="category_id" class="form-control">
                            <option value="">-- All Categories --</option>
                            <?php foreach ($expense_categories_filter as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php if ($filter_category_id == $cat['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="date_from">Date From</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from ?? ''); ?>">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="date_to">Date To</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to ?? ''); ?>">
                    </div>
                    <div class="form-group col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-info"><i class="fas fa-filter"></i> Filter</button>
                        <a href="<?php echo site_url('admin/expenses', $app_base_path); ?>" class="btn btn-secondary ml-2" title="Clear Filters"><i class="fas fa-times"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <?php if (empty($expenses) && empty($_SESSION['flash_message']) && !empty($sql_where)): ?>
        <div class="alert alert-info">No expenses found matching your filter criteria.</div>
    <?php elseif (empty($expenses) && empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-info">No expenses recorded yet.</div>
    <?php elseif (!empty($expenses)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    Expense List (Total for selection: <?php echo ($store_settings['currency_symbol'] ?? '$') . number_format($total_expenses_amount, 2); ?>)
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="expensesTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th class="text-right">Amount</th>
                                <th>Receipt</th>
                                <th>Recorded By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($expense['expense_date']))); ?></td>
                                    <td><?php echo htmlspecialchars($expense['category_name']); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($expense['description'] ?: '-')); ?></td>
                                    <td class="text-right"><?php echo ($store_settings['currency_symbol'] ?? '$') . htmlspecialchars(number_format($expense['amount'], 2)); ?></td>
                                    <td class="text-center">
                                        <?php if (!empty($expense['receipt_url'])): ?>
                                            <a href="<?php echo site_url($expense['receipt_url'], $app_base_path); ?>" target="_blank" title="View Receipt">
                                                <i class="fas fa-receipt"></i>
                                            </a>
                                        <?php else: echo '-'; endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($expense['recorded_by_user']); ?></td>
                                    <td>
                                        <a href="<?php echo site_url('admin/manage-expense/?id=' . $expense['id'], $app_base_path); ?>" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?php echo site_url('admin/manage-expense/?action=delete&id=' . $expense['id'], $app_base_path); ?>"
                                           class="btn btn-sm btn-danger" title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this expense record?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                         <tfoot>
                            <tr>
                                <th colspan="3" class="text-right">Total for this selection:</th>
                                <th class="text-right"><?php echo ($store_settings['currency_symbol'] ?? '$') . number_format($total_expenses_amount, 2); ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/../../templates/footer.php';
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
