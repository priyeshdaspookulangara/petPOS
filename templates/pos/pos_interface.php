<?php
// This template is included by modules/pos/index.php
// It has access to $pos_settings (tax_rate_percentage, currency_symbol, store_name)
// and $base_module_url

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}

// For AJAX calls from JS
$ajax_base_url = $base_module_url; // e.g., index.php?module=pos
$currency_symbol = htmlspecialchars($pos_settings['currency_symbol']);
$tax_rate_percentage = floatval($pos_settings['tax_rate_percentage']);

?>
<style>
    /* POS Specific Styles */
    #pos-container {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }
    #product-selection-area {
        flex: 3; /* Takes more space */
        min-width: 300px;
    }
    #cart-area {
        flex: 2; /* Takes less space */
        min-width: 300px;
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    #product-search-results {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #ddd;
        margin-top: 10px;
    }
    .product-result-item {
        padding: 10px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
        display: flex;
        align-items: center;
    }
    .product-result-item:hover {
        background-color: #e9ecef;
    }
    .product-result-item img {
        width: 40px;
        height: 40px;
        object-fit: cover;
        margin-right: 10px;
        border-radius: 3px;
    }
    #cart-items-table td, #cart-items-table th {
        vertical-align: middle;
    }
    .quantity-input {
        width: 60px;
        text-align: center;
    }
    #pos-summary span {
        display: block;
        margin-bottom: 5px;
        font-size: 1.1em;
    }
    #pos-summary .grand-total {
        font-weight: bold;
        font-size: 1.5em;
        color: #28a745;
    }
    .payment-methods button {
        margin: 5px;
    }
    /* Touch-friendly adjustments */
    .btn {
        padding: 10px 15px;
    }
    .form-control {
        padding: 10px;
        height: auto;
    }
</style>

<div class="container-fluid mt-3" id="pos-container">
    <!-- Product Selection Area -->
    <div id="product-selection-area">
        <h4>Add Products to Sale</h4>
        <div class="form-group">
            <label for="product-search">Search Product (Name, SKU, Barcode)</label>
            <input type="text" id="product-search" class="form-control form-control-lg" placeholder="Start typing...">
        </div>
        <div id="product-search-results">
            <!-- Search results will appear here -->
        </div>

        <div class="mt-3">
            <h5>Quick Add / Popular Items (Placeholder)</h5>
            <div class="btn-group-toggle" data-toggle="buttons">
                <!-- Example: <button class="btn btn-outline-secondary m-1" data-product-id="X">Product X</button> -->
            </div>
        </div>
    </div>

    <!-- Cart & Payment Area -->
    <div id="cart-area">
        <h4>Current Sale</h4>
        <div class="table-responsive">
            <table class="table table-sm" id="cart-items-table">
                <thead class="thead-light">
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Cart items will be dynamically added here -->
                    <tr id="cart-empty-row">
                        <td colspan="5" class="text-center text-muted">Cart is empty</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <hr>
        <div id="pos-summary">
            <span>Subtotal: <strong id="cart-subtotal"><?php echo $currency_symbol; ?>0.00</strong></span>
            <div class="form-group row mb-1">
                <label for="cart-discount" class="col-sm-5 col-form-label">Discount (<?php echo $currency_symbol; ?>):</label>
                <div class="col-sm-7">
                    <input type="number" id="cart-discount" class="form-control form-control-sm" value="0.00" step="0.01" min="0">
                </div>
            </div>
            <span>Tax (<?php echo $tax_rate_percentage; ?>%): <strong id="cart-tax"><?php echo $currency_symbol; ?>0.00</strong></span>
            <hr>
            <span class="grand-total">Total: <strong id="cart-grandtotal"><?php echo $currency_symbol; ?>0.00</strong></span>
        </div>
        <hr>

        <h5>Payment</h5>
        <div class="form-group">
            <label for="customer-select">Customer (Optional)</label>
            <select id="customer-select" class="form-control">
                <option value="">Walk-in Customer</option>
                <!-- Customers can be loaded here if feature is enabled -->
            </select>
        </div>
        <div class="form-group payment-methods">
            <label>Payment Method:</label><br>
            <button type="button" class="btn btn-primary payment-method-btn" data-method="cash">Cash</button>
            <button type="button" class="btn btn-info payment-method-btn" data-method="card">Card</button>
            <!-- <button type="button" class="btn btn-warning payment-method-btn" data-method="split">Split</button> -->
        </div>
        <input type="hidden" id="selected-payment-method" value="cash">

        <div class="form-group">
             <label for="pos-notes">Notes:</label>
             <textarea id="pos-notes" class="form-control" rows="2"></textarea>
        </div>

        <div class="mt-3 d-flex justify-content-between">
            <button type="button" id="hold-sale-btn" class="btn btn-secondary"><i class="fas fa-pause"></i> Hold Sale</button>
            <button type="button" id="process-sale-btn" class="btn btn-success btn-lg"><i class="fas fa-check-circle"></i> Process Sale</button>
        </div>
         <button type="button" id="clear-sale-btn" class="btn btn-danger btn-block mt-2"><i class="fas fa-times-circle"></i> Clear Sale</button>
    </div>
