<?php
$page_title = "Manage Purchase Order";
$app_base_path = '/'; // Adjust if your application is in a subdirectory.

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

$po_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$edit_mode = false;
$current_user_id = get_current_user_id();

// --- Form field values ---
$supplier_id_val = '';
$po_number_val = '';
$purchase_date_val = date('Y-m-d');
$expected_delivery_date_val = '';
$notes_val = '';
$status_val = 'Draft';
$po_items_val = [];
$grand_total_val = 0.00;

// --- Fetch Suppliers and Products for dropdowns ---
$suppliers = [];
$sup_sql = "SELECT id, name FROM suppliers ORDER BY name ASC";
$sup_result = $mysqli->query($sup_sql);
if ($sup_result) while ($row = $sup_result->fetch_assoc()) $suppliers[] = $row;
if ($sup_result) $sup_result->free();

$products_json = []; // For JS product data
$prod_sql = "SELECT id, name, sku, purchase_price FROM products ORDER BY name ASC";
$prod_result = $mysqli->query($prod_sql);
if ($prod_result) {
    while ($row = $prod_result->fetch_assoc()) {
        $products_json[] = $row; // Keep for JS
    }
    $prod_result->free();
}
$products_json_encoded = json_encode($products_json);


if ($po_id) {
    $edit_mode = true;
    $page_title = "Edit Purchase Order";
    $sql_po_edit = "SELECT * FROM purchases WHERE id = $po_id";
    $po_edit_res = $mysqli->query($sql_po_edit);
    if ($po_edit_res && $po_data = $po_edit_res->fetch_assoc()) {
        $supplier_id_val = $po_data['supplier_id'];
        $po_number_val = $po_data['po_number'];
        $purchase_date_val = date('Y-m-d', strtotime($po_data['purchase_date']));
        $expected_delivery_date_val = $po_data['expected_delivery_date'] ? date('Y-m-d', strtotime($po_data['expected_delivery_date'])) : '';
        $notes_val = $po_data['notes'];
        $status_val = $po_data['status'];
        $grand_total_val = $po_data['total_amount'];

        $sql_items_edit = "SELECT product_id, quantity_ordered, price_per_item FROM purchase_items WHERE purchase_id = $po_id";
        $items_edit_res = $mysqli->query($sql_items_edit);
        if($items_edit_res) {
            while($item_data = $items_edit_res->fetch_assoc()) {
                $po_items_val[] = [
                    'product_id' => $item_data['product_id'],
                    'quantity' => $item_data['quantity_ordered'],
                    'price_per_item' => $item_data['price_per_item']
                ];
            }
            $items_edit_res->free();
        }

        if ($status_val != 'Draft' && $status_val != 'Ordered') { // More strict for Ordered later if needed
             $_SESSION['flash_message'] = "This Purchase Order (status: '$status_val') cannot be edited directly from here.";
             $_SESSION['flash_message_type'] = "warning";
             header("Location: " . site_url('admin/view-po/?id=' . $po_id, $app_base_path));
             exit;
        }
    } else {
        $_SESSION['flash_message'] = "Purchase Order not found for editing.";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: " . site_url('admin/purchase-orders', $app_base_path));
        exit;
    }
    if($po_edit_res) $po_edit_res->free();
} else {
    $page_title = "Create New Purchase Order";
    // $po_number_val = 'PO-' . time(); // Example auto-generation
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id_form = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $po_number_form = isset($_POST['po_number']) ? sanitize_input($mysqli, $_POST['po_number']) : null;
    $purchase_date_form = isset($_POST['purchase_date']) ? sanitize_input($mysqli, $_POST['purchase_date']) : date('Y-m-d');
    $expected_delivery_date_form = isset($_POST['expected_delivery_date']) && !empty($_POST['expected_delivery_date']) ? sanitize_input($mysqli, $_POST['expected_delivery_date']) : null;
    $notes_form = isset($_POST['notes']) ? sanitize_input($mysqli, $_POST['notes']) : '';
    $status_form = isset($_POST['status']) ? sanitize_input($mysqli, $_POST['status']) : 'Draft'; // Default to Draft
    if ($edit_mode && $status_val !== 'Draft' && $status_val !== 'Ordered') $status_form = $status_val; // Prevent status change from here for non-editable statuses

    $form_items = isset($_POST['po_items']) ? $_POST['po_items'] : [];
    $calculated_grand_total = 0;

    // --- Validation ---
    if (empty($supplier_id_form)) {
        $_SESSION['flash_message'] = "Supplier is required.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif (empty($purchase_date_form)) {
        $_SESSION['flash_message'] = "Purchase Date is required.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif (empty($form_items) || !is_array($form_items)) {
        $_SESSION['flash_message'] = "At least one item is required in the purchase order.";
        $_SESSION['flash_message_type'] = "danger";
    } else {
        $valid_items = true;
        foreach ($form_items as $item) {
            if (empty($item['product_id']) || !isset($item['quantity']) || (int)$item['quantity'] <= 0 || !isset($item['price_per_item']) || (float)$item['price_per_item'] < 0) {
                $valid_items = false;
                $_SESSION['flash_message'] = "Invalid data in one or more PO items. Ensure Product, Quantity (>0), and Price (>=0) are set.";
                $_SESSION['flash_message_type'] = "danger";
                break;
            }
            $calculated_grand_total += (int)$item['quantity'] * (float)$item['price_per_item'];
        }

        if ($valid_items) {
            $mysqli->begin_transaction();
            try {
                if ($edit_mode) { // Update existing PO
                    $sql_update_po = "UPDATE purchases SET
                                        supplier_id = $supplier_id_form,
                                        po_number = " . ($po_number_form ? "'$po_number_form'" : "NULL") . ",
                                        purchase_date = '$purchase_date_form',
                                        expected_delivery_date = " . ($expected_delivery_date_form ? "'$expected_delivery_date_form'" : "NULL") . ",
                                        notes = '$notes_form',
                                        status = '$status_form',
                                        total_amount = $calculated_grand_total,
                                        user_id = $current_user_id
                                      WHERE id = $po_id";
                    if (!$mysqli->query($sql_update_po)) {
                        throw new Exception("Error updating PO header: " . $mysqli->error);
                    }
                    // Delete old items before re-inserting (simple approach for edit)
                    $delete_old_items_sql = "DELETE FROM purchase_items WHERE purchase_id = $po_id";
                    if (!$mysqli->query($delete_old_items_sql)) {
                        throw new Exception("Error clearing old PO items: " . $mysqli->error);
                    }
                    $current_po_id = $po_id; // Use existing PO ID
                } else { // Insert new PO
                    $sql_insert_po = "INSERT INTO purchases (supplier_id, po_number, purchase_date, expected_delivery_date, notes, status, total_amount, user_id)
                                      VALUES ($supplier_id_form, " . ($po_number_form ? "'$po_number_form'" : "NULL") . ", '$purchase_date_form',
                                              " . ($expected_delivery_date_form ? "'$expected_delivery_date_form'" : "NULL") . ", '$notes_form', '$status_form', $calculated_grand_total, $current_user_id)";
                    if (!$mysqli->query($sql_insert_po)) {
                        throw new Exception("Error creating PO header: " . $mysqli->error);
                    }
                    $current_po_id = $mysqli->insert_id; // Get new PO ID
                }

                // Insert/Re-insert PO items
                foreach ($form_items as $item_data) {
                    $item_product_id = (int)$item_data['product_id'];
                    $item_quantity = (int)$item_data['quantity'];
                    $item_price = (float)$item_data['price_per_item'];
                    $item_total_val = $item_quantity * $item_price;

                    $sql_insert_item = "INSERT INTO purchase_items (purchase_id, product_id, quantity_ordered, purchase_price_per_item, item_total)
                                        VALUES ($current_po_id, $item_product_id, $item_quantity, $item_price, $item_total_val)";
                    if (!$mysqli->query($sql_insert_item)) {
                        throw new Exception("Error adding PO item (Product ID: $item_product_id): " . $mysqli->error);
                    }
                }

                $mysqli->commit();
                $_SESSION['flash_message'] = "Purchase Order " . ($edit_mode ? "updated" : "created") . " successfully. PO ID: $current_po_id";
                $_SESSION['flash_message_type'] = "success";
                header("Location: " . site_url('admin/view-po/?id=' . $current_po_id, $app_base_path));
                exit;

            } catch (Exception $e) {
                $mysqli->rollback();
                $_SESSION['flash_message'] = "Transaction failed: " . htmlspecialchars($e->getMessage());
                $_SESSION['flash_message_type'] = "danger";
            }
        }
    }
    // If here, validation failed or error occurred, repopulate form with submitted values
    $supplier_id_val = $supplier_id_form;
    $po_number_val = $po_number_form;
    $purchase_date_val = $purchase_date_form;
    $expected_delivery_date_val = $expected_delivery_date_form;
    $notes_val = $notes_form;
    $status_val = $status_form;
    $po_items_val = []; // Repopulate from $form_items
    foreach($form_items as $fi){
        $po_items_val[] = [
            'product_id' => $fi['product_id'],
            'quantity' => $fi['quantity'],
            'price_per_item' => $fi['price_per_item']
        ];
    }
    $grand_total_val = $calculated_grand_total;
}


