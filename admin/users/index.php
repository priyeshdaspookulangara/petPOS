<?php
$page_title = "Manage Users";
$app_base_path = '/'; // Adjust if your application is in a subdirectory. Should be global.

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin(); // Ensures only admin can access

// Fetch all users to display
$users = [];
$sql = "SELECT id, username, email, role, created_at FROM users ORDER BY username ASC";
$result = $mysqli->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $result->free();
} else {
    // Handle query error, maybe set a flash message
    $_SESSION['flash_message'] = "Error fetching users: " . htmlspecialchars($mysqli->error);
    $_SESSION['flash_message_type'] = "danger";
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
        <a href="<?php echo site_url('admin/manage-user', $app_base_path); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New User
        </a>
    </div>

    <?php if (empty($users) && empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-info">No users found.</div>
    <?php elseif (!empty($users)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">User List</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="usersTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Registered On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><span class="badge badge-<?php echo ($user['role'] == 'Admin' ? 'success' : 'secondary'); ?>"><?php echo htmlspecialchars($user['role']); ?></span></td>
                                    <td><?php echo date("Y-m-d H:i", strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <a href="<?php echo site_url('admin/manage-user/?id=' . $user['id'], $app_base_path); ?>" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php // Prevent admin from deleting themselves or the primary admin (ID 1 perhaps) ?>
                                        <?php if ($user['id'] != get_current_user_id() && $user['id'] != 1 /* Assuming ID 1 is the main admin */): ?>
                                        <a href="<?php echo site_url('admin/manage-user/?action=delete&id=' . $user['id'], $app_base_path); ?>"
                                           class="btn btn-sm btn-danger" title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-danger" title="Delete" disabled><i class="fas fa-trash"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/../../templates/footer.php';
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
<?php // Optional: Add DataTables for better table interaction ?>
<!-- <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css"/>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
<script>
    // $(document).ready(function() {
    //     $('#usersTable').DataTable();
    // });
</script> -->
