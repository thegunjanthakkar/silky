<?php
// Define base URL if not already defined
if (!isset($base_url)) {
    $base_url = defined('BASE_URL') ? BASE_URL : '/';
}
?>
<div class="header-nav">
      <div class="container-fluid container-xl position-relative">
        <nav id="navmenu" class="navmenu">
          <ul>
            <li><a href="<?php echo $base_url; ?>index.php" class="active">Home</a></li>
            <li><a href="#">Saree</a></li>
            <li><a href="#">Salwar Suits</a></li>
            <li><a href="#">Lehengas</a></li>
            <li><a href="#">Kurti</a></li>
            <!--<li class="dropdown"><a href="#"><span>Dropdown</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>-->
              
        </nav>
      </div>
    </div>
