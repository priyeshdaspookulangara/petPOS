<?php
// This template is included by modules/purchases/index.php (via action=purchase_return)
// It has access to:
// $pr_sub_action ('find_po_for_return', 'show_return_form')
// $original_po_for_return (array of PO details if found)
// $returnable_po_items (array of items from the PO if found and returnable)
// $form_errors_pr (array of errors, if any from a previous POST attempt)
// $base_module_url (for form actions, points to index.php?module=purchases)

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}

$currency_symbol = '$'; // Placeholder, ideally from settings
$sql_currency_pr_form = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_pr_form = mysqli_query($conn, $sql_currency_pr_form);
if ($res_currency_pr_form && mysqli_num_rows($res_currency_pr_form) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_pr_form)['setting_value']);
    mysqli_free_result($res_currency_pr_form);
}

$po_number_value = htmlspecialchars($_REQUEST['po_number_return'] ?? '');
?>

<div class="container mt-4">
    <h2>Process Purchase Return (Return to Supplier)</h2>

    <?php
    // Session messages are displayed by header.php.
    // Display local form errors if any from POST processing (not session messages)
    if (!empty($form_errors_pr) && is_array($form_errors_pr)) {
        echo '<div class="alert alert-danger"><strong>Please correct the following errors:</strong><ul>';
        foreach ($form_errors_pr as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul></div>';
    } elseif(isset($_GET['error']) && $_GET['error'] == 'processing_pr'){
         echo '<div class="alert alert-danger">An error occurred while processing the purchase return. Please try again.</div>';
    } elseif(isset($_GET['validation_errors_pr'])){
        echo '<div class="alert alert-danger">There were validation errors. Please check your input.</div>';
    }
    ?>

    <!-- Step 1: Find PO Form -->
    <div class="card mb-4 <?php echo ($original_po_for_return && !empty($returnable_po_items)) ? 'd-none' : ''; ?>" id="findPoForReturnSection">
        <div class="card-header">Find Original Purchase Order by PO Number</div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($base_module_url . '&sub_action=purchase_return&pr_sub_action=find_po_for_return'); ?>" method="GET" class="form-inline">
                <input type="hidden" name="module" value="purchases">
                <input type="hidden" name="action" value="purchase_return"> <!-- This is the main action for this page -->
                <input type="hidden" name="pr_sub_action" value="find_po_for_return">
                <div class="form-group mx-sm-3 mb-2">
                    <label for="po_number_return" class="sr-only">PO Number</label>
                    <input type="text" class="form-control" id="po_number_return" name="po_number_return"
                           placeholder="Enter Original PO No." value="<?php echo $po_number_value; ?>" required>
                </div>
                <button type="submit" class="btn btn-primary mb-2"><i class="fas fa-search"></i> Find PO</button>
            </form>
        </div>
    </div>


    <!-- Step 2: Process Purchase Return Form (shown if PO is found and has returnable items) -->
    <?php if ($original_po_for_return && !empty($returnable_po_items) && $pr_sub_action === 'show_return_form'): ?>
    <div id="processPurchaseReturnSection">
        <div class="card mb-3">
            <div class="card-header">
                Original PO Details (PO #: <?php echo htmlspecialchars($original_po_for_return['po_number']); ?>)
                <button type="button" class="btn btn-sm btn-outline-secondary float-right" id="changePoReturnBtn">Change PO</button>
            </div>
            <div class="card-body">
                <p><strong>Supplier:</strong> <?php echo htmlspecialchars($original_po_for_return['supplier_name']); ?></p>
                <p><strong>PO Date:</strong> <?php echo htmlspecialchars(date('Y-m-d', strtotime($original_po_for_return['purchase_date']))); ?></p>
                <p><strong>Status:</strong> <span class="badge badge-info"><?php echo htmlspecialchars(ucfirst($original_po_for_return['status'])); ?></span></p>
            </div>
        </div>

        <form action="<?php echo htmlspecialchars($base_module_url . '&sub_action=process_purchase_return'); ?>" method="POST" id="confirmPurchaseReturnForm">
            <input type="hidden" name="original_purchase_id_ret" value="<?php echo htmlspecialchars($original_po_for_return['id']); ?>">
            <input type="hidden" name="original_po_number_hidden" value="<?php echo htmlspecialchars($original_po_for_return['po_number']); ?>">

            <div class="card">
                <div class="card-header">Select Items to Return to Supplier</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>SKU</th>
                                    <th>Product Name</th>
                                    <th class="text-right">Qty Received</th>
                                    <th class="text-right">Purchase Price</th>
                                    <th class="text-right">Max Returnable</th>
                                    <th style="width: 15%;">Return Qty <span class="text-danger">*</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($returnable_po_items as $item):
                                    if ($item['quantity_returnable_from_received'] <= 0) continue;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['product_sku']); ?></td>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td class="text-right"><?php echo htmlspecialchars($item['quantity_received']); ?></td>
                                    <td class="text-right"><?php echo $currency_symbol . number_format($item['purchase_price_per_item'], 2); ?></td>
                                    <td class="text-right font-weight-bold"><?php echo htmlspecialchars($item['quantity_returnable_from_received']); ?></td>
                                    <td>
                                        <input type="number" name="return_quantity_pr[<?php echo $item['id']; // purchase_item_id ?>]"
                                               class="form-control form-control-sm text-right item-return-qty-pr"
                                               value="0" min="0" max="<?php echo $item['quantity_returnable_from_received']; ?>"
                                               data-price="<?php echo $item['purchase_price_per_item']; ?>">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label for="overall_reason_pr">Overall Reason for Return to Supplier <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="overall_reason_pr" name="overall_reason_pr" rows="2" required></textarea>
                    </div>
                    <div class="text-right mt-3">
                        <h4>Total Return Value: <strong id="totalReturnValuePr" class="text-danger"><?php echo $currency_symbol; ?>0.00</strong></h4>
                    </div>
                </div>
            </div>

            <div class="form-group mt-4 text-right">
                <button type="submit" class="btn btn-success btn-lg" id="submitPurchaseReturnBtn">
                    <i class="fas fa-check-circle"></i> Confirm Purchase Return
                </button>
                <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=purchase_return&pr_sub_action=find_po_for_return'); ?>" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
    <?php elseif (isset($_REQUEST['po_number_return']) && !$original_po_for_return): ?>
         <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=purchase_return&pr_sub_action=find_po_for_return'); ?>" class="btn btn-info mt-3">
            <i class="fas fa-search"></i> Try Another PO Number
        </a>
    <?php elseif (isset($_REQUEST['po_number_return']) && $original_po_for_return && empty($returnable_po_items)): ?>
         <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=purchase_return&pr_sub_action=find_po_for_return'); ?>" class="btn btn-info mt-3">
            <i class="fas fa-search"></i> Try Another PO Number
        </a>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const processPurchaseReturnSection = document.getElementById('processPurchaseReturnSection');
    const findPoForReturnSection = document.getElementById('findPoForReturnSection');
    const changePoReturnBtn = document.getElementById('changePoReturnBtn');
    const confirmPurchaseReturnForm = document.getElementById('confirmPurchaseReturnForm');
    const submitPurchaseReturnBtn = document.getElementById('submitPurchaseReturnBtn');

    if (changePoReturnBtn) {
        changePoReturnBtn.addEventListener('click', function() {
            if (findPoForReturnSection) findPoForReturnSection.classList.remove('d-none');
            if (processPurchaseReturnSection) processPurchaseReturnSection.innerHTML = '<p class="text-center my-5"><i class="fas fa-spinner fa-spin fa-2x"></i> Loading...</p>';
            window.location.href = "<?php echo htmlspecialchars($base_module_url . '&sub_action=purchase_return&pr_sub_action=find_po_for_return'); ?>";
        });
    }

    function calculateTotalReturnValuePr() {
        let totalValue = 0;
        document.querySelectorAll('.item-return-qty-pr').forEach(function(input) {
            const qty = parseInt(input.value) || 0;
            const price = parseFloat(input.dataset.price) || 0;
            totalValue += qty * price;
        });
        const totalValueElem = document.getElementById('totalReturnValuePr');
        if (totalValueElem) {
            totalValueElem.textContent = '<?php echo $currency_symbol; ?>' + totalValue.toFixed(2);
        }
    }

    document.querySelectorAll('.item-return-qty-pr').forEach(function(input) {
        input.addEventListener('input', function() {
            const max = parseInt(this.getAttribute('max'));
            if (parseInt(this.value) > max) {
                this.value = max;
                alert('Return quantity cannot exceed max returnable from received stock (' + max + ').');
            }
            if (parseInt(this.value) < 0) {
                this.value = 0;
            }
            calculateTotalReturnValuePr();
        });
    });

    if(confirmPurchaseReturnForm && submitPurchaseReturnBtn){
        confirmPurchaseReturnForm.addEventListener('submit', function(e){
            let totalQtyToReturnPr = 0;
            document.querySelectorAll('.item-return-qty-pr').forEach(function(input) {
                totalQtyToReturnPr += (parseInt(input.value) || 0);
            });
            if(totalQtyToReturnPr === 0){
                e.preventDefault();
                alert("Please enter a quantity for at least one item to return.");
                return false;
            }
             if(document.getElementById('overall_reason_pr') && document.getElementById('overall_reason_pr').value.trim() === ''){
                e.preventDefault();
                alert("Overall reason for return to supplier is required.");
                document.getElementById('overall_reason_pr').focus();
                return false;
            }
            submitPurchaseReturnBtn.disabled = true;
            submitPurchaseReturnBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        });
    }

    calculateTotalReturnValuePr(); // Initial calculation
});
</script>
