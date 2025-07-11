<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
require_once BASE_PATH . '/config/db.php'; // For $conn

if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Security check: Only admins can access reports (cashiers might access some basic sales reports - adjust as needed)
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'cashier'])) { // Allowing cashier for this one
    $_SESSION['message'] = "Access denied. You do not have permission to view this report.";
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
$base_module_url .= $script_dir_path . "/index.php?module=reports&action=sales";

$report_type = $_GET['type'] ?? 'summary_by_date'; // summary_by_date, by_product, by_category, by_user, top_selling, sales_return

// Common date filtering for most sales reports
$filter_period = $_GET['period'] ?? 'daily'; // daily, weekly, monthly, custom
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$user_filter = isset($_GET['user_id']) && $_SESSION['user_role'] === 'admin' ? (int)$_GET['user_id'] : ($_SESSION['user_role'] === 'cashier' ? $_SESSION['user_id'] : ''); // Cashiers see their own unless admin
$product_filter = isset($_GET['product_id']) ? (int)$_GET['product_id'] : '';
$category_filter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : '';


$sql_where_conditions = ["s.status = 'completed'"]; // Base condition for completed sales

// Date range SQL
$date_range_sql = "";
switch ($filter_period) {
    case 'daily':
        $current_date_for_sql = date('Y-m-d');
        $date_range_sql = "DATE(s.sale_date) = '" . $current_date_for_sql . "'";
        break;
    case 'weekly':
        $start_of_week_sql = date('Y-m-d', strtotime('monday this week'));
        $end_of_week_sql = date('Y-m-d', strtotime('sunday this week'));
        $date_range_sql = "DATE(s.sale_date) BETWEEN '" . $start_of_week_sql . "' AND '" . $end_of_week_sql . "'";
        break;
    case 'monthly':
        $start_of_month_sql = date('Y-m-01');
        $end_of_month_sql = date('Y-m-t');
        $date_range_sql = "DATE(s.sale_date) BETWEEN '" . $start_of_month_sql . "' AND '" . $end_of_month_sql . "'";
        break;
    case 'custom':
        if (!empty($start_date) && !empty($end_date)) {
            $s_date_sql = escape_string($conn, $start_date);
            $e_date_sql = escape_string($conn, $end_date);
            $date_range_sql = "DATE(s.sale_date) BETWEEN '" . $s_date_sql . "' AND '" . $e_date_sql . "'";
        } else { // Default if custom but no dates
            $current_date_for_sql = date('Y-m-d');
            $date_range_sql = "DATE(s.sale_date) = '" . $current_date_for_sql . "'";
            $filter_period = 'daily';
        }
        break;
    default: // Default to daily
        $current_date_for_sql = date('Y-m-d');
        $date_range_sql = "DATE(s.sale_date) = '" . $current_date_for_sql . "'";
        $filter_period = 'daily';
}
if (!empty($date_range_sql)) {
    $sql_where_conditions[] = $date_range_sql;
}

// User filter (if admin is viewing, or cashier for their own sales)
if (!empty($user_filter)) {
    $sql_where_conditions[] = "s.user_id = " . (int)$user_filter;
}

// Product filter for 'by_product' report
if ($report_type === 'by_product' && !empty($product_filter)) {
    $sql_where_conditions[] = "si.product_id = " . (int)$product_filter;
}

// Category filter for 'by_category' report
if ($report_type === 'by_category' && !empty($category_filter)) {
    // This requires joining products and categories table in the main query
    // Handled within specific report logic if needed by joins.
}


$report_data = [];
$report_title = "";


// --- Report Specific Logic ---
if ($report_type === 'summary_by_date') {
    $report_title = "Sales Summary by Date";
    // This report typically groups sales by day, sums totals.
    $sql_summary = "SELECT DATE(s.sale_date) as sale_day,
                           COUNT(s.id) as number_of_sales,
                           SUM(s.sub_total) as total_sub_total,
                           SUM(s.discount_amount) as total_discount,
                           SUM(s.tax_amount) as total_tax,
                           SUM(s.grand_total) as total_grand_total
                    FROM sales s ";
    if (!empty($sql_where_conditions)) {
        $sql_summary .= " WHERE " . implode(" AND ", $sql_where_conditions);
    }
    $sql_summary .= " GROUP BY DATE(s.sale_date) ORDER BY DATE(s.sale_date) DESC";

    $res_summary = mysqli_query($conn, $sql_summary);
    if ($res_summary) {
        while ($row = mysqli_fetch_assoc($res_summary)) $report_data[] = $row;
        mysqli_free_result($res_summary);
    } else {
        $_SESSION['message'] = "Error generating sales summary: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    require_once BASE_PATH . '/templates/reports/sales_summary_by_date_view.php';

} elseif ($report_type === 'by_product') {
    $report_title = "Sales by Product";
     $sql_by_product = "SELECT p.name as product_name, p.sku as product_sku,
                              SUM(si.quantity) as total_quantity_sold,
                              SUM(si.line_total) as total_revenue_from_product,
                              AVG(si.price_per_item_after_discount) as avg_selling_price
                       FROM sale_items si
                       JOIN sales s ON si.sale_id = s.id
                       JOIN products p ON si.product_id = p.id ";
    if (!empty($sql_where_conditions)) {
        $sql_by_product .= " WHERE " . implode(" AND ", $sql_where_conditions);
    }
    // Add product_filter if specifically set (already part of $sql_where_conditions if product_filter is set)
    $sql_by_product .= " GROUP BY si.product_id, p.name, p.sku ORDER BY total_revenue_from_product DESC";

    $res_by_product = mysqli_query($conn, $sql_by_product);
    if ($res_by_product) {
        while ($row = mysqli_fetch_assoc($res_by_product)) $report_data[] = $row;
        mysqli_free_result($res_by_product);
    } else {
        $_SESSION['message'] = "Error generating sales by product report: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    // Fetch products for filter dropdown
    $products_for_filter = [];
    $sql_prod_filter_list = "SELECT id, name, sku FROM products ORDER BY name ASC";
    $res_prod_filter_list = mysqli_query($conn, $sql_prod_filter_list);
    if($res_prod_filter_list) while($prow = mysqli_fetch_assoc($res_prod_filter_list)) $products_for_filter[] = $prow;
    if($res_prod_filter_list) mysqli_free_result($res_prod_filter_list);

    require_once BASE_PATH . '/templates/reports/sales_by_product_view.php';

} elseif ($report_type === 'by_category') {
    $report_title = "Sales by Category";
    $sql_by_category = "SELECT c.name as category_name,
                               SUM(si.quantity) as total_quantity_sold,
                               SUM(si.line_total) as total_revenue_from_category
                        FROM sale_items si
                        JOIN sales s ON si.sale_id = s.id
                        JOIN products p ON si.product_id = p.id
                        JOIN categories c ON p.category_id = c.id ";

    $category_where_conditions = $sql_where_conditions; // Copy base conditions
    if (!empty($category_filter)) {
        $category_where_conditions[] = "p.category_id = " . (int)$category_filter;
    }
    if (!empty($category_where_conditions)) {
        $sql_by_category .= " WHERE " . implode(" AND ", $category_where_conditions);
    }
    $sql_by_category .= " GROUP BY p.category_id, c.name ORDER BY total_revenue_from_category DESC";

    $res_by_category = mysqli_query($conn, $sql_by_category);
    if ($res_by_category) {
        while ($row = mysqli_fetch_assoc($res_by_category)) $report_data[] = $row;
        mysqli_free_result($res_by_category);
    } else {
        $_SESSION['message'] = "Error generating sales by category report: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    // Fetch categories for filter dropdown
    $categories_for_filter = [];
    $sql_cat_filter_list = "SELECT id, name FROM categories ORDER BY name ASC";
    $res_cat_filter_list = mysqli_query($conn, $sql_cat_filter_list);
    if($res_cat_filter_list) while($crow = mysqli_fetch_assoc($res_cat_filter_list)) $categories_for_filter[] = $crow;
    if($res_cat_filter_list) mysqli_free_result($res_cat_filter_list);

    require_once BASE_PATH . '/templates/reports/sales_by_category_view.php';

} elseif ($report_type === 'by_user') {
    $report_title = "Sales by User (Cashier)";
    // Admin can see all users, cashier only their own (handled by $user_filter in $sql_where_conditions)
     $sql_by_user = "SELECT u.username as cashier_name, u.id as user_id,
                           COUNT(s.id) as number_of_sales,
                           SUM(s.grand_total) as total_sales_value
                    FROM sales s
                    JOIN users u ON s.user_id = u.id ";
    if (!empty($sql_where_conditions)) {
        $sql_by_user .= " WHERE " . implode(" AND ", $sql_where_conditions);
    }
    $sql_by_user .= " GROUP BY s.user_id, u.username ORDER BY total_sales_value DESC";

    $res_by_user = mysqli_query($conn, $sql_by_user);
    if ($res_by_user) {
        while ($row = mysqli_fetch_assoc($res_by_user)) $report_data[] = $row;
        mysqli_free_result($res_by_user);
    } else {
        $_SESSION['message'] = "Error generating sales by user report: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    // Fetch users for filter dropdown (if admin)
    $users_for_filter = [];
    if ($_SESSION['user_role'] === 'admin') {
        $sql_user_filter_list = "SELECT id, username FROM users WHERE role IN ('admin', 'cashier') ORDER BY username ASC";
        $res_user_filter_list = mysqli_query($conn, $sql_user_filter_list);
        if($res_user_filter_list) while($urow = mysqli_fetch_assoc($res_user_filter_list)) $users_for_filter[] = $urow;
        if($res_user_filter_list) mysqli_free_result($res_user_filter_list);
    }
    require_once BASE_PATH . '/templates/reports/sales_by_user_view.php';

} elseif ($report_type === 'top_selling') {
    $report_title = "Top Selling Products";
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; // Default top 10
    if ($limit <=0) $limit = 10;

    $sql_top_selling = "SELECT p.name as product_name, p.sku as product_sku,
                               SUM(si.quantity) as total_quantity_sold,
                               SUM(si.line_total) as total_revenue
                        FROM sale_items si
                        JOIN sales s ON si.sale_id = s.id
                        JOIN products p ON si.product_id = p.id ";
    if (!empty($sql_where_conditions)) {
        $sql_top_selling .= " WHERE " . implode(" AND ", $sql_where_conditions);
    }
    $sql_top_selling .= " GROUP BY si.product_id, p.name, p.sku
                          ORDER BY total_quantity_sold DESC, total_revenue DESC
                          LIMIT " . $limit;

    $res_top_selling = mysqli_query($conn, $sql_top_selling);
    if ($res_top_selling) {
        while ($row = mysqli_fetch_assoc($res_top_selling)) $report_data[] = $row;
        mysqli_free_result($res_top_selling);
    } else {
        $_SESSION['message'] = "Error generating top selling products report: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    require_once BASE_PATH . '/templates/reports/top_selling_products_view.php';

} elseif ($report_type === 'sales_return_summary') {
    $report_title = "Sales Return Report";
     $sql_returns = "SELECT sr.return_receipt_no, sr.return_date, s.receipt_no as original_sale_receipt,
                           u.username as processed_by, sr.total_refund_amount, sr.reason
                    FROM sales_returns sr
                    JOIN sales s ON sr.original_sale_id = s.id
                    JOIN users u ON sr.user_id = u.id ";

    // Modify date filter to use sr.return_date
    $return_date_conditions = [];
    if (!empty($date_range_sql)) { // Re-use date logic but apply to return_date
        $return_date_conditions[] = str_replace("s.sale_date", "sr.return_date", $date_range_sql);
    }
    if (!empty($user_filter)) { // Filter by user who processed the return
        $return_date_conditions[] = "sr.user_id = " . (int)$user_filter;
    }

    if (!empty($return_date_conditions)) {
        $sql_returns .= " WHERE " . implode(" AND ", $return_date_conditions);
    }
    $sql_returns .= " ORDER BY sr.return_date DESC";

    $res_returns = mysqli_query($conn, $sql_returns);
    if ($res_returns) {
        while ($row = mysqli_fetch_assoc($res_returns)) $report_data[] = $row;
        mysqli_free_result($res_returns);
    } else {
        $_SESSION['message'] = "Error generating sales return report: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    // Fetch users for filter dropdown (if admin)
    $users_for_filter = [];
    if ($_SESSION['user_role'] === 'admin') {
        $sql_user_filter_list = "SELECT id, username FROM users WHERE role IN ('admin', 'cashier') ORDER BY username ASC";
        $res_user_filter_list = mysqli_query($conn, $sql_user_filter_list);
        if($res_user_filter_list) while($urow = mysqli_fetch_assoc($res_user_filter_list)) $users_for_filter[] = $urow;
        if($res_user_filter_list) mysqli_free_result($res_user_filter_list);
    }
    require_once BASE_PATH . '/templates/reports/sales_return_report_view.php';

} else {
    // Fallback or default report if type is unknown (e.g. redirect to sales summary by date)
    $_SESSION['message'] = "Unknown sales report type. Showing summary by date.";
    $_SESSION['message_type'] = "info";
    // This will effectively re-run the 'summary_by_date' logic due to default $report_type
    // To avoid re-running, redirect:
    header("Location: " . $base_module_url . "&type=summary_by_date");
    exit;
}

// mysqli_close($conn); // Closed by index.php
?>
