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

// Include database connection
include 'db_config.php';

// Helper to parse product images
function get_first_image_url($image_json) {
    if (empty($image_json)) return 'assets/img/product/product-1.webp';
    $decoded = json_decode($image_json, true);
    if (is_array($decoded) && !empty($decoded)) {
        $path = $decoded[0];
        if (strpos($path, './') === 0) {
            $path = substr($path, 2);
        }
        return $path;
    }
    // Fallback if it's a plain string
    if (is_string($image_json) && $image_json !== '') {
        $path = $image_json;
        if (strpos($path, './') === 0) {
            $path = substr($path, 2);
        }
        return $path;
    }
    return 'assets/img/product/product-1.webp';
}

// Fetch user data from database
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($result);

// If user not found in database, logout
if (!$user_data) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle Return Request POST
$return_message = '';
$return_status = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_return') {
    $return_order_id = intval($_POST['order_id']);
    $return_reason = trim($_POST['return_reason']);
    $refund_method = trim($_POST['refund_method']);
    $return_comments = trim($_POST['return_comments']);
    
    // Verify order belongs to user and is eligible
    $check_q = "SELECT order_status FROM orders WHERE id = ? AND user_id = ?";
    $stmt_chk = mysqli_prepare($conn, $check_q);
    mysqli_stmt_bind_param($stmt_chk, "ii", $return_order_id, $user_id);
    mysqli_stmt_execute($stmt_chk);
    $res_chk = mysqli_stmt_get_result($stmt_chk);
    
    if ($row_chk = mysqli_fetch_assoc($res_chk)) {
        if (!in_array($row_chk['order_status'], ['cancelled', 'returned'])) { // Assuming eligible if not cancelled/returned
            // Insert return request
            $ins_q = "INSERT INTO return_requests (order_id, user_id, reason, refund_method, comments, status) VALUES (?, ?, ?, ?, ?, 'pending')";
            $stmt_ins = mysqli_prepare($conn, $ins_q);
            mysqli_stmt_bind_param($stmt_ins, "iisss", $return_order_id, $user_id, $return_reason, $refund_method, $return_comments);
            if (mysqli_stmt_execute($stmt_ins)) {
                $return_message = 'Return request submitted successfully. We will review it shortly.';
                $return_status = 'success';
                
                // Optionally update order status
                $upd_q = "UPDATE orders SET order_status = 'returned' WHERE id = ?";
                $stmt_upd = mysqli_prepare($conn, $upd_q);
                mysqli_stmt_bind_param($stmt_upd, "i", $return_order_id);
                mysqli_stmt_execute($stmt_upd);
            } else {
                $return_message = 'Failed to submit return request. Please try again.';
                $return_status = 'danger';
            }
        } else {
            $return_message = 'Order is not eligible for return.';
            $return_status = 'warning';
        }
    } else {
        $return_message = 'Invalid order.';
        $return_status = 'danger';
    }
}

// Handle Profile Update POST
$profile_msg = '';
$profile_status = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $dob = trim($_POST['date_of_birth'] ?? '');
    
    $upd_q = "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, date_of_birth=? WHERE id=?";
    $stmt_upd = mysqli_prepare($conn, $upd_q);
    mysqli_stmt_bind_param($stmt_upd, "sssssi", $first_name, $last_name, $email, $phone, $dob, $user_id);
    if (mysqli_stmt_execute($stmt_upd)) {
        $profile_msg = "Profile updated successfully.";
        $profile_status = "success";
        // refresh user data
        $query = "SELECT * FROM users WHERE id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    } else {
        $profile_msg = "Failed to update profile.";
        $profile_status = "danger";
    }
}

// Handle Password Update POST
$pass_msg = '';
$pass_status = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    
    if (password_verify($current_password, $user_data['password'])) {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $upd_pass_q = "UPDATE users SET password=? WHERE id=?";
        $stmt_pass = mysqli_prepare($conn, $upd_pass_q);
        mysqli_stmt_bind_param($stmt_pass, "si", $new_hash, $user_id);
        if (mysqli_stmt_execute($stmt_pass)) {
            $pass_msg = "Password changed successfully.";
            $pass_status = "success";
        } else {
            $pass_msg = "Failed to change password.";
            $pass_status = "danger";
        }
    } else {
        $pass_msg = "Incorrect current password.";
        $pass_status = "danger";
    }
}

