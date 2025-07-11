<?php
// This template is included by modules/purchases/index.php when $page_action is 'create_po' or 'edit_po'
// It has access to:
// $page_action ('create_po' or 'edit_po')
// $po_id (for edit_po mode)
// $po_data (array of current PO values for the form, including items)
// $form_errors (array of errors)
// $base_module_url (for form action and cancel link)
// $products_list (array of products for dropdown)
// $suppliers_list (array of suppliers for dropdown)

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}

$form_mode = ($page_action === 'edit_po') ? 'Edit' : 'Create';
$submit_button_text = ($page_action === 'edit_po') ? 'Save Changes' : 'Create Purchase Order';

// Currency symbol from settings
$currency_symbol = '$';
$sql_currency_form = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_form = mysqli_query($conn, $sql_currency_form);
if ($res_currency_form && mysqli_num_rows($res_currency_form) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_form)['setting_value']);
    mysqli_free_result($res_currency_form);
}

// Helper to get value safely and htmlspecialchars it
function val_po($data_array, $key, $default = '') {
    return isset($data_array[$key]) ? htmlspecialchars($data_array[$key]) : htmlspecialchars($default);
}
function val_po_num($data_array, $key, $default = '0.00') {
    return isset($data_array[$key]) ? htmlspecialchars(number_format((float)$data_array[$key], 2, '.', '')) : htmlspecialchars(number_format((float)$default, 2, '.', ''));
}

$po_items = isset($po_data['items']) && is_array($po_data['items']) ? $po_data['items'] : [];

