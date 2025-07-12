<?php
$page_title = "Point of Sale";
$app_base_path = '/'; // Adjust if your application is in a subdirectory.

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

redirect_if_not_logged_in(); // Accessible by Admin and Cashier

// Fetch products for display
$products = [];
// Only fetch products that are in stock, or fetch all and handle display in JS/HTML
// For simplicity, let's fetch all and let JS/HTML indicate stock status.
// Could also add `WHERE current_stock > 0` if preferred.
$sql_products = "SELECT id, name, sku, selling_price, current_stock, image_url
                 FROM products
                 ORDER BY name ASC";
$result_products = $mysqli->query($sql_products);
if ($result_products) {
    while ($row = $result_products->fetch_assoc()) {
        $products[] = $row;
    }
    $result_products->free();
} else {
    // Handle error, though POS should ideally still load with an error message
    $pos_error_message = "Error fetching products: " . htmlspecialchars($mysqli->error);
}

// Fetch settings like tax rate for JS use later
$settings = [];
$sql_settings = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('tax_rate_percentage', 'currency_symbol')";
$result_settings = $mysqli->query($sql_settings);
if($result_settings){
    while($row = $result_settings->fetch_assoc()){
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $result_settings->free();
}
$tax_rate = isset($settings['tax_rate_percentage']) ? (float)$settings['tax_rate_percentage'] : 0.00;
$currency_symbol = isset($settings['currency_symbol']) ? htmlspecialchars($settings['currency_symbol']) : '$';

// We need header/footer, but POS might have a slightly different layout (e.g. full width, minimal header)
// For now, use standard header/footer. Can customize later.
include __DIR__ . '/../templates/header.php';
?>
<style>
    /* POS specific styles */
    #pos-container {
        display: flex;
        flex-wrap: wrap;
        height: calc(100vh - 56px - 40px); /* Full viewport height minus navbar and some padding */
        overflow: hidden; /* Prevent scrolling on main container */
    }
    #product-list-area {
        flex: 3; /* Takes up 3 parts of the space */
        overflow-y: auto;
        padding: 15px;
        background-color: #f8f9fa;
        border-right: 1px solid #dee2e6;
    }
    #cart-area {
        flex: 2; /* Takes up 2 parts of the space */
        padding: 15px;
        display: flex;
        flex-direction: column;
        background-color: #fff;
    }
    .product-card {
        cursor: pointer;
        margin-bottom: 15px;
        transition: transform 0.1s ease-in-out, box-shadow 0.1s ease-in-out;
    }
    .product-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .product-card img {
        max-height: 120px;
        object-fit: contain; /* Use 'contain' to ensure whole image is visible */
        margin-top: 10px;
    }
    .product-card .card-body {
        padding: 0.8rem;
    }
    .product-card .card-title {
        font-size: 0.95rem;
        margin-bottom: 0.25rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .product-card .card-text {
        font-size: 0.9rem;
    }
    .product-card .product-price {
        font-weight: bold;
        color: #28a745; /* Green price */
    }
    .out-of-stock {
        opacity: 0.6;
        cursor: not-allowed;
        background-color: #e9ecef;
    }
    .out-of-stock .card-body::after {
        content: "Out of Stock";
        position: absolute;
        top: 40%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-15deg);
        color: red;
        font-weight: bold;
        font-size: 1.2em;
        padding: 5px 10px;
        background-color: rgba(255,255,255,0.8);
        border: 1px solid red;
        z-index:10;
    }

    #cart-items-list {
        flex-grow: 1;
        overflow-y: auto;
        margin-bottom: 15px;
        font-size: 0.9rem;
    }
    #cart-items-list .list-group-item {
        padding: 0.5rem 0.8rem;
    }
    .cart-item-actions button {
        padding: 0.1rem 0.4rem;
        font-size: 0.8rem;
    }
    .cart-item-qty-input {
        width: 50px;
        text-align: center;
        font-size: 0.9rem;
        margin: 0 5px;
        padding: 0.2rem;
    }
    #pos-totals table td, #pos-totals table th {
        padding: 0.4rem;
        font-size: 0.95rem;
    }
    #pos-payment-area .btn { margin-top: 5px; }

    /* Sticky sidebar for cart - if product list is very long */
    /* This is a simple approach; more robust might need JS */
    @media (min-width: 768px) { /* Apply only on medium screens and up */
      #cart-area {
        height: calc(100vh - 56px - 40px); /* Adjust based on header/footer */
        position: sticky;
        top: 56px; /* Height of the navbar */
      }
    }
    .product-search-filter {
        position: sticky;
        top: 0;
        background-color: #f8f9fa; /* Match product list area bg */
        padding-bottom: 10px;
        z-index: 100; /* Ensure it stays on top of product cards when scrolling */
        border-bottom: 1px solid #dee2e6;
        margin-bottom:15px;
    }
