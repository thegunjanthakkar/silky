<?php
session_start();
date_default_timezone_set('Asia/Kolkata'); // Set to Indian Standard Time (IST)
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'includes/permission-manager.php';
checkPageAccess();
require_once '../db_config.php';

// Create email_settings table if not exists
$create_table = "CREATE TABLE IF NOT EXISTS email_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    smtp_host VARCHAR(255) NOT NULL DEFAULT 'smtp.gmail.com',
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_encryption VARCHAR(10) NOT NULL DEFAULT 'tls',
    smtp_auth TINYINT(1) DEFAULT 1,
    sender_email VARCHAR(255) NOT NULL,
    sender_password VARCHAR(255) NOT NULL,
    sender_name VARCHAR(100) NOT NULL DEFAULT 'Silky Saree',
    reply_to_email VARCHAR(255) DEFAULT NULL,
    reply_to_name VARCHAR(100) DEFAULT NULL,
    timeout INT DEFAULT 30,
    charset VARCHAR(20) DEFAULT 'utf-8',
    is_enabled TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT DEFAULT NULL
)";
mysqli_query($conn, $create_table);

// Initialize default settings if table is empty
$check_sql = "SELECT COUNT(*) as count FROM email_settings";
$check_result = mysqli_query($conn, $check_sql);
$row = mysqli_fetch_assoc($check_result);

if ($row['count'] == 0) {
    $insert_sql = "INSERT INTO email_settings (sender_email, sender_password, sender_name) 
                   VALUES ('', '', 'Silky Saree')";
    mysqli_query($conn, $insert_sql);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_email_settings') {
        $smtp_host = mysqli_real_escape_string($conn, $_POST['smtp_host']);
        $smtp_port = intval($_POST['smtp_port']);
        $smtp_encryption = mysqli_real_escape_string($conn, $_POST['smtp_encryption']);
        $smtp_auth = isset($_POST['smtp_auth']) ? 1 : 0;
        $sender_email = mysqli_real_escape_string($conn, $_POST['sender_email']);
        $sender_password = mysqli_real_escape_string($conn, $_POST['sender_password']);
        $sender_name = mysqli_real_escape_string($conn, $_POST['sender_name']);
        $reply_to_email = mysqli_real_escape_string($conn, $_POST['reply_to_email']);
        $reply_to_name = mysqli_real_escape_string($conn, $_POST['reply_to_name']);
        $timeout = intval($_POST['timeout']);
        $charset = mysqli_real_escape_string($conn, $_POST['charset']);
        $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
        
        $update_sql = "UPDATE email_settings SET 
                       smtp_host = ?, 
                       smtp_port = ?, 
                       smtp_encryption = ?, 
                       smtp_auth = ?,
                       sender_email = ?,
                       sender_password = ?,
                       sender_name = ?,
                       reply_to_email = ?,
                       reply_to_name = ?,
                       timeout = ?,
                       charset = ?,
                       is_enabled = ?,
                       updated_by = ?
                       WHERE id = 1";
        
        $stmt = mysqli_prepare($conn, $update_sql);
        $user_id = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? null;
        mysqli_stmt_bind_param($stmt, "sisssssssisii", 
            $smtp_host, $smtp_port, $smtp_encryption, $smtp_auth,
            $sender_email, $sender_password, $sender_name,
            $reply_to_email, $reply_to_name, $timeout, $charset, $is_enabled, $user_id
        );
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Email settings updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating email settings: " . mysqli_error($conn);
        }
        
        header('Location: email-settings.php');
        exit;
    } elseif ($_POST['action'] === 'test_email') {
        // Test email functionality
        $test_email = mysqli_real_escape_string($conn, $_POST['test_email']);
        
        // Get current settings
        $settings_sql = "SELECT * FROM email_settings WHERE id = 1";
        $settings_result = mysqli_query($conn, $settings_sql);
        $settings = mysqli_fetch_assoc($settings_result);
        
        if (!$settings || !$settings['is_enabled']) {
            $_SESSION['error'] = "Email service is not enabled!";
        } elseif (empty($settings['sender_email'])) {
            $_SESSION['error'] = "Sender email is not configured!";
        } elseif (empty($settings['sender_password'])) {
            $_SESSION['error'] = "Sender password is not configured!";
        } elseif (empty($settings['smtp_host'])) {
            $_SESSION['error'] = "SMTP host is not configured!";
        } else {
            // All required settings are configured - Send actual email
            require_once 'PHPmailer/src/Exception.php';
            require_once 'PHPmailer/src/PHPMailer.php';
            require_once 'PHPmailer/src/SMTP.php';
            
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
                
                // Disable SSL verification for shared hosting with certificate mismatch
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                // Recipients
                $mail->setFrom($settings['sender_email'], $settings['sender_name']);
                $mail->addAddress($test_email);
                
                // Reply-To
                if (!empty($settings['reply_to_email'])) {
                    $mail->addReplyTo($settings['reply_to_email'], $settings['reply_to_name'] ?: $settings['sender_name']);
                }
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Test Email from Silky Saree - ' . date('d/m/Y h:i:s A');
                $mail->Body = '
                    <html>
                    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                        <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                            <h2 style="color: #0e2187; border-bottom: 2px solid #0e2187; padding-bottom: 10px;">Test Email</h2>
                            <p>This is a test email from <strong>Silky Saree</strong> email system.</p>
                            <p>If you received this email, your SMTP configuration is working correctly!</p>
                            <div style="background-color: #f8f9fa; padding: 15px; border-left: 4px solid #0e2187; margin: 20px 0;">
                                <h3 style="margin-top: 0;">Configuration Details:</h3>
                                <ul style="list-style: none; padding: 0;">
                                    <li><strong>SMTP Host:</strong> ' . htmlspecialchars($settings['smtp_host']) . '</li>
                                    <li><strong>SMTP Port:</strong> ' . htmlspecialchars($settings['smtp_port']) . '</li>
                                    <li><strong>Encryption:</strong> ' . strtoupper(htmlspecialchars($settings['smtp_encryption'])) . '</li>
                                    <li><strong>Sender:</strong> ' . htmlspecialchars($settings['sender_email']) . '</li>
                                    <li><strong>Sent At:</strong> ' . date('d/m/Y h:i:s A') . '</li>
                                </ul>
                            </div>
                            <p style="color: #28a745; font-weight: bold;">✓ Email service is working properly!</p>
                            <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
                            <p style="font-size: 12px; color: #666;">
                                This is an automated test email from Silky Saree Admin Panel.<br>
                                Please do not reply to this email.
                            </p>
                        </div>
                    </body>
                    </html>
                ';
                $mail->AltBody = "This is a test email from Silky Saree email system.\n\nIf you received this email, your SMTP configuration is working correctly!\n\nSMTP Host: {$settings['smtp_host']}\nSMTP Port: {$settings['smtp_port']}\nEncryption: " . strtoupper($settings['smtp_encryption']) . "\nSender: {$settings['sender_email']}\nSent At: " . date('d/m/Y h:i:s A');
                
                $mail->send();
                $_SESSION['success'] = "Test email sent successfully to " . htmlspecialchars($test_email) . "! Please check your inbox.";
                
            } catch (\PHPMailer\PHPMailer\Exception $e) {
                $_SESSION['error'] = "Failed to send test email. Error: {$mail->ErrorInfo}";
            }
        }
        
        header('Location: email-settings.php');
        exit;
    }
}

