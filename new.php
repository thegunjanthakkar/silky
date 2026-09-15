<?php
// Start session for header functionality
session_start();

// Define base URL for assets (handles both /products and /products/category-name URLs)
require_once 'db_config.php';
$base_url = defined('BASE_URL') ? BASE_URL : '/';

// Check if a category slug is provided in the URL
$selected_category_id = null;
$selected_category_name = '';
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $category_slug = $_GET['category'];
    
    // First, fetch all categories to match the slug
    $all_categories_sql = "SELECT id, name FROM categories WHERE status = 'active'";
    $all_categories_result = mysqli_query($conn, $all_categories_sql);
    
    while ($cat = mysqli_fetch_assoc($all_categories_result)) {
        // Generate slug from category name
        $cat_slug = strtolower(trim($cat['name']));
        $cat_slug = preg_replace('/[^a-z0-9]+/', '-', $cat_slug);
        $cat_slug = trim($cat_slug, '-');
        
        // Check if this category matches the URL slug
        if ($cat_slug === $category_slug) {
            $selected_category_id = $cat['id'];
            $selected_category_name = $cat['name'];
            break;
        }
    }
}

// Fetch active categories for filter
$categories_sql = "SELECT DISTINCT c.id, c.name 
                   FROM categories c 
                   INNER JOIN products p ON c.id = p.category_id 
                   WHERE c.status = 'active' AND p.status = 'active'
                   ORDER BY c.name ASC";
$categories_result = mysqli_query($conn, $categories_sql);

// Fetch active colors for filter
$colors_sql = "SELECT DISTINCT col.id, col.color_name, col.color_code 
               FROM colors col 
               INNER JOIN product_colors pc ON col.id = pc.color_id
               INNER JOIN products p ON pc.product_id = p.id
               WHERE col.status = 'active' AND p.status = 'active'
               ORDER BY col.color_name ASC";
$colors_result = mysqli_query($conn, $colors_sql);

// Get price range from database
$price_range_sql = "SELECT MAX(CAST(price AS DECIMAL(15,2))) as max_price 
                    FROM products 
                    WHERE status = 'active'";
// Add category filter to price range if category is selected
if ($selected_category_id) {
    $price_range_sql .= " AND category_id = " . intval($selected_category_id);
}
$price_range_result = mysqli_query($conn, $price_range_sql);
$price_range = mysqli_fetch_assoc($price_range_result);
$min_db_price = 0; // Always start from 0
$max_db_price = ceil(floatval($price_range['max_price'] ?? 10000));
// Ensure max is at least 1000 for better slider usability
if ($max_db_price < 1000) $max_db_price = 10000;

// Fetch products from database with category names, colors, sizes, and YouTube video
$sql = "SELECT p.*, c.name as category_name, p.youtube_video_id,
            GROUP_CONCAT(DISTINCT CONCAT(col.color_name, '|', col.color_code) SEPARATOR '~') as product_colors,
            GROUP_CONCAT(DISTINCT s.size_label SEPARATOR '~') as product_sizes
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            LEFT JOIN colors col ON pc.color_id = col.id AND col.status = 'active'
            LEFT JOIN product_sizes ps ON p.id = ps.product_id
            LEFT JOIN sizes s ON ps.size_id = s.id AND s.status = 'active'
            WHERE p.status = 'active'";
// Add category filter if category is selected
if ($selected_category_id) {
    $sql .= " AND p.category_id = " . intval($selected_category_id);
}
$sql .= " GROUP BY p.id
            ORDER BY p.created_at DESC";
$products_result = mysqli_query($conn, $sql);

