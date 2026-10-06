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

// Central rule (even via direct URL): self never; Admin targets need Admin actor;
// normal users need creator OR Admin actor
$__current_id = (int)($_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);
[$__ok, $__reason] = canManageUser($__current_id, $id);
if (!$__ok) {
    $_SESSION['error'] = $__reason;
    header('Location: users.php');
    exit;
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
