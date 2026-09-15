<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_POST['state'])) {
    echo json_encode(['error' => 'State is required']);
    exit;
}

$state = trim($_POST['state']);

// Get shipping settings
$settings_sql = "SELECT setting_key, setting_value FROM general_settings WHERE setting_key IN ('shipping_cost_gujarat', 'shipping_cost_other_states', 'tax_percentage')";
$settings_result = mysqli_query($conn, $settings_sql);
$settings = [];
if ($settings_result) {
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $settings[$row['setting_key']] = floatval($row['setting_value']);
    }
}

$shipping_cost_gujarat = $settings['shipping_cost_gujarat'] ?? 0;
$shipping_cost_other_states = $settings['shipping_cost_other_states'] ?? 0;
$tax_rate = ($settings['tax_percentage'] ?? 0) / 100;

// Calculate total weight of cart
$total_grams = 0;
$subtotal = 0;

if (isset($_SESSION['user_id'])) {
    // User is logged in, fetch from DB
    $user_id = intval($_SESSION['user_id']);
    $cart_sql = "SELECT ci.product_id, ci.quantity, p.price, p.grams 
                 FROM cart_items ci 
                 INNER JOIN products p ON ci.product_id = p.id 
                 WHERE ci.user_id = $user_id AND p.status = 'active'";
    $cart_result = mysqli_query($conn, $cart_sql);
    if ($cart_result) {
        while ($row = mysqli_fetch_assoc($cart_result)) {
            $qty = intval($row['quantity']);
            // The DB price might have formatting if p.price is varchar, so clean it
            $raw_price = str_replace(['₹', ','], '', $row['price']);
            $item_price = floatval($raw_price);
            
            $grams = floatval($row['grams']);
            $total_grams += ($grams * $qty);
            $subtotal += ($item_price * $qty);
        }
    }
} elseif (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    // Guest user, fetch from session
    foreach ($_SESSION['cart'] as $item) {
        $product_id = intval($item['product_id']);
        $qty = intval($item['quantity']);
        $item_price = 0;
        
        $sql = "SELECT price, grams FROM products WHERE id = $product_id";
        $result = mysqli_query($conn, $sql);
        if ($row = mysqli_fetch_assoc($result)) {
            $grams = floatval($row['grams']);
            $total_grams += ($grams * $qty);
            $raw_price = str_replace(['₹', ','], '', $row['price']);
            $item_price = floatval($raw_price);
            $subtotal += ($item_price * $qty);
        }
    }
}

// Calculate total kg (rounded up)
$total_kg = ceil($total_grams / 1000);
if ($total_kg == 0 && $subtotal > 0) {
    $total_kg = 1; // Default to 1kg if weight is missing but items exist
}

// Determine shipping rate based on state
if (strtolower($state) === 'gujarat') {
    $shipping_cost = $shipping_cost_gujarat * $total_kg;
} else {
    $shipping_cost = $shipping_cost_other_states * $total_kg;
}

// No items in cart = no shipping
if ($subtotal == 0) {
    $shipping_cost = 0;
}

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

echo json_encode([
    'success' => true,
    'subtotal' => number_format($subtotal, 2, '.', ''),
    'shipping_cost' => number_format($shipping_cost, 2, '.', ''),
    'tax_amount' => number_format($tax_amount, 2, '.', ''),
    'tax_rate' => $tax_rate,
    'discount_amount' => number_format($discount_amount, 2, '.', ''),
    'total_amount' => number_format($total_amount, 2, '.', ''),
    'total_grams' => $total_grams,
    'total_kg' => $total_kg,
    'state' => $state
]);
?>
