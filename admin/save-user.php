<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once '../db_config.php';
require_once './PHPmailer/src/PHPMailer.php';
require_once './PHPmailer/src/SMTP.php';
require_once './PHPmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Simple validation
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : 'create';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : '';
$role_id = isset($_POST['role']) ? trim($_POST['role']) : '';

if ($email === '' || $first_name === '' || $last_name === '' || $phone === '' || $status === '' || $role_id === '') {
    $_SESSION['error'] = 'All fields are required.';
    header('Location: add-user.php');
    exit;
}

// Generate random 8-character password
function generateRandomPassword($length = 8)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

// Check if this is an update or create
if ($action === 'update' && $id > 0) {
    // Update existing user
    $sql = "UPDATE admin_users SET 
        email = '" . mysqli_real_escape_string($conn, $email) . "',
        first_name = '" . mysqli_real_escape_string($conn, $first_name) . "',
        last_name = '" . mysqli_real_escape_string($conn, $last_name) . "',
        phone = '" . mysqli_real_escape_string($conn, $phone) . "',
        status = '" . mysqli_real_escape_string($conn, $status) . "',
        role_id = '" . mysqli_real_escape_string($conn, $role_id) . "'
        WHERE id = " . $id;
        
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = 'User updated successfully.';
        header('Location: users.php');
        exit;
    } else {
        $_SESSION['error'] = 'Error updating user: ' . mysqli_error($conn);
        header('Location: edit-user.php?id=' . $id);
        exit;
    }
} else {
    // Create new user
    $random_password = generateRandomPassword(8);
    $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);

    // Insert user
    $sql = "INSERT INTO admin_users (email, first_name, last_name, phone, status, role_id, password, created_at) VALUES ('" .
        mysqli_real_escape_string($conn, $email) . "', '" .
        mysqli_real_escape_string($conn, $first_name) . "', '" .
        mysqli_real_escape_string($conn, $last_name) . "', '" .
        mysqli_real_escape_string($conn, $phone) . "', '" .
        mysqli_real_escape_string($conn, $status) . "', '" .
        mysqli_real_escape_string($conn, $role_id) . "', '" .
        mysqli_real_escape_string($conn, $hashed_password) . "', NOW())";
        
    if (mysqli_query($conn, $sql)) {
        // Send password via email
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'mail.coida.in';
            $mail->SMTPAuth = true;
            $mail->Username = 'no-reply@coida.in';
            $mail->Password = '12@Coida';
            $mail->SMTPSecure = 'ssl';
            $mail->Port = 465;
            $mail->SMTPKeepAlive = true;
            $mail->CharSet = 'UTF-8';

            // Recipients
            $mail->setFrom('no-reply@coida.in', 'Coida Technologies');
            $mail->addAddress($email, $first_name . ' ' . $last_name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Your Account Login Details - Silky Admin';

            // Logo URL (hosted to avoid Gmail attachment chip)
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
                        <h1 style="margin: 0; font-size: 22px;">Welcome to Silky Admin</h1>
                    </div>
                    <div class="content">
                        <h2>Hello ' . htmlspecialchars($first_name) . ',</h2>
                        <p>Your admin account has been created successfully. Below are your login credentials:</p>
                        
                        <div class="credentials">
                            <p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
                            <p><strong>Password:</strong> <span class="password">' . htmlspecialchars($random_password) . '</span></p>
                        </div>
                        
                        <p>You can login at: <a href="https://silkysaree.in/admin">Admin Login</a></p>
                    </div>
                    <div class="footer">
                        <p>This is an automated email. Please do not reply.</p>
                        <p>&copy; ' . date("Y") . ' Silky</p>
                    </div>
                </div>
            </body>
            </html>';

            $mail->send();
            $_SESSION['success'] = 'User created successfully. Login credentials sent to email.';
        } catch (Exception $e) {
            $_SESSION['success'] = 'User created successfully, but email could not be sent. Password: ' . $random_password;
        }

        header('Location: users.php');
        exit;
    } else {
        $_SESSION['error'] = 'Error creating user: ' . mysqli_error($conn);
        header('Location: add-user.php');
        exit;
    }
}
