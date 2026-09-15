<?php
require_once __DIR__ . '/email-functions.php';

function checkAndSendStockNotifications($conn, $product_id, $variant_key = null) {
    $variant_cond = "";
    if ($variant_key !== null) {
        $variant_cond = "AND (variant_key = '" . mysqli_real_escape_string($conn, $variant_key) . "' OR variant_key IS NULL OR variant_key = '')";
    }
    
    $sql = "SELECT id FROM stock_notifications WHERE product_id = " . intval($product_id) . " $variant_cond AND status = 'pending'";
    $res = mysqli_query($conn, $sql);
    
    if ($res && mysqli_num_rows($res) > 0) {
        while ($row = mysqli_fetch_assoc($res)) {
            sendStockNotificationEmail($conn, $row['id']);
        }
    }
}
?>
