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
