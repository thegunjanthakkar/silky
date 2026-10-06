<?php
/**
 * Permission Manager
 * Central RBAC enforcement for admin panel.
 *
 * Behaviour (as requested):
 *  - Users ONLY see sidebar links / pages they are allowed to access.
 *  - Disallowed pages redirect to the dashboard (index.php), NOT to
 *    access-denied.php, so users never land on an "Access Denied" page
 *    through normal navigation.
 */

// Permission mapping: functionality => admin file(s)
$permission_map = [
    // Dashboard
    'dashboard_view' => ['index.php', 'dashboard.php'],

    // Products
    'products_view' => ['products.php', 'product-list.php'],
    'product_add' => ['add-product.php', 'edit-product.php', 'save-product.php', 'save-updated-product.php', 'delete-product.php', 'upload-image.php', 'upload-video.php', 'get-product-details.php', 'get-hero-banners.php', 'add-color-ajax.php', 'bulk-actions.php'],

    // Categories
    'categories_view' => ['categories.php', 'category-list.php', 'collections.php'],
    'category_add' => ['add-category.php', 'edit-category.php', 'save-category.php', 'save-updated-category.php', 'delete-category.php', 'update-category-order.php'],

    // Customers & Orders
    'customers_view' => ['customers.php', 'customer-list.php', 'get-customer-details.php'],
    'orders_view' => ['orders.php', 'order-list.php', 'edit-order.php', 'save-updated-order.php'],
    'stock_management' => ['stocks.php', 'stock.php', 'inventory.php'],

    // Marketing & Content
    'edit_homepage' => ['homepage-editor.php', 'edit-homepage.php', 'edit-website.php', 'save-website.php', 'get-hero-banners.php'],
    'coupons_discounts' => ['coupons.php', 'discounts.php'],
    'reviews_manage' => ['reviews.php', 'manage-reviews.php'],

    // Utilities
    'contact_queries' => ['contact-queries.php', 'queries.php'],

    // User Management
    'users_list' => ['users.php', 'user-list.php', 'add-user.php', 'edit-user.php', 'save-user.php', 'save-updated-user.php', 'delete-user.php'],
    'user_roles' => ['user-roles.php', 'add-role.php', 'edit-role.php', 'delete-role.php', 'save-role.php'],

    // Settings
    'general_settings' => ['general-settings.php', 'settings.php'],
    'payment_settings' => ['payment-settings.php'],
    'email_settings' => ['email-settings.php'],
    'activity_logs' => ['activity-logs.php'],

    // Analytics & Reports
    'sales_report' => ['sales-report.php', 'reports.php'],
    'customer_analytics' => ['customer-analytics.php', 'analytics.php'],
    'inventory_report' => ['inventory-report.php'],
    'performance_metrics' => ['performance-metrics.php'],
];

/**
 * Display label for a permission key.
 * Labels intentionally match the leftbar (sidebar) menu names so the
 * sidebar, role create/edit forms and role badges all use the same wording.
 * @param string $permission
 * @return string
 */
function permissionLabel($permission) {
    static $labels = [
        'dashboard_view'     => 'Dashboard',
        'products_view'      => 'All Products',
        'product_add'        => 'Add Product',
        'categories_view'    => 'All Categories',
        'category_add'       => 'Add Category',
        'customers_view'     => 'Customers',
        'orders_view'        => 'All Orders',
        'stock_management'   => 'Stock Management',
        'edit_homepage'      => 'Edit Website',
        'coupons_discounts'  => 'Coupons & Discounts',
        'reviews_manage'     => 'Customer Reviews',
        'contact_queries'    => 'Contact Queries',
        'users_list'         => 'User List',
        'user_roles'         => 'User Roles (Admin / Staff)',
        'general_settings'   => 'General Settings',
        'payment_settings'   => 'Payment Settings',
        'email_settings'     => 'Email Settings',
        'activity_logs'      => 'Activity Logs',
        'sales_report'       => 'Sales Report',
        'customer_analytics' => 'Customer Analytics',
        'inventory_report'   => 'Inventory Report',
        'performance_metrics'=> 'Performance Metrics',
    ];
    if (isset($labels[$permission])) {
        return $labels[$permission];
    }
    // Fallback for legacy stored permissions no longer offered
    return ucwords(str_replace(['_', '-'], ' ', (string)$permission));
}

