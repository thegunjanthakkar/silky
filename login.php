<?php
session_start();
require_once 'db_config.php';

// Process login if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Determine redirect URL
    $redirect_url = 'index.php'; // Default to index
    
    // Only check for explicit redirect parameters (POST or GET or Session)
    if (isset($_POST['redirect_url']) && !empty($_POST['redirect_url'])) {
        $redirect_url = $_POST['redirect_url'];
    } elseif (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
        $redirect_url = $_GET['redirect'];
    } elseif (isset($_SESSION['redirect_after_login']) && !empty($_SESSION['redirect_after_login'])) {
        $redirect_url = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
    }
    
    // Sanitize redirect URL to prevent external redirects
    $redirect_url = preg_replace('#^https?://#', '', $redirect_url);
    $redirect_url = preg_replace('#^www\.#', '', $redirect_url);
    // Replace multiple leading slashes (e.g. protocol-relative //) with a single slash
    if (strpos($redirect_url, '/') === 0) {
        $redirect_url = preg_replace('#^/+#', '/', $redirect_url);
    }
    
    // Validate input
    if (empty($email)) {
        $_SESSION['error_message'] = 'Email is required';
    } elseif (empty($password)) {
        $_SESSION['error_message'] = 'Password is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = 'Invalid email format';
    } else {
        // Prepare SQL statement
        $stmt = $conn->prepare("SELECT id, email, password, first_name, last_name, phone, status, email_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $_SESSION['error_message'] = 'Invalid email or password';
        } else {
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if (!password_verify($password, $user['password'])) {
                $_SESSION['error_message'] = 'Invalid email or password';
            } elseif ($user['status'] !== 'active') {
                $_SESSION['error_message'] = 'Your account has been suspended. Please contact support';
            } else {
                // Login successful
                $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_first_name'] = $user['first_name'];
                $_SESSION['user_last_name'] = $user['last_name'];
                $_SESSION['user_phone'] = $user['phone'];
                $_SESSION['logged_in'] = true;
                
                // Merge guest cart if exists
                if (isset($_SESSION['guest_cart']) && !empty($_SESSION['guest_cart'])) {
                    $cart_stmt = $conn->prepare("SELECT product_id, quantity FROM cart WHERE user_id = ?");
                    $cart_stmt->bind_param("i", $user['id']);
                    $cart_stmt->execute();
                    $cart_result = $cart_stmt->get_result();
                    
                    $existing_cart = array();
                    while ($row = $cart_result->fetch_assoc()) {
                        $existing_cart[$row['product_id']] = $row['quantity'];
                    }
                    $cart_stmt->close();
                    
                    foreach ($_SESSION['guest_cart'] as $product_id => $quantity) {
                        if (isset($existing_cart[$product_id])) {
                            $new_quantity = $existing_cart[$product_id] + $quantity;
                            $merge_stmt = $conn->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE user_id = ? AND product_id = ?");
                            $merge_stmt->bind_param("iii", $new_quantity, $user['id'], $product_id);
                            $merge_stmt->execute();
                            $merge_stmt->close();
                        } else {
                            $merge_stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
                            $merge_stmt->bind_param("iii", $user['id'], $product_id, $quantity);
                            $merge_stmt->execute();
                            $merge_stmt->close();
                        }
                    }
                    
                    unset($_SESSION['guest_cart']);
                }
                
                $_SESSION['success_message'] = 'Welcome back, ' . $user['first_name'] . '!';
                $conn->close();
                
                // Redirect
                header('Location: ' . $redirect_url);
                exit();
            }
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
    <title>Login - Silky Saree</title>
    <meta name="description" content="Sign in to your Silky Saree account to manage your orders, wishlist, and profile.">
    <meta name="keywords" content="Silky Saree, saree login, customer account, ethnic wear">

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

        body.login-page {
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
            filter: blur(70px);
            pointer-events: none;
            z-index: 0;
        }
        .ambient-1 {
            width: 350px;
            height: 350px;
            background: rgba(14, 33, 135, 0.06);
            top: -90px;
            left: -90px;
        }
        .ambient-2 {
            width: 300px;
            height: 300px;
            background: rgba(151, 197, 29, 0.07);
            bottom: -70px;
            right: -70px;
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
            max-width: 450px;
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

        /* Modern Inputs with Icons */
        .input-group-modern {
            position: relative;
            margin-bottom: 20px;
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
        .input-group-modern .form-control {
            width: 100%;
            padding: 13px 15px 13px 44px;
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
        .input-group-modern .form-control:focus {
            border-color: var(--brand-navy);
            background-color: #ffffff;
            box-shadow: 0 0 0 3.5px rgba(14, 33, 135, 0.12);
            outline: none;
        }
        .input-group-modern .input-wrapper:focus-within .field-icon {
            color: var(--brand-navy);
        }
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

        /* Form Options Row (Remember Me & Forgot Password) */
        .form-options-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
            font-size: 0.88rem;
        }
        .custom-remember {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
            margin: 0;
        }
        .custom-remember input[type="checkbox"] {
            width: 17px;
            height: 17px;
            accent-color: var(--brand-navy);
            border-radius: 4px;
            cursor: pointer;
            margin: 0;
        }
        .custom-remember label {
            cursor: pointer;
            color: #475569;
            font-weight: 500;
            margin: 0;
        }
        .forgot-link {
            color: var(--brand-navy);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }
        .forgot-link:hover {
            color: var(--brand-navy-light);
            text-decoration: underline;
        }

        /* Primary Submit Button - Silky Signature Gradient Button */
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
            margin: 22px 0;
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
            padding: 11px 18px;
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

        /* Alert styling */
        .custom-alert {
            border-radius: 12px;
            padding: 11px 14px;
            font-size: 0.88rem;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            .auth-card {
                padding: 30px 22px 26px;
                border-radius: 20px;
            }
            .brand-title {
                font-size: 1.45rem;
            }
        }
    </style>
</head>

<body class="login-page">

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
                <h1 class="brand-title">Welcome Back</h1>
                <p class="brand-subtitle">Sign in to access your orders and account</p>
            </div>

            <!-- Error Notification -->
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="custom-alert custom-alert-danger" role="alert">
                    <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
                    <div><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
                </div>
            <?php endif; ?>

            <!-- Success Notification -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="custom-alert custom-alert-success" role="alert">
                    <i class="bi bi-check-circle-fill fs-5 flex-shrink-0"></i>
                    <div><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" method="POST" class="auth-form" id="loginForm">
                
                <!-- Email Address Field -->
                <div class="input-group-modern">
                    <label for="email" class="input-label">Email Address</label>
                    <div class="input-wrapper">
                        <i class="bi bi-envelope field-icon"></i>
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required autocomplete="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>

                <!-- Password Field with Show/Hide Toggle -->
                <div class="input-group-modern">
                    <label for="password" class="input-label">Password</label>
                    <div class="input-wrapper">
                        <i class="bi bi-shield-lock field-icon"></i>
                        <input type="password" class="form-control has-toggle" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" class="password-toggle-btn" id="passwordToggle" aria-label="Toggle password visibility">
                            <i class="bi bi-eye-slash" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Preserve redirect URL from both GET and session -->
                <?php 
                $redirect_url = '';
                if (isset($_GET['redirect'])) {
                    $redirect_url = $_GET['redirect'];
                } elseif (isset($_SESSION['redirect_after_login'])) {
                    $redirect_url = $_SESSION['redirect_after_login'];
                }
                ?>
                <input type="hidden" name="redirect_url" id="redirect_url" value="<?php echo htmlspecialchars($redirect_url); ?>">

                <!-- Options Row: Remember Me & Forgot Password -->
                <div class="form-options-row">
                    <div class="custom-remember">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="#" class="forgot-link" title="Reset your password">Forgot password?</a>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-auth-submit" id="btnSubmit">
                    <span>Sign In</span>
                    <i class="bi bi-arrow-right submit-arrow"></i>
                </button>

                <!-- Divider -->
                <div class="divider-modern">
                    <span>or continue with</span>
                </div>

                <!-- Google Sign-In Button -->
                <button type="button" class="btn-google-auth" id="btnGoogleLogin">
                    <svg width="20" height="20" viewBox="0 0 48 48">
                        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                    </svg>
                    <span>Sign in with Google</span>
                </button>

                <!-- Sign Up Footer Link -->
                <div class="auth-footer">
                    <span>Don't have an account?</span>
                    <a href="register">Sign up</a>
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

    <!-- Interactive Scripts -->
    <script>
        // Password Visibility Toggle
        const passwordToggle = document.getElementById('passwordToggle');
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');

        if (passwordToggle && passwordInput && toggleIcon) {
            passwordToggle.addEventListener('click', function () {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                toggleIcon.className = isPassword ? 'bi bi-eye' : 'bi bi-eye-slash';
                passwordToggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            });
        }

        // Google Sign-In Integration
        let googleTokenClient = null;
        const redirectUrl = document.getElementById('redirect_url') ? document.getElementById('redirect_url').value : '';

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
                    window.location.href = redirectUrl || 'index.php';
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

                // Also initialize One-Tap
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

        const googleBtn = document.getElementById('btnGoogleLogin');
        if (googleBtn) {
            googleBtn.addEventListener('click', function () {
                if (googleTokenClient) {
                    googleTokenClient.requestAccessToken({ prompt: 'select_account' });
                } else if (typeof google !== 'undefined' && google.accounts && google.accounts.id) {
                    google.accounts.id.prompt();
                } else {
                    alert("Google Sign-In is still loading. Please try again in a moment or sign in using your credentials.");
                }
            });
        }
    </script>
</body>
</html>
