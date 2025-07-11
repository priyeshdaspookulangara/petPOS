<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
require_once BASE_PATH . '/config/db.php'; // For $conn, escape_string

if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Security check: Only admins can access this page
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Access denied. You must be an admin to manage expenses.";
    $_SESSION['message_type'] = "danger";
    // Standard redirect logic
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
$base_module_url .= $script_dir_path . "/index.php?module=accounts&action=expenses";

$page_action = isset($_GET['sub_action']) ? $_GET['sub_action'] : 'list'; // list, add, edit, delete
$expense_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$expense_data = [
    'expense_category_id' => '',
    'expense_date' => date('Y-m-d'),
    'amount' => '',
    'description' => '',
    'receipt_reference' => ''
];
$form_errors = [];
$expense_categories_list = [];

// Fetch expense categories for dropdown
$sql_exp_cats = "SELECT id, name FROM expense_categories ORDER BY name ASC";
$res_exp_cats = mysqli_query($conn, $sql_exp_cats);
if ($res_exp_cats) {
    while ($row = mysqli_fetch_assoc($res_exp_cats)) $expense_categories_list[] = $row;
    mysqli_free_result($res_exp_cats);
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_data['expense_category_id'] = trim($_POST['expense_category_id']);
    $expense_data['expense_date'] = trim($_POST['expense_date']);
    $expense_data['amount'] = trim($_POST['amount']);
    $expense_data['description'] = trim($_POST['description']);
    $expense_data['receipt_reference'] = trim($_POST['receipt_reference']);
    $posted_action = $_POST['form_action']; // 'add' or 'edit'
    $posted_expense_id = isset($_POST['expense_id']) ? (int)$_POST['expense_id'] : 0;
    $user_id = $_SESSION['user_id'];

    // Validation
    if (empty($expense_data['expense_category_id'])) $form_errors['expense_category_id'] = "Expense category is required.";
    if (empty($expense_data['expense_date'])) $form_errors['expense_date'] = "Expense date is required.";
    if (!is_numeric($expense_data['amount']) || floatval($expense_data['amount']) <= 0) $form_errors['amount'] = "Amount must be a positive number.";
    if (empty($expense_data['description'])) $form_errors['description'] = "Description is required.";


    if (empty($form_errors)) {
        $cat_id_sql = (int)$expense_data['expense_category_id'];
        $date_sql = "'" . escape_string($conn, $expense_data['expense_date']) . "'";
        $amount_sql = (float)$expense_data['amount'];
        $desc_sql = "'" . escape_string($conn, $expense_data['description']) . "'";
        $receipt_ref_sql = !empty($expense_data['receipt_reference']) ? "'" . escape_string($conn, $expense_data['receipt_reference']) . "'" : "NULL";

        if ($posted_action === 'add') {
            $sql = "INSERT INTO expenses (expense_category_id, user_id, expense_date, amount, description, receipt_reference) VALUES (
                        {$cat_id_sql}, {$user_id}, {$date_sql}, {$amount_sql}, {$desc_sql}, {$receipt_ref_sql}
                    )";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['message'] = "Expense recorded successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error recording expense: " . mysqli_error($conn);
                $_SESSION['message_type'] = "danger";
            }
        } elseif ($posted_action === 'edit' && $posted_expense_id > 0) {
            $sql = "UPDATE expenses SET
                        expense_category_id = {$cat_id_sql},
                        user_id = {$user_id}, /* Or keep original user? For now, current user */
                        expense_date = {$date_sql},
                        amount = {$amount_sql},
                        description = {$desc_sql},
                        receipt_reference = {$receipt_ref_sql},
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = " . $posted_expense_id;
            if (mysqli_query($conn, $sql)) {
                $_SESSION['message'] = "Expense updated successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error updating expense: " . mysqli_error($conn);
                $_SESSION['message_type'] = "danger";
            }
        }
        header("Location: " . $base_module_url);
        exit;
    } else {
        $page_action = ($posted_action === 'edit') ? 'edit' : 'add';
        $expense_id = $posted_expense_id;
    }
}


// Handle GET requests for actions
if ($page_action === 'edit' && $expense_id > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sql_get_exp = "SELECT * FROM expenses WHERE id = " . $expense_id;
    $result_get_exp = mysqli_query($conn, $sql_get_exp);
    if ($result_get_exp && mysqli_num_rows($result_get_exp) > 0) {
        $fetched_data = mysqli_fetch_assoc($result_get_exp);
        $expense_data['expense_category_id'] = $fetched_data['expense_category_id'];
        $expense_data['expense_date'] = $fetched_data['expense_date'];
        $expense_data['amount'] = $fetched_data['amount'];
        $expense_data['description'] = $fetched_data['description'];
        $expense_data['receipt_reference'] = $fetched_data['receipt_reference'];
        mysqli_free_result($result_get_exp);
    } else {
        $_SESSION['message'] = "Expense record not found.";
        $_SESSION['message_type'] = "warning";
        header("Location: " . $base_module_url);
        exit;
    }
} elseif ($page_action === 'delete' && $expense_id > 0) {
    $sql_delete = "DELETE FROM expenses WHERE id = " . $expense_id;
    if (mysqli_query($conn, $sql_delete)) {
        $_SESSION['message'] = "Expense record deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting expense record: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    header("Location: " . $base_module_url);
    exit;
}


// Display logic: list or form
if ($page_action === 'list') {
    $expenses = [];
    $sql_list = "SELECT e.*, ec.name as category_name, u.username as user_username
                 FROM expenses e
                 JOIN expense_categories ec ON e.expense_category_id = ec.id
                 JOIN users u ON e.user_id = u.id
                 ORDER BY e.expense_date DESC, e.id DESC";
    $result_list = mysqli_query($conn, $sql_list);
    if ($result_list) {
        while ($row = mysqli_fetch_assoc($result_list)) {
            $expenses[] = $row;
        }
        mysqli_free_result($result_list);
    } else {
        $_SESSION['message'] = "Error fetching expenses: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    require_once BASE_PATH . '/templates/accounts/expense_list.php';
} elseif ($page_action === 'add' || $page_action === 'edit') {
    // $expense_data, $form_errors, $expense_categories_list are prepared
    require_once BASE_PATH . '/templates/accounts/expense_form.php';
}

// mysqli_close($conn); // Closed by index.php
?>
