<?php
// Ensure db connection is available
if (empty($conn) && file_exists('db_config.php')) {
    require_once 'db_config.php';
}

if (!isset($website_settings)) {
    $website_settings = [];
    if (!empty($conn)) {
        $settings_res = mysqli_query($conn, "SELECT * FROM website_settings");
        if ($settings_res) {
            while ($row = mysqli_fetch_assoc($settings_res)) {
                $website_settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
}
$topbar_phone = $website_settings['topbar_phone'] ?? '079 2768 3326';
$announcement_1 = $website_settings['topbar_announcement_1'] ?? '🚚 Free shipping on orders over $50';
$announcement_2 = $website_settings['topbar_announcement_2'] ?? '💰 30 days money back guarantee.';
$announcement_3 = $website_settings['topbar_announcement_3'] ?? '🎁 20% off on your first order';
?>
 <!-- Top Bar -->
    <div class="top-bar py-2">
      <div class="container-fluid container-xl">
        <div class="row align-items-center">
          <div class="col-lg-4 col-6 d-flex d-lg-flex">
            <div class="top-bar-item">
              <i class="bi bi-telephone-fill me-2"></i>
              <span class="d-none d-sm-inline">Need help? Call us: </span>
              <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $topbar_phone)); ?>"> <?php echo htmlspecialchars($topbar_phone); ?></a>
            </div>
          </div>

          <div class="col-lg-4 col-md-12 text-center d-none d-lg-block">
            <div class="announcement-slider swiper init-swiper">
              <script type="application/json" class="swiper-config">
                {
                  "loop": true,
                  "speed": 600,
                  "autoplay": {
                    "delay": 5000
                  },
                  "slidesPerView": 1,
                  "direction": "vertical",
                  "effect": "slide"
                }
              </script>
              <div class="swiper-wrapper">
                <div class="swiper-slide"><?php echo htmlspecialchars($announcement_1); ?></div>
                <div class="swiper-slide"><?php echo htmlspecialchars($announcement_2); ?></div>
                <div class="swiper-slide"><?php echo htmlspecialchars($announcement_3); ?></div>
              </div>
            </div>
          </div>

          <div class="col-lg-4 col-6 d-flex justify-content-end">
            <div class="d-flex justify-content-end">
              <div class="top-bar-item dropdown me-3">
                <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown">
                  <i class="bi bi-translate me-2"></i>EN
                </a>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="#"><i class="bi bi-check2 me-2 selected-icon"></i>English</a></li>
                  <li><a class="dropdown-item" href="#">Español</a></li>
                  <li><a class="dropdown-item" href="#">Français</a></li>
                  <li><a class="dropdown-item" href="#">Deutsch</a></li>
                </ul>
              </div>
              <div class="top-bar-item dropdown">
                <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown">
                  <i class="bi bi-currency-dollar me-2"></i>USD
                </a>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="#"><i class="bi bi-check2 me-2 selected-icon"></i>USD</a></li>
                  <li><a class="dropdown-item" href="#">EUR</a></li>
                  <li><a class="dropdown-item" href="#">GBP</a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>