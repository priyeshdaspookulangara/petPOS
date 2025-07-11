-- SQL script to create tables for purchase management:
-- purchases, purchase_items, purchase_returns, purchase_return_items

-- Purchases Table: Records purchase orders or direct purchases from suppliers
CREATE TABLE IF NOT EXISTS `purchases` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `po_number` VARCHAR(50) DEFAULT NULL UNIQUE COMMENT 'Purchase Order number, if applicable',
    `supplier_id` INT NOT NULL COMMENT 'Foreign key to suppliers table',
    `user_id` INT NOT NULL COMMENT 'User who created/managed the purchase',
    `purchase_date` DATE NOT NULL COMMENT 'Date purchase was made or PO created',
    `expected_delivery_date` DATE DEFAULT NULL,
    `received_date` DATE DEFAULT NULL COMMENT 'Date goods were fully or partially received',
    `sub_total` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `shipping_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `other_charges` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(12, 2) NOT NULL COMMENT 'Total cost of the purchase',
    `amount_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_status` ENUM('pending', 'partially_paid', 'paid', 'overdue') NOT NULL DEFAULT 'pending',
    `supplier_invoice_no` VARCHAR(100) DEFAULT NULL COMMENT 'Invoice number from supplier',
    `status` ENUM('draft', 'ordered', 'partially_received', 'received', 'canceled') NOT NULL DEFAULT 'draft',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Purchase orders and received goods records';

ALTER TABLE `purchases` ADD INDEX `idx_purchase_po_number` (`po_number`);
ALTER TABLE `purchases` ADD INDEX `idx_purchase_supplier_id` (`supplier_id`);
ALTER TABLE `purchases` ADD INDEX `idx_purchase_user_id` (`user_id`);
ALTER TABLE `purchases` ADD INDEX `idx_purchase_date` (`purchase_date`);
ALTER TABLE `purchases` ADD INDEX `idx_purchase_status` (`status`);
ALTER TABLE `purchases` ADD INDEX `idx_payment_status` (`payment_status`);


-- Purchase Items Table: Records individual products in each purchase
CREATE TABLE IF NOT EXISTS `purchase_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `purchase_id` INT NOT NULL COMMENT 'Foreign key to purchases table',
    `product_id` INT NOT NULL COMMENT 'Foreign key to products table',
    `quantity_ordered` INT NOT NULL,
    `quantity_received` INT NOT NULL DEFAULT 0,
    `purchase_price_per_item` DECIMAL(10, 2) NOT NULL COMMENT 'Cost per item from supplier',
    `line_total` DECIMAL(12, 2) NOT NULL COMMENT 'Total for this line item (quantity_ordered * purchase_price_per_item)',
    FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Individual items within a purchase order';

ALTER TABLE `purchase_items` ADD INDEX `idx_pi_purchase_id` (`purchase_id`);
ALTER TABLE `purchase_items` ADD INDEX `idx_pi_product_id` (`product_id`);

-- Purchase Returns Table: Records returns to suppliers
CREATE TABLE IF NOT EXISTS `purchase_returns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `original_purchase_id` INT NOT NULL COMMENT 'Foreign key to the original purchases record',
    `return_note_no` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique debit note or return reference number',
    `supplier_id` INT NOT NULL,
    `user_id` INT NOT NULL COMMENT 'User who processed the return',
    `return_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `total_return_amount` DECIMAL(12, 2) NOT NULL COMMENT 'Total value of returned goods',
    `reason` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    FOREIGN KEY (`original_purchase_id`) REFERENCES `purchases`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Records of returns to suppliers';

ALTER TABLE `purchase_returns` ADD INDEX `idx_pr_original_purchase_id` (`original_purchase_id`);
ALTER TABLE `purchase_returns` ADD INDEX `idx_pr_return_note_no` (`return_note_no`);
ALTER TABLE `purchase_returns` ADD INDEX `idx_pr_user_id` (`user_id`);
ALTER TABLE `purchase_returns` ADD INDEX `idx_pr_return_date` (`return_date`);

-- Purchase Return Items Table: Records individual products returned to suppliers
CREATE TABLE IF NOT EXISTS `purchase_return_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `purchase_return_id` INT NOT NULL COMMENT 'Foreign key to purchase_returns table',
    `product_id` INT NOT NULL COMMENT 'Foreign key to products table',
    `original_purchase_item_id` INT DEFAULT NULL COMMENT 'Optional: link to original purchase_items.id',
    `quantity_returned` INT NOT NULL,
    `return_price_per_item` DECIMAL(10, 2) NOT NULL COMMENT 'Value at which the item was returned',
    `line_return_total` DECIMAL(12, 2) NOT NULL,
    FOREIGN KEY (`purchase_return_id`) REFERENCES `purchase_returns`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`original_purchase_item_id`) REFERENCES `purchase_items`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Individual items returned to a supplier';

ALTER TABLE `purchase_return_items` ADD INDEX `idx_pri_purchase_return_id` (`purchase_return_id`);
ALTER TABLE `purchase_return_items` ADD INDEX `idx_pri_product_id` (`product_id`);

SELECT 'Purchases tables (purchases, purchase_items, purchase_returns, purchase_return_items) creation script executed.' AS status;
