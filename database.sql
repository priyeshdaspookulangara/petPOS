-- Combined SQL dump for POS System --
-- Version: 1.0
-- Generation Date: Fri Jul 11 04:19:42 UTC 2025

-- Schema for users table (001_create_users_table.sql) --
-- SQL script to create the users table

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL, -- For storing hashed passwords
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `role` ENUM('admin', 'cashier') NOT NULL DEFAULT 'cashier',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: Insert a default admin user for initial setup
-- Important: Replace 'admin_password' with a strong password.
-- The password here is 'admin123' hashed.
-- You should generate a new hash for your chosen password.
-- To generate a hash in PHP: password_hash('your_password', PASSWORD_DEFAULT)

-- Example: INSERT INTO `users` (`username`, `password`, `email`, `role`) VALUES
-- ('admin', '$2y$10$yourGeneratedHashHere', 'admin@example.com', 'admin');

-- For testing, let's add a default admin user with a known password 'adminpass'
-- Hash for 'adminpass':
-- php -r "echo password_hash('adminpass', PASSWORD_DEFAULT);"
-- Output will be something like: $2y$10$abcdefghijklmnopqrstuv
-- Replace the hash below with the actual generated hash if running this manually.
-- For now, I will provide a way to create the first admin user via a script or manually
-- after the table is created, as including a default password hash directly here might be
-- overwritten or be a fixed known value.

-- The plan includes providing a script for the first admin or manual instructions.
-- So, the table creation is the main part for this file.

ALTER TABLE `users` COMMENT = 'Stores user accounts for the POS system';

-- Add indexes for performance
ALTER TABLE `users` ADD INDEX `idx_username` (`username`);
ALTER TABLE `users` ADD INDEX `idx_email` (`email`);
ALTER TABLE `users` ADD INDEX `idx_role` (`role`);

-- Instructions for user:
-- 1. Run this SQL script in your MySQL database (e.g., via phpMyAdmin or MySQL command line)
--    to create the 'users' table. Ensure the database 'pos_system_db' (or your chosen name in config/db.php) exists.
-- 2. After the table is created, you'll need an admin user.
--    You can:
--    a) Manually INSERT one:
--       INSERT INTO `users` (username, password, email, role) VALUES ('admin', '<?php echo password_hash("adminpass", PASSWORD_DEFAULT); ?>', 'admin@example.com', 'admin');
--       (Execute the PHP part separately to get the hash, then put the hash in the SQL)
--    b) Or I will provide a registration script or a command-line script to create the first admin user later.
--       For now, the login system will be built, assuming a user exists.
--
-- For immediate testing after this step, you might want to manually insert an admin user.
-- Example hash for 'adminpass' is '$2y$10$9iE5OUMyAU1fKbHoL4gG5.MRsOPO01L3F88iGyh2gIFvLALe9YmQO' (this will vary)
-- INSERT INTO `users` (`username`, `password`, `email`, `role`) VALUES ('admin', '$2y$10$9iE5OUMyAU1fKbHoL4gG5.MRsOPO01L3F88iGyh2gIFvLALe9YmQO', 'admin@example.com', 'admin');
-- (This is just an example, use a freshly generated hash)

SELECT 'Users table created successfully (if it did not exist).' AS status;


-- Schema for inventory tables (002_create_inventory_tables.sql) --
-- SQL script to create tables for inventory management:
-- products, categories, suppliers, stock_adjustments

-- Categories Table: Stores product categories
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Product categories';
ALTER TABLE `categories` ADD INDEX `idx_category_name` (`name`);

-- Suppliers Table: Stores supplier information
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL UNIQUE,
    `address` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Supplier information';
ALTER TABLE `suppliers` ADD INDEX `idx_supplier_name` (`name`);
ALTER TABLE `suppliers` ADD INDEX `idx_supplier_email` (`email`);

-- Products Table: Core table for all products
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sku` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Stock Keeping Unit',
    `barcode` VARCHAR(100) DEFAULT NULL UNIQUE COMMENT 'Product barcode, can be null if not used',
    `name` VARCHAR(255) NOT NULL COMMENT 'Product name',
    `description` TEXT DEFAULT NULL COMMENT 'Detailed product description',
    `purchase_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Cost price from supplier',
    `selling_price` DECIMAL(10, 2) NOT NULL COMMENT 'Price at which product is sold',
    `current_stock` INT NOT NULL DEFAULT 0 COMMENT 'Current available quantity',
    `reorder_level` INT NOT NULL DEFAULT 0 COMMENT 'Stock level at which to reorder',
    `unit` VARCHAR(20) DEFAULT 'pcs' COMMENT 'Unit of measure (e.g., pcs, kg, liter)',
    `category_id` INT DEFAULT NULL COMMENT 'Foreign key to categories table',
    `supplier_id` INT DEFAULT NULL COMMENT 'Foreign key to suppliers table (primary supplier)',
    `image_url` VARCHAR(255) DEFAULT NULL COMMENT 'URL or path to product image',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Product details and stock information';

