<?php
// Base path (should be globally configured)
$base_path = '/'; // Adjust if your application is in a subdirectory

require_once __DIR__ . '/../includes/db_connect.php'; // For $mysqli and sanitize_input()
require_once __DIR__ . '/../includes/auth.php';     // For is_logged_in() etc.

// If already logged in, redirect
if (is_logged_in()) {
    if (is_admin()) {
        header("Location: " . rtrim($base_path, '/') . "/admin/dashboard/");
    } else {
        header("Location: " . rtrim($base_path, '/') . "/pos/");
    }
    exit;
}

$error_message = '';
$success_message = '';
$username_value = '';
$email_value = '';
// Default role for new registrations - can be changed or made selectable if needed
$default_role = 'Cashier'; // As per settings table 'default_user_role'

// Fetch default role from settings if available
$sql_settings = "SELECT setting_value FROM settings WHERE setting_key = 'default_user_role'";
$result_settings = $mysqli->query($sql_settings);
if ($result_settings && $result_settings->num_rows > 0) {
    $setting_row = $result_settings->fetch_assoc();
    if (!empty($setting_row['setting_value'])) {
        $default_role = $setting_row['setting_value'];
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitize_input($mysqli, $_POST['username'] ?? '');
    $email = sanitize_input($mysqli, $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    // Role could be an input if we allow selection: $role = sanitize_input($mysqli, $_POST['role'] ?? $default_role);
    $role = $default_role; // For now, all new registrations get the default role

    // Store values for repopulating form
    $username_value = $username;
    $email_value = $email;

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        // Check if username or email already exists
        // IMPORTANT: No prepared statements. Inputs are sanitized.
        $sql_check = "SELECT id FROM users WHERE username = '$username' OR email = '$email'";
        $result_check = $mysqli->query($sql_check);

        if ($result_check && $result_check->num_rows > 0) {
            $existing_user = $result_check->fetch_assoc();
            // This simple check doesn't tell if it's username or email, but good enough for now
            $error_message = "Username or email already exists.";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user into database
            // IMPORTANT: No prepared statements. Inputs are sanitized. Role is hardcoded or from settings.
            $sql_insert = "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$hashed_password', '$role')";

            if ($mysqli->query($sql_insert)) {
                $success_message = "Registration successful! You can now <a href='" . rtrim($base_path, '/') . "/login/'>login</a>.";
                // Clear form values on success
                $username_value = '';
                $email_value = '';
            } else {
                $error_message = "Registration failed due to a system error. Please try again later.";
                // Log error: error_log("Registration SQL error: " . $mysqli->error);
            }
        }
        if (isset($result_check)) $result_check->free();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - POS System</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background-color: #f8f9fa; }
        .register-container { width: 100%; max-width: 450px; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="register-container">
        <h2 class="text-center mb-4">Create Account</h2>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" role="alert"><?php echo $success_message; // Already includes HTML link ?></div>
        <?php endif; ?>

        <?php if (empty($success_message)): // Hide form on success ?>
        <form action="<?php echo rtrim($base_path, '/') . '/register/'; ?>" method="post" novalidate>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username_value); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email_value); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password (min. 6 characters)</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            <!-- Role selection could be added here if needed -->
            <!--
            <div class="form-group">
                <label for="role">Role</label>
                <select class="form-control" id="role" name="role">
                    <option value="Cashier" <?php //echo ($default_role == 'Cashier' ? 'selected' : ''); ?>>Cashier</option>
                    <option value="Admin" <?php //echo ($default_role == 'Admin' ? 'selected' : ''); ?>>Admin (Requires Approval)</option>
                </select>
            </div>
            -->
            <button type="submit" class="btn btn-primary btn-block">Register</button>
            <p class="mt-3 text-center">
                Already have an account? <a href="<?php echo rtrim($base_path, '/') . '/login/'; ?>">Login here</a>
            </p>
        </form>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
<?php
// Close the database connection if it was opened.
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
