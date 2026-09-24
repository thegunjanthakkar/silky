<?php
session_start();
require_once 'db_config.php';

// Check if there is an email waiting to be verified
if (!isset($_SESSION['verification_email'])) {
    header('Location: login.php');
    exit;
}

$email = $_SESSION['verification_email'];
$response = array(
    'success' => false,
    'message' => '',
    'errors' => array()
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'verify') {
        $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
        
        if (empty($otp) || strlen($otp) !== 6) {
            $response['errors'][] = 'Please enter a valid 6-digit OTP.';
        } else {
            $stmt = $conn->prepare("SELECT id, first_name, verification_expiry, email_verified FROM users WHERE email = ? AND verification_code = ?");
            $stmt->bind_param("ss", $email, $otp);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Check if OTP is expired
                if (strtotime($user['verification_expiry']) < time()) {
                    $response['errors'][] = 'This verification code has expired. Please request a new one.';
                } else if ($user['email_verified'] == 1) {
                    $response['errors'][] = 'Email is already verified. You can login.';
                } else {
                    // Update user as verified
                    $update_stmt = $conn->prepare("UPDATE users SET email_verified = 1, verification_code = NULL, verification_expiry = NULL WHERE id = ?");
                    $update_stmt->bind_param("i", $user['id']);
                    
                    if ($update_stmt->execute()) {
                        // Log the user in
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $email;
                        $_SESSION['first_name'] = $user['first_name'];
                        $_SESSION['logged_in'] = true;
                        
                        // Clear the pending verification session
                        unset($_SESSION['verification_email']);
                        
                        $response['success'] = true;
                        $response['message'] = 'Email verified successfully! Redirecting...';
                        
                        echo "<script>setTimeout(function() { window.location.href = 'index.php'; }, 2000);</script>";
                    } else {
                        $response['errors'][] = 'Failed to update verification status.';
                    }
                    $update_stmt->close();
                }
            } else {
                $response['errors'][] = 'Invalid verification code.';
            }
            $stmt->close();
        }
    } else if (isset($_POST['action']) && $_POST['action'] === 'resend') {
        // Enforce: Resend OTP is ONLY allowed after the current countdown timer ends
        $check_stmt = $conn->prepare("SELECT id, first_name, verification_expiry, email_verified FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();
        
        if ($check_res && $check_res->num_rows > 0) {
            $user = $check_res->fetch_assoc();
            
            if ($user['email_verified'] == 1) {
                $response['errors'][] = 'Email is already verified. You can proceed to login.';
            } else {
                $expiry_time = !empty($user['verification_expiry']) ? strtotime($user['verification_expiry']) : 0;
                $seconds_left = $expiry_time - time();
                
                // Block resend if timer is still running
                if ($seconds_left > 0) {
                    $mins_left = ceil($seconds_left / 60);
                    $response['errors'][] = "Please wait for the current code to expire before requesting a new OTP ({$mins_left} min remaining).";
                } else {
                    // Current timer has expired, generate new OTP
                    $new_otp = sprintf("%06d", mt_rand(1, 999999));
                    $new_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    
                    $update_stmt = $conn->prepare("UPDATE users SET verification_code = ?, verification_expiry = ? WHERE email = ?");
                    $update_stmt->bind_param("sss", $new_otp, $new_expiry, $email);
                    
                    if ($update_stmt->execute()) {
                        $fname = !empty($user['first_name']) ? $user['first_name'] : 'User';
                        
                        // Send email
                        require_once 'includes/email-functions.php';
                        $email_sent = sendVerificationEmail($conn, $email, $fname, $new_otp);
                        
                        if ($email_sent) {
                            $response['success'] = true;
                            $response['message'] = 'A fresh 6-digit verification code has been sent to your email.';
                        } else {
                            $response['errors'][] = 'Verification code generated, but email could not be sent. Please check your mail settings.';
                        }
                    } else {
                        $response['errors'][] = 'Failed to generate a new verification code.';
                    }
                    $update_stmt->close();
                }
            }
        } else {
            $response['errors'][] = 'User account not found.';
        }
        $check_stmt->close();
        
        // If AJAX request, return JSON response immediately
        $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['ajax']);
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    }
}

