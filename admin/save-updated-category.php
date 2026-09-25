<?php
session_start();
require_once '../db_config.php';
require_once 'includes/permission-manager.php';

// Check if user is logged in
if (!isset($_SESSION['admin_user_id']) && !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Please login first"]);
    exit;
}

// Handle AJAX image delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_image') {
    header('Content-Type: application/json');
    
    $category_id = intval($_POST['id'] ?? 0);
    if ($category_id <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid category ID"]);
        exit;
    }
    
    // Get current image path
    $sql = "SELECT image FROM categories WHERE id = '" . mysqli_real_escape_string($conn, $category_id) . "'";
    $result = mysqli_query($conn, $sql);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $current_image = $row['image'];
        
        // Delete physical file if exists
        if (!empty($current_image)) {
            $file_path = __DIR__ . '/../' . ltrim(str_replace(['./', '\\'], ['','/'], $current_image), '/');
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Update database to remove image path
        $update_sql = "UPDATE categories SET image = '' WHERE id = '" . mysqli_real_escape_string($conn, $category_id) . "'";
        if (mysqli_query($conn, $update_sql)) {
            echo json_encode(["success" => true, "message" => "Image deleted successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "Database error: " . mysqli_error($conn)]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Category not found"]);
    }
    exit;
}

// Handle category update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $category_id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = ($_POST['status'] ?? 'inactive') === 'active' ? 'active' : 'inactive';
    $user_id = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'];

    // Validation
    if ($category_id <= 0) {
        $_SESSION['error'] = 'Invalid category ID.';
        header('Location: categories.php');
        exit;
    }

    if (empty($name)) {
        $_SESSION['error'] = 'Category name is required.';
        header('Location: edit-category.php?id=' . $category_id);
        exit;
    }

    // Get current category data
    $current_sql = "SELECT * FROM categories WHERE id = '" . mysqli_real_escape_string($conn, $category_id) . "'";
    $current_result = mysqli_query($conn, $current_sql);
    
    if (!$current_result || mysqli_num_rows($current_result) === 0) {
        $_SESSION['error'] = 'Category not found.';
        header('Location: categories.php');
        exit;
    }
    
    $current_category = mysqli_fetch_assoc($current_result);
    $image_path = $current_category['image']; // Keep current image by default

    // Handle cropped image path from POST
    if (!empty($_POST['image'])) {
        $posted_image = trim($_POST['image']);
        if ($posted_image !== $current_category['image']) {
            if (!empty($current_category['image'])) {
                $old_file_path = __DIR__ . '/../' . ltrim(str_replace(['./', '\\'], ['','/'], $current_category['image']), '/');
                if (file_exists($old_file_path)) {
                    @unlink($old_file_path);
                }
            }
            $image_path = $posted_image;
        }
    }

    // Handle new image upload fallback
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/categories/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Validate image
        $maxSize = 50 * 1024 * 1024; // 50MB
        if ($_FILES['image']['size'] > $maxSize) {
            $_SESSION['error'] = 'Image too large. Maximum 50MB allowed.';
            header('Location: edit-category.php?id=' . $category_id);
            exit;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        
        if (strpos($mime, 'image/') !== 0) {
            $_SESSION['error'] = 'Only image files are allowed.';
            header('Location: edit-category.php?id=' . $category_id);
            exit;
        }
        
        // Generate unique filename
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
            // Delete old image if exists
            if (!empty($current_category['image'])) {
                $old_file_path = __DIR__ . '/../' . ltrim(str_replace(['./', '\\'], ['','/'], $current_category['image']), '/');
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
            }
            
            $image_path = './uploads/categories/' . $filename;
        } else {
            $_SESSION['error'] = 'Failed to upload image.';
            header('Location: edit-category.php?id=' . $category_id);
            exit;
        }
    }

    // Escape strings for SQL
    $name = mysqli_real_escape_string($conn, $name);
    $description = mysqli_real_escape_string($conn, $description);
    $image_path = mysqli_real_escape_string($conn, $image_path);
    $status = mysqli_real_escape_string($conn, $status);

    // Update category in database
    $sql = "UPDATE categories SET 
                name = '$name', 
                description = '$description', 
                image = '$image_path', 
                status = '$status'
            WHERE id = '" . mysqli_real_escape_string($conn, $category_id) . "'";
    
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = 'Category updated successfully!';
        header('Location: categories.php');
        exit;
    } else {
        $_SESSION['error'] = 'Database error: ' . mysqli_error($conn);
        header('Location: edit-category.php?id=' . $category_id);
        exit;
    }
}

// If neither delete nor update action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['error'] = 'Invalid action.';
    header('Location: categories.php');
    exit;
}

// If not POST request
http_response_code(405);
echo json_encode(["success" => false, "message" => "Method not allowed"]);
exit;
?>
