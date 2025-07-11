<?php
// This template is included by modules/accounts/expenses.php when $page_action is 'list'
// It has access to $expenses array and $base_module_url

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
$currency_symbol = '$'; // Placeholder, ideally from settings
$sql_currency_el = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_el = mysqli_query($conn, $sql_currency_el);
if ($res_currency_el && mysqli_num_rows($res_currency_el) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_el)['setting_value']);
    mysqli_free_result($res_currency_el);
}
?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col">
            <h2>Manage Expenses</h2>
        </div>
        <div class="col text-right">
            <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=add'); ?>" class="btn btn-success">
                <i class="fas fa-plus"></i> Record New Expense
            </a>
        </div>
    </div>

    <?php if (!empty($expenses)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered dataTable">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th class="text-right">Amount</th>
                        <th>Recorded By</th>
                        <th>Receipt Ref.</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($expense['id']); ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($expense['expense_date']))); ?></td>
                            <td><?php echo htmlspecialchars($expense['category_name']); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($expense['description'])); ?></td>
                            <td class="text-right"><?php echo $currency_symbol . number_format($expense['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($expense['user_username']); ?></td>
                            <td><?php echo htmlspecialchars($expense['receipt_reference'] ? $expense['receipt_reference'] : '-'); ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=edit&id=' . $expense['id']); ?>" class="btn btn-sm btn-info" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=delete&id=' . $expense['id']); ?>"
                                   class="btn btn-sm btn-danger" title="Delete"
                                   onclick="return confirm('Are you sure you want to delete this expense record?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            No expenses recorded yet. <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=add'); ?>">Record the first expense!</a>
        </div>
    <?php endif; ?>
</div>
