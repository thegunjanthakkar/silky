<?php
session_start();
require_once 'db_config.php';
require_once 'includes/stock-functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Verify this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: checkout.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = intval($_POST['order_id']);
$order_number = mysqli_real_escape_string($conn, $_POST['order_number']);

// Verify order belongs to user
$order_sql = "SELECT * FROM orders WHERE id = $order_id AND user_id = $user_id AND order_number = '$order_number'";
$order_result = mysqli_query($conn, $order_sql);
$order = mysqli_fetch_assoc($order_result);

if (!$order) {
    die("Invalid order");
}

$payment_method = $order['payment_method'];

// Handle Razorpay payment verification
if ($payment_method === 'razorpay') {
    $razorpay_payment_id = mysqli_real_escape_string($conn, $_POST['razorpay_payment_id'] ?? '');
    $razorpay_order_id = mysqli_real_escape_string($conn, $_POST['razorpay_order_id'] ?? '');
    $razorpay_signature = mysqli_real_escape_string($conn, $_POST['razorpay_signature'] ?? '');

    // Fetch Razorpay settings
    $payment_settings_sql = "SELECT * FROM payment_settings WHERE payment_method = 'razorpay'";
    $payment_settings_result = mysqli_query($conn, $payment_settings_sql);
    $payment_settings = mysqli_fetch_assoc($payment_settings_result);

    if (!$payment_settings) {
        error_log("Razorpay settings not found");
        header("Location: checkout.php?error=payment_config_error");
        exit();
    }

    $api_secret = $payment_settings['api_secret'];

    // Verify signature
    $expected_signature = hash_hmac('sha256', $razorpay_order_id . "|" . $razorpay_payment_id, $api_secret);

    if ($expected_signature === $razorpay_signature) {
        // Signature is valid - payment successful
        error_log("Razorpay payment verified successfully for order: $order_number");

        // Update order status
        $update_sql = "UPDATE orders
                       SET payment_status = 'paid',
                           order_status = 'confirmed',
                           updated_at = CURRENT_TIMESTAMP
                       WHERE id = $order_id";

        if (mysqli_query($conn, $update_sql)) {
            // Deduct stock for Paid online orders (specific variant & aggregate stock)
            deductOrderStock($conn, $order_id);

            // Clear cart and cart items for the user
            mysqli_query($conn, "DELETE FROM cart_items WHERE user_id = $user_id");
            mysqli_query($conn, "DELETE FROM cart WHERE user_id = $user_id");
            error_log("Cleared cart and cart_items for user_id={$user_id}");
            
            // Parse shipping address to get customer email
            $shipping_address = json_decode($order['shipping_address'], true);
            $customer_email = $shipping_address['email'] ?? '';
            
            // Send order confirmation email
            if (!empty($customer_email)) {
                require_once 'includes/email-functions.php';
                sendOrderConfirmationEmail($conn, $order_id, $order_number, $customer_email);
            }

            // Store payment transaction details
            $transaction_sql = "CREATE TABLE IF NOT EXISTS payment_transactions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                order_id INT NOT NULL,
                payment_id VARCHAR(255) NOT NULL,
                payment_method VARCHAR(50) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                status VARCHAR(20) DEFAULT 'success',
                transaction_data TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_order_id (order_id)
            )";
            mysqli_query($conn, $transaction_sql);

            $transaction_data = json_encode([
                'razorpay_payment_id' => $razorpay_payment_id,
                'razorpay_order_id' => $razorpay_order_id,
                'razorpay_signature' => $razorpay_signature
            ]);

            $insert_transaction = "INSERT INTO payment_transactions
                                   (order_id, payment_id, payment_method, amount, transaction_data)
                                   VALUES ($order_id, '$razorpay_payment_id', 'razorpay', {$order['total_amount']}, '$transaction_data')";
            mysqli_query($conn, $insert_transaction);

            // Clear session
            unset($_SESSION['pending_order_id']);
            unset($_SESSION['pending_order_number']);
            unset($_SESSION['pending_order_amount']);
            unset($_SESSION['pending_payment_method']);

            // Redirect to success page
            header("Location: order-confirmation.php?order=" . $order_number . "&payment=success");
            exit();
        } else {
            error_log("Failed to update order status: " . mysqli_error($conn));
            header("Location: checkout.php?error=update_failed");
            exit();
        }
    } else {
        // Signature verification failed
        error_log("Razorpay signature verification failed for order: $order_number");
        error_log("Expected: $expected_signature, Got: $razorpay_signature");

        // Update order as payment failed
        mysqli_query($conn, "UPDATE orders SET payment_status = 'failed', order_status = 'cancelled' WHERE id = $order_id");

        header("Location: checkout.php?error=signature_mismatch");
        exit();
    }
} else {
    // Other payment methods can be handled here
    error_log("Unsupported payment method: $payment_method");
    header("Location: checkout.php?error=unsupported_method");
    exit();
}
?>
