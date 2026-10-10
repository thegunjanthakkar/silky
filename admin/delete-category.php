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

if ($id > 0) {
    $name_res = @mysqli_query($conn, "SELECT name FROM categories WHERE id = $id LIMIT 1");
    $cat_name = ($name_res && ($nrow = mysqli_fetch_assoc($name_res))) ? $nrow['name'] : '';
    $sql = "DELETE FROM categories WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        if (function_exists('logActivity')) { @logActivity('delete', 'Categories', 'Deleted category: ' . $cat_name . ' (ID: ' . $id . ')'); }
        $_SESSION['success'] = 'Category deleted successfully.';
    } else {
        $_SESSION['error'] = 'Error deleting category: ' . mysqli_error($conn);
    }
} else {
    $_SESSION['error'] = 'Invalid category ID.';
}

header('Location: categories.php');
exit;