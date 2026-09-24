<?php
session_start();
require_once 'db_config.php';

// Require the user to be logged in to view this page
if (!isset($_SESSION['user_id'])) {
  // Parse current request to separate path and query
  $request_uri = $_SERVER['REQUEST_URI']; // e.g. /silky/silky/order-confirmation?order=...
  $parsed = parse_url($request_uri);
  $path = $parsed['path'] ?? '/';
  $query = $parsed['query'] ?? '';

  // Normalize duplicate first path segment (e.g. /silky/silky/... -> /silky/...)
  $trimmed = ltrim($path, '/');
  $path_parts = $trimmed === '' ? [] : explode('/', $trimmed);
  if (count($path_parts) > 1 && $path_parts[0] === $path_parts[1]) {
    array_splice($path_parts, 0, 1);
  }
  $normalized_path = '/' . implode('/', $path_parts);

  // Rebuild target including query string if any
  $redirect_target = $normalized_path . ($query !== '' ? '?' . $query : '');

  // Save for server-side use as well
  $_SESSION['redirect_after_login'] = $redirect_target;

  // Compute site base prefix (first segment) to build absolute login path: /{site}/login.php
  $script_name = $_SERVER['SCRIPT_NAME'];
  $script_trim = ltrim($script_name, '/');
  $script_parts = $script_trim === '' ? [] : explode('/', $script_trim);
  $base_prefix = '';
  if (isset($script_parts[0]) && $script_parts[0] !== '') {
    $base_prefix = '/' . $script_parts[0];
  }

  // Redirect to login with encoded redirect parameter (slashes encoded as %2F)
  $login_url = $base_prefix . '/login.php?redirect=' . rawurlencode($redirect_target);
  header('Location: ' . $login_url);
  exit();
}

// Check if order number is provided
if (!isset($_GET['order']) || empty($_GET['order'])) {
    header('Location: products.php');
    exit();
}

$order_number = mysqli_real_escape_string($conn, $_GET['order']);

// Fetch order details
$order_sql = "SELECT * FROM orders WHERE order_number = '$order_number'";
$order_result = mysqli_query($conn, $order_sql);

if (!$order_result || mysqli_num_rows($order_result) == 0) {
    header('Location: products.php');
    exit();
}

$order = mysqli_fetch_assoc($order_result);

// Verify the order belongs to the currently logged-in user
if ($order['user_id'] != $_SESSION['user_id']) {
  header('Location: products.php');
  exit();
}

// Fetch order items
$items_sql = "SELECT * FROM order_items WHERE order_id = " . $order['id'];
$items_result = mysqli_query($conn, $items_sql);
$order_items = [];
while ($item = mysqli_fetch_assoc($items_result)) {
    $order_items[] = $item;
}

// Fetch tax setting to determine if tax is blank/included
$tax_setting_query = mysqli_query($conn, "SELECT setting_value FROM general_settings WHERE setting_key = 'tax_percentage' LIMIT 1");
$tax_setting_val = ($tax_setting_query && $trow = mysqli_fetch_assoc($tax_setting_query)) ? trim($trow['setting_value'] ?? '') : '';
$tax_is_blank = ($tax_setting_val === '' || floatval($tax_setting_val) <= 0);

// Parse shipping address
$shipping_address = json_decode($order['shipping_address'], true);

// Clear cart sync flag
if (isset($_SESSION['cart_sync_needed'])) {
    unset($_SESSION['cart_sync_needed']);
}

// --- Suggested Products Based on User's Previous Orders & Favorite Category ---
$order_user_id = intval($order['user_id'] ?? ($_SESSION['user_id'] ?? 0));
$fav_category_id = 0;
$fav_category_name = '';
$suggested_products = [];

// Track products in current order to prefer recommending items not yet bought in this order
$current_order_product_ids = [];
foreach ($order_items as $oi) {
    if (!empty($oi['product_id'])) {
        $current_order_product_ids[] = intval($oi['product_id']);
    }
}

if ($order_user_id > 0) {
    // Find which category the user likes most based on user's order history
    $fav_cat_sql = "SELECT p.category_id, c.name as category_name, SUM(oi.quantity) as total_qty, COUNT(oi.id) as item_count 
                    FROM orders o 
                    JOIN order_items oi ON o.id = oi.order_id 
                    JOIN products p ON oi.product_id = p.id 
                    JOIN categories c ON p.category_id = c.id 
                    WHERE o.user_id = $order_user_id AND p.category_id IS NOT NULL AND p.category_id > 0 AND c.status = 'active'
                    GROUP BY p.category_id 
                    ORDER BY total_qty DESC, item_count DESC 
                    LIMIT 1";
    $fav_cat_res = mysqli_query($conn, $fav_cat_sql);
    if ($fav_cat_res && $fc_row = mysqli_fetch_assoc($fav_cat_res)) {
        $fav_category_id = intval($fc_row['category_id']);
        $fav_category_name = $fc_row['category_name'];
    }
}

