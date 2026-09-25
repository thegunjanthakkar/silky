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
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $pass_msg = "All password fields are required.";
        $pass_status = "danger";
    } elseif (strlen($new_password) < 6) {
        $pass_msg = "New password must be at least 6 characters long.";
        $pass_status = "danger";
    } elseif ($new_password !== $confirm_password) {
        $pass_msg = "New password and Confirm password do not match.";
        $pass_status = "danger";
    } elseif ($current_password === $new_password) {
        $pass_msg = "New password cannot be the same as your current password.";
        $pass_status = "warning";
    } else {
        $db_hash = $user_data['password'] ?? '';
        $is_valid = false;
        if (!empty($db_hash)) {
            if (password_verify($current_password, $db_hash)) {
                $is_valid = true;
            } elseif ($db_hash === md5($current_password) || $db_hash === $current_password) {
                $is_valid = true;
            }
        }
        
        if ($is_valid) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $upd_pass_q = "UPDATE users SET password=? WHERE id=?";
            $stmt_pass = mysqli_prepare($conn, $upd_pass_q);
            mysqli_stmt_bind_param($stmt_pass, "si", $new_hash, $user_id);
            if (mysqli_stmt_execute($stmt_pass)) {
                $pass_msg = "Password updated successfully.";
                $pass_status = "success";
                $user_data['password'] = $new_hash;
            } else {
                $pass_msg = "Database error while updating password. Please try again.";
                $pass_status = "danger";
            }
        } else {
            $pass_msg = "Current password is incorrect.";
            $pass_status = "danger";
        }
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



// Build avatar initials
$avatar_initials = strtoupper(substr($user_data['first_name'], 0, 1) . substr($user_data['last_name'], 0, 1));

// Determine active tab based on submitted action or default to orders
$active_tab = 'orders';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (in_array($_POST['action'], ['change_password', 'update_profile'])) {
        $active_tab = 'settings';
    } elseif (in_array($_POST['action'], ['add_address', 'edit_address', 'delete_address', 'set_default_address'])) {
        $active_tab = 'addresses';
    } elseif ($_POST['action'] === 'delete_review') {
        $active_tab = 'reviews';
    }
}

