<?php
session_start();
require_once 'db_config.php';

$response = array(
    'success' => false,
    'message' => '',
    'errors' => array()
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirmPassword']) ? $_POST['confirmPassword'] : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $date_of_birth = isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : '';
    $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
    
    // Validate input
    if (empty($first_name)) {
        $response['errors'][] = 'First name is required';
    }
    
    if (empty($last_name)) {
        $response['errors'][] = 'Last name is required';
    }
    
    if (empty($email)) {
        $response['errors'][] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['errors'][] = 'Invalid email format';
    }
    
    if (empty($password)) {
        $response['errors'][] = 'Password is required';
    } else {
        if (strlen($password) < 8) {
            $response['errors'][] = 'Password must be at least 8 characters long';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $response['errors'][] = 'Password must contain at least one uppercase letter (A-Z)';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $response['errors'][] = 'Password must contain at least one lowercase letter (a-z)';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $response['errors'][] = 'Password must contain at least one number (0-9)';
        }
        if (!preg_match('/[\W_]/', $password)) {
            $response['errors'][] = 'Password must contain at least one special character (!@#$%^&*)';
        }
    }
    
    if (empty($phone)) {
        $response['errors'][] = 'Phone number is required';
    }
    
    if ($password !== $confirm_password) {
        $response['errors'][] = 'Passwords do not match';
    }
    
    // Check if email already exists
    if (empty($response['errors'])) {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $response['errors'][] = 'Email already registered';
        }
        $check_stmt->close();
    }
    
    // If no errors, create account
    if (empty($response['errors'])) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $status = 'active';
        $email_verified = 0; // Set to 0 to require email verification
        $verification_code = sprintf("%06d", mt_rand(1, 999999));
        $verification_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Handle empty optional fields - convert to NULL
        $phone = !empty($phone) ? $phone : null;
        $date_of_birth = !empty($date_of_birth) ? $date_of_birth : null;
        $gender = !empty($gender) ? $gender : null;
        
        $insert_stmt = $conn->prepare("INSERT INTO users (email, password, first_name, last_name, phone, date_of_birth, gender, status, email_verified, verification_code, verification_expiry, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        
        if (!$insert_stmt) {
            $response['errors'][] = 'Database error: ' . $conn->error;
        } else {
            $insert_stmt->bind_param("ssssssssiss", $email, $hashed_password, $first_name, $last_name, $phone, $date_of_birth, $gender, $status, $email_verified, $verification_code, $verification_expiry);
            
            if ($insert_stmt->execute()) {
                // Send OTP Email using PHPMailer & System Settings
                require_once 'includes/email-functions.php';
                sendVerificationEmail($conn, $email, $first_name, $verification_code);

                // Store email in session for verify.php
                $_SESSION['verification_email'] = $email;
                
                $response['success'] = true;
                $response['message'] = 'Account created successfully! Redirecting to verification...';
                
                // Add script to redirect to verify.php
                echo "<script>setTimeout(function() { window.location.href = 'verify.php'; }, 2000);</script>";
            } else {
                $response['errors'][] = 'Registration failed: ' . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
    }
    
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Register - Silky Saree</title>
    <meta name="description" content="Create an account with Silky Saree to explore exclusive designer sarees, track orders, and enjoy tailored shopping.">
    <meta name="keywords" content="Silky Saree register, create account, saree shopping, ethnic wear">

    <!-- Favicons -->
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/main.css" rel="stylesheet">

    <!-- Intl-tel-input CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/css/intlTelInput.css">

    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>

    <style>
        :root {
            --brand-navy: #0e2187;
            --brand-navy-dark: #0a1863;
            --brand-navy-light: #1e35b5;
            --brand-accent: #97c51d;
            --brand-accent-hover: #83ab18;
            --text-primary: #1e293b;
            --text-muted: #64748b;
            --border-light: #e2e8f0;
            --border-focus: #0e2187;
            --card-bg: #ffffff;
            --bg-page: #f8fafc;
        }

        body.register-page {
            background: radial-gradient(circle at 10% 20%, rgba(14, 33, 135, 0.04) 0%, transparent 45%),
                        radial-gradient(circle at 90% 80%, rgba(151, 197, 29, 0.06) 0%, transparent 45%),
                        linear-gradient(180deg, #ffffff 0%, #f4f6fb 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px 16px;
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--text-primary);
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient Decorative Spheres */
        .ambient-shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(75px);
            pointer-events: none;
            z-index: 0;
        }
        .ambient-1 {
            width: 400px;
            height: 400px;
            background: rgba(14, 33, 135, 0.06);
            top: -100px;
            left: -100px;
        }
        .ambient-2 {
            width: 350px;
            height: 350px;
            background: rgba(151, 197, 29, 0.07);
            bottom: -80px;
            right: -80px;
        }

        /* Back to Store Navigation */
        .back-to-store {
            position: absolute;
            top: 24px;
            left: 28px;
            z-index: 10;
        }
        .back-to-store a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            padding: 9px 16px;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-light);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }
        .back-to-store a:hover {
            color: var(--brand-navy);
            border-color: rgba(14, 33, 135, 0.3);
            background: #ffffff;
            box-shadow: 0 6px 16px rgba(14, 33, 135, 0.08);
            transform: translateX(-3px);
        }

        /* Main Card Container */
        .auth-card-container {
            width: 100%;
            max-width: 620px;
            position: relative;
            z-index: 1;
            margin: 40px auto 20px;
            animation: fadeInUp 0.5s ease-out both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-card {
            background: var(--card-bg);
            border-radius: 24px;
            padding: 38px 40px 36px;
            border: 1px solid rgba(14, 33, 135, 0.08);
            box-shadow: 0 20px 50px -12px rgba(14, 33, 135, 0.09),
                        0 0 1px 1px rgba(0, 0, 0, 0.02);
            position: relative;
            overflow: hidden;
        }

        /* Top Accent Brand Line */
        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--brand-navy) 0%, #1e35b5 60%, var(--brand-accent) 100%);
        }

        /* Brand Header */
        .brand-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .brand-logo-wrapper {
            display: inline-block;
            margin-bottom: 12px;
            transition: transform 0.3s ease;
        }
        .brand-logo-wrapper:hover {
            transform: scale(1.04);
        }
        .brand-logo {
            height: 52px;
            width: auto;
            object-fit: contain;
        }
        .brand-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 1.65rem;
            color: var(--brand-navy);
            margin: 0 0 6px;
            letter-spacing: -0.3px;
        }
        .brand-subtitle {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin: 0;
        }

        /* Modern Input Groups */
        .input-group-modern {
            position: relative;
            margin-bottom: 18px;
        }
        .input-group-modern .input-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 7px;
            letter-spacing: 0.1px;
        }
        .input-group-modern .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-group-modern .field-icon {
            position: absolute;
            left: 15px;
            color: #94a3b8;
            font-size: 1.15rem;
            pointer-events: none;
            transition: color 0.25s ease;
            z-index: 2;
        }
        .input-group-modern .form-control,
        .input-group-modern .form-select {
            width: 100%;
            padding: 12px 15px 12px 44px;
            font-size: 0.95rem;
            border-radius: 12px;
            border: 1.5px solid var(--border-light);
            background-color: #fcfdfe;
            color: var(--text-primary);
            transition: all 0.25s ease;
            height: auto;
        }
        .input-group-modern .form-control.has-toggle {
            padding-right: 46px;
        }
        .input-group-modern .form-control::placeholder {
            color: #94a3b8;
            font-size: 0.9rem;
        }
        .input-group-modern .form-control:focus,
        .input-group-modern .form-select:focus {
            border-color: var(--brand-navy);
            background-color: #ffffff;
            box-shadow: 0 0 0 3.5px rgba(14, 33, 135, 0.12);
            outline: none;
        }
        .input-group-modern .input-wrapper:focus-within .field-icon {
            color: var(--brand-navy);
        }

        /* Password Toggle Button */
        .password-toggle-btn {
            position: absolute;
            right: 12px;
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 1.15rem;
            padding: 6px 8px;
            cursor: pointer;
            border-radius: 8px;
            transition: color 0.2s ease, background-color 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }
        .password-toggle-btn:hover {
            color: var(--brand-navy);
            background-color: rgba(14, 33, 135, 0.05);
        }

        /* Password Criteria Hint */
        .field-hint {
            font-size: 0.76rem;
            color: #64748b;
            margin-top: 5px;
            padding-left: 2px;
        }

        /* Intl-tel-input specific styles */
        .iti {
            width: 100%;
            display: block;
            position: relative;
        }
        .iti__flag-container {
            z-index: 5;
        }
        .iti .iti__selected-flag {
            padding: 0 8px 0 14px;
            border-radius: 12px 0 0 12px;
            background-color: transparent;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .iti .iti__selected-dial-code {
            font-size: 0.95rem;
            font-weight: 600;
            color: #334155;
            margin-left: 2px;
        }
        .iti input#phone {
            padding-left: 95px !important;
        }

        /* Checkbox Terms */
        .terms-checkbox-wrap {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 20px 0 24px;
            font-size: 0.88rem;
            color: #475569;
        }
        .terms-checkbox-wrap input[type="checkbox"] {
            width: 17px;
            height: 17px;
            accent-color: var(--brand-navy);
            border-radius: 4px;
            cursor: pointer;
            margin-top: 2px;
            flex-shrink: 0;
        }
        .terms-checkbox-wrap label {
            cursor: pointer;
            margin: 0;
            line-height: 1.4;
        }
        .terms-checkbox-wrap a {
            color: var(--brand-navy);
            text-decoration: none;
            font-weight: 600;
        }
        .terms-checkbox-wrap a:hover {
            color: var(--brand-navy-light);
            text-decoration: underline;
        }

        /* Silky Signature Gradient Button */
        .btn-auth-submit {
            width: 100%;
            padding: 14px 24px;
            background: linear-gradient(135deg, #0e2187 0%, #97c51d 100%);
            border: none;
            border-radius: 50px;
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 6px 20px rgba(151, 197, 29, 0.38), 0 3px 10px rgba(14, 33, 135, 0.2);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1;
        }

        /* Signature Shining Shimmer Sweep Effect */
        .btn-auth-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(to right, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.6) 50%, rgba(255, 255, 255, 0) 100%);
            transform: skewX(-25deg);
            transition: left 0.7s ease;
            pointer-events: none;
            z-index: 2;
        }

        .btn-auth-submit:hover {
            background: linear-gradient(135deg, #0a1863 0%, #85b016 100%);
            box-shadow: 0 10px 28px rgba(151, 197, 29, 0.5), 0 4px 14px rgba(14, 33, 135, 0.3);
            transform: translateY(-2px) scale(1.02);
            color: #ffffff;
        }

        .btn-auth-submit:hover::before {
            left: 170%;
        }

        .btn-auth-submit:hover .submit-arrow {
            transform: translateX(5px);
        }

        .btn-auth-submit:active {
            transform: translateY(0) scale(0.99);
            box-shadow: 0 3px 10px rgba(151, 197, 29, 0.3);
        }

        .submit-arrow {
            transition: transform 0.25s ease;
            font-size: 1.15rem;
            position: relative;
            z-index: 3;
        }

        /* Divider */
        .divider-modern {
            position: relative;
            text-align: center;
            margin: 24px 0 20px;
        }
        .divider-modern::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background-color: var(--border-light);
        }
        .divider-modern span {
            position: relative;
            background-color: var(--card-bg);
            padding: 0 14px;
            font-size: 0.76rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #94a3b8;
        }

        /* Google Button */
        .btn-google-auth {
            width: 100%;
            padding: 12px 18px;
            background: #ffffff;
            border: 1.5px solid var(--border-light);
            border-radius: 12px;
            color: #334155;
            font-size: 0.92rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }
        .btn-google-auth:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            transform: translateY(-1px);
            color: #0f172a;
        }
        .btn-google-auth svg {
            flex-shrink: 0;
        }

        /* Alert Styling */
        .custom-alert {
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.88rem;
            font-weight: 500;
            margin-bottom: 22px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: fadeIn 0.3s ease;
        }
        .custom-alert-danger {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .custom-alert-success {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .custom-alert ul {
            margin: 0;
            padding-left: 18px;
        }

        /* Footer Links */
        .auth-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .auth-footer a {
            color: var(--brand-navy);
            font-weight: 700;
            text-decoration: none;
            margin-left: 4px;
            transition: color 0.2s ease;
        }
        .auth-footer a:hover {
            color: var(--brand-navy-light);
            text-decoration: underline;
        }

        /* Security Trust Badge */
        .trust-badge {
            text-align: center;
            margin-top: 18px;
            font-size: 0.78rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .trust-badge i {
            color: var(--brand-accent);
            font-size: 0.95rem;
        }

        @media (max-width: 576px) {
            .back-to-store {
                position: static;
                margin-bottom: 16px;
                align-self: flex-start;
            }
            .auth-card-container {
                margin: 20px auto;
            }
            .auth-card {
                padding: 30px 20px 26px;
                border-radius: 20px;
            }
            .brand-title {
                font-size: 1.45rem;
            }
        }
    </style>
</head>

<body class="register-page">

    <!-- Ambient Glowing Shapes -->
    <div class="ambient-shape ambient-1"></div>
    <div class="ambient-shape ambient-2"></div>

    <!-- Back to Store Navigation -->
    <div class="back-to-store">
        <a href="index.php">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Store</span>
        </a>
    </div>

    <!-- Main Auth Card Container -->
    <div class="auth-card-container">
        <div class="auth-card">
            
            <!-- Brand Header -->
            <div class="brand-header">
                <a href="index.php" class="brand-logo-wrapper" title="Silky Saree Home">
                    <img src="assets/img/silky.png" alt="Silky Saree" class="brand-logo">
                </a>
                <h1 class="brand-title">Create Account</h1>
                <p class="brand-subtitle">Join Silky Saree to explore handcrafted luxury ethnic wear</p>
            </div>

            <!-- Error Notifications -->
            <?php if (!empty($response['errors'])): ?>
                <div class="custom-alert custom-alert-danger" role="alert">
                    <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
                    <div>
                        <?php if (count($response['errors']) === 1): ?>
                            <?php echo htmlspecialchars($response['errors'][0]); ?>
                        <?php else: ?>
                            <ul>
                                <?php foreach ($response['errors'] as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Success Notification -->
            <?php if ($response['success']): ?>
                <div class="custom-alert custom-alert-success" role="alert">
                    <i class="bi bi-check-circle-fill fs-5 flex-shrink-0"></i>
                    <div>
                        <?php echo htmlspecialchars($response['message']); ?>
                        <div class="mt-1"><a href="login.php" class="text-success fw-bold">Click here to sign in</a></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form action="register.php" method="post" autocomplete="off" id="registerForm">
                
                <!-- Name Row (2 columns) -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group-modern">
                            <label for="first_name" class="input-label">First Name <span class="text-danger">*</span></label>
                            <div class="input-wrapper">
                                <i class="bi bi-person field-icon"></i>
                                <input type="text" class="form-control" id="first_name" name="first_name" placeholder="First Name" required autocomplete="given-name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group-modern">
                            <label for="last_name" class="input-label">Last Name <span class="text-danger">*</span></label>
                            <div class="input-wrapper">
                                <i class="bi bi-person field-icon"></i>
                                <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Last Name" required autocomplete="family-name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Address -->
                <div class="input-group-modern">
                    <label for="email" class="input-label">Email Address <span class="text-danger">*</span></label>
                    <div class="input-wrapper">
                        <i class="bi bi-envelope field-icon"></i>
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required autocomplete="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>

                <!-- Phone Number -->
                <div class="input-group-modern">
                    <label for="phone" class="input-label">Phone Number <span class="text-danger">*</span></label>
                    <div class="input-wrapper">
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone Number" autocomplete="tel" maxlength="25" required value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                </div>

                <!-- Password Row (2 columns) -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group-modern">
                            <label for="password" class="input-label">Password <span class="text-danger">*</span></label>
                            <div class="input-wrapper">
                                <i class="bi bi-shield-lock field-icon"></i>
                                <input type="password" class="form-control has-toggle" id="password" name="password" placeholder="Create password" required minlength="8" autocomplete="new-password">
                                <button type="button" class="password-toggle-btn toggle-password" data-target="password" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group-modern">
                            <label for="confirmPassword" class="input-label">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-wrapper">
                                <i class="bi bi-shield-check field-icon"></i>
                                <input type="password" class="form-control has-toggle" id="confirmPassword" name="confirmPassword" placeholder="Confirm password" required minlength="8" autocomplete="new-password">
                                <button type="button" class="password-toggle-btn toggle-password" data-target="confirmPassword" aria-label="Toggle confirm password visibility">
                                    <i class="bi bi-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="field-hint mb-3">
                    Must have at least 8 characters with 1 uppercase, 1 lowercase, 1 number & 1 special character.
                </div>

                <!-- Optional Details (Date of Birth & Gender) -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group-modern">
                            <label for="date_of_birth" class="input-label">Date of Birth <span class="text-muted fw-normal">(Optional)</span></label>
                            <div class="input-wrapper">
                                <i class="bi bi-calendar3 field-icon"></i>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group-modern">
                            <label for="gender" class="input-label">Gender <span class="text-muted fw-normal">(Optional)</span></label>
                            <div class="input-wrapper">
                                <i class="bi bi-gender-ambiguous field-icon"></i>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="" selected>Prefer not to say</option>
                                    <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="other" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Terms & Conditions Checkbox -->
                <div class="terms-checkbox-wrap">
                    <input type="checkbox" id="termsCheck" name="termsCheck" required>
                    <label for="termsCheck">
                        I agree to the <a href="tos.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>
                    </label>
                </div>

                <!-- Submit Button with Signature Gradient -->
                <button type="submit" class="btn-auth-submit" id="btnRegister">
                    <span>Create Account</span>
                    <i class="bi bi-arrow-right submit-arrow"></i>
                </button>

                <!-- Divider -->
                <div class="divider-modern">
                    <span>or continue with</span>
                </div>

                <!-- Google Sign-Up Button -->
                <button type="button" class="btn-google-auth" id="btn-google-signup">
                    <svg width="20" height="20" viewBox="0 0 48 48">
                        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                    </svg>
                    <span>Sign up with Google</span>
                </button>

                <!-- Sign In Footer Link -->
                <div class="auth-footer">
                    <span>Already have an account?</span>
                    <a href="login">Sign in</a>
                </div>

            </form>

        </div>

        <!-- Trust & Security Badge -->
        <div class="trust-badge">
            <i class="bi bi-shield-check"></i>
            <span>256-Bit SSL Secure & Encrypted Connection</span>
        </div>
    </div>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Intl-tel-input JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/intlTelInput.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const phoneInput = document.querySelector("#phone");
            let iti = null;
            if (phoneInput) {
                // Initialize intlTelInput
                iti = window.intlTelInput(phoneInput, {
                    utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/utils.js",
                    initialCountry: "auto",
                    geoIpLookup: function(callback) {
                        fetch("https://ipapi.co/json")
                            .then(function(res) { return res.json(); })
                            .then(function(data) { callback(data.country_code); })
                            .catch(function() { callback("in"); });
                    },
                    separateDialCode: true,
                });

                // Dynamically adjust padding-left according to dial code width
                const adjustPhonePadding = () => {
                    const selectedFlag = phoneInput.closest('.iti')?.querySelector('.iti__selected-flag');
                    if (selectedFlag) {
                        const width = selectedFlag.offsetWidth;
                        if (width > 0) {
                            phoneInput.style.setProperty('padding-left', (width + 12) + 'px', 'important');
                        }
                    }
                };

                // Lock maxlength to 25 so intlTelInput utilsScript doesn't truncate
                const enforceMaxLen = () => {
                    if (phoneInput.getAttribute('maxlength') !== '25') {
                        phoneInput.setAttribute('maxlength', '25');
                    }
                };
                enforceMaxLen();
                adjustPhonePadding();
                phoneInput.addEventListener('countrychange', () => {
                    enforceMaxLen();
                    setTimeout(adjustPhonePadding, 10);
                });
                setTimeout(adjustPhonePadding, 50);
                setTimeout(adjustPhonePadding, 250);
                const observer = new MutationObserver(enforceMaxLen);
                observer.observe(phoneInput, { attributes: true, attributeFilter: ['maxlength'] });

                // Clean input on typing / pasting / autofill
                const cleanPhoneInput = () => {
                    let val = phoneInput.value;
                    if (val.includes('+')) {
                        iti.setNumber(val);
                        val = phoneInput.value; 
                    }
                    
                    let cleaned = val.replace(/\D/g, '');
                    cleaned = cleaned.replace(/^0+/, '');
                    
                    let countryData = iti.getSelectedCountryData();
                    if (countryData && countryData.dialCode) {
                        let dialCode = countryData.dialCode;
                        if (cleaned.startsWith(dialCode) && cleaned.length > dialCode.length) {
                            let possibleNational = cleaned.substring(dialCode.length);
                            if (cleaned.length > 10 || possibleNational.length === 10) {
                                cleaned = possibleNational;
                            } else if (window.intlTelInputUtils && typeof window.intlTelInputUtils.isValidNumber === 'function') {
                                if (window.intlTelInputUtils.isValidNumber(possibleNational, countryData.iso2)) {
                                    cleaned = possibleNational;
                                }
                            }
                        }
                    }
                    
                    if (phoneInput.value !== cleaned) {
                        phoneInput.value = cleaned;
                    }
                };

                phoneInput.addEventListener('input', function() {
                    cleanPhoneInput();
                    setTimeout(cleanPhoneInput, 10);
                    setTimeout(cleanPhoneInput, 100);
                });
                phoneInput.addEventListener('change', cleanPhoneInput);
                phoneInput.addEventListener('blur', cleanPhoneInput);

                // Before form submit, format phone value with country code
                const form = phoneInput.closest('form');
                if (form) {
                    form.addEventListener('submit', function() {
                        cleanPhoneInput();
                        if (phoneInput.value.trim() !== '' && iti) {
                            phoneInput.value = iti.getNumber();
                        }
                    });
                }
            }

            // Password Visibility Toggles
            document.querySelectorAll('.toggle-password').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('data-target');
                    const targetInput = document.getElementById(targetId);
                    const icon = this.querySelector('i');
                    
                    if (targetInput) {
                        const isPassword = targetInput.type === 'password';
                        targetInput.type = isPassword ? 'text' : 'password';
                        if (icon) {
                            icon.className = isPassword ? 'bi bi-eye' : 'bi bi-eye-slash';
                        }
                        this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
                    }
                });
            });

            // Google Sign-Up Integration
            let googleTokenClient = null;

            function handleGoogleUser(userInfo) {
                const formData = new FormData();
                formData.append('email', userInfo.email);
                formData.append('first_name', userInfo.given_name || userInfo.name || '');
                formData.append('last_name', userInfo.family_name || '');
                formData.append('google_id', userInfo.sub || userInfo.id || '');

                fetch('google-auth.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'index.php';
                    } else {
                        alert(data.message || 'Google Authentication failed');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('An error occurred during Google Sign-In');
                });
            }

            function handleGoogleResponse(response) {
                if (response && response.credential) {
                    try {
                        const base64Url = response.credential.split('.')[1];
                        const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
                        const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
                            return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
                        }).join(''));
                        
                        const payload = JSON.parse(jsonPayload);
                        handleGoogleUser(payload);
                    } catch(e) {
                        console.error('Error decoding Google credential', e);
                    }
                }
            }

            function initGoogleServices() {
                if (typeof google !== 'undefined' && google.accounts) {
                    // Initialize Token Client for custom button popup
                    if (google.accounts.oauth2) {
                        try {
                            googleTokenClient = google.accounts.oauth2.initTokenClient({
                                client_id: "1092814794837-6472he5o0899vnacua03u3r4pl56l1ev.apps.googleusercontent.com",
                                scope: "email profile openid",
                                callback: function(tokenResponse) {
                                    if (tokenResponse && tokenResponse.access_token) {
                                        fetch('https://www.googleapis.com/oauth2/v3/userinfo', {
                                            headers: { Authorization: 'Bearer ' + tokenResponse.access_token }
                                        })
                                        .then(res => res.json())
                                        .then(userInfo => {
                                            handleGoogleUser(userInfo);
                                        })
                                        .catch(err => {
                                            console.error('Error fetching Google userinfo', err);
                                        });
                                    }
                                }
                            });
                        } catch (e) {
                            console.error('Failed to initialize Google Token Client', e);
                        }
                    }

                    // Also initialize One-Tap as passive option
                    if (google.accounts.id) {
                        try {
                            google.accounts.id.initialize({
                                client_id: "1092814794837-6472he5o0899vnacua03u3r4pl56l1ev.apps.googleusercontent.com",
                                callback: handleGoogleResponse
                            });
                        } catch (e) {
                            console.error('Failed to initialize Google One Tap', e);
                        }
                    }
                }
            }

            // Initialize when Google script finishes loading
            if (typeof google !== 'undefined' && google.accounts) {
                initGoogleServices();
            } else {
                window.addEventListener('load', initGoogleServices);
            }

            const googleBtn = document.getElementById('btn-google-signup');
            if (googleBtn) {
                googleBtn.addEventListener('click', function() {
                    if (googleTokenClient) {
                        // Triggers official Google account selector popup window
                        googleTokenClient.requestAccessToken({ prompt: 'select_account' });
                    } else if (typeof google !== 'undefined' && google.accounts && google.accounts.id) {
                        google.accounts.id.prompt();
                    } else {
                        alert("Google Sign-In is still loading. Please try again in a moment or sign up using the form.");
                    }
                });
            }
        });
    </script>
</body>
</html>