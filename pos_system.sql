-- Database: `pos_system`
--

CREATE DATABASE IF NOT EXISTS `pos_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pos_system`;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `role` ENUM('Admin', 'Cashier') NOT NULL DEFAULT 'Cashier',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--
CREATE TABLE `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `contact_person` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sku` VARCHAR(100) UNIQUE DEFAULT NULL, -- Stock Keeping Unit
  `barcode` VARCHAR(100) UNIQUE DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` DECIMAL(10,2) NOT NULL,
  `current_stock` INT NOT NULL DEFAULT 0,
  `reorder_level` INT NOT NULL DEFAULT 0,
  `unit` VARCHAR(50) DEFAULT 'pcs', -- e.g., pcs, kg, liter
  `category_id` INT DEFAULT NULL,
  `supplier_id` INT DEFAULT NULL,
  `image_url` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers` (Optional, as per brief)
--
CREATE TABLE `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50) UNIQUE DEFAULT NULL,
  `email` VARCHAR(100) UNIQUE DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--
CREATE TABLE `sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `receipt_no` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `customer_id` INT DEFAULT NULL, -- Can be NULL if not tracking specific customers
  `sale_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `sub_total` DECIMAL(10,2) NOT NULL,
  `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
  `tax_percentage` DECIMAL(5,2) DEFAULT 0.00, -- Store tax rate at time of sale
  `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
  `grand_total` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL, -- e.g., Cash, Card
  `payment_details` TEXT DEFAULT NULL, -- For card type, transaction ID, or split payment info
  `status` VARCHAR(50) DEFAULT 'Completed', -- e.g., Completed, Held, Returned
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--
CREATE TABLE `sale_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `price_per_item` DECIMAL(10,2) NOT NULL, -- Selling price at the time of sale
  `discount_per_item` DECIMAL(10,2) DEFAULT 0.00,
  `item_total` DECIMAL(10,2) NOT NULL, -- (quantity * price_per_item) - (quantity * discount_per_item)
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) -- Consider ON DELETE RESTRICT or SET NULL if product deletion needs care
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_returns`
--
CREATE TABLE `sales_returns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `original_sale_id` INT NOT NULL,
  `return_receipt_no` VARCHAR(50) NOT NULL UNIQUE,
  `return_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `total_refund_amount` DECIMAL(10,2) NOT NULL,
  `reason` TEXT DEFAULT NULL,
  `user_id` INT NOT NULL, -- User who processed the return
  FOREIGN KEY (`original_sale_id`) REFERENCES `sales`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_return_items`
--
CREATE TABLE `sales_return_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sales_return_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `refund_price_per_item` DECIMAL(10,2) NOT NULL, -- Price at which it was returned
  `item_total_refund` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`sales_return_id`) REFERENCES `sales_returns`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--
CREATE TABLE `purchases` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `po_number` VARCHAR(50) UNIQUE DEFAULT NULL, -- Purchase Order number
  `supplier_id` INT NOT NULL,
  `purchase_date` DATE NOT NULL,
  `expected_delivery_date` DATE DEFAULT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `supplier_invoice_no` VARCHAR(100) DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Draft', -- e.g., Draft, Ordered, Partially Received, Received, Canceled
  `notes` TEXT DEFAULT NULL,
  `user_id` INT NOT NULL, -- User who created/managed the purchase
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--
CREATE TABLE `purchase_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity_ordered` INT NOT NULL,
  `quantity_received` INT DEFAULT 0,
  `purchase_price_per_item` DECIMAL(10,2) NOT NULL,
  `item_total` DECIMAL(10,2) NOT NULL, -- quantity_ordered * purchase_price_per_item
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_returns`
--
CREATE TABLE `purchase_returns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `original_purchase_id` INT NOT NULL,
  `return_date` DATE NOT NULL,
  `total_return_amount` DECIMAL(10,2) NOT NULL,
  `reason` TEXT DEFAULT NULL,
  `debit_note_no` VARCHAR(50) UNIQUE DEFAULT NULL,
  `user_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`original_purchase_id`) REFERENCES `purchases`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_return_items`
--
CREATE TABLE `purchase_return_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_return_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `return_price_per_item` DECIMAL(10,2) NOT NULL,
  `item_total_returned` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`purchase_return_id`) REFERENCES `purchase_returns`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustments`
--
CREATE TABLE `stock_adjustments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `adjustment_type` ENUM('In', 'Out') NOT NULL, -- 'In' for adding stock, 'Out' for deducting
  `quantity` INT NOT NULL,
  `reason` TEXT NOT NULL,
  `user_id` INT NOT NULL,
  `adjustment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--
CREATE TABLE `expense_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--
CREATE TABLE `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `expense_category_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `expense_date` DATE NOT NULL,
  `receipt_url` VARCHAR(255) DEFAULT NULL, -- Optional: path to scanned receipt
  `user_id` INT NOT NULL, -- User who recorded the expense
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('store_name', 'My Awesome POS'),
('store_address', '123 Retail Street, Shopsville'),
('tax_rate_percentage', '10.00'), -- Example tax rate
('currency_symbol', '$'),
('receipt_footer_message', 'Thank you for your business!'),
('default_user_role', 'Cashier'),
('low_stock_threshold', '10');

-- Add a default admin user (password: admin123)
-- IMPORTANT: This password should be changed immediately after setup.
INSERT INTO `users` (`username`, `password`, `email`, `role`) VALUES
('admin', '$2y$10$N9yX1qVCO2kSVOXzZn1.d.7fK0s9q.L0Z0mS.Q6m2p8j.JmG5cZqS', 'admin@example.com', 'Admin');
-- The password 'admin123' is hashed.

COMMIT;
