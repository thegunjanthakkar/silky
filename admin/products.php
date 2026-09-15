<?php
// Basic session check for dashboard access
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
// Check permission for this page
require_once 'includes/permission-manager.php';
checkPageAccess();
require_once '../db_config.php';

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['product_ids'])) {
    $action = $_POST['action'];
    $productIds = array_map('intval', $_POST['product_ids']);
    $count = count($productIds);
    
    // Debug logging
    error_log("Bulk action: " . $action . ", IDs: " . implode(',', $productIds));
    
    if ($count > 0) {
        $placeholders = implode(',', $productIds);
        
        switch ($action) {
            case 'activate':
                $sql = "UPDATE products SET status = 'active' WHERE id IN ($placeholders)";
                $successMessage = "$count product(s) activated successfully!";
                break;
                
            case 'deactivate':
                $sql = "UPDATE products SET status = 'inactive' WHERE id IN ($placeholders)";
                $successMessage = "$count product(s) deactivated successfully!";
                break;
                
            case 'delete':
                // Delete related records first
                $deleteColorsSql = "DELETE FROM product_colors WHERE product_id IN ($placeholders)";
                $deleteSizesSql = "DELETE FROM product_sizes WHERE product_id IN ($placeholders)";
                
                mysqli_query($conn, $deleteColorsSql);
                mysqli_query($conn, $deleteSizesSql);
                
                // Delete products
                $sql = "DELETE FROM products WHERE id IN ($placeholders)";
                $successMessage = "$count product(s) deleted successfully!";
                break;
                
            default:
                $_SESSION['error'] = 'Invalid action specified.';
                header('Location: products.php');
                exit;
        }
        
        error_log("Executing SQL: " . $sql);
        
        if (mysqli_query($conn, $sql)) {
            $affectedRows = mysqli_affected_rows($conn);
            error_log("Affected rows: " . $affectedRows);
            $_SESSION['success'] = $successMessage;
        } else {
            $error = mysqli_error($conn);
            error_log("SQL Error: " . $error);
            $_SESSION['error'] = 'Error performing bulk action: ' . $error;
        }
    } else {
        $_SESSION['error'] = 'No products selected.';
    }
    
    header('Location: products.php');
    exit;
}

// Fetch products from database with category names, colors, and sizes
$sql = "SELECT p.*, c.name as category_name, CONCAT(au.first_name, ' ', au.last_name) as created_by_name,
            GROUP_CONCAT(DISTINCT CONCAT(col.color_name, '|', col.color_code) SEPARATOR '~') as product_colors,
            GROUP_CONCAT(DISTINCT s.size_label SEPARATOR '~') as product_sizes
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN admin_users au ON p.created_by = au.id 
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            LEFT JOIN colors col ON pc.color_id = col.id AND col.status = 'active'
            LEFT JOIN product_sizes ps ON p.id = ps.product_id
            LEFT JOIN sizes s ON ps.size_id = s.id AND s.status = 'active'
            GROUP BY p.id
            ORDER BY p.created_at DESC";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light" id="html-root">