/**
 * Check if current user holds the Super Admin role.
 * NOTE: informational only - it does NOT grant access by itself.
 * Access is always decided by the functionality stored for the role,
 * so unchecking a permission hides it even for Super Admin.
 * Convention: role_id 1 == Super Admin.
 * @return bool
 */
function isSuperAdmin() {
    $role_id = $_SESSION['admin_role'] ?? $_SESSION['user_role'] ?? null;
    if ($role_id !== null && (int)$role_id === 1) {
        return true;
    }
    // Wildcard permission also means full access
    $perms = $_SESSION['admin_permissions'] ?? $_SESSION['user_permissions'] ?? null;
    if (is_array($perms) && (in_array('*', $perms, true) || in_array('all', $perms, true))) {
        return true;
    }
    return false;
}

/**
 * Check if user has permission to access a specific file
 * @param string $filename - The filename to check access for
 * @return bool - True if user has access, false otherwise
 */
function hasFileAccess($filename) {
    global $permission_map;

    // Normalise: strip query string, handle extensionless URLs
    $filename = trim((string)$filename);
    if (strpos($filename, '?') !== false) {
        $filename = strstr($filename, '?', true);
    }
    $filename = basename($filename);
    if ($filename === '' || $filename === '.' || $filename === '/') {
        $filename = 'index.php';
    }
    // Extensionless admin links like ./products -> products.php
    if (strpos($filename, '.') === false) {
        $filename .= '.php';
    }

    // Always allow access to common files
    // NOTE: index.php / dashboard.php are intentionally NOT here.
    // They require the 'dashboard_view' permission (see $permission_map),
    // so unchecking "View Dashboard" hides them. Includes/partials and
    // auth pages stay always-allowed.
    $always_allowed = [
        'topbar.php',
        'leftbar.php',
        'footer.php',
        'logout.php',
        'login.php',
        'login_process.php',
        'profile.php',
        'access-denied.php',
        'debug-session.php',
        'notifications.php'
    ];

    if (in_array($filename, $always_allowed, true)) {
        return true;
    }

    // Check if user is logged in and has role data
    $user_permissions = $_SESSION['admin_permissions'] ?? $_SESSION['user_permissions'] ?? null;
    if (!is_array($user_permissions)) {
        return false;
    }

    // Check each permission to see if it grants access to this file
    foreach ($permission_map as $permission => $files) {
        if (in_array($permission, $user_permissions, true) && in_array($filename, $files, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Check permission and redirect if access denied.
 * Redirects to dashboard (index.php) instead of access-denied.php so
 * users only ever see pages they are allowed to access.
 * If the user has no dashboard access, redirects to their first allowed
 * page instead (avoids redirect loop on index.php itself).
 * Call this at the top of protected admin files
 */
function getFirstAllowedPage() {
    global $permission_map;
    $user_permissions = $_SESSION['admin_permissions'] ?? $_SESSION['user_permissions'] ?? null;
    if (!is_array($user_permissions)) {
        return 'access-denied.php';
    }
    foreach ($permission_map as $permission => $files) {
        if (in_array($permission, $user_permissions, true) && !empty($files[0])) {
            return $files[0];
        }
    }
    return 'access-denied.php';
}

function checkPageAccess() {
    // Get current filename
    $current_file = basename($_SERVER['PHP_SELF'] ?? '');

    if (!hasFileAccess($current_file)) {
        // AJAX / JSON requests: return 403 JSON instead of redirect
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $is_ajax = (strtolower($xrw) === 'xmlhttprequest')
            || (stripos($accept, 'application/json') !== false)
            || (isset($_SERVER['HTTP_CONTENT_TYPE']) && stripos($_SERVER['HTTP_CONTENT_TYPE'], 'application/json') !== false);

        if ($is_ajax || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
            if (!headers_sent()) {
                http_response_code(403);
                header('Content-Type: application/json');
            }
            echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action.']);
            exit();
        }

        // Flash notice for dashboard (optional display)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['perm_notice'] = 'You do not have access to "' . $current_file . '".';
        // Redirect target: dashboard if allowed, else first allowed page.
        // This avoids a redirect loop when index.php itself is denied.
        $target = 'index.php';
        if (!hasFileAccess('index.php')) {
            $target = getFirstAllowedPage();
        } elseif (in_array($current_file, ['index.php', 'dashboard.php'], true)) {
            // Should not happen (we just checked hasFileAccess), but guard anyway
            $target = getFirstAllowedPage();
        }
        // If target is the same page (e.g. no permissions at all), go to access-denied
        if (basename($target) === $current_file) {
            $target = 'access-denied.php';
        }
        // Redirect to allowed page instead of access-denied page
        if (!headers_sent()) {
            header('Location: ' . $target);
            exit();
        }
        echo '<script>window.location.href="' . htmlspecialchars($target, ENT_QUOTES) . '";</script>';
        exit();
    }
}

/**
 * Get user permissions from database
 * @param int $user_id - User ID
 * @return array - Array of permission strings
 */
function getUserPermissions($user_id) {
    global $conn, $permission_map;

    if (!isset($conn) || !$conn) {
        require_once '../db_config.php';
    }

    // Get user's role and permissions
    $sql = "SELECT u.email, u.role_id, r.role_name, r.functionality FROM admin_users u
            LEFT JOIN admin_roles r ON u.role_id = r.id
            WHERE u.id = '" . mysqli_real_escape_string($conn, $user_id) . "' AND u.status = 'active'";

    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        // Always respect exactly what is stored for the role - even for
        // Super Admin. Unchecking a permission must hide it.
        if (!empty($row['functionality'])) {
            $permissions = json_decode($row['functionality'], true);
            if (is_array($permissions)) {
                return array_values($permissions);
            }
        }

        // Fresh/legacy Super Admin role with nothing stored yet:
        // default to full access so the main admin is never locked out.
        $role_id = (int)($row['role_id'] ?? 0);
        $role_name = trim((string)($row['role_name'] ?? ''));
        if ($role_id === 1 || strcasecmp($role_name, 'super admin') === 0) {
            return array_keys($permission_map);
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

    return in_array($permission, $perms, true);
}

/**
 * Check if user has ANY of the given permissions (for parent menu visibility)
 * @param array $permissions - Permissions to check
 * @return bool
 */
function hasAnyPermission(array $permissions) {
    $perms = $_SESSION['admin_permissions'] ?? $_SESSION['user_permissions'] ?? [];
    if (!is_array($perms)) {
        return false;
    }
    foreach ($permissions as $p) {
        if (in_array($p, $perms, true)) {
            return true;
        }
    }
    return false;
}

/**
 * Generate navigation menu based on user permissions
 * @return array - Array of allowed menu items
 */
function getAllowedMenuItems() {
    // Source of truth is always the (auto-refreshed) session permissions,
    // so unchecked permissions disappear here as well.
    if (!isset($_SESSION['user_permissions']) && !isset($_SESSION['admin_permissions'])) {
        return [];
    }

    $user_permissions = $_SESSION['user_permissions'] ?? $_SESSION['admin_permissions'] ?? [];
    if (!is_array($user_permissions)) {
        return [];
    }

    $allowed_items = [];

    // Define menu structure with required permissions
    $menu_structure = [
        'dashboard' => ['permission' => 'dashboard_view', 'file' => 'index.php'],
        'products' => ['permission' => 'products_view', 'file' => 'products.php'],
        'product_add' => ['permission' => 'product_add', 'file' => 'add-product.php'],
        'categories' => ['permission' => 'categories_view', 'file' => 'categories.php'],
        'category_add' => ['permission' => 'category_add', 'file' => 'add-category.php'],
        'orders' => ['permission' => 'orders_view', 'file' => 'orders.php'],
        'customers' => ['permission' => 'customers_view', 'file' => 'customers.php'],
        'stocks' => ['permission' => 'stock_management', 'file' => 'stocks.php'],
        'reviews' => ['permission' => 'reviews_manage', 'file' => 'reviews.php'],
        'coupons' => ['permission' => 'coupons_discounts', 'file' => 'coupons.php'],
        'website' => ['permission' => 'edit_homepage', 'file' => 'edit-website.php'],
        'queries' => ['permission' => 'contact_queries', 'file' => 'contact-queries.php'],
        'users' => ['permission' => 'users_list', 'file' => 'users.php'],
        'user_roles' => ['permission' => 'user_roles', 'file' => 'user-roles.php'],
        'general_settings' => ['permission' => 'general_settings', 'file' => 'general-settings.php'],
        'payment_settings' => ['permission' => 'payment_settings', 'file' => 'payment-settings.php'],
        'email_settings' => ['permission' => 'email_settings', 'file' => 'email-settings.php'],
        'activity_logs' => ['permission' => 'activity_logs', 'file' => 'activity-logs.php'],
        'sales_report' => ['permission' => 'sales_report', 'file' => 'sales-report.php'],
        'customer_analytics' => ['permission' => 'customer_analytics', 'file' => 'customer-analytics.php'],
        'inventory_report' => ['permission' => 'inventory_report', 'file' => 'inventory-report.php'],
        'performance_metrics' => ['permission' => 'performance_metrics', 'file' => 'performance-metrics.php'],
    ];

    foreach ($menu_structure as $item => $config) {
        if (in_array($config['permission'], $user_permissions, true)) {
            $allowed_items[$item] = $config;
        }
    }

    return $allowed_items;
}

/**
 * Ensure the activity_logs table exists (safe to call on every request).
 */
function ensureActivityLogsTable() {
    try {
        global $conn;
        if (!isset($conn) || !$conn) {
            $cfg = __DIR__ . '/../../db_config.php';
            if (file_exists($cfg)) {
                require_once $cfg;
            }
        }
        if (!isset($conn) || !$conn) {
            return false;
        }
        $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NULL,
            user_name VARCHAR(150) NULL,
            role_name VARCHAR(100) NULL,
            action VARCHAR(50) NOT NULL,
            module VARCHAR(80) NOT NULL,
            description TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at),
            INDEX idx_module (module),
            INDEX idx_action (action),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        @mysqli_query($conn, $sql);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Normalize an IP address for display/storage as IPv4 where possible.
 * Localhost often reports ::1 (IPv6 loopback) - show 127.0.0.1 instead.
 * IPv4-mapped IPv6 addresses (::ffff:1.2.3.4) are unwrapped to plain IPv4.
 */
function normalizeIpAddress($ip) {
    $ip = trim((string)$ip);
    if ($ip === '') {
        return '';
    }
    if ($ip === '::1') {
        return '127.0.0.1';
    }
    if (stripos($ip, '::ffff:') === 0) {
        $v4 = substr($ip, 7);
        if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $v4;
        }
    }
    return $ip;
}

/**
 * Record an admin activity log entry.
 * @param string $action  e.g. login, logout, create, update, delete
 * @param string $module  e.g. Auth, Users, Roles, Products
 * @param string $description human-readable detail
 */
function logActivity($action, $module, $description = '') {
    try {
        if (!ensureActivityLogsTable()) {
            return false;
        }
        global $conn;
        if (!isset($conn) || !$conn) {
            return false;
        }
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $user_id = $_SESSION['admin_user_id'] ?? null;
        $user_name = $_SESSION['admin_name'] ?? $_SESSION['admin_first_name'] ?? $_SESSION['first_name'] ?? 'System';
        $role_name = null;
        $role_id = $_SESSION['admin_role'] ?? $_SESSION['user_role'] ?? null;
        if ($role_id !== null && is_numeric($role_id)) {
            $rid = (int)$role_id;
            $rres = @mysqli_query($conn, "SELECT role_name FROM admin_roles WHERE id = $rid LIMIT 1");
            if ($rres && ($rrow = mysqli_fetch_assoc($rres)) && !empty($rrow['role_name'])) {
                $role_name = $rrow['role_name'];
            }
        }
        $uid_sql = is_numeric($user_id) ? (int)$user_id : 'NULL';
        $action_esc = mysqli_real_escape_string($conn, substr((string)$action, 0, 50));
        $module_esc = mysqli_real_escape_string($conn, substr((string)$module, 0, 80));
        $desc_esc = mysqli_real_escape_string($conn, (string)$description);
        $uname_esc = mysqli_real_escape_string($conn, substr((string)$user_name, 0, 150));
        $rname_esc = $role_name !== null ? ("'" . mysqli_real_escape_string($conn, substr((string)$role_name, 0, 100)) . "'") : 'NULL';
        $ip_esc = mysqli_real_escape_string($conn, substr(normalizeIpAddress($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
        $ua_esc = mysqli_real_escape_string($conn, substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255));
        @mysqli_query($conn, "INSERT INTO activity_logs (user_id, user_name, role_name, action, module, description, ip_address, user_agent) VALUES ($uid_sql, '$uname_esc', $rname_esc, '$action_esc', '$module_esc', '$desc_esc', '$ip_esc', '$ua_esc')");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Re-read the logged-in admin's role permissions from the database and
 * update the session. Runs once per request (see auto-call below).
 *
 * Why: role permissions are written to the session at login, so editing a
 * role (e.g. unchecking "User List") had no effect until the affected user
 * logged out and back in. Refreshing here makes role changes apply on the
 * very next page load / click.
 *
 * Safety: on any DB failure the existing session permissions are kept, so
 * a database hiccup can never lock everybody out or wipe the sidebar.
 */
function refreshSessionPermissions() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $uid = $_SESSION['admin_user_id'] ?? null;
    if (empty($uid) || !is_numeric($uid)) {
        return; // not logged in as admin - nothing to refresh
    }

    try {
        global $conn, $permission_map;

        if (!isset($conn) || !$conn) {
            $cfg = __DIR__ . '/../../db_config.php';
            if (file_exists($cfg)) {
                require_once $cfg;
            }
        }
        if (!isset($conn) || !$conn) {
            return; // DB unavailable - keep existing session permissions
        }

        $uid_esc = mysqli_real_escape_string($conn, (string)$uid);
        $res = @mysqli_query($conn, "SELECT u.role_id, r.role_name, r.functionality FROM admin_users u LEFT JOIN admin_roles r ON u.role_id = r.id WHERE u.id = '$uid_esc' AND u.status = 'active' LIMIT 1");
        if (!$res || mysqli_num_rows($res) === 0) {
            return; // user/role not found - keep existing session permissions
        }
        $row = mysqli_fetch_assoc($res);
        if (!is_array($row)) {
            return;
        }

        $perms = null;
        if (!empty($row['functionality'])) {
            $decoded = json_decode($row['functionality'], true);
            if (is_array($decoded)) {
                $perms = array_values($decoded);
            }
        }
        if ($perms === null) {
            // Nothing stored for this role: full access only for a fresh
            // Super Admin role, otherwise no permissions.
            $role_id = (int)($row['role_id'] ?? 0);
            $role_name = trim((string)($row['role_name'] ?? ''));
            if ($role_id === 1 || strcasecmp($role_name, 'super admin') === 0) {
                $perms = array_keys($permission_map);
            } else {
                $perms = [];
            }
        }

        $_SESSION['admin_permissions'] = $perms;
        $_SESSION['user_permissions'] = $perms;
        // Keep role id in sync in case the admin was moved to another role
        if (isset($row['role_id']) && is_numeric($row['role_id'])) {
            $_SESSION['admin_role'] = (int)$row['role_id'];
            $_SESSION['user_role'] = (int)$row['role_id'];
        }
    } catch (Throwable $e) {
        // Keep existing session permissions on any error
        return;
    }
}

// Auto-refresh once per request so role edits take effect immediately
// without forcing users to log out and back in.
refreshSessionPermissions();

/**
 * Creator-based user management helpers.
 * admin_users.created_by stores the id of the admin who created the user.
 * Rule: normal users manageable by their creator OR the Admin role;
 * Admin-role users manageable only by the Admin role; self never.
 */
if (!function_exists('ensureCreatedByColumn')) {
    function ensureCreatedByColumn() {
        try {
            global $conn;
            if (!isset($conn) || !$conn) {
                $cfg = __DIR__ . '/../../db_config.php';
                if (file_exists($cfg)) {
                    require_once $cfg;
                }
            }
            if (!isset($conn) || !$conn) {
                return;
            }
            $col = @mysqli_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = 'created_by' LIMIT 1");
            if ($col && mysqli_num_rows($col) === 0) {
                @mysqli_query($conn, "ALTER TABLE `admin_users` ADD COLUMN `created_by` INT NULL AFTER `role_id`");
            }
        } catch (Throwable $e) {
            return;
        }
    }
}

if (!function_exists('isStrictAdminById')) {
    // Strict check: does this admin user hold the role literally named "Admin"?
    // Super Admin (and any other role) returns false here.
    function isStrictAdminById($admin_id) {
        try {
            global $conn;
            $admin_id = (int)$admin_id;
            if ($admin_id <= 0 || !isset($conn) || !$conn) {
                return false;
            }
            $res = @mysqli_query($conn, "SELECT r.role_name FROM admin_users u LEFT JOIN admin_roles r ON u.role_id = r.id WHERE u.id = $admin_id LIMIT 1");
            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);
                return (strtolower(trim($row['role_name'] ?? '')) === 'admin');
            }
        } catch (Throwable $e) {
            return false;
        }
        return false;
    }
}

if (!function_exists('isTargetAdminRole')) {
    // Is the target user holding a protected Admin-type role?
    function isTargetAdminRole($role_id, $role_name) {
        if ((int)$role_id === 1) {
            return true;
        }
        return in_array(strtolower(trim((string)($role_name ?? ''))), ['admin', 'super admin', 'superadmin', 'super-admin', 'administrator'], true);
    }
}

if (!function_exists('canManageUser')) {
    // Central decision: can $actor_id edit/toggle/delete target user $target_id?
    // Returns [bool $allowed, string $reason].
    function canManageUser($actor_id, $target_id) {
        try {
            global $conn;
            if (function_exists('ensureCreatedByColumn')) {
                ensureCreatedByColumn();
            }
            $actor_id = (int)$actor_id;
            $target_id = (int)$target_id;
            if ($target_id <= 0) {
                return [false, 'Invalid user ID.'];
            }
            if ($actor_id > 0 && $actor_id === $target_id) {
                return [false, 'You cannot manage your own account.'];
            }
            if (!isset($conn) || !$conn) {
                return [false, 'Database connection not available.'];
            }
            $res = @mysqli_query($conn, "SELECT u.role_id, u.created_by, r.role_name FROM admin_users u LEFT JOIN admin_roles r ON u.role_id = r.id WHERE u.id = $target_id LIMIT 1");
            if (!$res) {
                // created_by column may not exist (migration blocked) - fall back without it
                $res = @mysqli_query($conn, "SELECT u.role_id, r.role_name FROM admin_users u LEFT JOIN admin_roles r ON u.role_id = r.id WHERE u.id = $target_id LIMIT 1");
            }
            if (!$res || mysqli_num_rows($res) === 0) {
                return [false, 'User not found.'];
            }
            $t = mysqli_fetch_assoc($res);
            if (isTargetAdminRole($t['role_id'] ?? 0, $t['role_name'] ?? '')) {
                // Admin lock (unchanged): only the Admin role, never self
                if (isStrictAdminById($actor_id)) {
                    return [true, ''];
                }
                return [false, 'Only an Admin can manage another Admin user.'];
            }
            // Normal user: creator OR Admin role
            $created_by = isset($t['created_by']) && $t['created_by'] !== null ? (int)$t['created_by'] : 0;
            if ($actor_id > 0 && $created_by > 0 && $created_by === $actor_id) {
                return [true, ''];
            }
            if (isStrictAdminById($actor_id)) {
                return [true, ''];
            }
            return [false, 'Only the creator or an Admin can manage this user.'];
        } catch (Throwable $e) {
            return [false, 'Operation failed. Please try again.'];
        }
    }
}
?>
