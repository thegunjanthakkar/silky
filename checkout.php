<?php
// Start session to check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Kolkata');
require_once 'db_config.php';
require_once 'includes/email-functions.php';
require_once 'includes/stock-functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // User is not logged in, redirect to login page with return URL
    $return_url = urlencode($_SERVER['REQUEST_URI']);
    header("Location: login.php?redirect=" . $return_url);
    exit();
}

$user_id = intval($_SESSION['user_id']);

// Create necessary tables if they don't exist
$create_orders_table = "CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    shipping_cost DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    payment_method VARCHAR(50) NOT NULL,
    payment_status VARCHAR(20) DEFAULT 'pending',
    order_status VARCHAR(20) DEFAULT 'pending',
    shipping_address TEXT NOT NULL,
    billing_address TEXT DEFAULT NULL,
    customer_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_order_number (order_number)
)";
mysqli_query($conn, $create_orders_table);

$create_order_items_table = "CREATE TABLE IF NOT EXISTS order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    variant_info TEXT DEFAULT NULL,
    INDEX idx_order_id (order_id)
)";
mysqli_query($conn, $create_order_items_table);

$create_addresses_table = "CREATE TABLE IF NOT EXISTS customer_addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    address_type VARCHAR(20) DEFAULT 'shipping',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    street_address VARCHAR(255) NOT NULL,
    apartment VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    zip_code VARCHAR(20) NOT NULL,
    country VARCHAR(100) NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
)";
mysqli_query($conn, $create_addresses_table);



