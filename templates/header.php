<?php
// session_start(); // Session is already started in index.php
if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$module_param = isset($_GET['module']) ? $_GET['module'] : '';
$page_param = isset($_GET['page']) ? $_GET['page'] : '';

// Define a base URL for cleaner links if not using full .htaccess rewrites for assets yet
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$script_name = $_SERVER['SCRIPT_NAME']; // e.g., /pos_project/index.php or /index.php

// $base_url should now simply be the path to index.php
// If index.php is in root, $script_name is /index.php
// If in subdir like /mypos/, $script_name is /mypos/index.php
$base_url = $protocol . "://" . $host . $script_name;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System</title>
    <!-- Bootstrap CSS CDN -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome CDN (for icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Custom CSS (if any) -->
    <?php
        // For assets, we need the path relative to the domain, not including index.php
        $asset_base_path = dirname($script_name);
        if ($asset_base_path === '/' || $asset_base_path === '\\') $asset_base_path = '';
        $asset_base_url = $protocol . "://" . $host . $asset_base_path;
    ?>
    <?php if (file_exists(BASE_PATH . '/assets/css/style.css')): // Check actual file existence using server path ?>
        <link rel="stylesheet" href="<?php echo $asset_base_url; ?>/assets/css/style.css">
    <?php endif; ?>
    <script>
        // Define base_url for JavaScript usage if needed for AJAX calls
        // This BASE_URL should point to index.php for AJAX routing
        const BASE_APP_URL = "<?php echo $base_url; ?>"; // $base_url now includes index.php
    </script>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <a class="navbar-brand" href="<?php echo $base_url; ?>?page=dashboard">POS System</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav mr-auto">
            <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item <?php echo ($page_param == 'dashboard' || empty($page_param) && empty($module_param)) ? 'active' : ''; ?>">
                    <a class="nav-link" href="<?php echo $base_url; ?>?page=dashboard">Dashboard</a>
                </li>
                <li class="nav-item <?php echo ($module_param == 'pos') ? 'active' : ''; ?>">
                    <a class="nav-link" href="<?php echo $base_url; ?>?module=pos">POS</a>
                </li>

                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
                <li class="nav-item dropdown <?php echo ($module_param == 'inventory') ? 'active' : ''; ?>">
                    <a class="nav-link dropdown-toggle" href="#" id="inventoryDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Inventory
                    </a>
                    <div class="dropdown-menu" aria-labelledby="inventoryDropdown">
                        <a class="dropdown-item" href="<?php echo $base_url; ?>?module=inventory&action=products">Products</a>
                        <a class="dropdown-item" href="<?php echo $base_url; ?>?module=inventory&action=categories">Categories</a>
                        <a class="dropdown-item" href="<?php echo $base_url; ?>?module=inventory&action=suppliers">Suppliers</a>
                        <a class="dropdown-item" href="<?php echo $base_url; ?>?module=inventory&action=stock_levels">Stock Levels</a>
                        <a class="dropdown-item" href="<?php echo $base_url; ?>?module=inventory&action=stock_adjustments">Stock Adjustments</a>
                         <a class="dropdown-item" href="<?php echo $base_url; ?>?module=inventory&action=reorder_alerts">Reorder Alerts</a>
                    </div>
                </li>
                <li class="nav-item dropdown <?php echo ($module_param == 'purchases') ? 'active' : ''; ?>">
                    <a class="nav-link dropdown-toggle" href="#" id="purchasesDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Purchases
                    </a>
                    <div class="dropdown-menu" aria-labelledby="purchasesDropdown">
                        <a class="dropdown-item" href="<?php echo $base_url; ?>?module=purchases&action=index">Manage Purchases</a>
                        <a class="dropdown-item" href="<?php echo $base_url; ?>?module=purchases&action=create_po">New Purchase Order</a>
                        <a class="dropdown-item" href="<?php echo $base_url; ?>?module=purchases&action=purchase_return">Purchase Returns</a>
                    </div>
                </li>
                 <li class="nav-item dropdown <?php echo ($module_param == 'accounts') ? 'active' : ''; ?>">
                    <a class="nav-link dropdown-toggle" href="#" id="accountsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Accounts
                    </a>
                    <div class="dropdown-menu" aria-labelledby="accountsDropdown">
                        <a class="dropdown-item" href="<?php echo $base_url; ?>?module=accounts&action=expenses">Expenses</a>
                        <a class="dropdown-item" href="<?php echo $base_url; ?>?module=accounts&action=expense_categories">Expense Categories</a>
                        <!-- <a class="dropdown-item" href="<?php echo $base_url; ?>?module=reports&action=cash_flow">Cash Flow</a> -->
                    </div>
                </li>
                <li class="nav-item dropdown <?php echo ($module_param == 'reports') ? 'active' : ''; ?>">
                    <a class="nav-link dropdown-toggle" href="#" id="reportsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Reports
                    </a>
                    <div class="dropdown-menu" aria-labelledby="reportsDropdown">
                        <a class="dropdown-item" href="<?php echo $base_url; ?>?module=reports&action=sales">Sales Report</a>
                        <a class="dropdown-item" href="<?php echo $base_url; ?>?module=reports&action=inventory">Inventory Report</a>
                         <a class="dropdown-item" href="<?php echo $base_url; ?>?module=reports&action=purchases">Purchases Report</a>
                        <a class="dropdown-item" href="<?php echo $base_url; ?>?module=reports&action=cash_flow">Cash Flow Report</a>
                    </div>
                </li>
                <li class="nav-item dropdown <?php echo ($module_param == 'users' && $action != 'login' && $action != 'logout') ? 'active' : ''; ?>">
                    <a class="nav-link dropdown-toggle" href="#" id="usersDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Users
                    </a>
                    <div class="dropdown-menu" aria-labelledby="usersDropdown">
                        <a class="dropdown-item" href="<?php echo $base_url; ?>?module=users&action=index">Manage Users</a>
                        <a class="dropdown-item" href="<?php echo $base_url; ?>?module=users&action=create">Add User</a>
                    </div>
                </li>
                <li class="nav-item <?php echo ($module_param == 'settings') ? 'active' : ''; ?>">
                    <a class="nav-link" href="<?php echo $base_url; ?>?module=settings&action=index">Settings</a>
                </li>
                <?php endif; ?>

            <?php endif; ?>
        </ul>
        <ul class="navbar-nav ml-auto">
            <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item">
                    <span class="navbar-text mr-3">
                        Welcome, <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?>!
                        (<?php echo isset($_SESSION['user_role']) ? htmlspecialchars(ucfirst($_SESSION['user_role'])) : ''; ?>)
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>?page=logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </li>
            <?php else: ?>
                <li class="nav-item <?php echo ($page_param == 'login') ? 'active' : ''; ?>">
                    <a class="nav-link" href="<?php echo $base_url; ?>?page=login"><i class="fas fa-sign-in-alt"></i> Login</a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<main role="main" class="container-fluid mt-4"> <!-- Changed to container-fluid for wider content area -->
    <!-- Content will be loaded here by index.php -->
    <?php
    // Display session messages (e.g., success or error messages after an action)
    if (isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
        echo '<div class="alert alert-' . $_SESSION['message_type'] . ' alert-dismissible fade show" role="alert">';
        echo $_SESSION['message'];
        echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
        echo '</div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>
