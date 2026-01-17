<?php
$page_title = "Purchase Reports";
$app_base_path = '/';

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/auth.php';

redirect_if_not_admin();
$currency_symbol = ($mysqli->query("SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'")->fetch_assoc()['setting_value']) ?? '$';

// --- Fetch data for filters ---
$suppliers = [];
$sup_sql = "SELECT id, name FROM suppliers ORDER BY name ASC";
$sup_res = $mysqli->query($sup_sql);
if ($sup_res) while($row = $sup_res->fetch_assoc()) $suppliers[] = $row;
if ($sup_res) $sup_res->free();

// --- Filtering Logic ---
$where_clauses = [];
$default_date_from = date('Y-m-01');
$default_date_to = date('Y-m-t');
$filter_date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? sanitize_input($mysqli, $_GET['date_from']) : $default_date_from;
$filter_date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? sanitize_input($mysqli, $_GET['date_to']) : $default_date_to;
$filter_supplier_id = isset($_GET['supplier_id']) && !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;

// Always filter by date range
$where_clauses[] = "p.purchase_date BETWEEN '$filter_date_from' AND '$filter_date_to'";
// Filter out canceled POs from reports
$where_clauses[] = "p.status != 'Canceled'";

if ($filter_supplier_id) {
    $where_clauses[] = "p.supplier_id = $filter_supplier_id";
}

$sql_where = "WHERE " . implode(" AND ", $where_clauses);

// --- Report Queries ---

// 1. Purchase Summary by Date Range
$purchases_by_date = [];
$sql_by_date = "SELECT
                    p.id, p.po_number, p.purchase_date, p.total_amount, p.status,
                    s.name as supplier_name
                FROM purchases p
                JOIN suppliers s ON p.supplier_id = s.id
                $sql_where
                ORDER BY p.purchase_date DESC";
$res_by_date = $mysqli->query($sql_by_date);
$total_purchase_amount_period = 0;
if($res_by_date) {
    while($row = $res_by_date->fetch_assoc()) {
        $purchases_by_date[] = $row;
        $total_purchase_amount_period += (float)$row['total_amount'];
    }
    $res_by_date->free();
}

// 2. Purchase Summary by Supplier
$purchases_by_supplier = [];
$sql_by_supplier = "SELECT
                        s.id, s.name as supplier_name,
                        COUNT(p.id) as total_pos,
                        SUM(p.total_amount) as total_purchase_value
                    FROM purchases p
                    JOIN suppliers s ON p.supplier_id = s.id
                    $sql_where
                    GROUP BY s.id, s.name
                    ORDER BY total_purchase_value DESC";
$res_by_supplier = $mysqli->query($sql_by_supplier);
if($res_by_supplier) while($row = $res_by_supplier->fetch_assoc()) $purchases_by_supplier[] = $row;
if($res_by_supplier) $res_by_supplier->free();


include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-2 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    <p class="mb-4">Reports for purchase activity. Canceled orders are excluded from these reports.</p>

    <!-- Filter Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Reports</h6>
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
                        <label for="supplier_id">Supplier</label>
                        <select name="supplier_id" id="supplier_id" class="form-control">
                            <option value="">-- All Suppliers --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?php echo $sup['id']; ?>" <?php if ($filter_supplier_id == $sup['id']) echo 'selected'; ?>><?php echo htmlspecialchars($sup['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                     <div class="form-group col-md-12">
                        <button type="submit" class="btn btn-info"><i class="fas fa-filter"></i> Generate Report</button>
                        <a href="<?php echo site_url('admin/reports/purchases', $app_base_path); ?>" class="btn btn-secondary ml-2" title="Reset to Current Month"><i class="fas fa-sync-alt"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Purchases by Date -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Purchases by Date Range (Total: <?php echo $currency_symbol . number_format($total_purchase_amount_period, 2); ?>)</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead><tr><th>Date</th><th>PO Number</th><th>Supplier</th><th>Status</th><th class="text-right">Amount</th></tr></thead>
                            <tbody>
                                <?php if(!empty($purchases_by_date)): foreach($purchases_by_date as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['purchase_date']); ?></td>
                                        <td><a href="<?php echo site_url('admin/view-po/?id='.$row['id']); ?>"><?php echo htmlspecialchars($row['po_number'] ?: 'PO-'.$row['id']); ?></a></td>
                                        <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                                        <td class="text-right"><?php echo $currency_symbol . number_format($row['total_amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="5" class="text-center">No purchases found for this period.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Purchases by Supplier -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Summary by Supplier</h6></div>
                <div class="card-body">
                     <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead><tr><th>Supplier</th><th class="text-center"># of POs</th><th class="text-right">Total Value</th></tr></thead>
                            <tbody>
                                <?php if(!empty($purchases_by_supplier)): foreach($purchases_by_supplier as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                        <td class="text-center"><?php echo $row['total_pos']; ?></td>
                                        <td class="text-right"><?php echo $currency_symbol . number_format($row['total_purchase_value'], 2); ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="3" class="text-center">No supplier purchase data for this period.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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
