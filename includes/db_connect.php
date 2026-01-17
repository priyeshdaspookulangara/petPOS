<?php
// Database configuration
define('DB_SERVER', 'localhost'); // Database server hostname (usually localhost)
define('DB_USERNAME', 'root');    // Database username - CHANGE THIS for production
define('DB_PASSWORD', '');        // Database password - CHANGE THIS for production
define('DB_NAME', 'pos_system');  // Database name

// Attempt to connect to MySQL database
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($mysqli === false){
    // If connection fails, kill the script and show an error.
    // In a production environment, you might want to log this error instead of showing it to the user.
    die("ERROR: Could not connect to database. " . $mysqli->connect_error);
}

// Set character set to utf8mb4 (optional, but good for multi-language support)
if (!$mysqli->set_charset("utf8mb4")) {
    // printf("Error loading character set utf8mb4: %s\n", $mysqli->error);
    // In production, you might log this error. For now, we can ignore if it fails silently.
}

// Function to sanitize input before using in a query.
// IMPORTANT: This is a basic sanitization. For robust security, prepared statements are recommended,
// but the project explicitly forbids them. This function is a requirement of the project constraints.
function sanitize_input($mysqli_conn, $input) {
    if (is_array($input)) {
        $sanitized_array = array();
        foreach ($input as $key => $value) {
            $sanitized_array[$key] = sanitize_input($mysqli_conn, $value); // Recursively sanitize array elements
        }
        return $sanitized_array;
    } else {
        // Ensure the input is a string before escaping
        // Trim whitespace from the beginning and end of the string
        $trimmed_input = trim((string)$input);
        // Escape special characters in a string for use in an SQL statement
        return $mysqli_conn->real_escape_string($trimmed_input);
    }
}

// Example of how to use sanitize_input (do not run this here, just for illustration)
/*
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitize_input($mysqli, $_POST['username']);
    $email = sanitize_input($mysqli, $_POST['email']);
    // Now $username and $email can be used in a query
    // $sql = "INSERT INTO users (username, email) VALUES ('$username', '$email')";
}
*/

// The $mysqli object can now be used by any script that includes this file.
// Example: require_once 'includes/db_connect.php';
// Then use $mysqli->query("SELECT * FROM ...");

// Optional: You can also include a function to close the connection,
// though PHP usually handles this automatically at the end of script execution.
/*
function close_db_connection($mysqli_conn) {
    $mysqli_conn->close();
}
*/
?>
