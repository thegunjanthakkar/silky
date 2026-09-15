<?php
/**
 * Temporary Database Setup Script for Silky Saree
 * Run this script once in your browser (e.g. https://silky.coida.in/setup_db.php)
 * to create all required database tables and insert initial demo/admin data.
 */

header('Content-Type: text/html; charset=utf-8');
require_once 'db_config.php';

if (!isset($conn) || !$conn) {
    die("<h1>Database Connection Failed</h1><p>Error: " . htmlspecialchars(isset($db_error) ? $db_error : mysqli_connect_error()) . "</p><p>Please fix <code>db_config.php</code> credentials first.</p>");
}

$logs = [];

function runQuery($sql, $description) {
    global $conn, $logs;
    if (mysqli_query($conn, $sql)) {
        $logs[] = "<li style='color: green;'><strong>SUCCESS:</strong> {$description}</li>";
        return true;
    } else {
        $logs[] = "<li style='color: red;'><strong>ERROR:</strong> {$description} - " . mysqli_error($conn) . "</li>";
        return false;
    }
}

// 1. Create admin_roles table
$sql_roles = "CREATE TABLE IF NOT EXISTS `admin_roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_name` VARCHAR(100) NOT NULL UNIQUE,
    `functionality` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($sql_roles, "Create `admin_roles` table");

// 2. Create admin_users table
$sql_admin_users = "CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `status` VARCHAR(50) DEFAULT 'active',
    `role_id` INT DEFAULT NULL,
    `last_login` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `admin_users_role_fk` FOREIGN KEY (`role_id`) REFERENCES `admin_roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($sql_admin_users, "Create `admin_users` table");

// 3. Create website_settings table & check section column
$sql_settings = "CREATE TABLE IF NOT EXISTS `website_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `section` VARCHAR(100) DEFAULT 'general',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($sql_settings, "Create `website_settings` table");

@mysqli_query($conn, "ALTER TABLE `website_settings` ADD COLUMN `section` VARCHAR(100) DEFAULT 'general'");

// 4. Create categories table
$sql_categories = "CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `image` VARCHAR(255) NULL,
    `status` VARCHAR(50) DEFAULT 'active',
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($sql_categories, "Create `categories` table");

// 5. Create products table
$sql_products = "CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `title` VARCHAR(255) NULL,
    `slug` VARCHAR(255) NULL,
    `category_id` INT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `compare_price` DECIMAL(10,2) NULL,
    `description` TEXT NULL,
    `image` TEXT NULL,
    `color` VARCHAR(255) NULL,
    `size` VARCHAR(255) NULL,
    `youtube_video_id` VARCHAR(100) NULL,
    `status` VARCHAR(50) DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($sql_products, "Create `products` table");

// 6. Create stock table
$sql_stock = "CREATE TABLE IF NOT EXISTS `stock` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `quantity` INT DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($sql_stock, "Create `stock` table");

// 7. Create colors & sizes tables
$sql_colors = "CREATE TABLE IF NOT EXISTS `colors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `color_name` VARCHAR(100) NOT NULL,
    `color_code` VARCHAR(50) NULL,
    `status` VARCHAR(50) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($sql_colors, "Create `colors` table");

$sql_sizes = "CREATE TABLE IF NOT EXISTS `sizes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `size_label` VARCHAR(50) NOT NULL,
    `status` VARCHAR(50) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($sql_sizes, "Create `sizes` table");

$sql_pcolors = "CREATE TABLE IF NOT EXISTS `product_colors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `color_id` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($sql_pcolors, "Create `product_colors` table");

$sql_psizes = "CREATE TABLE IF NOT EXISTS `product_sizes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `size_id` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($sql_psizes, "Create `product_sizes` table");

$sql_pvariants = "CREATE TABLE IF NOT EXISTS `product_variants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `color_id` INT NOT NULL,
    `size_id` INT NOT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `compare_price` DECIMAL(10,2) NULL,
    `grams` DECIMAL(10,2) NULL,
    `stock_quantity` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($sql_pvariants, "Create `product_variants` table");

// 8. Create orders table
$sql_orders = "CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_status` VARCHAR(50) DEFAULT 'pending',
    `order_status` VARCHAR(50) DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($sql_orders, "Create `orders` table");
runQuery("UPDATE orders SET order_number = LPAD(id, 4, '0') WHERE order_number IS NULL OR order_number NOT REGEXP '^[0-9]{4,}$'", "Re-sequence existing orders to 4-digit numeric format (0001, 0002...)");

// 9. Create users table
$sql_users = "CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `first_name` VARCHAR(100) NULL,
    `last_name` VARCHAR(100) NULL,
    `password` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($sql_users, "Create `users` table");

// 10. Create navigation_menus table
$sql_nav = "CREATE TABLE IF NOT EXISTS `navigation_menus` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `menu_type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `link` VARCHAR(255) NULL,
    `icon_class` VARCHAR(100) NULL,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($sql_nav, "Create `navigation_menus` table");

