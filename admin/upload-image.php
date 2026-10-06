<?php
session_start();
require_once '../db_config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_user_id']) && !isset($_SESSION['user_id']) && (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true)) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Please login first"]);
    exit;
}
require_once 'includes/permission-manager.php';
if (!hasFileAccess('upload-image.php')) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "You do not have permission to upload images"]);
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

// Validate image (50MB limit for hero images, products, and categories)
$maxSize = 50 * 1024 * 1024; // 50MB
if ($_FILES['image']['size'] > $maxSize) {
    echo json_encode(["success" => false, "message" => "Image too large. Maximum 50MB allowed."]);
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
// Helper function to sanitize name parts into clean URL-safe slugs
if (!function_exists('sanitizeImageNamePart')) {
    function sanitizeImageNamePart($str) {
        if (empty($str)) return '';
        $str = strip_tags($str);
        $str = preg_replace('/[^\p{L}\p{N}\s_-]/u', '', $str);
        $str = preg_replace('/[\s_]+/', '-', $str);
        $str = preg_replace('/-+/', '-', $str);
        $str = trim($str, '-');
        return strtolower($str);
    }
}

// Generate filename based on type
if ($type === 'product') {
    $categoryName = trim($_POST['category_name'] ?? '');
    $categoryId = intval($_POST['category_id'] ?? 0);
    $productName = trim($_POST['product_name'] ?? $_POST['name'] ?? '');

    // If category name is empty but category_id is provided, look it up in DB
    if (empty($categoryName) && $categoryId > 0 && isset($conn)) {
        $catStmt = mysqli_query($conn, "SELECT name FROM categories WHERE id = " . $categoryId . " LIMIT 1");
        if ($catStmt && $row = mysqli_fetch_assoc($catStmt)) {
            $categoryName = $row['name'] ?? '';
        }
    }

    $catSlug = sanitizeImageNamePart($categoryName);
    $prodSlug = sanitizeImageNamePart($productName);

    if (empty($catSlug)) {
        $catSlug = 'product';
    }
    if (empty($prodSlug)) {
        $clientBase = pathinfo($_FILES['image']['name'] ?? '', PATHINFO_FILENAME);
        $prodSlug = sanitizeImageNamePart($clientBase);
        if (empty($prodSlug) || $prodSlug === 'blob' || $prodSlug === 'image') {
            $prodSlug = 'item-' . time();
        }
    }

    $baseName = $catSlug . '_' . $prodSlug;
    $filename = $baseName . '.' . $ext;

    // Avoid overwriting existing files (append _2, _3, etc.)
    $counter = 1;
    while (file_exists($uploadDir . $filename)) {
        $counter++;
        $filename = $baseName . '_' . $counter . '.' . $ext;
    }
} elseif ($type === 'category') {
    $categoryName = trim($_POST['category_name'] ?? $_POST['name'] ?? '');
    $catSlug = sanitizeImageNamePart($categoryName);
    if (!empty($catSlug)) {
        $baseName = 'category_' . $catSlug;
        $filename = $baseName . '.' . $ext;
        $counter = 1;
        while (file_exists($uploadDir . $filename)) {
            $counter++;
            $filename = $baseName . '_' . $counter . '.' . $ext;
        }
    } else {
        $filename = time() . '_category_' . bin2hex(random_bytes(6)) . '.' . $ext;
    }
} elseif ($type === 'addon') {
    $addonName = trim($_POST['addon_name'] ?? $_POST['name'] ?? '');
    $addonSlug = sanitizeImageNamePart($addonName);
    if (!empty($addonSlug)) {
        $baseName = 'addon_' . $addonSlug;
        $filename = $baseName . '.' . $ext;
        $counter = 1;
        while (file_exists($uploadDir . $filename)) {
            $counter++;
            $filename = $baseName . '_' . $counter . '.' . $ext;
        }
    } else {
        $filename = time() . '_addon_' . bin2hex(random_bytes(6)) . '.' . $ext;
    }
} else {
    $prefix = ($type === 'hero') ? 'hero_' : '';
    $filename = time() . '_' . $prefix . bin2hex(random_bytes(6)) . '.' . $ext;
}
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