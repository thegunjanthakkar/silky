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
    <meta name="description" content="">
    <meta name="keywords" content="">

    <!-- Favicons -->
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/main.css" rel="stylesheet">
</head>

<body class="login-page">

    <main class="main">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="login-container">
                        <div class="text-center mb-4">
                            <img src="assets/img/silky.png" alt="Silky Saree" class="mb-3" style="height: 60px;">
                            <h2>Welcome Back</h2>
                            <p class="text-muted">Please sign in to your account</p>
                        </div>

                        <?php if (isset($_SESSION['error_message'])): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success">
                                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                            </div>
                        <?php endif; ?>

                        <form action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" method="POST" class="login-form">
                            <div class="form-group mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
                            </div>

                            <div class="form-group mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
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
                            <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($redirect_url); ?>">

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    Remember me
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mb-3">Sign In</button>

                            <div class="text-center">
                                <a href="#" class="text-decoration-none">Forgot your password?</a>
                            </div>

                            <hr class="my-4">

                            <div class="text-center">
                                <p class="mb-0">Don't have an account? <a href="register.html" class="text-decoration-none">Sign up</a></p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>



  <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>

</body>
</html>
