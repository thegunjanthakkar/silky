<?php
session_start();
require_once '../db_config.php';

// Get role ID from query string
$role_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($role_id <= 0) {
    $_SESSION['role_flash'] = [
        'type' => 'error',
        'messages' => ['Invalid role ID.']
    ];
    header('Location: user-roles.php');
    exit;
}

// Check if role exists
$sql = "SELECT id FROM admin_roles WHERE id = '" . mysqli_real_escape_string($conn, $role_id) . "'";
$result = mysqli_query($conn, $sql);
if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['role_flash'] = [
        'type' => 'error',
        'messages' => ['Role not found.']
    ];
    header('Location: user-roles.php');
    exit;
}

// Delete role
$del_sql = "DELETE FROM admin_roles WHERE id = '" . mysqli_real_escape_string($conn, $role_id) . "'";
if (mysqli_query($conn, $del_sql)) {
    $_SESSION['role_flash'] = [
        'type' => 'success',
        'messages' => ['Role deleted successfully.']
    ];
} else {
    $_SESSION['role_flash'] = [
        'type' => 'error',
        'messages' => ['Failed to delete role.']
    ];
}
header('Location: user-roles.php');
exit;
?>
