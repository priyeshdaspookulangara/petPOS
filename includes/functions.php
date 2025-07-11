<?php
// Helper functions

if (!defined('BASE_PATH')) {
    // This check might be redundant if always included after BASE_PATH is defined.
    // However, good for standalone testing or direct includes.
    $current_dir_func = dirname(__FILE__); // includes
    $base_path_parts_func = explode(DIRECTORY_SEPARATOR, $current_dir_func);
    // Assuming 'includes' is a direct child of the project root
    $project_root_index_func = array_search('includes', $base_path_parts_func);
    if ($project_root_index_func !== false) {
        $project_root_parts_func = array_slice($base_path_parts_func, 0, $project_root_index_func);
        define('BASE_PATH', implode(DIRECTORY_SEPARATOR, $project_root_parts_func));
    } else {
        // Fallback for safety, adjust if structure is different
        define('BASE_PATH', dirname(__DIR__));
    }
}


/**
 * Handles logging an error and setting a user-friendly session message.
 *
 * @param string $errorMessage The detailed error message to log.
 * @param string $userMessage The generic message to show to the user.
 * @param mysqli|null $db_conn Optional: The database connection for mysqli_error.
 * @param string $logFilePath Optional: Path to the error log file.
 */
function handle_error($errorMessage, $userMessage = "An unexpected error occurred. Please try again later.", $db_conn = null, $logFilePath = null) {
    if ($db_conn && mysqli_error($db_conn)) {
        $errorMessage .= " | MySQLi Error: " . mysqli_error($db_conn);
    }

    if ($logFilePath === null) {
        // Define a default log file path if not provided, e.g., in project root logs/ directory
        // Ensure this directory is writable by the web server.
        if (!is_dir(BASE_PATH . '/logs')) {
            mkdir(BASE_PATH . '/logs', 0775, true); // Create if not exists
        }
        $logFilePath = BASE_PATH . '/logs/application_errors.log';
    }

    // Log the detailed error message
    $logEntry = "[" . date("Y-m-d H:i:s") . "] " . $errorMessage . PHP_EOL;
    error_log($logEntry, 3, $logFilePath);

    // Set a user-friendly message in the session
    if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
        session_start(); // Ensure session is active
    }
    $_SESSION['message'] = $userMessage;
    $_SESSION['message_type'] = "danger"; // Typically errors are 'danger' type for Bootstrap alerts
}


/**
 * Escapes HTML special characters for safe output.
 *
 * @param string|null $string The string to escape.
 * @return string The escaped string.
 */
function html_escape($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generates a CSRF token and stores it in the session.
 * Call this when displaying a form.
 *
 * @return string The generated CSRF token.
 */
function generate_csrf_token() {
    if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a CSRF token from POST data against the one in the session.
 * Call this when processing a form submission.
 *
 * @param string|null $token_from_form The token received from the form. If null, checks $_POST.
 * @return bool True if valid, false otherwise.
 */
function validate_csrf_token($token_from_form = null) {
    if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
    if ($token_from_form === null && isset($_POST['csrf_token'])) {
        $token_from_form = $_POST['csrf_token'];
    }

    if (!empty($_SESSION['csrf_token']) && !empty($token_from_form)) {
        if (hash_equals($_SESSION['csrf_token'], $token_from_form)) {
            // Token is valid, clear it to prevent reuse for this session (optional, depends on strategy)
            // unset($_SESSION['csrf_token']); // Or generate a new one
            return true;
        }
    }
    return false;
}

/**
 * Helper to get a setting value from the database.
 * Caches settings in a static variable to reduce DB queries per request.
 *
 * @param string $key The setting_key to retrieve.
 * @param mixed $default Default value if key not found.
 * @param mysqli $db_conn Database connection.
 * @return mixed The setting value or default.
 */
function get_setting($key, $default = null, $db_conn = null) {
    static $settings_cache = null;

    if ($settings_cache === null) {
        $settings_cache = [];
        if ($db_conn) {
            $sql = "SELECT setting_key, setting_value FROM settings";
            $result = mysqli_query($db_conn, $sql);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $settings_cache[$row['setting_key']] = $row['setting_value'];
                }
                mysqli_free_result($result);
            } else {
                // Log error fetching settings, but don't die
                handle_error("Error fetching settings for cache in get_setting(): " . mysqli_error($db_conn), "Could not load system settings.", $db_conn);
            }
        } else {
             handle_error("Database connection not provided to get_setting() for key: " . $key, "System configuration error.");
             return $default; // Cannot fetch without DB
        }
    }

    return isset($settings_cache[$key]) ? $settings_cache[$key] : $default;
}

// Example of use:
// if (!mysqli_query($conn, $sql)) {
//     handle_error("Failed to execute query: " . $sql, "Database operation failed.", $conn);
//     // Potentially redirect or display error page
// }

?>
