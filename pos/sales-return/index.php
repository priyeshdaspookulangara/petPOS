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

// Handle processing the return (placeholder for full logic)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_return_submit'])) {
    $original_sale_id_for_return = isset($_POST['original_sale_id']) ? (int)$_POST['original_sale_id'] : null;
    $return_reason = isset($_POST['return_reason']) ? sanitize_input($mysqli, $_POST['return_reason']) : '';
    $items_to_return_data = isset($_POST['return_items']) ? $_POST['return_items'] : []; // Expects array like [product_id => ['quantity' => x, 'price' => y]]

    if (empty($original_sale_id_for_return)) {
        $_SESSION['flash_message'] = "Original sale information is missing.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif (empty($items_to_return_data)) {
        $_SESSION['flash_message'] = "No items selected for return.";
        $_SESSION['flash_message_type'] = "warning";
    } else {
        // --- Full Return Processing Logic (Next Step/Phase) ---
        // 1. Validate items_to_return_data (quantities against sold & previously returned, prices)
        // 2. Calculate total_refund_amount
        // 3. Start DB Transaction
        // 4. Insert into `sales_returns`
        // 5. Insert each item into `sales_return_items`
        // 6. Increment `products.current_stock` for each returned item
        // 7. Commit or Rollback
        // 8. Generate return receipt / credit note
        // --- End of Full Logic Placeholder ---

        $_SESSION['flash_message'] = "Return processing logic is not yet fully implemented. Data received.";
        $_SESSION['flash_message_type'] = "info";
        // For now, just clear the search to avoid resubmitting the same form
        // header("Location: " . site_url('pos/sales-return', $app_base_path));
        // exit;
        $searched_sale = null; // Clear search results after "processing"
        $searched_sale_items = [];
        $search_receipt_no = '';
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
