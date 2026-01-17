<?php
$app_base_path = '/'; // Adjust if your application is in a subdirectory. Should be global.

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

$page_title = "Manage Category";
$category_id = null;
$edit_mode = false;

// Category data for form pre-fill
$name_val = '';
// $description_val = ''; // If you add a description field to categories table

// Determine if we are editing an existing category
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $category_id = (int)$_GET['id'];
    $edit_mode = true;
    $page_title = "Edit Category";

    // Fetch category data for editing
    // IMPORTANT: No prepared statements. $category_id is cast to int, so it's safe.
    $sql_fetch_category = "SELECT name FROM categories WHERE id = $category_id"; // Add description if exists
    $result_fetch_category = $mysqli->query($sql_fetch_category);
    if ($result_fetch_category && $result_fetch_category->num_rows > 0) {
        $category_data = $result_fetch_category->fetch_assoc();
        $name_val = $category_data['name'];
        // $description_val = $category_data['description'];
        $result_fetch_category->free();
    } else {
        $_SESSION['flash_message'] = "Category not found.";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: " . site_url('admin/categories', $app_base_path));
        exit;
    }
} else {
    $page_title = "Add New Category";
}

// Handle DELETE request
if (isset($_GET['action']) && $_GET['action'] == 'delete' && $category_id) {
    // Check if category is assigned to any products
    $sql_check_products = "SELECT COUNT(*) as count FROM products WHERE category_id = $category_id";
    $result_check_products = $mysqli->query($sql_check_products);
    $product_count = 0;
    if ($result_check_products) {
        $product_count = $result_check_products->fetch_assoc()['count'];
        $result_check_products->free();
    }

    if ($product_count > 0) {
        $_SESSION['flash_message'] = "Cannot delete category: It is currently assigned to " . $product_count . " product(s). Reassign products first.";
        $_SESSION['flash_message_type'] = "danger";
    } else {
        // IMPORTANT: No prepared statements. $category_id is cast to int.
        $sql_delete = "DELETE FROM categories WHERE id = $category_id";
        if ($mysqli->query($sql_delete)) {
            $_SESSION['flash_message'] = "Category deleted successfully.";
            $_SESSION['flash_message_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Error deleting category: " . htmlspecialchars($mysqli->error);
            $_SESSION['flash_message_type'] = "danger";
        }
    }
    header("Location: " . site_url('admin/categories', $app_base_path));
    exit;
}


// Handle POST request (Add or Update)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = sanitize_input($mysqli, $_POST['name'] ?? '');
    // $description = sanitize_input($mysqli, $_POST['description'] ?? '');

    // Repopulate form values in case of error
    $name_val = $name;
    // $description_val = $description;

    // Validation
    if (empty($name)) {
        $_SESSION['flash_message'] = "Category name is required.";
        $_SESSION['flash_message_type'] = "danger";
    } else {
        // Check for name conflict (excluding current category if editing)
        $conflict_check_sql = "SELECT id FROM categories WHERE name = '$name'";
        if ($edit_mode && $category_id) {
            $conflict_check_sql .= " AND id != $category_id";
        }
        $conflict_result = $mysqli->query($conflict_check_sql);

        if ($conflict_result && $conflict_result->num_rows > 0) {
            $_SESSION['flash_message'] = "A category with this name already exists.";
            $_SESSION['flash_message_type'] = "danger";
            $conflict_result->free();
        } else {
            if ($edit_mode && $category_id) {
                // Update existing category
                // $sql_update = "UPDATE categories SET name = '$name', description = '$description' WHERE id = $category_id";
                $sql_update = "UPDATE categories SET name = '$name' WHERE id = $category_id"; // Simplified
                if ($mysqli->query($sql_update)) {
                    $_SESSION['flash_message'] = "Category updated successfully.";
                    $_SESSION['flash_message_type'] = "success";
                    header("Location: " . site_url('admin/categories', $app_base_path));
                    exit;
                } else {
                    $_SESSION['flash_message'] = "Error updating category: " . htmlspecialchars($mysqli->error);
                    $_SESSION['flash_message_type'] = "danger";
                }
            } else {
                // Add new category
                // $sql_insert = "INSERT INTO categories (name, description) VALUES ('$name', '$description')";
                $sql_insert = "INSERT INTO categories (name) VALUES ('$name')"; // Simplified
                if ($mysqli->query($sql_insert)) {
                    $_SESSION['flash_message'] = "Category added successfully.";
                    $_SESSION['flash_message_type'] = "success";
                    header("Location: " . site_url('admin/categories', $app_base_path));
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
            <h6 class="m-0 font-weight-bold text-primary"><?php echo $edit_mode ? "Edit Category Details" : "Add New Category Form"; ?></h6>
        </div>
        <div class="card-body">
            <form action="<?php echo site_url('admin/manage-category/' . ($edit_mode && $category_id ? '?id=' . $category_id : ''), $app_base_path); ?>" method="post" novalidate>
                <div class="form-group">
                    <label for="name">Category Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name_val); ?>" required>
                </div>

                <!-- Optional: Description field -->
                <!--
                <div class="form-group">
                    <label for="description">Description (Optional)</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php //echo htmlspecialchars($description_val); ?></textarea>
                </div>
                -->

                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_mode ? '<i class="fas fa-save"></i> Update Category' : '<i class="fas fa-plus-circle"></i> Add Category'; ?>
                </button>
                <a href="<?php echo site_url('admin/categories', $app_base_path); ?>" class="btn btn-secondary">
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
