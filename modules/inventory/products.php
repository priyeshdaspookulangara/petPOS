<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
require_once BASE_PATH . '/config/db.php'; // For $conn, escape_string

if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Security check: Only admins can access this page (adjust if other roles need access)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Access denied. You must be an admin to manage products.";
    $_SESSION['message_type'] = "danger";
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $app_path = dirname($_SERVER['SCRIPT_NAME']);
    if ($app_path === '/' || $app_path === '\\') $app_path = '';
    header("Location: " . $protocol . "://" . $host . $app_path . "/index.php?page=dashboard");
    exit;
}

$base_module_self_url = APP_INDEX_URL . "?module=inventory&action=products";

$page_action = isset($_GET['sub_action']) ? $_GET['sub_action'] : 'list'; // list, add, edit, delete, export_csv, import_csv_form, process_import_csv
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Initialize product data array and form errors
$product_data = [
    'sku' => '', 'barcode' => '', 'name' => '', 'description' => '',
    'purchase_price' => '0.00', 'selling_price' => '0.00', 'current_stock' => '0',
    'reorder_level' => '0', 'unit' => 'pcs', 'category_id' => null,
    'supplier_id' => null, 'image_url' => ''
];
$form_errors = [];

// Fetch categories and suppliers for dropdowns in the form
$categories_list = [];
$suppliers_list = [];

$sql_categories = "SELECT id, name FROM categories ORDER BY name ASC";
$res_categories = mysqli_query($conn, $sql_categories);
if ($res_categories) {
    while ($row = mysqli_fetch_assoc($res_categories)) $categories_list[] = $row;
    mysqli_free_result($res_categories);
}

$sql_suppliers = "SELECT id, name FROM suppliers ORDER BY name ASC";
$res_suppliers = mysqli_query($conn, $sql_suppliers);
if ($res_suppliers) {
    while ($row = mysqli_fetch_assoc($res_suppliers)) $suppliers_list[] = $row;
    mysqli_free_result($res_suppliers);
}


