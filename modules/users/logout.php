<?php
// Ensure BASE_PATH is defined for consistency, though not strictly needed for this simple script
if (!defined('BASE_PATH')) {
    $current_dir = dirname(__FILE__);
    $base_path_parts = explode(DIRECTORY_SEPARATOR, $current_dir);
    $project_root_index = array_search('modules', $base_path_parts);
    if ($project_root_index !== false) {
        $project_root_parts = array_slice($base_path_parts, 0, $project_root_index);
        define('BASE_PATH', implode(DIRECTORY_SEPARATOR, $project_root_parts));
    } else {
        define('BASE_PATH', dirname(__DIR__, 2));
    }
}

// Start or resume session to access session variables
if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Unset all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Determine the base URL for redirection
// This ensures redirection works correctly whether the site is in root or a subdirectory.
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
// SCRIPT_NAME would be /index.php if accessed via index.php?page=logout
// We want the path to the root of the application.
// dirname($_SERVER['SCRIPT_NAME']) gives the directory of index.php
$app_path = dirname($_SERVER['SCRIPT_NAME']);
if ($app_path === '/' || $app_path === '\\') {
    $app_path = ''; // Avoid double slashes if root
}
$base_redirect_url = $protocol . "://" . $host . $app_path;

// Set a logout message
// We have to start a new session briefly to pass this message, or pass it as a GET param.
// For simplicity with the current message display system, let's re-initiate session for the message.
if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start(); // Start a new session just for the message
}
$_SESSION['message'] = "You have been logged out successfully.";
$_SESSION['message_type'] = "info";


// Redirect to the login page
header("Location: " . $base_redirect_url . "/index.php?page=login");
exit;
?>
