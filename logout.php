<?php
session_start();

// Clear ONLY customer storefront session variables (preserves Admin panel session)
unset($_SESSION['user_id']);
unset($_SESSION['user_email']);
unset($_SESSION['first_name']);
unset($_SESSION['last_name']);
unset($_SESSION['user_name']);
unset($_SESSION['client_logged_in']);
unset($_SESSION['cart']);
unset($_SESSION['wishlist']);

// Clear remember me cookie if it exists
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/');
}

// Redirect to home page
header("Location: index.php");
exit();
?>
