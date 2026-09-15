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
if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["success" => false, "message" => "No video uploaded or upload error"]);
    exit;
}

$type = $_POST['type'] ?? 'product_video';
$uploadDir = __DIR__ . '/../uploads/videos/';

// Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Validate video
$maxSize = 5 * 1024 * 1024; // 5MB
if ($_FILES['video']['size'] > $maxSize) {
    echo json_encode(["success" => false, "message" => "Video too large. Maximum 5MB allowed."]);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['video']['tmp_name']);
finfo_close($finfo);

$allowedVideoMimes = ['video/mp4', 'video/avi', 'video/quicktime', 'video/webm', 'video/x-msvideo'];
if (!in_array($mime, $allowedVideoMimes)) {
    echo json_encode(["success" => false, "message" => "Only MP4, AVI, MOV, WEBM video files are allowed."]);
    exit;
}

// Generate unique filename
$ext = pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION);
$productName = isset($_POST['name']) ? $_POST['name'] : 'video';
$productName = strtolower(trim(preg_replace('/\s+/', '-', $productName)));
$filename = date('YmdHis') . '_' . $productName . '.' . $ext;
$destPath = $uploadDir . $filename;

if (move_uploaded_file($_FILES['video']['tmp_name'], $destPath)) {
    $relativePath = './uploads/videos/' . $filename;
    $fullUrl = str_replace('./', '/', $relativePath);
    
    echo json_encode([
        "success" => true, 
        "message" => "Video uploaded successfully",
        "path" => $relativePath,
        "url" => $fullUrl,
        "filename" => $filename
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to upload video"]);
}
?>