<?php
// Returns list of images in the specified directory as JSON
session_start();
if ((!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) && !isset($_SESSION['admin_user_id']) && !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? 'hero';

if ($type === 'product' || $type === 'products') {
    $dir = dirname(__DIR__) . '/uploads/products/';
    $prefix = './uploads/products/';
} else {
    $dir = dirname(__DIR__) . '/assets/img/hero/';
    $prefix = 'assets/img/hero/';
}

if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$allowed = ['jpg','jpeg','png','webp','gif','svg'];
$images = [];

$files = glob($dir . '*');
if ($files) {
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $images[] = [
                'name' => basename($file),
                'path' => $prefix . basename($file),
                'size' => (filesize($file) >= 1048576) ? (round(filesize($file) / 1048576, 2) . ' MB') : (round(filesize($file) / 1024, 1) . ' KB'),
            ];
        }
    }
}

header('Content-Type: application/json');
echo json_encode($images);
?>