// Fallback: If user has no previous category data, pick the most popular category overall on the store
if ($fav_category_id <= 0) {
    $pop_cat_sql = "SELECT p.category_id, c.name as category_name, COUNT(oi.id) as order_count 
                    FROM order_items oi 
                    JOIN products p ON oi.product_id = p.id 
                    JOIN categories c ON p.category_id = c.id 
                    WHERE p.category_id IS NOT NULL AND p.category_id > 0 AND c.status = 'active'
                    GROUP BY p.category_id 
                    ORDER BY order_count DESC 
                    LIMIT 1";
    $pop_cat_res = mysqli_query($conn, $pop_cat_sql);
    if ($pop_cat_res && $pc_row = mysqli_fetch_assoc($pop_cat_res)) {
        $fav_category_id = intval($pc_row['category_id']);
        $fav_category_name = $pc_row['category_name'];
    } else {
        $any_cat_res = mysqli_query($conn, "SELECT id, name FROM categories WHERE status = 'active' LIMIT 1");
        if ($any_cat_res && $ac_row = mysqli_fetch_assoc($any_cat_res)) {
            $fav_category_id = intval($ac_row['id']);
            $fav_category_name = $ac_row['name'];
        }
    }
}

// Select 3 random products from this favorite category
if ($fav_category_id > 0) {
    // 1. Prefer products from this category not in the current order
    $exclude_sql = !empty($current_order_product_ids) ? " AND p.id NOT IN (" . implode(',', $current_order_product_ids) . ") " : "";

    $prod_sql = "SELECT p.*, c.name as category_name,
                 (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND status = 'approved') as review_count,
                 (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') as avg_rating
                 FROM products p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 LEFT JOIN stock stk ON p.id = stk.product_id 
                 WHERE p.status = 'active' AND p.category_id = $fav_category_id $exclude_sql 
                 ORDER BY RAND() 
                 LIMIT 3";
    $prod_res = mysqli_query($conn, $prod_sql);
    if ($prod_res) {
        while ($p_row = mysqli_fetch_assoc($prod_res)) {
            $suggested_products[] = $p_row;
        }
    }

    // 2. If less than 3, allow products from this category that might have been in the current order
    if (count($suggested_products) < 3) {
        $selected_ids = array_map(function($p) { return intval($p['id']); }, $suggested_products);
        $not_in_sql = !empty($selected_ids) ? " AND p.id NOT IN (" . implode(',', $selected_ids) . ") " : "";
        $needed = 3 - count($suggested_products);

        $more_sql = "SELECT p.*, c.name as category_name,
                     (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND status = 'approved') as review_count,
                     (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') as avg_rating
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     LEFT JOIN stock stk ON p.id = stk.product_id 
                     WHERE p.status = 'active' AND p.category_id = $fav_category_id $not_in_sql 
                     ORDER BY RAND() 
                     LIMIT $needed";
        $more_res = mysqli_query($conn, $more_sql);
        if ($more_res) {
            while ($mp_row = mysqli_fetch_assoc($more_res)) {
                $suggested_products[] = $mp_row;
            }
        }
    }

    // 3. If category has fewer than 3 total products, fill remaining spots with random active products
    if (count($suggested_products) < 3) {
        $selected_ids = array_map(function($p) { return intval($p['id']); }, $suggested_products);
        $not_in_sql = !empty($selected_ids) ? " AND p.id NOT IN (" . implode(',', $selected_ids) . ") " : "";
        $needed = 3 - count($suggested_products);

        $fill_sql = "SELECT p.*, c.name as category_name,
                     (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND status = 'approved') as review_count,
                     (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') as avg_rating
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     LEFT JOIN stock stk ON p.id = stk.product_id 
                     WHERE p.status = 'active' $not_in_sql 
                     ORDER BY RAND() 
                     LIMIT $needed";
        $fill_res = mysqli_query($conn, $fill_sql);
        if ($fill_res) {
            while ($fp_row = mysqli_fetch_assoc($fill_res)) {
                $suggested_products[] = $fp_row;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Order Confirmation - Silky Saree</title>
  <meta name="description" content="">
  <meta name="keywords" content="">

  <!-- Favicons -->
  <link href="assets/img/favicon/favicon.ico" rel="icon" type="image/x-icon">
  <link href="assets/img/favicon/favicon-32x32.png" rel="icon" type="image/png" sizes="32x32">
  <link href="assets/img/favicon/favicon-16x16.png" rel="icon" type="image/png" sizes="16x16">
  <link href="assets/img/favicon/apple-touch-icon.png" rel="apple-touch-icon" sizes="180x180">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">

  <!-- Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">

  <style>
    .order-confirmation-page {
      background: #f8f9fa;
    }
    
    .success-hero {
      background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 50%, #f8f9fa 100%);
      padding: 4rem 0;
      color: #333;
      margin-bottom: 3rem;
    }
    
    .success-animation {
      margin: 2rem auto;
      text-align: center;
    }
    
    .checkmark {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      display: block;
      stroke-width: 3;
      stroke: #fff;
      stroke-miterlimit: 10;
      margin: 0 auto 2rem;
      background: linear-gradient(135deg, #0e2187 0%, #97c51d 100%);
      backdrop-filter: blur(10px);
      box-shadow: 0 8px 32px rgba(14, 33, 135, 0.3);
      animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
    }
    
    .checkmark__circle {
      stroke-dasharray: 166;
      stroke-dashoffset: 166;
      stroke-width: 3;
      stroke-miterlimit: 10;
      stroke: #fff;
      fill: none;
      animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
    }
    
    .checkmark__check {
      transform-origin: 50% 50%;
      stroke-dasharray: 48;
      stroke-dashoffset: 48;
      animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
    }
    
    @keyframes stroke {
      100% { stroke-dashoffset: 0; }
    }
    
    @keyframes scale {
      0%, 100% { transform: none; }
      50% { transform: scale3d(1.1, 1.1, 1); }
    }
    
    @keyframes fill {
      100% { box-shadow: inset 0px 0px 0px 60px rgba(255, 255, 255, 0.3); }
    }
    
    .order-number-badge {
      background: linear-gradient(135deg, #0e2187 0%, #97c51d 100%);
      backdrop-filter: blur(10px);
      padding: 1rem 2rem;
      border-radius: 50px;
      display: inline-block;
      font-size: 1.1rem;
      font-weight: 600;
      margin-top: 1rem;
      border: none;
      color: white;
      box-shadow: 0 4px 15px rgba(14, 33, 135, 0.3);
    }
    
    .confirmation-card {
      background: white;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      border: none;
      overflow: hidden;
      margin-bottom: 2rem;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .confirmation-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    }
    
    .card-header-custom {
      background: white;
      color: #0e2187;
      padding: 1.5rem;
      border: none;
      border-bottom: 2px solid #0e2187;
    }
    
    .card-header-custom h4,
    .card-header-custom h5 {
      margin: 0;
      font-weight: 600;
      color: #0e2187;
    }
    
    .card-header-custom i {
      color: #0e2187;
    }
    
    .order-item-row {
      padding: 1rem;
      border-bottom: 1px solid #f0f0f0;
      transition: background 0.2s ease;
    }
    
    .order-item-row .badge {
      white-space: normal !important;
      word-break: break-word;
      text-align: left;
    }
    
    .order-item-row:hover {
      background: #f8f9fa;
    }
    
    .order-item-row:last-child {
      border-bottom: none;
    }
    
    .order-summary-row {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #f0f0f0;
    }
    
    .order-summary-row.total {
      background: linear-gradient(135deg, rgba(14, 33, 135, 0.1) 0%, rgba(151, 197, 29, 0.1) 100%);
      font-weight: 700;
      font-size: 1.2rem;
      border: none;
      margin-top: 0.5rem;
      border-radius: 8px;
    }
    
    .info-card {
      background: white;
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      margin-bottom: 1.5rem;
      border: 1px solid #f0f0f0;
    }
    
    .info-card h5 {
      color: #0e2187;
      font-weight: 600;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid #f0f0f0;
    }
    
    .info-item {
      margin-bottom: 1.5rem;
    }
    
    .info-item:last-child {
      margin-bottom: 0;
    }
    
    .info-item strong {
      display: block;
      color: #666;
      font-size: 0.875rem;
      margin-bottom: 0.5rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .info-item .value {
      color: #333;
      font-size: 1rem;
      font-weight: 500;
    }
    
    .status-badge {
      padding: 0.5rem 1rem;
      border-radius: 50px;
      font-weight: 600;
      font-size: 0.875rem;
      display: inline-block;
    }
    
    .status-badge.success {
      background: #d4edda;
      color: #155724;
    }
    
    .status-badge.warning {
      background: #fff3cd;
      color: #856404;
    }
    
    .status-badge.info {
      background: #d1ecf1;
      color: #0c5460;
    }
    
    .next-steps-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    
    .next-steps-list li {
      padding: 1rem;
      background: #f8f9fa;
      border-radius: 8px;
      margin-bottom: 0.75rem;
      display: flex;
      align-items: start;
      transition: all 0.3s ease;
    }
    
    .next-steps-list li:hover {
      background: #e9ecef;
      transform: translateX(5px);
    }
    
    .next-steps-list li::before {
      content: "✓";
      display: inline-block;
      width: 24px;
      height: 24px;
      background: linear-gradient(135deg, #0e2187 0%, #97c51d 100%);
      color: white;
      border-radius: 50%;
      text-align: center;
      line-height: 24px;
      margin-right: 1rem;
      flex-shrink: 0;
      font-weight: bold;
      font-size: 0.875rem;
    }
    
    .action-buttons .btn {
      border-radius: 50px;
      padding: 0.875rem 2rem;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .action-buttons .btn-primary {
      background: linear-gradient(135deg, #0e2187 0%, #97c51d 100%);
      border: none;
      box-shadow: 0 4px 15px rgba(14, 33, 135, 0.4);
    }
    
    .action-buttons .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(14, 33, 135, 0.5);
    }
    
    .action-buttons .btn-outline-secondary {
      border: 2px solid #0e2187;
      color: #0e2187;
    }
    
    .action-buttons .btn-outline-secondary:hover {
      background: #0e2187;
      color: white;
      transform: translateY(-2px);
    }
    
    .btn-invoice {
      background: white;
      color: #0e2187;
      border: 2px solid #0e2187;
      border-radius: 50px;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .btn-invoice:hover {
      background: #0e2187;
      color: white;
      border-color: #0e2187;
    }
    
    .btn-invoice i {
      transition: transform 0.3s ease;
    }
    
    .btn-invoice:hover i {
      transform: scale(1.1);
    }
    
    .shipping-address {
      background: #f8f9fa;
      padding: 1.5rem;
      border-radius: 12px;
    }
    
    .shipping-address address {
      margin: 0;
      line-height: 1.8;
      color: #555;
    }
    
    .shipping-address strong {
      color: #333;
      font-size: 1.1rem;
      display: block;
      margin-bottom: 0.5rem;
    }
    
    @media (max-width: 768px) {
      .success-hero {
        padding: 2rem 0;
      }
      
      .checkmark {
        width: 80px;
        height: 80px;
      }
      
      .order-number-badge {
        font-size: 0.9rem;
        padding: 0.75rem 1.5rem;
      }
    }

    /* Suggested Products Section - Same styling as collections.php & products.php */
    .suggested-products-wrap {
      margin-top: 3.5rem;
      padding-top: 2.5rem;
      border-top: 1.5px dashed #e2e8f0;
    }

    .custom-glass-btn {
      display: inline-block !important;
      background: linear-gradient(90deg, rgba(74, 120, 163, 0.75) 0%, rgba(144, 175, 87, 0.75) 100%) !important;
      backdrop-filter: blur(8px) !important;
      -webkit-backdrop-filter: blur(8px) !important;
      border: 2px solid rgba(74, 120, 163, 0.8) !important;
      color: #ffffff !important;
      border-radius: 50px !important;
      padding: 12px 28px !important;
      font-weight: 700 !important;
      font-size: 0.95rem !important;
      text-transform: uppercase !important;
      letter-spacing: 1px !important;
      position: relative !important;
      overflow: hidden !important;
      white-space: nowrap !important;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2) !important;
    }

    /* Shine Effect */
    .custom-glass-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 50%;
      height: 100%;
      background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.6) 50%, rgba(255,255,255,0) 100%);
      transform: skewX(-25deg);
      transition: left 0.7s ease;
    }

    .bs3d-card:hover .custom-glass-btn::before {
      left: 150%;
    }
  </style>
</head>

<body class="order-confirmation-page">

  <header id="header" class="header sticky-top">
    <!-- Top Bar -->
    <?php include './topbar.php'; ?>
    <!-- Main Header -->
    <?php include './main-header.php'; ?>
  </header>

  <main class="main">

    <!-- Success Hero Section -->
    <div class="success-hero">
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-lg-8 text-center" data-aos="fade-up">
            <div class="success-animation">
              <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
                <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
              </svg>
            </div>
            <h1 class="mb-3 display-4" style="color: #0e2187;">Thank You for Your Order!</h1>
            <p class="lead mb-4" style="color: #555;">Your order has been successfully placed and is being processed.</p>
            <div class="order-number-badge">
              <i class="bi bi-receipt me-2"></i>Order #<?php echo htmlspecialchars($order['order_number']); ?>
            </div>
            <p class="mt-4 mb-0" style="color: #666;">
              <i class="bi bi-envelope-check me-2"></i>Confirmation sent to <strong style="color: #0e2187;"><?php echo htmlspecialchars($shipping_address['email']); ?></strong>
            </p>
          </div>
        </div>
      </div>
    </div><!-- End Success Hero -->

    <!-- Order Confirmation Section -->
    <section id="order-confirmation" class="order-confirmation section py-5">
      <div class="container" data-aos="fade-up">


        <div class="row">
          <!-- Order Details -->
          <div class="col-lg-8 mb-4">
            <div class="confirmation-card" data-aos="fade-up">
              <div class="card-header-custom">
                <h4><i class="bi bi-bag-check me-2"></i>Order Items</h4>
              </div>
              <div class="card-body p-0">
                <?php foreach ($order_items as $item): ?>
                <div class="order-item-row">
                  <div class="row align-items-start gy-2">
                    <div class="col-12 col-md-6">
                      <strong class="d-block mb-1"><?php echo htmlspecialchars($item['product_name']); ?></strong>
                      <?php if (!empty($item['variant_info'])): 
                        $vInfo = trim($item['variant_info']);
                        $isAddon = (stripos($vInfo, 'add-on') !== false || stripos($vInfo, 'addon') !== false);
                        $isCustom = (stripos($vInfo, 'custom') !== false);
                        $cMeasurements = [];
                        $cNotes = '';
                        $colorPart = '';
                        $sizePart = '';

                        if (preg_match('/\(([^)]+)\)/', $vInfo, $pm)) {
                          $parenStr = trim($pm[1]);
                          $baseV = trim(str_replace($pm[0], '', $vInfo));
                          foreach (preg_split('/[•\x{2022},;|\n\r]+/u', $parenStr) as $pr) {
                            $pr = trim($pr);
                            if ($pr === '') continue;
                            if (strpos($pr, ':') !== false) {
                              list($k, $v) = explode(':', $pr, 2);
                              $k = trim($k);
                              $v = trim($v);
                              if (strtolower($k) === 'notes' || strtolower($k) === 'special instructions') {
                                $cNotes = $v;
                              } else {
                                $cMeasurements[ucfirst($k)] = $v;
                              }
                            } else {
                              if ($cNotes === '') $cNotes = $pr;
                              else $cNotes .= ', ' . $pr;
                            }
                          }
                        } else {
                          $baseV = $vInfo;
                        }

                        // Fallback: detect measurements without parens
                        if (empty($cMeasurements) && preg_match('/(bust|waist|hips?|chest|length|shoulder|sleeve|armhole|front\s*neck)\s*:/i', $vInfo)) {
                          foreach (preg_split('/[•\x{2022},;|\n\r]+/u', $vInfo) as $pr) {
                            $pr = trim($pr);
                            if ($pr === '') continue;
                            if (strpos($pr, ':') !== false) {
                              list($k, $v) = explode(':', $pr, 2);
                              $lowerK = strtolower(trim($k));
                              if ($lowerK === 'notes') $cNotes = trim($v);
                              elseif (in_array($lowerK, ['bust', 'chest', 'waist', 'hip', 'hips', 'length', 'shoulder', 'sleeve', 'armhole', 'front neck', 'fit'])) {
                                $cMeasurements[ucfirst(trim($k))] = trim($v);
                              }
                            }
                          }
                        }

                        $baseV = trim(preg_replace('/\s*\|\s*\|\s*/', ' | ', $baseV), " |");
                        if (strpos($baseV, '|') !== false) {
                          $bParts = array_map('trim', explode('|', $baseV));
                          $colorPart = $bParts[0] ?? '';
                          $sizePart = $bParts[1] ?? '';
                        } else {
                          if ($isCustom) $sizePart = 'Custom';
                          elseif (!$isAddon) $sizePart = $baseV;
                        }
                      ?>
                        <div class="mb-2">
                          <div class="d-flex flex-wrap gap-1 align-items-center mb-1">
                            <?php if ($isAddon): ?>
                              <span class="badge bg-warning-subtle text-dark border border-warning-subtle" style="font-size: 11px;"><i class="fas fa-puzzle-piece text-warning me-1"></i>Add-on</span>
                            <?php elseif ($isCustom): ?>
                              <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" style="font-size: 11px;"><i class="bi bi-scissors me-1"></i>Custom Tailoring</span>
                            <?php endif; ?>
                            <?php if ($colorPart !== ''): ?>
                              <span class="badge bg-light text-dark border" style="font-size: 11px;"><i class="bi bi-palette me-1 text-muted"></i><?php echo htmlspecialchars($colorPart); ?></span>
                            <?php endif; ?>
                            <?php if ($sizePart !== '' && strtolower($sizePart) !== 'custom' && strtolower($sizePart) !== 'add-on'): ?>
                              <span class="badge bg-light text-dark border" style="font-size: 11px;"><?php echo htmlspecialchars($sizePart); ?></span>
                            <?php endif; ?>
                          </div>

                          <?php if (!empty($cMeasurements) || !empty($cNotes)): ?>
                            <div class="p-2 rounded bg-light border" style="font-size: 11px; max-width: 100%; white-space: normal; word-break: break-word;">
                              <div class="text-secondary fw-semibold mb-1"><i class="bi bi-rulers text-primary me-1"></i>Measurements (Inches):</div>
                              <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($cMeasurements as $mk => $mv): ?>
                                  <span class="badge bg-white text-dark border px-2 py-1 shadow-sm" style="font-size: 10.5px; font-weight: normal; white-space: normal;">
                                    <span class="text-muted"><?php echo htmlspecialchars($mk); ?>:</span> <strong><?php echo htmlspecialchars($mv); ?></strong>
                                  </span>
                                <?php endforeach; ?>
                              </div>
                              <?php if (!empty($cNotes)): ?>
                                <div class="text-muted mt-1 pt-1 border-top" style="font-size: 10.5px;">
                                  <i class="bi bi-chat-left-text me-1 text-info"></i><strong>Notes:</strong> <?php echo htmlspecialchars($cNotes); ?>
                                </div>
                              <?php endif; ?>
                            </div>
                          <?php elseif (!$isAddon && !$isCustom && empty($colorPart) && empty($sizePart)): ?>
                            <span class="badge bg-light text-dark border text-wrap text-start" style="font-size: 11px; max-width: 100%; white-space: normal; word-break: break-word;"><i class="bi bi-tag me-1"></i><?php echo htmlspecialchars($vInfo); ?></span>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                      <small class="text-muted d-block mt-1">Qty: <?php echo intval($item['quantity']); ?></small>
                    </div>
                    <div class="col-6 col-md-3 text-md-center">
                      <small class="text-muted d-block d-md-none">Unit Price</small>
                      <span class="text-muted">₹<?php echo number_format($item['product_price'], 2); ?></span>
                    </div>
                    <div class="col-6 col-md-3 text-end">
                      <small class="text-muted d-block d-md-none">Subtotal</small>
                      <strong class="text-primary fs-6">₹<?php echo number_format($item['subtotal'], 2); ?></strong>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Order Summary -->
                <div class="p-3 bg-light">
                  <div class="order-summary-row d-flex justify-content-between">
                    <span>Subtotal</span>
                    <span>₹<?php echo number_format($order['subtotal'], 2); ?></span>
                  </div>
                  <?php if ($order['shipping_cost'] > 0): ?>
                  <div class="order-summary-row d-flex justify-content-between">
                    <span>Shipping</span>
                    <span>₹<?php echo number_format($order['shipping_cost'], 2); ?></span>
                  </div>
                  <?php endif; ?>
                  <?php if (isset($order['discount_amount']) && $order['discount_amount'] > 0): ?>
                  <div class="order-summary-row d-flex justify-content-between text-danger">
                    <span>Discount</span>
                    <span>-₹<?php echo number_format($order['discount_amount'], 2); ?></span>
                  </div>
                  <?php endif; ?>
                  <div class="order-summary-row d-flex justify-content-between align-items-center">
                    <span>Tax</span>
                    <span>
                      <?php if ($tax_is_blank || floatval($order['tax_amount'] ?? 0) <= 0): ?>
                        <span class="text-success fw-semibold">Included</span>
                      <?php else: ?>
                        ₹<?php echo number_format($order['tax_amount'], 2); ?>
                      <?php endif; ?>
                    </span>
                  </div>
                  <div class="order-summary-row total d-flex justify-content-between">
                    <span>Total</span>
                    <span>₹<?php echo number_format($order['total_amount'], 2); ?></span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Shipping Address Card -->
            <div class="confirmation-card" data-aos="fade-up" data-aos-delay="100">
              <div class="card-header-custom">
                <h5><i class="bi bi-truck me-2"></i>Delivery Address</h5>
              </div>
              <div class="card-body">
                <div class="shipping-address">
                  <address>
                    <strong><?php echo htmlspecialchars($shipping_address['first_name'] . ' ' . $shipping_address['last_name']); ?></strong><br>
                    <?php echo htmlspecialchars($shipping_address['street_address']); ?><br>
                    <?php if (!empty($shipping_address['apartment'])): ?>
                      <?php echo htmlspecialchars($shipping_address['apartment']); ?><br>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($shipping_address['city'] . ', ' . $shipping_address['state'] . ' ' . $shipping_address['zip_code']); ?><br>
                    <?php echo htmlspecialchars($shipping_address['country']); ?>
                  </address>
                  <hr class="my-3">
                  <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-telephone-fill me-2 text-primary"></i>
                    <span><?php echo htmlspecialchars($shipping_address['phone']); ?></span>
                  </div>
                  <div class="d-flex align-items-center">
                    <i class="bi bi-envelope-fill me-2 text-primary"></i>
                    <span><?php echo htmlspecialchars($shipping_address['email']); ?></span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Order Information Sidebar -->
          <div class="col-lg-4">
            <!-- Order Info Card -->
            <div class="info-card" data-aos="fade-up">
              <h5><i class="bi bi-info-circle me-2"></i>Order Information</h5>
              
              <div class="info-item">
                <strong>Order Number</strong>
                <div class="value text-primary">#<?php echo htmlspecialchars($order['order_number']); ?></div>
              </div>
              
              <div class="info-item">
                <strong>Order Date</strong>
                <div class="value"><?php echo date('F j, Y', strtotime($order['created_at'])); ?></div>
              </div>
              
              <div class="info-item">
                <strong>Payment Method</strong>
                <div class="value">
                  <?php 
                  $payment_labels = [
                    'cod' => 'Cash on Delivery',
                    'stripe' => 'Credit/Debit Card',
                    'paypal' => 'PayPal',
                    'razorpay' => 'Online Payment',
                    'bank_transfer' => 'Bank Transfer'
                  ];
                  echo $payment_labels[$order['payment_method']] ?? ucfirst($order['payment_method']);
                  ?>
                </div>
              </div>
              
              <div class="info-item">
                <strong>Payment Status</strong>
                <div class="value">
                  <span class="status-badge <?php echo $order['payment_status'] == 'paid' ? 'success' : 'warning'; ?>">
                    <?php echo ucfirst($order['payment_status']); ?>
                  </span>
                </div>
              </div>
              
              <div class="info-item">
                <strong>Order Status</strong>
                <div class="value">
                  <span class="status-badge info"><?php echo ucfirst($order['order_status']); ?></span>
                </div>
              </div>
              
              <div class="info-item">
                <button type="button" class="btn btn-invoice w-100" data-bs-toggle="modal" data-bs-target="#invoiceModal">
                  <i class="bi bi-file-pdf me-2"></i>View Invoice
                </button>
              </div>
            </div>

            <!-- What's Next Card -->
            <div class="info-card" data-aos="fade-up" data-aos-delay="100">
              <h5><i class="bi bi-list-check me-2"></i>What's Next?</h5>
              <ul class="next-steps-list">
                <li>You'll receive an order confirmation email shortly</li>
                <li>We'll send you shipping updates via email</li>
                <li>Track your order status in your account</li>
                <li>Expect delivery within 5-7 business days</li>
              </ul>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons d-grid gap-3" data-aos="fade-up" data-aos-delay="200">
              <a href="products.php" class="btn btn-primary btn-lg">
                <i class="bi bi-arrow-left me-2"></i>Continue Shopping
              </a>
              <?php if (isset($_SESSION['user_id'])): ?>
              <a href="account.php" class="btn btn-outline-secondary btn-lg">
                <i class="bi bi-person me-2"></i>View My Orders
              </a>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Suggested Products Section (Based on User's Previous Orders & Most Liked Category - Exact same card as collections.php & products.php) -->
        <?php if (!empty($suggested_products)): ?>
        <div class="suggested-products-wrap" data-aos="fade-up">
          <div class="section-title text-center mb-4" data-aos="fade-up">
            <h2 style="font-family: 'Montserrat', sans-serif; font-weight: 700; color: #0e2187;">Discover Your Next Favorite</h2>
            <p class="text-muted">Because you love <strong><?php echo htmlspecialchars($fav_category_name); ?></strong>, take a look before it disappears.</p>
          </div>

          <div class="row gy-4">
            <?php foreach ($suggested_products as $index => $product): 
              // Process main image exactly as in collections.php
              $mainImage = 'assets/img/product/saree1.png';
              if (!empty($product['image'])) {
                  $imageData = json_decode($product['image'], true);
                  if (is_array($imageData) && !empty($imageData[0])) {
                      $img_name = $imageData[0];
                  } else {
                      $images_arr = array_map('trim', explode(',', $product['image']));
                      $img_name = $images_arr[0] ?? '';
                  }
                  
                  if (!empty($img_name)) {
                      $possiblePaths = [
                          'uploads/products/' . $img_name,
                          'uploads/' . $img_name,
                          'admin/uploads/' . $img_name,
                          'assets/img/product/' . $img_name,
                          $img_name
                      ];
                      foreach ($possiblePaths as $path) {
                          if (file_exists(__DIR__ . '/' . $path)) {
                              $mainImage = $path;
                              break;
                          } elseif (file_exists($path)) {
                              $mainImage = $path;
                              break;
                          }
                      }
                  }
              }

              $product_slug = !empty($product['slug']) ? $product['slug'] : 'product-' . $product['id'];
              $product_link = 'product-details.php?slug=' . htmlspecialchars($product_slug);
            ?>
              <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo 100 + ($index * 100); ?>">
                <div class="bs3d-card product-item h-100"
                     data-product-name="<?php echo htmlspecialchars($product['name']); ?>" 
                     data-product-price="<?php echo htmlspecialchars($product['price']); ?>" 
                     data-product-image="<?php echo htmlspecialchars($mainImage); ?>">
                  <div class="bs3d-card-img">
                    <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.src='assets/img/product/saree1.png'">

                    <div class="bs3d-overlay">
                      <div class="bs3d-overlay-actions">
                        <button class="bs3d-action-btn wishlist-btn" data-product-id="<?php echo $product['id']; ?>" title="Wishlist">
                          <i class="bi bi-heart"></i>
                        </button>
                        <button class="bs3d-action-btn add-to-cart" data-product-id="<?php echo $product['id']; ?>" title="Add to Cart">
                          <i class="bi bi-cart-plus"></i>
                        </button>
                        <a href="<?php echo $product_link; ?>" class="bs3d-action-btn" title="View Details">
                          <i class="bi bi-eye"></i>
                        </a>
                      </div>
                      <a href="<?php echo $product_link; ?>" class="bs3d-shop-btn custom-glass-btn" style="text-decoration: none;">SHOP NOW</a>
                    </div>
                  </div>
                  <div class="bs3d-card-info">
                    <span class="bs3d-category"><?php echo htmlspecialchars($product['category_name'] ?? $fav_category_name); ?></span>
                    <h4 class="bs3d-name">
                      <a href="<?php echo $product_link; ?>" style="color: inherit; text-decoration: none;">
                        <?php echo htmlspecialchars($product['name']); ?>
                      </a>
                    </h4>
                    <div class="bs3d-stars">
                      <?php 
                      $reviewCount = isset($product['review_count']) ? intval($product['review_count']) : 0;
                      $rating = ($reviewCount > 0 && !empty($product['avg_rating'])) ? round($product['avg_rating'], 1) : 0;
                      for ($i = 1; $i <= 5; $i++) {
                          if ($i <= $rating) {
                              echo '<i class="bi bi-star-fill"></i>';
                          } elseif ($i - 0.5 <= $rating) {
                              echo '<i class="bi bi-star-half"></i>';
                          } else {
                              echo '<i class="bi bi-star"></i>';
                          }
                      }
                      ?>
                      <span>(<?php echo $reviewCount; ?>)</span>
                    </div>
                    <div class="bs3d-price">
                      <?php if (!empty($product['compare_price']) && $product['compare_price'] > $product['price']): ?>
                        ₹<?php echo number_format($product['price'], 2); ?> <span class="bs3d-old-price" style="text-decoration: line-through; font-size: 0.85em; color: #888; margin-left: 5px;">₹<?php echo number_format($product['compare_price'], 2); ?></span>
                      <?php elseif (!empty($product['discount_price'])): ?>
                        ₹<?php echo number_format($product['discount_price'], 2); ?> <span class="bs3d-old-price" style="text-decoration: line-through; font-size: 0.85em; color: #888; margin-left: 5px;">₹<?php echo number_format($product['price'], 2); ?></span>
                      <?php else: ?>
                        ₹<?php echo number_format($product['price'], 2); ?>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </section><!-- /Order Confirmation Section -->

  </main>

  <?php include './footer.php'; ?>

  <!-- Invoice Modal -->
  <div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header" style="background: #0e2187; color: white;">
          <h5 class="modal-title" id="invoiceModalLabel"><i class="bi bi-file-pdf me-2"></i>Invoice - Order #<?php echo htmlspecialchars($order['order_number']); ?></h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-0" style="height: 80vh;">
          <iframe 
            src="generate-invoice.php?order=<?php echo htmlspecialchars($order['order_number']); ?>" 
            style="width: 100%; height: 100%; border: none;"
            title="Invoice PDF">
          </iframe>
        </div>
        <div class="modal-footer">
          <a href="generate-invoice.php?order=<?php echo htmlspecialchars($order['order_number']); ?>&download=1" 
             class="btn btn-primary" 
             download="Invoice-<?php echo htmlspecialchars($order['order_number']); ?>.pdf">
            <i class="bi bi-download me-2"></i>Download Invoice
          </a>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Scroll Top -->
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Preloader -->
  <div id="preloader"></div>

  <!-- Mobile Bottom Navigation -->
  <?php include 'mobile-bottom-nav.php'; ?>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>

  <!-- Main JS File -->
  <script src="assets/js/main.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      let cart = JSON.parse(localStorage.getItem('cart')) || [];
      let wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];

      function updateCartBadge() {
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        const cartBadges = document.querySelectorAll('a[href*="cart"] .badge, .cart-badge');
        cartBadges.forEach(badge => {
          badge.textContent = totalItems;
          badge.style.display = totalItems > 0 ? 'inline' : 'none';
        });
      }

      function updateWishlistBadge() {
        const wishlistBadges = document.querySelectorAll('a[href*="wishlist"] .badge, .wishlist-badge');
        wishlistBadges.forEach(badge => {
          badge.textContent = wishlist.length;
          badge.style.display = wishlist.length > 0 ? 'inline' : 'none';
        });
      }

      function updateWishlistButtons() {
        document.querySelectorAll('.wishlist-btn').forEach(button => {
          const productId = button.getAttribute('data-product-id');
          const isInWishlist = wishlist.some(item => String(item.id) === String(productId));
          const heartIcon = button.querySelector('i');
          if (heartIcon) {
            if (isInWishlist) {
              heartIcon.className = 'bi bi-heart-fill';
              button.classList.add('active');
            } else {
              heartIcon.className = 'bi bi-heart';
              button.classList.remove('active');
            }
          }
        });
      }

      updateCartBadge();
      updateWishlistBadge();
      updateWishlistButtons();

      document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function (e) {
          e.preventDefault();
          const productId = this.getAttribute('data-product-id');
          const productItem = this.closest('.product-item');
          const pName = productItem ? productItem.getAttribute('data-product-name') : 'Product';
          const pPrice = productItem ? parseFloat(productItem.getAttribute('data-product-price')) : 0;
          const pImage = productItem ? productItem.getAttribute('data-product-image') : '';

          const existingItem = cart.find(item => String(item.id) === String(productId));
          if (existingItem) {
            existingItem.quantity += 1;
          } else {
            cart.push({ id: productId, name: pName, price: pPrice, quantity: 1, image: pImage });
          }

          localStorage.setItem('cart', JSON.stringify(cart));
          updateCartBadge();
          showToast('Product added to cart!', 'success');
        });
      });

      document.querySelectorAll('.wishlist-btn').forEach(button => {
        button.addEventListener('click', function (e) {
          e.preventDefault();
          const productId = this.getAttribute('data-product-id');
          const productItem = this.closest('.product-item');
          const heartIcon = this.querySelector('i');

          const existingIndex = wishlist.findIndex(item => String(item.id) === String(productId));
          if (existingIndex > -1) {
            wishlist.splice(existingIndex, 1);
            if (heartIcon) heartIcon.className = 'bi bi-heart';
            this.classList.remove('active');
            showToast('Removed from wishlist', 'info');
          } else {
            const product = {
              id: productId,
              name: productItem ? productItem.getAttribute('data-product-name') : 'Product',
              price: productItem ? parseFloat(productItem.getAttribute('data-product-price')) : 0,
              image: productItem ? productItem.getAttribute('data-product-image') : ''
            };
            wishlist.push(product);
            if (heartIcon) heartIcon.className = 'bi bi-heart-fill';
            this.classList.add('active');
            showToast('Added to wishlist!', 'success');
          }

          localStorage.setItem('wishlist', JSON.stringify(wishlist));
          updateWishlistBadge();
        });
      });

      function showToast(message, type = 'info') {
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
          toastContainer = document.createElement('div');
          toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
          toastContainer.style.zIndex = '1080';
          document.body.appendChild(toastContainer);
        }

        let bgColor = type === 'success' ? '#8AC53E' : (type === 'error' ? '#e74c3c' : '#0e2187');
        let iconClass = type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill';

        const toast = document.createElement('div');
        toast.className = 'toast align-items-center border-0 mb-3 text-white';
        toast.style.background = bgColor;
        toast.style.borderRadius = '12px';
        toast.style.boxShadow = '0 10px 30px rgba(0,0,0,0.15)';
        toast.innerHTML = `
          <div class="d-flex align-items-center p-3">
            <div class="me-3 fs-4 d-flex align-items-center"><i class="bi ${iconClass}"></i></div>
            <div class="flex-grow-1 fs-6 fw-medium">${message}</div>
            <button type="button" class="btn-close btn-close-white ms-2 m-auto" data-bs-dismiss="toast"></button>
          </div>
        `;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
      }
    });
  </script>

</body>

</html>
