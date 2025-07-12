<?php
$app_base_path = '/'; // Adjust if your application is in a subdirectory. Should be global.

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

$page_title = "Manage Product";
$product_id = null;
$edit_mode = false;

// Product data for form pre-fill
$name_val = ''; $sku_val = ''; $barcode_val = ''; $description_val = '';
$purchase_price_val = '0.00'; $selling_price_val = '0.00';
$category_id_val = null; $supplier_id_val = null;
$current_stock_val = '0'; $reorder_level_val = '0'; $unit_val = 'pcs';
$image_url_val = '';

// Fetch categories and suppliers for dropdowns
$categories = [];
$cat_sql = "SELECT id, name FROM categories ORDER BY name ASC";
$cat_result = $mysqli->query($cat_sql);
if ($cat_result) while ($row = $cat_result->fetch_assoc()) $categories[] = $row;
if ($cat_result) $cat_result->free();

$suppliers = [];
$sup_sql = "SELECT id, name FROM suppliers ORDER BY name ASC";
$sup_result = $mysqli->query($sup_sql);
if ($sup_result) while ($row = $sup_result->fetch_assoc()) $suppliers[] = $row;
if ($sup_result) $sup_result->free();


// Determine if we are editing an existing product
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    $edit_mode = true;
    $page_title = "Edit Product";

    $sql_fetch_product = "SELECT * FROM products WHERE id = $product_id";
    $result_fetch_product = $mysqli->query($sql_fetch_product);
    if ($result_fetch_product && $result_fetch_product->num_rows > 0) {
        $p = $result_fetch_product->fetch_assoc();
        $name_val = $p['name']; $sku_val = $p['sku']; $barcode_val = $p['barcode']; $description_val = $p['description'];
        $purchase_price_val = $p['purchase_price']; $selling_price_val = $p['selling_price'];
        $category_id_val = $p['category_id']; $supplier_id_val = $p['supplier_id'];
        $current_stock_val = $p['current_stock']; $reorder_level_val = $p['reorder_level']; $unit_val = $p['unit'];
        $image_url_val = $p['image_url'];
        $result_fetch_product->free();
    } else {
        $_SESSION['flash_message'] = "Product not found.";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: " . site_url('admin/products', $app_base_path));
        exit;
    }
} else {
    $page_title = "Add New Product";
}

