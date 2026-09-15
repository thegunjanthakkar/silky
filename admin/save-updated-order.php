<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'includes/permission-manager.php';
checkPageAccess(); 
require_once '../db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'update') {
    $_SESSION['add_order_error'] = 'Invalid request.';
    header('Location: orders.php');
    exit;
}

$order_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($order_id <= 0) {
    $_SESSION['add_order_error'] = 'Invalid order ID.';
    header('Location: orders.php');
    exit;
}

// Fetch current order to preserve other fields in JSON
$sql = "SELECT shipping_address, payment_status, order_status FROM orders WHERE id = $order_id LIMIT 1";
$result = mysqli_query($conn, $sql);
if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['add_order_error'] = 'Order not found.';
    header('Location: orders.php');
    exit;
}
$order = mysqli_fetch_assoc($result);

// Decode current shipping address
$shipping = [];
if (!empty($order['shipping_address'])) {
    $decoded = json_decode($order['shipping_address'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $shipping = $decoded;
    }
}

// Validate inputs
$payment_status = strtolower(trim($_POST['payment_status'] ?? 'pending'));
$order_status = strtolower(trim($_POST['order_status'] ?? 'pending'));
$customer_notes = trim($_POST['customer_notes'] ?? '');

$valid_payment_statuses = ['pending', 'paid', 'failed'];
$valid_order_statuses = ['pending', 'placed', 'processing', 'confirmed', 'out for delivery', 'delivered', 'cancelled', 'returned'];

if (!in_array($payment_status, $valid_payment_statuses)) {
    $payment_status = 'pending';
}
if (!in_array($order_status, $valid_order_statuses)) {
    $order_status = 'pending';
}

// Update shipping fields
$shipping['first_name'] = trim($_POST['first_name'] ?? '');
$shipping['last_name'] = trim($_POST['last_name'] ?? '');
$shipping['email'] = trim($_POST['email'] ?? '');
$shipping['phone'] = trim($_POST['phone'] ?? '');
$shipping['street_address'] = trim($_POST['street_address'] ?? '');
$shipping['apartment'] = trim($_POST['apartment'] ?? '');
$shipping['city'] = trim($_POST['city'] ?? '');
$shipping['state'] = trim($_POST['state'] ?? '');
$shipping['zip_code'] = trim($_POST['zip_code'] ?? '');
$shipping['country'] = trim($_POST['country'] ?? '');

// Re-encode JSON
$new_shipping_json = json_encode($shipping);

// Prepare statement
$update_sql = "UPDATE orders SET 
    payment_status = ?, 
    order_status = ?, 
    customer_notes = ?, 
    shipping_address = ?, 
    updated_at = NOW() 
    WHERE id = ?";

$stmt = mysqli_prepare($conn, $update_sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ssssi", 
        $payment_status, 
        $order_status, 
        $customer_notes, 
        $new_shipping_json, 
        $order_id
    );
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['add_order_success'] = 'Order updated successfully.';
        
        // Check if status changed
        if ($payment_status !== $order['payment_status'] || $order_status !== $order['order_status']) {
            require_once '../includes/email-functions.php';
            sendOrderStatusUpdateEmail($conn, $order_id);

            require_once '../includes/stock-functions.php';
            if ($order_status === 'cancelled') {
                restoreOrderStock($conn, $order_id);
            } elseif ($order['order_status'] === 'cancelled' && $order_status !== 'cancelled') {
                deductOrderStock($conn, $order_id);
            }
        }
    } else {
        $_SESSION['add_order_error'] = 'Error updating order: ' . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
} else {
    $_SESSION['add_order_error'] = 'Database error: ' . mysqli_error($conn);
}

header('Location: orders.php');
exit;
