<?php
session_start();
require_once 'db_config.php';
$base_url = defined('BASE_URL') ? BASE_URL : '/';

// ==============================================
// 1. AJAX AUTOSUGGEST HANDLER
// ==============================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (empty($q) || strlen($q) < 2) {
        echo json_encode([]);
        exit;
    }
    
    $search_term = "%{$q}%";
    
    // We search across product name, description, category name, and product code
    $sql = "SELECT p.id, p.name, p.product_code, p.price, p.image, p.slug, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.status = 'active' 
            AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR p.product_code LIKE ?)
            ORDER BY p.created_at DESC 
            LIMIT 6"; // limit suggestions
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $search_term, $search_term, $search_term, $search_term);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $results = [];
    while ($row = $res->fetch_assoc()) {
        // Parse image path (handle JSON array or comma separated)
        $img_str = $row['image'];
        $img = 'assets/img/product/product-1.webp'; // Default fallback
        if (!empty($img_str)) {
            $decoded = json_decode($img_str, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && count($decoded) > 0) {
                $img = $decoded[0];
            } else {
                $imgs = explode(',', $img_str);
                $img = trim($imgs[0]);
            }
        }
        
        if (strpos($img, './') === 0) {
            $img = substr($img, 2);
        }
        $row['image_url'] = $base_url . $img;
        
        // Generate product details link
        $row['link'] = $base_url . 'product-details/' . ($row['slug'] ?? 'product-' . $row['id']);
        
        $results[] = $row;
    }
    
    echo json_encode($results);
    exit;
}

// ==============================================
// 2. REGULAR PAGE LOAD HANDLER
// ==============================================
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_results = [];

if (!empty($search_query)) {
    $search_term = "%{$search_query}%";
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.status = 'active' 
            AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR p.product_code LIKE ?)
            ORDER BY p.created_at DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $search_term, $search_term, $search_term, $search_term);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        $search_results[] = $row;
    }
}

// Suggested products (latest 8)
$suggested_products = [];
$sug_res = mysqli_query($conn, "SELECT p.id, p.name, p.price, p.image, p.slug, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status='active' ORDER BY p.created_at DESC LIMIT 8");
if ($sug_res) { while ($row = mysqli_fetch_assoc($sug_res)) { $suggested_products[] = $row; } }

// Active categories
$categories = [];
$cat_res = mysqli_query($conn, "SELECT id, name, image FROM categories WHERE status='active' ORDER BY display_order ASC, name ASC LIMIT 8");
if ($cat_res) { while ($row = mysqli_fetch_assoc($cat_res)) { $categories[] = $row; } }

// Trending searches (Mix of categories and products)
$trending_searches = [];
$trend_cats = mysqli_query($conn, "SELECT name FROM categories WHERE status='active' ORDER BY RAND() LIMIT 3");
if ($trend_cats) { while ($row = mysqli_fetch_assoc($trend_cats)) { $trending_searches[] = $row['name']; } }

$trend_prods = mysqli_query($conn, "SELECT name FROM products WHERE status='active' ORDER BY RAND() LIMIT 3");
if ($trend_prods) { while ($row = mysqli_fetch_assoc($trend_prods)) { $trending_searches[] = $row['name']; } }

shuffle($trending_searches);

// Fallback if db is completely empty
if (empty($trending_searches)) {
    $trending_searches = ['Silk Sarees', 'Lehengas', 'Bridal', 'Salwar Suits', 'Party Wear', 'Designer'];
}

function parseProductImage($img_str, $base_url) {
    $img = 'assets/img/product/product-1.webp';
    if (!empty($img_str)) {
        $decoded = json_decode($img_str, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && count($decoded) > 0) { $img = $decoded[0]; }
        else { $imgs = explode(',', $img_str); $img = trim($imgs[0]); }
    }
    if (strpos($img, './') === 0) $img = substr($img, 2);
    return $base_url . $img;
}

