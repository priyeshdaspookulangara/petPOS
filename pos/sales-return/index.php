<?php
$page_title = "Process Sales Return";
$app_base_path = '/';

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_logged_in(); // Accessible by Admin and Cashier

$searched_sale = null;
$searched_sale_items = [];
$search_receipt_no = '';
$current_user_id = get_current_user_id();
$store_settings = []; // For currency symbol

// Fetch currency symbol
$sql_settings = "SELECT setting_key, setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_settings = $mysqli->query($sql_settings);
if($res_settings && $row_setting = $res_settings->fetch_assoc()){
    $store_settings['currency_symbol'] = $row_setting['setting_value'];
}
$currency_symbol = $store_settings['currency_symbol'] ?? '$';
if($res_settings) $res_settings->free();


// Handle search for original sale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_sale_submit'])) {
    $search_receipt_no = sanitize_input($mysqli, $_POST['receipt_no_search']);

    if (empty($search_receipt_no)) {
        $_SESSION['flash_message'] = "Please enter a receipt number to search.";
        $_SESSION['flash_message_type'] = "warning";
    } else {
        $sql_find_sale = "SELECT s.*, u.username as cashier_name
                          FROM sales s
                          JOIN users u ON s.user_id = u.id
                          WHERE s.receipt_no = '$search_receipt_no'";
        $result_find_sale = $mysqli->query($sql_find_sale);

        if ($result_find_sale && $result_find_sale->num_rows > 0) {
            $searched_sale = $result_find_sale->fetch_assoc();
            $result_find_sale->free();

            // Fetch items for this sale
            $sale_id_found = $searched_sale['id'];
            $sql_find_items = "SELECT si.*, p.name as product_name, p.sku as product_sku,
                                      (SELECT SUM(sri.quantity) FROM sales_return_items sri JOIN sales_returns sr ON sri.sales_return_id = sr.id WHERE sr.original_sale_id = $sale_id_found AND sri.product_id = si.product_id) as total_previously_returned
                               FROM sale_items si
                               JOIN products p ON si.product_id = p.id
                               WHERE si.sale_id = $sale_id_found";
            $result_find_items = $mysqli->query($sql_find_items);
            if ($result_find_items) {
                while ($item_row = $result_find_items->fetch_assoc()) {
                    $item_row['total_previously_returned'] = $item_row['total_previously_returned'] ?: 0;
                    $item_row['returnable_qty'] = $item_row['quantity'] - $item_row['total_previously_returned'];
                    $searched_sale_items[] = $item_row;
                }
                $result_find_items->free();
            } else {
                $_SESSION['flash_message'] = "Error fetching items for the sale: " . htmlspecialchars($mysqli->error);
                $_SESSION['flash_message_type'] = "danger";
            }
        } else {
            $_SESSION['flash_message'] = "Sale with Receipt No. '" . htmlspecialchars($search_receipt_no) . "' not found.";
            $_SESSION['flash_message_type'] = "danger";
        }
    }
}

