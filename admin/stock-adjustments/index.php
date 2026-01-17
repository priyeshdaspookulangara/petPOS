<?php
$page_title = "Stock Adjustments";
$app_base_path = '/'; // Adjust if your application is in a subdirectory. Should be global.

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

// Fetch products for the dropdown
$products_for_select = [];
$prod_sql = "SELECT id, name, sku, current_stock FROM products ORDER BY name ASC";
$prod_result = $mysqli->query($prod_sql);
if ($prod_result) {
    while ($row = $prod_result->fetch_assoc()) {
        $products_for_select[] = $row;
    }
    $prod_result->free();
}

// Handle POST request for new stock adjustment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_adjustment'])) {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $adjustment_type = isset($_POST['adjustment_type']) ? sanitize_input($mysqli, $_POST['adjustment_type']) : null;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $reason = isset($_POST['reason']) ? sanitize_input($mysqli, $_POST['reason']) : '';
    $user_id = get_current_user_id();

    // Validation
    if (empty($product_id) || empty($adjustment_type) || $quantity <= 0 || empty($reason)) {
        $_SESSION['flash_message'] = "All fields (Product, Type, Quantity > 0, Reason) are required.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif (!in_array($adjustment_type, ['In', 'Out'])) {
        $_SESSION['flash_message'] = "Invalid adjustment type.";
        $_SESSION['flash_message_type'] = "danger";
    } else {
        // Fetch current stock to validate 'Out' adjustments
        $current_stock_sql = "SELECT current_stock FROM products WHERE id = $product_id";
        $current_stock_res = $mysqli->query($current_stock_sql);
        $current_stock_val = 0;
        if ($current_stock_res && $current_stock_row = $current_stock_res->fetch_assoc()) {
            $current_stock_val = (int)$current_stock_row['current_stock'];
        }
        if($current_stock_res) $current_stock_res->free();

        if ($adjustment_type == 'Out' && $quantity > $current_stock_val) {
            $_SESSION['flash_message'] = "Cannot adjust 'Out' by $quantity. Current stock is only $current_stock_val.";
            $_SESSION['flash_message_type'] = "danger";
        } else {
            // Start transaction
            $mysqli->begin_transaction();
            $success = true;

            // 1. Log the adjustment
            $log_sql = "INSERT INTO stock_adjustments (product_id, adjustment_type, quantity, reason, user_id, adjustment_date)
                        VALUES ($product_id, '$adjustment_type', $quantity, '$reason', $user_id, NOW())";
            if (!$mysqli->query($log_sql)) {
                $success = false;
                $_SESSION['flash_message'] = "Error logging stock adjustment: " . htmlspecialchars($mysqli->error);
                $_SESSION['flash_message_type'] = "danger";
            }

            // 2. Update product stock
            if ($success) {
                $update_stock_sql = "";
                if ($adjustment_type == 'In') {
                    $update_stock_sql = "UPDATE products SET current_stock = current_stock + $quantity WHERE id = $product_id";
                } else { // Out
                    $update_stock_sql = "UPDATE products SET current_stock = current_stock - $quantity WHERE id = $product_id";
                }

                if (!$mysqli->query($update_stock_sql)) {
                    $success = false;
                    $_SESSION['flash_message'] = "Error updating product stock: " . htmlspecialchars($mysqli->error);
                    $_SESSION['flash_message_type'] = "danger";
                }
            }

            if ($success) {
                $mysqli->commit();
                $_SESSION['flash_message'] = "Stock adjusted successfully.";
                $_SESSION['flash_message_type'] = "success";
                // Clear form values or redirect to avoid resubmission
                header("Location: " . site_url('admin/stock-adjustments', $app_base_path));
                exit;
            } else {
                $mysqli->rollback();
                // Error message already set
            }
        }
    }
}


// Fetch recent stock adjustments for display
$adjustments = [];
$adj_log_sql = "SELECT sa.id, p.name as product_name, p.sku as product_sku, sa.adjustment_type,
                       sa.quantity, sa.reason, u.username as adjusted_by, sa.adjustment_date
                FROM stock_adjustments sa
                JOIN products p ON sa.product_id = p.id
                JOIN users u ON sa.user_id = u.id
                ORDER BY sa.adjustment_date DESC LIMIT 50"; // Limit for display
