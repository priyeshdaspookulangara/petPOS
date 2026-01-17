<?php
$page_title = "Product Categories";
$app_base_path = '/'; // Adjust if your application is in a subdirectory. Should be global.

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin(); // Ensures only admin can access

// Fetch all categories to display
$categories = [];
// Query to get categories and count of products in each (optional)
$sql = "SELECT c.id, c.name, c.created_at, COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        GROUP BY c.id, c.name, c.created_at
        ORDER BY c.name ASC";
$result = $mysqli->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $result->free();
} else {
    $_SESSION['flash_message'] = "Error fetching categories: " . htmlspecialchars($mysqli->error);
    $_SESSION['flash_message_type'] = "danger";
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
        <a href="<?php echo site_url('admin/manage-category', $app_base_path); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Category
        </a>
    </div>

    <?php if (empty($categories) && empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-info">No categories found. Start by adding a new one!</div>
    <?php elseif (!empty($categories)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Category List</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="categoriesTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Products in Category</th>
                                <th>Created On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo $category['product_count']; ?></td>
                                    <td><?php echo date("Y-m-d H:i", strtotime($category['created_at'])); ?></td>
                                    <td>
                                        <a href="<?php echo site_url('admin/manage-category/?id=' . $category['id'], $app_base_path); ?>" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php // Prevent deletion if products are assigned (product_count > 0) ?>
                                        <?php if ($category['product_count'] == 0): ?>
                                        <a href="<?php echo site_url('admin/manage-category/?action=delete&id=' . $category['id'], $app_base_path); ?>"
                                           class="btn btn-sm btn-danger" title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this category? This action cannot be undone.');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-danger" title="Cannot delete: Category has products assigned." disabled><i class="fas fa-trash"></i></button>
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
<?php // Optional: Add DataTables for better table interaction, similar to users page ?>
<!--
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css"/>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
<script>
    // $(document).ready(function() {
    //     $('#categoriesTable').DataTable();
    // });
</script>
-->
