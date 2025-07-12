<?php
$page_title = "View Purchase Order";
$app_base_path = '/'; // Adjust if your application is in a subdirectory.

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/auth.php';

redirect_if_not_admin();

$po_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$po_id) {
    $_SESSION['flash_message'] = "No Purchase Order ID provided.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: " . site_url('admin/purchase-orders', $app_base_path));
    exit;
}

$purchase_order = null;
$po_items = [];

// Function to fetch PO details (to avoid repetition)
function fetchPoDetails($mysqli_conn, $po_id_func) {
    global $purchase_order, $po_items; // Use global vars to set them

    $sql_po = "SELECT p.*, s.name as supplier_name, s.contact_person as supplier_contact, s.email as supplier_email, s.phone as supplier_phone, s.address as supplier_address
               FROM purchases p
               LEFT JOIN suppliers s ON p.supplier_id = s.id
               WHERE p.id = $po_id_func";
    $po_result = $mysqli_conn->query($sql_po);
    if ($po_result && $po_result->num_rows > 0) {
        $purchase_order = $po_result->fetch_assoc();
        $po_result->free();

        $sql_items = "SELECT pi.*, pr.name as product_name, pr.sku as product_sku
                      FROM purchase_items pi
                      JOIN products pr ON pi.product_id = pr.id
                      WHERE pi.purchase_id = $po_id_func ORDER BY pr.name ASC";
        $items_result = $mysqli_conn->query($sql_items);
        if ($items_result) {
            while ($item_row = $items_result->fetch_assoc()) {
                $po_items[] = $item_row;
            }
            $items_result->free();
        } else {
            $_SESSION['flash_message'] = "Error fetching PO items: " . htmlspecialchars($mysqli_conn->error);
            $_SESSION['flash_message_type'] = "danger"; // This might get overwritten by status update messages
            return false;
        }
        return true;
    }
    return false;
}

if (!fetchPoDetails($mysqli, $po_id)) {
    $_SESSION['flash_message'] = "Purchase Order not found (ID: $po_id)."; // Ensure this message is set if fetch fails
    $_SESSION['flash_message_type'] = "danger";
    header("Location: " . site_url('admin/purchase-orders', $app_base_path));
    exit;
}

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status_submit'])) {
    $new_status = sanitize_input($mysqli, $_POST['new_status']);
    $allowed_statuses = ['Draft', 'Ordered', 'Partially Received', 'Received', 'Canceled'];

    if (!in_array($new_status, $allowed_statuses)) {
        $_SESSION['flash_message'] = "Invalid status selected.";
        $_SESSION['flash_message_type'] = "danger";
    } elseif ($new_status == $purchase_order['status']) {
        $_SESSION['flash_message'] = "PO is already in '$new_status' status.";
        $_SESSION['flash_message_type'] = "info";
    } else {
        $mysqli->begin_transaction();
        $all_successful = true;
        $goods_receipt_messages = [];

        try {
            // Update PO status first
            $update_status_sql = "UPDATE purchases SET status = '$new_status' WHERE id = $po_id";
            if (!$mysqli->query($update_status_sql)) {
                throw new Exception("Error updating PO status: " . $mysqli->error);
            }
            $goods_receipt_messages[] = "PO status updated to '$new_status'.";

            // If status changed to 'Received' and was not 'Received' before, process goods receipt
            if ($new_status == 'Received' && $purchase_order['status'] != 'Received') {
                if (empty($po_items)) { // Should not happen if PO has items, but good check
                     // Re-fetch items if they were not available due to some prior state
                    fetchPoDetails($mysqli, $po_id); // This will repopulate $po_items
                }

                if (!empty($po_items)) {
                    $goods_receipt_messages[] = "Processing goods receipt...";
                    foreach ($po_items as $item) {
                        $product_id_gr = $item['product_id'];
                        $quantity_ordered_gr = $item['quantity_ordered'];
                        // For now, assume quantity_received is the full quantity_ordered
                        // Partial receipts would require a form to input received quantities.
                        $quantity_received_gr = $quantity_ordered_gr;

                        // 1. Update purchase_items.quantity_received
                        $update_pi_sql = "UPDATE purchase_items SET quantity_received = $quantity_received_gr
                                          WHERE purchase_id = $po_id AND product_id = $product_id_gr";
                        if (!$mysqli->query($update_pi_sql)) {
                            throw new Exception("Error updating quantity received for product ID $product_id_gr: " . $mysqli->error);
                        }

                        // 2. Update products.current_stock
                        // We add quantity_received_gr to current stock.
                        // This assumes stock was not already updated for this PO item if it was partially received before.
                        // A more robust system for partial receipts would need careful state management.
                        // For simplicity, if moving from 'Ordered' or 'Draft' or 'Partially Received' to 'Received', we process the full ordered quantity.
                        $update_prod_stock_sql = "UPDATE products SET current_stock = current_stock + $quantity_received_gr
                                                  WHERE id = $product_id_gr";
                        if (!$mysqli->query($update_prod_stock_sql)) {
                            throw new Exception("Error updating stock for product ID $product_id_gr: " . $mysqli->error);
                        }

                        // 3. Optional: Update products.purchase_price
                        $new_purchase_price_gr = $item['price_per_item'];
                        $update_prod_price_sql = "UPDATE products SET purchase_price = $new_purchase_price_gr
                                                   WHERE id = $product_id_gr";
                        if (!$mysqli->query($update_prod_price_sql)) {
                            // This is optional, so don't throw an exception, maybe log a warning
                            $goods_receipt_messages[] = "Warning: Could not update purchase price for product ID $product_id_gr.";
                        }
                        $goods_receipt_messages[] = "Product ID $product_id_gr: Stock updated by +$quantity_received_gr. Purchase price set to $new_purchase_price_gr.";
                    }
                    $goods_receipt_messages[] = "Goods receipt processed successfully.";
                } else {
                     $goods_receipt_messages[] = "Warning: No items found for this PO to process receipt. Stock not updated.";
                }
            }

            $mysqli->commit();
            $_SESSION['flash_message'] = implode("<br>", $goods_receipt_messages);
            $_SESSION['flash_message_type'] = "success";
            // Re-fetch PO details to show updated status and item quantities
            fetchPoDetails($mysqli, $po_id);

        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['flash_message'] = "Transaction failed: " . htmlspecialchars($e->getMessage());
            $_SESSION['flash_message_type'] = "danger";
        }
    }
}


