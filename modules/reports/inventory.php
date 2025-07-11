<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
require_once BASE_PATH . '/config/db.php'; // For $conn

if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Security check: Only admins can access inventory reports
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Access denied. You must be an admin to view inventory reports.";
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
$base_module_url .= $script_dir_path . "/index.php?module=reports&action=inventory";

$report_type = $_GET['type'] ?? 'stock_movement'; // stock_movement, (current_stock, low_stock are separate modules)

// Common date filtering for movement reports
$filter_period = $_GET['period'] ?? 'monthly';
$start_date_req = $_GET['start_date'] ?? ''; // Renamed to avoid conflict with global $start_date if any
$end_date_req = $_GET['end_date'] ?? '';     // Renamed
$product_id_filter = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;

$sql_date_condition = "";
// Setup date ranges based on period
switch ($filter_period) {
    case 'daily':
        $current_date_sql = date('Y-m-d');
        $sql_date_condition = " = '" . $current_date_sql . "'";
        $start_date = $current_date_sql; $end_date = $current_date_sql; // For display
        break;
    case 'weekly':
        $start_of_week_sql = date('Y-m-d', strtotime('monday this week'));
        $end_of_week_sql = date('Y-m-d', strtotime('sunday this week'));
        $sql_date_condition = " BETWEEN '" . $start_of_week_sql . "' AND '" . $end_of_week_sql . "'";
        $start_date = $start_of_week_sql; $end_date = $end_of_week_sql; // For display
        break;
    case 'monthly':
        $start_of_month_sql = date('Y-m-01');
        $end_of_month_sql = date('Y-m-t');
        $sql_date_condition = " BETWEEN '" . $start_of_month_sql . "' AND '" . $end_of_month_sql . "'";
        $start_date = $start_of_month_sql; $end_date = $end_of_month_sql; // For display
        break;
    case 'custom':
        if (!empty($start_date_req) && !empty($end_date_req)) {
            $s_date_sql = escape_string($conn, $start_date_req);
            $e_date_sql = escape_string($conn, $end_date_req);
            $sql_date_condition = " BETWEEN '" . $s_date_sql . "' AND '" . $e_date_sql . "'";
            $start_date = $start_date_req; $end_date = $end_date_req; // For display
        } else { // Default if custom but no dates
            $start_of_month_sql = date('Y-m-01');
            $end_of_month_sql = date('Y-m-t');
            $sql_date_condition = " BETWEEN '" . $start_of_month_sql . "' AND '" . $end_of_month_sql . "'";
            $filter_period = 'monthly';
            $start_date = $start_of_month_sql; $end_date = $end_of_month_sql;
        }
        break;
    default: // Default to monthly
        $start_of_month_sql = date('Y-m-01');
        $end_of_month_sql = date('Y-m-t');
        $sql_date_condition = " BETWEEN '" . $start_of_month_sql . "' AND '" . $end_of_month_sql . "'";
        $filter_period = 'monthly';
        $start_date = $start_of_month_sql; $end_date = $end_of_month_sql;
}


$report_data = [];
$report_title = "";
$products_for_filter = []; // For product dropdown

// Fetch products for filter dropdown
$sql_prod_list = "SELECT id, name, sku FROM products ORDER BY name ASC";
$res_prod_list = mysqli_query($conn, $sql_prod_list);
if($res_prod_list) while($prow = mysqli_fetch_assoc($res_prod_list)) $products_for_filter[] = $prow;
if($res_prod_list) mysqli_free_result($res_prod_list);