<head>
    <meta charset="utf-8" />
    <title>Products | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta content="" name="description" />
    <meta content="" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <!-- App favicon -->
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">

    <!-- DataTables CSS -->
    <link href="assets/libs/simple-datatables/style.css" rel="stylesheet" type="text/css" />

    <!-- App css -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />

    <!-- Dark Mode State Check Script -->
    <script>
        // Check and apply saved theme before page loads
        (function() {
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
                            <h4 class="page-title">Products</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="index.php">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="#">Ecommerce</a></li>
                                    <li class="breadcrumb-item active">Products</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success'];
                        unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error'];
                        unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h4 class="card-title">Products</h4>
                                    </div>
                                    <div class="col-auto">
                                        <form class="row g-2">
                                            <div class="col-auto">
                                                <a class="btn bg-primary-subtle text-primary dropdown-toggle d-flex align-items-center arrow-none"
                                                    data-bs-toggle="dropdown" href="#" role="button"
                                                    aria-haspopup="false" aria-expanded="false"
                                                    data-bs-auto-close="outside">
                                                    <i class="iconoir-filter-alt me-1"></i> Filter
                                                </a>
                                                <div class="dropdown-menu dropdown-menu-start">
                                                    <div class="p-2">
                                                        <div class="form-check mb-2">
                                                            <input type="checkbox" class="form-check-input" checked
                                                                id="filter-all">
                                                            <label class="form-check-label" for="filter-all">All</label>
                                                        </div>
                                                        <div class="form-check mb-2">
                                                            <input type="checkbox" class="form-check-input" checked
                                                                id="filter-active">
                                                            <label class="form-check-label"
                                                                for="filter-active">Active</label>
                                                        </div>
                                                        <div class="form-check mb-2">
                                                            <input type="checkbox" class="form-check-input" checked
                                                                id="filter-inactive">
                                                            <label class="form-check-label"
                                                                for="filter-inactive">Inactive</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input type="checkbox" class="form-check-input" checked
                                                                id="filter-draft">
                                                            <label class="form-check-label"
                                                                for="filter-draft">Draft</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <a href="add-product.php" class="btn btn-primary">
                                                    <i class="fa-solid fa-plus me-1"></i> Add Product
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body">
                                <!-- Bulk Action Bar -->
                                <div id="bulkActionBar" class="d-none mb-3" role="alert">
                                    <div class="card border-primary">
                                        <div class="card-body py-3">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-check-square text-primary fs-20 me-3"></i>
                                                    <div>
                                                        <h6 class="mb-0">
                                                            <strong id="selectedCount">0</strong> product(s) selected
                                                        </h6>
                                                        <small class="text-muted">Choose an action to perform on selected products</small>
                                                    </div>
                                                </div>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-success btn-sm" id="bulkActivate">
                                                        <i class="fas fa-check me-1"></i> Activate
                                                    </button>
                                                    <button type="button" class="btn btn-warning btn-sm" id="bulkDeactivate">
                                                        <i class="fas fa-pause me-1"></i> Inactive
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm" id="bulkDelete">
                                                        <i class="fas fa-trash me-1"></i> Delete
                                                    </button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm ms-2" id="bulkCancel">
                                                        <i class="fas fa-times me-1"></i> Cancel
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table mb-0 checkbox-all" id="datatable_1">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 16px;">
                                                    <div class="form-check mb-0 ms-n1">
                                                        <input type="checkbox" class="form-check-input"
                                                            name="select-all" id="select-all">
                                                    </div>
                                                </th>
                                                <th class="ps-0">Product Name</th>
                                                <th>Category</th>
                                                <th>Size</th>
                                                <th>Color</th>
                                                <th>Price</th>
                                                <th>Status</th>
                                                <th>Created At</th>
                                                <th>Created By</th>
                                                <th class="text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                                <?php $serial = 1; ?>
                                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                                    <tr>
                                                        <td style="width: 16px;">
                                                            <div class="form-check">
                                                                <input type="checkbox" class="form-check-input" name="check"
                                                                    id="customCheck<?php echo $serial; ?>">
                                                            </div>
                                                        </td>
                                                        <td class="ps-0">
                                                            <?php
                                                            $images = json_decode($row['image'], true);
                                                            $firstImage = '';
                                                            if (is_array($images) && count($images) > 0) {
                                                                $firstImage = $images[0];
                                                            } elseif (!empty($row['image']) && !is_array(json_decode($row['image'], true))) {
                                                                // Fallback for old single image format
                                                                $firstImage = $row['image'];
                                                            }
                                                            ?>
                                                            <?php if (!empty($firstImage)): ?>
                                                                <img src="<?php echo htmlspecialchars(str_replace('./', '/', $firstImage)); ?>"
                                                                    alt="" height="40" class="rounded me-1">
                                                            <?php else: ?>
                                                                <img src="assets/images/products/default.png" alt="" height="40"
                                                                    class="rounded me-1">
                                                            <?php endif; ?>
                                                            <p class="d-inline-block align-middle mb-0">
                                                                <a href="../product-details/<?php echo htmlspecialchars($row['slug'] ?? 'product-'.$row['id']); ?>"
                                                                    class="d-inline-block align-middle mb-0 product-name">
                                                                    <?php echo htmlspecialchars($row['name']); ?>
                                                                </a>
                                                                <?php if (!empty($row['product_code'])): ?>
                                                                <br><small class="text-muted">Code: <?php echo htmlspecialchars($row['product_code']); ?></small>
                                                                <?php endif; ?>
                                                                <br>
                                                                <span class="text-muted font-13">
                                                                    <?php echo htmlspecialchars(substr($row['description'], 0, 30)) . (strlen($row['description']) > 30 ? '...' : ''); ?>
                                                                    <?php
                                                                    $imageCount = is_array($images) ? count($images) : 1;
                                                                    $hasVideo = !empty($row['youtube_video_id']);
                                                                    $videoCount = $hasVideo ? 1 : 0;
                                                                    ?>
                                                                    <br>
                                                                    <small class="text-info">
                                                                        <i class="fas fa-image"></i> <?php echo $imageCount; ?>
                                                                        image<?php echo $imageCount > 1 ? 's' : ''; ?>
                                                                        <?php if ($hasVideo): ?>
                                                                            <i class="fab fa-youtube ms-2 text-danger"></i>
                                                                            <?php echo $videoCount; ?> video
                                                                        <?php endif; ?>
                                                                    </small>
                                                                </span>
                                                            </p>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($row['category_name'] ?: 'No Category'); ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            if (!empty($row['product_sizes'])) {
                                                                $sizes = explode('~', $row['product_sizes']);
                                                                $sizeCount = count($sizes);
                                                                $visibleSizes = array_slice($sizes, 0, 2);
                                                                
                                                                foreach ($visibleSizes as $size) {
                                                                    echo '<span class="badge bg-secondary-subtle text-secondary me-1 mb-1">' . htmlspecialchars($size) . '</span>';
                                                                }
                                                                
                                                                if ($sizeCount > 2) {
                                                                    $remainingSizes = array_slice($sizes, 2);
                                                                    $tooltipText = implode(', ', array_map('htmlspecialchars', $remainingSizes));
                                                                    echo '<span class="badge bg-info-subtle text-info me-1 mb-1" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $tooltipText . '">+' . ($sizeCount - 2) . ' more</span>';
                                                                }
                                                            } else {
                                                                echo '<span class="text-muted">N/A</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            if (!empty($row['product_colors'])) {
                                                                $colors = explode('~', $row['product_colors']);
                                                                $colorCount = count($colors);
                                                                $visibleColors = array_slice($colors, 0, 2);
                                                                
                                                                foreach ($visibleColors as $color) {
                                                                    $colorParts = explode('|', $color);
                                                                    $colorName = $colorParts[0];
                                                                    echo '<span class="badge bg-secondary-subtle text-secondary me-1 mb-1">' . htmlspecialchars($colorName) . '</span>';
                                                                }
                                                                
                                                                if ($colorCount > 2) {
                                                                    $remainingColors = array_slice($colors, 2);
                                                                    $remainingColorNames = array_map(function($c) {
                                                                        $parts = explode('|', $c);
                                                                        return htmlspecialchars($parts[0]);
                                                                    }, $remainingColors);
                                                                    $tooltipText = implode(', ', $remainingColorNames);
                                                                    echo '<span class="badge bg-info-subtle text-info me-1 mb-1" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $tooltipText . '">+' . ($colorCount - 2) . ' more</span>';
                                                                }
                                                            } else {
                                                                echo '<span class="text-muted">N/A</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>₹<?php echo number_format($row['price'], 2); ?></td>
                                                        <td>
                                                            <?php if ($row['status'] === 'active'): ?>
                                                                <span class="badge bg-success-subtle text-success">
                                                                    <i class="fas fa-check me-1"></i> Active
                                                                </span>
                                                            <?php elseif ($row['status'] === 'inactive'): ?>
                                                                <span class="badge bg-danger-subtle text-danger">
                                                                    <i class="fas fa-xmark me-1"></i> Inactive
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary-subtle text-secondary">
                                                                    <i class="fas fa-box-archive me-1"></i> Draft
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($row['created_by_name'] ?: 'Unknown'); ?>
                                                        </td>
                                                        <td class="text-end">
                                                            <div class="btn-group" role="group">
                                                                <button type="button"
                                                                    class="btn btn-sm btn-soft-primary btn-view-product"
                                                                    data-id="<?php echo $row['id']; ?>" title="View"><i
                                                                        class="fas fa-eye"></i></button>
                                                                <button type="button"
                                                                    class="btn btn-sm btn-soft-secondary btn-edit-product"
                                                                    data-id="<?php echo $row['id']; ?>" title="Edit"><i
                                                                        class="fas fa-edit"></i></button>
                                                                <button type="button"
                                                                    class="btn btn-sm btn-soft-danger btn-delete-product"
                                                                    data-id="<?php echo $row['id']; ?>" title="Delete"><i
                                                                        class="fas fa-trash"></i></button>
                                                            </div>
                                                    </tr>
                                                    <?php $serial++; ?>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="10" class="text-center py-4">
                                                        <i class="iconoir-box fs-48 text-muted mb-2"></i>
                                                        <p class="text-muted mb-0">No products found</p>
                                                        <a href="add-product.php" class="btn btn-primary btn-sm mt-2">
                                                            <i class="fa-solid fa-plus me-1"></i> Add First Product
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!--Start Footer-->
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Product Preview Modal -->
    <div class="modal fade" id="productPreviewModal" tabindex="-1" aria-labelledby="productPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productPreviewModalLabel">Product Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="productPreviewContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="btnEditProduct">Edit Product</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Javascript -->
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/libs/simple-datatables/umd/simple-datatables.js"></script>
    <script src="assets/js/pages/datatable.init.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/theme-manager.js"></script>

    <!-- Delete Product Function -->
    <script>
        function deleteProduct(id) {
            if (confirm('Are you sure you want to delete this product?')) {
                window.location.href = 'delete-product.php?id=' + id;
            }
        }

        // Select all checkbox functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="check"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActionBar();
        });

        // Individual checkbox change handler
        document.addEventListener('change', function(e) {
            if (e.target.name === 'check') {
                updateBulkActionBar();
                
                // Update select-all checkbox state
                const checkboxes = document.querySelectorAll('input[name="check"]');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
                document.getElementById('select-all').checked = allChecked;
                document.getElementById('select-all').indeterminate = anyChecked && !allChecked;
            }
        });

        function updateBulkActionBar() {
            const checkboxes = document.querySelectorAll('input[name="check"]:checked');
            const count = checkboxes.length;
            const bulkActionBar = document.getElementById('bulkActionBar');
            
            if (count > 0) {
                bulkActionBar.classList.remove('d-none');
                document.getElementById('selectedCount').textContent = count;
            } else {
                bulkActionBar.classList.add('d-none');
            }
        }

        function getSelectedProductIds() {
            const checkboxes = document.querySelectorAll('input[name="check"]:checked');
            const ids = [];
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const viewBtn = row.querySelector('.btn-view-product');
                if (viewBtn) {
                    ids.push(viewBtn.getAttribute('data-id'));
                }
            });
            return ids;
        }

        function performBulkAction(action) {
            const ids = getSelectedProductIds();
            if (ids.length === 0) return;

            console.log('Performing bulk action:', action, 'on IDs:', ids);

            const actionText = {
                'activate': 'activate',
                'deactivate': 'deactivate',
                'delete': 'delete'
            };

            if (action === 'delete') {
                if (!confirm(`Are you sure you want to ${actionText[action]} ${ids.length} product(s)? This action cannot be undone.`)) {
                    return;
                }
            } else {
                if (!confirm(`Are you sure you want to ${actionText[action]} ${ids.length} product(s)?`)) {
                    return;
                }
            }

            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'products.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = action;
            form.appendChild(actionInput);

            ids.forEach(id => {
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'product_ids[]';
                idInput.value = id;
                form.appendChild(idInput);
            });

            console.log('Form data:', new FormData(form));
            document.body.appendChild(form);
            form.submit();
        }

        // Bulk action button handlers
        document.getElementById('bulkActivate').addEventListener('click', function() {
            performBulkAction('activate');
        });

        document.getElementById('bulkDeactivate').addEventListener('click', function() {
            performBulkAction('deactivate');
        });

        document.getElementById('bulkDelete').addEventListener('click', function() {
            performBulkAction('delete');
        });

        document.getElementById('bulkCancel').addEventListener('click', function() {
            document.querySelectorAll('input[name="check"]').forEach(cb => cb.checked = false);
            document.getElementById('select-all').checked = false;
            updateBulkActionBar();
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // View Product Modal
            document.querySelectorAll('.btn-view-product').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var productId = this.getAttribute('data-id');
                    if (productId) {
                        // Show modal
                        var modal = new bootstrap.Modal(document.getElementById('productPreviewModal'));
                        modal.show();
                        
                        // Reset content
                        document.getElementById('productPreviewContent').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                        
                        // Fetch product details
                        fetch('get-product-details.php?id=' + productId)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('HTTP error! status: ' + response.status);
                                }
                                return response.json();
                            })
                            .then(data => {
                                console.log('Product data:', data);
                                if (data.success) {
                                    displayProductPreview(data.product);
                                } else {
                                    document.getElementById('productPreviewContent').innerHTML = '<div class="alert alert-danger">Failed to load product details: ' + (data.message || 'Unknown error') + '</div>';
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                document.getElementById('productPreviewContent').innerHTML = '<div class="alert alert-danger">Error loading product details: ' + error.message + '</div>';
                            });
                        
                        // Set edit button
                        document.getElementById('btnEditProduct').onclick = function() {
                            window.location.href = 'edit-product.php?id=' + productId;
                        };
                    }
                });
            });
            
            function displayProductPreview(product) {
                let html = '<div class="row g-4">';
                
                // Left side - Images Section
                html += '<div class="col-md-5">';
                
                // Main Image
                html += '<div class="mb-3">';
                if (product.images && product.images.length > 0) {
                    let mainImgPath = product.images[0].replace('./', '../');
                    if (!mainImgPath.startsWith('../') && !mainImgPath.startsWith('http')) {
                        mainImgPath = '../' + mainImgPath;
                    }
                    html += '<img id="mainProductImage" src="' + mainImgPath + '" class="img-fluid rounded border" alt="Product Image" style="width: 100%; height: 400px; object-fit: contain; background: #f8f9fa;" onerror="this.src=\'assets/images/products/default.png\'">';
                } else {
                    html += '<img id="mainProductImage" src="assets/images/products/default.png" class="img-fluid rounded border" alt="No Image" style="width: 100%; height: 400px; object-fit: contain; background: #f8f9fa;">';
                }
                html += '</div>';
                
                // Thumbnail Images
                if (product.images && product.images.length > 1) {
                    html += '<div class="d-flex gap-2 flex-wrap">';
                    product.images.forEach((img, index) => {
                        let imgPath = img.replace('./', '../');
                        if (!imgPath.startsWith('../') && !imgPath.startsWith('http')) {
                            imgPath = '../' + imgPath;
                        }
                        html += '<img src="' + imgPath + '" class="rounded border thumbnail-img" alt="Thumbnail" style="width: 60px; height: 60px; object-fit: cover; cursor: pointer;" onclick="document.getElementById(\'mainProductImage\').src = this.src">';
                    });
                    html += '</div>';
                }
                
                // Video Section
                if (product.youtube_video_id) {
                    html += '<div class="mt-3">';
                    html += '<div class="ratio ratio-16x9">';
                    html += '<iframe src="https://www.youtube.com/embed/' + product.youtube_video_id + '" allowfullscreen class="rounded"></iframe>';
                    html += '</div>';
                    html += '</div>';
                }
                html += '</div>';
                
                // Right side - Details Section
                html += '<div class="col-md-7">';
                
                // Product Title
                html += '<h3 class="mb-2">' + product.name + '</h3>';
                
                // Category
                html += '<p class="text-muted mb-2"><i class="fas fa-tag me-1"></i>' + (product.category_name || 'No Category') + '</p>';
                
                // Status Badge
                if (product.status === 'active') {
                    html += '<span class="badge bg-success mb-3"><i class="fas fa-check me-1"></i> Active</span>';
                } else if (product.status === 'inactive') {
                    html += '<span class="badge bg-danger mb-3"><i class="fas fa-xmark me-1"></i> Inactive</span>';
                } else {
                    html += '<span class="badge bg-secondary mb-3"><i class="fas fa-box-archive me-1"></i> Draft</span>';
                }
                
                html += '<hr>';
                
                // Price Section
                html += '<div class="mb-3">';
                html += '<h2 class="text-danger fw-bold mb-1">₹' + parseFloat(product.price).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</h2>';
                html += '<p class="text-muted small mb-0">Inclusive of all taxes</p>';
                html += '</div>';
                
                html += '<hr>';
                
                // Description
                html += '<div class="mb-3">';
                html += '<h6 class="fw-bold mb-2">Product Description</h6>';
                html += '<p class="text-muted">' + product.description + '</p>';
                html += '</div>';
                
                // Product Details Grid
                html += '<div class="row g-3 mb-3">';
                
                // Colors
                if (product.colors && product.colors.length > 0) {
                    html += '<div class="col-6">';
                    html += '<div class="p-3 border rounded bg-light">';
                    html += '<h6 class="fw-bold mb-2 small">Available Colors</h6>';
                    product.colors.forEach(color => {
                        html += '<span class="badge bg-secondary me-1 mb-1">' + color.name + '</span>';
                    });
                    html += '</div>';
                    html += '</div>';
                }
                
                // Sizes
                if (product.sizes && product.sizes.length > 0) {
                    html += '<div class="col-6">';
                    html += '<div class="p-3 border rounded bg-light">';
                    html += '<h6 class="fw-bold mb-2 small">Available Sizes</h6>';
                    product.sizes.forEach(size => {
                        html += '<span class="badge bg-secondary me-1 mb-1">' + size + '</span>';
                    });
                    html += '</div>';
                    html += '</div>';
                }
                
                // Stock
                html += '<div class="col-6">';
                html += '<div class="p-3 border rounded bg-light">';
                html += '<h6 class="fw-bold mb-1 small">Stock Availability</h6>';
                let stockQty = (product.stock !== null && product.stock !== undefined ? product.stock : 0);
                if (stockQty > 0) {
                    html += '<p class="mb-0 text-success"><i class="fas fa-check-circle me-1"></i>' + stockQty + ' units available</p>';
                } else {
                    html += '<p class="mb-0 text-danger"><i class="fas fa-times-circle me-1"></i>Out of stock</p>';
                }
                html += '</div>';
                html += '</div>';
                
                // Created Info
                html += '<div class="col-6">';
                html += '<div class="p-3 border rounded bg-light">';
                html += '<h6 class="fw-bold mb-1 small">Created</h6>';
                html += '<p class="mb-0 small text-muted">' + product.created_at + '</p>';
                html += '<p class="mb-0 small text-muted">by ' + product.created_by + '</p>';
                html += '</div>';
                html += '</div>';
                
                html += '</div>'; // End row
                
                html += '</div>'; // End col-md-7
                html += '</div>'; // End row
                
                document.getElementById('productPreviewContent').innerHTML = html;
            }
            document.querySelectorAll('.btn-edit-product').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var productId = this.getAttribute('data-id');
                    if (productId) {
                        window.location.href = 'edit-product.php?id=' + productId;
                    }
                });
            });
            document.querySelectorAll('.btn-delete-product').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var productId = this.getAttribute('data-id');
                    if (productId && confirm('Are you sure you want to delete this product?')) {
                        window.location.href = 'delete-product.php?id=' + productId;
                    }
                });
            });
        });
    </script>
</body>

</html>