// Handle POST requests for add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Populate $product_data from POST
    foreach ($product_data as $key => $default_value) {
        if (isset($_POST[$key])) {
            $product_data[$key] = trim($_POST[$key]);
        }
    }
    $posted_action = $_POST['form_action']; // 'add' or 'edit'
    $posted_product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

    // --- Validation ---
    if (empty($product_data['name'])) $form_errors['name'] = "Product name is required.";
    if (empty($product_data['sku'])) $form_errors['sku'] = "SKU is required.";
    else { // Check SKU uniqueness
        $sql_check_sku = "SELECT id FROM products WHERE sku = '" . escape_string($conn, $product_data['sku']) . "'";
        if ($posted_action === 'edit') $sql_check_sku .= " AND id != " . $posted_product_id;
        $res_sku = mysqli_query($conn, $sql_check_sku);
        if ($res_sku && mysqli_num_rows($res_sku) > 0) $form_errors['sku'] = "This SKU already exists.";
        if($res_sku) mysqli_free_result($res_sku);
    }
    if (!empty($product_data['barcode'])) { // Check Barcode uniqueness if provided
        $sql_check_barcode = "SELECT id FROM products WHERE barcode = '" . escape_string($conn, $product_data['barcode']) . "'";
        if ($posted_action === 'edit') $sql_check_barcode .= " AND id != " . $posted_product_id;
        $res_barcode = mysqli_query($conn, $sql_check_barcode);
        if ($res_barcode && mysqli_num_rows($res_barcode) > 0) $form_errors['barcode'] = "This Barcode already exists.";
        if($res_barcode) mysqli_free_result($res_barcode);
    }

    if (!is_numeric($product_data['purchase_price']) || $product_data['purchase_price'] < 0) $form_errors['purchase_price'] = "Purchase price must be a valid non-negative number.";
    if (!is_numeric($product_data['selling_price']) || $product_data['selling_price'] < 0) $form_errors['selling_price'] = "Selling price must be a valid non-negative number.";
    if (!ctype_digit($product_data['current_stock']) || (int)$product_data['current_stock'] < 0) $form_errors['current_stock'] = "Current stock must be a valid non-negative integer.";
    if (!ctype_digit($product_data['reorder_level']) || (int)$product_data['reorder_level'] < 0) $form_errors['reorder_level'] = "Reorder level must be a valid non-negative integer.";
    if (empty($product_data['unit'])) $form_errors['unit'] = "Unit of measure is required.";

    // Ensure category_id and supplier_id are null if empty, or integer
    $product_data['category_id'] = !empty($product_data['category_id']) ? (int)$product_data['category_id'] : null;
    $product_data['supplier_id'] = !empty($product_data['supplier_id']) ? (int)$product_data['supplier_id'] : null;
    // --- End Validation ---

    if (empty($form_errors)) {
        // Prepare fields for SQL
        $fields = [];
        $values = [];
        $update_pairs = [];

        foreach ($product_data as $key => $value) {
            $escaped_value = "'" . escape_string($conn, $value) . "'";
            if ($value === null || ($key === 'category_id' && $value === 0) || ($key === 'supplier_id' && $value === 0) ) { // Handle NULL for foreign keys
                $escaped_value = "NULL";
            }
            $fields[] = "`" . $key . "`";
            $values[] = $escaped_value;
            if ($key !== 'sku' || $posted_action === 'add') { // Don't update SKU typically, or handle as per policy
                 $update_pairs[] = "`" . $key . "` = " . $escaped_value;
            }
        }

        if ($posted_action === 'add') {
            $sql = "INSERT INTO products (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['message'] = "Product added successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error adding product: " . mysqli_error($conn);
                $_SESSION['message_type'] = "danger";
            }
        } elseif ($posted_action === 'edit' && $posted_product_id > 0) {
            // For edit, we might not want to update current_stock directly from this form,
            // as stock is usually managed by sales/purchases/adjustments.
            // For now, allowing it as per fields.
            // Remove sku from update_pairs if it's not editable or handled differently
            $update_pairs_filtered = array_filter($update_pairs, function($pair){
                return strpos($pair, '`sku` =') === false; // Example: Don't allow SKU update
            });


            $sql = "UPDATE products SET " . implode(', ', $update_pairs) . ", updated_at = CURRENT_TIMESTAMP WHERE id = " . $posted_product_id;
            if (mysqli_query($conn, $sql)) {
                $_SESSION['message'] = "Product updated successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error updating product: " . mysqli_error($conn);
                $_SESSION['message_type'] = "danger";
            }
        }
        header("Location: " . $base_module_self_url); // Redirect to product list
        exit;
    } else {
        // Errors found, re-render form. $product_data is already populated from POST.
        $page_action = ($posted_action === 'edit') ? 'edit' : 'add';
        $product_id = $posted_product_id; // Keep id for edit form
    }
}


