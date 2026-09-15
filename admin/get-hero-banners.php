<?php
// Returns list of images in the hero-banners directory as JSON
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$dir = dirname(__DIR__) . '/assets/img/hero/';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$allowed = ['jpg','jpeg','png','webp','gif','svg'];
$images = [];

foreach (glob($dir . '*') as $file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($ext, $allowed)) {
        $images[] = [
            'name' => basename($file),
            'path' => 'assets/img/hero/' . basename($file),
            'size' => round(filesize($file) / 1024, 1) . ' KB',
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($images);
?>
