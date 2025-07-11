<?php
// This template is included by modules/inventory/suppliers.php when $page_action is 'add' or 'edit'
// It has access to:
// $page_action ('add' or 'edit')
// $supplier_id (for edit mode)
// $supplier_data (array of current supplier values for the form)
// $form_errors (array of errors)
// $base_module_url (for form action and cancel link)

if (!defined('BASE_PATH')) {
    die("Access denied: BASE_PATH not defined.");
}

$form_mode = ($page_action === 'edit') ? 'Edit' : 'Add';
$submit_action = ($page_action === 'edit') ? 'edit' : 'add';

// Helper to get value safely and htmlspecialchars it
function val_sup($data_array, $key, $default = '') {
    return isset($data_array[$key]) ? htmlspecialchars($data_array[$key]) : htmlspecialchars($default);
}
?>
<div class="container mt-4">
    <h2><?php echo $form_mode; ?> Supplier</h2>

    <form action="<?php echo htmlspecialchars($base_module_url); ?>" method="POST" novalidate>
        <input type="hidden" name="form_action" value="<?php echo $submit_action; ?>">
        <?php if ($page_action === 'edit'): ?>
            <input type="hidden" name="supplier_id" value="<?php echo htmlspecialchars($supplier_id); ?>">
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="form-group">
                    <label for="name">Supplier Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?php echo isset($form_errors['name']) ? 'is-invalid' : ''; ?>"
                           id="name" name="name" value="<?php echo val_sup($supplier_data, 'name'); ?>" required>
                    <?php if (isset($form_errors['name'])): ?><div class="invalid-feedback"><?php echo $form_errors['name']; ?></div><?php endif; ?>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="contact_person">Contact Person</label>
                            <input type="text" class="form-control <?php echo isset($form_errors['contact_person']) ? 'is-invalid' : ''; ?>"
                                   id="contact_person" name="contact_person" value="<?php echo val_sup($supplier_data, 'contact_person'); ?>">
                            <?php if (isset($form_errors['contact_person'])): ?><div class="invalid-feedback"><?php echo $form_errors['contact_person']; ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" class="form-control <?php echo isset($form_errors['phone']) ? 'is-invalid' : ''; ?>"
                                   id="phone" name="phone" value="<?php echo val_sup($supplier_data, 'phone'); ?>">
                            <?php if (isset($form_errors['phone'])): ?><div class="invalid-feedback"><?php echo $form_errors['phone']; ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" class="form-control <?php echo isset($form_errors['email']) ? 'is-invalid' : ''; ?>"
                           id="email" name="email" value="<?php echo val_sup($supplier_data, 'email'); ?>">
                    <?php if (isset($form_errors['email'])): ?><div class="invalid-feedback"><?php echo $form_errors['email']; ?></div><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea class="form-control <?php echo isset($form_errors['address']) ? 'is-invalid' : ''; ?>"
                              id="address" name="address" rows="3"><?php echo val_sup($supplier_data, 'address'); ?></textarea>
                    <?php if (isset($form_errors['address'])): ?><div class="invalid-feedback"><?php echo $form_errors['address']; ?></div><?php endif; ?>
                </div>

                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $form_mode === 'Edit' ? 'Save Changes' : 'Add Supplier'; ?>
                    </button>
                    <a href="<?php echo htmlspecialchars($base_module_url); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
