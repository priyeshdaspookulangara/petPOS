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
    $_SESSION['message'] = "Access denied. You must be an admin to manage settings.";
    $_SESSION['message_type'] = "danger";
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $app_path = dirname($_SERVER['SCRIPT_NAME']);
    if ($app_path === '/' || $app_path === '\\') $app_path = '';
    header("Location: " . $protocol . "://" . $host . $app_path . "/index.php?page=dashboard");
    exit;
}

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$script_dir_path = dirname($_SERVER['SCRIPT_NAME']);
if ($script_dir_path === '/' || $script_dir_path === '\\') $script_dir_path = '';
$base_url .= $script_dir_path;


$settings_feedback = [];
$current_settings = [];

// Fetch all current settings
$sql_get_settings = "SELECT setting_key, setting_value FROM settings";
$result_get_settings = mysqli_query($conn, $sql_get_settings);
if ($result_get_settings) {
    while ($row = mysqli_fetch_assoc($result_get_settings)) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
    mysqli_free_result($result_get_settings);
} else {
    $settings_feedback[] = ['type' => 'danger', 'message' => 'Error fetching settings: ' . mysqli_error($conn)];
}

// Handle form submission for updating settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        // Validate CSRF token
        if (!validate_csrf_token()) {
            handle_error("CSRF token validation failed for settings update.", "Invalid request. Please try again.");
            // Redirect or show error, prevent further processing
            header("Location: " . $base_url . "/index.php?module=settings&action=index&csrf_error=1");
            exit;
        }

        $allowed_keys = [
            'store_name', 'store_address', 'store_phone', 'store_email',
            'tax_rate_percentage', 'receipt_footer_message', 'store_logo_url', 'currency_symbol'
        ];

        $all_updates_successful = true;

        foreach ($allowed_keys as $key) {
            if (isset($_POST[$key])) {
                $value = $_POST[$key];
                $escaped_key = escape_string($conn, $key);
                $escaped_value = escape_string($conn, $value);

                // Using INSERT ... ON DUPLICATE KEY UPDATE for simplicity
                $sql_update = "INSERT INTO settings (setting_key, setting_value)
                               VALUES ('" . $escaped_key . "', '" . $escaped_value . "')
                               ON DUPLICATE KEY UPDATE setting_value = '" . $escaped_value . "'";

                if (mysqli_query($conn, $sql_update)) {
                    $current_settings[$key] = $value; // Update current view
                } else {
                    $settings_feedback[] = ['type' => 'danger', 'message' => "Error updating setting '{$key}': " . mysqli_error($conn)];
                    $all_updates_successful = false;
                }
            }
        }

        if ($all_updates_successful) {
            $_SESSION['message'] = "Settings updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Some settings could not be updated. Please check errors below.";
            $_SESSION['message_type'] = "warning";
        }
        // Redirect to the same page to show messages and prevent resubmission
        header("Location: " . $base_url . "/index.php?module=settings&action=index");
        exit;
    }
}

// Include the settings form template
// The template path should be relative to the BASE_PATH
require_once BASE_PATH . '/templates/settings/settings_form.php';

// Note: $conn is typically closed at the end of index.php or automatically by PHP.
// If this script were standalone, you'd add mysqli_close($conn); here.
?>