?>
<div class="container-fluid mt-4">
    <h2><?php echo $form_mode; ?> Purchase Order</h2>

    <form action="<?php echo htmlspecialchars($base_module_url . '&sub_action=' . $page_action . ($po_id ? '&id='.$po_id : '')); ?>" method="POST" novalidate id="poForm">
        <input type="hidden" name="form_action" value="save_po">
        <?php if ($page_action === 'edit_po'): ?>
            <input type="hidden" name="po_id" value="<?php echo htmlspecialchars($po_id); ?>">
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header">PO Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="po_number">PO Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($form_errors['po_number']) ? 'is-invalid' : ''; ?>"
                                   id="po_number" name="po_number" value="<?php echo val_po($po_data, 'po_number'); ?>" required>
                            <?php if (isset($form_errors['po_number'])): ?><div class="invalid-feedback"><?php echo $form_errors['po_number']; ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="supplier_id">Supplier <span class="text-danger">*</span></label>
                            <select class="form-control <?php echo isset($form_errors['supplier_id']) ? 'is-invalid' : ''; ?>" id="supplier_id" name="supplier_id" required>
                                <option value="">-- Select Supplier --</option>
                                <?php foreach ($suppliers_list as $supplier): ?>
                                    <option value="<?php echo htmlspecialchars($supplier['id']); ?>" <?php echo (val_po($po_data, 'supplier_id') == $supplier['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($supplier['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($form_errors['supplier_id'])): ?><div class="invalid-feedback"><?php echo $form_errors['supplier_id']; ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                         <div class="form-group">
                            <label for="status">Status <span class="text-danger">*</span></label>
                            <select class="form-control <?php echo isset($form_errors['status']) ? 'is-invalid' : ''; ?>" id="status" name="status" required>
                                <option value="draft" <?php echo (val_po($po_data, 'status', 'draft') == 'draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="ordered" <?php echo (val_po($po_data, 'status') == 'ordered') ? 'selected' : ''; ?>>Ordered</option>
                                <?php if ($page_action === 'edit_po'): // More statuses available when editing ?>
                                <option value="partially_received" <?php echo (val_po($po_data, 'status') == 'partially_received') ? 'selected' : ''; ?> disabled>Partially Received (via Goods Receipt)</option>
                                <option value="received" <?php echo (val_po($po_data, 'status') == 'received') ? 'selected' : ''; ?> disabled>Received (via Goods Receipt)</option>
                                <option value="canceled" <?php echo (val_po($po_data, 'status') == 'canceled') ? 'selected' : ''; ?>>Canceled</option>
                                <?php endif; ?>
                            </select>
                            <?php if (isset($form_errors['status'])): ?><div class="invalid-feedback"><?php echo $form_errors['status']; ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="purchase_date">Purchase Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control <?php echo isset($form_errors['purchase_date']) ? 'is-invalid' : ''; ?>"
                                   id="purchase_date" name="purchase_date" value="<?php echo val_po($po_data, 'purchase_date', date('Y-m-d')); ?>" required>
                            <?php if (isset($form_errors['purchase_date'])): ?><div class="invalid-feedback"><?php echo $form_errors['purchase_date']; ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="expected_delivery_date">Expected Delivery Date</label>
                            <input type="date" class="form-control <?php echo isset($form_errors['expected_delivery_date']) ? 'is-invalid' : ''; ?>"
                                   id="expected_delivery_date" name="expected_delivery_date" value="<?php echo val_po($po_data, 'expected_delivery_date'); ?>">
                            <?php if (isset($form_errors['expected_delivery_date'])): ?><div class="invalid-feedback"><?php echo $form_errors['expected_delivery_date']; ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo val_po($po_data, 'notes'); ?></textarea>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                Items
                <?php if (isset($form_errors['items'])): ?><span class="text-danger ml-2"><?php echo $form_errors['items']; ?></span><?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm" id="poItemsTable">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 40%;">Product <span class="text-danger">*</span></th>
                                <th style="width: 15%;">Quantity <span class="text-danger">*</span></th>
                                <th style="width: 20%;">Purchase Price (<?php echo $currency_symbol; ?>) <span class="text-danger">*</span></th>
                                <th style="width: 20%;" class="text-right">Line Total (<?php echo $currency_symbol; ?>)</th>
                                <th style="width: 5%;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="poItemsTbody">
                            <?php if (empty($po_items)): ?>
                            <tr class="po-item-row">
                                <td>
                                    <select name="item_product_id[]" class="form-control product-select" required>
                                        <option value="">-- Select Product --</option>
                                        <?php foreach ($products_list as $product): ?>
                                            <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['purchase_price']; ?>">
                                                <?php echo htmlspecialchars($product['name'] . " (SKU: " . $product['sku'] . ")"); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" name="item_quantity[]" class="form-control item-quantity" value="1" min="1" required></td>
                                <td><input type="number" name="item_purchase_price[]" class="form-control item-price" value="0.00" step="0.01" min="0" required></td>
                                <td class="text-right item-line-total">0.00</td>
                                <td><button type="button" class="btn btn-danger btn-sm remove-po-item-btn"><i class="fas fa-trash"></i></button></td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($po_items as $item): ?>
                                <tr class="po-item-row">
                                    <td>
                                        <select name="item_product_id[]" class="form-control product-select" required>
                                            <option value="">-- Select Product --</option>
                                            <?php foreach ($products_list as $product): ?>
                                                <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['purchase_price']; ?>" <?php echo ($item['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($product['name'] . " (SKU: " . $product['sku'] . ")"); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" name="item_quantity[]" class="form-control item-quantity" value="<?php echo val_po($item, 'quantity_ordered', '1'); ?>" min="1" required></td>
                                    <td><input type="number" name="item_purchase_price[]" class="form-control item-price" value="<?php echo val_po_num($item, 'purchase_price_per_item', '0.00'); ?>" step="0.01" min="0" required></td>
                                    <td class="text-right item-line-total"><?php echo number_format(floatval(val_po($item, 'quantity_ordered', '1')) * floatval(val_po_num($item, 'purchase_price_per_item', '0.00')), 2, '.', ''); ?></td>
                                    <td><button type="button" class="btn btn-danger btn-sm remove-po-item-btn"><i class="fas fa-trash"></i></button></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" id="addPoItemBtn" class="btn btn-info btn-sm"><i class="fas fa-plus"></i> Add Item</button>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Summary</div>
            <div class="card-body">
                <div class="row justify-content-end">
                    <div class="col-md-5">
                        <table class="table table-sm">
                            <tr>
                                <th>Subtotal:</th>
                                <td class="text-right" id="poSubtotal"><?php echo $currency_symbol; ?>0.00</td>
                            </tr>
                            <tr>
                                <th>Shipping Cost:</th>
                                <td class="text-right">
                                    <input type="number" name="shipping_cost" id="shipping_cost" class="form-control form-control-sm text-right" value="<?php echo val_po_num($po_data, 'shipping_cost'); ?>" step="0.01" min="0">
                                </td>
                            </tr>
                             <tr>
                                <th>Other Charges:</th>
                                <td class="text-right">
                                    <input type="number" name="other_charges" id="other_charges" class="form-control form-control-sm text-right" value="<?php echo val_po_num($po_data, 'other_charges'); ?>" step="0.01" min="0">
                                </td>
                            </tr>
                            <tr>
                                <th>Discount:</th>
                                <td class="text-right">
                                    <input type="number" name="discount_amount" id="discount_amount" class="form-control form-control-sm text-right" value="<?php echo val_po_num($po_data, 'discount_amount'); ?>" step="0.01" min="0">
                                </td>
                            </tr>
                            <tr class="font-weight-bold">
                                <th>Total Amount:</th>
                                <td class="text-right" id="poGrandTotal"><?php echo $currency_symbol; ?>0.00</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group mt-4 text-right">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> <?php echo $submit_button_text; ?>
            </button>
            <a href="<?php echo htmlspecialchars($base_module_url); ?>" class="btn btn-secondary btn-lg">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('poItemsTbody');
    const addBtn = document.getElementById('addPoItemBtn');
    const productOptions = `
        <option value="">-- Select Product --</option>
        <?php foreach ($products_list as $product): ?>
            <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['purchase_price']; ?>">
                <?php echo htmlspecialchars($product['name'] . " (SKU: " . $product['sku'] . ")"); ?>
            </option>
        <?php endforeach; ?>`;

    function addRow() {
        const newRow = document.createElement('tr');
        newRow.classList.add('po-item-row');
        newRow.innerHTML = `
            <td>
                <select name="item_product_id[]" class="form-control product-select" required>${productOptions}</select>
            </td>
            <td><input type="number" name="item_quantity[]" class="form-control item-quantity" value="1" min="1" required></td>
            <td><input type="number" name="item_purchase_price[]" class="form-control item-price" value="0.00" step="0.01" min="0" required></td>
            <td class="text-right item-line-total">0.00</td>
            <td><button type="button" class="btn btn-danger btn-sm remove-po-item-btn"><i class="fas fa-trash"></i></button></td>
        `;
        tbody.appendChild(newRow);
        attachRowListeners(newRow);
    }

    function attachRowListeners(row) {
        const productSelect = row.querySelector('.product-select');
        const quantityInput = row.querySelector('.item-quantity');
        const priceInput = row.querySelector('.item-price');
        const removeBtn = row.querySelector('.remove-po-item-btn');

        productSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.dataset.price || '0.00';
            priceInput.value = parseFloat(price).toFixed(2);
            updateLineTotal(row);
            updateTotals();
        });

        quantityInput.addEventListener('input', function() { updateLineTotal(row); updateTotals(); });
        priceInput.addEventListener('input', function() { updateLineTotal(row); updateTotals(); });

        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                if (tbody.querySelectorAll('tr.po-item-row').length > 1) {
                    row.remove();
                } else {
                    alert('At least one item is required in the Purchase Order.');
                }
                updateTotals();
            });
        }
    }

    function updateLineTotal(row) {
        const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const lineTotalCell = row.querySelector('.item-line-total');
        lineTotalCell.textContent = (quantity * price).toFixed(2);
    }

    function updateTotals() {
        let subtotal = 0;
        tbody.querySelectorAll('tr.po-item-row').forEach(function(row) {
            const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            subtotal += quantity * price;
        });
        document.getElementById('poSubtotal').textContent = '<?php echo $currency_symbol; ?>' + subtotal.toFixed(2);

        const shipping = parseFloat(document.getElementById('shipping_cost').value) || 0;
        const otherCharges = parseFloat(document.getElementById('other_charges').value) || 0;
        const discount = parseFloat(document.getElementById('discount_amount').value) || 0;

        const grandTotal = subtotal + shipping + otherCharges - discount;
        document.getElementById('poGrandTotal').textContent = '<?php echo $currency_symbol; ?>' + grandTotal.toFixed(2);
    }

    // Initial setup for existing rows
    tbody.querySelectorAll('tr.po-item-row').forEach(row => {
        attachRowListeners(row);
         // Trigger change on product select to populate price if editing existing PO
        const productSelect = row.querySelector('.product-select');
        if (productSelect.value) { // If a product is already selected
             const selectedOption = productSelect.options[productSelect.selectedIndex];
             const price = selectedOption.dataset.price || '0.00';
             // If price field is empty or 0.00, populate it. Otherwise, keep existing value.
             const priceInput = row.querySelector('.item-price');
             if(parseFloat(priceInput.value) == 0 && price != '0.00'){
                // priceInput.value = parseFloat(price).toFixed(2); // This was causing issues on edit if price was manually set
             }
        }
        updateLineTotal(row); // Calculate line total for existing items
    });
    updateTotals(); // Calculate overall totals for existing form

    addBtn.addEventListener('click', addRow);

    // Listen to changes on summary fields
    document.getElementById('shipping_cost').addEventListener('input', updateTotals);
    document.getElementById('other_charges').addEventListener('input', updateTotals);
    document.getElementById('discount_amount').addEventListener('input', updateTotals);

});
</script>
