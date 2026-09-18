<?php
// Database configuration for Silky Saree project

// Disable automatic fatal mysqli exceptions so connection errors can be caught gracefully
if (function_exists('mysqli_report')) {
    @mysqli_report(MYSQLI_REPORT_OFF);
}

$servername  = "localhost";
$username_db = "root";
$password_db = "";
$dbname      = "silky_saree";

// Trim variables to remove accidental leading/trailing spaces
$servername  = trim($servername);
$username_db = trim($username_db);
$password_db = trim($password_db);
$dbname      = trim($dbname);

// Create connection gracefully
$conn = @mysqli_connect($servername, $username_db, $password_db, $dbname);

// Check connection
if (!$conn) {
    $db_error = mysqli_connect_error();
} else {
    // Set charset to utf8mb4 for proper emoji handling
    @mysqli_set_charset($conn, "utf8mb4");
}

// Universal Dynamic Base URL Detection (bulletproof for rewritten URLs like /product-details/slug)
if (!defined('BASE_URL')) {
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = str_replace('\\', '/', dirname($script_name));
    
    // Step up to project root if executed script is inside /admin or /includes
    if (substr($dir, -6) === '/admin') {
        $dir = substr($dir, 0, -6);
    } elseif (substr($dir, -9) === '/includes') {
        $dir = substr($dir, 0, -9);
    }
    
    $b_url = rtrim($dir, '/') . '/';
    if ($b_url === '//') {
        $b_url = '/';
    }
    define('BASE_URL', $b_url);
}

$base_url = BASE_URL;

// Ensure default product placeholder images exist
$admin_def_img = __DIR__ . '/admin/assets/images/products/default.png';
if (!file_exists($admin_def_img)) {
    $src_sample = __DIR__ . '/admin/assets/images/products/01.png';
    if (file_exists($src_sample)) {
        @copy($src_sample, $admin_def_img);
    }
}
$root_def_img = __DIR__ . '/assets/images/products/default.png';
if (!file_exists($root_def_img)) {
    if (!is_dir(__DIR__ . '/assets/images/products')) {
        @mkdir(__DIR__ . '/assets/images/products', 0755, true);
    }
    if (file_exists($admin_def_img)) {
        @copy($admin_def_img, $root_def_img);
    }
}