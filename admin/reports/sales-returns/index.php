<?php
$page_title = "Sales Returns Report";
$app_base_path = '/';

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/auth.php';

redirect_if_not_admin();

// Filtering Logic
$default_date_from = date('Y-m-01');
$default_date_to = date('Y-m-t');
$filter_date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? sanitize_input($mysqli, $_GET['date_from']) : $default_date_from;
$filter_date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? sanitize_input($mysqli, $_GET['date_to']) : $default_date_to;

$sql_where = "WHERE sr.return_date BETWEEN '$filter_date_from 00:00:00' AND '$filter_date_to 23:59:59'";

// Report Query
$returns_sql = "SELECT
                    sr.id as return_id, sr.return_date, sr.total_refund_amount, sr.reason,
                    s.receipt_no as original_receipt_no, s.id as original_sale_id,
                    u.username as processed_by
                FROM sales_returns sr
                JOIN sales s ON sr.original_sale_id = s.id
                JOIN users u ON sr.user_id = u.id
                $sql_where
                ORDER BY sr.return_date DESC";
$returns_res = $mysqli->query($returns_sql);
$currency_symbol = ($mysqli->query("SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'")->fetch_assoc()['setting_value']) ?? '$';

$total_refund_amount = 0;
$returns_data = [];
if ($returns_res) {
    while($row = $returns_res->fetch_assoc()){
        $returns_data[] = $row;
        $total_refund_amount += (float)$row['total_refund_amount'];
    }
    $returns_res->free();
}

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-2 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Report</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-4">
                        <label for="date_from">Date From</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="date_to">Date To</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <button type="submit" class="btn btn-info"><i class="fas fa-filter"></i> Generate</button>
                         <a href="<?php echo site_url('admin/reports/sales-returns', $app_base_path); ?>" class="btn btn-secondary ml-2" title="Reset to Current Month"><i class="fas fa-sync-alt"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Sales Returns for Period (Total Refunded: <?php echo $currency_symbol . number_format($total_refund_amount, 2); ?>)
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Return ID</th>
                            <th>Return Date</th>
                            <th>Original Receipt</th>
                            <th>Reason</th>
                            <th class="text-right">Total Refund</th>
                            <th>Processed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($returns_data)):
                            foreach($returns_data as $row): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo site_url('pos/sales-return/?id='.$row['return_id']); // Link to view return details, needs creating maybe ?>">
                                        SR-<?php echo $row['return_id']; ?>
                                    </a>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($row['return_date'])); ?></td>
                                <td>
                                    <a href="<?php echo site_url('pos/receipt/?id='.$row['original_sale_id']); ?>">
                                        <?php echo htmlspecialchars($row['original_receipt_no']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($row['reason'] ?: '-'); ?></td>
                                <td class="text-right"><?php echo $currency_symbol . number_format($row['total_refund_amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['processed_by']); ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" class="text-center">No sales returns found for this period.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-right">Total Refunded in this Period:</th>
                            <th class="text-right"><?php echo $currency_symbol . number_format($total_refund_amount, 2); ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../../templates/footer.php';
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