// 11. Create website_hero_slides table
$sql_hero = "CREATE TABLE IF NOT EXISTS `website_hero_slides` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `image_path` VARCHAR(255) NULL,
    `title` VARCHAR(255) NULL,
    `subtitle` TEXT NULL,
    `button_1_text` VARCHAR(100) NULL,
    `button_1_link` VARCHAR(255) NULL,
    `button_2_text` VARCHAR(100) NULL,
    `button_2_link` VARCHAR(255) NULL,
    `slide_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($sql_hero, "Create `website_hero_slides` table");

// 12. Create client_reviews table
$sql_reviews = "CREATE TABLE IF NOT EXISTS `client_reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `author_name` VARCHAR(255) NOT NULL,
    `location` VARCHAR(255) NULL,
    `review_text` TEXT NULL,
    `rating` DECIMAL(3,1) DEFAULT 5.0,
    `avatar_letter` VARCHAR(10) NULL,
    `avatar_color_class` VARCHAR(50) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
runQuery($sql_reviews, "Create `client_reviews` table");

// 13. Handle Super Admin Role dynamically
$all_permissions = json_encode([
    'dashboard_view', 'products_view', 'product_add', 'categories_view', 'category_add',
    'customers_view', 'orders_view', 'returns_refunds', 'stock_management', 'edit_homepage',
    'coupons_discounts', 'blogs_manage', 'reviews_manage', 'cart_wishlist', 'contact_queries',
    'users_list', 'manage_profiles', 'user_roles', 'admin_users', 'general_settings',
    'payment_settings', 'shipping_settings', 'email_settings', 'sales_report',
    'customer_analytics', 'inventory_report', 'performance_metrics', 'support_access'
]);

$role_id = null;
$check_role = mysqli_query($conn, "SELECT id FROM admin_roles WHERE role_name = 'Super Admin' LIMIT 1");
if ($check_role && mysqli_num_rows($check_role) > 0) {
    $row = mysqli_fetch_assoc($check_role);
    $role_id = $row['id'];
    $logs[] = "<li style='color: blue;'><strong>INFO:</strong> Using existing 'Super Admin' role with ID {$role_id}.</li>";
} else {
    $any_role = mysqli_query($conn, "SELECT id FROM admin_roles ORDER BY id ASC LIMIT 1");
    if ($any_role && mysqli_num_rows($any_role) > 0) {
        $row = mysqli_fetch_assoc($any_role);
        $role_id = $row['id'];
        $logs[] = "<li style='color: blue;'><strong>INFO:</strong> Using existing role ID {$role_id}.</li>";
    } else {
        $insert_role = "INSERT INTO `admin_roles` (`role_name`, `functionality`) VALUES ('Super Admin', '" . mysqli_real_escape_string($conn, $all_permissions) . "')";
        if (runQuery($insert_role, "Insert Super Admin Role")) {
            $role_id = mysqli_insert_id($conn);
        }
    }
}

// 14. Handle Default Admin User
$admin_email = "admin@silky.com";
$admin_pass = "admin123";
$hashed_pass = password_hash($admin_pass, PASSWORD_DEFAULT);

if ($role_id) {
    $check_admin = mysqli_query($conn, "SELECT id FROM admin_users WHERE email = '" . mysqli_real_escape_string($conn, $admin_email) . "'");
    if ($check_admin && mysqli_num_rows($check_admin) == 0) {
        $insert_admin = "INSERT INTO `admin_users` (`email`, `first_name`, `last_name`, `password`, `status`, `role_id`) 
                        VALUES ('" . mysqli_real_escape_string($conn, $admin_email) . "', 'Super', 'Admin', '" . mysqli_real_escape_string($conn, $hashed_pass) . "', 'active', " . intval($role_id) . ")";
        runQuery($insert_admin, "Insert Default Admin User (Email: <strong>{$admin_email}</strong>, Password: <strong>{$admin_pass}</strong>)");
    } else {
        $update_admin = "UPDATE admin_users SET password = '" . mysqli_real_escape_string($conn, $hashed_pass) . "', status = 'active', role_id = " . intval($role_id) . " WHERE email = '" . mysqli_real_escape_string($conn, $admin_email) . "'";
        runQuery($update_admin, "Updated Admin User credentials (Email: <strong>{$admin_email}</strong>, Password: <strong>{$admin_pass}</strong>)");
    }
} else {
    $logs[] = "<li style='color: red;'><strong>ERROR:</strong> Could not determine a valid role_id for Admin User.</li>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Setup - Silky Saree</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; padding: 40px; }
        .container { max-width: 700px; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin: auto; }
        h1 { color: #333; margin-top: 0; }
        ul { background: #f8f9fa; padding: 20px 30px; border-radius: 6px; font-size: 15px; line-height: 1.6; }
        .credentials { background: #eef9ff; border-left: 4px solid #007bff; padding: 15px 20px; margin-top: 20px; border-radius: 4px; }
        .btn { display: inline-block; background: #007bff; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 4px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Silky Saree - Database Setup</h1>
        <p>Database table creation & initial data seeding results:</p>
        <ul>
            <?php echo implode("\n", $logs); ?>
        </ul>

        <div class="credentials">
            <h3>Admin Login Credentials:</h3>
            <p><strong>URL:</strong> <a href="admin/login.php" target="_blank">admin/login.php</a></p>
            <p><strong>Email:</strong> <code>admin@silky.com</code></p>
            <p><strong>Password:</strong> <code>admin123</code></p>
        </div>

        <p style="margin-top: 25px; color: #666; font-size: 13px;">
            <em>Note: Once setup is complete, you can safely delete or keep <code>setup_db.php</code>.</em>
        </p>
        <a href="admin/login.php" class="btn">Go to Admin Login &rarr;</a>
    </div>
</body>
</html>