// Handle Address Add/Edit/Delete POST
$addr_msg = '';
$addr_status = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_address' || $_POST['action'] === 'edit_address') {
        $addr_id = isset($_POST['address_id']) ? intval($_POST['address_id']) : 0;
        $addr_type = $_POST['address_type'] ?? 'Home';
        $fname = $_POST['first_name'];
        $lname = $_POST['last_name'];
        $phone = $_POST['phone'];
        $street = $_POST['street_address'];
        $apt = $_POST['apartment'] ?? '';
        $city = $_POST['city'];
        $state = $_POST['state'];
        $zip = $_POST['zip_code'];
        $country = $_POST['country'] ?? 'India';
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        if ($is_default) {
            $reset_q = "UPDATE customer_addresses SET is_default=0 WHERE user_id=?";
            $stmt_res = mysqli_prepare($conn, $reset_q);
            mysqli_stmt_bind_param($stmt_res, "i", $user_id);
            mysqli_stmt_execute($stmt_res);
        }
        
        if ($_POST['action'] === 'add_address') {
            $ins_addr = "INSERT INTO customer_addresses (user_id, address_type, first_name, last_name, phone, street_address, apartment, city, state, zip_code, country, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_ins = mysqli_prepare($conn, $ins_addr);
            mysqli_stmt_bind_param($stmt_ins, "issssssssssi", $user_id, $addr_type, $fname, $lname, $phone, $street, $apt, $city, $state, $zip, $country, $is_default);
            if (mysqli_stmt_execute($stmt_ins)) {
                $addr_msg = "Address added successfully.";
                $addr_status = "success";
            } else {
                $addr_msg = "Error adding address.";
                $addr_status = "danger";
            }
        } else {
            $upd_addr = "UPDATE customer_addresses SET address_type=?, first_name=?, last_name=?, phone=?, street_address=?, apartment=?, city=?, state=?, zip_code=?, country=?, is_default=? WHERE id=? AND user_id=?";
            $stmt_upd_addr = mysqli_prepare($conn, $upd_addr);
            mysqli_stmt_bind_param($stmt_upd_addr, "ssssssssssiii", $addr_type, $fname, $lname, $phone, $street, $apt, $city, $state, $zip, $country, $is_default, $addr_id, $user_id);
            if (mysqli_stmt_execute($stmt_upd_addr)) {
                $addr_msg = "Address updated successfully.";
                $addr_status = "success";
            } else {
                $addr_msg = "Error updating address.";
                $addr_status = "danger";
            }
        }
    } elseif ($_POST['action'] === 'delete_address') {
        $addr_id = intval($_POST['address_id']);
        $del_addr = "DELETE FROM customer_addresses WHERE id=? AND user_id=?";
        $stmt_del = mysqli_prepare($conn, $del_addr);
        mysqli_stmt_bind_param($stmt_del, "ii", $addr_id, $user_id);
        if (mysqli_stmt_execute($stmt_del)) {
            $addr_msg = "Address deleted.";
            $addr_status = "success";
        }
    } elseif ($_POST['action'] === 'set_default_address') {
        $addr_id = intval($_POST['address_id']);
        $reset_q = "UPDATE customer_addresses SET is_default=0 WHERE user_id=?";
        $stmt_res = mysqli_prepare($conn, $reset_q);
        mysqli_stmt_bind_param($stmt_res, "i", $user_id);
        mysqli_stmt_execute($stmt_res);
        
        $set_q = "UPDATE customer_addresses SET is_default=1 WHERE id=? AND user_id=?";
        $stmt_set = mysqli_prepare($conn, $set_q);
        mysqli_stmt_bind_param($stmt_set, "ii", $addr_id, $user_id);
        if (mysqli_stmt_execute($stmt_set)) {
            $addr_msg = "Default address updated.";
            $addr_status = "success";
        }
    }
}

// Handle Wishlist Remove
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_wishlist') {
    $wishlist_id = intval($_POST['wishlist_id']);
    $del_w = "DELETE FROM wishlists WHERE id=? AND user_id=?";
    $stmt_w = mysqli_prepare($conn, $del_w);
    mysqli_stmt_bind_param($stmt_w, "ii", $wishlist_id, $user_id);
    mysqli_stmt_execute($stmt_w);
}

// Handle Review Remove
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_review') {
    $review_id = intval($_POST['review_id']);
    $del_r = "DELETE FROM reviews WHERE id=? AND user_id=?";
    $stmt_r = mysqli_prepare($conn, $del_r);
    mysqli_stmt_bind_param($stmt_r, "ii", $review_id, $user_id);
    mysqli_stmt_execute($stmt_r);
}


// Fetch sidebar counts
$order_count_q = "SELECT COUNT(*) as cnt FROM orders WHERE user_id = ?";
$stmt_oc = mysqli_prepare($conn, $order_count_q);
mysqli_stmt_bind_param($stmt_oc, "i", $user_id);
mysqli_stmt_execute($stmt_oc);
$order_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_oc))['cnt'];

$wish_count_q = "SELECT COUNT(*) as cnt FROM wishlists WHERE user_id = ?";
$stmt_wc = mysqli_prepare($conn, $wish_count_q);
mysqli_stmt_bind_param($stmt_wc, "i", $user_id);
mysqli_stmt_execute($stmt_wc);
$wish_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_wc))['cnt'];

// Build avatar initials
$avatar_initials = strtoupper(substr($user_data['first_name'], 0, 1) . substr($user_data['last_name'], 0, 1));

