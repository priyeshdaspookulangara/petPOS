<?php
// This template is included by modules/inventory/suppliers.php when $page_action is 'list'
// It has access to $suppliers array and $base_module_url (for links)

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col">
            <h2>Manage Suppliers</h2>
        </div>
        <div class="col text-right">
            <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=add'); ?>" class="btn btn-success">
                <i class="fas fa-plus"></i> Add New Supplier
            </a>
        </div>
    </div>

    <?php
    // Session messages are displayed by header.php
    ?>

    <div class="table-responsive">
        <?php if (!empty($suppliers)): ?>
            <table class="table table-striped table-bordered table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Products</th>
                        <th>Purchases</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($supplier['id']); ?></td>
                            <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                            <td><?php echo htmlspecialchars($supplier['contact_person'] ? $supplier['contact_person'] : '-'); ?></td>
                            <td><?php echo htmlspecialchars($supplier['phone'] ? $supplier['phone'] : '-'); ?></td>
                            <td><?php echo htmlspecialchars($supplier['email'] ? $supplier['email'] : '-'); ?></td>
                            <td><?php echo htmlspecialchars($supplier['product_count']); ?></td>
                            <td><?php echo htmlspecialchars($supplier['purchase_count']); ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=edit&id=' . $supplier['id']); ?>" class="btn btn-sm btn-info" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($supplier['product_count'] == 0 && $supplier['purchase_count'] == 0): ?>
                                <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=delete&id=' . $supplier['id']); ?>"
                                   class="btn btn-sm btn-danger" title="Delete"
                                   onclick="return confirm('Are you sure you want to delete this supplier? This action cannot be undone.');">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php else: ?>
                                <button class="btn btn-sm btn-danger" title="Cannot delete: Supplier is associated with products or purchases." disabled>
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
                No suppliers found. <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=add'); ?>">Add the first supplier!</a>
            </div>
        <?php endif; ?>
    </div>
</div>
