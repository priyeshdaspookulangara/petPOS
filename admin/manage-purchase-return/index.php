<?php
$page_title = "Manage Purchase Return";
$app_base_path = '/';

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

$return_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$edit_mode = false;
$current_user_id = get_current_user_id();

// Form field values
$original_po_id_val = '';
// $supplier_id_val = ''; // Supplier comes from Original PO
$debit_note_no_val = '';
$return_date_val = date('Y-m-d');
$reason_val = '';
$returned_items_val = [];
$total_return_amount_val = 0.00;

// Fetch Purchase Orders for dropdown
$purchase_orders_list = [];
$po_sql = "SELECT p.id, p.po_number, p.purchase_date, s.name as supplier_name, p.supplier_id
           FROM purchases p
           JOIN suppliers s ON p.supplier_id = s.id
           WHERE p.status IN ('Received', 'Partially Received', 'Ordered')
           ORDER BY p.purchase_date DESC";
$po_res = $mysqli->query($po_sql);
if ($po_res) while($row = $po_res->fetch_assoc()) $purchase_orders_list[] = $row;
if ($po_res) $po_res->free();

// Fetch all products for manual item addition if needed (though primarily items from selected PO)
$products_list_json = [];
$prod_sql = "SELECT id, name, sku, purchase_price FROM products ORDER BY name ASC"; // Using purchase_price as default return price
$prod_res = $mysqli->query($prod_sql);
if ($prod_res) while($row = $prod_res->fetch_assoc()) $products_list_json[] = $row;
if ($prod_res) $prod_res->free();
$products_list_json_encoded = json_encode($products_list_json);


