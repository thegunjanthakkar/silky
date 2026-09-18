<?php
// Basic session check for dashboard access
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if this is an AJAX toggle bestseller request
$isAjaxToggle = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_bestseller');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if ($isAjaxToggle) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    header('Location: login.php');
    exit;
}
// Check permission for this page
require_once 'includes/permission-manager.php';
if ($isAjaxToggle) {
    if (!hasFileAccess('products.php')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }
} else {
    checkPageAccess();
}
require_once '../db_config.php';

// Handle AJAX toggle for bestseller directly in products.php
if ($isAjaxToggle) {
    header('Content-Type: application/json');
    $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit;
    }

    $checkSql = "SELECT id, name, is_bestseller FROM products WHERE id = $productId LIMIT 1";
    $res = mysqli_query($conn, $checkSql);
    if (!$res || mysqli_num_rows($res) === 0) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    $product = mysqli_fetch_assoc($res);

    if (isset($_POST['is_bestseller'])) {
        $newStatus = intval($_POST['is_bestseller']) === 1 ? 1 : 0;
    } else {
        $newStatus = empty($product['is_bestseller']) ? 1 : 0;
    }

    $updateSql = "UPDATE products SET is_bestseller = $newStatus WHERE id = $productId";
    if (mysqli_query($conn, $updateSql)) {
        $productName = htmlspecialchars($product['name']);
        $msg = $newStatus === 1 
            ? "'$productName' added to Best Sellers." 
            : "'$productName' removed from Best Sellers.";
        echo json_encode([
            'success' => true,
            'is_bestseller' => $newStatus,
            'product_id' => $productId,
            'message' => $msg
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . mysqli_error($conn)
        ]);
    }
    exit;
}

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
                
            case 'mark_bestseller':
                $sql = "UPDATE products SET is_bestseller = 1 WHERE id IN ($placeholders)";
                $successMessage = "$count product(s) marked as Best Seller successfully!";
                break;

            case 'unmark_bestseller':
                $sql = "UPDATE products SET is_bestseller = 0 WHERE id IN ($placeholders)";
                $successMessage = "$count product(s) removed from Best Seller successfully!";
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
                                                <div class="btn-group flex-wrap gap-1" role="group">
                                                    <button type="button" class="btn btn-success btn-sm" id="bulkActivate">
                                                        <i class="fas fa-check me-1"></i> Activate
                                                    </button>
                                                    <button type="button" class="btn btn-warning btn-sm" id="bulkDeactivate">
                                                        <i class="fas fa-pause me-1"></i> Inactive
                                                    </button>
                                                    <button type="button" class="btn btn-primary btn-sm" id="bulkMarkBestSeller">
                                                        <i class="fas fa-star me-1"></i> Add to Best Seller
                                                    </button>
                                                    <button type="button" class="btn btn-soft-warning btn-sm" id="bulkUnmarkBestSeller">
                                                        <i class="far fa-star me-1"></i> Remove Best Seller
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm" id="bulkDelete">
                                                        <i class="fas fa-trash me-1"></i> Delete
                                                    </button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm ms-1" id="bulkCancel">
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
                                                <th class="text-center">Best Seller</th>
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
                                                                    id="customCheck<?php echo $serial; ?>" value="<?php echo $row['id']; ?>">
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
                                                        <td class="text-center">
                                                            <div class="form-check form-switch form-switch-warning d-inline-flex align-items-center justify-content-center mb-1">
                                                                <input class="form-check-input bestseller-toggle" type="checkbox" role="switch"
                                                                    id="bestseller_switch_<?php echo $row['id']; ?>"
                                                                    data-product-id="<?php echo $row['id']; ?>"
                                                                    <?php echo (!empty($row['is_bestseller']) && $row['is_bestseller'] == 1) ? 'checked' : ''; ?>
                                                                    style="cursor: pointer;"
                                                                    title="<?php echo (!empty($row['is_bestseller']) && $row['is_bestseller'] == 1) ? 'Click to remove from Best Seller' : 'Click to add to Best Seller'; ?>">
                                                            </div>
                                                            <div class="bestseller-badge-wrap" id="bestseller_badge_<?php echo $row['id']; ?>">
                                                                <?php if (!empty($row['is_bestseller']) && $row['is_bestseller'] == 1): ?>
                                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-11"><i class="fas fa-star me-1"></i>Yes</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-11">No</span>
                                                                <?php endif; ?>
                                                            </div>
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
                                                        </td>
                                                    </tr>
                                                    <?php $serial++; ?>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="11" class="text-center py-4">
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
                if (checkbox.value) {
                    ids.push(checkbox.value);
                } else {
                    const row = checkbox.closest('tr');
                    const viewBtn = row ? row.querySelector('.btn-view-product') : null;
                    if (viewBtn) {
                        ids.push(viewBtn.getAttribute('data-id'));
                    }
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
                'delete': 'delete',
                'mark_bestseller': 'add to Best Seller',
                'unmark_bestseller': 'remove from Best Seller'
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

        const bulkMarkBsBtn = document.getElementById('bulkMarkBestSeller');
        if (bulkMarkBsBtn) {
            bulkMarkBsBtn.addEventListener('click', function() {
                performBulkAction('mark_bestseller');
            });
        }

        const bulkUnmarkBsBtn = document.getElementById('bulkUnmarkBestSeller');
        if (bulkUnmarkBsBtn) {
            bulkUnmarkBsBtn.addEventListener('click', function() {
                performBulkAction('unmark_bestseller');
            });
        }

        document.getElementById('bulkDelete').addEventListener('click', function() {
            performBulkAction('delete');
        });

        document.getElementById('bulkCancel').addEventListener('click', function() {
            document.querySelectorAll('input[name="check"]').forEach(cb => cb.checked = false);
            document.getElementById('select-all').checked = false;
            updateBulkActionBar();
        });

        // Toast notification helper
        function showToast(msg, type = 'success') {
            const el = document.getElementById('adminToast');
            const msgEl = document.getElementById('adminToastMsg');
            if (!el || !msgEl) return;
            el.className = `toast align-items-center border-0 shadow-lg text-white ${type === 'success' ? 'bg-success' : (type === 'error' ? 'bg-danger' : 'bg-primary')}`;
            msgEl.textContent = msg;
            bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
        }

        // Individual Best Seller switch AJAX toggle (Delegated event listener)
        document.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('bestseller-toggle')) {
                const toggle = e.target;
                const productId = toggle.getAttribute('data-product-id');
                const isBestseller = toggle.checked ? 1 : 0;
                const badgeWrap = document.getElementById('bestseller_badge_' + productId);

                toggle.disabled = true;

                fetch('products.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'toggle_bestseller',
                        product_id: productId,
                        is_bestseller: isBestseller
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    toggle.disabled = false;
                    if (data.success) {
                        if (data.is_bestseller == 1) {
                            if (badgeWrap) badgeWrap.innerHTML = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-11"><i class="fas fa-star me-1"></i>Yes</span>';
                            toggle.setAttribute('title', 'Click to remove from Best Seller');
                            showToast(data.message || 'Product added to Best Seller!', 'success');
                        } else {
                            if (badgeWrap) badgeWrap.innerHTML = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-11">No</span>';
                            toggle.setAttribute('title', 'Click to add to Best Seller');
                            showToast(data.message || 'Product removed from Best Seller!', 'info');
                        }
                    } else {
                        // Revert
                        toggle.checked = !toggle.checked;
                        showToast(data.message || 'Failed to update Best Seller status.', 'error');
                    }
                })
                .catch(err => {
                    toggle.disabled = false;
                    toggle.checked = !toggle.checked;
                    console.error('Error toggling bestseller:', err);
                    showToast('Network error while updating Best Seller status.', 'error');
                });
            }
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
                    html += '<img id="mainProductImage" src="' + mainImgPath + '" class="img-fluid rounded border" alt="Product Image" style="width: 100%; height: 400px; object-fit: contain; background: #f8f9fa;" onerror="this.onerror=null; this.src=\'assets/images/products/default.png\'">';
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
                    html += '<span class="badge bg-success mb-3 me-2"><i class="fas fa-check me-1"></i> Active</span>';
                } else if (product.status === 'inactive') {
                    html += '<span class="badge bg-danger mb-3 me-2"><i class="fas fa-xmark me-1"></i> Inactive</span>';
                } else {
                    html += '<span class="badge bg-secondary mb-3 me-2"><i class="fas fa-box-archive me-1"></i> Draft</span>';
                }

                if (product.is_bestseller == 1) {
                    html += '<span class="badge bg-warning text-dark mb-3"><i class="fas fa-star me-1"></i> Best Seller</span>';
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
                
                // Highlights Section
                if (product.custom_highlights_enabled) {
                    html += '<div class="col-12">';
                    html += '<div class="p-3 border rounded">';
                    html += '<div class="d-flex justify-content-between align-items-center mb-2">';
                    html += '<h6 class="fw-bold mb-0 small"><i class="bi bi-stars text-warning me-1"></i>Overview Highlights</h6>';
                    html += '<span class="badge bg-success-subtle text-success border border-success-subtle">Customized</span>';
                    html += '</div>';
                    if (product.custom_highlights_title) {
                        html += '<p class="small text-muted mb-2">Heading: <strong>' + escapeHtml(product.custom_highlights_title) + '</strong></p>';
                    }
                    if (product.custom_highlights_cards && product.custom_highlights_cards.length > 0) {
                        html += '<div class="row g-2">';
                        product.custom_highlights_cards.forEach(card => {
                            html += '<div class="col-6"><div class="p-2 border rounded small h-100"><i class="' + escapeHtml(card.icon || 'bi bi-gem') + ' me-1 text-primary"></i><strong>' + escapeHtml(card.title || '') + '</strong><div class="text-muted fs-11 mt-1 text-truncate" title="' + escapeHtml(card.desc || '') + '">' + escapeHtml(card.desc || '') + '</div></div></div>';
                        });
                        html += '</div>';
                    }
                    html += '</div>';
                    html += '</div>';
                }

                // Care Instructions Section
                if (product.custom_care_enabled) {
                    html += '<div class="col-12">';
                    html += '<div class="p-3 border rounded">';
                    html += '<div class="d-flex justify-content-between align-items-center mb-2">';
                    html += '<h6 class="fw-bold mb-0 small"><i class="bi bi-droplet-half text-info me-1"></i>Care Instructions</h6>';
                    html += '<span class="badge bg-success-subtle text-success border border-success-subtle">Customized</span>';
                    html += '</div>';
                    if (product.custom_care_title) {
                        html += '<p class="small text-muted mb-2">Heading: <strong>' + escapeHtml(product.custom_care_title) + '</strong></p>';
                    }
                    if (product.custom_care_cards && product.custom_care_cards.length > 0) {
                        html += '<div class="row g-2">';
                        product.custom_care_cards.forEach(card => {
                            let styleAttr = card.color ? ' style="color: ' + escapeHtml(card.color) + ';"' : ' class="text-info"';
                            html += '<div class="col-6"><div class="p-2 border rounded small h-100"><i class="' + escapeHtml(card.icon || 'bi bi-droplet-half') + ' me-1"' + styleAttr + '></i><strong>' + escapeHtml(card.title || '') + '</strong><div class="text-muted fs-11 mt-1 text-truncate" title="' + escapeHtml(card.desc || '') + '">' + escapeHtml(card.desc || '') + '</div></div></div>';
                        });
                        html += '</div>';
                    }
                    html += '</div>';
                    html += '</div>';
                }

                // Addon Products Section
                if (product.addon_products && product.addon_products.length > 0) {
                    html += '<div class="col-12">';
                    html += '<div class="p-3 border rounded bg-light">';
                    html += '<div class="d-flex justify-content-between align-items-center mb-2">';
                    html += '<h6 class="fw-bold mb-0 small"><i class="fas fa-puzzle-piece text-primary me-1"></i>Add-on Products (' + product.addon_products.length + ')</h6>';
                    html += '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">' + escapeHtml(product.addon_title || 'Frequently Added Together') + '</span>';
                    html += '</div>';
                    html += '<div class="row g-2">';
                    product.addon_products.forEach(addon => {
                        let img = addon.image || 'assets/images/products/default.png';
                        if (!img.startsWith('../') && !img.startsWith('http')) {
                            img = '../' + img;
                        }
                        let priceDisplay = '₹' + parseFloat(addon.regular_price).toLocaleString('en-IN');
                        if (addon.custom_price !== null && addon.custom_price !== undefined) {
                            priceDisplay = '<span class="text-success fw-bold">₹' + parseFloat(addon.custom_price).toLocaleString('en-IN') + '</span> <span class="text-muted text-decoration-line-through fs-11">₹' + parseFloat(addon.regular_price).toLocaleString('en-IN') + '</span>';
                        }
                        html += '<div class="col-6"><div class="d-flex align-items-center gap-2 p-2 border rounded bg-white small h-100"><img src="' + img + '" class="rounded border" style="width:36px;height:36px;object-fit:cover;" onerror="this.onerror=null; this.src=\'../assets/images/products/default.png\'"><div class="overflow-hidden flex-grow-1"><div class="text-truncate fw-semibold">' + escapeHtml(addon.name) + '</div><div class="small">' + priceDisplay + '</div></div></div></div>';
                    });
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                }

                html += '</div>'; // End row
                
                html += '</div>'; // End col-md-7
                html += '</div>'; // End row
                
                document.getElementById('productPreviewContent').innerHTML = html;
            }

            function escapeHtml(str) {
                return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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

    <!-- Toast Notification -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
        <div id="adminToast" class="toast align-items-center border-0 shadow-lg" role="alert" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-semibold" id="adminToastMsg"></div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
</body>

</html>