// Handle processing the return (Full Implementation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_return_submit'])) {
    $original_sale_id_for_return = isset($_POST['original_sale_id']) ? (int)$_POST['original_sale_id'] : null;
    $return_reason = isset($_POST['return_reason']) ? sanitize_input($mysqli, $_POST['return_reason']) : '';
    $items_to_return_data = isset($_POST['return_items']) ? $_POST['return_items'] : [];

    if (empty($original_sale_id_for_return)) {
        $_SESSION['flash_message'] = "Original sale information is missing.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif (empty($items_to_return_data)) {
        $_SESSION['flash_message'] = "No items selected for return. Please check the 'Return?' box for at least one item.";
        $_SESSION['flash_message_type'] = "warning";
    } else {
        $mysqli->begin_transaction();
        try {
            $total_refund_amount = 0;
            $validated_return_items = [];

            // --- Server-side validation of each item ---
            foreach ($items_to_return_data as $product_id => $details) {
                if (!isset($details['selected'])) continue; // Skip if checkbox wasn't checked

                $product_id = (int)$product_id;
                $return_qty = isset($details['quantity']) ? (int)$details['quantity'] : 0;
                $price_at_sale = isset($details['price_at_sale']) ? (float)$details['price_at_sale'] : -1;

                if ($return_qty <= 0) {
                    throw new Exception("Return quantity for Product ID $product_id must be positive.");
                }
                if ($price_at_sale < 0) {
                    throw new Exception("Invalid price for returned Product ID $product_id.");
                }

                // Fetch original sale item details and previous returns to validate quantity
                $sql_validate_item = "SELECT
                                        si.quantity as sold_qty,
                                        (SELECT SUM(sri.quantity) FROM sales_return_items sri JOIN sales_returns sr ON sri.sales_return_id = sr.id WHERE sr.original_sale_id = $original_sale_id_for_return AND sri.product_id = $product_id) as prev_returned_qty
                                      FROM sale_items si
                                      WHERE si.sale_id = $original_sale_id_for_return AND si.product_id = $product_id";
                $res_validate = $mysqli->query($sql_validate_item);
                if (!$res_validate || $res_validate->num_rows === 0) {
                    throw new Exception("Product ID $product_id was not found in the original sale.");
                }
                $validation_data = $res_validate->fetch_assoc();
                $returnable_qty = (int)$validation_data['sold_qty'] - (int)($validation_data['prev_returned_qty'] ?? 0);

                if ($return_qty > $returnable_qty) {
                    throw new Exception("Cannot return quantity $return_qty for Product ID $product_id. Only $returnable_qty are available to be returned.");
                }

                $validated_return_items[] = [
                    'product_id' => $product_id,
                    'quantity' => $return_qty,
                    'price_per_item' => $price_at_sale,
                    'item_total_refund' => $return_qty * $price_at_sale
                ];
                $total_refund_amount += ($return_qty * $price_at_sale);
            }

            if (empty($validated_return_items)) {
                 throw new Exception("No valid items were processed for return.");
            }

            // 1. Insert into `sales_returns` table
            $return_receipt_no = 'RT-' . time() . '-' . mt_rand(100, 999);
            $sql_insert_return = "INSERT INTO sales_returns (original_sale_id, return_receipt_no, return_date, total_refund_amount, reason, user_id)
                                  VALUES ($original_sale_id_for_return, '$return_receipt_no', NOW(), $total_refund_amount, '$return_reason', $current_user_id)";
            if (!$mysqli->query($sql_insert_return)) {
                throw new Exception("Error saving sales return record: " . $mysqli->error);
            }
            $sales_return_id = $mysqli->insert_id;

            // 2. Insert items and update stock
            foreach ($validated_return_items as $item) {
                // Insert into `sales_return_items`
                $sql_insert_item = "INSERT INTO sales_return_items (sales_return_id, product_id, quantity, refund_price_per_item, item_total_refund)
                                    VALUES ($sales_return_id, {$item['product_id']}, {$item['quantity']}, {$item['price_per_item']}, {$item['item_total_refund']})";
                if (!$mysqli->query($sql_insert_item)) {
                    throw new Exception("Error saving returned item (Product ID {$item['product_id']}): " . $mysqli->error);
                }

                // Increment product stock
                $sql_update_stock = "UPDATE products SET current_stock = current_stock + {$item['quantity']} WHERE id = {$item['product_id']}";
                if (!$mysqli->query($sql_update_stock)) {
                    throw new Exception("Error updating stock for returned product ID {$item['product_id']}: " . $mysqli->error);
                }
            }

            $mysqli->commit();
            $_SESSION['flash_message'] = "Sales return processed successfully! Return Receipt No: $return_receipt_no";
            $_SESSION['flash_message_type'] = "success";
            // Redirect to the new return receipt page
            header("Location: " . site_url('pos/sales-return-receipt/?id=' . $sales_return_id, $app_base_path));
            exit;

        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['flash_message'] = "Return processing failed: " . htmlspecialchars($e->getMessage());
            $_SESSION['flash_message_type'] = "danger";
            // To allow user to correct, we should repopulate the form.
            // This requires keeping $searched_sale and $searched_sale_items available after POST error.
            // For now, we just show the error. A full redirect loses the context.
        }
    }
}


include __DIR__ . '/../templates/header.php';
?>

