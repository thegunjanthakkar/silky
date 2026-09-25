<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <title>Add Category | Silky Admin</title>
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
    <?php
    // Start session first
    session_start();
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
    // Check permission for this page
    require_once 'includes/permission-manager.php';
    checkPageAccess();
    ?>
   
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
                            <h4 class="page-title" id="page-title">Add New Category</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="./categories">Categories</a></li>
                                    <li class="breadcrumb-item active" id="breadcrumb-title">Add Category</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end row -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title" id="card-title">Add New Category</h4>
                            </div>
                            <div class="card-body">
                                <form id="add-category-form" method="POST" action="save-category.php"
                                    enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="category-name" class="form-label">Category Name</label>
                                        <input type="text" class="form-control" id="category-name" name="name"
                                            placeholder="Enter category name" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="category-description" class="form-label">Category
                                            Description</label>
                                        <textarea class="form-control" id="category-description" name="description"
                                            rows="3" placeholder="Enter category description" required></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="category-image-file" class="form-label">Category Image <span class="text-danger">*</span></label>
                                        <input type="file" id="category-image-file" class="form-control" accept="image/*">
                                        <!-- Hidden input holds the WebP cropped path for submission to save-category.php -->
                                        <input type="hidden" name="image" id="category-image-path">
                                        <div class="form-text text-muted">Select an image to crop in 3:4 portrait ratio. It will be automatically converted to high-performance compressed WebP.</div>

                                        <!-- Image preview container -->
                                        <div id="category-image-preview-container" class="mt-3" style="display: none;">
                                            <div class="position-relative d-inline-block border rounded p-1 bg-light shadow-sm">
                                                <img id="category-image-preview" src="" alt="Cropped Preview" style="width: 150px; height: 200px; object-fit: cover; border-radius: 6px; display: block;">
                                                <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 rounded-circle" id="btn-remove-category-img" style="width: 26px; height: 26px; padding: 0; line-height: 24px;" title="Remove image">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <div class="mt-1">
                                                <span class="badge bg-success-subtle text-success"><i class="fas fa-check-circle me-1"></i>Cropped & converted to WebP</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="category-status" class="form-label">Status</label>
                                        <select name="status" id="category-status" class="form-select" required>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>

                                    <button type="submit" class="btn btn-primary" id="btn-submit-category">Add Category</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end row -->
            </div>

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

            <?php include 'footer.php'; ?>
        </div>
    </div>

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
            const removeBtn = document.getElementById('btn-remove-category-img');
            const form = document.getElementById('add-category-form');

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

            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    document.getElementById('category-image-path').value = '';
                    document.getElementById('category-image-preview-container').style.display = 'none';
                    document.getElementById('category-image-preview').src = '';
                    if (fileInput) fileInput.value = '';
                });
            }

            if (form) {
                form.addEventListener('submit', function (e) {
                    const imgPath = document.getElementById('category-image-path').value;
                    if (!imgPath) {
                        e.preventDefault();
                        alert('Please select and crop an image for this category.');
                        return false;
                    }
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

            // Export high-resolution 3:4 canvas (900x1200)
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
                        const prev = document.getElementById('category-image-preview');
                        if (prev) {
                            prev.src = resolveAdminImgJs(data.path);
                        }
                        const prevContainer = document.getElementById('category-image-preview-container');
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
</body>

</html>