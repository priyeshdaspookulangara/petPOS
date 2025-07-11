<?php
// This template is included by modules/inventory/categories.php when $page_action is 'list'
// It has access to $categories array and $base_module_url (for links)

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col">
            <h2>Manage Product Categories</h2>
        </div>
        <div class="col text-right">
            <a href="<?php echo htmlspecialchars($base_module_self_url . '&sub_action=add'); ?>" class="btn btn-success">
                <i class="fas fa-plus"></i> Add New Category
            </a>
        </div>
    </div>

    <?php
    // Session messages are displayed by header.php
    // This local message display is if there were errors during category fetching within categories.php itself
    // if (isset($_SESSION['local_message'])):
    ?>
        <!-- <div class="alert alert-<?php //echo $_SESSION['local_message_type']; ?> alert-dismissible fade show" role="alert"> -->
            <?php //echo $_SESSION['local_message']; ?>
            <!-- <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button> -->
        <!-- </div> -->
    <?php
        // unset($_SESSION['local_message']);
        // unset($_SESSION['local_message_type']);
    // endif;
    ?>

    <?php if (!empty($categories)): ?>
        <table class="table table-striped table-bordered">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Product Count</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($category['id']); ?></td>
                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($category['description'] ? $category['description'] : '-')); ?></td>
                        <td><?php echo htmlspecialchars($category['product_count']); ?></td>
                        <td>
                            <a href="<?php echo htmlspecialchars($base_module_self_url . '&sub_action=edit&id=' . $category['id']); ?>" class="btn btn-sm btn-info" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($category['product_count'] == 0): ?>
                            <a href="<?php echo htmlspecialchars($base_module_self_url . '&sub_action=delete&id=' . $category['id']); ?>"
                               class="btn btn-sm btn-danger" title="Delete"
                               onclick="return confirm('Are you sure you want to delete this category? This action cannot be undone.');">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php else: ?>
                            <button class="btn btn-sm btn-danger" title="Cannot delete: Category in use" disabled>
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
            No categories found. <a href="<?php echo htmlspecialchars($base_module_self_url . '&sub_action=add'); ?>">Add the first category!</a>
        </div>
    <?php endif; ?>
</div>
