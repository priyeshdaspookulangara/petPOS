<?php
$page_title = "Purchase Orders";
$app_base_path = '/'; // Adjust if your application is in a subdirectory.

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

// Fetch purchase orders
$purchase_orders = [];
$sql = "SELECT
            p.id, p.po_number, p.purchase_date, p.expected_delivery_date,
            p.total_amount, p.status,
            s.name as supplier_name
        FROM purchases p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        ORDER BY p.purchase_date DESC, p.id DESC";

$result = $mysqli->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $purchase_orders[] = $row;
    }
    $result->free();
} else {
    $_SESSION['flash_message'] = "Error fetching purchase orders: " . htmlspecialchars($mysqli->error);
    $_SESSION['flash_message_type'] = "danger";
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
        <a href="<?php echo site_url('admin/manage-po', $app_base_path); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create New Purchase Order
        </a>
    </div>

    <?php if (empty($purchase_orders) && empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-info">No purchase orders found. Start by creating one!</div>
    <?php elseif (!empty($purchase_orders)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Purchase Order List</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="purchaseOrdersTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>PO Number</th>
                                <th>Supplier</th>
                                <th>Purchase Date</th>
                                <th>Expected Delivery</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchase_orders as $po): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($po['po_number'] ?: 'PO-' . $po['id']); ?></td>
                                    <td><?php echo htmlspecialchars($po['supplier_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($po['purchase_date']))); ?></td>
                                    <td><?php echo $po['expected_delivery_date'] ? htmlspecialchars(date('Y-m-d', strtotime($po['expected_delivery_date']))) : '-'; ?></td>
                                    <td><?php echo htmlspecialchars(isset($po['total_amount']) ? number_format($po['total_amount'], 2) : '0.00'); ?></td>
                                    <td>
                                        <?php
                                        $status_badge = 'secondary';
                                        if ($po['status'] == 'Ordered') $status_badge = 'info';
                                        elseif ($po['status'] == 'Received') $status_badge = 'success';
                                        elseif ($po['status'] == 'Partially Received') $status_badge = 'primary';
                                        elseif ($po['status'] == 'Canceled') $status_badge = 'danger';
                                        elseif ($po['status'] == 'Draft') $status_badge = 'light';
                                        ?>
                                        <span class="badge badge-<?php echo $status_badge; ?>"><?php echo htmlspecialchars($po['status']); ?></span>
                                    </td>
                                    <td>
                                        <a href="<?php echo site_url('admin/view-po/?id=' . $po['id'], $app_base_path); ?>" class="btn btn-sm btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($po['status'] == 'Draft' || $po['status'] == 'Ordered'): // Allow edit only for Draft or Ordered status ?>
                                        <a href="<?php echo site_url('admin/manage-po/?id=' . $po['id'], $app_base_path); ?>" class="btn btn-sm btn-warning" title="Edit PO">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($po['status'] == 'Draft'): // Allow delete only for Draft status ?>
                                        <a href="<?php echo site_url('admin/manage-po/?action=delete&id=' . $po['id'], $app_base_path); ?>"
                                           class="btn btn-sm btn-danger" title="Delete PO"
                                           onclick="return confirm('Are you sure you want to delete this draft purchase order?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
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
<?php // Optional: DataTables for purchaseOrdersTable ?>
