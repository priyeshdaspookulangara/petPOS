<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
require_once BASE_PATH . '/config/db.php'; // For $conn

if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Security check: Only admins can access this page (adjust if other roles need access)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Access denied. You must be an admin to view stock levels.";
    $_SESSION['message_type'] = "danger";
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $app_path = dirname($_SERVER['SCRIPT_NAME']);
    if ($app_path === '/' || $app_path === '\\') $app_path = '';
    header("Location: " . $protocol . "://" . $host . $app_path . "/index.php?page=dashboard");
    exit;
}

$base_module_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$script_dir_path = dirname($_SERVER['SCRIPT_NAME']);
if ($script_dir_path === '/' || $script_dir_path === '\\') $script_dir_path = '';
$base_module_url .= $script_dir_path . "/index.php?module=inventory&action=stock_levels";


$products_stock = [];
// Fetch all products with their stock levels, category, and supplier for better context
$sql = "SELECT p.id, p.sku, p.name, p.current_stock, p.reorder_level, p.unit,
               c.name as category_name, s.name as supplier_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        ORDER BY p.name ASC";

$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $products_stock[] = $row;
    }
    mysqli_free_result($result);
} else {
    // Handle error, e.g., display a message in the template
    $_SESSION['message'] = "Error fetching stock levels: " . mysqli_error($conn);
    $_SESSION['message_type'] = "danger";
    // This message will be shown by header.php if not overridden locally
}

// Include the view template
require_once BASE_PATH . '/templates/inventory/stock_levels_view.php';

// mysqli_close($conn); // Closed by index.php
?>