// Handle GET requests for actions
if ($page_action === 'edit' && $product_id > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sql_get_prod = "SELECT * FROM products WHERE id = " . $product_id;
    $result_get_prod = mysqli_query($conn, $sql_get_prod);
    if ($result_get_prod && mysqli_num_rows($result_get_prod) > 0) {
        $fetched_data = mysqli_fetch_assoc($result_get_prod);
        foreach ($product_data as $key => $default_value) {
            if (isset($fetched_data[$key])) {
                $product_data[$key] = $fetched_data[$key];
            }
        }
        mysqli_free_result($result_get_prod);
    } else {
        $_SESSION['message'] = "Product not found.";
        $_SESSION['message_type'] = "warning";
        header("Location: " . $base_module_self_url);
        exit;
    }
} elseif ($page_action === 'delete' && $product_id > 0) {
    // Check if product is in use (e.g., in sale_items, purchase_items, stock_adjustments)
    // For simplicity, we'll just delete. A robust system would check or use soft delete.
    // A simple check: if current_stock is not zero, or if it appears in sale_items.
    $sql_check_sales = "SELECT COUNT(*) as count FROM sale_items WHERE product_id = " . $product_id;
    $res_check_sales = mysqli_query($conn, $sql_check_sales);
    $sales_count = 0;
    if ($res_check_sales) {
        $sales_count = mysqli_fetch_assoc($res_check_sales)['count'];
        mysqli_free_result($res_check_sales);
    }

    if ($sales_count > 0) {
         $_SESSION['message'] = "Cannot delete product. It has associated sales records. Consider deactivating instead (feature not yet implemented).";
         $_SESSION['message_type'] = "warning";
    } else {
        $sql_delete = "DELETE FROM products WHERE id = " . $product_id;
        if (mysqli_query($conn, $sql_delete)) {
            $_SESSION['message'] = "Product deleted successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting product: " . mysqli_error($conn);
            $_SESSION['message_type'] = "danger";
        }
    }
    header("Location: " . $base_module_self_url);
    exit;
}


