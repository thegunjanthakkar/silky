<?php
// Start session for header functionality
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize database connection
if (!isset($conn)) {
    require_once 'db_config.php';
}

// Fetch general settings
$settings = [];
$settings_sql = "SELECT setting_key, setting_value FROM general_settings";
$settings_result = mysqli_query($conn, $settings_sql);
if ($settings_result) {
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Extract dynamic settings
$business_email = !empty($settings['business_email']) ? $settings['business_email'] : 'hello@silkysaree.in';
$business_phone = !empty($settings['business_phone']) ? $settings['business_phone'] : '079 2768 3326';
$business_address = !empty($settings['business_address']) ? $settings['business_address'] : 'Suryoday Society, Shivalay Apartments, 1, near Sardar Patel Colony, Naranpura, Ahmedabad, Gujarat 380013';
$website_tagline = !empty($settings['website_tagline']) ? $settings['website_tagline'] : 'A silky touch to beauty';
$privacy_content = trim($settings['privacy_policy'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Silky Saree</title>
    <meta name="description" content="Learn how Silky Saree collects, uses, and protects your personal information and privacy when you visit or shop on our store.">
    
    <!-- Favicons -->
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/main.css" rel="stylesheet">

    <style>
        :root {
            --brand-navy: #0e2187;
            --brand-navy-dark: #081459;
            --brand-accent: #97c51d;
            --brand-accent-hover: #82ab16;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --bg-page: #f8fafc;
            --card-border: rgba(14, 33, 135, 0.08);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            line-height: 1.7;
        }

        /* Top Navbar */
        .legal-navbar {
            background: #ffffff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            border-bottom: 1px solid #edf2f7;
            padding: 14px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .legal-navbar .navbar-brand img {
            max-height: 44px;
            width: auto;
        }
        .btn-nav-store {
            background: #f1f5f9;
            color: var(--brand-navy);
            border: 1px solid #e2e8f0;
            font-size: 0.88rem;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 50px;
            text-decoration: none;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-nav-store:hover {
            background: var(--brand-navy);
            color: #ffffff;
            border-color: var(--brand-navy);
            transform: translateY(-1px);
        }

        /* Hero Header */
        .legal-hero {
            background: linear-gradient(135deg, #0e2187 0%, #1833a8 60%, #97c51d 100%);
            color: #ffffff;
            padding: 65px 0 55px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .legal-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -20%;
            width: 140%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 60%);
            pointer-events: none;
        }
        .legal-hero .badge-policy {
            display: inline-block;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(8px);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }
        .legal-hero h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.6rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 12px;
        }
        .legal-hero p {
            font-size: 0.96rem;
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 0;
        }

        /* Card Container */
        .legal-card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid var(--card-border);
            box-shadow: 0 15px 35px rgba(14, 33, 135, 0.05);
            padding: 45px 50px;
            margin-top: -35px;
            margin-bottom: 60px;
            position: relative;
            z-index: 10;
        }

        /* Legal Content Styling */
        .legal-content h2, .legal-content h3, .legal-content h4 {
            font-family: 'Montserrat', sans-serif;
            color: var(--brand-navy);
            font-weight: 700;
            margin-top: 32px;
            margin-bottom: 14px;
        }
        .legal-content h2 {
            font-size: 1.45rem;
            border-left: 4px solid var(--brand-accent);
            padding-left: 12px;
        }
        .legal-content h3 {
            font-size: 1.22rem;
        }
        .legal-content p {
            color: #334155;
            font-size: 0.97rem;
            margin-bottom: 18px;
        }
        .legal-content ul, .legal-content ol {
            color: #334155;
            font-size: 0.97rem;
            padding-left: 24px;
            margin-bottom: 22px;
        }
        .legal-content li {
            margin-bottom: 8px;
        }
        .legal-content strong {
            color: #0f172a;
        }

        /* Policy Switch Tabs */
        .policy-nav-pills {
            display: flex;
            gap: 10px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 16px;
            margin-bottom: 30px;
        }
        .policy-nav-pills a {
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--text-muted);
            transition: all 0.2s ease;
        }
        .policy-nav-pills a.active {
            background: var(--brand-navy);
            color: #ffffff;
        }
        .policy-nav-pills a:hover:not(.active) {
            background: #f1f5f9;
            color: var(--brand-navy);
        }

        /* Footer */
        .legal-footer {
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            padding: 30px 0;
            text-align: center;
            font-size: 0.88rem;
            color: var(--text-muted);
        }
        .legal-footer a {
            color: var(--brand-navy);
            text-decoration: none;
            font-weight: 600;
        }
        .legal-footer a:hover {
            color: var(--brand-accent-hover);
        }

        @media (max-width: 768px) {
            .legal-card {
                padding: 30px 22px;
                border-radius: 16px;
                margin-top: -20px;
            }
            .legal-hero h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

    <!-- Header Navbar -->
    <header id="header" class="header sticky-top">
        <?php include_once("main-header.php"); ?>
    </header>

    <!-- Main Content -->
    <main class="container">
        <div class="legal-card">
            
            <!-- Policy Navigation Tabs -->
            <div class="policy-nav-pills">
                <a href="tos.php"><i class="bi bi-file-earmark-text me-1"></i> Terms & Conditions</a>
                <a href="privacy.php" class="active"><i class="bi bi-shield-lock me-1"></i> Privacy Policy</a>
            </div>

            <!-- LEGAL_CONTENT_START -->
            <div class="legal-content" id="legalContent">
                <h2>1. Commitment to Privacy</h2>
                    <p>At <strong>Silky Saree</strong>, accessible from <a href="index.php">https://silkysaree.in</a>, the privacy of our visitors and customers is one of our primary priorities. This Privacy Policy document outlines the types of personal information that is collected and recorded by Silky Saree and how we use and safeguard it.</p>

                    <h2>2. Information We Collect</h2>
                    <p>When you register, place an order, or interact with our website, we may collect the following personal information:</p>
                    <ul>
                        <li><strong>Contact &amp; Identification Data:</strong> Full name, email address, phone number, and postal addresses for delivery and invoicing.</li>
                        <li><strong>Account Credentials:</strong> Passwords (encrypted using secure hashing algorithms) and account activity records.</li>
                        <li><strong>Custom Fitting Information:</strong> Blouse stitching measurements, height, and custom tailoring requests provided by you for custom orders.</li>
                        <li><strong>Transactional Information:</strong> Order numbers, product selections, payment methods, and bank transaction reference numbers. <em>(Note: We do not store complete credit/debit card numbers or CVVs on our servers).</em></li>
                    </ul>

                    <h2>3. How We Use Your Information</h2>
                    <p>We use the information we collect in various ways, including to:</p>
                    <ul>
                        <li>Process, dispatch, and track your orders and custom measurements.</li>
                        <li>Send automated transactional notifications, such as order receipts, shipping tracking links, and OTP verification codes.</li>
                        <li>Communicate with you regarding customer support inquiries, returns, or back-in-stock alerts.</li>
                        <li>Prevent fraud and ensure a safe, secure checkout experience.</li>
                        <li>Improve and optimize our website catalog and shopping experience.</li>
                    </ul>

                    <h2>4. Cookies &amp; Tracking Technologies</h2>
                    <p>Silky Saree uses cookies and session identifiers to store information including visitors' preferences, items saved in the shopping cart, and login session status. These cookies are used to optimize users' browsing experience and ensure uninterrupted shopping.</p>

                    <h2>5. Third-Party Service Providers</h2>
                    <p>We partner with trustworthy third-party service providers solely to perform necessary services on our behalf:</p>
                    <ul>
                        <li><strong>Payment Gateways:</strong> Razorpay, Stripe, and authorized banks for PCI-DSS compliant payment processing.</li>
                        <li><strong>Logistics &amp; Courier Partners:</strong> Delivery carriers (such as BlueDart, Delhivery, DTDC) to deliver your parcels safely to your doorstep.</li>
                        <li><strong>Transactional Email Services:</strong> Encrypted SMTP servers to deliver order confirmations and verification codes.</li>
                    </ul>

                    <h2>6. Data Security &amp; Storage</h2>
                    <p>We employ strict administrative, technical, and physical security measures—including industry-standard 256-bit SSL encryption—to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p>

                    <h2>7. Your Data Rights</h2>
                    <p>You have the right to review, modify, or update your personal account information at any time by logging into <a href="account.php">My Account</a>. You may also request deletion of your account data by contacting our support team.</p>
            </div>
            <!-- LEGAL_CONTENT_END -->

        </div>
    </main>

    <!-- Footer -->
    <footer class="legal-footer">
        <div class="container">
            <p class="mb-1">&copy; <?php echo date('Y'); ?> Silky Saree. All rights reserved.</p>
            <p class="mb-0">
                <a href="index.php">Home</a> &bull; 
                <a href="tos.php">Terms of Service</a> &bull; 
                <a href="privacy.php">Privacy Policy</a> &bull; 
                <a href="contact.php">Contact Us</a>
            </p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
