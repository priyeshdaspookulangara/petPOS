<?php
$app_base_path = '/';

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

$page_title = "Manage Expense Category";
$category_id = null;
$edit_mode = false;

// Form data
$name_val = '';
$description_val = '';

// Determine if editing
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $category_id = (int)$_GET['id'];
    $edit_mode = true;
    $page_title = "Edit Expense Category";

    $sql_fetch = "SELECT name, description FROM expense_categories WHERE id = $category_id";
    $result_fetch = $mysqli->query($sql_fetch);
    if ($result_fetch && $result_fetch->num_rows > 0) {
        $category_data = $result_fetch->fetch_assoc();
        $name_val = $category_data['name'];
        $description_val = $category_data['description'];
        $result_fetch->free();
    } else {
        $_SESSION['flash_message'] = "Expense Category not found.";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: " . site_url('admin/expense-categories', $app_base_path));
        exit;
    }
} else {
    $page_title = "Add New Expense Category";
}

// Handle DELETE request
if (isset($_GET['action']) && $_GET['action'] == 'delete' && $category_id) {
    // Check if category has expenses
    $sql_check_expenses = "SELECT COUNT(*) as count FROM expenses WHERE expense_category_id = $category_id";
    $result_check = $mysqli->query($sql_check_expenses);
    $expense_count = ($result_check) ? (int)$result_check->fetch_assoc()['count'] : 0;
    if($result_check) $result_check->free();

    if ($expense_count > 0) {
        $_SESSION['flash_message'] = "Cannot delete category: It is linked to $expense_count expense(s).";
        $_SESSION['flash_message_type'] = "danger";
    } else {
        $sql_delete = "DELETE FROM expense_categories WHERE id = $category_id";
        if ($mysqli->query($sql_delete)) {
            $_SESSION['flash_message'] = "Expense category deleted successfully.";
            $_SESSION['flash_message_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Error deleting category: " . htmlspecialchars($mysqli->error);
            $_SESSION['flash_message_type'] = "danger";
        }
    }
    header("Location: " . site_url('admin/expense-categories', $app_base_path));
    exit;
}


// Handle POST request (Add or Update)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = sanitize_input($mysqli, $_POST['name'] ?? '');
    $description = sanitize_input($mysqli, $_POST['description'] ?? '');

    // Repopulate form values
    $name_val = $name;
    $description_val = $description;

    if (empty($name)) {
        $_SESSION['flash_message'] = "Category name is required.";
        $_SESSION['flash_message_type'] = "danger";
    } else {
        // Check for name conflict
        $conflict_check_sql = "SELECT id FROM expense_categories WHERE name = '$name'";
        if ($edit_mode) {
            $conflict_check_sql .= " AND id != $category_id";
        }
        $conflict_result = $mysqli->query($conflict_check_sql);

        if ($conflict_result && $conflict_result->num_rows > 0) {
            $_SESSION['flash_message'] = "An expense category with this name already exists.";
            $_SESSION['flash_message_type'] = "danger";
            $conflict_result->free();
        } else {
            if ($edit_mode) {
                // Update
                $sql_update = "UPDATE expense_categories SET name = '$name', description = '$description' WHERE id = $category_id";
                if ($mysqli->query($sql_update)) {
                    $_SESSION['flash_message'] = "Expense category updated successfully.";
                    $_SESSION['flash_message_type'] = "success";
                    header("Location: " . site_url('admin/expense-categories', $app_base_path));
                    exit;
                } else {
                    $_SESSION['flash_message'] = "Error updating category: " . htmlspecialchars($mysqli->error);
                    $_SESSION['flash_message_type'] = "danger";
                }
            } else {
                // Add
                $sql_insert = "INSERT INTO expense_categories (name, description) VALUES ('$name', '$description')";
                if ($mysqli->query($sql_insert)) {
                    $_SESSION['flash_message'] = "Expense category added successfully.";
                    $_SESSION['flash_message_type'] = "success";
                    header("Location: " . site_url('admin/expense-categories', $app_base_path));
                    exit;
                } else {
                    $_SESSION['flash_message'] = "Error adding category: " . htmlspecialchars($mysqli->error);
                    $_SESSION['flash_message_type'] = "danger";
                }
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
            <h6 class="m-0 font-weight-bold text-primary"><?php echo $edit_mode ? "Edit Details" : "Add New Category Form"; ?></h6>
        </div>
        <div class="card-body">
            <form action="<?php echo site_url('admin/manage-expense-category/' . ($edit_mode ? '?id=' . $category_id : ''), $app_base_path); ?>" method="post">
                <div class="form-group">
                    <label for="name">Category Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name_val); ?>" required>
                </div>

                <div class="form-group">
                    <label for="description">Description (Optional)</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($description_val); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_mode ? '<i class="fas fa-save"></i> Update Category' : '<i class="fas fa-plus-circle"></i> Add Category'; ?>
                </button>
                <a href="<?php echo site_url('admin/expense-categories', $app_base_path); ?>" class="btn btn-secondary">
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
