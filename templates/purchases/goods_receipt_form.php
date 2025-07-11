<?php
// This template is included by modules/purchases/index.php when $page_action is 'receive_goods'
// It has access to $po_for_receipt (array with PO details and items) and $base_module_url

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}

// Currency symbol from settings
$currency_symbol = '$';
$sql_currency_gr = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_gr = mysqli_query($conn, $sql_currency_gr);
if ($res_currency_gr && mysqli_num_rows($res_currency_gr) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_gr)['setting_value']);
    mysqli_free_result($res_currency_gr);
}

$po_items_receipt = isset($po_for_receipt['items']) && is_array($po_for_receipt['items']) ? $po_for_receipt['items'] : [];
?>

<div class="container-fluid mt-4">
    <h2>Receive Goods for PO #<?php echo htmlspecialchars($po_for_receipt['po_number']); ?></h2>
    <p>Supplier: <strong><?php echo htmlspecialchars($po_for_receipt['supplier_name']); ?></strong></p>
    <p>PO Date: <?php echo htmlspecialchars(date('Y-m-d', strtotime($po_for_receipt['purchase_date']))); ?></p>
    <p>PO Status: <span class="badge badge-info"><?php echo htmlspecialchars(ucfirst($po_for_receipt['status'])); ?></span></p>

    <form action="<?php echo htmlspecialchars($base_module_url . '&sub_action=process_goods_receipt'); ?>" method="POST" id="goodsReceiptForm">
        <input type="hidden" name="po_id_receipt" value="<?php echo htmlspecialchars($po_for_receipt['id']); ?>">

        <div class="card mb-3">
            <div class="card-header">Items to Receive</div>
            <div class="card-body">
                <?php if (!empty($po_items_receipt)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>SKU</th>
                                <th>Product Name</th>
                                <th class="text-right">Ordered</th>
                                <th class="text-right">Already Received</th>
                                <th class="text-right">Pending</th>
                                <th style="width: 15%;">Qty Received Now <span class="text-danger">*</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($po_items_receipt as $item):
                                $pending_qty = $item['quantity_ordered'] - $item['quantity_received'];
                                if ($pending_qty < 0) $pending_qty = 0; // Should not happen
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_sku']); ?></td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($item['quantity_ordered']); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($item['quantity_received']); ?></td>
                                <td class="text-right font-weight-bold <?php echo ($pending_qty > 0) ? 'text-primary' : 'text-success'; ?>">
                                    <?php echo htmlspecialchars($pending_qty); ?>
                                </td>
                                <td>
                                    <?php if ($pending_qty > 0): ?>
                                    <input type="number" name="quantity_received[<?php echo $item['id']; // purchase_item_id ?>]"
                                           class="form-control form-control-sm text-right item-qty-received"
                                           value="0" min="0" max="<?php echo $pending_qty; ?>" required>
                                    <?php else: ?>
                                    <input type="number" class="form-control form-control-sm text-right" value="0" disabled>
                                    <small class="text-success">Fully Received</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted">No items found for this purchase order to receive.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($po_items_receipt) && array_sum(array_map(function($item){ return $item['quantity_ordered'] - $item['quantity_received']; }, $po_items_receipt)) > 0 ): // Check if any item has pending qty ?>
        <div class="card mb-3">
            <div class="card-header">Receipt Details</div>
            <div class="card-body">
                 <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="received_date">Received Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="received_date" name="received_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="receipt_notes">Notes (Optional)</label>
                    <textarea class="form-control" id="receipt_notes" name="receipt_notes" rows="2"></textarea>
                    <small class="form-text text-muted">e.g., Supplier Invoice #, condition of goods.</small>
                </div>
            </div>
        </div>

        <div class="form-group mt-4 text-right">
            <button type="submit" class="btn btn-success btn-lg" id="submitGoodsReceiptBtn">
                <i class="fas fa-check-circle"></i> Confirm Goods Received
            </button>
            <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=view_po&id=' . $po_for_receipt['id']); ?>" class="btn btn-secondary btn-lg">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
        <?php else: ?>
             <div class="alert alert-success">All items for this PO have been fully received.</div>
              <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=view_po&id=' . $po_for_receipt['id']); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to PO View
            </a>
        <?php endif; ?>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('goodsReceiptForm');
    const submitBtn = document.getElementById('submitGoodsReceiptBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', function(event) {
            let totalReceivedNow = 0;
            const qtyInputs = form.querySelectorAll('.item-qty-received');
            qtyInputs.forEach(function(input) {
                totalReceivedNow += parseInt(input.value) || 0;
            });

            if (totalReceivedNow === 0) {
                if (!confirm('You have not entered any quantities to receive for this batch. Do you want to proceed with receiving zero items? This will not update stock or PO status.')) {
                    event.preventDefault(); // Stop form submission
                    return false;
                }
            }
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        });

        // Validate quantity inputs not to exceed pending
        const qtyInputs = form.querySelectorAll('.item-qty-received');
        qtyInputs.forEach(function(input) {
            input.addEventListener('input', function() {
                const max = parseInt(this.getAttribute('max'));
                if (parseInt(this.value) > max) {
                    this.value = max;
                    alert('Quantity received cannot exceed pending quantity (' + max + ').');
                }
                if (parseInt(this.value) < 0) {
                    this.value = 0;
                }
            });
        });
    }
});
</script>
