<?php
/**
 * Permission Manager
 */

// Permission mapping: functionality => admin file(s)
$permission_map = [
    // Dashboard
    'dashboard_view' => ['index.php', 'dashboard.php'],
    
    // Products
    'products_view' => ['products.php', 'product-list.php'],
    'product_add' => ['add-product.php', 'edit-product.php'],
    
    // Categories
    'categories_view' => ['categories.php', 'category-list.php'],
    'category_add' => ['add-category.php', 'edit-category.php'],
    
    // Customers & Orders
    'customers_view' => ['customers.php', 'customer-list.php'],
    'orders_view' => ['orders.php', 'order-list.php', 'edit-order.php', 'save-updated-order.php'],
    'returns_refunds' => ['returns.php', 'refunds.php'],
    'stock_management' => ['stocks.php', 'inventory.php'],
    
    // Marketing & Content
    'edit_homepage' => ['homepage-editor.php', 'edit-homepage.php', 'edit-website.php', 'save-website.php'],
    'coupons_discounts' => ['coupons.php', 'discounts.php'],
    'blogs_manage' => ['blogs.php', 'add-blog.php', 'edit-blog.php'],
    'reviews_manage' => ['reviews.php', 'manage-reviews.php'],
    
    // Utilities
    'cart_wishlist' => ['cart-management.php', 'wishlist-management.php'],
    'contact_queries' => ['contact-queries.php', 'queries.php'],
    
    // User Management
    'users_list' => ['users.php', 'user-list.php', 'edit-user.php','add-user.php'],
    'manage_profiles' => ['manage-profiles.php', 'edit-profile.php'],
    'user_roles' => ['user-roles.php', 'add-role.php', 'edit-role.php', 'delete-role.php'],
    'admin_users' => ['add-user.php', 'edit-user.php', 'delete-user.php'],
    
    // Settings
    'general_settings' => ['general-settings.php', 'settings.php'],
    'payment_settings' => ['payment-settings.php'],
    'shipping_settings' => ['shipping-settings.php'],
    'email_settings' => ['email-settings.php'],
    
    // Analytics & Reports
    'sales_report' => ['sales-report.php', 'reports.php'],
    'customer_analytics' => ['customer-analytics.php', 'analytics.php'],
    'inventory_report' => ['inventory-report.php'],
    'performance_metrics' => ['performance-metrics.php'],
    
    // Support
    'support_access' => ['support.php', 'help.php']
];

/**
 * Check if user has permission to access a specific file
 * @param string $filename - The filename to check access for
 * @return bool - True if user has access, false otherwise
 */
function hasFileAccess($filename) {
    global $permission_map;
    
    // Always allow access to common files
    $always_allowed = [
        'index.php',
        'dashboard.php', 
        'topbar.php',
        'leftbar.php',
        'footer.php',
        'logout.php',
        'profile.php',
        'access-denied.php',
        'debug-session.php',
        'edit-website.php',
        'save-website.php',
        'notifications.php'
    ];
    
    if (in_array($filename, $always_allowed)) {
        return true;
    }
    
    // Check if user is logged in and has role data
    $user_permissions = $_SESSION['admin_permissions'] ?? $_SESSION['user_permissions'] ?? null;
    if (!is_array($user_permissions)) {
        error_log("Permission Debug: No admin_permissions/user_permissions in session for file: $filename");
        return false;
    }
    
    // Debug: Log current check
    error_log("Permission Debug: Checking file '$filename' with permissions: " . implode(', ', $user_permissions));
    
    // Check each permission to see if it grants access to this file
    foreach ($permission_map as $permission => $files) {
        if (in_array($permission, $user_permissions) && in_array($filename, $files)) {
            error_log("Permission Debug: Access GRANTED for '$filename' via permission '$permission'");
            return true;
        }
    }
    
    error_log("Permission Debug: Access DENIED for '$filename'");
    return false;
}

/**
 * Check permission and redirect if access denied
 * Call this at the top of protected admin files
 */
function checkPageAccess() {
    // Get current filename
    $current_file = basename($_SERVER['PHP_SELF']);
    
    if (!hasFileAccess($current_file)) {
        // Debug: Log denial
        error_log("Permission Debug: Access denied for file: $current_file");
        // Redirect to access denied page or dashboard
        header('Location: access-denied.php');
        exit();
    }
}

/**
 * Get user permissions from database
 * @param int $user_id - User ID
 * @return array - Array of permission strings
 */
function getUserPermissions($user_id) {
    global $conn;
    
    if (!isset($conn) || !$conn) {
        require_once '../db_config.php';
    }
    
    // Get user's role and permissions
    $sql = "SELECT u.email, r.functionality FROM admin_users u 
            LEFT JOIN admin_roles r ON u.role_id = r.id 
            WHERE u.id = '" . mysqli_real_escape_string($conn, $user_id) . "' AND u.status = 'active'";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        
        if (!empty($row['functionality'])) {
            $permissions = json_decode($row['functionality'], true);
            if (is_array($permissions)) {
                return $permissions;
            }
        }
    }
    
    // Return empty array if no permissions found
    return [];
}

/**
 * Set user permissions in session
 * Call this when user logs in
 * @param int $user_id - User ID
 */
function setUserPermissions($user_id) {
    $perms = getUserPermissions($user_id);
    $_SESSION['admin_permissions'] = $perms;
    $_SESSION['user_permissions'] = $perms;
}

/**
 * Check if user has a specific permission
 * @param string $permission - Permission to check
 * @return bool - True if user has permission
 */
function hasPermission($permission) {
    $perms = $_SESSION['admin_permissions'] ?? $_SESSION['user_permissions'] ?? null;
    if (!is_array($perms)) {
        return false;
    }
    
    return in_array($permission, $perms);
}

/**
 * Generate navigation menu based on user permissions
 * @return array - Array of allowed menu items
 */
function getAllowedMenuItems() {
    if (!isset($_SESSION['user_permissions']) || !is_array($_SESSION['user_permissions'])) {
        return [];
    }
    
    $user_permissions = $_SESSION['user_permissions'];
    $allowed_items = [];
    
    // Define menu structure with required permissions
    $menu_structure = [
        'dashboard' => ['permission' => 'dashboard_view', 'file' => 'index.php'],
        'products' => ['permission' => 'products_view', 'file' => 'products.php'],
        'categories' => ['permission' => 'categories_view', 'file' => 'categories.php'],
        'orders' => ['permission' => 'orders_view', 'file' => 'orders.php'],
        'customers' => ['permission' => 'customers_view', 'file' => 'customers.php'],
        'users' => ['permission' => 'users_list', 'file' => 'users.php'],
        'user_roles' => ['permission' => 'user_roles', 'file' => 'user-roles.php'],
        'settings' => ['permission' => 'general_settings', 'file' => 'settings.php'],
        'reports' => ['permission' => 'sales_report', 'file' => 'reports.php']
    ];
    
    foreach ($menu_structure as $item => $config) {
        if (in_array($config['permission'], $user_permissions)) {
            $allowed_items[$item] = $config;
        }
    }
    
    return $allowed_items;
}
?>