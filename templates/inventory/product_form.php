<?php
// This template is included by modules/inventory/products.php when $page_action is 'add' or 'edit'
// It has access to:
// $page_action ('add' or 'edit')
// $product_id (for edit mode)
// $product_data (array of current product values for the form)
// $form_errors (array of errors)
// $base_module_self_url (for form action and cancel link)
// $categories_list (array of categories for dropdown)
// $suppliers_list (array of suppliers for dropdown)

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}

$form_mode = ($page_action === 'edit') ? 'Edit' : 'Add'; // $page_action is from the controller
$submit_action = ($page_action === 'edit') ? 'edit' : 'add';

// Helper to get value safely and htmlspecialchars it
function val($data_array, $key, $default = '') {
    return isset($data_array[$key]) ? htmlspecialchars($data_array[$key]) : htmlspecialchars($default);
}
function val_num($data_array, $key, $default = '0') {
    return isset($data_array[$key]) ? htmlspecialchars(number_format((float)$data_array[$key], 2, '.', '')) : htmlspecialchars(number_format((float)$default,2,'.',''));
}
function val_int($data_array, $key, $default = '0') {
    return isset($data_array[$key]) ? htmlspecialchars((int)$data_array[$key]) : htmlspecialchars((int)$default);
}

?>
<div class="container mt-4">
    <h2><?php echo $form_mode; ?> Product</h2>

    <form action="<?php echo htmlspecialchars($base_module_self_url); ?>" method="POST" novalidate>
        <input type="hidden" name="form_action" value="<?php echo $submit_action; ?>">
        <?php if ($page_action === 'edit'): ?>
            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_id); ?>">
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($form_errors['name']) ? 'is-invalid' : ''; ?>"
                                   id="name" name="name" value="<?php echo val($product_data, 'name'); ?>" required>
                            <?php if (isset($form_errors['name'])): ?><div class="invalid-feedback"><?php echo $form_errors['name']; ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="sku">SKU (Stock Keeping Unit) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($form_errors['sku']) ? 'is-invalid' : ''; ?>"
                                   id="sku" name="sku" value="<?php echo val($product_data, 'sku'); ?>" required <?php // if ($page_action === 'edit') echo 'readonly'; ?>>
                            <?php if (isset($form_errors['sku'])): ?><div class="invalid-feedback"><?php echo $form_errors['sku']; ?></div><?php endif; ?>
                            <?php // if ($page_action === 'edit'): ?><!-- <small class="form-text text-muted">SKU cannot be changed after creation.</small> --><?php // endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="category_id">Category</label>
                            <select class="form-control <?php echo isset($form_errors['category_id']) ? 'is-invalid' : ''; ?>" id="category_id" name="category_id">
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories_list as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo (val($product_data, 'category_id') == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($form_errors['category_id'])): ?><div class="invalid-feedback"><?php echo $form_errors['category_id']; ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="supplier_id">Supplier</label>
                            <select class="form-control <?php echo isset($form_errors['supplier_id']) ? 'is-invalid' : ''; ?>" id="supplier_id" name="supplier_id">
                                <option value="">-- Select Supplier --</option>
                                <?php foreach ($suppliers_list as $supplier): ?>
                                    <option value="<?php echo htmlspecialchars($supplier['id']); ?>" <?php echo (val($product_data, 'supplier_id') == $supplier['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($supplier['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($form_errors['supplier_id'])): ?><div class="invalid-feedback"><?php echo $form_errors['supplier_id']; ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control <?php echo isset($form_errors['description']) ? 'is-invalid' : ''; ?>"
                              id="description" name="description" rows="3"><?php echo val($product_data, 'description'); ?></textarea>
                    <?php if (isset($form_errors['description'])): ?><div class="invalid-feedback"><?php echo $form_errors['description']; ?></div><?php endif; ?>
                </div>

                <hr>
                <h5>Pricing & Stock</h5>

                <div class="row">
                    <div class="col-md-6 col-lg-3">
                        <div class="form-group">
                            <label for="purchase_price">Purchase Price <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control <?php echo isset($form_errors['purchase_price']) ? 'is-invalid' : ''; ?>"
                                   id="purchase_price" name="purchase_price" value="<?php echo val_num($product_data, 'purchase_price', '0.00'); ?>" required>
                            <?php if (isset($form_errors['purchase_price'])): ?><div class="invalid-feedback"><?php echo $form_errors['purchase_price']; ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="form-group">
                            <label for="selling_price">Selling Price <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control <?php echo isset($form_errors['selling_price']) ? 'is-invalid' : ''; ?>"
                                   id="selling_price" name="selling_price" value="<?php echo val_num($product_data, 'selling_price', '0.00'); ?>" required>
                            <?php if (isset($form_errors['selling_price'])): ?><div class="invalid-feedback"><?php echo $form_errors['selling_price']; ?></div><?php endif; ?>
                        </div>
                    </div>
                     <div class="col-md-6 col-lg-3">
                        <div class="form-group">
                            <label for="unit">Unit of Measure <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($form_errors['unit']) ? 'is-invalid' : ''; ?>"
                                   id="unit" name="unit" value="<?php echo val($product_data, 'unit', 'pcs'); ?>" required>
                            <?php if (isset($form_errors['unit'])): ?><div class="invalid-feedback"><?php echo $form_errors['unit']; ?></div><?php endif; ?>
                            <small class="form-text text-muted">e.g., pcs, kg, box, liter</small>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 col-lg-3">
                        <div class="form-group">
                            <label for="current_stock">Current Stock <span class="text-danger">*</span></label>
                            <input type="number" step="1" class="form-control <?php echo isset($form_errors['current_stock']) ? 'is-invalid' : ''; ?>"
                                   id="current_stock" name="current_stock" value="<?php echo val_int($product_data, 'current_stock', '0'); ?>" required
                                   <?php // if ($page_action === 'edit') echo 'readonly'; /* Stock usually managed by transactions */ ?>>
                            <?php if (isset($form_errors['current_stock'])): ?><div class="invalid-feedback"><?php echo $form_errors['current_stock']; ?></div><?php endif; ?>
                            <?php if ($page_action === 'edit'): ?><small class="form-text text-muted">Stock is primarily updated via Sales, Purchases, and Adjustments.</small><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="form-group">
                            <label for="reorder_level">Reorder Level <span class="text-danger">*</span></label>
                            <input type="number" step="1" class="form-control <?php echo isset($form_errors['reorder_level']) ? 'is-invalid' : ''; ?>"
                                   id="reorder_level" name="reorder_level" value="<?php echo val_int($product_data, 'reorder_level', '0'); ?>" required>
                            <?php if (isset($form_errors['reorder_level'])): ?><div class="invalid-feedback"><?php echo $form_errors['reorder_level']; ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>

                <hr>
                <h5>Optional Information</h5>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="barcode">Barcode</label>
                            <input type="text" class="form-control <?php echo isset($form_errors['barcode']) ? 'is-invalid' : ''; ?>"
                                   id="barcode" name="barcode" value="<?php echo val($product_data, 'barcode'); ?>">
                            <?php if (isset($form_errors['barcode'])): ?><div class="invalid-feedback"><?php echo $form_errors['barcode']; ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="image_url">Image URL</label>
                            <input type="text" class="form-control <?php echo isset($form_errors['image_url']) ? 'is-invalid' : ''; ?>"
                                   id="image_url" name="image_url" value="<?php echo val($product_data, 'image_url'); ?>">
                            <?php if (isset($form_errors['image_url'])): ?><div class="invalid-feedback"><?php echo $form_errors['image_url']; ?></div><?php endif; ?>
                            <small class="form-text text-muted">e.g., assets/images/product.jpg or http://example.com/image.jpg</small>
                        </div>
                    </div>
                </div>

                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $form_mode === 'Edit' ? 'Save Changes' : 'Add Product'; ?>
                    </button>
                    <a href="<?php echo htmlspecialchars($base_module_self_url); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