// If user is logged in, continue with account page
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Account - NiceShop Bootstrap Template</title>
  <meta name="description" content="">
  <meta name="keywords" content="">

  <!-- Favicons -->
  <link href="assets/img/favicon/favicon.ico" rel="icon" type="image/x-icon">
  <link href="assets/img/favicon/favicon-32x32.png" rel="icon" type="image/png" sizes="32x32">
  <link href="assets/img/favicon/favicon-16x16.png" rel="icon" type="image/png" sizes="16x16">
  <link href="assets/img/favicon/apple-touch-icon.png" rel="apple-touch-icon" sizes="180x180">

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

  <style>
    /* Avatar Initials */
    .user-avatar .avatar-initials {
      width: 90px;
      height: 90px;
      border-radius: 50%;
      background: linear-gradient(135deg, #97c51d, #0e2187);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      font-weight: 700;
      letter-spacing: 2px;
      margin: 0 auto;
      box-shadow: 0 4px 20px rgba(124, 58, 237, 0.35);
    }
    
    /* Modal Styling Fixes */
    .modal-content {
      background-color: var(--surface-color, #ffffff) !important;
      border: none;
      box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    .modal-header .modal-title {
      color: var(--heading-color);
    }
    .modal .btn-primary {
      background-color: var(--accent-color);
      border-color: var(--accent-color);
      color: #fff;
    }
    .modal .btn-primary:hover {
      background-color: color-mix(in srgb, var(--accent-color), #000 10%);
      border-color: color-mix(in srgb, var(--accent-color), #000 10%);
    }
    .btn-invoice {
      background-color: #f0f4ff;
      color: #0e2187;
      border: 1px solid #d1dbff;
      text-decoration: none;
      text-align: center;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 0.75rem 1.5rem;
      font-weight: 500;
      border-radius: 8px;
      transition: all 0.3s ease;
      flex: 1;
    }
    .btn-invoice:hover {
      background-color: #e1e8ff;
      color: #0e2187;
    }
    .btn-details {
      background-color: #f8f9fa;
      color: var(--heading-color);
      border: 1px solid #ddd;
      text-decoration: none;
      text-align: center;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 0.75rem 1.5rem;
      font-weight: 500;
      border-radius: 8px;
      transition: all 0.3s ease;
      flex: 1;
    }
    .btn-details:hover {
      background-color: #e9ecef;
      color: var(--heading-color);
    }
    .btn-return-req {
      background-color: #f0fdf4;
      color: #16a34a;
      border: 1px solid #bbf7d0;
      text-decoration: none;
      text-align: center;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 0.75rem 1.5rem;
      font-weight: 500;
      border-radius: 8px;
      transition: all 0.3s ease;
      flex: 1;
    }
    .btn-return-req:hover {
      background-color: #dcfce7;
      color: #15803d;
    }
    .order-footer {
      display: flex;
      gap: 10px;
    }
    .order-footer button, .order-footer a {
      flex: 1;
    }
  </style>

</head>

<body class="account-page">

  <header id="header" class="header sticky-top">
    <!-- Top Bar -->
    <?php include 'topbar.php'; ?>

    <!-- Main Header -->
    <?php include 'main-header.php'; ?>

  <main class="main">

    <!-- Page Title -->
    <div class="page-title light-background">
      <div class="container d-lg-flex justify-content-between align-items-center">
        <h1 class="mb-2 mb-lg-0">Account</h1>
        <nav class="breadcrumbs">
          <ol>
            <li><a href="index.html">Home</a></li>
            <li class="current">Account</li>
          </ol>
        </nav>
      </div>
    </div><!-- End Page Title -->

    <!-- Account Section -->
    <section id="account" class="account section">

      <div class="container" data-aos="fade-up" data-aos-delay="100">

        <!-- Mobile Menu Toggle -->
        <div class="mobile-menu d-lg-none mb-4">
          <button class="mobile-menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#profileMenu">
            <i class="bi bi-grid"></i>
            <span>Menu</span>
          </button>
        </div>

        <div class="row g-4">
          <!-- Profile Menu -->
          <div class="col-lg-3">
            <div class="profile-menu collapse d-lg-block" id="profileMenu">
              <!-- User Info -->
              <div class="user-info" data-aos="fade-right">
                <div class="user-avatar">
                  <div class="avatar-initials"><?php echo htmlspecialchars($avatar_initials); ?></div>
                </div>
                <h4><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></h4>
                <p class="user-email" style="font-size:13px; color: var(--default-color); opacity:0.65; margin:2px 0 0;"><?php echo htmlspecialchars($user_data['email']); ?></p>
              </div>

              <!-- Navigation Menu -->
              <nav class="menu-nav">
                <ul class="nav flex-column" role="tablist">
                  <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#orders">
                      <i class="bi bi-box-seam"></i>
                      <span>My Orders</span>
                      <?php if ($order_count > 0): ?><span class="badge"><?php echo $order_count; ?></span><?php endif; ?>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#wishlist">
                      <i class="bi bi-heart"></i>
                      <span>Wishlist</span>
                      <?php if ($wish_count > 0): ?><span class="badge"><?php echo $wish_count; ?></span><?php endif; ?>
                    </a>
                  </li>
                  <!--
                  <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#wallet">
                      <i class="bi bi-wallet2"></i>
                      <span>Payment Methods</span>
                    </a>
                  </li>
                  -->
                  <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#reviews">
                      <i class="bi bi-star"></i>
                      <span>My Reviews</span>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#addresses">
                      <i class="bi bi-geo-alt"></i>
                      <span>Addresses</span>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#settings">
                      <i class="bi bi-gear"></i>
                      <span>Account Settings</span>
                    </a>
                  </li>
                </ul>

                <div class="menu-footer">
                  <a href="#" class="help-link">
                    <i class="bi bi-question-circle"></i>
                    <span>Help Center</span>
                  </a>
                  <a href="logout.php" class="logout-link">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Log Out</span>
                  </a>
                </div>
              </nav>
            </div>
          </div>

          <!-- Content Area -->
          <div class="col-lg-9">
            <div class="content-area">
              <div class="tab-content">
                <!-- Orders Tab -->
                <div class="tab-pane fade show active" id="orders">
                  <div class="section-header" data-aos="fade-up">
                    <h2>My Orders</h2>
                    <?php if (!empty($return_message)): ?>
                      <div class="alert alert-<?php echo $return_status; ?> alert-dismissible fade show w-100 mt-2" role="alert">
                        <?php echo htmlspecialchars($return_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>
                    <?php endif; ?>
                    <div class="header-actions">
                      <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" placeholder="Search orders...">
                      </div>
                      <div class="dropdown">
                        <button class="filter-btn" data-bs-toggle="dropdown">
                          <i class="bi bi-funnel"></i>
                          <span>Filter</span>
                        </button>
                        <ul class="dropdown-menu">
                          <li><a class="dropdown-item" href="#">All Orders</a></li>
                          <li><a class="dropdown-item" href="#">Processing</a></li>
                          <li><a class="dropdown-item" href="#">Shipped</a></li>
                          <li><a class="dropdown-item" href="#">Delivered</a></li>
                          <li><a class="dropdown-item" href="#">Cancelled</a></li>
                        </ul>
                      </div>
                    </div>
                  </div>

                  <div class="orders-grid">
                    <?php
                    $orders_query = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
                    $orders_stmt = mysqli_prepare($conn, $orders_query);
                    mysqli_stmt_bind_param($orders_stmt, "i", $user_id);
                    mysqli_stmt_execute($orders_stmt);
                    $orders_result = mysqli_stmt_get_result($orders_stmt);
                    
                    if (mysqli_num_rows($orders_result) > 0) {
                        $delay = 100;
                        while ($order = mysqli_fetch_assoc($orders_result)) {
                            $order_id = $order['id'];
                            $order_number = htmlspecialchars($order['order_number'] ?? '');
                            $order_date = date('M d, Y', strtotime($order['created_at']));
                            $order_status = strtolower($order['order_status']);
                            $total = number_format($order['total_amount'], 2);
                            
                            $status_class = 'processing';
                            if (in_array($order_status, ['confirmed', 'delivered'])) $status_class = 'delivered';
                            elseif (in_array($order_status, ['shipped', 'out for delivery'])) $status_class = 'shipped';
                            elseif (in_array($order_status, ['cancelled', 'returned'])) $status_class = 'cancelled';
                            
                            $items_q = "SELECT oi.*, p.image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?";
                            $items_stmt = mysqli_prepare($conn, $items_q);
                            mysqli_stmt_bind_param($items_stmt, "i", $order_id);
                            mysqli_stmt_execute($items_stmt);
                            $items_res = mysqli_stmt_get_result($items_stmt);
                            $items = [];
                            while ($it = mysqli_fetch_assoc($items_res)) {
                                $items[] = $it;
                            }
                            $item_count = count($items);
                            
                            $eligible_for_return = in_array($order_status, ['confirmed', 'out for delivery', 'delivered', 'placed']);
                            
                            $ret_q = "SELECT status FROM return_requests WHERE order_id = ? LIMIT 1";
                            $ret_stmt = mysqli_prepare($conn, $ret_q);
                            mysqli_stmt_bind_param($ret_stmt, "i", $order_id);
                            mysqli_stmt_execute($ret_stmt);
                            $ret_res = mysqli_stmt_get_result($ret_stmt);
                            $has_return_req = false;
                            $ret_status = '';
                            if ($ret_row = mysqli_fetch_assoc($ret_res)) {
                                $has_return_req = true;
                                $ret_status = $ret_row['status'];
                                $eligible_for_return = false;
                            }
                    ?>
                    <div class="order-card" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                      <div class="order-header">
                        <div class="order-id">
                          <span class="label">Order ID:</span>
                          <span class="value">#<?php echo $order_number; ?></span>
                        </div>
                        <div class="order-date"><?php echo $order_date; ?></div>
                      </div>
                      <div class="order-content">
                        <div class="product-grid">
                          <?php foreach(array_slice($items, 0, 3) as $it): ?>
                            <?php $preview_img = get_first_image_url($it['image']); ?>
                            <img src="<?php echo htmlspecialchars($preview_img); ?>" alt="Product" style="width:60px; height:60px; object-fit:cover; border-radius:8px; margin-right:5px;">
                          <?php endforeach; ?>
                          <?php if($item_count > 3): ?>
                            <span class="more-items">+<?php echo ($item_count - 3); ?></span>
                          <?php endif; ?>
                        </div>
                        <div class="order-info">
                          <div class="info-row">
                            <span>Status</span>
                            <span class="status <?php echo $status_class; ?>">
                              <?php 
                              if ($has_return_req) {
                                  echo "Return " . ucfirst($ret_status);
                              } else {
                                  echo ucfirst($order_status); 
                              }
                              ?>
                            </span>
                          </div>
                          <div class="info-row">
                            <span>Items</span>
                            <span><?php echo $item_count; ?> item(s)</span>
                          </div>
                          <div class="info-row">
                            <span>Total</span>
                            <span class="price">₹<?php echo $total; ?></span>
                          </div>
                        </div>
                      </div>
                      <div class="order-footer">
                        <?php if ($eligible_for_return): ?>
                          <button type="button" class="btn-review btn-return-req" data-order-id="<?php echo $order_id; ?>" data-order-number="<?php echo $order_number; ?>" data-bs-toggle="modal" data-bs-target="#returnModal">Request Return</button>
                        <?php endif; ?>
                        <a href="generate-invoice.php?order=<?php echo urlencode($order_number); ?>&download=1" class="btn-invoice"><i class="bi bi-download"></i> Invoice</a>
                        <a href="order-confirmation.php?order=<?php echo urlencode($order_number); ?>" class="btn-details" style="text-decoration: none; text-align: center;">View Details</a>
                      </div>
                    </div>
                    <?php 
                            $delay += 100;
                        } 
                    } else {
                        echo "<p>No orders found.</p>";
                    }
                    ?>
                    </div>
                  <!-- Pagination -->
                  <div class="pagination-wrapper" data-aos="fade-up">
                    <button type="button" class="btn-prev" disabled="">
                      <i class="bi bi-chevron-left"></i>
                    </button>
                    <div class="page-numbers">
                      <button type="button" class="active">1</button>
                      <button type="button">2</button>
                      <button type="button">3</button>
                      <span>...</span>
                      <button type="button">12</button>
                    </div>
                    <button type="button" class="btn-next">
                      <i class="bi bi-chevron-right"></i>
                    </button>
                  </div>
                </div>

                <!-- Wishlist Tab -->
                <div class="tab-pane fade" id="wishlist">
                  <div class="section-header" data-aos="fade-up">
                    <h2>My Wishlist</h2>
                    <div class="header-actions">
                      <button type="button" class="btn-add-all">Add All to Cart</button>
                    </div>
                  </div>

                  <div class="wishlist-grid">
                    <?php
                    $wish_q = "SELECT w.id as wishlist_id, p.* FROM wishlists w JOIN products p ON w.product_id = p.id WHERE w.user_id = ?";
                    $stmt_w = mysqli_prepare($conn, $wish_q);
                    mysqli_stmt_bind_param($stmt_w, "i", $user_id);
                    mysqli_stmt_execute($stmt_w);
                    $res_w = mysqli_stmt_get_result($stmt_w);
                    if (mysqli_num_rows($res_w) > 0) {
                        $delay = 100;
                        while ($wish = mysqli_fetch_assoc($res_w)) {
                    ?>
                    <div class="wishlist-card" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                      <div class="wishlist-image">
                        <?php 
                        $img = get_first_image_url($wish['image']);
                        ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" alt="Product" loading="lazy">
                        <form method="POST" style="display:inline;">
                          <input type="hidden" name="action" value="remove_wishlist">
                          <input type="hidden" name="wishlist_id" value="<?php echo $wish['wishlist_id']; ?>">
                          <button class="btn-remove" type="submit" aria-label="Remove from wishlist">
                            <i class="bi bi-trash"></i>
                          </button>
                        </form>
                      </div>
                      <div class="wishlist-content">
                        <h4><?php echo htmlspecialchars($wish['name']); ?></h4>
                        <div class="product-meta">
                          <div class="price">
                            <span class="current">₹<?php echo number_format($wish['price'], 2); ?></span>
                          </div>
                        </div>
                        <a href="product-details/<?php echo htmlspecialchars($wish['slug'] ?? 'product-'.$wish['id']); ?>" class="btn-add-cart">View Product</a>
                      </div>
                    </div>
                    <?php 
                            $delay += 100;
                        }
                    } else {
                        echo "<p>Your wishlist is empty.</p>";
                    }
                    ?>
                  </div>
                </div>

                <!-- Payment Methods Tab (Hidden for Security) -->
                <!-- 
                <div class="tab-pane fade" id="wallet">
                  <div class="section-header" data-aos="fade-up">
                    <h2>Payment Methods</h2>
                  </div>
                  <div class="payment-cards-grid">
                  </div>
                </div>
                -->
                <!-- Reviews Tab -->
                <div class="tab-pane fade" id="reviews">
                  <div class="section-header" data-aos="fade-up">
                    <h2>My Reviews</h2>
                    <div class="header-actions">
                      <div class="dropdown">
                        <button class="filter-btn" data-bs-toggle="dropdown">
                          <i class="bi bi-funnel"></i>
                          <span>Sort by: Recent</span>
                        </button>
                        <ul class="dropdown-menu">
                          <li><a class="dropdown-item" href="#">Recent</a></li>
                          <li><a class="dropdown-item" href="#">Highest Rating</a></li>
                          <li><a class="dropdown-item" href="#">Lowest Rating</a></li>
                        </ul>
                      </div>
                    </div>
                  </div>

                  <div class="reviews-grid">
                    <?php
                    $rev_q = "SELECT r.*, p.name as product_name, p.image FROM reviews r JOIN products p ON r.product_id = p.id WHERE r.user_id = ? ORDER BY r.created_at DESC";
                    $stmt_r = mysqli_prepare($conn, $rev_q);
                    mysqli_stmt_bind_param($stmt_r, "i", $user_id);
                    mysqli_stmt_execute($stmt_r);
                    $res_r = mysqli_stmt_get_result($stmt_r);
                    if (mysqli_num_rows($res_r) > 0) {
                        $delay = 100;
                        while ($rev = mysqli_fetch_assoc($res_r)) {
                    ?>
                    <div class="review-card" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                      <div class="review-header">
                        <?php $img = get_first_image_url($rev['image']); ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" alt="Product" class="product-image" loading="lazy">
                        <div class="review-meta">
                          <h4><?php echo htmlspecialchars($rev['product_name']); ?></h4>
                          <div class="rating">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <?php if($i <= $rev['rating']): ?>
                                    <i class="bi bi-star-fill"></i>
                                <?php else: ?>
                                    <i class="bi bi-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <span>(<?php echo number_format($rev['rating'], 1); ?>)</span>
                          </div>
                          <div class="review-date">Reviewed on <?php echo date('M d, Y', strtotime($rev['created_at'])); ?></div>
                        </div>
                      </div>
                      <div class="review-content">
                        <p><?php echo nl2br(htmlspecialchars($rev['review_text'])); ?></p>
                      </div>
                      <div class="review-footer">
                        <form method="POST" style="display:inline;">
                          <input type="hidden" name="action" value="delete_review">
                          <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                          <button type="submit" class="btn-delete">Delete</button>
                        </form>
                      </div>
                    </div>
                    <?php
                            $delay += 100;
                        }
                    } else {
                        echo "<p>You have not written any reviews yet.</p>";
                    }
                    ?>
                  </div>
                </div>

                <!-- Addresses Tab -->
                <div class="tab-pane fade" id="addresses">
                  <div class="section-header" data-aos="fade-up">
                    <h2>My Addresses</h2>
                    <?php if (!empty($addr_msg)): ?>
                      <div class="alert alert-<?php echo $addr_status; ?> alert-dismissible fade show w-100 mt-2" role="alert">
                        <?php echo htmlspecialchars($addr_msg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>
                    <?php endif; ?>
                    <div class="header-actions">
                      <button type="button" class="btn-add-new btn-add-addr" data-bs-toggle="modal" data-bs-target="#addressModal">
                        <i class="bi bi-plus-lg"></i>
                        Add New Address
                      </button>
                    </div>
                  </div>

                  <div class="addresses-grid">
                    <?php
                    $addr_q = "SELECT * FROM customer_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC";
                    $stmt_a = mysqli_prepare($conn, $addr_q);
                    mysqli_stmt_bind_param($stmt_a, "i", $user_id);
                    mysqli_stmt_execute($stmt_a);
                    $res_a = mysqli_stmt_get_result($stmt_a);
                    if (mysqli_num_rows($res_a) > 0) {
                        $delay = 100;
                        while ($addr = mysqli_fetch_assoc($res_a)) {
                            $is_def = $addr['is_default'] ? 'default' : '';
                    ?>
                    <div class="address-card <?php echo $is_def; ?>" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                      <div class="card-header">
                        <h4><?php echo htmlspecialchars($addr['address_type']); ?></h4>
                        <?php if($addr['is_default']): ?>
                          <span class="default-badge">Default</span>
                        <?php endif; ?>
                      </div>
                      <div class="card-body">
                        <p class="address-text">
                          <?php echo htmlspecialchars($addr['street_address']); ?><br>
                          <?php if(!empty($addr['apartment'])) echo htmlspecialchars($addr['apartment']) . '<br>'; ?>
                          <?php echo htmlspecialchars($addr['city']) . ', ' . htmlspecialchars($addr['state']) . ' ' . htmlspecialchars($addr['zip_code']); ?><br>
                          <?php echo htmlspecialchars($addr['country']); ?>
                        </p>
                        <div class="contact-info">
                          <div><i class="bi bi-person"></i> <?php echo htmlspecialchars($addr['first_name'] . ' ' . $addr['last_name']); ?></div>
                          <div><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($addr['phone']); ?></div>
                        </div>
                      </div>
                      <div class="card-actions">
                        <button type="button" class="btn-edit btn-edit-addr" 
                           data-id="<?php echo $addr['id']; ?>"
                           data-type="<?php echo htmlspecialchars($addr['address_type']); ?>"
                           data-fname="<?php echo htmlspecialchars($addr['first_name']); ?>"
                           data-lname="<?php echo htmlspecialchars($addr['last_name']); ?>"
                           data-phone="<?php echo htmlspecialchars($addr['phone']); ?>"
                           data-street="<?php echo htmlspecialchars($addr['street_address']); ?>"
                           data-apt="<?php echo htmlspecialchars($addr['apartment']); ?>"
                           data-city="<?php echo htmlspecialchars($addr['city']); ?>"
                           data-state="<?php echo htmlspecialchars($addr['state']); ?>"
                           data-zip="<?php echo htmlspecialchars($addr['zip_code']); ?>"
                           data-country="<?php echo htmlspecialchars($addr['country']); ?>"
                           data-def="<?php echo $addr['is_default']; ?>"
                           data-bs-toggle="modal" data-bs-target="#addressModal">
                          <i class="bi bi-pencil"></i>
                          Edit
                        </button>
                        <form method="POST" style="display:inline;">
                          <input type="hidden" name="action" value="delete_address">
                          <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                          <button type="submit" class="btn-remove">
                            <i class="bi bi-trash"></i> Remove
                          </button>
                        </form>
                        <?php if(!$addr['is_default']): ?>
                        <form method="POST" style="display:inline; margin-left: 10px;">
                          <input type="hidden" name="action" value="set_default_address">
                          <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                          <button type="submit" class="btn-make-default">Make Default</button>
                        </form>
                        <?php endif; ?>
                      </div>
                    </div>
                    <?php
                            $delay += 100;
                        }
                    } else {
                        echo "<p>No addresses found.</p>";
                    }
                    ?>
                  </div>
                </div>

                <!-- Settings Tab -->
                <div class="tab-pane fade" id="settings">
                  <div class="section-header" data-aos="fade-up">
                    <h2>Account Settings</h2>
                    <?php if (!empty($profile_msg)): ?>
                      <div class="alert alert-<?php echo $profile_status; ?> alert-dismissible fade show w-100 mt-2" role="alert">
                        <?php echo htmlspecialchars($profile_msg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div class="settings-content">
                    <!-- Personal Information -->
                    <div class="settings-section" data-aos="fade-up">
                      <h3>Personal Information</h3>
                      <form method="POST" class="settings-form">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row g-3">
                          <div class="col-md-6">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" id="firstName" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required="">
                          </div>
                          <div class="col-md-6">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" id="lastName" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required="">
                          </div>
                          <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required="">
                          </div>
                          <div class="col-md-6">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" id="phone" value="<?php echo htmlspecialchars($user_data['phone']); ?>">
                          </div>
                        </div>

                        <div class="form-buttons">
                          <button type="submit" class="btn-save">Save Changes</button>
                        </div>

                        
                        
                        
                      </form>
                    </div>

                    <!-- Email Preferences -->
                    <div class="settings-section" data-aos="fade-up" data-aos-delay="100">
                      <h3>Email Preferences</h3>
                      <div class="preferences-list">
                        <div class="preference-item">
                          <div class="preference-info">
                            <h4>Order Updates</h4>
                            <p>Receive notifications about your order status</p>
                          </div>
                          <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="orderUpdates" checked="">
                          </div>
                        </div>

                        <div class="preference-item">
                          <div class="preference-info">
                            <h4>Promotions</h4>
                            <p>Receive emails about new promotions and deals</p>
                          </div>
                          <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="promotions">
                          </div>
                        </div>

                        <div class="preference-item">
                          <div class="preference-info">
                            <h4>Newsletter</h4>
                            <p>Subscribe to our weekly newsletter</p>
                          </div>
                          <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="newsletter" checked="">
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="settings-section" data-aos="fade-up" data-aos-delay="200">
                      <h3>Security</h3>
                      <form method="POST" class="settings-form">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row g-3">
                          <div class="col-md-12">
                            <label for="currentPassword" class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" id="currentPassword" required="">
                          </div>
                          <div class="col-md-6">
                            <label for="newPassword" class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" id="newPassword" required="">
                          </div>
                          <div class="col-md-6">
                            <label for="confirmPassword" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required="">
                          </div>
                        </div>

                        <div class="form-buttons">
                          <button type="submit" class="btn-save">Update Password</button>
                        </div>

                        
                        
                        
                      </form>
                    </div>

                    <!-- Delete Account -->
                    <div class="settings-section danger-zone" data-aos="fade-up" data-aos-delay="300">
                      <h3>Delete Account</h3>
                      <div class="danger-zone-content">
                        <p>Once you delete your account, there is no going back. Please be certain.</p>
                        <button type="button" class="btn-danger">Delete Account</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

    </section><!-- /Account Section -->

  </main>
<!-- /Main -->
 <!-- Footer -->
  <?php include 'footer.php'; ?>

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

  <!-- Return Request Modal -->
  <div class="modal fade" id="returnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" action="">
          <div class="modal-header">
            <h5 class="modal-title">Request Return / Refund</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" value="submit_return">
            <input type="hidden" name="order_id" id="return_order_id">
            
            <p><strong>Order:</strong> <span id="return_order_number_display"></span></p>

            <div class="mb-3">
              <label class="form-label">Reason for Return</label>
              <select name="return_reason" class="form-select" required>
                <option value="">Select a reason</option>
                <option value="Defective / Damaged">Defective / Damaged Item</option>
                <option value="Wrong Item">Received Wrong Item</option>
                <option value="Not as Described">Item Not as Described</option>
                <option value="Changed Mind">Changed My Mind</option>
                <option value="Other">Other</option>
              </select>
            </div>
            
            <div class="mb-3">
              <label class="form-label">Preferred Refund Method</label>
              <select name="refund_method" class="form-select" required>
                <option value="Original Payment Method">Original Payment Method</option>
                <option value="Store Credit">Store Credit / Wallet</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Additional Comments (Optional)</label>
              <textarea name="return_comments" class="form-control" rows="3" placeholder="Please provide any additional details..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Submit Request</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Address Modal -->
  <div class="modal fade" id="addressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form method="POST" action="">
          <div class="modal-header">
            <h5 class="modal-title" id="addrModalTitle">Add New Address</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" id="addr_action" value="add_address">
            <input type="hidden" name="address_id" id="addr_id" value="">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" id="addr_fname" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" id="addr_lname" class="form-control" required>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="addr_phone" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Address Type</label>
                    <select name="address_type" id="addr_type" class="form-select" required>
                        <option value="Home">Home</option>
                        <option value="Office">Office</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Street Address</label>
                <input type="text" name="street_address" id="addr_street" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Apartment, suite, etc.</label>
                <input type="text" name="apartment" id="addr_apt" class="form-control">
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">City</label>
                    <input type="text" name="city" id="addr_city" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">State/Province</label>
                    <input type="text" name="state" id="addr_state" class="form-control" required>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Zip Code</label>
                    <input type="text" name="zip_code" id="addr_zip" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Country</label>
                    <input type="text" name="country" id="addr_country" class="form-control" value="India" required>
                </div>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_default" id="addr_def" value="1">
                <label class="form-check-label" for="addr_def">Set as default address</label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Address</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Return Modal logic
        const returnBtns = document.querySelectorAll('.btn-return-req');
        returnBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('return_order_id').value = this.getAttribute('data-order-id');
                document.getElementById('return_order_number_display').textContent = '#' + this.getAttribute('data-order-number');
            });
        });

        // Address Modal logic
        const addrModalTitle = document.getElementById('addrModalTitle');
        const addrAction = document.getElementById('addr_action');
        
        document.querySelectorAll('.btn-add-addr').forEach(btn => {
            btn.addEventListener('click', function() {
                addrModalTitle.textContent = 'Add New Address';
                addrAction.value = 'add_address';
                document.getElementById('addr_id').value = '';
                document.getElementById('addr_fname').value = '';
                document.getElementById('addr_lname').value = '';
                document.getElementById('addr_phone').value = '';
                document.getElementById('addr_type').value = 'Home';
                document.getElementById('addr_street').value = '';
                document.getElementById('addr_apt').value = '';
                document.getElementById('addr_city').value = '';
                document.getElementById('addr_state').value = '';
                document.getElementById('addr_zip').value = '';
                document.getElementById('addr_country').value = 'India';
                document.getElementById('addr_def').checked = false;
            });
        });

        document.querySelectorAll('.btn-edit-addr').forEach(btn => {
            btn.addEventListener('click', function() {
                addrModalTitle.textContent = 'Edit Address';
                addrAction.value = 'edit_address';
                document.getElementById('addr_id').value = this.getAttribute('data-id');
                document.getElementById('addr_fname').value = this.getAttribute('data-fname');
                document.getElementById('addr_lname').value = this.getAttribute('data-lname');
                document.getElementById('addr_phone').value = this.getAttribute('data-phone');
                document.getElementById('addr_type').value = this.getAttribute('data-type');
                document.getElementById('addr_street').value = this.getAttribute('data-street');
                document.getElementById('addr_apt').value = this.getAttribute('data-apt');
                document.getElementById('addr_city').value = this.getAttribute('data-city');
                document.getElementById('addr_state').value = this.getAttribute('data-state');
                document.getElementById('addr_zip').value = this.getAttribute('data-zip');
                document.getElementById('addr_country').value = this.getAttribute('data-country');
                document.getElementById('addr_def').checked = this.getAttribute('data-def') == '1';
            });
        });
    });
  </script>

  <!-- Main JS File -->
  <script src="assets/js/main.js"></script>

</body>

</html>