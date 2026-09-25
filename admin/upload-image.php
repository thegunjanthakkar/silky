<?php
session_start();
require_once '../db_config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_user_id']) && !isset($_SESSION['user_id']) && (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true)) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Please login first"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["success" => false, "message" => "No file uploaded or upload error"]);
    exit;
}

$type = $_POST['type'] ?? 'product'; // 'product', 'category', 'addon', or 'hero'
if ($type === 'hero') {
    $uploadDir = dirname(__DIR__) . '/assets/img/hero/';
    $folderName = 'hero';
} elseif ($type === 'category') {
    $folderName = 'categories';
    $uploadDir = __DIR__ . '/../uploads/' . $folderName . '/';
} else {
    $folderName = ($type === 'addon') ? 'addons' : $type . 's';
    $uploadDir = __DIR__ . '/../uploads/' . $folderName . '/';
}

// Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Validate image (5MB limit for cropped WebP files)
$maxSize = 5 * 1024 * 1024; // 5MB
if ($_FILES['image']['size'] > $maxSize) {
    echo json_encode(["success" => false, "message" => "Image too large. Maximum 5MB allowed."]);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
finfo_close($finfo);

if (strpos($mime, 'image/') !== 0) {
    echo json_encode(["success" => false, "message" => "Only image files are allowed."]);
    exit;
}

// Generate unique filename
$ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
if ($mime === 'image/webp' || $ext === 'webp') {
    $ext = 'webp';
} elseif (empty($ext)) {
    $mimeMap = [
        'image/webp' => 'webp',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif'
    ];
    $ext = $mimeMap[$mime] ?? 'webp';
}
$prefix = ($type === 'hero') ? 'hero_' : '';
$filename = time() . '_' . $prefix . bin2hex(random_bytes(6)) . '.' . $ext;
$destPath = $uploadDir . $filename;

if (move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
    if ($type === 'hero') {
        $relativePath = 'assets/img/hero/' . $filename;
        $fullUrl = '/' . $relativePath;
    } else {
        $relativePath = './uploads/' . $folderName . '/' . $filename;
        $fullUrl = str_replace('./', '/', $relativePath);
    }
    
    echo json_encode([
        "success" => true, 
        "message" => "Image uploaded successfully",
        "path" => $relativePath,
        "url" => $fullUrl,
        "filename" => $filename
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to upload image"]);
}
?>