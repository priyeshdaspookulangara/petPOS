<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
require_once BASE_PATH . '/config/db.php'; // For $conn, escape_string

if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Security check: Only admins can access this page (adjust if other roles need access)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Access denied. You must be an admin to manage categories.";
    $_SESSION['message_type'] = "danger";
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $app_path = dirname($_SERVER['SCRIPT_NAME']);
    if ($app_path === '/' || $app_path === '\\') $app_path = '';
    header("Location: " . $protocol . "://" . $host . $app_path . "/index.php?page=dashboard");
    exit;
}

// Base URL for links within this specific module action context
$base_module_self_url = APP_INDEX_URL . "?module=inventory&action=categories";


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

    // Validate name
    if (empty($category_name)) {
        $form_errors['name'] = "Category name is required.";
    } else {
        // Check for duplicate category name (optional, but good for usability)
        $escaped_name = escape_string($conn, $category_name);
        $sql_check_duplicate = "SELECT id FROM categories WHERE name = '" . $escaped_name . "'";
        if ($posted_action === 'edit') {
            $sql_check_duplicate .= " AND id != " . $posted_category_id;
        }
        $res_check = mysqli_query($conn, $sql_check_duplicate);
        if ($res_check && mysqli_num_rows($res_check) > 0) {
            $form_errors['name'] = "A category with this name already exists.";
        }
        if($res_check) mysqli_free_result($res_check);
    }

    if (empty($form_errors)) {
        $escaped_name = escape_string($conn, $category_name);
        $escaped_description = escape_string($conn, $category_description);

        if ($posted_action === 'add') {
            $sql = "INSERT INTO categories (name, description) VALUES ('" . $escaped_name . "', '" . $escaped_description . "')";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['message'] = "Category added successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error adding category: " . mysqli_error($conn);
                $_SESSION['message_type'] = "danger";
            }
        } elseif ($posted_action === 'edit' && $posted_category_id > 0) {
            $sql = "UPDATE categories SET name = '" . $escaped_name . "', description = '" . $escaped_description . "', updated_at = CURRENT_TIMESTAMP WHERE id = " . $posted_category_id;
            if (mysqli_query($conn, $sql)) {
                $_SESSION['message'] = "Category updated successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error updating category: " . mysqli_error($conn);
                $_SESSION['message_type'] = "danger";
            }
        }
        header("Location: " . $base_module_self_url); // Redirect to the list view of categories
        exit;
    } else {
        // Errors found, re-render form with errors and data
        // Ensure $page_action is set to 'add' or 'edit' to show the form
        $page_action = ($posted_action === 'edit') ? 'edit' : 'add';
        $category_id = $posted_category_id; // Keep id for edit form
        // Data ($category_name, $category_description) is already set from POST
    }
}


// Handle GET requests for actions
if ($page_action === 'edit' && $category_id > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') { // Only if not a POST error recovery
    $sql_get_cat = "SELECT name, description FROM categories WHERE id = " . $category_id;
    $result_get_cat = mysqli_query($conn, $sql_get_cat);
    if ($result_get_cat && mysqli_num_rows($result_get_cat) > 0) {
        $category_data = mysqli_fetch_assoc($result_get_cat);
        $category_name = $category_data['name'];
        $category_description = $category_data['description'];
        mysqli_free_result($result_get_cat);
    } else {
        $_SESSION['message'] = "Category not found.";
        $_SESSION['message_type'] = "warning";
        header("Location: " . $base_module_self_url);
        exit;
    }
} elseif ($page_action === 'delete' && $category_id > 0) {
    // Check if category is in use by products (optional, but good practice)
    $sql_check_usage = "SELECT COUNT(*) as count FROM products WHERE category_id = " . $category_id;
    $res_usage = mysqli_query($conn, $sql_check_usage);
    $usage_count = 0;
    if($res_usage) {
        $row_usage = mysqli_fetch_assoc($res_usage);
        $usage_count = $row_usage['count'];
        mysqli_free_result($res_usage);
    }

    if ($usage_count > 0) {
        $_SESSION['message'] = "Cannot delete category. It is currently assigned to " . $usage_count . " product(s). Please reassign products first.";
        $_SESSION['message_type'] = "warning";
    } else {
        $sql_delete = "DELETE FROM categories WHERE id = " . $category_id;
        if (mysqli_query($conn, $sql_delete)) {
            $_SESSION['message'] = "Category deleted successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting category: " . mysqli_error($conn);
            $_SESSION['message_type'] = "danger";
        }
    }
    header("Location: " . $base_module_self_url);
    exit;
}


// Display logic: list or form
if ($page_action === 'list') {
    $categories = [];
    $sql_list = "SELECT id, name, description, (SELECT COUNT(*) FROM products WHERE category_id = categories.id) as product_count FROM categories ORDER BY name ASC";
    $result_list = mysqli_query($conn, $sql_list);
    if ($result_list) {
        while ($row = mysqli_fetch_assoc($result_list)) {
            $categories[] = $row;
        }
        mysqli_free_result($result_list);
    } else {
        // Handle error, e.g., display a message
        $_SESSION['message'] = "Error fetching categories: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
        // This message will be shown by header.php
    }
    require_once BASE_PATH . '/templates/inventory/category_list.php';
} elseif ($page_action === 'add' || $page_action === 'edit') {
    // Data for form ($category_name, $category_description, $category_id for edit)
    // is already prepared by the POST handling or GET 'edit' handling logic.
    require_once BASE_PATH . '/templates/inventory/category_form.php';
}

// mysqli_close($conn); // Closed by index.php
?>
