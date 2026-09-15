<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a review.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';

if ($product_id <= 0 || $rating < 1 || $rating > 5 || empty($review_text)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid rating and review text.']);
    exit;
}

// Double check if purchased
$can_review = false;
$purchase_sql = "SELECT 1 FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE o.user_id = ? AND oi.product_id = ? AND (o.payment_status = 'paid' OR o.order_status = 'delivered' OR o.order_status = 'completed') LIMIT 1";
$stmt_pur = mysqli_prepare($conn, $purchase_sql);
if ($stmt_pur) {
    mysqli_stmt_bind_param($stmt_pur, "ii", $user_id, $product_id);
    mysqli_stmt_execute($stmt_pur);
    $res_pur = mysqli_stmt_get_result($stmt_pur);
    if ($res_pur && $res_pur->num_rows > 0) {
        $can_review = true;
    }
}

if (!$can_review) {
    echo json_encode(['success' => false, 'message' => 'You can only review products that you have purchased and received.']);
    exit;
}

// Check if already reviewed
$check_rev_sql = "SELECT 1 FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1";
$stmt_check = mysqli_prepare($conn, $check_rev_sql);
if ($stmt_check) {
    mysqli_stmt_bind_param($stmt_check, "ii", $product_id, $user_id);
    mysqli_stmt_execute($stmt_check);
    $res_check = mysqli_stmt_get_result($stmt_check);
    if ($res_check && $res_check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already reviewed this product.']);
        exit;
    }
}

// Insert the review
$insert_sql = "INSERT INTO reviews (product_id, user_id, rating, review_text, status) VALUES (?, ?, ?, ?, 'approved')";
$stmt_ins = mysqli_prepare($conn, $insert_sql);
if ($stmt_ins) {
    mysqli_stmt_bind_param($stmt_ins, "iiis", $product_id, $user_id, $rating, $review_text);
    if (mysqli_stmt_execute($stmt_ins)) {
        echo json_encode(['success' => true, 'message' => 'Your review has been submitted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save review. Please try again.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>
