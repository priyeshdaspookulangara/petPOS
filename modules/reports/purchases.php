<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
require_once BASE_PATH . '/config/db.php'; // For $conn

if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Security check: Only admins can access purchase reports
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Access denied. You must be an admin to view purchase reports.";
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
$base_module_url .= $script_dir_path . "/index.php?module=reports&action=purchases";

$report_type = $_GET['type'] ?? 'summary_by_date'; // summary_by_date, by_supplier, purchase_return_summary

// Common date filtering
$filter_period = $_GET['period'] ?? 'monthly';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$supplier_filter = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : '';


$sql_where_conditions = ["p.status IN ('received', 'partially_received', 'ordered')"]; // Consider 'ordered' if pre-payments are relevant
$date_field_to_filter = "p.purchase_date"; // Default date field

// Date range SQL
$date_range_sql = "";
switch ($filter_period) {
    case 'daily':
        $current_date_sql = date('Y-m-d');
        $date_range_sql = "DATE({$date_field_to_filter}) = '" . $current_date_sql . "'";
        break;
    case 'weekly':
        $start_of_week_sql = date('Y-m-d', strtotime('monday this week'));
        $end_of_week_sql = date('Y-m-d', strtotime('sunday this week'));
        $date_range_sql = "DATE({$date_field_to_filter}) BETWEEN '" . $start_of_week_sql . "' AND '" . $end_of_week_sql . "'";
        break;
    case 'monthly':
        $start_of_month_sql = date('Y-m-01');
        $end_of_month_sql = date('Y-m-t');
        $date_range_sql = "DATE({$date_field_to_filter}) BETWEEN '" . $start_of_month_sql . "' AND '" . $end_of_month_sql . "'";
        break;
    case 'custom':
        if (!empty($start_date) && !empty($end_date)) {
            $s_date_sql = escape_string($conn, $start_date);
            $e_date_sql = escape_string($conn, $end_date);
            $date_range_sql = "DATE({$date_field_to_filter}) BETWEEN '" . $s_date_sql . "' AND '" . $e_date_sql . "'";
        } else {
            $current_date_sql = date('Y-m-d');
            $date_range_sql = "DATE({$date_field_to_filter}) = '" . $current_date_sql . "'";
            $filter_period = 'daily';
        }
        break;
    default:
        $current_date_sql = date('Y-m-d');
        $date_range_sql = "DATE({$date_field_to_filter}) = '" . $current_date_sql . "'";
        $filter_period = 'daily';
}
if (!empty($date_range_sql)) {
    $sql_where_conditions[] = $date_range_sql;
}

// Supplier filter for 'by_supplier' report
if (!empty($supplier_filter)) {
    $sql_where_conditions[] = "p.supplier_id = " . (int)$supplier_filter;
}


$report_data = [];
$report_title = "";
$suppliers_for_filter = [];

// Fetch suppliers for filter dropdown
$sql_supp_list = "SELECT id, name FROM suppliers ORDER BY name ASC";
$res_supp_list = mysqli_query($conn, $sql_supp_list);
if($res_supp_list) while($srow = mysqli_fetch_assoc($res_supp_list)) $suppliers_for_filter[] = $srow;
if($res_supp_list) mysqli_free_result($res_supp_list);


