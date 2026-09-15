<?php
// Prevent any output before JSON
ob_start();
session_start();
ob_clean();
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

try {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    require_once '../db_config.php';

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
        exit;
    }

    $customerId = intval($_GET['id']);
    
    // Fetch customer details
    $sql = "SELECT id, email, first_name, last_name, phone, gender, status, email_verified, created_at FROM users WHERE id = $customerId";
    $result = mysqli_query($conn, $sql);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit;
    }

    $customer = mysqli_fetch_assoc($result);

    // Fetch orders count and total spent (based on email match)
    $email_esc = mysqli_real_escape_string($conn, $customer['email']);
    $orders_sql = "SELECT COUNT(id) as order_count, SUM(total_amount) as total_spent FROM orders WHERE JSON_UNQUOTE(JSON_EXTRACT(shipping_address, '$.email')) = '$email_esc' AND (payment_status = 'paid' OR order_status = 'confirmed')";
    $orders_result = mysqli_query($conn, $orders_sql);
    $orders_data = mysqli_fetch_assoc($orders_result);

    $customer['order_count'] = (int)($orders_data['order_count'] ?? 0);
    $customer['total_spent'] = (float)($orders_data['total_spent'] ?? 0);
    $customer['formatted_date'] = $customer['created_at'] ? date('d M Y, h:i A', strtotime($customer['created_at'])) : 'N/A';

    echo json_encode([
        'success' => true,
        'customer' => $customer
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error occurred']);
}
