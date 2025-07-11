<?php
// Database configuration
define('DB_SERVER', 'localhost'); // MySQL server address
define('DB_USERNAME', 'root');    // MySQL username (adjust as needed)
define('DB_PASSWORD', '');        // MySQL password (adjust as needed)
define('DB_NAME', 'pos_system_db'); // MySQL database name

// Attempt to connect to MySQL database
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn === false) {
    // Do not output mysqli_connect_error() directly in production
    // Log error or show a generic message
    die("ERROR: Could not connect to database. Please check your configuration.");
}

// Set charset to utf8mb4 (recommended)
mysqli_set_charset($conn, "utf8mb4");

// Function to safely escape strings for SQL queries
// This is crucial since we are not using prepared statements
function escape_string($conn, $string) {
    if ($conn) {
        return mysqli_real_escape_string($conn, $string);
    }
    // Fallback or error if connection is not available, though $conn should always be available here
    // For simplicity, returning the original string might be problematic.
    // It's better to ensure $conn is always passed and valid.
    // Consider throwing an exception or logging an error if $conn is invalid.
    trigger_error("Database connection is not available for escaping string.", E_USER_WARNING);
    return $string; // Or handle error more gracefully
}

// Example of how $conn would be available in other files:
// global $conn; (if functions are not class-based)
// or pass $conn as an argument to functions that need it.
?>
