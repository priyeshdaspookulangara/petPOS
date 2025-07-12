# Comprehensive POS System (Plain PHP/MySQLi)

This is a robust, user-friendly, and feature-rich Point of Sale (POS) system designed for retail operations. It is built using plain PHP and MySQLi, with a modern, responsive front-end utilizing Bootstrap and jQuery.

## Features

*   **Point of Sale (POS) Module:** Intuitive interface for sales processing, cart management, multiple payment methods (dummy), and sales returns.
*   **Inventory Management:** Full CRUD for Products, Categories, and Suppliers. Manual stock adjustments and automatic low-stock alerts.
*   **Purchase Management:** Create and manage Purchase Orders, process Goods Receipts to update stock, and handle Purchase Returns to suppliers.
*   **Expense Tracking:** Record and categorize business expenses, with optional receipt uploads.
*   **Comprehensive Reporting:**
    *   **Sales:** Filterable reports by date, user, and category. Top-selling products and sales returns reports.
    *   **Inventory:** Current stock value reports and a simplified stock movement log.
    *   **Purchases:** Filterable reports on purchase history by date and supplier.
    *   **Financial:** High-level financial snapshot (Sales vs. Purchases vs. Expenses) and a simplified cash flow view.
*   **User Management:** Admin and Cashier roles with role-based access control.
*   **Settings:** Configure store details, tax rate, and customize receipts with a store logo.
*   **CSV Import/Export:** Bulk manage products via CSV upload and download.

## Technical Stack

*   **Backend:** Plain PHP (7.4+ recommended)
*   **Database:** MySQL / MariaDB (InnoDB engine recommended)
*   **Database Access:** MySQLi (procedural/object-oriented, direct queries)
*   **Frontend:** HTML5, CSS3, Bootstrap 4, jQuery (via CDN)

---

## Server Requirements

*   PHP version 7.4 or higher.
*   MySQL or MariaDB database server.
*   Web Server such as Apache or Nginx.
*   The web server must be configured to serve `index.php` when a directory is requested (this is default behavior for most servers).

---

## Setup and Installation

Follow these steps to set up the project on your local or remote server.

### 1. Clone the Repository

Clone this repository to your local machine or server, ideally into the document root of your web server (e.g., `/var/www/html/pos/`, `htdocs/pos/`).

```sh
git clone <repository-url> pos-system
cd pos-system
```

### 2. Create the Database

Using a MySQL client like phpMyAdmin, Adminer, or the command line, create a new database for the POS system.

```sql
CREATE DATABASE pos_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Import the SQL Schema

Import the `pos_system.sql` file located in the root of the project into your newly created `pos_system` database. This will create all the necessary tables and insert initial data, including a default admin user and system settings.

Using the command line:
```sh
mysql -u your_username -p pos_system < pos_system.sql
```
Alternatively, use the "Import" feature in your database management tool (e.g., phpMyAdmin) to upload and execute the `pos_system.sql` file.

### 4. Configure Database Connection

Open the `includes/db_connect.php` file in a text editor. Update the database credentials to match your environment.

```php
<?php
// Database configuration
define('DB_SERVER', 'localhost'); // Your database host, usually 'localhost'
define('DB_USERNAME', 'root');    // Your database username
define('DB_PASSWORD', '');        // Your database password
define('DB_NAME', 'pos_system');  // The name of the database you created
```

### 5. Configure Web Server

Point your web server's document root to the project directory where you cloned the repository.

**Clean URLs:** This project is designed to use clean URLs (e.g., `/login/` instead of `/login.php`) by relying on the web server's default behavior of serving `index.php` from a directory. No special rewrite rules or `.htaccess` files are required or used. Ensure your server configuration has `index.php` in its directory index list.

For Apache, this is typically handled by the `DirectoryIndex` directive in your `httpd.conf` or virtual host configuration:
```apache
<Directory /var/www/html/pos-system>
    DirectoryIndex index.php index.html
    AllowOverride None # .htaccess is not used
    Require all granted
</Directory>
```

### 6. File Permissions (If applicable)

On Linux-based systems, you may need to ensure the web server has write permissions for the asset upload directories.

```sh
# From the project root directory
chmod -R 775 assets/uploads/
chown -R www-data:www-data assets/uploads/ # Replace www-data with your web server's user/group
```

---

## Initial Login

After completing the setup, you can access the application through your web browser.

*   **URL:** `http://localhost/` (or the specific path you configured)
*   **Admin Username:** `admin`
*   **Admin Password:** `admin123`

**IMPORTANT:** It is strongly recommended that you log in and change the default admin password immediately via the "User Management" section in the admin panel.

You can now start configuring your store settings, adding categories, suppliers, and products!