</div>

<!-- Receipt Modal (Bootstrap) -->
<div class="modal fade" id="receiptModal" tabindex="-1" role="dialog" aria-labelledby="receiptModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="receiptModalLabel">Sale Receipt</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="receipt-content">
        <!-- Receipt content will be injected here -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" onclick="printReceipt()">Print Receipt</button>
      </div>
    </div>
  </div>
</div>


<script>
jQuery(document).ready(function($) {
    let cart = [];
    const taxRate = <?php echo $tax_rate_percentage / 100; ?>;
    const currencySymbol = '<?php echo $currency_symbol; ?>';
    const storeName = `<?php echo html_escape($pos_settings['store_name']); ?>`;
    const storeAddress = `<?php echo html_escape($pos_settings['store_address']); ?>`;
    const storePhone = `<?php echo html_escape($pos_settings['store_phone']); ?>`;
    const storeLogoUrl = `<?php echo html_escape($pos_settings['store_logo_url']); ?>`;
    const receiptFooterMessage = `<?php echo html_escape($pos_settings['receipt_footer_message']); ?>`;
    let heldSales = JSON.parse(localStorage.getItem('heldSalesPOS')) || []; // Basic hold sales in localStorage

    // Product Search
    $('#product-search').on('keyup', function() {
        let query = $(this).val();
        if (query.length >= 2) { // Start search after 2 characters
            $.ajax({
                url: '<?php echo $ajax_base_url; ?>&sub_action=search_product',
                method: 'GET',
                data: { query: query },
                dataType: 'json',
                success: function(response) {
                    $('#product-search-results').empty();
                    if (response.success && response.products.length > 0) {
                        response.products.forEach(function(product) {
                            let itemHtml = `
                                <div class="product-result-item" data-product-id="${product.id}"
                                     data-name="${product.name}" data-price="${product.selling_price}"
                                     data-sku="${product.sku}" data-stock="${product.current_stock}">
                                    <img src="${product.image_url ? product.image_url : '<?php echo $base_url ?? ''; ?>/assets/images/default_product.png'}" alt="${product.name}">
                                    <div>
                                        <strong>${product.name}</strong> (SKU: ${product.sku})<br>
                                        Price: ${currencySymbol}${parseFloat(product.selling_price).toFixed(2)} | Stock: ${product.current_stock}
                                    </div>
                                </div>`;
                            $('#product-search-results').append(itemHtml);
                        });
                    } else {
                        $('#product-search-results').html('<p class="p-2 text-muted">No products found.</p>');
                    }
                },
                error: function() {
                    $('#product-search-results').html('<p class="p-2 text-danger">Error searching products.</p>');
                }
            });
        } else {
            $('#product-search-results').empty();
        }
    });

    // Add to Cart from Search Results
    $('#product-search-results').on('click', '.product-result-item', function() {
        const product = {
            id: $(this).data('product-id'),
            name: $(this).data('name'),
            price: parseFloat($(this).data('price')),
            sku: $(this).data('sku'),
            stock: parseInt($(this).data('stock')),
            quantity: 1,
            item_discount: 0.00 // Per item discount, can be added later
        };

        if (product.stock <= 0) {
            alert('Product is out of stock.');
            return;
        }

        const existingItem = cart.find(item => item.id === product.id);
        if (existingItem) {
            if (existingItem.quantity < product.stock) {
                existingItem.quantity++;
            } else {
                alert('Cannot add more than available stock for ' + product.name);
            }
        } else {
            cart.push(product);
        }
        renderCart();
        $('#product-search').val('').focus(); // Clear search and refocus
        $('#product-search-results').empty();
    });

    // Render Cart
    function renderCart() {
        const $cartTableBody = $('#cart-items-table tbody');
        $cartTableBody.empty();
        if (cart.length === 0) {
            $cartTableBody.html('<tr id="cart-empty-row"><td colspan="5" class="text-center text-muted">Cart is empty</td></tr>');
        } else {
            cart.forEach(function(item, index) {
                let itemTotal = (item.price - item.item_discount) * item.quantity;
                let rowHtml = `
                    <tr>
                        <td>${item.name}<br><small class="text-muted">SKU: ${item.sku}</small></td>
                        <td>${currencySymbol}${parseFloat(item.price).toFixed(2)}</td>
                        <td>
                            <input type="number" class="form-control form-control-sm quantity-input"
                                   value="${item.quantity}" min="1" max="${item.stock}" data-index="${index}">
                        </td>
                        <td>${currencySymbol}${itemTotal.toFixed(2)}</td>
                        <td>
                            <button class="btn btn-danger btn-sm remove-item-btn" data-index="${index}"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>`;
                $cartTableBody.append(rowHtml);
            });
        }
        updateSummary();
    }

    // Update Quantity in Cart
    $('#cart-items-table').on('change keyup', '.quantity-input', function() {
        let index = $(this).data('index');
        let newQuantity = parseInt($(this).val());
        if (newQuantity >= 1 && newQuantity <= cart[index].stock) {
            cart[index].quantity = newQuantity;
        } else if (newQuantity > cart[index].stock) {
            $(this).val(cart[index].stock); // Reset to max stock
            cart[index].quantity = cart[index].stock;
            alert('Quantity cannot exceed available stock (' + cart[index].stock + ')');
        } else {
             $(this).val(cart[index].quantity); // Reset to previous if invalid (e.g. 0 or negative)
        }
        renderCart();
    });

    // Remove Item from Cart
    $('#cart-items-table').on('click', '.remove-item-btn', function() {
        let index = $(this).data('index');
        cart.splice(index, 1);
        renderCart();
    });

    // Update Discount
    $('#cart-discount').on('change keyup', function() {
        updateSummary();
    });

    // Update Summary (Subtotal, Tax, Grand Total)
    function updateSummary() {
        let subtotal = 0;
        cart.forEach(item => {
            subtotal += (item.price - item.item_discount) * item.quantity;
        });
        $('#cart-subtotal').text(currencySymbol + subtotal.toFixed(2));

        let discount = parseFloat($('#cart-discount').val()) || 0;
        if (discount < 0) {
            discount = 0;
            $('#cart-discount').val('0.00');
        }
        if (discount > subtotal) { // Discount cannot be more than subtotal
            discount = subtotal;
             $('#cart-discount').val(subtotal.toFixed(2));
        }


        let totalAfterDiscount = subtotal - discount;
        let taxAmount = totalAfterDiscount * taxRate;
        let grandTotal = totalAfterDiscount + taxAmount;

        $('#cart-tax').text(currencySymbol + taxAmount.toFixed(2));
        $('#cart-grandtotal').text(currencySymbol + grandTotal.toFixed(2));
    }

    // Payment Method Selection
    $('.payment-method-btn').on('click', function() {
        $('.payment-method-btn').removeClass('active btn-success').addClass('btn-outline-primary');
        $(this).addClass('active btn-success').removeClass('btn-outline-primary');
        $('#selected-payment-method').val($(this).data('method'));
    });
    // Default select cash
    $('.payment-method-btn[data-method="cash"]').click();


    // Process Sale
    $('#process-sale-btn').on('click', function() {
        if (cart.length === 0) {
            alert('Cart is empty. Please add products to proceed.');
            return;
        }

        const saleData = {
            cart_items: JSON.stringify(cart.map(item => ({ id: item.id, quantity: item.quantity, name: item.name, price: item.price, item_discount: item.item_discount }))), // Send essential data
            customer_id: $('#customer-select').val() || null,
            payment_method: $('#selected-payment-method').val(),
            discount_total: parseFloat($('#cart-discount').val()) || 0,
            tax_rate_applied: taxRate * 100, // Store the rate used
            notes: $('#pos-notes').val(),
            // Totals will be recalculated server-side for accuracy based on DB prices
        };

        // Add CSRF token if you have one
        // saleData.csrf_token = 'your_csrf_token_here';

        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: '<?php echo $ajax_base_url; ?>&sub_action=process_sale',
            method: 'POST',
            data: saleData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    generateReceiptModal(response.receipt_no, response.grand_total);
                    clearSale();
                } else {
                    alert('Error: ' . (response.message || 'Could not process sale.'));
                }
            },
            error: function(xhr, status, error) {
                alert('Sale processing failed. Please try again. ' + error + xhr.responseText);
                console.error(xhr.responseText);
            },
            complete: function() {
                $('#process-sale-btn').prop('disabled', false).html('<i class="fas fa-check-circle"></i> Process Sale');
            }
        });
    });

    // Clear Sale
    $('#clear-sale-btn').on('click', function(){
        if(confirm('Are you sure you want to clear the current sale?')){
            clearSale();
        }
    });

    function clearSale(){
        cart = [];
        renderCart();
        $('#cart-discount').val('0.00');
        $('#customer-select').val('');
        $('#pos-notes').val('');
        $('.payment-method-btn[data-method="cash"]').click(); // Reset to cash
        updateSummary();
        $('#product-search').val('').focus();
        $('#product-search-results').empty();
    }

    // Hold Sale
    $('#hold-sale-btn').on('click', function() {
        if (cart.length === 0) {
            alert('Cart is empty. Nothing to hold.');
            return;
        }
        const heldSaleData = {
            cart: cart,
            discount: $('#cart-discount').val(),
            customer: $('#customer-select').val(),
            notes: $('#pos-notes').val(),
            paymentMethod: $('#selected-payment-method').val(),
            timestamp: new Date().toISOString()
        };
        heldSales.push(heldSaleData);
        localStorage.setItem('heldSalesPOS', JSON.stringify(heldSales));
        alert('Sale held successfully! (' + heldSales.length + ' held sales)');
        clearSale();
        // Could add a UI to list/retrieve held sales
    });

    // Function to generate and show receipt in modal
    function generateReceiptModal(receiptNo, grandTotal) {
        let receiptHTML = `
            <div id="printable-receipt" style="font-family: monospace; font-size: 12px; width: 280px; margin: 0 auto;">
                <h4 style="text-align:center; margin-bottom: 5px;"><?php echo htmlspecialchars($pos_settings['store_name']); ?></h4>
                <p style="text-align:center; margin:0;">Receipt: ${receiptNo}</p>
                <p style="text-align:center; margin:0;">Date: ${new Date().toLocaleString()}</p>
                <hr style="border-top: 1px dashed #000;">
                <table style="width:100%; font-size: 12px;">
                    <thead><tr><th style="text-align:left;">Item</th><th>Qty</th><th style="text-align:right;">Price</th><th style="text-align:right;">Total</th></tr></thead>
                    <tbody>`;

        let modalSubtotal = 0;
        cart.forEach(item => {
            let itemDisplayTotal = (item.price - item.item_discount) * item.quantity;
            modalSubtotal += itemDisplayTotal;
            receiptHTML += `<tr>
                                <td style="text-align:left;">${item.name.substring(0,15)}</td>
                                <td style="text-align:center;">${item.quantity}</td>
                                <td style="text-align:right;">${(item.price - item.item_discount).toFixed(2)}</td>
                                <td style="text-align:right;">${itemDisplayTotal.toFixed(2)}</td>
                            </tr>`;
        });

        receiptHTML += `</tbody></table><hr style="border-top: 1px dashed #000;">`;

        let modalDiscount = parseFloat($('#cart-discount').val()) || 0;
        let modalTotalAfterDiscount = modalSubtotal - modalDiscount;
        let modalTaxAmount = modalTotalAfterDiscount * taxRate;
        // let modalGrandTotal = modalTotalAfterDiscount + modalTaxAmount; // Use server-provided grandTotal for accuracy

        receiptHTML += `<p style="text-align:right; margin:0;">Subtotal: ${currencySymbol}${modalSubtotal.toFixed(2)}</p>`;
        if (modalDiscount > 0) {
            receiptHTML += `<p style="text-align:right; margin:0;">Discount: -${currencySymbol}${modalDiscount.toFixed(2)}</p>`;
        }
        receiptHTML += `<p style="text-align:right; margin:0;">Tax (${tax_rate_percentage}%): ${currencySymbol}${modalTaxAmount.toFixed(2)}</p>`;
        receiptHTML += `<h5 style="text-align:right; margin-top:5px;">Total: ${currencySymbol}${parseFloat(grandTotal).toFixed(2)}</h5>`;
        receiptHTML += `<hr style="border-top: 1px dashed #000;">`;
        receiptHTML += `<p style="text-align:center; margin-top:10px;"><?php echo htmlspecialchars(get_setting_value($current_settings ?? [], 'receipt_footer_message', 'Thank you!')); ?></p>`;
        receiptHTML += `</div>`;

        $('#receipt-content').html(receiptHTML);
        $('#receiptModal').modal('show');
    }

    // Print Receipt function
    window.printReceipt = function() {
        const receiptElement = document.getElementById('printable-receipt');
        if (receiptElement) {
            const printWindow = window.open('', '_blank', 'height=600,width=400');
            printWindow.document.write('<html><head><title>Print Receipt</title>');
            printWindow.document.write('<style>body{font-family:monospace; font-size:12px; margin:10px;} table{width:100%; font-size:12px; border-collapse:collapse;} td,th{padding:2px;} hr{border:0; border-top:1px dashed #000; margin: 5px 0;} h4,h5,p{margin:3px 0;}</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(receiptElement.innerHTML);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            // Timeout needed for some browsers to load content before printing
            setTimeout(function(){ printWindow.print(); printWindow.close(); }, 250);
        }
    }


    // Initial render
    renderCart();
    // Load held sales UI if any (example)
    // console.log('Held Sales on Load:', heldSales);
});
</script>