// Handle order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Log for debugging
    error_log("=== CHECKOUT: Order placement started ===");
    error_log("User ID: " . $user_id);
    error_log("POST data: " . print_r($_POST, true));

    // Get form data
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $street_address = mysqli_real_escape_string($conn, $_POST['address']);
    $apartment = mysqli_real_escape_string($conn, $_POST['apartment'] ?? '');
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $state = mysqli_real_escape_string($conn, $_POST['state']);
    $zip_code = mysqli_real_escape_string($conn, $_POST['zip']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $customer_notes = mysqli_real_escape_string($conn, $_POST['customer_notes'] ?? '');

    // Build shipping address JSON
    $shipping_address = json_encode([
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'phone' => $phone,
        'street_address' => $street_address,
        'apartment' => $apartment,
        'city' => $city,
        'state' => $state,
        'zip_code' => $zip_code,
        'country' => $country
    ]);

        // Fetch cart header (if any) and then cart items by user_id
        $cart_id = 0;
        $cart_total_price = 0;
        $cart_header_res = mysqli_query($conn, "SELECT id, total_price FROM cart WHERE user_id = $user_id LIMIT 1");
        if ($cart_header_res && mysqli_num_rows($cart_header_res) > 0) {
          $cart_header = mysqli_fetch_assoc($cart_header_res);
          $cart_id = intval($cart_header['id']);
          $cart_total_price = floatval($cart_header['total_price']);
        }

          $cart_sql = "SELECT ci.product_id, ci.quantity, ci.variant_info,
            ci.price,
            (ci.price * ci.quantity) AS subtotal, p.name, p.product_code, p.image
            FROM cart_items ci
            INNER JOIN products p ON ci.product_id = p.id
            WHERE ci.user_id = $user_id AND p.status = 'active'";
      $cart_result = mysqli_query($conn, $cart_sql);

    error_log("Cart query executed. Rows found: " . mysqli_num_rows($cart_result));

    if (mysqli_num_rows($cart_result) > 0) {
        // Calculate totals
        $subtotal = 0;
        $cart_items = [];
        $cart_id = 0;

        while ($item = mysqli_fetch_assoc($cart_result)) {
          $item_total = isset($item['subtotal']) ? floatval($item['subtotal']) : ($item['price'] * $item['quantity']);
          $subtotal += $item_total;
          $cart_items[] = $item;
        }

        // Calculate shipping and tax dynamically based on cart weight and state
        $settings_query_checkout = mysqli_query($conn, "SELECT setting_key, setting_value FROM general_settings WHERE setting_key IN ('shipping_cost_gujarat', 'shipping_cost_other_states', 'tax_percentage')");
        $shipping_cost_gujarat = 0;
        $shipping_cost_other_states = 0;
        $tax_rate = 0.05; // 5% default fallback
        
        if ($settings_query_checkout) {
            while ($setting = mysqli_fetch_assoc($settings_query_checkout)) {
                if ($setting['setting_key'] === 'shipping_cost_gujarat') {
                    $shipping_cost_gujarat = floatval($setting['setting_value']);
                } elseif ($setting['setting_key'] === 'shipping_cost_other_states') {
                    $shipping_cost_other_states = floatval($setting['setting_value']);
                } elseif ($setting['setting_key'] === 'tax_percentage') {
                    $tax_rate = round(floatval($setting['setting_value']) / 100, 4);
                }
            }
        }
        
        // Calculate total weight (grams)
        $total_grams = 0;
        foreach ($cart_items as $item) {
            $prod_id = intval($item['product_id']);
            $qty = intval($item['quantity']);
            // If variant_info exists we might need to check product_variants, but for simplicity assuming weight is on product table or same across variants if variants aren't fully structured
            $weight_res = mysqli_query($conn, "SELECT grams FROM products WHERE id = $prod_id");
            if ($w_row = mysqli_fetch_assoc($weight_res)) {
                $total_grams += floatval($w_row['grams']) * $qty;
            }
        }
        
        $total_kg = ceil($total_grams / 1000);
        if ($total_kg == 0 && $subtotal > 0) $total_kg = 1;
        
        if (strtolower(trim($state)) === 'gujarat') {
            $shipping_cost = $shipping_cost_gujarat * $total_kg;
        } else {
            $shipping_cost = $shipping_cost_other_states * $total_kg;
        }
        
        $shipping_cost = $subtotal > 0 ? round($shipping_cost, 2) : 0;
        
        $discount_amount = 0;
        if (isset($_SESSION['applied_coupon'])) {
            $coupon = $_SESSION['applied_coupon'];
            if (!isset($coupon['type'])) {
                unset($_SESSION['applied_coupon']);
            } else {
                if ($coupon['type'] === 'percentage') {
                    $discount_amount = $subtotal * ($coupon['value'] / 100);
                    if (isset($coupon['max_discount']) && $coupon['max_discount'] > 0 && $discount_amount > $coupon['max_discount']) {
                        $discount_amount = $coupon['max_discount'];
                    }
                } else if ($coupon['type'] === 'fixed') {
                    $discount_amount = $coupon['value'];
                }
                if ($discount_amount > $subtotal) {
                    $discount_amount = $subtotal;
                }
            }
        }
        
        $tax_amount = round($subtotal * $tax_rate, 2);
        $total_amount = round($subtotal + $shipping_cost + $tax_amount - $discount_amount, 2);

        // Cleanup: If the user has any abandoned 'pending' orders (e.g. dropped off at payment gateway), delete them to avoid duplicates
        $abandoned_sql = "SELECT id FROM orders WHERE user_id = $user_id AND order_status = 'pending' AND payment_status = 'pending'";
        $abandoned_res = @mysqli_query($conn, $abandoned_sql);
        if ($abandoned_res) {
            while ($abd = mysqli_fetch_assoc($abandoned_res)) {
                $abd_id = intval($abd['id']);
                @mysqli_query($conn, "DELETE FROM order_items WHERE order_id = $abd_id");
                @mysqli_query($conn, "DELETE FROM orders WHERE id = $abd_id");
            }
        }

        // Generate 4-digit sequential numeric order number (0001, 0002, 0003...)
        $max_res = @mysqli_query($conn, "SELECT MAX(id) as max_id FROM orders");
        $next_id = 1;
        if ($max_res && $max_row = mysqli_fetch_assoc($max_res)) {
            if (!empty($max_row['max_id'])) {
                $next_id = intval($max_row['max_id']) + 1;
            }
        }
        $order_number = sprintf('%04d', $next_id);

        // Determine payment status based on method
        $payment_status = 'pending';
        $order_status = ($payment_method === 'cod') ? 'confirmed' : 'pending';

        // Insert order
        $insert_order = "INSERT INTO orders (
            user_id, order_number, total_amount, subtotal, shipping_cost,
            tax_amount, discount_amount, payment_method, payment_status, order_status, shipping_address, customer_notes
        ) VALUES (
            $user_id, '$order_number', $total_amount, $subtotal, $shipping_cost,
            $tax_amount, $discount_amount, '$payment_method', '$payment_status', '$order_status', '$shipping_address', '$customer_notes'
        )";

        if (mysqli_query($conn, $insert_order)) {
            $order_id = mysqli_insert_id($conn);
            $order_number = sprintf('%04d', $order_id);
            @mysqli_query($conn, "UPDATE orders SET order_number = '$order_number' WHERE id = $order_id");
            error_log("Order inserted successfully! Order ID: $order_id, Order Number: $order_number");

            // Insert order items
            $items_inserted = 0;
            foreach ($cart_items as $item) {
                $product_id = intval($item['product_id']);
                $product_name = mysqli_real_escape_string($conn, $item['name']);
                $product_price = floatval($item['price']);
                $quantity = intval($item['quantity']);
                $item_subtotal = $product_price * $quantity;
                $variant_info_val = isset($item['variant_info']) ? mysqli_real_escape_string($conn, $item['variant_info']) : '';

                $insert_item = "INSERT INTO order_items (
                    order_id, product_id, product_name, product_price, quantity, subtotal, variant_info
                ) VALUES (
                    $order_id, $product_id, '$product_name', $product_price, $quantity, $item_subtotal, '$variant_info_val'
                )";

                if (mysqli_query($conn, $insert_item)) {
                    $items_inserted++;
                    error_log("Order item inserted: Product ID $product_id, Qty $quantity");
                } else {
                    error_log("Failed to insert order item: " . mysqli_error($conn));
                }
            }

            error_log("Total order items inserted: $items_inserted");

            // Save address if requested
            if (isset($_POST['save_address']) && $_POST['save_address'] == '1') {
                $save_address = "INSERT INTO customer_addresses (
                    user_id, first_name, last_name, email, phone,
                    street_address, apartment, city, state, zip_code, country
                ) VALUES (
                    $user_id, '$first_name', '$last_name', '$email', '$phone',
                    '$street_address', '$apartment', '$city', '$state', '$zip_code', '$country'
                )";
                mysqli_query($conn, $save_address);
            }

            // Handle payment based on method
            if ($payment_method === 'cod') {
                // Deduct stock for COD orders (specific variant & aggregate stock)
                deductOrderStock($conn, $order_id);

                // For COD, clear cart and redirect to confirmation
                mysqli_query($conn, "DELETE FROM cart WHERE user_id = $user_id");
                mysqli_query($conn, "DELETE FROM cart_items WHERE user_id = $user_id");
                unset($_SESSION['applied_coupon']);
                
                // Send order confirmation email
                sendOrderConfirmationEmail($conn, $order_id, $order_number, $email);
                
                error_log("COD Order completed successfully. Redirecting to confirmation page.");
                header('Location: order-confirmation.php?order=' . $order_number);
                exit();
            } else {
                // For online payments (Razorpay, etc.), initiate payment gateway
                error_log("Online payment required. Setting up payment gateway for order: $order_number");

                // Store order details in session for payment processing
                $_SESSION['pending_order_id'] = $order_id;
                $_SESSION['pending_order_number'] = $order_number;
                $_SESSION['pending_order_amount'] = $total_amount;
                $_SESSION['pending_payment_method'] = $payment_method;

                // Ensure session is written before redirecting
                session_write_close();

                // If debug_session flag is present, show diagnostic page. Otherwise redirect to payment.
                if (isset($_POST['debug_session']) && $_POST['debug_session'] == '1') {
                    ?>
                    <!doctype html>
                    <html>
                    <head>
                      <meta charset="utf-8">
                      <title>Order Created - Debug</title>
                      <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
                      <style>body{padding:20px}</style>
                    </head>
                    <body>
                      <div class="container">
                        <div class="alert alert-success">
                          <h4 class="alert-heading">Order Created (Debug)</h4>
                          <p>Order <strong><?php echo htmlspecialchars($order_number); ?></strong> (ID: <?php echo $order_id; ?>) was created and session values were saved.</p>
                          <hr>
                          <p><strong>Session pending_order_number:</strong> <?php echo isset($_SESSION['pending_order_number']) ? htmlspecialchars($_SESSION['pending_order_number']) : '<em>NOT SET</em>'; ?></p>
                          <p><strong>Session pending_order_id:</strong> <?php echo isset($_SESSION['pending_order_id']) ? htmlspecialchars($_SESSION['pending_order_id']) : '<em>NOT SET</em>'; ?></p>
                          <p><strong>Session pending_order_amount:</strong> <?php echo isset($_SESSION['pending_order_amount']) ? htmlspecialchars($_SESSION['pending_order_amount']) : '<em>NOT SET</em>'; ?></p>
                          <p><strong>Session pending_payment_method:</strong> <?php echo isset($_SESSION['pending_payment_method']) ? htmlspecialchars($_SESSION['pending_payment_method']) : '<em>NOT SET</em>'; ?></p>
                        </div>
                        <a href="process-payment.php?order=<?php echo urlencode($order_number); ?>" class="btn btn-primary">Continue to Payment</a>
                      </div>
                    </body>
                    </html>
                    <?php
                    exit();
                } else {
                    header('Location: process-payment.php?order=' . urlencode($order_number));
                    exit();
                }
            }
        } else {
            $error_message = "Failed to place order. Database error: " . mysqli_error($conn);
            error_log("Failed to insert order: " . mysqli_error($conn));
            error_log("Failed SQL: " . $insert_order);
        }
    } else {
        $error_message = "Your cart is empty.";
        error_log("Cart is empty for user $user_id");
    }
}

