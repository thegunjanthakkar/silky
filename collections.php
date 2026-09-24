<?php
session_start();
require_once 'db_config.php';
$base_url = defined('BASE_URL') ? BASE_URL : '/';

// Ensure category placeholder directory & image exist
$cat_placeholder_dir = __DIR__ . '/assets/img/category';
$cat_placeholder_file = $cat_placeholder_dir . '/placeholder.jpg';
if (!file_exists($cat_placeholder_file)) {
    if (!is_dir($cat_placeholder_dir)) {
        @mkdir($cat_placeholder_dir, 0755, true);
    }
    $sample_src = __DIR__ . '/assets/img/product/saree1.webp';
    if (!file_exists($sample_src)) {
        $sample_src = __DIR__ . '/assets/img/product/saree1.png';
    }
    if (file_exists($sample_src)) {
        @copy($sample_src, $cat_placeholder_file);
    }
}

// Fetch all active categories
$categories_query = "SELECT id, name, description, image, created_at 
                     FROM categories 
                     WHERE status = 'active' 
                     ORDER BY display_order ASC, name ASC";
$categories_result = mysqli_query($conn, $categories_query);
$categories = [];

if ($categories_result && mysqli_num_rows($categories_result) > 0) {
    while ($category = mysqli_fetch_assoc($categories_result)) {
        $categories[] = $category;
    }
}

