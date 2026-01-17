<?php
$page_title = "Admin Dashboard";
// $app_base_path needs to be defined before including header.php if not globally defined.
// For consistency, it's better to define $app_base_path in a central config file.
// Let's assume it's defined or defaults correctly in header.php for now.
// If not, define it: $app_base_path = '/'; // Adjust if needed

require_once __DIR__ . '/../../includes/db_connect.php'; // For $mysqli and potential DB operations
require_once __DIR__ . '/../../includes/auth.php';     // For redirect_if_not_admin()

// Ensure user is logged in and is an admin
redirect_if_not_admin(); // This will also handle not being logged in

// Fetch store name for display if not already in session (e.g. after login)
if (!isset($_SESSION['store_name'])) {
    $store_name_query = "SELECT setting_value FROM settings WHERE setting_key = 'store_name'";
    $store_name_result = $mysqli->query($store_name_query);
    if ($store_name_result && $store_name_result->num_rows > 0) {
        $_SESSION['store_name'] = $store_name_result->fetch_assoc()['setting_value'];
    }
    $store_name_result->free();
}


// Placeholder: Fetch some quick stats for the dashboard
// Number of products
$product_count_sql = "SELECT COUNT(*) as count FROM products";
$product_count_result = $mysqli->query($product_count_sql);
$product_count = ($product_count_result) ? $product_count_result->fetch_assoc()['count'] : 0;
if($product_count_result) $product_count_result->free();

// Number of sales today
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
// IMPORTANT: No prepared statements. Dates are generated server-side, so less risk, but be mindful.
$sales_today_sql = "SELECT COUNT(*) as count FROM sales WHERE sale_date >= '$today_start' AND sale_date <= '$today_end'";
$sales_today_result = $mysqli->query($sales_today_sql);
$sales_today_count = ($sales_today_result) ? $sales_today_result->fetch_assoc()['count'] : 0;
if($sales_today_result) $sales_today_result->free();

// Total users
$user_count_sql = "SELECT COUNT(*) as count FROM users";
$user_count_result = $mysqli->query($user_count_sql);
$user_count = ($user_count_result) ? $user_count_result->fetch_assoc()['count'] : 0;
if($user_count_result) $user_count_result->free();

// Low stock items count
$low_stock_sql = "SELECT COUNT(*) as count FROM products WHERE current_stock <= reorder_level AND reorder_level > 0";
$low_stock_result = $mysqli->query($low_stock_sql);
$low_stock_count = ($low_stock_result) ? $low_stock_result->fetch_assoc()['count'] : 0;
if($low_stock_result) $low_stock_result->free();


include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
        <!-- Optional: Add a button like "Generate Report" if relevant -->
        <!-- <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i class="fas fa-download fa-sm text-white-50"></i> Generate Report</a> -->
    </div>

    <!-- Quick Stats Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Products</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $product_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-boxes fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Sales (Today)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $sales_today_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Users</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $user_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Low Stock Items</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $low_stock_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                         <div class="col-12 mt-1">
                            <a href="<?php echo site_url('admin/reorder-alerts', $app_base_path); ?>" class="text-xs">View Details &rarr;</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Placeholder for more dashboard content -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Links</h6>
                </div>
                <div class="card-body">
                    <ul>
                        <li><a href="<?php echo site_url('pos', $app_base_path); ?>">Go to POS</a></li>
                        <li><a href="<?php echo site_url('admin/products', $app_base_path); ?>">Manage Products</a></li>
                        <li><a href="<?php echo site_url('admin/users', $app_base_path); ?>">Manage Users</a></li>
                        <li><a href="<?php echo site_url('admin/settings', $app_base_path); ?>">System Settings</a></li>
                        <li><a href="<?php echo site_url('admin/reports/sales', $app_base_path); ?>">View Sales Reports</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
             <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">System Information</h6>
                </div>
                <div class="card-body">
                    <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                    <p><strong>MySQL Version:</strong> <?php echo $mysqli->server_info; ?></p>
                    <p><strong>Web Server:</strong> <?php echo isset($_SERVER['SERVER_SOFTWARE']) ? htmlspecialchars($_SERVER['SERVER_SOFTWARE']) : 'N/A'; ?></p>
                    <p><strong>Store Name:</strong> <?php echo isset($_SESSION['store_name']) ? htmlspecialchars($_SESSION['store_name']) : 'Not Set'; ?></p>
                    <!-- Add more relevant system info if needed -->
                </div>
            </div>
        </div>
    </div>

</div>

<?php
include __DIR__ . '/../../templates/footer.php';
// Close the database connection
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