$adj_log_result = $mysqli->query($adj_log_sql);
if ($adj_log_result) {
    while ($row = $adj_log_result->fetch_assoc()) {
        $adjustments[] = $row;
    }
    $adj_log_result->free();
} else {
    // Error fetching log, could set a message but might conflict with form submission messages
}


include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="row">
        <div class="col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">New Stock Adjustment</h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo site_url('admin/stock-adjustments', $app_base_path); ?>" method="post">
                        <div class="form-group">
                            <label for="product_id">Product <span class="text-danger">*</span></label>
                            <select class="form-control" id="product_id" name="product_id" required>
                                <option value="">-- Select Product --</option>
                                <?php foreach ($products_for_select as $product): ?>
                                    <option value="<?php echo $product['id']; ?>" data-current-stock="<?php echo $product['current_stock']; ?>">
                                        <?php echo htmlspecialchars($product['name']) . ($product['sku'] ? ' (SKU: ' . htmlspecialchars($product['sku']) . ')' : '') . ' - Stock: ' . $product['current_stock']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Adjustment Type <span class="text-danger">*</span></label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="adjustment_type" id="type_in" value="In" required>
                                    <label class="form-check-label" for="type_in">Stock In (Add)</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="adjustment_type" id="type_out" value="Out" required>
                                    <label class="form-check-label" for="type_out">Stock Out (Deduct)</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="quantity">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="reason">Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                        </div>
                        <button type="submit" name="submit_adjustment" class="btn btn-primary">
                            <i class="fas fa-check-circle"></i> Submit Adjustment
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Stock Adjustments Log</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($adjustments)): ?>
                        <div class="alert alert-info">No stock adjustments recorded yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="adjustmentsLogTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Product (SKU)</th>
                                        <th>Type</th>
                                        <th>Qty</th>
                                        <th>Reason</th>
                                        <th>By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($adjustments as $adj): ?>
                                        <tr>
                                            <td><?php echo date("Y-m-d H:i", strtotime($adj['adjustment_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($adj['product_name']) . ($adj['product_sku'] ? ' (' . htmlspecialchars($adj['product_sku']) . ')' : ''); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo ($adj['adjustment_type'] == 'In' ? 'success' : 'danger'); ?>">
                                                    <?php echo htmlspecialchars($adj['adjustment_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($adj['quantity']); ?></td>
                                            <td><?php echo nl2br(htmlspecialchars($adj['reason'])); ?></td>
                                            <td><?php echo htmlspecialchars($adj['adjusted_by']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
// Optional: Client-side validation for quantity based on selected product stock if type is 'Out'
document.addEventListener('DOMContentLoaded', function() {
    const productSelect = document.getElementById('product_id');
    const quantityInput = document.getElementById('quantity');
    const typeOutRadio = document.getElementById('type_out');

    if (productSelect && quantityInput && typeOutRadio) {
        productSelect.addEventListener('change', function() {
            checkMaxQuantity();
        });
        typeOutRadio.addEventListener('change', function() {
            checkMaxQuantity();
        });
        quantityInput.addEventListener('input', function() {
             checkMaxQuantity(); // Check on input as well
        });

        function checkMaxQuantity() {
            if (typeOutRadio.checked) {
                const selectedOption = productSelect.options[productSelect.selectedIndex];
                if (selectedOption && selectedOption.value !== "") {
                    const currentStock = parseInt(selectedOption.dataset.currentStock, 10);
                    quantityInput.max = currentStock;
                    if (parseInt(quantityInput.value, 10) > currentStock) {
                       // quantityInput.value = currentStock; // Or show a warning
                       // Consider adding a visual warning here instead of auto-correcting
                    }
                } else {
                    quantityInput.removeAttribute('max');
                }
            } else {
                quantityInput.removeAttribute('max');
            }
        }
    }
});
</script>
<?php
include __DIR__ . '/../../templates/footer.php';
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
