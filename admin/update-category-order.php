<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../db_config.php';
require_once 'includes/permission-manager.php';
// Optional: check permissions here

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['order']) && is_array($data['order'])) {
        $order = $data['order'];
        $success = true;
        
        mysqli_begin_transaction($conn);
        
        try {
            foreach ($order as $index => $id) {
                $id = (int)$id;
                $display_order = (int)$index;
                
                $stmt = mysqli_prepare($conn, "UPDATE categories SET display_order = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "ii", $display_order, $id);
                
                if (!mysqli_stmt_execute($stmt)) {
                    $success = false;
                    break;
                }
                mysqli_stmt_close($stmt);
            }
            
            if ($success) {
                mysqli_commit($conn);
                echo json_encode(['success' => true, 'message' => 'Order updated successfully']);
            } else {
                mysqli_rollback($conn);
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