if ($return_id) {
    $edit_mode = true;
    $page_title = "View Purchase Return Details (PR-$return_id)";

    $sql_pr_corrected = "SELECT pr.*, p.po_number as original_po_number, s_from_p.name as supplier_name_from_po, p.supplier_id as supplier_id_from_po
                         FROM purchase_returns pr
                         JOIN purchases p ON pr.original_purchase_id = p.id -- Must have original_purchase_id
                         JOIN suppliers s_from_p ON p.supplier_id = s_from_p.id
                         WHERE pr.id = $return_id";

    $pr_res = $mysqli->query($sql_pr_corrected);
    if ($pr_res && $pr_data = $pr_res->fetch_assoc()) {
        $original_po_id_val = $pr_data['original_purchase_id'];
        $debit_note_no_val = $pr_data['debit_note_no'];
        $return_date_val = date('Y-m-d', strtotime($pr_data['return_date']));
        $reason_val = $pr_data['reason'];
        $total_return_amount_val = $pr_data['total_return_amount'];

        $sql_pr_items = "SELECT pri.*, prod.name as product_name, prod.sku as product_sku
                         FROM purchase_return_items pri
                         JOIN products prod ON pri.product_id = prod.id
                         WHERE pri.purchase_return_id = $return_id";
        $pr_items_res = $mysqli->query($sql_pr_items);
        if ($pr_items_res) {
            while($item = $pr_items_res->fetch_assoc()) {
                $returned_items_val[] = $item;
            }
            $pr_items_res->free();
        }
    } else {
        $_SESSION['flash_message'] = "Purchase Return not found.";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: " . site_url('admin/purchase-returns', $app_base_path));
        exit;
    }
    if($pr_res) $pr_res->free();
} else {
    $page_title = "Create New Purchase Return";
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$edit_mode) {
    $form_original_po_id = isset($_POST['original_po_id']) ? (int)$_POST['original_po_id'] : null;
    $form_debit_note_no = isset($_POST['debit_note_no']) ? sanitize_input($mysqli, $_POST['debit_note_no']) : null;
    $form_return_date = isset($_POST['return_date']) ? sanitize_input($mysqli, $_POST['return_date']) : date('Y-m-d');
    $form_reason = isset($_POST['reason']) ? sanitize_input($mysqli, $_POST['reason']) : '';
    $form_pr_items = isset($_POST['pr_items']) ? $_POST['pr_items'] : [];
    $form_total_return_amount = 0;

    // --- Validation ---
    if (empty($form_original_po_id)) {
        $_SESSION['flash_message'] = "Original Purchase Order is required to process a return.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif (empty($form_return_date)) {
        $_SESSION['flash_message'] = "Return Date is required.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif (empty($form_pr_items) || !is_array($form_pr_items)) {
        $_SESSION['flash_message'] = "At least one item is required for the purchase return.";
        $_SESSION['flash_message_type'] = "danger";
    } else {
        $valid_items = true;
        foreach ($form_pr_items as $item) {
            if (empty($item['product_id']) || !isset($item['quantity']) || (int)$item['quantity'] <= 0 || !isset($item['price_per_item']) || (float)$item['price_per_item'] < 0) {
                $valid_items = false;
                $_SESSION['flash_message'] = "Invalid data in one or more return items. Ensure Product, Quantity (>0), and Price (>=0) are set.";
                $_SESSION['flash_message_type'] = "danger";
                break;
            }
            // Further validation: quantity returned should not exceed quantity purchased/received on original PO for that item.
            // This requires fetching original PO item details. For simplicity now, this check is basic.
            $form_total_return_amount += (int)$item['quantity'] * (float)$item['price_per_item'];
        }

        if ($valid_items) {
            $mysqli->begin_transaction();
            try {
                // Insert into purchase_returns
                // original_purchase_id is now mandatory for supplier context.
                $sql_insert_pr = "INSERT INTO purchase_returns (original_purchase_id, debit_note_no, return_date, reason, total_return_amount, user_id)
                                  VALUES ($form_original_po_id, " . ($form_debit_note_no ? "'$form_debit_note_no'" : "NULL") . ",
                                          '$form_return_date', '$form_reason', $form_total_return_amount, $current_user_id)";
                if (!$mysqli->query($sql_insert_pr)) {
                    throw new Exception("Error creating Purchase Return header: " . $mysqli->error);
                }
                $current_pr_id = $mysqli->insert_id;

                // Insert items into purchase_return_items and update product stock
                foreach ($form_pr_items as $item_data) {
                    $item_product_id = (int)$item_data['product_id'];
                    $item_quantity = (int)$item_data['quantity'];
                    $item_price = (float)$item_data['price_per_item'];
                    $item_total_val = $item_quantity * $item_price;

                    $sql_insert_pr_item = "INSERT INTO purchase_return_items (purchase_return_id, product_id, quantity, return_price_per_item, item_total_returned)
                                           VALUES ($current_pr_id, $item_product_id, $item_quantity, $item_price, $item_total_val)";
                    if (!$mysqli->query($sql_insert_pr_item)) {
                        throw new Exception("Error adding Purchase Return item (Product ID: $item_product_id): " . $mysqli->error);
                    }

                    // Update product stock (deduct returned quantity)
                    $sql_update_stock = "UPDATE products SET current_stock = current_stock - $item_quantity WHERE id = $item_product_id";
                    if (!$mysqli->query($sql_update_stock)) {
                        throw new Exception("Error updating stock for returned product ID $item_product_id: " . $mysqli->error);
                    }
                }

                $mysqli->commit();
                $_SESSION['flash_message'] = "Purchase Return (PR-$current_pr_id) processed successfully.";
                $_SESSION['flash_message_type'] = "success";
                header("Location: " . site_url('admin/purchase-returns', $app_base_path));
                exit;

            } catch (Exception $e) {
                $mysqli->rollback();
                $_SESSION['flash_message'] = "Transaction failed: " . htmlspecialchars($e->getMessage());
                $_SESSION['flash_message_type'] = "danger";
            }
        }
    }
    // Repopulate form on error
    $original_po_id_val = $form_original_po_id;
    $debit_note_no_val = $form_debit_note_no;
    $return_date_val = $form_return_date;
    $reason_val = $form_reason;
    $returned_items_val = []; // Repopulate from $form_pr_items
    foreach($form_pr_items as $fi){ $returned_items_val[] = $fi; }
    $total_return_amount_val = $form_total_return_amount;
}