<div class="container mt-4">
    <h1 class="mb-4"><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="card shadow mb-4">
        <div class="card-header">
            <h6 class="m-0 font-weight-bold text-primary">Find Original Sale by Receipt Number</h6>
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo site_url('pos/sales-return', $app_base_path); ?>">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-8">
                        <label for="receipt_no_search">Enter Original Receipt Number:</label>
                        <input type="text" class="form-control" id="receipt_no_search" name="receipt_no_search"
                               value="<?php echo htmlspecialchars($search_receipt_no); ?>" required>
                    </div>
                    <div class="form-group col-md-4">
                        <button type="submit" name="search_sale_submit" class="btn btn-info btn-block"><i class="fas fa-search"></i> Search Sale</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($searched_sale): ?>
    <hr class="my-4">
    <form method="POST" action="<?php echo site_url('pos/sales-return', $app_base_path); ?>" id="processReturnForm">
        <input type="hidden" name="original_sale_id" value="<?php echo $searched_sale['id']; ?>">
        <input type="hidden" name="original_receipt_no" value="<?php echo htmlspecialchars($searched_sale['receipt_no']); ?>">

        <div class="card shadow mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="m-0">Original Sale Details (Receipt: <?php echo htmlspecialchars($searched_sale['receipt_no']); ?>)</h5>
                <small>Date: <?php echo date('M d, Y H:i', strtotime($searched_sale['sale_date'])); ?> | Cashier: <?php echo htmlspecialchars($searched_sale['cashier_name']); ?></small>
            </div>
            <div class="card-body">
                <h6>Items from Original Sale:</h6>
                <?php if (!empty($searched_sale_items)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 5%;">Return?</th>
                                <th>Product (SKU)</th>
                                <th class="text-center">Qty Sold</th>
                                <th class="text-center">Prev. Returned</th>
                                <th class="text-center">Qty to Return</th>
                                <th class="text-right">Price Paid</th>
                                <!--<th>Return Price (if different)</th>-->
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($searched_sale_items as $idx => $item): ?>
                                <tr>
                                    <td class="text-center">
                                        <?php if ($item['returnable_qty'] > 0): ?>
                                        <input type="checkbox" name="return_items[<?php echo $item['product_id']; ?>][selected]"
                                               value="<?php echo $item['product_id']; ?>" class="item-return-checkbox">
                                        <input type="hidden" name="return_items[<?php echo $item['product_id']; ?>][price_at_sale]" value="<?php echo $item['price_per_item']; ?>">
                                        <?php else: ?>
                                            <i class="fas fa-check-circle text-success" title="Fully returned or not returnable"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?> <small class="text-muted"><?php echo htmlspecialchars($item['product_sku'] ?: ''); ?></small></td>
                                    <td class="text-center"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($item['total_previously_returned']); ?></td>
                                    <td class="text-center">
                                        <?php if ($item['returnable_qty'] > 0): ?>
                                        <input type="number" name="return_items[<?php echo $item['product_id']; ?>][quantity]"
                                               class="form-control form-control-sm return-qty-input" value="0"
                                               min="0" max="<?php echo $item['returnable_qty']; ?>" style="width: 70px; display: inline-block;" disabled>
                                        <?php else: echo '0'; endif; ?>
                                    </td>
                                    <td class="text-right"><?php echo $currency_symbol; ?><?php echo htmlspecialchars(number_format($item['price_per_item'], 2)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-muted">No items found for this sale (this should not happen if sale exists).</p>
                <?php endif; ?>

                <div class="form-group mt-3">
                    <label for="return_reason">Reason for Return (Optional):</label>
                    <textarea name="return_reason" id="return_reason" class="form-control" rows="2"></textarea>
                </div>

                <div class="text-right mt-3">
                    <h5>Total Refund Amount: <span id="totalRefundAmount" class="font-weight-bold"><?php echo $currency_symbol; ?>0.00</span></h5>
                </div>

                <hr>
                <button type="submit" name="process_return_submit" class="btn btn-warning btn-lg float-right"><i class="fas fa-undo-alt"></i> Process Return</button>
            </div>
        </div>
    </form>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const currencySymbolJS = '<?php echo $currency_symbol; ?>';
    const returnCheckboxes = document.querySelectorAll('.item-return-checkbox');
    const returnQtyInputs = document.querySelectorAll('.return-qty-input');
    const totalRefundAmountEl = document.getElementById('totalRefundAmount');

    function calculateTotalRefund() {
        let totalRefund = 0;
        document.querySelectorAll('.pr-item-row-active').forEach(row => { // Assuming active rows get a class
            const qtyInput = row.querySelector('.return-qty-input');
            const price = parseFloat(row.dataset.priceAtSale); // Store price_at_sale in a data attribute on the row
            const qty = parseInt(qtyInput.value) || 0;
            if (qty > 0 && !isNaN(price)) {
                totalRefund += qty * price;
            }
        });
        if(totalRefundAmountEl) totalRefundAmountEl.textContent = currencySymbolJS + totalRefund.toFixed(2);
    }

    returnCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            const qtyInput = row.querySelector('.return-qty-input');
            if (this.checked) {
                qtyInput.disabled = false;
                qtyInput.value = qtyInput.max > 0 ? 1 : 0; // Default to 1 if possible
                row.classList.add('pr-item-row-active'); // Mark row for calculation
                row.dataset.priceAtSale = row.cells[5].textContent.replace(currencySymbolJS, ''); // Assuming price is in 6th cell
            } else {
                qtyInput.disabled = true;
                qtyInput.value = 0;
                row.classList.remove('pr-item-row-active');
            }
            calculateTotalRefund();
        });
    });

    returnQtyInputs.forEach(input => {
        input.addEventListener('input', function() {
            const maxQty = parseInt(this.max);
            if (parseInt(this.value) > maxQty) {
                this.value = maxQty;
                alert('Return quantity cannot exceed available returnable quantity (' + maxQty + ').');
            }
            if (parseInt(this.value) < 0) {
                this.value = 0;
            }
            // Ensure checkbox is checked if quantity > 0
            const row = this.closest('tr');
            const checkbox = row.querySelector('.item-return-checkbox');
            if (parseInt(this.value) > 0 && checkbox && !checkbox.checked) {
                checkbox.checked = true;
                qtyInput.disabled = false; // Should already be if checkbox was manually checked
                row.classList.add('pr-item-row-active');
                row.dataset.priceAtSale = row.cells[5].textContent.replace(currencySymbolJS, '');
            } else if (parseInt(this.value) === 0 && checkbox && checkbox.checked) {
                // Optional: uncheck if qty becomes 0, or just let user manage checkbox
            }

            calculateTotalRefund();
        });
    });

    // Initial calculation for pre-filled forms (e.g. after server-side validation error)
    // This needs more robust state handling if form is repopulated by PHP after error
    // calculateTotalRefund();
});
</script>

<?php
include __DIR__ . '/../templates/footer.php';
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
