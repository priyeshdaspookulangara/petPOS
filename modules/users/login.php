<?php
// This file acts as a controller for the login page.
// It ensures that configuration is loaded and then includes the login form template.

if (!defined('BASE_PATH')) {
    // Attempt to define BASE_PATH if not already defined
    $current_dir = dirname(__FILE__); // modules/users
    $base_path_parts = explode(DIRECTORY_SEPARATOR, $current_dir);
    $project_root_index = array_search('modules', $base_path_parts);
    if ($project_root_index !== false) {
        $project_root_parts = array_slice($base_path_parts, 0, $project_root_index);
        define('BASE_PATH', implode(DIRECTORY_SEPARATOR, $project_root_parts));
    } else {
        define('BASE_PATH', dirname(__DIR__, 2));
    }
}

// Ensure session is started (idempotent)
if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Calculate base URL for redirection if needed, though index.php handles most navigation
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$script_dir = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
// If index.php is in root, $script_dir might be just '/', if in subdir, e.g. /mypos/
// We need the path to the project root relative to the domain.
// dirname($_SERVER['SCRIPT_NAME'], 2) if SCRIPT_NAME is /project/modules/users/login.php (wrong context)
// The $base_url in header.php is more robust for general use.
// For redirection from this specific file, we need to ensure it points correctly to index.php
$project_base_path = dirname($_SERVER['SCRIPT_NAME']); // If accessed via index.php?page=login, SCRIPT_NAME is /index.php
if ($project_base_path === '/' || $project_base_path === '\\') $project_base_path = '';


// If user is already logged in, redirect them to the dashboard
if (isset($_SESSION['user_id'])) {
    // Construct the correct redirect URL to the main index.php
    $redirect_url = $base_url . $project_base_path . "/index.php?page=dashboard";
    header("Location: " . $redirect_url);
    exit;
}

// The actual login form HTML is in a template file.
// This script just needs to ensure it's included.
// Any messages (like errors from dologin.php or logout status) are handled by header.php
// when it displays $_SESSION['message'].

// Include the login form template
// The template path should be relative to the BASE_PATH
require_once BASE_PATH . '/templates/users/login_form.php';

?>
