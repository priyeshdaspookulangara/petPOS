<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Regenerate session ID periodically to help prevent session fixation
if (!isset($_SESSION['last_regen']) || (time() - $_SESSION['last_regen'] > 60 * 5)) { // Regenerate every 5 minutes
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}


/**
 * Checks if a user is currently logged in.
 *
 * @return bool True if logged in, false otherwise.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Checks if the logged-in user has a specific role.
 *
 * @param string $role The role to check for (e.g., 'Admin', 'Cashier').
 * @return bool True if the user has the role, false otherwise.
 */
function user_has_role($role) {
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Checks if the logged-in user is an Admin.
 *
 * @return bool True if the user is an Admin, false otherwise.
 */
function is_admin() {
    return user_has_role('Admin');
}

/**
 * Checks if the logged-in user is a Cashier.
 *
 * @return bool True if the user is a Cashier, false otherwise.
 */
function is_cashier() {
    return user_has_role('Cashier');
}

/**
 * Redirects the user to the login page if they are not logged in.
 * Optionally, can redirect to a specific page.
 *
 * @param string $redirect_url The URL to redirect to after login (optional).
 *                             Defaults to the current page.
 * @param string $base_path The base path of the application for constructing URLs.
 *                          Default is '/'. Adjust if your app is in a subdirectory.
 */
function redirect_if_not_logged_in($base_path = '/') {
    if (!is_logged_in()) {
        // Store the current page URL to redirect back after login
        // $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI']; // Be careful with this, ensure it's a valid internal URL

        header("Location: " . rtrim($base_path, '/') . "/login/");
        exit;
    }
}

/**
 * Redirects the user if they do not have Admin privileges.
 * Shows an unauthorized message or redirects to a specific page.
 *
 * @param string $base_path The base path of the application.
 */
function redirect_if_not_admin($base_path = '/') {
    redirect_if_not_logged_in($base_path); // First, ensure user is logged in
    if (!is_admin()) {
        // Optionally, you can redirect to a specific "unauthorized" page
        // header("Location: " . rtrim($base_path, '/') . "/unauthorized/");
        // Or just show a simple message and exit
        http_response_code(403); // Forbidden
        echo "Access Denied. You do not have sufficient privileges to access this page.";
        // You might want to include a nicer HTML page for this error.
        // For now, a simple message. Consider creating templates/unauthorized.php
        exit;
    }
}

/**
 * Gets the current user's ID.
 * @return int|null User ID if logged in, null otherwise.
 */
function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Gets the current user's role.
 * @return string|null User role if logged in, null otherwise.
 */
function get_current_user_role() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Gets the current user's username.
 * @return string|null Username if logged in, null otherwise.
 */
function get_current_user_username() {
    return $_SESSION['username'] ?? null;
}

/**
 * Logs out the current user by destroying the session.
 *
 * @param string $redirect_path The path to redirect to after logout.
 *                              Defaults to '/login/'.
 * @param string $base_path The base path of the application.
 */
function logout_user($redirect_path = 'login/', $base_path = '/') {
    // Unset all of the session variables.
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

    header("Location: " . rtrim($base_path, '/') . '/' . ltrim($redirect_path, '/'));
    exit;
}

?>
