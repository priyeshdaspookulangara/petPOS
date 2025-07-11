<?php
// This template is included by modules/inventory/stock_adjustments.php when $page_action is 'add'
// It has access to:
// $adjustment_data (array of current values for the form)
// $form_errors (array of errors)
// $products_list (array of products for dropdown)
// $base_module_url (for form action and cancel link)
// $product_id_to_adjust (pre-selected product ID from GET param)

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}

// Helper to get value safely and htmlspecialchars it
function val_adj($data_array, $key, $default = '') {
    return isset($data_array[$key]) ? htmlspecialchars($data_array[$key]) : htmlspecialchars($default);
}
?>
<div class="container mt-4">
    <h2>Make Stock Adjustment</h2>

    <form action="<?php echo htmlspecialchars($base_module_url . '&sub_action=add'); ?>" method="POST" novalidate>
        <input type="hidden" name="form_action" value="add_adjustment">

        <div class="card">
            <div class="card-body">
                <div class="form-group">
                    <label for="product_id">Product <span class="text-danger">*</span></label>
                    <select class="form-control <?php echo isset($form_errors['product_id']) ? 'is-invalid' : ''; ?>"
                            id="product_id" name="product_id" required>
                        <option value="">-- Select Product --</option>
                        <?php foreach ($products_list as $product): ?>
                            <?php
                                $selected = (val_adj($adjustment_data, 'product_id') == $product['id'] || $product_id_to_adjust == $product['id']);
                                $stock_info = $product['current_stock'] !== null ? " (Stock: " . $product['current_stock'] . ")" : "";
                            ?>
                            <option value="<?php echo htmlspecialchars($product['id']); ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($product['name'] . " (SKU: " . $product['sku'] . ")" . $stock_info); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($form_errors['product_id'])): ?><div class="invalid-feedback"><?php echo $form_errors['product_id']; ?></div><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="type_of_adjustment">Adjustment Type <span class="text-danger">*</span></label>
                    <select class="form-control <?php echo isset($form_errors['type_of_adjustment']) ? 'is-invalid' : ''; ?>"
                            id="type_of_adjustment" name="type_of_adjustment" required>
                        <option value="increase" <?php echo (val_adj($adjustment_data, 'type_of_adjustment') === 'increase') ? 'selected' : ''; ?>>Increase Stock (e.g., found items, non-PO receival)</option>
                        <option value="decrease" <?php echo (val_adj($adjustment_data, 'type_of_adjustment') === 'decrease') ? 'selected' : ''; ?>>Decrease Stock (e.g., damaged, expired, internal use)</option>
                        <option value="initial_stock" <?php echo (val_adj($adjustment_data, 'type_of_adjustment') === 'initial_stock') ? 'selected' : ''; ?>>Set Initial Stock (for new products)</option>
                        <!-- 'correction' type is better handled as a reason for increase/decrease for this form structure -->
                    </select>
                    <?php if (isset($form_errors['type_of_adjustment'])): ?><div class="invalid-feedback"><?php echo $form_errors['type_of_adjustment']; ?></div><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="quantity_changed">Quantity to Adjust By <span class="text-danger">*</span></label>
                    <input type="number" step="1" min="1" class="form-control <?php echo isset($form_errors['quantity_changed']) ? 'is-invalid' : ''; ?>"
                           id="quantity_changed" name="quantity_changed"
                           value="<?php echo val_adj($adjustment_data, 'quantity_changed'); ?>" required>
                    <?php if (isset($form_errors['quantity_changed'])): ?><div class="invalid-feedback"><?php echo $form_errors['quantity_changed']; ?></div><?php endif; ?>
                     <small class="form-text text-muted">Enter a positive number. The 'Adjustment Type' determines if it's added or subtracted.</small>
                </div>

                <div class="form-group">
                    <label for="reason">Reason for Adjustment <span class="text-danger">*</span></label>
                    <textarea class="form-control <?php echo isset($form_errors['reason']) ? 'is-invalid' : ''; ?>"
                              id="reason" name="reason" rows="3" required><?php echo val_adj($adjustment_data, 'reason'); ?></textarea>
                    <?php if (isset($form_errors['reason'])): ?><div class="invalid-feedback"><?php echo $form_errors['reason']; ?></div><?php endif; ?>
                    <small class="form-text text-muted">e.g., "Annual stock count correction", "Damaged goods disposal", "Promotional use".</small>
                </div>

                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Submit Adjustment
                    </button>
                    <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=list'); ?>" class="btn btn-secondary">
                        <i class="fas fa-list"></i> View Adjustment Log
                    </a>
                     <a href="<?php echo htmlspecialchars(dirname($base_module_url,2) . "/index.php?module=inventory&action=stock_levels"); ?>" class="btn btn-info">
                        <i class="fas fa-boxes"></i> View Stock Levels
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Optional: Add JS to update available stock display when product is selected, or client-side validation.
document.addEventListener('DOMContentLoaded', function() {
    const productSelect = document.getElementById('product_id');
    // You can add an event listener to productSelect to show current stock of selected product if needed.
});
</script>
