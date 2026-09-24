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
    $sql = "SELECT p.*, c.name as category_name,
            GROUP_CONCAT(DISTINCT CONCAT(col.color_name, '|', col.color_code) SEPARATOR '~') as product_colors,
            (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND status = 'approved') as review_count,
            (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') as avg_rating
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            LEFT JOIN colors col ON pc.color_id = col.id AND col.status = 'active'
            WHERE p.status = 'active' 
            AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR p.product_code LIKE ?)
            GROUP BY p.id
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
$sug_sql = "SELECT p.*, c.name as category_name,
            GROUP_CONCAT(DISTINCT CONCAT(col.color_name, '|', col.color_code) SEPARATOR '~') as product_colors,
            (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND status = 'approved') as review_count,
            (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') as avg_rating
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            LEFT JOIN colors col ON pc.color_id = col.id AND col.status = 'active'
            WHERE p.status='active' 
            GROUP BY p.id
            ORDER BY p.created_at DESC 
            LIMIT 8";
$sug_res = mysqli_query($conn, $sug_sql);
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
    if (strpos($img, 'http') === 0 || strpos($img, '/') === 0) return $img;
    return $base_url . $img;
}

// Helper to render product card identical to products.php
function renderProductCard($product, $base_url, $badge = '', $badgeClass = '') {
    $mainImage = parseProductImage($product['image'] ?? '', $base_url);
    $product_slug = !empty($product['slug']) ? $product['slug'] : 'product-' . $product['id'];
    $link = $base_url . 'product-details/' . htmlspecialchars($product_slug);

    if (empty($badge)) {
        if (!empty($product['discount_price'])) {
            $badge = 'Sale';
            $badgeClass = 'bs3d-badge-sale';
        } elseif (!empty($product['created_at']) && strtotime($product['created_at']) > strtotime('-7 days')) {
            $badge = 'New';
            $badgeClass = 'trending-badge';
        }
    }

    $colors = [];
    if (!empty($product['product_colors'])) {
        $colorEntries = explode('~', $product['product_colors']);
        foreach ($colorEntries as $colorEntry) {
            $colorParts = explode('|', $colorEntry);
            if (count($colorParts) == 2) {
                $colors[] = [
                    'name' => $colorParts[0],
                    'code' => $colorParts[1]
                ];
            }
        }
    }

    $rating = isset($product['avg_rating']) ? round(floatval($product['avg_rating']), 1) : 0;
    $reviewCount = isset($product['review_count']) ? intval($product['review_count']) : 0;
    ?>
    <div class="col-6 col-md-4 col-lg-3">
      <div class="bs3d-card product-item h-100" 
           data-product-id="<?php echo $product['id']; ?>" 
           data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
           data-product-price="<?php echo number_format($product['price'], 2, '.', ''); ?>" 
           data-product-image="<?php echo htmlspecialchars($mainImage); ?>">
        <div class="bs3d-card-img">
          <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.src='<?php echo $base_url; ?>assets/img/product/saree1.png'">
          <?php if (!empty($product['discount_price'])): ?>
            <div class="bs3d-badge bs3d-badge-sale">Sale</div>
          <?php elseif (!empty($badge)): ?>
            <div class="bs3d-badge <?php echo htmlspecialchars($badgeClass); ?>"><?php echo htmlspecialchars($badge); ?></div>
          <?php endif; ?>

          <?php if (!empty($product['youtube_video_id'])): ?>
          <div class="product-video" data-video-id="<?php echo htmlspecialchars($product['youtube_video_id']); ?>"></div>
          <?php endif; ?>

          <div class="bs3d-overlay">
            <div class="bs3d-overlay-actions">
              <button class="bs3d-action-btn wishlist-btn" data-product-id="<?php echo $product['id']; ?>" title="Wishlist">
                <i class="bi bi-heart"></i>
              </button>
              <button class="bs3d-action-btn add-to-cart" data-product-id="<?php echo $product['id']; ?>" title="Add to Cart">
                <i class="bi bi-cart-plus"></i>
              </button>
              <a href="<?php echo $link; ?>" class="bs3d-action-btn" title="View Details">
                <i class="bi bi-eye"></i>
              </a>
            </div>
            <a href="<?php echo $link; ?>" class="bs3d-shop-btn custom-glass-btn" style="text-decoration: none;">SHOP NOW</a>
          </div>
        </div>
        <div class="bs3d-card-info">
          <span class="bs3d-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></span>
          <h4 class="bs3d-name">
            <a href="<?php echo $link; ?>">
              <?php echo htmlspecialchars($product['name']); ?>
            </a>
          </h4>
          <div class="bs3d-stars">
            <?php 
            for ($s = 1; $s <= 5; $s++) {
                if ($s <= $rating) {
                    echo '<i class="bi bi-star-fill"></i>';
                } elseif ($s - 0.5 <= $rating) {
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
          <?php if (!empty($colors)): ?>
          <div class="color-swatches mt-2">
            <?php foreach (array_slice($colors, 0, 3) as $index => $color): ?>
            <span class="swatch <?php echo $index == 0 ? 'active' : ''; ?>" style="background-color: <?php echo htmlspecialchars($color['code']); ?>;" title="<?php echo htmlspecialchars($color['name']); ?>"></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
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
  <link href="assets/img/favicon/favicon.ico" rel="icon" type="image/x-icon">
  <link href="assets/img/favicon/favicon-32x32.png" rel="icon" type="image/png" sizes="32x32">
  <link href="assets/img/favicon/favicon-16x16.png" rel="icon" type="image/png" sizes="16x16">
  <link href="assets/img/favicon/apple-touch-icon.png" rel="apple-touch-icon" sizes="180x180">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>

  <!-- Pass PHP session data to JavaScript -->
  <script>
    const isUserLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
    const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '0'; ?>;
    const baseUrl = '<?php echo $base_url; ?>';
  </script>
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

    /* ── PRODUCT CARD (Same as products.php) ── */
    .bs3d-card {
      border-radius: 20px !important;
      border: 2px solid #97c51d !important;
      overflow: hidden;
      background: #ffffff;
      box-shadow: 0 8px 30px rgba(0,0,0,0.10), 0 0 0 1px rgba(0,0,0,0.06);
      transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      display: flex;
      flex-direction: column;
    }
    .bs3d-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 20px 50px rgba(151,197,29,0.22), 0 4px 16px rgba(0,0,0,0.08);
      border-color: #7aab0a !important;
    }
    .bs3d-card-img {
      position: relative;
      width: 100%;
      aspect-ratio: 3/4;
      overflow: hidden;
      background: #f0f0f0;
    }
    .bs3d-card-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: top center;
      display: block;
      transition: transform 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }
    .bs3d-card:hover .bs3d-card-img img {
      transform: scale(1.07);
    }
    .bs3d-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.3) 50%, transparent 100%);
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      align-items: center;
      padding: 20px 16px;
      opacity: 0;
      transition: opacity 0.35s ease;
      z-index: 6;
    }
    .bs3d-card:hover .bs3d-overlay {
      opacity: 1;
    }
    .bs3d-overlay-actions {
      display: flex;
      gap: 10px;
      margin-bottom: 14px;
    }
    .bs3d-action-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: rgba(255,255,255,0.15);
      border: 1.5px solid rgba(255,255,255,0.3);
      color: #ffffff;
      font-size: 16px;
      cursor: pointer;
      backdrop-filter: blur(8px);
      transition: background 0.25s ease, border-color 0.25s ease, transform 0.2s ease;
      text-decoration: none;
    }
    .bs3d-action-btn:hover {
      background: #97c51d;
      border-color: #97c51d;
      transform: scale(1.12);
      color: #fff;
    }
    .bs3d-action-btn.active {
      background: #0e2187;
      border-color: #0e2187;
      color: #fff;
    }
    .bs3d-shop-btn {
      display: inline-block;
      padding: 10px 28px;
      border-radius: 50px;
      background: linear-gradient(135deg, #0e2187, #97c51d);
      color: #fff;
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.5px;
      text-decoration: none;
      text-transform: uppercase;
      transition: transform 0.25s ease, box-shadow 0.25s ease;
      box-shadow: 0 6px 20px rgba(151,197,29,0.4);
    }
    .bs3d-shop-btn:hover {
      transform: translateY(-2px) scale(1.04);
      box-shadow: 0 10px 30px rgba(151,197,29,0.5);
      color: #fff;
    }
    .bs3d-badge {
      position: absolute;
      top: 14px;
      left: 14px;
      z-index: 5;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      padding: 4px 10px;
      border-radius: 50px;
      color: #fff;
    }
    .bs3d-badge.trending-badge {
      background: #97c51d !important;
      color: #fff !important;
    }
    .bs3d-badge.bs3d-badge-sale {
      background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
      color: #fff !important;
    }
    .bs3d-badge.bs3d-badge-limited {
      background: linear-gradient(135deg, #0e2187, #1e3dbd) !important;
      color: #fff !important;
    }
    .bs3d-badge.bs3d-badge-trending {
      background: linear-gradient(135deg, #16a34a, #15803d) !important;
      color: #fff !important;
    }
    .bs3d-card-info {
      padding: 18px 18px 20px;
      background: #ffffff;
      flex: 1;
      display: flex;
      flex-direction: column;
    }
    .bs3d-category {
      display: block;
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.2px;
      color: #97c51d;
      margin-bottom: 6px;
    }
    .bs3d-name {
      font-size: 15px;
      font-weight: 600;
      color: #0e2187;
      margin: 0 0 8px;
      line-height: 1.3;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .bs3d-name a {
      color: inherit;
      text-decoration: none;
      transition: color 0.2s;
    }
    .bs3d-name a:hover {
      color: #97c51d;
    }
    .bs3d-stars {
      display: flex;
      align-items: center;
      gap: 3px;
      font-size: 12px;
      color: #f59e0b;
      margin-bottom: 8px;
    }
    .bs3d-stars span {
      color: rgba(0,0,0,0.4);
      font-size: 11px;
      margin-left: 4px;
    }
    .bs3d-price {
      font-size: 16px;
      font-weight: 700;
      color: #0e2187;
      margin-bottom: 8px;
    }
    .bs3d-old-price {
      font-size: 13px;
      font-weight: 400;
      color: rgba(0,0,0,0.35);
      text-decoration: line-through;
      margin-left: 5px;
    }
    .color-swatches {
      display: flex;
      gap: 6px;
      margin-top: 4px;
    }
    .swatch {
      width: 16px;
      height: 16px;
      border-radius: 50%;
      cursor: pointer;
      position: relative;
      transition: all 0.2s ease;
      display: inline-block;
    }
    .swatch:hover {
      transform: scale(1.15);
    }
    .swatch.active:after {
      content: "";
      position: absolute;
      top: -3px;
      left: -3px;
      right: -3px;
      bottom: -3px;
      border: 1px solid #0e2187;
      border-radius: 50%;
    }
    /* Video on hover styles */
    .product-video {
      position: absolute;
      inset: 0;
      background: #000;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.35s ease;
      z-index: 5;
      overflow: hidden;
    }
    .product-video.playing { opacity: 1; }
    .product-video iframe {
      position: absolute;
      width: 200%;
      height: 200%;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      border: 0;
      pointer-events: none;
    }
    .product-video .vid-blocker {
      position: absolute;
      inset: 0;
      z-index: 9;
      pointer-events: none;
    }
    .product-video .vid-mask-top,
    .product-video .vid-mask-bottom {
      position: absolute;
      left: 0;
      right: 0;
      z-index: 10;
      pointer-events: none;
    }
    .product-video .vid-mask-top    { top: 0;    height: 12%; background: #000; }
    .product-video .vid-mask-bottom { bottom: 0; height: 12%; background: #000; }
    .toast-container { z-index: 99999 !important; }

    @media (max-width: 575.98px) {
      .bs3d-card-info { padding: 12px 12px 14px; }
      .bs3d-name { font-size: 13px; }
      .bs3d-price { font-size: 14px; }
      .bs3d-overlay {
        opacity: 1;
        background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, rgba(0,0,0,0.1) 40%, transparent 100%);
      }
      .bs3d-overlay-actions { display: none; }
      .bs3d-shop-btn { padding: 7px 18px; font-size: 11px; }
    }

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
            $badges = ['New', 'Hot', 'Trending'];
            $classes = ['trending-badge', 'bs3d-badge-limited', 'bs3d-badge-trending'];
            $badge = $i < 3 ? $badges[$i] : '';
            $badgeClass = $i < 3 ? $classes[$i] : '';
            renderProductCard($product, $base_url, $badge, $badgeClass);
          endforeach; ?>
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
              renderProductCard($product, $base_url);
            endforeach; ?>
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
            <?php foreach ($suggested_products as $i => $product):
              $badges = ['New', 'Hot', 'Trending'];
              $classes = ['trending-badge', 'bs3d-badge-limited', 'bs3d-badge-trending'];
              $badge = $i < 3 ? $badges[$i] : '';
              $badgeClass = $i < 3 ? $classes[$i] : '';
              renderProductCard($product, $base_url, $badge, $badgeClass);
            endforeach; ?>
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

      // ==============================================
      // PRODUCT INTERACTIONS (Same as products.php)
      // ==============================================
      let wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];
      let cart = JSON.parse(localStorage.getItem('cart')) || [];

      function showToast(message, type = 'success') {
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
          toastContainer = document.createElement('div');
          toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
          toastContainer.style.zIndex = '99999';
          document.body.appendChild(toastContainer);
        }

        let bgColor = type === 'success' ? '#8AC53E' : (type === 'danger' || type === 'error' ? '#e74c3c' : (type === 'warning' ? '#f39c12' : '#0e2187'));
        let iconClass = type === 'success' ? 'bi-check-circle-fill' : (type === 'danger' || type === 'error' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill');

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
      }

      function updateWishlistButtons() {
        document.querySelectorAll('.wishlist-btn').forEach(btn => {
          const pid = btn.getAttribute('data-product-id');
          const inWishlist = wishlist.some(item => String(item.id) === String(pid));
          const icon = btn.querySelector('i');
          if (inWishlist) {
            btn.classList.add('active');
            if (icon) { icon.classList.remove('bi-heart'); icon.classList.add('bi-heart-fill'); }
          } else {
            btn.classList.remove('active');
            if (icon) { icon.classList.remove('bi-heart-fill'); icon.classList.add('bi-heart'); }
          }
        });
      }

      function updateCartCount() {
        const totalItems = cart.reduce((total, item) => total + (parseInt(item.quantity) || 1), 0);
        document.querySelectorAll('.cart-count, .badge').forEach(element => {
          if (element.closest('.header-action-btn') || element.classList.contains('cart-count')) {
            element.textContent = totalItems;
            element.style.display = totalItems > 0 ? 'inline' : 'none';
          }
        });
      }

      function updateCartCountFromDatabase() {
        fetch(baseUrl + 'cart.php?action=get_cart')
          .then(response => response.json())
          .then(data => {
            if (data.status === 'success' && data.cart) {
              const totalItems = data.cart.reduce((total, item) => total + item.quantity, 0);
              document.querySelectorAll('.cart-count, .badge').forEach(element => {
                if (element.closest('.header-action-btn') || element.classList.contains('cart-count')) {
                  element.textContent = totalItems;
                  element.style.display = totalItems > 0 ? 'inline' : 'none';
                }
              });
            }
          })
          .catch(err => console.error('Error fetching cart:', err));
      }

      function updateWishlistCount() {
        document.querySelectorAll('.wishlist-count').forEach(element => {
          element.textContent = wishlist.length;
          element.style.display = wishlist.length > 0 ? 'inline' : 'none';
        });
      }

      function addToCartDatabase(product) {
        const requestBody = {
          product_id: parseInt(product.id),
          quantity: parseInt(product.quantity) || 1
        };

        fetch(baseUrl + 'cart.php?action=add_to_cart', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(requestBody)
        })
        .then(response => response.json())
        .then(data => {
          if (data.status === 'success') {
            showToast(data.message || 'Product added to cart!', 'success');
            updateCartCountFromDatabase();
          } else {
            showToast(data.message || 'Failed to add to cart', 'danger');
          }
        })
        .catch(error => {
          console.error('Error adding to cart:', error);
          showToast('Failed to add to cart', 'danger');
        });
      }

      function addToCartLocalStorage(product) {
        const existingIndex = cart.findIndex(item => String(item.id) === String(product.id));
        if (existingIndex > -1) {
          cart[existingIndex].quantity = (parseInt(cart[existingIndex].quantity) || 1) + 1;
        } else {
          cart.push(product);
        }
        localStorage.setItem('cart', JSON.stringify(cart));
        showToast('Product added to cart!', 'success');
        updateCartCount();
      }

      function attachProductEventListeners() {
        // Add to Cart
        document.querySelectorAll('.add-to-cart').forEach(button => {
          button.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const productItem = this.closest('.product-item');
            if (!productItem) return;
            const productId = productItem.getAttribute('data-product-id');
            const productName = productItem.getAttribute('data-product-name');
            const productPrice = parseFloat(productItem.getAttribute('data-product-price')) || 0;
            const productImage = productItem.getAttribute('data-product-image') || '';

            const product = {
              id: productId,
              name: productName,
              price: productPrice,
              image: productImage,
              quantity: 1
            };

            if (isUserLoggedIn) {
              addToCartDatabase(product);
            } else {
              addToCartLocalStorage(product);
            }
          });
        });

        // Wishlist
        document.querySelectorAll('.wishlist-btn').forEach(button => {
          button.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const productItem = this.closest('.product-item');
            if (!productItem) return;
            const productId = productItem.getAttribute('data-product-id');
            const productName = productItem.getAttribute('data-product-name');
            const productPrice = parseFloat(productItem.getAttribute('data-product-price')) || 0;
            const productImage = productItem.getAttribute('data-product-image') || '';

            const product = {
              id: productId,
              name: productName,
              price: productPrice,
              image: productImage
            };

            const existingIndex = wishlist.findIndex(item => String(item.id) === String(productId));
            const icon = this.querySelector('i');

            if (existingIndex > -1) {
              wishlist.splice(existingIndex, 1);
              showToast('Product removed from wishlist!', 'info');
              if (icon) { icon.classList.remove('bi-heart-fill'); icon.classList.add('bi-heart'); }
              this.classList.remove('active');
            } else {
              wishlist.push(product);
              showToast('Product added to wishlist!', 'success');
              if (icon) { icon.classList.remove('bi-heart'); icon.classList.add('bi-heart-fill'); }
              this.classList.add('active');
            }

            localStorage.setItem('wishlist', JSON.stringify(wishlist));
            updateWishlistCount();
          });
        });
      }

      function initVideoOnHover() {
        document.querySelectorAll('.product-item').forEach(item => {
          const videoContainer = item.querySelector('.product-video');
          if (videoContainer && !item.hasAttribute('data-video-initialized')) {
            item.setAttribute('data-video-initialized', 'true');

            if (!videoContainer.querySelector('.vid-mask-top')) {
              const blocker    = document.createElement('div');
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
              timeoutId = setTimeout(() => {
                const videoId = videoContainer.dataset.videoId;
                if (!iframe && videoId) {
                  iframe = document.createElement('iframe');
                  iframe.src = `https://www.youtube.com/embed/${videoId}?autoplay=1&mute=1&controls=0&modestbranding=1&rel=0&showinfo=0&iv_load_policy=3&fs=0&disablekb=1&loop=1&playlist=${videoId}&playsinline=1`;
                  iframe.setAttribute('allow', 'autoplay; encrypted-media');
                  iframe.tabIndex = -1;
                  const topMask = videoContainer.querySelector('.vid-mask-top');
                  videoContainer.insertBefore(iframe, topMask);
                }
                videoContainer.classList.add('playing');
              }, 250);
            });

            item.addEventListener('mouseleave', () => {
              clearTimeout(timeoutId);
              videoContainer.classList.remove('playing');
              if (iframe) {
                iframe.remove();
                iframe = null;
              }
            });
          }
        });
      }

      // Initialize product features
      attachProductEventListeners();
      updateWishlistButtons();
      updateWishlistCount();
      initVideoOnHover();

      if (isUserLoggedIn) {
        updateCartCountFromDatabase();
      } else {
        updateCartCount();
      }
    });
  </script>
</body>
</html>