$website_settings = [];
$settings_res = mysqli_query($conn, "SELECT * FROM website_settings");
if ($settings_res) { while ($row = mysqli_fetch_assoc($settings_res)) { $website_settings[$row['setting_key']] = $row['setting_value']; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Search - Silky Saree</title>
  <meta name="description" content="Search for sarees, lehengas, salwar suits and more at Silky Saree.">
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link rel="shortcut icon" href="./assets/img/silky-jpg.jpg" type="image/x-icon">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="<?php echo $base_url; ?>assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?php echo $base_url; ?>assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="<?php echo $base_url; ?>assets/css/main.css" rel="stylesheet">
  <style>
    :root { --accent:#97c51d; --accent-dark:#7aab0a; --navy:#0e2187; --soft-bg:#f5f7fb; --card-radius:18px; }
    html, body { font-family:'Poppins',sans-serif; background:var(--soft-bg); overflow-x:hidden; max-width:100%; }

    /* ── HERO ── */
    .search-hero {
      background: linear-gradient(135deg, #0a1a6e 0%, #1a3aad 30%, #97c51d 70%, #5e8508 100%);
      padding:55px 0 90px; position:relative; overflow:hidden;
    }
    .search-hero::before { content:''; position:absolute; top:-80px; right:-80px; width:380px; height:380px; border-radius:50%; background:rgba(255,255,255,0.06); pointer-events:none; }
    .search-hero::after  { content:''; position:absolute; bottom:-100px; left:-60px; width:320px; height:320px; border-radius:50%; background:rgba(151,197,29,0.10); pointer-events:none; }
    .search-hero-label { font-size:0.78rem; letter-spacing:3px; text-transform:uppercase; color:rgba(255,255,255,0.75); font-weight:700; margin-bottom:12px; }
    .search-hero h1 { font-family:'Montserrat',sans-serif; font-size:clamp(1.8rem,4vw,2.8rem); font-weight:800; color:#fff; margin-bottom:8px; line-height:1.2; text-shadow:0 2px 20px rgba(0,0,0,0.2); }
    .hero-sub { color:rgba(255,255,255,0.7); font-size:1rem; margin-bottom:0; }

    /* ── SEARCH BOX ── */
    .search-box-wrap { position:relative; max-width:780px; margin:0 auto; }
    .search-field-wrap { background:#fff; border-radius:60px; box-shadow:0 20px 60px rgba(0,0,0,0.25); display:flex; align-items:center; padding:8px 8px 8px 28px; transition:box-shadow 0.3s; }
    .search-field-wrap:focus-within { box-shadow:0 20px 60px rgba(151,197,29,0.40); }
    .srch-icon { font-size:1.15rem; color:#aaa; margin-right:12px; flex-shrink:0; }
    #searchInput { flex:1; border:none; outline:none; font-size:1.05rem; font-family:'Poppins',sans-serif; color:#333; background:transparent; padding:10px 0; }
    #searchInput::placeholder { color:#bbb; }
    .search-submit-btn { background:linear-gradient(135deg,var(--accent),var(--accent-dark)); color:#fff; border:none; border-radius:50px; padding:14px 28px; font-size:0.95rem; font-weight:600; cursor:pointer; transition:all 0.3s; white-space:nowrap; display:flex; align-items:center; gap:8px; }
    .search-submit-btn:hover { background:linear-gradient(135deg,var(--accent-dark),#5e8508); transform:scale(1.03); }
    @media(max-width:480px){.search-submit-btn .btn-text{display:none;}.search-field-wrap{padding:6px 6px 6px 20px;}}

    /* ── AUTOSUGGEST (FIXED — no overflow clipping) ── */
    .autosuggest-dropdown {
      position:fixed; /* fixed so hero overflow:visible doesn't matter */
      background:#fff; border-radius:20px;
      box-shadow:0 30px 80px rgba(0,0,0,0.22);
      z-index:99999; overflow:hidden; display:none;
      width:780px; max-width:calc(100vw - 32px);
    }
    .autosuggest-dropdown.active { display:block; animation:fadeDown 0.2s ease; }
    @keyframes fadeDown { from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);} }
    .suggestion-item { display:flex; align-items:center; padding:14px 20px; border-bottom:1px solid #f0f0f0; text-decoration:none; color:#333; transition:background 0.2s; }
    .suggestion-item:last-child { border-bottom:none; }
    .suggestion-item:hover { background:#fafafa; }
    .suggestion-img { width:52px; height:68px; object-fit:cover; border-radius:10px; margin-right:14px; flex-shrink:0; }
    .suggestion-info h6 { margin:0 0 4px; font-size:0.95rem; font-weight:600; color:#222; }
    .sug-cat { font-size:0.72rem; color:#aaa; margin-bottom:3px; }
    .sug-price { color:var(--accent-dark); font-weight:700; font-size:0.9rem; }

    /* ── TRENDING ── */
    .trending-wrap { display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin-top:20px; }
    .trending-label { color:rgba(255,255,255,0.65); font-size:0.8rem; font-weight:500; white-space:nowrap; }
    .trend-chip { background:rgba(255,255,255,0.14); color:rgba(255,255,255,0.9); border:1px solid rgba(255,255,255,0.25); border-radius:50px; padding:6px 16px; font-size:0.8rem; font-weight:500; cursor:pointer; transition:all 0.2s; text-decoration:none; backdrop-filter:blur(6px); }
    .trend-chip:hover { background:var(--accent); color:#fff; border-color:var(--accent); }

    /* ── PAGE CONTENT ── */
    .search-content { padding:50px 0 80px; }
    .section-eyebrow { display:flex; align-items:center; gap:12px; margin-bottom:10px; }
    .section-eyebrow .line { flex:1; height:2px; background:linear-gradient(to right,#e0e0e0,transparent); }
    .section-eyebrow span { font-size:0.72rem; text-transform:uppercase; letter-spacing:2.5px; font-weight:700; color:var(--accent-dark); white-space:nowrap; }
    .section-title { font-family:'Montserrat',sans-serif; font-size:1.5rem; font-weight:800; color:var(--navy); margin-bottom:4px; }
    .section-sub { color:#999; font-size:0.88rem; margin-bottom:28px; }
    .category-chips { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:44px; }
    .cat-chip { display:inline-flex; align-items:center; gap:8px; background:#fff; border:1.5px solid #eee; border-radius:50px; padding:10px 18px; font-size:0.85rem; font-weight:600; color:#444; text-decoration:none; transition:all 0.25s; box-shadow:0 2px 8px rgba(0,0,0,0.04); }
    .cat-chip img { width:28px; height:28px; border-radius:50%; object-fit:cover; }
    .cat-chip .cat-icon { width:28px; height:28px; border-radius:50%; background:var(--soft-bg); display:flex; align-items:center; justify-content:center; }
    .cat-chip:hover { background:var(--navy); color:#fff; border-color:var(--navy); box-shadow:0 6px 20px rgba(14,33,135,0.18); transform:translateY(-2px); }

    /* ── PRODUCT CARD (image3 style) ── */
    .product-card-link { text-decoration:none; color:inherit; display:block; height:100%; }
    .product-card {
      background:#fff;
      border-radius:var(--card-radius);
      border:2px solid var(--accent);
      overflow:hidden;
      box-shadow:0 4px 24px rgba(151,197,29,0.10);
      transition:all 0.35s cubic-bezier(0.4,0,0.2,1);
      height:100%; position:relative;
    }
    .product-card:hover { transform:translateY(-7px); box-shadow:0 20px 56px rgba(151,197,29,0.22), 0 4px 16px rgba(0,0,0,0.08); border-color:var(--accent-dark); }

    /* image area */
    .product-card-img-wrap {
      position:relative; overflow:hidden; aspect-ratio:3/4;
      background:linear-gradient(160deg,#f0f0f0,#e4e4e4);
    }
    .product-card-img-wrap img { width:100%; height:100%; object-fit:cover; transition:transform 0.55s ease; display:block; }
    .product-card:hover .product-card-img-wrap img { transform:scale(1.08); }

    /* gradient overlay on image */
    .product-card-img-wrap::after {
      content:''; position:absolute; inset:0;
      background:linear-gradient(to top, rgba(10,26,110,0.55) 0%, rgba(10,26,110,0.10) 45%, transparent 70%);
      opacity:0; transition:opacity 0.35s;
    }
    .product-card:hover .product-card-img-wrap::after { opacity:1; }

    /* badge */
    .product-badge { position:absolute; top:12px; left:12px; background:var(--accent); color:#fff; font-size:0.65rem; font-weight:700; padding:4px 11px; border-radius:50px; text-transform:uppercase; letter-spacing:1px; z-index:2; }

    /* action buttons overlay */
    .product-card-actions {
      position:absolute; bottom:58px; left:50%; transform:translateX(-50%);
      display:flex; gap:10px; z-index:3;
      opacity:0; transition:opacity 0.3s, bottom 0.3s;
    }
    .product-card:hover .product-card-actions { opacity:1; bottom:68px; }
    .pca-btn {
      width:42px; height:42px; border-radius:50%;
      background:rgba(255,255,255,0.22); backdrop-filter:blur(8px);
      border:1.5px solid rgba(255,255,255,0.5);
      color:#fff; display:flex; align-items:center; justify-content:center;
      font-size:1rem; cursor:pointer; transition:all 0.2s; text-decoration:none;
    }
    .pca-btn:hover { background:var(--accent); border-color:var(--accent); color:#fff; transform:scale(1.12); }

    /* SHOP NOW button */
    .product-shop-now {
      position:absolute; bottom:12px; left:50%; transform:translateX(-50%);
      background:linear-gradient(135deg,var(--navy),#1a3aad);
      color:#fff; font-size:0.72rem; font-weight:700; letter-spacing:1.5px;
      text-transform:uppercase; border-radius:50px;
      padding:9px 22px; white-space:nowrap; z-index:3;
      opacity:0; transition:opacity 0.3s, bottom 0.3s;
      text-decoration:none; display:flex; align-items:center; gap:6px;
    }
    .product-card:hover .product-shop-now { opacity:1; bottom:18px; }

    /* card body */
    .product-card-body { padding:14px 16px 16px; border-top:1px solid #f5f5f5; }
    .product-cat-tag { font-size:0.68rem; color:var(--accent-dark); text-transform:uppercase; letter-spacing:1px; font-weight:700; margin-bottom:4px; }
    .product-card-name { font-size:0.88rem; font-weight:700; color:#1a1a1a; margin-bottom:8px; line-height:1.35; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .product-card-price { font-size:1.05rem; font-weight:800; color:var(--navy); }

    /* results bar */
    .results-count-bar { display:flex; align-items:center; justify-content:space-between; padding:14px 20px; background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,0.05); margin-bottom:28px; font-size:0.88rem; color:#666; }
    .results-count-bar strong { color:var(--navy); }
    .no-results-wrap { text-align:center; padding:60px 20px; }
    .no-results-icon { font-size:4rem; color:#ddd; margin-bottom:16px; }
    .no-results-wrap h3 { color:#555; font-size:1.3rem; font-weight:700; }
    .no-results-wrap p { color:#aaa; margin-bottom:24px; }
  </style>
</head>
<body>

  <header id="header" class="header sticky-top">
    <?php include 'topbar.php'; ?>
    <?php include 'main-header.php'; ?>
  </header>

  <section class="search-hero">
    <div class="container">
      <div class="text-center mb-4">
        <p class="search-hero-label">Silky Saree</p>
        <h1>Find Your Perfect Look</h1>
        <p class="hero-sub">Search from hundreds of sarees, lehengas, suits &amp; more</p>
      </div>
      <div class="search-box-wrap">
        <form action="search.php" method="GET" id="searchForm">
          <div class="search-field-wrap">
            <i class="bi bi-search srch-icon"></i>
            <input type="text" name="q" id="searchInput" placeholder="Search sarees, lehengas, colors..." value="<?php echo htmlspecialchars($search_query); ?>" autocomplete="off" autofocus>
            <button type="submit" class="search-submit-btn"><i class="bi bi-search"></i><span class="btn-text">Search</span></button>
          </div>
        </form>
        <div class="autosuggest-dropdown" id="suggestionBox"></div>
      </div>
      <div class="search-box-wrap mt-0">
        <div class="trending-wrap">
          <span class="trending-label"><i class="bi bi-fire me-1"></i>Trending:</span>
          <?php foreach($trending_searches as $t): ?>
          <a href="search.php?q=<?php echo urlencode($t); ?>" class="trend-chip"><?php echo htmlspecialchars($t); ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <main class="search-content">
    <div class="container">

      <?php if (empty($search_query)): ?>

        <?php if (!empty($categories)): ?>
        <div class="section-eyebrow"><span>Browse by Category</span><div class="line"></div></div>
        <h2 class="section-title">Shop by Collection</h2>
        <p class="section-sub">Jump straight to what you love</p>
        <div class="category-chips">
          <?php foreach ($categories as $cat):
            $cat_img = '';
            if (!empty($cat['image'])) {
              $dec = json_decode($cat['image'], true);
              $cat_img = is_array($dec) ? ($dec[0] ?? '') : $cat['image'];
              if (strpos($cat_img, './') === 0) $cat_img = substr($cat_img, 2);
            }
          ?>
          <a href="search.php?q=<?php echo urlencode($cat['name']); ?>" class="cat-chip">
            <?php if ($cat_img): ?><img src="<?php echo htmlspecialchars($base_url.$cat_img); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>"><?php else: ?><span class="cat-icon"><i class="bi bi-bag"></i></span><?php endif; ?>
            <?php echo htmlspecialchars($cat['name']); ?>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($suggested_products)): ?>
        <div class="section-eyebrow"><span>You Might Love</span><div class="line"></div></div>
        <h2 class="section-title">New Arrivals</h2>
        <p class="section-sub">Fresh picks from our latest collection</p>
        <div class="row g-3 g-md-4">
          <?php foreach ($suggested_products as $i => $product):
            $img_url = parseProductImage($product['image'], $base_url);
            $link = $base_url.'product-details/'.($product['slug'] ?? 'product-'.$product['id']);
            $badges = ['New','Hot','Trending']; $badge = $i < 3 ? $badges[$i] : '';
          ?>
          <div class="col-6 col-md-4 col-lg-3">
            <a href="<?php echo htmlspecialchars($link); ?>" class="product-card-link">
              <div class="product-card">
                <div class="product-card-img-wrap">
                  <img src="<?php echo htmlspecialchars($img_url); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy">
                  <?php if ($badge): ?><span class="product-badge"><?php echo $badge; ?></span><?php endif; ?>
                  <div class="product-card-actions">
                    <span class="pca-btn" title="Wishlist"><i class="bi bi-heart"></i></span>
                    <span class="pca-btn" title="Add to Cart"><i class="bi bi-cart3"></i></span>
                    <span class="pca-btn" title="Quick View"><i class="bi bi-eye"></i></span>
                  </div>
                  <span class="product-shop-now">Shop Now</span>
                </div>
                <div class="product-card-body">
                  <?php if (!empty($product['category_name'])): ?><div class="product-cat-tag"><?php echo htmlspecialchars($product['category_name']); ?></div><?php endif; ?>
                  <div class="product-card-name"><?php echo htmlspecialchars($product['name']); ?></div>
                  <?php if (!empty($product['product_code'])): ?><div class="text-muted" style="font-size:0.75rem; font-family:monospace; margin-bottom:4px;"><?php echo htmlspecialchars($product['product_code']); ?></div><?php endif; ?>
                  <div class="product-card-price">&#8377;<?php echo number_format($product['price']); ?></div>
                </div>
              </div>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      <?php else: ?>

        <?php if (!empty($search_results)): ?>
          <div class="results-count-bar">
            <span>Showing <strong><?php echo count($search_results); ?> results</strong> for "<strong><?php echo htmlspecialchars($search_query); ?></strong>"</span>
            <a href="search.php" class="text-muted" style="font-size:0.8rem;text-decoration:none;"><i class="bi bi-x-circle me-1"></i>Clear</a>
          </div>
          <div class="row g-3 g-md-4">
            <?php foreach ($search_results as $product):
              $img_url = parseProductImage($product['image'], $base_url);
              $link = $base_url.'product-details/'.($product['slug'] ?? 'product-'.$product['id']);
            ?>
            <div class="col-6 col-md-4 col-lg-3">
              <a href="<?php echo htmlspecialchars($link); ?>" class="product-card-link">
                <div class="product-card">
                  <div class="product-card-img-wrap">
                    <img src="<?php echo htmlspecialchars($img_url); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy">
                    <div class="product-card-actions">
                      <span class="pca-btn" title="Wishlist"><i class="bi bi-heart"></i></span>
                      <span class="pca-btn" title="Add to Cart"><i class="bi bi-cart3"></i></span>
                      <span class="pca-btn" title="Quick View"><i class="bi bi-eye"></i></span>
                    </div>
                    <span class="product-shop-now">Shop Now</span>
                  </div>
                  <div class="product-card-body">
                    <?php if (!empty($product['category_name'])): ?><div class="product-cat-tag"><?php echo htmlspecialchars($product['category_name']); ?></div><?php endif; ?>
                    <div class="product-card-name"><?php echo htmlspecialchars($product['name']); ?></div>
                    <?php if (!empty($product['product_code'])): ?><div class="text-muted" style="font-size:0.75rem; font-family:monospace; margin-bottom:4px;"><?php echo htmlspecialchars($product['product_code']); ?></div><?php endif; ?>
                    <div class="product-card-price">&#8377;<?php echo number_format($product['price']); ?></div>
                  </div>
                </div>
              </a>
            </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="no-results-wrap">
            <div class="no-results-icon"><i class="bi bi-search"></i></div>
            <h3>No results for "<?php echo htmlspecialchars($search_query); ?>"</h3>
            <p>Try different keywords or browse our collections below.</p>
            <a href="search.php" class="btn btn-outline-primary px-4">Clear Search</a>
          </div>
          <?php if (!empty($suggested_products)): ?>
          <hr class="my-5">
          <h2 class="section-title text-center">You Might Also Like</h2>
          <p class="section-sub text-center mb-4">Browse our latest arrivals</p>
          <div class="row g-3 g-md-4">
            <?php foreach ($suggested_products as $product):
              $img_url = parseProductImage($product['image'], $base_url);
              $link = $base_url.'product-details/'.($product['slug'] ?? 'product-'.$product['id']);
            ?>
            <div class="col-6 col-md-4 col-lg-3">
              <a href="<?php echo htmlspecialchars($link); ?>" class="product-card-link">
                <div class="product-card">
                  <div class="product-card-img-wrap">
                    <img src="<?php echo htmlspecialchars($img_url); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy">
                    <div class="product-card-actions">
                      <span class="pca-btn" title="Wishlist"><i class="bi bi-heart"></i></span>
                      <span class="pca-btn" title="Add to Cart"><i class="bi bi-cart3"></i></span>
                      <span class="pca-btn" title="Quick View"><i class="bi bi-eye"></i></span>
                    </div>
                    <span class="product-shop-now">Shop Now</span>
                  </div>
                  <div class="product-card-body">
                    <?php if (!empty($product['category_name'])): ?><div class="product-cat-tag"><?php echo htmlspecialchars($product['category_name']); ?></div><?php endif; ?>
                    <div class="product-card-name"><?php echo htmlspecialchars($product['name']); ?></div>
                    <?php if (!empty($product['product_code'])): ?><div class="text-muted" style="font-size:0.75rem; font-family:monospace; margin-bottom:4px;"><?php echo htmlspecialchars($product['product_code']); ?></div><?php endif; ?>
                    <div class="product-card-price">&#8377;<?php echo number_format($product['price']); ?></div>
                  </div>
                </div>
              </a>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>

    </div>
  </main>

  <?php include 'footer.php'; ?>
  
  <!-- Mobile Bottom Navigation -->
  <?php include 'mobile-bottom-nav.php'; ?>

  <script src="<?php echo $base_url; ?>assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const searchInput = document.getElementById('searchInput');
      const suggestionBox = document.getElementById('suggestionBox');
      let debounceTimer;

      // Position the fixed dropdown beneath the search field
      function positionDropdown() {
        const rect = searchInput.closest('.search-field-wrap').getBoundingClientRect();
        suggestionBox.style.top  = (rect.bottom + 10) + 'px';
        suggestionBox.style.left = rect.left + 'px';
        suggestionBox.style.width = rect.width + 'px';
      }

      document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !suggestionBox.contains(e.target)) {
          suggestionBox.classList.remove('active');
        }
      });

      window.addEventListener('resize', function () {
        if (suggestionBox.classList.contains('active')) positionDropdown();
      });

      searchInput.addEventListener('focus', function () {
        if (this.value.trim().length >= 2 && suggestionBox.innerHTML.trim() !== '') {
          positionDropdown();
          suggestionBox.classList.add('active');
        }
      });

      searchInput.addEventListener('input', function () {
        const query = this.value.trim();
        clearTimeout(debounceTimer);
        if (query.length < 2) { suggestionBox.classList.remove('active'); suggestionBox.innerHTML = ''; return; }
        debounceTimer = setTimeout(() => {
          fetch('search.php?ajax=1&q=' + encodeURIComponent(query))
            .then(r => r.json())
            .then(data => {
              suggestionBox.innerHTML = '';
              if (data.length > 0) {
                data.forEach(p => {
                  const a = document.createElement('a');
                  a.href = p.link;
                  a.className = 'suggestion-item';
                  a.innerHTML = '<img src="'+p.image_url+'" class="suggestion-img" alt="'+p.name+'">'+'<div class="suggestion-info"><h6>'+(p.product_code && p.product_code.toLowerCase().includes(query.toLowerCase()) ? p.product_code + ' - ' : '')+p.name+'</h6><div class="sug-cat">'+(p.category_name||'')+'</div><div class="sug-price">&#8377;'+parseFloat(p.price).toLocaleString('en-IN')+'</div></div>'+'<i class="bi bi-arrow-up-left ms-auto text-muted" style="font-size:0.8rem;"></i>';
                  suggestionBox.appendChild(a);
                });
                const viewAll = document.createElement('a');
                viewAll.href = 'search.php?q=' + encodeURIComponent(query);
                viewAll.className = 'suggestion-item justify-content-center';
                viewAll.style.cssText = 'background:#f8f9fa;color:#7aab0a;font-weight:600;font-size:0.85rem;';
                viewAll.innerHTML = '<i class="bi bi-search me-2"></i>View all results for "' + query + '"';
                suggestionBox.appendChild(viewAll);
              } else {
                suggestionBox.innerHTML = '<div class="p-4 text-center text-muted" style="font-size:0.88rem;"><i class="bi bi-emoji-frown me-2"></i>No products found for "<strong>'+query+'</strong>"</div>';
              }
              positionDropdown();
              suggestionBox.classList.add('active');
            })
            .catch(err => console.error('Search error:', err));
        }, 280);
      });
    });
  </script>
</body>
</html>