// Fetch email settings
$sql = "SELECT * FROM email_settings WHERE id = 1";
$result = mysqli_query($conn, $sql);
$settings = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light" id="html-root">

<head>
    <meta charset="utf-8" />
    <title>Email Settings | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta content="Email SMTP Configuration" name="description" />
    <meta content="" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <!-- App favicon -->
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">

    <!-- App css -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />

    <style>
        .settings-card {
            transition: all 0.3s ease;
            border: none;
            margin-bottom: 1.5rem;
        }
        .settings-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(255,255,255,0.1);
        }
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 38px;
            z-index: 10;
        }
        .info-text {
            font-size: 0.875rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }
        .alert-info {
            background-color: rgba(23, 162, 184, 0.15);
            border-color: rgba(23, 162, 184, 0.3);
            border: 1px solid rgba(23, 162, 184, 0.3);
        }
        .alert-info small {
            opacity: 0.9;
        }
    </style>

    <!-- Dark Mode State Check Script -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('silky_admin_theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
            }
        })();
    </script>
</head>

<body>
    <!-- Top Bar Start -->
    <?php include 'topbar.php'; ?>
    <!-- Top Bar End -->

    <!-- leftbar-tab-menu -->
    <?php include 'leftbar.php'; ?>
    <!-- end leftbar-tab-menu-->

    <div class="page-wrapper">
        <!-- Page Content-->
        <div class="page-content">
            <div class="container-fluid">
                <!-- Page Title -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                            <h4 class="page-title">Email Settings</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="index.php">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="#">Settings</a></li>
                                    <li class="breadcrumb-item active">Email Settings</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php 
                            echo $_SESSION['success'];
                            unset($_SESSION['success']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Email Settings Form -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card settings-card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">
                                    <i class="fas fa-envelope me-2"></i>SMTP Configuration
                                </h4>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_email_settings">

                                    <!-- Enable Email Service -->
                                    <div class="mb-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" <?php echo ($settings['is_enabled'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_enabled">
                                                <strong>Enable Email Service</strong>
                                            </label>
                                        </div>
                                        <small class="info-text">Turn this on to enable email notifications</small>
                                    </div>

                                    <hr class="my-4">

                                    <!-- SMTP Server Settings -->
                                    <div class="section-title">
                                        <i class="fas fa-server me-2"></i>SMTP Server Settings
                                    </div>

                                    <div class="row">
                                        <div class="col-md-8 mb-3">
                                            <label for="smtp_host" class="form-label">SMTP Host <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                                   value="<?php echo htmlspecialchars($settings['smtp_host'] ?? 'smtp.gmail.com'); ?>" required>
                                            <small class="info-text">Example: smtp.gmail.com, smtp.office365.com</small>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="smtp_port" class="form-label">SMTP Port <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                                   value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>" required>
                                            <small class="info-text">Usually 587 or 465</small>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_encryption" class="form-label">Encryption Type <span class="text-danger">*</span></label>
                                            <select class="form-select" id="smtp_encryption" name="smtp_encryption" required>
                                                <option value="tls" <?php echo (($settings['smtp_encryption'] ?? 'tls') === 'tls') ? 'selected' : ''; ?>>TLS (Port 587)</option>
                                                <option value="ssl" <?php echo (($settings['smtp_encryption'] ?? '') === 'ssl') ? 'selected' : ''; ?>>SSL (Port 465)</option>
                                                <option value="none" <?php echo (($settings['smtp_encryption'] ?? '') === 'none') ? 'selected' : ''; ?>>None</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">SMTP Authentication</label>
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" id="smtp_auth" name="smtp_auth" <?php echo ($settings['smtp_auth'] ?? 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="smtp_auth">Require authentication</label>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-4">

                                    <!-- Sender Details -->
                                    <div class="section-title">
                                        <i class="fas fa-user me-2"></i>Sender Details
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="sender_email" class="form-label">Sender Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="sender_email" name="sender_email" 
                                                   value="<?php echo htmlspecialchars($settings['sender_email'] ?? ''); ?>" required>
                                            <small class="info-text">The email address that will send emails</small>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="sender_name" class="form-label">Sender Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="sender_name" name="sender_name" 
                                                   value="<?php echo htmlspecialchars($settings['sender_name'] ?? 'Silky Saree'); ?>" required>
                                            <small class="info-text">The name displayed as sender</small>
                                        </div>
                                    </div>

                                    <div class="mb-3 position-relative">
                                        <label for="sender_password" class="form-label">Sender Email Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="sender_password" name="sender_password" 
                                               value="<?php echo htmlspecialchars($settings['sender_password'] ?? ''); ?>" required>
                                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                                        <small class="info-text">
                                            For Gmail: Use App Password instead of regular password 
                                            <a href="https://support.google.com/accounts/answer/185833" target="_blank">(How to create?)</a>
                                        </small>
                                    </div>

                                    <hr class="my-4">

                                    <!-- Reply-To Settings -->
                                    <div class="section-title">
                                        <i class="fas fa-reply me-2"></i>Reply-To Settings (Optional)
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="reply_to_email" class="form-label">Reply-To Email</label>
                                            <input type="email" class="form-control" id="reply_to_email" name="reply_to_email" 
                                                   value="<?php echo htmlspecialchars($settings['reply_to_email'] ?? ''); ?>">
                                            <small class="info-text">Leave empty to use sender email</small>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="reply_to_name" class="form-label">Reply-To Name</label>
                                            <input type="text" class="form-control" id="reply_to_name" name="reply_to_name" 
                                                   value="<?php echo htmlspecialchars($settings['reply_to_name'] ?? ''); ?>">
                                            <small class="info-text">Leave empty to use sender name</small>
                                        </div>
                                    </div>

                                    <hr class="my-4">

                                    <!-- Advanced Settings -->
                                    <div class="section-title">
                                        <i class="fas fa-cog me-2"></i>Advanced Settings
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="timeout" class="form-label">Connection Timeout (seconds)</label>
                                            <input type="number" class="form-control" id="timeout" name="timeout" 
                                                   value="<?php echo htmlspecialchars($settings['timeout'] ?? '30'); ?>" min="10" max="120">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="charset" class="form-label">Character Set</label>
                                            <select class="form-select" id="charset" name="charset">
                                                <option value="utf-8" <?php echo (($settings['charset'] ?? 'utf-8') === 'utf-8') ? 'selected' : ''; ?>>UTF-8</option>
                                                <option value="iso-8859-1" <?php echo (($settings['charset'] ?? '') === 'iso-8859-1') ? 'selected' : ''; ?>>ISO-8859-1</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save Settings
                                        </button>
                                        <a href="index.php" class="btn btn-secondary ms-2">
                                            <i class="fas fa-times me-2"></i>Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Test Email Card -->
                    <div class="col-lg-4">
                        <div class="card settings-card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">
                                    <i class="fas fa-paper-plane me-2"></i>Test Email
                                </h4>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Send a test email to verify your SMTP configuration is working correctly.</p>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="test_email">
                                    
                                    <div class="mb-3">
                                        <label for="test_email" class="form-label">Test Email Address</label>
                                        <input type="email" class="form-control" id="test_email" name="test_email" 
                                               placeholder="test@example.com" required>
                                    </div>

                                    <?php 
                                    $can_send_test = ($settings['is_enabled'] ?? 0) && 
                                                     !empty($settings['sender_email']) && 
                                                     !empty($settings['sender_password']) && 
                                                     !empty($settings['smtp_host']);
                                    ?>

                                    <button type="submit" class="btn btn-success w-100" <?php echo !$can_send_test ? 'disabled' : ''; ?>>
                                        <i class="fas fa-paper-plane me-2"></i>Send Test Email
                                    </button>

                                    <?php if (!($settings['is_enabled'] ?? 0)): ?>
                                    <small class="text-danger d-block mt-2">
                                        <i class="fas fa-info-circle me-1"></i>Enable email service first
                                    </small>
                                    <?php elseif (empty($settings['sender_email']) || empty($settings['sender_password']) || empty($settings['smtp_host'])): ?>
                                    <small class="text-danger d-block mt-2">
                                        <i class="fas fa-info-circle me-1"></i>Please configure sender email, password, and SMTP host first
                                    </small>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>

                        <!-- Quick Setup Guide -->
                        <div class="card settings-card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">
                                    <i class="fas fa-question-circle me-2"></i>Quick Setup Guide
                                </h4>
                            </div>
                            <div class="card-body">
                                <h6 class="fw-bold">Gmail Setup:</h6>
                                <ul class="small">
                                    <li>SMTP Host: smtp.gmail.com</li>
                                    <li>SMTP Port: 587</li>
                                    <li>Encryption: TLS</li>
                                    <li>Use App Password (not regular password)</li>
                                </ul>

                                <h6 class="fw-bold mt-3">Outlook/Office365:</h6>
                                <ul class="small">
                                    <li>SMTP Host: smtp.office365.com</li>
                                    <li>SMTP Port: 587</li>
                                    <li>Encryption: TLS</li>
                                </ul>

                                <div class="alert alert-info mt-3">
                                    <small>
                                        <i class="fas fa-lightbulb me-1"></i>
                                        <strong>Tip:</strong> For Gmail, enable "Less secure app access" or use App Passwords for better security.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- container-fluid -->

            <?php include 'footer.php'; ?>
        </div>
        <!-- end page content -->
    </div>
    <!-- end page-wrapper -->

    <!-- Javascript -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>

    <!-- Password Toggle Script -->
    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('sender_password');
            const icon = this;
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Auto-sync Encryption Type and Port
        const encryptionSelect = document.getElementById('smtp_encryption');
        const portInput = document.getElementById('smtp_port');

        // When encryption type changes, update port
        encryptionSelect.addEventListener('change', function() {
            if (this.value === 'tls') {
                portInput.value = '587';
            } else if (this.value === 'ssl') {
                portInput.value = '465';
            }
        });

        // When port changes, update encryption type
        portInput.addEventListener('change', function() {
            if (this.value === '587') {
                encryptionSelect.value = 'tls';
            } else if (this.value === '465') {
                encryptionSelect.value = 'ssl';
            }
        });

        // Also trigger on input event for real-time updates
        portInput.addEventListener('input', function() {
            if (this.value === '587') {
                encryptionSelect.value = 'tls';
            } else if (this.value === '465') {
                encryptionSelect.value = 'ssl';
            }
        });
    </script>
</body>
</html>