// Fetch cart header and then cart items for display (avoid joining on ci.cart_id)
$cart_id = 0;
$cart_total_price = 0;
$cart_header_res = mysqli_query($conn, "SELECT id, total_price FROM cart WHERE user_id = $user_id LIMIT 1");
if ($cart_header_res && mysqli_num_rows($cart_header_res) > 0) {
  $cart_header = mysqli_fetch_assoc($cart_header_res);
  $cart_id = intval($cart_header['id']);
  $cart_total_price = floatval($cart_header['total_price']);
}

$cart_sql = "SELECT ci.product_id, ci.quantity, ci.variant_info,
  CAST(REPLACE(REPLACE(p.price, '₹', ''), ',', '') AS DECIMAL(10,2)) AS price,
  (CAST(REPLACE(REPLACE(p.price, '₹', ''), ',', '') AS DECIMAL(10,2)) * ci.quantity) AS subtotal, p.name, p.product_code, p.image
  FROM cart_items ci
  INNER JOIN products p ON ci.product_id = p.id
  WHERE ci.user_id = $user_id AND p.status = 'active'";
$cart_result = mysqli_query($conn, $cart_sql);

$cart_items = [];
$subtotal = 0;

if ($cart_result) {
    while ($item = mysqli_fetch_assoc($cart_result)) {
        // Parse product image
        $image_url = 'assets/images/products/default.png';
        if (!empty($item['image'])) {
            $imageData = json_decode($item['image'], true);
            $img_name = '';
            if (is_array($imageData) && !empty($imageData[0])) {
                $img_name = $imageData[0];
            } else {
                $images_arr = array_map('trim', explode(',', $item['image']));
                $img_name = $images_arr[0] ?? '';
            }

            if (!empty($img_name)) {
                $clean_img = ltrim(preg_replace('~^\.?/~', '', $img_name), '/');
                $possiblePaths = [
                    $clean_img,
                    'uploads/products/' . $clean_img,
                    'uploads/' . $clean_img,
                    'admin/uploads/' . $clean_img,
                    'assets/img/product/' . $clean_img,
                    $img_name
                ];
                foreach ($possiblePaths as $path) {
                    if (file_exists($path) || file_exists(__DIR__ . '/' . $path)) {
                        $image_url = $path;
                        break;
                    }
                }
            }
        }

        $item['image_url'] = $image_url;
        $item['item_total'] = $item['price'] * $item['quantity'];
        $subtotal += $item['item_total'];
        $cart_items[] = $item;
    }
}

// Fetch saved addresses FIRST to use state for initial shipping calculation
$addresses_sql = "SELECT * FROM customer_addresses WHERE user_id = $user_id ORDER BY is_default DESC, created_at DESC LIMIT 1";
$address_result = mysqli_query($conn, $addresses_sql);
$saved_address = $address_result ? mysqli_fetch_assoc($address_result) : null;
$saved_state = $saved_address['state'] ?? '';

// Calculate shipping dynamically based on cart weight and state
$settings_query = mysqli_query($conn, "SELECT setting_key, setting_value FROM general_settings WHERE setting_key IN ('shipping_cost_gujarat', 'shipping_cost_other_states', 'tax_percentage')");
$shipping_cost_gujarat = 0;
$shipping_cost_other_states = 0;
$tax_rate = 0.05; // 5% default fallback

if ($settings_query) {
    while ($setting = mysqli_fetch_assoc($settings_query)) {
        if ($setting['setting_key'] === 'shipping_cost_gujarat') {
            $shipping_cost_gujarat = floatval($setting['setting_value']);
        } elseif ($setting['setting_key'] === 'shipping_cost_other_states') {
            $shipping_cost_other_states = floatval($setting['setting_value']);
        } elseif ($setting['setting_key'] === 'tax_percentage') {
            $tax_rate = round(floatval($setting['setting_value']) / 100, 4);
        }
    }
}

// Calculate total weight (grams)
$total_grams = 0;
foreach ($cart_items as $item) {
    $prod_id = intval($item['product_id']);
    $qty = intval($item['quantity']);
    $weight_res = mysqli_query($conn, "SELECT grams FROM products WHERE id = $prod_id");
    if ($w_row = mysqli_fetch_assoc($weight_res)) {
        $total_grams += floatval($w_row['grams']) * $qty;
    }
}

$total_kg = ceil($total_grams / 1000);
if ($total_kg == 0 && $subtotal > 0) $total_kg = 1;

if (strtolower(trim($saved_state)) === 'gujarat') {
    $shipping_cost = $shipping_cost_gujarat * $total_kg;
} else if (!empty($saved_state)) {
    $shipping_cost = $shipping_cost_other_states * $total_kg;
} else {
    $shipping_cost = 0; // Don't show cost until state is selected
}

$shipping_cost = $subtotal > 0 ? round($shipping_cost, 2) : 0;

$discount_amount = 0;
if (isset($_SESSION['applied_coupon'])) {
    $coupon = $_SESSION['applied_coupon'];
    if (!isset($coupon['type'])) {
        unset($_SESSION['applied_coupon']);
    } else {
        if ($coupon['type'] === 'percentage') {
            $discount_amount = $subtotal * ($coupon['value'] / 100);
            if (isset($coupon['max_discount']) && $coupon['max_discount'] > 0 && $discount_amount > $coupon['max_discount']) {
                $discount_amount = $coupon['max_discount'];
            }
        } else if ($coupon['type'] === 'fixed') {
            $discount_amount = $coupon['value'];
        }
        if ($discount_amount > $subtotal) {
            $discount_amount = $subtotal;
        }
    }
}

$tax_amount = round($subtotal * $tax_rate, 2);
$total_amount = round($subtotal + $shipping_cost + $tax_amount - $discount_amount, 2);

// Fetch active payment methods
$payment_methods_sql = "SELECT * FROM payment_settings WHERE is_enabled = 1 ORDER BY display_order ASC";
$payment_methods_result = mysqli_query($conn, $payment_methods_sql);
$payment_methods = [];
if ($payment_methods_result) {
    while ($method = mysqli_fetch_assoc($payment_methods_result)) {
        $payment_methods[] = $method;
    }
}

// Fetch user info
$user_sql = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_sql);
$user_info = $user_result ? mysqli_fetch_assoc($user_result) : null;

