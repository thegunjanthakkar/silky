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

    // Custom care instructions
    $custom_care_enabled = isset($_POST['custom_care_enabled']) ? 1 : 0;
    $custom_care_title_raw = $_POST['custom_care_title'] ?? '';
    if (is_array($custom_care_title_raw)) {
        $custom_care_title = '';
    } else {
        $custom_care_title = trim((string)$custom_care_title_raw);
    }
    if (isset($_POST['custom_care_heading']) && is_string($_POST['custom_care_heading'])) {
        $custom_care_title = trim($_POST['custom_care_heading']);
    }

    $custom_care_cards = [];
    $raw_card_titles = $_POST['custom_care_card_title'] ?? (is_array($_POST['custom_care_title'] ?? null) ? $_POST['custom_care_title'] : []);
    $raw_card_icons  = $_POST['custom_care_card_icon'] ?? ($_POST['custom_care_icon'] ?? []);
    $raw_card_colors = $_POST['custom_care_card_color'] ?? ($_POST['custom_care_color'] ?? []);
    $raw_card_descs  = $_POST['custom_care_card_desc'] ?? ($_POST['custom_care_desc'] ?? []);

    if (is_array($raw_card_titles)) {
        foreach ($raw_card_titles as $i => $t) {
            $t = is_string($t) ? trim($t) : '';
            $icon = isset($raw_card_icons[$i]) && is_string($raw_card_icons[$i]) ? trim($raw_card_icons[$i]) : 'bi bi-droplet-half';
            $color = isset($raw_card_colors[$i]) && is_string($raw_card_colors[$i]) ? trim($raw_card_colors[$i]) : '#0dcaf0';
            $desc = isset($raw_card_descs[$i]) && is_string($raw_card_descs[$i]) ? trim($raw_card_descs[$i]) : '';
            if ($t !== '' || $desc !== '') {
                $custom_care_cards[] = [
                    'icon' => $icon,
                    'color' => $color,
                    'title' => $t,
                    'desc' => $desc
                ];
            }
        }
    }
    $custom_care_title_sql = $custom_care_title !== '' ? "'" . mysqli_real_escape_string($conn, $custom_care_title) . "'" : "NULL";
    $custom_care_cards_sql = !empty($custom_care_cards) ? "'" . mysqli_real_escape_string($conn, json_encode($custom_care_cards)) . "'" : "NULL";

    $is_bestseller = isset($_POST['is_bestseller']) ? 1 : 0;

    // Auto-ensure custom_care columns exist in products table
    $col_care_check = @mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'custom_care_enabled'");
    if ($col_care_check && mysqli_num_rows($col_care_check) == 0) {
        @mysqli_query($conn, "ALTER TABLE `products` ADD COLUMN `custom_care_enabled` TINYINT(1) NOT NULL DEFAULT 0");
        @mysqli_query($conn, "ALTER TABLE `products` ADD COLUMN `custom_care_title` VARCHAR(255) NULL");
        @mysqli_query($conn, "ALTER TABLE `products` ADD COLUMN `custom_care_cards` TEXT NULL");
    }

    // Addon products
    $addon_title_raw = $_POST['addon_title'] ?? '';
    $addon_title = is_string($addon_title_raw) ? trim($addon_title_raw) : '';
    $addon_products = [];
    $addon_types        = isset($_POST['addon_type'])              && is_array($_POST['addon_type'])              ? $_POST['addon_type']              : [];
    $addon_product_ids  = isset($_POST['addon_product_id'])        && is_array($_POST['addon_product_id'])        ? $_POST['addon_product_id']        : [];
    $addon_custom_names = isset($_POST['addon_custom_name'])       && is_array($_POST['addon_custom_name'])       ? $_POST['addon_custom_name']       : [];
    $addon_custom_imgs  = isset($_POST['addon_custom_image_path']) && is_array($_POST['addon_custom_image_path']) ? $_POST['addon_custom_image_path'] : [];
    $addon_custom_prices= isset($_POST['addon_custom_price'])      && is_array($_POST['addon_custom_price'])      ? $_POST['addon_custom_price']      : [];
    $addon_needs_meas   = isset($_POST['addon_needs_measurement']) && is_array($_POST['addon_needs_measurement']) ? $_POST['addon_needs_measurement'] : [];
    $addon_custom_colors= isset($_POST['addon_custom_color'])      && is_array($_POST['addon_custom_color'])      ? $_POST['addon_custom_color']      : [];
    $addon_custom_sizes = isset($_POST['addon_custom_size'])       && is_array($_POST['addon_custom_size'])       ? $_POST['addon_custom_size']       : [];

    foreach ($addon_types as $idx => $atype) {
        $atype = ($atype === 'custom') ? 'custom' : 'catalog';
        if ($atype === 'catalog') {
            $apid = intval($addon_product_ids[$idx] ?? 0);
            if ($apid <= 0) continue;
            $custom_p = isset($addon_custom_prices[$idx]) ? trim($addon_custom_prices[$idx]) : '';
            $addon_products[] = [
                'type'              => 'catalog',
                'product_id'        => $apid,
                'custom_price'      => (is_numeric($custom_p) && floatval($custom_p) > 0) ? floatval($custom_p) : null,
                'needs_measurement' => in_array((string)$idx, $addon_needs_meas) ? true : false,
            ];
        } else {
            $cname = trim($addon_custom_names[$idx] ?? '');
            if ($cname === '') continue;
            $custom_p = isset($addon_custom_prices[$idx]) ? trim($addon_custom_prices[$idx]) : '';
            $addon_products[] = [
                'type'              => 'custom',
                'custom_name'       => $cname,
                'custom_image'      => trim($addon_custom_imgs[$idx] ?? ''),
                'custom_price'      => (is_numeric($custom_p) && floatval($custom_p) > 0) ? floatval($custom_p) : 0,
                'custom_color'      => trim($addon_custom_colors[$idx] ?? ''),
                'custom_size'       => trim($addon_custom_sizes[$idx] ?? ''),
                'needs_measurement' => in_array((string)$idx, $addon_needs_meas) ? true : false,
            ];
        }
    }
    $addon_title_sql = $addon_title !== '' ? "'" . mysqli_real_escape_string($conn, $addon_title) . "'" : "NULL";
    $addon_products_sql = !empty($addon_products) ? "'" . mysqli_real_escape_string($conn, json_encode($addon_products)) . "'" : "NULL";

    // Auto-ensure addon columns exist in products table
    $col_addon_check = @mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'addon_products'");
    if ($col_addon_check && mysqli_num_rows($col_addon_check) == 0) {
        @mysqli_query($conn, "ALTER TABLE `products` ADD COLUMN `addon_title` VARCHAR(255) NULL");
        @mysqli_query($conn, "ALTER TABLE `products` ADD COLUMN `addon_products` TEXT NULL");
    }

    // Auto-ensure custom_measurement_fields column exists
    $col_cmf = @mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'custom_measurement_fields'");
    if ($col_cmf && mysqli_num_rows($col_cmf) == 0) {
        @mysqli_query($conn, "ALTER TABLE `products` ADD COLUMN `custom_measurement_fields` TEXT NULL");
    }

    // Parse custom measurement fields
    $raw_cmf = trim($_POST['custom_measurement_fields_json'] ?? '');
    $custom_meas_arr = ($raw_cmf !== '') ? (json_decode($raw_cmf, true) ?: []) : [];
    $custom_meas_arr = array_values(array_filter(array_map('trim', $custom_meas_arr)));
    $custom_meas_sql = !empty($custom_meas_arr) ? "'" . mysqli_real_escape_string($conn, json_encode($custom_meas_arr)) . "'" : "NULL";

    // Insert product into database (without size and color fields)
    $sql = "INSERT INTO products (name, product_code, slug, description, category_id, price, compare_price, grams, status, is_bestseller, image, youtube_video_id, custom_highlights_enabled, custom_highlights_title, custom_highlights_cards, custom_care_enabled, custom_care_title, custom_care_cards, addon_title, addon_products, custom_measurement_fields, created_by, created_at) 
            VALUES ('$name', $product_code, '$slug', '$description', '$category_id', '$price', $compare_price, $grams, '$status', $is_bestseller, '$imagesJson', '$youtube_video_id', $custom_highlights_enabled, $custom_hl_title_sql, $custom_hl_cards_sql, $custom_care_enabled, $custom_care_title_sql, $custom_care_cards_sql, $addon_title_sql, $addon_products_sql, $custom_meas_sql, '$user_id', NOW())";
    
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