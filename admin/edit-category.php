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

    <!-- Cropper CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet" />

    <style>
        /* Fixed Crop Modal Styles */
        #cropperModal .modal-dialog {
            max-width: 560px;
            width: 95%;
            margin: 1.5rem auto;
        }
        #cropperModal .modal-content {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--bs-border-color, rgba(255, 255, 255, 0.12));
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.45);
        }
        #cropperModal .modal-header {
            padding: 10px 16px;
        }
        #cropperModal .modal-body {
            padding: 10px 14px;
            background-color: #0b0f19;
        }
        #cropperModal .img-container {
            height: 480px;
            max-height: 58vh;
            min-height: 320px;
            width: 100%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #000000;
            border-radius: 6px;
            position: relative;
        }
        #cropperModal .img-container img {
            display: block;
            max-width: 100%;
        }
        #cropperModal .cropper-container {
            max-height: 100% !important;
            width: 100% !important;
        }
        #cropperModal .modal-footer {
            padding: 10px 16px;
        }
    </style>

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
                                        <label for="category-image-file" class="form-label">Category Image</label>
                                        
                                        <?php if (!empty($category['image'])): ?>
                                            <div class="mb-3" id="current-image-container">
                                                <label class="form-label d-block text-muted">Current Image:</label>
                                                <div class="position-relative d-inline-block border rounded p-1 bg-light">
                                                    <img src="<?php echo htmlspecialchars('../' . ltrim(str_replace(['./', '\\'], ['','/'], $category['image']), '/')); ?>" 
                                                         alt="Category Image" 
                                                         id="current-category-img"
                                                         style="width: 150px; height: 200px; object-fit: cover; border-radius: 4px; display: block;">
                                                    <button type="button" 
                                                            class="btn btn-danger btn-sm position-absolute top-0 end-0 rounded-circle p-1" 
                                                            onclick="deleteImage(<?php echo $category['id']; ?>)"
                                                            style="width: 25px; height: 25px; margin: -5px;"
                                                            title="Delete Image">
                                                        <i class="fas fa-times" style="font-size: 12px;"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted d-block mt-1">Click &times; to delete current image</small>
                                            </div>
                                        <?php endif; ?>

                                        <label class="form-label text-muted"><?php echo !empty($category['image']) ? 'Upload New Image (Replaces current):' : 'Upload Image:'; ?></label>
                                        <input type="file" class="form-control" id="category-image-file" accept="image/*">
                                        <!-- Hidden input holds the current or newly cropped WebP image path -->
                                        <input type="hidden" name="image" id="category-image-path" value="<?php echo htmlspecialchars($category['image'] ?? ''); ?>">
                                        <div class="form-text text-muted">Select an image to crop in 3:4 portrait ratio. It will be automatically converted to high-performance compressed WebP.</div>

                                        <!-- New cropped preview container -->
                                        <div id="new-image-preview-container" class="mt-3" style="display: none;">
                                            <label class="form-label d-block text-muted">New Cropped WebP Preview:</label>
                                            <div class="position-relative d-inline-block border rounded p-1 bg-light shadow-sm">
                                                <img id="new-category-image-preview" src="" alt="Cropped Preview" style="width: 150px; height: 200px; object-fit: cover; border-radius: 6px; display: block;">
                                                <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 rounded-circle" id="btn-remove-new-category-img" style="width: 26px; height: 26px; padding: 0; line-height: 24px;" title="Cancel new image">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <div class="mt-1">
                                                <span class="badge bg-success-subtle text-success"><i class="fas fa-check-circle me-1"></i>New Image Cropped & Converted to WebP</span>
                                            </div>
                                        </div>
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

            <!-- Cropper Modal -->
            <div class="modal fade" id="cropperModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="cropperModalTitle">Crop Category Image (3:4)</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="img-container">
                                <img id="crop-image" src="" alt="Crop">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="btn-crop-upload" onclick="cropAndUploadCategory()">Crop & Convert to WebP</button>
                        </div>
                    </div>
                </div>
            </div>

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

    <!-- Cropper JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script>
        let cropper = null;
        let activeCropObjectUrl = null;
        let selectedFileName = '';
        const originalImagePath = <?php echo json_encode($category['image'] ?? ''); ?>;

        function resolveAdminImgJs(path) {
            if (!path) return 'assets/images/products/default.png';
            if (path.startsWith('http') || path.startsWith('data:')) return path;
            const clean = path.replace(/^\.\//, '');
            if (clean.startsWith('uploads/')) {
                return '../' + clean;
            }
            if (clean.startsWith('assets/')) {
                return clean;
            }
            return clean.startsWith('../') ? clean : '../' + clean;
        }

        document.addEventListener('DOMContentLoaded', function () {
            const fileInput = document.getElementById('category-image-file');
            const cropModalEl = document.getElementById('cropperModal');
            const cropModal = bootstrap.Modal.getOrCreateInstance(cropModalEl);
            const cropImg = document.getElementById('crop-image');
            const removeNewBtn = document.getElementById('btn-remove-new-category-img');

            if (fileInput) {
                fileInput.addEventListener('change', function (e) {
                    const file = e.target.files[0];
                    if (!file) return;

                    const maxBytes = 50 * 1024 * 1024;
                    if (file.size > maxBytes) {
                        alert('Image too large. Maximum 50 MB allowed.');
                        fileInput.value = '';
                        return;
                    }

                    selectedFileName = file.name || ('category_' + Date.now());

                    if (activeCropObjectUrl) {
                        URL.revokeObjectURL(activeCropObjectUrl);
                        activeCropObjectUrl = null;
                    }

                    activeCropObjectUrl = URL.createObjectURL(file);

                    function onShown() {
                        cropModalEl.removeEventListener('shown.bs.modal', onShown);
                        if (cropper) {
                            cropper.destroy();
                            cropper = null;
                        }
                        cropper = new Cropper(cropImg, {
                            aspectRatio: 3 / 4,
                            viewMode: 1,
                            autoCropArea: 1,
                            responsive: true,
                            restore: false,
                            guides: true,
                            center: true,
                            highlight: false,
                            cropBoxMovable: true,
                            cropBoxResizable: true,
                            toggleDragModeOnDblclick: false
                        });
                    }

                    cropModalEl.addEventListener('shown.bs.modal', onShown);
                    cropImg.src = activeCropObjectUrl;
                    cropModal.show();
                });
            }

            if (cropModalEl) {
                cropModalEl.addEventListener('hidden.bs.modal', function () {
                    if (cropper) {
                        cropper.destroy();
                        cropper = null;
                    }
                    if (activeCropObjectUrl) {
                        URL.revokeObjectURL(activeCropObjectUrl);
                        activeCropObjectUrl = null;
                    }
                    if (fileInput) fileInput.value = '';
                });
            }

            if (removeNewBtn) {
                removeNewBtn.addEventListener('click', function () {
                    // Revert to original category image
                    document.getElementById('category-image-path').value = originalImagePath;
                    document.getElementById('new-image-preview-container').style.display = 'none';
                    document.getElementById('new-category-image-preview').src = '';
                    if (fileInput) fileInput.value = '';
                });
            }
        });

        function cropAndUploadCategory() {
            if (!cropper) return;

            const cropBtn = document.getElementById('btn-crop-upload');
            const originalBtnHtml = cropBtn ? cropBtn.innerHTML : 'Crop & Convert to WebP';
            if (cropBtn) {
                cropBtn.disabled = true;
                cropBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Converting to WebP...';
            }

            // High-resolution 3:4 canvas (900x1200)
            const canvas = cropper.getCroppedCanvas({
                width: 900,
                height: 1200,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });

            if (!canvas) {
                if (cropBtn) { cropBtn.disabled = false; cropBtn.innerHTML = originalBtnHtml; }
                alert('Could not generate cropped canvas.');
                return;
            }

            canvas.toBlob(async (blob) => {
                if (!blob) {
                    if (cropBtn) { cropBtn.disabled = false; cropBtn.innerHTML = originalBtnHtml; }
                    alert('Error creating WebP image. Please try again.');
                    return;
                }

                const formData = new FormData();
                let baseName = 'category_' + Date.now();
                if (selectedFileName) {
                    baseName = selectedFileName.replace(/\.[^/.]+$/, '').replace(/[^a-zA-Z0-9_-]/g, '_');
                }
                const filename = baseName + '.webp';

                formData.append('image', blob, filename);
                formData.append('type', 'category');

                try {
                    const response = await fetch('upload-image.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        document.getElementById('category-image-path').value = data.path;
                        const prev = document.getElementById('new-category-image-preview');
                        if (prev) {
                            prev.src = resolveAdminImgJs(data.path);
                        }
                        const prevContainer = document.getElementById('new-image-preview-container');
                        if (prevContainer) {
                            prevContainer.style.display = 'block';
                        }

                        const cropModal = bootstrap.Modal.getInstance(document.getElementById('cropperModal'));
                        if (cropModal) cropModal.hide();
                    } else {
                        alert('Upload failed: ' + (data.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Upload failed: ' + error.message);
                } finally {
                    if (cropBtn) {
                        cropBtn.disabled = false;
                        cropBtn.innerHTML = originalBtnHtml;
                    }
                }
            }, 'image/webp', 0.88);
        }
    </script>

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