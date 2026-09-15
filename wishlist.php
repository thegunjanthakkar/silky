<?php
// Start session to check if user is logged in
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // User is not logged in, redirect to login page with return URL
    $return_url = urlencode($_SERVER['REQUEST_URI']);
    header("Location: login.php?redirect=" . $return_url);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Wishlist - Silky Saree</title>
  <meta name="description" content="">
  <meta name="keywords" content="">

  <!-- Favicons -->
  <link rel="shortcut icon" href="./assets/img/silky-jpg.jpg" type="image/x-icon">

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

  <!-- Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">

  <!-- Wishlist Page Custom Styles -->
  <style>
    /* Wishlist Page Button Styling - Theme Consistent */
    .wishlist-page .btn {
      position: relative;
      overflow: hidden;
      border-radius: 8px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      transition: all 0.3s ease;
      z-index: 1;
    }

    /* Primary Button (Add to Cart) - Theme Green */
    .wishlist-page .btn-primary {
      background: linear-gradient(45deg, var(--heading-color), #0e2187);
      border: 2px solid var(--heading-color);
      color: #ffffff;
      box-shadow: 0 4px 15px rgba(151, 197, 29, 0.3);
      padding: 8px 20px;
      font-size: 0.85rem;
    }

    .wishlist-page .btn-primary:hover {
      background: linear-gradient(45deg, #0e2187, var(--heading-color));
      border-color: #0e2187;
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(151, 197, 29, 0.4);
    }

    /* Shining Animation Effect */
    .wishlist-page .btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
      transition: left 0.5s ease;
      z-index: -1;
    }

    .wishlist-page .btn:hover::before {
      left: 100%;
    }

    /* Remove from Wishlist Button */
    .wishlist-page .remove-from-wishlist {
      background: none;
      border: none;
      color: #dc3545;
      font-size: 0.9rem;
      padding: 5px 10px;
      border-radius: 20px;
      transition: all 0.3s ease;
    }

    .wishlist-page .remove-from-wishlist:hover {
      background-color: rgba(220, 53, 69, 0.1);
      color: #b02a37;
      transform: scale(1.05);
    }

    /* Add All to Cart Button - Large Primary */
    .wishlist-page #add-all-to-cart {
      background: linear-gradient(45deg, var(--heading-color), #0e2187);
      border: 2px solid var(--heading-color);
      color: #ffffff;
      box-shadow: 0 4px 15px rgba(14, 33, 135, 0.3);
      padding: 12px 30px;
      font-size: 1rem;
      animation: pulse-green 2s infinite;
    }

    .wishlist-page #add-all-to-cart:hover {
      background: linear-gradient(45deg, #0e2187, var(--heading-color));
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(14, 33, 135, 0.4);
    }

    /* Clear Wishlist Button - Danger Style */
    .wishlist-page .btn-outline-remove {
      background: rgba(220, 53, 69, 0.1);
      border: 2px solid #dc3545;
      color: #dc3545;
      backdrop-filter: blur(10px);
    }

    .wishlist-page .btn-outline-remove:hover {
      background: #dc3545;
      color: #ffffff;
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
    }

    /* Continue Shopping Button */
    .wishlist-page .btn-primary.continue-shopping {
      background: linear-gradient(45deg, #6c757d, #495057);
      border: 2px solid #6c757d;
      color: #ffffff;
      box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
    }

    .wishlist-page .btn-primary.continue-shopping:hover {
      background: linear-gradient(45deg, #495057, #6c757d);
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(108, 117, 125, 0.4);
    }

    /* Pulse Animation for Primary Buttons */
    @keyframes pulse-green {
      0% {
        box-shadow: 0 4px 15px rgba(151, 197, 29, 0.3);
      }
      50% {
        box-shadow: 0 4px 25px rgba(151, 197, 29, 0.5);
      }
      100% {
        box-shadow: 0 4px 15px rgba(151, 197, 29, 0.3);
      }
    }

    /* Stock Status Badge */
    .wishlist-page .badge {
      border-radius: 20px;
      font-size: 0.75rem;
      padding: 6px 12px;
      font-weight: 500;
      letter-spacing: 0.5px;
    }

    .wishlist-page .badge.bg-success {
      background: linear-gradient(45deg, #59ff536b) !important;
      color: #00b306ff;
      box-shadow: 0 2px 8px rgba(151, 197, 29, 0.3);
    }

    /* Wishlist Item Styling */
    .wishlist-page .wishlist-item {
      background: var(--surface-color);
      border-radius: 15px;
      margin-bottom: 20px;
      padding: 20px;
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 90%);
      transition: all 0.3s ease;
    }

    .wishlist-page .wishlist-item:hover {
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
      transform: translateY(-2px);
    }

    /* Product Image in Wishlist */
    .wishlist-page .product-image img {
      border-radius: 10px;
      width: 80px;
      height: 80px;
      object-fit: cover;
    }

    /* Empty Wishlist Styling */
    .wishlist-page #empty-wishlist-message {
      background: var(--surface-color);
      border-radius: 20px;
      padding: 60px 40px;
      border: 2px dashed color-mix(in srgb, var(--default-color), transparent 80%);
    }

    .wishlist-page #empty-wishlist-message .bi {
      color: color-mix(in srgb, var(--default-color), transparent 60%);
    }

    /* Mobile Responsiveness */
    @media (max-width: 768px) {
      .wishlist-page .btn {
        font-size: 0.8rem;
        padding: 10px 16px;
      }
      
      .wishlist-page #add-all-to-cart {
        padding: 10px 20px;
        font-size: 0.9rem;
      }
      
      .wishlist-page .product-image img {
        width: 60px;
        height: 60px;
      }
    }
  </style>


