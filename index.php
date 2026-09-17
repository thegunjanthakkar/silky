<?php
// Start session for header functionality
session_start();
require_once 'db_config.php';

// Fetch global website settings
$website_settings = [];
$settings_res = mysqli_query($conn, "SELECT * FROM website_settings");
if ($settings_res) {
    while ($row = mysqli_fetch_assoc($settings_res)) {
        $website_settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Fetch active categories for Collections section
$index_categories = [];
$cat_res = mysqli_query($conn, "SELECT id, name, description, image FROM categories WHERE status = 'active' ORDER BY display_order ASC, name ASC");
if ($cat_res) {
    while ($cat = mysqli_fetch_assoc($cat_res)) {
        $index_categories[] = $cat;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Silky Saree - A silky touch to beauty.</title>
  <meta name="description" content="">
  <meta name="keywords" content="">

  <!-- Favicons -->
  <link href="assets/img/silky-jpg.jpg" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="assets/vendor/drift-zoom/drift-basic.css" rel="stylesheet">

  <!-- Intl-tel-input CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/css/intlTelInput.css">
  <style>
    .cr-input-wrap .iti { width: 100%; }
    .cr-input-wrap .iti__flag-container { z-index: 5; }
  </style>

  <!-- Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">

  
</head>

<body class="index-page">

  <header id="header" class="header sticky-top">
   <!-- Top Bar -->
    <?php include 'topbar.php'; ?>

    <!-- Main Header -->
    <?php include 'main-header.php'; ?>
    
    <!-- Mobile Search Form -->
    <div class="collapse" id="mobileSearch">
      <div class="container">
        <form class="search-form">
          <div class="input-group">
            <input type="text" class="form-control" placeholder="Search for products">
            <button class="btn" type="submit">
              <i class="bi bi-search"></i>
            </button>
          </div>
        </form>
      </div>
    </div>

  </header>

  <main class="main">

    <!-- CSS and JS for Full-Screen Hero & Scroll Snapping -->
    <style>
      #hero {
        height: calc(100vh - var(--header-height, 80px));
        min-height: calc(100vh - var(--header-height, 80px));
        overflow: hidden;
        padding: 0 !important;
        margin: 0 !important;
      }

      #heroCarousel {
        height: 100%;
      }

      #heroCarousel .carousel-inner {
        height: 100%;
      }

      #heroCarousel .carousel-item {
        height: 100%;
      }

      #heroCarousel .carousel-img {
        height: 100%;
        width: 100%;
        object-fit: cover;
        display: block;
      }
    </style>

    <script>
      (function () {
        // Set --header-height CSS variable dynamically
        function updateHeaderHeight() {
          const header = document.getElementById('header');
          if (header) {
            document.documentElement.style.setProperty('--header-height', header.offsetHeight + 'px');
          }
        }
        window.addEventListener('resize', updateHeaderHeight);
        document.addEventListener('DOMContentLoaded', updateHeaderHeight);
        updateHeaderHeight();

        // Programmatic scroll snap: only between hero and next section
        let isSnapping = false;

        function getHero() { return document.getElementById('hero'); }
        function getBestSellers() { return document.getElementById('best-sellers'); }

        function snapToBestSellers() {
          if (isSnapping) return;
          isSnapping = true;
          const el = getBestSellers();
          if (el) {
            const headerH = document.getElementById('header')?.offsetHeight || 0;
            const top = el.getBoundingClientRect().top + window.scrollY - headerH;
            window.scrollTo({ top: top, behavior: 'smooth' });
          }
          setTimeout(() => { isSnapping = false; }, 800);
        }

        function snapToHero() {
          if (isSnapping) return;
          isSnapping = true;
          window.scrollTo({ top: 0, behavior: 'smooth' });
          setTimeout(() => { isSnapping = false; }, 800);
        }

        function isHeroInView() {
          const hero = getHero();
          if (!hero) return false;
          const rect = hero.getBoundingClientRect();
          const headerH = document.getElementById('header')?.offsetHeight || 0;
          // Hero is "in view" if its bottom is still below the bottom of the viewport
          return rect.top <= headerH + 5 && rect.bottom > window.innerHeight * 0.3;
        }

        function isAtHeroBottom() {
          const hero = getHero();
          if (!hero) return false;
          const rect = hero.getBoundingClientRect();
          // Hero bottom is near or below the viewport bottom
          return rect.bottom <= window.innerHeight + 5 && rect.bottom > window.innerHeight * 0.7;
        }

        // Wheel event
        document.addEventListener('wheel', function(e) {
          if (!isHeroInView()) return;
          if (e.deltaY > 0 && isAtHeroBottom()) {
            e.preventDefault();
            snapToBestSellers();
          } else if (e.deltaY < 0 && window.scrollY < 10) {
            // already at top, nothing to do
          }
        }, { passive: false });

        // Touch support
        let touchStartY = 0;
        document.addEventListener('touchstart', function(e) {
          touchStartY = e.touches[0].clientY;
        }, { passive: true });

        document.addEventListener('touchend', function(e) {
          if (!isHeroInView()) return;
          const deltaY = touchStartY - e.changedTouches[0].clientY;
          if (deltaY > 30 && isAtHeroBottom()) {
            snapToBestSellers();
          }
        }, { passive: true });

      })();
    </script>


    <!-- Hero Section -->
    <section id="hero" class="hero section">

      <!-- Bootstrap Carousel -->
  <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000" data-bs-pause="false">
        
        <?php
        $hero_slides = [];
        if (!empty($conn)) {
            $hero_res = mysqli_query($conn, "SELECT * FROM website_hero_slides ORDER BY slide_order ASC");
            if ($hero_res) {
                while ($h = mysqli_fetch_assoc($hero_res)) {
                    $hero_slides[] = $h;
                }
            }
        }
        ?>
        <!-- Carousel Indicators -->
        <div class="carousel-indicators">
          <?php foreach ($hero_slides as $index => $slide): ?>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?php echo $index; ?>" <?php echo $index === 0 ? 'class="active" aria-current="true"' : ''; ?>></button>
          <?php endforeach; ?>
        </div>

        <!-- Carousel Inner -->
        <div class="carousel-inner">
          <?php foreach ($hero_slides as $index => $slide): ?>
          <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
            <img src="<?php echo htmlspecialchars($slide['image_path']); ?>" class="d-block w-100 carousel-img" alt="<?php echo htmlspecialchars($slide['title']); ?>">
            <div class="carousel-caption">
              <h2 class="display-4 fw-bold text-white mb-3"><?php echo htmlspecialchars($slide['title']); ?></h2>
              <p class="lead text-white-50 mb-4"><?php echo htmlspecialchars($slide['subtitle']); ?></p>
              <div class="d-flex gap-3 justify-content-center">
                <?php if (!empty($slide['button_1_text'])): ?>
                <a href="<?php echo htmlspecialchars($slide['button_1_link']); ?>" class="btn btn-primary btn-lg px-4"><?php echo htmlspecialchars($slide['button_1_text']); ?></a>
                <?php endif; ?>
                <?php if (!empty($slide['button_2_text'])): ?>
                <a href="<?php echo htmlspecialchars($slide['button_2_link']); ?>" class="btn btn-outline-light btn-lg px-4"><?php echo htmlspecialchars($slide['button_2_text']); ?></a>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Carousel Controls -->
        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
          <span class="carousel-control-prev-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
          <span class="carousel-control-next-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Next</span>
        </button>

      </div>

    </section><!-- /Hero Section -->

    <!-- Promo Cards Section -->
    <!-- <section id="promo-cards" class="promo-cards section">
      <div class="container" data-aos="fade-up" data-aos-delay="100">
        <div class="row gy-4">

          <div class="col-lg-6">
            <div class="category-featured" data-aos="fade-right" data-aos-delay="200">
              <div class="category-image">
                <img src="assets/img/product/product-f-2.webp" alt="Women's Collection" class="img-fluid">
              </div>
              <div class="category-content">
                <span class="category-tag">Trending Now</span>
                <h2>New Summer Collection</h2>
                <p>Discover our latest arrivals designed for the modern lifestyle. Elegant, comfortable, and sustainable fashion for every occasion.</p>
                <a href="#" class="btn-shop">Explore Collection <i class="bi bi-arrow-right"></i></a>
              </div>
            </div>
          </div>

          <div class="col-lg-6">

            <div class="row gy-4">

              <div class="col-xl-6">
                <div class="category-card cat-men" data-aos="fade-up" data-aos-delay="300">
                  <div class="category-image">
                    <img src="assets/img/product/product-m-5.webp" alt="Men's Fashion" class="img-fluid">
                  </div>
                  <div class="category-content">
                    <h4>Saree Collection</h4>
                    <p>242 products</p>
                    <a href="#" class="card-link">Shop Now <i class="bi bi-arrow-right"></i></a>
                  </div>
                </div>
              </div>

              <div class="col-xl-6">
                <div class="category-card cat-kids" data-aos="fade-up" data-aos-delay="400">
                  <div class="category-image">
                    <img src="assets/img/product/product-8.webp" alt="Kid's Fashion" class="img-fluid">
                  </div>
                  <div class="category-content">
                    <h4>Designer Sarees</h4>
                    <p>185 products</p>
                    <a href="#" class="card-link">Shop Now <i class="bi bi-arrow-right"></i></a>
                  </div>
                </div>
              </div>

              <div class="col-xl-6">
                <div class="category-card cat-cosmetics" data-aos="fade-up" data-aos-delay="500">
                  <div class="category-image">
                    <img src="assets/img/product/product-3.webp" alt="Cosmetics" class="img-fluid">
                  </div>
                  <div class="category-content">
                    <h4>Silk Sarees</h4>
                    <p>127 products</p>
                    <a href="#" class="card-link">Shop Now <i class="bi bi-arrow-right"></i></a>
                  </div>
                </div>
              </div>

              <div class="col-xl-6">
                <div class="category-card cat-accessories" data-aos="fade-up" data-aos-delay="600">
                  <div class="category-image">
                    <img src="assets/img/product/product-12.webp" alt="Accessories" class="img-fluid">
                  </div>
                  <div class="category-content">
                    <h4>Saree Accessories</h4>
                    <p>308 products</p>
                    <a href="#" class="card-link">Shop Now <i class="bi bi-arrow-right"></i></a>
                  </div>
                </div>
              </div>

            </div>
          </div>

        </div>

      </div>
    </section>/Promo Cards Section -->

    <!-- Best Sellers Section - 3D CoverFlow Slider -->
    <section id="best-sellers" class="best-sellers-3d section">

      <!-- Section Title -->
      <div class="container section-title" data-aos="fade-up">
        <h2>Loved by Many, Chosen by You</h2>
        <p>Discover the sarees and ethnic wear that make every occasion feel special</p>
      </div><!-- End Section Title -->

      <div class="bs3d-wrapper" data-aos="fade-up" data-aos-delay="100">
        <!-- Swiper 3D CoverFlow Slider -->
        <div class="swiper bs3d-swiper">
                    <div class="swiper-wrapper">
            <?php
            // Fetch Best Sellers
            $best_sellers = [];
            if (!empty($conn)) {
                $bs_sql = "
                    SELECT p.*, c.name as category_name, COALESCE(SUM(oi.quantity), 0) as total_sold,
                           (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND status = 'approved') as review_count,
                           (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') as avg_rating
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN order_items oi ON p.id = oi.product_id
                    WHERE p.status = '1' OR p.status = 'active'
                    GROUP BY p.id
                    ORDER BY p.is_bestseller DESC, total_sold DESC, p.created_at DESC
                    LIMIT 8
                ";
                $bs_res = mysqli_query($conn, $bs_sql);
                if ($bs_res) {
                    while ($p = mysqli_fetch_assoc($bs_res)) {
                        $best_sellers[] = $p;
                    }
                }
            }
            foreach ($best_sellers as $product): 
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
                      $clean_img = ltrim(str_replace('\\', '/', $img_name), './');
                      $possiblePaths = [
                          $clean_img,
                          'uploads/products/' . basename($clean_img),
                          'uploads/' . basename($clean_img),
                          'admin/uploads/' . basename($clean_img),
                          'assets/img/product/' . basename($clean_img)
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
            <div class="swiper-slide bs3d-slide product-item" 
                 data-product-name="<?php echo htmlspecialchars($product['name']); ?>" 
                 data-product-price="<?php echo htmlspecialchars($product['price']); ?>" 
                 data-product-image="<?php echo htmlspecialchars($mainImage); ?>">
              <div class="bs3d-card">
                <div class="bs3d-card-img">
                  <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.src='<?php echo $base_url; ?>assets/img/product/saree1.png'">
                  <?php if (!empty($product['discount_price'])): ?>
                  <div class="bs3d-badge bs3d-badge-sale">Sale</div>
                  <?php endif; ?>
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
                  <span class="bs3d-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Best Sellers'); ?></span>
                  <h4 class="bs3d-name"><a href="<?php echo $product_link; ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($product['name']); ?></a></h4>
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
          </div><!-- End Swiper Wrapper -->

          <!-- Pagination -->
          <div class="swiper-pagination bs3d-pagination"></div>

          <!-- Navigation -->
          <div class="swiper-button-prev bs3d-prev"></div>
          <div class="swiper-button-next bs3d-next"></div>
        </div><!-- End Swiper -->
      </div><!-- End bs3d-wrapper -->

    </section><!-- /Best Sellers 3D Section -->

    <!-- Cards Section -->
    <section id="cards" class="cards section light-background">
      <div class="container section-title" data-aos="fade-up">
        <h2>Every Moment Has a Story</h2>
        <p>Find something special to make yours even more memorable</p>
      </div>

      <div class="container">
        <?php if (!empty($index_categories)): ?>
        <div class="row gy-5">
          <?php foreach ($index_categories as $i => $cat):
            // Build image URL
            $cat_img = 'assets/img/product/saree1.webp';
            if (!empty($cat['image'])) {
                $img_path = $cat['image'];
                if (strpos($img_path, './') === 0) {
                    $img_path = substr($img_path, 2);
                }
                if (file_exists(__DIR__ . '/' . $img_path)) {
                    $cat_img = $img_path;
                } elseif (file_exists($img_path)) {
                    $cat_img = $img_path;
                }
            }
            // URL-friendly slug
            $cat_slug = strtolower(trim($cat['name']));
            $cat_slug = preg_replace('/[^a-z0-9]+/', '-', $cat_slug);
            $cat_slug = trim($cat_slug, '-');
            $delay = 100 + ($i * 100);
          ?>
          <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
            <div class="collection-card position-relative overflow-hidden" style="border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); aspect-ratio: 3/4; group;">
              <a href="products/<?php echo htmlspecialchars($cat_slug); ?>" class="d-block h-100 w-100 position-relative text-decoration-none">
                <img src="<?php echo htmlspecialchars($cat_img); ?>" 
                     alt="<?php echo htmlspecialchars($cat['name']); ?>"
                     class="w-100 h-100 object-fit-cover transition-transform duration-500"
                     style="object-fit: cover; transition: transform 0.5s ease;"
                     onmouseover="this.style.transform='scale(1.05)'"
                     onmouseout="this.style.transform='scale(1)'"
                     onerror="this.src='assets/img/product/saree1.webp'">
                
                <!-- Gradient Overlay -->
                <div class="position-absolute bottom-0 start-0 w-100 h-50" style="background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0) 100%); pointer-events: none;"></div>
                
                <!-- Text Content -->
                <div class="position-absolute bottom-0 start-0 w-100 text-center pb-4 px-3" style="z-index: 2;">
                  <h4 class="text-white mb-1" style="font-size: 1.6rem; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                    <?php echo htmlspecialchars($cat['name']); ?>
                  </h4>
                  <?php if (!empty($cat['description'])): ?>
                  <p class="text-white-50 small mb-0 text-truncate" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">
                    <?php echo htmlspecialchars($cat['description']); ?>
                  </p>
                  <?php endif; ?>
                </div>

                <!-- Hover Overlay Button -->
                <div class="position-absolute top-50 start-50 translate-middle opacity-0 transition-opacity duration-300 hover-overlay-btn" style="z-index: 3; transition: opacity 0.3s ease;">
                  <span class="custom-glass-btn">VIEW COLLECTION</span>
                </div>
              </a>
            </div>
            <style>
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

              .collection-card:hover .custom-glass-btn::before,
              .bs3d-card:hover .custom-glass-btn::before {
                left: 200%;
              }
            </style>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
          <i class="bi bi-collection" style="font-size:3rem; color:#ccc;"></i>
          <p class="mt-3 text-muted">No collections available at the moment.</p>
        </div>
        <?php endif; ?>
      </div>
    </section><!-- /Cards Section -->

            <!-- Call To Action Section -->
    <?php if (($website_settings['cta_show'] ?? '1') == '1'): ?>
    <section id="call-to-action" class="call-to-action section">
      <div class="container" data-aos="fade-up" data-aos-delay="100">
        <div class="row">
          <div class="col-lg-8 mx-auto">
            <div class="main-content text-center" data-aos="zoom-in" data-aos-delay="200">
              <div class="offer-badge" data-aos="fade-down" data-aos-delay="250">
                <span class="limited-time">Limited Time</span>
                <span class="offer-text"><?php echo htmlspecialchars($website_settings['cta_offer_badge'] ?? '50% OFF'); ?></span>
              </div>
              <h2 data-aos="fade-up" data-aos-delay="300"><?php echo htmlspecialchars($website_settings['cta_heading'] ?? 'Exclusive Flash Sale'); ?></h2>
              <p class="subtitle" data-aos="fade-up" data-aos-delay="350"><?php echo htmlspecialchars($website_settings['cta_subtitle'] ?? ''); ?></p>
              <div class="countdown-wrapper" data-aos="fade-up" data-aos-delay="400">
                <div class="countdown d-flex justify-content-center" data-count="<?php echo htmlspecialchars($website_settings['cta_countdown_date'] ?? '2025/12/31'); ?>">
                  <div><h3 class="count-days"></h3><h4>Days</h4></div>
                  <div><h3 class="count-hours"></h3><h4>Hours</h4></div>
                  <div><h3 class="count-minutes"></h3><h4>Minutes</h4></div>
                  <div><h3 class="count-seconds"></h3><h4>Seconds</h4></div>
                </div>
              </div>
              <div class="action-buttons" data-aos="fade-up" data-aos-delay="450">
                <a href="<?php echo htmlspecialchars($website_settings['cta_btn1_link'] ?? '#'); ?>" class="btn-shop-now"><?php echo htmlspecialchars($website_settings['cta_btn1_text'] ?? 'Shop Now'); ?></a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section><!-- /Call To Action Section -->
    <?php endif; ?>

    <!-- Reviews + Enquiry Section -->
    <section id="client-reviews" class="client-reviews section light-background">

      <div class="container" data-aos="fade-up">
        <div class="row g-0 cr-wrapper">

          <!-- ===== LEFT: Reviews Ticker ===== -->
          <div class="col-lg-6 cr-left">

            <div class="cr-left-header">
              <span class="cr-tag">Customer Stories</span>
              <h3>What Our Customers Say</h3>
              <p>Loved by thousands of women across India</p>
              <div class="cr-rating-summary">
                <span class="cr-big-rating">4.9</span>
                <div>
                  <div class="cr-sum-stars">
                    <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-half"></i>
                  </div>
                  <small>Based on 4,500+ reviews</small>
                </div>
              </div>
            </div>

            <!-- Continuous auto-scroll ticker -->
                                    <div class="cr-ticker-wrap">
              <div class="cr-ticker" id="crTicker">
                <?php
                $client_reviews = [];
                if (!empty($conn)) {
                    $rev_res = mysqli_query($conn, "SELECT * FROM client_reviews ORDER BY id DESC");
                    if ($rev_res) {
                        while ($r = mysqli_fetch_assoc($rev_res)) {
                            $client_reviews[] = $r;
                        }
                    }
                }
                foreach ($client_reviews as $rev):
                ?>
                <div class="cr-tick-item">
                  <div class="cr-tick-stars">
                    <?php 
                    $rating = round($rev['rating']);
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $rating) echo '<i class="bi bi-star-fill"></i>';
                        else echo '<i class="bi bi-star"></i>';
                    }
                    ?>
                  </div>
                  <p>"<?php echo htmlspecialchars($rev['review_text']); ?>"</p>
                  <div class="cr-tick-author">
                    <div class="cr-tick-avatar <?php echo htmlspecialchars($rev['avatar_color_class']); ?>"><?php echo htmlspecialchars($rev['avatar_letter']); ?></div>
                    <div>
                      <strong><?php echo htmlspecialchars($rev['author_name']); ?></strong>
                      <span><?php echo htmlspecialchars($rev['location']); ?> · <i class="bi bi-patch-check-fill text-success"></i> Verified</span>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div><!-- End Ticker -->
            </div><!-- End Ticker Wrap -->

          </div><!-- End Left -->

          <!-- ===== RIGHT: WhatsApp Enquiry Form ===== -->
          <?php
          $wa_title = $website_settings['wa_form_title'] ?? 'Quick Enquiry';
          $wa_subtitle = $website_settings['wa_form_subtitle'] ?? "Send us your details — we'll reply on WhatsApp instantly!";
          $wa_show_product = ($website_settings['wa_show_product'] ?? '1') == '1';
          $wa_show_budget = ($website_settings['wa_show_budget'] ?? '1') == '1';
          $wa_show_color = ($website_settings['wa_show_color'] ?? '1') == '1';
          $wa_show_message = ($website_settings['wa_show_message'] ?? '1') == '1';
          
          $default_products = "Kanjivaram Silk Saree\nBanarasi Silk Saree\nChiffon Saree\nMysore Silk Saree\nPaithani Saree\nDesigner Lehenga\nCotton Saree\nGeorgette Saree\nOther / Custom";
          $raw_product_opts = $website_settings['wa_product_options'] ?? $default_products;
          $product_options_array = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw_product_opts)));

          $default_budgets = "Under ₹1,000\n₹1,000 – ₹3,000\n₹3,000 – ₹5,000\n₹5,000 – ₹10,000\nAbove ₹10,000";
          $raw_budget_opts = $website_settings['wa_budget_options'] ?? $default_budgets;
          $budget_options_array = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw_budget_opts)));

          $default_colors = "Red\nPink\nBlue\nGreen\nYellow\nOrange\nPurple\nGold\nSilver\nWhite\nBlack\nMulticolor";
          $raw_color_opts = $website_settings['wa_color_options'] ?? $default_colors;
          $color_options_array = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw_color_opts)));

          $wa_button_text = $website_settings['wa_button_text'] ?? 'Send Enquiry on WhatsApp';
          $wa_form_note = $website_settings['wa_form_note'] ?? 'Your details are safe and will only be used to assist your enquiry.';
          ?>
          <div class="col-lg-6 cr-right">
            <div class="cr-form-box">

              <div class="cr-form-header">
                <div class="cr-wa-icon"><i class="bi bi-whatsapp"></i></div>
                <div>
                  <h3><?php echo htmlspecialchars($wa_title); ?></h3>
                  <p><?php echo htmlspecialchars($wa_subtitle); ?></p>
                </div>
              </div>

              <form id="waEnquiryForm" class="cr-form" novalidate>

                <div class="cr-form-row">
                  <div class="cr-form-group">
                    <label for="waName">Full Name <span>*</span></label>
                    <div class="cr-input-wrap">
                      <i class="bi bi-person"></i>
                      <input type="text" id="waName" placeholder="e.g. Priya Sharma" required>
                    </div>
                  </div>
                  <div class="cr-form-group">
                    <label for="waPhone">Phone Number <span>*</span></label>
                    <div class="cr-input-wrap">
                      <input type="tel" id="waPhone" placeholder="e.g. 9876543210" required>
                    </div>
                  </div>
                </div>

                <?php if ($wa_show_product): ?>
                <div class="cr-form-group">
                  <label for="waProduct">Product Interest <span>*</span></label>
                  <div class="cr-input-wrap">
                    <i class="bi bi-bag"></i>
                    <select id="waProduct" required>
                      <option value="" disabled selected>Select a product category</option>
                      <?php foreach ($product_options_array as $opt): ?>
                      <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <?php endif; ?>

                <?php if ($wa_show_budget || $wa_show_color): ?>
                <div class="cr-form-row">
                  <?php if ($wa_show_budget): ?>
                  <div class="cr-form-group">
                    <label for="waBudget">Budget Range</label>
                    <div class="cr-input-wrap">
                      <i class="bi bi-currency-rupee"></i>
                      <select id="waBudget">
                        <option value="" disabled selected>Select budget</option>
                        <?php foreach ($budget_options_array as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <?php endif; ?>

                  <?php if ($wa_show_color): ?>
                  <div class="cr-form-group">
                    <label for="waColor">Colour Preference</label>
                    <div class="cr-input-wrap">
                      <i class="bi bi-palette"></i>
                      <?php if (!empty($color_options_array)): ?>
                      <select id="waColor">
                        <option value="" disabled selected>Select a colour</option>
                        <?php foreach ($color_options_array as $color): ?>
                        <option value="<?php echo htmlspecialchars($color); ?>"><?php echo htmlspecialchars($color); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <?php else: ?>
                      <input type="text" id="waColor" placeholder="e.g. Red, Pink, Blue">
                      <?php endif; ?>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($wa_show_message): ?>
                <div class="cr-form-group">
                  <label for="waMessage">Additional Message</label>
                  <div class="cr-input-wrap cr-textarea-wrap">
                    <i class="bi bi-chat-left-text"></i>
                    <textarea id="waMessage" rows="3" placeholder="Any specific requirements, occasion, or questions..."></textarea>
                  </div>
                </div>
                <?php endif; ?>

                <button type="submit" class="cr-wa-btn" id="waSendBtn">
                  <i class="bi bi-whatsapp"></i>
                  <?php echo htmlspecialchars($wa_button_text); ?>
                  <i class="bi bi-arrow-right-short cr-btn-arrow"></i>
                </button>

                <?php if (!empty($wa_form_note)): ?>
                <p class="cr-form-note"><i class="bi bi-shield-check"></i> <?php echo htmlspecialchars($wa_form_note); ?></p>
                <?php endif; ?>

              </form>
            </div>
          </div><!-- End Right -->

        </div><!-- End Row -->
      </div><!-- End Container -->

    </section><!-- /Reviews + Enquiry Section -->

  </main>

  <!-- Footer -->
   <?php include 'footer.php'?>

  <!-- Scroll Top -->
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Preloader -->
  <div id="preloader"></div>

  <!-- Mobile Bottom Navigation -->
  <?php include 'mobile-bottom-nav.php'?>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>
  <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
  <script src="assets/vendor/drift-zoom/Drift.min.js"></script>
  <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>

  <!-- Intl-tel-input JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/intlTelInput.min.js"></script>

  <!-- Main JS File -->
  <script src="assets/js/main.js"></script>

  <!-- Best Sellers 3D CoverFlow Swiper Init -->
  <script>
    (function() {
      var bs3dEl = document.querySelector('.bs3d-swiper');
      if (!bs3dEl) return;

      var bs3dSwiper = new Swiper('.bs3d-swiper', {
        effect: 'coverflow',
        grabCursor: true,
        centeredSlides: true,
        slidesPerView: 'auto',
        loop: true,
        loopAddBlankSlides: false,
        keyboard: { enabled: true },
        watchSlidesProgress: true,
        coverflowEffect: {
          rotate: 30,
          stretch: 0,
          depth: 120,
          modifier: 1,
          slideShadows: true
        },
        pagination: {
          el: '.bs3d-pagination',
          clickable: true
        },
        navigation: {
          nextEl: '.bs3d-next',
          prevEl: '.bs3d-prev'
        },
        autoplay: {
          delay: 3000,
          disableOnInteraction: false,
          pauseOnMouseEnter: true,
          waitForTransition: false
        },
        speed: 700,
        on: {
          // Ensure autoplay restarts after any interaction
          touchEnd: function(swiper) {
            if (swiper.autoplay && !swiper.autoplay.running) {
              swiper.autoplay.start();
            }
          }
        }
      });
    })();
  </script>

  <!-- Reviews Ticker & WhatsApp Form JS -->
  <script>
    (function() {
      // 1. Continuous Ticker duplicate for infinite scroll
      var ticker = document.getElementById('crTicker');
      if (ticker) {
        // Clone the content to make it seamless
        var content = ticker.innerHTML;
        ticker.innerHTML = content + content;
      }

      // 2. WhatsApp Form Submission & Intl-tel-input Init
      var waPhoneInput = document.querySelector("#waPhone");
      var iti = null;
      if (waPhoneInput) {
        iti = window.intlTelInput(waPhoneInput, {
          initialCountry: "auto",
          geoIpLookup: function(callback) {
            fetch("https://ipapi.co/json")
              .then(function(res) { return res.json(); })
              .then(function(data) { callback(data.country_code); })
              .catch(function() { callback("in"); });
          },
          utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/utils.js",
          separateDialCode: true
        });

        waPhoneInput.addEventListener('input', function() {
            if (this.value.startsWith('0')) {
                this.value = this.value.replace(/^0+/, '');
            }
        });
      }

      var waForm = document.getElementById('waEnquiryForm');
      if (waForm) {
        waForm.addEventListener('submit', function(e) {
          e.preventDefault();
          
          if (!this.checkValidity()) {
            this.reportValidity();
            return;
          }

          var waNumber = '<?php echo htmlspecialchars(!empty($website_settings['whatsapp_number']) ? preg_replace('/[^0-9]/', '', $website_settings['whatsapp_number']) : '918799582279'); ?>';
          var name = document.getElementById('waName').value.trim();
          var phone = iti ? iti.getNumber() : document.getElementById('waPhone').value.trim();
          
          var productEl = document.getElementById('waProduct');
          var budgetEl = document.getElementById('waBudget');
          var colorEl = document.getElementById('waColor');
          var messageEl = document.getElementById('waMessage');

          var text = "Hello Silky Saree, I have a new enquiry:\n\n" +
                     "*Name:* " + name + "\n" +
                     "*Phone:* " + phone + "\n";

          if (productEl) text += "*Product:* " + (productEl.value || 'Not specified') + "\n";
          if (budgetEl) text += "*Budget:* " + (budgetEl.value || 'Not specified') + "\n";
          if (colorEl && colorEl.value.trim()) text += "*Colour Pref:* " + colorEl.value.trim() + "\n";
          if (messageEl && messageEl.value.trim()) text += "\n*Message:* " + messageEl.value.trim();

          var url = "https://wa.me/" + waNumber + "?text=" + encodeURIComponent(text);
          window.open(url, '_blank');
        });
      }
    })();
  </script>

  <script>
    // Ensure carousel continues sliding when pointer is over it (pointerenter)
    (function() {
      var carouselEl = document.getElementById('heroCarousel');
      if (!carouselEl) return;
      var carousel = bootstrap.Carousel.getInstance(carouselEl);
      if (!carousel) {
        carousel = new bootstrap.Carousel(carouselEl, { interval: 5000, pause: false });
      }
    })();
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      let cart = JSON.parse(localStorage.getItem('cart') || '[]');
      let wishlist = JSON.parse(localStorage.getItem('wishlist') || '[]');

      updateCartBadge();
      updateWishlistBadge();
      updateWishlistButtons();

      // Add to cart functionality (wishlist & category cards & best sellers)
      document.querySelectorAll('.add-to-cart-btn, .cart-btn, .add-to-cart').forEach(button => {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          const productId = this.getAttribute('data-product-id');
          const productItem = this.closest('.product-item');
          
          // Basic cart logic for demo
          const existingItem = cart.find(item => item.id === productId);
          if (existingItem) {
            existingItem.quantity += 1;
          } else {
            let pName = productItem ? productItem.getAttribute('data-product-name') : 'Silk Saree';
            let pPrice = productItem ? parseFloat(productItem.getAttribute('data-product-price')) : 1999;
            let pImage = productItem ? productItem.getAttribute('data-product-image') : 'assets/img/product/saree1.png';
            cart.push({
              id: productId,
              name: pName,
              price: pPrice,
              quantity: 1,
              image: pImage
            });
          }
          
          localStorage.setItem('cart', JSON.stringify(cart));
          updateCartBadge();
          showToast('Product added to cart!', 'success');
          this.style.transform = 'scale(0.95)';
          setTimeout(() => {
            this.style.transform = 'scale(1)';
          }, 150);
        });
      });

      // Wishlist functionality (wishlist only)
      document.querySelectorAll('.wishlist-btn').forEach(button => {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          const productId = this.getAttribute('data-product-id');
          const productItem = this.closest('.product-item');
          const heartIcon = this.querySelector('i');

          // Only update wishlist, not cart
          const existingIndex = wishlist.findIndex(item => item.id === productId);

          if (existingIndex > -1) {
            wishlist.splice(existingIndex, 1);
            heartIcon.className = 'bi bi-heart';
            this.classList.remove('active');
            showToast('Removed from wishlist', 'info');
          } else {
            const product = {
              id: productId,
              name: productItem.getAttribute('data-product-name'),
              price: parseFloat(productItem.getAttribute('data-product-price')),
              image: productItem.getAttribute('data-product-image')
            };
            wishlist.push(product);
            heartIcon.className = 'bi bi-heart-fill';
            this.classList.add('active');
            showToast('Added to wishlist!', 'success');
          }

          localStorage.setItem('wishlist', JSON.stringify(wishlist));
          updateWishlistBadge();
        });
      });
      
      // Update cart badge
      function updateCartBadge() {
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        // Update all cart badges including mobile bottom nav
        const cartBadges = document.querySelectorAll('a[href="cart.php"] .badge, .cart-badge');
        cartBadges.forEach(badge => {
          badge.textContent = totalItems;
          badge.style.display = totalItems > 0 ? 'inline' : 'none';
        });
      }
      
      // Update wishlist badge
      function updateWishlistBadge() {
        // Update all wishlist badges including mobile bottom nav
        const wishlistBadges = document.querySelectorAll('a[href="wishlist.php"] .badge, .wishlist-badge');
        wishlistBadges.forEach(badge => {
          badge.textContent = wishlist.length;
          badge.style.display = wishlist.length > 0 ? 'inline' : 'none';
        });
      }
      
      // Update wishlist button states
      function updateWishlistButtons() {
        document.querySelectorAll('.wishlist-btn').forEach(button => {
          const productId = button.getAttribute('data-product-id');
          const isInWishlist = wishlist.some(item => item.id === productId);
          const heartIcon = button.querySelector('i');
          
          if (isInWishlist) {
            heartIcon.className = 'bi bi-heart-fill';
            button.classList.add('active');
          } else {
            heartIcon.className = 'bi bi-heart';
            button.classList.remove('active');
          }
        });
      }
      
      // Toast notification system
      function showToast(message, type = 'info') {
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
        } else if (type === 'warning') {
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
    });
  </script>

</body>

</html>