// Handle DELETE request
if (isset($_GET['action']) && $_GET['action'] == 'delete' && $product_id) {
    // Check if product is in any sale_items or purchase_items
    $error_msg_delete = "";
    $check_sales_sql = "SELECT COUNT(*) as count FROM sale_items WHERE product_id = $product_id";
    $sales_res = $mysqli->query($check_sales_sql);
    if ($sales_res && $sales_res->fetch_assoc()['count'] > 0) {
        $error_msg_delete .= "Product is part of sales transactions. ";
    }
    if($sales_res) $sales_res->free();

    $check_purch_sql = "SELECT COUNT(*) as count FROM purchase_items WHERE product_id = $product_id";
    $purch_res = $mysqli->query($check_purch_sql);
    if ($purch_res && $purch_res->fetch_assoc()['count'] > 0) {
        $error_msg_delete .= "Product is part of purchase orders. ";
    }
    if($purch_res) $purch_res->free();

    // Also check stock adjustments
    $check_stock_adj_sql = "SELECT COUNT(*) as count FROM stock_adjustments WHERE product_id = $product_id";
    $stock_adj_res = $mysqli->query($check_stock_adj_sql);
    if ($stock_adj_res && $stock_adj_res->fetch_assoc()['count'] > 0) {
        $error_msg_delete .= "Product has stock adjustment records. ";
    }
    if($stock_adj_res) $stock_adj_res->free();


    if (!empty($error_msg_delete)) {
        $_SESSION['flash_message'] = "Cannot delete product: " . trim($error_msg_delete) . "Consider deactivating instead if this feature existed.";
        $_SESSION['flash_message_type'] = "danger";
    } else {
        // Also delete image file if it exists and is locally stored (not a URL)
        if (!empty($image_url_val) && !filter_var($image_url_val, FILTER_VALIDATE_URL) && file_exists(__DIR__ . '/../../' . $image_url_val)) {
            unlink(__DIR__ . '/../../' . $image_url_val);
        }
        $sql_delete = "DELETE FROM products WHERE id = $product_id";
        if ($mysqli->query($sql_delete)) {
            $_SESSION['flash_message'] = "Product deleted successfully.";
            $_SESSION['flash_message_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Error deleting product: " . htmlspecialchars($mysqli->error);
            $_SESSION['flash_message_type'] = "danger";
        }
    }
    header("Location: " . site_url('admin/products', $app_base_path));
    exit;
}

// Handle POST request (Add or Update)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize all inputs
    $name = sanitize_input($mysqli, $_POST['name'] ?? '');
    $sku = sanitize_input($mysqli, $_POST['sku'] ?? null); // SKU can be null
    $barcode = sanitize_input($mysqli, $_POST['barcode'] ?? null); // Barcode can be null
    $description = sanitize_input($mysqli, $_POST['description'] ?? '');
    $purchase_price = filter_var($_POST['purchase_price'] ?? '0', FILTER_VALIDATE_FLOAT) !== false ? $_POST['purchase_price'] : '0.00';
    $selling_price = filter_var($_POST['selling_price'] ?? '0', FILTER_VALIDATE_FLOAT) !== false ? $_POST['selling_price'] : '0.00';
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $current_stock = ctype_digit($_POST['current_stock'] ?? '0') ? (int)$_POST['current_stock'] : 0;
    $reorder_level = ctype_digit($_POST['reorder_level'] ?? '0') ? (int)$_POST['reorder_level'] : 0;
    $unit = sanitize_input($mysqli, $_POST['unit'] ?? 'pcs');
    $image_url_input = sanitize_input($mysqli, $_POST['image_url'] ?? ''); // For direct URL input

    // Repopulate form values
    $name_val=$name; $sku_val=$sku; $barcode_val=$barcode; $description_val=$description;
    $purchase_price_val=$purchase_price; $selling_price_val=$selling_price;
    $category_id_val=$category_id; $supplier_id_val=$supplier_id;
    $current_stock_val=$current_stock; $reorder_level_val=$reorder_level; $unit_val=$unit;
    $image_url_val = $image_url_input; // Keep existing image if new one not uploaded or URL not changed

    // Image Upload Handling
    $uploaded_image_path = $edit_mode ? $image_url_val : ''; // Keep old image path if editing and no new upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../assets/uploads/products/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true); // Create if not exists
        }
        $filename = uniqid('prod_', true) . "_" . basename($_FILES['product_image']['name']);
        $target_file = $upload_dir . $filename;
        $image_file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Basic validation for image
        $check = getimagesize($_FILES['product_image']['tmp_name']);
        if ($check === false) {
            $_SESSION['flash_message'] = "Uploaded file is not a valid image.";
            $_SESSION['flash_message_type'] = "danger";
        } elseif ($_FILES['product_image']['size'] > 2 * 1024 * 1024) { // Max 2MB
            $_SESSION['flash_message'] = "Image file is too large (Max 2MB).";
            $_SESSION['flash_message_type'] = "danger";
        } elseif (!in_array($image_file_type, ['jpg', 'jpeg', 'png', 'gif'])) {
            $_SESSION['flash_message'] = "Only JPG, JPEG, PNG & GIF files are allowed for images.";
            $_SESSION['flash_message_type'] = "danger";
        } elseif (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
            // Delete old image if a new one is uploaded during edit
            if ($edit_mode && !empty($image_url_val) && $image_url_val != 'assets/uploads/products/' . $filename && !filter_var($image_url_val, FILTER_VALIDATE_URL) && file_exists(__DIR__ . '/../../' . $image_url_val)) {
                 unlink(__DIR__ . '/../../' . $image_url_val);
            }
            $uploaded_image_path = 'assets/uploads/products/' . $filename; // Relative path to store in DB
        } else {
            $_SESSION['flash_message'] = "Sorry, there was an error uploading your image.";
            $_SESSION['flash_message_type'] = "danger";
        }
    } elseif (!empty($image_url_input) && filter_var($image_url_input, FILTER_VALIDATE_URL)) {
        $uploaded_image_path = $image_url_input; // Use direct URL if provided and valid
    }


    // Validation
    if (empty($name) || empty($selling_price)) {
        $_SESSION['flash_message'] = "Product Name and Selling Price are required.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif ((float)$selling_price < 0 || (float)$purchase_price < 0) {
        $_SESSION['flash_message'] = "Prices cannot be negative.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif ($current_stock < 0 || $reorder_level < 0) {
         $_SESSION['flash_message'] = "Stock and Reorder Level cannot be negative.";
        $_SESSION['flash_message_type'] = "danger";
    } else {
        // SKU and Barcode uniqueness check (if not empty)
        $conflict_fields = [];
        if (!empty($sku)) $conflict_fields[] = "sku = '$sku'";
        if (!empty($barcode)) $conflict_fields[] = "barcode = '$barcode'";

        if (!empty($conflict_fields)) {
            $conflict_check_sql = "SELECT id FROM products WHERE (" . implode(' OR ', $conflict_fields) . ")";
            if ($edit_mode && $product_id) {
                $conflict_check_sql .= " AND id != $product_id";
            }
            $conflict_result = $mysqli->query($conflict_check_sql);
            if ($conflict_result && $conflict_result->num_rows > 0) {
                $_SESSION['flash_message'] = "SKU or Barcode already exists for another product.";
                $_SESSION['flash_message_type'] = "danger";
                if($conflict_result) $conflict_result->free();
                // To avoid nested if/else, use a flag or goto (though goto is generally discouraged)
                $validation_failed = true;
            }
            if(isset($validation_failed) && $validation_failed) { /* Do nothing here, error already set */ }
            else { // Proceed with insert/update
                 if ($edit_mode && $product_id) {
                    $sql_update = "UPDATE products SET
                                    name = '$name', sku = " . ($sku ? "'$sku'" : "NULL") . ", barcode = " . ($barcode ? "'$barcode'" : "NULL") . ",
                                    description = '$description', purchase_price = '$purchase_price', selling_price = '$selling_price',
                                    category_id = " . ($category_id ? $category_id : "NULL") . ", supplier_id = " . ($supplier_id ? $supplier_id : "NULL") . ",
                                    current_stock = $current_stock, reorder_level = $reorder_level, unit = '$unit',
                                    image_url = " . ($uploaded_image_path ? "'$uploaded_image_path'" : "NULL") . "
                                   WHERE id = $product_id";
                    if ($mysqli->query($sql_update)) {
                        $_SESSION['flash_message'] = "Product updated successfully.";
                        $_SESSION['flash_message_type'] = "success";
                        header("Location: " . site_url('admin/products', $app_base_path));
                        exit;
                    } else {
                        $_SESSION['flash_message'] = "Error updating product: " . htmlspecialchars($mysqli->error);
                        $_SESSION['flash_message_type'] = "danger";
                    }
                } else { // Add new product
                    $sql_insert = "INSERT INTO products (name, sku, barcode, description, purchase_price, selling_price, category_id, supplier_id, current_stock, reorder_level, unit, image_url)
                                   VALUES ('$name', " . ($sku ? "'$sku'" : "NULL") . ", " . ($barcode ? "'$barcode'" : "NULL") . ", '$description', '$purchase_price', '$selling_price',
                                           " . ($category_id ? $category_id : "NULL") . ", " . ($supplier_id ? $supplier_id : "NULL") . ",
                                           $current_stock, $reorder_level, '$unit', " . ($uploaded_image_path ? "'$uploaded_image_path'" : "NULL") . ")";
                    if ($mysqli->query($sql_insert)) {
                        $_SESSION['flash_message'] = "Product added successfully.";
                        $_SESSION['flash_message_type'] = "success";
                        header("Location: " . site_url('admin/products', $app_base_path));
                        exit;
                    } else {
                        $_SESSION['flash_message'] = "Error adding product: " . htmlspecialchars($mysqli->error);
                        $_SESSION['flash_message_type'] = "danger";
                    }
                }
            }
        } else { // No SKU or Barcode to check, proceed with insert/update directly
             if ($edit_mode && $product_id) {
                // Duplicate of above update block to avoid complex flag logic
                $sql_update = "UPDATE products SET
                                name = '$name', sku = NULL, barcode = NULL,
                                description = '$description', purchase_price = '$purchase_price', selling_price = '$selling_price',
                                category_id = " . ($category_id ? $category_id : "NULL") . ", supplier_id = " . ($supplier_id ? $supplier_id : "NULL") . ",
                                current_stock = $current_stock, reorder_level = $reorder_level, unit = '$unit',
                                image_url = " . ($uploaded_image_path ? "'$uploaded_image_path'" : "NULL") . "
                               WHERE id = $product_id";
                if ($mysqli->query($sql_update)) {
                    $_SESSION['flash_message'] = "Product updated successfully.";
                    $_SESSION['flash_message_type'] = "success";
                    header("Location: " . site_url('admin/products', $app_base_path));
                    exit;
                } else {
                    $_SESSION['flash_message'] = "Error updating product: " . htmlspecialchars($mysqli->error);
                    $_SESSION['flash_message_type'] = "danger";
                }
            } else { // Add new product
                // Duplicate of above insert block
                 $sql_insert = "INSERT INTO products (name, sku, barcode, description, purchase_price, selling_price, category_id, supplier_id, current_stock, reorder_level, unit, image_url)
                               VALUES ('$name', NULL, NULL, '$description', '$purchase_price', '$selling_price',
                                       " . ($category_id ? $category_id : "NULL") . ", " . ($supplier_id ? $supplier_id : "NULL") . ",
                                       $current_stock, $reorder_level, '$unit', " . ($uploaded_image_path ? "'$uploaded_image_path'" : "NULL") . ")";
                if ($mysqli->query($sql_insert)) {
                    $_SESSION['flash_message'] = "Product added successfully.";
                    $_SESSION['flash_message_type'] = "success";
                    header("Location: " . site_url('admin/products', $app_base_path));
                    exit;
                } else {
                    $_SESSION['flash_message'] = "Error adding product: " . htmlspecialchars($mysqli->error);
                    $_SESSION['flash_message_type'] = "danger";
                }
            }
        }
    }
    // If execution reaches here after POST, it means there was a validation error handled by setting flash message
    // and the form will be re-displayed with user's input and the error message.
}


