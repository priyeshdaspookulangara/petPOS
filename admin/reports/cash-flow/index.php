<?php
$page_title = "Cash Flow Summary";
$app_base_path = '/';

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/auth.php';

redirect_if_not_admin();
$currency_symbol = ($mysqli->query("SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'")->fetch_assoc()['setting_value']) ?? '$';

// --- Filtering Logic ---
$default_date_from = date('Y-m-01');
$default_date_to = date('Y-m-t');
$filter_date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? sanitize_input($mysqli, $_GET['date_from']) : $default_date_from;
$filter_date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? sanitize_input($mysqli, $_GET['date_to']) : $default_date_to;

// --- Report Queries ---
$cash_inflows = 0;
$cash_outflows = 0;

// Cash Inflows: Sales
$sql_inflows = "SELECT SUM(grand_total) as total_inflows
                FROM sales
                WHERE sale_date BETWEEN '$filter_date_from 00:00:00' AND '$filter_date_to 23:59:59'";
$res_inflows = $mysqli->query($sql_inflows);
if ($res_inflows) {
    $cash_inflows = (float)($res_inflows->fetch_assoc()['total_inflows'] ?? 0);
    $res_inflows->free();
}

// Cash Outflows: Purchases + Expenses
$sql_outflows_purchases = "SELECT SUM(total_amount) as total_purchases
                           FROM purchases
                           WHERE purchase_date BETWEEN '$filter_date_from' AND '$filter_date_to'
                           AND status IN ('Ordered', 'Received', 'Partially Received')"; // Only count ordered/received purchases as outflows
$res_out_purch = $mysqli->query($sql_outflows_purchases);
if ($res_out_purch) {
    $cash_outflows += (float)($res_out_purch->fetch_assoc()['total_purchases'] ?? 0);
    $res_out_purch->free();
}

$sql_outflows_expenses = "SELECT SUM(amount) as total_expenses
                          FROM expenses
                          WHERE expense_date BETWEEN '$filter_date_from' AND '$filter_date_to'";
$res_out_exp = $mysqli->query($sql_outflows_expenses);
if ($res_out_exp) {
    $cash_outflows += (float)($res_out_exp->fetch_assoc()['total_expenses'] ?? 0);
    $res_out_exp->free();
}

$net_cash_flow = $cash_inflows - $cash_outflows;

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-2 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    <p class="mb-4">A simplified view of cash inflows (Sales) vs. outflows (Purchases & Expenses) for a period.</p>

    <!-- Filter Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter by Date Range</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-5">
                        <label for="date_from">Date From</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                    </div>
                    <div class="form-group col-md-5">
                        <label for="date_to">Date To</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                    </div>
                    <div class="form-group col-md-2">
                        <button type="submit" class="btn btn-info"><i class="fas fa-filter"></i> Generate</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Cash Flow Summary -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Cash Flow for <?php echo htmlspecialchars($filter_date_from) . " to " . htmlspecialchars($filter_date_to); ?></h6>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-4">
                    <div class="card border-left-success py-3">
                        <div class="card-body">
                            <h5 class="card-title text-success">Cash Inflows</h5>
                            <p class="card-text h3"><?php echo $currency_symbol . number_format($cash_inflows, 2); ?></p>
                            <small class="text-muted">(From Sales)</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-left-danger py-3">
                        <div class="card-body">
                            <h5 class="card-title text-danger">Cash Outflows</h5>
                            <p class="card-text h3"><?php echo $currency_symbol . number_format($cash_outflows, 2); ?></p>
                            <small class="text-muted">(From Purchases & Expenses)</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-left-primary py-3">
                        <div class="card-body">
                            <h5 class="card-title text-primary">Net Cash Flow</h5>
                            <p class="card-text h3 <?php echo ($net_cash_flow >= 0 ? 'text-success' : 'text-danger'); ?>">
                                <?php echo $currency_symbol . number_format($net_cash_flow, 2); ?>
                            </p>
                            <small class="text-muted">(Inflows - Outflows)</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <p class="text-muted text-center">
                    <small><strong>Disclaimer:</strong> This is a highly simplified cash flow view. It assumes all sales are immediate cash inflows and all ordered/received purchases and logged expenses are immediate cash outflows. It does not account for credit terms, accounts receivable/payable, depreciation, or other non-cash items.</small>
                </p>
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
