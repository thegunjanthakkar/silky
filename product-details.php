<?php
session_start();
require_once 'db_config.php';

$product_slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$product_id_param = isset($_GET['id']) ? intval($_GET['id']) : 0;

$where_clause = ($product_id_param > 0) ? "p.id = ?" : "p.slug = ?";
$param_type = ($product_id_param > 0) ? "i" : "s";
$param_val = ($product_id_param > 0) ? $product_id_param : $product_slug;

$sql = "SELECT p.*, c.name as category_name,
        IFNULL(stk.quantity, 0) as stock,
        GROUP_CONCAT(DISTINCT CONCAT(col.color_name, '|', col.color_code) SEPARATOR '~') as product_colors,
        GROUP_CONCAT(DISTINCT s.size_label ORDER BY FIELD(s.size_label, 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'Free Size', 'Custom'), s.size_label SEPARATOR '~') as product_sizes
        FROM products p 
        LEFT JOIN stock stk ON p.id = stk.product_id
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN product_colors pc ON p.id = pc.product_id
        LEFT JOIN colors col ON pc.color_id = col.id AND col.status = 'active'
        LEFT JOIN product_sizes ps ON p.id = ps.product_id
        LEFT JOIN sizes s ON ps.size_id = s.id AND s.status = 'active'
        WHERE $where_clause AND p.status = 'active'
        GROUP BY p.id";

$product = null;
$stmt = @mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $param_type, $param_val);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        $product = mysqli_fetch_assoc($result);
    }
}

if (!$product && (!empty($product_slug) || $product_id_param > 0)) {
    $fallback_sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE $where_clause AND p.status = 'active'";
    $stmt2 = @mysqli_prepare($conn, $fallback_sql);
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, $param_type, $param_val);
        mysqli_stmt_execute($stmt2);
        $result2 = mysqli_stmt_get_result($stmt2);
        if ($result2) {
            $product = mysqli_fetch_assoc($result2);
        }
    }
}

if (!$product) {
    header('Location: products.php');
    exit;
}

$product_images = [];
// Fix: use 'image' column instead of 'images'
if (!empty($product['image'])) {
    $decoded = json_decode($product['image'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $product_images = $decoded;
    } else {
        $product_images = array_map('trim', explode(',', $product['image']));
    }
}

$colors = [];
if (!empty($product['product_colors'])) {
    $color_items = explode('~', $product['product_colors']);
    foreach ($color_items as $color_item) {
        $parts = explode('|', $color_item);
        if (count($parts) == 2) {
            $colors[] = ['name' => $parts[0], 'code' => $parts[1]];
        }
    }
} elseif (!empty($product['color'])) {
    // Fallback to the basic 'color' column if product_colors relation is empty
    $basic_colors = array_map('trim', explode(',', $product['color']));
    foreach ($basic_colors as $c) {
        if (!empty($c)) {
            // Assign a default hex code (e.g., #0e2187 or generate one) since it's just a string
            $colors[] = ['name' => $c, 'code' => '#0e2187']; 
        }
    }
}

// Fetch variants mapping
$variants = [];
$variants_sql = "SELECT c.color_name, s.size_label, pv.price, pv.compare_price, pv.grams, pv.stock_quantity 
                 FROM product_variants pv 
                 JOIN colors c ON pv.color_id = c.id 
                 JOIN sizes s ON pv.size_id = s.id 
                 WHERE pv.product_id = " . intval($product['id']);
$variants_res = mysqli_query($conn, $variants_sql);
if ($variants_res) {
    while($row = mysqli_fetch_assoc($variants_res)) {
        $key = trim($row['color_name']) . '_' . trim($row['size_label']);
        $variants[$key] = [
            'price' => floatval($row['price']),
            'compare_price' => $row['compare_price'] ? floatval($row['compare_price']) : null,
            'grams' => $row['grams'] ? floatval($row['grams']) : null,
            'stock' => intval($row['stock_quantity'])
        ];
    }
}

$sizes = [];
if (!empty($product['product_sizes'])) {
    $sizes = explode('~', $product['product_sizes']);
} elseif (!empty($product['size'])) {
    // Fallback to the basic 'size' column if product_sizes relation is empty
    $sizes = array_map('trim', explode(',', $product['size']));
}

$main_image = !empty($product_images) ? $product_images[0] : 'assets/img/product/product-1.webp';
$has_video = !empty($product['youtube_video_id']);
$video_id = $has_video ? htmlspecialchars($product['youtube_video_id']) : '';

// Fetch reviews
$reviews = [];
$avg_rating = 0;
$total_reviews = 0;
$reviews_sql = "SELECT r.*, u.first_name, u.last_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? AND r.status = 'approved' ORDER BY r.created_at DESC";
$stmt_rev = mysqli_prepare($conn, $reviews_sql);
if ($stmt_rev) {
    mysqli_stmt_bind_param($stmt_rev, "i", $product['id']);
    mysqli_stmt_execute($stmt_rev);
    $res_rev = mysqli_stmt_get_result($stmt_rev);
    $sum_rating = 0;
    if ($res_rev) {
        while ($row_rev = mysqli_fetch_assoc($res_rev)) {
            $reviews[] = $row_rev;
            $sum_rating += $row_rev['rating'];
        }
    }
    $total_reviews = count($reviews);
    if ($total_reviews > 0) {
        $avg_rating = round($sum_rating / $total_reviews, 1);
    }
}

// Check if user can review
$can_review = false;
$has_reviewed = false;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Check if purchased (assume paid or delivered means purchased)
    $purchase_sql = "SELECT 1 FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE o.user_id = ? AND oi.product_id = ? AND (o.payment_status = 'paid' OR o.order_status = 'delivered' OR o.order_status = 'completed') LIMIT 1";
    $stmt_pur = mysqli_prepare($conn, $purchase_sql);
    if ($stmt_pur) {
        mysqli_stmt_bind_param($stmt_pur, "ii", $user_id, $product['id']);
        mysqli_stmt_execute($stmt_pur);
        $res_pur = mysqli_stmt_get_result($stmt_pur);
        if ($res_pur && $res_pur->num_rows > 0) {
            $can_review = true;
        }
    }
    
    // Check if already reviewed
    $check_rev_sql = "SELECT 1 FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1";
    $stmt_check = mysqli_prepare($conn, $check_rev_sql);
    if ($stmt_check) {
        mysqli_stmt_bind_param($stmt_check, "ii", $product['id'], $user_id);
        mysqli_stmt_execute($stmt_check);
        $res_check = mysqli_stmt_get_result($stmt_check);
        if ($res_check && $res_check->num_rows > 0) {
            $has_reviewed = true;
            $can_review = false; // Only one review per user
        }
    }
}

