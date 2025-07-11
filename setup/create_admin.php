<?php
// This script is for one-time use to create the initial admin user.
// Run it from your browser (e.g., yoursite.com/setup/create_admin.php)
// or via PHP CLI: php setup/create_admin.php
// IMPORTANT: Delete or protect this file after use!

// Define BASE_PATH relative to this script's location
define('BASE_PATH', dirname(__DIR__)); // Assumes setup/ is in the project root

require_once BASE_PATH . '/config/db.php'; // For $conn and escape_string

// Admin user details (change these as needed)
$admin_username = 'admin';
$admin_password = 'adminpassword'; // Change this to a strong password!
$admin_email = 'admin@example.com';

// Check if the user already exists
$escaped_username = escape_string($conn, $admin_username);
$sql_check_user = "SELECT id FROM users WHERE username = '" . $escaped_username . "'";
$result_check_user = mysqli_query($conn, $sql_check_user);

if ($result_check_user && mysqli_num_rows($result_check_user) > 0) {
    echo "Admin user '" . htmlspecialchars($admin_username) . "' already exists.\n";
    mysqli_close($conn);
    exit;
} elseif (!$result_check_user) {
    echo "Error checking for existing user: " . mysqli_error($conn) . "\n";
    mysqli_close($conn);
    exit;
}

// Hash the password
$hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

if ($hashed_password === false) {
    echo "Failed to hash password.\n";
    mysqli_close($conn);
    exit;
}

// Prepare SQL query to insert admin user
// No prepared statements: ensure variables are safe or hardcoded like here for setup.
// email is also hardcoded/defined above, if it were user input, it would need escaping.
$escaped_email = escape_string($conn, $admin_email); // Good practice even for defined variable

$sql_insert_admin = "INSERT INTO users (username, password, email, role) VALUES (
    '" . $escaped_username . "',
    '" . $hashed_password . "',
    '" . $escaped_email . "',
    'admin'
)";

// Execute the query
if (mysqli_query($conn, $sql_insert_admin)) {
    echo "Admin user '" . htmlspecialchars($admin_username) . "' created successfully.\n";
    echo "Username: " . htmlspecialchars($admin_username) . "\n";
    echo "Password: " . htmlspecialchars($admin_password) . " (This is the password you set in the script)\n";
    echo "Please login and change this password if it's a default one.\n";
    echo "IMPORTANT: Delete or protect this 'setup/create_admin.php' file now!\n";
} else {
    echo "Error creating admin user: " . mysqli_error($conn) . "\n";
}

mysqli_close($conn);
?>
