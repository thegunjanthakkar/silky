<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id'] ?? 0);
$order_param = isset($_GET['order']) ? mysqli_real_escape_string($conn, trim($_GET['order'])) : '';

// 1. If an order parameter is passed, check its status in the database first
if ($order_param !== '' && $user_id > 0) {
    $check_sql = "SELECT id, order_number, total_amount, payment_method, payment_status, order_status FROM orders WHERE order_number = '$order_param' AND user_id = $user_id LIMIT 1";
    $check_res = mysqli_query($conn, $check_sql);
    if ($check_res && mysqli_num_rows($check_res) > 0) {
        $existing_order = mysqli_fetch_assoc($check_res);
        $pay_status = strtolower(trim($existing_order['payment_status'] ?? ''));
        $ord_status = strtolower(trim($existing_order['order_status'] ?? ''));

        // If the order is ALREADY PAID or confirmed, redirect directly to order confirmation
        if ($pay_status === 'paid' || in_array($ord_status, ['confirmed', 'placed', 'processing', 'out for delivery', 'delivered'])) {
            header("Location: order-confirmation.php?order=" . urlencode($existing_order['order_number']) . "&payment=success");
            exit();
        }

        // If the order is still pending payment, restore session state so the user can pay smoothly
        if (!isset($_SESSION['pending_order_number'])) {
            $_SESSION['pending_order_id'] = intval($existing_order['id']);
            $_SESSION['pending_order_number'] = $existing_order['order_number'];
            $_SESSION['pending_order_amount'] = floatval($existing_order['total_amount']);
            $_SESSION['pending_payment_method'] = $existing_order['payment_method'];
        }
    }
}

// 2. If still missing required session data or order parameter, redirect cleanly to checkout
if (!isset($_SESSION['pending_order_number']) || !isset($_GET['order'])) {
    header("Location: checkout.php");
    exit();
}

$order_number = $_SESSION['pending_order_number'];
$order_id = $_SESSION['pending_order_id'];
$order_amount = $_SESSION['pending_order_amount'];
$payment_method = $_SESSION['pending_payment_method'];
$user_id = $_SESSION['user_id'];

// Fetch order details
$order_sql = "SELECT * FROM orders WHERE id = $order_id AND user_id = $user_id";
$order_result = mysqli_query($conn, $order_sql);
$order = mysqli_fetch_assoc($order_result);

if (!$order) {
    die("Order not found");
}

// Fetch payment gateway settings
$payment_sql = "SELECT * FROM payment_settings WHERE payment_method = '$payment_method' AND is_enabled = 1";
$payment_result = mysqli_query($conn, $payment_sql);
$payment_config = mysqli_fetch_assoc($payment_result);

if (!$payment_config) {
        die("Payment method not available");
}

// Basic validation: ensure API key is present for Razorpay
if ($payment_method === 'razorpay' && empty($payment_config['api_key'])) {
        // Show an informative page instead of trying to load checkout.js silently
        ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Payment Configuration Error</title>
            <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body class="p-5">
            <div class="container">
                <div class="alert alert-danger">
                    <h4 class="alert-heading">Payment Gateway Not Configured</h4>
                    <p>The Razorpay API key is not configured. Please configure the API key in the admin <code>payment_settings</code> table or contact support.</p>
                    <hr>
                    <a href="checkout.php" class="btn btn-primary">Return to Checkout</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
}

// Fetch user details
$user_sql = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_sql);
$user = mysqli_fetch_assoc($user_result);

// Parse shipping address
$shipping_address = json_decode($order['shipping_address'], true);

// Amount in smallest currency unit (paise for INR)
$order_amount = floatval($order_amount);
$amount_in_paise = (int)round($order_amount * 100);

// Log key info for debugging (will appear in PHP error log)
error_log("PROCESS-PAYMENT: order_number={$order_number}, order_id={$order_id}, amount={$order_amount}, amount_in_paise={$amount_in_paise}, payment_method={$payment_method}");
error_log("PAYMENT CONFIG: " . print_r($payment_config, true));

