<?php
// This template is included by modules/reports/purchases.php for 'purchase_return_summary'
// Access to $report_data, $report_title, $filter_period, $start_date, $end_date, $base_module_url,
// $supplier_filter, $suppliers_for_filter

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
$currency_symbol = '$';
$sql_currency_prr = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_prr = mysqli_query($conn, $sql_currency_prr);
if ($res_currency_prr && mysqli_num_rows($res_currency_prr) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_prr)['setting_value']);
    mysqli_free_result($res_currency_prr);
}

$period_display_text_prr = ucfirst($filter_period);
if ($filter_period === 'custom' && !empty($start_date) && !empty($end_date)) {
    $period_display_text_prr = "Custom Range: " . htmlspecialchars($start_date) . " to " . htmlspecialchars($end_date);
}
elseif ($filter_period === 'daily') { $period_display_text_prr = "Today (" . date('Y-m-d') . ")"; }
elseif ($filter_period === 'weekly') { $period_display_text_prr = "This Week"; }
elseif ($filter_period === 'monthly') { $period_display_text_prr = "This Month (" . date('F Y') . ")"; }
?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2><?php echo htmlspecialchars($report_title); ?></h2>
            <p>Displaying purchase returns for: <strong><?php echo $period_display_text_prr; ?></strong></p>
            <?php if (!empty($supplier_filter) && isset($suppliers_for_filter)): ?>
                <?php
                    $filtered_supp_name_prr = "Unknown Supplier";
                    foreach($suppliers_for_filter as $s) { if($s['id'] == $supplier_filter) $filtered_supp_name_prr = $s['name']; }
                ?>
                <p>Filtered by Supplier: <strong><?php echo htmlspecialchars($filtered_supp_name_prr); ?></strong></p>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#purchaseReturnFilterCollapse" aria-expanded="false" aria-controls="purchaseReturnFilterCollapse">
                <i class="fas fa-filter"></i> Filter Report
            </button>
        </div>
    </div>

    <div class="collapse mb-3" id="purchaseReturnFilterCollapse">
        <div class="card card-body">
            <form action="<?php echo htmlspecialchars($base_module_url); ?>" method="GET" class="form">
                <input type="hidden" name="module" value="reports">
                <input type="hidden" name="action" value="purchases">
                <input type="hidden" name="type" value="purchase_return_summary">
                <div class="row">
                    <div class="col-md-3">
                        <label for="period_prr">Period:</label>
                        <select class="form-control" id="period_prr" name="period">
                            <option value="daily" <?php echo ($filter_period == 'daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo ($filter_period == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo ($filter_period == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="custom" <?php echo ($filter_period == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                     <div class="col-md-4 <?php echo ($filter_period != 'custom') ? 'd-none' : ''; ?>" id="customDateRangePrr">
                        <div class="row">
                            <div class="col">
                                <label for="start_date_prr">Start Date:</label>
                                <input type="date" class="form-control" id="start_date_prr" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col">
                                <label for="end_date_prr">End Date:</label>
                                <input type="date" class="form-control" id="end_date_prr" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="supplier_id_prr">Supplier:</label>
                        <select class="form-control" id="supplier_id_prr" name="supplier_id">
                            <option value="">All Suppliers</option>
                            <?php foreach ($suppliers_for_filter as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>" <?php echo ($supplier_filter == $supplier['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 align-self-end">
                        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-check"></i> Apply</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($report_data)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered dataTable">
                <thead class="thead-dark">
                    <tr>
                        <th>Return Date</th>
                        <th>Return Note #</th>
                        <th>Original PO #</th>
                        <th>Supplier</th>
                        <th>Processed By</th>
                        <th class="text-right">Total Return Value</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                     <?php
                        $overall_prr_value = 0;
                    ?>
                    <?php foreach ($report_data as $row):
                        $overall_prr_value += $row['total_return_amount'];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($row['return_date']))); ?></td>
                            <td><?php echo htmlspecialchars($row['return_note_no']); ?></td>
                            <td><?php echo htmlspecialchars($row['original_po_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['processed_by']); ?></td>
                            <td class="text-right text-danger"><strong><?php echo $currency_symbol . number_format($row['total_return_amount'], 2); ?></strong></td>
                            <td><?php echo nl2br(htmlspecialchars($row['reason'] ? $row['reason'] : '-')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                 <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td colspan="5" class="text-right">Total Returned Value</td>
                        <td class="text-right text-danger"><?php echo $currency_symbol . number_format($overall_prr_value, 2); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            No purchase returns found for the selected criteria.
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodSelectPrr = document.getElementById('period_prr');
    const customDateRangePrr = document.getElementById('customDateRangePrr');

    function toggleCustomDatePrr() {
        if (periodSelectPrr.value === 'custom') {
            customDateRangePrr.classList.remove('d-none');
        } else {
            customDateRangePrr.classList.add('d-none');
        }
    }
    if(periodSelectPrr) periodSelectPrr.addEventListener('change', toggleCustomDatePrr);
});
</script>
