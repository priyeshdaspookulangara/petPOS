<?php
// This template is included by modules/accounts/expenses.php when $page_action is 'add' or 'edit'
// It has access to:
// $page_action ('add' or 'edit')
// $expense_id (for edit mode)
// $expense_data (array of current values for the form)
// $form_errors (array of errors)
// $expense_categories_list (array of categories for dropdown)
// $base_module_url (for form action and cancel link)

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}

$form_mode_exp = ($page_action === 'edit') ? 'Edit' : 'Add';
$submit_action_exp = ($page_action === 'edit') ? 'edit' : 'add';

function val_exp($data_array, $key, $default = '') {
    return isset($data_array[$key]) ? htmlspecialchars($data_array[$key]) : htmlspecialchars($default);
}
function val_exp_num($data_array, $key, $default = '0.00') {
    return isset($data_array[$key]) ? htmlspecialchars(number_format((float)$data_array[$key], 2, '.', '')) : htmlspecialchars(number_format((float)$default, 2, '.', ''));
}
?>
<div class="container mt-4">
    <h2><?php echo $form_mode_exp; ?> Expense Record</h2>

    <form action="<?php echo htmlspecialchars($base_module_url . ($page_action === 'edit' ? '&sub_action=edit&id='.$expense_id : '&sub_action=add')); ?>" method="POST">
        <input type="hidden" name="form_action" value="<?php echo $submit_action_exp; ?>">
        <?php if ($page_action === 'edit'): ?>
            <input type="hidden" name="expense_id" value="<?php echo htmlspecialchars($expense_id); ?>">
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="expense_date">Expense Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control <?php echo isset($form_errors['expense_date']) ? 'is-invalid' : ''; ?>"
                                   id="expense_date" name="expense_date" value="<?php echo val_exp($expense_data, 'expense_date', date('Y-m-d')); ?>" required>
                            <?php if (isset($form_errors['expense_date'])): ?><div class="invalid-feedback"><?php echo $form_errors['expense_date']; ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="expense_category_id">Expense Category <span class="text-danger">*</span></label>
                            <select class="form-control <?php echo isset($form_errors['expense_category_id']) ? 'is-invalid' : ''; ?>" id="expense_category_id" name="expense_category_id" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($expense_categories_list as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo (val_exp($expense_data, 'expense_category_id') == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if(empty($expense_categories_list)): ?>
                                    <option value="" disabled>No categories found. Please add categories first.</option>
                                <?php endif; ?>
                            </select>
                            <?php if (isset($form_errors['expense_category_id'])): ?><div class="invalid-feedback"><?php echo $form_errors['expense_category_id']; ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="amount">Amount <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" class="form-control <?php echo isset($form_errors['amount']) ? 'is-invalid' : ''; ?>"
                           id="amount" name="amount" value="<?php echo val_exp_num($expense_data, 'amount', ''); ?>" required placeholder="0.00">
                    <?php if (isset($form_errors['amount'])): ?><div class="invalid-feedback"><?php echo $form_errors['amount']; ?></div><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="description">Description <span class="text-danger">*</span></label>
                    <textarea class="form-control <?php echo isset($form_errors['description']) ? 'is-invalid' : ''; ?>"
                              id="description" name="description" rows="3" required><?php echo val_exp($expense_data, 'description'); ?></textarea>
                    <?php if (isset($form_errors['description'])): ?><div class="invalid-feedback"><?php echo $form_errors['description']; ?></div><?php endif; ?>
                </div>

                 <div class="form-group">
                    <label for="receipt_reference">Receipt Reference (Optional)</label>
                    <input type="text" class="form-control <?php echo isset($form_errors['receipt_reference']) ? 'is-invalid' : ''; ?>"
                           id="receipt_reference" name="receipt_reference" value="<?php echo val_exp($expense_data, 'receipt_reference'); ?>">
                    <?php if (isset($form_errors['receipt_reference'])): ?><div class="invalid-feedback"><?php echo $form_errors['receipt_reference']; ?></div><?php endif; ?>
                </div>


                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary" <?php if(empty($expense_categories_list)) echo 'disabled'; ?>>
                        <i class="fas fa-save"></i> <?php echo $form_mode_exp === 'Edit' ? 'Save Changes' : 'Record Expense'; ?>
                    </button>
                    <a href="<?php echo htmlspecialchars($base_module_url); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
