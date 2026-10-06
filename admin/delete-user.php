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
$__current_id = (int)($_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? 0);
if ($__current_id > 0 && $id === $__current_id) {
    $_SESSION['error'] = 'You cannot delete your own account.';
    header('Location: users.php');
    exit;
}

// Protected: users whose role is Admin cannot be deleted by anyone (even via direct URL)
$__target = mysqli_query($conn, "SELECT u.role_id, r.role_name FROM admin_users u LEFT JOIN admin_roles r ON u.role_id = r.id WHERE u.id = $id LIMIT 1");
if ($__target && mysqli_num_rows($__target) > 0) {
    $__t = mysqli_fetch_assoc($__target);
    $__t_role = strtolower(trim($__t['role_name'] ?? ''));
    if ((int)$__t['role_id'] === 1 || in_array($__t_role, ['admin', 'super admin', 'administrator'], true)) {
        $_SESSION['error'] = 'This user has an Admin role and cannot be deleted.';
        header('Location: users.php');
        exit;
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
