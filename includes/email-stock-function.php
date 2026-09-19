<?php
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

    $product_id = $notification['product_id'];
    $customer_email = $notification['email'];
    $variant_key = $notification['variant_key'];
    
    // Get product details
    $product_sql = "SELECT * FROM products WHERE id = $product_id";
    $product_result = mysqli_query($conn, $product_sql);
    $product = mysqli_fetch_assoc($product_result);

    if (!$product) {
        error_log("Product not found. Cannot send stock notification email.");
        return false;
    }

    $product_name = $product['name'];
    $product_slug = $product['slug'];
    $product_image = 'assets/img/products/placeholder.jpg';
    $images = json_decode($product['image'], true);
    if (!empty($images) && isset($images[0])) {
        $product_image = $images[0];
    }
    // ensure absolute url for image and link
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    $script_dir = str_replace('/admin', '', $script_dir);
    $base_url .= $script_dir;

    if (!filter_var($product_image, FILTER_VALIDATE_URL)) {
        $image_url = $base_url . '/admin/' . $product_image;
    } else {
        $image_url = $product_image;
    }
    
    $product_url = $base_url . '/product-details.php?slug=' . $product_slug;
    
    $variant_text = "";
    if ($variant_key) {
        $variant_text = " (Variant: " . str_replace('_', ' / ', $variant_key) . ")";
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
        $mail->Subject = 'Back in Stock: ' . htmlspecialchars($product_name) . ' - Silky Saree';
        
        // Logo URL (hosted to avoid Gmail attachment chip)
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $burl = defined('BASE_URL') ? BASE_URL : '/';
        if (empty($host) || in_array($host, ['localhost', '127.0.0.1', '::1']) || strpos($host, 'localhost:') === 0) {
            $logo_src = 'https://silkysaree.in/assets/img/silky.png';
        } else {
            $logo_src = $protocol . '://' . $host . $burl . 'assets/img/silky.png';
        }

        $mail->Body = '
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f8f9fa;">
                <div style="max-width: 650px; margin: 30px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    <!-- Brand Header -->
                    <div style="background: #ffffff; padding: 25px 20px; text-align: center; border-bottom: 1px solid #f0f0f0;">
                        <img src="' . $logo_src . '" alt="Silky Saree" style="max-width: 160px; height: auto; display: inline-block; border: 0;">
                    </div>
                    <!-- Header -->
                    <div style="background: linear-gradient(135deg, #0e2187 0%, #97c51d 100%); padding: 30px; text-align: center;">
                        <h1 style="color: white; margin: 0; font-size: 28px; font-weight: bold;">Back in Stock!</h1>
                        <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 16px;">The item you requested is now available</p>
                    </div>
                    
                    <!-- Content -->
                    <div style="padding: 30px;">
                        <p style="margin: 0 0 20px 0; font-size: 16px;">Hello,</p>
                        <p style="margin: 0 0 20px 0; color: #555;">Great news! The product you were waiting for is finally back in stock. Grab it before it sells out again!</p>
                        
                        <!-- Product Details -->
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0; display: flex; align-items: center; gap: 20px;">
                            <img src="' . htmlspecialchars($image_url) . '" alt="' . htmlspecialchars($product_name) . '" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px;">
                            <div>
                                <h3 style="margin: 0 0 5px 0; color: #0e2187; font-size: 18px;">' . htmlspecialchars($product_name) . '</h3>
                                ' . ($variant_text ? '<p style="margin: 0; color: #666; font-size: 14px;">' . htmlspecialchars($variant_text) . '</p>' : '') . '
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="' . htmlspecialchars($product_url) . '" style="background: linear-gradient(135deg, #97c51d, #7aa817); color: white; padding: 14px 30px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 4px 15px rgba(151,197,29,0.35);">Shop Now</a>
                        </div>
                        
                        <p style="margin: 20px 0 0 0; color: #555;">If you have any questions, please don\'t hesitate to contact us.</p>
                    </div>
                    
                    <!-- Footer -->
                    <div style="background: #f8f9fa; padding: 25px; text-align: center; border-top: 1px solid #ddd;">
                        <p style="margin: 0 0 10px 0; color: #666; font-size: 14px;">Thank you for shopping with Silky Saree!</p>
                        <p style="margin: 0; color: #999; font-size: 12px;">This is an automated email. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ';
        
        // Plain text alternative
        $mail->AltBody = "Back in Stock: $product_name\n\nHello,\n\nGreat news! The product you were waiting for is finally back in stock:\n\n$product_name$variant_text\n\nShop Now: $product_url\n\nThank you for shopping with Silky Saree!";
        
        $mail->send();
        
        // Update notification status
        $update_sql = "UPDATE stock_notifications SET status = 'notified' WHERE id = $notification_id";
        mysqli_query($conn, $update_sql);
        
        error_log("Stock notification email sent successfully to: $customer_email");
        return true;
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("Failed to send stock notification email. Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
