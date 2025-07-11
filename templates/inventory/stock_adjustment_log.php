<?php
// This template is included by modules/inventory/stock_adjustments.php when $page_action is 'list'
// It has access to $adjustments array and $base_module_url

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
?>

<div class="container-fluid mt-4"> <!-- Use container-fluid for wider tables -->
    <div class="row mb-3">
        <div class="col">
            <h2>Stock Adjustment Log</h2>
        </div>
        <div class="col text-right">
            <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=add'); ?>" class="btn btn-success">
                <i class="fas fa-plus"></i> New Stock Adjustment
            </a>
        </div>
    </div>

    <?php
    // Session messages are displayed by header.php
    ?>

    <div class="table-responsive">
        <?php if (!empty($adjustments)): ?>
            <table class="table table-striped table-bordered table-hover dataTable">
                <thead class="thead-dark">
                    <tr>
                        <th>Log ID</th>
                        <th>Date</th>
                        <th>Product SKU</th>
                        <th>Product Name</th>
                        <th>User</th>
                        <th>Type</th>
                        <th class="text-right">Qty Changed</th>
                        <th class="text-right">New Stock Level</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($adjustments as $adj): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($adj['id']); ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($adj['adjustment_date']))); ?></td>
                            <td><?php echo htmlspecialchars($adj['product_sku']); ?></td>
                            <td><?php echo htmlspecialchars($adj['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($adj['user_username']); ?></td>
                            <td>
                                <?php
                                    $type_display = ucfirst(str_replace('_', ' ', $adj['type_of_adjustment']));
                                    $badge_class = 'secondary';
                                    if ($adj['type_of_adjustment'] === 'increase' || $adj['type_of_adjustment'] === 'initial_stock') {
                                        $badge_class = 'success';
                                    } elseif ($adj['type_of_adjustment'] === 'decrease') {
                                        $badge_class = 'danger';
                                    } elseif ($adj['type_of_adjustment'] === 'correction') {
                                         $badge_class = 'info';
                                    }
                                    echo '<span class="badge badge-' . $badge_class . '">' . htmlspecialchars($type_display) . '</span>';
                                ?>
                            </td>
                            <td class="text-right <?php echo ($adj['quantity_changed'] < 0) ? 'text-danger font-weight-bold' : 'text-success font-weight-bold'; ?>">
                                <?php echo htmlspecialchars($adj['quantity_changed']); ?>
                            </td>
                            <td class="text-right"><?php echo htmlspecialchars($adj['new_stock_level']); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($adj['reason'] ? $adj['reason'] : '-')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                No stock adjustments found yet. <a href="<?php echo htmlspecialchars($base_module_url . '&sub_action=add'); ?>">Make the first adjustment.</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// DataTables initialization script can be added here if desired (same as in stock_levels_view.php)
?>
