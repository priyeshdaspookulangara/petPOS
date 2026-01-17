<?php
$page_title = "Sales Reports";
$app_base_path = '/';

require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/auth.php';

redirect_if_not_admin();

// --- Fetch data for filters ---
$users = [];
$user_sql = "SELECT id, username FROM users WHERE role = 'Cashier' OR role = 'Admin' ORDER BY username ASC";
$user_res = $mysqli->query($user_sql);
if ($user_res) while($row = $user_res->fetch_assoc()) $users[] = $row;
if ($user_res) $user_res->free();

$categories = [];
$cat_sql = "SELECT id, name FROM categories ORDER BY name ASC";
$cat_res = $mysqli->query($cat_sql);
if ($cat_res) while($row = $cat_res->fetch_assoc()) $categories[] = $row;
if ($cat_res) $cat_res->free();


// --- Filtering Logic ---
$where_clauses = [];
$join_clauses = []; // For filtering by category

$filter_user_id = isset($_GET['user_id']) && !empty($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$filter_category_id = isset($_GET['category_id']) && !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
// Default date range to current month
$default_date_from = date('Y-m-01');
$default_date_to = date('Y-m-t');
$filter_date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? sanitize_input($mysqli, $_GET['date_from']) : $default_date_from;
$filter_date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? sanitize_input($mysqli, $_GET['date_to']) : $default_date_to;

// Always filter by date range
$where_clauses[] = "s.sale_date BETWEEN '$filter_date_from 00:00:00' AND '$filter_date_to 23:59:59'";

if ($filter_user_id) {
    $where_clauses[] = "s.user_id = $filter_user_id";
}
if ($filter_category_id) {
    // Need to join sale_items and products
    $join_clauses[] = "JOIN sale_items si_cat ON s.id = si_cat.sale_id";
    $join_clauses[] = "JOIN products p_cat ON si_cat.product_id = p_cat.id";
    $where_clauses[] = "p_cat.category_id = $filter_category_id";
}

$sql_where = "WHERE " . implode(" AND ", $where_clauses);
$sql_join = implode(" ", array_unique($join_clauses)); // array_unique to avoid duplicate joins if logic expands


// --- Report Queries ---
$currency_symbol = ($mysqli->query("SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'")->fetch_assoc()['setting_value']) ?? '$';

// 1. Sales Summary
$summary_sql = "SELECT
                    COUNT(DISTINCT s.id) as total_sales_count,
                    SUM(s.grand_total) as total_revenue,
                    SUM(s.discount_amount) as total_discount,
                    SUM(s.tax_amount) as total_tax,
                    (SELECT SUM(si.quantity) FROM sale_items si JOIN sales s_inner ON si.sale_id = s_inner.id WHERE " . implode(" AND ", $where_clauses) . ") as total_items_sold
                FROM sales s $sql_join $sql_where";
$summary_res = $mysqli->query($summary_sql);
$sales_summary = $summary_res ? $summary_res->fetch_assoc() : [];
if($summary_res) $summary_res->free();

// 2. Sales by Product
$products_report_sql = "SELECT
                            p.id, p.name, p.sku,
                            SUM(si.quantity) as total_quantity_sold,
                            SUM(si.item_total) as total_revenue_per_product
                        FROM sale_items si
                        JOIN sales s ON si.sale_id = s.id
                        JOIN products p ON si.product_id = p.id
                        $sql_where
                        GROUP BY p.id, p.name, p.sku
                        ORDER BY total_quantity_sold DESC
                        LIMIT 50"; // Limit to top 50 for this report page
$products_res = $mysqli->query($products_report_sql);

// 3. Sales by Category
$categories_report_sql = "SELECT
                            c.id, c.name as category_name,
                            SUM(si.quantity) as total_quantity_sold,
                            SUM(si.item_total) as total_revenue_per_category
                         FROM sale_items si
                         JOIN sales s ON si.sale_id = s.id
                         JOIN products p ON si.product_id = p.id
                         JOIN categories c ON p.category_id = c.id
                         $sql_where
                         GROUP BY c.id, c.name
                         ORDER BY total_revenue_per_category DESC";
$categories_res = $mysqli->query($categories_report_sql);

// 4. Sales by User
$users_report_sql = "SELECT
                        u.id, u.username,
                        COUNT(s.id) as number_of_sales,
                        SUM(s.grand_total) as total_revenue_per_user
                     FROM sales s
                     JOIN users u ON s.user_id = u.id
                     $sql_join $sql_where
                     GROUP BY u.id, u.username
                     ORDER BY total_revenue_per_user DESC";
$users_res = $mysqli->query($users_report_sql);


include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-2 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    <p class="mb-4">Reports for sales activity. Use the filters to narrow down the results.</p>

    <!-- Filter Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Reports</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="date_from">Date From</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="date_to">Date To</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="user_id">Cashier/User</label>
                        <select name="user_id" id="user_id" class="form-control">
                            <option value="">-- All Users --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php if ($filter_user_id == $user['id']) echo 'selected'; ?>><?php echo htmlspecialchars($user['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="category_id">Product Category</label>
                        <select name="category_id" id="category_id" class="form-control">
                            <option value="">-- All Categories --</option>
                             <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php if ($filter_category_id == $cat['id']) echo 'selected'; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                 <div class="form-row">
                    <div class="form-group col-md-12">
                        <button type="submit" class="btn btn-info"><i class="fas fa-filter"></i> Generate Report</button>
                        <a href="<?php echo site_url('admin/reports/sales', $app_base_path); ?>" class="btn btn-secondary ml-2" title="Reset to Current Month"><i class="fas fa-sync-alt"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Sales Summary Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4"><div class="card border-left-primary shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Sales</div><div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $sales_summary['total_sales_count'] ?? 0; ?></div></div><div class="col-auto"><i class="fas fa-receipt fa-2x text-gray-300"></i></div></div></div></div></div>
        <div class="col-xl-3 col-md-6 mb-4"><div class="card border-left-success shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Revenue</div><div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $currency_symbol . number_format($sales_summary['total_revenue'] ?? 0, 2); ?></div></div><div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-gray-300"></i></div></div></div></div></div>
        <div class="col-xl-3 col-md-6 mb-4"><div class="card border-left-info shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Items Sold</div><div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $sales_summary['total_items_sold'] ?? 0; ?></div></div><div class="col-auto"><i class="fas fa-boxes fa-2x text-gray-300"></i></div></div></div></div></div>
        <div class="col-xl-3 col-md-6 mb-4"><div class="card border-left-warning shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Discount Given</div><div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $currency_symbol . number_format($sales_summary['total_discount'] ?? 0, 2); ?></div></div><div class="col-auto"><i class="fas fa-tags fa-2x text-gray-300"></i></div></div></div></div></div>
    </div>

    <div class="row">
        <!-- Sales by Product -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Top 50 Products by Quantity Sold</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead><tr><th>Product</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
                            <tbody>
                                <?php if($products_res && $products_res->num_rows > 0): while($row = $products_res->fetch_assoc()): ?>
                                    <tr><td><?php echo htmlspecialchars($row['name']); ?></td><td><?php echo $row['total_quantity_sold']; ?></td><td><?php echo $currency_symbol . number_format($row['total_revenue_per_product'], 2); ?></td></tr>
                                <?php endwhile; $products_res->free(); else: ?>
                                    <tr><td colspan="3" class="text-center">No product sales data for this period.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Sales by Category -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Sales by Category</h6></div>
                <div class="card-body">
                     <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead><tr><th>Category</th><th>Items Sold</th><th>Revenue</th></tr></thead>
                            <tbody>
                                <?php if($categories_res && $categories_res->num_rows > 0): while($row = $categories_res->fetch_assoc()): ?>
                                    <tr><td><?php echo htmlspecialchars($row['category_name']); ?></td><td><?php echo $row['total_quantity_sold']; ?></td><td><?php echo $currency_symbol . number_format($row['total_revenue_per_category'], 2); ?></td></tr>
                                <?php endwhile; $categories_res->free(); else: ?>
                                    <tr><td colspan="3" class="text-center">No category sales data for this period.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
             <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Sales by User</h6></div>
                <div class="card-body">
                     <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead><tr><th>User</th><th># of Sales</th><th>Total Revenue</th></tr></thead>
                            <tbody>
                                <?php if($users_res && $users_res->num_rows > 0): while($row = $users_res->fetch_assoc()): ?>
                                    <tr><td><?php echo htmlspecialchars($row['username']); ?></td><td><?php echo $row['number_of_sales']; ?></td><td><?php echo $currency_symbol . number_format($row['total_revenue_per_user'], 2); ?></td></tr>
                                <?php endwhile; $users_res->free(); else: ?>
                                    <tr><td colspan="3" class="text-center">No user sales data for this period.</td></tr>
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
