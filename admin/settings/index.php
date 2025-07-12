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
    'store_logo_url' => 'Store Logo', // Add logo to managed keys
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

    // Handle Logo Upload first
    if (isset($_FILES['store_logo_file']) && $_FILES['store_logo_file']['error'] == UPLOAD_ERR_OK) {
        $upload_dir_logo = __DIR__ . '/../../assets/uploads/logo/';
        if (!is_dir($upload_dir_logo)) {
            mkdir($upload_dir_logo, 0775, true);
        }
        $logo_ext = strtolower(pathinfo($_FILES['store_logo_file']['name'], PATHINFO_EXTENSION));
        // Use a fixed name for simplicity, so we don't have to update the path in settings constantly if file type changes
        $logo_filename = 'store_logo.' . $logo_ext;
        $target_logo_file = $upload_dir_logo . $logo_filename;
        $allowed_logo_types = ['jpg', 'jpeg', 'png', 'gif'];

        if ($_FILES['store_logo_file']['size'] > 1 * 1024 * 1024) { // Max 1MB
            $errors[] = "Store Logo file is too large (Max 1MB).";
        } elseif (!in_array($logo_ext, $allowed_logo_types)) {
            $errors[] = "Invalid logo file type. Allowed: " . implode(', ', $allowed_logo_types);
        } elseif (move_uploaded_file($_FILES['store_logo_file']['tmp_name'], $target_logo_file)) {
            $logo_db_path = 'assets/uploads/logo/' . $logo_filename;
            // Save this path to settings DB
            $key = 'store_logo_url';
            $value = sanitize_input($mysqli, $logo_db_path);
            $sql_update_logo = "INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value') ON DUPLICATE KEY UPDATE setting_value = '$value'";
            if (!$mysqli->query($sql_update_logo)) {
                $errors[] = "Error saving logo path to database: " . $mysqli->error;
            } else {
                $current_settings[$key] = $value; // Update for immediate display
            }
        } else {
            $errors[] = "Sorry, there was an error uploading the logo.";
        }
    }


    // Handle other text-based settings
    foreach ($setting_keys as $key => $label) {
        if ($key === 'store_logo_url') continue; // Skip logo url, handled above

        if (isset($_POST[$key])) {
            $value = sanitize_input($mysqli, $_POST[$key]);

            if ($key === 'tax_rate_percentage' && !is_numeric($value) && !empty($value)) {
                $errors[] = "Tax Rate must be a numeric value.";
                $current_settings[$key] = $value; continue;
            }
            if ($key === 'low_stock_threshold' && !ctype_digit($value) && !empty($value)) {
                 $errors[] = "Low Stock Threshold must be a whole number.";
                 $current_settings[$key] = $value; continue;
            }
             if ($key === 'default_user_role' && !in_array($value, ['Admin', 'Cashier'])) {
                $errors[] = "Invalid Default User Role.";
                $current_settings[$key] = $value; continue;
            }

            $sql_update = "INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value') ON DUPLICATE KEY UPDATE setting_value = '$value'";

            if (!$mysqli->query($sql_update)) {
                $all_updates_successful = false;
                $errors[] = "Error updating " . htmlspecialchars($label) . ": " . htmlspecialchars($mysqli->error);
            } else {
                $current_settings[$key] = stripslashes($value);
                if ($key === 'store_name') $_SESSION['store_name'] = stripslashes($value);
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
            <form action="<?php echo site_url('admin/settings', $app_base_path); ?>" method="post" enctype="multipart/form-data">
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
                            <?php elseif ($key === 'store_logo_url'): ?>
                                <input type="file" class="form-control-file" id="store_logo_file" name="store_logo_file" accept="image/png, image/jpeg, image/gif">
                                <?php if (!empty($value)): ?>
                                <div class="mt-2">
                                    <small class="form-text text-muted">Current Logo:</small>
                                    <img src="<?php echo site_url($value, $app_base_path); ?>" alt="Current Store Logo" style="max-width: 150px; max-height: 100px; background-color: #f8f9fa; padding: 5px; border-radius: 5px;">
                                </div>
                                <?php endif; ?>
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
