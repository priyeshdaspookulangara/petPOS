<?php
// $app_base_path should be defined globally or at the top of every entry script.
$app_base_path = '/'; // Adjust if your application is in a subdirectory.

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

$page_title = "Manage User";
$user_id = null;
$edit_mode = false;

// User data for form pre-fill
$username_val = '';
$email_val = '';
$role_val = 'Cashier'; // Default for new user

// Determine if we are editing an existing user
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $edit_mode = true;
    $page_title = "Edit User";

    // Fetch user data for editing
    // IMPORTANT: No prepared statements. $user_id is cast to int, so it's safe.
    $sql_fetch_user = "SELECT username, email, role FROM users WHERE id = $user_id";
    $result_fetch_user = $mysqli->query($sql_fetch_user);
    if ($result_fetch_user && $result_fetch_user->num_rows > 0) {
        $user_data = $result_fetch_user->fetch_assoc();
        $username_val = $user_data['username'];
        $email_val = $user_data['email'];
        $role_val = $user_data['role'];
        $result_fetch_user->free();
    } else {
        $_SESSION['flash_message'] = "User not found.";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: " . site_url('admin/users', $app_base_path));
        exit;
    }
} else {
    $page_title = "Add New User";
}

// Handle DELETE request
if (isset($_GET['action']) && $_GET['action'] == 'delete' && $user_id) {
    if ($user_id == get_current_user_id()) {
        $_SESSION['flash_message'] = "You cannot delete your own account.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif ($user_id == 1) { // Assuming ID 1 is a protected super admin
         $_SESSION['flash_message'] = "This primary admin account cannot be deleted.";
         $_SESSION['flash_message_type'] = "danger";
    } else {
        // IMPORTANT: No prepared statements. $user_id is cast to int.
        $sql_delete = "DELETE FROM users WHERE id = $user_id";
        if ($mysqli->query($sql_delete)) {
            $_SESSION['flash_message'] = "User deleted successfully.";
            $_SESSION['flash_message_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Error deleting user: " . htmlspecialchars($mysqli->error);
            $_SESSION['flash_message_type'] = "danger";
        }
    }
    header("Location: " . site_url('admin/users', $app_base_path));
    exit;
}


// Handle POST request (Add or Update)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitize_input($mysqli, $_POST['username'] ?? '');
    $email = sanitize_input($mysqli, $_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; // Not sanitized before hashing
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = sanitize_input($mysqli, $_POST['role'] ?? 'Cashier');

    // Repopulate form values in case of error
    $username_val = $username;
    $email_val = $email;
    $role_val = $role;

    // Validation
    if (empty($username) || empty($email) || empty($role)) {
        $_SESSION['flash_message'] = "Username, email, and role are required.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_message'] = "Invalid email format.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif (!$edit_mode && empty($password)) { // Password required for new users
        $_SESSION['flash_message'] = "Password is required for new users.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif (!empty($password) && strlen($password) < 6) {
        $_SESSION['flash_message'] = "Password must be at least 6 characters long.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif (!empty($password) && $password !== $confirm_password) {
        $_SESSION['flash_message'] = "Passwords do not match.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif (!in_array($role, ['Admin', 'Cashier'])) {
        $_SESSION['flash_message'] = "Invalid role selected.";
        $_SESSION['flash_message_type'] = "danger";
    } else {
        // Check for username/email conflicts (excluding current user if editing)
        $conflict_check_sql = "SELECT id FROM users WHERE (username = '$username' OR email = '$email')";
        if ($edit_mode && $user_id) {
            $conflict_check_sql .= " AND id != $user_id";
        }
        $conflict_result = $mysqli->query($conflict_check_sql);

        if ($conflict_result && $conflict_result->num_rows > 0) {
            $_SESSION['flash_message'] = "Username or email already taken by another user.";
            $_SESSION['flash_message_type'] = "danger";
            $conflict_result->free();
        } else {
            if ($edit_mode && $user_id) {
                // Update existing user
                $sql_update = "UPDATE users SET username = '$username', email = '$email', role = '$role'";
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql_update .= ", password = '$hashed_password'";
                }
                $sql_update .= " WHERE id = $user_id";

                if ($mysqli->query($sql_update)) {
                    $_SESSION['flash_message'] = "User updated successfully.";
                    $_SESSION['flash_message_type'] = "success";
                    if ($user_id == get_current_user_id()){ // If admin edits their own details
                         $_SESSION['username'] = $username; // Update session username
                         $_SESSION['user_role'] = $role; // Update session role
                    }
                    header("Location: " . site_url('admin/users', $app_base_path));
                    exit;
                } else {
                    $_SESSION['flash_message'] = "Error updating user: " . htmlspecialchars($mysqli->error);
                    $_SESSION['flash_message_type'] = "danger";
                }
            } else {
                // Add new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql_insert = "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$hashed_password', '$role')";
                if ($mysqli->query($sql_insert)) {
                    $_SESSION['flash_message'] = "User added successfully.";
                    $_SESSION['flash_message_type'] = "success";
                    header("Location: " . site_url('admin/users', $app_base_path));
                    exit;
                } else {
                    $_SESSION['flash_message'] = "Error adding user: " . htmlspecialchars($mysqli->error);
                    $_SESSION['flash_message_type'] = "danger";
                }
            }
        }
    }
}


include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo $edit_mode ? "Edit User Details" : "Add New User Form"; ?></h6>
        </div>
        <div class="card-body">
            <form action="<?php echo site_url('admin/manage-user/' . ($edit_mode && $user_id ? '?id=' . $user_id : ''), $app_base_path); ?>" method="post" novalidate>
                <div class="form-group">
                    <label for="username">Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username_val); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email_val); ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password <?php echo $edit_mode ? "(Leave blank to keep current)" : "<span class='text-danger'>*</span> (min. 6 chars)"; ?></label>
                    <input type="password" class="form-control" id="password" name="password" <?php echo !$edit_mode ? 'required' : ''; ?>>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <?php echo !$edit_mode ? "<span class='text-danger'>*</span>" : ""; ?></label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" <?php echo !$edit_mode ? 'required' : ''; ?>>
                </div>
                <div class="form-group">
                    <label for="role">Role <span class="text-danger">*</span></label>
                    <select class="form-control" id="role" name="role" required>
                        <option value="Cashier" <?php echo ($role_val == 'Cashier') ? 'selected' : ''; ?>>Cashier</option>
                        <option value="Admin" <?php echo ($role_val == 'Admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_mode ? '<i class="fas fa-save"></i> Update User' : '<i class="fas fa-plus-circle"></i> Add User'; ?>
                </button>
                <a href="<?php echo site_url('admin/users', $app_base_path); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </form>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../templates/footer.php';
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
