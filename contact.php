<?php
session_start();
require_once 'db_config.php';

// Fetch general settings
$settings_query = "SELECT setting_key, setting_value FROM general_settings";
$settings_result = mysqli_query($conn, $settings_query);
$settings = [];
if ($settings_result) {
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
$business_address = $settings['business_address'] ?? "123 Silky Avenue, Textile Hub\nNew Delhi, ND 110001";
$business_phone = $settings['business_phone'] ?? '+91 98765 43210';
$business_email = $settings['business_email'] ?? 'support@silkysaree.com';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Contact Us - Silky Saree</title>
  <meta name="description" content="Contact Silky Saree for any inquiries or support.">

  <!-- Favicon -->
  <link href="assets/img/favicon/favicon.ico" rel="icon" type="image/x-icon">
  <link href="assets/img/favicon/favicon-32x32.png" rel="icon" type="image/png" sizes="32x32">
  <link href="assets/img/favicon/favicon-16x16.png" rel="icon" type="image/png" sizes="16x16">
  <link href="assets/img/favicon/apple-touch-icon.png" rel="apple-touch-icon" sizes="180x180">
  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">

  <!-- Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">

  <!-- Intl-tel-input CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/css/intlTelInput.css">
  <style>
    .iti { width: 100%; }
    .iti__flag-container { z-index: 5; }
  </style>

  <!-- Google reCAPTCHA API -->
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>

  <style>
    .contact-header {
      background-color: #f8f9fc;
      padding: 60px 0;
      text-align: center;
      margin-bottom: 50px;
    }
    .contact-header h1 {
      font-weight: 700;
      color: #333;
    }
    .contact-info-card {
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.05);
      text-align: center;
      height: 100%;
      transition: transform 0.3s ease;
    }
    .contact-info-card:hover {
      transform: translateY(-5px);
    }
    .contact-info-card i {
      font-size: 32px;
      color: var(--heading-color, #0e2187);
      margin-bottom: 15px;
    }
    .contact-info-card h4 {
      font-weight: 600;
      margin-bottom: 15px;
      font-size: 1.1rem;
    }
    .contact-info-card p {
      color: #666;
      margin-bottom: 0;
    }
    .contact-form-wrapper {
      background: #fff;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    }
    .contact-form .form-control {
      padding: 12px 15px;
      border-radius: 6px;
      border: 1px solid #ddd;
    }
    .contact-form .form-control:focus {
      border-color: var(--heading-color, #0e2187);
      box-shadow: 0 0 0 0.2rem rgba(14, 33, 135, 0.25);
    }
    .submit-btn {
      background: var(--heading-color, #0e2187);
      color: white;
      padding: 12px 30px;
      border: none;
      border-radius: 50px;
      font-weight: 500;
      transition: all 0.3s ease;
      width: 100%;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    .submit-btn:hover {
      background: #09165b;
      transform: translateY(-2px);
    }
    .map-container {
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0,0,0,0.08);
      height: 100%;
      min-height: 400px;
    }
    .map-container iframe {
      width: 100%;
      height: 100%;
      border: 0;
    }
    .g-recaptcha {
      margin-bottom: 20px;
    }
  </style>
</head>

<body class="contact-page">

  <header id="header" class="header sticky-top">
    <?php include './topbar.php'; ?>
    <?php include './main-header.php'; ?>
  </header>

  <main class="main">

    <!-- Contact Header -->
    <section class="contact-header" data-aos="fade-up">
      <div class="container">
        <h1>Contact Us</h1>
        <p>We'd love to hear from you. Get in touch with us for any inquiries.</p>
        <nav class="breadcrumbs d-flex justify-content-center mt-3">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active">Contact</li>
          </ol>
        </nav>
      </div>
    </section>

    <section class="contact-section py-5">
      <div class="container">
        
        <div class="row g-5 mb-5">
          <!-- Contact Form -->
          <div class="col-lg-6" data-aos="fade-right" data-aos-delay="200">
            <div class="contact-form-wrapper">
              <h3 class="mb-4">Send us a Message</h3>
              
              <div id="form-message" class="alert d-none"></div>

              <form id="contactForm" class="contact-form" autocomplete="off">
                <div class="row g-3">
                  <div class="col-md-6">
                    <input type="text" class="form-control" name="name" id="name" placeholder="Your Name" required>
                  </div>
                  <div class="col-md-6">
                    <input type="email" class="form-control" name="email" id="email" placeholder="Your Email" required>
                  </div>
                  <div class="col-12">
                    <input type="tel" class="form-control" name="phone" id="phone" placeholder="Phone Number" autocomplete="new-password" maxlength="20" required>
                  </div>
                  <div class="col-12">
                    <input type="text" class="form-control" name="subject" id="subject" placeholder="Subject" required>
                  </div>
                  <div class="col-12">
                    <textarea class="form-control" name="message" id="message" rows="5" placeholder="Message" maxlength="1000" required></textarea>
                  </div>
                  <div class="col-12">
                    <!-- Google reCAPTCHA widget -->
                    <div class="g-recaptcha" data-sitekey="6LcX6aYtAAAAAA57TOKw-WU3UPuZYe39l0uAhuIv"></div>
                  </div>
                  <div class="col-12">
                    <button type="submit" class="submit-btn" id="submitBtn">
                      <span>Send Message</span>
                      <div class="spinner-border spinner-border-sm text-light ms-2 d-none" role="status" id="submitSpinner"></div>
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>

          <!-- Map -->
          <div class="col-lg-6" data-aos="fade-left" data-aos-delay="300">
            <div class="map-container">
              <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3671.2874221536995!2d72.5616584!3d23.049921899999994!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x395e8486019177bf%3A0xbcdf02991ac77623!2sSilky!5e0!3m2!1sen!2sin!4v1788429309592!5m2!1sen!2sin" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
          </div>
        </div>

        <!-- Info Cards -->
        <div class="row g-4" data-aos="fade-up" data-aos-delay="100">
          <div class="col-lg-4 col-md-6">
            <div class="contact-info-card">
              <i class="bi bi-geo-alt"></i>
              <h4>Our Location</h4>
              <p><?php echo nl2br(htmlspecialchars($business_address)); ?></p>
            </div>
          </div>
          <div class="col-lg-4 col-md-6">
            <div class="contact-info-card">
              <i class="bi bi-telephone"></i>
              <h4>Call Us</h4>
              <p><?php echo htmlspecialchars($business_phone); ?></p>
            </div>
          </div>
          <div class="col-lg-4 col-md-6">
            <div class="contact-info-card">
              <i class="bi bi-envelope"></i>
              <h4>Email Us</h4>
              <p><?php echo htmlspecialchars($business_email); ?></p>
            </div>
          </div>
        </div>

      </div>
    </section>

  </main>

  <?php include './footer.php'; ?>

  <!-- Mobile Bottom Navigation -->
  <?php include 'mobile-bottom-nav.php'; ?>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>

  <!-- Intl-tel-input JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/intlTelInput.min.js"></script>

  <!-- Main JS File -->
  <script src="assets/js/main.js"></script>

  <!-- Contact Form Logic -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize AOS
      if(typeof AOS !== 'undefined') {
        AOS.init({
          duration: 800,
          once: true
        });
      }

      // Initialize intl-tel-input
      const phoneInput = document.querySelector("#phone");
      let iti = null;
      if (phoneInput) {
        iti = window.intlTelInput(phoneInput, {
          initialCountry: "auto",
          geoIpLookup: function(callback) {
            fetch("https://ipapi.co/json")
              .then(function(res) { return res.json(); })
              .then(function(data) { callback(data.country_code); })
              .catch(function() { callback("in"); });
          },
          utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/utils.js",
          separateDialCode: true
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
      }

      const form = document.getElementById('contactForm');
      const submitBtn = document.getElementById('submitBtn');
      const submitSpinner = document.getElementById('submitSpinner');
      const formMessage = document.getElementById('formMessage');
      const formMsgAlert = document.getElementById('form-message');

      form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Frontend Validations
        const nameVal = document.getElementById('name').value.trim();
        const emailVal = document.getElementById('email').value.trim();
        const messageVal = document.getElementById('message').value.trim();
        
        if (nameVal.length < 2 || nameVal.length > 100) {
            showMessage('error', 'Name must be between 2 and 100 characters.');
            return;
        }
        if (!/^[a-zA-Z\s]+$/.test(nameVal)) {
            showMessage('error', 'Name should only contain letters and spaces.');
            return;
        }
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailVal)) {
            showMessage('error', 'Please enter a valid email address.');
            return;
        }

        if (iti) {
            if (!iti.isValidNumber()) {
                showMessage('error', 'Please enter a valid phone number.');
                return;
            }
        }

        if (messageVal.length > 1000) {
            showMessage('error', 'Message is too long. Max 1000 characters allowed.');
            return;
        }

        const recaptchaResponse = grecaptcha.getResponse();
        
        if(recaptchaResponse.length === 0) {
            showMessage('error', 'Please complete the reCAPTCHA verification.');
            return;
        }

        const formData = new FormData(form);
        // Add recaptcha response to form data
        formData.append('g-recaptcha-response', recaptchaResponse);
        
        // Add raw phone number if valid
        if (iti) {
            formData.set('phone', phoneInput.value.replace(/\D/g, ''));
        }

        // Disable button and show spinner
        submitBtn.disabled = true;
        submitSpinner.classList.remove('d-none');
        
        fetch('contact-process.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showMessage('success', data.message);
            form.reset();
            grecaptcha.reset();
          } else {
            showMessage('error', data.message || 'Something went wrong. Please try again.');
            grecaptcha.reset();
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showMessage('error', 'Network error occurred. Please try again later.');
          grecaptcha.reset();
        })
        .finally(() => {
          submitBtn.disabled = false;
          submitSpinner.classList.add('d-none');
        });
      });

      function showMessage(type, text) {
        formMsgAlert.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-danger');
        formMsgAlert.innerHTML = text;
        formMsgAlert.classList.remove('d-none');
        
        // Hide after 5 seconds
        setTimeout(() => {
          formMsgAlert.classList.add('d-none');
        }, 5000);
      }
    });
  </script>

</body>
</html>