if ($report_type === 'stock_movement') {
    $report_title = "Stock Movement Report";

    $movements = [];
    $product_where_clause = $product_id_filter ? " AND p.id = " . $product_id_filter : "";

    // Sales (Stock Out)
    $sql_sales_mov = "SELECT s.sale_date as movement_date, 'Sale' as type, si.quantity as qty_out, 0 as qty_in,
                             p.id as product_id, p.name as product_name, p.sku as product_sku, s.receipt_no as reference,
                             NULL as balance_stock
                      FROM sale_items si
                      JOIN sales s ON si.sale_id = s.id
                      JOIN products p ON si.product_id = p.id
                      WHERE s.status = 'completed' AND DATE(s.sale_date) {$sql_date_condition} {$product_where_clause}";
    $res_sales_mov = mysqli_query($conn, $sql_sales_mov);
    if($res_sales_mov) while($row = mysqli_fetch_assoc($res_sales_mov)) $movements[] = $row;
    if($res_sales_mov) mysqli_free_result($res_sales_mov);

    $sql_purch_mov = "SELECT COALESCE(pu.received_date, pu.purchase_date) as movement_date, 'Purchase Receipt' as type, 0 as qty_out, pi.quantity_received as qty_in,
                             p.id as product_id, p.name as product_name, p.sku as product_sku, pu.po_number as reference,
                             NULL as balance_stock
                      FROM purchase_items pi
                      JOIN purchases pu ON pi.purchase_id = pu.id
                      JOIN products p ON pi.product_id = p.id
                      WHERE pi.quantity_received > 0 AND pu.status IN ('received', 'partially_received')
                            AND DATE(COALESCE(pu.received_date, pu.purchase_date)) {$sql_date_condition} {$product_where_clause}";
    $res_purch_mov = mysqli_query($conn, $sql_purch_mov);
    if($res_purch_mov) while($row = mysqli_fetch_assoc($res_purch_mov)) $movements[] = $row;
    if($res_purch_mov) mysqli_free_result($res_purch_mov);

    $sql_adj_mov = "SELECT sa.adjustment_date as movement_date,
                           CONCAT('Adjustment (', sa.type_of_adjustment, ')') as type,
                           IF(sa.quantity_changed < 0, ABS(sa.quantity_changed), 0) as qty_out,
                           IF(sa.quantity_changed > 0, sa.quantity_changed, 0) as qty_in,
                           p.id as product_id, p.name as product_name, p.sku as product_sku, CONCAT('Adj ID: ', sa.id) as reference,
                           sa.new_stock_level as balance_stock -- Stock adjustments table has the new stock level
                    FROM stock_adjustments sa
                    JOIN products p ON sa.product_id = p.id
                    WHERE DATE(sa.adjustment_date) {$sql_date_condition} {$product_where_clause}";
    $res_adj_mov = mysqli_query($conn, $sql_adj_mov);
    if($res_adj_mov) while($row = mysqli_fetch_assoc($res_adj_mov)) $movements[] = $row;
    if($res_adj_mov) mysqli_free_result($res_adj_mov);

    $sql_sret_mov = "SELECT sr.return_date as movement_date, 'Sales Return' as type, 0 as qty_out, sri.quantity_returned as qty_in,
                            p.id as product_id, p.name as product_name, p.sku as product_sku, sr.return_receipt_no as reference,
                            NULL as balance_stock
                     FROM sales_return_items sri
                     JOIN sales_returns sr ON sri.sales_return_id = sr.id
                     JOIN products p ON sri.product_id = p.id
                     WHERE DATE(sr.return_date) {$sql_date_condition} {$product_where_clause}";
    $res_sret_mov = mysqli_query($conn, $sql_sret_mov);
    if($res_sret_mov) while($row = mysqli_fetch_assoc($res_sret_mov)) $movements[] = $row;
    if($res_sret_mov) mysqli_free_result($res_sret_mov);

    $sql_pret_mov = "SELECT pr.return_date as movement_date, 'Purchase Return' as type, pri.quantity_returned as qty_out, 0 as qty_in,
                            p.id as product_id, p.name as product_name, p.sku as product_sku, pr.return_note_no as reference,
                            NULL as balance_stock
                     FROM purchase_return_items pri
                     JOIN purchase_returns pr ON pri.purchase_return_id = pr.id
                     JOIN products p ON pri.product_id = p.id
                     WHERE DATE(pr.return_date) {$sql_date_condition} {$product_where_clause}";
    $res_pret_mov = mysqli_query($conn, $sql_pret_mov);
    if($res_pret_mov) while($row = mysqli_fetch_assoc($res_pret_mov)) $movements[] = $row;
    if($res_pret_mov) mysqli_free_result($res_pret_mov);

    // Sort all movements by date and then by type (ins before outs for same timestamp if needed)
    usort($movements, function($a, $b) {
        $dateComparison = strtotime($a['movement_date']) - strtotime($b['movement_date']);
        if ($dateComparison == 0) {
            // Optional: define order for types if dates are identical (e.g. adjustments last)
            return 0; // Keep original order for same timestamp or define specific logic
        }
        return $dateComparison;
    });

    // If a specific product is filtered, calculate opening and closing stock
    $opening_stock = null;
    $closing_stock = null;
    $current_balance = 0;

    if ($product_id_filter && count($products_for_filter) > 0) {
        // Get stock at the beginning of the start_date
        // This is complex: sum all transactions before $start_date for that product.
        // Or, simpler: get current stock and work backwards from transactions in the period.
        // For now, let's get the stock right before the first movement in the period.
        // This is an approximation for this simplified report.

        $sql_current_stock_val = "SELECT current_stock FROM products WHERE id = " . $product_id_filter;
        $res_curr_stock_val = mysqli_query($conn, $sql_current_stock_val);
        if($res_curr_stock_val && mysqli_num_rows($res_curr_stock_val) > 0) {
            $closing_stock = (int)mysqli_fetch_assoc($res_curr_stock_val)['current_stock'];
            mysqli_free_result($res_curr_stock_val);

            // Calculate opening stock by reversing period movements from closing stock
            $net_change_in_period = 0;
            foreach ($movements as $mov) {
                $net_change_in_period += ($mov['qty_in'] - $mov['qty_out']);
            }
            $opening_stock = $closing_stock - $net_change_in_period;
            $current_balance = $opening_stock;

            // Add balance to each movement row
            foreach ($movements as $key => $mov) {
                 if ($movements[$key]['balance_stock'] === null) { // Don't overwrite if adjustment already set it
                    $current_balance += ($mov['qty_in'] - $mov['qty_out']);
                    $movements[$key]['balance_stock'] = $current_balance;
                 } else {
                     // If adjustment set it, subsequent balances should follow from there
                     $current_balance = $movements[$key]['balance_stock'];
                 }
            }
        }
    }


    $report_data = [
        'movements' => $movements,
        'opening_stock' => $opening_stock,
        'closing_stock' => $closing_stock, // This is the current stock if period includes today
        'product_id_filtered' => $product_id_filter
    ];
    require_once BASE_PATH . '/templates/reports/stock_movement_view.php';
} else {
    $_SESSION['message'] = "Unknown inventory report type.";
    $_SESSION['message_type'] = "info";
    header("Location: " . $base_module_url . "&type=stock_movement");
    exit;
}

// mysqli_close($conn); // Closed by index.php
?>