// Check if query was successful
if (!$products_result) {
    die("Database query failed: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Products - Silky Saree</title>
  <meta name="description" content="">
  <meta name="keywords" content="">

  <!-- Favicons -->
  <link href="<?php echo $base_url; ?>assets/img/favicon.png" rel="icon">
  <link href="<?php echo $base_url; ?>assets/img/apple-touch-icon.png" rel="apple-touch-icon">
  
  <!-- Pass PHP session data to JavaScript -->
  <script>
    const isUserLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
    const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '0'; ?>;
  </script>

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
    rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="<?php echo $base_url; ?>assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?php echo $base_url; ?>assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="<?php echo $base_url; ?>assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
  <link href="<?php echo $base_url; ?>assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="<?php echo $base_url; ?>assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="<?php echo $base_url; ?>assets/vendor/drift-zoom/drift-basic.css" rel="stylesheet">

  <!-- Main CSS File -->
  <link href="<?php echo $base_url; ?>assets/css/main.css" rel="stylesheet">

  <style>
    .toast-container {
      z-index: 9999 !important;
    }

    /* Price Range Slider Styles */
    .pricing-range-widget .range-slider {
      position: relative;
      height: 30px;
      margin: 15px 0;
    }

    .pricing-range-widget .slider-track {
      position: absolute;
      width: 100%;
      height: 5px;
      top: 50%;
      transform: translateY(-50%);
      background: #ddd;
      border-radius: 5px;
    }

    .pricing-range-widget .slider-progress {
      position: absolute;
      height: 5px;
      top: 50%;
      transform: translateY(-50%);
      background: #0e2187;
      border-radius: 5px;
      left: 0;
      width: 100%;
    }

    .pricing-range-widget input[type="range"] {
      position: absolute;
      width: 100%;
      height: 30px;
      top: 0;
      background: transparent;
      pointer-events: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      appearance: none;
      margin: 0;
      padding: 0;
    }

    .pricing-range-widget input[type="range"]::-webkit-slider-runnable-track {
      height: 5px;
      background: transparent;
    }

    .pricing-range-widget input[type="range"]::-webkit-slider-thumb {
      -webkit-appearance: none;
      height: 20px;
      width: 20px;
      border-radius: 50%;
      background: #0e2187;
      pointer-events: auto;
      cursor: pointer;
      border: 3px solid white;
      box-shadow: 0 2px 8px rgba(0,0,0,0.3);
      margin-top: -7px;
      position: relative;
      z-index: 10;
    }

    .pricing-range-widget input[type="range"]::-moz-range-track {
      height: 5px;
      background: transparent;
      border: none;
    }

    .pricing-range-widget input[type="range"]::-moz-range-thumb {
      height: 20px;
      width: 20px;
      border-radius: 50%;
      background: #0e2187;
      pointer-events: auto;
      cursor: pointer;
      border: 3px solid white;
      box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }

    .pricing-range-widget .min-range {
      z-index: 2;
    }

    .pricing-range-widget .max-range {
      z-index: 1;
    }

    /* Category Filter Styles */
    .category-filter-widget .form-check {
      padding: 8px 0;
      border-bottom: 1px solid #f0f0f0;
    }

    .category-filter-widget .form-check:last-child {
      border-bottom: none;
    }

    .category-filter-widget .form-check-label {
      cursor: pointer;
      margin-left: 5px;
      font-size: 0.9rem;
    }

    /* Color Filter Styles */
    .color-filter-widget .color-options {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 10px;
      margin-bottom: 15px;
    }

    .color-filter-widget .color-option {
      margin-bottom: 0;
    }

    .color-filter-widget .color-option .form-check-input {
      display: none;
    }

    .color-filter-widget .color-option label {
      cursor: pointer;
      margin: 0;
    }

    .color-filter-widget .color-swatch {
      display: inline-block;
      width: 35px;
      height: 35px;
      border-radius: 4px;
      border: 2px solid #ddd;
      transition: all 0.3s ease;
    }

    .color-filter-widget .form-check-input:checked + label .color-swatch {
      border-color: var(--accent-color);
      box-shadow: 0 0 0 3px rgba(14, 33, 135, 0.1);
      transform: scale(1.1);
    }

    /* View Button Styles */
    .view-options .view-btn {
      padding: 8px 12px;
      border: 1px solid #ddd;
      background: white;
      color: #666;
      margin-right: 5px;
      transition: all 0.3s ease;
    }

    .view-options .view-btn.active {
      background: var(--accent-color);
      color: white;
      border-color: var(--accent-color);
    }

    .view-options .view-btn:hover {
      border-color: var(--accent-color);
      color: var(--accent-color);
    }

    .view-options .view-btn.active:hover {
      color: white;
    }

    /* List View Styles */
    .category-product-list .product-item.list-view {
      display: flex;
      flex-direction: row;
    }

    .category-product-list .product-item.list-view .product-image {
      width: 250px;
      flex-shrink: 0;
    }

    .category-product-list .product-item.list-view .product-info {
      flex: 1;
      padding: 20px 30px;
    }

    /* Product Item Styles for Products Page */
    .category-product-list .product-item {
      height: 100%;
      background-color: var(--surface-color);
      border-radius: 2px;
      overflow: hidden;
      transition: all 0.4s ease;
    }

    .category-product-list .product-item:hover {
      transform: translateY(-4px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.06);
    }

    .category-product-list .product-item:hover .product-image img {
      transform: scale(1.02);
    }

    .category-product-list .product-item:hover .product-actions {
      opacity: 1;
      transform: translateY(0);
    }

    .category-product-list .product-item:hover .cart-btn {
      opacity: 1;
      transform: translateY(0);
    }

    .category-product-list .product-image {
      position: relative;
      overflow: hidden;
      aspect-ratio: 3/4;
      background-color: color-mix(in srgb, var(--default-color), transparent 96%);
    }

    .category-product-list .product-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.5s ease;
    }

    .category-product-list .product-badge {
      position: absolute;
      top: 15px;
      left: 15px;
      z-index: 2;
      background-color: var(--heading-color);
      color: var(--contrast-color);
      font-size: 0.7rem;
      font-weight: 500;
      font-family: var(--heading-font);
      padding: 0.3em 0.8em;
      border-radius: 0;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }

    .category-product-list .product-badge.sale-badge {
      background-color: #dc2626;
    }

    .category-product-list .product-badge.trending-badge {
      background-color: var(--accent-color);
    }

    .category-product-list .product-actions {
      position: absolute;
      top: 15px;
      right: 15px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      opacity: 0;
      transform: translateY(-10px);
      transition: all 0.3s ease;
    }

    .category-product-list .action-btn {
      width: 48px;
      height: 48px;
      border: none;
      background-color: #fff;
      color: #0e2187;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(.4,0,.2,1);
      font-size: 1.3rem;
      box-shadow: 0 4px 16px rgba(0,0,0,0.08);
      outline: none;
      border: 0;
    }

    .category-product-list .action-btn:hover {
      background-color: #0e2187;
      color: #fff;
      box-shadow: 0 8px 24px rgba(14,33,135,0.12);
    }

    .category-product-list .action-btn.active {
      background-color: #0e2187;
      color: #fff;
      box-shadow: 0 8px 24px rgba(14,33,135,0.18);
    }

    .category-product-list .cart-btn {
      position: absolute;
      bottom: 15px;
      left: 15px;
      right: 15px;
      padding: 10px 20px;
      background-color: var(--contrast-color);
      color: var(--heading-color);
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 90%);
      font-weight: 500;
      font-size: 0.8rem;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      cursor: pointer;
      opacity: 0;
      transform: translateY(20px);
      transition: all 0.3s ease;
      border-radius: 8px;
    }

    .category-product-list .cart-btn:hover {
      background-color: var(--heading-color);
      color: var(--contrast-color);
      border-color: var(--heading-color);
    }

    .category-product-list .product-info {
      padding: 20px 15px 18px;
    }

    .category-product-list .product-category {
      color: color-mix(in srgb, var(--default-color), transparent 40%);
      font-size: 0.75rem;
      font-weight: 500;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      margin-bottom: 8px;
    }

    .category-product-list .product-name {
      font-size: 0.95rem;
      font-weight: 300;
      line-height: 1.3;
      margin-bottom: 10px;
      height: 2.6rem;
      overflow: hidden;
      display: -webkit-box;
      -webkit-box-orient: vertical;
      -webkit-line-clamp: 2;
    }

    .category-product-list .product-name a {
      color: var(--heading-color);
    }

    .category-product-list .product-name a:hover {
      color: var(--accent-color);
    }

    .category-product-list .product-rating {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 12px;
    }

    .category-product-list .stars {
      color: #f59e0b;
      font-size: 0.8rem;
    }

    .category-product-list .stars i {
      margin-right: 1px;
    }

    .category-product-list .rating-count {
      font-size: 0.75rem;
      color: color-mix(in srgb, var(--default-color), transparent 50%);
      font-weight: 400;
    }

    .category-product-list .product-price {
      margin-bottom: 12px;
      font-size: 1.05rem;
      font-weight: 500;
      color: var(--heading-color);
    }

    .category-product-list .product-price .original-price {
      font-size: 0.9rem;
      color: color-mix(in srgb, var(--default-color), transparent 50%);
      text-decoration: line-through;
      margin-left: 8px;
    }

    .category-product-list .color-swatches {
      display: flex;
      gap: 6px;
    }

    .category-product-list .swatch {
      width: 16px;
      height: 16px;
      border-radius: 50%;
      cursor: pointer;
      position: relative;
      transition: all 0.2s ease;
    }

    .category-product-list .swatch:hover {
      transform: scale(1.15);
    }

    .category-product-list .swatch.active:after {
      content: "";
      position: absolute;
      top: -3px;
      left: -3px;
      right: -3px;
      bottom: -3px;
      border: 1px solid var(--heading-color);
      border-radius: 50%;
    }

    /* Video on hover styles */
    .category-product-list .product-video {
      position: absolute;
      inset: 0;
      background: #000;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.35s ease;
      z-index: 5;
      overflow: hidden; /* clip the oversized iframe */
    }

    .category-product-list .product-video.playing {
      opacity: 1;
    }

    /* 
     * Oversized iframe trick:
     * Make the iframe much larger than the container and center it.
     * This pushes YouTube's title bar (top) and control bar + logo (bottom)
     * outside the overflow:hidden clip — so only the raw video is visible.
     */
    .category-product-list .product-video iframe {
      position: absolute;
      /* Oversized so YouTube's top bar and bottom controls are pushed
         outside the overflow:hidden clip region */
      width: 200%;
      height: 200%;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      border: 0;
      pointer-events: none;
    }

    /* Full-cover transparent blocker — sits above the iframe,
       prevents the browser from ever passing mouse events into
       the YouTube iframe so the control bar never appears */
    .category-product-list .product-video .vid-blocker {
      position: absolute;
      inset: 0;
      z-index: 9;
      pointer-events: none;
    }

    /* Extra safety overlays to mask any remaining YT chrome */
    .category-product-list .product-video .vid-mask-top,
    .category-product-list .product-video .vid-mask-bottom {
      position: absolute;
      left: 0;
      right: 0;
      z-index: 10;
      pointer-events: none;
    }
    .category-product-list .product-video .vid-mask-top    { top: 0;    height: 12%; background: #000; }
    .category-product-list .product-video .vid-mask-bottom { bottom: 0; height: 12%; background: #000; }

    @media (max-width: 1199.98px) {
      .category-product-list .product-info {
        padding: 25px 18px 22px;
      }

      .category-product-list .product-name {
        font-size: 0.95rem;
      }
    }

    @media (max-width: 991.98px) {
      .category-product-list .product-item {
        max-width: 320px;
        margin: 0 auto;
      }
    }
  </style>

</head>

<body class="category-page">

  <header id="header" class="header sticky-top">
    <!-- Top Bar -->
    <?php include './topbar.php'; ?>

    <!-- Main Header -->
    <?php include './main-header.php'; ?>

    <main class="main">

      <!-- Page Title -->
      <div class="page-title light-background">
        <div class="container d-lg-flex justify-content-between align-items-center">
          <h1 class="mb-2 mb-lg-0"><?php echo $selected_category_name ? htmlspecialchars($selected_category_name) : 'Products'; ?></h1>
          <nav class="breadcrumbs">
            <ol>
              <li><a href="<?php echo $base_url; ?>index.php">Home</a></li>
              <?php if ($selected_category_name): ?>
                <li><a href="<?php echo $base_url; ?>products">Products</a></li>
                <li class="current"><?php echo htmlspecialchars($selected_category_name); ?></li>
              <?php else: ?>
                <li class="current">Products</li>
              <?php endif; ?>
            </ol>
          </nav>
        </div>
      </div><!-- End Page Title -->

      <div class="container">
        <div class="row">

          <div class="col-lg-3 sidebar">

            <div class="widgets-container">
              
              <!-- Category Filter Widget -->
              <div class="category-filter-widget widget-item">
                <h3 class="widget-title">Categories</h3>
                <div class="category-filter-content">
                  <?php
                  if (mysqli_num_rows($categories_result) > 0) {
                    $catCount = 0;
                    $hasHiddenChecked = false;
                    while ($category = mysqli_fetch_assoc($categories_result)) {
                      $catCount++;
                      // Check if this category should be pre-selected
                      $isChecked = ($selected_category_id && $category['id'] == $selected_category_id) ? 'checked' : '';
                      
                      $hiddenClass = ($catCount > 3) ? 'd-none hidden-category' : '';
                      if ($isChecked && $catCount > 3) {
                          $hasHiddenChecked = true;
                      }
                      
                      echo '<div class="form-check ' . $hiddenClass . '">';
                      echo '<input class="form-check-input category-checkbox" type="checkbox" value="' . htmlspecialchars($category['name']) . '" id="cat-' . $category['id'] . '" ' . $isChecked . '>';
                      echo '<label class="form-check-label" for="cat-' . $category['id'] . '">';
                      echo htmlspecialchars($category['name']);
                      echo '</label>';
                      echo '</div>';
                    }
                    
                    if (mysqli_num_rows($categories_result) > 3) {
                        $btnText = $hasHiddenChecked ? '- Show Less' : '+ Show More';
                        echo '<button class="btn btn-link p-0 mt-2 text-decoration-none shadow-none" id="showMoreCategoriesBtn" type="button" style="font-size: 0.85rem; color: var(--accent-color); font-weight: 500;" onclick="toggleCategories()">' . $btnText . '</button>';
                        
                        echo '<script>
                            if (' . ($hasHiddenChecked ? 'true' : 'false') . ') {
                                document.querySelectorAll(".hidden-category").forEach(el => el.classList.remove("d-none"));
                            }
                            function toggleCategories() {
                                const hiddenCats = document.querySelectorAll(".hidden-category");
                                const btn = document.getElementById("showMoreCategoriesBtn");
                                let isShowing = false;
                                hiddenCats.forEach(el => {
                                    if(el.classList.contains("d-none")) {
                                        el.classList.remove("d-none");
                                        isShowing = true;
                                    } else {
                                        el.classList.add("d-none");
                                        isShowing = false;
                                    }
                                });
                                btn.innerHTML = isShowing ? "- Show Less" : "+ Show More";
                            }
                        </script>';
                    }
                  }
                  ?>
                </div>
              </div><!--/Category Filter Widget -->

              <!-- Pricing Range Widget -->
              <div class="pricing-range-widget widget-item">

                <h3 class="widget-title">Price Range</h3>

                <div class="price-range-container">
                  <div class="current-range mb-3">
                    <span class="min-price">₹0</span>
                    <span class="max-price float-end">₹<?php echo number_format($max_db_price); ?></span>
                  </div>

                  <div class="range-slider">
                    <div class="slider-track"></div>
                    <div class="slider-progress"></div>
                    <input type="range" class="min-range" min="0" max="<?php echo $max_db_price; ?>" value="0" step="<?php echo max(100, floor($max_db_price / 100)); ?>">
                    <input type="range" class="max-range" min="0" max="<?php echo $max_db_price; ?>" value="<?php echo $max_db_price; ?>" step="<?php echo max(100, floor($max_db_price / 100)); ?>">
                  </div>

                  <div class="price-inputs mt-3">
                    <div class="row g-2">
                      <div class="col-6">
                        <div class="input-group input-group-sm">
                          <span class="input-group-text">₹</span>
                          <input type="number" class="form-control min-price-input" placeholder="Min" min="0" max="<?php echo $max_db_price; ?>"
                            value="0" step="100">
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="input-group input-group-sm">
                          <span class="input-group-text">₹</span>
                          <input type="number" class="form-control max-price-input" placeholder="Max" min="0" max="<?php echo $max_db_price; ?>"
                            value="<?php echo $max_db_price; ?>" step="100">
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="filter-actions mt-3">
                    <button type="button" class="btn btn-sm btn-primary w-100 apply-price-filter">Apply Filter</button>
                  </div>
                </div>

              </div><!--/Pricing Range Widget -->



              <!-- Color Filter Widget -->
              <div class="color-filter-widget widget-item">

                <h3 class="widget-title">Filter by Color</h3>

                <div class="color-filter-content">
                  <div class="color-options">
                    <?php
                    mysqli_data_seek($colors_result, 0); // Reset pointer
                    if (mysqli_num_rows($colors_result) > 0) {
                      while ($color = mysqli_fetch_assoc($colors_result)) {
                        $colorId = 'color-' . $color['id'];
                        $colorName = htmlspecialchars($color['color_name']);
                        $colorCode = htmlspecialchars($color['color_code']);
                        echo '<div class="form-check color-option">';
                        echo '<input class="form-check-input color-checkbox" type="checkbox" value="' . strtolower($colorName) . '" id="' . $colorId . '">';
                        echo '<label class="form-check-label" for="' . $colorId . '">';
                        echo '<span class="color-swatch" style="background-color: ' . $colorCode . ';" title="' . $colorName . '"></span>';
                        echo '</label>';
                        echo '</div>';
                      }
                    }
                    ?>
                  </div>

                  <div class="filter-actions mt-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary clear-colors-btn">Clear All</button>
                    <button type="button" class="btn btn-sm btn-primary apply-color-filter">Apply Filter</button>
                  </div>
                </div>

              </div><!--/Color Filter Widget -->



            </div>

          </div>

          <div class="col-lg-9">

            <!-- Category Header Section -->
            <section id="category-header" class="category-header section">

              <div class="container" data-aos="fade-up">

                <!-- Filter and Sort Options -->
                <div class="filter-container mb-4" data-aos="fade-up" data-aos-delay="100">
                  <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-4">
                      <div class="filter-item search-form">
                        <label for="productSearch" class="form-label">Search Products</label>
                        <div class="input-group">
                          <input type="text" class="form-control" id="productSearch"
                            placeholder="Search for products..." aria-label="Search for products">
                          <button class="btn search-btn" type="button">
                            <i class="bi bi-search"></i>
                          </button>
                        </div>
                      </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-2">
                      <div class="filter-item">
                        <label for="priceRange" class="form-label">Price Range</label>
                        <select class="form-select" id="priceRange">
                          <option value="">All Prices</option>
                          <?php
                          // Generate dynamic price ranges based on actual data
                          $priceGap = ceil(($max_db_price - $min_db_price) / 5);
                          if ($priceGap > 0) {
                            for ($i = 0; $i < 5; $i++) {
                              $rangeMin = $min_db_price + ($i * $priceGap);
                              $rangeMax = ($i == 4) ? $max_db_price : $min_db_price + (($i + 1) * $priceGap);
                              $label = '₹' . number_format($rangeMin) . ' to ₹' . number_format($rangeMax);
                              echo '<option data-min="' . $rangeMin . '" data-max="' . $rangeMax . '">' . $label . '</option>';
                            }
                          }
                          ?>
                        </select>
                      </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-2">
                      <div class="filter-item">
                        <label for="sortBy" class="form-label">Sort By</label>
                        <select class="form-select" id="sortBy">
                          <option selected="">Featured</option>
                          <option>Price: Low to High</option>
                          <option>Price: High to Low</option>
                          <option>Customer Rating</option>
                          <option>Newest Arrivals</option>
                        </select>
                      </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-4">
                      <div class="filter-item">
                        <label class="form-label">View</label>
                        <div class="d-flex align-items-center">
                          <div class="view-options me-3">
                            <button type="button" class="btn view-btn active" data-view="grid" aria-label="Grid view">
                              <i class="bi bi-grid-3x3-gap-fill"></i>
                            </button>
                            <button type="button" class="btn view-btn" data-view="list" aria-label="List view">
                              <i class="bi bi-list-ul"></i>
                            </button>
                          </div>
                          <div class="items-per-page">
                            <select class="form-select" id="itemsPerPage" aria-label="Items per page">
                              <option value="12">12 per page</option>
                              <option value="24">24 per page</option>
                              <option value="48">48 per page</option>
                              <option value="96">96 per page</option>
                            </select>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="row mt-3" style="display: none;">
                    <div class="col-12" data-aos="fade-up" data-aos-delay="200">
                      <div class="active-filters">
                        <span class="active-filter-label">Active Filters:</span>
                        <div class="filter-tags">
                          <!-- Dynamic filters will be added by JavaScript -->
                          <button class="clear-all-btn">Clear All</button>
                        </div>
                      </div>
                    </div>
                  </div>

                </div>

              </div>

            </section><!-- /Category Header Section -->

            <!-- Category Product List Section -->
            <section id="category-product-list" class="category-product-list section">

              <div class="container" data-aos="fade-up" data-aos-delay="100">

                <div class="row g-4">
                  <?php
                  // Check if there are products
                  if (mysqli_num_rows($products_result) > 0) {
                    while ($product = mysqli_fetch_assoc($products_result)) {
                      // Parse product images
                      $productImages = [];
                      if (!empty($product['image'])) {
                        $imageData = json_decode($product['image'], true);
                        if (is_array($imageData)) {
                          $productImages = $imageData;
                        } else {
                          $productImages = [$product['image']];
                        }
                      }
                      // Fix image path - images are in uploads/products/
                      $mainImage = $base_url . 'assets/img/product/placeholder.png';
                      if (!empty($productImages)) {
                        // Try different possible paths
                        $possiblePaths = [
                          'uploads/products/' . $productImages[0],
                          'admin/uploads/' . $productImages[0],
                          $productImages[0]
                        ];
                        foreach ($possiblePaths as $path) {
                          if (file_exists($path)) {
                            $mainImage = $base_url . $path;
                            break;
                          }
                        }
                      }

                      // Parse colors
                      $colors = [];
                      if (!empty($product['product_colors'])) {
                        $colorArray = explode('~', $product['product_colors']);
                        foreach ($colorArray as $colorData) {
                          $colorParts = explode('|', $colorData);
                          if (count($colorParts) == 2) {
                            $colors[] = [
                              'name' => $colorParts[0],
                              'code' => $colorParts[1]
                            ];
                          }
                        }
                      }

                      // Parse sizes
                      $sizes = [];
                      if (!empty($product['product_sizes'])) {
                        $sizes = explode('~', $product['product_sizes']);
                      }

                      // Determine badge
                      $badge = '';
                      $badgeClass = '';
                      // You can add logic here based on product data
                      // For now, we'll use a simple check
                      if (strtotime($product['created_at']) > strtotime('-7 days')) {
                        $badge = 'New';
                        $badgeClass = 'trending-badge';
                      }
                  ?>
                  <!-- Product <?php echo $product['id']; ?> -->
                  <div class="col-lg-3 col-md-6">
                    <div class="product-item" 
                         data-product-id="<?php echo $product['id']; ?>" 
                         data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                         data-product-price="<?php echo number_format($product['price'], 2, '.', ''); ?>" 
                         data-product-image="<?php echo htmlspecialchars($mainImage); ?>">
                      <div class="product-image">
                        <?php if ($badge): ?>
                        <div class="product-badge <?php echo $badgeClass; ?>"><?php echo $badge; ?></div>
                        <?php endif; ?>
                        <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="img-fluid" loading="lazy">
                        
                        <?php if (!empty($product['youtube_video_id'])): ?>
                        <div class="product-video" data-video-id="<?php echo htmlspecialchars($product['youtube_video_id']); ?>">
                          <!-- Iframe injected via JS on hover -->
                        </div>
                        <?php endif; ?>
                        
                        <div class="product-actions">
                          <button class="action-btn wishlist-btn" data-product-id="<?php echo $product['id']; ?>">
                            <i class="bi bi-heart"></i>
                          </button>
                          <button class="action-btn compare-btn">
                            <i class="bi bi-arrow-left-right"></i>
                          </button>
                          <button class="action-btn quickview-btn">
                            <i class="bi bi-zoom-in"></i>
                          </button>
                        </div>
                        <button class="cart-btn add-to-cart" data-product-id="<?php echo $product['id']; ?>">Add to Cart</button>
                      </div>
                      <div class="product-info">
                        <div class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></div>
                        <h4 class="product-name"><a href="<?php echo $base_url; ?>product-details/<?php echo htmlspecialchars($product['slug'] ?? 'product-' . $product['id']); ?>"><?php echo htmlspecialchars($product['name']); ?></a></h4>
                        <div class="product-rating">
                          <div class="stars">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star"></i>
                          </div>
                          <span class="rating-count">(0)</span>
                        </div>
                        <div class="product-price">₹<?php echo number_format($product['price'], 2); ?></div>
                        <?php if (!empty($colors)): ?>
                        <div class="color-swatches">
                          <?php foreach (array_slice($colors, 0, 3) as $index => $color): ?>
                          <span class="swatch <?php echo $index == 0 ? 'active' : ''; ?>" style="background-color: <?php echo htmlspecialchars($color['code']); ?>;" title="<?php echo htmlspecialchars($color['name']); ?>"></span>
                          <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  <?php
                    } // end while
                  } else {
                    // No products found
                    echo '<div class="col-12"><div class="alert alert-info text-center">No products found. Please check back later.</div></div>';
                  }
                  ?>
                </div>

              </div>

            </section><!-- /Category Product List Section -->

            <!-- Category Pagination Section -->
            <section id="category-pagination" class="category-pagination section">

              <div class="container">
                <nav class="d-flex justify-content-center" aria-label="Page navigation">
                  <ul>
                    <li>
                      <a href="#" aria-label="Previous page">
                        <i class="bi bi-arrow-left"></i>
                        <span class="d-none d-sm-inline">Previous</span>
                      </a>
                    </li>

                    <li><a href="#" class="active">1</a></li>
                    <li><a href="#">2</a></li>
                    <li><a href="#">3</a></li>
                    <li class="ellipsis">...</li>
                    <li><a href="#">8</a></li>
                    <li><a href="#">9</a></li>
                    <li><a href="#">10</a></li>

                    <li>
                      <a href="#" aria-label="Next page">
                        <span class="d-none d-sm-inline">Next</span>
                        <i class="bi bi-arrow-right"></i>
                      </a>
                    </li>
                  </ul>
                </nav>
              </div>

            </section><!-- /Category Pagination Section -->

          </div>

        </div>
      </div>

    </main>
    <!--footer-->
    <?php include './footer.php'; ?>

    <!-- Scroll Top -->
    <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i
        class="bi bi-arrow-up-short"></i></a>

    <!-- Preloader -->
    <div id="preloader"></div>

    <!-- Mobile Bottom Navigation -->
    <?php include 'mobile-bottom-nav.php'?>

    <!-- Vendor JS Files -->
    <script src="<?php echo $base_url; ?>assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $base_url; ?>assets/vendor/php-email-form/validate.js"></script>
    <script src="<?php echo $base_url; ?>assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="<?php echo $base_url; ?>assets/vendor/aos/aos.js"></script>
    <script src="<?php echo $base_url; ?>assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="<?php echo $base_url; ?>assets/vendor/drift-zoom/Drift.min.js"></script>
    <script src="<?php echo $base_url; ?>assets/vendor/purecounter/purecounter_vanilla.js"></script>

    <!-- Main JS File -->
    <script src="<?php echo $base_url; ?>assets/js/main.js"></script>

    <script>
// =====================================================
// AJAX-BASED PRODUCT FILTERING SYSTEM
// =====================================================
document.addEventListener('DOMContentLoaded', function () {
  // Base URL for AJAX requests
  const baseUrl = '<?php echo $base_url; ?>';
  
  // ========== STATE MANAGEMENT ==========
  let cart = JSON.parse(localStorage.getItem('cart')) || [];
  let wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];
  let isLoading = false;
  let currentView = 'grid';

  // ========== DOM ELEMENTS ==========
  const productContainer = document.querySelector('.category-product-list .row');
  const searchInput = document.querySelector('#productSearch');
  const searchBtn = document.querySelector('.search-btn');
  const categoryCheckboxes = document.querySelectorAll('.category-checkbox');
  const colorCheckboxes = document.querySelectorAll('.color-checkbox');
  const minRangeSlider = document.querySelector('.min-range');
  const maxRangeSlider = document.querySelector('.max-range');
  const minPriceInput = document.querySelector('.min-price-input');
  const maxPriceInput = document.querySelector('.max-price-input');
  const minPriceDisplay = document.querySelector('.current-range .min-price');
  const maxPriceDisplay = document.querySelector('.current-range .max-price');
  const sliderProgress = document.querySelector('.slider-progress');
  const applyPriceFilterBtn = document.querySelector('.apply-price-filter');
  const applyColorBtn = document.querySelector('.apply-color-filter');
  const clearColorBtn = document.querySelector('.clear-colors-btn');
  const priceRangeSelect = document.querySelector('#priceRange');
  const sortSelect = document.querySelector('#sortBy');
  const itemsPerPageSelect = document.querySelector('#itemsPerPage');
  const clearAllBtn = document.querySelector('.clear-all-btn');
  const viewBtns = document.querySelectorAll('.view-btn');

  // Initialize
  updateCartCount();
  updateWishlistCount();
  initializeSlider();
  attachEventListeners();
  attachProductEventListeners();
  initVideoOnHover();
  
  // Load cart count from database if user is logged in
  if (isUserLoggedIn) {
    updateCartCountFromDatabase();
  }

  console.log('Filter system initialized');
  console.log('User logged in status:', isUserLoggedIn);
  console.log('User ID:', userId);

  // ========== SLIDER FUNCTIONS ==========
  function initializeSlider() {
    if (minRangeSlider && maxRangeSlider) {
      updateSliderUI();
    }
  }

  function updateSliderUI() {
    if (!minRangeSlider || !maxRangeSlider) return;
    
    let minVal = parseInt(minRangeSlider.value);
    let maxVal = parseInt(maxRangeSlider.value);
    const rangeMin = parseInt(minRangeSlider.min);
    const rangeMax = parseInt(minRangeSlider.max);
    const gap = Math.max(100, Math.floor((rangeMax - rangeMin) * 0.01));

    // Prevent sliders from crossing
    if (maxVal - minVal < gap) {
      maxVal = minVal + gap;
      if (maxVal > rangeMax) {
        maxVal = rangeMax;
        minVal = rangeMax - gap;
      }
      minRangeSlider.value = minVal;
      maxRangeSlider.value = maxVal;
    }

    // Update inputs and displays
    if (minPriceInput) minPriceInput.value = minVal;
    if (maxPriceInput) maxPriceInput.value = maxVal;
    if (minPriceDisplay) minPriceDisplay.textContent = '₹' + minVal.toLocaleString('en-IN');
    if (maxPriceDisplay) maxPriceDisplay.textContent = '₹' + maxVal.toLocaleString('en-IN');

    // Update progress bar
    if (sliderProgress && rangeMax > rangeMin) {
      const minPercent = ((minVal - rangeMin) / (rangeMax - rangeMin)) * 100;
      const maxPercent = ((maxVal - rangeMin) / (rangeMax - rangeMin)) * 100;
      sliderProgress.style.left = minPercent + '%';
      sliderProgress.style.width = (maxPercent - minPercent) + '%';
    }
  }

  // ========== ATTACH EVENT LISTENERS ==========
  function attachEventListeners() {
    // Search
    if (searchInput) {
      searchInput.addEventListener('keyup', debounce(fetchFilteredProducts, 500));
    }
    if (searchBtn) {
      searchBtn.addEventListener('click', fetchFilteredProducts);
    }

    // Category checkboxes
    categoryCheckboxes.forEach(checkbox => {
      checkbox.addEventListener('change', function() {
        console.log('Category changed:', this.value, this.checked);
        fetchFilteredProducts();
      });
    });

    // Color checkboxes
    colorCheckboxes.forEach(checkbox => {
      checkbox.addEventListener('change', function() {
        console.log('Color changed:', this.value, this.checked);
        fetchFilteredProducts();
      });
    });

    // Price slider
    if (minRangeSlider) {
      minRangeSlider.addEventListener('input', function() {
        let minVal = parseInt(this.value);
        let maxVal = parseInt(maxRangeSlider.value);
        if (minVal > maxVal - 100) {
          this.value = maxVal - 100;
        }
        updateSliderUI();
      });
      minRangeSlider.addEventListener('change', fetchFilteredProducts);
    }

    if (maxRangeSlider) {
      maxRangeSlider.addEventListener('input', function() {
        let maxVal = parseInt(this.value);
        let minVal = parseInt(minRangeSlider.value);
        if (maxVal < minVal + 100) {
          this.value = minVal + 100;
        }
        updateSliderUI();
      });
      maxRangeSlider.addEventListener('change', fetchFilteredProducts);
    }

    // Price inputs
    if (minPriceInput) {
      minPriceInput.addEventListener('change', function() {
        let val = parseInt(this.value) || 0;
        val = Math.max(parseInt(minRangeSlider.min), Math.min(parseInt(maxRangeSlider.value) - 100, val));
        minRangeSlider.value = val;
        this.value = val;
        updateSliderUI();
        fetchFilteredProducts();
      });
    }

    if (maxPriceInput) {
      maxPriceInput.addEventListener('change', function() {
        let val = parseInt(this.value) || parseInt(maxRangeSlider.max);
        val = Math.min(parseInt(maxRangeSlider.max), Math.max(parseInt(minRangeSlider.value) + 100, val));
        maxRangeSlider.value = val;
        this.value = val;
        updateSliderUI();
        fetchFilteredProducts();
      });
    }

    // Apply Price Filter button
    if (applyPriceFilterBtn) {
      applyPriceFilterBtn.addEventListener('click', fetchFilteredProducts);
    }

    // Apply Color Filter button
    if (applyColorBtn) {
      applyColorBtn.addEventListener('click', fetchFilteredProducts);
    }

    // Clear Colors button
    if (clearColorBtn) {
      clearColorBtn.addEventListener('click', function() {
        colorCheckboxes.forEach(cb => cb.checked = false);
        fetchFilteredProducts();
      });
    }

    // Price range dropdown
    if (priceRangeSelect) {
      priceRangeSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const min = selectedOption.getAttribute('data-min');
        const max = selectedOption.getAttribute('data-max');

        if (min && max && minRangeSlider && maxRangeSlider) {
          minRangeSlider.value = parseInt(min);
          maxRangeSlider.value = parseInt(max);
          updateSliderUI();
        } else if (minRangeSlider && maxRangeSlider) {
          // "All Prices" selected - reset to full range
          minRangeSlider.value = minRangeSlider.min;
          maxRangeSlider.value = maxRangeSlider.max;
          updateSliderUI();
        }
        fetchFilteredProducts();
      });
    }

    // Sort dropdown
    if (sortSelect) {
      sortSelect.addEventListener('change', function() {
        console.log('Sort changed:', this.value);
        fetchFilteredProducts();
      });
    }

    // Items per page
    if (itemsPerPageSelect) {
      itemsPerPageSelect.addEventListener('change', fetchFilteredProducts);
    }

    // View toggle
    viewBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        const view = this.getAttribute('data-view');
        currentView = view;
        
        viewBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        applyViewMode();
      });
    });

    // Clear All button
    if (clearAllBtn) {
      clearAllBtn.addEventListener('click', resetAllFilters);
    }
  }

  // ========== FETCH FILTERED PRODUCTS (AJAX) ==========
  function fetchFilteredProducts() {
    if (isLoading) return;
    
    isLoading = true;
    showLoading();

    // Collect filter values
    const params = new URLSearchParams();

    // Search
    const searchTerm = searchInput?.value.trim() || '';
    if (searchTerm) {
      params.append('search', searchTerm);
    }

    // Categories
    const selectedCategories = Array.from(categoryCheckboxes)
      .filter(cb => cb.checked)
      .map(cb => cb.value);
    if (selectedCategories.length > 0) {
      params.append('categories', selectedCategories.join(','));
    }

    // Colors
    const selectedColors = Array.from(colorCheckboxes)
      .filter(cb => cb.checked)
      .map(cb => cb.value);
    if (selectedColors.length > 0) {
      params.append('colors', selectedColors.join(','));
    }

    // Price range
    const minPrice = minPriceInput?.value || minRangeSlider?.min || 0;
    const maxPrice = maxPriceInput?.value || maxRangeSlider?.max || 999999999;
    params.append('min_price', minPrice);
    params.append('max_price', maxPrice);

    // Sort
    const sortValue = sortSelect?.value || 'Featured';
    params.append('sort', sortValue);

    // Items per page
    const perPage = itemsPerPageSelect?.value || 12;
    params.append('per_page', perPage);

    console.log('Fetching products with params:', params.toString());

    // Make AJAX request
    fetch(baseUrl + 'filter_products.php?' + params.toString())
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then(data => {
        console.log('Received data:', data);
        if (data.success) {
          renderProducts(data.products);
          updateResultsCount(data.total);
        } else {
          showError('Failed to load products');
        }
      })
      .catch(error => {
        console.error('Error fetching products:', error);
        showError('Error loading products. Please try again.');
      })
      .finally(() => {
        isLoading = false;
        hideLoading();
      });
  }

  // ========== RENDER PRODUCTS ==========
  function renderProducts(products) {
    if (!productContainer) return;

    if (products.length === 0) {
      productContainer.innerHTML = `
        <div class="col-12">
          <div class="alert alert-info text-center">
            <i class="bi bi-search me-2"></i>
            No products found matching your filters. Try adjusting your criteria.
          </div>
        </div>
      `;
      return;
    }

    let html = '';
    products.forEach(product => {
      html += generateProductHTML(product);
    });

    productContainer.innerHTML = html;
    
    // Re-attach event listeners to new product elements
    attachProductEventListeners();
    initVideoOnHover();
    
    // Apply current view mode
    applyViewMode();
  }

  function generateProductHTML(product) {
    // Generate color swatches HTML
    let colorSwatchesHTML = '';
    if (product.colors && product.colors.length > 0) {
      colorSwatchesHTML = '<div class="color-swatches">';
      product.colors.slice(0, 3).forEach((color, index) => {
        colorSwatchesHTML += `<span class="swatch ${index === 0 ? 'active' : ''}" style="background-color: ${color.code};" title="${color.name}"></span>`;
      });
      colorSwatchesHTML += '</div>';
    }

    // Badge HTML
    let badgeHTML = '';
    if (product.badge) {
      badgeHTML = `<div class="product-badge ${product.badge_class}">${product.badge}</div>`;
    }

    // YouTube video HTML
    let videoHTML = '';
    if (product.youtube_video_id) {
      videoHTML = `<div class="product-video mt-2" data-video-id="${product.youtube_video_id}"></div>`;
    }

    // Check if in wishlist
    const isInWishlist = wishlist.some(item => item.id == product.id);
    const heartIcon = isInWishlist ? 'bi-heart-fill' : 'bi-heart';
    const activeClass = isInWishlist ? 'active' : '';

    return `
      <div class="col-lg-3 col-md-6">
        <div class="product-item" 
             data-product-id="${product.id}" 
             data-product-name="${escapeHtml(product.name)}"
             data-product-price="${product.price}" 
             data-product-image="${escapeHtml(product.image)}">
          <div class="product-image">
            ${badgeHTML}
            <img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name)}" class="img-fluid" loading="lazy">
            ${videoHTML}
            <div class="product-actions">
              <button class="action-btn wishlist-btn ${activeClass}" data-product-id="${product.id}">
                <i class="bi ${heartIcon}"></i>
              </button>
              <button class="action-btn compare-btn">
                <i class="bi bi-arrow-left-right"></i>
              </button>
              <button class="action-btn quickview-btn">
                <i class="bi bi-zoom-in"></i>
              </button>
            </div>
            <button class="cart-btn add-to-cart" data-product-id="${product.id}">Add to Cart</button>
          </div>
          <div class="product-info">
            <div class="product-category">${escapeHtml(product.category)}</div>
            <h4 class="product-name"><a href="<?php echo $base_url; ?>product-details/${product.slug}">${escapeHtml(product.name)}</a></h4>
            <div class="product-rating">
              <div class="stars">
                <i class="bi bi-star-fill"></i>
                <i class="bi bi-star-fill"></i>
                <i class="bi bi-star-fill"></i>
                <i class="bi bi-star-fill"></i>
                <i class="bi bi-star"></i>
              </div>
              <span class="rating-count">(0)</span>
            </div>
            <div class="product-price">${product.price_formatted}</div>
            ${colorSwatchesHTML}
          </div>
        </div>
      </div>
    `;
  }

  // ========== PRODUCT EVENT LISTENERS ==========
  function attachProductEventListeners() {
    // Add to Cart
    document.querySelectorAll('.add-to-cart').forEach(button => {
      button.addEventListener('click', function () {
        const productItem = this.closest('.product-item');
        const productId = productItem.getAttribute('data-product-id');
        const productName = productItem.getAttribute('data-product-name');
        const productPrice = parseFloat(productItem.getAttribute('data-product-price'));
        const productImage = productItem.getAttribute('data-product-image');

        const product = {
          id: productId,
          name: productName,
          price: productPrice,
          image: productImage,
          quantity: 1
        };

        // Check if user is logged in
        if (isUserLoggedIn) {
          // User is logged in - add to database via AJAX
          addToCartDatabase(product);
        } else {
          // User not logged in - add to localStorage
          addToCartLocalStorage(product);
        }
      });
    });

    // Add to Wishlist
    document.querySelectorAll('.wishlist-btn').forEach(button => {
      button.addEventListener('click', function () {
        const productItem = this.closest('.product-item');
        const productId = productItem.getAttribute('data-product-id');
        const productName = productItem.getAttribute('data-product-name');
        const productPrice = parseFloat(productItem.getAttribute('data-product-price'));
        const productImage = productItem.getAttribute('data-product-image');

        const product = {
          id: productId,
          name: productName,
          price: productPrice,
          image: productImage
        };

        const existingProductIndex = wishlist.findIndex(item => item.id === productId);

        if (existingProductIndex > -1) {
          wishlist.splice(existingProductIndex, 1);
          showToast('Product removed from wishlist!', 'info');
          this.querySelector('i').classList.remove('bi-heart-fill');
          this.querySelector('i').classList.add('bi-heart');
          this.classList.remove('active');
        } else {
          wishlist.push(product);
          showToast('Product added to wishlist!', 'success');
          this.querySelector('i').classList.remove('bi-heart');
          this.querySelector('i').classList.add('bi-heart-fill');
          this.classList.add('active');
        }

        localStorage.setItem('wishlist', JSON.stringify(wishlist));
        updateWishlistCount();
      });
    });

    // Compare & Quick View
    document.querySelectorAll('.compare-btn').forEach(button => {
      button.addEventListener('click', function () {
        showToast('Compare feature coming soon!', 'info');
      });
    });

    document.querySelectorAll('.quickview-btn').forEach(button => {
      button.addEventListener('click', function () {
        showToast('Quick view coming soon!', 'info');
      });
    });
  }

  // ========== RESET ALL FILTERS ==========
  function resetAllFilters() {
    console.log('Resetting all filters...');
    
    // Reset search
    if (searchInput) searchInput.value = '';
    
    // Reset price sliders
    if (minRangeSlider && maxRangeSlider) {
      minRangeSlider.value = minRangeSlider.min;
      maxRangeSlider.value = maxRangeSlider.max;
      updateSliderUI();
    }
    
    // Reset checkboxes
    categoryCheckboxes.forEach(cb => cb.checked = false);
    colorCheckboxes.forEach(cb => cb.checked = false);
    
    // Reset dropdowns
    if (sortSelect) sortSelect.selectedIndex = 0;
    if (priceRangeSelect) priceRangeSelect.selectedIndex = 0;
    if (itemsPerPageSelect) itemsPerPageSelect.selectedIndex = 0;
    
    // Fetch products without any filters
    fetchFilteredProducts();
  }

  // ========== ADD TO CART FUNCTIONS ==========
  function addToCartDatabase(product) {
    console.log('=== ADD TO CART DATABASE ===');
    console.log('User logged in:', isUserLoggedIn);
    console.log('User ID:', userId);
    console.log('Product object:', product);
    console.log('Product ID:', product.id, 'Type:', typeof product.id);
    console.log('Product quantity:', product.quantity, 'Type:', typeof product.quantity);
    
    const requestBody = {
      product_id: parseInt(product.id),
      quantity: parseInt(product.quantity) || 1
    };
    const bodyString = JSON.stringify(requestBody);
    console.log('Request body:', requestBody);
    console.log('Request body string:', bodyString);
    console.log('Body string length:', bodyString.length);
    
    const url = baseUrl + 'cart.php?action=add_to_cart';
    console.log('Sending POST to:', url);
    
    fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: bodyString
    })
    .then(response => {
      console.log('Response status:', response.status);
      console.log('Response headers:', response.headers);
      return response.text(); // Get as text first to see what we're getting
    })
    .then(text => {
      console.log('Raw response:', text);
      try {
        const data = JSON.parse(text);
        console.log('Parsed response:', data);
        if (data.status === 'success') {
          showToast(data.message, 'success');
          updateCartCountFromDatabase();
        } else {
          showToast(data.message || 'Failed to add to cart', 'danger');
        }
      } catch (e) {
        console.error('JSON parse error:', e);
        console.error('Response was not JSON:', text.substring(0, 500));
        showToast('Server returned invalid response', 'danger');
      }
    })
    .catch(error => {
      console.error('Fetch error:', error);
      showToast('Error adding to cart. Please try again.', 'danger');
    });
  }

  function addToCartLocalStorage(product) {
    const existingProductIndex = cart.findIndex(item => item.id === product.id);

    if (existingProductIndex > -1) {
      cart[existingProductIndex].quantity += 1;
      showToast('Product quantity updated in cart!', 'success');
    } else {
      cart.push(product);
      showToast('Product added to cart!', 'success');
    }

    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartCount();
  }

  function updateCartCountFromDatabase() {
    fetch(baseUrl + 'cart.php?action=get_cart')
      .then(response => response.json())
      .then(data => {
        if (data.status === 'success' && data.cart) {
          const totalItems = data.cart.reduce((total, item) => total + item.quantity, 0);
          const cartCountElements = document.querySelectorAll('.cart-count');
          cartCountElements.forEach(element => {
            element.textContent = totalItems;
            element.style.display = totalItems > 0 ? 'inline' : 'none';
          });
        }
      })
      .catch(error => {
        console.error('Error updating cart count:', error);
      });
  }

  // ========== VIEW MODE ==========
  function applyViewMode() {
    const products = productContainer.querySelectorAll('.col-lg-3, .col-md-6, .col-lg-12');
    
    if (currentView === 'list') {
      products.forEach(product => {
        product.classList.remove('col-lg-3');
        product.classList.add('col-lg-12');
        const productItem = product.querySelector('.product-item');
        if (productItem) productItem.classList.add('list-view');
      });
    } else {
      products.forEach(product => {
        product.classList.remove('col-lg-12');
        product.classList.add('col-lg-3');
        const productItem = product.querySelector('.product-item');
        if (productItem) productItem.classList.remove('list-view');
      });
    }
  }

  // ========== UI HELPERS ==========
  function showLoading() {
    if (!productContainer) return;
    const loader = document.createElement('div');
    loader.id = 'filter-loader';
    loader.className = 'text-center py-5';
    loader.innerHTML = `
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="mt-2 text-muted">Loading products...</p>
    `;
    productContainer.innerHTML = '';
    productContainer.appendChild(loader);
  }

  function hideLoading() {
    const loader = document.getElementById('filter-loader');
    if (loader) loader.remove();
  }

  function showError(message) {
    if (!productContainer) return;
    productContainer.innerHTML = `
      <div class="col-12">
        <div class="alert alert-danger text-center">
          <i class="bi bi-exclamation-triangle me-2"></i>
          ${message}
        </div>
      </div>
    `;
  }

  function updateResultsCount(total) {
    // Update any results count display if it exists
    const resultsDisplay = document.querySelector('.results-count');
    if (resultsDisplay) {
      resultsDisplay.textContent = `${total} product${total !== 1 ? 's' : ''} found`;
    }
  }

  function updateCartCount() {
    const cartCountElements = document.querySelectorAll('.cart-count');
    const totalItems = cart.reduce((total, item) => total + item.quantity, 0);
    cartCountElements.forEach(element => {
      element.textContent = totalItems;
      element.style.display = totalItems > 0 ? 'inline' : 'none';
    });
  }

  function updateWishlistCount() {
    const wishlistCountElements = document.querySelectorAll('.wishlist-count');
    wishlistCountElements.forEach(element => {
      element.textContent = wishlist.length;
      element.style.display = wishlist.length > 0 ? 'inline' : 'none';
    });
  }

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

  // ========== UTILITY FUNCTIONS ==========
  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // ========== VIDEO ON HOVER ==========
  function initVideoOnHover() {
    document.querySelectorAll('.product-item').forEach(item => {
      const videoContainer = item.querySelector('.product-video');
      if (videoContainer && !item.hasAttribute('data-video-initialized')) {
        item.setAttribute('data-video-initialized', 'true');

        // Build the overlay masks + blocker once
        if (!videoContainer.querySelector('.vid-mask-top')) {
          const blocker     = document.createElement('div');
          blocker.className = 'vid-blocker';
          const topMask    = document.createElement('div');
          topMask.className = 'vid-mask-top';
          const bottomMask  = document.createElement('div');
          bottomMask.className = 'vid-mask-bottom';
          videoContainer.appendChild(blocker);
          videoContainer.appendChild(topMask);
          videoContainer.appendChild(bottomMask);
        }

        let iframe    = null;
        let timeoutId = null;

        item.addEventListener('mouseenter', () => {
          // Small delay so a fleeting cursor pass doesn't flash a video
          timeoutId = setTimeout(() => {
            const videoId = videoContainer.dataset.videoId;
            if (!iframe) {
              iframe = document.createElement('iframe');
              // controls=0  – no bottom controls
              // modestbranding=1 – minimal logo
              // rel=0       – no related videos at end
              // showinfo=0  – no title overlay (legacy param, still helps)
              // iv_load_policy=3 – no annotations
              // fs=0        – no fullscreen button in the player UI
              // disablekb=1 – no keyboard control
              // loop=1 + playlist=videoId – seamless looping
              // playsinline=1 – prevent native fullscreen on iOS
              iframe.src = `https://www.youtube.com/embed/${videoId}?autoplay=1&mute=1&controls=0&modestbranding=1&rel=0&showinfo=0&iv_load_policy=3&fs=0&disablekb=1&loop=1&playlist=${videoId}&playsinline=1`;
              iframe.setAttribute('allow', 'autoplay; encrypted-media');
              iframe.tabIndex = -1;
              // Insert before the mask divs so masks sit on top
              const topMask = videoContainer.querySelector('.vid-mask-top');
              videoContainer.insertBefore(iframe, topMask);
            }
            videoContainer.classList.add('playing');
          }, 200);
        });

        item.addEventListener('mouseleave', () => {
          clearTimeout(timeoutId);
          videoContainer.classList.remove('playing');
          // Destroy iframe so the video actually stops (no background audio)
          if (iframe) {
            iframe.src = 'about:blank';
            iframe.remove();
            iframe = null;
          }
        });
      }
    });
  }

});
    </script>

</body>
</html>