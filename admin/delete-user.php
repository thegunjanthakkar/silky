<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once '../db_config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $sql = "DELETE FROM admin_users WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = 'User deleted successfully.';
    } else {
        $_SESSION['error'] = 'Error deleting user: ' . mysqli_error($conn);
    }
} else {
    $_SESSION['error'] = 'Invalid user ID.';
}

header('Location: users.php');
exit;
