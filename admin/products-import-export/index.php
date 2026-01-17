<?php
$page_title = "Product CSV Import / Export";
$app_base_path = '/';

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

// --- Handle CSV Export ---
if (isset($_GET['action']) && $_GET['action'] == 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="products_export_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Add headers to CSV
    fputcsv($output, [
        'sku', 'name', 'description', 'purchase_price', 'selling_price',
        'current_stock', 'reorder_level', 'unit', 'category_name', 'supplier_name', 'image_url'
    ]);

    // Fetch products with category and supplier names
    $sql_export = "SELECT
                    p.sku, p.name, p.description, p.purchase_price, p.selling_price,
                    p.current_stock, p.reorder_level, p.unit,
                    c.name as category_name,
                    s.name as supplier_name,
                    p.image_url
                 FROM products p
                 LEFT JOIN categories c ON p.category_id = c.id
                 LEFT JOIN suppliers s ON p.supplier_id = s.id
                 ORDER BY p.name ASC";

    $result_export = $mysqli->query($sql_export);
    if ($result_export) {
        while ($row = $result_export->fetch_assoc()) {
            fputcsv($output, $row);
        }
        $result_export->free();
    }
    fclose($output);
    exit;
}

// --- Handle CSV Import ---
$import_summary = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv_submit'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['csv_file']['tmp_name'];
        $file_name = $_FILES['csv_file']['name'];
        $file_size = $_FILES['csv_file']['size'];
        $file_type = $_FILES['csv_file']['type'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_ext !== 'csv') {
            $_SESSION['flash_message'] = "Invalid file type. Please upload a CSV file.";
            $_SESSION['flash_message_type'] = "danger";
        } elseif ($file_size > 5 * 1024 * 1024) { // 5MB limit
            $_SESSION['flash_message'] = "File size exceeds 5MB limit.";
            $_SESSION['flash_message_type'] = "danger";
        } else {
            // Pre-fetch categories and suppliers to avoid querying in a loop
            $categories_map = [];
            $cat_map_res = $mysqli->query("SELECT id, name FROM categories");
            if($cat_map_res) while($r = $cat_map_res->fetch_assoc()) $categories_map[strtolower($r['name'])] = $r['id'];
            if($cat_map_res) $cat_map_res->free();

            $suppliers_map = [];
            $sup_map_res = $mysqli->query("SELECT id, name FROM suppliers");
            if($sup_map_res) while($r = $sup_map_res->fetch_assoc()) $suppliers_map[strtolower($r['name'])] = $r['id'];
            if($sup_map_res) $sup_map_res->free();

            // Process the CSV file
            $csv_file = fopen($file_tmp_path, 'r');
            $header = fgetcsv($csv_file); // Read header row
            // Basic header validation (optional but recommended)
            $expected_headers = ['sku', 'name', 'selling_price']; // At least these
            if (count(array_intersect($expected_headers, $header)) != count($expected_headers)) {
                 $_SESSION['flash_message'] = "CSV file is missing required headers: sku, name, selling_price.";
                 $_SESSION['flash_message_type'] = "danger";
            } else {
                $processed = 0; $added = 0; $updated = 0; $failed = 0; $failed_rows = [];
                $header_map = array_flip($header); // For easy column access by name

                $mysqli->begin_transaction();
                try {
                    while (($row = fgetcsv($csv_file)) !== FALSE) {
                        $processed++;
                        $sku = isset($row[$header_map['sku']]) ? sanitize_input($mysqli, $row[$header_map['sku']]) : '';
                        $name = isset($row[$header_map['name']]) ? sanitize_input($mysqli, $row[$header_map['name']]) : '';
                        $selling_price = isset($row[$header_map['selling_price']]) ? (float)$row[$header_map['selling_price']] : 0.0;

                        // Validation
                        if (empty($sku) || empty($name) || $selling_price <= 0) {
                            $failed++;
                            $failed_rows[] = "Row $processed: SKU, Name, and a positive Selling Price are required.";
                            continue;
                        }

                        // Get other fields, with defaults
                        $description = isset($row[$header_map['description']]) ? sanitize_input($mysqli, $row[$header_map['description']]) : '';
                        $purchase_price = isset($row[$header_map['purchase_price']]) ? (float)$row[$header_map['purchase_price']] : 0.0;
                        $current_stock = isset($row[$header_map['current_stock']]) ? (int)$row[$header_map['current_stock']] : 0;
                        $reorder_level = isset($row[$header_map['reorder_level']]) ? (int)$row[$header_map['reorder_level']] : 0;
                        $unit = isset($row[$header_map['unit']]) ? sanitize_input($mysqli, $row[$header_map['unit']]) : 'pcs';
                        $image_url = isset($row[$header_map['image_url']]) ? sanitize_input($mysqli, $row[$header_map['image_url']]) : '';

                        $category_name = isset($row[$header_map['category_name']]) ? strtolower(trim($row[$header_map['category_name']])) : '';
                        $supplier_name = isset($row[$header_map['supplier_name']]) ? strtolower(trim($row[$header_map['supplier_name']])) : '';

                        $category_id = isset($categories_map[$category_name]) ? $categories_map[$category_name] : 'NULL';
                        $supplier_id = isset($suppliers_map[$supplier_name]) ? $suppliers_map[$supplier_name] : 'NULL';

                        // INSERT ON DUPLICATE KEY UPDATE using SKU
                        $sql_import = "INSERT INTO products (sku, name, description, purchase_price, selling_price, current_stock, reorder_level, unit, category_id, supplier_id, image_url)
                                       VALUES ('$sku', '$name', '$description', $purchase_price, $selling_price, $current_stock, $reorder_level, '$unit', $category_id, $supplier_id, '$image_url')
                                       ON DUPLICATE KEY UPDATE
                                       name = VALUES(name), description = VALUES(description), purchase_price = VALUES(purchase_price),
                                       selling_price = VALUES(selling_price), current_stock = VALUES(current_stock), reorder_level = VALUES(reorder_level),
                                       unit = VALUES(unit), category_id = VALUES(category_id), supplier_id = VALUES(supplier_id), image_url = VALUES(image_url)";

                        $result_import = $mysqli->query($sql_import);
                        if (!$result_import) {
                            throw new Exception("Database error on row $processed: " . $mysqli->error);
                        }

                        // Check if it was an insert or update
                        if ($mysqli->affected_rows === 1) {
                            $added++;
                        } elseif ($mysqli->affected_rows === 2) { // 2 rows affected means an update occurred
                            $updated++;
                        }
                    }
                    $mysqli->commit();
                    $import_summary = [
                        'processed' => $processed, 'added' => $added, 'updated' => $updated,
                        'failed' => $failed, 'failed_rows' => $failed_rows
                    ];
                    $_SESSION['flash_message'] = "CSV import completed successfully.";
                    $_SESSION['flash_message_type'] = "success";

                } catch (Exception $e) {
                    $mysqli->rollback();
                    $_SESSION['flash_message'] = "Import failed and was rolled back. Error: " . $e->getMessage();
                    $_SESSION['flash_message_type'] = "danger";
                }
                fclose($csv_file);
            }
        }
    } else {
        $_SESSION['flash_message'] = "No file uploaded or an error occurred during upload.";
        $_SESSION['flash_message_type'] = "danger";
    }
}


