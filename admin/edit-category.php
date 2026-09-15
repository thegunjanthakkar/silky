<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'includes/permission-manager.php';
checkPageAccess();
require_once '../db_config.php';

// Get category ID from query string
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($category_id <= 0) {
    $_SESSION['error'] = 'Invalid category ID.';
    header('Location: categories.php');
    exit;
}

// Fetch category details
$sql = "SELECT * FROM categories WHERE id = '" . mysqli_real_escape_string($conn, $category_id) . "'";
$result = mysqli_query($conn, $sql);
if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = 'Category not found.';
    header('Location: categories.php');
    exit;
}
$category = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <title>Edit Category | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">

    <!-- App CSS -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <!-- Dark Mode State Check Script -->
    <script>
        // Check and apply saved theme before page loads
        (function () {
            const savedTheme = localStorage.getItem('silky_admin_theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
            }
        })();
    </script>
</head>

<body>
    <!-- Top Bar Start -->
    <?php include 'topbar.php'; ?>
    <!-- Top Bar End -->

    <!-- leftbar-tab-menu -->
    <?php include 'leftbar.php'; ?>
    <!-- end leftbar-tab-menu-->

    <div class="page-wrapper">
        <!-- Page Content-->
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-12">
                        <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                            <h4 class="page-title">Edit User</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="#">User Management</a></li>
                                    <li class="breadcrumb-item"><a href="users.php">Users</a></li>
                                    <li class="breadcrumb-item active">Edit User</li>
                                </ol>
                            </div>
                        </div><!--end page-title-box-->
                    </div><!--end col-->
                </div><!--end row-->

                <div class="row justify-content-center">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h4 class="card-title">Edit Category</h4>
                                    </div><!--end col-->
                                    <div class="col-auto">
                                        <a href="categories.php" class="btn btn-outline-secondary">
                                            <i class="iconoir-arrow-left me-2"></i>Back to Categories
                                        </a>
                                    </div><!--end col-->
                                </div> <!--end row-->
                            </div><!--end card-header-->
                            <div class="card-body">
                                <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger">
                                        <?php echo $_SESSION['error'];
                                        unset($_SESSION['error']); ?></div>
                                <?php endif; ?>
                                <?php if (isset($_SESSION['success'])): ?>
                                    <div class="alert alert-success">
                                        <?php echo $_SESSION['success'];
                                        unset($_SESSION['success']); ?></div>
                                <?php endif; ?>

                                <form action="save-updated-category.php" method="post" enctype="multipart/form-data"
                                    class="needs-validation" novalidate>
                                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                    <input type="hidden" name="action" value="update">

                                    <div class="mb-3">
                                        <label for="name" class="form-label">Category Name <span
                                                class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name"
                                            value="<?php echo htmlspecialchars($category['name']); ?>" required>
                                        <div class="invalid-feedback">Please provide a valid category name.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description"
                                            rows="3"><?php echo htmlspecialchars($category['description']); ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="image" class="form-label">Category Image</label>
                                        
                                        <?php if (!empty($category['image'])): ?>
                                            <div class="mb-3">
                                                <label class="form-label">Current Image:</label>
                                                <div class="position-relative d-inline-block">
                                                    <img src="<?php echo htmlspecialchars('../' . ltrim(str_replace(['./', '\\'], ['','/'], $category['image']), '/')); ?>" 
                                                         alt="Category Image" 
                                                         style="max-width:150px; max-height:150px; border: 1px solid #ddd; border-radius: 5px;">
                                                    <button type="button" 
                                                            class="btn btn-danger btn-sm position-absolute top-0 end-0 rounded-circle p-1" 
                                                            onclick="deleteImage(<?php echo $category['id']; ?>)"
                                                            style="width: 25px; height: 25px; margin: -5px;"
                                                            title="Delete Image">
                                                        <i class="fas fa-times" style="font-size: 12px;"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted d-block mt-1">Click X to delete current image</small>
                                            </div>
                                        <?php endif; ?>

                                        <input type="file" class="form-control" id="image" name="image">
                                    </div>
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="active" <?php if ($category['status'] == 'active')
                                                echo 'selected'; ?>>Active</option>
                                            <option value="inactive" <?php if ($category['status'] == 'inactive')
                                                echo 'selected'; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="iconoir-check me-2"></i>Update Category
                                        </button>
                                        <a href="categories.php" class="btn btn-secondary">
                                            <i class="iconoir-cancel me-2"></i>Cancel
                                        </a>
                                    </div>
                                </form>
                            </div><!--end card-body-->
                        </div><!--end card-->
                    </div> <!--end col-->
                </div><!--end row-->

            </div><!-- container -->

            <!--Start Footer-->
            <?php include 'footer.php'; ?>
            <!--end footer-->
        </div>
    </div>
    <!-- end page-wrapper -->

    <!-- Javascript  -->
    <!-- vendor js -->
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>

    <!-- App js -->
    <script src="assets/js/app.js"></script>

    <!-- Form Validation -->
    <script>
        // Bootstrap form validation
        (function () {
            'use strict';
            window.addEventListener('load', function () {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function (form) {
                    form.addEventListener('submit', function (event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Delete image function
        function deleteImage(categoryId) {
            if (confirm('Are you sure you want to delete this image?')) {
                fetch('save-category.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete_image&id=' + categoryId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload(); // Reload page to show updated state
                    } else {
                        alert('Error: ' + (data.message || 'Failed to delete image'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the image');
                });
            }
        }
    </script>

</body>

</html>