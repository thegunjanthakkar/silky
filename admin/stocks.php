<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Handle form submissions before any output
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_stock'])) {
        require_once '../db_config.php';

        $product_id = (int)$_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        $updated_by = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? 1;

        // Check if product already has stock record
        $checkSQL = "SELECT * FROM stock WHERE product_id = $product_id";
        $checkResult = mysqli_query($conn, $checkSQL);

        if (mysqli_num_rows($checkResult) > 0) {
            // Update existing stock
            $updateSQL = "UPDATE stock SET quantity = quantity + $quantity, updated_by = $updated_by WHERE product_id = $product_id";
            if (mysqli_query($conn, $updateSQL)) {
                $_SESSION['success'] = "Stock quantity updated successfully!";
                
                // Check if the new stock is > 0
                $checkNewStockSQL = "SELECT quantity FROM stock WHERE product_id = $product_id";
                $checkNewStockRes = mysqli_query($conn, $checkNewStockSQL);
                $newStockRow = mysqli_fetch_assoc($checkNewStockRes);
                if ($newStockRow['quantity'] > 0) {
                    require_once '../includes/process-notifications.php';
                    checkAndSendStockNotifications($conn, $product_id);
                }
            } else {
                $_SESSION['error'] = "Error updating stock: " . mysqli_error($conn);
            }
        } else {
            // Insert new stock record
            $insertSQL = "INSERT INTO stock (product_id, quantity, updated_by) VALUES ($product_id, $quantity, $updated_by)";
            if (mysqli_query($conn, $insertSQL)) {
                $_SESSION['success'] = "Stock added successfully!";
                if ($quantity > 0) {
                    require_once '../includes/process-notifications.php';
                    checkAndSendStockNotifications($conn, $product_id);
                }
            } else {
                $_SESSION['error'] = "Error adding stock: " . mysqli_error($conn);
            }
        }
        header('Location: stocks.php');
        exit;
    }

    if (isset($_POST['edit_stock'])) {
        require_once '../db_config.php';

        $stock_id = (int)$_POST['stock_id'];
        $product_id = (int)$_POST['edit_product_id'];
        $quantity = (int)$_POST['edit_quantity'];
        $updated_by = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? 1;

        // Check if another product already has this stock record (excluding current)
        $checkSQL = "SELECT * FROM stock WHERE product_id = $product_id AND id != $stock_id";
        $checkResult = mysqli_query($conn, $checkSQL);

        if (mysqli_num_rows($checkResult) > 0) {
            $_SESSION['error'] = "This product already has a stock record. Please update the existing record instead.";
        } else {
            // Update stock record
            $updateSQL = "UPDATE stock SET product_id = $product_id, quantity = $quantity, updated_by = $updated_by WHERE id = $stock_id";
            if (mysqli_query($conn, $updateSQL)) {
                $_SESSION['success'] = "Stock updated successfully!";
                if ($quantity > 0) {
                    require_once '../includes/process-notifications.php';
                    checkAndSendStockNotifications($conn, $product_id);
                }
            } else {
                $_SESSION['error'] = "Error updating stock: " . mysqli_error($conn);
            }
        }
        header('Location: stocks.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <title>Stocks | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta content="" name="description" />
    <meta content="" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <!-- App favicon -->
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">

    <!-- DataTable CSS -->
    <link href="assets/libs/simple-datatables/style.css" rel="stylesheet" type="text/css" />

    <!-- App css -->
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
    <?php
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
                            <h4 class="page-title">Stock Management</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="#">Stock Management</a></li>
                                    <li class="breadcrumb-item active">Stocks</li>
                                </ol>
                            </div>
                        </div><!--end page-title-box-->
                    </div><!--end col-->
                </div><!--end row-->

                <!-- Flash Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php
                // Get stock statistics
                require_once '../db_config.php';

                $totalProductsQuery = "SELECT COUNT(*) as total FROM products";
                $totalProductsResult = mysqli_query($conn, $totalProductsQuery);
                $totalProducts = mysqli_fetch_assoc($totalProductsResult)['total'];

                $inStockQuery = "SELECT COUNT(DISTINCT p.id) as in_stock FROM products p LEFT JOIN stock s ON p.id = s.product_id WHERE p.status = 'active' AND s.quantity > 0";
                $inStockResult = mysqli_query($conn, $inStockQuery);
                $inStock = mysqli_fetch_assoc($inStockResult)['in_stock'];

                $outOfStockQuery = "SELECT COUNT(DISTINCT p.id) as out_of_stock FROM products p LEFT JOIN stock s ON p.id = s.product_id WHERE p.status = 'active' AND (s.quantity IS NULL OR s.quantity = 0)";
                $outOfStockResult = mysqli_query($conn, $outOfStockQuery);
                $outOfStock = mysqli_fetch_assoc($outOfStockResult)['out_of_stock'];

                $lowStockQuery = "SELECT COUNT(DISTINCT p.id) as low_stock FROM products p LEFT JOIN stock s ON p.id = s.product_id WHERE p.status = 'active' AND s.quantity > 0 AND s.quantity < 5";
                $lowStockResult = mysqli_query($conn, $lowStockQuery);
                $lowStock = mysqli_fetch_assoc($lowStockResult)['low_stock'];
                ?>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="row justify-content-center">
                            <div class="col-md-6 col-lg-3">
                                <div class="card report-card">
                                    <div class="card-body">
                                        <div class="row d-flex justify-content-center">
                                            <div class="col">
                                                <p class="text-dark mb-0 fw-semibold">Total Products</p>
                                                <h3 class="my-1 fs-20"><?php echo $totalProducts; ?></h3>
                                                <p class="mb-0 text-truncate text-muted"><span class="text-primary"><i
                                                            class="iconoir-package"></i>100%</span> All Products</p>
                                            </div>
                                            <div class="col-auto align-self-center">
                                                <div
                                                    class="flex-shrink-0 bg-primary-subtle text-primary thumb-md rounded-circle">
                                                    <i class="iconoir-package fs-4"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div><!--end card-body-->
                                </div><!--end card-->
                            </div> <!--end col-->
                            <div class="col-md-6 col-lg-3">
                                <div class="card report-card">
                                    <div class="card-body">
                                        <div class="row d-flex justify-content-center">
                                            <div class="col">
                                                <p class="text-dark mb-0 fw-semibold">In Stocks</p>
                                                <h3 class="my-1 fs-20"><?php echo $inStock; ?></h3>
                                                <p class="mb-0 text-truncate text-muted"><span class="text-success"><i
                                                            class="las la-trending-up"></i><?php echo $totalProducts > 0 ? round(($inStock/$totalProducts)*100, 1) : 0; ?>%</span> Available</p>
                                            </div>
                                            <div class="col-auto align-self-center">
                                                <div
                                                    class="flex-shrink-0 bg-success-subtle text-success thumb-md rounded-circle">
                                                    <i class="iconoir-check-circle fs-4"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div><!--end card-body-->
                                </div><!--end card-->
                            </div> <!--end col-->
                            <div class="col-md-6 col-lg-3">
                                <div class="card report-card">
                                    <div class="card-body">
                                        <div class="row d-flex justify-content-center">
                                            <div class="col">
                                                <p class="text-dark mb-0 fw-semibold">Out of Stocks</p>
                                                <h3 class="my-1 fs-20"><?php echo $outOfStock; ?></h3>
                                                <p class="mb-0 text-truncate text-muted"><span class="text-danger"><i
                                                            class="las la-trending-down"></i><?php echo $totalProducts > 0 ? round(($outOfStock/$totalProducts)*100, 1) : 0; ?>%</span> Need Restock</p>
                                            </div>
                                            <div class="col-auto align-self-center">
                                                <div
                                                    class="flex-shrink-0 bg-danger-subtle text-danger thumb-md rounded-circle">
                                                    <i class="iconoir-xmark-circle fs-4"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div><!--end card-body-->
                                </div><!--end card-->
                            </div> <!--end col-->
                            <div class="col-md-6 col-lg-3">
                                <div class="card report-card">
                                    <div class="card-body">
                                        <div class="row d-flex justify-content-center">
                                            <div class="col">
                                                <p class="text-dark mb-0 fw-semibold">Low Stocks</p>
                                                <h3 class="my-1 fs-20"><?php echo $lowStock; ?></h3>
                                                <p class="mb-0 text-truncate text-muted"><span class="text-warning"><i
                                                            class="las la-exclamation-triangle"></i><?php echo $totalProducts > 0 ? round(($lowStock/$totalProducts)*100, 1) : 0; ?>%</span> Running Low</p>
                                            </div>
                                            <div class="col-auto align-self-center">
                                                <div
                                                    class="flex-shrink-0 bg-warning-subtle text-warning thumb-md rounded-circle">
                                                    <i class="iconoir-warning-triangle fs-4"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div><!--end card-body-->
                                </div><!--end card-->
                            </div> <!--end col-->
                        </div><!--end row-->
                    </div><!--end col-->
                </div><!--end row-->

                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h4 class="card-title">Stock Data</h4>
                                    </div><!--end col-->
                                    <div class="col-auto">
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStockModal">
                                            <i class="iconoir-plus me-2"></i>Add New Stock
                                        </button>
                                    </div><!--end col-->
                                </div> <!--end row-->
                            </div><!--end card-header-->
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table datatable" id="datatable_1">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Product Name</th>
                                                <th>Category</th>
                                                <th>Size</th>
                                                <th>Color</th>
                                                <th>Price</th>
                                                <th>Stock Qty</th>
                                                <th>Stock Status</th>
                                                <th>Product Status</th>
                                                <th data-type="date" data-format="DD/MM/YYYY">Last Updated</th>
                                                <th>Updated By</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            require_once 'includes/permission-manager.php';
                                            if (!isset($conn)) {
                                                require_once '../db_config.php';
                                            }

                                            // Fetch all variants grouped by product_id
                                            $variants_query = mysqli_query($conn, "
                                                SELECT pv.product_id, c.color_name, s.size_label, pv.stock_quantity 
                                                FROM product_variants pv 
                                                JOIN colors c ON pv.color_id = c.id 
                                                JOIN sizes s ON pv.size_id = s.id
                                            ");
                                            $all_variants = [];
                                            if ($variants_query) {
                                                while ($v = mysqli_fetch_assoc($variants_query)) {
                                                    $all_variants[$v['product_id']][] = $v;
                                                }
                                            }

                                            $result = mysqli_query($conn, "SELECT s.id as stock_id, s.product_id, s.quantity, s.last_updated, s.updated_by, p.id as product_id_main, p.name as product_name, p.size, p.color, p.price, p.status as product_status, c.name as category_name, CONCAT(au.first_name, ' ', au.last_name) as updated_by_name FROM products p LEFT JOIN stock s ON p.id = s.product_id LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN admin_users au ON s.updated_by = au.id WHERE p.status = 'active' ORDER BY p.name ASC");
                                            if ($result && mysqli_num_rows($result) > 0) {
                                                $rowIndex = 0;
                                                while ($row = mysqli_fetch_assoc($result)) {
                                                    $rowIndex++;

                                                    // Handle NULL values for stock data
                                                    $stock_id = $row['stock_id'];
                                                    $product_id = $row['product_id_main']; // Use the main product ID
                                                    $quantity = (int)($row['quantity'] ?? 0);
                                                    $has_stock_record = !is_null($stock_id);

                                                    $display_size = htmlspecialchars($row['size'] ?: 'N/A');
                                                    $display_color = htmlspecialchars($row['color'] ?: 'N/A');
                                                    
                                                    $variants = $all_variants[$product_id] ?? [];
                                                    $variant_count = count($variants);

                                                    if ($variant_count > 0) {
                                                        $first_variant = $variants[0];
                                                        
                                                        // Total quantity is the sum of variants' stock
                                                        $total_stock = 0;
                                                        foreach ($variants as $v) $total_stock += $v['stock_quantity'];
                                                        $quantity = $total_stock;
                                                        
                                                        $display_size = htmlspecialchars($first_variant['size_label']);
                                                        $display_color = htmlspecialchars($first_variant['color_name']);
                                                        
                                                        if ($variant_count > 1) {
                                                            $extra_count = $variant_count - 1;
                                                            $tooltip_text = "";
                                                            for ($i = 1; $i < $variant_count; $i++) {
                                                                $tooltip_text .= htmlspecialchars($variants[$i]['size_label']) . " - " . htmlspecialchars($variants[$i]['color_name']) . " (Qty: " . $variants[$i]['stock_quantity'] . ")&#10;";
                                                            }
                                                            $display_size .= ' <span class="badge bg-secondary ms-1" title="' . $tooltip_text . '">+' . $extra_count . '</span>';
                                                            $display_color .= ' <span class="badge bg-secondary ms-1" title="' . $tooltip_text . '">+' . $extra_count . '</span>';
                                                        }
                                                    }

                                                    // Determine stock status
                                                    if ($quantity == 0) {
                                                        $status = '<span class="badge bg-danger-subtle text-danger"><i class="fas fa-xmark me-1"></i>Out of Stock</span>';
                                                    } elseif ($quantity < 5) {
                                                        $status = '<span class="badge bg-warning-subtle text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Low Stock</span>';
                                                    } else {
                                                        $status = '<span class="badge bg-success-subtle text-success"><i class="fas fa-check me-1"></i>In Stock</span>';
                                                    }

                                                    echo '<tr>';
                                                    echo '<td>' . $rowIndex . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['product_name'] ?: 'Unknown Product') . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['category_name'] ?: 'No Category') . '</td>';
                                                    echo '<td>' . $display_size . '</td>';
                                                    echo '<td>' . $display_color . '</td>';
                                                    echo '<td>₹' . number_format($row['price'], 2) . '</td>';
                                                    echo '<td><span class="fw-semibold">' . $quantity . '</span></td>';
                                                    echo '<td>' . $status . '</td>';
                                                    echo '<td>';
                                                    if ($row['product_status'] === 'active') {
                                                        echo '<span class="badge bg-success-subtle text-success"><i class="fas fa-check me-1"></i>Active</span>';
                                                    } elseif ($row['product_status'] === 'inactive') {
                                                        echo '<span class="badge bg-danger-subtle text-danger"><i class="fas fa-xmark me-1"></i>Inactive</span>';
                                                    } else {
                                                        echo '<span class="badge bg-secondary-subtle text-secondary"><i class="fas fa-box-archive me-1"></i>Draft</span>';
                                                    }
                                                    echo '</td>';
                                                    echo '<td>' . ($row['last_updated'] ? date('d/m/Y h:i', strtotime($row['last_updated'])) : '-') . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['updated_by_name'] ?: 'System') . '</td>';
                                                    echo '<td>';
                                                    echo '<div class="btn-group" role="group">';
                                                    echo '<button type="button" class="btn btn-sm btn-soft-primary" title="View"><i class="fas fa-eye"></i></button>';
                                                    
                                                    if ($variant_count > 0) {
                                                        // Product has variants, direct Edit button to edit-product.php
                                                        echo '<a href="edit-product.php?id=' . $product_id . '#variants-section" class="btn btn-sm btn-soft-secondary" title="Edit Variants Stock"><i class="fas fa-edit"></i></a>';
                                                        if ($has_stock_record) {
                                                            echo '<button type="button" class="btn btn-sm btn-soft-danger btn-delete-stock" data-id="' . $stock_id . '" title="Delete"><i class="fas fa-trash"></i></button>';
                                                        }
                                                    } else {
                                                        // No variants, use standard modal buttons
                                                        if ($has_stock_record) {
                                                            echo '<button type="button" class="btn btn-sm btn-soft-secondary btn-edit-stock" data-id="' . $stock_id . '" data-product-id="' . $product_id . '" data-quantity="' . $quantity . '" data-product-name="' . htmlspecialchars($row['product_name']) . '" data-size="' . htmlspecialchars($row['size']) . '" data-color="' . htmlspecialchars($row['color']) . '" data-category="' . htmlspecialchars($row['category_name']) . '" title="Edit Stock"><i class="fas fa-edit"></i></button>';
                                                            echo '<button type="button" class="btn btn-sm btn-soft-danger btn-delete-stock" data-id="' . $stock_id . '" title="Delete"><i class="fas fa-trash"></i></button>';
                                                        } else {
                                                            echo '<button type="button" class="btn btn-sm btn-soft-success btn-add-stock" data-product-id="' . $product_id . '" data-product-name="' . htmlspecialchars($row['product_name']) . '" data-size="' . htmlspecialchars($row['size']) . '" data-color="' . htmlspecialchars($row['color']) . '" data-category="' . htmlspecialchars($row['category_name']) . '" title="Add Stock"><i class="fas fa-plus"></i></button>';
                                                        }
                                                    }
                                                    
                                                    echo '</div>';
                                                    echo '</td>';
                                                    echo '</tr>';
                                                    echo '</div>';
                                                    echo '</td>';
                                                    echo '</tr>';
                                                }
                                                mysqli_free_result($result);
                                            } else {
                                                echo '<tr><td colspan="12" class="text-center text-muted">No stock data found</td></tr>';
                                            }
                                            ?>

                                        </tbody>
                                    </table>
                                </div>
                            </div><!--end card-body-->
                        </div><!--end card-->
                    </div> <!--end col-->
                </div><!--end row-->

                <!-- Add Stock Modal -->
                <div class="modal fade" id="addStockModal" tabindex="-1" aria-labelledby="addStockModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addStockModalLabel">Add New Stock</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="">
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="product_id" class="form-label">Select Product *</label>
                                                <select class="form-select" id="product_id" name="product_id" required>
                                                    <option value="">Choose a product...</option>
                                                    <?php
                                                    // Fetch active products with category info (similar to products.php)
                                                    $products_sql = "SELECT p.id, p.name, p.size, p.color, c.name as category_name 
                                                                   FROM products p 
                                                                   LEFT JOIN categories c ON p.category_id = c.id 
                                                                   WHERE p.status = 'active' 
                                                                   ORDER BY p.name ASC";
                                                    $products_result = mysqli_query($conn, $products_sql);
                                                    while ($product = mysqli_fetch_assoc($products_result)):
                                                    ?>
                                                        <option value="<?php echo $product['id']; ?>">
                                                            <?php echo htmlspecialchars($product['name'] . ' (' . $product['size'] . ' - ' . $product['color'] . ') - ' . $product['category_name']); ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="quantity" class="form-label">Quantity *</label>
                                                <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="1" required>
                                                <div class="form-text">Enter the quantity to add</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Stock Status Preview</label>
                                                <div id="add_status_preview" class="p-2 border rounded">
                                                    <span id="add_status_badge" class="badge bg-success-subtle text-success">
                                                        <i class="fas fa-check me-1"></i>In Stock
                                                    </span>
                                                </div>
                                                <div class="form-text small text-muted">
                                                    Status will update automatically based on quantity
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Note:</strong> If the selected product already has stock, the quantity will be added to the existing stock. Otherwise, a new stock entry will be created.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </button>
                                    <button type="submit" name="add_stock" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Add Stock
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Edit Stock Modal -->
                <div class="modal fade" id="editStockModal" tabindex="-1" aria-labelledby="editStockModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editStockModalLabel">Edit Stock</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="">
                                <input type="hidden" id="edit_stock_id" name="stock_id">
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="edit_product_id" class="form-label">Select Product *</label>
                                                <select class="form-select" id="edit_product_id" name="edit_product_id" required>
                                                    <option value="">Choose a product...</option>
                                                    <?php
                                                    // Fetch active products with category info (similar to products.php)
                                                    $products_sql = "SELECT p.id, p.name, p.size, p.color, c.name as category_name
                                                                   FROM products p
                                                                   LEFT JOIN categories c ON p.category_id = c.id
                                                                   WHERE p.status = 'active'
                                                                   ORDER BY p.name ASC";
                                                    $products_result = mysqli_query($conn, $products_sql);
                                                    while ($product = mysqli_fetch_assoc($products_result)):
                                                    ?>
                                                        <option value="<?php echo $product['id']; ?>">
                                                            <?php echo htmlspecialchars($product['name'] . ' (' . $product['size'] . ' - ' . $product['color'] . ') - ' . $product['category_name']); ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="edit_quantity" class="form-label">Quantity *</label>
                                                <input type="number" class="form-control" id="edit_quantity" name="edit_quantity" min="0" required>
                                                <div class="form-text">Enter the new quantity</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Stock Status Preview</label>
                                                <div id="edit_status_preview" class="p-2 border rounded">
                                                    <span id="edit_status_badge" class="badge bg-secondary-subtle text-secondary">
                                                        <i class="fas fa-box-archive me-1"></i>Unknown
                                                    </span>
                                                </div>
                                                <div class="form-text small text-muted">
                                                    Status will update automatically based on quantity
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Warning:</strong> Changing the product will update the stock record for a different product. Make sure this is what you want to do.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </button>
                                    <button type="submit" name="edit_stock" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Update Stock
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div><!-- container -->

            <!--Start Footer-->
            <?php include 'footer.php'; ?>
            <!--end footer-->
        </div>
        <!-- end page content -->
    </div>
    <!-- end page-wrapper -->

    <!-- Javascript  -->
    <!-- vendor js -->
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>

    <!-- DataTable js -->
    <script src="assets/libs/simple-datatables/umd/simple-datatables.js"></script>
    <script>
        // Initialize DataTable
        document.addEventListener('DOMContentLoaded', function () {
            const dataTable = new simpleDatatables.DataTable("#datatable_1", {
                searchable: true,
                fixedHeight: true,
                perPage: 10,
                perPageSelect: [5, 10, 15, 20, 25],
                sortable: true,
                pagination: true,
                labels: {
                    placeholder: "Search stocks...",
                    searchTitle: "Search within table",
                    pageTitle: "Page {page}",
                    perPage: "stocks per page",
                    noRows: "No stocks found",
                    info: "Showing {start} to {end} of {rows} stocks"
                }
            });
        });
    </script>

    <script src="assets/js/app.js"></script>

    <!-- Theme Manager for Dark Mode Persistence -->
    <script src="assets/js/theme-manager.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Function to update stock status preview
        function updateStockStatusPreview(quantity, previewElement, badgeElement) {
            var statusClass, statusIcon, statusText;

            if (quantity == 0) {
                statusClass = 'bg-danger-subtle text-danger';
                statusIcon = 'fas fa-xmark';
                statusText = 'Out of Stock';
            } else if (quantity < 5) {
                statusClass = 'bg-warning-subtle text-warning';
                statusIcon = 'fas fa-exclamation-triangle';
                statusText = 'Low Stock';
            } else {
                statusClass = 'bg-success-subtle text-success';
                statusIcon = 'fas fa-check';
                statusText = 'In Stock';
            }

            badgeElement.className = 'badge ' + statusClass;
            badgeElement.innerHTML = '<i class="' + statusIcon + ' me-1"></i>' + statusText;
        }

        document.querySelectorAll('.btn-edit-stock').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var stockId = this.getAttribute('data-id');
                var productId = this.getAttribute('data-product-id');
                var quantity = this.getAttribute('data-quantity');
                var productName = this.getAttribute('data-product-name');
                var size = this.getAttribute('data-size');
                var color = this.getAttribute('data-color');
                var category = this.getAttribute('data-category');

                // Populate modal with data
                document.getElementById('edit_stock_id').value = stockId;
                document.getElementById('edit_quantity').value = quantity;

                // Find and select the correct product option
                var productSelect = document.getElementById('edit_product_id');
                for (var i = 0; i < productSelect.options.length; i++) {
                    var option = productSelect.options[i];
                    if (option.value == productId) {
                        option.selected = true;
                        break;
                    }
                }

                // Update status preview
                var quantityInput = document.getElementById('edit_quantity');
                var statusPreview = document.getElementById('edit_status_preview');
                var statusBadge = document.getElementById('edit_status_badge');
                updateStockStatusPreview(quantity, statusPreview, statusBadge);

                // Show modal
                var modal = new bootstrap.Modal(document.getElementById('editStockModal'));
                modal.show();
            });
        });

        // Add event listener for quantity input changes in add modal
        document.getElementById('quantity').addEventListener('input', function() {
            var quantity = parseInt(this.value) || 0;
            var statusPreview = document.getElementById('add_status_preview');
            var statusBadge = document.getElementById('add_status_badge');
            updateStockStatusPreview(quantity, statusPreview, statusBadge);
        });

        // Add event listener for quantity input changes in edit modal
        document.getElementById('edit_quantity').addEventListener('input', function() {
            var quantity = parseInt(this.value) || 0;
            var statusPreview = document.getElementById('edit_status_preview');
            var statusBadge = document.getElementById('edit_status_badge');
            updateStockStatusPreview(quantity, statusPreview, statusBadge);
        });

        document.querySelectorAll('.btn-add-stock').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var productId = this.getAttribute('data-product-id');
                var productName = this.getAttribute('data-product-name');
                var size = this.getAttribute('data-size');
                var color = this.getAttribute('data-color');
                var category = this.getAttribute('data-category');

                // Find and select the correct product option in add modal
                var productSelect = document.getElementById('product_id');
                for (var i = 0; i < productSelect.options.length; i++) {
                    var option = productSelect.options[i];
                    if (option.value == productId) {
                        option.selected = true;
                        break;
                    }
                }

                // Reset quantity to 1
                document.getElementById('quantity').value = 1;

                // Update status preview
                var statusPreview = document.getElementById('add_status_preview');
                var statusBadge = document.getElementById('add_status_badge');
                updateStockStatusPreview(1, statusPreview, statusBadge);

                // Show modal
                var modal = new bootstrap.Modal(document.getElementById('addStockModal'));
                modal.show();
            });
        });
    });
    </script>
</body>
<!--end body-->

</html>