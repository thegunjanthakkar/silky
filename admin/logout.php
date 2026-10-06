<?php
session_start();

// Log the logout while session data is still available
if (isset($_SESSION['admin_user_id'])) {
    require_once '../db_config.php';
    if (file_exists('includes/permission-manager.php')) {
        require_once 'includes/permission-manager.php';
    }
    if (function_exists('logActivity')) {
        @logActivity('logout', 'Auth', 'Logged out');
    }
}

// Unset ONLY Admin session keys
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_user_id']);
unset($_SESSION['admin_email']);
unset($_SESSION['admin_first_name']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_role']);
unset($_SESSION['admin_permissions']);
unset($_SESSION['logged_in']);
unset($_SESSION['user_role']);
unset($_SESSION['user_permissions']);

// Redirect to admin login page
header("Location: login.php");
exit();
?>