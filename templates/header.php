<?php
// This ensures auth functions are available if not already included.
// It's good practice if header is included in files that might not have explicitly included auth.php.
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // auth.php also starts session, but good to be sure.
}
require_once __DIR__ . '/../includes/auth.php'; // For is_logged_in(), is_admin() etc.

// Define base path - this should ideally be a global constant defined in a central config file (e.g. db_connect.php or a new config.php)
// For now, defining it here for template context.
// Example: If your app is http://localhost/mypos/, $app_base_path = '/mypos/';
// If your app is http://localhost/, $app_base_path = '/';
$app_base_path = '/'; // IMPORTANT: Adjust this to your application's base path relative to the web server root.

// Function to make constructing URLs easier, respecting the base path and clean URL structure (trailing slash)
function site_url($path = '', $base_path_var = '/') {
    $url = rtrim($base_path_var, '/'); // Remove trailing slash from base path if present
    if (!empty($path)) {
        $url .= '/' . ltrim($path, '/'); // Add path, ensuring single slash
        if (substr($url, -1) !== '/' && strpos(basename($url), '.') === false) { // Add trailing slash if not present and not a file
            $url .= '/';
        }
    } else {
         $url .= '/'; // For base path itself, ensure trailing slash
    }
    return htmlspecialchars($url); // Sanitize for HTML output
}

$current_page_title = isset($page_title) ? htmlspecialchars($page_title) . " - POS System" : "POS System";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title><?php echo $current_page_title; ?></title>
    <!-- Bootstrap CSS CDN -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
    <!-- Font Awesome CDN (for icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" integrity="sha512-+4zCK9k+qNFUR5X+cKL9EIR+ZOhtIloNl9GIKS57V1MyNsYpYcUrUeQc9vNfzsWfV28IaLL3i96P9sdNyeRssA==" crossorigin="anonymous" />
    <!-- Custom Styles (optional - create this file if needed) -->
    <link rel="stylesheet" href="<?php echo site_url('assets/css/style.css', $app_base_path); ?>">
    <style>
        body { padding-top: 56px; /* Adjust if navbar height changes */ } /* For fixed navbar */
        .main-content { padding: 20px; }
        .footer { background-color: #f8f9fa; padding: 20px 0; text-align: center; margin-top: auto; }
        /* Add more global styles here or in style.css */
    </style>
</head>
<body class="d-flex flex-column min-vh-100"> <?php // Flex classes for sticky footer ?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <a class="navbar-brand" href="<?php echo site_url('', $app_base_path); ?>">
        <i class="fas fa-cash-register"></i> <!-- Icon for POS -->
        <?php
            // Display store name from settings if available
            // This requires $mysqli to be available if not already connected.
            // For simplicity in a header, it's better if settings are loaded once and stored in session or a global var.
            // For now, let's assume a default or skip direct DB query in header.
            echo isset($_SESSION['store_name']) ? htmlspecialchars($_SESSION['store_name']) : 'POS System';
        ?>
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavDropdown">
        <ul class="navbar-nav mr-auto">
            <?php if (is_logged_in()): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo site_url('pos', $app_base_path); ?>"><i class="fas fa-th-large"></i> POS</a>
                </li>
                <?php if (is_admin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo site_url('admin/dashboard', $app_base_path); ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminMenuInventory" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-boxes"></i> Inventory
                        </a>
                        <div class="dropdown-menu" aria-labelledby="adminMenuInventory">
                            <a class="dropdown-item" href="<?php echo site_url('admin/products', $app_base_path); ?>">Products</a>
                            <a class="dropdown-item" href="<?php echo site_url('admin/categories', $app_base_path); ?>">Categories</a>
                            <a class="dropdown-item" href="<?php echo site_url('admin/suppliers', $app_base_path); ?>">Suppliers</a>
                            <a class="dropdown-item" href="<?php echo site_url('admin/stock-adjustments', $app_base_path); ?>">Stock Adjustments</a>
                            <a class="dropdown-item" href="<?php echo site_url('admin/reorder-alerts', $app_base_path); ?>">Reorder Alerts</a>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminMenuPurchases" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-truck-loading"></i> Purchases
                        </a>
                        <div class="dropdown-menu" aria-labelledby="adminMenuPurchases">
                            <a class="dropdown-item" href="<?php echo site_url('admin/purchase-orders', $app_base_path); ?>">Purchase Orders</a>
                            <a class="dropdown-item" href="<?php echo site_url('admin/purchase-returns', $app_base_path); ?>">Purchase Returns</a>
                        </div>
                    </li>
                     <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminMenuSales" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-chart-line"></i> Sales
                        </a>
                        <div class="dropdown-menu" aria-labelledby="adminMenuSales">
                            <a class="dropdown-item" href="<?php echo site_url('admin/sales-history', $app_base_path); ?>">Sales History</a>
                            <a class="dropdown-item" href="<?php echo site_url('admin/sales-returns', $app_base_path); ?>">Sales Returns</a>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminMenuAccounts" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-file-invoice-dollar"></i> Accounts
                        </a>
                        <div class="dropdown-menu" aria-labelledby="adminMenuAccounts">
                            <a class="dropdown-item" href="<?php echo site_url('admin/expenses', $app_base_path); ?>">Expenses</a>
                            <a class="dropdown-item" href="<?php echo site_url('admin/expense-categories', $app_base_path); ?>">Expense Categories</a>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminMenuReports" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-chart-pie"></i> Reports
                        </a>
                        <div class="dropdown-menu" aria-labelledby="adminMenuReports">
                            <a class="dropdown-item" href="<?php echo site_url('admin/reports/sales', $app_base_path); ?>">Sales Reports</a>
                            <a class="dropdown-item" href="<?php echo site_url('admin/reports/inventory', $app_base_path); ?>">Inventory Reports</a>
                            <a class="dropdown-item" href="<?php echo site_url('admin/reports/financial', $app_base_path); ?>">Financial Reports</a>
                        </div>
                    </li>
                <?php endif; // end is_admin ?>
            <?php endif; // end is_logged_in ?>
        </ul>
        <ul class="navbar-nav ml-auto">
            <?php if (is_logged_in()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars(get_current_user_username()); ?> (<?php echo htmlspecialchars(get_current_user_role()); ?>)
                    </a>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                        <!-- <a class="dropdown-item" href="#">Profile</a> -->
                        <?php if (is_admin()): ?>
                            <a class="dropdown-item" href="<?php echo site_url('admin/users', $app_base_path); ?>"><i class="fas fa-users-cog"></i> User Management</a>
                            <a class="dropdown-item" href="<?php echo site_url('admin/settings', $app_base_path); ?>"><i class="fas fa-cogs"></i> Settings</a>
                            <div class="dropdown-divider"></div>
                        <?php endif; ?>
                        <a class="dropdown-item" href="<?php echo site_url('logout', $app_base_path); ?>"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo site_url('login', $app_base_path); ?>"><i class="fas fa-sign-in-alt"></i> Login</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo site_url('register', $app_base_path); ?>"><i class="fas fa-user-plus"></i> Register</a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<main role="main" class="container-fluid main-content">
    <?php // Main page content will be included here by the specific page ?>
    <?php
    // Display session messages (e.g., success or error after an action)
    // To use: $_SESSION['flash_message'] = "Success!"; $_SESSION['flash_message_type'] = "success"; // (or danger, warning, info)
    if (isset($_SESSION['flash_message'])):
    ?>
        <div class="alert alert-<?php echo htmlspecialchars($_SESSION['flash_message_type']); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_message_type']);
    endif;
    ?>