include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <?php if ($purchase_order): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0 text-gray-800">
            <?php echo htmlspecialchars($page_title); ?>: <?php echo htmlspecialchars($purchase_order['po_number'] ?: 'PO-' . $purchase_order['id']); ?>
        </h1>
        <div>
            <a href="<?php echo site_url('admin/purchase-orders', $app_base_path); ?>" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
            <?php if ($purchase_order['status'] == 'Draft' || $purchase_order['status'] == 'Ordered'): ?>
            <a href="<?php echo site_url('admin/manage-po/?id=' . $purchase_order['id'], $app_base_path); ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Edit PO</a>
            <?php endif; ?>
            <button onclick="window.print();" class="btn btn-sm btn-outline-info"><i class="fas fa-print"></i> Print PO</button>
        </div>
    </div>

    <div id="printableArea"> <?php // Wrap content to be printed ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Purchase Order Details</h6>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>To Supplier:</h5>
                        <address>
                            <strong><?php echo htmlspecialchars($purchase_order['supplier_name'] ?: 'N/A'); ?></strong><br>
                            <?php if($purchase_order['supplier_contact']): ?>
                                Attn: <?php echo htmlspecialchars($purchase_order['supplier_contact']); ?><br>
                            <?php endif; ?>
                            <?php echo nl2br(htmlspecialchars($purchase_order['supplier_address'] ?: 'No address provided')); ?><br>
                            <?php if($purchase_order['supplier_phone']): ?>
                                Phone: <?php echo htmlspecialchars($purchase_order['supplier_phone']); ?><br>
                            <?php endif; ?>
                            <?php if($purchase_order['supplier_email']): ?>
                                Email: <?php echo htmlspecialchars($purchase_order['supplier_email']); ?>
                            <?php endif; ?>
                        </address>
                    </div>
                    <div class="col-md-6 text-md-right">
                        <h5>PO Information:</h5>
                        <p class="mb-1"><strong>PO Number:</strong> <?php echo htmlspecialchars($purchase_order['po_number'] ?: 'PO-' . $purchase_order['id']); ?></p>
                        <p class="mb-1"><strong>Purchase Date:</strong> <?php echo htmlspecialchars(date('M d, Y', strtotime($purchase_order['purchase_date']))); ?></p>
                        <p class="mb-1"><strong>Expected Delivery:</strong> <?php echo $purchase_order['expected_delivery_date'] ? htmlspecialchars(date('M d, Y', strtotime($purchase_order['expected_delivery_date']))) : 'N/A'; ?></p>
                        <p class="mb-1"><strong>Status:</strong>
                            <?php
                            $status_badge_view = 'secondary'; $status_text_class = '';
                            if ($purchase_order['status'] == 'Ordered') {$status_badge_view = 'info';}
                            elseif ($purchase_order['status'] == 'Received') {$status_badge_view = 'success';}
                            elseif ($purchase_order['status'] == 'Partially Received') {$status_badge_view = 'primary';}
                            elseif ($purchase_order['status'] == 'Canceled') {$status_badge_view = 'danger';}
                            elseif ($purchase_order['status'] == 'Draft') {$status_badge_view = 'light'; $status_text_class = 'text-dark';}
                            ?>
                            <span class="badge badge-<?php echo $status_badge_view; ?> <?php echo $status_text_class; ?> p-2" style="font-size: 0.9rem;"><?php echo htmlspecialchars($purchase_order['status']); ?></span>
                        </p>
                    </div>
                </div>

                <h5 class="mt-4">Order Items:</h5>
                <?php if (!empty($po_items)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th class="text-center">#</th>
                                <th>SKU</th>
                                <th>Product Name</th>
                                <th class="text-right">Qty Ordered</th>
                                <th class="text-right">Qty Received</th>
                                <th class="text-right">Price/Item</th>
                                <th class="text-right">Item Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $calculated_grand_total = 0;
                            $item_counter = 1;
                            foreach($po_items as $item):
                                $item_total = $item['quantity_ordered'] * $item['price_per_item'];
                                $calculated_grand_total += $item_total;
                            ?>
                            <tr>
                                <td class="text-center"><?php echo $item_counter++; ?></td>
                                <td><?php echo htmlspecialchars($item['product_sku'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($item['quantity_ordered']); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($item['quantity_received'] ?: '0'); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars(number_format($item['price_per_item'], 2)); ?></td>
                                <td class="text-right"><?php echo htmlspecialchars(number_format($item_total, 2)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="6" class="text-right"><h5>Grand Total:</h5></th>
                                <th class="text-right"><h5><?php echo htmlspecialchars(number_format($calculated_grand_total, 2)); ?></h5></th>
                            </tr>
                            <?php if(isset($purchase_order['total_amount']) && abs((float)$purchase_order['total_amount'] - $calculated_grand_total) > 0.01 && (float)$purchase_order['total_amount'] != 0.00) : // Show stored total if different and not zero ?>
                            <tr class="table-danger">
                                <th colspan="6" class="text-right text-danger">Stored PO Total (Discrepancy):</th>
                                <th class="text-right text-danger"><?php echo htmlspecialchars(number_format($purchase_order['total_amount'], 2)); ?></th>
                            </tr>
                            <?php endif; ?>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted">No items found for this purchase order.</p>
                <?php endif; ?>

                <?php if(!empty($purchase_order['notes'])): ?>
                <div class="mt-4">
                    <h6>Notes:</h6>
                    <p class="text-muted_"><?php echo nl2br(htmlspecialchars($purchase_order['notes'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div> <!-- End of printableArea -->


    <?php // Status Update Form - Keep outside printable area ?>
    <?php if ($purchase_order['status'] != 'Received' && $purchase_order['status'] != 'Canceled'): // Do not show if already fully received or canceled ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Update PO Status / Process Receipt</h6>
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo site_url('admin/view-po/?id=' . $po_id, $app_base_path); ?>">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-4">
                        <label for="new_status">Change Status To:</label>
                        <select name="new_status" id="new_status" class="form-control">
                            <?php if ($purchase_order['status'] == 'Draft'): ?>
                                <option value="Draft" selected>Draft</option>
                                <option value="Ordered">Ordered</option>
                                <option value="Canceled">Canceled</option>
                            <?php elseif ($purchase_order['status'] == 'Ordered'): ?>
                                <option value="Ordered" selected>Ordered</option>
                                <option value="Received">Mark as Fully Received</option>
                                <!-- <option value="Partially Received">Partially Received</option> -->
                                <option value="Canceled">Canceled</option>
                            <?php elseif ($purchase_order['status'] == 'Partially Received'): ?>
                                <!-- Logic for partial receipts would be more complex, for now, assume full receipt from 'Ordered' -->
                                <option value="Partially Received" selected>Partially Received</option>
                                <option value="Received">Mark as Fully Received</option>
                                <option value="Canceled">Canceled</option>
                            <?php else: ?>
                                <option value="<?php echo htmlspecialchars($purchase_order['status']); ?>" selected><?php echo htmlspecialchars($purchase_order['status']); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <button type="submit" name="update_status_submit" class="btn btn-info"><i class="fas fa-sync-alt"></i> Update Status</button>
                    </div>
                </div>
                <?php if ($purchase_order['status'] == 'Ordered' || $purchase_order['status'] == 'Partially Received'): ?>
                <p class="text-muted mt-2">
                    <small>Note: Changing status to 'Received' will trigger the Goods Receipt process in the next step (stock update). For now, it only updates the PO status.</small>
                </p>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php endif; ?>


    <?php else: // Should not happen if initial check is done properly ?>
        <div class="alert alert-danger">Could not load purchase order details.</div>
    <?php endif; ?>
</div>
<style>
@media print {
  body * {
    visibility: hidden;
  }
  #printableArea, #printableArea * {
    visibility: visible;
  }
  #printableArea {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
  .btn, .badge-<?php echo $status_badge_view; ?>{ /* Ensure badge prints ok */
      border: 1px solid #ccc !important; /* Example for badges */
  }
  .badge {
      -webkit-print-color-adjust: exact !important; /* Chrome, Safari */
      color-adjust: exact !important; /* Standard */
  }
  /* Hide non-essential elements during print */
  .navbar, .footer, .card.shadow.mb-4 form,
  a[href*='admin/manage-po'], a[href*='admin/purchase-orders'], button[onclick*='window.print'] {
      display: none !important;
  }
}
</style>

<?php
include __DIR__ . '/../../templates/footer.php';
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>
