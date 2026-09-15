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
        // Handle Resend OTP
        $new_otp = sprintf("%06d", mt_rand(1, 999999));
        $new_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $update_stmt = $conn->prepare("UPDATE users SET verification_code = ?, verification_expiry = ? WHERE email = ?");
        $update_stmt->bind_param("sss", $new_otp, $new_expiry, $email);
        
        if ($update_stmt->execute()) {
            // Get user's first name
            $name_stmt = $conn->prepare("SELECT first_name FROM users WHERE email = ?");
            $name_stmt->bind_param("s", $email);
            $name_stmt->execute();
            $name_result = $name_stmt->get_result();
            $fname = "User";
            if ($name_result->num_rows > 0) {
                $fname = $name_result->fetch_assoc()['first_name'];
            }
            $name_stmt->close();
            
            // Send email
            require_once 'includes/email-functions.php';
            sendVerificationEmail($conn, $email, $fname, $new_otp);
            
            $response['success'] = true;
            $response['message'] = 'A new verification code has been sent to your email.';
        } else {
            $response['errors'][] = 'Failed to generate a new verification code.';
        }
        $update_stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Verify Email - Silky Saree</title>

  <!-- Favicons -->
  <link rel="shortcut icon" href="./assets/img/silky-jpg.jpg" type="image/x-icon">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="assets/vendor/drift-zoom/drift-basic.css" rel="stylesheet">

  <!-- Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">

  <style>
    .otp-input {
      letter-spacing: 0.5rem;
      font-size: 1.5rem;
      text-align: center;
      font-weight: bold;
    }
    .btn-verify {
      background-color: #4CAF50;
      color: white;
      border-radius: 50px;
      padding: 12px;
      font-weight: 600;
      transition: all 0.3s;
    }
    .btn-verify:hover {
      background-color: #43a047;
      color: white;
    }
    .resend-btn {
      background: none;
      border: none;
      color: #0d6efd;
      text-decoration: underline;
      padding: 0;
      cursor: pointer;
    }
  </style>
</head>

<body class="register-page">

  <main class="main">

    <!-- Verify Section -->
    <section id="verify" class="register section">
      <div class="container" data-aos="fade-up" data-aos-delay="100">
        <div class="row justify-content-center">
          <div class="col-lg-10">
            <div class="registration-form-wrapper">
              <div class="form-header text-center">
                <h2>Verify Your Email</h2>
                <p>We've sent a 6-digit verification code to <strong><?php echo htmlspecialchars($email); ?></strong>.</p>
              </div>

              <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                  
                  <?php if (!empty($response['errors'])): ?>
                    <div class="alert alert-danger text-start">
                      <ul class="mb-0">
                        <?php foreach ($response['errors'] as $error): ?>
                          <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  <?php endif; ?>

                  <?php if ($response['success']): ?>
                    <div class="alert alert-success">
                      <?php echo htmlspecialchars($response['message']); ?>
                    </div>
                  <?php endif; ?>

                  <form action="verify.php" method="post" autocomplete="off">
                    <input type="hidden" name="action" value="verify">
                    <div class="mb-4">
                      <input type="text" name="otp" class="form-control otp-input" placeholder="••••••" maxlength="6" pattern="\d{6}" title="Please enter exactly 6 digits" required autocomplete="off">
                    </div>
                    <div class="d-grid mb-3">
                      <button type="submit" class="btn btn-verify w-100">Verify Code</button>
                    </div>
                  </form>

                  <form action="verify.php" method="post" class="mt-3">
                    <input type="hidden" name="action" value="resend">
                    <p class="mb-0">Didn't receive the code? <button type="submit" class="resend-btn">Resend OTP</button></p>
                  </form>

                </div>
              </div>

              <div class="decorative-elements">
                <div class="circle circle-1"></div>
                <div class="circle circle-2"></div>
                <div class="circle circle-3"></div>
                <div class="square square-1"></div>
                <div class="square square-2"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- Scroll Top -->
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Preloader -->
  <div id="preloader"></div>

 

  <!-- Vendor JS Files -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>
  <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
  <script src="assets/vendor/drift-zoom/Drift.min.js"></script>
  <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>

  <!-- Main JS File -->
  <script src="assets/js/main.js"></script>

</body>
</html>
