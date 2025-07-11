<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
require_once BASE_PATH . '/config/db.php'; // For $conn

if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Security check: Only admins can access financial reports
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Access denied. You must be an admin to view financial reports.";
    $_SESSION['message_type'] = "danger";
    // Standard redirect
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
$base_module_url .= $script_dir_path . "/index.php?module=reports&action=financial";

$report_type = $_GET['type'] ?? 'expense_by_category'; // expense_by_category, profit_loss_summary (or sales_vs_purchases)

// Common date filtering
$filter_period = $_GET['period'] ?? 'monthly';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$category_filter_exp = isset($_GET['expense_category_id']) ? (int)$_GET['expense_category_id'] : '';


$sql_where_conditions = [];
$date_field_to_filter = "expense_date"; // Default for expenses

// Date range SQL
$date_range_sql = "";
// This date logic is repeated; consider moving to a helper function if more reports use it.
switch ($filter_period) {
    case 'daily':
        $current_date_sql = date('Y-m-d');
        $date_range_sql = "DATE({OBIY}) = '" . $current_date_sql . "'";
        break;
    case 'weekly':
        $start_of_week_sql = date('Y-m-d', strtotime('monday this week'));
        $end_of_week_sql = date('Y-m-d', strtotime('sunday this week'));
        $date_range_sql = "DATE({OBIY}) BETWEEN '" . $start_of_week_sql . "' AND '" . $end_of_week_sql . "'";
        break;
    case 'monthly':
        $start_of_month_sql = date('Y-m-01');
        $end_of_month_sql = date('Y-m-t');
        $date_range_sql = "DATE({OBIY}) BETWEEN '" . $start_of_month_sql . "' AND '" . $end_of_month_sql . "'";
        break;
    case 'custom':
        if (!empty($start_date) && !empty($end_date)) {
            $s_date_sql = escape_string($conn, $start_date);
            $e_date_sql = escape_string($conn, $end_date);
            $date_range_sql = "DATE({OBIY}) BETWEEN '" . $s_date_sql . "' AND '" . $e_date_sql . "'";
        } else {
            $current_date_sql = date('Y-m-d');
            $date_range_sql = "DATE({OBIY}) = '" . $current_date_sql . "'";
            $filter_period = 'daily';
        }
        break;
    default:
        $current_date_sql = date('Y-m-d');
        $date_range_sql = "DATE({OBIY}) = '" . $current_date_sql . "'";
        $filter_period = 'daily';
}


$report_data = [];
$report_title = "";
$expense_categories_for_filter = [];

// Fetch expense categories for filter dropdown
$sql_exp_cat_list = "SELECT id, name FROM expense_categories ORDER BY name ASC";
$res_exp_cat_list = mysqli_query($conn, $sql_exp_cat_list);
if($res_exp_cat_list) while($ecrow = mysqli_fetch_assoc($res_exp_cat_list)) $expense_categories_for_filter[] = $ecrow;
if($res_exp_cat_list) mysqli_free_result($res_exp_cat_list);


if ($report_type === 'expense_by_category') {
    $report_title = "Expense Report by Category";
    $current_date_field = "e.expense_date"; // Alias for expenses table
    $current_date_range_sql = str_replace("{OBIY}", $current_date_field, $date_range_sql);

    $sql_where_conditions_exp = [];
    if(!empty($current_date_range_sql)) $sql_where_conditions_exp[] = $current_date_range_sql;
    if (!empty($category_filter_exp)) {
        $sql_where_conditions_exp[] = "e.expense_category_id = " . (int)$category_filter_exp;
    }

    $sql_summary = "SELECT ec.name as category_name,
                           COUNT(e.id) as number_of_expenses,
                           SUM(e.amount) as total_amount_spent
                    FROM expenses e
                    JOIN expense_categories ec ON e.expense_category_id = ec.id ";
    if (!empty($sql_where_conditions_exp)) {
        $sql_summary .= " WHERE " . implode(" AND ", $sql_where_conditions_exp);
    }
    $sql_summary .= " GROUP BY e.expense_category_id, ec.name ORDER BY total_amount_spent DESC";

    $res_summary = mysqli_query($conn, $sql_summary);
    if ($res_summary) {
        while ($row = mysqli_fetch_assoc($res_summary)) $report_data[] = $row;
        mysqli_free_result($res_summary);
    } else {
        $_SESSION['message'] = "Error generating expense report: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    require_once BASE_PATH . '/templates/reports/expense_by_category_view.php';

} elseif ($report_type === 'sales_vs_purchases_summary') {
    $report_title = "Summary: Sales vs Purchases vs Expenses";

    // Sales
    $sales_date_field = "s.sale_date";
    $sales_date_range_sql = str_replace("{OBIY}", $sales_date_field, $date_range_sql);
    $sql_total_sales = "SELECT SUM(grand_total) as total_sales_value FROM sales s WHERE s.status = 'completed' AND {$sales_date_range_sql}";
    $res_sales = mysqli_query($conn, $sql_total_sales);
    $total_sales = ($res_sales && mysqli_num_rows($res_sales) > 0) ? (float)mysqli_fetch_assoc($res_sales)['total_sales_value'] : 0;
    if($res_sales) mysqli_free_result($res_sales);

    // Purchases (Cost of Goods - approximation using purchase price of items sold, or total purchases value)
    // For simplicity, using total value of purchases made/received in period
    $purch_date_field = "p.purchase_date"; // Or received_date
    $purch_date_range_sql = str_replace("{OBIY}", $purch_date_field, $date_range_sql);
    $sql_total_purchases = "SELECT SUM(total_amount) as total_purchase_value FROM purchases p WHERE p.status IN ('received', 'partially_received', 'ordered') AND {$purch_date_range_sql}";
    $res_purch = mysqli_query($conn, $sql_total_purchases);
    $total_purchases = ($res_purch && mysqli_num_rows($res_purch) > 0) ? (float)mysqli_fetch_assoc($res_purch)['total_purchase_value'] : 0;
    if($res_purch) mysqli_free_result($res_purch);

    // Expenses
    $exp_date_field = "e.expense_date";
    $exp_date_range_sql = str_replace("{OBIY}", $exp_date_field, $date_range_sql);
    $sql_total_expenses = "SELECT SUM(amount) as total_expense_value FROM expenses e WHERE {$exp_date_range_sql}";
    $res_exp = mysqli_query($conn, $sql_total_expenses);
    $total_expenses = ($res_exp && mysqli_num_rows($res_exp) > 0) ? (float)mysqli_fetch_assoc($res_exp)['total_expense_value'] : 0;
    if($res_exp) mysqli_free_result($res_exp);

    // A true Profit & Loss would require COGS calculation (Cost Of Goods Sold)
    // COGS = Opening Stock Value + Purchases Value - Closing Stock Value
    // Or, for each sale item, (purchase_price_at_time_of_sale * quantity_sold)
    // This is a simplified summary.
    $report_data = [
        'total_sales' => $total_sales,
        'total_purchases' => $total_purchases, // This is total purchase orders value, not COGS
        'total_expenses' => $total_expenses,
        // 'gross_profit_estimate' => $total_sales - $total_purchases, // Highly inaccurate as 'profit'
        // 'net_profit_estimate' => $total_sales - $total_purchases - $total_expenses // Highly inaccurate
    ];
    require_once BASE_PATH . '/templates/reports/sales_vs_purchases_summary_view.php';

} else {
    $_SESSION['message'] = "Unknown financial report type.";
    $_SESSION['message_type'] = "info";
    header("Location: " . $base_module_url . "&type=expense_by_category");
    exit;
}

// mysqli_close($conn); // Closed by index.php
?>