include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="row">
        <!-- Export Card -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Export Products</h6></div>
                <div class="card-body">
                    <p>Export all products currently in the system to a CSV file. This file can be used for backups or as a template for bulk updates.</p>
                    <a href="?action=export" class="btn btn-info"><i class="fas fa-file-csv"></i> Export All Products to CSV</a>
                </div>
            </div>
        </div>
        <!-- Import Card -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Import Products</h6></div>
                <div class="card-body">
                    <p>Import new products or update existing ones by uploading a CSV file. <strong>The `sku` column is used as the unique identifier.</strong></p>
                    <p>Required columns: <strong>sku, name, selling_price</strong>. Other columns are optional.</p>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="csv_file">Select CSV File (Max 5MB)</label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control-file" accept=".csv" required>
                        </div>
                        <button type="submit" name="import_csv_submit" class="btn btn-warning"><i class="fas fa-upload"></i> Import and Update Products</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($import_summary): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-success">Import Summary</h6></div>
        <div class="card-body">
            <ul>
                <li>Total rows processed: <strong><?php echo $import_summary['processed']; ?></strong></li>
                <li>New products added: <strong><?php echo $import_summary['added']; ?></strong></li>
                <li>Existing products updated: <strong><?php echo $import_summary['updated']; ?></strong></li>
                <li class="<?php echo ($import_summary['failed'] > 0 ? 'text-danger' : ''); ?>">Rows failed/skipped: <strong><?php echo $import_summary['failed']; ?></strong></li>
            </ul>
            <?php if (!empty($import_summary['failed_rows'])): ?>
                <h6>Failed Row Details:</h6>
                <pre style="max-height: 200px; overflow-y: auto; background-color: #f8f9fa; padding: 10px; border-radius: 5px;"><?php echo htmlspecialchars(implode("\n", $import_summary['failed_rows'])); ?></pre>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php
include __DIR__ . '/../../templates/footer.php';
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
