<?php
$page_title = "Products";
$app_base_path = '/'; // Adjust if your application is in a subdirectory. Should be global.

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

// Fetch products with category and supplier names
$products = [];
$sql = "SELECT
            p.id, p.sku, p.name, p.selling_price, p.purchase_price, p.current_stock, p.reorder_level,
            c.name as category_name,
            s.name as supplier_name,
            p.image_url,
            p.created_at
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        ORDER BY p.name ASC";
// Add search/filter logic here if implementing
// Example:
// $search_term = isset($_GET['search']) ? sanitize_input($mysqli, $_GET['search']) : '';
// if (!empty($search_term)) {
//     $sql = str_replace("ORDER BY", "WHERE p.name LIKE '%$search_term%' OR p.sku LIKE '%$search_term%' ORDER BY", $sql);
// }

$result = $mysqli->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $result->free();
} else {
    $_SESSION['flash_message'] = "Error fetching products: " . htmlspecialchars($mysqli->error);
    $_SESSION['flash_message_type'] = "danger";
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
        <a href="<?php echo site_url('admin/manage-product', $app_base_path); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Product
        </a>
    </div>

    <!-- Optional: Search/Filter Form -->
    <!--
    <form method="GET" action="<?php echo site_url('admin/products', $app_base_path); ?>" class="mb-3">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search by Name or SKU..." value="<?php // echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <div class="input-group-append">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
            </div>
        </div>
    </form>
    -->

    <?php if (empty($products) && empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-info">No products found. Start by adding a new one!</div>
    <?php elseif (!empty($products)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Product List</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="productsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>SKU</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Supplier</th>
                                <th>Sell Price</th>
                                <th>Purch Price</th>
                                <th>Stock</th>
                                <th>Reorder Lvl</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr class="<?php echo ($product['current_stock'] > 0 && $product['reorder_level'] > 0 && $product['current_stock'] <= $product['reorder_level']) ? 'table-warning' : ''; ?>">
                                    <td>
                                        <?php if (!empty($product['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars(filter_var($product['image_url'], FILTER_VALIDATE_URL) ? $product['image_url'] : site_url($product['image_url'], $app_base_path)); ?>"
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <i class="fas fa-image fa-2x text-muted" style="width: 50px; height: 50px; line-height: 50px; text-align: center;"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['sku'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($product['supplier_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($product['selling_price'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($product['purchase_price'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars($product['current_stock']); ?></td>
                                    <td><?php echo htmlspecialchars($product['reorder_level']); ?></td>
                                    <td>
                                        <a href="<?php echo site_url('admin/manage-product/?id=' . $product['id'], $app_base_path); ?>" class="btn btn-sm btn-warning mb-1" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php
                                        // Check if product is in any sale_items or purchase_items before allowing deletion
                                        // This check can be intensive here. A flag on product or a more optimized query might be better.
                                        // For now, a simple approach (can be refined if performance issues arise)
                                        $can_delete_product = true;
                                        // $check_sales_sql = "SELECT COUNT(*) as count FROM sale_items WHERE product_id = {$product['id']}";
                                        // $check_purch_sql = "SELECT COUNT(*) as count FROM purchase_items WHERE product_id = {$product['id']}";
                                        // $sales_count_res = $mysqli->query($check_sales_sql);
                                        // $purch_count_res = $mysqli->query($check_purch_sql);
                                        // if (($sales_count_res && $sales_count_res->fetch_assoc()['count'] > 0) || ($purch_count_res && $purch_count_res->fetch_assoc()['count'] > 0)) {
                                        //    $can_delete_product = false;
                                        // }
                                        // if($sales_count_res) $sales_count_res->free();
                                        // if($purch_count_res) $purch_count_res->free();
                                        // The above check is commented out to avoid N+1 queries in the loop. Deletion check will be in manage-product.php
                                        ?>
                                        <a href="<?php echo site_url('admin/manage-product/?action=delete&id=' . $product['id'], $app_base_path); ?>"
                                           class="btn btn-sm btn-danger mb-1"
                                           title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
<?php // Optional: DataTables for productsTable ?>
