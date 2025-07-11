<?php
// This template is included by modules/inventory/categories.php when $page_action is 'add' or 'edit'
// It has access to:
// $page_action ('add' or 'edit')
// $category_id (for edit mode)
// $category_name
// $category_description
// $form_errors (array of errors)
// $base_module_url (for form action and cancel link)

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}

$form_mode = ($page_action === 'edit') ? 'Edit' : 'Add';
$submit_action = ($page_action === 'edit') ? 'edit' : 'add';

?>
<div class="container mt-4">
    <h2><?php echo $form_mode; ?> Product Category</h2>

    <?php
    // Display general form errors if any (e.g., DB error on save, not field specific)
    // Field specific errors are shown below each field.
    // Session messages are displayed by header.php
    ?>

    <form action="<?php echo htmlspecialchars($base_module_url); ?>" method="POST">
        <input type="hidden" name="form_action" value="<?php echo $submit_action; ?>">
        <?php if ($page_action === 'edit'): ?>
            <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($category_id); ?>">
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="form-group">
                    <label for="category_name">Category Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?php echo isset($form_errors['name']) ? 'is-invalid' : ''; ?>"
                           id="category_name" name="category_name"
                           value="<?php echo htmlspecialchars($category_name); ?>" required>
                    <?php if (isset($form_errors['name'])): ?>
                        <div class="invalid-feedback"><?php echo htmlspecialchars($form_errors['name']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="category_description">Description</label>
                    <textarea class="form-control <?php echo isset($form_errors['description']) ? 'is-invalid' : ''; ?>"
                              id="category_description" name="category_description"
                              rows="3"><?php echo htmlspecialchars($category_description); ?></textarea>
                    <?php if (isset($form_errors['description'])): ?>
                        <div class="invalid-feedback"><?php echo htmlspecialchars($form_errors['description']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $form_mode === 'Edit' ? 'Save Changes' : 'Add Category'; ?>
                    </button>
                    <a href="<?php echo htmlspecialchars($base_module_url); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
