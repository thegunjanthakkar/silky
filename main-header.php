<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (empty($conn) && file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
}

// Define base URL if not already defined
if (!isset($base_url)) {
    $base_url = defined('BASE_URL') ? BASE_URL : '/';
}
?>
<div class="main-header">
      <div class="container-fluid container-xl">
        <div class="d-flex py-1 align-items-center justify-content-between position-relative">
          
          <!-- Logo -->
          <style>
            @media (max-width: 991.98px) {
              .mobile-center-logo {
                position: absolute !important;
                left: 50% !important;
                transform: translateX(-50%) !important;
                z-index: 10;
              }
              .main-header {
                background-color: #fff !important;
                min-height: 75px;
                display: flex;
                align-items: center;
              }
            }
          </style>
          <a href="<?php echo $base_url; ?>" class="logo d-flex align-items-center mobile-center-logo">
            <!-- Uncomment the line below if you also wish to use an image logo -->
            <img src="<?php echo $base_url; ?>assets/img/silky.png" alt="Silky logo" style="transform: scale(1.2); transform-origin: center;">
            <!-- <img class="sitename" src="<?php echo $base_url; ?>assets/img/silky.png" alt="Silky Saree"> -->
          </a>

          <!-- Navigation Menu -->
          <nav id="navmenu" class="navmenu d-none d-lg-block">
            <style>
              .navmenu a, .navmenu a:focus {
                font-size: 1.1rem !important; /* Increased font size */
              }
            </style>
            <ul class="d-flex mb-0 list-unstyled">
              <?php
              if (empty($conn) && file_exists('db_config.php')) {
                  require_once 'db_config.php';
              }
              if (!empty($conn)) {
                  $menu_res = mysqli_query($conn, "SELECT * FROM navigation_menus WHERE menu_type='main_desktop' ORDER BY display_order ASC");
                  if ($menu_res) {
                      while ($m = mysqli_fetch_assoc($menu_res)) {
                          echo '<li><a href="'.htmlspecialchars($m['link']).'">'.htmlspecialchars($m['title']).'</a></li>';
                      }
                  }
              }
              ?>
            </ul>
          </nav>

          <!-- Actions -->
          <div class="header-actions d-flex align-items-center justify-content-end">

            <!-- Desktop Search Link -->
            <a href="<?php echo $base_url; ?>search" class="header-action-btn d-none d-xl-block">
              <i class="bi bi-search"></i>
            </a>

            <!-- Mobile Search Link -->
            <a href="<?php echo $base_url; ?>search" class="header-action-btn d-xl-none">
              <i class="bi bi-search"></i>
            </a>

            <!-- Account -->
            <div class="dropdown account-dropdown">
              <button class="header-action-btn" data-bs-toggle="dropdown">
                <i class="bi bi-person"></i>
              </button>
              <div class="dropdown-menu">
                <?php if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])): 
                  $welcome_name = 'User';
                  if (empty($conn) && file_exists('db_config.php')) {
                      require_once 'db_config.php';
                  }
                  if (!empty($conn)) {
                      $uid = (int)$_SESSION['user_id'];
                      $u_res = mysqli_query($conn, "SELECT first_name, last_name FROM users WHERE id = $uid");
                      if ($u_res && mysqli_num_rows($u_res) > 0) {
                          $u_row = mysqli_fetch_assoc($u_res);
                          $fname = trim($u_row['first_name'] ?? '');
                          $lname = trim($u_row['last_name'] ?? '');
                          if ($fname || $lname) {
                              $welcome_name = trim($fname . ' ' . $lname);
                          }
                      }
                  }
                ?>
                  <!-- Logged In User Menu -->
                  <div class="dropdown-header">
                    <h6>Welcome, <?php echo htmlspecialchars($welcome_name); ?>!</h6>
                    <p class="mb-0">Manage your account &amp; orders</p>
                  </div>
                  <div class="dropdown-body">
                    <a class="dropdown-item d-flex align-items-center" href="<?php echo $base_url; ?>account.php">
                      <i class="bi bi-person-circle me-2"></i>
                      <span>My Profile</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="<?php echo $base_url; ?>account.php">
                      <i class="bi bi-bag-check me-2"></i>
                      <span>My Orders</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="<?php echo $base_url; ?>wishlist.php">
                      <i class="bi bi-heart me-2"></i>
                      <span>My Wishlist</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="<?php echo $base_url; ?>account.php">
                      <i class="bi bi-gear me-2"></i>
                      <span>Settings</span>
                    </a>
                  </div>
                  <div class="dropdown-footer">
                    <a href="<?php echo $base_url; ?>logout.php" class="btn btn-danger w-100">
                      <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a>
                  </div>
                <?php else: ?>
                  <!-- Guest User Menu -->
                  <div class="dropdown-header">
                    <h6>Welcome to <span class="sitename">Silky Saree</span></h6>
                    <p class="mb-0">Access account &amp; manage orders</p>
                  </div>
                  <div class="dropdown-body">
                    <a class="dropdown-item d-flex align-items-center" href="<?php echo $base_url; ?>login.php">
                      <i class="bi bi-person-circle me-2"></i>
                      <span>My Profile</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="<?php echo $base_url; ?>login.php">
                      <i class="bi bi-bag-check me-2"></i>
                      <span>My Orders</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="<?php echo $base_url; ?>login.php">
                      <i class="bi bi-heart me-2"></i>
                      <span>My Wishlist</span>
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="<?php echo $base_url; ?>login.php">
                      <i class="bi bi-gear me-2"></i>
                      <span>Settings</span>
                    </a>
                  </div>
                  <div class="dropdown-footer">
                    <a href="<?php echo $base_url; ?>login" class="btn btn-primary w-100 mb-2">Sign In</a>
                    <a href="<?php echo $base_url; ?>register" class="btn btn-outline-primary w-100">Register</a>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            

            <!-- Cart -->
            <a href="<?php echo $base_url; ?>cart" class="header-action-btn">
              <i class="bi bi-cart3"></i>
              <span class="badge" style="display: none;">0</span>
            </a>

            <!-- Mobile Navigation Toggle -->
            <i class="mobile-nav-toggle d-xl-none bi bi-list me-0"></i>

          </div>
        </div>
      </div>
    </div>

    <!-- Desktop Search Form removed, now handled by search.php -->

    <!-- Mobile Navigation -->
    <div class="header-nav d-none">
      <div class="container-fluid container-xl position-relative">
        <nav id="navmenu-mobile" class="navmenu">
          <ul>
            <li><a href="<?php echo $base_url; ?>index" class="active">Home</a></li>
            <li><a href="#">Saree</a></li>
            <li><a href="#">Salwar Suits</a></li>
            <li><a href="#">Lehengas</a></li>
            <li><a href="<?php echo $base_url; ?>contact">Contact</a></li>
          </ul>
        </nav>
      </div>
    </div>