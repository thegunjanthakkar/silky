<?php
session_start();
require_once '../db_config.php';
require_once 'includes/permission-manager.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['admin_user_id']) && !isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please login first.';
    header('Location: login.php');
    exit;
}

$product_id = intval($_GET['id'] ?? 0);
if ($product_id <= 0) {
    $_SESSION['error'] = 'Invalid product ID.';
    header('Location: products.php');
    exit;
}

// Get product data to check if image exists
$sql = "SELECT image FROM products WHERE id = '" . mysqli_real_escape_string($conn, $product_id) . "'";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $product = mysqli_fetch_assoc($result);
    
    // Delete physical image file if exists
    if (!empty($product['image'])) {
        $file_path = __DIR__ . '/../' . ltrim(str_replace(['./', '\\'], ['','/'], $product['image']), '/');
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Delete product from database
    $delete_sql = "DELETE FROM products WHERE id = '" . mysqli_real_escape_string($conn, $product_id) . "'";
    if (mysqli_query($conn, $delete_sql)) {
        $_SESSION['success'] = 'Product deleted successfully!';
    } else {
        $_SESSION['error'] = 'Failed to delete product: ' . mysqli_error($conn);
    }
} else {
    $_SESSION['error'] = 'Product not found.';
}

header('Location: products.php');
exit;
?>