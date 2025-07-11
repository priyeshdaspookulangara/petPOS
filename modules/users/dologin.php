<?php
if (!defined('BASE_PATH')) {
    // Attempt to define BASE_PATH if not already defined
    // This is for direct access testing; ideally, all requests go through index.php
    $current_dir = dirname(__FILE__); // modules/users
    $base_path_parts = explode(DIRECTORY_SEPARATOR, $current_dir);
    // Assuming 'modules' is a direct child of the project root
    $project_root_index = array_search('modules', $base_path_parts);
    if ($project_root_index !== false) {
        $project_root_parts = array_slice($base_path_parts, 0, $project_root_index);
        define('BASE_PATH', implode(DIRECTORY_SEPARATOR, $project_root_parts));
    } else {
        // Fallback or error if structure is not as expected
        // This path might be incorrect if the file is moved or structure changes
        define('BASE_PATH', dirname(__DIR__, 2)); // Adjust if structure is different
    }
}

require_once BASE_PATH . '/config/db.php';

if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . $base_url . "/index.php?page=dashboard");
    exit;
}

$login_error = '';
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME'], 2);


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['username']) || !isset($_POST['password'])) {
        $_SESSION['message'] = "Username and password are required.";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $base_url . "/index.php?page=login");
        exit;
    }

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $_SESSION['message'] = "Username and password cannot be empty.";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $base_url . "/index.php?page=login");
        exit;
    }

    // Use the escape_string function from db.php
    $escaped_username = escape_string($conn, $username);

    // Query to fetch user by username
    // IMPORTANT: No prepared statements as per requirement. Variables directly in query AFTER escaping.
    $sql = "SELECT id, username, password, role, email FROM users WHERE username = '" . $escaped_username . "'";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Password is correct, start session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_email'] = $user['email']; // Store email if needed

                // Regenerate session ID for security
                session_regenerate_id(true);

                $_SESSION['message'] = "Login successful. Welcome, " . htmlspecialchars($user['username']) . "!";
                $_SESSION['message_type'] = "success";
                header("Location: " . $base_url . "/index.php?page=dashboard"); // Redirect to dashboard
                exit;
            } else {
                // Invalid password
                $_SESSION['message'] = "Invalid username or password.";
                $_SESSION['message_type'] = "danger";
            }
        } else {
            // No user found with that username
            $_SESSION['message'] = "Invalid username or password."; // Generic message for security
            $_SESSION['message_type'] = "danger";
        }
        mysqli_free_result($result);
    } else {
        // SQL query error
        // In production, log this error instead of showing to user
        error_log("SQL Error in dologin.php: " . mysqli_error($conn));
        $_SESSION['message'] = "An error occurred. Please try again later.";
        $_SESSION['message_type'] = "danger";
    }

    // If login failed, redirect back to login page
    header("Location: " . $base_url . "/index.php?page=login");
    exit;

} else {
    // If not a POST request, redirect to login page or show error
    $_SESSION['message'] = "Invalid request method.";
    $_SESSION['message_type'] = "warning";
    header("Location: " . $base_url . "/index.php?page=login");
    exit;
}

// Close connection (optional as PHP auto-closes)
// mysqli_close($conn); // $conn is from db.php
?>
