<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'includes/permission-manager.php';
// Gated by the 'change_password' permission (see permission-manager.php).
// Grant it per role in User Roles; backfill gives it to all existing roles.
checkPageAccess();
require_once '../db_config.php';

$current_id = (int)($_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);
if ($current_id <= 0) {
    $_SESSION['error'] = 'Session expired. Please login again.';
    header('Location: login.php');
    exit;
}

// Fetch current user for display
$me = null;
$me_res = mysqli_query($conn, "SELECT id, email, first_name, last_name FROM admin_users WHERE id = $current_id LIMIT 1");
if ($me_res && mysqli_num_rows($me_res) > 0) {
    $me = mysqli_fetch_assoc($me_res);
}
if (!$me) {
    $_SESSION['error'] = 'User not found. Please login again.';
    header('Location: logout.php');
    exit;
}

// Handle POST (self change)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = isset($_POST['current_password']) ? (string)$_POST['current_password'] : '';
    $new_password     = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $_SESSION['error'] = 'All fields are required.';
        header('Location: change-password.php');
        exit;
    }
    if (strlen($new_password) < 8) {
        $_SESSION['error'] = 'New password must be at least 8 characters long.';
        header('Location: change-password.php');
        exit;
    }
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = 'New password and confirm password do not match.';
        header('Location: change-password.php');
        exit;
    }

    // Verify current password
    $pw_res = mysqli_query($conn, "SELECT password FROM admin_users WHERE id = $current_id LIMIT 1");
    if (!$pw_res || mysqli_num_rows($pw_res) === 0) {
        $_SESSION['error'] = 'User not found.';
        header('Location: change-password.php');
        exit;
    }
    $pw_row = mysqli_fetch_assoc($pw_res);
    $hash = (string)($pw_row['password'] ?? '');
    if ($hash === '' || !password_verify($current_password, $hash)) {
        $_SESSION['error'] = 'Current password is incorrect.';
        header('Location: change-password.php');
        exit;
    }
    if (password_verify($new_password, $hash)) {
        $_SESSION['error'] = 'New password must be different from the current password.';
        header('Location: change-password.php');
        exit;
    }

    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $new_hash_esc = mysqli_real_escape_string($conn, $new_hash);
    if (mysqli_query($conn, "UPDATE admin_users SET password = '$new_hash_esc' WHERE id = $current_id LIMIT 1")) {
        if (function_exists('logActivity')) {
            @logActivity('update', 'Users', 'Changed own password (ID: ' . $current_id . ')');
        }
        $_SESSION['success'] = 'Your password has been changed successfully.';
        header('Location: change-password.php');
        exit;
    } else {
        $_SESSION['error'] = 'Error updating password: ' . mysqli_error($conn);
        header('Location: change-password.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">
<head>
    <meta charset="utf-8" />
    <title>Change Password | Silky Admin</title>
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
                            <h4 class="page-title">Change Password</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                    <li class="breadcrumb-item active">Change Password</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0"><i class="fas fa-key me-2"></i>Change Your Password</h4>
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
                                    <small>Logged in as <strong><?php echo htmlspecialchars($me['email']); ?></strong> (<?php echo htmlspecialchars(trim($me['first_name'] . ' ' . $me['last_name'])); ?>)</small>
                                </div>

                                <form action="change-password.php" method="post" autocomplete="off">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                                        <small class="text-muted">Minimum 8 characters.</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-check me-2"></i>Update Password</button>
                                        <a href="./" class="btn btn-secondary">Cancel</a>
                                    </div>
                                </form>
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
