<?php
// This template is included by modules/inventory/products.php for 'import_csv_form'
// Access to $base_module_self_url, $report_title (reused for page title)

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col">
            <h2><?php echo htmlspecialchars($report_title ?? "Import Products from CSV"); ?></h2>
        </div>
        <div class="col text-right">
            <a href="<?php echo htmlspecialchars($base_module_self_url . '&sub_action=list'); ?>" class="btn btn-secondary">
                <i class="fas fa-list"></i> Back to Product List
            </a>
             <a href="<?php echo htmlspecialchars($base_module_self_url . '&sub_action=export_csv'); ?>" class="btn btn-info">
                <i class="fas fa-file-export"></i> Download CSV Template/Export Products
            </a>
        </div>
    </div>

    <?php
    // Session messages are displayed by header.php
    // Example: if (isset($_SESSION['message'])) display it here too or rely on header.
    ?>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Upload CSV File</h5>
            <p class="card-text">
                Please ensure your CSV file follows the correct format. The expected columns are: <br>
                <code>sku, barcode, name, description, purchase_price, selling_price, current_stock, reorder_level, unit, category_name, supplier_name, image_url</code>
            </p>
            <p class="card-text">
                - <strong>sku</strong> and <strong>name</strong> are required.<br>
                - <strong>category_name</strong> and <strong>supplier_name</strong> should match existing names in the system. If not found, the product will be imported without them, and a warning may be shown.<br>
                - Products with existing SKUs will be skipped.
            </p>
            <hr>
            <form action="<?php echo htmlspecialchars($base_module_self_url . '&sub_action=process_import_csv'); ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); // Assuming generate_csrf_token() is available via functions.php ?>">

                <div class="form-group">
                    <label for="csv_file">Choose CSV File <span class="text-danger">*</span></label>
                    <input type="file" class="form-control-file <?php echo isset($form_errors['csv_file']) ? 'is-invalid' : ''; ?>"
                           id="csv_file" name="csv_file" accept=".csv" required>
                    <?php if (isset($form_errors['csv_file'])): ?>
                        <div class="invalid-feedback"><?php echo htmlspecialchars($form_errors['csv_file']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload and Import Products
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div class="mt-3">
        <p><strong>Tip:</strong> It's recommended to <a href="<?php echo htmlspecialchars($base_module_self_url . '&sub_action=export_csv'); ?>">download the current product list as a CSV</a> first to use as a template for formatting your import file.</p>
    </div>
</div>
