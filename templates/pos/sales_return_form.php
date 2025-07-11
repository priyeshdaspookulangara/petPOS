<?php
// This template is included by modules/pos/sales_return.php
// It has access to:
// $page_sub_action ('find_sale', 'process_return_form', 'confirm_return')
// $original_sale (array of sale details if found)
// $original_sale_items (array of items from the sale if found and returnable)
// $form_errors (array of errors)
// $base_module_url (for form actions)

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}

$currency_symbol = '$'; // Placeholder, ideally from settings
$sql_currency_sr = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_sr = mysqli_query($conn, $sql_currency_sr);
if ($res_currency_sr && mysqli_num_rows($res_currency_sr) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_sr)['setting_value']);
    mysqli_free_result($res_currency_sr);
}

$receipt_no_value = htmlspecialchars($_REQUEST['receipt_no'] ?? '');
?>

<div class="container mt-4">
    <h2>Process Sales Return</h2>

    <?php
    // Session messages are displayed by header.php.
    // Display local form errors if any from POST processing (not session messages)
    if (!empty($form_errors) && is_array($form_errors)) { // Check if $form_errors is an array
        echo '<div class="alert alert-danger"><strong>Please correct the following errors:</strong><ul>';
        foreach ($form_errors as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul></div>';
    } elseif(isset($_GET['error']) && $_GET['error'] == 'processing'){
         // Generic message if specific errors aren't passed back easily after redirect
         echo '<div class="alert alert-danger">An error occurred while processing the return. Please try again.</div>';
    } elseif(isset($_GET['validation_errors'])){
        echo '<div class="alert alert-danger">There were validation errors. Please check your input.</div>';
    }
    ?>

    <!-- Step 1: Find Sale Form -->
    <div class="card mb-4 <?php echo ($original_sale && !empty($original_sale_items)) ? 'd-none' : ''; ?>" id="findSaleSection">
        <div class="card-header">Find Original Sale by Receipt Number</div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($base_module_url . '&sub_action=find_sale'); ?>" method="GET" class="form-inline">
                <input type="hidden" name="module" value="pos">
                <input type="hidden" name="action" value="sales_return">
                <input type="hidden" name="sub_action" value="find_sale">
                <div class="form-group mx-sm-3 mb-2">
                    <label for="receipt_no" class="sr-only">Receipt Number</label>
                    <input type="text" class="form-control" id="receipt_no" name="receipt_no"
                           placeholder="Enter Original Receipt No." value="<?php echo $receipt_no_value; ?>" required>
                </div>
                <button type="submit" class="btn btn-primary mb-2"><i class="fas fa-search"></i> Find Sale</button>
            </form>
        </div>
    </div>


    <!-- Step 2: Process Return Form (shown if sale is found and has returnable items) -->
    <?php if ($original_sale && !empty($original_sale_items) && $page_sub_action === 'process_return_form'): ?>
    <div id="processReturnSection">
        <div class="card mb-3">
            <div class="card-header">
                Original Sale Details (Receipt: <?php echo htmlspecialchars($original_sale['receipt_no']); ?>)
                <button type="button" class="btn btn-sm btn-outline-secondary float-right" id="changeSaleBtn">Change Sale</button>
            </div>
            <div class="card-body">
                <p><strong>Date:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($original_sale['sale_date']))); ?></p>
                <p><strong>Customer:</strong> <?php echo htmlspecialchars($original_sale['customer_name'] ?? 'Walk-in'); ?></p>
                <p><strong>Original Total:</strong> <?php echo $currency_symbol . number_format($original_sale['grand_total'], 2); ?></p>
                <p><strong>Status:</strong> <span class="badge badge-info"><?php echo htmlspecialchars(ucfirst($original_sale['status'])); ?></span></p>
            </div>
        </div>

        <form action="<?php echo htmlspecialchars($base_module_url . '&sub_action=confirm_return'); ?>" method="POST" id="confirmReturnForm">
            <input type="hidden" name="original_sale_id" value="<?php echo htmlspecialchars($original_sale['id']); ?>">
            <input type="hidden" name="original_receipt_no_hidden" value="<?php echo htmlspecialchars($original_sale['receipt_no']); ?>">

            <div class="card">
                <div class="card-header">Select Items to Return</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>SKU</th>
                                    <th>Product Name</th>
                                    <th class="text-right">Sold Qty</th>
                                    <th class="text-right">Price Paid</th>
                                    <th class="text-right">Max Returnable</th>
                                    <th style="width: 15%;">Return Qty <span class="text-danger">*</span></th>
                                    <!-- <th style="width: 20%;">Reason for Return (Item)</th> -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($original_sale_items as $item):
                                    if ($item['quantity_returnable'] <= 0) continue; // Skip if none can be returned
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['product_sku']); ?></td>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td class="text-right"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td class="text-right"><?php echo $currency_symbol . number_format($item['price_per_item_after_discount'], 2); ?></td>
                                    <td class="text-right font-weight-bold"><?php echo htmlspecialchars($item['quantity_returnable']); ?></td>
                                    <td>
                                        <input type="number" name="return_quantity[<?php echo $item['id']; // sale_item_id ?>]"
                                               class="form-control form-control-sm text-right item-return-qty"
                                               value="0" min="0" max="<?php echo $item['quantity_returnable']; ?>"
                                               data-price="<?php echo $item['price_per_item_after_discount']; ?>">
                                    </td>
                                    <!-- <td>
                                        <input type="text" name="return_reason[<?php //echo $item['id']; ?>]" class="form-control form-control-sm">
                                    </td> -->
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label for="overall_return_reason">Overall Reason for Return <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="overall_return_reason" name="overall_return_reason" rows="2" required></textarea>
                    </div>
                    <div class="text-right mt-3">
                        <h4>Total Refund Due: <strong id="totalRefundAmount" class="text-success"><?php echo $currency_symbol; ?>0.00</strong></h4>
                    </div>
                </div>
            </div>

            <div class="form-group mt-4 text-right">
                <button type="submit" class="btn btn-success btn-lg" id="submitReturnBtn">
                    <i class="fas fa-check-circle"></i> Confirm Return & Process Refund
                </button>
                <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=find_sale'); ?>" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Cancel Return
                </a>
            </div>
        </form>
    </div>
    <?php elseif (isset($_REQUEST['receipt_no']) && !$original_sale): ?>
        <!-- Message about sale not found is handled by session message in controller -->
         <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=find_sale'); ?>" class="btn btn-info mt-3">
            <i class="fas fa-search"></i> Try Another Receipt
        </a>
    <?php elseif (isset($_REQUEST['receipt_no']) && $original_sale && empty($original_sale_items)): ?>
        <!-- Message about no returnable items is handled by session message in controller -->
         <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=find_sale'); ?>" class="btn btn-info mt-3">
            <i class="fas fa-search"></i> Try Another Receipt
        </a>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const processReturnSection = document.getElementById('processReturnSection');
    const findSaleSection = document.getElementById('findSaleSection');
    const changeSaleBtn = document.getElementById('changeSaleBtn');
    const confirmReturnForm = document.getElementById('confirmReturnForm');
    const submitReturnBtn = document.getElementById('submitReturnBtn');

    if (changeSaleBtn) {
        changeSaleBtn.addEventListener('click', function() {
            if (findSaleSection) findSaleSection.classList.remove('d-none');
            if (processReturnSection) processReturnSection.innerHTML = '<p class="text-center my-5"><i class="fas fa-spinner fa-spin fa-2x"></i> Loading...</p>'; // Clear previous form
             // Redirect to find_sale to clear state properly
            window.location.href = "<?php echo htmlspecialchars($base_module_url . '&sub_action=find_sale'); ?>";
        });
    }

    function calculateTotalRefund() {
        let totalRefund = 0;
        const qtyInputs = document.querySelectorAll('.item-return-qty');
        qtyInputs.forEach(function(input) {
            const qty = parseInt(input.value) || 0;
            const price = parseFloat(input.dataset.price) || 0;
            totalRefund += qty * price;
        });
        const totalRefundAmountElem = document.getElementById('totalRefundAmount');
        if (totalRefundAmountElem) {
            totalRefundAmountElem.textContent = '<?php echo $currency_symbol; ?>' + totalRefund.toFixed(2);
        }
    }

    document.querySelectorAll('.item-return-qty').forEach(function(input) {
        input.addEventListener('input', function() {
            const max = parseInt(this.getAttribute('max'));
            if (parseInt(this.value) > max) {
                this.value = max;
                alert('Return quantity cannot exceed max returnable (' + max + ').');
            }
            if (parseInt(this.value) < 0) {
                this.value = 0;
            }
            calculateTotalRefund();
        });
    });

    if(confirmReturnForm && submitReturnBtn){
        confirmReturnForm.addEventListener('submit', function(e){
            let totalQtyToReturn = 0;
            document.querySelectorAll('.item-return-qty').forEach(function(input) {
                totalQtyToReturn += (parseInt(input.value) || 0);
            });
            if(totalQtyToReturn === 0){
                e.preventDefault();
                alert("Please enter a quantity for at least one item to return.");
                return false;
            }
            if(document.getElementById('overall_return_reason') && document.getElementById('overall_return_reason').value.trim() === ''){
                e.preventDefault();
                alert("Overall reason for return is required.");
                document.getElementById('overall_return_reason').focus();
                return false;
            }

            submitReturnBtn.disabled = true;
            submitReturnBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        });
    }

    // Initial calculation if form is pre-filled (e.g. on error)
    calculateTotalRefund();
});
</script>
