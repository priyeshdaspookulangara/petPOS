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
    $_SESSION['message'] = "Access denied. You must be an admin to manage expense categories.";
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
$base_module_url .= $script_dir_path . "/index.php?module=accounts&action=expense_categories";

$page_action = isset($_GET['sub_action']) ? $_GET['sub_action'] : 'list'; // list, add, edit, delete
$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$category_name = '';
$category_description = '';
$form_errors = [];

// Handle POST requests for add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_name = trim($_POST['category_name']);
    $category_description = trim($_POST['category_description']);
    $posted_action = $_POST['form_action']; // 'add' or 'edit'
    $posted_category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

    if (empty($category_name)) {
        $form_errors['name'] = "Category name is required.";
    } else {
        $escaped_name = escape_string($conn, $category_name);
        $sql_check_duplicate = "SELECT id FROM expense_categories WHERE name = '" . $escaped_name . "'";
        if ($posted_action === 'edit') {
            $sql_check_duplicate .= " AND id != " . $posted_category_id;
        }
        $res_check = mysqli_query($conn, $sql_check_duplicate);
        if ($res_check && mysqli_num_rows($res_check) > 0) {
            $form_errors['name'] = "An expense category with this name already exists.";
        }
        if($res_check) mysqli_free_result($res_check);
    }

    if (empty($form_errors)) {
        $escaped_name = escape_string($conn, $category_name);
        $escaped_description = escape_string($conn, $category_description);

        if ($posted_action === 'add') {
            $sql = "INSERT INTO expense_categories (name, description) VALUES ('" . $escaped_name . "', '" . $escaped_description . "')";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['message'] = "Expense category added successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error adding category: " . mysqli_error($conn);
                $_SESSION['message_type'] = "danger";
            }
        } elseif ($posted_action === 'edit' && $posted_category_id > 0) {
            $sql = "UPDATE expense_categories SET name = '" . $escaped_name . "', description = '" . $escaped_description . "', updated_at = CURRENT_TIMESTAMP WHERE id = " . $posted_category_id;
            if (mysqli_query($conn, $sql)) {
                $_SESSION['message'] = "Expense category updated successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error updating category: " . mysqli_error($conn);
                $_SESSION['message_type'] = "danger";
            }
        }
        header("Location: " . $base_module_url);
        exit;
    } else {
        $page_action = ($posted_action === 'edit') ? 'edit' : 'add';
        $category_id = $posted_category_id;
    }
}


// Handle GET requests for actions
if ($page_action === 'edit' && $category_id > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sql_get_cat = "SELECT name, description FROM expense_categories WHERE id = " . $category_id;
    $result_get_cat = mysqli_query($conn, $sql_get_cat);
    if ($result_get_cat && mysqli_num_rows($result_get_cat) > 0) {
        $category_data = mysqli_fetch_assoc($result_get_cat);
        $category_name = $category_data['name'];
        $category_description = $category_data['description'];
        mysqli_free_result($result_get_cat);
    } else {
        $_SESSION['message'] = "Expense category not found.";
        $_SESSION['message_type'] = "warning";
        header("Location: " . $base_module_url);
        exit;
    }
} elseif ($page_action === 'delete' && $category_id > 0) {
    $sql_check_usage = "SELECT COUNT(*) as count FROM expenses WHERE expense_category_id = " . $category_id;
    $res_usage = mysqli_query($conn, $sql_check_usage);
    $usage_count = 0;
    if($res_usage) {
        $row_usage = mysqli_fetch_assoc($res_usage);
        $usage_count = $row_usage['count'];
        mysqli_free_result($res_usage);
    }

    if ($usage_count > 0) {
        $_SESSION['message'] = "Cannot delete category. It is currently assigned to " . $usage_count . " expense(s).";
        $_SESSION['message_type'] = "warning";
    } else {
        $sql_delete = "DELETE FROM expense_categories WHERE id = " . $category_id;
        if (mysqli_query($conn, $sql_delete)) {
            $_SESSION['message'] = "Expense category deleted successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting category: " . mysqli_error($conn);
            $_SESSION['message_type'] = "danger";
        }
    }
    header("Location: " . $base_module_url);
    exit;
}

// Display logic: list or form
if ($page_action === 'list') {
    $categories = [];
    $sql_list = "SELECT ec.*, (SELECT COUNT(*) FROM expenses WHERE expense_category_id = ec.id) as expense_count
                 FROM expense_categories ec ORDER BY ec.name ASC";
    $result_list = mysqli_query($conn, $sql_list);
    if ($result_list) {
        while ($row = mysqli_fetch_assoc($result_list)) {
            $categories[] = $row;
        }
        mysqli_free_result($result_list);
    } else {
        $_SESSION['message'] = "Error fetching expense categories: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    require_once BASE_PATH . '/templates/accounts/expense_category_list.php';
} elseif ($page_action === 'add' || $page_action === 'edit') {
    require_once BASE_PATH . '/templates/accounts/expense_category_form.php';
}

// mysqli_close($conn); // Closed by index.php
?>