// Fetch add-ons configured for this product
$addon_products_data = [];
$addon_section_title = !empty($product['addon_title']) ? $product['addon_title'] : 'Frequently Added Together';
if (!empty($product['addon_products'])) {
    $decoded_addons = json_decode($product['addon_products'], true);
    if (is_array($decoded_addons) && count($decoded_addons) > 0) {
        // Collect catalog IDs for a single DB query
        $addon_ids = [];
        $addon_meta = []; // indexed by product_id for catalog, or sequential for custom
        foreach ($decoded_addons as $ad) {
            $atype = $ad['type'] ?? (isset($ad['product_id']) ? 'catalog' : 'custom');
            if ($atype === 'catalog') {
                $aid = intval($ad['product_id'] ?? 0);
                if ($aid > 0) {
                    $addon_ids[] = $aid;
                    $addon_meta[$aid] = $ad; // store the config
                }
            } else {
                // Custom add-on — push directly without DB query
                $cname  = trim($ad['custom_name'] ?? '');
                if ($cname === '') continue;
                $cimg   = !empty($ad['custom_image']) ? ltrim($ad['custom_image'], './') : 'assets/images/products/default.png';
                $cprice = floatval($ad['custom_price'] ?? 0);
                $addon_products_data[] = [
                    'id'               => 'custom_' . count($addon_products_data),
                    'type'             => 'custom',
                    'name'             => $cname,
                    'slug'             => '',
                    'regular_price'    => $cprice,
                    'effective_price'  => $cprice,
                    'has_discount'     => false,
                    'image'            => $cimg,
                    'stock'            => 1, // custom items are always "in stock"
                    'needs_measurement'=> !empty($ad['needs_measurement']),
                ];
            }
        }
        // Fetch catalog products from DB
        if (!empty($addon_ids)) {
            $ids_str = implode(',', $addon_ids);
            $addons_sql = "SELECT p.id, p.name, p.slug, p.price, p.compare_price, p.image, IFNULL(stk.quantity, 0) as stock FROM products p LEFT JOIN stock stk ON p.id = stk.product_id WHERE p.id IN ($ids_str) AND p.status = 'active'";
            $addons_res = mysqli_query($conn, $addons_sql);
            if ($addons_res) {
                $db_rows = [];
                while ($a_row = mysqli_fetch_assoc($addons_res)) {
                    $db_rows[intval($a_row['id'])] = $a_row;
                }
                // Maintain original order
                foreach ($addon_ids as $aid) {
                    if (!isset($db_rows[$aid])) continue;
                    $a_row = $db_rows[$aid];
                    $meta  = $addon_meta[$aid];
                    $a_img = 'assets/images/products/default.png';
                    if (!empty($a_row['image'])) {
                        $aimg_dec = json_decode($a_row['image'], true);
                        if (is_array($aimg_dec) && count($aimg_dec) > 0) {
                            $a_img = $aimg_dec[0];
                        } else {
                            $a_img = trim(explode(',', $a_row['image'])[0]);
                        }
                        $a_img = ltrim($a_img, './');
                    }
                    $effective_price = (isset($meta['custom_price']) && is_numeric($meta['custom_price']) && floatval($meta['custom_price']) > 0)
                        ? floatval($meta['custom_price']) : floatval($a_row['price']);
                    $addon_products_data[] = [
                        'id'               => $aid,
                        'type'             => 'catalog',
                        'name'             => $a_row['name'],
                        'slug'             => $a_row['slug'] ?? '',
                        'regular_price'    => floatval($a_row['price']),
                        'effective_price'  => $effective_price,
                        'has_discount'     => $effective_price < floatval($a_row['price']),
                        'image'            => $a_img,
                        'stock'            => intval($a_row['stock'] ?? 0),
                        'needs_measurement'=> !empty($meta['needs_measurement']),
                    ];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <base href="<?php echo $base_url; ?>">
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title><?php echo htmlspecialchars($product['name']); ?> - Silky Saree</title>
  <meta name="description" content="<?php echo htmlspecialchars(substr($product['description'], 0, 160)); ?>">
  <meta name="keywords" content="<?php echo htmlspecialchars($product['category_name']); ?>, saree, silky saree">

  <link href="<?php echo $base_url; ?>assets/img/favicon.png" rel="icon">
  <link href="<?php echo $base_url; ?>assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <link href="<?php echo $base_url; ?>assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?php echo $base_url; ?>assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="<?php echo $base_url; ?>assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
  <link href="<?php echo $base_url; ?>assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="<?php echo $base_url; ?>assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">

  <link href="<?php echo $base_url; ?>assets/css/main.css" rel="stylesheet">

  <style>
    /* ===== PRODUCT DETAILS PAGE - MODERN REDESIGN ===== */

    .pd-section {
      padding: 60px 0 80px;
      background: #f8f9fc;
    }

    /* ---- GALLERY COLUMN ---- */
    .pd-gallery {
      position: sticky;
      top: 90px;
    }

    .pd-main-viewer {
      position: relative;
      border-radius: 20px;
      overflow: hidden;
      background: #fff;
      box-shadow: 0 8px 40px rgba(14,33,135,0.10);
      aspect-ratio: 9 / 16;
      max-height: 600px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .pd-main-viewer img#main-product-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.4s ease, opacity 0.25s ease;
      display: block;
    }

    .pd-main-viewer img#main-product-image:hover {
      transform: scale(1.04);
    }

    /* Video embed in main viewer */
    .pd-main-viewer .pd-video-frame {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      border: 0;
      display: none;
    }

    .pd-main-viewer.showing-video img#main-product-image {
      display: none;
    }

    .pd-main-viewer.showing-video .pd-video-frame {
      display: block;
    }

    .pd-main-viewer .pd-video-frame iframe {
      width: 100%;
      height: 100%;
      border: 0;
      pointer-events: none; /* Block direct YouTube interactions */
    }

    /* Video click overlay — covers the whole video, always pointer-enabled when showing */
    .vid-click-overlay {
      position: absolute;
      inset: 0;
      z-index: 10;
      cursor: pointer;
      display: none;
    }

    .pd-main-viewer.showing-video .vid-click-overlay {
      display: block;
    }

    /* Center Play/Pause feedback icon */
    .vid-click-overlay .center-icon {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s ease;
    }

    .pd-main-viewer.showing-video.is-paused .vid-click-overlay .center-icon {
      opacity: 1;
    }

    .vid-click-overlay .center-icon i {
      font-size: 4rem;
      color: #fff;
      background: rgba(14, 33, 135, 0.75);
      width: 90px;
      height: 90px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 20px rgba(0,0,0,0.35);
      transition: all 0.2s ease;
      backdrop-filter: blur(4px);
    }

    .vid-click-overlay:hover .center-icon i {
      background: rgba(151, 197, 29, 0.85);
      transform: scale(1.08);
    }

    /* Custom Video Controls */
    .custom-video-controls {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
      padding: 30px 20px 15px;
      display: flex;
      align-items: center;
      gap: 15px;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s ease;
      z-index: 20;
    }

    .pd-main-viewer.showing-video:hover .custom-video-controls {
      opacity: 1;
      pointer-events: all;
    }

    .custom-video-controls button {
      background: none;
      border: none;
      color: #fff;
      font-size: 1.6rem;
      cursor: pointer;
      padding: 0;
      transition: color 0.2s, transform 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .custom-video-controls button:hover {
      color: #97c51d;
      transform: scale(1.1);
    }

    .vid-progress-container {
      flex: 1;
      height: 5px;
      background: rgba(255,255,255,0.3);
      border-radius: 4px;
      cursor: pointer;
      position: relative;
      transition: height 0.15s;
    }

    .vid-progress-container:hover {
      height: 7px;
    }

    .vid-progress-fill {
      position: absolute;
      left: 0;
      top: 0;
      bottom: 0;
      width: 0%;
      background: #97c51d;
      border-radius: 4px;
      pointer-events: none;
    }

    .vid-progress-thumb {
      position: absolute;
      top: 50%;
      transform: translate(-50%, -50%) scale(0);
      width: 13px;
      height: 13px;
      background: #fff;
      border-radius: 50%;
      pointer-events: none;
      transition: transform 0.15s;
      box-shadow: 0 0 4px rgba(0,0,0,0.4);
    }

    .vid-progress-container:hover .vid-progress-thumb {
      transform: translate(-50%, -50%) scale(1);
    }

    /* Nav arrows */
    .pd-gallery-nav {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      z-index: 10;
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background: rgba(255,255,255,0.92);
      border: none;
      box-shadow: 0 3px 14px rgba(0,0,0,0.18);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      color: #0e2187;
      font-size: 1.1rem;
      transition: all 0.25s ease;
    }

    .pd-main-viewer.is-fullscreen .pd-gallery-nav {
      display: none !important;
    }

    .pd-gallery-nav:hover {
      background: #97c51d;
      color: #fff;
      transform: translateY(-50%) scale(1.1);
    }

    .pd-gallery-nav.prev { left: 12px; }
    .pd-gallery-nav.next { right: 12px; }

    /* Zoom badge */
    .pd-zoom-badge {
      position: absolute;
      top: 14px;
      right: 14px;
      background: rgba(14,33,135,0.85);
      color: #fff;
      border-radius: 8px;
      padding: 4px 10px;
      font-size: 0.72rem;
      font-weight: 600;
      letter-spacing: 0.04em;
      z-index: 5;
      pointer-events: none;
    }

    /* Thumbnails */
    .pd-thumbnails {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 14px;
    }

    .pd-thumb {
      width: 72px;
      height: 72px;
      border-radius: 12px;
      overflow: hidden;
      cursor: pointer;
      border: 2.5px solid transparent;
      transition: all 0.25s ease;
      background: #fff;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      flex-shrink: 0;
    }

    .pd-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .pd-thumb.active {
      border-color: #97c51d;
      box-shadow: 0 0 0 3px rgba(151,197,29,0.25);
    }

    .pd-thumb:hover:not(.active) {
      border-color: #0e2187;
      transform: translateY(-2px);
    }

    /* Video thumbnail */
    .pd-thumb-video {
      position: relative;
    }

    .pd-thumb-video .yt-thumb-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .pd-thumb-video .play-overlay {
      position: absolute;
      inset: 0;
      background: rgba(14,33,135,0.45);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 1.5rem;
      transition: background 0.2s;
    }

    .pd-thumb-video:hover .play-overlay,
    .pd-thumb-video.active .play-overlay {
      background: rgba(151,197,29,0.6);
    }

    /* ---- INFO COLUMN ---- */
    .pd-info {
      padding: 0 0 0 10px;
    }

    /* Category + rating bar */
    .pd-meta-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 14px;
    }

    .pd-category-badge {
      background: linear-gradient(135deg, #97c51d, #7aa817);
      color: #fff;
      padding: 4px 14px;
      border-radius: 50px;
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .pd-stars {
      display: flex;
      align-items: center;
      gap: 6px;
      color: #ccc;
      font-size: 0.9rem;
    }

    .pd-stars .star-filled { color: #f5a623; }

    .pd-product-title {
      font-family: 'Montserrat', sans-serif;
      font-size: 2rem;
      font-weight: 800;
      color: #0e2187;
      line-height: 1.25;
      margin-bottom: 16px;
    }

    .pd-price-row {
      display: flex;
      align-items: baseline;
      gap: 12px;
      margin-bottom: 16px;
    }

    .pd-price-main {
      font-family: 'Montserrat', sans-serif;
      font-size: 2rem;
      font-weight: 800;
      color: #97c51d;
    }

    .pd-description {
      color: #666;
      font-size: 0.95rem;
      line-height: 1.75;
      margin-bottom: 20px;
      border-top: 1px solid #eee;
      border-bottom: 1px solid #eee;
      padding: 16px 0;
    }

    /* Stock badge */
    .pd-stock-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      margin-bottom: 22px;
    }

    .pd-stock-badge.in-stock {
      background: rgba(25,135,84,0.1);
      color: #198754;
      border: 1.5px solid rgba(25,135,84,0.2);
    }

    .pd-stock-badge.out-of-stock {
      background: rgba(220,53,69,0.08);
      color: #dc3545;
      border: 1.5px solid rgba(220,53,69,0.2);
    }

    .pd-stock-count {
      font-size: 0.78rem;
      color: #dc3545;
      margin-left: 4px;
      font-weight: 500;
    }

    /* Variants */
    .pd-variant-label {
      font-size: 0.82rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #0e2187;
      margin-bottom: 10px;
      display: block;
    }

    .pd-color-grid {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 6px;
    }

    .pd-color-chip {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      cursor: pointer;
      border: 3px solid transparent;
      outline: 3px solid transparent;
      transition: all 0.25s ease;
      position: relative;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }

    .pd-color-chip:hover {
      transform: scale(1.15);
    }

    .pd-color-chip.active {
      outline-color: #97c51d;
      outline-offset: 2px;
    }

    .pd-color-chip .check-mark {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 0.9rem;
      opacity: 0;
      transition: opacity 0.2s;
    }

    .pd-color-chip.active .check-mark {
      opacity: 1;
    }

    .pd-selected-color {
      font-size: 0.82rem;
      color: #666;
      margin-top: 4px;
      margin-bottom: 18px;
    }

    .pd-selected-color strong {
      color: #0e2187;
    }

    .pd-size-grid {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 22px;
    }

    .pd-size-btn {
      padding: 8px 20px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      font-size: 0.88rem;
      color: #444;
      background: #fff;
      transition: all 0.2s ease;
    }

    .pd-size-btn:hover {
      border-color: #0e2187;
      color: #0e2187;
    }

    .pd-size-btn.active {
      border-color: #97c51d;
      background: #97c51d;
      color: #fff;
    }

    .pd-size-btn-custom {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      border-style: dashed;
      position: relative;
    }
    .pd-size-btn-custom:hover {
      border-color: #0e2187;
      background: #f4f6fc;
    }
    .pd-size-btn-custom.active {
      border-style: solid;
      background: #0e2187;
      border-color: #0e2187;
      color: #fff;
    }
    .pd-size-btn-custom.active i {
      color: #97c51d !important;
    }

    /* Custom Measurements Status Box */
    .pd-custom-meas-box {
      margin-top: -12px;
      margin-bottom: 22px;
      animation: fadeIn 0.25s ease;
    }
    .pd-custom-meas-card {
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 0.86rem;
      transition: all 0.2s ease;
    }
    .pd-custom-meas-card.unfilled {
      background: #fff9ed;
      border: 1px solid #fde68a;
      color: #92400e;
    }
    .pd-custom-meas-card.filled {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      color: #166534;
    }

    /* Custom Measurements Modal */
    #customMeasurementsModal .modal-content {
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 15px 50px rgba(0,0,0,0.15);
      border: none;
    }
    #customMeasurementsModal .modal-header {
      background: linear-gradient(135deg, #0e2187, #1b3cc7);
      color: #fff;
      padding: 20px 24px;
    }
    #customMeasurementsModal .meas-unit-btn-group .btn {
      font-size: 0.82rem;
      font-weight: 600;
      padding: 5px 14px;
      border-radius: 6px;
    }
    #customMeasurementsModal .form-control:focus, 
    #customMeasurementsModal .form-select:focus {
      border-color: #0e2187;
      box-shadow: 0 0 0 0.2rem rgba(14, 33, 135, 0.15);
    }
    #customMeasurementsModal .meas-label {
      font-size: 0.8rem;
      font-weight: 600;
      color: #374151;
      margin-bottom: 4px;
      display: flex;
      justify-content: space-between;
    }
    #customMeasurementsModal .meas-tip {
      font-size: 0.72rem;
      color: #9ca3af;
      font-weight: normal;
    }

    /* Quantity */
    .pd-qty-row {
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 22px;
    }

    .pd-qty-label {
      font-size: 0.82rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #0e2187;
      white-space: nowrap;
    }

    .pd-qty-control {
      display: flex;
      align-items: center;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      overflow: hidden;
    }

    .pd-qty-btn {
      width: 40px;
      height: 40px;
      background: #f8f9fc;
      border: none;
      cursor: pointer;
      font-size: 1.1rem;
      color: #0e2187;
      transition: background 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .pd-qty-btn:hover { background: #e8f5d0; }

    .pd-qty-input {
      width: 52px;
      text-align: center;
      border: none;
      outline: none;
      font-size: 1rem;
      font-weight: 700;
      color: #0e2187;
      background: #fff;
      height: 40px;
    }

    .pd-qty-input::-webkit-outer-spin-button,
    .pd-qty-input::-webkit-inner-spin-button { -webkit-appearance: none; }

    /* Action Buttons */
    .pd-action-row {
      display: flex;
      gap: 10px;
      margin-bottom: 26px;
    }

    .pd-btn-cart {
      flex: 1;
      padding: 14px 20px;
      background: linear-gradient(135deg, #97c51d, #7aa817);
      color: #fff;
      border: none;
      border-radius: 12px;
      font-weight: 700;
      font-size: 0.95rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(151,197,29,0.35);
    }

    .pd-btn-cart:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(151,197,29,0.45);
    }

    .pd-btn-buynow {
      flex: 1;
      padding: 14px 20px;
      background: #fff;
      color: #0e2187;
      border: 2px solid #0e2187;
      border-radius: 12px;
      font-weight: 700;
      font-size: 0.95rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.3s ease;
    }

    .pd-btn-buynow:hover {
      background: #0e2187;
      color: #fff;
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(14,33,135,0.25);
    }

    .pd-btn-wishlist {
      width: 52px;
      height: 52px;
      border-radius: 12px;
      background: #fff;
      border: 2px solid #e0e0e0;
      color: #888;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      transition: all 0.3s ease;
      flex-shrink: 0;
    }

    .pd-btn-wishlist:hover,
    .pd-btn-wishlist.wishlisted {
      border-color: #dc3545;
      color: #dc3545;
      background: rgba(220,53,69,0.05);
    }

    /* Add-on Products Styling */
    .pd-addons-wrapper {
      background: #fbfbfe;
      border: 1.5px solid #edf0fb;
      border-radius: 14px;
      padding: 16px;
      margin-bottom: 22px;
    }
    .pd-addons-title {
      font-size: 0.96rem;
      font-weight: 700;
      color: #0e2187;
      font-family: 'Montserrat', sans-serif;
    }
    .pd-addon-item {
      background: #fff;
      transition: all 0.2s ease;
      cursor: pointer;
    }
    .pd-addon-item:hover {
      border-color: #97c51d !important;
      box-shadow: 0 4px 12px rgba(14,33,135,0.06);
    }
    .pd-addon-checkbox {
      width: 18px;
      height: 18px;
      cursor: pointer;
      border: 2px solid #ced4da;
    }
    .pd-addon-checkbox:checked {
      background-color: #97c51d;
      border-color: #97c51d;
    }
    .pd-addon-img {
      width: 48px;
      height: 48px;
      object-fit: cover;
    }
    .pd-addon-name {
      font-size: 0.86rem;
      transition: color 0.2s;
    }
    .pd-addon-name:hover {
      color: #0e2187 !important;
    }
    .pd-addon-price {
      font-size: 0.9rem;
    }
    .pd-addons-total-box {
      background: linear-gradient(135deg, rgba(14,33,135,0.05), rgba(151,197,29,0.07));
      border: 1px dashed rgba(14,33,135,0.2);
    }

    /* Benefits */
    .pd-benefits {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }

    .pd-benefit-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 14px;
      background: #fff;
      border-radius: 10px;
      border: 1.5px solid #f0f0f0;
      transition: border-color 0.2s;
    }

    .pd-benefit-item:hover {
      border-color: #97c51d;
    }

    .pd-benefit-item i {
      font-size: 1.1rem;
      color: #97c51d;
      flex-shrink: 0;
    }

    .pd-benefit-item span {
      font-size: 0.8rem;
      color: #555;
      line-height: 1.3;
    }

    /* ---- TABS ---- */
    .pd-tabs-section {
      margin-top: 60px;
    }

    .pd-tab-nav {
      display: flex;
      gap: 4px;
      background: #fff;
      border-radius: 14px;
      padding: 6px;
      box-shadow: 0 2px 14px rgba(14,33,135,0.08);
      margin-bottom: 28px;
    }

    .pd-tab-btn {
      flex: 1;
      padding: 12px 20px;
      border: none;
      border-radius: 10px;
      background: transparent;
      color: #666;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      transition: all 0.25s ease;
      font-family: 'Poppins', sans-serif;
    }

    .pd-tab-btn.active {
      background: linear-gradient(135deg, #0e2187, #1a35c9);
      color: #fff;
      box-shadow: 0 4px 14px rgba(14,33,135,0.3);
    }

    .pd-tab-btn:not(.active):hover {
      background: rgba(151,197,29,0.1);
      color: #0e2187;
    }

    .pd-tab-content-panel {
      display: none;
      animation: fadeInUp 0.3s ease;
    }

    .pd-tab-content-panel.active {
      display: block;
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Overview tab */
    .pd-overview-wrapper {
      background: #fff;
      border-radius: 18px;
      padding: 36px;
      box-shadow: 0 4px 24px rgba(14,33,135,0.07);
    }

    .pd-section-title {
      font-family: 'Montserrat', sans-serif;
      font-size: 1.4rem;
      font-weight: 800;
      color: #0e2187;
      margin-bottom: 16px;
    }

    .pd-overview-desc {
      font-size: 1rem;
      line-height: 1.85;
      color: #555;
      margin-bottom: 40px;
    }

    .pd-highlights-section {
      border-top: 1px solid #eee;
      padding-top: 32px;
    }

    .pd-highlights-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin-top: 24px;
    }

    .pd-highlight-card {
      background: linear-gradient(135deg, #f8f9fc, #fff);
      border: 1.5px solid #edf0fb;
      border-radius: 16px;
      padding: 24px 18px;
      text-align: center;
      transition: all 0.3s ease;
    }

    .pd-highlight-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 12px 30px rgba(14,33,135,0.12);
      border-color: #97c51d;
    }

    .pd-highlight-icon {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: linear-gradient(135deg, rgba(151,197,29,0.15), rgba(151,197,29,0.05));
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 14px;
      font-size: 1.4rem;
      color: #97c51d;
    }

    .pd-highlight-card h5 {
      font-family: 'Montserrat', sans-serif;
      font-size: 0.95rem;
      font-weight: 700;
      color: #0e2187;
      margin-bottom: 8px;
    }

    .pd-highlight-card p {
      font-size: 0.82rem;
      color: #888;
      line-height: 1.55;
      margin: 0;
    }

    /* Tech Details tab */
    .pd-tech-wrapper {
      background: #fff;
      border-radius: 18px;
      padding: 36px;
      box-shadow: 0 4px 24px rgba(14,33,135,0.07);
    }

    .pd-spec-table {
      border-radius: 12px;
      overflow: hidden;
      border: 1.5px solid #edf0fb;
    }

    .pd-spec-row {
      display: flex;
      align-items: center;
      padding: 14px 20px;
      border-bottom: 1px solid #f0f3ff;
      transition: background 0.2s;
    }

    .pd-spec-row:last-child { border-bottom: none; }
    .pd-spec-row:hover { background: #f8f9fc; }

    .pd-spec-key {
      width: 180px;
      flex-shrink: 0;
      font-size: 0.85rem;
      font-weight: 600;
      color: #888;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    .pd-spec-val {
      font-size: 0.95rem;
      font-weight: 600;
      color: #0e2187;
    }

    .pd-care-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 14px;
      margin-top: 24px;
    }

    .pd-care-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 16px;
      background: #f8f9fc;
      border-radius: 12px;
      border: 1.5px solid #edf0fb;
    }

    .pd-care-item i {
      font-size: 1.4rem;
      flex-shrink: 0;
      margin-top: 2px;
    }

    .pd-care-item .care-text {
      font-size: 0.85rem;
      color: #555;
      line-height: 1.5;
    }

    .pd-care-item .care-title {
      font-weight: 700;
      color: #0e2187;
      font-size: 0.88rem;
      display: block;
      margin-bottom: 3px;
    }

    /* Reviews tab */
    .pd-reviews-wrapper {
      background: #fff;
      border-radius: 18px;
      padding: 60px 36px;
      box-shadow: 0 4px 24px rgba(14,33,135,0.07);
      text-align: center;
    }

    .pd-empty-reviews-icon {
      width: 90px;
      height: 90px;
      border-radius: 50%;
      background: linear-gradient(135deg, rgba(151,197,29,0.12), rgba(14,33,135,0.06));
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 2.4rem;
      color: #c8d8e8;
      margin-bottom: 24px;
    }

    .pd-write-review-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 13px 28px;
      background: linear-gradient(135deg, #97c51d, #7aa817);
      color: #fff;
      border: none;
      border-radius: 50px;
      font-weight: 700;
      font-size: 0.9rem;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(151,197,29,0.35);
    }

    .pd-write-review-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 22px rgba(151,197,29,0.45);
    }

    /* ---- RESPONSIVE ---- */
    @media (max-width: 991px) {
      .pd-gallery { position: relative; top: 0; }
      .pd-info { padding: 0; margin-top: 28px; }
      .pd-highlights-grid { grid-template-columns: repeat(2, 1fr); }
      .pd-product-title { font-size: 1.5rem; }
      .pd-overview-wrapper, .pd-tech-wrapper, .pd-reviews-wrapper { padding: 24px; }
    }

    @media (max-width: 576px) {
      .pd-highlights-grid { grid-template-columns: 1fr; }
      .pd-benefits { grid-template-columns: 1fr; }
      .pd-care-grid { grid-template-columns: 1fr; }
      .pd-action-row { flex-wrap: wrap; }
      .pd-btn-cart, .pd-btn-buynow { flex: 1 1 calc(50% - 5px); min-width: 0; }
      .pd-tab-btn { padding: 10px 8px; font-size: 0.78rem; }
    }
  </style>
