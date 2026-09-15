<?php
session_start();
require_once '../db_config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $sql = "DELETE FROM categories WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = 'Category deleted successfully.';
    } else {
        $_SESSION['error'] = 'Error deleting category: ' . mysqli_error($conn);
    }
} else {
    $_SESSION['error'] = 'Invalid category ID.';
}

header('Location: categories.php');
exit;