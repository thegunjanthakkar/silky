<?php
// Define base URL if not already defined
if (!isset($base_url)) {
    $base_url = defined('BASE_URL') ? BASE_URL : '/';
}
?>
<nav class="mobile-bottom-nav d-block d-md-none">
    <div class="bottom-nav-container">
      <?php
      if (empty($conn) && file_exists('db_config.php')) {
          require_once 'db_config.php';
      }
      if (!empty($conn)) {
          $mob_res = mysqli_query($conn, "SELECT * FROM navigation_menus WHERE menu_type='mobile_bottom' ORDER BY display_order ASC");
          if ($mob_res) {
              while ($m = mysqli_fetch_assoc($mob_res)) {
                  // Special logic for badges (e.g. cart and wishlist)
                  $badge_html = '';
                  if (strtolower($m['title']) == 'cart') {
                      $badge_html = '<span class="cart-badge" style="display: none;">0</span>';
                  } elseif (strtolower($m['title']) == 'account' || strtolower($m['title']) == 'wishlist') {
                      $badge_html = '<span class="wishlist-badge" style="display: none;">0</span>';
                  }
                  
                  $data_attr = (strpos($m['link'], '#') === 0 && strlen($m['link']) > 1) ? 'data-bs-toggle="collapse" data-bs-target="'.$m['link'].'"' : '';
                  $href = (strpos($m['link'], '#') === 0) ? $m['link'] : htmlspecialchars($m['link']);
                  
                  echo '<a href="'.$href.'" class="nav-item" '.$data_attr.'>';
                  echo '<i class="'.htmlspecialchars($m['icon_class']).'"></i>';
                  echo '<span>'.htmlspecialchars($m['title']).'</span>';
                  echo $badge_html;
                  echo '</a>';
              }
          }
      }
      ?>
    </div>
  </nav>