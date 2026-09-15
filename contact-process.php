<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

// Basic validation
if (empty($name) || empty($email) || empty($subject) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (strlen($name) < 2 || strlen($name) > 100) {
    echo json_encode(['success' => false, 'message' => 'Name must be between 2 and 100 characters.']);
    exit;
}
if (!preg_match("/^[a-zA-Z\s]+$/", $name)) {
    echo json_encode(['success' => false, 'message' => 'Name should only contain letters and spaces.']);
    exit;
}

if (strlen($message) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Message is too long. Max 1000 characters allowed.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

if (empty($recaptcha_response)) {
    echo json_encode(['success' => false, 'message' => 'Please complete the reCAPTCHA verification.']);
    exit;
}

// Verify reCAPTCHA
$recaptcha_secret = "6LcX6aYtAAAAAO8GXqmVIklN9cW1SwZTuCB9eYZu"; // Google reCAPTCHA secret key
$verify_url = 'https://www.google.com/recaptcha/api/siteverify';
$verify_data = [
    'secret' => $recaptcha_secret,
    'response' => $recaptcha_response
];

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($verify_data)
    ]
];
$context  = stream_context_create($options);
$verify_result = file_get_contents($verify_url, false, $context);
$recaptcha_data = json_decode($verify_result);

if (!$recaptcha_data->success) {
    echo json_encode(['success' => false, 'message' => 'reCAPTCHA verification failed. Please try again.']);
    exit;
}

$ip_address = $_SERVER['REMOTE_ADDR'];

// IP Rate Limiting (Max 3 per day)
$limit_sql = "SELECT COUNT(*) as msg_count FROM contact_messages WHERE ip_address = ? AND DATE(created_at) = CURDATE()";
$limit_stmt = mysqli_prepare($conn, $limit_sql);
if ($limit_stmt) {
    mysqli_stmt_bind_param($limit_stmt, "s", $ip_address);
    mysqli_stmt_execute($limit_stmt);
    $limit_result = mysqli_stmt_get_result($limit_stmt);
    if ($limit_result) {
        $limit_row = mysqli_fetch_assoc($limit_result);
        if ($limit_row['msg_count'] >= 3) {
            echo json_encode(['success' => false, 'message' => 'You have reached the maximum number of messages allowed per day. Please try again tomorrow.']);
            exit;
        }
    }
    mysqli_stmt_close($limit_stmt);
}

// Insert into database
$sql = "INSERT INTO contact_messages (name, email, phone, subject, message, ip_address) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ssssss", $name, $email, $phone, $subject, $message, $ip_address);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Thank you! Your message has been sent successfully. We will get back to you soon.']);
    } else {
        error_log("Database error in contact-process.php: " . mysqli_error($conn));
        echo json_encode(['success' => false, 'message' => 'An error occurred while saving your message. Please try again later.']);
    }
    mysqli_stmt_close($stmt);
} else {
    error_log("Database preparation error in contact-process.php: " . mysqli_error($conn));
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}

mysqli_close($conn);
?>