ALTER TABLE `products` ADD INDEX `idx_product_sku` (`sku`);
ALTER TABLE `products` ADD INDEX `idx_product_name` (`name`);
ALTER TABLE `products` ADD INDEX `idx_product_category_id` (`category_id`);
ALTER TABLE `products` ADD INDEX `idx_product_supplier_id` (`supplier_id`);
ALTER TABLE `products` ADD INDEX `idx_product_reorder_level` (`reorder_level`);


-- Stock Adjustments Table: Logs manual changes to stock levels
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `user_id` INT NOT NULL COMMENT 'User who made the adjustment',
    `adjustment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `type_of_adjustment` ENUM('increase', 'decrease', 'initial_stock', 'correction') NOT NULL COMMENT 'Type of stock change',
    `quantity_changed` INT NOT NULL COMMENT 'The amount by which stock was changed (positive for increase, negative for decrease)',
    `new_stock_level` INT NOT NULL COMMENT 'Stock level after adjustment',
    `reason` TEXT DEFAULT NULL COMMENT 'Reason for the adjustment (e.g., damaged goods, stock count correction)',
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE -- Or SET NULL if user can be deleted
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tracks manual stock adjustments';

ALTER TABLE `stock_adjustments` ADD INDEX `idx_sa_product_id` (`product_id`);
ALTER TABLE `stock_adjustments` ADD INDEX `idx_sa_user_id` (`user_id`);
ALTER TABLE `stock_adjustments` ADD INDEX `idx_sa_adjustment_date` (`adjustment_date`);

SELECT 'Inventory tables (categories, suppliers, products, stock_adjustments) creation script executed.' AS status;


-- Schema for sales tables (003_create_sales_tables.sql) --
-- SQL script to create tables for sales management:
-- customers (optional), sales, sale_items, sales_returns, sales_return_items

-- Customers Table: Optional, if tracking customer-specific sales
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL UNIQUE,
    `email` VARCHAR(100) DEFAULT NULL UNIQUE,
    `address` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Customer information (optional)';
ALTER TABLE `customers` ADD INDEX `idx_customer_name` (`name`);
ALTER TABLE `customers` ADD INDEX `idx_customer_phone` (`phone`);

-- Sales Table: Records each sale transaction
CREATE TABLE IF NOT EXISTS `sales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `receipt_no` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique receipt number for the sale',
    `user_id` INT NOT NULL COMMENT 'User (cashier) who processed the sale',
    `customer_id` INT DEFAULT NULL COMMENT 'Foreign key to customers table (if applicable)',
    `sale_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `sub_total` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total before discount and tax',
    `discount_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total discount applied to the sale',
    `tax_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Tax percentage applied at time of sale',
    `tax_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total tax amount for the sale',
    `grand_total` DECIMAL(12, 2) NOT NULL COMMENT 'Final amount paid by customer',
    `payment_method` VARCHAR(50) DEFAULT 'cash' COMMENT 'e.g., Cash, Card, Split',
    `payment_details` TEXT DEFAULT NULL COMMENT 'e.g., card type, transaction ID, split payment details',
    `status` ENUM('completed', 'held', 'returned', 'partially_returned', 'canceled') NOT NULL DEFAULT 'completed',
    `notes` TEXT DEFAULT NULL COMMENT 'Any special notes for the sale',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Sales transaction records';

ALTER TABLE `sales` ADD INDEX `idx_sales_receipt_no` (`receipt_no`);
ALTER TABLE `sales` ADD INDEX `idx_sales_user_id` (`user_id`);
ALTER TABLE `sales` ADD INDEX `idx_sales_customer_id` (`customer_id`);
ALTER TABLE `sales` ADD INDEX `idx_sales_sale_date` (`sale_date`);
ALTER TABLE `sales` ADD INDEX `idx_sales_status` (`status`);

