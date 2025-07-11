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
    $_SESSION['message'] = "Access denied. You must be an admin to view this page.";
    $_SESSION['message_type'] = "danger";
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $app_path = dirname($_SERVER['SCRIPT_NAME']);
     if ($app_path === '/' || $app_path === '\\') $app_path = '';
    header("Location: " . $protocol . "://" . $host . $app_path . "/index.php?page=dashboard");
    exit;
}

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME'],2);
if (substr($base_url, -1) == '/') $base_url = substr($base_url, 0, -1); // Remove trailing slash if exists from dirname

?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col">
            <h2>Manage Users</h2>
        </div>
        <div class="col text-right">
            <a href="<?php echo $base_url; ?>/index.php?module=users&action=create" class="btn btn-success"><i class="fas fa-plus"></i> Add New User</a>
        </div>
    </div>

    <p>User listing and management interface will be implemented here. Admins will be able to view, edit, and delete users.</p>
    <p>Current features under development include:</p>
    <ul>
        <li>Displaying a table of all registered users (username, email, role, created_at).</li>
        <li>Options to edit user details (e.g., change role, reset password - though password reset needs careful implementation).</li>
        <li>Option to delete users.</li>
        <li>Pagination for large number of users.</li>
        <li>Search/filter functionality.</li>
    </ul>
    <div class="alert alert-info" role="alert">
      Full user management (CRUD operations) will be built out in a later phase as per the project plan (Step F under User Management).
    </div>
</div>

<?php
// Example: Fetch and list users (very basic)
// $sql_users = "SELECT id, username, email, role, created_at FROM users ORDER BY username ASC";
// $result_users = mysqli_query($conn, $sql_users);
// if ($result_users && mysqli_num_rows($result_users) > 0) {
//     echo "<table class='table table-striped table-bordered mt-3'>";
//     echo "<thead class='thead-dark'><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Registered</th><th>Actions</th></tr></thead><tbody>";
//     while ($user = mysqli_fetch_assoc($result_users)) {
//         echo "<tr>";
//         echo "<td>" . htmlspecialchars($user['id']) . "</td>";
//         echo "<td>" . htmlspecialchars($user['username']) . "</td>";
//         echo "<td>" . htmlspecialchars($user['email']) . "</td>";
//         echo "<td>" . htmlspecialchars(ucfirst($user['role'])) . "</td>";
//         echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
//         echo "<td><a href='#' class='btn btn-sm btn-info disabled'>Edit</a> <a href='#' class='btn btn-sm btn-danger disabled'>Delete</a></td>";
//         echo "</tr>";
//     }
//     echo "</tbody></table>";
//     mysqli_free_result($result_users);
// } else {
//     echo "<p class='mt-3'>No users found.</p>";
// }
// mysqli_close($conn); // Close connection if opened for this script specifically
?>
