<?php
// Basic router/dispatcher
session_start();

// Database connection
require_once 'config/db.php';

// Helper functions
require_once 'includes/functions.php';

// Define APP_INDEX_URL for consistent link generation
$protocol_app = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host_app = $_SERVER['HTTP_HOST'];
$script_name_app = $_SERVER['SCRIPT_NAME']; // Correctly points to index.php
define('APP_INDEX_URL', $protocol_app . "://" . $host_app . $script_name_app);

// Define default page
$page = isset($_GET['page']) ? $_GET['page'] : 'login'; // Default to login if not logged in, else dashboard
$module = isset($_GET['module']) ? $_GET['module'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : 'index'; // Default action for a module

// Simple routing logic
// More advanced routing will be handled by .htaccess and specific module handlers

// Include header
include_once 'templates/header.php';

// Page/Module inclusion logic
if ($page === 'login' && !isset($_SESSION['user_id'])) {
    include_once 'modules/users/login.php';
} elseif ($page === 'logout') {
    include_once 'modules/users/logout.php';
} elseif (isset($_SESSION['user_id'])) {
    // Main content based on page or module
    // This will be expanded significantly
    if (!empty($module)) {
        $module_path = "modules/{$module}/{$action}.php";
        if (file_exists($module_path)) {
            include_once $module_path;
        } else {
            echo "<div class='container mt-4'><p class='alert alert-danger'>Module action not found: {$module}/{$action}</p></div>";
        }
    } elseif ($page === 'dashboard' || $page === 'login') { // 'login' here means user is logged in but tried to access login page, redirect to dashboard
         // Simple dashboard placeholder
        if (!file_exists('modules/dashboard/index.php')) {
            if (!is_dir('modules/dashboard')) {
                mkdir('modules/dashboard', 0777, true);
            }
            file_put_contents('modules/dashboard/index.php', '<?php echo "<div class=\\"container\\"><h1 class=\\"mt-4\\">Dashboard</h1><p>Welcome to the POS system.</p></div>"; ?>');
        }
        include_once 'modules/dashboard/index.php';
    } else {
        // Fallback for other top-level pages if defined
        $page_path = "modules/{$page}/index.php"; // Assuming top-level pages are like modules with an index action
        if(file_exists($page_path)){
            include_once $page_path;
        } else {
            // If user is logged in and page not found, redirect to dashboard or show error
            // For now, simple error
            echo "<div class='container mt-4'><p class='alert alert-danger'>Page not found: {$page}</p></div>";
             if (!file_exists('modules/dashboard/index.php')) {
                if (!is_dir('modules/dashboard')) {
                    mkdir('modules/dashboard', 0777, true);
                }
                file_put_contents('modules/dashboard/index.php', '<?php echo "<div class=\\"container\\"><h1 class=\\"mt-4\\">Dashboard</h1><p>Welcome to the POS system.</p></div>"; ?>');
            }
            include_once 'modules/dashboard/index.php'; // Default to dashboard
        }
    }
} else {
    // If not logged in and not trying to access login page, redirect to login
    include_once 'modules/users/login.php';
}


// Include footer
include_once 'templates/footer.php';

// Close DB connection (optional, PHP does this at script end)
// $conn->close(); // $conn will be defined in db.php
?>
