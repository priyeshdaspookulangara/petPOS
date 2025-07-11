<?php
// This template is included by modules/inventory/stock_levels.php
// It has access to $products_stock array and $base_module_url

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}

// Currency symbol from settings (could be passed from controller or fetched here if needed globally)
$currency_symbol = '$'; // Placeholder, ideally from settings
$sql_currency = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency = mysqli_query($conn, $sql_currency); // $conn should be available from db.php
if ($res_currency && mysqli_num_rows($res_currency) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency)['setting_value']);
    mysqli_free_result($res_currency);
}


// Helper function to format price (can be moved to a global functions file later)
if (!function_exists('format_price_stock')) { // Prevent re-declaration if used elsewhere
    function format_price_stock($price, $symbol) {
        return $symbol . number_format($price, 2);
    }
}

?>

<div class="container-fluid mt-4"> <!-- Use container-fluid for wider tables -->
    <div class="row mb-3">
        <div class="col">
            <h2>Current Stock Levels</h2>
        </div>
        <div class="col text-right">
            <a href="<?php echo dirname($base_module_url,2); ?>/index.php?module=inventory&action=stock_adjustments&sub_action=add" class="btn btn-warning">
                <i class="fas fa-wrench"></i> Make Stock Adjustment
            </a>
             <a href="<?php echo dirname($base_module_url,2); ?>/index.php?module=inventory&action=reorder_alerts" class="btn btn-info">
                <i class="fas fa-bell"></i> View Low Stock Alerts
            </a>
        </div>
    </div>

    <?php
    // Session messages are displayed by header.php
    ?>

    <div class="table-responsive">
        <?php if (!empty($products_stock)): ?>
            <table class="table table-striped table-bordered table-hover dataTable"> <!-- Added dataTable class for potential JS enhancements -->
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>SKU</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th class="text-right">Current Stock</th>
                        <th class="text-right">Reorder Level</th>
                        <th>Unit</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products_stock as $product): ?>
                        <?php
                            $is_low_stock = ($product['current_stock'] <= $product['reorder_level'] && $product['reorder_level'] > 0);
                            $stock_status_class = '';
                            $stock_status_text = 'OK';
                            if ($product['current_stock'] == 0) {
                                $stock_status_class = 'table-danger';
                                $stock_status_text = 'Out of Stock';
                            } elseif ($is_low_stock) {
                                $stock_status_class = 'table-warning';
                                $stock_status_text = 'Low Stock';
                            }
                        ?>
                        <tr class="<?php echo $stock_status_class; ?>"
                            title="<?php echo $is_low_stock ? 'Stock is at or below reorder level!' : ($product['current_stock'] == 0 ? 'Product is out of stock!' : ''); ?>">
                            <td><?php echo htmlspecialchars($product['id']); ?></td>
                            <td><?php echo htmlspecialchars($product['sku']); ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category_name'] ? $product['category_name'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($product['supplier_name'] ? $product['supplier_name'] : 'N/A'); ?></td>
                            <td class="text-right"><strong><?php echo htmlspecialchars($product['current_stock']); ?></strong></td>
                            <td class="text-right"><?php echo htmlspecialchars($product['reorder_level']); ?></td>
                            <td><?php echo htmlspecialchars($product['unit']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo ($product['current_stock'] == 0 ? 'danger' : ($is_low_stock ? 'warning' : 'success')); ?>">
                                    <?php echo $stock_status_text; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="<?php echo dirname($base_module_url,2); ?>/index.php?module=inventory&action=stock_adjustments&sub_action=add&product_id=<?php echo $product['id']; ?>"
                                   class="btn btn-sm btn-outline-primary" title="Adjust Stock for this Product">
                                    <i class="fas fa-edit"></i> Adjust
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                No products found in the inventory. <a href="<?php echo dirname($base_module_url,2); ?>/index.php?module=inventory&action=products&sub_action=add">Add products first.</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Simple script for datatables if you want to include it.
// Make sure to include jQuery and DataTables JS/CSS files in main header/footer if using this.
/*
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>
<script>
    $(document).ready(function() {
        $('.dataTable').DataTable({
            "pageLength": 25, // Default number of rows to display
            "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ] // Options for number of rows
        });
    });
</script>
*/
?>
