<?php
// This template is included by modules/settings/index.php
// It has access to $current_settings, $settings_feedback, and $base_url

if (!defined('BASE_PATH')) {
    // This should ideally not happen if included correctly
    die("Access denied: BASE_PATH not defined.");
}

// Ensure $current_settings is an array, even if empty, to avoid errors
if (!is_array($current_settings)) {
    $current_settings = [];
}

// Helper function to safely get setting value
function get_setting_value($settings_array, $key, $default = '') {
    return isset($settings_array[$key]) ? htmlspecialchars($settings_array[$key]) : htmlspecialchars($default);
}

?>
<div class="container mt-4">
    <h2>System Settings</h2>
    <p>Manage general store and system settings here.</p>

    <?php
    // Display any feedback messages from the controller (modules/settings/index.php)
    if (!empty($settings_feedback)):
        foreach ($settings_feedback as $feedback):
    ?>
        <div class="alert alert-<?php echo htmlspecialchars($feedback['type']); ?>" role="alert">
            <?php echo htmlspecialchars($feedback['message']); ?>
        </div>
    <?php
        endforeach;
    endif;
    ?>

    <?php
    // Session messages are displayed by header.php, but if there's a redirect
    // immediately after form processing, they are the primary way to show success/failure.
    // If $settings_feedback is used for errors during load/display, that's fine.
    ?>

    <form action="<?php echo $base_url; ?>/index.php?module=settings&action=index" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <div class="card mb-4">
            <div class="card-header">
                Store Information
            </div>
            <div class="card-body">
                <div class="form-group row">
                    <label for="store_name" class="col-sm-3 col-form-label">Store Name</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="store_name" name="store_name" value="<?php echo get_setting_value($current_settings, 'store_name', 'My POS Store'); ?>">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="store_address" class="col-sm-3 col-form-label">Store Address</label>
                    <div class="col-sm-9">
                        <textarea class="form-control" id="store_address" name="store_address" rows="2"><?php echo get_setting_value($current_settings, 'store_address', '123 Main Street'); ?></textarea>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="store_phone" class="col-sm-3 col-form-label">Store Phone</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="store_phone" name="store_phone" value="<?php echo get_setting_value($current_settings, 'store_phone', '+1-555-123-4567'); ?>">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="store_email" class="col-sm-3 col-form-label">Store Email</label>
                    <div class="col-sm-9">
                        <input type="email" class="form-control" id="store_email" name="store_email" value="<?php echo get_setting_value($current_settings, 'store_email', 'contact@myposstore.com'); ?>">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="store_logo_url" class="col-sm-3 col-form-label">Store Logo URL</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="store_logo_url" name="store_logo_url" value="<?php echo get_setting_value($current_settings, 'store_logo_url', 'assets/images/default_logo.png'); ?>">
                        <small class="form-text text-muted">Relative path (e.g., assets/images/logo.png) or absolute URL.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                Financial & Receipt Settings
            </div>
            <div class="card-body">
                <div class="form-group row">
                    <label for="tax_rate_percentage" class="col-sm-3 col-form-label">Sales Tax Rate (%)</label>
                    <div class="col-sm-9">
                        <input type="number" step="0.01" class="form-control" id="tax_rate_percentage" name="tax_rate_percentage" value="<?php echo get_setting_value($current_settings, 'tax_rate_percentage', '7.5'); ?>">
                        <small class="form-text text-muted">Enter as a percentage, e.g., 7.5 for 7.5%.</small>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="currency_symbol" class="col-sm-3 col-form-label">Currency Symbol</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="currency_symbol" name="currency_symbol" value="<?php echo get_setting_value($current_settings, 'currency_symbol', '$'); ?>" style="width: 100px;">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="receipt_footer_message" class="col-sm-3 col-form-label">Receipt Footer Message</label>
                    <div class="col-sm-9">
                        <textarea class="form-control" id="receipt_footer_message" name="receipt_footer_message" rows="2"><?php echo get_setting_value($current_settings, 'receipt_footer_message', 'Thank you for your business!'); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group text-right">
            <button type="submit" name="save_settings" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
        </div>
    </form>
</div>

<script>
// Add any client-side validation or interactivity if needed in the future.
// For example, ensuring tax rate is a valid number.
document.addEventListener('DOMContentLoaded', function() {
    // Example: Client-side validation for tax rate
    const taxRateInput = document.getElementById('tax_rate_percentage');
    if (taxRateInput) {
        taxRateInput.addEventListener('input', function() {
            if (parseFloat(this.value) < 0) {
                this.value = '0';
            }
            // Could add more complex validation and feedback here
        });
    }
});
</script>