// If user is logged in, continue with checkout page
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Checkout - Silky Saree</title>
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
    .is-invalid {
      border-color: #dc3545 !important;
    }
    .is-invalid:focus {
      border-color: #dc3545 !important;
      box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
    }
    .place-order-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
    .payment-option {
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .payment-option.active {
      background-color: #f0f8ff;
      border-color: #0d6efd;
    }
    .payment-info-section {
      margin-top: 1rem;
    }
    .payment-info-box {
      animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .coupon-group {
      display: flex;
      gap: 0;
      border: 1px solid color-mix(in srgb, var(--default-color, #212529), transparent 75%);
      border-radius: 10px;
      overflow: hidden;
      margin: 16px 0;
      width: 100%;
    }
    .coupon-group input {
      border: none;
      padding: 10px 16px;
      font-size: 0.9rem;
      background: var(--surface-color, #ffffff);
      color: var(--default-color, #212529);
      flex: 1;
      width: 100%;
    }
    .coupon-group input:focus { outline: none; }
    .btn-coupon {
      background: linear-gradient(135deg, var(--heading-color, #333333), var(--accent-color, #6a9739));
      color: #fff;
      border: none;
      padding: 10px 18px;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.2s;
    }
    .btn-coupon:hover { opacity: 0.88; }
  </style>

</head>

<body class="checkout-page">

  <header id="header" class="header sticky-top">
    <!-- Top Bar -->
    <?php include './topbar.php'?>
    <!-- Main Header -->
    <?php include './main-header.php'?>
  </header>

  <main class="main">

    <!-- Page Title -->
    <div class="page-title light-background">
      <div class="container d-lg-flex justify-content-between align-items-center">
        <h1 class="mb-2 mb-lg-0">Checkout</h1>
        <nav class="breadcrumbs">
          <ol>
            <li><a href="index.html">Home</a></li>
            <li class="current">Checkout</li>
          </ol>
        </nav>
      </div>
    </div><!-- End Page Title -->

    <!-- Checkout Section -->
    <section id="checkout" class="checkout section">

      <div class="container" data-aos="fade-up" data-aos-delay="100">

        <div class="row">
          <div class="col-lg-7">
            <!-- Checkout Form -->
            <div class="checkout-container" data-aos="fade-up">
              <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                  <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
              <?php endif; ?>

              <?php if (empty($cart_items)): ?>
                <div class="alert alert-warning" id="empty-cart-msg" style="display: none;">
                  <h4>Your cart is empty</h4>
                  <p>Add some items to your cart before checking out.</p>
                  <a href="products.php" class="btn btn-primary">Continue Shopping</a>
                </div>
                <div id="sync-cart-msg" class="text-center py-5">
                  <div class="spinner-border text-primary mb-3" role="status"></div>
                  <h4>Loading your cart...</h4>
                </div>
                <script>
                  document.addEventListener('DOMContentLoaded', function() {
                    const localCart = JSON.parse(localStorage.getItem('cart') || '[]');
                    if (localCart.length > 0) {
                      fetch('cart.php?action=sync_cart', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ cart: localCart })
                      }).then(() => {
                        localStorage.removeItem('cart');
                        window.location.reload();
                      }).catch(() => {
                        document.getElementById('sync-cart-msg').style.display = 'none';
                        document.getElementById('empty-cart-msg').style.display = 'block';
                      });
                    } else {
                      document.getElementById('sync-cart-msg').style.display = 'none';
                      document.getElementById('empty-cart-msg').style.display = 'block';
                    }
                  });
                </script>
              <?php else: ?>
              <form class="checkout-form" method="POST" action="" id="checkoutForm">
                <!-- Customer Information -->
                <div class="checkout-section" id="customer-info">
                  <div class="section-header">
                    <div class="section-number">1</div>
                    <h3>Customer Information</h3>
                  </div>
                  <div class="section-content">
                    <div class="row">
                      <div class="col-md-6 form-group">
                        <label for="first-name">First Name</label>
                        <input type="text" name="first_name" class="form-control" id="first-name"
                               value="<?php echo htmlspecialchars($saved_address['first_name'] ?? $user_info['name'] ?? ''); ?>"
                               placeholder="Your First Name" required>
                      </div>
                      <div class="col-md-6 form-group">
                        <label for="last-name">Last Name</label>
                        <input type="text" name="last_name" class="form-control" id="last-name"
                               value="<?php echo htmlspecialchars($saved_address['last_name'] ?? ''); ?>"
                               placeholder="Your Last Name" required>
                      </div>
                    </div>
                    <div class="form-group">
                      <label for="email">Email Address</label>
                      <input type="email" class="form-control" name="email" id="email"
                             value="<?php echo htmlspecialchars($saved_address['email'] ?? $user_info['email'] ?? ''); ?>"
                             placeholder="Your Email" required>
                    </div>
                    <div class="form-group">
                      <label for="phone">Phone Number</label>
                      <input type="tel" class="form-control" name="phone" id="phone"
                             value="<?php echo htmlspecialchars($saved_address['phone'] ?? ''); ?>"
                             placeholder="Your Phone Number" required>
                    </div>
                  </div>
                </div>

                <!-- Shipping Address -->
                <div class="checkout-section" id="shipping-address">
                  <div class="section-header">
                    <div class="section-number">2</div>
                    <h3>Shipping Address</h3>
                  </div>
                  <div class="section-content">
                    <div class="form-group">
                      <label for="address">Street Address</label>
                      <input type="text" class="form-control" name="address" id="address"
                             value="<?php echo htmlspecialchars($saved_address['street_address'] ?? ''); ?>"
                             placeholder="Street Address" required>
                    </div>
                    <div class="form-group">
                      <label for="apartment">Apartment, Suite, etc. (optional)</label>
                      <input type="text" class="form-control" name="apartment" id="apartment"
                             value="<?php echo htmlspecialchars($saved_address['apartment'] ?? ''); ?>"
                             placeholder="Apartment, Suite, Unit, etc.">
                    </div>
                    <div class="row">
                      <div class="col-md-4 form-group">
                         <label for="city">City <span class="text-danger">*</span></label>
                         <input type="text" name="city" class="form-control" id="city"
                                value="<?php echo htmlspecialchars($saved_address['city'] ?? ''); ?>"
                                placeholder="City" required autocomplete="off">
                         <div id="city-error" class="invalid-feedback" style="display:none;"></div>
                       </div>
                      <div class="col-md-4 form-group">
                         <label for="state">State <span class="text-danger">*</span></label>
                         <select name="state" class="form-control" id="state" required>
                             <option value="">Select State</option>
                             <?php
                             $states = ["Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh", "Goa", "Gujarat", "Haryana", "Himachal Pradesh", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh", "Maharashtra", "Manipur", "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Punjab", "Rajasthan", "Sikkim", "Tamil Nadu", "Telangana", "Tripura", "Uttar Pradesh", "Uttarakhand", "West Bengal", "Andaman and Nicobar Islands", "Chandigarh", "Dadra and Nagar Haveli and Daman and Diu", "Delhi", "Jammu and Kashmir", "Ladakh", "Lakshadweep", "Puducherry"];
                             $saved_state = $saved_address['state'] ?? '';
                             foreach ($states as $s) {
                                 $selected = (strtolower(trim($saved_state)) == strtolower($s)) ? 'selected' : '';
                                 echo "<option value=\"$s\" $selected>$s</option>";
                             }
                             ?>
                         </select>
                         <div id="state-error" class="invalid-feedback" style="display:none;"></div>
                       </div>
                      <div class="col-md-4 form-group">
                         <label for="zip">PIN Code
                           <span id="pincode-status" style="font-size:0.78rem;font-weight:400;margin-left:6px;"></span>
                         </label>
                         <div style="position:relative;">
                           <input type="text" name="zip" class="form-control" id="zip"
                                  value="<?php echo htmlspecialchars($saved_address['zip_code'] ?? ''); ?>"
                                  placeholder="6-digit PIN Code" maxlength="6"
                                  inputmode="numeric" pattern="[0-9]{6}" required>
                           <span id="pincode-spinner" style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);">
                             <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                           </span>
                         </div>
                         <small class="text-muted" id="pincode-hint">Enter PIN code to auto-detect state &amp; city</small>
                       </div>
                    </div>
                    <div class="form-group">
                      <label for="country">Country</label>
                      <select class="form-select" id="country" name="country" required>
                        <option value="">Select Country</option>
                        <option value="US" <?php echo ($saved_address['country'] ?? '') == 'US' ? 'selected' : ''; ?>>United States</option>
                        <option value="IN" <?php echo ($saved_address['country'] ?? '') == 'IN' ? 'selected' : ''; ?>>India</option>
                        <option value="CA" <?php echo ($saved_address['country'] ?? '') == 'CA' ? 'selected' : ''; ?>>Canada</option>
                        <option value="UK" <?php echo ($saved_address['country'] ?? '') == 'UK' ? 'selected' : ''; ?>>United Kingdom</option>
                        <option value="AU" <?php echo ($saved_address['country'] ?? '') == 'AU' ? 'selected' : ''; ?>>Australia</option>
                        <option value="DE" <?php echo ($saved_address['country'] ?? '') == 'DE' ? 'selected' : ''; ?>>Germany</option>
                        <option value="FR" <?php echo ($saved_address['country'] ?? '') == 'FR' ? 'selected' : ''; ?>>France</option>
                      </select>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="save-address" name="save_address" value="1">
                      <label class="form-check-label" for="save-address">
                        Save this address for future orders
                      </label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="billing-same" name="billing-same" checked="">
                      <label class="form-check-label" for="billing-same">
                        Billing address same as shipping
                      </label>
                    </div>
                  </div>
                </div>

                <!-- Payment Method -->
                <div class="checkout-section" id="payment-method">
                  <div class="section-header">
                    <div class="section-number">3</div>
                    <h3>Payment Method</h3>
                  </div>
                  <div class="section-content">
                    <div class="payment-options">
                      <?php if (!empty($payment_methods)): ?>
                        <?php
                        $payment_icons = [
                          'cod' => 'bi-cash-coin',
                          'stripe' => 'bi-credit-card-2-front',
                          'paypal' => 'bi-paypal',
                          'razorpay' => 'bi-credit-card',
                          'bank_transfer' => 'bi-bank'
                        ];
                        foreach ($payment_methods as $index => $method):
                        ?>
                        <div class="payment-option <?php echo $index === 0 ? 'active' : ''; ?>">
                          <input type="radio" name="payment_method" id="payment-<?php echo $method['payment_method']; ?>"
                                 value="<?php echo $method['payment_method']; ?>" <?php echo $index === 0 ? 'checked' : ''; ?> required>
                          <label for="payment-<?php echo $method['payment_method']; ?>">
                            <span class="payment-icon">
                              <i class="bi <?php echo $payment_icons[$method['payment_method']] ?? 'bi-credit-card'; ?>"></i>
                            </span>
                            <span class="payment-label"><?php echo htmlspecialchars($method['display_name']); ?></span>
                          </label>
                        </div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <div class="alert alert-warning">
                          No payment methods available. Please contact support.
                        </div>
                      <?php endif; ?>
                    </div>

                    <!-- Payment Method Info -->
                    <div class="payment-info-section mt-3">
                      <?php foreach ($payment_methods as $method): ?>
                        <div class="payment-info-box d-none" id="info-<?php echo $method['payment_method']; ?>">
                          <div class="alert alert-info">
                            <strong><i class="bi <?php echo $payment_icons[$method['payment_method']] ?? 'bi-info-circle'; ?> me-2"></i><?php echo htmlspecialchars($method['display_name']); ?></strong>
                            <p class="mb-0 mt-2">
                              <?php
                              switch($method['payment_method']) {
                                case 'cod':
                                  echo 'Pay with cash when your order is delivered to your doorstep. Please keep exact change ready.';
                                  $config = json_decode($method['additional_config'], true) ?? [];
                                  if (!empty($config['extra_charges']) && $config['extra_charges'] > 0) {
                                    echo '<br><small class="text-muted">Extra COD charges: ₹' . number_format($config['extra_charges'], 2) . '</small>';
                                  }
                                  break;
                                case 'razorpay':
                                  echo 'You will be redirected to secure payment gateway to complete your payment using Credit/Debit Card, UPI, Net Banking, or Wallet.';
                                  break;
                                case 'stripe':
                                  echo 'You will be redirected to Stripe secure payment gateway to complete your payment using Credit/Debit Card.';
                                  break;
                                case 'paypal':
                                  echo 'You will be redirected to PayPal to complete your purchase securely.';
                                  break;
                                case 'bank_transfer':
                                  echo 'Transfer payment to our bank account. Order will be processed after payment verification.';
                                  break;
                                default:
                                  echo 'Complete your payment securely using this method.';
                              }
                              ?>
                            </p>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>

                <!-- Order Review -->
                <div class="checkout-section" id="order-review">
                  <div class="section-header">
                    <div class="section-number">4</div>
                    <h3>Review &amp; Place Order</h3>
                  </div>
                  <div class="section-content">
                    <div class="form-group">
                      <label for="customer-notes">Order Notes (Optional)</label>
                      <textarea class="form-control" name="customer_notes" id="customer-notes" rows="3"
                                placeholder="Any special instructions for your order"></textarea>
                    </div>
                    <div class="form-check terms-check">
                      <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                      <label class="form-check-label" for="terms">
                        I agree to the <a href="tos.php" target="_blank">Terms and Conditions</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>
                      </label>
                    </div>
                    <div class="place-order-container">
                      <button type="button" id="place-order-btn" name="place_order" class="btn btn-primary place-order-btn" onclick="doPlaceOrder()">
                        <span class="btn-text">Place Order</span>
                        <span class="btn-price">₹<?php echo number_format($total_amount, 2); ?></span>
                      </button>
                    </div>
                  </div>
                </div>
              </form>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-lg-5">
            <!-- Order Summary -->
            <div class="order-summary" data-aos="fade-left" data-aos-delay="200">
              <div class="order-summary-header">
                <h3>Order Summary</h3>
                <span class="item-count"><?php echo count($cart_items); ?> Item<?php echo count($cart_items) != 1 ? 's' : ''; ?></span>
              </div>

              <div class="order-summary-content">
                <div class="order-items">
                  <?php foreach ($cart_items as $item): ?>
                  <div class="order-item">
                    <div class="order-item-image">
                      <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="img-fluid" onerror="this.onerror=null;this.src='assets/images/products/default.png';">
                    </div>
                    <div class="order-item-details">
                      <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                      <?php if(!empty($item['product_code'])): ?>
                      <div style="font-size: 0.85rem; color: #666; font-family: monospace; margin-bottom: 2px;">
                          Code: <?php echo htmlspecialchars($item['product_code']); ?>
                      </div>
                      <?php endif; ?>
                      <?php if(!empty($item['variant_info'])): ?>
                      <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">
                          <?php echo htmlspecialchars($item['variant_info']); ?>
                      </div>
                      <?php endif; ?>
                      <div class="order-item-price">
                        <span class="quantity"><?php echo $item['quantity']; ?> ×</span>
                        <span class="price">₹<?php echo number_format($item['price'], 2); ?></span>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>

                <div class="coupon-group">
                  <input type="text" placeholder="Coupon code" id="coupon-input">
                  <button class="btn-coupon" id="apply-coupon">Apply</button>
                </div>

                <div class="order-totals">
                  <div class="order-subtotal d-flex justify-content-between mb-3">
                    <span class="text-muted">Subtotal</span>
                    <span class="font-weight-medium">₹<?php echo number_format($subtotal, 2); ?></span>
                  </div>
                  <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Shipping</span>
                    <span class="font-weight-medium" id="shipping-cost-display">
                        <?php if ($shipping_cost > 0): ?>
                            ₹<?php echo number_format($shipping_cost, 2); ?>
                        <?php else: ?>
                            Select State
                        <?php endif; ?>
                    </span>
                  </div>
                  <div class="order-tax d-flex justify-content-between mb-3">
                    <?php if ($tax_amount > 0): ?>
                        <span class="text-muted" id="tax-label">Tax (<?php echo ($tax_rate * 100); ?>%)</span>
                        <span class="font-weight-medium" id="tax-amount-display">₹<?php echo number_format($tax_amount, 2); ?></span>
                    <?php else: ?>
                        <span class="text-muted" id="tax-label">Tax</span>
                        <span class="font-weight-medium text-success" id="tax-amount-display" style="font-size: 0.9em;">Included</span>
                    <?php endif; ?>
                  </div>
                  <div class="order-discount d-flex justify-content-between mb-3">
                    <span class="text-muted">Discount</span>
                    <span class="font-weight-medium" id="discount-amount-display" style="<?php echo $discount_amount > 0 ? 'color: #dc3545;' : ''; ?>">
                        -₹<?php echo number_format($discount_amount, 2); ?>
                    </span>
                  </div>
                  <hr>
                  <div class="d-flex justify-content-between mt-2">
                    <h5 class="font-weight-bold">Total</h5>
                    <h5 class="font-weight-bold text-success" id="total-amount-display">₹<?php echo number_format($total_amount, 2); ?></h5>
                  </div>
                </div>

                <div class="secure-checkout mt-4">
                  <div class="secure-checkout-header">
                    <i class="bi bi-shield-lock"></i>
                    <span>Secure Checkout</span>
                  </div>
                  <div class="payment-icons">
                    <i class="bi bi-credit-card-2-front"></i>
                    <i class="bi bi-credit-card"></i>
                    <i class="bi bi-paypal"></i>
                    <i class="bi bi-apple"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Terms and Privacy Modals -->
        <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam in dui mauris. Vivamus hendrerit arcu sed erat molestie vehicula. Sed auctor neque eu tellus rhoncus ut eleifend nibh porttitor. Ut in nulla enim. Phasellus molestie magna non est bibendum non venenatis nisl tempor.</p>
                <p>Suspendisse in orci enim. Vivamus hendrerit arcu sed erat molestie vehicula. Sed auctor neque eu tellus rhoncus ut eleifend nibh porttitor. Ut in nulla enim. Phasellus molestie magna non est bibendum non venenatis nisl tempor.</p>
                <p>Suspendisse in orci enim. Vivamus hendrerit arcu sed erat molestie vehicula. Sed auctor neque eu tellus rhoncus ut eleifend nibh porttitor. Ut in nulla enim. Phasellus molestie magna non est bibendum non venenatis nisl tempor.</p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
              </div>
            </div>
          </div>
        </div>

        <div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="privacyModalLabel">Privacy Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam in dui mauris. Vivamus hendrerit arcu sed erat molestie vehicula. Sed auctor neque eu tellus rhoncus ut eleifend nibh porttitor. Ut in nulla enim.</p>
                <p>Suspendisse in orci enim. Vivamus hendrerit arcu sed erat molestie vehicula. Sed auctor neque eu tellus rhoncus ut eleifend nibh porttitor. Ut in nulla enim. Phasellus molestie magna non est bibendum non venenatis nisl tempor.</p>
                <p>Suspendisse in orci enim. Vivamus hendrerit arcu sed erat molestie vehicula. Sed auctor neque eu tellus rhoncus ut eleifend nibh porttitor. Ut in nulla enim. Phasellus molestie magna non est bibendum non venenatis nisl tempor.</p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
              </div>
            </div>
          </div>
        </div>

      </div>

    </section><!-- /Checkout Section -->

  </main>

  <!-- Footer -->


  <?php include './footer.php'?>

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

  <!-- CRITICAL: Set flag BEFORE main.js loads to prevent cart sync interference -->
  <script>
    window.skipCartSync = true;
    console.log('Cart sync disabled for checkout page');
  </script>

  <!-- Main JS File -->
  <script src="assets/js/main.js"></script>

  <script>
    // Checkout form handling - Override main.js preventDefault behavior
    document.addEventListener('DOMContentLoaded', function() {
      // Wait a tick to ensure main.js has finished initializing
      setTimeout(function() {
        const checkoutForm = document.getElementById('checkoutForm');

        if (checkoutForm) {
          console.log('Checkout form found - Setting up form submission');

          // CRITICAL: Remove all existing submit listeners added by main.js
          // We do this by cloning the form element which removes all event listeners
          const newForm = checkoutForm.cloneNode(true);
          checkoutForm.parentNode.replaceChild(newForm, checkoutForm);

          console.log('Removed main.js submit listeners');

          // Now add our own handlers to the new form
          const form = document.getElementById('checkoutForm'); // Get the new form reference

          // Handle payment method selection to show info
          const paymentRadios = form.querySelectorAll('input[name="payment_method"]');
          paymentRadios.forEach(radio => {
            radio.addEventListener('change', function() {
              // Hide all payment info boxes
              const allInfoBoxes = document.querySelectorAll('.payment-info-box');
              allInfoBoxes.forEach(box => box.classList.add('d-none'));

              // Show selected payment info
              const selectedInfo = document.querySelector('#info-' + this.value);
              if (selectedInfo) {
                selectedInfo.classList.remove('d-none');
              }

              // Update active state on payment options
              const allOptions = document.querySelectorAll('.payment-option');
              allOptions.forEach(opt => opt.classList.remove('active'));
              this.closest('.payment-option').classList.add('active');
            });
          });

          // Show info for pre-selected payment method
          const selectedPayment = form.querySelector('input[name="payment_method"]:checked');
          if (selectedPayment) {
            const selectedInfo = document.querySelector('#info-' + selectedPayment.value);
            if (selectedInfo) {
              selectedInfo.classList.remove('d-none');
            }
          }

          // Add submit handler with validation that ALLOWS form submission
          // Use capture phase to run before main.js handlers
          // We now use a programmatic submit function `doPlaceOrder()` which calls form.submit()
          // This bypasses any external submit event handlers that may prevent native submission.

          // Add input event listeners to clear validation styling
          const formInputs = form.querySelectorAll('input, select, textarea');
          formInputs.forEach(input => {
            input.addEventListener('input', function() {
              if (this.value.trim()) {
                this.classList.remove('is-invalid');
              }
            });
          });

          // Expose a global function to perform validation and native submit
          window.doPlaceOrder = function() {
            const f = document.getElementById('checkoutForm');
            if (!f) return;

            // Simple validation (matches server-side required attributes)
            let isValid = true;
            const requiredFields = f.querySelectorAll('[required]');
            requiredFields.forEach(field => {
              if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
              } else {
                field.classList.remove('is-invalid');
              }
            });

            const paymentMethod = f.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
              alert('Please select a payment method');
              isValid = false;
            }

            const termsCheckbox = f.querySelector('#terms');
            if (termsCheckbox && !termsCheckbox.checked) {
              alert('Please accept the terms and conditions');
              isValid = false;
            }

            if (!isValid) {
              const firstInvalid = f.querySelector('.is-invalid');
              if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalid.focus();
              }
              return false;
            }

            // Show loading state on button
            const btn = document.getElementById('place-order-btn');
            if (btn) {
              btn.disabled = true;
              btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing Order...';
            }



            // Ensure server sees this as a place_order submission
            var existingPlace = f.querySelector('input[name="place_order"]');
            if (!existingPlace) {
              var pl = document.createElement('input');
              pl.type = 'hidden'; pl.name = 'place_order'; pl.value = '1';
              f.appendChild(pl);
            }

            // Use native form.submit() to bypass event listeners
            try {
              f.submit();
            } catch (err) {
              console.error('Native submit failed:', err);
              alert('Unable to submit order. Please try again.');
              if (btn) btn.disabled = false;
            }
          };

          console.log('Checkout form setup complete - Form will POST to checkout.php for processing');
        }
      }, 150); // Slightly longer delay to ensure main.js has fully initialized
    });
  // ── Pincode logic ──────────────────────────────────────────────────────
  var pincodeTimer        = null;
  var pincodeExpectedCity  = '';   // district name from last successful lookup
  var pincodeExpectedState = '';   // state name from last successful lookup
  var lastPinLooked        = '';   // avoid re-fetching the same PIN

  function setPincodeStatus(type, msg) {
    var el      = document.getElementById('pincode-status');
    var hint    = document.getElementById('pincode-hint');
    var spinner = document.getElementById('pincode-spinner');
    var zip     = document.getElementById('zip');
    if (!el) return;
    spinner.style.display = 'none';
    if (type === 'loading') {
      el.innerHTML = '';
      spinner.style.display = 'inline-block';
      if (zip) zip.classList.remove('is-valid','is-invalid');
    } else if (type === 'success') {
      el.innerHTML = '<span style="color:#198754;"><i class="bi bi-check-circle-fill"></i> ' + msg + '</span>';
      if (zip) { zip.classList.add('is-valid'); zip.classList.remove('is-invalid'); }
      if (hint) hint.style.display = 'none';
    } else if (type === 'error') {
      el.innerHTML = '<span style="color:#dc3545;"><i class="bi bi-x-circle"></i> ' + msg + '</span>';
      if (zip) { zip.classList.add('is-invalid'); zip.classList.remove('is-valid'); }
      if (hint) hint.style.display = '';
    } else {
      el.innerHTML = '';
      if (zip) zip.classList.remove('is-valid','is-invalid');
      if (hint) hint.style.display = '';
    }
  }

  // Show / hide city mismatch error
  function setCityError(msg) {
    var cityEl  = document.getElementById('city');
    var errEl   = document.getElementById('city-error');
    if (!cityEl) return;
    if (msg) {
      cityEl.classList.add('is-invalid');
      if (errEl) { errEl.textContent = msg; errEl.style.display = 'block'; }
    } else {
      cityEl.classList.remove('is-invalid');
      if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
    }
  }

  // Show / hide state mismatch error
  function setStateError(msg) {
    var stateEl = document.getElementById('state');
    var errEl   = document.getElementById('state-error');
    if (!stateEl) return;
    if (msg) {
      stateEl.classList.add('is-invalid');
      if (errEl) { errEl.textContent = msg; errEl.style.display = 'block'; }
    } else {
      stateEl.classList.remove('is-invalid');
      if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
    }
  }

  // Clear all address fields (when user enters a new PIN)
  function clearAddressFields() {
    var ids = ['address','apartment','city','state'];
    ids.forEach(function(id) {
      var el = document.getElementById(id);
      if (!el) return;
      if (el.tagName === 'SELECT') {
        el.selectedIndex = 0;
      } else {
        el.value = '';
      }
      el.classList.remove('is-valid','is-invalid');
    });
    setCityError('');
    setStateError('');
    pincodeExpectedCity  = '';
    pincodeExpectedState = '';
    setPincodeStatus('clear');
    // Reset shipping display
    var sd = document.getElementById('shipping-cost-display');
    if (sd) sd.innerHTML = '<em class="text-muted">Select state</em>';
  }

  function lookupPincode(pincode) {
    if (pincode === lastPinLooked) return;  // skip duplicate lookups
    lastPinLooked = pincode;
    setPincodeStatus('loading');
    fetch('get_state_from_pincode.php?pincode=' + encodeURIComponent(pincode))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.state) {
          var stateEl = document.getElementById('state');
          var cityEl  = document.getElementById('city');
          var matched = false;

          // Auto-fill state
          if (stateEl) {
            for (var i = 0; i < stateEl.options.length; i++) {
              if (stateEl.options[i].value.toLowerCase() === data.state.toLowerCase()) {
                stateEl.selectedIndex = i;
                matched = true;
                break;
              }
            }
          }

          // Auto-fill city from pincode district
          if (data.city && cityEl) {
            cityEl.value = data.city;
            setCityError('');
          }

          // Store expected state & city for validation
          pincodeExpectedState = data.state || '';
          pincodeExpectedCity  = data.city  || '';

          if (matched) {
            setPincodeStatus('success', data.state + (data.city ? ', ' + data.city : ''));
            updateShippingDisplay();
          } else {
            setPincodeStatus('error', 'State not in list');
          }
        } else {
          setPincodeStatus('error', 'PIN not found');
          lastPinLooked = '';
        }
      })
      .catch(function() {
        setPincodeStatus('error', 'Lookup failed');
        lastPinLooked = '';
      });
  }

  var zipEl2 = document.getElementById('zip');
  if (zipEl2) {
    // Auto-lookup pre-filled PIN (after clone at 150ms)
    var prePin = zipEl2.value.replace(/\D/g, '');
    if (prePin.length === 6) {
      setTimeout(function() { lookupPincode(prePin); }, 300);
    }
  }

  // ── Document-level event delegation (survives form clone) ────────────────
  document.addEventListener('input', function(e) {
    if (!e.target) return;

    // ZIP / Pincode input
    if (e.target.id === 'zip') {
      var pin = e.target.value.replace(/\D/g, '');
      clearTimeout(pincodeTimer);

      if (pin.length < 6) {
        // User is editing PIN → clear all address fields immediately
        clearAddressFields();
        lastPinLooked = '';
        return;
      }
      if (pin.length === 6) {
        // Clear first, then lookup
        clearAddressFields();
        lastPinLooked = '';
        pincodeTimer = setTimeout(function() { lookupPincode(pin); }, 500);
      }
    }

    // City validation against expected district from pincode
    if (e.target.id === 'city') {
      var typedCity = e.target.value.trim().toLowerCase();
      if (pincodeExpectedCity && typedCity.length > 1) {
        var expected = pincodeExpectedCity.toLowerCase();
        // Allow if typed city contains or matches expected district (partial OK)
        if (typedCity.indexOf(expected) === -1 && expected.indexOf(typedCity) === -1) {
          setCityError('City doesn\'t match PIN code. Expected area: ' + pincodeExpectedCity);
        } else {
          setCityError('');
        }
      } else {
        setCityError('');
      }
    }
  });

  document.addEventListener('change', function(e) {
    if (!e.target) return;
    if (e.target.id === 'state') {
      // Validate state against pincode expected state
      if (pincodeExpectedState) {
        var selectedState = e.target.value.toLowerCase();
        var expectedState = pincodeExpectedState.toLowerCase();
        if (selectedState !== expectedState) {
          setStateError('State doesn\'t match PIN code. Expected: ' + pincodeExpectedState);
        } else {
          setStateError('');
        }
      } else {
        setStateError('');
      }
      updateShippingDisplay();
    }
  });

  // Trigger initial shipping calc if state is pre-filled (after clone)
  setTimeout(function() {
    var stateEl = document.getElementById('state');
    if (stateEl && stateEl.value) {
      updateShippingDisplay();
    }
  }, 350);

  // ── Shipping recalculation ────────────────────────────────────────────────
  function updateShippingDisplay() {
      var stateEl = document.getElementById('state');
      var state = stateEl ? stateEl.value : '';
      if (!state) {
          document.getElementById('shipping-cost-display').innerHTML = '<em class="text-muted">Select state</em>';
          return;
      }

      var shippingDisplay = document.getElementById('shipping-cost-display');
      shippingDisplay.innerHTML = '<span class="spinner-border spinner-border-sm text-secondary" style="width:14px;height:14px;"></span>';

      var formData = new FormData();
      formData.append('state', state);

      fetch('calculate_shipping.php', {
          method: 'POST',
          body: formData
      })
      .then(function(res) { return res.json(); })
      .then(function(data) {
          if (data.success) {
              var fmt = function(n) { return '₹' + parseFloat(n).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2}); };

              shippingDisplay.innerHTML = parseFloat(data.shipping_cost) > 0 ? fmt(data.shipping_cost) : '<span class="text-success fw-semibold">Free</span>';

              var totalDisplay = document.getElementById('total-amount-display');
              if (totalDisplay) totalDisplay.innerHTML = fmt(data.total_amount);

              var taxDisplay = document.getElementById('tax-amount-display');
              var taxLabel = document.getElementById('tax-label');
              if (taxDisplay) {
                  if (parseFloat(data.tax_amount) > 0) {
                      taxDisplay.innerHTML = fmt(data.tax_amount);
                      taxDisplay.className = 'font-weight-medium';
                      taxDisplay.style.fontSize = '';
                      if (taxLabel && data.tax_rate !== undefined) taxLabel.innerHTML = 'Tax (' + (data.tax_rate * 100) + '%)';
                  } else {
                      taxDisplay.innerHTML = 'Included';
                      taxDisplay.className = 'font-weight-medium text-success';
                      taxDisplay.style.fontSize = '0.9em';
                      if (taxLabel) taxLabel.innerHTML = 'Tax';
                  }
              }

              var discountDisplay = document.getElementById('discount-amount-display');
              if (discountDisplay) {
                  if (parseFloat(data.discount_amount) > 0) {
                      discountDisplay.innerHTML = '-' + fmt(data.discount_amount);
                      discountDisplay.style.color = '#dc3545';
                  } else {
                      discountDisplay.innerHTML = '-₹0.00';
                      discountDisplay.style.color = '';
                  }
              }

              // Update Place Order button price
              var btnPrice = document.querySelector('.btn-price');
              if (btnPrice) btnPrice.innerHTML = fmt(data.total_amount);
          }
      })
      .catch(function(err) {
          console.error('Shipping calc error:', err);
          shippingDisplay.innerHTML = '<span class="text-danger">Error</span>';
      });
  }


  // Coupon handling
  const couponInput = document.getElementById('coupon-input');
  const applyCouponBtn = document.getElementById('apply-coupon');
  
  if (couponInput) {
    <?php if (isset($_SESSION['applied_coupon'])): ?>
    couponInput.value = <?php echo json_encode($_SESSION['applied_coupon']['code'] ?? $_SESSION['applied_coupon']['coupon_code'] ?? ''); ?>;
    <?php endif; ?>
    
    couponInput.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
  }
  
  if (applyCouponBtn && couponInput) {
    applyCouponBtn.addEventListener('click', async function() {
        const code = couponInput.value.trim();
        if (!code) {
            if(typeof toast === 'function') toast('Please enter a coupon code', 'warning');
            else alert('Please enter a coupon code');
            return;
        }
        
        try {
            applyCouponBtn.innerHTML = '...';
            applyCouponBtn.disabled = true;
            
            const subtotal = <?php echo isset($subtotal) ? $subtotal : 0; ?>;
            
            const response = await fetch('cart.php?action=apply_coupon', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ coupon_code: code, cart_subtotal: subtotal })
            });
            
            const data = await response.json();
            if (data.status === 'success') {
                if(typeof toast === 'function') toast(data.message, 'success');
                else alert(data.message);
                updateShippingDisplay();
            } else {
                if(typeof toast === 'function') toast(data.message, 'danger');
                else alert(data.message);
                updateShippingDisplay();
            }
        } catch (error) {
            console.error(error);
            if(typeof toast === 'function') toast('Error applying coupon', 'danger');
            else alert('Error applying coupon');
        } finally {
            applyCouponBtn.innerHTML = 'Apply';
            applyCouponBtn.disabled = false;
        }
    });
  }
</script>

</body>

</html>