if ($report_type === 'summary_by_date' || $report_type === 'by_supplier') {
    if ($report_type === 'summary_by_date') $report_title = "Purchases Summary by Date";
    if ($report_type === 'by_supplier') $report_title = "Purchases by Supplier";

    $group_by_sql = ($report_type === 'summary_by_date') ? "DATE({$date_field_to_filter})" : "p.supplier_id, s.name";
    $select_extra = ($report_type === 'summary_by_date') ? "DATE({$date_field_to_filter}) as purchase_day" : "s.name as supplier_name, p.supplier_id";

    $sql_summary = "SELECT {$select_extra},
                           COUNT(p.id) as number_of_pos,
                           SUM(p.total_amount) as total_po_value,
                           SUM(p.amount_paid) as total_amount_paid
                    FROM purchases p
                    JOIN suppliers s ON p.supplier_id = s.id ";
    if (!empty($sql_where_conditions)) {
        $sql_summary .= " WHERE " . implode(" AND ", $sql_where_conditions);
    }
    $sql_summary .= " GROUP BY {$group_by_sql} ORDER BY {$group_by_sql} DESC";

    $res_summary = mysqli_query($conn, $sql_summary);
    if ($res_summary) {
        while ($row = mysqli_fetch_assoc($res_summary)) $report_data[] = $row;
        mysqli_free_result($res_summary);
    } else {
        $_SESSION['message'] = "Error generating purchases summary: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    if ($report_type === 'summary_by_date') require_once BASE_PATH . '/templates/reports/purchases_summary_by_date_view.php';
    if ($report_type === 'by_supplier') require_once BASE_PATH . '/templates/reports/purchases_by_supplier_view.php';

} elseif ($report_type === 'purchase_return_summary') {
    $report_title = "Purchase Return Report";
    // Adjust date filter for purchase returns (pr.return_date)
    $return_date_field = "pr.return_date";
    $return_sql_where_conditions = []; // Reset for this report type

    // Rebuild date_range_sql for return_date
    $return_date_range_sql = "";
    switch ($filter_period) { // Using same $filter_period, $start_date, $end_date
        case 'daily': $return_date_range_sql = "DATE({$return_date_field}) = '" . date('Y-m-d') . "'"; break;
        case 'weekly': $return_date_range_sql = "DATE({$return_date_field}) BETWEEN '" . date('Y-m-d', strtotime('monday this week')) . "' AND '" . date('Y-m-d', strtotime('sunday this week')) . "'"; break;
        case 'monthly': $return_date_range_sql = "DATE({$return_date_field}) BETWEEN '" . date('Y-m-01') . "' AND '" . date('Y-m-t') . "'"; break;
        case 'custom':
            if (!empty($start_date) && !empty($end_date)) {
                $return_date_range_sql = "DATE({$return_date_field}) BETWEEN '" . escape_string($conn, $start_date) . "' AND '" . escape_string($conn, $end_date) . "'";
            } else { $return_date_range_sql = "DATE({$return_date_field}) = '" . date('Y-m-d') . "'"; $filter_period = 'daily';}
            break;
        default: $return_date_range_sql = "DATE({$return_date_field}) = '" . date('Y-m-d') . "'"; $filter_period = 'daily';
    }
    if (!empty($return_date_range_sql)) $return_sql_where_conditions[] = $return_date_range_sql;

    if (!empty($supplier_filter)) { // Filter by supplier if provided
        $return_sql_where_conditions[] = "pr.supplier_id = " . (int)$supplier_filter;
    }

    $sql_returns = "SELECT pr.return_note_no, pr.return_date, p.po_number as original_po_number,
                           s.name as supplier_name, u.username as processed_by, pr.total_return_amount, pr.reason
                    FROM purchase_returns pr
                    JOIN purchases p ON pr.original_purchase_id = p.id
                    JOIN suppliers s ON pr.supplier_id = s.id
                    JOIN users u ON pr.user_id = u.id ";
    if (!empty($return_sql_where_conditions)) {
        $sql_returns .= " WHERE " . implode(" AND ", $return_sql_where_conditions);
    }
    $sql_returns .= " ORDER BY pr.return_date DESC";

    $res_returns = mysqli_query($conn, $sql_returns);
    if ($res_returns) {
        while ($row = mysqli_fetch_assoc($res_returns)) $report_data[] = $row;
        mysqli_free_result($res_returns);
    } else {
        $_SESSION['message'] = "Error generating purchase return report: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    require_once BASE_PATH . '/templates/reports/purchase_return_report_view.php';

} else {
    $_SESSION['message'] = "Unknown purchase report type.";
    $_SESSION['message_type'] = "info";
    header("Location: " . $base_module_url . "&type=summary_by_date");
    exit;
}

// mysqli_close($conn); // Closed by index.php
?>
