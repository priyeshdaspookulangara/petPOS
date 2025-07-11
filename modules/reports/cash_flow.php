<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
require_once BASE_PATH . '/config/db.php'; // For $conn

if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Security check: Only admins can access reports
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Access denied. You must be an admin to view reports.";
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
$base_module_url .= $script_dir_path . "/index.php?module=reports&action=cash_flow";


// Date filtering
$filter_period = $_GET['period'] ?? 'daily'; // daily, weekly, monthly, custom
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$sql_where_clause_sales = " WHERE status = 'completed' "; // Only completed sales for inflows
$sql_where_clause_purchases = " WHERE status IN ('received', 'partially_received') "; // Only count received purchases as outflow
$sql_where_clause_expenses = " WHERE 1=1 ";


// Setup date ranges based on period
switch ($filter_period) {
    case 'daily':
        $current_date = date('Y-m-d');
        $sql_where_clause_sales .= " AND DATE(sale_date) = '" . $current_date . "'";
        $sql_where_clause_purchases .= " AND DATE(purchase_date) = '" . $current_date . "'"; // or received_date? For cash flow, payment date is better but not tracked in detail. Using purchase_date for now.
        $sql_where_clause_expenses .= " AND DATE(expense_date) = '" . $current_date . "'";
        break;
    case 'weekly':
        $start_of_week = date('Y-m-d', strtotime('monday this week'));
        $end_of_week = date('Y-m-d', strtotime('sunday this week'));
        $sql_where_clause_sales .= " AND DATE(sale_date) BETWEEN '" . $start_of_week . "' AND '" . $end_of_week . "'";
        $sql_where_clause_purchases .= " AND DATE(purchase_date) BETWEEN '" . $start_of_week . "' AND '" . $end_of_week . "'";
        $sql_where_clause_expenses .= " AND DATE(expense_date) BETWEEN '" . $start_of_week . "' AND '" . $end_of_week . "'";
        break;
    case 'monthly':
        $start_of_month = date('Y-m-01');
        $end_of_month = date('Y-m-t');
        $sql_where_clause_sales .= " AND DATE(sale_date) BETWEEN '" . $start_of_month . "' AND '" . $end_of_month . "'";
        $sql_where_clause_purchases .= " AND DATE(purchase_date) BETWEEN '" . $start_of_month . "' AND '" . $end_of_month . "'";
        $sql_where_clause_expenses .= " AND DATE(expense_date) BETWEEN '" . $start_of_month . "' AND '" . $end_of_month . "'";
        break;
    case 'custom':
        if (!empty($start_date) && !empty($end_date)) {
            $s_date = escape_string($conn, $start_date);
            $e_date = escape_string($conn, $end_date);
            $sql_where_clause_sales .= " AND DATE(sale_date) BETWEEN '" . $s_date . "' AND '" . $e_date . "'";
            $sql_where_clause_purchases .= " AND DATE(purchase_date) BETWEEN '" . $s_date . "' AND '" . $e_date . "'";
            $sql_where_clause_expenses .= " AND DATE(expense_date) BETWEEN '" . $s_date . "' AND '" . $e_date . "'";
        } else {
            // Default to daily if custom dates are not provided
            $current_date = date('Y-m-d');
            $sql_where_clause_sales .= " AND DATE(sale_date) = '" . $current_date . "'";
            $sql_where_clause_purchases .= " AND DATE(purchase_date) = '" . $current_date . "'";
            $sql_where_clause_expenses .= " AND DATE(expense_date) = '" . $current_date . "'";
            $filter_period = 'daily'; // Reset period for display
        }
        break;
}


// Calculate Inflows (Sales)
$total_inflows = 0;
$sql_inflows = "SELECT SUM(grand_total) as total_sales FROM sales " . $sql_where_clause_sales;
$res_inflows = mysqli_query($conn, $sql_inflows);
if ($res_inflows && mysqli_num_rows($res_inflows) > 0) {
    $total_inflows = (float)mysqli_fetch_assoc($res_inflows)['total_sales'];
    mysqli_free_result($res_inflows);
}

// Calculate Outflows (Purchases - using amount_paid for cash flow)
$total_purchase_outflows = 0;
// Using total_amount for now, as amount_paid might not be fully implemented for partial payments yet.
// A more accurate cash flow would sum actual payments made on purchases.
// For simplicity, assuming total_amount of received purchases is the outflow for that period.
$sql_purch_outflows = "SELECT SUM(total_amount) as total_purchases FROM purchases " . $sql_where_clause_purchases;
$res_purch_outflows = mysqli_query($conn, $sql_purch_outflows);
if ($res_purch_outflows && mysqli_num_rows($res_purch_outflows) > 0) {
    $total_purchase_outflows = (float)mysqli_fetch_assoc($res_purch_outflows)['total_purchases'];
    mysqli_free_result($res_purch_outflows);
}

// Calculate Outflows (Expenses)
$total_expense_outflows = 0;
$sql_exp_outflows = "SELECT SUM(amount) as total_expenses FROM expenses " . $sql_where_clause_expenses;
$res_exp_outflows = mysqli_query($conn, $sql_exp_outflows);
if ($res_exp_outflows && mysqli_num_rows($res_exp_outflows) > 0) {
    $total_expense_outflows = (float)mysqli_fetch_assoc($res_exp_outflows)['total_expenses'];
    mysqli_free_result($res_exp_outflows);
}

$total_outflows = $total_purchase_outflows + $total_expense_outflows;
$net_cash_flow = $total_inflows - $total_outflows;

$cash_flow_data = [
    'total_inflows' => $total_inflows,
    'total_purchase_outflows' => $total_purchase_outflows,
    'total_expense_outflows' => $total_expense_outflows,
    'total_outflows' => $total_outflows,
    'net_cash_flow' => $net_cash_flow,
    'filter_period' => $filter_period,
    'start_date' => $start_date, // For custom range display
    'end_date' => $end_date     // For custom range display
];

// Fetch detailed transactions for the period (optional, for display in the report)
$detailed_transactions = [
    'sales' => [],
    'purchases' => [],
    'expenses' => []
];

$sql_detailed_sales = "SELECT receipt_no, sale_date, grand_total FROM sales " . $sql_where_clause_sales . " ORDER BY sale_date DESC";
$res_det_sales = mysqli_query($conn, $sql_detailed_sales);
if($res_det_sales) while($row = mysqli_fetch_assoc($res_det_sales)) $detailed_transactions['sales'][] = $row;
if($res_det_sales) mysqli_free_result($res_det_sales);

$sql_detailed_purchases = "SELECT po_number, purchase_date, total_amount FROM purchases " . $sql_where_clause_purchases . " ORDER BY purchase_date DESC";
$res_det_purch = mysqli_query($conn, $sql_detailed_purchases);
if($res_det_purch) while($row = mysqli_fetch_assoc($res_det_purch)) $detailed_transactions['purchases'][] = $row;
if($res_det_purch) mysqli_free_result($res_det_purch);

$sql_detailed_expenses = "SELECT description, expense_date, amount, ec.name as category_name
                          FROM expenses e JOIN expense_categories ec ON e.expense_category_id = ec.id "
                          . $sql_where_clause_expenses . " ORDER BY expense_date DESC";
$res_det_exp = mysqli_query($conn, $sql_detailed_expenses);
if($res_det_exp) while($row = mysqli_fetch_assoc($res_det_exp)) $detailed_transactions['expenses'][] = $row;
if($res_det_exp) mysqli_free_result($res_det_exp);


// Include the view template
require_once BASE_PATH . '/templates/reports/cash_flow_view.php';

// mysqli_close($conn); // Closed by index.php
?>
