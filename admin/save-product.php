<?php
session_start();
require_once '../db_config.php';
require_once 'includes/permission-manager.php';

// Check if user is logged in
if (!isset($_SESSION['admin_user_id']) && !isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please login first.';
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $product_code = trim($_POST['product_code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $compare_price = (isset($_POST['compare_price']) && $_POST['compare_price'] !== '') ? floatval($_POST['compare_price']) : 'NULL';
    $grams = (isset($_POST['grams']) && $_POST['grams'] !== '') ? floatval($_POST['grams']) : 'NULL';
    $status = ($_POST['status'] ?? 'draft');
    $images = trim($_POST['images'] ?? '');
    $youtube_video_id = trim($_POST['youtube_video_id'] ?? '');
    $colors = $_POST['colors'] ?? [];
    $sizes = $_POST['sizes'] ?? [];
    $user_id = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'];

    // Debug: Check what data is being received
    error_log("Images data received: " . $images);
    error_log("Images length: " . strlen($images));

    // Validation
    if (empty($name)) {
        $_SESSION['error'] = 'Product name is required.';
        header('Location: add-product.php');
        exit;
    }

    if (empty($description)) {
        $_SESSION['error'] = 'Description is required.';
        header('Location: add-product.php');
        exit;
    }

    if ($category_id <= 0) {
        $_SESSION['error'] = 'Please select a category.';
        header('Location: add-product.php');
        exit;
    }

    if ($price <= 0) {
        $_SESSION['error'] = 'Price must be greater than 0.';
        header('Location: add-product.php');
        exit;
    }

    if ($compare_price !== 'NULL' && $compare_price <= $price) {
        $_SESSION['error'] = 'Compare price must be greater than the main price.';
        header('Location: add-product.php');
        exit;
    }

    // Validate colors
    if (empty($colors) || !is_array($colors)) {
        $_SESSION['error'] = 'Please select at least one color.';
        header('Location: add-product.php');
        exit;
    }

    // Validate sizes
    if (empty($sizes) || !is_array($sizes)) {
        $_SESSION['error'] = 'Please select at least one size.';
        header('Location: add-product.php');
        exit;
    }

    // Validate variant compare prices
    $variant_prices = $_POST['variant_price'] ?? [];
    $variant_compare_prices = $_POST['variant_compare_price'] ?? [];
    foreach ($colors as $color_id) {
        if (intval($color_id) > 0) {
            foreach ($sizes as $size_id) {
                if (intval($size_id) > 0) {
                    $v_price = isset($variant_prices[$color_id][$size_id]) ? floatval($variant_prices[$color_id][$size_id]) : $price;
                    $v_cp = (isset($variant_compare_prices[$color_id][$size_id]) && $variant_compare_prices[$color_id][$size_id] !== '') ? floatval($variant_compare_prices[$color_id][$size_id]) : 'NULL';
                    if ($v_cp !== 'NULL' && $v_cp <= $v_price) {
                        $_SESSION['error'] = 'Variant compare price must be greater than the variant price.';
                        header('Location: add-product.php');
                        exit;
                    }
                }
            }
        }
    }

    // Validate images
    if (empty($images)) {
        $_SESSION['error'] = 'At least 1 product image is required.';
        header('Location: add-product.php');
        exit;
    }

    // Decode and validate images JSON
    $imagesArray = json_decode($images, true);
    if (!is_array($imagesArray) || count($imagesArray) === 0) {
        $_SESSION['error'] = 'Invalid images data. Please upload at least 1 image.';
        header('Location: add-product.php');
        exit;
    }

    if (count($imagesArray) > 6) {
        $_SESSION['error'] = 'Maximum 6 images allowed per product.';
        header('Location: add-product.php');
        exit;
    }

    // Images can be 1-6, not necessarily 6
    // Convert images array to JSON string for database
    $imagesJson = json_encode($imagesArray);

    // YouTube video ID is optional, validate if provided
    if (!empty($youtube_video_id)) {
        // Validate YouTube video ID format (11 characters, alphanumeric with - and _)
        if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $youtube_video_id)) {
            $_SESSION['error'] = 'Invalid YouTube video ID format.';
            header('Location: add-product.php');
            exit;
        }
    }

    if (!in_array($status, ['active', 'inactive', 'draft'])) {
        $status = 'draft';
    }

    // Category is required, no NULL values
    if ($category_id <= 0) {
        $_SESSION['error'] = 'Please select a valid category.';
        header('Location: add-product.php');
        exit;
    }

    // Generate a unique slug
    $base_slug = strtolower(trim($_POST['name'] ?? ''));
    $base_slug = preg_replace('/[^a-z0-9-]+/', '-', $base_slug);
    $base_slug = preg_replace('/-+/', '-', $base_slug);
    $base_slug = trim($base_slug, '-');
    if (empty($base_slug)) $base_slug = 'product-' . time();
    
    $slug = $base_slug;
    $counter = 1;
    while (true) {
        $check = mysqli_query($conn, "SELECT id FROM products WHERE slug = '$slug'");
        if (mysqli_num_rows($check) == 0) break;
        $slug = $base_slug . '-' . $counter;
        $counter++;
    }

    // Escape strings for SQL
    $name = mysqli_real_escape_string($conn, $name);
    $product_code = $product_code !== '' ? "'" . mysqli_real_escape_string($conn, $product_code) . "'" : "NULL";
    $description = mysqli_real_escape_string($conn, $description);
    $status = mysqli_real_escape_string($conn, $status);
    $imagesJson = mysqli_real_escape_string($conn, $imagesJson);
    $youtube_video_id = mysqli_real_escape_string($conn, $youtube_video_id);

    // Custom overview highlights
    $custom_highlights_enabled = isset($_POST['custom_highlights_enabled']) ? 1 : 0;
    $custom_highlights_title = trim($_POST['custom_highlights_title'] ?? '');
    $custom_highlights_cards = [];
    if (isset($_POST['custom_highlight_title']) && is_array($_POST['custom_highlight_title'])) {
        foreach ($_POST['custom_highlight_title'] as $i => $t) {
            $t = trim($t);
            $icon = trim($_POST['custom_highlight_icon'][$i] ?? 'bi bi-gem');
            $desc = trim($_POST['custom_highlight_desc'][$i] ?? '');
            if ($t !== '' || $desc !== '') {
                $custom_highlights_cards[] = [
                    'icon' => $icon,
                    'title' => $t,
                    'desc' => $desc
                ];
            }
        }
    }
    $custom_hl_title_sql = $custom_highlights_title !== '' ? "'" . mysqli_real_escape_string($conn, $custom_highlights_title) . "'" : "NULL";
    $custom_hl_cards_sql = !empty($custom_highlights_cards) ? "'" . mysqli_real_escape_string($conn, json_encode($custom_highlights_cards)) . "'" : "NULL";

    // Insert product into database (without size and color fields)
    $sql = "INSERT INTO products (name, product_code, slug, description, category_id, price, compare_price, grams, status, image, youtube_video_id, custom_highlights_enabled, custom_highlights_title, custom_highlights_cards, created_by, created_at) 
            VALUES ('$name', $product_code, '$slug', '$description', '$category_id', '$price', $compare_price, $grams, '$status', '$imagesJson', '$youtube_video_id', $custom_highlights_enabled, $custom_hl_title_sql, $custom_hl_cards_sql, '$user_id', NOW())";
    
    if (mysqli_query($conn, $sql)) {
        $product_id = mysqli_insert_id($conn);
        
        // Insert color mappings
        foreach ($colors as $color_id) {
            $color_id = intval($color_id);
            if ($color_id > 0) {
                $color_sql = "INSERT INTO product_colors (product_id, color_id) VALUES ($product_id, $color_id)";
                mysqli_query($conn, $color_sql);
            }
        }
        
        // Insert size mappings
        foreach ($sizes as $size_id) {
            $size_id = intval($size_id);
            if ($size_id > 0) {
                $size_sql = "INSERT INTO product_sizes (product_id, size_id) VALUES ($product_id, $size_id)";
                mysqli_query($conn, $size_sql);
            }
        }

        // Insert variants and calculate total stock
        $variant_prices = $_POST['variant_price'] ?? [];
        $variant_compare_prices = $_POST['variant_compare_price'] ?? [];
        $variant_grams = $_POST['variant_grams'] ?? [];
        $variant_stocks = $_POST['variant_stock'] ?? [];
        $total_stock = 0;

        foreach ($colors as $color_id) {
            $color_id = intval($color_id);
            if ($color_id > 0) {
                foreach ($sizes as $size_id) {
                    $size_id = intval($size_id);
                    if ($size_id > 0) {
                        $v_price = isset($variant_prices[$color_id][$size_id]) ? floatval($variant_prices[$color_id][$size_id]) : $price;
                        $v_cp = (isset($variant_compare_prices[$color_id][$size_id]) && $variant_compare_prices[$color_id][$size_id] !== '') ? floatval($variant_compare_prices[$color_id][$size_id]) : 'NULL';
                        $v_g = (isset($variant_grams[$color_id][$size_id]) && $variant_grams[$color_id][$size_id] !== '') ? floatval($variant_grams[$color_id][$size_id]) : 'NULL';
                        $v_stock = isset($variant_stocks[$color_id][$size_id]) ? intval($variant_stocks[$color_id][$size_id]) : 0;
                        
                        $total_stock += $v_stock;

                        $variant_sql = "INSERT INTO product_variants (product_id, color_id, size_id, price, compare_price, grams, stock_quantity) VALUES ($product_id, $color_id, $size_id, $v_price, $v_cp, $v_g, $v_stock)";
                        mysqli_query($conn, $variant_sql);
                    }
                }
            }
        }

        // Also add total stock to the main stock table to maintain compatibility with existing features
        $stock_sql = "INSERT INTO stock (product_id, quantity, last_updated, updated_by) VALUES ($product_id, $total_stock, NOW(), $user_id)";
        mysqli_query($conn, $stock_sql);
        
        $_SESSION['success'] = 'Product added successfully!';
        header('Location: products.php');
        exit;
    } else {
        $_SESSION['error'] = 'Database error: ' . mysqli_error($conn);
        header('Location: add-product.php');
        exit;
    }
} else {
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: add-product.php');
    exit;
}
?>