</style>

<div class="container-fluid mt-0 p-0">
    <div id="pos-container">
        <!-- Product List Area -->
        <div id="product-list-area">
            <div class="product-search-filter">
                <input type="text" id="productSearchInput" class="form-control" placeholder="Search products by Name or SKU...">
            </div>
            <div class="row" id="productDisplayRow">
                <?php if (!empty($pos_error_message)): ?>
                    <div class="col-12"><div class="alert alert-danger"><?php echo $pos_error_message; ?></div></div>
                <?php endif; ?>
                <?php if (empty($products) && empty($pos_error_message)): ?>
                    <div class="col-12"><div class="alert alert-info">No products found in the system.</div></div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 product-item-col"
                             data-name="<?php echo strtolower(htmlspecialchars($product['name'])); ?>"
                             data-sku="<?php echo strtolower(htmlspecialchars($product['sku'] ?? '')); ?>">
                            <div class="card product-card <?php echo ($product['current_stock'] <= 0) ? 'out-of-stock' : ''; ?>"
                                 <?php if ($product['current_stock'] > 0): ?>
                                 onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>', <?php echo $product['selling_price']; ?>, <?php echo $product['current_stock']; ?>)"
                                 <?php endif; ?>>
                                <?php
                                $image_path = $app_base_path . 'assets/uploads/products/placeholder.png'; // Default placeholder
                                if (!empty($product['image_url'])) {
                                    if (filter_var($product['image_url'], FILTER_VALIDATE_URL)) {
                                        $image_path = htmlspecialchars($product['image_url']);
                                    } else {
                                        // Check if local file exists, otherwise use placeholder
                                        $local_image_full_path = __DIR__ . '/../' . $product['image_url']; // Relative to project root
                                        if (file_exists($local_image_full_path)) {
                                           $image_path = site_url($product['image_url'], $app_base_path);
                                        }
                                    }
                                }
                                ?>
                                <img src="<?php echo $image_path; ?>" class="card-img-top mx-auto" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <div class="card-body text-center">
                                    <h5 class="card-title" title="<?php echo htmlspecialchars($product['name']); ?>"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="card-text product-price"><?php echo $currency_symbol; ?><?php echo htmlspecialchars(number_format($product['selling_price'], 2)); ?></p>
                                    <p class="card-text"><small>Stock: <?php echo htmlspecialchars($product['current_stock']); ?><?php if($product['sku']) { echo ' | SKU: '.htmlspecialchars($product['sku']); } ?></small></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cart & Payment Area -->
        <aside id="cart-area">
            <h5>Order Cart <button id="clearCartBtn" class="btn btn-sm btn-outline-danger float-right <?php /* Hidden if cart empty */ ?>"><i class="fas fa-trash-alt"></i> Clear</button></h5>
            <hr>
            <div id="cart-items-list">
                <!-- Cart items will be rendered here by JavaScript -->
                <p class="text-muted text-center cart-empty-msg">Your cart is empty.</p>
            </div>
            <div id="pos-totals" class="mt-auto">
                <table class="table table-sm">
                    <tbody>
                        <tr>
                            <th>Subtotal:</th>
                            <td class="text-right"><span id="cartSubtotal"><?php echo $currency_symbol; ?>0.00</span></td>
                        </tr>
                        <tr>
                            <th>Discount:</th>
                            <td class="text-right">
                                <input type="number" step="0.01" min="0" id="discountAmountInput" class="form-control form-control-sm d-inline-block" style="width: 70px;" placeholder="Amt">
                                <!-- Or Percentage -->
                                <!-- <input type="number" step="1" min="0" max="100" id="discountPercentInput" class="form-control form-control-sm d-inline-block" style="width: 60px;" placeholder="%"> -->
                                <span id="cartDiscount"><?php echo $currency_symbol; ?>0.00</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Tax (<?php echo htmlspecialchars(number_format($tax_rate, 2)); ?>%):</th>
                            <td class="text-right"><span id="cartTax"><?php echo $currency_symbol; ?>0.00</span></td>
                        </tr>
                        <tr class="table-active" style="font-size: 1.1rem;">
                            <th >Grand Total:</th>
                            <td class="text-right font-weight-bold"><span id="cartGrandTotal"><?php echo $currency_symbol; ?>0.00</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div id="pos-payment-area">
                <div class="form-group">
                    <label for="paymentMethod">Payment Method:</label>
                    <select id="paymentMethod" class="form-control form-control-sm">
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <!-- Add more methods as needed -->
                    </select>
                </div>
                <div class="form-group" id="amountTenderedGroup">
                    <label for="amountTendered">Amount Tendered:</label>
                    <input type="number" step="0.01" min="0" id="amountTendered" class="form-control form-control-sm">
                </div>
                <div class="form-group" id="changeDueGroup">
                    <strong>Change Due:</strong> <span id="changeDue" class="font-weight-bold"><?php echo $currency_symbol; ?>0.00</span>
                </div>
                <button id="processSaleBtn" class="btn btn-success btn-block btn-lg"><i class="fas fa-check-circle"></i> Process Sale</button>
            </div>
        </aside>
    </div>
