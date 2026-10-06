<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'includes/permission-manager.php';
checkPageAccess();
require_once '../db_config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    $_SESSION['error'] = 'Invalid user ID.';
    header('Location: users.php');
    exit;
}

// Protected: no one can delete their own profile (even via direct URL)
$__current_id = (int)($_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);
if ($__current_id > 0 && $id === $__current_id) {
    $_SESSION['error'] = 'You cannot delete your own account.';
    header('Location: users.php');
    exit;
}

// Protected: Admin-role users can only be deleted by an Admin (even via direct URL)
$__target = mysqli_query($conn, "SELECT u.role_id, r.role_name FROM admin_users u LEFT JOIN admin_roles r ON u.role_id = r.id WHERE u.id = $id LIMIT 1");
if ($__target && mysqli_num_rows($__target) > 0) {
    $__t = mysqli_fetch_assoc($__target);
    $__t_role = strtolower(trim($__t['role_name'] ?? ''));
    if ((int)$__t['role_id'] === 1 || in_array($__t_role, ['admin', 'super admin', 'superadmin', 'super-admin', 'administrator'], true)) {
        $__sess_role = (int)($_SESSION['admin_role'] ?? $_SESSION['user_role'] ?? $_SESSION['role_id'] ?? 0);
        $__i_am_admin = ($__sess_role === 1);
        if (!$__i_am_admin && $__current_id > 0) {
            $__me_res = mysqli_query($conn, "SELECT u.role_id, r.role_name FROM admin_users u LEFT JOIN admin_roles r ON u.role_id = r.id WHERE u.id = $__current_id LIMIT 1");
            if ($__me_res && mysqli_num_rows($__me_res) > 0) {
                $__me = mysqli_fetch_assoc($__me_res);
                $__i_am_admin = ((int)$__me['role_id'] === 1) || in_array(strtolower(trim($__me['role_name'] ?? '')), ['admin', 'super admin', 'superadmin', 'super-admin', 'administrator'], true);
            }
        }
        if (!$__i_am_admin) {
            $_SESSION['error'] = 'Only an Admin can delete another Admin user.';
            header('Location: users.php');
            exit;
        }
    }
}

$sql = "DELETE FROM admin_users WHERE id = $id";
if (mysqli_query($conn, $sql)) {
    if (function_exists('logActivity')) {
        @logActivity('delete', 'Users', 'Deleted admin user (ID: ' . $id . ')');
    }
    $_SESSION['success'] = 'User deleted successfully.';
} else {
    $_SESSION['error'] = 'Error deleting user: ' . mysqli_error($conn);
}

header('Location: users.php');
exit;
