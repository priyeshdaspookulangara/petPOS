<?php
// This template is included by modules/inventory/products.php when $page_action is 'list'
// It has access to $products array and $base_module_url (for links)

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}

function format_price($price) {
    // Later, use currency_symbol from settings
    return '$' . number_format($price, 2);
}
?>

<div class="container-fluid mt-4"> <!-- Use container-fluid for wider tables -->
    <div class="row mb-3">
        <div class="col">
            <h2>Manage Products</h2>
        </div>
        <div class="col text-right">
            <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=add'); ?>" class="btn btn-success mr-2">
                <i class="fas fa-plus"></i> Add New Product
            </a>
            <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=import_csv_form'); ?>" class="btn btn-info mr-2"><i class="fas fa-file-import"></i> Import CSV</a>
            <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=export_csv'); ?>" class="btn btn-secondary"><i class="fas fa-file-export"></i> Export CSV</a>
        </div>
    </div>

    <?php
    // Session messages are displayed by header.php
    ?>

    <div class="table-responsive">
        <?php if (!empty($products)): ?>
            <table class="table table-striped table-bordered table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>SKU</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th>Purchase Price</th>
                        <th>Selling Price</th>
                        <th>Stock</th>
                        <th>Reorder Lvl</th>
                        <th>Unit</th>
                        <!-- <th>Barcode</th> -->
                        <!-- <th>Image</th> -->
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr class="<?php echo ($product['current_stock'] <= $product['reorder_level'] && $product['reorder_level'] > 0) ? 'table-warning' : ''; ?>"
                            title="<?php echo ($product['current_stock'] <= $product['reorder_level'] && $product['reorder_level'] > 0) ? 'Stock is at or below reorder level!' : ''; ?>">
                            <td><?php echo htmlspecialchars($product['id']); ?></td>
                            <td><?php echo htmlspecialchars($product['sku']); ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category_name'] ? $product['category_name'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($product['supplier_name'] ? $product['supplier_name'] : 'N/A'); ?></td>
                            <td><?php echo format_price($product['purchase_price']); ?></td>
                            <td><?php echo format_price($product['selling_price']); ?></td>
                            <td><?php echo htmlspecialchars($product['current_stock']); ?></td>
                            <td><?php echo htmlspecialchars($product['reorder_level']); ?></td>
                            <td><?php echo htmlspecialchars($product['unit']); ?></td>
                            <!-- <td><?php //echo htmlspecialchars($product['barcode'] ? $product['barcode'] : '-'); ?></td> -->
                            <!-- <td><?php //if ($product['image_url']): ?> <img src="<?php //echo htmlspecialchars($product['image_url']); ?>" alt="<?php //echo htmlspecialchars($product['name']); ?>" style="width: 50px; height: auto;"> <?php //else: echo '-'; endif; ?></td> -->
                            <td>
                                <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=edit&id=' . $product['id']); ?>" class="btn btn-sm btn-info" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <!-- Add a check for related sales/purchases before allowing delete -->
                                <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=delete&id=' . $product['id']); ?>"
                                   class="btn btn-sm btn-danger" title="Delete"
                                   onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone and might affect historical sales/purchase data if not handled carefully.');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                No products found. <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=add'); ?>">Add the first product!</a>
            </div>
        <?php endif; ?>
    </div>
</div>