</div>

<?php
// POS page might not need standard footer or a simplified one
// include __DIR__ . '/../templates/footer.php';
?>
<!-- Instead of full footer, just JS links for POS -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js" integrity="sha384-q9CRHqZndzlxGLOj+xrdLDJa9ittGte1NksRmgJKeCV9LN7G+FP3r9jgjP8G7WeS" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" crossorigin="anonymous"></script>
<script src="<?php echo site_url('assets/js/main.js', $app_base_path); ?>"></script> <?php // If you have a global main.js ?>

<script>
    // POS JavaScript (Cart, Calculations, Product Search) will go here in the next step.
    // For now, basic product search:
    const productSearchInput = document.getElementById('productSearchInput');
    const productItems = document.querySelectorAll('.product-item-col');
    const taxRate = <?php echo $tax_rate / 100; // Convert percentage to decimal for JS ?>;
    const currencySymbol = '<?php echo $currency_symbol; ?>';

    productSearchInput.addEventListener('keyup', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        productItems.forEach(item => {
            const name = item.dataset.name.toLowerCase();
            const sku = item.dataset.sku.toLowerCase();
            if (name.includes(searchTerm) || sku.includes(searchTerm)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    });

    let cart = [];
    const cartItemsList = document.getElementById('cart-items-list');
    const cartSubtotalEl = document.getElementById('cartSubtotal');
    const cartDiscountEl = document.getElementById('cartDiscount');
    const cartTaxEl = document.getElementById('cartTax');
    const cartGrandTotalEl = document.getElementById('cartGrandTotal');
    const discountAmountInput = document.getElementById('discountAmountInput');
    const processSaleBtn = document.getElementById('processSaleBtn');
    const clearCartBtn = document.getElementById('clearCartBtn');
    const cartEmptyMsg = document.querySelector('.cart-empty-msg');
    const amountTenderedInput = document.getElementById('amountTendered');
    const changeDueEl = document.getElementById('changeDue');


    function findCartItem(productId) {
        return cart.find(item => item.id === productId);
    }

    function addToCart(productId, name, price, availableStock) {
        if (availableStock <= 0) {
            alert("This product is out of stock.");
            return;
        }
        const existingItem = findCartItem(productId);
        if (existingItem) {
            if (existingItem.quantity < availableStock) {
                existingItem.quantity++;
            } else {
                alert(`Cannot add more ${name}. Maximum stock (${availableStock}) reached in cart.`);
            }
        } else {
            cart.push({ id: productId, name: name, price: parseFloat(price), quantity: 1, stock: parseInt(availableStock) });
        }
        renderCart();
    }

    function updateCartQuantity(productId, newQuantity) {
        const item = findCartItem(productId);
        if (item) {
            newQuantity = parseInt(newQuantity);
            if (newQuantity <= 0) {
                removeFromCart(productId);
            } else if (newQuantity > item.stock) {
                item.quantity = item.stock;
                alert(`Quantity for ${item.name} cannot exceed available stock (${item.stock}). Set to max available.`);
            } else {
                item.quantity = newQuantity;
            }
        }
        renderCart();
    }

    function removeFromCart(productId) {
        cart = cart.filter(item => item.id !== productId);
        renderCart();
    }

    function clearCart() {
        cart = [];
        renderCart();
    }
    if(clearCartBtn) clearCartBtn.addEventListener('click', clearCart);


    function renderCart() {
        cartItemsList.innerHTML = ''; // Clear current items
        if (cart.length === 0) {
            if(cartEmptyMsg) cartEmptyMsg.style.display = 'block';
            if(clearCartBtn) clearCartBtn.style.display = 'none';
            processSaleBtn.disabled = true;
        } else {
            if(cartEmptyMsg) cartEmptyMsg.style.display = 'none';
            if(clearCartBtn) clearCartBtn.style.display = 'inline-block';
            processSaleBtn.disabled = false;

            const ul = document.createElement('ul');
            ul.className = 'list-group list-group-flush';
            cart.forEach(item => {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center';
                li.innerHTML = `
                    <div style="flex-grow: 1;">
                        <small>${item.name}</small><br>
                        <small class="text-muted">${currencySymbol}${item.price.toFixed(2)}</small>
                    </div>
                    <div class="cart-item-actions d-flex align-items-center">
                        <button class="btn btn-sm btn-outline-secondary decrement-qty" data-id="${item.id}">-</button>
                        <input type="number" class="form-control form-control-sm cart-item-qty-input" value="${item.quantity}" min="1" max="${item.stock}" data-id="${item.id}">
                        <button class="btn btn-sm btn-outline-secondary increment-qty" data-id="${item.id}">+</button>
                        <button class="btn btn-sm btn-outline-danger remove-item ml-2" data-id="${item.id}"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="text-right ml-2" style="min-width: 60px;">
                        <small>${currencySymbol}${(item.price * item.quantity).toFixed(2)}</small>
                    </div>
                `;
                ul.appendChild(li);
            });
            cartItemsList.appendChild(ul);

            // Attach event listeners to new cart item controls
            cartItemsList.querySelectorAll('.decrement-qty').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = parseInt(this.dataset.id);
                    const item = findCartItem(id);
                    if (item) updateCartQuantity(id, item.quantity - 1);
                });
            });
            cartItemsList.querySelectorAll('.increment-qty').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = parseInt(this.dataset.id);
                    const item = findCartItem(id);
                    if (item) updateCartQuantity(id, item.quantity + 1);
                });
            });
            cartItemsList.querySelectorAll('.cart-item-qty-input').forEach(input => {
                input.addEventListener('change', function() { // Or 'blur' or 'keyup'
                    const id = parseInt(this.dataset.id);
                    updateCartQuantity(id, this.value);
                });
            });
            cartItemsList.querySelectorAll('.remove-item').forEach(btn => {
                btn.addEventListener('click', function() {
                    removeFromCart(parseInt(this.dataset.id));
                });
            });
        }
        calculateTotals();
    }

    function calculateTotals() {
        let subtotal = 0;
        cart.forEach(item => {
            subtotal += item.price * item.quantity;
        });
        cartSubtotalEl.textContent = currencySymbol + subtotal.toFixed(2);

        let discount = parseFloat(discountAmountInput.value) || 0;
        // Basic discount validation: not more than subtotal
        if (discount > subtotal) {
            discount = subtotal;
            discountAmountInput.value = discount.toFixed(2);
        }
        cartDiscountEl.textContent = currencySymbol + discount.toFixed(2);

        const taxableAmount = subtotal - discount;
        const tax = taxableAmount * taxRate;
        cartTaxEl.textContent = currencySymbol + tax.toFixed(2);

        const grandTotal = taxableAmount + tax;
        cartGrandTotalEl.textContent = currencySymbol + grandTotal.toFixed(2);

        calculateChange(); // Update change whenever totals change
    }

    function calculateChange() {
        const grandTotal = parseFloat(cartGrandTotalEl.textContent.replace(currencySymbol, '')) || 0;
        const tendered = parseFloat(amountTenderedInput.value) || 0;
        let change = 0;
        if (paymentMethodSelect.value === 'Cash' && tendered >= grandTotal) {
            change = tendered - grandTotal;
        }
        changeDueEl.textContent = currencySymbol + change.toFixed(2);
    }

    // Initial render
    renderCart();

    // Event listeners for discount and amount tendered
    discountAmountInput.addEventListener('input', calculateTotals);
    if(amountTenderedInput) amountTenderedInput.addEventListener('input', calculateChange);


    // Process Sale Button Logic
    if(processSaleBtn) {
        processSaleBtn.addEventListener('click', function() {
            if (cart.length === 0) {
                alert("Cart is empty. Please add products to proceed.");
                return;
            }

            // Basic validation (more robust server-side)
            const currentGrandTotal = parseFloat(cartGrandTotalEl.textContent.replace(currencySymbol, '')) || 0;
            if (paymentMethodSelect.value === 'Cash') {
                const tendered = parseFloat(amountTenderedInput.value) || 0;
                if (tendered < currentGrandTotal) {
                    alert("Amount tendered is less than the grand total for a cash sale.");
                    return;
                }
            }

            this.disabled = true; // Prevent double-clicking
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            const saleData = {
                cart: cart.map(item => ({ // Send only necessary data
                    id: item.id,
                    quantity: item.quantity,
                    price_at_sale: item.price // Price at the time of sale
                    // discount_per_item: 0 // Implement if item-level discount is added
                })),
                subtotal: parseFloat(cartSubtotalEl.textContent.replace(currencySymbol, '')),
                discount_total: parseFloat(cartDiscountEl.textContent.replace(currencySymbol, '')),
                tax_rate: taxRate * 100, // Send original percentage
                tax_amount: parseFloat(cartTaxEl.textContent.replace(currencySymbol, '')),
                grand_total: currentGrandTotal,
                payment_method: paymentMethodSelect.value,
                amount_tendered: paymentMethodSelect.value === 'Cash' ? (parseFloat(amountTenderedInput.value) || 0) : null,
                // customer_id: null // Add customer selection later if needed
            };

            fetch('<?php echo site_url("pos/process-sale", $app_base_path); ?>', { // Assuming process-sale/index.php or .htaccess for clean URL
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest' // Common practice for identifying AJAX
                },
                body: JSON.stringify(saleData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Sale processed successfully! Receipt No: ' + data.receipt_no);
                    clearCart(); // Clear cart on frontend
                    // Reset payment fields
                    discountAmountInput.value = '';
                    if(amountTenderedInput) amountTenderedInput.value = '';
                    paymentMethodSelect.value = 'Cash'; // Reset to default
                    calculateTotals(); // Recalculate to reset display

                    // Provide link to receipt
                    if (data.sale_id) {
                        const receiptLink = '<?php echo site_url("pos/receipt/?id=", $app_base_path); ?>' + data.sale_id;
                        // Could display this link more elegantly, e.g., in a modal or specific div
                        console.log("Receipt link: ", receiptLink);
                        // Example: window.open(receiptLink, '_blank'); // Opens receipt in new tab
                        alert("View receipt: " + receiptLink); // Simple alert for now
                    }
                     // Refresh product quantities on page if possible, or prompt user
                    // For now, we rely on next page load or manual refresh to see stock updates.
                    // A more advanced solution would re-fetch product data via AJAX.
                    // Quick and dirty way: location.reload(); (but user loses context of success)

                } else {
                    alert('Error processing sale: ' + (data.message || 'Unknown error.'));
                }
            })
            .catch(error => {
                console.error('Sale processing error:', error);
                alert('An unexpected error occurred while processing the sale. Please try again.');
            })
            .finally(() => {
                this.disabled = false; // Re-enable button
                this.innerHTML = '<i class="fas fa-check-circle"></i> Process Sale';
            });
        });
    }


    // Payment method toggle for amount tendered
    const paymentMethodSelect = document.getElementById('paymentMethod');
    const amountTenderedGroup = document.getElementById('amountTenderedGroup');
    const changeDueGroup = document.getElementById('changeDueGroup');

    paymentMethodSelect.addEventListener('change', function() {
        if (this.value === 'Cash') {
            amountTenderedGroup.style.display = 'block';
            changeDueGroup.style.display = 'block';
        } else {
            amountTenderedGroup.style.display = 'none';
            changeDueGroup.style.display = 'none';
            document.getElementById('amountTendered').value = '';
            document.getElementById('changeDue').textContent = currencySymbol + '0.00';
        }
    });
    // Initial state based on default payment method
    if (paymentMethodSelect.value !== 'Cash') {
        amountTenderedGroup.style.display = 'none';
        changeDueGroup.style.display = 'none';
    }

</script>
</body>
</html>
<?php
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
