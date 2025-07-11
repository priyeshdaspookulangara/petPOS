<?php
// This template is included by modules/inventory/reorder_alerts.php
// It has access to $low_stock_products array and $base_module_url

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
?>

<div class="container-fluid mt-4"> <!-- Use container-fluid for wider tables -->
    <div class="row mb-3">
        <div class="col">
            <h2>Low Stock / Reorder Alerts</h2>
            <p>Products listed below are at or below their specified reorder level.</p>
        </div>
         <div class="col text-right">
            <a href="<?php echo dirname($base_module_url,2); ?>/index.php?module=inventory&action=stock_levels" class="btn btn-info">
                <i class="fas fa-boxes"></i> View All Stock Levels
            </a>
        </div>
    </div>

    <?php
    // Session messages are displayed by header.php
    ?>

    <div class="table-responsive">
        <?php if (!empty($low_stock_products)): ?>
            <table class="table table-striped table-bordered table-hover dataTable">
                <thead class="thead-dark">
                    <tr>
                        <th>SKU</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th class="text-right">Current Stock</th>
                        <th class="text-right">Reorder Level</th>
                        <th class="text-right">Needed Qty</th>
                        <th>Unit</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($low_stock_products as $product): ?>
                        <tr class="table-warning">
                            <td><?php echo htmlspecialchars($product['sku']); ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category_name'] ? $product['category_name'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($product['supplier_name'] ? $product['supplier_name'] : 'N/A'); ?></td>
                            <td class="text-right"><strong><?php echo htmlspecialchars($product['current_stock']); ?></strong></td>
                            <td class="text-right"><?php echo htmlspecialchars($product['reorder_level']); ?></td>
                            <td class="text-right text-danger font-weight-bold">
                                <?php echo htmlspecialchars($product['needed_quantity'] > 0 ? $product['needed_quantity'] : '0'); ?>
                            </td>
                            <td><?php echo htmlspecialchars($product['unit']); ?></td>
                            <td class="text-center">
                                <a href="<?php echo dirname($base_module_url,2); ?>/index.php?module=purchases&action=create_po&product_id=<?php echo $product['id']; ?>&qty=<?php echo $product['needed_quantity']; ?>"
                                   class="btn btn-sm btn-primary" title="Create Purchase Order for this Product">
                                    <i class="fas fa-shopping-cart"></i> Create PO
                                </a>
                                <a href="<?php echo dirname($base_module_url,2); ?>/index.php?module=inventory&action=products&sub_action=edit&id=<?php echo $product['id']; ?>"
                                   class="btn btn-sm btn-info" title="Edit Product (e.g., update reorder level)">
                                    <i class="fas fa-edit"></i> Edit Product
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i> All products are currently above their reorder levels. No alerts at this time.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// DataTables initialization script can be added here if desired
?>
