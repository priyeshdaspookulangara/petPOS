<?php
$app_base_path = '/';

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();
$current_user_id = get_current_user_id();

$page_title = "Manage Expense";
$expense_id = null;
$edit_mode = false;

// Form data
$expense_category_id_val = '';
$amount_val = '';
$description_val = '';
$expense_date_val = date('Y-m-d');
$receipt_url_val = ''; // Store existing URL for edit mode

// Fetch expense categories for dropdown
$expense_categories = [];
$cat_sql = "SELECT id, name FROM expense_categories ORDER BY name ASC";
$cat_result = $mysqli->query($cat_sql);
if ($cat_result) while ($row = $cat_result->fetch_assoc()) $expense_categories[] = $row;
if ($cat_result) $cat_result->free();


if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $expense_id = (int)$_GET['id'];
    $edit_mode = true;
    $page_title = "Edit Expense Record";

    $sql_fetch = "SELECT * FROM expenses WHERE id = $expense_id";
    $result_fetch = $mysqli->query($sql_fetch);
    if ($result_fetch && $result_fetch->num_rows > 0) {
        $expense_data = $result_fetch->fetch_assoc();
        $expense_category_id_val = $expense_data['expense_category_id'];
        $amount_val = $expense_data['amount'];
        $description_val = $expense_data['description'];
        $expense_date_val = $expense_data['expense_date']; // Already in Y-m-d
        $receipt_url_val = $expense_data['receipt_url'];
        $result_fetch->free();
    } else {
        $_SESSION['flash_message'] = "Expense record not found.";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: " . site_url('admin/expenses', $app_base_path));
        exit;
    }
} else {
    $page_title = "Add New Expense Record";
}

