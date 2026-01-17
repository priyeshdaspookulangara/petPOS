<?php
$page_title = "Suppliers";
$app_base_path = '/'; // Adjust if your application is in a subdirectory. Should be global.

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin(); // Ensures only admin can access

// Fetch all suppliers to display
$suppliers = [];
// Query to get suppliers and count of products/POs (optional, for deletion check later)
$sql = "SELECT s.id, s.name, s.contact_person, s.phone, s.email, s.created_at,
               (SELECT COUNT(*) FROM products p WHERE p.supplier_id = s.id) as product_count,
               (SELECT COUNT(*) FROM purchases pu WHERE pu.supplier_id = s.id) as purchase_order_count
        FROM suppliers s
        ORDER BY s.name ASC";
$result = $mysqli->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
    $result->free();
} else {
    $_SESSION['flash_message'] = "Error fetching suppliers: " . htmlspecialchars($mysqli->error);
    $_SESSION['flash_message_type'] = "danger";
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
        <a href="<?php echo site_url('admin/manage-supplier', $app_base_path); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Supplier
        </a>
    </div>

    <?php if (empty($suppliers) && empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-info">No suppliers found. Start by adding a new one!</div>
    <?php elseif (!empty($suppliers)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Supplier List</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="suppliersTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact Person</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <!-- <th>Products</th> -->
                                <!-- <th>POs</th> -->
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $supplier): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['contact_person'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['phone'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['email'] ?: '-'); ?></td>
                                    <!-- <td><?php // echo $supplier['product_count']; ?></td> -->
                                    <!-- <td><?php // echo $supplier['purchase_order_count']; ?></td> -->
                                    <td>
                                        <a href="<?php echo site_url('admin/manage-supplier/?id=' . $supplier['id'], $app_base_path); ?>" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php
                                        // Prevent deletion if products or POs are assigned
                                        $can_delete = ($supplier['product_count'] == 0 && $supplier['purchase_order_count'] == 0);
                                        $delete_title = $can_delete ? "Delete" : "Cannot delete: Supplier has products or purchase orders linked.";
                                        ?>
                                        <a href="<?php echo site_url('admin/manage-supplier/?action=delete&id=' . $supplier['id'], $app_base_path); ?>"
                                           class="btn btn-sm btn-danger <?php echo !$can_delete ? 'disabled' : ''; ?>"
                                           title="<?php echo $delete_title; ?>"
                                           <?php if ($can_delete): ?>
                                           onclick="return confirm('Are you sure you want to delete this supplier? This action cannot be undone.');"
                                           <?php else: ?>
                                           onclick="event.preventDefault(); alert('<?php echo $delete_title; ?>');"
                                           <?php endif; ?>
                                           >
                                            <i class="fas fa-trash"></i>
                                        </a>
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
<?php // Optional: DataTables for suppliersTable ?>
