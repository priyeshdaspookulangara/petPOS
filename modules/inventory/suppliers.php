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
    $_SESSION['message'] = "Access denied. You must be an admin to manage suppliers.";
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
$base_module_url .= $script_dir_path . "/index.php?module=inventory&action=suppliers";

$page_action = isset($_GET['sub_action']) ? $_GET['sub_action'] : 'list'; // list, add, edit, delete
$supplier_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Initialize supplier data array and form errors
$supplier_data = [
    'name' => '', 'contact_person' => '', 'phone' => '',
    'email' => '', 'address' => ''
];
$form_errors = [];

// Handle POST requests for add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($supplier_data as $key => $default_value) {
        if (isset($_POST[$key])) {
            $supplier_data[$key] = trim($_POST[$key]);
        }
    }
    $posted_action = $_POST['form_action']; // 'add' or 'edit'
    $posted_supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;

    // --- Validation ---
    if (empty($supplier_data['name'])) $form_errors['name'] = "Supplier name is required.";
    if (!empty($supplier_data['email']) && !filter_var($supplier_data['email'], FILTER_VALIDATE_EMAIL)) {
        $form_errors['email'] = "Invalid email format.";
    } else if (!empty($supplier_data['email'])) {
        // Check email uniqueness
        $sql_check_email = "SELECT id FROM suppliers WHERE email = '" . escape_string($conn, $supplier_data['email']) . "'";
        if ($posted_action === 'edit') $sql_check_email .= " AND id != " . $posted_supplier_id;
        $res_email = mysqli_query($conn, $sql_check_email);
        if ($res_email && mysqli_num_rows($res_email) > 0) $form_errors['email'] = "This email address is already registered to another supplier.";
        if($res_email) mysqli_free_result($res_email);
    }
    // Add other validations as needed (e.g., phone format)
    // --- End Validation ---

    if (empty($form_errors)) {
        $escaped_data = [];
        foreach($supplier_data as $key => $value) {
            $escaped_data[$key] = escape_string($conn, $value);
        }

        if ($posted_action === 'add') {
            $sql = "INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (
                        '" . $escaped_data['name'] . "',
                        '" . $escaped_data['contact_person'] . "',
                        '" . $escaped_data['phone'] . "',
                        '" . $escaped_data['email'] . "',
                        '" . $escaped_data['address'] . "'
                    )";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['message'] = "Supplier added successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error adding supplier: " . mysqli_error($conn);
                $_SESSION['message_type'] = "danger";
            }
        } elseif ($posted_action === 'edit' && $posted_supplier_id > 0) {
            $sql = "UPDATE suppliers SET
                        name = '" . $escaped_data['name'] . "',
                        contact_person = '" . $escaped_data['contact_person'] . "',
                        phone = '" . $escaped_data['phone'] . "',
                        email = '" . $escaped_data['email'] . "',
                        address = '" . $escaped_data['address'] . "',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = " . $posted_supplier_id;
            if (mysqli_query($conn, $sql)) {
                $_SESSION['message'] = "Supplier updated successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error updating supplier: " . mysqli_error($conn);
                $_SESSION['message_type'] = "danger";
            }
        }
        header("Location: " . $base_module_url);
        exit;
    } else {
        // Errors found, re-render form. $supplier_data is populated from POST.
        $page_action = ($posted_action === 'edit') ? 'edit' : 'add';
        $supplier_id = $posted_supplier_id; // Keep id for edit form
    }
}


// Handle GET requests for actions
if ($page_action === 'edit' && $supplier_id > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sql_get_sup = "SELECT * FROM suppliers WHERE id = " . $supplier_id;
    $result_get_sup = mysqli_query($conn, $sql_get_sup);
    if ($result_get_sup && mysqli_num_rows($result_get_sup) > 0) {
        $fetched_data = mysqli_fetch_assoc($result_get_sup);
        foreach ($supplier_data as $key => $default_value) {
            if (isset($fetched_data[$key])) {
                $supplier_data[$key] = $fetched_data[$key];
            }
        }
        mysqli_free_result($result_get_sup);
    } else {
        $_SESSION['message'] = "Supplier not found.";
        $_SESSION['message_type'] = "warning";
        header("Location: " . $base_module_url);
        exit;
    }
} elseif ($page_action === 'delete' && $supplier_id > 0) {
    // Check if supplier is in use by products or purchases
    $sql_check_prod = "SELECT COUNT(*) as count FROM products WHERE supplier_id = " . $supplier_id;
    $res_prod = mysqli_query($conn, $sql_check_prod);
    $prod_count = ($res_prod) ? mysqli_fetch_assoc($res_prod)['count'] : 0;
    if($res_prod) mysqli_free_result($res_prod);

    $sql_check_purch = "SELECT COUNT(*) as count FROM purchases WHERE supplier_id = " . $supplier_id;
    $res_purch = mysqli_query($conn, $sql_check_purch);
    $purch_count = ($res_purch) ? mysqli_fetch_assoc($res_purch)['count'] : 0;
    if($res_purch) mysqli_free_result($res_purch);

    if ($prod_count > 0 || $purch_count > 0) {
        $error_msg = "Cannot delete supplier. It is associated with ";
        if ($prod_count > 0) $error_msg .= $prod_count . " product(s)";
        if ($prod_count > 0 && $purch_count > 0) $error_msg .= " and ";
        if ($purch_count > 0) $error_msg .= $purch_count . " purchase record(s)";
        $error_msg .= ". Please reassign or remove these associations first.";
        $_SESSION['message'] = $error_msg;
        $_SESSION['message_type'] = "warning";
    } else {
        $sql_delete = "DELETE FROM suppliers WHERE id = " . $supplier_id;
        if (mysqli_query($conn, $sql_delete)) {
            $_SESSION['message'] = "Supplier deleted successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting supplier: " . mysqli_error($conn);
            $_SESSION['message_type'] = "danger";
        }
    }
    header("Location: " . $base_module_url);
    exit;
}


// Display logic: list or form
if ($page_action === 'list') {
    $suppliers = [];
    $sql_list = "SELECT s.*, (SELECT COUNT(*) FROM products WHERE supplier_id = s.id) as product_count,
                             (SELECT COUNT(*) FROM purchases WHERE supplier_id = s.id) as purchase_count
                 FROM suppliers s ORDER BY s.name ASC";
    $result_list = mysqli_query($conn, $sql_list);
    if ($result_list) {
        while ($row = mysqli_fetch_assoc($result_list)) {
            $suppliers[] = $row;
        }
        mysqli_free_result($result_list);
    } else {
        $_SESSION['message'] = "Error fetching suppliers: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    require_once BASE_PATH . '/templates/inventory/supplier_list.php';
} elseif ($page_action === 'add' || $page_action === 'edit') {
    // $supplier_data is already prepared.
    require_once BASE_PATH . '/templates/inventory/supplier_form.php';
}

// mysqli_close($conn); // Closed by index.php
?>