// Handle DELETE action (for Draft POs) - Moved from previous placeholder version
if (isset($_GET['action']) && $_GET['action'] == 'delete' && $po_id && $status_val == 'Draft') {
    $mysqli->begin_transaction();
    try {
        $delete_items_sql = "DELETE FROM purchase_items WHERE purchase_id = $po_id";
        if (!$mysqli->query($delete_items_sql)) throw new Exception("Error deleting PO items: " . $mysqli->error);

        $delete_po_sql = "DELETE FROM purchases WHERE id = $po_id";
        if (!$mysqli->query($delete_po_sql)) throw new Exception("Error deleting PO: " . $mysqli->error);

        $mysqli->commit();
        $_SESSION['flash_message'] = "Draft Purchase Order (ID: $po_id) deleted successfully.";
        $_SESSION['flash_message_type'] = "success";
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['flash_message'] = "Failed to delete Draft PO: " . htmlspecialchars($e->getMessage());
        $_SESSION['flash_message_type'] = "danger";
    }
    header("Location: " . site_url('admin/purchase-orders', $app_base_path));
    exit;
} elseif (isset($_GET['action']) && $_GET['action'] == 'delete' && $po_id && $status_val != 'Draft') {
    $_SESSION['flash_message'] = "Only 'Draft' Purchase Orders can be deleted. This PO is in status: '$status_val'.";
    $_SESSION['flash_message_type'] = "warning";
    header("Location: " . site_url('admin/purchase-orders', $app_base_path));
    exit;
}


