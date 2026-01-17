<?php
$page_title = "Purchase Returns";
$app_base_path = '/'; // Adjust if your application is in a subdirectory.

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

// Fetch purchase returns
$purchase_returns = [];
$sql = "SELECT
            pr.id, pr.debit_note_no, pr.return_date, pr.total_return_amount,
            p.po_number as original_po_number, p.id as original_po_id,
            s.name as supplier_name,
            u.username as returned_by_user
        FROM purchase_returns pr
        LEFT JOIN purchases p ON pr.original_purchase_id = p.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id  -- Assuming you add supplier_id to purchase_returns table
        JOIN users u ON pr.user_id = u.id
        ORDER BY pr.return_date DESC, pr.id DESC";
// Note: Need to add supplier_id to purchase_returns table or get it via purchases table.
// If getting via purchases table (p.supplier_id), then the JOIN to suppliers should be ON p.supplier_id = s.id

// Let's assume for now we get supplier via the original purchase order.
// If a purchase return can exist WITHOUT an original_purchase_id, then supplier_id on purchase_returns is essential.
// For now, sticking to original_purchase_id link for supplier.
$sql_alternative_supplier = "SELECT
            pr.id, pr.debit_note_no, pr.return_date, pr.total_return_amount,
            p.po_number as original_po_number, p.id as original_po_id,
            s_po.name as supplier_name, -- Supplier from original PO
            u.username as returned_by_user
        FROM purchase_returns pr
        LEFT JOIN purchases p ON pr.original_purchase_id = p.id
        LEFT JOIN suppliers s_po ON p.supplier_id = s_po.id
        JOIN users u ON pr.user_id = u.id
        ORDER BY pr.return_date DESC, pr.id DESC";

// Using the alternative SQL that gets supplier from the linked PO
$result = $mysqli->query($sql_alternative_supplier);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $purchase_returns[] = $row;
    }
    $result->free();
} else {
    $_SESSION['flash_message'] = "Error fetching purchase returns: " . htmlspecialchars($mysqli->error);
    $_SESSION['flash_message_type'] = "danger";
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
        <a href="<?php echo site_url('admin/manage-purchase-return', $app_base_path); ?>" class="btn btn-primary">
            <i class="fas fa-undo"></i> Create New Purchase Return
        </a>
    </div>

    <?php if (empty($purchase_returns) && empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-info">No purchase returns found.</div>
    <?php elseif (!empty($purchase_returns)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Purchase Return List</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="purchaseReturnsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Return ID</th>
                                <th>Debit Note #</th>
                                <th>Return Date</th>
                                <th>Original PO</th>
                                <th>Supplier</th>
                                <th>Total Amount</th>
                                <th>Processed By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchase_returns as $pr_item): ?>
                                <tr>
                                    <td>PR-<?php echo htmlspecialchars($pr_item['id']); ?></td>
                                    <td><?php echo htmlspecialchars($pr_item['debit_note_no'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($pr_item['return_date']))); ?></td>
                                    <td>
                                        <?php if ($pr_item['original_po_id']): ?>
                                            <a href="<?php echo site_url('admin/view-po/?id=' . $pr_item['original_po_id'], $app_base_path); ?>">
                                                <?php echo htmlspecialchars($pr_item['original_po_number'] ?: 'PO-' . $pr_item['original_po_id']); ?>
                                            </a>
                                        <?php else: echo 'N/A'; endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($pr_item['supplier_name'] ?: 'N/A'); ?></td>
                                    <td class="text-right"><?php echo htmlspecialchars(number_format($pr_item['total_return_amount'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars($pr_item['returned_by_user']); ?></td>
                                    <td>
                                        <a href="<?php echo site_url('admin/manage-purchase-return/?id=' . $pr_item['id'], $app_base_path); ?>" class="btn btn-sm btn-info" title="View Details / Edit">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <!-- Delete functionality for returns might be tricky depending on accounting implications -->
                                        <!--
                                        <a href="<?php echo site_url('admin/manage-purchase-return/?action=delete&id=' . $pr_item['id'], $app_base_path); ?>"
                                           class="btn btn-sm btn-danger" title="Delete Return"
                                           onclick="return confirm('Are you sure you want to delete this purchase return? This may affect stock levels and accounting.');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        -->
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
<?php // Optional: DataTables for purchaseReturnsTable ?>
