<?php
// Simple version with exception handling
session_start();
require_once '../../db_config.php';

// Enable MySQLi exceptions for cleaner error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection not available');
    }
    $conn->set_charset('utf8mb4');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../add-role.php');
        exit;
    }

    $role_name = isset($_POST['role_name']) ? trim($_POST['role_name']) : '';
    $role_description = isset($_POST['role_description']) ? trim($_POST['role_description']) : '';
    $permissions = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];
    $action = isset($_POST['action']) ? $_POST['action'] : 'create';
    $role_id = isset($_POST['role_id']) ? (int) $_POST['role_id'] : 0;

    if ($role_name === '' || empty($permissions)) {
        $_SESSION['role_flash'] = [
            'type' => 'error',
            'messages' => ['Role name and at least one permission are required']
        ];
        header('Location: ../add-role.php' . ($action === 'update' && $role_id ? '?edit=' . $role_id : ''));
        exit;
    }

    // Escape values
    $role_name_esc = mysqli_real_escape_string($conn, $role_name);
    $role_description_esc = mysqli_real_escape_string($conn, $role_description);
    $permissions_json = mysqli_real_escape_string($conn, json_encode(array_values($permissions)));

    if ($action === 'update' && $role_id > 0) {
        $role_id_int = (int) $role_id;
        $sql = "UPDATE admin_roles SET role_name='$role_name_esc', role_description='$role_description_esc', functionality='$permissions_json' WHERE id=$role_id_int";
        mysqli_query($conn, $sql);
        $_SESSION['role_flash'] = ['type' => 'success', 'messages' => ['Role updated successfully']];
        header('Location: ../user-roles.php');
        exit;
    } else {
        $sql = "INSERT INTO admin_roles (role_name, role_description, functionality, created_at) VALUES ('$role_name_esc', '$role_description_esc', '$permissions_json', NOW())";
        mysqli_query($conn, $sql);
        $_SESSION['role_flash'] = ['type' => 'success', 'messages' => ['Role created successfully']];
        header('Location: ../user-roles.php');
        exit;
    }
} catch (Throwable $e) {
    // Friendly error handling
    $friendly = 'Operation failed. Please try again.';
    if ($e instanceof mysqli_sql_exception) {
        if ($e->getCode() == 1062) { // duplicate entry
            // Attempt to extract the duplicate value
            $dupValue = '';
            if (preg_match("/Duplicate entry '([^']+)'/", $e->getMessage(), $m)) {
                $dupValue = $m[1];
            }
            $friendly = "Role name '" . htmlspecialchars($dupValue ?: 'already used') . "' already exists. Please choose a different name.";
        } else {
            // Other MySQL error codes can be customized here if desired
            $friendly = 'Database error occurred. Please retry.';
        }
    }
    $_SESSION['role_flash'] = [
        'type' => 'error',
        'messages' => [$friendly]
    ];
    $redirect = '../add-role.php';
    if (!empty($_POST['action']) && $_POST['action'] === 'update' && !empty($_POST['role_id'])) {
        $redirect .= '?edit=' . (int) $_POST['role_id'];
    }
    header('Location: ' . $redirect);
    exit;
}
?>