<?php
// Prevent any output before JSON
ob_start();

session_start();

// Clean any previous output
ob_clean();

header('Content-Type: application/json');

// Error handling
error_reporting(0);
ini_set('display_errors', 0);

try {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    require_once '../db_config.php';

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'Product ID is required']);
        exit;
    }

    // Check if connection is valid
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . mysqli_connect_error()]);
        exit;
    }

    $productId = intval($_GET['id']);

    // Fetch product details
    $sql = "SELECT p.*, c.name as category_name, CONCAT(au.first_name, ' ', au.last_name) as created_by_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN admin_users au ON p.created_by = au.id 
            WHERE p.id = $productId";

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Query failed: ' . mysqli_error($conn)]);
        exit;
    }

    if (mysqli_num_rows($result) === 0) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    $product = mysqli_fetch_assoc($result);

    // Fetch stock from stock table
    $stockSql = "SELECT quantity FROM stock WHERE product_id = $productId";
    $stockResult = mysqli_query($conn, $stockSql);
    $stockQuantity = 0;
    if ($stockResult && mysqli_num_rows($stockResult) > 0) {
        $stockRow = mysqli_fetch_assoc($stockResult);
        $stockQuantity = $stockRow['quantity'];
    }

    // Fetch colors
    $colorSql = "SELECT col.color_name as name, col.color_code as code
                FROM product_colors pc
                JOIN colors col ON pc.color_id = col.id
                WHERE pc.product_id = $productId AND col.status = 'active'";
    $colorResult = mysqli_query($conn, $colorSql);
    $colors = [];
    if ($colorResult) {
        while ($row = mysqli_fetch_assoc($colorResult)) {
            $colors[] = $row;
        }
    }

    // Fetch sizes
    $sizeSql = "SELECT s.size_label
                FROM product_sizes ps
                JOIN sizes s ON ps.size_id = s.id
                WHERE ps.product_id = $productId AND s.status = 'active'";
    $sizeResult = mysqli_query($conn, $sizeSql);
    $sizes = [];
    if ($sizeResult) {
        while ($row = mysqli_fetch_assoc($sizeResult)) {
            $sizes[] = $row['size_label'];
        }
    }

    // Parse images
    $images = json_decode($product['image'], true);
    if (!is_array($images)) {
        $images = !empty($product['image']) ? [$product['image']] : [];
    }

    // Format date
    $createdAt = date('d M Y, h:i A', strtotime($product['created_at']));

    // Prepare response
    $response = [
        'success' => true,
        'product' => [
            'id' => $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => $product['price'],
            'stock' => $stockQuantity,
            'status' => $product['status'],
            'category_name' => $product['category_name'] ?: 'No Category',
            'youtube_video_id' => isset($product['youtube_video_id']) ? $product['youtube_video_id'] : '',
            'images' => $images,
            'colors' => $colors,
            'sizes' => $sizes,
            'created_at' => $createdAt,
            'created_by' => $product['created_by_name'] ?: 'Unknown',
            'custom_highlights_enabled' => !empty($product['custom_highlights_enabled']),
            'custom_highlights_title' => $product['custom_highlights_title'] ?? '',
            'custom_highlights_cards' => !empty($product['custom_highlights_cards']) ? (json_decode($product['custom_highlights_cards'], true) ?: []) : []
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
