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
