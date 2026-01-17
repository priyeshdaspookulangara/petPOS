<?php
// The base path should ideally be configured globally, perhaps in db_connect.php or a config file.
// For now, we'll define it here. If your app is at http://localhost/pos_app/, $base_path should be '/pos_app/'
// If it's at http://localhost/, then $base_path = '/';
$base_path = '/'; // Adjust if your application is in a subdirectory

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

// If already logged in, redirect based on role
if (is_logged_in()) {
    if (is_admin()) {
        header("Location: " . rtrim($base_path, '/') . "/admin/dashboard/");
    } else {
        header("Location: " . rtrim($base_path, '/') . "/pos/");
    }
    exit;
}

$error_message = '';
$username_value = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = sanitize_input($mysqli, $_POST['username']);
        $password = $_POST['password']; // Password itself is not escaped before hashing/verification

        $username_value = $username; // For repopulating the form

        if (empty($username) || empty($password)) {
            $error_message = "Username and password are required.";
        } else {
            // Query to fetch user by username
            // IMPORTANT: No prepared statements as per project constraints.
            // Username is sanitized using mysqli_real_escape_string via sanitize_input().
            $sql = "SELECT id, username, password, role FROM users WHERE username = '$username'";
            $result = $mysqli->query($sql);

            if ($result) {
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    // Verify password
                    if (password_verify($password, $user['password'])) {
                        // Password is correct, start session
                        session_regenerate_id(true); // Regenerate ID on login
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['last_regen'] = time();


                        // Redirect to appropriate page based on role
                        if ($user['role'] == 'Admin') {
                            header("Location: " . rtrim($base_path, '/') . "/admin/dashboard/");
                        } else { // Cashier or other roles
                            header("Location: " . rtrim($base_path, '/') . "/pos/");
                        }
                        exit;
                    } else {
                        $error_message = "Invalid username or password.";
                    }
                } else {
                    $error_message = "Invalid username or password.";
                }
                $result->free();
            } else {
                // Query failed
                $error_message = "Login failed due to a system error. Please try again later.";
                // Log error: error_log("Login SQL error: " . $mysqli->error);
            }
        }
    } else {
        $error_message = "Please enter both username and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - POS System</title>
    <!-- Bootstrap CDN CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-center mb-4">POS System Login</h2>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        <form action="<?php echo rtrim($base_path, '/') . '/login/'; ?>" method="post" novalidate>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username_value); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
            <p class="mt-3 text-center">
                <!-- Optional: Link to registration page -->
                <!-- Don't have an account? <a href="<?php echo rtrim($base_path, '/') . '/register/'; ?>">Register here</a> -->
            </p>
        </form>
    </div>

    <!-- jQuery and Bootstrap Bundle (Popper.js included) CDN JS -->
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
