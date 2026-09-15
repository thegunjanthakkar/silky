<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $google_id = isset($_POST['google_id']) ? trim($_POST['google_id']) : '';

    if (empty($email)) {
        $response['message'] = 'Email is required';
        echo json_encode($response);
        exit;
    }

    // Check if user already exists
    $stmt = $conn->prepare("SELECT id, first_name, email FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // User exists, log them in
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['logged_in'] = true;

        $response['success'] = true;
        $response['message'] = 'Logged in successfully via Google!';
    } else {
        // New user - Register via Google
        $status = 'active';
        $email_verified = 1;
        $random_password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        
        if (empty($first_name)) {
            $parts = explode('@', $email);
            $first_name = ucfirst($parts[0]);
        }

        $insert_stmt = $conn->prepare("INSERT INTO users (email, password, first_name, last_name, status, email_verified, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $insert_stmt->bind_param("sssssi", $email, $random_password, $first_name, $last_name, $status, $email_verified);

        if ($insert_stmt->execute()) {
            $_SESSION['user_id'] = $insert_stmt->insert_id;
            $_SESSION['user_email'] = $email;
            $_SESSION['first_name'] = $first_name;
            $_SESSION['logged_in'] = true;

            $response['success'] = true;
            $response['message'] = 'Account created and logged in successfully via Google!';
        } else {
            $response['message'] = 'Failed to create account: ' . $conn->error;
        }
        $insert_stmt->close();
    }
    $stmt->close();
    $conn->close();

    echo json_encode($response);
    exit;
}

$response['message'] = 'Invalid request method';
echo json_encode($response);
