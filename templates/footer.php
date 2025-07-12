<?php
// Define base path - this should ideally be a global constant
$app_base_path = '/'; // IMPORTANT: Adjust this to your application's base path

// Function to make constructing URLs easier, should be identical to the one in header.php
// If not already defined (e.g. header was not included, which is unlikely for a footer)
if (!function_exists('site_url')) {
    function site_url($path = '', $base_path_var = '/') {
        $url = rtrim($base_path_var, '/');
        if (!empty($path)) {
            $url .= '/' . ltrim($path, '/');
            if (substr($url, -1) !== '/' && strpos(basename($url), '.') === false) {
                $url .= '/';
            }
        } else {
            $url .= '/';
        }
        return htmlspecialchars($url);
    }
}

$current_year = date('Y');
// Fetch store name from settings or session for footer credit
// This is just an example; for performance, load settings once.
$store_name_footer = isset($_SESSION['store_name']) ? htmlspecialchars($_SESSION['store_name']) : 'POS System';

?>
</main> <!-- /.main-content -->

<footer class="footer mt-auto py-3 bg-light">
    <div class="container text-center">
        <span class="text-muted">
            &copy; <?php echo $current_year; ?> <?php echo $store_name_footer; ?>. All rights reserved.
            <?php if (is_logged_in() && is_admin()): ?>
                 | <a href="<?php echo site_url('admin/settings', $app_base_path); ?>">System Settings</a>
            <?php endif; ?>
        </span>
    </div>
</footer>

<!-- jQuery CDN -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<!-- Popper.js CDN (required for Bootstrap dropdowns, tooltips, popovers) -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js" integrity="sha384-q9CRHqZndzlxGLOj+xrdLDJa9ittGte1NksRmgJKeCV9LN7G+FP3r9jgjP8G7WeS" crossorigin="anonymous"></script>
<!-- Bootstrap JS CDN -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" crossorigin="anonymous"></script>

<!-- Custom JS (optional - create this file if needed) -->
<script src="<?php echo site_url('assets/js/main.js', $app_base_path); ?>"></script>

<?php
// Close the database connection if it was opened and is still active.
// This is generally good practice, though PHP often closes it at script end.
// Ensure $mysqli is the correct variable name used in db_connect.php
if (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->thread_id) {
    // $mysqli->close(); // Uncomment if you want explicit closing here.
    // Be cautious if this footer is included multiple times or before all DB operations are done on a page.
}
?>
</body>
</html>
