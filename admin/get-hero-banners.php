<?php
// Returns list of images in the specified directory as JSON
session_start();
if ((!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) && !isset($_SESSION['admin_user_id']) && !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
require_once 'includes/permission-manager.php';
// Serves both website hero images and product images
if (!hasFileAccess('get-hero-banners.php')) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
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
            $basename = basename($file);
            $lower = strtolower($basename);
            $width = null; $height = null;
            if ($ext !== 'svg') {
                $dims = @getimagesize($file);
                if ($dims) { $width = $dims[0]; $height = $dims[1]; }
            }
            // Classify: filename tag wins (_mobile / _desktop from dual-cropper), else fall back to dimensions
            $kind = 'other';
            if (strpos($lower, '_mobile') !== false) {
                $kind = 'mobile';
            } elseif (strpos($lower, '_desktop') !== false) {
                $kind = 'desktop';
            } elseif ($width && $height && $height > 0) {
                $ratio = $width / $height;
                if ($ratio >= 1.55 && $ratio <= 2.1) {
                    $kind = 'desktop'; // ~16:9 landscape
                } elseif ($ratio >= 0.4 && $ratio <= 0.7) {
                    $kind = 'mobile';  // ~9:16 portrait
                } elseif ($height > $width * 1.2) {
                    $kind = 'mobile';
                } elseif ($width > $height * 1.2) {
                    $kind = 'desktop';
                }
            }
            $images[] = [
                'name' => $basename,
                'path' => $prefix . $basename,
                'size' => (filesize($file) >= 1048576) ? (round(filesize($file) / 1048576, 2) . ' MB') : (round(filesize($file) / 1024, 1) . ' KB'),
                'width' => $width,
                'height' => $height,
                'dims' => ($width && $height) ? ($width . '×' . $height) : '',
                'kind' => $kind, // desktop | mobile | other
            ];
        }
    }
}

header('Content-Type: application/json');
echo json_encode($images);
?>
