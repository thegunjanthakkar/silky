<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

if (!isset($_POST['action']) || !isset($_POST['product_ids']) || !is_array($_POST['product_ids'])) {
    $_SESSION['error'] = 'Invalid request. Please select products and an action.';
    header('Location: products.php');
    exit;
}

$action = $_POST['action'];
$productIds = array_map('intval', $_POST['product_ids']);
$count = count($productIds);

if ($count === 0) {
    $_SESSION['error'] = 'No products selected.';
    header('Location: products.php');
    exit;
}

// Create placeholders for SQL IN clause
$placeholders = implode(',', $productIds);

switch ($action) {
    case 'activate':
        $sql = "UPDATE products SET status = 'active' WHERE id IN ($placeholders)";
        $successMessage = "$count product(s) activated successfully!";
        break;
        
    case 'deactivate':
        $sql = "UPDATE products SET status = 'inactive' WHERE id IN ($placeholders)";
        $successMessage = "$count product(s) deactivated successfully!";
        break;
        
    case 'draft':
        $sql = "UPDATE products SET status = 'draft' WHERE id IN ($placeholders)";
        $successMessage = "$count product(s) set to draft successfully!";
        break;
        
    case 'delete':
        // Delete related records first
        $deleteColorsSql = "DELETE FROM product_colors WHERE product_id IN ($placeholders)";
        $deleteSizesSql = "DELETE FROM product_sizes WHERE product_id IN ($placeholders)";
        
        mysqli_query($conn, $deleteColorsSql);
        mysqli_query($conn, $deleteSizesSql);
        
        // Delete products
        $sql = "DELETE FROM products WHERE id IN ($placeholders)";
        $successMessage = "$count product(s) deleted successfully!";
        break;
        
    default:
        $_SESSION['error'] = 'Invalid action specified.';
        header('Location: products.php');
        exit;
}

if (mysqli_query($conn, $sql)) {
    $_SESSION['success'] = $successMessage;
} else {
    $_SESSION['error'] = 'Error performing bulk action: ' . mysqli_error($conn);
}

header('Location: products.php');
exit;
