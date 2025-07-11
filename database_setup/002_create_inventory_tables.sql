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
