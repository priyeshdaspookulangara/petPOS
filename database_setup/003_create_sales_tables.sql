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
