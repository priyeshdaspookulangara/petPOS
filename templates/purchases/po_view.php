<?php
// This template is included by modules/purchases/index.php when $page_action is 'view_po'
// It has access to $po_to_view (array with PO details and items) and $base_module_url

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}

// Currency symbol from settings
$currency_symbol = '$';
$sql_currency_view = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_view = mysqli_query($conn, $sql_currency_view);
if ($res_currency_view && mysqli_num_rows($res_currency_view) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_view)['setting_value']);
    mysqli_free_result($res_currency_view);
}

function get_po_status_badge_view($status) { // Copied from po_list, can be global
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

$po_items_view = isset($po_to_view['items']) && is_array($po_to_view['items']) ? $po_to_view['items'] : [];
?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2>Purchase Order #<?php echo htmlspecialchars($po_to_view['po_number']); ?></h2>
            <p>Status: <?php echo get_po_status_badge_view($po_to_view['status']); ?></p>
        </div>
        <div class="col-md-4 text-right">
            <a href="<?php echo htmlspecialchars($base_module_url); ?>" class="btn btn-secondary"><i class="fas fa-list"></i> Back to List</a>
            <?php if ($po_to_view['status'] === 'draft' || $po_to_view['status'] === 'ordered'): ?>
            <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=edit_po&id=' . $po_to_view['id']); ?>" class="btn btn-info"><i class="fas fa-edit"></i> Edit PO</a>
            <?php endif; ?>
            <?php if ($po_to_view['status'] === 'ordered' || $po_to_view['status'] === 'partially_received'): ?>
            <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=receive_goods&id=' . $po_to_view['id']); ?>" class="btn btn-warning"><i class="fas fa-truck-loading"></i> Receive Goods</a>
            <?php endif; ?>
            <!-- Print PO Button (Placeholder) -->
            <!-- <button type="button" class="btn btn-outline-primary" onclick="window.print();"><i class="fas fa-print"></i> Print PO</button> -->
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            Purchase Order Details
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Supplier Details:</h5>
                    <p>
                        <strong><?php echo htmlspecialchars($po_to_view['supplier_name']); ?></strong><br>
                        <?php if($po_to_view['supplier_address']): ?>
                            <?php echo nl2br(htmlspecialchars($po_to_view['supplier_address'])); ?><br>
                        <?php endif; ?>
                        <?php if($po_to_view['supplier_email']): ?>
                            Email: <?php echo htmlspecialchars($po_to_view['supplier_email']); ?><br>
                        <?php endif; ?>
                        <?php if($po_to_view['supplier_phone']): ?>
                            Phone: <?php echo htmlspecialchars($po_to_view['supplier_phone']); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-6">
                    <h5>PO Information:</h5>
                    <p>
                        <strong>PO Number:</strong> <?php echo htmlspecialchars($po_to_view['po_number']); ?><br>
                        <strong>Purchase Date:</strong> <?php echo htmlspecialchars(date('F j, Y', strtotime($po_to_view['purchase_date']))); ?><br>
                        <strong>Expected Delivery:</strong> <?php echo $po_to_view['expected_delivery_date'] ? htmlspecialchars(date('F j, Y', strtotime($po_to_view['expected_delivery_date']))) : 'N/A'; ?><br>
                        <strong>Created By:</strong> <?php echo htmlspecialchars($po_to_view['created_by_username']); ?><br>
                        <strong>Created At:</strong> <?php echo htmlspecialchars(date('F j, Y H:i', strtotime($po_to_view['created_at']))); ?><br>
                        <?php if ($po_to_view['notes']): ?>
                            <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($po_to_view['notes'])); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <hr>
            <h5>Items Ordered:</h5>
            <?php if (!empty($po_items_view)): ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th class="text-right">Qty Ordered</th>
                            <th class="text-right">Purchase Price</th>
                            <th class="text-right">Line Total</th>
                            <th class="text-right">Qty Received</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $item_num = 1; foreach ($po_items_view as $item): ?>
                        <tr>
                            <td><?php echo $item_num++; ?></td>
                            <td><?php echo htmlspecialchars($item['product_sku']); ?></td>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td class="text-right"><?php echo htmlspecialchars($item['quantity_ordered']); ?></td>
                            <td class="text-right"><?php echo $currency_symbol . number_format($item['purchase_price_per_item'], 2); ?></td>
                            <td class="text-right"><?php echo $currency_symbol . number_format($item['line_total'], 2); ?></td>
                            <td class="text-right"><?php echo htmlspecialchars($item['quantity_received']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted">No items found for this purchase order.</p>
            <?php endif; ?>

            <hr>
            <div class="row justify-content-end">
                <div class="col-md-5">
                    <table class="table table-sm">
                        <tr>
                            <th>Subtotal:</th>
                            <td class="text-right"><?php echo $currency_symbol . number_format($po_to_view['sub_total'], 2); ?></td>
                        </tr>
                        <?php if ($po_to_view['shipping_cost'] > 0): ?>
                        <tr>
                            <th>Shipping Cost:</th>
                            <td class="text-right"><?php echo $currency_symbol . number_format($po_to_view['shipping_cost'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($po_to_view['other_charges'] > 0): ?>
                        <tr>
                            <th>Other Charges:</th>
                            <td class="text-right"><?php echo $currency_symbol . number_format($po_to_view['other_charges'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($po_to_view['discount_amount'] > 0): ?>
                        <tr>
                            <th>Discount:</th>
                            <td class="text-right text-danger">-<?php echo $currency_symbol . number_format($po_to_view['discount_amount'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="font-weight-bold table-info">
                            <th>Total Amount:</th>
                            <td class="text-right"><?php echo $currency_symbol . number_format($po_to_view['total_amount'], 2); ?></td>
                        </tr>
                         <tr>
                            <th>Amount Paid:</th>
                            <td class="text-right"><?php echo $currency_symbol . number_format($po_to_view['amount_paid'], 2); ?></td>
                        </tr>
                        <tr class="font-weight-bold <?php echo ($po_to_view['total_amount'] - $po_to_view['amount_paid'] > 0) ? 'table-danger' : 'table-success'; ?>">
                            <th>Balance Due:</th>
                            <td class="text-right"><?php echo $currency_symbol . number_format($po_to_view['total_amount'] - $po_to_view['amount_paid'], 2); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php if ($po_to_view['status'] !== 'received' && $po_to_view['status'] !== 'canceled'): ?>
            <hr>
            <div class="row">
                <div class="col-md-12">
                    <h5>Actions:</h5>
                    <form action="<?php echo htmlspecialchars($base_module_url . '&sub_action=index&po_id=' . $po_to_view['id']); ?>" method="POST" class="form-inline">
                        <input type="hidden" name="form_action" value="update_po_status">
                        <input type="hidden" name="id" value="<?php echo $po_to_view['id']; ?>">
                        <div class="form-group mb-2">
                            <label for="new_status_<?php echo $po_to_view['id']; ?>" class="sr-only">New Status</label>
                            <select name="new_status" id="new_status_<?php echo $po_to_view['id']; ?>" class="form-control mr-2">
                                <?php if ($po_to_view['status'] === 'draft'): ?>
                                    <option value="ordered">Mark as Ordered</option>
                                <?php endif; ?>
                                <?php if ($po_to_view['status'] !== 'canceled' && $po_to_view['status'] !== 'received'): ?>
                                    <option value="canceled">Mark as Canceled</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary mb-2" <?php echo ($po_to_view['status'] === 'received' || $po_to_view['status'] === 'canceled') ? 'disabled' : ''; ?>>Update Status</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
