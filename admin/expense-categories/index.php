<?php
$page_title = "Expense Categories";
$app_base_path = '/';

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

// Fetch expense categories and count of expenses in each
$expense_categories = [];
$sql = "SELECT ec.id, ec.name, ec.description, ec.created_at, COUNT(e.id) as expense_count
        FROM expense_categories ec
        LEFT JOIN expenses e ON ec.id = e.expense_category_id
        GROUP BY ec.id, ec.name, ec.description, ec.created_at
        ORDER BY ec.name ASC";
$result = $mysqli->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $expense_categories[] = $row;
    }
    $result->free();
} else {
    $_SESSION['flash_message'] = "Error fetching expense categories: " . htmlspecialchars($mysqli->error);
    $_SESSION['flash_message_type'] = "danger";
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
        <a href="<?php echo site_url('admin/manage-expense-category', $app_base_path); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Expense Category
        </a>
    </div>

    <?php if (empty($expense_categories) && empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-info">No expense categories found. Add categories like 'Rent', 'Utilities', 'Marketing', etc.</div>
    <?php elseif (!empty($expense_categories)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Expense Category List</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="expenseCategoriesTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th class="text-center">Expenses Logged</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expense_categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['description'] ?: '-'); ?></td>
                                    <td class="text-center"><?php echo $category['expense_count']; ?></td>
                                    <td>
                                        <a href="<?php echo site_url('admin/manage-expense-category/?id=' . $category['id'], $app_base_path); ?>" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($category['expense_count'] == 0): ?>
                                        <a href="<?php echo site_url('admin/manage-expense-category/?action=delete&id=' . $category['id'], $app_base_path); ?>"
                                           class="btn btn-sm btn-danger" title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this expense category?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-danger" title="Cannot delete: Category has expenses logged." disabled><i class="fas fa-trash"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
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
