<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$variant_key = isset($_POST['variant_key']) ? mysqli_real_escape_string($conn, trim($_POST['variant_key'])) : null;
$ip_address = $_SERVER['REMOTE_ADDR'];

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

$user_id = null;
$email = '';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Fetch user's email from db just to be sure
    $user_sql = "SELECT email FROM users WHERE id = $user_id";
    $user_result = mysqli_query($conn, $user_sql);
    if ($user_result && mysqli_num_rows($user_result) > 0) {
        $row = mysqli_fetch_assoc($user_result);
        $email = $row['email'];
    }
}

if (empty($email) && isset($_POST['email'])) {
    $email = trim($_POST['email']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

$email = mysqli_real_escape_string($conn, $email);

// Check if this IP address already submitted a notification for this product
$variant_check = $variant_key ? "AND variant_key = '$variant_key'" : "AND (variant_key IS NULL OR variant_key = '')";
$ip_check_sql = "SELECT id FROM stock_notifications WHERE ip_address = '$ip_address' AND product_id = $product_id $variant_check AND status = 'pending'";
$ip_check_res = mysqli_query($conn, $ip_check_sql);

if (mysqli_num_rows($ip_check_res) > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already subscribed for notifications for this product from this IP address.']);
    exit;
}

// Check if this email already submitted
$email_check_sql = "SELECT id FROM stock_notifications WHERE email = '$email' AND product_id = $product_id $variant_check AND status = 'pending'";
$email_check_res = mysqli_query($conn, $email_check_sql);

if (mysqli_num_rows($email_check_res) > 0) {
    echo json_encode(['success' => false, 'message' => 'This email is already subscribed for notifications for this product.']);
    exit;
}

// Insert
$variant_val = $variant_key ? "'$variant_key'" : "NULL";
$user_val = $user_id ? $user_id : "NULL";

$insert_sql = "INSERT INTO stock_notifications (user_id, email, product_id, variant_key, ip_address, status) 
               VALUES ($user_val, '$email', $product_id, $variant_val, '$ip_address', 'pending')";

if (mysqli_query($conn, $insert_sql)) {
    echo json_encode(['success' => true, 'message' => 'You will be notified when this is back in stock!']);
} else {
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
