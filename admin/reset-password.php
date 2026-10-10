<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'includes/permission-manager.php';
checkPageAccess();
require_once '../db_config.php';

$current_id = (int)($_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = isset($_POST['form']) ? trim((string)$_POST['form']) : 'reset';

    // --- Send the just-reset password via email (existing admin mail system) ---
    if ($form === 'send_mail') {
        $target_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($target_id <= 0) {
            $_SESSION['error'] = 'Invalid user ID.';
            header('Location: users.php');
            exit;
        }
        if ($current_id > 0 && $current_id === $target_id) {
            $_SESSION['error'] = 'Please use Change Password to update your own password.';
            header('Location: change-password.php');
            exit;
        }
        [$ok, $reason] = canResetUserPassword($current_id, $target_id);
        if (!$ok) {
            $_SESSION['error'] = $reason;
            header('Location: users.php');
            exit;
        }
        $pending = $_SESSION['pw_reset_done'] ?? null;
        if (!is_array($pending) || (int)($pending['id'] ?? 0) !== $target_id || empty($pending['password'])) {
            $_SESSION['error'] = 'Password is no longer available to send. Please reset it again.';
            header('Location: reset-password.php?id=' . $target_id);
            exit;
        }
        $plain_password = (string)$pending['password'];

        $t_res = mysqli_query($conn, "SELECT email, first_name, last_name FROM admin_users WHERE id = $target_id LIMIT 1");
        if (!$t_res || mysqli_num_rows($t_res) === 0) {
            unset($_SESSION['pw_reset_done']);
            $_SESSION['error'] = 'User not found.';
            header('Location: users.php');
            exit;
        }
        $t = mysqli_fetch_assoc($t_res);
        $to_email = (string)($t['email'] ?? '');
        $to_name = trim((string)($t['first_name'] ?? '') . ' ' . (string)($t['last_name'] ?? ''));
        if ($to_email === '') {
            unset($_SESSION['pw_reset_done']);
            $_SESSION['error'] = 'User has no email address.';
            header('Location: users.php');
            exit;
        }

        try {
            require_once './PHPmailer/src/PHPMailer.php';
            require_once './PHPmailer/src/SMTP.php';
            require_once './PHPmailer/src/Exception.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            // Same admin mail system used for new-user credentials (see save-user.php)
            $mail->isSMTP();
            $mail->Host = 'mail.coida.in';
            $mail->SMTPAuth = true;
            $mail->Username = 'no-reply@coida.in';
            $mail->Password = '12@Coida';
            $mail->SMTPSecure = 'ssl';
            $mail->Port = 465;
            $mail->SMTPKeepAlive = true;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom('no-reply@coida.in', 'Coida Technologies');
            $mail->addAddress($to_email, $to_name);

            $mail->isHTML(true);
            $mail->Subject = 'Your Password Was Reset - Silky Admin';

            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $burl = defined('BASE_URL') ? BASE_URL : '/';
            if (empty($host) || in_array($host, ['localhost', '127.0.0.1', '::1']) || strpos($host, 'localhost:') === 0) {
                $logo_src = 'https://silkysaree.in/assets/img/silky.png';
            } else {
                $logo_src = $protocol . '://' . $host . $burl . 'assets/img/silky.png';
            }

            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    .email-container { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
                    .brand-header { background-color: #ffffff; padding: 25px 20px; text-align: center; border-bottom: 1px solid #eee; }
                    .header { background-color: #0e2187; color: white; padding: 20px; text-align: center; }
                    .content { padding: 25px; background-color: #f8f9fc; }
                    .credentials { background-color: white; padding: 20px; border-radius: 8px; margin: 15px 0; border: 1px solid #e2e8f0; }
                    .password { font-size: 18px; font-weight: bold; color: #0e2187; font-family: monospace; }
                    .footer { padding: 20px; text-align: center; color: #666; font-size: 13px; background-color: #f1f5f9; }
                </style>
            </head>
            <body>
                <div class="email-container">
                    <div class="brand-header">
                        <img src="' . $logo_src . '" alt="Silky Saree" style="max-width: 160px; height: auto; display: inline-block; border: 0;">
                    </div>
                    <div class="header">
                        <h1 style="margin: 0; font-size: 22px;">Your Password Was Reset</h1>
                    </div>
                    <div class="content">
                        <h2>Hello ' . htmlspecialchars($t['first_name'] ?? 'User') . ',</h2>
                        <p>Your Silky Admin account password was reset by an administrator. Below are your new login credentials:</p>

                        <div class="credentials">
                            <p><strong>Email:</strong> ' . htmlspecialchars($to_email) . '</p>
                            <p><strong>Password:</strong> <span class="password">' . htmlspecialchars($plain_password) . '</span></p>
                        </div>

                        <p>You can login at: <a href="https://silkysaree.in/admin">Admin Login</a></p>
                        <p>If you did not request this, please contact your administrator immediately.</p>
                    </div>
                    <div class="footer">
                        <p>This is an automated email. Please do not reply.</p>
                        <p>&copy; ' . date("Y") . ' Silky Saree</p>
                    </div>
                </div>
            </body>
            </html>';

            $mail->SMTPDebug = 0;
            $mail->send();
            unset($_SESSION['pw_reset_done']);
            if (function_exists('logActivity')) {
                @logActivity('update', 'Users', 'Emailed reset password to admin user (ID: ' . $target_id . ')');
            }
            $_SESSION['success'] = 'New password sent via email to ' . $to_email . '.';
            header('Location: users.php');
            exit;
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Could not send email. Please try again.';
            header('Location: reset-password.php?id=' . $target_id . '&done=1');
            exit;
        }
    }

    // --- Reset the password ---
    $target_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $new_password = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';

    if ($target_id <= 0) {
        $_SESSION['error'] = 'Invalid user ID.';
        header('Location: users.php');
        exit;
    }
    // Self resets must go through change-password (current password verification)
    if ($current_id > 0 && $current_id === $target_id) {
        $_SESSION['error'] = 'Please use Change Password to update your own password.';
        header('Location: change-password.php');
        exit;
    }
    [$ok, $reason] = canResetUserPassword($current_id, $target_id);
    if (!$ok) {
        $_SESSION['error'] = $reason;
        header('Location: users.php');
        exit;
    }
    if ($new_password === '' || $confirm_password === '') {
        $_SESSION['error'] = 'All fields are required.';
        header('Location: reset-password.php?id=' . $target_id);
        exit;
    }
    if (strlen($new_password) < 8) {
        $_SESSION['error'] = 'New password must be at least 8 characters long.';
        header('Location: reset-password.php?id=' . $target_id);
        exit;
    }
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = 'New password and confirm password do not match.';
        header('Location: reset-password.php?id=' . $target_id);
        exit;
    }

    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $new_hash_esc = mysqli_real_escape_string($conn, $new_hash);
    if (mysqli_query($conn, "UPDATE admin_users SET password = '$new_hash_esc' WHERE id = $target_id LIMIT 1")) {
        if (function_exists('logActivity')) {
            @logActivity('update', 'Users', 'Reset password for admin user (ID: ' . $target_id . ')');
        }
        // Keep the new password server-side (short-lived) so the admin can
        // optionally email it via the "Send via Mail" button below.
        $_SESSION['pw_reset_done'] = ['id' => $target_id, 'password' => $new_password];
        $_SESSION['success'] = 'Password reset successfully.';
        header('Location: reset-password.php?id=' . $target_id . '&done=1');
        exit;
    } else {
        $_SESSION['error'] = 'Error resetting password: ' . mysqli_error($conn);
        header('Location: reset-password.php?id=' . $target_id);
        exit;
    }
}

// GET: show form
$target_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($target_id <= 0) {
    $_SESSION['error'] = 'Invalid user ID.';
    header('Location: users.php');
    exit;
}
if ($current_id > 0 && $current_id === $target_id) {
    $_SESSION['error'] = 'Please use Change Password to update your own password.';
    header('Location: change-password.php');
    exit;
}
[$ok, $reason] = canResetUserPassword($current_id, $target_id);
if (!$ok) {
    $_SESSION['error'] = $reason;
    header('Location: users.php');
    exit;
}

$t_res = mysqli_query($conn, "SELECT u.id, u.email, u.first_name, u.last_name, r.role_name FROM admin_users u LEFT JOIN admin_roles r ON u.role_id = r.id WHERE u.id = $target_id LIMIT 1");
if (!$t_res || mysqli_num_rows($t_res) === 0) {
    $_SESSION['error'] = 'User not found.';
    header('Location: users.php');
    exit;
}
$target = mysqli_fetch_assoc($t_res);

// Success state after a reset: offer to email the new password.
// The plaintext password lives only in the short-lived server-side flash.
$just_reset = (isset($_GET['done']) && (string)$_GET['done'] === '1');
if ($just_reset) {
    $pending = $_SESSION['pw_reset_done'] ?? null;
    if (!is_array($pending) || (int)($pending['id'] ?? 0) !== $target_id || empty($pending['password'])) {
        header('Location: users.php');
        exit;
    }
} elseif (isset($_SESSION['pw_reset_done'])) {
    // Clear stale pending password when viewing the form normally.
    unset($_SESSION['pw_reset_done']);
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">
<head>
    <meta charset="utf-8" />
    <title>Reset Password | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
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
    <?php include 'topbar.php'; ?>
    <?php include 'leftbar.php'; ?>

    <div class="page-wrapper">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-12">
                        <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                            <h4 class="page-title">Reset Password</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="users.php">Users</a></li>
                                    <li class="breadcrumb-item active">Reset Password</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h4 class="card-title mb-0"><i class="fas fa-key me-2"></i>Reset User Password</h4>
                                    </div>
                                    <div class="col-auto">
                                        <a href="users.php" class="btn btn-outline-secondary btn-sm"><i class="iconoir-arrow-left me-2"></i>Back to Users</a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($_SESSION['success'])): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <div class="alert alert-info py-2">
                                    <small>Resetting password for <strong><?php echo htmlspecialchars($target['email']); ?></strong>
                                    (<?php echo htmlspecialchars(trim(($target['first_name'] ?? '') . ' ' . ($target['last_name'] ?? ''))); ?>)
                                    <?php if (!empty($target['role_name'])): ?>— <span class="badge bg-primary-subtle text-primary"><?php echo htmlspecialchars($target['role_name']); ?></span><?php endif; ?></small>
                                </div>

                                <?php if (!empty($just_reset)): ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle me-2"></i>Password reset successfully for <strong><?php echo htmlspecialchars($target['email']); ?></strong>.
                                        You can now email the new password to the user via the button below.
                                    </div>
                                    <form action="reset-password.php" method="post" autocomplete="off">
                                        <input type="hidden" name="form" value="send_mail">
                                        <input type="hidden" name="id" value="<?php echo (int)$target['id']; ?>">
                                        <div class="d-flex gap-2 flex-wrap">
                                            <button type="submit" class="btn btn-primary"><i class="fas fa-envelope me-2"></i>Send via Mail</button>
                                            <a href="users.php" class="btn btn-secondary">Back to Users</a>
                                        </div>
                                    </form>
                                <?php else: ?>
                                <form action="reset-password.php" method="post" autocomplete="off">
                                    <input type="hidden" name="form" value="reset">
                                    <input type="hidden" name="id" value="<?php echo (int)$target['id']; ?>">
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                                        <small class="text-muted">Minimum 8 characters. Share it securely with the user.</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-warning"><i class="fas fa-key me-2"></i>Reset Password</button>
                                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                                    </div>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/theme-manager.js"></script>
</body>
</html>
