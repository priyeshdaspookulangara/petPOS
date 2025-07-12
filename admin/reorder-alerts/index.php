<?php
$page_title = "Low Stock / Reorder Alerts";
$app_base_path = '/'; // Adjust if your application is in a subdirectory. Should be global.

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

// Fetch products that are at or below their reorder level
$low_stock_products = [];
$sql = "SELECT
            p.id, p.sku, p.name, p.current_stock, p.reorder_level, p.unit,
            s.name as supplier_name, s.id as supplier_id, s.contact_person as supplier_contact, s.phone as supplier_phone,
            c.name as category_name
        FROM products p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.current_stock <= p.reorder_level AND p.reorder_level > 0
        ORDER BY (p.reorder_level - p.current_stock) DESC, p.name ASC"; // Order by how much below, then by name

$result = $mysqli->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $low_stock_products[] = $row;
    }
    $result->free();
} else {
    $_SESSION['flash_message'] = "Error fetching low stock products: " . htmlspecialchars($mysqli->error);
    $_SESSION['flash_message_type'] = "danger";
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
        <!-- Optional: Add a button like "Create Bulk PO" if such functionality is planned -->
    </div>

    <?php if (empty($low_stock_products) && empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> All products are currently above their reorder levels. Good job!
        </div>
    <?php elseif (!empty($low_stock_products)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Products Requiring Reorder (<?php echo count($low_stock_products); ?> items)</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="lowStockTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Reorder Level</th>
                                <th>Difference</th>
                                <th>Unit</th>
                                <th>Supplier</th>
                                <th>Supplier Contact</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_stock_products as $product): ?>
                                <tr class="table-warning"> <?php // Highlight these rows ?>
                                    <td><?php echo htmlspecialchars($product['sku'] ?: '-'); ?></td>
                                    <td>
                                        <a href="<?php echo site_url('admin/manage-product/?id=' . $product['id'], $app_base_path); ?>">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($product['current_stock']); ?></td>
                                    <td><?php echo htmlspecialchars($product['reorder_level']); ?></td>
                                    <td><?php echo ($product['reorder_level'] - $product['current_stock']); ?></td>
                                    <td><?php echo htmlspecialchars($product['unit']); ?></td>
                                    <td>
                                        <?php if ($product['supplier_id']): ?>
                                            <a href="<?php echo site_url('admin/manage-supplier/?id=' . $product['supplier_id'], $app_base_path); ?>">
                                                <?php echo htmlspecialchars($product['supplier_name']); ?>
                                            </a>
                                        <?php else: echo 'N/A'; endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($product['supplier_contact'] ?: '-'); ?>
                                        <?php if ($product['supplier_phone']) echo '<br><small><i class="fas fa-phone"></i> ' . htmlspecialchars($product['supplier_phone']) . '</small>'; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo site_url('admin/manage-product/?id=' . $product['id'], $app_base_path); ?>" class="btn btn-sm btn-outline-primary mb-1" title="View/Edit Product">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <?php if ($product['supplier_id']): ?>
                                        <!-- Placeholder for future "Add to PO" or "Create PO" functionality -->
                                        <a href="<?php echo site_url('admin/create-po/?supplier_id=' . $product['supplier_id'] . '&product_id=' . $product['id'] , $app_base_path); // Link to be defined in Purchase module ?>"
                                           class="btn btn-sm btn-outline-success mb-1" title="Create Purchase Order for this item">
                                            <i class="fas fa-plus-circle"></i> Create PO
                                        </a>
                                        <?php endif; ?>
                                         <a href="<?php echo site_url('admin/stock-adjustments', $app_base_path); ?>?product_id=<?php echo $product['id']; ?>"
                                           class="btn btn-sm btn-outline-info mb-1" title="Adjust Stock">
                                            <i class="fas fa-exchange-alt"></i> Adjust
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <p class="text-muted small">
                        This report shows products where the "Current Stock" is less than or equal to the "Reorder Level", and "Reorder Level" is greater than 0.
                    </p>
                </div>
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
<?php // Optional: DataTables for lowStockTable ?>