</head>

<body class="wishlist-page">

  <header id="header" class="header sticky-top">
    <!-- Top Bar -->
    <?php include 'topbar.php'; ?>

    <!-- Main Header -->
    <?php include 'main-header.php'; ?>

  </header>

  <main class="main">

    <!-- Page Title -->
    <div class="page-title light-background">
      <div class="container d-lg-flex justify-content-between align-items-center">
        <h1 class="mb-2 mb-lg-0">Wishlist</h1>
        <nav class="breadcrumbs">
          <ol>
            <li><a href="index.php">Home</a></li>
            <li class="current">Wishlist</li>
          </ol>
        </nav>
      </div>
    </div><!-- End Page Title -->

    <!-- Wishlist Section -->
    <section id="wishlist" class="wishlist section">

      <div class="container" data-aos="fade-up" data-aos-delay="100">

        <div class="row">
          <div class="col-12" data-aos="fade-up" data-aos-delay="200">
            <div class="wishlist-items">
              <div class="wishlist-header d-none d-lg-block">
                <div class="row align-items-center">
                  <div class="col-lg-6">
                    <h5>Product</h5>
                  </div>
                  <div class="col-lg-2 text-center">
                    <h5>Price</h5>
                  </div>
                  <div class="col-lg-2 text-center">
                    <h5>Stock Status</h5>
                  </div>
                  <div class="col-lg-2 text-center">
                    <h5>Action</h5>
                  </div>
                </div>
              </div>

              <!-- Dynamic Wishlist Items will be loaded here -->
              <div id="wishlist-items-container">
                <!-- Items will be populated by JavaScript -->
              </div>

              <!-- Empty Wishlist Message -->
              <div id="empty-wishlist-message" class="text-center py-5" style="display: none;">
                <i class="bi bi-heart display-1 text-muted mb-3"></i>
                <h4 class="text-muted">Your wishlist is empty</h4>
                <p class="text-muted">Add some items to your wishlist to keep track of products you love!</p>
                <a href="index.php" class="btn btn-primary continue-shopping">Continue Shopping</a>
              </div>

              <div class="wishlist-actions">
                <div class="row">
                  <div class="col-lg-6 mb-3 mb-lg-0">
                    <button class="btn btn-primary" id="add-all-to-cart">
                      <i class="bi bi-cart-plus"></i> Add All to Cart
                    </button>
                  </div>
                  <div class="col-lg-6 text-md-end">
                    <button class="btn btn-outline-remove" id="clear-wishlist">
                      <i class="bi bi-trash"></i> Clear Wishlist
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

    </section><!-- /Wishlist Section -->

  </main>

  <?php include 'footer.php'?>

  <!-- Scroll Top -->
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Preloader -->
  <div id="preloader"></div>

  <!-- Mobile Bottom Navigation -->
  <?php include 'mobile-bottom-nav.php'; ?>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>
  <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
  <script src="assets/vendor/drift-zoom/Drift.min.js"></script>
  <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>

  <!-- Main JS File -->
  <script src="assets/js/main.js"></script>

  <!-- Wishlist Management Script -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Load wishlist and cart from localStorage
      let wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];
      let cart = JSON.parse(localStorage.getItem('cart')) || [];
      
      // DOM elements
      const wishlistContainer = document.getElementById('wishlist-items-container');
      const emptyWishlistMessage = document.getElementById('empty-wishlist-message');
      
      // Load and display wishlist items
      loadWishlistItems();
      
      function loadWishlistItems() {
        if (wishlist.length === 0) {
          wishlistContainer.style.display = 'none';
          emptyWishlistMessage.style.display = 'block';
          return;
        }
        
        wishlistContainer.style.display = 'block';
        emptyWishlistMessage.style.display = 'none';
        
        wishlistContainer.innerHTML = '';
        
        wishlist.forEach((item, index) => {
          const wishlistItemHTML = `
            <div class="wishlist-item" data-product-id="${item.id}">
              <div class="row align-items-center">
                <div class="col-lg-6 col-12 mt-3 mt-lg-0 mb-lg-0 mb-3">
                  <div class="product-info d-flex align-items-center">
                    <div class="product-image">
                      <img src="${item.image}" alt="${item.name}" class="img-fluid" loading="lazy">
                    </div>
                    <div class="product-details">
                      <h6 class="product-title">${item.name}</h6>
                      <div class="product-meta">
                        <span class="product-id">Product ID: ${item.id}</span>
                      </div>
                      <button class="remove-from-wishlist" type="button" data-product-id="${item.id}">
                        <i class="bi bi-heart-fill text-danger"></i> Remove from Wishlist
                      </button>
                    </div>
                  </div>
                </div>
                <div class="col-lg-2 col-12 mt-3 mt-lg-0 text-center">
                  <div class="price-tag">
                    <span class="current-price">₹${item.price.toFixed(2)}</span>
                  </div>
                </div>
                <div class="col-lg-2 col-12 mt-3 mt-lg-0 text-center">
                  <div class="stock-status">
                    <span class="badge bg-success">In Stock</span>
                  </div>
                </div>
                <div class="col-lg-2 col-12 mt-3 mt-lg-0 text-center">
                  <div class="wishlist-actions">
                    <button class="btn btn-primary btn-sm add-to-cart-from-wishlist" data-product-id="${item.id}">
                      <i class="bi bi-cart-plus"></i> Add to Cart
                    </button>
                  </div>
                </div>
              </div>
            </div>
          `;
          
          wishlistContainer.innerHTML += wishlistItemHTML;
        });
        
        // Add event listeners for wishlist actions
        addWishlistEventListeners();
      }
      
      function addWishlistEventListeners() {
        // Remove from wishlist buttons
        document.querySelectorAll('.remove-from-wishlist').forEach(button => {
          button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            removeFromWishlist(productId);
          });
        });
        
        // Add to cart from wishlist buttons
        document.querySelectorAll('.add-to-cart-from-wishlist').forEach(button => {
          button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            addToCartFromWishlist(productId);
          });
        });
      }
      
      function removeFromWishlist(productId) {
        wishlist = wishlist.filter(item => item.id != productId);
        localStorage.setItem('wishlist', JSON.stringify(wishlist));
        loadWishlistItems();
        updateWishlistBadge();
        showToast('Item removed from wishlist', 'info');
      }
      
      function addToCartFromWishlist(productId) {
        const wishlistItem = wishlist.find(item => item.id == productId);
        if (wishlistItem) {
          const button = document.querySelector(`.add-to-cart-from-wishlist[data-product-id="${productId}"]`);
          if (button) button.style.transform = 'scale(0.95)';

          fetch('cart.php?action=add_to_cart', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              product_id: parseInt(productId),
              quantity: 1
            })
          })
          .then(response => response.json())
          .then(data => {
            if (button) setTimeout(() => { button.style.transform = 'scale(1)'; }, 150);
            if (data.status === 'success') {
              showToast('Item added to cart!', 'success');
              if (typeof updateCartCountFromDatabase === 'function') {
                updateCartCountFromDatabase();
              }
            } else {
              showToast('Error adding item to cart', 'error');
            }
          })
          .catch(error => {
            if (button) setTimeout(() => { button.style.transform = 'scale(1)'; }, 150);
            console.error('Error:', error);
            showToast('Network error while adding to cart', 'error');
          });
        }
      }
      
      function updateWishlistBadge() {
        const wishlistBadges = document.querySelectorAll('a[href="wishlist.php"] .badge, .wishlist-badge');
        wishlistBadges.forEach(badge => {
          badge.textContent = wishlist.length;
          badge.style.display = wishlist.length > 0 ? 'inline' : 'none';
        });
      }
      
      function updateCartBadge() {
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        const cartBadges = document.querySelectorAll('a[href="cart.php"] .badge, .cart-badge');
        cartBadges.forEach(badge => {
          badge.textContent = totalItems;
          badge.style.display = totalItems > 0 ? 'inline' : 'none';
        });
      }
      
      // Add all to cart functionality
      document.getElementById('add-all-to-cart')?.addEventListener('click', function() {
        if (wishlist.length === 0) {
          showToast('Your wishlist is empty', 'warning');
          return;
        }
        
        let itemsAdded = 0;
        
        wishlist.forEach(wishlistItem => {
          const existingCartItem = cart.find(item => item.id === wishlistItem.id);
          
          if (existingCartItem) {
            existingCartItem.quantity += 1;
          } else {
            const cartItem = {
              ...wishlistItem,
              quantity: 1
            };
            cart.push(cartItem);
          }
          itemsAdded++;
        });
        
        localStorage.setItem('cart', JSON.stringify(cart));
        updateCartBadge();
        showToast(`${itemsAdded} items added to cart!`, 'success');
      });
      
      // Clear wishlist functionality
      document.getElementById('clear-wishlist')?.addEventListener('click', function() {
        if (wishlist.length === 0) {
          showToast('Your wishlist is already empty', 'info');
          return;
        }
        
        if (confirm('Are you sure you want to clear your wishlist?')) {
          wishlist = [];
          localStorage.setItem('wishlist', JSON.stringify(wishlist));
          loadWishlistItems();
          updateWishlistBadge();
          showToast('Wishlist cleared', 'info');
        }
      });
      
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
    });
  </script>

</body>

</html>