include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <?php echo $edit_mode ? 'Edit PO: ' . htmlspecialchars($po_number_val ?: 'PO-'.$po_id) : 'Create New Purchase Order'; ?>
            </h6>
            <a href="<?php echo site_url('admin/purchase-orders', $app_base_path); ?>" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
        </div>
        <div class="card-body">
            <form id="managePoForm" method="POST" action="<?php echo site_url('admin/manage-po/' . ($edit_mode ? '?id='.$po_id : ''), $app_base_path); ?>">
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="supplier_id">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" id="supplier_id" class="form-control" required>
                            <option value="">-- Select Supplier --</option>
                            <?php foreach($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>" <?php if($supplier_id_val == $supplier['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="po_number">PO Number (Optional)</label>
                        <input type="text" name="po_number" id="po_number" class="form-control" value="<?php echo htmlspecialchars($po_number_val); ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="purchase_date">Purchase Date <span class="text-danger">*</span></label>
                        <input type="date" name="purchase_date" id="purchase_date" class="form-control" value="<?php echo htmlspecialchars($purchase_date_val); ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="expected_delivery_date">Expected Delivery Date</label>
                        <input type="date" name="expected_delivery_date" id="expected_delivery_date" class="form-control" value="<?php echo htmlspecialchars($expected_delivery_date_val); ?>">
                    </div>
                </div>
                 <div class="form-group">
                    <label for="status">Status <span class="text-danger">*</span></label>
                    <?php if ($edit_mode && ($status_val != 'Draft' && $status_val != 'Ordered')): ?>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($status_val); ?>" readonly title="Status cannot be changed from here for this PO.">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_val); ?>">
                    <?php else: ?>
                    <select name="status" id="status" class="form-control" required>
                        <option value="Draft" <?php if($status_val == 'Draft') echo 'selected'; ?>>Draft</option>
                        <option value="Ordered" <?php if($status_val == 'Ordered') echo 'selected'; ?>>Ordered</option>
                        <!-- Other statuses managed via View PO page -->
                    </select>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea name="notes" id="notes" class="form-control" rows="3"><?php echo htmlspecialchars($notes_val); ?></textarea>
                </div>

                <hr>
                <h5 class="mb-3">Order Items</h5>
                <div id="po_items_container">
                    <!-- Item rows will be added here by JavaScript -->
                </div>
                <button type="button" id="add_po_item_btn" class="btn btn-success btn-sm mb-3"><i class="fas fa-plus"></i> Add Item</button>

                <div class="text-right">
                    <h4>Grand Total: <span id="po_grand_total"><?php echo number_format($grand_total_val, 2); ?></span></h4>
                </div>

                <hr>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $edit_mode ? 'Update' : 'Save'; ?> Purchase Order</button>
            </form>
        </div>
    </div>
</div>

<template id="po_item_template">
    <div class="form-row po-item-row align-items-end mb-2 border p-3 rounded">
        <div class="form-group col-md-5 mb-0">
            <label>Product <span class="text-danger">*</span></label>
            <select name="po_items[PRODUCT_INDEX][product_id]" class="form-control product-select" required>
                <option value="">-- Select Product --</option>
                <?php /* Options populated by JS */ ?>
            </select>
        </div>
        <div class="form-group col-md-2 mb-0">
            <label>Quantity <span class="text-danger">*</span></label>
            <input type="number" name="po_items[PRODUCT_INDEX][quantity]" class="form-control quantity-input" min="1" value="1" required>
        </div>
        <div class="form-group col-md-2 mb-0">
            <label>Price/Item <span class="text-danger">*</span></label>
            <input type="number" name="po_items[PRODUCT_INDEX][price_per_item]" step="0.01" class="form-control price-input" min="0" value="0.00" required>
        </div>
        <div class="form-group col-md-2 mb-0">
            <label>Subtotal</label>
            <input type="text" class="form-control item-subtotal bg-light" readonly value="0.00">
        </div>
        <div class="form-group col-md-1 mb-0 d-flex align-items-end">
            <button type="button" class="btn btn-danger btn-sm remove-po-item-btn w-100"><i class="fas fa-trash"></i></button>
        </div>
    </div>
</template>

<script>
const productsData = <?php echo $products_json_encoded; ?>;
const existingPoItems = <?php echo json_encode($po_items_val); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const itemsContainer = document.getElementById('po_items_container');
    const addItemBtn = document.getElementById('add_po_item_btn');
    const grandTotalSpan = document.getElementById('po_grand_total');
    const itemTemplate = document.getElementById('po_item_template').innerHTML;
    let itemIndex = 0;

    function addPoItemRow(itemData = null) {
        const newRowHtml = itemTemplate.replace(/PRODUCT_INDEX/g, itemIndex);
        itemsContainer.insertAdjacentHTML('beforeend', newRowHtml);
        const newRowElement = itemsContainer.lastElementChild;

        const productSelect = newRowElement.querySelector('.product-select');
        productsData.forEach(product => {
            const option = document.createElement('option');
            option.value = product.id;
            option.textContent = `${product.name} ${product.sku ? '(SKU: ' + product.sku + ')' : ''}`;
            option.dataset.price = product.purchase_price || '0.00';
            productSelect.appendChild(option);
        });

        if (itemData) {
            productSelect.value = itemData.product_id;
            newRowElement.querySelector('.quantity-input').value = itemData.quantity;
            newRowElement.querySelector('.price-input').value = itemData.price_per_item;
        } else { // Set default price if new item and product selected
             if (productSelect.options.length > 1) { // If products exist
                productSelect.value = productSelect.options[1].value; // Select first actual product
                newRowElement.querySelector('.price-input').value = productSelect.options[1].dataset.price || '0.00';
            }
        }


        newRowElement.querySelector('.remove-po-item-btn').addEventListener('click', function() {
            this.closest('.po-item-row').remove();
            updateGrandTotal();
        });

        [productSelect, newRowElement.querySelector('.quantity-input'), newRowElement.querySelector('.price-input')].forEach(input => {
            input.addEventListener('change', updateRowSubtotal);
            input.addEventListener('keyup', updateRowSubtotal); // For quantity input
        });

        itemIndex++;
        updateRowSubtotal.call(newRowElement.querySelector('.product-select')); // Call initially to set subtotal
        updateGrandTotal(); // Ensure grand total is updated after adding a row
    }

    function updateRowSubtotal() {
        const row = this.closest('.po-item-row');
        const productSelect = row.querySelector('.product-select');
        const quantity = parseInt(row.querySelector('.quantity-input').value) || 0;
        let price = parseFloat(row.querySelector('.price-input').value) || 0;

        // If price is 0 or not set by user, try to get from selected product dataset
        if (price === 0 && productSelect.value) {
            const selectedOption = productSelect.options[productSelect.selectedIndex];
            if (selectedOption && selectedOption.dataset.price) {
                price = parseFloat(selectedOption.dataset.price);
                row.querySelector('.price-input').value = price.toFixed(2);
            }
        }

        const subtotal = quantity * price;
        row.querySelector('.item-subtotal').value = subtotal.toFixed(2);
        updateGrandTotal();
    }

    function updateGrandTotal() {
        let total = 0;
        itemsContainer.querySelectorAll('.po-item-row').forEach(row => {
            total += parseFloat(row.querySelector('.item-subtotal').value) || 0;
        });
        grandTotalSpan.textContent = total.toFixed(2);
    }

    addItemBtn.addEventListener('click', function() { addPoItemRow(); });

    // Load existing items if in edit mode
    if (existingPoItems.length > 0) {
        existingPoItems.forEach(item => addPoItemRow(item));
    } else if (!<?php echo $edit_mode ? 'true' : 'false'; ?>) { // Add one empty row for new POs
        addPoItemRow();
    }

    // Initial calculation in case of pre-filled form from server-side error
    itemsContainer.querySelectorAll('.po-item-row').forEach(row => {
         updateRowSubtotal.call(row.querySelector('.product-select'));
    });
    updateGrandTotal();

});
</script>

<?php
include __DIR__ . '/../../templates/footer.php';
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
