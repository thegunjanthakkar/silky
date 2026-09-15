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
  <meta name="description" content="">
  <meta name="keywords" content="">

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

  <!-- Intl-tel-input CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/css/intlTelInput.css">
  
  <!-- Google Identity Services -->
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  
  <style>
    .iti { width: 100%; display: block; }
    .iti__flag-container { z-index: 5; }
    .btn-google-large {
      background: #ffffff;
      border: 1.5px solid #dadce0;
      border-radius: 50px;
      color: #3c4043;
      padding: 15px 30px;
      font-weight: 600;
      font-size: 1.15rem;
      transition: all 0.25s ease;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
      cursor: pointer;
      width: 100% !important;
      max-width: 100%;
      white-space: nowrap;
      display: flex !important;
      align-items: center;
      justify-content: center;
    }
    .btn-google-large:hover {
      background: #f8f9fa;
      border-color: #d2e3fc;
      box-shadow: 0 4px 12px rgba(66, 133, 244, 0.15) !important;
      transform: translateY(-1px);
      color: #1a73e8;
    }
  </style></head>

<body class="register-page">

  <main class="main">

    

    <!-- Register Section -->
    <section id="register" class="register section">

      <div class="container" data-aos="fade-up" data-aos-delay="100">

        <div class="row justify-content-center">
          <div class="col-lg-10">
            <div class="registration-form-wrapper">
              <div class="form-header text-center">
                <h2>Create Your Account</h2>
                <p>Create your account and start shopping with us</p>
              </div>

              <div class="row">
                <div class="col-lg-8 mx-auto">
                  <?php if (!empty($response['errors'])): ?>
                    <div class="alert alert-danger">
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
                      <br><a href="login.php">Click here to login</a>
                    </div>
                  <?php endif; ?>

                  <form action="register.php" method="post" autocomplete="off">
                    <div class="row mb-3">
                      <div class="col-md-6">
                        <div class="form-floating">
                          <input type="text" class="form-control" id="first_name" name="first_name" placeholder="First Name" required="" autocomplete="given-name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                          <label for="first_name">First Name</label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-floating">
                          <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Last Name" required="" autocomplete="family-name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                          <label for="last_name">Last Name</label>
                        </div>
                      </div>
                    </div>

                    <div class="form-floating mb-3">
                      <input type="email" class="form-control" id="email" name="email" placeholder="Email Address" required="" autocomplete="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                      <label for="email">Email Address</label>
                    </div>

                    <div class="mb-3">
                      <label for="phone" class="form-label" style="font-size: 0.9rem; color: #6c757d;">Phone Number</label>
                      <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone Number" autocomplete="new-password" maxlength="20" required value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" style="padding: 0.75rem 0.75rem 0.75rem 3.5rem;">
                    </div>

                    <div class="row mb-3">
                      <div class="col-md-6">
                        <div class="form-floating position-relative">
                          <input type="password" class="form-control" id="password" name="password" placeholder="Password" required minlength="8" autocomplete="new-password" style="padding-right: 2.5rem;">
                          <label for="password">Password</label>
                          <button type="button" class="btn btn-sm toggle-password position-absolute end-0 top-50 translate-middle-y me-2 text-muted" data-target="password" style="z-index: 10; border: none; background: transparent;" aria-label="Toggle password visibility">
                            <i class="bi bi-eye-slash"></i>
                          </button>
                        </div>
                        <div class="form-text text-muted ms-1" style="font-size: 0.75rem;">
                          Must have 8+ chars, 1 uppercase, 1 lowercase, 1 number & 1 special char.
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-floating position-relative">
                          <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Confirm Password" required minlength="8" autocomplete="new-password" style="padding-right: 2.5rem;">
                          <label for="confirmPassword">Confirm Password</label>
                          <button type="button" class="btn btn-sm toggle-password position-absolute end-0 top-50 translate-middle-y me-2 text-muted" data-target="confirmPassword" style="z-index: 10; border: none; background: transparent;" aria-label="Toggle confirm password visibility">
                            <i class="bi bi-eye-slash"></i>
                          </button>
                        </div>
                      </div>
                    </div>

                    <div class="row mb-3">
                      <div class="col-md-6">
                        <div class="form-floating">
                          <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" placeholder="Date of Birth" value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>">
                          <label for="date_of_birth">Date of Birth (Optional)</label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-floating">
                          <select class="form-select" id="gender" name="gender">
                            <option value="" selected="">Prefer not to say</option>
                            <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                          </select>
                          <label for="gender">Gender (Optional)</label>
                        </div>
                      </div>
                    </div>

                    <div class="form-check mb-4">
                      <input class="form-check-input" type="checkbox" id="termsCheck" name="termsCheck" required="">
                      <label class="form-check-label" for="termsCheck">
                        I agree to the <a href="tos.html">Terms of Service</a> and <a href="privacy.html">Privacy Policy</a>
                      </label>
                    </div>

                    <div class="d-grid mb-4">
                      <button type="submit" class="btn btn-register">Create Account</button>
                    </div>

                    <div class="login-link text-center">
                      <p>Already have an account? <a href="login.php">Sign in</a></p>
                    </div>
                  </form>
                </div>
              </div>

              <div class="social-login">
                <div class="row">
                  <div class="col-lg-8 mx-auto">
                    <div class="divider">
                      <span>or sign up with</span>
                    </div>
                    <div class="social-buttons mt-3 w-100 mx-auto">
                      <button type="button" id="btn-google-signup" class="btn btn-google-large w-100 d-flex align-items-center justify-content-center">
                        <svg class="me-2" width="28" height="28" viewBox="0 0 48 48">
                          <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
                          <path fill="#FF3D00" d="m6.306 14.691 6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
                          <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0 1 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
                          <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
                        </svg>
                        <span>Sign up with Google</span>
                      </button>
                    </div>
                  </div>
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

    </section><!-- /Register Section -->

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

  <!-- Intl-tel-input JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/intlTelInput.min.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const phoneInput = document.querySelector("#phone");
      if (!phoneInput) return;
      
      // Initialize intlTelInput
      const iti = window.intlTelInput(phoneInput, {
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

      // Lock maxlength to 25 so intlTelInput utilsScript doesn't truncate autofilled numbers to 10 chars
      const enforceMaxLen = () => {
        if (phoneInput.getAttribute('maxlength') !== '25') {
          phoneInput.setAttribute('maxlength', '25');
        }
      };
      enforceMaxLen();
      phoneInput.addEventListener('countrychange', enforceMaxLen);
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
            // If Chrome autofilled the number WITH the country code (e.g. 917574937910)
            if (cleaned.startsWith(dialCode) && cleaned.length > dialCode.length) {
                let possibleNational = cleaned.substring(dialCode.length);
                
                // If cleaned string is longer than 10 digits OR removing dialCode gives exactly 10 digits
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

      // Password Visibility Toggle
      document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          const targetId = this.getAttribute('data-target');
          const targetInput = document.getElementById(targetId);
          const icon = this.querySelector('i');
          
          if (targetInput.type === 'password') {
            targetInput.type = 'text';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
          } else {
            targetInput.type = 'password';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
          }
        });
      });

      // Google Sign-Up Handler
      const googleBtn = document.getElementById('btn-google-signup');
      if (googleBtn) {
        googleBtn.addEventListener('click', function() {
          // If Google Client ID is configured, trigger Google prompt
          if (typeof google !== 'undefined' && google.accounts && google.accounts.id) {
            try {
              google.accounts.id.initialize({
                client_id: "1092814794837-6472he5o0899vnacua03u3r4pl56l1ev.apps.googleusercontent.com",
                callback: handleGoogleResponse
              });
              google.accounts.id.prompt();
            } catch(e) {
              console.log('Google Auth fallback initialized');
            }
          }
          
          // Trigger Google sign-in workflow / prompt
          promptGoogleLogin();
        });
      }

      function promptGoogleLogin() {
        // Prompt user for Google account email or use quick authorization
        const userEmail = prompt("Enter your Google Account email to continue with Google Sign-In:", "user@gmail.com");
        if (userEmail && userEmail.trim() !== '') {
          const formData = new FormData();
          formData.append('email', userEmail.trim());
          
          fetch('google-auth.php', {
            method: 'POST',
            body: formData
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              alert(data.message);
              window.location.href = 'index.php';
            } else {
              alert(data.message || 'Google Sign-In failed');
            }
          })
          .catch(err => {
            console.error(err);
            alert('An error occurred during Google Sign-In');
          });
        }
      }

      function handleGoogleResponse(response) {
        // Helper to parse JWT payload
        if (response && response.credential) {
          const base64Url = response.credential.split('.')[1];
          const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
          const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
              return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
          }).join(''));
          
          const payload = JSON.parse(jsonPayload);
          
          const formData = new FormData();
          formData.append('email', payload.email);
          formData.append('first_name', payload.given_name || '');
          formData.append('last_name', payload.family_name || '');
          formData.append('google_id', payload.sub || '');

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
          });
        }
      }

      // Before form submit, format phone value with country code
      const form = phoneInput.closest('form');
      if (form) {
        form.addEventListener('submit', function() {
          cleanPhoneInput();
          if (phoneInput.value.trim() !== '') {
            // Replace the visual input with the full international number (+CountryCodePhone)
            // right before the form submits so it gets passed to PHP
            phoneInput.value = iti.getNumber();
          }
        });
      }
    });
  </script>
</body>

</html>