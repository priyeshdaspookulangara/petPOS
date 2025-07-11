<?php
// This template is included by modules/reports/purchases.php for 'by_supplier'
// Access to $report_data, $report_title, $filter_period, $start_date, $end_date, $base_module_url,
// $supplier_filter, $suppliers_for_filter

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}
$currency_symbol = '$';
$sql_currency_pbs = "SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'";
$res_currency_pbs = mysqli_query($conn, $sql_currency_pbs);
if ($res_currency_pbs && mysqli_num_rows($res_currency_pbs) > 0) {
    $currency_symbol = htmlspecialchars(mysqli_fetch_assoc($res_currency_pbs)['setting_value']);
    mysqli_free_result($res_currency_pbs);
}

$period_display_text_pbs = ucfirst($filter_period);
if ($filter_period === 'custom' && !empty($start_date) && !empty($end_date)) {
    $period_display_text_pbs = "Custom Range: " . htmlspecialchars($start_date) . " to " . htmlspecialchars($end_date);
}
elseif ($filter_period === 'daily') { $period_display_text_pbs = "Today (" . date('Y-m-d') . ")"; }
elseif ($filter_period === 'weekly') { $period_display_text_pbs = "This Week"; }
elseif ($filter_period === 'monthly') { $period_display_text_pbs = "This Month (" . date('F Y') . ")"; }

?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2><?php echo htmlspecialchars($report_title); ?></h2>
            <p>Displaying purchases by supplier for: <strong><?php echo $period_display_text_pbs; ?></strong></p>
            <?php if (!empty($supplier_filter) && isset($suppliers_for_filter)): ?>
                <?php
                    $filtered_supp_name = "Unknown Supplier";
                    foreach($suppliers_for_filter as $s) { if($s['id'] == $supplier_filter) $filtered_supp_name = $s['name']; }
                ?>
                <p>Filtered by Supplier: <strong><?php echo htmlspecialchars($filtered_supp_name); ?></strong></p>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#purchasesBySupplierFilterCollapse" aria-expanded="false" aria-controls="purchasesBySupplierFilterCollapse">
                <i class="fas fa-filter"></i> Filter Report
            </button>
        </div>
    </div>

    <div class="collapse mb-3" id="purchasesBySupplierFilterCollapse">
        <div class="card card-body">
            <form action="<?php echo htmlspecialchars($base_module_url); ?>" method="GET" class="form">
                <input type="hidden" name="module" value="reports">
                <input type="hidden" name="action" value="purchases">
                <input type="hidden" name="type" value="by_supplier">
                <div class="row">
                    <div class="col-md-3">
                        <label for="period_pbs">Period:</label>
                        <select class="form-control" id="period_pbs" name="period">
                            <option value="daily" <?php echo ($filter_period == 'daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo ($filter_period == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo ($filter_period == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="custom" <?php echo ($filter_period == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                     <div class="col-md-4 <?php echo ($filter_period != 'custom') ? 'd-none' : ''; ?>" id="customDateRangePbs">
                        <div class="row">
                            <div class="col">
                                <label for="start_date_pbs">Start Date:</label>
                                <input type="date" class="form-control" id="start_date_pbs" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col">
                                <label for="end_date_pbs">End Date:</label>
                                <input type="date" class="form-control" id="end_date_pbs" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="supplier_id_pbs">Supplier:</label>
                        <select class="form-control" id="supplier_id_pbs" name="supplier_id">
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
                        <th>Supplier ID</th>
                        <th>Supplier Name</th>
                        <th class="text-right">No. of POs</th>
                        <th class="text-right">Total PO Value</th>
                        <th class="text-right">Total Amount Paid</th>
                    </tr>
                </thead>
                <tbody>
                     <?php
                        $overall_pbs_po_count = 0;
                        $overall_pbs_po_value = 0;
                        $overall_pbs_amount_paid = 0;
                    ?>
                    <?php foreach ($report_data as $row):
                        $overall_pbs_po_count += $row['number_of_pos'];
                        $overall_pbs_po_value += $row['total_po_value'];
                        $overall_pbs_amount_paid += $row['total_amount_paid'];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['supplier_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                            <td class="text-right"><?php echo htmlspecialchars($row['number_of_pos']); ?></td>
                            <td class="text-right"><?php echo $currency_symbol . number_format($row['total_po_value'], 2); ?></td>
                            <td class="text-right"><?php echo $currency_symbol . number_format($row['total_amount_paid'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                 <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td colspan="2">Total</td>
                        <td class="text-right"><?php echo $overall_pbs_po_count; ?></td>
                        <td class="text-right"><?php echo $currency_symbol . number_format($overall_pbs_po_value, 2); ?></td>
                        <td class="text-right"><?php echo $currency_symbol . number_format($overall_pbs_amount_paid, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            No purchase data found for the selected supplier and period.
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodSelectPbs = document.getElementById('period_pbs');
    const customDateRangePbs = document.getElementById('customDateRangePbs');

    function toggleCustomDatePbs() {
        if (periodSelectPbs.value === 'custom') {
            customDateRangePbs.classList.remove('d-none');
        } else {
            customDateRangePbs.classList.add('d-none');
        }
    }
    if(periodSelectPbs) periodSelectPbs.addEventListener('change', toggleCustomDatePbs);
});
</script>
