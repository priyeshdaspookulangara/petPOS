<?php
// Base path (should be globally configured)
$base_path = '/'; // Adjust if your application is in a subdirectory

// auth.php starts session if not already started, and contains logout_user()
require_once __DIR__ . '/../includes/auth.php';

// Call the logout function.
// It handles session destruction and redirection.
// Default redirection is to '/login/', which is fine here.
logout_user('login/', $base_path);

// No further output or HTML is needed as logout_user() calls exit.
?>
