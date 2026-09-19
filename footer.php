<?php
// Fetch general settings for footer
if (!isset($conn)) {
    require_once 'db_config.php';
}

// Define base URL if not already defined
if (!isset($base_url)) {
    $base_url = defined('BASE_URL') ? BASE_URL : '/';
}

$settings = [];
$settings_sql = "SELECT setting_key, setting_value FROM general_settings WHERE setting_key IN ('business_email', 'business_phone', 'business_address', 'website_tagline', 'business_timings', 'social_facebook', 'social_instagram', 'social_twitter', 'social_pinterest', 'social_youtube', 'social_whatsapp')";
$settings_result = mysqli_query($conn, $settings_sql);
if ($settings_result) {
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Set default values if settings don't exist
$business_email = $settings['business_email'] ?? 'hello@silkysaree.in';
$business_phone = $settings['business_phone'] ?? '079 2768 3326';
$business_address = $settings['business_address'] ?? 'Suryoday Society, Shivalay Apartments, 1, near Sardar Patel Colony, Naranpura, Ahmedabad, Gujarat 380013';
$website_tagline = $settings['website_tagline'] ?? 'A silky touch to beauty';
?>
<!-- Ensure Font Awesome is available for social icons (no SRI to avoid blocking if hash mismatches) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

<footer id="footer" class="footer">
    <div class="footer-main">
      <div class="container">
        <div class="row gy-4">
          <div class="col-lg-4 col-md-6">
            <div class="footer-widget footer-about">
              <a href="<?php echo $base_url; ?>" class="logo">
                <img src="<?php echo $base_url; ?>assets/img/silky.png" alt="" style="max-width: 150px;">
              </a>
              <p><?php echo htmlspecialchars($website_tagline); ?></p>

              <div class="social-links mt-4">
                <h5>Connect With Us</h5>
                <div class="social-icons">
<?php
                  // Map of supported platforms to Font Awesome icons and labels
                  $platforms = [
                      'facebook' => ['icon' => 'fab fa-facebook', 'label' => 'Facebook'],
                      'instagram' => ['icon' => 'fab fa-instagram', 'label' => 'Instagram'],
                      'twitter' => ['icon' => 'fab fa-twitter', 'label' => 'Twitter/X'],
                      'pinterest' => ['icon' => 'fab fa-pinterest', 'label' => 'Pinterest'],
                      'youtube' => ['icon' => 'fab fa-youtube', 'label' => 'YouTube'],
                      'whatsapp' => ['icon' => 'fab fa-whatsapp', 'label' => 'WhatsApp']
                  ];

                  $rendered = 0;
                  foreach ($platforms as $key => $meta) {
                      $setting_key = 'social_' . $key;
                      if (!empty($settings[$setting_key])) {
                          $url = htmlspecialchars($settings[$setting_key]);
                          echo "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener\" aria-label=\"{$meta['label']}\"><i class=\"{$meta['icon']}\"></i></a>\n";
                          $rendered++;
                      }
                  }

                  // Fallback: show default static icons if no social links configured
                  if ($rendered === 0) {
                      echo '<a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>';
                      echo '<a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>';
                      echo '<a href="#" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>';
                      echo '<a href="#" aria-label="Pinterest"><i class="bi bi-pinterest"></i></a>';
                      echo '<a href="#" aria-label="YouTube"><i class="bi bi-youtube"></i></a>';
                  }
?>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-2 col-md-6 col-sm-6">
            <div class="footer-widget">
              <h4>Shop</h4>
              <ul class="footer-links">
                <li><a href="category.html">New Arrivals</a></li>
                <li><a href="category.html">Bestsellers</a></li>
                <li><a href="category.html">Saree</a></li>
                <li><a href="category.html">Salwar Suit</a></li>
                <li><a href="category.html">Lehengas</a></li>
                <li><a href="category.html">Kurti</a></li>
              </ul>
            </div>
          </div>

          <div class="col-lg-2 col-md-6 col-sm-6">
            <div class="footer-widget">
              <h4>Support</h4>
              <ul class="footer-links">
                <li><a href="support.html">Help Center</a></li>
                <li><a href="account.html">Order Status</a></li>
                <li><a href="shiping-info.html">Shipping Info</a></li>
                <li><a href="return-policy.html">Returns &amp; Exchanges</a></li>
                <li><a href="#">Size Guide</a></li>
                <li><a href="contact.html">Contact Us</a></li>
              </ul>
            </div>
          </div>

          <div class="col-lg-4 col-md-6">
            <div class="footer-widget">
              <h4>Contact Information</h4>
              <div class="footer-contact">
                <div class="contact-item">
                  <i class="bi bi-geo-alt"></i>
                  <span><?php echo nl2br(htmlspecialchars($business_address)); ?></span>
                </div>
                <div class="contact-item">
                  <i class="bi bi-telephone"></i>
                  <span><?php echo htmlspecialchars($business_phone); ?></span>
                </div>
                <div class="contact-item">
                  <i class="bi bi-envelope"></i>
                  <span><?php echo htmlspecialchars($business_email); ?></span>
                </div>
                <div class="contact-item">
                  <i class="bi bi-clock"></i>
                  <span><?php echo nl2br(htmlspecialchars($settings['business_timings'] ?? "Monday-Saturday: 11:30am-9pm\nSunday: 12:00pm-8:00pm")); ?></span>
                </div>
              </div>

              
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <div class="container">
        <div class="row gy-3 align-items-center">
          <div class="col-lg-6 col-md-12">
            <div class="copyright">
              <p>&copy;<span> Copyright</span> <strong class="sitename">Silky Saree</strong>. All Rights Reserved.</p>
            </div>
            <div class="credits mt-1">
              Developed by <a href="https://coida.in">Coida Technologies</a>
            </div>
          </div>

          <div class="col-lg-6 col-md-12">
            <div class="d-flex flex-wrap justify-content-lg-end justify-content-center align-items-center gap-4">
                <div class="payment-methods">
                <div class="payment-icons">
        <?php
                  // Render only enabled payment methods (use Font Awesome icons)
                  $enabled_methods = [];
                  $pm_q = "SELECT payment_method FROM payment_settings WHERE is_enabled = 1";
                  if (isset($conn)) {
                    $pm_res = mysqli_query($conn, $pm_q);
                    if ($pm_res) {
                      while ($pm = mysqli_fetch_assoc($pm_res)) {
                        $enabled_methods[] = $pm['payment_method'];
                      }
                    }
                  }

                  $payment_icons_map = [
                    'cod' => ['icon' => 'fas fa-money-bill-wave', 'label' => 'Cash on Delivery'],
                    'stripe' => ['icon' => 'fab fa-cc-stripe', 'label' => 'Stripe'],
                    'paypal' => ['icon' => 'fab fa-paypal', 'label' => 'PayPal'],
                    'razorpay' => ['icon' => 'fas fa-credit-card', 'label' => 'Razorpay'],
                    'bank_transfer' => ['icon' => 'fas fa-university', 'label' => 'Bank Transfer']
                  ];

                  if (!empty($enabled_methods)) {
                    foreach ($enabled_methods as $method) {
                      $meta = $payment_icons_map[$method] ?? ['icon' => 'fas fa-credit-card', 'label' => ucfirst($method)];
                      echo "<i class=\"{$meta['icon']}\" aria-label=\"{$meta['label']}\"></i>\n";
                    }
                  } else {
                    // Fallback: show original static icons
                    echo '<i class="bi bi-credit-card" aria-label="Credit Card"></i>';
                    echo '<i class="bi bi-paypal" aria-label="PayPal"></i>';
                    echo '<i class="bi bi-apple" aria-label="Apple Pay"></i>';
                    echo '<i class="bi bi-google" aria-label="Google Pay"></i>';
                    echo '<i class="bi bi-shop" aria-label="Shop Pay"></i>';
                    echo '<i class="bi bi-cash" aria-label="Cash on Delivery"></i>';
                  }
        ?>
                </div>
                </div>

              <div class="legal-links">
                <a href="tos.php">Terms</a>
                <a href="privacy.php">Privacy</a>
                <a href="tos.php">Cookies</a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </footer>