// Display logic: list or form
if ($page_action === 'list') {
    $products = [];
    // Joining with categories and suppliers for display
    $sql_list = "SELECT p.*, c.name as category_name, s.name as supplier_name
                 FROM products p
                 LEFT JOIN categories c ON p.category_id = c.id
                 LEFT JOIN suppliers s ON p.supplier_id = s.id
                 ORDER BY p.name ASC";
    $result_list = mysqli_query($conn, $sql_list);
    if ($result_list) {
        while ($row = mysqli_fetch_assoc($result_list)) {
            $products[] = $row;
        }
        mysqli_free_result($result_list);
    } else {
        $_SESSION['message'] = "Error fetching products: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    require_once BASE_PATH . '/templates/inventory/product_list.php';
} elseif ($page_action === 'add' || $page_action === 'edit') {
    // $product_data is already prepared. $categories_list and $suppliers_list are also available.
    require_once BASE_PATH . '/templates/inventory/product_form.php';
} elseif ($page_action === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=products_export_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');

    // Add headers to CSV
    fputcsv($output, [
        'sku', 'barcode', 'name', 'description',
        'purchase_price', 'selling_price', 'current_stock', 'reorder_level',
        'unit', 'category_name', 'supplier_name', 'image_url'
        // Not exporting IDs, created_at, updated_at directly for re-import simplicity
        // Category/Supplier names are for reference; re-import would need to map them to IDs or handle by name
    ]);

    $sql_export = "SELECT p.sku, p.barcode, p.name, p.description,
                          p.purchase_price, p.selling_price, p.current_stock, p.reorder_level,
                          p.unit, c.name as category_name, s.name as supplier_name, p.image_url
                   FROM products p
                   LEFT JOIN categories c ON p.category_id = c.id
                   LEFT JOIN suppliers s ON p.supplier_id = s.id
                   ORDER BY p.name ASC";

    $result_export = mysqli_query($conn, $sql_export);
    if ($result_export) {
        while ($row = mysqli_fetch_assoc($result_export)) {
            fputcsv($output, $row);
        }
        mysqli_free_result($result_export);
    } else {
        // Log error, though headers already sent might make user feedback tricky here
        error_log("Error exporting products to CSV: " . mysqli_error($conn));
        fputcsv($output, ['Error exporting data.']); // Basic error in CSV
    }
    fclose($output);
    exit;

} elseif ($page_action === 'import_csv_form') {
    $report_title = "Import Products from CSV"; // Re-use for page title concept
    require_once BASE_PATH . '/templates/inventory/product_import_csv_form.php';

} elseif ($page_action === 'process_import_csv' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['message'] = "Error uploading file or no file selected. Error code: " . ($_FILES['csv_file']['error'] ?? 'Unknown');
        $_SESSION['message_type'] = "danger";
        header("Location: " . $base_module_self_url . "&sub_action=import_csv_form");
        exit;
    }

    $file_tmp_path = $_FILES['csv_file']['tmp_name'];
    $file_mime_type = mime_content_type($file_tmp_path);

    if ($file_mime_type !== 'text/csv' && $file_mime_type !== 'application/csv' && $file_mime_type !== 'text/plain') {
        $_SESSION['message'] = "Invalid file type. Please upload a CSV file. Detected type: " . htmlspecialchars($file_mime_type);
        $_SESSION['message_type'] = "danger";
        header("Location: " . $base_module_self_url . "&sub_action=import_csv_form");
        exit;
    }

    $csv_file = fopen($file_tmp_path, 'r');
    if (!$csv_file) {
        $_SESSION['message'] = "Failed to open uploaded file.";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $base_module_self_url . "&sub_action=import_csv_form");
        exit;
    }

    $header = fgetcsv($csv_file); // Read header row
    // Expected headers (must match export or be documented)
    $expected_headers = ['sku', 'barcode', 'name', 'description', 'purchase_price', 'selling_price', 'current_stock', 'reorder_level', 'unit', 'category_name', 'supplier_name', 'image_url'];
    if (!$header || array_map('strtolower', $header) !== array_map('strtolower', $expected_headers)) {
         $_SESSION['message'] = "CSV file header does not match expected format. Expected: " . implode(', ', $expected_headers);
         $_SESSION['message_type'] = "danger";
         fclose($csv_file);
         header("Location: " . $base_module_self_url . "&sub_action=import_csv_form");
         exit;
    }

    $imported_count = 0;
    $skipped_count = 0;
    $error_count = 0;
    $error_messages = [];
    $row_number = 1; // After header

    mysqli_begin_transaction($conn);

    while (($row_data = fgetcsv($csv_file)) !== FALSE) {
        $row_number++;
        if (count($row_data) !== count($expected_headers)) {
            $skipped_count++;
            $error_messages[] = "Row {$row_number}: Incorrect number of columns.";
            continue;
        }
        $product_csv_data = array_combine($expected_headers, $row_data);

        // Data Transformation & Validation
        $sku = trim($product_csv_data['sku']);
        $name = trim($product_csv_data['name']);

        if (empty($sku) || empty($name)) {
            $skipped_count++;
            $error_messages[] = "Row {$row_number}: SKU and Name are required. Skipping.";
            continue;
        }

        // Check for existing SKU
        $sql_check_sku_import = "SELECT id FROM products WHERE sku = '" . escape_string($conn, $sku) . "'";
        $res_sku_import = mysqli_query($conn, $sql_check_sku_import);
        if ($res_sku_import && mysqli_num_rows($res_sku_import) > 0) {
            $skipped_count++;
            $error_messages[] = "Row {$row_number}: SKU '{$sku}' already exists. Skipping.";
            mysqli_free_result($res_sku_import);
            continue;
        }
        if($res_sku_import) mysqli_free_result($res_sku_import);


        // Category and Supplier: Find ID by name (simple lookup)
        $category_id_import = null;
        if (!empty($product_csv_data['category_name'])) {
            $cat_name_esc = escape_string($conn, trim($product_csv_data['category_name']));
            $sql_find_cat = "SELECT id FROM categories WHERE name = '{$cat_name_esc}' LIMIT 1";
            $res_find_cat = mysqli_query($conn, $sql_find_cat);
            if ($res_find_cat && mysqli_num_rows($res_find_cat) > 0) {
                $category_id_import = (int)mysqli_fetch_assoc($res_find_cat)['id'];
            } else {
                 $error_messages[] = "Row {$row_number}: Category '{$product_csv_data['category_name']}' not found. Product will be added without category.";
            }
            if($res_find_cat) mysqli_free_result($res_find_cat);
        }

        $supplier_id_import = null;
        if (!empty($product_csv_data['supplier_name'])) {
            $sup_name_esc = escape_string($conn, trim($product_csv_data['supplier_name']));
            $sql_find_sup = "SELECT id FROM suppliers WHERE name = '{$sup_name_esc}' LIMIT 1";
            $res_find_sup = mysqli_query($conn, $sql_find_sup);
            if ($res_find_sup && mysqli_num_rows($res_find_sup) > 0) {
                $supplier_id_import = (int)mysqli_fetch_assoc($res_find_sup)['id'];
            } else {
                $error_messages[] = "Row {$row_number}: Supplier '{$product_csv_data['supplier_name']}' not found. Product will be added without supplier.";
            }
            if($res_find_sup) mysqli_free_result($res_find_sup);
        }

        $barcode_import = !empty($product_csv_data['barcode']) ? "'" . escape_string($conn, trim($product_csv_data['barcode'])) . "'" : "NULL";
        $description_import = "'" . escape_string($conn, trim($product_csv_data['description'])) . "'";
        $purchase_price_import = is_numeric($product_csv_data['purchase_price']) ? floatval($product_csv_data['purchase_price']) : 0.00;
        $selling_price_import = is_numeric($product_csv_data['selling_price']) ? floatval($product_csv_data['selling_price']) : 0.00;
        $current_stock_import = is_numeric($product_csv_data['current_stock']) ? intval($product_csv_data['current_stock']) : 0;
        $reorder_level_import = is_numeric($product_csv_data['reorder_level']) ? intval($product_csv_data['reorder_level']) : 0;
        $unit_import = "'" . escape_string($conn, trim($product_csv_data['unit'] ?: 'pcs')) . "'";
        $image_url_import = !empty($product_csv_data['image_url']) ? "'" . escape_string($conn, trim($product_csv_data['image_url'])) . "'" : "NULL";
        $cat_id_sql_import = $category_id_import ? $category_id_import : "NULL";
        $sup_id_sql_import = $supplier_id_import ? $supplier_id_import : "NULL";


        $sql_insert_product = "INSERT INTO products (sku, barcode, name, description, purchase_price, selling_price, current_stock, reorder_level, unit, category_id, supplier_id, image_url, created_at, updated_at) VALUES (
                                '" . escape_string($conn, $sku) . "',
                                {$barcode_import},
                                '" . escape_string($conn, $name) . "',
                                {$description_import},
                                {$purchase_price_import},
                                {$selling_price_import},
                                {$current_stock_import},
                                {$reorder_level_import},
                                {$unit_import},
                                {$cat_id_sql_import},
                                {$sup_id_sql_import},
                                {$image_url_import},
                                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                              )";

        if (mysqli_query($conn, $sql_insert_product)) {
            $imported_count++;
        } else {
            $error_count++;
            $error_messages[] = "Row {$row_number}: Error inserting product '{$sku}': " . mysqli_error($conn);
            // If one fails, decide whether to rollback all or continue
            // For simple import, might continue and report errors.
        }
    }
    fclose($csv_file);

    if ($error_count > 0) {
        mysqli_rollback($conn); // Rollback if any error occurred during insertions
        $_SESSION['message'] = "Product import failed. {$error_count} products could not be inserted. {$skipped_count} products were skipped. No products were imported due to errors. Details: <br>" . implode("<br>", array_slice($error_messages, 0, 5)); // Show first 5 errors
        $_SESSION['message_type'] = "danger";
    } else {
        mysqli_commit($conn);
        $_SESSION['message'] = "Product import completed. {$imported_count} products imported successfully. {$skipped_count} products skipped.";
        if (!empty($error_messages)) {
            $_SESSION['message'] .= "<br>Additional info/warnings: <br>" . implode("<br>", array_slice($error_messages, 0, 10));
        }
        $_SESSION['message_type'] = "success";
    }

    header("Location: " . $base_module_self_url . "&sub_action=list"); // Or back to import form to show messages
    exit;
}


// mysqli_close($conn); // Closed by index.php
?>
