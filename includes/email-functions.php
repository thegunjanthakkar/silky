<?php
// Email helper functions for Silky Saree

// Function to send order confirmation email
function sendOrderConfirmationEmail($conn, $order_id, $order_number, $customer_email) {
    // Get email settings
    $settings_sql = "SELECT * FROM email_settings WHERE id = 1 AND is_enabled = 1";
    $settings_result = mysqli_query($conn, $settings_sql);
    
    if (!$settings_result || mysqli_num_rows($settings_result) == 0) {
        error_log("Email service is disabled or not configured. Skipping order confirmation email.");
        return false;
    }
    
    $settings = mysqli_fetch_assoc($settings_result);
    
    // Validate required settings
    if (empty($settings['sender_email']) || empty($settings['sender_password']) || empty($settings['smtp_host'])) {
        error_log("Email settings incomplete. Skipping order confirmation email.");
        return false;
    }
    
    // Get order details
    $order_sql = "SELECT * FROM orders WHERE id = $order_id";
    $order_result = mysqli_query($conn, $order_sql);
    $order = mysqli_fetch_assoc($order_result);
    
    if (!$order) {
        error_log("Order not found. Cannot send confirmation email.");
        return false;
    }
    
    // Get order items
    $items_sql = "SELECT * FROM order_items WHERE order_id = $order_id";
    $items_result = mysqli_query($conn, $items_sql);
    $order_items = [];
    while ($item = mysqli_fetch_assoc($items_result)) {
        $order_items[] = $item;
    }
    
    // Parse shipping address
    $shipping_address = json_decode($order['shipping_address'], true);
    $customer_name = $shipping_address['first_name'] . ' ' . $shipping_address['last_name'];
    
    // Load PHPMailer
    require_once __DIR__ . '/../admin/PHPmailer/src/Exception.php';
    require_once __DIR__ . '/../admin/PHPmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../admin/PHPmailer/src/SMTP.php';
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->SMTPAuth = $settings['smtp_auth'];
        $mail->Username = $settings['sender_email'];
        $mail->Password = $settings['sender_password'];
        
        // Encryption
        if ($settings['smtp_encryption'] === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($settings['smtp_encryption'] === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $mail->Port = $settings['smtp_port'];
        $mail->Timeout = $settings['timeout'];
        $mail->CharSet = $settings['charset'];
        
        // Disable SSL verification for shared hosting
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom($settings['sender_email'], $settings['sender_name']);
        $mail->addAddress($customer_email, $customer_name);
        
        // Reply-To
        if (!empty($settings['reply_to_email'])) {
            $mail->addReplyTo($settings['reply_to_email'], $settings['reply_to_name'] ?: $settings['sender_name']);
        }
        
        // Build order items HTML
        $items_html = '';
        foreach ($order_items as $item) {
            $items_html .= '
                <tr>
                    <td style="padding: 15px; border-bottom: 1px solid #eee;">' . htmlspecialchars($item['product_name']) . '</td>
                    <td style="padding: 15px; border-bottom: 1px solid #eee; text-align: center;">' . $item['quantity'] . '</td>
                    <td style="padding: 15px; border-bottom: 1px solid #eee; text-align: right;">₹' . number_format($item['product_price'], 2) . '</td>
                    <td style="padding: 15px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;">₹' . number_format($item['subtotal'], 2) . '</td>
                </tr>';
        }
        
        // Payment method label
        $payment_labels = [
            'cod' => 'Cash on Delivery',
            'stripe' => 'Credit/Debit Card',
            'paypal' => 'PayPal',
            'razorpay' => 'Online Payment',
            'bank_transfer' => 'Bank Transfer'
        ];
        $payment_method_label = $payment_labels[$order['payment_method']] ?? ucfirst($order['payment_method']);
        
        // Content
        $mail->isHTML(true);
        
        // Logo URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $burl = defined('BASE_URL') ? BASE_URL : '/';
        $logo_src = $protocol . '://' . $host . $burl . 'assets/img/silky-png.png';
        
        $mail->Subject = 'Order Confirmation - Order #' . htmlspecialchars($order_number) . ' - Silky Saree';
        $mail->Body = '
            <html>
            <body style="font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; line-height: 1.6; color: #333333; margin: 0; padding: 0; background-color: #f4f7f6;">
                <div style="max-width: 650px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
                    <!-- Header -->
                    <div style="background: #ffffff; padding: 30px 20px; text-align: center; border-bottom: 1px solid #f0f0f0;">
                        ' . ($logo_src ? '<img src="' . $logo_src . '" alt="Silky Saree" style="max-width: 160px; height: auto;">' : '') . '
                    </div>
                    
                    <div style="text-align: center; margin-top: 30px;">
                        <h1 style="color: #0e2187; margin: 0; font-size: 26px; font-weight: 700;">Thank You for Your Order!</h1>
                        <p style="color: #666666; margin: 10px 0 0 0; font-size: 16px;">Your order has been received and is being processed</p>
                    </div>
                    
                    <!-- Order Number -->
                    <div style="background: #fafafa; padding: 20px; text-align: center; margin: 25px 30px; border-radius: 8px; border: 1px solid #eeeeee;">
                        <p style="margin: 0; color: #888888; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Order Number</p>
                        <h2 style="margin: 5px 0 0 0; color: #0e2187; font-size: 24px; font-weight: 700;">#' . htmlspecialchars($order_number) . '</h2>
                    </div>
                    
                    <!-- Content -->
                    <div style="padding: 30px;">
                        <p style="margin: 0 0 20px 0; font-size: 16px;">Dear <strong>' . htmlspecialchars($customer_name) . '</strong>,</p>
                        <p style="margin: 0 0 20px 0; color: #555555;">Thank you for shopping with Silky Saree! We have received your order and it is being processed. You will receive another email when your order ships.</p>
                                              <!-- Order Details -->
                        <div style="background: #ffffff; padding: 25px; border-radius: 12px; margin: 25px 0; border: 1px solid #eeeeee; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
                            <h3 style="margin: 0 0 15px 0; color: #333333; font-size: 18px; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px;">Order Details</h3>
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 10px 0; color: #666666;">Order Date:</td>
                                    <td style="padding: 10px 0; text-align: right; font-weight: 600; color: #333333;">' . date('d/m/Y h:i A', strtotime($order['created_at'])) . '</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #666666;">Payment Method:</td>
                                    <td style="padding: 8px 0; text-align: right; font-weight: bold;">' . $payment_method_label . '</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #666666;">Payment Status:</td>
                                    <td style="padding: 8px 0; text-align: right;">
                                        <span style="background: ' . ($order['payment_status'] == 'paid' ? '#d4edda' : '#fff3cd') . '; color: ' . ($order['payment_status'] == 'paid' ? '#155724' : '#856404') . '; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: bold;">' . ucfirst($order['payment_status']) . '</span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                                              <!-- Order Items -->
                        <h3 style="margin: 35px 0 15px 0; color: #333333; font-size: 18px;">Order Items</h3>
                        <div style="border: 1px solid #eeeeee; border-radius: 12px; overflow: hidden; margin-bottom: 25px;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #fafafa; border-bottom: 1px solid #eeeeee;">
                                        <th style="padding: 15px; text-align: left; font-weight: 600; color: #555555; font-size: 14px;">Product</th>
                                        <th style="padding: 15px; text-align: center; font-weight: 600; color: #555555; font-size: 14px;">Qty</th>
                                        <th style="padding: 15px; text-align: right; font-weight: 600; color: #555555; font-size: 14px;">Price</th>
                                        <th style="padding: 15px; text-align: right; font-weight: 600; color: #555555; font-size: 14px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ' . $items_html . '
                                    <tr>
                                        <td colspan="3" style="padding: 10px 15px; text-align: right; color: #555555;">Subtotal:</td>
                                        <td style="padding: 10px 15px; text-align: right; color: #333333;">₹' . number_format($order['subtotal'], 2) . '</td>
                                    </tr>
                                    ' . ($order['shipping_cost'] > 0 ? '
                                    <tr>
                                        <td colspan="3" style="padding: 10px 15px; text-align: right; color: #555555;">Shipping:</td>
                                        <td style="padding: 10px 15px; text-align: right; color: #333333;">₹' . number_format($order['shipping_cost'], 2) . '</td>
                                    </tr>' : '') . '
                                    ' . ((isset($order['discount_amount']) && $order['discount_amount'] > 0) ? '
                                    <tr>
                                        <td colspan="3" style="padding: 10px 15px; text-align: right; color: #dc3545;">Discount:</td>
                                        <td style="padding: 10px 15px; text-align: right; color: #dc3545;">-₹' . number_format($order['discount_amount'], 2) . '</td>
                                    </tr>' : '') . '
                                    <tr>
                                        <td colspan="3" style="padding: 10px 15px; text-align: right; color: #555555;">Tax:</td>
                                        <td style="padding: 10px 15px; text-align: right; color: #333333;">₹' . number_format($order['tax_amount'], 2) . '</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="padding: 15px; text-align: right; font-weight: 600; color: #555555;">Total:</td>
                                        <td style="padding: 15px; text-align: right; font-weight: 700; color: #333333; font-size: 18px;">₹' . number_format($order['total_amount'], 2) . '</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Shipping Address -->
                        <div style="background: #ffffff; padding: 25px; border-radius: 12px; margin: 25px 0; border: 1px solid #eeeeee; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
                            <h3 style="margin: 0 0 15px 0; color: #333333; font-size: 18px; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px;">Shipping Address</h3>
                            <p style="margin: 0 0 5px 0; font-weight: 600; color: #333333;">' . htmlspecialchars($customer_name) . '</p>
                            <p style="margin: 0 0 5px 0; color: #666666;">' . htmlspecialchars($shipping_address['street_address']) . '</p>
                            ' . (!empty($shipping_address['apartment']) ? '<p style="margin: 0 0 5px 0; color: #666666;">' . htmlspecialchars($shipping_address['apartment']) . '</p>' : '') . '
                            <p style="margin: 0 0 5px 0; color: #666666;">' . htmlspecialchars($shipping_address['city'] . ', ' . $shipping_address['state'] . ' ' . $shipping_address['zip_code']) . '</p>
                            <p style="margin: 0; color: #666666;">Phone: ' . htmlspecialchars($shipping_address['phone']) . '</p>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div style="background: #fafafa; padding: 30px; text-align: center; border-top: 1px solid #eeeeee;">
                        <p style="margin: 0 0 10px 0; color: #888888; font-size: 14px;">If you have any questions, please don\'t hesitate to contact us.</p>
                        <p style="margin: 0 0 15px 0; color: #0e2187; font-size: 14px; font-weight: 600;">Thank you for shopping with Silky Saree!</p>
                        <p style="margin: 0; color: #bbbbbb; font-size: 12px;">This is an automated email. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ';
        
        // Plain text alternative
        $mail->AltBody = "Order Confirmation - Order #$order_number\n\nDear $customer_name,\n\nThank you for your order! We have received your order and it is being processed.\n\nOrder Number: #$order_number\nOrder Date: " . date('d/m/Y h:i A', strtotime($order['created_at'])) . "\nPayment Method: $payment_method_label\nTotal Amount: ₹" . number_format($order['total_amount'], 2) . "\n\nYour order will be delivered within 5-7 business days.\n\nThank you for shopping with Silky Saree!";
        
        $mail->send();
        error_log("Order confirmation email sent successfully to: $customer_email");
        return true;
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("Failed to send order confirmation email. Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Function to send order status update email
function sendOrderStatusUpdateEmail($conn, $order_id) {
    // Get email settings
    $settings_sql = "SELECT * FROM email_settings WHERE id = 1 AND is_enabled = 1";
    $settings_result = mysqli_query($conn, $settings_sql);
    
    if (!$settings_result || mysqli_num_rows($settings_result) == 0) {
        error_log("Email service is disabled or not configured. Skipping order status update email.");
        return false;
    }
    
    $settings = mysqli_fetch_assoc($settings_result);
    
    // Validate required settings
    if (empty($settings['sender_email']) || empty($settings['sender_password']) || empty($settings['smtp_host'])) {
        error_log("Email settings incomplete. Skipping order status update email.");
        return false;
    }
    
    // Get order details
    $order_sql = "SELECT * FROM orders WHERE id = " . intval($order_id);
    $order_result = mysqli_query($conn, $order_sql);
    if (!$order_result || mysqli_num_rows($order_result) == 0) {
        error_log("Order not found. Cannot send status update email.");
        return false;
    }
    $order = mysqli_fetch_assoc($order_result);
    
    $order_number = $order['order_number'];
    
    // Parse shipping address to get email, name, and address details
    $shipping_address = [];
    if (!empty($order['shipping_address'])) {
        $dec = json_decode($order['shipping_address'], true);
        if (is_array($dec)) {
            $shipping_address = $dec;
        }
    }
    
    $customer_email = trim($shipping_address['email'] ?? '');
    $customer_name = trim(($shipping_address['first_name'] ?? '') . ' ' . ($shipping_address['last_name'] ?? ''));
    
    // Fallback to users table if email is missing from shipping address
    if (empty($customer_email) && !empty($order['user_id'])) {
        $u_stmt = mysqli_query($conn, "SELECT email, first_name, last_name FROM users WHERE id = " . intval($order['user_id']) . " LIMIT 1");
        if ($u_stmt && $u_row = mysqli_fetch_assoc($u_stmt)) {
            $customer_email = trim($u_row['email'] ?? '');
            if (empty($customer_name)) {
                $customer_name = trim(($u_row['first_name'] ?? '') . ' ' . ($u_row['last_name'] ?? ''));
            }
        }
    }
    
    if (empty($customer_name)) {
        $customer_name = 'Valued Customer';
    }
    
    if (empty($customer_email)) {
        error_log("No customer email found for order #{$order_number}. Cannot send status update email.");
        return false;
    }
    
    // Get order items
    $items_sql = "SELECT * FROM order_items WHERE order_id = " . intval($order_id);
    $items_result = mysqli_query($conn, $items_sql);
    $order_items = [];
    if ($items_result) {
        while ($item = mysqli_fetch_assoc($items_result)) {
            $order_items[] = $item;
        }
    }
    
    // Build order items HTML
    $items_html = '';
    foreach ($order_items as $item) {
        $variant_info = '';
        if (!empty($item['variant_info'])) {
            $dec_v = json_decode($item['variant_info'], true);
            if (is_array($dec_v)) {
                $v_parts = [];
                if (!empty($dec_v['color'])) $v_parts[] = 'Color: ' . htmlspecialchars($dec_v['color']);
                if (!empty($dec_v['size'])) $v_parts[] = 'Size: ' . htmlspecialchars($dec_v['size']);
                if (!empty($v_parts)) $variant_info = '<br><small style="color: #777;">' . implode(' | ', $v_parts) . '</small>';
            } else {
                $variant_info = '<br><small style="color: #777;">' . htmlspecialchars($item['variant_info']) . '</small>';
            }
        }
        
        $items_html .= '
            <tr>
                <td style="padding: 14px 15px; border-bottom: 1px solid #eee; text-align: left;">
                    <span style="font-weight: 600; color: #333;">' . htmlspecialchars($item['product_name']) . '</span>
                    ' . $variant_info . '
                </td>
                <td style="padding: 14px 15px; border-bottom: 1px solid #eee; text-align: center; color: #555;">' . intval($item['quantity']) . '</td>
                <td style="padding: 14px 15px; border-bottom: 1px solid #eee; text-align: right; color: #555;">₹' . number_format(floatval($item['product_price']), 2) . '</td>
                <td style="padding: 14px 15px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold; color: #333;">₹' . number_format(floatval($item['subtotal']), 2) . '</td>
            </tr>';
    }
    
    // Payment method label
    $payment_labels = [
        'cod'           => 'Cash on Delivery',
        'cash'          => 'Cash',
        'stripe'        => 'Credit/Debit Card',
        'paypal'        => 'PayPal',
        'razorpay'      => 'Online Payment',
        'online'        => 'Online Payment',
        'bank_transfer' => 'Bank Transfer'
    ];
    $pm_key = strtolower(trim($order['payment_method'] ?? ''));
    $payment_method_label = $payment_labels[$pm_key] ?? ucfirst($order['payment_method'] ?: 'Standard');
    
    $payment_status_clean = strtolower(trim($order['payment_status'] ?? 'pending'));
    $payment_status_label = ucfirst($payment_status_clean);
    $pay_badge_bg = $payment_status_clean === 'paid' ? '#d4edda' : ($payment_status_clean === 'failed' ? '#f8d7da' : '#fff3cd');
    $pay_badge_color = $payment_status_clean === 'paid' ? '#155724' : ($payment_status_clean === 'failed' ? '#721c24' : '#856404');
    
    // Status-specific definitions
    $order_status_clean = strtolower(trim($order['order_status'] ?? 'pending'));
    $order_status_label = ucfirst($order['order_status'] ?: 'Pending');
    
    $status_configs = [
        'confirmed' => [
            'subject'     => 'Order Confirmed - Order #' . $order_number . ' - Silky Saree',
            'title'       => 'Your Order is Confirmed!',
            'subtitle'    => 'We have confirmed your order and are preparing it for dispatch.',
            'message'     => 'Great news! Your order <strong>#' . htmlspecialchars($order_number) . '</strong> has been officially confirmed. Our team has verified your items and scheduled them for careful preparation and packaging.',
            'guidance'    => 'You will receive another update with courier tracking details as soon as your package is dispatched.',
            'badge_bg'    => '#d4edda',
            'badge_color' => '#155724'
        ],
        'processing' => [
            'subject'     => 'Order Processing - Order #' . $order_number . ' - Silky Saree',
            'title'       => 'Your Order is Being Processed',
            'subtitle'    => 'Our artisans and packaging team are working on your items.',
            'message'     => 'We are pleased to inform you that your order <strong>#' . htmlspecialchars($order_number) . '</strong> is currently in progress. We ensure every fold and weave meets our highest standards of elegance and quality.',
            'guidance'    => 'Your package will soon be securely boxed and handed over to our delivery partner.',
            'badge_bg'    => '#fff3cd',
            'badge_color' => '#856404'
        ],
        'placed' => [
            'subject'     => 'Order Placed Successfully - Order #' . $order_number . ' - Silky Saree',
            'title'       => 'Order Placed Successfully',
            'subtitle'    => 'Thank you for choosing Silky Saree.',
            'message'     => 'We have successfully received your order <strong>#' . htmlspecialchars($order_number) . '</strong>. Our team is reviewing the details and getting it ready for confirmation.',
            'guidance'    => 'We will notify you immediately once your order status moves to confirmed.',
            'badge_bg'    => '#cce5ff',
            'badge_color' => '#004085'
        ],
        'out for delivery' => [
            'subject'     => 'Order Out for Delivery - Order #' . $order_number . ' - Silky Saree',
            'title'       => 'Your Order is Out for Delivery!',
            'subtitle'    => 'Your package is on its way to your doorstep.',
            'message'     => 'Exciting news! Your order <strong>#' . htmlspecialchars($order_number) . '</strong> is out for delivery today. Our delivery executive is en route to your shipping address.',
            'guidance'    => 'Please ensure someone is available at the delivery location to receive the package and complete any applicable payment.',
            'badge_bg'    => '#d1ecf1',
            'badge_color' => '#0c5460'
        ],
        'delivered' => [
            'subject'     => 'Order Delivered - Order #' . $order_number . ' - Silky Saree',
            'title'       => 'Your Order Has Been Delivered!',
            'subtitle'    => 'We hope you love your new ethnic wear.',
            'message'     => 'Your order <strong>#' . htmlspecialchars($order_number) . '</strong> has been delivered successfully. We hope your new attire brings grace and elegance to your special moments!',
            'guidance'    => 'We would love to know how you liked our collection. Feel free to leave a product review or tag us on social media.',
            'badge_bg'    => '#d4edda',
            'badge_color' => '#155724'
        ],
        'cancelled' => [
            'subject'     => 'Order Cancelled - Order #' . $order_number . ' - Silky Saree',
            'title'       => 'Order Cancelled',
            'subtitle'    => 'Your order has been cancelled.',
            'message'     => 'We are writing to notify you that order <strong>#' . htmlspecialchars($order_number) . '</strong> has been cancelled.',
            'guidance'    => 'If a payment was already made for this order, your refund process has been initiated according to our refund policy. If this cancellation was unintentional, please contact our customer support team right away.',
            'badge_bg'    => '#f8d7da',
            'badge_color' => '#721c24'
        ],
        'returned' => [
            'subject'     => 'Order Return Processed - Order #' . $order_number . ' - Silky Saree',
            'title'       => 'Return Request Processed',
            'subtitle'    => 'Your order return has been processed.',
            'message'     => 'We have received and processed the return for your order <strong>#' . htmlspecialchars($order_number) . '</strong>.',
            'guidance'    => 'If applicable, your refund or store credit is being processed in accordance with our return policy. Thank you for your patience.',
            'badge_bg'    => '#e2e3e5',
            'badge_color' => '#383d41'
        ],
        'pending' => [
            'subject'     => 'Order Status: Pending - Order #' . $order_number . ' - Silky Saree',
            'title'       => 'Your Order is Pending',
            'subtitle'    => 'Your order is currently awaiting confirmation.',
            'message'     => 'Your order <strong>#' . htmlspecialchars($order_number) . '</strong> is currently pending confirmation or payment verification.',
            'guidance'    => 'We will review your order and send you a follow-up notification as soon as it progresses.',
            'badge_bg'    => '#fff3cd',
            'badge_color' => '#856404'
        ]
    ];
    
    $current_config = $status_configs[$order_status_clean] ?? [
        'subject'     => 'Order Status Update: ' . $order_status_label . ' - Order #' . $order_number . ' - Silky Saree',
        'title'       => 'Order Status Update',
        'subtitle'    => 'There is an update on your order status.',
        'message'     => 'We wanted to let you know that the status of your order <strong>#' . htmlspecialchars($order_number) . '</strong> has been updated to <strong>' . htmlspecialchars($order_status_label) . '</strong>.',
        'guidance'    => 'If you have any questions about your order, please do not hesitate to reach out to our team.',
        'badge_bg'    => '#e8ebf8',
        'badge_color' => '#0e2187'
    ];
    
    // Load PHPMailer
    require_once __DIR__ . '/../admin/PHPmailer/src/Exception.php';
    require_once __DIR__ . '/../admin/PHPmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../admin/PHPmailer/src/SMTP.php';
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->SMTPAuth = $settings['smtp_auth'];
        $mail->Username = $settings['sender_email'];
        $mail->Password = $settings['sender_password'];
        
        // Encryption
        if ($settings['smtp_encryption'] === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($settings['smtp_encryption'] === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $mail->Port = $settings['smtp_port'];
        $mail->Timeout = $settings['timeout'];
        $mail->CharSet = $settings['charset'];
        
        // Disable SSL verification for shared hosting
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom($settings['sender_email'], $settings['sender_name']);
        $mail->addAddress($customer_email, $customer_name);
        
        // Reply-To
        if (!empty($settings['reply_to_email'])) {
            $mail->addReplyTo($settings['reply_to_email'], $settings['reply_to_name'] ?: $settings['sender_name']);
        }
        
        // Content
        $mail->isHTML(true);
        
        // Logo URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $burl = defined('BASE_URL') ? BASE_URL : '/';
        $logo_src = $protocol . '://' . $host . $burl . 'assets/img/silky-png.png';
        
        // Financial calculations
        $subtotal = floatval($order['subtotal'] ?? 0);
        $shipping_cost = floatval($order['shipping_cost'] ?? 0);
        $tax_amount = floatval($order['tax_amount'] ?? 0);
        $discount_amount = floatval($order['discount_amount'] ?? 0);
        $total_amount = floatval($order['total_amount'] ?? 0);
        $created_date = !empty($order['created_at']) ? date('d/m/Y h:i A', strtotime($order['created_at'])) : date('d/m/Y h:i A');
        
        $mail->Subject = $current_config['subject'];
        $mail->Body = '
            <html>
            <body style="font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; line-height: 1.6; color: #333333; margin: 0; padding: 0; background-color: #f4f7f6;">
                <div style="max-width: 650px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
                    <!-- Header -->
                    <div style="background: #ffffff; padding: 30px 20px; text-align: center; border-bottom: 1px solid #f0f0f0;">
                        ' . ($logo_src ? '<img src="' . $logo_src . '" alt="Silky Saree" style="max-width: 160px; height: auto;">' : '') . '
                    </div>
                    
                    <div style="text-align: center; margin-top: 30px; padding: 0 20px;">
                        <h1 style="color: #0e2187; margin: 0; font-size: 26px; font-weight: 700;">' . $current_config['title'] . '</h1>
                        <p style="color: #666666; margin: 10px 0 0 0; font-size: 16px;">' . $current_config['subtitle'] . '</p>
                    </div>
                    
                    <!-- Order Number Banner & Status Badge -->
                    <div style="background: #fafafa; padding: 20px; text-align: center; margin: 25px 30px; border-radius: 8px; border: 1px solid #eeeeee;">
                        <p style="margin: 0; color: #888888; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">Order Number</p>
                        <h2 style="margin: 5px 0 12px 0; color: #0e2187; font-size: 24px; font-weight: 700;">#' . htmlspecialchars($order_number) . '</h2>
                        <span style="background: ' . $current_config['badge_bg'] . '; color: ' . $current_config['badge_color'] . '; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block;">
                            Status: ' . htmlspecialchars($order_status_label) . '
                        </span>
                    </div>
                    
                    <!-- Content -->
                    <div style="padding: 10px 30px 30px;">
                        <p style="margin: 0 0 15px 0; font-size: 16px;">Dear <strong>' . htmlspecialchars($customer_name) . '</strong>,</p>
                        <p style="margin: 0 0 15px 0; color: #555555; font-size: 15px; line-height: 1.6;">' . $current_config['message'] . '</p>
                        
                        <div style="background: #fdfbf7; border-left: 4px solid #0e2187; padding: 12px 16px; margin: 20px 0; border-radius: 4px;">
                            <p style="margin: 0; color: #555555; font-size: 14px; line-height: 1.5;">' . $current_config['guidance'] . '</p>
                        </div>
                        
                        <!-- Order Details Card -->
                        <div style="background: #ffffff; padding: 20px 25px; border-radius: 12px; margin: 25px 0; border: 1px solid #eeeeee; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
                            <h3 style="margin: 0 0 12px 0; color: #333333; font-size: 16px; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px;">Order Summary</h3>
                            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                                <tr>
                                    <td style="padding: 8px 0; color: #666666;">Order Date:</td>
                                    <td style="padding: 8px 0; text-align: right; font-weight: 600; color: #333333;">' . $created_date . '</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #666666;">Payment Method:</td>
                                    <td style="padding: 8px 0; text-align: right; font-weight: 600; color: #333333;">' . htmlspecialchars($payment_method_label) . '</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #666666;">Payment Status:</td>
                                    <td style="padding: 8px 0; text-align: right;">
                                        <span style="background: ' . $pay_badge_bg . '; color: ' . $pay_badge_color . '; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold;">' . htmlspecialchars($payment_status_label) . '</span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        ' . (!empty($items_html) ? '
                        <!-- Order Items Card -->
                        <h3 style="margin: 30px 0 12px 0; color: #333333; font-size: 16px;">Items in this Order</h3>
                        <div style="border: 1px solid #eeeeee; border-radius: 12px; overflow: hidden; margin-bottom: 25px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                                <thead>
                                    <tr style="background: #fafafa; border-bottom: 1px solid #eeeeee;">
                                        <th style="padding: 12px 15px; text-align: left; font-weight: 600; color: #555555; font-size: 13px;">Product</th>
                                        <th style="padding: 12px 15px; text-align: center; font-weight: 600; color: #555555; font-size: 13px;">Qty</th>
                                        <th style="padding: 12px 15px; text-align: right; font-weight: 600; color: #555555; font-size: 13px;">Price</th>
                                        <th style="padding: 12px 15px; text-align: right; font-weight: 600; color: #555555; font-size: 13px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ' . $items_html . '
                                    <tr>
                                        <td colspan="3" style="padding: 8px 15px; text-align: right; color: #555555;">Subtotal:</td>
                                        <td style="padding: 8px 15px; text-align: right; color: #333333;">₹' . number_format($subtotal, 2) . '</td>
                                    </tr>
                                    ' . ($shipping_cost > 0 ? '
                                    <tr>
                                        <td colspan="3" style="padding: 8px 15px; text-align: right; color: #555555;">Shipping:</td>
                                        <td style="padding: 8px 15px; text-align: right; color: #333333;">₹' . number_format($shipping_cost, 2) . '</td>
                                    </tr>' : '') . '
                                    ' . ($discount_amount > 0 ? '
                                    <tr>
                                        <td colspan="3" style="padding: 8px 15px; text-align: right; color: #dc3545;">Discount:</td>
                                        <td style="padding: 8px 15px; text-align: right; color: #dc3545;">-₹' . number_format($discount_amount, 2) . '</td>
                                    </tr>' : '') . '
                                    ' . ($tax_amount > 0 ? '
                                    <tr>
                                        <td colspan="3" style="padding: 8px 15px; text-align: right; color: #555555;">Tax:</td>
                                        <td style="padding: 8px 15px; text-align: right; color: #333333;">₹' . number_format($tax_amount, 2) . '</td>
                                    </tr>' : '') . '
                                    <tr style="border-top: 1px solid #eeeeee;">
                                        <td colspan="3" style="padding: 12px 15px; text-align: right; font-weight: 600; color: #333333; font-size: 15px;">Total:</td>
                                        <td style="padding: 12px 15px; text-align: right; font-weight: 700; color: #0e2187; font-size: 16px;">₹' . number_format($total_amount, 2) . '</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>' : '') . '
                        
                        <!-- Delivery Address Card -->
                        ' . (!empty($shipping_address['street_address']) ? '
                        <div style="background: #ffffff; padding: 20px 25px; border-radius: 12px; margin: 25px 0; border: 1px solid #eeeeee; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
                            <h3 style="margin: 0 0 12px 0; color: #333333; font-size: 16px; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px;">Delivery Address</h3>
                            <p style="margin: 0 0 4px 0; font-weight: 600; color: #333333;">' . htmlspecialchars($customer_name) . '</p>
                            <p style="margin: 0 0 4px 0; color: #666666; font-size: 14px;">' . htmlspecialchars($shipping_address['street_address']) . '</p>
                            ' . (!empty($shipping_address['apartment']) ? '<p style="margin: 0 0 4px 0; color: #666666; font-size: 14px;">' . htmlspecialchars($shipping_address['apartment']) . '</p>' : '') . '
                            <p style="margin: 0 0 4px 0; color: #666666; font-size: 14px;">' . htmlspecialchars(($shipping_address['city'] ?? '') . ', ' . ($shipping_address['state'] ?? '') . ' ' . ($shipping_address['zip_code'] ?? '')) . '</p>
                            ' . (!empty($shipping_address['phone']) ? '<p style="margin: 0; color: #666666; font-size: 14px;">Phone: ' . htmlspecialchars($shipping_address['phone']) . '</p>' : '') . '
                        </div>' : '') . '
                        
                        <p style="margin: 25px 0 0 0; color: #666666; font-size: 14px;">If you have any questions or need to make changes to your order, please do not hesitate to contact our customer support team.</p>
                    </div>
                    
                    <!-- Footer -->
                    <div style="background: #fafafa; padding: 30px; text-align: center; border-top: 1px solid #eeeeee;">
                        <p style="margin: 0 0 10px 0; color: #888888; font-size: 14px;">Thank you for choosing Silky Saree!</p>
                        <p style="margin: 0 0 12px 0; color: #0e2187; font-size: 14px; font-weight: 600;">Timeless elegance, crafted for you.</p>
                        <p style="margin: 0; color: #bbbbbb; font-size: 12px;">This is an automated notification regarding order #' . htmlspecialchars($order_number) . '. Please do not reply directly to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ';
        
        // Plain text alternative
        $mail->AltBody = "{$current_config['title']} - Order #$order_number\n\n"
                       . "Dear $customer_name,\n\n"
                       . strip_tags($current_config['message']) . "\n\n"
                       . "Order Status: $order_status_label\n"
                       . "Payment Status: $payment_status_label\n"
                       . "Order Date: $created_date\n"
                       . "Total Amount: ₹" . number_format($total_amount, 2) . "\n\n"
                       . "{$current_config['guidance']}\n\n"
                       . "Thank you for shopping with Silky Saree!";
        
        $mail->send();
        error_log("Order status update email ($order_status_clean) sent successfully to: $customer_email for order #$order_number");
        return true;
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("Failed to send order status update email for order #$order_number. Error: {$mail->ErrorInfo}");
        return false;
    }
}


// Function to send stock notification email
function sendStockNotificationEmail($conn, $notification_id) {
    // Get email settings
    $settings_sql = "SELECT * FROM email_settings WHERE id = 1 AND is_enabled = 1";
    $settings_result = mysqli_query($conn, $settings_sql);
    
    if (!$settings_result || mysqli_num_rows($settings_result) == 0) {
        error_log("Email service is disabled or not configured. Skipping stock notification email.");
        return false;
    }
    
    $settings = mysqli_fetch_assoc($settings_result);
    
    // Validate required settings
    if (empty($settings['sender_email']) || empty($settings['sender_password']) || empty($settings['smtp_host'])) {
        error_log("Email settings incomplete. Skipping stock notification email.");
        return false;
    }
    
    // Get notification details
    $notif_sql = "SELECT * FROM stock_notifications WHERE id = $notification_id AND status = 'pending'";
    $notif_result = mysqli_query($conn, $notif_sql);
    $notification = mysqli_fetch_assoc($notif_result);
    
    if (!$notification) {
        error_log("Pending notification not found. Cannot send stock notification email.");
        return false;
    }
    
    $customer_email = $notification['email'];
    $product_id = $notification['product_id'];
    $variant_id = $notification['variant_id']; // This could be NULL
    
    // Get product details
    $product_sql = "SELECT * FROM products WHERE id = $product_id";
    $product_result = mysqli_query($conn, $product_sql);
    $product = mysqli_fetch_assoc($product_result);
    
    if (!$product) {
        error_log("Product not found. Cannot send stock notification email.");
        return false;
    }
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $burl = defined('BASE_URL') ? BASE_URL : '/';
    $product_name = $product['name'];
    $product_slug = $product['slug'];
    $product_url = $protocol . "://" . $host . $burl . "product-details/" . $product_slug;
    
    $product_image_path = '';
    // Main product image
    if (!empty($product['image'])) {
        $product_image_path = __DIR__ . '/../assets/img/products/' . $product['image'];
    }
    
    $variant_text = "";
    if ($variant_id) {
        // Get variant details
        $variant_sql = "SELECT color, size FROM product_variants WHERE id = $variant_id";
        $variant_result = mysqli_query($conn, $variant_sql);
        $variant = mysqli_fetch_assoc($variant_result);
        
        if ($variant) {
            $variant_text = "(Variant: ";
            if (!empty($variant['color'])) {
                $variant_text .= $variant['color'];
            }
            if (!empty($variant['color']) && !empty($variant['size'])) {
                $variant_text .= " / ";
            }
            if (!empty($variant['size'])) {
                $variant_text .= $variant['size'];
            }
            $variant_text .= ")";
        }
    }
    
    // Load PHPMailer
    require_once __DIR__ . '/../admin/PHPmailer/src/Exception.php';
    require_once __DIR__ . '/../admin/PHPmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../admin/PHPmailer/src/SMTP.php';
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->SMTPAuth = $settings['smtp_auth'];
        $mail->Username = $settings['sender_email'];
        $mail->Password = $settings['sender_password'];
        
        // Encryption
        if ($settings['smtp_encryption'] === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($settings['smtp_encryption'] === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $mail->Port = $settings['smtp_port'];
        $mail->Timeout = $settings['timeout'];
        $mail->CharSet = $settings['charset'];
        
        // Disable SSL verification for shared hosting
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom($settings['sender_email'], $settings['sender_name']);
        $mail->addAddress($customer_email);
        
        // Reply-To
        if (!empty($settings['reply_to_email'])) {
            $mail->addReplyTo($settings['reply_to_email'], $settings['reply_to_name'] ?: $settings['sender_name']);
        }
        
        // Content
        $mail->isHTML(true);
        
        // Logo URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $burl = defined('BASE_URL') ? BASE_URL : '/';
        $logo_src = $protocol . '://' . $host . $burl . 'assets/img/silky-png.png';
        $image_url = "";
        
        if (!empty($product_image_path) && file_exists($product_image_path)) {
            $mail->AddEmbeddedImage($product_image_path, 'product_img', basename($product_image_path));
            $image_url = 'cid:product_img';
        } else {
            $image_url = $protocol . '://' . $host . $burl . 'assets/img/products/placeholder.jpg';
        }
        
        $mail->Subject = 'Back in Stock: ' . htmlspecialchars($product_name) . ' - Silky Saree';
        $mail->Body = '
            <html>
            <body style="font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; line-height: 1.6; color: #333333; margin: 0; padding: 0; background-color: #f4f7f6;">
                <div style="max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
                    <!-- Header -->
                    <div style="background: #ffffff; padding: 30px 20px; text-align: center; border-bottom: 1px solid #f0f0f0;">
                        ' . ($logo_src ? '<img src="' . $logo_src . '" alt="Silky Saree" style="max-width: 160px; height: auto;">' : '') . '
                    </div>
                    
                    <!-- Content -->
                    <div style="padding: 40px 30px;">
                        <div style="text-align: center; margin-bottom: 30px;">
                            <h1 style="color: #0e2187; margin: 0; font-size: 26px; font-weight: 700;">Back in Stock!</h1>
                            <p style="color: #666666; margin: 10px 0 0 0; font-size: 16px;">The item you requested is now available.</p>
                        </div>
                        
                        <p style="margin: 0 0 20px 0; font-size: 16px; color: #444;">Hello,</p>
                        <p style="margin: 0 0 25px 0; font-size: 16px; color: #444;">Great news! The product you were waiting for is finally back in stock. Grab it before it sells out again!</p>
                        
                        <!-- Product Details -->
                        <div style="background: #ffffff; border: 1px solid #eeeeee; padding: 20px; border-radius: 12px; margin: 30px 0; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 120px; padding: 0; vertical-align: top;">
                                        <img src="' . htmlspecialchars($image_url) . '" alt="' . htmlspecialchars($product_name) . '" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 1px solid #f0f0f0;">
                                    </td>
                                    <td style="padding: 0 0 0 20px; vertical-align: middle;">
                                        <h3 style="margin: 0 0 8px 0; color: #333333; font-size: 18px;">' . htmlspecialchars($product_name) . '</h3>
                                        ' . ($variant_text ? '<p style="margin: 0; color: #777777; font-size: 14px;">' . htmlspecialchars($variant_text) . '</p>' : '') . '
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div style="text-align: center; margin: 40px 0 20px;">
                            <a href="' . htmlspecialchars($product_url) . '" style="background: linear-gradient(135deg, #0e2187, #97c51d); color: #ffffff; padding: 15px 35px; text-decoration: none; border-radius: 50px; font-weight: 600; font-size: 16px; display: inline-block; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 15px rgba(151,197,29,0.35);">Shop Now</a>
                        </div>
                        
                        <p style="margin: 20px 0 0 0; color: #888888; font-size: 14px;">If you have any questions, please don\'t hesitate to contact us.</p>
                    </div>
                    
                    <!-- Footer -->
                    <div style="background: #fafafa; padding: 30px; text-align: center; border-top: 1px solid #eeeeee;">
                        <p style="margin: 0 0 10px 0; color: #888888; font-size: 14px;">Thank you for shopping with Silky Saree!</p>
                        <p style="margin: 0; color: #bbbbbb; font-size: 12px;">This is an automated email. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ';
        
        // Plain text alternative
        $mail->AltBody = "Back in Stock: $product_name\n\nGreat news! The product you were waiting for is finally back in stock. Grab it before it sells out again!\n\nShop Now: $product_url\n\nThank you for shopping with Silky Saree!";
        
        $mail->send();
        
        // Update notification status
        $update_sql = "UPDATE stock_notifications SET status = 'sent', sent_at = NOW() WHERE id = $notification_id";
        mysqli_query($conn, $update_sql);
        
        error_log("Stock notification email sent successfully to: $customer_email");
        return true;
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("Failed to send stock notification email. Error: {$mail->ErrorInfo}");
        return false;
    }
}
// Function to send OTP verification email
function sendVerificationEmail($conn, $customer_email, $first_name, $otp_code) {
    // Get email settings
    $settings_sql = "SELECT * FROM email_settings WHERE id = 1 AND is_enabled = 1";
    $settings_result = mysqli_query($conn, $settings_sql);
    
    if (!$settings_result || mysqli_num_rows($settings_result) == 0) {
        error_log("Email service is disabled or not configured. Skipping verification email.");
        return false;
    }
    
    $settings = mysqli_fetch_assoc($settings_result);
    
    // Validate required settings
    if (empty($settings['sender_email']) || empty($settings['sender_password']) || empty($settings['smtp_host'])) {
        error_log("Email settings incomplete. Skipping verification email.");
        return false;
    }
    
    // Load PHPMailer
    require_once __DIR__ . '/../admin/PHPmailer/src/Exception.php';
    require_once __DIR__ . '/../admin/PHPmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../admin/PHPmailer/src/SMTP.php';
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->SMTPAuth = $settings['smtp_auth'];
        $mail->Username = $settings['sender_email'];
        $mail->Password = $settings['sender_password'];
        
        // Encryption
        if ($settings['smtp_encryption'] === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($settings['smtp_encryption'] === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $mail->Port = $settings['smtp_port'];
        $mail->Timeout = $settings['timeout'];
        $mail->CharSet = $settings['charset'];
        
        // Recipients
        $mail->setFrom($settings['sender_email'], $settings['sender_name']);
        $mail->addAddress($customer_email, $first_name);
        if (!empty($settings['reply_to_email'])) {
            $mail->addReplyTo($settings['reply_to_email'], $settings['reply_to_name']);
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "Your Verification Code";
        
        // Logo URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $burl = defined('BASE_URL') ? BASE_URL : '/';
        $logo_src = $protocol . '://' . $host . $burl . 'assets/img/silky-png.png';

        $message_html = '
        <html>
        <body style="font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; line-height: 1.6; color: #333333; margin: 0; padding: 0; background-color: #f4f7f6;">
            <div style="max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
                <!-- Header -->
                <div style="background: #ffffff; padding: 30px 20px; text-align: center; border-bottom: 1px solid #f0f0f0;">
                    ' . ($logo_src ? '<img src="' . $logo_src . '" alt="Silky Saree" style="max-width: 160px; height: auto;">' : '<h1 style="color: #4CAF50; margin: 0;">Silky Saree</h1>') . '
                </div>
                <!-- Body -->
                <div style="padding: 40px 30px; text-align: center;">
                    <h2 style="color: #2c3e50; margin-top: 0; font-size: 24px;">Verify Your Email</h2>
                    <p style="font-size: 16px; color: #555;">Hello ' . htmlspecialchars($first_name) . ',</p>
                    <p style="font-size: 16px; color: #555; margin-bottom: 30px;">Thank you for registering. Please use the following 6-digit verification code to complete your signup process:</p>
                    
                    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px dashed #ced4da; margin-bottom: 30px;">
                        <span style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #4CAF50;">
                            ' . htmlspecialchars($otp_code) . '
                        </span>
                    </div>
                    
                    <p style="font-size: 14px; color: #777;">This code will expire in <strong>15 minutes</strong>.</p>
                    <p style="font-size: 14px; color: #777; margin-bottom: 0;">If you did not create an account, no further action is required.</p>
                </div>
                <!-- Footer -->
                <div style="background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #eeeeee;">
                    <p style="font-size: 12px; color: #999999; margin: 0;">This is an automated message from Silky Saree. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>';
        
        $message_plain = "Hello {$first_name},\n\nYour verification code is: {$otp_code}\n\nIt expires in 15 minutes.";
        
        $mail->Body = $message_html;
        $mail->AltBody = $message_plain;
        
        $mail->send();
        error_log("Verification email sent successfully to: $customer_email");
        return true;
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("Failed to send verification email. Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
