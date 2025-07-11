<?php
// This template is included by modules/accounts/expense_categories.php when $page_action is 'list'
// It has access to $categories array and $base_module_url

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col">
            <h2>Manage Expense Categories</h2>
        </div>
        <div class="col text-right">
            <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=add'); ?>" class="btn btn-success">
                <i class="fas fa-plus"></i> Add New Category
            </a>
        </div>
    </div>

    <?php if (!empty($categories)): ?>
        <table class="table table-striped table-bordered">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Expense Count</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($category['id']); ?></td>
                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($category['description'] ? $category['description'] : '-')); ?></td>
                        <td><?php echo htmlspecialchars($category['expense_count']); ?></td>
                        <td>
                            <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=edit&id=' . $category['id']); ?>" class="btn btn-sm btn-info" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($category['expense_count'] == 0): ?>
                            <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=delete&id=' . $category['id']); ?>"
                               class="btn btn-sm btn-danger" title="Delete"
                               onclick="return confirm('Are you sure you want to delete this expense category?');">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php else: ?>
                            <button class="btn btn-sm btn-danger" title="Cannot delete: Category has expenses linked." disabled>
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            No expense categories found. <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=add'); ?>">Add the first category!</a>
        </div>
    <?php endif; ?>
</div>
