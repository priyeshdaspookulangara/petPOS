<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
require_once BASE_PATH . '/config/db.php'; // For $conn and session checks if any

if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Security check: Only admins can access this page
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Access denied. You must be an admin to perform this action.";
    $_SESSION['message_type'] = "danger";
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $app_path = dirname($_SERVER['SCRIPT_NAME']);
     if ($app_path === '/' || $app_path === '\\') $app_path = '';
    header("Location: " . $protocol . "://" . $host . $app_path . "/index.php?page=dashboard");
    exit;
}

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME'],2);
if (substr($base_url, -1) == '/') $base_url = substr($base_url, 0, -1);

// Placeholder for form handling logic (to be implemented later)
$username = '';
$email = '';
$role = 'cashier'; // Default role
$form_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form submission logic will go here in a later step.
    // For now, just a message.
    $_SESSION['message'] = "User creation form submitted (functionality pending).";
    $_SESSION['message_type'] = "info";
    // Redirect to user list or same page to prevent form resubmission
    header("Location: " . $base_url . "/index.php?module=users&action=create");
    exit;
}

?>

<div class="container mt-4">
    <h2>Add New User</h2>
    <p>This page will contain a form to add new users (e.g., cashiers or other admins) to the system.</p>

    <div class="alert alert-info" role="alert">
      Full user creation functionality, including input validation, password assignment, and database insertion, will be implemented in a later phase as per the project plan (Step F under User Management).
    </div>

    <form action="<?php echo $base_url; ?>/index.php?module=users&action=create" method="POST">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required disabled>
            <?php // if(isset($form_errors['username'])) echo '<small class="form-text text-danger">' . $form_errors['username'] . '</small>'; ?>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required disabled>
            <?php // if(isset($form_errors['email'])) echo '<small class="form-text text-danger">' . $form_errors['email'] . '</small>'; ?>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" class="form-control" id="password" name="password" required disabled>
            <?php // if(isset($form_errors['password'])) echo '<small class="form-text text-danger">' . $form_errors['password'] . '</small>'; ?>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required disabled>
            <?php // if(isset($form_errors['confirm_password'])) echo '<small class="form-text text-danger">' . $form_errors['confirm_password'] . '</small>'; ?>
        </div>
        <div class="form-group">
            <label for="role">Role</label>
            <select class="form-control" id="role" name="role" disabled>
                <option value="cashier" <?php echo ($role === 'cashier' ? 'selected' : ''); ?>>Cashier</option>
                <option value="admin" <?php echo ($role === 'admin' ? 'selected' : ''); ?>>Admin</option>
            </select>
            <?php // if(isset($form_errors['role'])) echo '<small class="form-text text-danger">' . $form_errors['role'] . '</small>'; ?>
        </div>
        <button type="submit" class="btn btn-primary" disabled>Create User (Disabled)</button>
        <a href="<?php echo $base_url; ?>/index.php?module=users&action=index" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php
// mysqli_close($conn); // Close connection if opened for this script specifically
?>
