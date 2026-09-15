<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    session_start();
    require_once '../db_config.php';

    // Check if user is logged in
    if (!isset($_SESSION['admin_user_id']) && !isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Please login first"]);
        exit;
    }

    // Handle delete image action
    if (isset($_POST['action']) && $_POST['action'] === 'delete_image') {
        $category_id = intval($_POST['id'] ?? 0);
        
        if ($category_id <= 0) {
            echo json_encode(["success" => false, "message" => "Invalid category ID"]);
            exit;
        }
        
        // Get current image path
        $sql = "SELECT image FROM categories WHERE id = '$category_id'";
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
            $update_sql = "UPDATE categories SET image = '' WHERE id = '$category_id'";
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

    $name = trim($_POST["name"] ?? '');
    $description = trim($_POST["description"] ?? '');
    $status = $_POST["status"] ?? 'inactive';
    $user_id = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'];
    
    // Check if image path is already in POST data (from previous upload/cropping)
    $image_path = '';
    if (!empty($_POST['image'])) {
        $image_path = trim($_POST['image']);
        error_log("Image path from POST: " . $image_path);
    }

    if (empty($name)) {
        echo json_encode(["status" => "error", "message" => "Category name is required."]);
        exit;
    }

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        // Debug
        error_log("Image upload detected: " . json_encode($_FILES['image']));
        // Validate image
        $maxSize = 1.5 * 1024 * 1024; // 1.5MB
        if ($_FILES['image']['size'] > $maxSize) {
            $_SESSION['error'] = 'Image too large. Maximum 1.5MB allowed.';
            header('Location: add-category.php');
            exit;
        }
        
        $uploadDir = __DIR__ . '/../uploads/categories/';  // Fixed path with correct slash
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            error_log("Created directory: " . $uploadDir);
        }
        
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destPath = $uploadDir . $filename;
        
        error_log("Trying to move: " . $_FILES['image']['tmp_name'] . " to " . $destPath);
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
            $image_path = './uploads/categories/' . $filename;
            error_log("Successfully moved image to: " . $destPath);
            error_log("Image path saved to DB: " . $image_path);
        } else {
            error_log("Failed to move uploaded file: " . error_get_last()['message'] ?? 'Unknown error');
            $image_path = '';
        }
    } else {
        $image_path = '';
        error_log("No image uploaded or error: " . json_encode($_FILES));
    }

    // Escape strings for SQL
    $name = mysqli_real_escape_string($conn, $name);
    $description = mysqli_real_escape_string($conn, $description);
    $image_path = mysqli_real_escape_string($conn, $image_path);
    $status = ($status === 'active') ? 'active' : 'inactive';

    // Insert new category with all required fields
    $query = "INSERT INTO categories (name, description, image, status, created_by, created_at) 
              VALUES ('$name', '$description', '$image_path', '$status', '$user_id', NOW())";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(["status" => "success", "message" => "Category added successfully."]);
        header("Location: ./categories");
        exit;
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . mysqli_error($conn)]);
    }

    mysqli_close($conn);
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}
exit;
?>