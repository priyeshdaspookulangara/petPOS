</main> <!-- /.container-fluid -->

<footer class="footer mt-auto py-3 bg-light">
    <div class="container text-center">
        <span class="text-muted">POS System &copy; <?php echo date("Y"); ?>. All Rights Reserved.</span>
    </div>
</footer>

<!-- jQuery CDN -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<!-- Popper.js CDN (required for Bootstrap dropdowns, tooltips, popovers) -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script> <!-- Note: Bootstrap 4 might prefer Popper.js v1.x, but v2.x is more current. For BS 4.5.2, jQuery slim and Popper.js v1.16.1 is often used. -->
<!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script> -->
<!-- Bootstrap JS CDN -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<!-- Custom JS (if any) -->
<?php
    // A bit of PHP to construct the base URL for assets if not already available
    $protocol_footer = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host_footer = $_SERVER['HTTP_HOST'];
    $script_name_footer = $_SERVER['SCRIPT_NAME']; // e.g., /pos_project/index.php
    $base_path_footer = dirname($script_name_footer); // e.g., /pos_project
    if ($base_path_footer === '/' || $base_path_footer === '\\') {
        $base_path_footer = ''; // Avoid double slashes if root
    }
    $base_url_footer = $protocol_footer . "://" . $host_footer . $base_path_footer;
?>
<?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . $base_path_footer . '/assets/js/main.js')): ?>
    <script src="<?php echo $base_url_footer; ?>/assets/js/main.js"></script>
<?php endif; ?>

<script>
// Generic confirm delete
function confirmDelete(url) {
    if (confirm("Are you sure you want to delete this item?")) {
        window.location.href = url;
    }
}
</script>

</body>
</html>
