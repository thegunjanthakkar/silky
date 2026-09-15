<?php
session_start();
require_once '../db_config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_user_id']) && !isset($_SESSION['user_id'])) {
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

$type = $_POST['type'] ?? 'product'; // 'product' or 'category'
$uploadDir = __DIR__ . '/../uploads/' . $type . 's/';

// Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Validate image
$maxSize = 1.5 * 1024 * 1024; // 1.5MB
if ($_FILES['image']['size'] > $maxSize) {
    echo json_encode(["success" => false, "message" => "Image too large. Maximum 1.5MB allowed."]);
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
$ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
$filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$destPath = $uploadDir . $filename;

if (move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
    $relativePath = './uploads/' . $type . 's/' . $filename;
    $fullUrl = str_replace('./', '/', $relativePath);
    
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