// Fetch 5 random active products for Suggested Products section
$suggested_products = [];
if (!empty($conn)) {
    $suggested_query = "SELECT p.*, c.name as category_name,
                        (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND status = 'approved') as review_count,
                        (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') as avg_rating
                        FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        WHERE p.status = '1' OR p.status = 'active'
                        ORDER BY RAND() 
                        LIMIT 5";
    $suggested_result = mysqli_query($conn, $suggested_query);
    if ($suggested_result && mysqli_num_rows($suggested_result) > 0) {
        while ($sp = mysqli_fetch_assoc($suggested_result)) {
            $suggested_products[] = $sp;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Collections - Silky Saree</title>
  <meta name="description" content="Browse our exclusive collection of sarees">
  <meta name="keywords" content="saree collections, silk sarees, designer sarees">

  <!-- Favicons -->
  <link href="<?php echo $base_url; ?>assets/img/favicon/favicon.ico" rel="icon" type="image/x-icon">
  <link href="<?php echo $base_url; ?>assets/img/favicon/favicon-32x32.png" rel="icon" type="image/png" sizes="32x32">
  <link href="<?php echo $base_url; ?>assets/img/favicon/favicon-16x16.png" rel="icon" type="image/png" sizes="16x16">
  <link href="<?php echo $base_url; ?>assets/img/favicon/apple-touch-icon.png" rel="apple-touch-icon" sizes="180x180">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="<?php echo $base_url; ?>assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?php echo $base_url; ?>assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="<?php echo $base_url; ?>assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
  <link href="<?php echo $base_url; ?>assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="<?php echo $base_url; ?>assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">

  <!-- Main CSS File -->
  <link href="<?php echo $base_url; ?>assets/css/main.css" rel="stylesheet">

  <style>
    .collection-card {
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.08);
      aspect-ratio: 3/4;
    }
    .collection-card:hover .hover-overlay-btn { opacity: 1 !important; }
    .collection-card:hover img { transform: scale(1.05); }

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

    .collection-card:hover .custom-glass-btn::before {
      left: 150%;
    }

    .no-collections {
      text-align: center;
      padding: 80px 20px;
    }

    .no-collections i {
      font-size: 4rem;
      color: #ddd;
      margin-bottom: 20px;
    }

    .no-collections h3 {
      color: #666;
      margin-bottom: 10px;
    }

    .no-collections p {
      color: #999;
    }

    /* 5-Column Grid for Suggested Products */
    .suggested-grid .col-5-card {
      flex: 0 0 auto;
      width: 100%;
    }
    @media (min-width: 480px) {
      .suggested-grid .col-5-card {
        width: 50%;
      }
    }
    @media (min-width: 768px) {
      .suggested-grid .col-5-card {
        width: 33.333333%;
      }
    }
    @media (min-width: 1200px) {
      .suggested-grid .col-5-card {
        width: 20%;
      }
    }
    .suggested-grid .bs3d-card {
      box-shadow: 0 8px 24px rgba(14, 33, 135, 0.08);
      border-radius: 16px;
      overflow: hidden;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .suggested-grid .bs3d-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 30px rgba(14, 33, 135, 0.14);
    }
  </style>
</head>

<body class="collections-page">

  <header id="header" class="header sticky-top">
    <!-- Top Bar -->
    <?php include './topbar.php'; ?>
    <!-- Main Header -->
    <?php include './main-header.php'; ?>
  </header>

  <main class="main">

    <!-- Page Title -->
    <div class="page-title light-background">
      <div class="container d-lg-flex justify-content-between align-items-center">
        <h1 class="mb-2 mb-lg-0">Our Collections</h1>
        <nav class="breadcrumbs">
          <ol>
            <li><a href="<?php echo $base_url; ?>index.php">Home</a></li>
            <li class="current">Collections</li>
          </ol>
        </nav>
      </div>
    </div><!-- End Page Title -->

    <!-- Collections Section -->
    <section id="collections" class="collections section">
      <div class="container" data-aos="fade-up" data-aos-delay="100">

        <?php if (!empty($categories)): ?>
          <div class="row gy-4">
            <?php foreach ($categories as $i => $category): ?>
              <?php
              // Process category image
              $category_image = $base_url . 'assets/img/product/saree1.webp';
              if (!empty($category['image'])) {
                // Handle both relative and absolute paths
                $image_path = $category['image'];
                if (strpos($image_path, './') === 0) {
                  $image_path = substr($image_path, 2); // Remove './'
                }
                if (file_exists(__DIR__ . '/' . $image_path)) {
                  $category_image = $base_url . $image_path;
                } elseif (file_exists($image_path)) {
                  $category_image = $base_url . $image_path;
                }
              }
              
              // Create URL-friendly slug from category name
              $category_slug = strtolower(trim($category['name']));
              $category_slug = preg_replace('/[^a-z0-9]+/', '-', $category_slug);
              $category_slug = trim($category_slug, '-');
              $delay = 100 + ($i * 100);
              ?>
              <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                <div class="collection-card position-relative overflow-hidden">
                  <a href="<?php echo $base_url; ?>products/<?php echo htmlspecialchars($category_slug); ?>" class="d-block h-100 w-100 position-relative text-decoration-none">
                    <img src="<?php echo htmlspecialchars($category_image); ?>" 
                         alt="<?php echo htmlspecialchars($category['name']); ?>"
                         class="w-100 h-100 object-fit-cover"
                         style="object-fit: cover; transition: transform 0.5s ease;"
                         onerror="this.onerror=null; this.src='<?php echo $base_url; ?>assets/img/product/saree1.webp';">
                    
                    <!-- Gradient Overlay -->
                    <div class="position-absolute bottom-0 start-0 w-100 h-50" style="background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0) 100%); pointer-events: none;"></div>
                    
                    <!-- Text Content -->
                    <div class="position-absolute bottom-0 start-0 w-100 text-center pb-4 px-3" style="z-index: 2;">
                      <h4 class="text-white mb-1" style="font-size: 1.6rem; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                        <?php echo htmlspecialchars($category['name']); ?>
                      </h4>
                      <?php if (!empty($category['description'])): ?>
                      <p class="text-white-50 small mb-0 text-truncate" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">
                        <?php echo htmlspecialchars($category['description']); ?>
                      </p>
                      <?php endif; ?>
                    </div>

                    <!-- Hover Overlay Button -->
                    <div class="position-absolute top-50 start-50 translate-middle opacity-0 transition-opacity duration-300 hover-overlay-btn" style="z-index: 3; transition: opacity 0.3s ease;">
                      <span class="custom-glass-btn">VIEW COLLECTION</span>
                    </div>
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="no-collections">
            <i class="bi bi-inbox"></i>
            <h3>No Collections Available</h3>
            <p>We're currently updating our collections. Please check back soon!</p>
            <a href="index.php" class="btn btn-primary mt-3">
              <i class="bi bi-house-door me-2"></i>Back to Home
            </a>
          </div>
        <?php endif; ?>

      </div>
    </section><!-- /Collections Section -->

    <!-- Suggested Products Section -->
    <section id="suggested-products" class="suggested-products section light-background">
      <div class="container section-title" data-aos="fade-up">
        <h2>Discover Your Next Favorite</h2>
        <p>Take a look before it disappears.</p>
      </div>

      <div class="container" data-aos="fade-up" data-aos-delay="100">
        <?php if (!empty($suggested_products)): ?>
          <div class="row gy-4 justify-content-center suggested-grid">
            <?php foreach ($suggested_products as $index => $product): 
              // Process main image
              $mainImage = $base_url . 'assets/img/product/saree1.png';
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
                          'admin/uploads/' . $img_name,
                          'assets/img/product/' . $img_name,
                          $img_name
                      ];
                      foreach ($possiblePaths as $path) {
                          if (file_exists(__DIR__ . '/' . $path)) {
                              $mainImage = $base_url . $path;
                              break;
                          } elseif (file_exists($path)) {
                              $mainImage = $base_url . $path;
                              break;
                          }
                      }
                  }
              }

              $product_slug = !empty($product['slug']) ? $product['slug'] : 'product-' . $product['id'];
              $product_link = $base_url . 'product-details/' . htmlspecialchars($product_slug);
            ?>
              <div class="col-5-card" data-aos="fade-up" data-aos-delay="<?php echo 100 + ($index * 100); ?>">
                <div class="bs3d-card product-item h-100"
                     data-product-name="<?php echo htmlspecialchars($product['name']); ?>" 
                     data-product-price="<?php echo htmlspecialchars($product['price']); ?>" 
                     data-product-image="<?php echo htmlspecialchars($mainImage); ?>">
                  <div class="bs3d-card-img">
                    <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.onerror=null; this.src='<?php echo $base_url; ?>assets/img/product/saree1.webp';">

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
                    <span class="bs3d-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Sarees'); ?></span>
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
        <?php else: ?>
          <p class="text-center text-muted">No suggested products available at the moment.</p>
        <?php endif; ?>
      </div>
    </section><!-- /Suggested Products Section -->

  </main>

  <!-- Footer -->
  <?php include './footer.php'; ?>

  <!-- Scroll Top -->
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center">
    <i class="bi bi-arrow-up-short"></i>
  </a>

  <!-- Preloader -->
  <div id="preloader"></div>
  <script>
    // Safeguard preloader dismissal to prevent indefinite loading on slow connections/assets
    (function() {
      function dismissPreloader() {
        var p = document.getElementById('preloader');
        if (p) {
          p.style.transition = 'opacity 0.3s ease';
          p.style.opacity = '0';
          setTimeout(function() {
            if (p && p.parentNode) p.parentNode.removeChild(p);
          }, 300);
        }
      }
      if (document.readyState === 'complete') {
        dismissPreloader();
      } else {
        window.addEventListener('load', dismissPreloader);
        document.addEventListener('DOMContentLoaded', function() {
          setTimeout(dismissPreloader, 600);
        });
        setTimeout(dismissPreloader, 1500);
      }
    })();
  </script>

  <!-- Mobile Bottom Navigation -->
  <?php include 'mobile-bottom-nav.php'?>

  <!-- Vendor JS Files -->
  <script src="<?php echo $base_url; ?>assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="<?php echo $base_url; ?>assets/vendor/php-email-form/validate.js"></script>
  <script src="<?php echo $base_url; ?>assets/vendor/swiper/swiper-bundle.min.js"></script>
  <script src="<?php echo $base_url; ?>assets/vendor/aos/aos.js"></script>
  <script src="<?php echo $base_url; ?>assets/vendor/glightbox/js/glightbox.min.js"></script>
  <script src="<?php echo $base_url; ?>assets/vendor/purecounter/purecounter_vanilla.js"></script>

  <!-- Main JS File -->
  <script src="<?php echo $base_url; ?>assets/js/main.js"></script>

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