// Fetch current verification expiry from database to calculate real dynamic remaining time
$remaining_seconds = 0;
$expiry_stmt = $conn->prepare("SELECT verification_expiry FROM users WHERE email = ? AND email_verified = 0");
if ($expiry_stmt) {
    $expiry_stmt->bind_param("s", $email);
    $expiry_stmt->execute();
    $expiry_res = $expiry_stmt->get_result();
    if ($expiry_res && $expiry_res->num_rows > 0) {
        $user_row = $expiry_res->fetch_assoc();
        if (!empty($user_row['verification_expiry'])) {
            $expiry_time = strtotime($user_row['verification_expiry']);
            $remaining_seconds = max(0, $expiry_time - time());
        } else {
            // Fallback: If verification_expiry is not yet set in DB, initialize it once to +15 minutes
            $initial_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $init_up = $conn->prepare("UPDATE users SET verification_expiry = ? WHERE email = ? AND email_verified = 0");
            if ($init_up) {
                $init_up->bind_param("ss", $initial_expiry, $email);
                $init_up->execute();
                $init_up->close();
            }
            $remaining_seconds = 900;
        }
    }
    $expiry_stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Verify Email - Silky Saree</title>
    <meta name="description" content="Verify your email address to complete your Silky Saree account registration.">
    <meta name="keywords" content="Silky Saree, verify email, OTP verification, customer account">

    <!-- Favicons -->
    <link href="assets/img/favicon/favicon.ico" rel="icon" type="image/x-icon">
    <link href="assets/img/favicon/favicon-32x32.png" rel="icon" type="image/png" sizes="32x32">
    <link href="assets/img/favicon/favicon-16x16.png" rel="icon" type="image/png" sizes="16x16">
    <link href="assets/img/favicon/apple-touch-icon.png" rel="apple-touch-icon" sizes="180x180">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/main.css" rel="stylesheet">

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

        body.verify-page {
            background: radial-gradient(circle at 10% 20%, rgba(14, 33, 135, 0.04) 0%, transparent 45%),
                        radial-gradient(circle at 90% 80%, rgba(151, 197, 29, 0.06) 0%, transparent 45%),
                        linear-gradient(180deg, #ffffff 0%, #f4f6fb 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 30px 16px;
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
            width: 360px;
            height: 360px;
            background: rgba(14, 33, 135, 0.06);
            top: -90px;
            left: -90px;
        }
        .ambient-2 {
            width: 320px;
            height: 320px;
            background: rgba(151, 197, 29, 0.07);
            bottom: -70px;
            right: -70px;
        }

        /* Main Card Container */
        .auth-card-container {
            width: 100%;
            max-width: 460px;
            position: relative;
            z-index: 1;
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
            padding: 38px 36px 34px;
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
            margin-bottom: 26px;
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
            margin: 0 0 8px;
            letter-spacing: -0.3px;
        }
        .brand-subtitle {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.5;
            margin: 0;
        }
        .email-badge {
            display: inline-block;
            background: rgba(14, 33, 135, 0.06);
            color: var(--brand-navy);
            font-weight: 600;
            padding: 3px 12px;
            border-radius: 20px;
            border: 1px solid rgba(14, 33, 135, 0.12);
            margin-top: 5px;
            word-break: break-all;
        }

        /* Custom Alert Notifications */
        .custom-alert {
            padding: 13px 16px;
            border-radius: 14px;
            font-size: 0.88rem;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: fadeInAlert 0.3s ease-out;
        }
        @keyframes fadeInAlert {
            from { opacity: 0; transform: scale(0.98); }
            to { opacity: 1; transform: scale(1); }
        }
        .custom-alert-danger {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }
        .custom-alert-success {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
        }
        .custom-alert ul {
            margin: 0;
            padding-left: 18px;
        }

        /* Modern Input Group */
        .input-group-modern {
            position: relative;
            margin-bottom: 22px;
        }
        .input-group-modern .input-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
            text-align: center;
            letter-spacing: 0.2px;
        }
        .input-group-modern .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-group-modern .field-icon {
            position: absolute;
            left: 16px;
            color: #94a3b8;
            font-size: 1.25rem;
            pointer-events: none;
            transition: color 0.25s ease;
            z-index: 2;
        }

        /* OTP Code Input Styling */
        .otp-input-modern {
            width: 100%;
            padding: 15px 16px 15px 46px;
            font-family: 'Montserrat', monospace, sans-serif;
            font-size: 1.85rem;
            font-weight: 700;
            letter-spacing: 0.65rem;
            text-align: center;
            border-radius: 14px;
            border: 2px solid var(--border-light);
            background-color: #f8fafc;
            color: var(--brand-navy);
            transition: all 0.25s ease;
            height: auto;
        }
        .otp-input-modern::placeholder {
            color: #cbd5e1;
            letter-spacing: 0.45rem;
            font-weight: 400;
        }
        .otp-input-modern:focus {
            border-color: var(--brand-navy);
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(14, 33, 135, 0.12);
            outline: none;
        }
        .input-group-modern .input-wrapper:focus-within .field-icon {
            color: var(--brand-navy);
        }
        .otp-hint {
            font-size: 0.84rem;
            color: var(--text-muted);
            text-align: center;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .timer-countdown {
            font-family: 'Montserrat', monospace, sans-serif;
            font-weight: 700;
            color: var(--brand-navy);
            background: rgba(14, 33, 135, 0.06);
            padding: 2px 8px;
            border-radius: 6px;
            letter-spacing: 0.5px;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .timer-countdown.expiring-soon {
            color: #dc2626;
            background: #fef2f2;
            animation: pulseWarning 1.5s infinite ease-in-out;
        }
        @keyframes pulseWarning {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.65; }
        }
        .timer-expired {
            color: #dc2626;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Primary Submit Button - Silky Signature Gradient */
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
            left: 150%;
        }
        .btn-auth-submit:active {
            transform: translateY(0) scale(0.99);
        }
        .btn-arrow-icon {
            font-size: 1.1rem;
            transition: transform 0.25s ease;
        }
        .btn-auth-submit:hover .btn-arrow-icon {
            transform: translateX(4px);
        }

        /* Resend Section & Switch Links */
        .auth-switch {
            margin-top: 22px;
            font-size: 0.9rem;
        }
        .auth-switch-text {
            color: var(--text-muted);
            margin: 0;
        }
        .resend-link-btn {
            background: none;
            border: none;
            color: var(--brand-navy);
            font-weight: 700;
            font-size: 0.92rem;
            cursor: pointer;
            padding: 0;
            text-decoration: underline;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .resend-link-btn:hover:not(:disabled) {
            color: var(--brand-accent-hover);
        }
        .resend-link-btn:disabled {
            color: #94a3b8;
            cursor: not-allowed;
            text-decoration: none;
            opacity: 0.7;
        }
        .resend-timer-notice {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
            margin-left: 2px;
        }
        .resend-link-btn.btn-active-resend {
            color: #0e2187;
            background: #eef2ff;
            padding: 5px 14px;
            border-radius: 20px;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(14, 33, 135, 0.12);
            animation: resendPulse 2s infinite ease-in-out;
        }
        .resend-link-btn.btn-active-resend:hover {
            background: #e0e7ff;
            color: #081459;
            transform: translateY(-1px);
        }
        @keyframes resendPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 2px 8px rgba(14, 33, 135, 0.12); }
            50% { transform: scale(1.03); box-shadow: 0 4px 14px rgba(14, 33, 135, 0.25); }
        }
        .auth-switch-link {
            color: var(--brand-navy);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .auth-switch-link:hover {
            color: var(--brand-accent-hover);
            text-decoration: underline;
        }

        /* Trust & Security Badge */
        .auth-security-badge {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.78rem;
            color: #94a3b8;
            letter-spacing: 0.2px;
        }
        .auth-security-badge i {
            color: var(--brand-accent);
            font-size: 0.95rem;
        }

        @media (max-width: 576px) {
            .auth-card {
                padding: 30px 22px;
                border-radius: 20px;
            }
            .brand-title {
                font-size: 1.45rem;
            }
            .otp-input-modern {
                font-size: 1.5rem;
                letter-spacing: 0.45rem;
                padding-left: 36px;
            }
            .input-group-modern .field-icon {
                left: 12px;
                font-size: 1.1rem;
            }
        }
    </style>
</head>

<body class="verify-page">

    <!-- Ambient Decorative Glow Spheres -->
    <div class="ambient-shape ambient-1"></div>
    <div class="ambient-shape ambient-2"></div>


    <!-- Main Auth Card Container -->
    <div class="auth-card-container">
        <div class="auth-card">
            
            <!-- Brand Header -->
            <div class="brand-header">
                <a href="index.php" class="brand-logo-wrapper" title="Silky Saree Home">
                    <img src="assets/img/silky.png" alt="Silky Saree" class="brand-logo">
                </a>
                <h1 class="brand-title">Verify Your Email</h1>
                <p class="brand-subtitle">
                    We've sent a 6-digit verification code to<br>
                    <span class="email-badge"><?php echo htmlspecialchars($email); ?></span>
                </p>
            </div>

            <!-- Notification Alerts Container -->
            <div id="alertContainer">
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
                        <div><?php echo htmlspecialchars($response['message']); ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Verification Code Form -->
            <form action="verify.php" method="POST" class="auth-form" id="verifyForm" autocomplete="off">
                <input type="hidden" name="action" value="verify">
                
                <!-- 6-Digit Code Field -->
                <div class="input-group-modern">
                    <label for="otpInput" class="input-label">Enter 6-Digit Verification Code</label>
                    <div class="input-wrapper">
                        <i class="bi bi-shield-lock field-icon"></i>
                        <input type="text" name="otp" id="otpInput" class="form-control otp-input-modern" placeholder="••••••" maxlength="6" pattern="\d{6}" inputmode="numeric" required autocomplete="one-time-code" autofocus>
                    </div>
                    <div class="otp-hint" id="timerContainer">
                        <?php if ($remaining_seconds <= 0): ?>
                            <span class="timer-expired"><i class="bi bi-exclamation-circle-fill"></i> Code expired. Please request a new OTP below.</span>
                        <?php else: ?>
                            <i class="bi bi-clock-history text-muted" id="timerIcon"></i>
                            <span id="timerLabel">Code expires in</span>
                            <span id="timerCountdown" class="timer-countdown <?php echo ($remaining_seconds <= 120) ? 'expiring-soon' : ''; ?>"><?php echo sprintf('%02d:%02d', floor($remaining_seconds / 60), $remaining_seconds % 60); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Verify CTA Button -->
                <button type="submit" class="btn-auth-submit" id="verifyBtn">
                    <span>Verify Code</span>
                    <i class="bi bi-arrow-right btn-arrow-icon"></i>
                </button>
            </form>

            <!-- Resend Code Form -->
            <div class="auth-switch text-center">
                <form action="verify.php" method="POST" id="resendForm" class="d-inline">
                    <input type="hidden" name="action" value="resend">
                    <p class="auth-switch-text mb-0">
                        Didn't receive the code? 
                        <button type="submit" class="resend-link-btn <?php echo ($remaining_seconds <= 0) ? 'btn-active-resend' : ''; ?>" id="resendBtn" <?php echo ($remaining_seconds > 0) ? 'disabled' : ''; ?>>
                            <i class="bi bi-arrow-clockwise"></i>
                            <span id="resendBtnText">Resend OTP</span>
                        </button>
                        <span id="resendTimerNotice" class="resend-timer-notice <?php echo ($remaining_seconds <= 0) ? 'd-none' : ''; ?>">
                            (wait <span id="resendSecondsText"><?php echo sprintf('%02d:%02d', floor($remaining_seconds / 60), $remaining_seconds % 60); ?></span>)
                        </span>
                    </p>
                </form>
            </div>

            <!-- Alternative Switch Links -->
            <div class="auth-switch text-center mt-3 pt-3" style="border-top: 1px solid #f1f5f9;">
                <p class="auth-switch-text mb-0" style="font-size: 0.84rem;">
                    Entered wrong email? <a href="register" class="auth-switch-link">Register Again</a> or <a href="login" class="auth-switch-link">Sign In</a>
                </p>
            </div>

            <!-- Security Trust Badge -->
            <div class="auth-security-badge">
                <i class="bi bi-shield-lock-fill"></i>
                <span>256-Bit SSL Secure Verification</span>
            </div>

        </div>
    </div>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        // Numeric-only filter & auto-focus
        const otpInput = document.getElementById('otpInput');
        if (otpInput) {
            otpInput.addEventListener('input', function(e) {
                // Strip non-digit characters
                this.value = this.value.replace(/\D/g, '').slice(0, 6);
            });
            // Focus on load
            otpInput.focus();
        }

        // Form submission UX feedback
        const verifyForm = document.getElementById('verifyForm');
        const verifyBtn = document.getElementById('verifyBtn');
        if (verifyForm && verifyBtn) {
            verifyForm.addEventListener('submit', function() {
                if (otpInput && otpInput.value.length === 6) {
                    verifyBtn.disabled = true;
                    verifyBtn.style.opacity = '0.85';
                    verifyBtn.innerHTML = '<span>Verifying...</span> <div class="spinner-border spinner-border-sm text-light ms-2" role="status"></div>';
                }
            });
        }

        // Elements
        const timerContainer = document.getElementById('timerContainer');
        const resendBtn = document.getElementById('resendBtn');
        const resendBtnText = document.getElementById('resendBtnText');
        const resendTimerNotice = document.getElementById('resendTimerNotice');
        const resendSecondsText = document.getElementById('resendSecondsText');
        const alertContainer = document.getElementById('alertContainer');
        const resendForm = document.getElementById('resendForm');

        function showAlert(type, msg) {
            if (!alertContainer) return;
            const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';
            alertContainer.innerHTML = `
                <div class="custom-alert custom-alert-${type}" role="alert">
                    <i class="bi ${icon} fs-5 flex-shrink-0"></i>
                    <div>${msg}</div>
                </div>
            `;
            try {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch(e) {}
        }

        let countdownTimerId = null;

        function startTimer(seconds) {
            if (countdownTimerId) {
                clearInterval(countdownTimerId);
                countdownTimerId = null;
            }

            if (seconds <= 0) {
                onTimerExpired();
                return;
            }

            // Restore initial timer clock markup in timerContainer if it previously had expired message
            if (timerContainer) {
                const initialM = Math.floor(seconds / 60);
                const initialS = seconds % 60;
                const formattedInitial = (initialM < 10 ? '0' : '') + initialM + ':' + (initialS < 10 ? '0' : '') + initialS;
                timerContainer.innerHTML = `
                    <i class="bi bi-clock-history text-muted" id="timerIcon"></i>
                    <span id="timerLabel">Code expires in</span>
                    <span id="timerCountdown" class="timer-countdown ${seconds <= 120 ? 'expiring-soon' : ''}">${formattedInitial}</span>
                `;
            }

            // Lock Resend OTP button while timer is active
            if (resendBtn) {
                resendBtn.disabled = true;
                resendBtn.classList.remove('btn-active-resend');
                if (resendBtnText) resendBtnText.textContent = 'Resend OTP';
            }
            if (resendTimerNotice) {
                resendTimerNotice.classList.remove('d-none');
            }

            const targetEndTime = Date.now() + (seconds * 1000);

            function updateTick() {
                const now = Date.now();
                const secondsLeft = Math.max(0, Math.ceil((targetEndTime - now) / 1000));

                if (secondsLeft <= 0) {
                    onTimerExpired();
                    return false;
                }

                const m = Math.floor(secondsLeft / 60);
                const s = secondsLeft % 60;
                const formatted = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;

                const timerCountdown = document.getElementById('timerCountdown');
                if (timerCountdown) {
                    timerCountdown.textContent = formatted;
                    if (secondsLeft <= 120) {
                        timerCountdown.classList.add('expiring-soon');
                    } else {
                        timerCountdown.classList.remove('expiring-soon');
                    }
                }

                if (resendSecondsText) {
                    resendSecondsText.textContent = formatted;
                }

                return true;
            }

            updateTick();
            countdownTimerId = setInterval(function() {
                if (!updateTick()) {
                    clearInterval(countdownTimerId);
                    countdownTimerId = null;
                }
            }, 1000);
        }

        function onTimerExpired() {
            if (timerContainer) {
                timerContainer.innerHTML = '<span class="timer-expired"><i class="bi bi-exclamation-circle-fill"></i> Code expired. Please request a new OTP below.</span>';
            }
            if (resendTimerNotice) {
                resendTimerNotice.classList.add('d-none');
            }
            if (resendBtn) {
                resendBtn.disabled = false;
                resendBtn.classList.add('btn-active-resend');
                if (resendBtnText) resendBtnText.textContent = 'Resend OTP';
            }
        }

        // Initialize timer with server remaining seconds
        const serverRemainingSeconds = <?php echo intval($remaining_seconds); ?>;
        startTimer(serverRemainingSeconds);

        // Handle AJAX Resend Form submission (only fires when button is enabled)
        if (resendForm && resendBtn) {
            resendForm.addEventListener('submit', function(e) {
                // If button is currently disabled, prevent submit
                if (resendBtn.disabled) {
                    e.preventDefault();
                    return false;
                }

                e.preventDefault();

                // Show loading state on button
                resendBtn.disabled = true;
                resendBtn.classList.remove('btn-active-resend');
                if (resendBtnText) {
                    resendBtnText.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Sending...';
                }

                const formData = new FormData(resendForm);
                formData.append('ajax', '1');

                fetch('verify.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(res) {
                    return res.json();
                })
                .then(function(data) {
                    if (data.success) {
                        showAlert('success', data.message || 'A fresh verification code has been sent to your email.');
                        // Restart countdown to 15 minutes (900 seconds)
                        startTimer(900);
                        if (otpInput) {
                            otpInput.value = '';
                            otpInput.focus();
                        }
                    } else {
                        const errorMsg = (data.errors && data.errors.length > 0) ? data.errors[0] : 'Failed to send new verification code.';
                        showAlert('danger', errorMsg);
                        // Re-enable if error occurred and timer had expired
                        resendBtn.disabled = false;
                        resendBtn.classList.add('btn-active-resend');
                        if (resendBtnText) resendBtnText.textContent = 'Resend OTP';
                    }
                })
                .catch(function(err) {
                    // Fallback to normal form submit if fetch fails
                    resendForm.submit();
                });
            });
        }
    </script>

</body>
</html>
