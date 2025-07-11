<?php
// This template is included by modules/purchases/index.php when $page_action is 'list_po'
// It has access to $purchase_orders array and $base_module_url

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}

// Currency symbol from settings
$currency_symbol = '$';
$sql_currency = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency = mysqli_query($conn, $sql_currency);
if ($res_currency && mysqli_num_rows($res_currency) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency)['setting_value']);
    mysqli_free_result($res_currency);
}

function get_po_status_badge($status) {
    $badge_class = 'secondary';
    switch (strtolower($status)) {
        case 'draft': $badge_class = 'light'; break;
        case 'ordered': $badge_class = 'info'; break;
        case 'partially_received': $badge_class = 'primary'; break;
        case 'received': $badge_class = 'success'; break;
        case 'canceled': $badge_class = 'danger'; break;
    }
    return '<span class="badge badge-' . $badge_class . '">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) . '</span>';
}
?>

<div class="container-fluid mt-4"> <!-- Use container-fluid for wider tables -->
    <div class="row mb-3">
        <div class="col">
            <h2>Manage Purchase Orders</h2>
        </div>
        <div class="col text-right">
            <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=create_po'); ?>" class="btn btn-success">
                <i class="fas fa-plus"></i> Create New Purchase Order
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <?php if (!empty($purchase_orders)): ?>
            <table class="table table-striped table-bordered table-hover dataTable">
                <thead class="thead-dark">
                    <tr>
                        <th>PO ID</th>
                        <th>PO Number</th>
                        <th>Supplier</th>
                        <th>Purchase Date</th>
                        <th>Expected Delivery</th>
                        <th class="text-right">Total Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchase_orders as $po): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($po['id']); ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=view_po&id=' . $po['id']); ?>">
                                    <?php echo htmlspecialchars($po['po_number']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($po['purchase_date']))); ?></td>
                            <td><?php echo $po['expected_delivery_date'] ? htmlspecialchars(date('Y-m-d', strtotime($po['expected_delivery_date']))) : '-'; ?></td>
                            <td class="text-right"><?php echo $currency_symbol . number_format($po['total_amount'], 2); ?></td>
                            <td><?php echo get_po_status_badge($po['status']); ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=view_po&id=' . $po['id']); ?>" class="btn btn-sm btn-primary" title="View PO">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($po['status'] === 'draft' || $po['status'] === 'ordered'): // Can edit draft or ordered POs ?>
                                <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=edit_po&id=' . $po['id']); ?>" class="btn btn-sm btn-info" title="Edit PO">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($po['status'] === 'draft' || $po['status'] === 'canceled'): // Can delete draft or canceled POs ?>
                                <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=delete_po&id=' . $po['id']); ?>"
                                   class="btn btn-sm btn-danger" title="Delete PO"
                                   onclick="return confirm('Are you sure you want to delete this Purchase Order? This action cannot be undone.');">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                                 <?php if ($po['status'] === 'ordered' || $po['status'] === 'partially_received'): ?>
                                     <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=receive_goods&id=' . $po['id']); ?>" class="btn btn-sm btn-warning" title="Receive Goods for this PO">
                                        <i class="fas fa-truck-loading"></i> Receive
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                No purchase orders found. <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=create_po'); ?>">Create the first PO!</a>
            </div>
        <?php endif; ?>
    </div>
</div>
