<?php
// Health check endpoint
require_once 'db_config.php';
header('Content-Type: application/json');

echo json_encode([
    'status' => 'ok',
    'db' => isset($conn) && $conn ? 'connected' : 'error'
]);