// For Razorpay: create a Razorpay Order server-side and pass `order_id` to checkout.js
$razorpay_order_id = '';
if ($payment_method === 'razorpay') {
    $api_key = $payment_config['api_key'];
    $api_secret = $payment_config['api_secret'];

    // Prepare payload
    $payload = json_encode([
        'amount' => $amount_in_paise,
        'currency' => 'INR',
        'receipt' => $order_number,
        'payment_capture' => 1
    ]);

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_USERPWD, $api_key . ':' . $api_secret);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $resp = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        error_log('Razorpay order creation failed: ' . $curl_err);
    } else {
        $respData = json_decode($resp, true);
        if ($httpcode >= 200 && $httpcode < 300 && !empty($respData['id'])) {
            $razorpay_order_id = $respData['id'];
            error_log('Razorpay order created: ' . $razorpay_order_id);
        } else {
            error_log('Razorpay order creation error: HTTP ' . $httpcode . ' Response: ' . $resp);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Payment - Silky Saree</title>

    <!-- Favicons -->
    <link href="assets/img/favicon/favicon.ico" rel="icon" type="image/x-icon">
    <link href="assets/img/favicon/favicon-32x32.png" rel="icon" type="image/png" sizes="32x32">
    <link href="assets/img/favicon/favicon-16x16.png" rel="icon" type="image/png" sizes="16x16">
    <link href="assets/img/favicon/apple-touch-icon.png" rel="apple-touch-icon" sizes="180x180">

    <!-- Bootstrap CSS -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">

    <!-- Main CSS -->
    <link href="assets/css/main.css" rel="stylesheet">

    <?php if ($payment_method === 'razorpay'): ?>
    <!-- Razorpay Checkout -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <?php endif; ?>

    <style>
        .payment-processing {
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .payment-card {
            max-width: 500px;
            margin: 0 auto;
            padding: 2rem;
            text-align: center;
        }
        .spinner-border-lg {
            width: 3rem;
            height: 3rem;
        }
        .order-summary-box {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
        }
    </style>
</head>
<body>

<header id="header" class="header sticky-top">
    <?php include './topbar.php'; ?>
    <?php include './main-header.php'; ?>
</header>

<main class="main">
    <div class="container">
        <div class="payment-processing">
            <div class="payment-card">
                <div class="card shadow-lg">
                    <div class="card-body">
                        <div class="mb-4">
                            <div class="spinner-border spinner-border-lg text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>

                        <h3>Processing Your Payment</h3>
                        <p class="text-muted">Please wait while we initialize the payment gateway...</p>

                        <div class="order-summary-box mt-4">
                            <h5 class="mb-3">Order Summary</h5>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Order Number:</span>
                                <strong><?php echo htmlspecialchars($order_number); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Amount:</span>
                                <strong class="text-primary">₹<?php echo number_format($order_amount, 2); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Payment Method:</span>
                                <strong><?php echo htmlspecialchars($payment_config['display_name']); ?></strong>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4">
                            <i class="bi bi-shield-check me-2"></i>
                            This is a secure payment gateway. Your payment details are safe and encrypted.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php if ($payment_method === 'razorpay'): ?>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Razorpay
    var options = {
        "key": "<?php echo htmlspecialchars($payment_config['api_key'] ?? ''); ?>",
        "amount": "<?php echo $amount_in_paise; ?>",
        "currency": "INR",
        "name": "Silky Saree",
        "description": "Order #<?php echo $order_number; ?>",
        // Include Razorpay `order_id` created server-side so signature verification matches
        "order_id": "<?php echo htmlspecialchars($razorpay_order_id); ?>",
        "prefill": {
            "name": "<?php echo htmlspecialchars($shipping_address['first_name'] . ' ' . $shipping_address['last_name']); ?>",
            "email": "<?php echo htmlspecialchars($shipping_address['email']); ?>",
            "contact": "<?php echo htmlspecialchars($shipping_address['phone']); ?>"
        },
        "theme": {
            "color": "#0d6efd"
        },
        "handler": function (response) {
            // Payment successful
            console.log('Payment successful:', response);

            // Send payment details to server for verification
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'verify-payment.php';

            var fields = {
                'razorpay_payment_id': response.razorpay_payment_id,
                'razorpay_order_id': response.razorpay_order_id,
                'razorpay_signature': response.razorpay_signature,
                'order_number': '<?php echo $order_number; ?>',
                'order_id': '<?php echo $order_id; ?>'
            };

            for (var key in fields) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
        },
        "modal": {
            "ondismiss": function() {
                // User closed the payment modal — stop automatic redirects and show controls
                showPaymentError('Payment cancelled by user.');
            }
        }
    };

    var rzp;
    try {
        rzp = new Razorpay(options);

        // Open Razorpay automatically after 1 second
        setTimeout(function() {
                        try {
                                console.log('Opening Razorpay checkout with key:', options.key, 'amount:', options.amount);
                                rzp.open();
                        } catch (err) {
                                console.error('Failed to open Razorpay checkout:', err);
                                // Show on-page error UI with retry option instead of redirecting
                                showPaymentError('Unable to open payment window. ' + (err && err.message ? err.message : 'Please try again.'));
                                // Send diagnostic info to server (best-effort, non-blocking)
                                try {
                                    navigator.sendBeacon('payment-diagnostic.php', JSON.stringify({
                                        order_number: '<?php echo addslashes($order_number); ?>',
                                        error: String(err),
                                        amount: '<?php echo $amount_in_paise; ?>'
                                    }));
                                } catch (be) { console.warn('sendBeacon failed', be); }
                        }
        }, 1000);

        rzp.on('payment.failed', function (response) {
            console.error('Payment failed:', response.error);
            // Show error UI with details and options
            var msg = 'Payment failed: ' + (response.error && response.error.description ? response.error.description : 'Unknown error');
            showPaymentError(msg);
        });
    } catch (err) {
        console.error('Razorpay initialization error:', err);
        // Show on-page error and stop further redirects
        showPaymentError('Payment initialization failed. ' + (err && err.message ? err.message : 'Please try again later.'));
    }
});
</script>
<?php endif; ?>

<script>
// Helper to display a persistent payment error panel with Retry / Back buttons
function showPaymentError(message) {
    // Remove any existing panel
    var existing = document.getElementById('payment-error-panel');
    if (existing) existing.remove();

    var panel = document.createElement('div');
    panel.id = 'payment-error-panel';
    panel.className = 'position-fixed top-0 start-50 translate-middle-x mt-5';
    panel.style.zIndex = 1060;
    panel.innerHTML = `
        <div class="card text-dark shadow" style="min-width:320px;">
            <div class="card-body">
                <h5 class="card-title">Payment Error</h5>
                <p class="card-text">${message}</p>
                <div class="d-flex justify-content-end">
                    <button id="payment-retry" class="btn btn-primary btn-sm me-2">Retry</button>
                    <a href="checkout.php" class="btn btn-secondary btn-sm">Back to Checkout</a>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(panel);

    document.getElementById('payment-retry').addEventListener('click', function() {
        // Remove panel and attempt to re-initiate the payment flow by reloading this page once
        panel.remove();
        // To avoid loops, add a flag to the URL so retry only happens manually
        var url = new URL(window.location.href);
        url.searchParams.set('retry', '1');
        window.location.href = url.toString();
    });
}
</script>

</body>
</html>