</head>

<body class="product-details-page">

  <header id="header" class="header sticky-top">
    <?php include './topbar.php'; ?>
    <?php include './main-header.php'; ?>
  </header>

  <main class="main">

    <!-- Page Title -->
    <div class="page-title light-background">
      <div class="container d-lg-flex justify-content-between align-items-center">
        <h1 class="mb-2 mb-lg-0"><?php echo htmlspecialchars($product['name']); ?></h1>
        <nav class="breadcrumbs">
          <ol>
            <li><a href="index.php">Home</a></li>
            <li><a href="products.php">Products</a></li>
            <li class="current"><?php echo htmlspecialchars($product['name']); ?></li>
          </ol>
        </nav>
      </div>
    </div>

    <!-- Product Details Section -->
    <section class="pd-section">
      <div class="container">
        <div class="row g-5" data-aos="fade-up">

          <!-- ===== GALLERY ===== -->
          <div class="col-lg-6">
            <div class="pd-gallery">

              <!-- Main Viewer -->
              <div class="pd-main-viewer" id="pd-main-viewer">
                <img
                  id="main-product-image"
                  src="<?php echo htmlspecialchars($main_image); ?>"
                  alt="<?php echo htmlspecialchars($product['name']); ?>"
                  onerror="this.src='assets/img/product/product-1.webp'"
                >
                <?php if ($has_video): ?>
                <div id="pd-yt-player-container" class="pd-video-frame" data-video-id="<?php echo $video_id; ?>">
                  <div id="pd-yt-player"></div>
                  <!-- Click overlay: play/pause on tap, shows center icon when paused -->
                  <div class="vid-click-overlay" id="vid-click-overlay">
                    <div class="center-icon">
                      <i class="bi bi-play-fill" id="center-icon-i"></i>
                    </div>
                  </div>
                </div>

                <!-- Custom Video Controls -->
                <div class="custom-video-controls" id="custom-video-controls">
                  <button id="vid-play-pause" title="Play/Pause"><i class="bi bi-play-fill"></i></button>
                  <div class="vid-progress-container" id="vid-progress">
                    <div class="vid-progress-fill" id="vid-progress-fill"></div>
                    <div class="vid-progress-thumb" id="vid-progress-thumb"></div>
                  </div>
                  <button id="vid-mute" title="Mute"><i class="bi bi-volume-up-fill"></i></button>
                  <button id="vid-fullscreen" title="Fullscreen"><i class="bi bi-fullscreen"></i></button>
                </div>
                <?php endif; ?>

                <!-- Navigation arrows -->
                <button class="pd-gallery-nav prev" id="gallery-prev" type="button">
                  <i class="bi bi-chevron-left"></i>
                </button>
                <button class="pd-gallery-nav next" id="gallery-next" type="button">
                  <i class="bi bi-chevron-right"></i>
                </button>

                <span class="pd-zoom-badge"><i class="bi bi-zoom-in me-1"></i>Hover to Zoom</span>
              </div>

              <!-- Thumbnails -->
              <div class="pd-thumbnails" id="pd-thumbnails">
                <?php if (!empty($product_images)): ?>
                  <?php foreach ($product_images as $index => $image): ?>
                  <div class="pd-thumb <?php echo $index === 0 ? 'active' : ''; ?>"
                       data-type="image"
                       data-index="<?php echo $index; ?>"
                       data-src="<?php echo htmlspecialchars($image); ?>">
                    <img src="<?php echo htmlspecialchars($image); ?>"
                         alt="View <?php echo $index + 1; ?>"
                         onerror="this.src='assets/img/product/product-1.webp'">
                  </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="pd-thumb active" data-type="image" data-index="0" data-src="assets/img/product/product-1.webp">
                    <img src="assets/img/product/product-1.webp" alt="No image">
                  </div>
                <?php endif; ?>

                <?php if ($has_video): ?>
                <div class="pd-thumb pd-thumb-video"
                     data-type="video"
                     data-index="<?php echo empty($product_images) ? 1 : count($product_images); ?>"
                     data-video-id="<?php echo $video_id; ?>">
                  <img class="yt-thumb-img"
                       src="https://img.youtube.com/vi/<?php echo $video_id; ?>/mqdefault.jpg"
                       alt="Product Video"
                       onerror="this.src='assets/img/product/product-1.webp'">
                  <div class="play-overlay">
                    <i class="bi bi-play-circle-fill"></i>
                  </div>
                </div>
                <?php endif; ?>
              </div>

            </div>
          </div>

          <!-- ===== PRODUCT INFO ===== -->
          <div class="col-lg-6">
            <div class="pd-info" data-aos="fade-left" data-aos-delay="100">

              <!-- Category + Rating -->
              <div class="pd-meta-bar">
                <span class="pd-category-badge"><?php echo htmlspecialchars($product['category_name']); ?></span>
                <div class="pd-stars">
                  <?php for($i = 1; $i <= 5; $i++): ?>
                    <i class="bi bi-star<?php echo ($i <= round($avg_rating)) ? '-fill star-filled' : ''; ?>"></i>
                  <?php endfor; ?>
                  <span style="font-size:0.82rem; color:#aaa;">(<?php echo $total_reviews; ?> reviews)</span>
                </div>
              </div>

              <!-- Title -->
              <h1 class="pd-product-title mb-1"><?php echo htmlspecialchars($product['name']); ?></h1>
              <?php if (!empty($product['product_code'])): ?>
                <div class="mb-3 text-muted" style="font-size: 0.9rem; font-family: monospace;">
                  Code: <?php echo htmlspecialchars($product['product_code']); ?>
                </div>
              <?php endif; ?>

              <!-- Price -->
              <div class="pd-price-row d-flex align-items-center mb-3">
                <span class="pd-price-main" id="pd-price-main">₹<?php echo number_format($product['price'], 2); ?></span>
                <?php if (isset($product['compare_price']) && floatval($product['compare_price']) > floatval($product['price'])): 
                    $compare_val = floatval($product['compare_price']);
                    $price_val = floatval($product['price']);
                    $discount = round((($compare_val - $price_val) / $compare_val) * 100);
                ?>
                <span id="pd-price-compare" class="text-muted text-decoration-line-through ms-3 fs-6">₹<?php echo number_format($compare_val, 2); ?></span> 
                <span id="pd-discount-badge" class="badge bg-danger ms-2 px-2 py-1" style="font-size: 0.8rem; border-radius: 4px;">-<?php echo $discount; ?>%</span>
                <?php else: ?>
                <span id="pd-price-compare" class="text-muted text-decoration-line-through ms-3 fs-6" style="display: none;"></span>
                <span id="pd-discount-badge" class="badge bg-danger ms-2 px-2 py-1" style="font-size: 0.8rem; border-radius: 4px; display: none;"></span>
                <?php endif; ?>
              </div>

              <!-- Description -->
              <!-- <div class="pd-description"> -->
                <!-- <?php echo nl2br(htmlspecialchars($product['description'])); ?> -->
              <!-- </div> -->

              <!-- Stock -->
              <div id="stock-container" class="mb-3">
                  <?php if ($product['stock'] > 0): ?>
                    <span class="pd-stock-badge in-stock">
                      <i class="bi bi-check-circle-fill"></i> In Stock
                    </span>
                    <span class="pd-stock-count" id="stock-count-text">Only <?php echo $product['stock']; ?> left!</span>
                  <?php else: ?>
                    <span class="pd-stock-badge out-of-stock">
                      <i class="bi bi-x-circle-fill"></i> Out of Stock
                    </span>
                    <span class="pd-stock-count" id="stock-count-text" style="display:none;"></span>
                  <?php endif; ?>
              </div>

              <!-- Colors -->
              <?php if (!empty($colors)): ?>
              <div class="mb-3">
                <span class="pd-variant-label">Color</span>
                <div class="pd-color-grid" id="pd-color-grid">
                  <?php foreach ($colors as $i => $color): ?>
                  <div class="pd-color-chip <?php echo $i === 0 ? 'active' : ''; ?>"
                       data-color="<?php echo htmlspecialchars($color['name']); ?>"
                       style="background: <?php echo htmlspecialchars($color['code']); ?>;"
                       title="<?php echo htmlspecialchars($color['name']); ?>">
                    <span class="check-mark"><i class="bi bi-check2"></i></span>
                  </div>
                  <?php endforeach; ?>
                </div>
                <div class="pd-selected-color">Color: <strong id="selected-color-name"><?php echo htmlspecialchars($colors[0]['name']); ?></strong></div>
              </div>
              <?php endif; ?>

              <!-- Sizes -->
              <?php if (!empty($sizes)): ?>
              <div class="mb-3">
                <span class="pd-variant-label">Size</span>
                <div class="pd-size-grid" id="pd-size-grid">
                  <?php foreach ($sizes as $i => $size): 
                    $isCustomSize = (strtolower(trim($size)) === 'custom');
                  ?>
                  <button class="pd-size-btn <?php echo $i === 0 ? 'active' : ''; ?> <?php echo $isCustomSize ? 'pd-size-btn-custom' : ''; ?>"
                          data-size="<?php echo htmlspecialchars($size); ?>"
                          <?php echo $isCustomSize ? 'data-is-custom="1"' : ''; ?>
                          type="button">
                    <?php if ($isCustomSize): ?>
                      <i class="bi bi-scissors me-1 text-primary"></i>Custom
                    <?php else: ?>
                      <?php echo htmlspecialchars($size); ?>
                    <?php endif; ?>
                  </button>
                  <?php endforeach; ?>
                </div>

                <!-- Custom Measurements Status Banner -->
                <div id="custom-meas-status-box" class="pd-custom-meas-box" style="display: none;">
                  <div id="custom-meas-card-unfilled" class="pd-custom-meas-card unfilled d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                      <i class="bi bi-rulers fs-5 text-warning"></i>
                      <div>
                        <strong>Custom measurements required:</strong>
                        <div class="text-muted small">Please provide your garment measurements.</div>
                      </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-dark px-3 py-1 fw-semibold fs-12 text-nowrap" id="openCustomMeasModalBtn">
                      <i class="bi bi-pencil-square me-1"></i> Fill Measurements
                    </button>
                  </div>
                  <div id="custom-meas-card-filled" class="pd-custom-meas-card filled d-flex align-items-center justify-content-between" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                      <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                      <div>
                        <strong>Custom Measurements Attached</strong>
                        <div class="text-muted small text-truncate" style="max-width: 340px;" id="custom-meas-summary-text"></div>
                      </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-success px-3 py-1 fw-semibold fs-12 text-nowrap" id="editCustomMeasModalBtn">
                      <i class="bi bi-pencil-square me-1"></i> Edit
                    </button>
                  </div>
                </div>
              </div>
              <?php endif; ?>

              <!-- Quantity -->
              <div class="pd-qty-row">
                <span class="pd-qty-label">Qty</span>
                <div class="pd-qty-control">
                  <button class="pd-qty-btn" id="qty-decrease" type="button">
                    <i class="bi bi-dash"></i>
                  </button>
                  <input type="number" class="pd-qty-input" id="product-quantity"
                         value="1" min="1" max="<?php echo min(10, max(1, (int)$product['stock'])); ?>">
                  <button class="pd-qty-btn" id="qty-increase" type="button">
                    <i class="bi bi-plus"></i>
                  </button>
                </div>
                <small class="text-muted" style="font-size: 0.8rem; font-weight: 500;">(Max 10 at a time)</small>
              </div>

              <?php if (!empty($addon_products_data)): ?>
              <!-- Add-on Products Section -->
              <div class="pd-addons-wrapper">
                <div class="pd-addons-header d-flex align-items-center justify-content-between mb-3">
                  <h6 class="pd-addons-title mb-0">
                    <i class="bi bi-gift-fill text-warning me-1"></i><?php echo htmlspecialchars($addon_section_title); ?>
                  </h6>
                  <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1" style="font-size: 11px;">
                    Frequently Added
                  </span>
                </div>

                <div class="pd-addons-list">
                  <?php foreach ($addon_products_data as $ad_idx => $ad):
                      $is_out_of_stock   = $ad['stock'] <= 0;
                      $needs_measurement = !empty($ad['needs_measurement']);
                      $ad_uid = 'addon-' . $ad_idx; // unique per page render
                  ?>
                  <div class="pd-addon-item d-flex flex-column gap-1 p-2 rounded border mb-2 <?php echo $is_out_of_stock ? 'opacity-50' : ''; ?>" 
                       data-addon-id="<?php echo htmlspecialchars((string)$ad['id']); ?>" 
                       data-addon-price="<?php echo $ad['effective_price']; ?>"
                       data-needs-measurement="<?php echo $needs_measurement ? '1' : '0'; ?>"
                       onclick="toggleAddonItem(event, '<?php echo htmlspecialchars((string)$ad['id']); ?>')">
                    <div class="d-flex align-items-center gap-2">
                      <div class="form-check m-0 p-0 d-flex align-items-center ps-1">
                        <input class="form-check-input pd-addon-checkbox m-0" type="checkbox" 
                               id="addon-check-<?php echo $ad_idx; ?>" 
                               value="<?php echo htmlspecialchars((string)$ad['id']); ?>" 
                               <?php echo $is_out_of_stock ? 'disabled' : ''; ?> 
                               onclick="event.stopPropagation(); saveAddonSelections(); updateAddonsTotal();">
                      </div>
                      <a href="<?php echo !empty($ad['slug']) ? 'product/' . htmlspecialchars($ad['slug']) : '#'; ?>" 
                         target="<?php echo !empty($ad['slug']) ? '_blank' : '_self'; ?>" 
                         class="flex-shrink-0" onclick="event.stopPropagation();">
                        <img src="<?php echo htmlspecialchars($ad['image']); ?>" 
                             alt="<?php echo htmlspecialchars($ad['name']); ?>" 
                             class="pd-addon-img rounded border" 
                             onerror="this.onerror=null; this.src='assets/images/products/default.png'">
                      </a>
                      <div class="pd-addon-info flex-grow-1 overflow-hidden">
                        <a href="<?php echo !empty($ad['slug']) ? 'product/' . htmlspecialchars($ad['slug']) : '#'; ?>" 
                           target="<?php echo !empty($ad['slug']) ? '_blank' : '_self'; ?>" 
                           class="pd-addon-name text-dark fw-semibold text-truncate d-block mb-1 text-decoration-none" 
                           onclick="event.stopPropagation();">
                          <?php echo htmlspecialchars($ad['name']); ?>
                        </a>
                        <div class="pd-addon-price-box d-flex align-items-center gap-2">
                          <span class="pd-addon-price fw-bold text-success">₹<?php echo number_format($ad['effective_price'], 2); ?></span>
                          <?php if ($ad['has_discount']): ?>
                          <span class="text-muted text-decoration-line-through small" style="font-size: 11px;">₹<?php echo number_format($ad['regular_price'], 2); ?></span>
                          <?php endif; ?>
                          <?php if ($is_out_of_stock): ?>
                          <span class="badge bg-danger-subtle text-danger" style="font-size: 10px;">Out of Stock</span>
                          <?php endif; ?>
                          <?php if ($needs_measurement): ?>
                          <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size: 10px;">
                            <i class="fas fa-ruler-combined me-1"></i>Measurements needed
                          </span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <?php if ($needs_measurement): ?>
                    <!-- Inline measurement form (shown when addon is checked) -->
                    <div class="pd-addon-measurements mt-2 pt-2 border-top" id="meas-<?php echo $ad_idx; ?>" style="display:none;">
                      <p class="mb-2 text-muted" style="font-size:12px;"><i class="fas fa-ruler-combined text-info me-1"></i>Please provide your measurements for <strong><?php echo htmlspecialchars($ad['name']); ?></strong> (in inches):</p>
                      <div class="row g-2">
                        <?php foreach (['Chest','Waist','Hip','Length','Shoulder'] as $mf): ?>
                        <div class="col-6 col-md-4">
                          <label class="form-label mb-0" style="font-size:11px;"><?php echo $mf; ?></label>
                          <div class="input-group input-group-sm">
                            <input type="number" step="0.5" min="1" 
                                   class="form-control form-control-sm pd-addon-meas-input" 
                                   data-meas-field="<?php echo strtolower($mf); ?>"
                                   data-addon-id="<?php echo htmlspecialchars((string)$ad['id']); ?>"
                                   placeholder="e.g. 36"
                                   onclick="event.stopPropagation();"
                                   oninput="saveAddonSelections();">
                            <span class="input-group-text" style="font-size:11px;">in</span>
                          </div>
                        </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                    <?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>

                <!-- Combined Price Box -->
                <div class="pd-addons-total-box p-2 px-3 rounded mt-2 d-flex justify-content-between align-items-center">
                  <div>
                    <span class="text-muted d-block" style="font-size: 11px;">Combined Total</span>
                    <span class="pd-addons-total-val fw-bold fs-14 text-primary" id="pd-combined-total">₹0.00</span>
                  </div>
                  <span class="badge bg-white text-dark border px-2 py-1 fw-medium" id="pd-addons-count-badge" style="font-size: 11px;">
                    1 item selected
                  </span>
                </div>
              </div>
              <?php endif; ?>

              <!-- Action Buttons -->
              <div class="pd-action-row">
                <button class="pd-btn-cart" id="btn-add-to-cart" data-product-id="<?php echo $product['id']; ?>" style="<?php echo $product['stock'] <= 0 ? 'display:none;' : ''; ?>">
                  <i class="bi bi-bag-plus-fill"></i> Add to Cart
                </button>
                <button class="pd-btn-buynow" id="btn-buy-now" data-product-id="<?php echo $product['id']; ?>" style="<?php echo $product['stock'] <= 0 ? 'display:none;' : ''; ?>">
                  <i class="bi bi-lightning-fill"></i> Buy Now
                </button>
                <button class="pd-btn-notify" id="btn-notify-me" data-product-id="<?php echo $product['id']; ?>" style="flex:2; padding: 14px 20px; background: #dc3545; color: white; border: none; border-radius: 12px; font-weight: 700; font-size: 0.95rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s ease; <?php echo $product['stock'] > 0 ? 'display:none;' : ''; ?>">
                  <i class="bi bi-bell-fill"></i> Notify Me!
                </button>
                <button class="pd-btn-wishlist" id="btn-add-to-wishlist" data-product-id="<?php echo $product['id']; ?>" title="Add to Wishlist">
                  <i class="bi bi-heart"></i>
                </button>
              </div>

              <!-- Benefits -->
              <div class="pd-benefits">
                <div class="pd-benefit-item">
                  <i class="bi bi-truck"></i>
                  <span>Free delivery over ₹999</span>
                </div>
                <div class="pd-benefit-item">
                  <i class="bi bi-arrow-repeat"></i>
                  <span>Easy 7-day returns</span>
                </div>
                <div class="pd-benefit-item">
                  <i class="bi bi-shield-check"></i>
                  <span>100% authentic product</span>
                </div>
                <div class="pd-benefit-item">
                  <i class="bi bi-headset"></i>
                  <span>24/7 customer support</span>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- ===== TABS ===== -->
        <div class="pd-tabs-section" data-aos="fade-up" data-aos-delay="200">

          <!-- Tab Nav -->
          <div class="pd-tab-nav">
            <button class="pd-tab-btn active" data-tab="overview">
              <i class="bi bi-grid-1x2 me-1"></i> Overview
            </button>
            <button class="pd-tab-btn" data-tab="technical">
              <i class="bi bi-clipboard-data me-1"></i> Specifications
            </button>
            <button class="pd-tab-btn" data-tab="reviews">
              <i class="bi bi-star me-1"></i> Reviews <span class="ms-1 opacity-75">(<?php echo $total_reviews; ?>)</span>
            </button>
          </div>

          <!-- Overview -->
          <div class="pd-tab-content-panel active" id="tab-overview">
            <div class="pd-overview-wrapper">
              <h3 class="pd-section-title">About This Product</h3>
              <div class="pd-overview-desc">
                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
              </div>

              <?php
              // Fetch website settings if not already fetched
              if (!isset($website_settings)) {
                  $website_settings = [];
                  $s_res = @mysqli_query($conn, "SELECT setting_key, setting_value FROM website_settings");
                  if ($s_res) {
                      while ($row = mysqli_fetch_assoc($s_res)) {
                          $website_settings[$row['setting_key']] = $row['setting_value'];
                      }
                  }
              }

              // Determine highlights to show
              $show_highlights = false;
              $highlights_title = '';
              $highlights_cards = [];

              $default_fallback_cards = [
                  ['icon' => 'bi bi-gem', 'title' => 'Premium Quality', 'desc' => 'Crafted from the finest fabrics for a luxurious feel and elegant drape.'],
                  ['icon' => 'bi bi-palette2', 'title' => 'Authentic Design', 'desc' => 'Traditional motifs blended beautifully with contemporary aesthetics.'],
                  ['icon' => 'bi bi-shield-check', 'title' => 'Long-lasting', 'desc' => 'Woven with precision to ensure your saree lasts for generations.'],
                  ['icon' => 'bi bi-stars', 'title' => 'Perfect Finish', 'desc' => 'Impeccable finishing and attention to detail in every single thread.']
              ];

              // Check if product has custom highlights enabled
              $has_custom = !empty($product['custom_highlights_enabled']);
              if ($has_custom) {
                  $show_highlights = true;
                  $highlights_title = !empty($product['custom_highlights_title']) 
                      ? $product['custom_highlights_title'] 
                      : ($website_settings['product_highlights_title'] ?? 'Why Choose Our Sarees?');
                  
                  if (!empty($product['custom_highlights_cards'])) {
                      $p_cards = json_decode($product['custom_highlights_cards'], true);
                      if (json_last_error() === JSON_ERROR_NONE && is_array($p_cards) && count($p_cards) > 0) {
                          $highlights_cards = $p_cards;
                      }
                  }
              } else {
                  // Check global setting
                  $global_enabled = ($website_settings['product_highlights_enabled'] ?? '1') !== '0';
                  if ($global_enabled) {
                      $show_highlights = true;
                      $highlights_title = !empty($website_settings['product_highlights_title']) 
                          ? $website_settings['product_highlights_title'] 
                          : 'Why Choose Our Sarees?';
                      
                      if (!empty($website_settings['product_highlights_cards'])) {
                          $g_cards = json_decode($website_settings['product_highlights_cards'], true);
                          if (json_last_error() === JSON_ERROR_NONE && is_array($g_cards) && count($g_cards) > 0) {
                              $highlights_cards = $g_cards;
                          }
                      }
                  }
              }

              // Fallback to default 4 cards if list is empty but section is enabled
              if ($show_highlights && empty($highlights_cards)) {
                  $highlights_cards = $default_fallback_cards;
              }
              ?>

              <?php if ($show_highlights && !empty($highlights_cards)): ?>
              <div class="pd-highlights-section">
                <h4 class="pd-section-title"><?php echo htmlspecialchars($highlights_title); ?></h4>
                <div class="pd-highlights-grid">
                  <?php foreach ($highlights_cards as $c): 
                      $raw_icon = trim($c['icon'] ?? 'bi bi-gem');
                      if (strpos($raw_icon, 'bi-') === 0 && strpos($raw_icon, 'bi ') !== 0) {
                          $raw_icon = 'bi ' . $raw_icon;
                      }
                      $c_title = $c['title'] ?? '';
                      $c_desc = $c['desc'] ?? '';
                  ?>
                  <div class="pd-highlight-card">
                    <div class="pd-highlight-icon"><i class="<?php echo htmlspecialchars($raw_icon); ?>"></i></div>
                    <h5><?php echo htmlspecialchars($c_title); ?></h5>
                    <p><?php echo htmlspecialchars($c_desc); ?></p>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Technical / Specs -->
          <div class="pd-tab-content-panel" id="tab-technical">
            <div class="pd-tech-wrapper">
              <?php
              // Fetch care instructions settings & cards
              $show_care = false;
              $care_title = '';
              $care_cards = [];

              $default_care_fallback = [
                  ['icon' => 'bi bi-droplet-half', 'color' => '#0dcaf0', 'title' => 'Washing', 'desc' => 'Dry clean only for best results'],
                  ['icon' => 'bi bi-brightness-high', 'color' => '#ffc107', 'title' => 'Drying', 'desc' => 'Avoid drying in direct sunlight'],
                  ['icon' => 'bi bi-archive', 'color' => '#0e2187', 'title' => 'Storage', 'desc' => 'Store in a cool, dry place'],
                  ['icon' => 'bi bi-thermometer-half', 'color' => '#dc3545', 'title' => 'Ironing', 'desc' => 'Iron on reverse side on low heat']
              ];

              $has_custom_care = !empty($product['custom_care_enabled']);
              if ($has_custom_care) {
                  $show_care = true;
                  $care_title = !empty($product['custom_care_title']) 
                      ? $product['custom_care_title'] 
                      : ($website_settings['product_care_title'] ?? 'Care Instructions');
                  
                  if (!empty($product['custom_care_cards'])) {
                      $p_care_cards = json_decode($product['custom_care_cards'], true);
                      if (json_last_error() === JSON_ERROR_NONE && is_array($p_care_cards) && count($p_care_cards) > 0) {
                          $care_cards = $p_care_cards;
                      }
                  }
              } else {
                  $global_care_enabled = ($website_settings['product_care_enabled'] ?? '1') !== '0';
                  if ($global_care_enabled) {
                      $show_care = true;
                      $care_title = !empty($website_settings['product_care_title']) 
                          ? $website_settings['product_care_title'] 
                          : 'Care Instructions';
                      
                      if (!empty($website_settings['product_care_cards'])) {
                          $g_care_cards = json_decode($website_settings['product_care_cards'], true);
                          if (json_last_error() === JSON_ERROR_NONE && is_array($g_care_cards) && count($g_care_cards) > 0) {
                              $care_cards = $g_care_cards;
                          }
                      }
                  }
              }

              if ($show_care && empty($care_cards)) {
                  $care_cards = $default_care_fallback;
              }
              ?>
              <div class="row g-5">
                <div class="<?php echo ($show_care && !empty($care_cards)) ? 'col-md-6' : 'col-md-12'; ?>">
                  <h3 class="pd-section-title">Product Specifications</h3>
                  <div class="pd-spec-table">
                    <div class="pd-spec-row">
                      <span class="pd-spec-key">Category</span>
                      <span class="pd-spec-val"><?php echo htmlspecialchars($product['category_name']); ?></span>
                    </div>
                    <div class="pd-spec-row">
                      <span class="pd-spec-key">Availability</span>
                      <span class="pd-spec-val" style="color:<?php echo $product['stock'] > 0 ? '#198754' : '#dc3545'; ?>">
                        <?php echo $product['stock'] > 0 ? 'In Stock' : 'Out of Stock'; ?>
                      </span>
                    </div>
                    <div class="pd-spec-row">
                      <span class="pd-spec-key">SKU</span>
                      <span class="pd-spec-val">SILKY-<?php echo str_pad($product['id'], 5, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <?php if (!empty($colors)): ?>
                    <div class="pd-spec-row">
                      <span class="pd-spec-key">Colors</span>
                      <span class="pd-spec-val"><?php echo implode(', ', array_column($colors, 'name')); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($sizes)): ?>
                    <div class="pd-spec-row">
                      <span class="pd-spec-key">Sizes</span>
                      <span class="pd-spec-val"><?php echo implode(', ', $sizes); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="pd-spec-row">
                      <span class="pd-spec-key">Origin</span>
                      <span class="pd-spec-val">India</span>
                    </div>
                  </div>
                </div>

                <?php if ($show_care && !empty($care_cards)): ?>
                <div class="col-md-6">
                  <h3 class="pd-section-title"><?php echo htmlspecialchars($care_title); ?></h3>
                  <div class="pd-care-grid">
                    <?php foreach ($care_cards as $c): 
                        $c_icon = trim($c['icon'] ?? 'bi bi-droplet-half');
                        if (strpos($c_icon, 'bi-') === 0 && strpos($c_icon, 'bi ') !== 0) {
                            $c_icon = 'bi ' . $c_icon;
                        }
                        $c_color = !empty($c['color']) ? $c['color'] : '#0dcaf0';
                        $c_title = $c['title'] ?? '';
                        $c_desc  = $c['desc'] ?? '';
                    ?>
                    <div class="pd-care-item">
                      <i class="<?php echo htmlspecialchars($c_icon); ?>" style="color:<?php echo htmlspecialchars($c_color); ?>;"></i>
                      <div class="care-text">
                        <span class="care-title"><?php echo htmlspecialchars($c_title); ?></span>
                        <?php echo htmlspecialchars($c_desc); ?>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Reviews -->
          <div class="pd-tab-content-panel" id="tab-reviews">
            <div class="pd-reviews-wrapper" style="text-align: left;">
              <?php if ($total_reviews == 0): ?>
                <div style="text-align: center;">
                  <div class="pd-empty-reviews-icon">
                    <i class="bi bi-star"></i>
                  </div>
                  <h4 style="font-family:'Montserrat',sans-serif; font-weight:800; color:#0e2187; margin-bottom:10px;">No Reviews Yet</h4>
                  <p style="color:#888; max-width:400px; margin:0 auto 28px;">Be the first to share your experience with this beautiful product and help others decide!</p>
                </div>
              <?php else: ?>
                <div class="reviews-list mb-5">
                  <h4 style="font-family:'Montserrat',sans-serif; font-weight:800; color:#0e2187; margin-bottom:20px;">Customer Reviews</h4>
                  <?php foreach($reviews as $r): ?>
                  <div class="review-item" style="border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px;">
                    <div class="d-flex align-items-center mb-2">
                      <div class="reviewer-name fw-bold me-3" style="color: #0e2187;"><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></div>
                      <div class="pd-stars" style="font-size: 0.9rem;">
                        <?php for($i=1; $i<=5; $i++): ?>
                          <i class="bi bi-star<?php echo ($i <= $r['rating']) ? '-fill star-filled' : ''; ?>"></i>
                        <?php endfor; ?>
                      </div>
                      <div class="review-date text-muted ms-auto" style="font-size: 0.8rem;"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></div>
                    </div>
                    <div class="review-text" style="color: #555; font-size: 0.95rem; line-height: 1.6;">
                      <?php echo nl2br(htmlspecialchars($r['review_text'])); ?>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if ($can_review): ?>
              <div class="review-form-section mt-4" style="background: #f8f9fc; padding: 30px; border-radius: 12px; text-align: center;">
                  <h5 style="font-weight: 700; color: #0e2187; margin-bottom: 15px;">Write a Review</h5>
                  <form id="reviewForm" style="text-align: left; max-width: 600px; margin: 0 auto;">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <div class="mb-3">
                      <label class="form-label" style="font-weight: 600; color: #444;">Your Rating</label>
                      <div class="rating-select d-flex gap-2" style="font-size: 1.5rem; color: #ccc; cursor: pointer;">
                        <i class="bi bi-star" data-rating="1"></i>
                        <i class="bi bi-star" data-rating="2"></i>
                        <i class="bi bi-star" data-rating="3"></i>
                        <i class="bi bi-star" data-rating="4"></i>
                        <i class="bi bi-star" data-rating="5"></i>
                      </div>
                      <input type="hidden" name="rating" id="selected-rating" value="0" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label" style="font-weight: 600; color: #444;">Your Review</label>
                      <textarea name="review_text" class="form-control" rows="4" placeholder="Share your experience..." required style="border-radius: 8px; border: 1.5px solid #edf0fb;"></textarea>
                    </div>
                    <button type="submit" class="pd-write-review-btn w-100 justify-content-center mt-2">
                      Submit Review
                    </button>
                    <div id="reviewMessage" class="mt-3 text-center fw-bold" style="display: none;"></div>
                  </form>
              </div>
              <?php endif; ?>

            </div>
          </div>

        </div><!-- /tabs section -->
      </div>
    </section>

  </main>

  <?php include './footer.php'; ?>

  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center">
    <i class="bi bi-arrow-up-short"></i>
  </a>



  <?php include 'mobile-bottom-nav.php'; ?>

  <!-- Add Notify Modal -->
  <div class="modal fade" id="notifyModal" tabindex="-1" aria-labelledby="notifyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0" style="border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
        <div class="modal-header border-0 pb-0" style="background: linear-gradient(135deg, #0e2187, #1a35c9); padding: 24px; justify-content: center; position: relative;">
          <h5 class="modal-title text-white" id="notifyModalLabel" style="font-family: 'Montserrat', sans-serif; font-weight: 700;">Notify Me When Available</h5>
          <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4 text-center">
          <i class="bi bi-bell-fill" style="font-size: 3rem; color: #97c51d; display: block; margin-bottom: 15px;"></i>
          <p class="text-muted mb-4">Enter your email address below and we'll send you an email as soon as this product is back in stock!</p>
          <form id="notifyForm">
            <div class="mb-3">
              <input type="email" class="form-control" id="notifyEmail" placeholder="your.email@example.com" required style="padding: 12px 16px; border-radius: 8px; border: 2px solid #edf0fb;" value="<?php echo isset($_SESSION['user_id']) ? htmlspecialchars($_SESSION['email'] ?? '') : ''; ?>">
            </div>
            <button type="submit" class="btn w-100 text-white" style="background: linear-gradient(135deg, #97c51d, #7aa817); border-radius: 8px; padding: 12px; font-weight: 600;" id="notifySubmitBtn">
              Subscribe to Notifications
            </button>
          </form>
          <div id="notifyMessage" class="mt-3" style="display: none; font-weight: 600;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Custom Tailoring Measurements Modal -->
  <div class="modal fade" id="customMeasurementsModal" tabindex="-1" aria-labelledby="customMeasurementsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content border-0">
        <div class="modal-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="modal-title text-white fw-bold mb-1" id="customMeasurementsModalLabel">
              <i class="bi bi-scissors me-2" style="color: #97c51d;"></i>Custom Tailoring Measurements
            </h5>
            <small class="text-white-50">Provide your measurements for a made-to-measure fit.</small>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <form id="customMeasurementsForm">
            <!-- Guidance Bar (Inches Only) -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3 border-bottom gap-2">
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fs-12 fw-semibold">
                  <i class="bi bi-rulers me-1"></i>All measurements in Inches (in)
                </span>
              </div>
              <div class="text-muted fs-12">
                <i class="bi bi-info-circle me-1 text-primary"></i>Use a standard flexible measuring tape
              </div>
            </div>

            <!-- Measurement Fields -->
            <div class="row g-3 mb-3">
              <div class="col-6 col-md-4">
                <label class="meas-label" for="meas_bust">
                  <span>Bust / Chest *</span>
                  <span class="meas-tip">Fullest point</span>
                </label>
                <div class="input-group input-group-sm">
                  <input type="number" step="0.5" min="10" max="150" class="form-control" id="meas_bust" placeholder="e.g. 36" required>
                  <span class="input-group-text meas-unit-badge">in</span>
                </div>
              </div>

              <div class="col-6 col-md-4">
                <label class="meas-label" for="meas_waist">
                  <span>Waist *</span>
                  <span class="meas-tip">Natural waist</span>
                </label>
                <div class="input-group input-group-sm">
                  <input type="number" step="0.5" min="10" max="150" class="form-control" id="meas_waist" placeholder="e.g. 30" required>
                  <span class="input-group-text meas-unit-badge">in</span>
                </div>
              </div>

              <div class="col-6 col-md-4">
                <label class="meas-label" for="meas_hips">
                  <span>Hips</span>
                  <span class="meas-tip">Widest part</span>
                </label>
                <div class="input-group input-group-sm">
                  <input type="number" step="0.5" min="10" max="180" class="form-control" id="meas_hips" placeholder="e.g. 40">
                  <span class="input-group-text meas-unit-badge">in</span>
                </div>
              </div>

              <div class="col-6 col-md-4">
                <label class="meas-label" for="meas_length">
                  <span>Garment Length *</span>
                  <span class="meas-tip">Shoulder to hem</span>
                </label>
                <div class="input-group input-group-sm">
                  <input type="number" step="0.5" min="10" max="200" class="form-control" id="meas_length" placeholder="e.g. 42" required>
                  <span class="input-group-text meas-unit-badge">in</span>
                </div>
              </div>

              <div class="col-6 col-md-4">
                <label class="meas-label" for="meas_shoulder">
                  <span>Shoulder Width</span>
                  <span class="meas-tip">Tip to tip across</span>
                </label>
                <div class="input-group input-group-sm">
                  <input type="number" step="0.5" min="5" max="50" class="form-control" id="meas_shoulder" placeholder="e.g. 14.5">
                  <span class="input-group-text meas-unit-badge">in</span>
                </div>
              </div>

              <div class="col-6 col-md-4">
                <label class="meas-label" for="meas_sleeve">
                  <span>Sleeve Length</span>
                  <span class="meas-tip">Shoulder to cuff</span>
                </label>
                <div class="input-group input-group-sm">
                  <input type="number" step="0.5" min="1" max="60" class="form-control" id="meas_sleeve" placeholder="e.g. 16">
                  <span class="input-group-text meas-unit-badge">in</span>
                </div>
              </div>

              <div class="col-6 col-md-4">
                <label class="meas-label" for="meas_armhole">
                  <span>Armhole</span>
                  <span class="meas-tip">Around shoulder joint</span>
                </label>
                <div class="input-group input-group-sm">
                  <input type="number" step="0.5" min="5" max="50" class="form-control" id="meas_armhole" placeholder="e.g. 15">
                  <span class="input-group-text meas-unit-badge">in</span>
                </div>
              </div>

              <div class="col-6 col-md-4">
                <label class="meas-label" for="meas_front_neck">
                  <span>Front Neck Depth</span>
                  <span class="meas-tip">Shoulder to center</span>
                </label>
                <div class="input-group input-group-sm">
                  <input type="number" step="0.5" min="2" max="25" class="form-control" id="meas_front_neck" placeholder="e.g. 7">
                  <span class="input-group-text meas-unit-badge">in</span>
                </div>
              </div>

              <div class="col-6 col-md-4">
                <label class="meas-label" for="meas_fit">
                  <span>Fit Preference</span>
                  <span class="meas-tip">Comfort level</span>
                </label>
                <select class="form-select form-select-sm" id="meas_fit">
                  <option value="Regular" selected>Regular Fit (Standard)</option>
                  <option value="Slim">Slim Fit (Form-fitting)</option>
                  <option value="Relaxed">Relaxed Fit (Comfortable)</option>
                </select>
              </div>
            </div>

            <!-- Tailoring Notes -->
            <div class="mb-3">
              <label class="meas-label" for="meas_notes">
                <span>Special Tailoring Notes / Customization Requests</span>
                <span class="meas-tip">Optional</span>
              </label>
              <textarea class="form-control" id="meas_notes" rows="2" placeholder="e.g. Leave extra seam margin inside, pads in blouse, specific neck style, etc." style="font-size: 0.85rem; border-radius: 8px;"></textarea>
            </div>

            <!-- Notice & Actions -->
            <div class="d-flex flex-wrap justify-content-between align-items-center pt-3 border-top gap-2">
              <div class="d-flex align-items-center gap-2 text-muted fs-12">
                <i class="bi bi-shield-check text-success fs-6"></i>
                <span>Our master tailors craft this piece individually to your measurements.</span>
              </div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary px-4 fw-semibold" id="saveCustomMeasBtn" style="background: linear-gradient(135deg, #0e2187, #1b3cc7); border: none;">
                  <i class="bi bi-check2-circle me-1"></i> Save Measurements
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Vendor JS Files -->
  <script src="<?php echo $base_url; ?>assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="<?php echo $base_url; ?>assets/vendor/php-email-form/validate.js"></script>
  <script src="<?php echo $base_url; ?>assets/vendor/swiper/swiper-bundle.min.js"></script>
  <script src="<?php echo $base_url; ?>assets/vendor/aos/aos.js"></script>
  <script src="<?php echo $base_url; ?>assets/vendor/glightbox/js/glightbox.min.js"></script>
  <script src="<?php echo $base_url; ?>assets/vendor/purecounter/purecounter_vanilla.js"></script>
  <script src="<?php echo $base_url; ?>assets/js/main.js"></script>

  <!-- YouTube IFrame API -->
  <script src="https://www.youtube.com/iframe_api"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function () {

    /* ========== GALLERY & CUSTOM VIDEO CONTROLS ========== */
    const mainViewer  = document.getElementById('pd-main-viewer');
    const mainImg     = document.getElementById('main-product-image');
    const ytContainer = document.getElementById('pd-yt-player-container');
    const thumbs      = document.querySelectorAll('.pd-thumb');
    let   currentIdx  = 0;
    let   totalItems  = thumbs.length;
    let   ytPlayer    = null;
    let   progressInterval = null;

    // Custom Controls
    const btnPlayPause  = document.getElementById('vid-play-pause');
    const btnMute       = document.getElementById('vid-mute');
    const btnFullscreen = document.getElementById('vid-fullscreen');
    const progressCont  = document.getElementById('vid-progress');
    const progressFill  = document.getElementById('vid-progress-fill');
    const progressThumb = document.getElementById('vid-progress-thumb');
    const clickOverlay  = document.getElementById('vid-click-overlay');
    const centerIconEl  = document.getElementById('center-icon-i');

    window.onYouTubeIframeAPIReady = function() {
      if (ytContainer) {
        const vidId = ytContainer.dataset.videoId;
        ytPlayer = new YT.Player('pd-yt-player', {
          videoId: vidId,
          playerVars: {
            'autoplay': 0,
            'controls': 0,
            'disablekb': 1,
            'rel': 0,
            'modestbranding': 1,
            'showinfo': 0,
            'fs': 0,
            'iv_load_policy': 3
          },
          events: { 'onStateChange': onPlayerStateChange }
        });
      }
    };

    function togglePlayPause() {
      if (!ytPlayer || !ytPlayer.getPlayerState) return;
      if (ytPlayer.getPlayerState() === YT.PlayerState.PLAYING) {
        ytPlayer.pauseVideo();
      } else {
        ytPlayer.playVideo();
      }
    }

    function onPlayerStateChange(event) {
      const playing = event.data === YT.PlayerState.PLAYING;
      mainViewer.classList.toggle('is-paused', !playing);
      const icon = playing ? 'bi-pause-fill' : 'bi-play-fill';
      if (btnPlayPause) btnPlayPause.innerHTML = `<i class="bi ${icon}"></i>`;
      if (centerIconEl)  centerIconEl.className = `bi ${icon}`;
      if (playing) startProgressLoop(); else stopProgressLoop();
    }

    function startProgressLoop() {
      if (progressInterval) clearInterval(progressInterval);
      progressInterval = setInterval(() => {
        if (!ytPlayer || !ytPlayer.getCurrentTime) return;
        const dur = ytPlayer.getDuration();
        if (dur > 0) {
          const pct = (ytPlayer.getCurrentTime() / dur) * 100;
          if (progressFill)  progressFill.style.width  = pct + '%';
          if (progressThumb) progressThumb.style.left   = pct + '%';
        }
      }, 100);
    }

    function stopProgressLoop() {
      if (progressInterval) clearInterval(progressInterval);
    }

    // Click overlay — toggle play/pause anywhere on the video
    if (clickOverlay) {
      clickOverlay.addEventListener('click', (e) => {
        // Don't fire if the controls bar was clicked
        if (e.target.closest('#custom-video-controls')) return;
        togglePlayPause();
      });
    }

    // Bottom-bar play/pause button
    if (btnPlayPause) {
      btnPlayPause.addEventListener('click', (e) => { e.stopPropagation(); togglePlayPause(); });
    }

    // Mute
    if (btnMute) {
      btnMute.addEventListener('click', (e) => {
        e.stopPropagation();
        if (!ytPlayer || !ytPlayer.isMuted) return;
        if (ytPlayer.isMuted()) {
          ytPlayer.unMute();
          btnMute.innerHTML = '<i class="bi bi-volume-up-fill"></i>';
        } else {
          ytPlayer.mute();
          btnMute.innerHTML = '<i class="bi bi-volume-mute-fill"></i>';
        }
      });
    }

    // Fullscreen — enter native browser fullscreen on the player container
    if (btnFullscreen) {
      btnFullscreen.addEventListener('click', (e) => {
        e.stopPropagation();
        const el = document.getElementById('pd-main-viewer');
        if (!document.fullscreenElement) {
          if (el.requestFullscreen) el.requestFullscreen();
          else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
          else if (el.mozRequestFullScreen) el.mozRequestFullScreen();
        } else {
          if (document.exitFullscreen) document.exitFullscreen();
        }
      });
      document.addEventListener('fullscreenchange', () => {
        if (document.fullscreenElement) {
          btnFullscreen.innerHTML = '<i class="bi bi-fullscreen-exit"></i>';
          mainViewer.classList.add('is-fullscreen');
        } else {
          btnFullscreen.innerHTML = '<i class="bi bi-fullscreen"></i>';
          mainViewer.classList.remove('is-fullscreen');
        }
      });
    }

    // Seek bar — support click AND drag
    if (progressCont) {
      let isSeeking = false;

      function seekTo(e) {
        if (!ytPlayer || !ytPlayer.getDuration) return;
        const rect = progressCont.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const frac = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
        const pct = frac * 100;
        if (progressFill)  progressFill.style.width = pct + '%';
        if (progressThumb) progressThumb.style.left  = pct + '%';
        ytPlayer.seekTo(frac * ytPlayer.getDuration(), true);
      }

      progressCont.addEventListener('mousedown', (e) => {
        e.stopPropagation();
        isSeeking = true;
        seekTo(e);
      });
      document.addEventListener('mousemove', (e) => { if (isSeeking) seekTo(e); });
      document.addEventListener('mouseup',   () => { isSeeking = false; });

      // Touch support
      progressCont.addEventListener('touchstart', (e) => { e.stopPropagation(); isSeeking = true; seekTo(e); }, { passive: true });
      document.addEventListener('touchmove',  (e) => { if (isSeeking) seekTo(e); }, { passive: true });
      document.addEventListener('touchend',   () => { isSeeking = false; });
    }

    function setActive(idx) {
      thumbs.forEach(t => t.classList.remove('active'));
      if (thumbs[idx]) thumbs[idx].classList.add('active');
      currentIdx = idx;

      const thumb = thumbs[idx];
      if (!thumb) return;

      if (thumb.dataset.type === 'video') {
        // Show video
        mainViewer.classList.add('showing-video');
        if (ytPlayer && ytPlayer.playVideo) {
          ytPlayer.playVideo();
        } else {
          mainViewer.classList.add('is-paused');
        }
      } else {
        // Show image
        mainViewer.classList.remove('showing-video');
        if (ytPlayer && ytPlayer.pauseVideo) {
          ytPlayer.pauseVideo();
        }
        const src = thumb.dataset.src;
        if (src) {
          mainImg.style.opacity = '0';
          setTimeout(() => {
            mainImg.src = src;
            mainImg.style.opacity = '1';
          }, 150);
        }
      }
    }

    thumbs.forEach((thumb, idx) => {
      thumb.addEventListener('click', () => setActive(idx));
    });

    document.getElementById('gallery-prev')?.addEventListener('click', () => {
      setActive((currentIdx - 1 + totalItems) % totalItems);
    });

    document.getElementById('gallery-next')?.addEventListener('click', () => {
      setActive((currentIdx + 1) % totalItems);
    });

    /* Keyboard navigation */
    document.addEventListener('keydown', e => {
      if (e.key === 'ArrowLeft')  setActive((currentIdx - 1 + totalItems) % totalItems);
      if (e.key === 'ArrowRight') setActive((currentIdx + 1) % totalItems);
    });

    /* ========== TABS ========== */
    const tabBtns = document.querySelectorAll('.pd-tab-btn');
    const tabPanels = document.querySelectorAll('.pd-tab-content-panel');

    tabBtns.forEach(btn => {
      btn.addEventListener('click', function () {
        const target = this.dataset.tab;
        tabBtns.forEach(b => b.classList.remove('active'));
        tabPanels.forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        const panel = document.getElementById('tab-' + target);
        if (panel) panel.classList.add('active');
      });
    });

    /* ========== PRODUCT DATA ========== */
    const productId    = <?php echo (int)$product['id']; ?>;
    const productName  = <?php echo json_encode($product['name']); ?>;
    const productPrice = <?php echo (float)$product['price']; ?>;
    const productImage = <?php echo json_encode($main_image); ?>;
    const productSlug  = <?php echo json_encode(isset($product['slug']) ? $product['slug'] : ''); ?>;

    /* ========== VARIANTS DATA & LOGIC ========== */
    const variantsData = <?php echo json_encode($variants); ?>;
    let currentPrice = <?php echo $product['price']; ?>;
    let currentStock = <?php echo max(0, $product['stock']); ?>;
    let selectedColor = null;
    let selectedSize  = null;

    function updateVariantDetails() {
      if (selectedColor && selectedSize) {
        const key = selectedColor + '_' + selectedSize;
        if (variantsData[key]) {
          currentPrice = variantsData[key].price;
          currentStock = variantsData[key].stock;
          let comparePrice = variantsData[key].compare_price;
          
          document.querySelector('.pd-price-main').textContent = '₹' + parseFloat(currentPrice).toFixed(2);
          
          const comparePriceEl = document.getElementById('pd-price-compare');
          const discountBadgeEl = document.getElementById('pd-discount-badge');
          
          if (comparePrice && parseFloat(comparePrice) > parseFloat(currentPrice)) {
            const discount = Math.round(((parseFloat(comparePrice) - parseFloat(currentPrice)) / parseFloat(comparePrice)) * 100);
            comparePriceEl.textContent = '₹' + parseFloat(comparePrice).toFixed(2);
            comparePriceEl.style.display = 'inline';
            discountBadgeEl.textContent = '-' + discount + '%';
            discountBadgeEl.style.display = 'inline-block';
          } else {
            if(comparePriceEl) comparePriceEl.style.display = 'none';
            if(discountBadgeEl) discountBadgeEl.style.display = 'none';
          }
          
          const stockContainer = document.getElementById('stock-container');
          const stockCountText = document.getElementById('stock-count-text');
          
          if (currentStock > 0) {
            stockContainer.innerHTML = `
              <span class="pd-stock-badge in-stock">
                <i class="bi bi-check-circle-fill"></i> In Stock
              </span>
              <span class="pd-stock-count" id="stock-count-text">Only ${currentStock} left!</span>
            `;
            document.getElementById('btn-add-to-cart').style.display = 'flex';
            document.getElementById('btn-buy-now').style.display = 'flex';
            document.getElementById('btn-notify-me').style.display = 'none';
          } else {
            stockContainer.innerHTML = `
              <span class="pd-stock-badge out-of-stock">
                <i class="bi bi-x-circle-fill"></i> Out of Stock
              </span>
              <span class="pd-stock-count" id="stock-count-text" style="display:none;"></span>
            `;
            document.getElementById('btn-add-to-cart').style.display = 'none';
            document.getElementById('btn-buy-now').style.display = 'none';
            document.getElementById('btn-notify-me').style.display = 'flex';
          }

          // Update max quantity input (capped at max 10)
          const qtyInput = document.getElementById('product-quantity');
          if (qtyInput) {
            const maxAllowed = Math.min(10, Math.max(1, currentStock));
            qtyInput.max = maxAllowed;
            if (parseInt(qtyInput.value) > maxAllowed) {
              qtyInput.value = maxAllowed;
            }
          }
          if (typeof updateAddonsTotal === 'function') {
            updateAddonsTotal();
          }
        }
      }
    }

    const colorChips = document.querySelectorAll('.pd-color-chip');
    if (colorChips.length > 0) {
      selectedColor = colorChips[0].dataset.color;
      colorChips.forEach(chip => {
        chip.addEventListener('click', function () {
          colorChips.forEach(c => c.classList.remove('active'));
          this.classList.add('active');
          selectedColor = this.dataset.color;
          const nameEl = document.getElementById('selected-color-name');
          if (nameEl) nameEl.textContent = selectedColor;
          updateVariantDetails();
        });
      });
    }

    const sizeBtns = document.querySelectorAll('.pd-size-btn');
    if (sizeBtns.length > 0) {
      selectedSize = sizeBtns[0].dataset.size;
      sizeBtns.forEach(btn => {
        btn.addEventListener('click', function () {
          const wasActive = this.classList.contains('active');
          sizeBtns.forEach(b => b.classList.remove('active'));
          this.classList.add('active');
          selectedSize = this.dataset.size;
          updateVariantDetails();
          const isCust = isCustomSizeSelected();
          if (isCust) {
            handleCustomSizeUI(wasActive ? true : (!checkHasCustomMeasurements()));
          } else {
            handleCustomSizeUI(false);
          }
        });
      });
    }

    /* ========== CUSTOM TAILORING MEASUREMENTS ========== */
    const CUSTOM_MEAS_STORAGE_KEY = 'custom_meas_' + productId;
    let customMeasurements = null;

    try {
      const savedMeas = localStorage.getItem(CUSTOM_MEAS_STORAGE_KEY);
      if (savedMeas) {
        customMeasurements = JSON.parse(savedMeas);
      }
    } catch (e) {
      console.error('Error loading custom measurements:', e);
    }

    function isCustomSizeSelected() {
      return !!(selectedSize && selectedSize.trim().toLowerCase() === 'custom');
    }

    function checkHasCustomMeasurements() {
      if (!customMeasurements) return false;
      const { bust, waist, hips, length, shoulder, sleeve, armhole, front_neck, notes } = customMeasurements;
      return !!(bust || waist || hips || length || shoulder || sleeve || armhole || front_neck || notes);
    }

    function getCustomMeasurementsSummary() {
      if (!customMeasurements) return '';
      const u = ' in';
      const parts = [];
      if (customMeasurements.bust) parts.push(`Bust: ${customMeasurements.bust}${u}`);
      if (customMeasurements.waist) parts.push(`Waist: ${customMeasurements.waist}${u}`);
      if (customMeasurements.hips) parts.push(`Hips: ${customMeasurements.hips}${u}`);
      if (customMeasurements.length) parts.push(`Length: ${customMeasurements.length}${u}`);
      if (customMeasurements.shoulder) parts.push(`Shoulder: ${customMeasurements.shoulder}${u}`);
      if (customMeasurements.sleeve) parts.push(`Sleeve: ${customMeasurements.sleeve}${u}`);
      if (customMeasurements.armhole) parts.push(`Armhole: ${customMeasurements.armhole}${u}`);
      if (customMeasurements.front_neck) parts.push(`Front Neck: ${customMeasurements.front_neck}${u}`);
      if (customMeasurements.fit && customMeasurements.fit !== 'Regular') parts.push(`Fit: ${customMeasurements.fit}`);
      if (customMeasurements.notes) parts.push(`Notes: ${customMeasurements.notes}`);
      return parts.join(' • ');
    }

    function renderCustomMeasStatus(hasMeas) {
      const statusBox = document.getElementById('custom-meas-status-box');
      if (!statusBox) return;

      if (!isCustomSizeSelected()) {
        statusBox.style.display = 'none';
        return;
      }

      statusBox.style.display = 'block';
      const cardUnfilled = document.getElementById('custom-meas-card-unfilled');
      const cardFilled   = document.getElementById('custom-meas-card-filled');
      const summaryEl    = document.getElementById('custom-meas-summary-text');

      if (hasMeas) {
        if (cardUnfilled) cardUnfilled.style.display = 'none';
        if (cardFilled) cardFilled.style.display = 'flex';
        if (summaryEl) summaryEl.textContent = getCustomMeasurementsSummary();
      } else {
        if (cardUnfilled) cardUnfilled.style.display = 'flex';
        if (cardFilled) cardFilled.style.display = 'none';
      }
    }

    function handleCustomSizeUI(autoOpenIfEmpty = true) {
      if (!isCustomSizeSelected()) {
        renderCustomMeasStatus(false);
        return;
      }
      const hasMeas = checkHasCustomMeasurements();
      renderCustomMeasStatus(hasMeas);
      if (!hasMeas && autoOpenIfEmpty) {
        openCustomMeasurementsModal();
      }
    }

    function openCustomMeasurementsModal() {
      const modalEl = document.getElementById('customMeasurementsModal');
      if (!modalEl) return;

      if (customMeasurements) {
        if (document.getElementById('meas_bust'))       document.getElementById('meas_bust').value       = customMeasurements.bust || '';
        if (document.getElementById('meas_waist'))      document.getElementById('meas_waist').value      = customMeasurements.waist || '';
        if (document.getElementById('meas_hips'))       document.getElementById('meas_hips').value       = customMeasurements.hips || '';
        if (document.getElementById('meas_length'))     document.getElementById('meas_length').value     = customMeasurements.length || '';
        if (document.getElementById('meas_shoulder'))   document.getElementById('meas_shoulder').value   = customMeasurements.shoulder || '';
        if (document.getElementById('meas_sleeve'))     document.getElementById('meas_sleeve').value     = customMeasurements.sleeve || '';
        if (document.getElementById('meas_armhole'))    document.getElementById('meas_armhole').value    = customMeasurements.armhole || '';
        if (document.getElementById('meas_front_neck')) document.getElementById('meas_front_neck').value = customMeasurements.front_neck || '';
        if (document.getElementById('meas_fit'))        document.getElementById('meas_fit').value        = customMeasurements.fit || 'Regular';
        if (document.getElementById('meas_notes'))      document.getElementById('meas_notes').value      = customMeasurements.notes || '';
      }

      const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
      modalInstance.show();
      setTimeout(() => {
        const bustInput = document.getElementById('meas_bust');
        if (bustInput) bustInput.focus();
      }, 400);
    }

    const customMeasForm = document.getElementById('customMeasurementsForm');
    if (customMeasForm) {
      customMeasForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const unit = 'in';
        const bust = document.getElementById('meas_bust')?.value.trim() || '';
        const waist = document.getElementById('meas_waist')?.value.trim() || '';
        const hips = document.getElementById('meas_hips')?.value.trim() || '';
        const length = document.getElementById('meas_length')?.value.trim() || '';
        const shoulder = document.getElementById('meas_shoulder')?.value.trim() || '';
        const sleeve = document.getElementById('meas_sleeve')?.value.trim() || '';
        const armhole = document.getElementById('meas_armhole')?.value.trim() || '';
        const front_neck = document.getElementById('meas_front_neck')?.value.trim() || '';
        const fit = document.getElementById('meas_fit')?.value || 'Regular';
        const notes = document.getElementById('meas_notes')?.value.trim() || '';

        if (!bust && !waist && !length && !hips && !shoulder && !notes) {
          alert('Please provide at least one key measurement (such as Bust, Waist, or Length).');
          return;
        }

        customMeasurements = {
          unit, bust, waist, hips, length, shoulder, sleeve, armhole, front_neck, fit, notes
        };

        try {
          localStorage.setItem(CUSTOM_MEAS_STORAGE_KEY, JSON.stringify(customMeasurements));
        } catch(err) {
          console.error('Failed to persist custom measurements:', err);
        }

        renderCustomMeasStatus(true);

        const modalEl = document.getElementById('customMeasurementsModal');
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();

        showToast('<i class="bi bi-check2-circle me-2"></i>Custom measurements saved successfully!', 'success');
      });
    }

    document.getElementById('openCustomMeasModalBtn')?.addEventListener('click', openCustomMeasurementsModal);
    document.getElementById('editCustomMeasModalBtn')?.addEventListener('click', openCustomMeasurementsModal);


    
    // Notify logic
    const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
    
    document.getElementById('btn-notify-me').addEventListener('click', function() {
        if (isLoggedIn) {
            submitNotification();
        } else {
            const notifyModal = new bootstrap.Modal(document.getElementById('notifyModal'));
            notifyModal.show();
        }
    });
    
    document.getElementById('notifyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitNotification(document.getElementById('notifyEmail').value);
    });
    
    function submitNotification(email = null) {
        const productId = document.getElementById('btn-notify-me').getAttribute('data-product-id');
        let variantKey = '';
        if (selectedColor && selectedSize) {
            variantKey = selectedColor + '_' + selectedSize;
        }
        
        let formData = new FormData();
        formData.append('product_id', productId);
        if (variantKey) formData.append('variant_key', variantKey);
        if (email) formData.append('email', email);
        
        const msgEl = document.getElementById('notifyMessage');
        const submitBtn = document.getElementById('notifySubmitBtn');
        const oldText = submitBtn.innerHTML;
        
        if (email) {
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
            submitBtn.disabled = true;
        } else {
            document.getElementById('btn-notify-me').innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        }
        
        fetch('submit-notification', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (email) {
                submitBtn.innerHTML = oldText;
                submitBtn.disabled = false;
                msgEl.style.display = 'block';
                if (data.success) {
                    msgEl.className = 'mt-3 text-success';
                    msgEl.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + data.message;
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(document.getElementById('notifyModal')).hide();
                        msgEl.style.display = 'none';
                    }, 2000);
                } else {
                    msgEl.className = 'mt-3 text-danger';
                    msgEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> ' + data.message;
                }
            } else {
                document.getElementById('btn-notify-me').innerHTML = '<i class="bi bi-bell-fill"></i> Notify Me!';
                if (data.success) {
                    alert(data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            }
        })
        .catch(err => {
            console.error('Error:', err);
            if (email) {
                submitBtn.innerHTML = oldText;
                submitBtn.disabled = false;
            } else {
                document.getElementById('btn-notify-me').innerHTML = '<i class="bi bi-bell-fill"></i> Notify Me!';
            }
            alert('An error occurred. Please try again.');
        });
    }
    
    // Initialize variant on load
    updateVariantDetails();
    handleCustomSizeUI(false);

    /* ========== QUANTITY ========== */
    const qtyInput    = document.getElementById('product-quantity');
    const qtyDecrease = document.getElementById('qty-decrease');
    const qtyIncrease = document.getElementById('qty-increase');

    function enforceQtyBounds() {
      if (!qtyInput) return;
      let val = parseInt(qtyInput.value, 10);
      const min = parseInt(qtyInput.min, 10) || 1;
      const max = Math.min(10, parseInt(qtyInput.max, 10) || 10);

      if (isNaN(val) || val < min) {
        qtyInput.value = min;
      } else if (val > max) {
        qtyInput.value = max;
      }
    }

    qtyDecrease?.addEventListener('click', () => {
      let v = parseInt(qtyInput.value, 10) || 1;
      const min = parseInt(qtyInput.min, 10) || 1;
      if (v > min) {
        qtyInput.value = v - 1;
        if (typeof updateAddonsTotal === 'function') updateAddonsTotal();
      }
    });

    qtyIncrease?.addEventListener('click', () => {
      let v = parseInt(qtyInput.value, 10) || 1;
      const max = Math.min(10, parseInt(qtyInput.max, 10) || 10);
      if (v < max) {
        qtyInput.value = v + 1;
        if (typeof updateAddonsTotal === 'function') updateAddonsTotal();
      }
    });

    qtyInput?.addEventListener('input', () => {
      let val = parseInt(qtyInput.value, 10);
      const max = Math.min(10, parseInt(qtyInput.max, 10) || 10);
      if (val > max) {
        qtyInput.value = max;
      }
      if (typeof updateAddonsTotal === 'function') updateAddonsTotal();
    });

    qtyInput?.addEventListener('change', () => {
      enforceQtyBounds();
      if (typeof updateAddonsTotal === 'function') updateAddonsTotal();
    });
    qtyInput?.addEventListener('blur', () => {
      enforceQtyBounds();
      if (typeof updateAddonsTotal === 'function') updateAddonsTotal();
    });

    /* ========== REVIEWS ========== */
    const ratingStars = document.querySelectorAll('.rating-select i');
    const ratingInput = document.getElementById('selected-rating');
    const reviewForm = document.getElementById('reviewForm');
    
    if (ratingStars.length > 0) {
      ratingStars.forEach(star => {
        star.addEventListener('click', function() {
          const rating = this.dataset.rating;
          ratingInput.value = rating;
          ratingStars.forEach(s => {
            if (s.dataset.rating <= rating) {
              s.classList.remove('bi-star');
              s.classList.add('bi-star-fill', 'text-warning');
            } else {
              s.classList.remove('bi-star-fill', 'text-warning');
              s.classList.add('bi-star');
            }
          });
        });
        
        star.addEventListener('mouseover', function() {
          const rating = this.dataset.rating;
          ratingStars.forEach(s => {
            if (s.dataset.rating <= rating) {
              s.classList.add('text-warning');
            } else {
              s.classList.remove('text-warning');
            }
          });
        });
        
        star.addEventListener('mouseout', function() {
          const currentRating = ratingInput.value;
          ratingStars.forEach(s => {
            if (s.dataset.rating <= currentRating) {
              s.classList.add('text-warning');
            } else {
              s.classList.remove('text-warning');
            }
          });
        });
      });
    }

    if (reviewForm) {
      reviewForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (ratingInput.value == 0) {
          showToast('Please select a rating', 'error');
          return;
        }
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const oldText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
        submitBtn.disabled = true;
        
        fetch('submit-review.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          submitBtn.innerHTML = oldText;
          submitBtn.disabled = false;
          if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
              window.location.reload();
            }, 1500);
          } else {
            showToast(data.message, 'error');
          }
        })
        .catch(err => {
          console.error('Error:', err);
          submitBtn.innerHTML = oldText;
          submitBtn.disabled = false;
          showToast('An error occurred. Please try again.', 'error');
        });
      });
    }

    /* ========== TOAST ========== */
    function showToast(message, type = 'success') {
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
          toastContainer = document.createElement('div');
          toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
          toastContainer.style.zIndex = '1080';
          document.body.appendChild(toastContainer);
        }
        
        let bgColor, iconClass, textColor;
        if (type === 'success') {
            bgColor = '#8AC53E';
            iconClass = 'bi-check-circle-fill';
            textColor = '#fff';
        } else if (type === 'error' || type === 'danger') {
            bgColor = '#e74c3c';
            iconClass = 'bi-exclamation-triangle-fill';
            textColor = '#fff';
        } else if (type === 'warning' || type === 'secondary') {
            bgColor = '#f39c12';
            iconClass = 'bi-exclamation-circle-fill';
            textColor = '#fff';
        } else {
            bgColor = '#2c3e50';
            iconClass = 'bi-info-circle-fill';
            textColor = '#fff';
        }

        const toast = document.createElement('div');
        toast.className = 'toast align-items-center border-0 mb-3';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.style.background = bgColor;
        toast.style.color = textColor;
        toast.style.borderRadius = '12px';
        toast.style.boxShadow = '0 10px 30px rgba(0,0,0,0.15)';
        toast.style.overflow = 'hidden';
        toast.style.animation = 'slideInUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
        
        toast.innerHTML = `
          <div class="d-flex align-items-center p-3">
            <div class="toast-icon me-3 fs-4 d-flex align-items-center justify-content-center">
              <i class="bi ${iconClass}"></i>
            </div>
            <div class="toast-body flex-grow-1 fs-6 fw-medium p-0 m-0" style="color: inherit; letter-spacing: 0.3px;">
              ${message}
            </div>
            <button type="button" class="btn-close ms-2 m-auto" data-bs-dismiss="toast" aria-label="Close" style="filter: ${textColor === '#fff' ? 'invert(1)' : 'none'}; opacity: 0.8;"></button>
          </div>
        `;
        
        if (!document.getElementById('toast-keyframes')) {
            const style = document.createElement('style');
            style.id = 'toast-keyframes';
            style.innerHTML = `
                @keyframes slideInUp {
                    from { transform: translateY(100%); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
        }

        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => {
          toast.remove();
        });
    }

    /* ========== CART BADGE ========== */
    function updateCartBadge() {
      const cart = JSON.parse(localStorage.getItem('cart')) || [];
      const total = cart.reduce((s, i) => s + i.quantity, 0);
      document.querySelectorAll('.cart-badge, a[href*="cart.php"] .badge').forEach(el => {
        if (el) { el.textContent = total; el.style.display = total > 0 ? 'inline' : 'none'; }
      });
    }



    /* ========== ADD-ON PRODUCTS ========== */
    const addonsData = <?php echo json_encode($addon_products_data); ?>;

    function toggleAddonItem(e, id) {
        if (e.target.tagName === 'A' || e.target.tagName === 'INPUT') return;
        const cb = document.querySelector(`.pd-addon-checkbox[value="${id}"]`);
        if (cb && !cb.disabled) {
            cb.checked = !cb.checked;
            showMeasurementForm(id, cb.checked);
            saveAddonSelections();
            updateAddonsTotal();
        }
    }

    function updateAddonsTotal() {
        const qtyEl = document.getElementById('product-quantity');
        let qty = parseInt(qtyEl?.value, 10) || 1;
        // Use currentPrice (variant-resolved) or fall back to base productPrice
        const basePrice = (typeof currentPrice !== 'undefined' && parseFloat(currentPrice) > 0)
            ? parseFloat(currentPrice)
            : parseFloat(productPrice) || 0;
        let mainTotal = basePrice * qty;
        let addonTotal = 0;
        let selectedAddonsCount = 0;
        document.querySelectorAll('.pd-addon-checkbox:checked').forEach(cb => {
            const itemEl = cb.closest('.pd-addon-item');
            if (itemEl) {
                const p = parseFloat(itemEl.dataset.addonPrice || 0);
                addonTotal += p;
                selectedAddonsCount++;
            }
        });
        const grandTotal = mainTotal + addonTotal;
        const totalEl = document.getElementById('pd-combined-total');
        if (totalEl) {
            totalEl.textContent = '₹' + grandTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        const badgeEl = document.getElementById('pd-addons-count-badge');
        if (badgeEl) {
            if (selectedAddonsCount > 0) {
                badgeEl.textContent = qty + ' main + ' + selectedAddonsCount + ' add-on' + (selectedAddonsCount > 1 ? 's' : '');
                badgeEl.className = 'badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-semibold';
            } else {
                badgeEl.textContent = qty + ' item' + (qty > 1 ? 's' : '') + ' selected';
                badgeEl.className = 'badge bg-white text-dark border px-2 py-1 fw-medium';
            }
        }
    }

    /* ========== ADDON PERSISTENCE (localStorage) ========== */
    const ADDON_STORAGE_KEY = 'addon_sel_' + productId;
    const ADDON_MEAS_KEY    = 'addon_meas2_' + productId;

    function showMeasurementForm(addonId, show) {
        // Find the .pd-addon-item containing this addon's checkbox
        const cb = document.querySelector(`.pd-addon-checkbox[value="${addonId}"]`);
        if (!cb) return;
        const itemEl = cb.closest('.pd-addon-item');
        if (!itemEl || itemEl.dataset.needsMeasurement !== '1') return;
        const measDiv = itemEl.querySelector('.pd-addon-measurements');
        if (measDiv) measDiv.style.display = show ? '' : 'none';
    }

    function saveAddonSelections() {
        // Save checked IDs
        const checked = [];
        document.querySelectorAll('.pd-addon-checkbox:checked').forEach(cb => {
            if (!cb.disabled) checked.push(String(cb.value));
        });
        localStorage.setItem(ADDON_STORAGE_KEY, JSON.stringify(checked));

        // Save measurements
        const meas = {};
        document.querySelectorAll('.pd-addon-meas-input').forEach(inp => {
            const addonId = inp.dataset.addonId;
            const field   = inp.dataset.measField;
            if (addonId && field && inp.value) {
                if (!meas[addonId]) meas[addonId] = {};
                meas[addonId][field] = inp.value;
            }
        });
        localStorage.setItem(ADDON_MEAS_KEY, JSON.stringify(meas));
    }

    function restoreAddonSelections() {
        try {
            const saved = JSON.parse(localStorage.getItem(ADDON_STORAGE_KEY) || '[]');
            if (!Array.isArray(saved)) return;
            document.querySelectorAll('.pd-addon-checkbox').forEach(cb => {
                if (!cb.disabled) {
                    const isChecked = saved.includes(String(cb.value));
                    cb.checked = isChecked;
                    showMeasurementForm(cb.value, isChecked);
                }
            });
        } catch(e) {}

        // Restore measurement values
        try {
            const meas = JSON.parse(localStorage.getItem(ADDON_MEAS_KEY) || '{}');
            document.querySelectorAll('.pd-addon-meas-input').forEach(inp => {
                const addonId = inp.dataset.addonId;
                const field   = inp.dataset.measField;
                if (addonId && field && meas[addonId] && meas[addonId][field]) {
                    inp.value = meas[addonId][field];
                }
            });
        } catch(e) {}
    }

    // Initialise on load
    function initAddons() {
        restoreAddonSelections();
        updateAddonsTotal();
        document.querySelectorAll('.pd-addon-checkbox').forEach(cb => {
            cb.addEventListener('change', () => {
                showMeasurementForm(cb.value, cb.checked);
                saveAddonSelections();
                updateAddonsTotal();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAddons);
    } else {
        initAddons();
    }

    /* ========== ADD TO CART ========== */
    async function addToCart(redirect = false) {
      const isCustom = isCustomSizeSelected();
      if (isCustom) {
        const hasMeas = checkHasCustomMeasurements();
        if (!hasMeas) {
          showToast('<i class="bi bi-rulers me-2"></i>Please fill your custom measurements first!', 'warning');
          openCustomMeasurementsModal();
          return;
        }
      }

      let qty  = parseInt(qtyInput?.value, 10) || 1;
      if (qty > 10) qty = 10;
      if (qty < 1) qty = 1;
      if (qtyInput) qtyInput.value = qty;
      const cart = JSON.parse(localStorage.getItem('cart')) || [];

      let variantInfo = '';
      if (isCustom) {
        const measSummary = getCustomMeasurementsSummary();
        const customLabel = measSummary ? ('Custom (' + measSummary + ')') : 'Custom';
        variantInfo = selectedColor ? (selectedColor + ' | ' + customLabel) : customLabel;
      } else {
        variantInfo = (selectedColor && selectedSize) 
          ? (selectedColor + ' | ' + selectedSize) 
          : (selectedColor || selectedSize || '');
      }

      const idx  = cart.findIndex(i => i.id == productId && i.color == selectedColor && i.size == selectedSize && i.variant_info == variantInfo);

      if (idx !== -1) {
        cart[idx].quantity += qty;
        // Update price in case it changed
        cart[idx].price = currentPrice;
        cart[idx].variant_info = variantInfo;
        if (isCustom) {
          cart[idx].custom_measurements = customMeasurements;
        }
      } else {
        cart.push({ 
          id: productId, 
          name: productName, 
          price: currentPrice, 
          image: productImage, 
          slug: productSlug, 
          quantity: qty, 
          color: selectedColor, 
          size: selectedSize,
          variant_info: variantInfo,
          custom_measurements: isCustom ? customMeasurements : null
        });
      }

      // Add selected add-on products
      let addedAddonsCount = 0;
      let savedMeas = {};
      try {
          savedMeas = JSON.parse(localStorage.getItem(ADDON_MEAS_KEY) || '{}');
      } catch(e) {}

      document.querySelectorAll('.pd-addon-checkbox:checked').forEach(cb => {
          const aid = String(cb.value);
          const addonObj = addonsData.find(a => String(a.id) === aid);
          if (addonObj) {
              const addonMeas = savedMeas[aid] || null;
              let variantInfo = 'Add-on';
              if (addonMeas && Object.keys(addonMeas).length > 0) {
                  const measParts = [];
                  for (const [k, v] of Object.entries(addonMeas)) {
                      if (v) measParts.push(k.charAt(0).toUpperCase() + k.slice(1) + ': ' + v + ' in');
                  }
                  if (measParts.length > 0) {
                      variantInfo = 'Add-on (' + measParts.join(', ') + ')';
                  }
              }

              const aIdx = cart.findIndex(i => String(i.id) === String(addonObj.id) && i.variant_info === variantInfo);
              if (aIdx !== -1) {
                  cart[aIdx].quantity += 1;
              } else {
                  cart.push({
                      id: addonObj.id,
                      name: addonObj.name,
                      price: addonObj.effective_price,
                      image: addonObj.image,
                      slug: addonObj.slug,
                      quantity: 1,
                      color: '',
                      size: '',
                      variant_info: variantInfo,
                      measurements: addonMeas
                  });
              }
              addedAddonsCount++;
          }
      });

      localStorage.setItem('cart', JSON.stringify(cart));
      updateCartBadge();

      <?php if (isset($_SESSION['user_id'])): ?>
      // If logged in, we MUST sync the cart to the DB before redirecting to checkout
      try {
        await fetch('cart.php?action=sync_cart', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ cart: cart })
        });
        localStorage.removeItem('cart'); // Clear local after syncing to DB
      } catch (e) {
        console.error('Failed to sync cart:', e);
      }
      <?php endif; ?>

      if (redirect) {
        window.location.href = 'checkout.php';
      } else {
        const btn = document.getElementById('btn-add-to-cart');
        if (btn) {
          const orig = btn.innerHTML;
          btn.innerHTML = '<i class="bi bi-check2-circle"></i> Added!';
          btn.style.background = 'linear-gradient(135deg, #198754, #146c43)';
          setTimeout(() => { btn.innerHTML = orig; btn.style.background = ''; }, 2000);
        }
        let msg = '<i class="bi bi-bag-check-fill me-2"></i>Added to cart successfully!';
        if (addedAddonsCount > 0) {
          msg = '<i class="bi bi-bag-check-fill me-2"></i>Added main product + ' + addedAddonsCount + ' add-on' + (addedAddonsCount > 1 ? 's' : '') + ' to cart!';
        }
        showToast(msg, 'success');
      }
    }

    document.getElementById('btn-add-to-cart')?.addEventListener('click', e => { e.preventDefault(); addToCart(false); });
    document.getElementById('btn-buy-now')?.addEventListener('click', e => { e.preventDefault(); addToCart(true); });

    /* ========== WISHLIST ========== */
    const wishlistBtn = document.getElementById('btn-add-to-wishlist');

    function setWishlistState(active) {
      if (!wishlistBtn) return;
      const icon = wishlistBtn.querySelector('i');
      if (active) {
        icon.classList.replace('bi-heart', 'bi-heart-fill');
        wishlistBtn.classList.add('wishlisted');
      } else {
        icon.classList.replace('bi-heart-fill', 'bi-heart');
        wishlistBtn.classList.remove('wishlisted');
      }
    }

    // Check on load
    const wlInit = JSON.parse(localStorage.getItem('wishlist')) || [];
    setWishlistState(wlInit.some(i => i.id == productId));

    wishlistBtn?.addEventListener('click', function (e) {
      e.preventDefault();
      const wl = JSON.parse(localStorage.getItem('wishlist')) || [];
      const existsAt = wl.findIndex(i => i.id == productId);

      if (existsAt === -1) {
        wl.push({ id: productId, name: productName, price: currentPrice, image: productImage, slug: productSlug, color: selectedColor, size: selectedSize });
        localStorage.setItem('wishlist', JSON.stringify(wl));
        setWishlistState(true);
        showToast('<i class="bi bi-heart-fill me-2 text-danger"></i>Added to wishlist!', 'success');
      } else {
        wl.splice(existsAt, 1);
        localStorage.setItem('wishlist', JSON.stringify(wl));
        setWishlistState(false);
        showToast('Removed from wishlist', 'secondary');
      }

      const wb = document.querySelector('.wishlist-badge');
      if (wb) {
        const newWl = JSON.parse(localStorage.getItem('wishlist')) || [];
        wb.textContent = newWl.length;
        wb.style.display = newWl.length > 0 ? 'inline' : 'none';
      }
    });

  });
  </script>

</body>
</html>