-- Sale Items Table: Records individual products sold in each sale
CREATE TABLE IF NOT EXISTS `sale_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT NOT NULL COMMENT 'Foreign key to sales table',
    `product_id` INT NOT NULL COMMENT 'Foreign key to products table',
    `quantity` INT NOT NULL,
    `original_price_per_item` DECIMAL(10, 2) NOT NULL COMMENT 'Selling price of the product at time of sale before item discount',
    `discount_per_item` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Discount applied to this item specifically',
    `price_per_item_after_discount` DECIMAL(10, 2) NOT NULL COMMENT 'Actual price per item after item-specific discount',
    `line_total` DECIMAL(12, 2) NOT NULL COMMENT 'Total for this line item (quantity * price_per_item_after_discount)',
    FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE -- RESTRICT to ensure product data integrity
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Individual items within a sale';

ALTER TABLE `sale_items` ADD INDEX `idx_si_sale_id` (`sale_id`);
ALTER TABLE `sale_items` ADD INDEX `idx_si_product_id` (`product_id`);

-- Sales Returns Table: Records information about returned sales
CREATE TABLE IF NOT EXISTS `sales_returns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `original_sale_id` INT NOT NULL COMMENT 'Foreign key to the original sales record',
    `return_receipt_no` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique receipt number for the return',
    `user_id` INT NOT NULL COMMENT 'User who processed the return',
    `customer_id` INT DEFAULT NULL,
    `return_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `total_refund_amount` DECIMAL(12, 2) NOT NULL,
    `reason` TEXT DEFAULT NULL COMMENT 'Reason for the return',
    `notes` TEXT DEFAULT NULL,
    FOREIGN KEY (`original_sale_id`) REFERENCES `sales`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE, -- RESTRICT as original sale is key info
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Records of sales returns';

ALTER TABLE `sales_returns` ADD INDEX `idx_sr_original_sale_id` (`original_sale_id`);
ALTER TABLE `sales_returns` ADD INDEX `idx_sr_return_receipt_no` (`return_receipt_no`);
ALTER TABLE `sales_returns` ADD INDEX `idx_sr_user_id` (`user_id`);
ALTER TABLE `sales_returns` ADD INDEX `idx_sr_return_date` (`return_date`);

-- Sales Return Items Table: Records individual products returned
CREATE TABLE IF NOT EXISTS `sales_return_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sales_return_id` INT NOT NULL COMMENT 'Foreign key to sales_returns table',
    `product_id` INT NOT NULL COMMENT 'Foreign key to products table',
    `original_sale_item_id` INT DEFAULT NULL COMMENT 'Optional: link to original sale_items.id for traceability',
    `quantity_returned` INT NOT NULL,
    `refund_price_per_item` DECIMAL(10, 2) NOT NULL COMMENT 'Price at which the item was refunded',
    `line_refund_total` DECIMAL(12, 2) NOT NULL,
    FOREIGN KEY (`sales_return_id`) REFERENCES `sales_returns`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`original_sale_item_id`) REFERENCES `sale_items`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Individual items returned in a sales return';

ALTER TABLE `sales_return_items` ADD INDEX `idx_sri_sales_return_id` (`sales_return_id`);
ALTER TABLE `sales_return_items` ADD INDEX `idx_sri_product_id` (`product_id`);

SELECT 'Sales tables (customers, sales, sale_items, sales_returns, sales_return_items) creation script executed.' AS status;


-- Schema for purchases tables (004_create_purchases_tables.sql) --
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


-- Schema for accounts and settings tables (005_create_accounts_settings_tables.sql) --
-- SQL script to create tables for basic accounts and system settings:
-- expense_categories, expenses, settings

-- Expense Categories Table: For categorizing business expenses
CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Categories for business expenses';
ALTER TABLE `expense_categories` ADD INDEX `idx_exp_cat_name` (`name`);

-- Expenses Table: Records various business expenses
CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `expense_category_id` INT NOT NULL,
    `user_id` INT NOT NULL COMMENT 'User who recorded the expense',
    `expense_date` DATE NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `description` TEXT DEFAULT NULL COMMENT 'Details about the expense',
    `receipt_reference` VARCHAR(100) DEFAULT NULL COMMENT 'Optional: receipt number or reference',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Records of business expenses';

ALTER TABLE `expenses` ADD INDEX `idx_expense_category_id` (`expense_category_id`);
ALTER TABLE `expenses` ADD INDEX `idx_expense_user_id` (`user_id`);
ALTER TABLE `expenses` ADD INDEX `idx_expense_date` (`expense_date`);

-- Settings Table: Stores global system settings
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Identifier for the setting (e.g., store_name, tax_rate)',
    `setting_value` TEXT DEFAULT NULL COMMENT 'Value of the setting',
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Global system settings';

ALTER TABLE `settings` ADD INDEX `idx_setting_key` (`setting_key`);

-- Insert some default essential settings (can be updated via admin panel later)
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('store_name', 'My POS Store', 'The name of the retail store')
ON DUPLICATE KEY UPDATE setting_value = setting_value; -- Do nothing if key exists

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('store_address', '123 Main Street, Anytown, USA', 'Physical address of the store')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('store_phone', '+1-555-123-4567', 'Contact phone number for the store')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('store_email', 'contact@myposstore.com', 'Contact email for the store')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('tax_rate_percentage', '7.5', 'Default sales tax rate as a percentage (e.g., 7.5 for 7.5%)')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('receipt_footer_message', 'Thank you for your business!', 'Message to appear at the bottom of receipts')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('store_logo_url', 'assets/images/default_logo.png', 'URL/path to the store logo for receipts/UI')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('currency_symbol', '$', 'Default currency symbol (e.g., $, €, £)')
ON DUPLICATE KEY UPDATE setting_value = setting_value;


SELECT 'Accounts and Settings tables (expense_categories, expenses, settings) creation script executed and default settings inserted.' AS status;