include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <?php echo $edit_mode ? 'Details for Return: PR-' . $return_id : 'Create New Purchase Return'; ?>
            </h6>
            <a href="<?php echo site_url('admin/purchase-returns', $app_base_path); ?>" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
        </div>
        <div class="card-body">
            <?php if ($edit_mode): ?>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Return ID:</strong> PR-<?php echo $return_id; ?></p>
                        <p><strong>Debit Note #:</strong> <?php echo htmlspecialchars($debit_note_no_val ?: '-'); ?></p>
                        <p><strong>Return Date:</strong> <?php echo htmlspecialchars($return_date_val); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Original PO:</strong>
                            <?php if ($original_po_id_val): ?>
                                <a href="<?php echo site_url('admin/view-po/?id='.$original_po_id_val); ?>">
                                    <?php echo htmlspecialchars($pr_data['original_po_number'] ?: 'PO-'.$original_po_id_val); ?>
                                </a>
                            <?php else: echo 'N/A'; endif; ?>
                        </p>
                        <p><strong>Supplier:</strong>
                            <?php echo htmlspecialchars($pr_data['supplier_name_from_po'] ?: 'N/A'); ?>
                        </p>
                        <p><strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($reason_val ?: '-')); ?></p>
                    </div>
                </div>
                <hr>
                <h5>Returned Items:</h5>
                <?php if(!empty($returned_items_val)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>SKU</th><th>Product</th><th>Qty Returned</th><th>Price/Item</th><th>Item Total</th></tr></thead>
                        <tbody>
                        <?php
                        $calculated_total = 0;
                        foreach($returned_items_val as $item):
                            $item_total = $item['quantity'] * $item['return_price_per_item'];
                            $calculated_total += $item_total;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_sku']); ?></td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars(number_format($item['return_price_per_item'],2)); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars(number_format($item_total,2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><th colspan="4" class="text-right">Total Return Amount:</th><th class="text-right"><?php echo htmlspecialchars(number_format($total_return_amount_val,2)); ?></th></tr>
                            <?php if(abs($calculated_total - (float)$total_return_amount_val) > 0.01): ?>
                            <tr class="table-warning"><td colspan="4" class="text-right">Calculated from items:</td><td class="text-right"><?php echo number_format($calculated_total, 2); ?></td></tr>
                            <?php endif; ?>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?> <p class="text-muted">No items recorded for this return.</p> <?php endif; ?>

            <?php else: ?>
                <form id="managePurchaseReturnForm" method="POST" action="<?php echo site_url('admin/manage-purchase-return', $app_base_path); ?>">
                    <div class="form-group">
                        <label for="original_po_id">Original Purchase Order <span class="text-danger">*</span></label>
                        <select name="original_po_id" id="original_po_id" class="form-control" required>
                            <option value="">-- Select PO --</option>
                            <?php foreach($purchase_orders_list as $po): ?>
                            <option value="<?php echo $po['id']; ?>" data-supplier-id="<?php echo $po['supplier_id']; ?>" <?php if($original_po_id_val == $po['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($po['po_number'] ?: 'PO-'.$po['id']) . ' (' . htmlspecialchars($po['supplier_name']) . ' - ' . date('Y-m-d', strtotime($po['purchase_date'])) . ')'; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Select the PO you are returning items from. Supplier will be derived from this PO.</small>
                    </div>

                    <div id="supplier_info_display" class="mb-3 p-2 bg-light" style="display:none;">
                        <strong>Supplier:</strong> <span id="selected_po_supplier_name"></span>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="return_date">Return Date <span class="text-danger">*</span></label>
                            <input type="date" name="return_date" id="return_date" class="form-control" value="<?php echo htmlspecialchars($return_date_val); ?>" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="debit_note_no">Debit Note Number (Optional)</label>
                            <input type="text" name="debit_note_no" id="debit_note_no" class="form-control" value="<?php echo htmlspecialchars($debit_note_no_val); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="reason">Reason for Return</label>
                        <textarea name="reason" id="reason" class="form-control" rows="2"><?php echo htmlspecialchars($reason_val); ?></textarea>
                    </div>
                    <hr>
                    <h5 class="mb-3">Items to Return</h5>
                    <div id="pr_items_container">
                        <?php /* JS will populate this, or PHP for repopulation on error */ ?>
                        <?php if(!empty($returned_items_val) && $_SERVER['REQUEST_METHOD'] === 'POST'): // Repopulate on POST error ?>
                            <?php foreach($returned_items_val as $idx => $item_val): ?>
                                <!-- Simplified repopulation, full JS handles dynamic rows better -->
                                <div class="form-row pr-item-row align-items-end mb-2 border p-3 rounded">
                                     <input type="hidden" name="pr_items[<?php echo $idx; ?>][product_id]" value="<?php echo htmlspecialchars($item_val['product_id']); ?>">
                                     <p>Product ID: <?php echo htmlspecialchars($item_val['product_id']);?>, Qty: <?php echo htmlspecialchars($item_val['quantity']);?>, Price: <?php echo htmlspecialchars($item_val['price_per_item']);?></p>
                                     <!-- Add full fields for robust repopulation -->
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="add_pr_item_btn" class="btn btn-info btn-sm mb-3"><i class="fas fa-plus"></i> Add Item to Return</button>
                    <div class="text-right">
                        <h4>Total Return Value: <span id="pr_grand_total"><?php echo number_format((float)$total_return_amount_val,2);?></span></h4>
                    </div>
                    <hr>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-undo-alt"></i> Process Purchase Return</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<template id="pr_item_template">
    <div class="form-row pr-item-row align-items-end mb-2 border p-3 rounded">
        <div class="form-group col-md-4 mb-0">
            <label>Product <span class="text-danger">*</span></label>
            <select name="pr_items[ITEM_INDEX][product_id]" class="form-control product-select-pr" required>
                <option value="">-- Select Product --</option>
                <?php /* Options populated by JS based on selected PO or all products */ ?>
            </select>
        </div>
        <div class="form-group col-md-2 mb-0">
            <label>Qty to Return <span class="text-danger">*</span></label>
            <input type="number" name="pr_items[ITEM_INDEX][quantity]" class="form-control quantity-input-pr" min="1" value="1" required>
            <small class="form-text text-muted">Max: <span class="max-returnable-qty">?</span></small>
        </div>
        <div class="form-group col-md-2 mb-0">
            <label>Price/Item <span class="text-danger">*</span></label>
            <input type="number" name="pr_items[ITEM_INDEX][price_per_item]" step="0.01" class="form-control price-input-pr" min="0" value="0.00" required>
        </div>
        <div class="form-group col-md-2 mb-0">
            <label>Subtotal</label>
            <input type="text" class="form-control item-subtotal-pr bg-light" readonly value="0.00">
        </div>
        <div class="form-group col-md-2 mb-0 d-flex align-items-end">
            <button type="button" class="btn btn-danger btn-sm remove-pr-item-btn w-100"><i class="fas fa-trash"></i> Remove</button>
        </div>
    </div>
</template>

<script>
const allProductsData = <?php echo $products_list_json_encoded; ?>;
// This would ideally be an AJAX call or preloaded data structure mapping PO ID to its items {product_id, name, qty_purchased, price_paid}
let poItemsCache = {}; // Example: { po_id_1: [{product_id:10, name:"P1", qty_ordered:5, price:10.00}, ...], ... }

document.addEventListener('DOMContentLoaded', function() {
    const originalPoSelect = document.getElementById('original_po_id');
    const itemsContainerPr = document.getElementById('pr_items_container');
    const addItemBtnPr = document.getElementById('add_pr_item_btn');
    const grandTotalSpanPr = document.getElementById('pr_grand_total');
    const itemTemplatePrHtml = document.getElementById('pr_item_template')?.innerHTML;
    const supplierInfoDisplay = document.getElementById('supplier_info_display');
    const selectedPoSupplierNameSpan = document.getElementById('selected_po_supplier_name');
    let itemIndexPr = 0;

    function updatePrRowSubtotal() {
        const row = this.closest('.pr-item-row');
        const quantity = parseInt(row.querySelector('.quantity-input-pr').value) || 0;
        const price = parseFloat(row.querySelector('.price-input-pr').value) || 0;
        const subtotal = quantity * price;
        row.querySelector('.item-subtotal-pr').value = subtotal.toFixed(2);
        updatePrGrandTotal();
    }

    function updatePrGrandTotal() {
        let total = 0;
        itemsContainerPr.querySelectorAll('.pr-item-row').forEach(row => {
            total += parseFloat(row.querySelector('.item-subtotal-pr').value) || 0;
        });
        grandTotalSpanPr.textContent = total.toFixed(2);
    }

    function addPrItemRow(itemData = null) {
        if (!itemTemplatePrHtml) return;
        const newRowHtml = itemTemplatePrHtml.replace(/ITEM_INDEX/g, itemIndexPr);
        itemsContainerPr.insertAdjacentHTML('beforeend', newRowHtml);
        const newRowElement = itemsContainerPr.lastElementChild;
        const productSelectPr = newRowElement.querySelector('.product-select-pr');
        const quantityInputPr = newRowElement.querySelector('.quantity-input-pr');
        const priceInputPr = newRowElement.querySelector('.price-input-pr');
        const maxQtySpan = newRowElement.querySelector('.max-returnable-qty');

        // Populate product dropdown for this row
        // If a PO is selected, only show items from that PO. Otherwise, show all products.
        const selectedPoId = originalPoSelect.value;
        let productsToShow = allProductsData;

        if (selectedPoId && poItemsCache[selectedPoId]) {
            productsToShow = poItemsCache[selectedPoId].map(poItem => {
                const fullProduct = allProductsData.find(p => p.id == poItem.product_id);
                return {
                    ...fullProduct, // id, name, sku
                    purchase_price: poItem.price_per_item, // Price from PO
                    max_qty: poItem.quantity_ordered // Qty from PO
                };
            });
        }

        productSelectPr.innerHTML = '<option value="">-- Select Product --</option>'; // Clear existing
        productsToShow.forEach(product => {
            const option = document.createElement('option');
            option.value = product.id;
            option.textContent = `${product.name} ${product.sku ? '(SKU: ' + product.sku + ')' : ''}`;
            option.dataset.price = product.purchase_price || '0.00';
            option.dataset.maxQty = product.max_qty || 'N/A'; // Max qty from PO item if available
            productSelectPr.appendChild(option);
        });

        if (itemData) { // Pre-fill if editing or error repopulation
            productSelectPr.value = itemData.product_id;
            quantityInputPr.value = itemData.quantity;
            priceInputPr.value = itemData.price_per_item;
            if (itemData.max_qty) maxQtySpan.textContent = itemData.max_qty;
        } else if (productSelectPr.options.length > 1) { // Auto-select first product and set price
            productSelectPr.value = productSelectPr.options[1].value;
            priceInputPr.value = productSelectPr.options[1].dataset.price || '0.00';
            maxQtySpan.textContent = productSelectPr.options[1].dataset.maxQty || 'N/A';
        }

        newRowElement.querySelector('.remove-pr-item-btn').addEventListener('click', function() {
            this.closest('.pr-item-row').remove();
            updatePrGrandTotal();
        });

        [productSelectPr, quantityInputPr, priceInputPr].forEach(input => {
            input.addEventListener('change', updatePrRowSubtotal);
            input.addEventListener('keyup', updatePrRowSubtotal);
        });

        productSelectPr.addEventListener('change', function(){
            const selectedOption = this.options[this.selectedIndex];
            priceInputPr.value = selectedOption.dataset.price || '0.00';
            maxQtySpan.textContent = selectedOption.dataset.maxQty || 'N/A';
            updatePrRowSubtotal.call(this); // Update subtotal after price change
        });

        itemIndexPr++;
        updatePrRowSubtotal.call(newRowElement.querySelector('.product-select-pr'));
    }

    if (addItemBtnPr) {
        addItemBtnPr.addEventListener('click', function() { addPrItemRow(); });
    }

    if (originalPoSelect) {
        originalPoSelect.addEventListener('change', async function() {
            const selectedPoId = this.value;
            const selectedOption = this.options[this.selectedIndex];

            itemsContainerPr.innerHTML = ''; // Clear existing items
            itemIndexPr = 0; // Reset index for new set of items

            if (selectedPoId) {
                const supplierName = selectedOption.textContent.match(/\((.*?)\s*-/)?.[1] || 'N/A';
                selectedPoSupplierNameSpan.textContent = supplierName;
                supplierInfoDisplay.style.display = 'block';

                // Fetch items for this PO via AJAX - this is a more robust way
                // For now, using a placeholder for AJAX. If poItemsCache was preloaded, it would be used.
                // This is a simplified version; real AJAX would be:
                // fetch('ajax_get_po_items.php?po_id=' + selectedPoId)
                // .then(response => response.json())
                // .then(data => { poItemsCache[selectedPoId] = data; data.forEach(item => addPrItemRow(item)); updatePrGrandTotal(); });

                // SIMULATED: If we had a way to get items for PO (e.g. if they were part of $purchase_orders_list)
                // For this example, assume we can't dynamically load from specific PO easily without AJAX or more complex preloading.
                // So, when a PO is selected, we will still allow adding from the *full* product list,
                // but the "max quantity" hint won't be accurate without knowing original PO item details.
                // The user would manually ensure they are returning correct items/quantities from that PO.
                // A real system would fetch and restrict to items on the selected PO.
                addPrItemRow(); // Add one blank row, user selects product from global list.

            } else {
                supplierInfoDisplay.style.display = 'none';
                selectedPoSupplierNameSpan.textContent = '';
                addPrItemRow(); // Add one blank row if no PO selected
            }
            updatePrGrandTotal();
        });
    }

    // Initial setup: if editing, populate items. If new, add one blank row.
    <?php if ($edit_mode && !empty($returned_items_val)): ?>
        <?php foreach($returned_items_val as $item_php): ?>
            addPrItemRow(<?php echo json_encode($item_php); ?>);
        <?php endforeach; ?>
    <?php elseif (!$edit_mode): ?>
        if(itemTemplatePrHtml) addPrItemRow(); // Add initial row for new returns
    <?php endif; ?>
    updatePrGrandTotal(); // Calculate total for pre-filled or initial rows

});
</script>

<?php
include __DIR__ . '/../../templates/footer.php';
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
