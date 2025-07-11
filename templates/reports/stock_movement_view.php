<?php
// This template is included by modules/reports/inventory.php for 'stock_movement'
// Access to $report_data (which contains 'movements', 'opening_stock', 'closing_stock', 'product_id_filtered'),
// $report_title, $filter_period, $start_date, $end_date, $base_module_url,
// $product_id_filter, $products_for_filter

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}

$movements_data = $report_data['movements'] ?? [];
$opening_stock_display = $report_data['opening_stock'];
$closing_stock_display = $report_data['closing_stock']; // This is current stock if period up to today
$is_product_filtered = !empty($report_data['product_id_filtered']);

$period_display_text_smv = ucfirst($filter_period);
if ($filter_period === 'custom' && !empty($start_date) && !empty($end_date)) {
    $period_display_text_smv = "Custom Range: " . htmlspecialchars($start_date) . " to " . htmlspecialchars($end_date);
}
elseif ($filter_period === 'daily') { $period_display_text_smv = "Today (" . date('Y-m-d') . ")"; }
elseif ($filter_period === 'weekly') { $period_display_text_smv = "This Week (" . date('Y-m-d', strtotime('monday this week')) . " to " . date('Y-m-d', strtotime('sunday this week')) . ")"; }
elseif ($filter_period === 'monthly') { $period_display_text_smv = "This Month (" . date('F Y') . ")"; }

$filtered_product_name_display = "All Products";
if ($is_product_filtered && isset($products_for_filter)) {
    foreach($products_for_filter as $p) {
        if($p['id'] == $report_data['product_id_filtered']) {
            $filtered_product_name_display = $p['name']." (".$p['sku'].")";
            break;
        }
    }
}

?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2><?php echo htmlspecialchars($report_title); ?></h2>
            <p>Displaying stock movements for: <strong><?php echo $period_display_text_smv; ?></strong></p>
            <p>Product Filter: <strong><?php echo htmlspecialchars($filtered_product_name_display); ?></strong></p>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#stockMovementFilterCollapse" aria-expanded="false" aria-controls="stockMovementFilterCollapse">
                <i class="fas fa-filter"></i> Filter Report
            </button>
        </div>
    </div>

    <div class="collapse mb-3" id="stockMovementFilterCollapse">
        <div class="card card-body">
            <form action="<?php echo htmlspecialchars($base_module_url); ?>" method="GET" class="form">
                <input type="hidden" name="module" value="reports">
                <input type="hidden" name="action" value="inventory">
                <input type="hidden" name="type" value="stock_movement">
                <div class="row">
                    <div class="col-md-3">
                        <label for="period_smv">Period:</label>
                        <select class="form-control" id="period_smv" name="period">
                            <option value="daily" <?php echo ($filter_period == 'daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo ($filter_period == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo ($filter_period == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="custom" <?php echo ($filter_period == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                     <div class="col-md-4 <?php echo ($filter_period != 'custom') ? 'd-none' : ''; ?>" id="customDateRangeSmv">
                        <div class="row">
                            <div class="col">
                                <label for="start_date_smv">Start Date:</label>
                                <input type="date" class="form-control" id="start_date_smv" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col">
                                <label for="end_date_smv">End Date:</label>
                                <input type="date" class="form-control" id="end_date_smv" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="product_id_smv">Product:</label>
                        <select class="form-control" id="product_id_smv" name="product_id">
                            <option value="">All Products (Summary)</option>
                            <?php foreach ($products_for_filter as $product): ?>
                                <option value="<?php echo $product['id']; ?>" <?php echo ($product_id_filter == $product['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['name'] . " (" . $product['sku'] . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                         <small class="form-text text-muted">Select a product to see detailed ledger with opening/closing stock.</small>
                    </div>
                    <div class="col-md-2 align-self-end">
                        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-check"></i> Apply</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($is_product_filtered && $opening_stock_display !== null): ?>
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="alert alert-info">
                <strong>Opening Stock (as of <?php echo htmlspecialchars($start_date); ?>): <?php echo htmlspecialchars($opening_stock_display); ?> units</strong>
            </div>
        </div>
        <div class="col-md-6">
             <div class="alert alert-info">
                <strong>Closing Stock (as of <?php echo htmlspecialchars($end_date); ?>): <?php echo htmlspecialchars($closing_stock_display); ?> units</strong>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <?php if (!empty($movements_data)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered dataTable">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Product SKU</th>
                        <th>Product Name</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th class="text-right">Qty In (+)</th>
                        <th class="text-right">Qty Out (-)</th>
                        <?php if ($is_product_filtered): ?>
                        <th class="text-right">Balance</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements_data as $mov): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($mov['movement_date']))); ?></td>
                            <td><?php echo htmlspecialchars($mov['product_sku']); ?></td>
                            <td><?php echo htmlspecialchars($mov['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($mov['type']); ?></td>
                            <td><?php echo htmlspecialchars($mov['reference']); ?></td>
                            <td class="text-right text-success"><?php echo ($mov['qty_in'] > 0) ? htmlspecialchars($mov['qty_in']) : '-'; ?></td>
                            <td class="text-right text-danger"><?php echo ($mov['qty_out'] > 0) ? htmlspecialchars($mov['qty_out']) : '-'; ?></td>
                            <?php if ($is_product_filtered): ?>
                            <td class="text-right font-weight-bold"><?php echo htmlspecialchars($mov['balance_stock'] ?? 'N/A'); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            No stock movements found for the selected criteria.
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodSelectSmv = document.getElementById('period_smv');
    const customDateRangeSmv = document.getElementById('customDateRangeSmv');

    function toggleCustomDateSmv() {
        if (periodSelectSmv.value === 'custom') {
            customDateRangeSmv.classList.remove('d-none');
        } else {
            customDateRangeSmv.classList.add('d-none');
        }
    }
   if(periodSelectSmv) periodSelectSmv.addEventListener('change', toggleCustomDateSmv);
});
</script>
