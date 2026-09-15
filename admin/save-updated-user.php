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

// Get POST data
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : '';
$role_id = isset($_POST['role']) ? trim($_POST['role']) : '';
$action = isset($_POST['action']) ? trim($_POST['action']) : 'update';

// Get original user data to check if email was changed
$old_email = '';
if ($id > 0) {
    $check_sql = "SELECT email FROM admin_users WHERE id = " . $id;
    $check_result = mysqli_query($conn, $check_sql);
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $old_data = mysqli_fetch_assoc($check_result);
        $old_email = $old_data['email'];
    }
    // Fetch password for the user
    $pass_sql = "SELECT password FROM admin_users WHERE id = " . $id;
    $pass_result = mysqli_query($conn, $pass_sql);
    $user_password = '';
    if ($pass_result && mysqli_num_rows($pass_result) > 0) {
        $pass_data = mysqli_fetch_assoc($pass_result);
        $user_password = $pass_data['password'];
    }
}

// Basic validation
if ($id <= 0 || $email === '' || $first_name === '' || $last_name === '' || $phone === '' || $status === '' || $role_id === '') {
    $_SESSION['error'] = 'All fields are required.';
    header('Location: edit-user.php?id=' . $id);
    exit;
}

// Update user
$sql = "UPDATE admin_users SET 
    email = '" . mysqli_real_escape_string($conn, $email) . "',
    first_name = '" . mysqli_real_escape_string($conn, $first_name) . "',
    last_name = '" . mysqli_real_escape_string($conn, $last_name) . "',
    phone = '" . mysqli_real_escape_string($conn, $phone) . "',
    status = '" . mysqli_real_escape_string($conn, $status) . "',
    role_id = '" . mysqli_real_escape_string($conn, $role_id) . "'
    WHERE id = " . $id;

if (mysqli_query($conn, $sql)) {
    // Send update notification email
    $mail = new PHPMailer(true);

    try {
        // Server settings - Coida SMTP
        $mail->isSMTP();
        $mail->Host = 'mail.coida.in';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@coida.in';
        $mail->Password = '12@Coida';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom('no-reply@coida.in', 'Silky Admin');
        
        // Send to the new email address
        $mail->addAddress($email, $first_name . ' ' . $last_name);
        
        // If email was changed, also send to the old email address
        if ($old_email && $old_email !== $email) {
            $mail->addCC($old_email, $first_name . ' ' . $last_name);
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Account Updated - Silky Admin';

        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                .email-container { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; }
                .header { background-color: #0e2187; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f8f9fc; }
                .update-info { background-color: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .footer { padding: 20px; text-align: center; color: #666; }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="header">
                    <h1>Account Update Notification</h1>
                </div>
                <div class="content">
                    <h2>Hello ' . htmlspecialchars($first_name) . ',</h2>
                    <p>Your account information has been updated in the Silky Admin panel.</p>
                    
                    <div class="update-info">
                        <p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
                        <p><strong>Name:</strong> ' . htmlspecialchars($first_name . ' ' . $last_name) . '</p>
                        <p><strong>Phone:</strong> ' . htmlspecialchars($phone) . '</p>
                        <p><strong>Status:</strong> ' . htmlspecialchars($status) . '</p>
                            <p><strong>Password:</strong> ' . htmlspecialchars($user_password) . '</p>
                    </div>
                    
                    <p>If you did not request these changes, please contact the administrator immediately.</p>
                    <p>You can log in at: <a href="' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http') . '://' . ($_SERVER['HTTP_HOST']??'localhost') . (defined('BASE_URL')?BASE_URL:'/') . 'admin/login">Admin Login</a></p>
                </div>
                <div class="footer">
                    <p>This is an automated email. Please do not reply.</p>
                    <p>&copy; 2024 Silky Garments</p>
                </div>
            </div>
        </body>
        </html>';

        // SMTP settings for production
        $mail->SMTPDebug = 0; // No debug output
        
        $mail->send();
        $_SESSION['success'] = 'User updated successfully and notification email sent.';

    } catch (Exception $e) {
        $_SESSION['success'] = 'User updated successfully, but notification email could not be sent.';
    }

    header('Location: users.php');
    exit;
} else {
    $_SESSION['error'] = 'Error updating user: ' . mysqli_error($conn);
    header('Location: edit-user.php?id=' . $id);
    exit;
}