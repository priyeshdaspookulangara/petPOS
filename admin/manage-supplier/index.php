<?php
$app_base_path = '/'; // Adjust if your application is in a subdirectory. Should be global.

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

$page_title = "Manage Supplier";
$supplier_id = null;
$edit_mode = false;

// Supplier data for form pre-fill
$name_val = '';
$contact_person_val = '';
$phone_val = '';
$email_val = '';
$address_val = '';

// Determine if we are editing an existing supplier
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $supplier_id = (int)$_GET['id'];
    $edit_mode = true;
    $page_title = "Edit Supplier";

    // Fetch supplier data for editing
    $sql_fetch_supplier = "SELECT name, contact_person, phone, email, address FROM suppliers WHERE id = $supplier_id";
    $result_fetch_supplier = $mysqli->query($sql_fetch_supplier);
    if ($result_fetch_supplier && $result_fetch_supplier->num_rows > 0) {
        $supplier_data = $result_fetch_supplier->fetch_assoc();
        $name_val = $supplier_data['name'];
        $contact_person_val = $supplier_data['contact_person'];
        $phone_val = $supplier_data['phone'];
        $email_val = $supplier_data['email'];
        $address_val = $supplier_data['address'];
        $result_fetch_supplier->free();
    } else {
        $_SESSION['flash_message'] = "Supplier not found.";
        $_SESSION['flash_message_type'] = "danger";
        header("Location: " . site_url('admin/suppliers', $app_base_path));
        exit;
    }
} else {
    $page_title = "Add New Supplier";
}

// Handle DELETE request
if (isset($_GET['action']) && $_GET['action'] == 'delete' && $supplier_id) {
    // Check if supplier is assigned to any products or purchase orders
    $sql_check_products = "SELECT COUNT(*) as count FROM products WHERE supplier_id = $supplier_id";
    $result_check_products = $mysqli->query($sql_check_products);
    $product_count = ($result_check_products) ? $result_check_products->fetch_assoc()['count'] : 0;
    if($result_check_products) $result_check_products->free();

    $sql_check_pos = "SELECT COUNT(*) as count FROM purchases WHERE supplier_id = $supplier_id";
    $result_check_pos = $mysqli->query($sql_check_pos);
    $po_count = ($result_check_pos) ? $result_check_pos->fetch_assoc()['count'] : 0;
    if($result_check_pos) $result_check_pos->free();

    if ($product_count > 0 || $po_count > 0) {
        $error_msg = "Cannot delete supplier: ";
        if ($product_count > 0) $error_msg .= "Assigned to $product_count product(s). ";
        if ($po_count > 0) $error_msg .= "Linked to $po_count purchase order(s). ";
        $error_msg .= "Reassign or remove these links first.";
        $_SESSION['flash_message'] = $error_msg;
        $_SESSION['flash_message_type'] = "danger";
    } else {
        $sql_delete = "DELETE FROM suppliers WHERE id = $supplier_id";
        if ($mysqli->query($sql_delete)) {
            $_SESSION['flash_message'] = "Supplier deleted successfully.";
            $_SESSION['flash_message_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Error deleting supplier: " . htmlspecialchars($mysqli->error);
            $_SESSION['flash_message_type'] = "danger";
        }
    }
    header("Location: " . site_url('admin/suppliers', $app_base_path));
    exit;
}


// Handle POST request (Add or Update)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = sanitize_input($mysqli, $_POST['name'] ?? '');
    $contact_person = sanitize_input($mysqli, $_POST['contact_person'] ?? '');
    $phone = sanitize_input($mysqli, $_POST['phone'] ?? '');
    $email = sanitize_input($mysqli, $_POST['email'] ?? '');
    $address = sanitize_input($mysqli, $_POST['address'] ?? '');

    // Repopulate form values
    $name_val = $name;
    $contact_person_val = $contact_person;
    $phone_val = $phone;
    $email_val = $email;
    $address_val = $address;

    // Validation
    if (empty($name)) {
        $_SESSION['flash_message'] = "Supplier name is required.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_message'] = "Invalid email format.";
        $_SESSION['flash_message_type'] = "danger";
    } else {
        // Check for name conflict (optional, usually supplier names can be similar)
        // For this example, we'll skip strict unique name check for suppliers.
        // If needed:
        // $conflict_check_sql = "SELECT id FROM suppliers WHERE name = '$name'";
        // if ($edit_mode && $supplier_id) {
        //     $conflict_check_sql .= " AND id != $supplier_id";
        // }
        // ... execute check ...

        if ($edit_mode && $supplier_id) {
            // Update existing supplier
            $sql_update = "UPDATE suppliers SET
                            name = '$name',
                            contact_person = '$contact_person',
                            phone = '$phone',
                            email = '$email',
                            address = '$address'
                           WHERE id = $supplier_id";
            if ($mysqli->query($sql_update)) {
                $_SESSION['flash_message'] = "Supplier updated successfully.";
                $_SESSION['flash_message_type'] = "success";
                header("Location: " . site_url('admin/suppliers', $app_base_path));
                exit;
            } else {
                $_SESSION['flash_message'] = "Error updating supplier: " . htmlspecialchars($mysqli->error);
                $_SESSION['flash_message_type'] = "danger";
            }
        } else {
            // Add new supplier
            $sql_insert = "INSERT INTO suppliers (name, contact_person, phone, email, address)
                           VALUES ('$name', '$contact_person', '$phone', '$email', '$address')";
            if ($mysqli->query($sql_insert)) {
                $_SESSION['flash_message'] = "Supplier added successfully.";
                $_SESSION['flash_message_type'] = "success";
                header("Location: " . site_url('admin/suppliers', $app_base_path));
                exit;
            } else {
                $_SESSION['flash_message'] = "Error adding supplier: " . htmlspecialchars($mysqli->error);
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
            <h6 class="m-0 font-weight-bold text-primary"><?php echo $edit_mode ? "Edit Supplier Details" : "Add New Supplier Form"; ?></h6>
        </div>
        <div class="card-body">
            <form action="<?php echo site_url('admin/manage-supplier/' . ($edit_mode && $supplier_id ? '?id=' . $supplier_id : ''), $app_base_path); ?>" method="post" novalidate>
                <div class="form-group">
                    <label for="name">Supplier Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name_val); ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="contact_person">Contact Person</label>
                        <input type="text" class="form-control" id="contact_person" name="contact_person" value="<?php echo htmlspecialchars($contact_person_val); ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="phone">Phone</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($phone_val); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email_val); ?>">
                </div>
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($address_val); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_mode ? '<i class="fas fa-save"></i> Update Supplier' : '<i class="fas fa-plus-circle"></i> Add Supplier'; ?>
                </button>
                <a href="<?php echo site_url('admin/suppliers', $app_base_path); ?>" class="btn btn-secondary">
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