include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo $edit_mode ? "Edit Product Details" : "Add New Product Form"; ?></h6>
        </div>
        <div class="card-body">
            <form action="<?php echo site_url('admin/manage-product/' . ($edit_mode && $product_id ? '?id=' . $product_id : ''), $app_base_path); ?>" method="post" enctype="multipart/form-data" novalidate>
                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label for="name">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name_val); ?>" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="sku">SKU (Stock Keeping Unit)</label>
                        <input type="text" class="form-control" id="sku" name="sku" value="<?php echo htmlspecialchars($sku_val); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="barcode">Barcode (EAN, UPC, etc.)</label>
                    <input type="text" class="form-control" id="barcode" name="barcode" value="<?php echo htmlspecialchars($barcode_val); ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($description_val); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="category_id">Category</label>
                        <select class="form-control" id="category_id" name="category_id">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo ($category_id_val == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="supplier_id">Supplier</label>
                        <select class="form-control" id="supplier_id" name="supplier_id">
                            <option value="">-- Select Supplier --</option>
                             <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>" <?php echo ($supplier_id_val == $supplier['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="purchase_price">Purchase Price</label>
                        <input type="number" step="0.01" class="form-control" id="purchase_price" name="purchase_price" value="<?php echo htmlspecialchars($purchase_price_val); ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="selling_price">Selling Price <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="selling_price" name="selling_price" value="<?php echo htmlspecialchars($selling_price_val); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="current_stock">Current Stock</label>
                        <input type="number" step="1" class="form-control" id="current_stock" name="current_stock" value="<?php echo htmlspecialchars($current_stock_val); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="reorder_level">Reorder Level</label>
                        <input type="number" step="1" class="form-control" id="reorder_level" name="reorder_level" value="<?php echo htmlspecialchars($reorder_level_val); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="unit">Unit (e.g., pcs, kg, box)</label>
                        <input type="text" class="form-control" id="unit" name="unit" value="<?php echo htmlspecialchars($unit_val); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="product_image">Product Image (Max 2MB: JPG, PNG, GIF)</label>
                    <input type="file" class="form-control-file" id="product_image" name="product_image">
                    <?php if ($edit_mode && !empty($image_url_val)): ?>
                        <small class="form-text text-muted">Current image:
                            <?php if (filter_var($image_url_val, FILTER_VALIDATE_URL)): ?>
                                <a href="<?php echo htmlspecialchars($image_url_val); ?>" target="_blank"><?php echo htmlspecialchars($image_url_val); ?></a>
                                <img src="<?php echo htmlspecialchars($image_url_val); ?>" alt="Current Product Image" style="max-width: 100px; max-height: 100px; margin-top: 5px;">
                            <?php else: // Local file ?>
                                <a href="<?php echo site_url($image_url_val, $app_base_path); ?>" target="_blank"><?php echo htmlspecialchars($image_url_val); ?></a>
                                <img src="<?php echo site_url($image_url_val, $app_base_path); ?>" alt="Current Product Image" style="max-width: 100px; max-height: 100px; margin-top: 5px;">
                            <?php endif; ?>
                        </small>
                    <?php endif; ?>
                </div>
                 <div class="form-group">
                    <label for="image_url">Or Image URL</label>
                    <input type="url" class="form-control" id="image_url" name="image_url" value="<?php echo ($edit_mode && filter_var($image_url_val, FILTER_VALIDATE_URL)) ? htmlspecialchars($image_url_val) : ''; ?>" placeholder="https://example.com/image.jpg">
                    <small class="form-text text-muted">If you provide a URL here, it will be used instead of an uploaded file. If you upload a file, this URL will be ignored.</small>
                </div>


                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_mode ? '<i class="fas fa-save"></i> Update Product' : '<i class="fas fa-plus-circle"></i> Add Product'; ?>
                </button>
                <a href="<?php echo site_url('admin/products', $app_base_path); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </form>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../templates/footer.php';
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
