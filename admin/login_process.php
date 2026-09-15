<?php
session_start();

// Disable unhandled mysqli exceptions so we can handle DB connection/query errors gracefully
mysqli_report(MYSQLI_REPORT_OFF);

require_once '../db_config.php';

// Check if DB connection exists
if (!isset($conn) || !$conn) {
    $db_err = isset($db_error) ? $db_error : (function_exists('mysqli_connect_error') ? mysqli_connect_error() : 'Unknown database error');
    $_SESSION['error'] = 'Database connection failed: ' . $db_err . '. Please check db_config.php settings on your live server.';
    header('Location: login.php');
    exit;
}

// Simple validation
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

if ($email === '' || $password === '') {
    $_SESSION['error'] = 'Email and password are required.';
    header('Location: login.php');
    exit;
}

try {
    // Check user in admin_users table
    $sql = "SELECT id, email, first_name, last_name, password, status, role_id FROM admin_users WHERE email = '" . mysqli_real_escape_string($conn, $email) . "' AND status = 'active'";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Check if user is active
            if (strtolower($user['status']) === 'active') {
                // Login successful - set ADMIN ONLY session keys
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user_id']   = $user['id'];
                $_SESSION['admin_email']     = $user['email'];
                $_SESSION['admin_first_name']= $user['first_name'];
                $_SESSION['admin_name']      = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['admin_role']      = $user['role_id'];
                
                // Legacy admin keys
                $_SESSION['logged_in']       = true;
                $_SESSION['user_role']       = $user['role_id'];
                $_SESSION['first_name']      = $user['first_name'];

                // CRITICAL FIX: Unset client user_id so admin login NEVER collides with customer storefront sessions!
                unset($_SESSION['user_id']);
                unset($_SESSION['user_email']);
                
                // Load user permissions safely
                if (file_exists('includes/permission-manager.php')) {
                    require_once 'includes/permission-manager.php';
                    if (function_exists('setUserPermissions')) {
                        @setUserPermissions($user['id']);
                    }
                }
                
                // Update last login
                $update_sql = "UPDATE admin_users SET last_login = NOW() WHERE id = '" . mysqli_real_escape_string($conn, $user['id']) . "'";
                @mysqli_query($conn, $update_sql);
                
                $_SESSION['success'] = 'Login successful! Welcome back.';
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['error'] = 'Your account is inactive. Please contact administrator.';
            }
        } else {
            $_SESSION['error'] = 'Invalid email or password.';
        }
    } else {
        $_SESSION['error'] = 'Invalid email or password.';
    }
} catch (Throwable $e) {
    $_SESSION['error'] = 'Login Error: ' . $e->getMessage();
}

// Login failed - redirect back
header('Location: login.php');
exit;