// Handle DELETE request
if (isset($_GET['action']) && $_GET['action'] == 'delete' && $expense_id) {
    // Fetch receipt_url before deleting to remove file
    $sql_get_receipt = "SELECT receipt_url FROM expenses WHERE id = $expense_id";
    $receipt_res = $mysqli->query($sql_get_receipt);
    $old_receipt_path_for_delete = null;
    if($receipt_res && $receipt_data = $receipt_res->fetch_assoc()){
        $old_receipt_path_for_delete = $receipt_data['receipt_url'];
    }
    if($receipt_res) $receipt_res->free();

    $sql_delete = "DELETE FROM expenses WHERE id = $expense_id";
    if ($mysqli->query($sql_delete)) {
        // If deletion successful, try to remove the associated receipt file
        if (!empty($old_receipt_path_for_delete) && !filter_var($old_receipt_path_for_delete, FILTER_VALIDATE_URL)) {
            $full_file_path = __DIR__ . '/../../' . $old_receipt_path_for_delete;
            if (file_exists($full_file_path)) {
                unlink($full_file_path);
            }
        }
        $_SESSION['flash_message'] = "Expense record deleted successfully.";
        $_SESSION['flash_message_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Error deleting expense: " . htmlspecialchars($mysqli->error);
        $_SESSION['flash_message_type'] = "danger";
    }
    header("Location: " . site_url('admin/expenses', $app_base_path));
    exit;
}

// Handle POST request (Add or Update)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $expense_category_id = isset($_POST['expense_category_id']) ? (int)$_POST['expense_category_id'] : null;
    $amount = isset($_POST['amount']) ? sanitize_input($mysqli, $_POST['amount']) : ''; // Sanitize, will validate as float
    $description = isset($_POST['description']) ? sanitize_input($mysqli, $_POST['description']) : '';
    $expense_date = isset($_POST['expense_date']) ? sanitize_input($mysqli, $_POST['expense_date']) : '';
    $uploaded_receipt_path = $receipt_url_val; // Keep old one if not changed

    // Repopulate form
    $expense_category_id_val = $expense_category_id;
    $amount_val = $amount;
    $description_val = $description;
    $expense_date_val = $expense_date;

    // Receipt Upload Handling
    if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] == UPLOAD_ERR_OK) {
        $upload_dir_receipts = __DIR__ . '/../../assets/uploads/receipts/';
        if (!is_dir($upload_dir_receipts)) {
            mkdir($upload_dir_receipts, 0775, true);
        }
        $receipt_filename = uniqid('rcpt_', true) . "_" . basename($_FILES['receipt_file']['name']);
        $target_receipt_file = $upload_dir_receipts . $receipt_filename;
        $receipt_file_type = strtolower(pathinfo($target_receipt_file, PATHINFO_EXTENSION));
        $allowed_receipt_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'];

        if ($_FILES['receipt_file']['size'] > 5 * 1024 * 1024) { // Max 5MB
            $_SESSION['flash_message'] = "Receipt file is too large (Max 5MB).";
            $_SESSION['flash_message_type'] = "danger";
        } elseif (!in_array($receipt_file_type, $allowed_receipt_types)) {
            $_SESSION['flash_message'] = "Invalid receipt file type. Allowed: " . implode(', ', $allowed_receipt_types);
            $_SESSION['flash_message_type'] = "danger";
        } elseif (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $target_receipt_file)) {
            // Delete old receipt file if a new one is uploaded during edit
            if ($edit_mode && !empty($receipt_url_val) && $receipt_url_val != 'assets/uploads/receipts/' . $receipt_filename && !filter_var($receipt_url_val, FILTER_VALIDATE_URL)) {
                 $old_receipt_full_path = __DIR__ . '/../../' . $receipt_url_val;
                 if(file_exists($old_receipt_full_path)) unlink($old_receipt_full_path);
            }
            $uploaded_receipt_path = 'assets/uploads/receipts/' . $receipt_filename;
        } else {
            $_SESSION['flash_message'] = "Sorry, there was an error uploading your receipt file.";
            $_SESSION['flash_message_type'] = "danger";
        }
    }


    // Validation
    if (empty($expense_category_id) || empty($amount) || empty($expense_date)) {
        $_SESSION['flash_message'] = "Category, Amount, and Expense Date are required.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif (!is_numeric($amount) || (float)$amount <= 0) {
        $_SESSION['flash_message'] = "Amount must be a positive number.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif (strtotime($expense_date) === false) {
        $_SESSION['flash_message'] = "Invalid expense date format.";
        $_SESSION['flash_message_type'] = "danger";
    } else {
        // Format amount to 2 decimal places for database
        $amount_db = number_format((float)$amount, 2, '.', '');

        if ($edit_mode) {
            $sql_update = "UPDATE expenses SET
                            expense_category_id = $expense_category_id,
                            amount = '$amount_db',
                            description = '$description',
                            expense_date = '$expense_date',
                            receipt_url = " . ($uploaded_receipt_path ? "'$uploaded_receipt_path'" : "NULL") . ",
                            user_id = $current_user_id -- Update user_id if editor changes
                           WHERE id = $expense_id";
            if ($mysqli->query($sql_update)) {
                $_SESSION['flash_message'] = "Expense record updated successfully.";
                $_SESSION['flash_message_type'] = "success";
                header("Location: " . site_url('admin/expenses', $app_base_path));
                exit;
            } else {
                $_SESSION['flash_message'] = "Error updating expense: " . htmlspecialchars($mysqli->error);
                $_SESSION['flash_message_type'] = "danger";
            }
        } else {
            $sql_insert = "INSERT INTO expenses (expense_category_id, amount, description, expense_date, receipt_url, user_id)
                           VALUES ($expense_category_id, '$amount_db', '$description', '$expense_date',
                                   " . ($uploaded_receipt_path ? "'$uploaded_receipt_path'" : "NULL") . ", $current_user_id)";
            if ($mysqli->query($sql_insert)) {
                $_SESSION['flash_message'] = "Expense record added successfully.";
                $_SESSION['flash_message_type'] = "success";
                header("Location: " . site_url('admin/expenses', $app_base_path));
                exit;
            } else {
                $_SESSION['flash_message'] = "Error adding expense: " . htmlspecialchars($mysqli->error);
                $_SESSION['flash_message_type'] = "danger";
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
            <h6 class="m-0 font-weight-bold text-primary"><?php echo $edit_mode ? "Edit Expense Details" : "Add New Expense Form"; ?></h6>
        </div>
        <div class="card-body">
            <form action="<?php echo site_url('admin/manage-expense/' . ($edit_mode ? '?id=' . $expense_id : ''), $app_base_path); ?>" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="expense_category_id">Expense Category <span class="text-danger">*</span></label>
                    <select class="form-control" id="expense_category_id" name="expense_category_id" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($expense_categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($expense_category_id_val == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="amount">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?php echo htmlspecialchars($amount_val); ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="expense_date">Expense Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="expense_date" name="expense_date" value="<?php echo htmlspecialchars($expense_date_val); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Description (Optional)</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($description_val); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="receipt_file">Upload Receipt (Optional - Max 5MB: jpg, png, pdf, doc, txt)</label>
                    <input type="file" class="form-control-file" id="receipt_file" name="receipt_file">
                    <?php if ($edit_mode && !empty($receipt_url_val)): ?>
                        <small class="form-text text-muted">Current receipt:
                            <a href="<?php echo site_url($receipt_url_val, $app_base_path); ?>" target="_blank">
                                <?php echo htmlspecialchars(basename($receipt_url_val)); ?>
                            </a>
                        </small>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_mode ? '<i class="fas fa-save"></i> Update Expense' : '<i class="fas fa-plus-circle"></i> Add Expense'; ?>
                </button>
                <a href="<?php echo site_url('admin/expenses', $app_base_path); ?>" class="btn btn-secondary">
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
