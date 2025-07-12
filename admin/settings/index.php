<?php
$page_title = "System Settings";
$app_base_path = '/'; // Adjust if your application is in a subdirectory. Should be global.

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

// Define the settings keys we want to manage
$setting_keys = [
    'store_name' => 'Store Name',
    'store_address' => 'Store Address',
    'tax_rate_percentage' => 'Tax Rate (%)',
    'currency_symbol' => 'Currency Symbol',
    'receipt_footer_message' => 'Receipt Footer Message',
    'default_user_role' => 'Default New User Role',
    'low_stock_threshold' => 'Low Stock Alert Threshold'
];

// Fetch current settings
$current_settings = [];
$sql_fetch_settings = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN (";
$placeholders = [];
foreach (array_keys($setting_keys) as $key) {
    $placeholders[] = "'" . sanitize_input($mysqli, $key) . "'";
}
$sql_fetch_settings .= implode(',', $placeholders) . ")";

$result_settings = $mysqli->query($sql_fetch_settings);
if ($result_settings) {
    while ($row = $result_settings->fetch_assoc()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
    $result_settings->free();
} else {
    $_SESSION['flash_message'] = "Error fetching settings: " . htmlspecialchars($mysqli->error);
    $_SESSION['flash_message_type'] = "danger";
}

// Handle POST request to update settings
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $all_updates_successful = true;
    $errors = [];

    foreach ($setting_keys as $key => $label) {
        if (isset($_POST[$key])) {
            $value = sanitize_input($mysqli, $_POST[$key]);

            // Specific validation if needed
            if ($key === 'tax_rate_percentage' && !is_numeric($value) && !empty($value)) {
                $errors[] = "Tax Rate must be a numeric value.";
                $current_settings[$key] = $value; // Keep user input for form repopulation
                continue;
            }
            if ($key === 'low_stock_threshold' && !ctype_digit($value) && !empty($value)) {
                 $errors[] = "Low Stock Threshold must be a whole number.";
                 $current_settings[$key] = $value;
                 continue;
            }
             if ($key === 'default_user_role' && !in_array($value, ['Admin', 'Cashier'])) {
                $errors[] = "Invalid Default User Role.";
                $current_settings[$key] = $value;
                continue;
            }


            // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both new and existing settings
            // Ensure setting_key is UNIQUE in your DB schema for this to work correctly.
            $sql_update = "INSERT INTO settings (setting_key, setting_value)
                           VALUES ('$key', '$value')
                           ON DUPLICATE KEY UPDATE setting_value = '$value'";

            if (!$mysqli->query($sql_update)) {
                $all_updates_successful = false;
                $errors[] = "Error updating " . htmlspecialchars($label) . ": " . htmlspecialchars($mysqli->error);
            } else {
                // Update current settings array for immediate display and session if needed
                $current_settings[$key] = stripslashes($value); // Display the raw value post-update
                if ($key === 'store_name') { // Update session store name if changed
                    $_SESSION['store_name'] = stripslashes($value);
                }
            }
        }
    }

    if (!empty($errors)) {
        $_SESSION['flash_message'] = "Failed to update some settings:<br>" . implode("<br>", $errors);
        $_SESSION['flash_message_type'] = "danger";
    } elseif ($all_updates_successful) {
        $_SESSION['flash_message'] = "Settings updated successfully.";
        $_SESSION['flash_message_type'] = "success";
    } else {
         $_SESSION['flash_message'] = "Some settings might not have been updated due to errors.";
        $_SESSION['flash_message_type'] = "warning";
    }
    // No redirect, just show messages and updated form.
}


include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Configure System Settings</h6>
        </div>
        <div class="card-body">
            <form action="<?php echo site_url('admin/settings', $app_base_path); ?>" method="post">
                <?php foreach ($setting_keys as $key => $label): ?>
                    <?php $value = isset($current_settings[$key]) ? htmlspecialchars($current_settings[$key]) : ''; ?>
                    <div class="form-group row">
                        <label for="<?php echo $key; ?>" class="col-sm-4 col-form-label"><?php echo htmlspecialchars($label); ?></label>
                        <div class="col-sm-8">
                            <?php if ($key === 'receipt_footer_message' || $key === 'store_address'): ?>
                                <textarea class="form-control" id="<?php echo $key; ?>" name="<?php echo $key; ?>" rows="3"><?php echo $value; ?></textarea>
                            <?php elseif ($key === 'default_user_role'): ?>
                                <select class="form-control" id="<?php echo $key; ?>" name="<?php echo $key; ?>">
                                    <option value="Cashier" <?php echo ($value == 'Cashier' ? 'selected' : ''); ?>>Cashier</option>
                                    <option value="Admin" <?php echo ($value == 'Admin' ? 'selected' : ''); ?>>Admin</option>
                                </select>
                            <?php elseif ($key === 'tax_rate_percentage'): ?>
                                 <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo $value; ?>">
                                    <div class="input-group-append">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                            <?php elseif ($key === 'low_stock_threshold'): ?>
                                <input type="number" step="1" min="0" class="form-control" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo $value; ?>">
                            <?php else: ?>
                                <input type="text" class="form-control" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo $value; ?>">
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="form-group row">
                    <div class="col-sm-8 offset-sm-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                </div>
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
