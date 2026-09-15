<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../db_config.php';

$color_name = isset($_POST['color_name']) ? trim($_POST['color_name']) : '';
$color_code = isset($_POST['color_code']) ? trim($_POST['color_code']) : '';

if (empty($color_name)) {
    echo json_encode(['success' => false, 'message' => 'Color name is required.']);
    exit;
}

if (empty($color_code)) {
    $color_code = '#000000';
}

if ($color_code[0] !== '#') {
    $color_code = '#' . $color_code;
}

$color_name_clean = mysqli_real_escape_string($conn, $color_name);
$color_code_clean = mysqli_real_escape_string($conn, strtoupper($color_code));

// Check if color with same name or code already exists
$check_sql = "SELECT id, color_name, color_code FROM colors WHERE LOWER(color_name) = LOWER('$color_name_clean') OR UPPER(color_code) = '$color_code_clean' LIMIT 1";
$check_res = mysqli_query($conn, $check_sql);

if ($check_res && mysqli_num_rows($check_res) > 0) {
    $existing = mysqli_fetch_assoc($check_res);
    echo json_encode([
        'success' => true,
        'color' => [
            'id' => intval($existing['id']),
            'color_name' => $existing['color_name'],
            'color_code' => $existing['color_code']
        ],
        'existing' => true,
        'message' => 'Color already exists in palette.'
    ]);
    exit;
}

$sql = "INSERT INTO colors (color_name, color_code, status) VALUES ('$color_name_clean', '$color_code_clean', 'active')";
if (mysqli_query($conn, $sql)) {
    $new_id = mysqli_insert_id($conn);
    echo json_encode([
        'success' => true,
        'color' => [
            'id' => $new_id,
            'color_name' => $color_name,
            'color_code' => strtoupper($color_code)
        ],
        'existing' => false,
        'message' => 'New color added successfully!'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}
