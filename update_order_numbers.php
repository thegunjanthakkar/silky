<?php
/**
 * Quick Order Number Re-sequencing Script
 * Upload and run this script once in your browser (e.g. https://silky.coida.in/update_order_numbers.php)
 * to convert existing orders in your database to 4-digit numeric format (0001, 0002, 0003...).
 */

require_once 'db_config.php';

if (!isset($conn) || !$conn) {
    die("Database connection failed. Check db_config.php.");
}

$sql = "UPDATE orders SET order_number = LPAD(id, 4, '0')";

if (mysqli_query($conn, $sql)) {
    $affected = mysqli_affected_rows($conn);
    echo "<h2 style='color:green;font-family:sans-serif;'>SUCCESS!</h2>";
    echo "<p style='font-family:sans-serif;'>Updated <strong>{$affected}</strong> existing order(s) to 4-digit numeric format (0001, 0002, 0003...).</p>";
} else {
    echo "<h2 style='color:red;font-family:sans-serif;'>ERROR!</h2>";
    echo "<p style='font-family:sans-serif;'>" . mysqli_error($conn) . "</p>";
}