// If user is logged in, continue with account page
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Account - Silky saree</title>
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
    
    /* Modal & Backdrop Stacking & Z-Index Fixes */
    .modal-backdrop {
      z-index: 10500 !important;
    }
    .modal {
      z-index: 10550 !important;
    }
    .modal-dialog {
      z-index: 10600 !important;
      position: relative;
    }
    .modal-content {
      background-color: #ffffff !important;
      border: none !important;
      border-radius: 16px !important;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25) !important;
      position: relative;
      z-index: 10650 !important;
    }
    .modal-header, .modal-body, .modal-footer {
      background-color: #ffffff !important;
    }
    .modal-header .modal-title {
      color: var(--heading-color);
      font-weight: 700;
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

    /* Prevent floating elements from bleeding over modal backdrop */
    body.modal-open .scroll-top {
      display: none !important;
      visibility: hidden !important;
      pointer-events: none !important;
    }
    body.modal-open .mobile-bottom-nav {
      z-index: 1040 !important;
    }
    body.modal-open .header,
    body.modal-open .sticky-top {
      z-index: 1020 !important;
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
    .search-box {
      position: relative;
    }
    .search-box input {
      padding-right: 34px !important;
    }
    .search-box .clear-search-btn {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #94a3b8;
      cursor: pointer;
      padding: 0;
      line-height: 1;
      font-size: 15px;
      display: none;
      transition: color 0.2s ease;
    }
    .search-box .clear-search-btn:hover {
      color: #334155;
    }
    .dropdown-menu .dropdown-item {
      padding: 8px 14px;
      font-size: 14px;
      border-radius: 6px;
      margin: 2px 4px;
      width: calc(100% - 8px);
      transition: all 0.2s ease;
    }
    .dropdown-menu .dropdown-item:hover {
      background-color: rgba(0, 0, 0, 0.05);
    }
    .dropdown-menu .dropdown-item.active {
      background-color: var(--accent-color, #708238);
      color: #fff !important;
    }
    .dropdown-menu .dropdown-item.active i {
      color: #fff !important;
    }
    .filter-btn.dropdown-toggle::after {
      margin-left: 6px;
      vertical-align: middle;
    }
    .pagination-wrapper {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 25px;
    }

    /* Theme Consistent Buttons */
    .account .content-area .section-header .header-actions .btn-add-new,
    .btn-add-new {
      height: 44px;
      padding: 0 20px;
      background-color: var(--accent-color);
      color: #ffffff !important;
      border: none;
      border-radius: 12px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px color-mix(in srgb, var(--accent-color), transparent 75%);
    }
    .account .content-area .section-header .header-actions .btn-add-new:hover,
    .btn-add-new:hover {
      background-color: color-mix(in srgb, var(--accent-color), #000 12%);
      color: #ffffff !important;
      transform: translateY(-1px);
      box-shadow: 0 6px 16px color-mix(in srgb, var(--accent-color), transparent 60%);
    }
    .btn-add-new i {
      font-size: 15px;
    }

    /* Address Card Action Buttons */
    .account .addresses-grid .address-card .card-actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
    }
    .account .addresses-grid .address-card .card-actions button {
      height: 36px;
      padding: 0 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.25s ease;
      cursor: pointer;
    }
    .account .addresses-grid .address-card .card-actions button.btn-edit {
      background-color: color-mix(in srgb, var(--accent-color), transparent 90%);
      color: color-mix(in srgb, var(--accent-color), #000 20%);
      border: 1px solid color-mix(in srgb, var(--accent-color), transparent 75%);
    }
    .account .addresses-grid .address-card .card-actions button.btn-edit:hover {
      background-color: var(--accent-color);
      color: #ffffff;
      border-color: var(--accent-color);
      box-shadow: 0 3px 8px color-mix(in srgb, var(--accent-color), transparent 70%);
    }
    .account .addresses-grid .address-card .card-actions button.btn-remove {
      background-color: #fef2f2;
      color: #dc2626;
      border: 1px solid #fee2e2;
    }
    .account .addresses-grid .address-card .card-actions button.btn-remove:hover {
      background-color: #dc2626;
      color: #ffffff;
      border-color: #dc2626;
      box-shadow: 0 3px 8px rgba(220, 38, 38, 0.25);
    }
    .account .addresses-grid .address-card .card-actions button.btn-make-default {
      background-color: #f8fafc;
      color: #475569;
      border: 1px solid #cbd5e1;
    }
    .account .addresses-grid .address-card .card-actions button.btn-make-default:hover {
      background-color: #e2e8f0;
      color: #0f172a;
      border-color: #94a3b8;
    }

    /* Review Card Delete Button */
    .account .reviews-grid .review-card .review-footer button.btn-delete {
      background-color: #fef2f2;
      color: #dc2626;
      border: 1px solid #fee2e2;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 500;
      height: 36px;
      padding: 0 16px;
      transition: all 0.25s ease;
      cursor: pointer;
    }
    .account .reviews-grid .review-card .review-footer button.btn-delete:hover {
      background-color: #dc2626;
      color: #ffffff;
      border-color: #dc2626;
    }

    /* Modal Theme Buttons */
    .modal .btn-primary {
      background-color: var(--accent-color);
      border-color: var(--accent-color);
      color: #ffffff;
      border-radius: 10px;
      font-weight: 500;
      padding: 9px 22px;
      transition: all 0.3s ease;
      box-shadow: 0 3px 10px color-mix(in srgb, var(--accent-color), transparent 70%);
    }
    .modal .btn-primary:hover,
    .modal .btn-primary:focus {
      background-color: color-mix(in srgb, var(--accent-color), #000 12%);
      border-color: color-mix(in srgb, var(--accent-color), #000 12%);
      color: #ffffff;
    }
    .modal .btn-secondary {
      background-color: #f1f5f9;
      color: #475569;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      font-weight: 500;
      padding: 9px 20px;
      transition: all 0.3s ease;
    }
    .modal .btn-secondary:hover {
      background-color: #e2e8f0;
      color: #1e293b;
    }

    /* Reset Filters Button */
    #resetOrderFiltersBtn, .account .btn-outline-primary {
      color: var(--accent-color) !important;
      border-color: var(--accent-color) !important;
      background-color: transparent;
      border-radius: 20px;
      font-weight: 500;
      transition: all 0.25s ease;
    }
    #resetOrderFiltersBtn:hover, .account .btn-outline-primary:hover {
      background-color: var(--accent-color) !important;
      color: #ffffff !important;
      border-color: var(--accent-color) !important;
      box-shadow: 0 3px 10px color-mix(in srgb, var(--accent-color), transparent 70%);
    }

    /* Password Field Visibility Toggle */
    .password-input-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }
    .password-input-wrap .form-control {
      padding-right: 44px !important;
    }
    .password-toggle-btn {
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      background: transparent;
      border: none;
      color: #94a3b8;
      cursor: pointer;
      padding: 0;
      width: 34px;
      height: 34px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      font-size: 1.15rem;
      transition: all 0.2s ease;
      z-index: 5;
    }
    .password-toggle-btn:hover {
      color: var(--heading-color, #0e2187);
      background-color: rgba(14, 33, 135, 0.08);
    }
    .password-toggle-btn:focus {
      outline: none;
      color: var(--heading-color, #0e2187);
      box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent-color), transparent 75%);
    }
  </style>

</head>

<body class="account-page">

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
                    <a class="nav-link <?php echo $active_tab === 'orders' ? 'active' : ''; ?>" data-bs-toggle="tab" href="#orders">
                      <i class="bi bi-box-seam"></i>
                      <span>My Orders</span>
                      <?php if ($order_count > 0): ?><span class="badge"><?php echo $order_count; ?></span><?php endif; ?>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="wishlist.php">
                      <i class="bi bi-heart"></i>
                      <span>Wishlist</span>
                      <span class="badge wishlist-count" id="accountWishlistBadge" style="display:none;">0</span>
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
                    <a class="nav-link <?php echo $active_tab === 'reviews' ? 'active' : ''; ?>" data-bs-toggle="tab" href="#reviews">
                      <i class="bi bi-star"></i>
                      <span>My Reviews</span>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link <?php echo $active_tab === 'addresses' ? 'active' : ''; ?>" data-bs-toggle="tab" href="#addresses">
                      <i class="bi bi-geo-alt"></i>
                      <span>Addresses</span>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" data-bs-toggle="tab" href="#settings">
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
                <div class="tab-pane fade <?php echo $active_tab === 'orders' ? 'show active' : ''; ?>" id="orders">
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
                        <input type="text" id="orderSearchInput" placeholder="Search orders...">
                        <button type="button" id="clearOrderSearchBtn" class="clear-search-btn" title="Clear search">
                          <i class="bi bi-x-circle-fill"></i>
                        </button>
                      </div>
                      <div class="dropdown">
                        <button class="filter-btn dropdown-toggle" type="button" id="orderFilterBtn" data-bs-toggle="dropdown" aria-expanded="false">
                          <i class="bi bi-funnel"></i>
                          <span id="orderFilterLabel">Filter</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="orderFilterBtn" style="min-width: 200px;">
                          <li><a class="dropdown-item order-filter-item active d-flex align-items-center justify-content-between" href="#" data-status="all"><span><i class="bi bi-collection me-2 text-muted"></i> All Orders</span><i class="bi bi-check2 check-icon"></i></a></li>
                          <li><hr class="dropdown-divider my-1"></li>
                          <li><a class="dropdown-item order-filter-item d-flex align-items-center justify-content-between" href="#" data-status="confirmed"><span><i class="bi bi-check-circle me-2 text-success"></i> Confirmed</span><i class="bi bi-check2 check-icon d-none"></i></a></li>
                          <li><a class="dropdown-item order-filter-item d-flex align-items-center justify-content-between" href="#" data-status="processing"><span><i class="bi bi-hourglass-split me-2 text-warning"></i> Processing</span><i class="bi bi-check2 check-icon d-none"></i></a></li>
                          <li><a class="dropdown-item order-filter-item d-flex align-items-center justify-content-between" href="#" data-status="shipped"><span><i class="bi bi-truck me-2 text-primary"></i> Shipped</span><i class="bi bi-check2 check-icon d-none"></i></a></li>
                          <li><a class="dropdown-item order-filter-item d-flex align-items-center justify-content-between" href="#" data-status="delivered"><span><i class="bi bi-bag-check me-2 text-success"></i> Delivered</span><i class="bi bi-check2 check-icon d-none"></i></a></li>
                          <li><a class="dropdown-item order-filter-item d-flex align-items-center justify-content-between" href="#" data-status="cancelled"><span><i class="bi bi-x-circle me-2 text-danger"></i> Cancelled</span><i class="bi bi-check2 check-icon d-none"></i></a></li>
                          <li><a class="dropdown-item order-filter-item d-flex align-items-center justify-content-between" href="#" data-status="returned"><span><i class="bi bi-arrow-return-left me-2 text-secondary"></i> Returned</span><i class="bi bi-check2 check-icon d-none"></i></a></li>
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
                            $product_names_arr = [];
                            foreach ($items as $it) {
                                if (!empty($it['product_name'])) {
                                    $product_names_arr[] = $it['product_name'];
                                }
                            }
                            $product_names_str = implode(' ', $product_names_arr);
                            $clean_num = preg_replace('/[^a-zA-Z0-9]/', '', $order_number);
                            $search_keywords = strtolower($order_number . ' #' . $order_number . ' ' . $clean_num . ' ' . $product_names_str . ' ' . $order_status . ' ' . $order_date . ' ' . $total . ' ' . ($has_return_req ? 'return' : ''));
                    ?>
                    <div class="order-card" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>"
                         data-order-id="<?php echo $order_id; ?>"
                         data-order-number="<?php echo htmlspecialchars($order_number); ?>"
                         data-order-status="<?php echo htmlspecialchars($order_status); ?>"
                         data-has-return="<?php echo $has_return_req ? '1' : '0'; ?>"
                         data-order-search="<?php echo htmlspecialchars($search_keywords); ?>">
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
                        <a href="generate-invoice.php?order=<?php echo urlencode($order_number); ?>&download=1" class="btn-invoice"><i class="bi bi-download"></i> Invoice</a>
                        <a href="order-confirmation.php?order=<?php echo urlencode($order_number); ?>" class="btn-details" style="text-decoration: none; text-align: center;">View Details</a>
                      </div>
                    </div>
                    <?php 
                            $delay += 100;
                        } 
                    } else {
                        echo "<p class=\"text-muted py-3\">No orders found.</p>";
                    }
                    ?>
                    <div id="noMatchingOrders" class="text-center py-5 w-100" style="display: none; grid-column: 1 / -1;">
                      <div style="font-size: 2.5rem; color: #adb5bd; margin-bottom: 12px;">
                        <i class="bi bi-search"></i>
                      </div>
                      <h5 class="text-muted mb-2">No matching orders found</h5>
                      <p class="text-muted small mb-3">We couldn't find any orders matching your search or filter.</p>
                      <button type="button" id="resetOrderFiltersBtn" class="btn btn-sm btn-outline-primary px-3 py-2 rounded-pill">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filters
                      </button>
                    </div>
                    </div>
                  <!-- Pagination -->
                  <div class="pagination-wrapper" id="ordersPagination" data-aos="fade-up" style="display: none;">
                    <button type="button" class="btn-prev" id="ordersPrevBtn" disabled="" aria-label="Previous page">
                      <i class="bi bi-chevron-left"></i>
                    </button>
                    <div class="page-numbers" id="ordersPageNumbers">
                    </div>
                    <button type="button" class="btn-next" id="ordersNextBtn" disabled="" aria-label="Next page">
                      <i class="bi bi-chevron-right"></i>
                    </button>
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
                <div class="tab-pane fade <?php echo $active_tab === 'reviews' ? 'show active' : ''; ?>" id="reviews">
                  <div class="section-header" data-aos="fade-up">
                    <h2>My Reviews</h2>
                    <div class="header-actions">
                      <div class="dropdown">
                        <button class="filter-btn dropdown-toggle" type="button" id="reviewSortBtn" data-bs-toggle="dropdown" aria-expanded="false">
                          <i class="bi bi-funnel"></i>
                          <span id="reviewSortLabel">Sort by: Recent</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="reviewSortBtn" style="min-width: 180px;">
                          <li><a class="dropdown-item review-sort-item active d-flex align-items-center justify-content-between" href="#" data-sort="recent"><span><i class="bi bi-clock-history me-2 text-muted"></i> Recent</span><i class="bi bi-check2 check-icon"></i></a></li>
                          <li><a class="dropdown-item review-sort-item d-flex align-items-center justify-content-between" href="#" data-sort="highest"><span><i class="bi bi-star-fill me-2 text-warning"></i> Highest Rating</span><i class="bi bi-check2 check-icon d-none"></i></a></li>
                          <li><a class="dropdown-item review-sort-item d-flex align-items-center justify-content-between" href="#" data-sort="lowest"><span><i class="bi bi-star me-2 text-muted"></i> Lowest Rating</span><i class="bi bi-check2 check-icon d-none"></i></a></li>
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
                    <div class="review-card" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>"
                         data-rating="<?php echo (float)$rev['rating']; ?>"
                         data-timestamp="<?php echo strtotime($rev['created_at']); ?>">
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
                <div class="tab-pane fade <?php echo $active_tab === 'addresses' ? 'show active' : ''; ?>" id="addresses">
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
                <div class="tab-pane fade <?php echo $active_tab === 'settings' ? 'show active' : ''; ?>" id="settings">
                  <div class="section-header" data-aos="fade-up">
                    <h2>Account Settings</h2>
                    <?php if (!empty($profile_msg)): ?>
                      <div class="alert alert-<?php echo $profile_status; ?> alert-dismissible fade show w-100 mt-2" role="alert">
                        <?php echo htmlspecialchars($profile_msg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($pass_msg)): ?>
                      <div class="alert alert-<?php echo $pass_status; ?> alert-dismissible fade show w-100 mt-2" role="alert">
                        <?php echo htmlspecialchars($pass_msg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div class="settings-content">
                    <!-- Personal Information -->
                    <div class="settings-section" data-aos="fade-up">
                      <h3>Personal Information</h3>
                      <form method="POST" action="account.php#settings" class="settings-form">
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

                    <!-- Security Settings -->
                    <div class="settings-section" data-aos="fade-up" data-aos-delay="100">
                      <h3>Security</h3>
                      <?php if (!empty($pass_msg)): ?>
                        <div class="alert alert-<?php echo $pass_status; ?> alert-dismissible fade show w-100 mb-3" role="alert">
                          <i class="bi <?php echo $pass_status === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> me-2"></i>
                          <?php echo htmlspecialchars($pass_msg); ?>
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                      <?php endif; ?>
                      <div id="passwordClientError" class="alert alert-danger alert-dismissible fade show w-100 mb-3" style="display:none;" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <span id="passwordClientErrorText"></span>
                        <button type="button" class="btn-close" onclick="document.getElementById('passwordClientError').style.display='none';"></button>
                      </div>
                      <form method="POST" action="account.php#settings" class="settings-form" id="passwordChangeForm">
                        <input type="hidden" name="action" value="change_password">
                        <div class="row g-3">
                          <div class="col-md-12">
                            <label for="currentPassword" class="form-label">Current Password</label>
                            <div class="password-input-wrap">
                              <input type="password" class="form-control" name="current_password" id="currentPassword" required autocomplete="current-password" placeholder="Enter current password">
                              <button type="button" class="password-toggle-btn" tabindex="-1" onclick="togglePasswordVisibility('currentPassword', this)" title="Show/hide password">
                                <i class="bi bi-eye"></i>
                              </button>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <label for="newPassword" class="form-label">New Password</label>
                            <div class="password-input-wrap">
                              <input type="password" class="form-control" name="new_password" id="newPassword" required minlength="6" autocomplete="new-password" placeholder="Minimum 6 characters">
                              <button type="button" class="password-toggle-btn" tabindex="-1" onclick="togglePasswordVisibility('newPassword', this)" title="Show/hide password">
                                <i class="bi bi-eye"></i>
                              </button>
                            </div>
                            <small class="text-muted" style="font-size: 12px;">Minimum 6 characters</small>
                          </div>
                          <div class="col-md-6">
                            <label for="confirmPassword" class="form-label">Confirm Password</label>
                            <div class="password-input-wrap">
                              <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required minlength="6" autocomplete="new-password" placeholder="Re-type new password">
                              <button type="button" class="password-toggle-btn" tabindex="-1" onclick="togglePasswordVisibility('confirmPassword', this)" title="Show/hide password">
                                <i class="bi bi-eye"></i>
                              </button>
                            </div>
                          </div>
                        </div>

                        <div class="form-buttons">
                          <button type="submit" class="btn-save">Update Password</button>
                        </div>
                      </form>
                    </div>

                    <!-- Delete Account -->
                    <div class="settings-section danger-zone" data-aos="fade-up" data-aos-delay="200">
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

        // ============================================
        // Orders Filter, Search & Dynamic Pagination
        // ============================================
        (function() {
            const searchInput = document.getElementById('orderSearchInput');
            const clearSearchBtn = document.getElementById('clearOrderSearchBtn');
            const filterBtnLabel = document.getElementById('orderFilterLabel');
            const filterItems = document.querySelectorAll('.order-filter-item');
            const orderCards = Array.from(document.querySelectorAll('.orders-grid .order-card'));
            const noOrdersMsg = document.getElementById('noMatchingOrders');
            const resetFiltersBtn = document.getElementById('resetOrderFiltersBtn');
            const paginationWrapper = document.getElementById('ordersPagination');
            const pageNumbersContainer = document.getElementById('ordersPageNumbers');
            const prevBtn = document.getElementById('ordersPrevBtn');
            const nextBtn = document.getElementById('ordersNextBtn');

            if (!orderCards.length) return;

            let currentFilter = 'all';
            let searchQuery = '';
            let currentPage = 1;
            const itemsPerPage = 5;

            function matchesStatus(status, hasReturn, filter) {
                if (filter === 'all' || !filter) return true;
                status = (status || '').toLowerCase().trim();
                if (filter === 'returned') {
                    return status === 'returned' || hasReturn === '1';
                }
                if (filter === 'confirmed') {
                    return status === 'confirmed' || status === 'placed';
                }
                if (filter === 'processing') {
                    return status === 'processing' || status === 'pending';
                }
                if (filter === 'shipped') {
                    return status === 'shipped' || status === 'out for delivery';
                }
                if (filter === 'delivered') {
                    return status === 'delivered' || status === 'completed';
                }
                if (filter === 'cancelled') {
                    return status === 'cancelled' || status === 'canceled' || status === 'failed';
                }
                return status === filter;
            }

            function applyFilters() {
                const query = searchQuery.toLowerCase().trim();
                const matchingCards = [];

                orderCards.forEach(card => {
                    const status = card.getAttribute('data-order-status') || '';
                    const hasReturn = card.getAttribute('data-has-return') || '0';
                    const searchData = (card.getAttribute('data-order-search') || '').toLowerCase();

                    const statusMatch = matchesStatus(status, hasReturn, currentFilter);
                    const searchMatch = !query || searchData.includes(query);

                    if (statusMatch && searchMatch) {
                        matchingCards.push(card);
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (matchingCards.length === 0) {
                    if (noOrdersMsg) noOrdersMsg.style.display = 'block';
                    if (paginationWrapper) paginationWrapper.style.display = 'none';
                    return;
                }

                if (noOrdersMsg) noOrdersMsg.style.display = 'none';

                const totalPages = Math.ceil(matchingCards.length / itemsPerPage);
                if (currentPage > totalPages) currentPage = 1;

                if (totalPages <= 1) {
                    if (paginationWrapper) paginationWrapper.style.display = 'none';
                    matchingCards.forEach(card => card.style.display = '');
                } else {
                    if (paginationWrapper) paginationWrapper.style.display = 'flex';
                    const startIdx = (currentPage - 1) * itemsPerPage;
                    const endIdx = startIdx + itemsPerPage;

                    matchingCards.forEach((card, idx) => {
                        if (idx >= startIdx && idx < endIdx) {
                            card.style.display = '';
                        } else {
                            card.style.display = 'none';
                        }
                    });

                    renderPagination(totalPages);
                }

                if (window.AOS) window.AOS.refresh();
            }

            function renderPagination(totalPages) {
                if (!pageNumbersContainer) return;
                pageNumbersContainer.innerHTML = '';

                let pages = [];
                if (totalPages <= 7) {
                    for (let i = 1; i <= totalPages; i++) pages.push(i);
                } else {
                    if (currentPage <= 4) {
                        pages = [1, 2, 3, 4, 5, '...', totalPages];
                    } else if (currentPage >= totalPages - 3) {
                        pages = [1, '...', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
                    } else {
                        pages = [1, '...', currentPage - 1, currentPage, currentPage + 1, '...', totalPages];
                    }
                }

                pages.forEach(p => {
                    if (p === '...') {
                        const span = document.createElement('span');
                        span.textContent = '...';
                        pageNumbersContainer.appendChild(span);
                    } else {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.textContent = p;
                        if (p === currentPage) {
                            btn.classList.add('active');
                        }
                        btn.addEventListener('click', function() {
                            currentPage = p;
                            applyFilters();
                            const ordersSec = document.getElementById('orders');
                            if (ordersSec) ordersSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                        pageNumbersContainer.appendChild(btn);
                    }
                });

                if (prevBtn) {
                    prevBtn.disabled = currentPage === 1;
                    prevBtn.onclick = function() {
                        if (currentPage > 1) {
                            currentPage--;
                            applyFilters();
                            const ordersSec = document.getElementById('orders');
                            if (ordersSec) ordersSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    };
                }

                if (nextBtn) {
                    nextBtn.disabled = currentPage === totalPages;
                    nextBtn.onclick = function() {
                        if (currentPage < totalPages) {
                            currentPage++;
                            applyFilters();
                            const ordersSec = document.getElementById('orders');
                            if (ordersSec) ordersSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    };
                }
            }

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    searchQuery = this.value;
                    if (clearSearchBtn) {
                        clearSearchBtn.style.display = searchQuery.trim().length > 0 ? 'block' : 'none';
                    }
                    currentPage = 1;
                    applyFilters();
                });
            }

            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', function() {
                    if (searchInput) {
                        searchInput.value = '';
                        searchQuery = '';
                        this.style.display = 'none';
                        currentPage = 1;
                        applyFilters();
                        searchInput.focus();
                    }
                });
            }

            filterItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    filterItems.forEach(el => {
                        el.classList.remove('active');
                        const check = el.querySelector('.check-icon');
                        if (check) check.classList.add('d-none');
                    });

                    this.classList.add('active');
                    const check = this.querySelector('.check-icon');
                    if (check) check.classList.remove('d-none');

                    const status = this.getAttribute('data-status');
                    currentFilter = status;

                    if (filterBtnLabel) {
                        const labelSpan = this.querySelector('span');
                        const labelText = labelSpan ? labelSpan.textContent.trim() : this.textContent.trim();
                        filterBtnLabel.textContent = status === 'all' ? 'Filter' : labelText;
                    }

                    currentPage = 1;
                    applyFilters();
                });
            });

            if (resetFiltersBtn) {
                resetFiltersBtn.addEventListener('click', function() {
                    if (searchInput) searchInput.value = '';
                    searchQuery = '';
                    if (clearSearchBtn) clearSearchBtn.style.display = 'none';

                    currentFilter = 'all';
                    if (filterBtnLabel) filterBtnLabel.textContent = 'Filter';

                    filterItems.forEach(el => {
                        const isAll = el.getAttribute('data-status') === 'all';
                        el.classList.toggle('active', isAll);
                        const check = el.querySelector('.check-icon');
                        if (check) check.classList.toggle('d-none', !isAll);
                    });

                    currentPage = 1;
                    applyFilters();
                });
            }

            // Initial setup
            applyFilters();

            // Refresh AOS on tab change
            const orderTabLink = document.querySelector('a[href="#orders"]');
            if (orderTabLink) {
                orderTabLink.addEventListener('shown.bs.tab', function() {
                    if (window.AOS) window.AOS.refresh();
                });
            }
        })();

        // ============================================
        // Reviews Sorting
        // ============================================
        (function() {
            const sortBtnLabel = document.getElementById('reviewSortLabel');
            const sortItems = document.querySelectorAll('.review-sort-item');
            const reviewsGrid = document.querySelector('.reviews-grid');
            if (!reviewsGrid) return;

            sortItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    sortItems.forEach(el => {
                        el.classList.remove('active');
                        const check = el.querySelector('.check-icon');
                        if (check) check.classList.add('d-none');
                    });
                    this.classList.add('active');
                    const check = this.querySelector('.check-icon');
                    if (check) check.classList.remove('d-none');

                    const sortType = this.getAttribute('data-sort');
                    const labelSpan = this.querySelector('span');
                    const labelText = labelSpan ? labelSpan.textContent.trim() : this.textContent.trim();
                    if (sortBtnLabel) sortBtnLabel.textContent = 'Sort by: ' + labelText;

                    const cards = Array.from(reviewsGrid.querySelectorAll('.review-card'));
                    cards.sort((a, b) => {
                        const ratingA = parseFloat(a.getAttribute('data-rating') || 0);
                        const ratingB = parseFloat(b.getAttribute('data-rating') || 0);
                        const timeA = parseInt(a.getAttribute('data-timestamp') || 0);
                        const timeB = parseInt(b.getAttribute('data-timestamp') || 0);

                        if (sortType === 'highest') {
                            return ratingB - ratingA || timeB - timeA;
                        } else if (sortType === 'lowest') {
                            return ratingA - ratingB || timeB - timeA;
                        } else {
                            return timeB - timeA;
                        }
                    });

                    cards.forEach(card => reviewsGrid.appendChild(card));
                    if (window.AOS) window.AOS.refresh();
                });
            });
        })();
        // Support tab activation from URL hash (e.g. #settings) & remember on click
        const currentHash = window.location.hash;
        if (currentHash) {
            const targetLink = document.querySelector(`.menu-nav a[href="${currentHash}"]`);
            if (targetLink && window.bootstrap && window.bootstrap.Tab) {
                const tab = new bootstrap.Tab(targetLink);
                tab.show();
            }
        }
        document.querySelectorAll('.menu-nav a[data-bs-toggle="tab"]').forEach(link => {
            link.addEventListener('shown.bs.tab', function(e) {
                if (history.replaceState) {
                    history.replaceState(null, null, e.target.getAttribute('href'));
                }
            });
        });

        // Client-side Password Form Validation
        const pwdForm = document.getElementById('passwordChangeForm');
        if (pwdForm) {
            pwdForm.addEventListener('submit', function(e) {
                const curPwd = document.getElementById('currentPassword').value.trim();
                const newPwd = document.getElementById('newPassword').value;
                const confPwd = document.getElementById('confirmPassword').value;
                const errBox = document.getElementById('passwordClientError');
                const errText = document.getElementById('passwordClientErrorText');

                if (!curPwd) {
                    e.preventDefault();
                    if (errBox && errText) {
                        errText.textContent = 'Please enter your current password.';
                        errBox.style.display = 'block';
                        errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    return false;
                }

                if (newPwd.length < 6) {
                    e.preventDefault();
                    if (errBox && errText) {
                        errText.textContent = 'New password must be at least 6 characters long.';
                        errBox.style.display = 'block';
                        errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    return false;
                }

                if (newPwd !== confPwd) {
                    e.preventDefault();
                    if (errBox && errText) {
                        errText.textContent = 'New password and confirm password do not match.';
                        errBox.style.display = 'block';
                        errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    return false;
                }

                if (curPwd === newPwd) {
                    e.preventDefault();
                    if (errBox && errText) {
                        errText.textContent = 'New password cannot be identical to current password.';
                        errBox.style.display = 'block';
                        errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    return false;
                }
                
                if (errBox) errBox.style.display = 'none';
            });
        }

        // Update wishlist count badge from localStorage
        try {
            const wl = JSON.parse(localStorage.getItem('wishlist')) || [];
            const wlBadge = document.getElementById('accountWishlistBadge');
            if (wlBadge && wl.length > 0) {
                wlBadge.textContent = wl.length;
                wlBadge.style.display = 'inline-block';
            }
        } catch (e) {}
    });

    // Password Visibility Toggle
    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) {
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            }
        } else {
            input.type = 'password';
            if (icon) {
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    }
  </script>

  <!-- Main JS File -->
  <script src="assets/js/main.js"></script>

</body>

</html>