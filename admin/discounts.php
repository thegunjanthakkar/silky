<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Handle form submissions before any output
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_discount'])) {
        require_once '../db_config.php';

        $product_id = (int)$_POST['product_id'];
        $discount_type = $_POST['discount_type'];
        $discount_value = (float)$_POST['discount_value'];
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        // Validate dates
        if ($start_date && !strtotime($start_date)) {
            $_SESSION['error'] = "Invalid start date format.";
            header('Location: discounts.php');
            exit;
        }
        if ($end_date && !strtotime($end_date)) {
            $_SESSION['error'] = "Invalid end date format.";
            header('Location: discounts.php');
            exit;
        }
        if ($start_date && $end_date && strtotime($start_date) > strtotime($end_date)) {
            $_SESSION['error'] = "End date must be after start date.";
            header('Location: discounts.php');
            exit;
        }
        $status = $_POST['status'];
        $created_by = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? 1;

        // Validate discount value based on type
        if ($discount_type === 'percentage' && ($discount_value < 0 || $discount_value > 100)) {
            $_SESSION['error'] = "Percentage discount must be between 0 and 100.";
        } elseif ($discount_type === 'fixed' && $discount_value < 0) {
            $_SESSION['error'] = "Fixed discount amount cannot be negative.";
        } else {
            // Insert discount record using basic PHP
            $start_date_value = !empty($_POST['start_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['start_date']) . "'" : "NULL";
            $end_date_value = !empty($_POST['end_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['end_date']) . "'" : "NULL";

            $sql = "INSERT INTO discounts (product_id, discount_type, discount_value, start_date, end_date, status, created_by) 
                    VALUES ($product_id, '$discount_type', $discount_value, $start_date_value, $end_date_value, '$status', $created_by)";

            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Discount added successfully!";
            } else {
                $_SESSION['error'] = "Error adding discount: " . mysqli_error($conn);
            }
        }
        header('Location: discounts.php');
        exit;
    }

    // Handle edit discount form submission
    if (isset($_POST['edit_discount'])) {
        require_once '../db_config.php';

        $discount_id = (int)$_POST['discount_id'];
        $product_id = (int)$_POST['product_id'];
        $discount_type = $_POST['discount_type'];
        $discount_value = (float)$_POST['discount_value'];
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        // Validate dates
        if ($start_date && !strtotime($start_date)) {
            $_SESSION['error'] = "Invalid start date format.";
            header('Location: discounts.php');
            exit;
        }
        if ($end_date && !strtotime($end_date)) {
            $_SESSION['error'] = "Invalid end date format.";
            header('Location: discounts.php');
            exit;
        }
        if ($start_date && $end_date && strtotime($start_date) > strtotime($end_date)) {
            $_SESSION['error'] = "End date must be after start date.";
            header('Location: discounts.php');
            exit;
        }
        $status = $_POST['status'];

        // Validate discount value based on type
        if ($discount_type === 'percentage' && ($discount_value < 0 || $discount_value > 100)) {
            $_SESSION['error'] = "Percentage discount must be between 0 and 100.";
        } elseif ($discount_type === 'fixed' && $discount_value < 0) {
            $_SESSION['error'] = "Fixed discount amount cannot be negative.";
        } else {
            // Update discount record using basic PHP
            $start_date_value = !empty($_POST['start_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['start_date']) . "'" : "NULL";
            $end_date_value = !empty($_POST['end_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['end_date']) . "'" : "NULL";

            $sql = "UPDATE discounts SET 
                    product_id = $product_id, 
                    discount_type = '$discount_type', 
                    discount_value = $discount_value, 
                    start_date = $start_date_value, 
                    end_date = $end_date_value, 
                    status = '$status' 
                    WHERE id = $discount_id";

            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Discount updated successfully!";
            } else {
                $_SESSION['error'] = "Error updating discount: " . mysqli_error($conn);
            }
        }
        header('Location: discounts.php');
        exit;
    }

    // Handle delete discount
    if (isset($_POST['delete_discount'])) {
        require_once '../db_config.php';

        $discount_id = (int)$_POST['discount_id'];

        $sql = "DELETE FROM discounts WHERE id = $discount_id";

        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Discount deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting discount: " . mysqli_error($conn);
        }
        header('Location: discounts.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <title>Discounts | Silky Admin</title>
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
                            <h4 class="page-title">Discounts</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="#">Offers</a></li>
                                    <li class="breadcrumb-item active">Discounts</li>
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

               
                
                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h4 class="card-title">Discount Management</h4>
                                    </div><!--end col-->
                                    <div class="col-auto">
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDiscountModal">
                                            <i class="iconoir-plus me-2"></i>Add New Discount
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
                                                <th>Discount Type</th>
                                                <th>Discount Value</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Status</th>
                                                <th>Created By</th>
                                                <th>Created At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            require_once 'includes/permission-manager.php';
                                            if (!isset($conn)) {
                                                require_once '../db_config.php';
                                            }
                                            $result = mysqli_query($conn, "SELECT d.*, p.name as product_name, p.color as product_color, CONCAT(au.first_name, ' ', au.last_name) as created_by_name FROM discounts d LEFT JOIN products p ON d.product_id = p.id LEFT JOIN admin_users au ON d.created_by = au.id ORDER BY d.created_at DESC");
                                            if ($result && mysqli_num_rows($result) > 0) {
                                                $counter = 1;
                                                while ($row = mysqli_fetch_assoc($result)) {
                                                    // Determine status badge
                                                    $statusBadge = $row['status'] === 'active'
                                                        ? '<span class="badge bg-success-subtle text-success"><i class="fas fa-check me-1"></i>Active</span>'
                                                        : '<span class="badge bg-danger-subtle text-danger"><i class="fas fa-xmark me-1"></i>Inactive</span>';

                                                    // Format discount value
                                                    $discountDisplay = $row['discount_type'] === 'percentage'
                                                        ? $row['discount_value'] . '%'
                                                        : '₹' . number_format($row['discount_value'], 2);

                                                    echo '<tr>';
                                                    echo '<td>' . $counter++ . '</td>';
                                                    echo '<td>' . htmlspecialchars(($row['product_name'] ?: 'Unknown Product') . ' (' . ($row['product_color'] ?: 'N/A') . ')') . '</td>';
                                                    echo '<td><span class="text-capitalize">' . $row['discount_type'] . '</span></td>';
                                                    echo '<td><strong>' . $discountDisplay . '</strong></td>';
                                                    echo '<td>';
                                                    if ($row['start_date'] && $row['start_date'] != '0000-00-00' && $row['start_date'] != null) {
                                                        echo date('d/m/Y', strtotime($row['start_date']));
                                                    } else {
                                                        echo '-';
                                                    }
                                                    echo '</td>';
                                                    echo '<td>';
                                                    if ($row['end_date'] && $row['end_date'] != '0000-00-00') {
                                                        echo date('d/m/Y', strtotime($row['end_date']));
                                                    } else {
                                                        echo '-';
                                                    }
                                                    echo '</td>';
                                                    echo '<td>' . $statusBadge . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['created_by_name'] ?: 'System') . '</td>';
                                                    echo '<td>' . date('d/m/Y h:i', strtotime($row['created_at'])) . '</td>';
                                                    echo '<td>';
                                                    echo '<div class="btn-group" role="group">';
                                                    echo '<button type="button" class="btn btn-sm btn-soft-secondary edit-discount-btn" 
                                                            data-id="' . $row['id'] . '" 
                                                            data-product-id="' . $row['product_id'] . '" 
                                                            data-discount-type="' . $row['discount_type'] . '" 
                                                            data-discount-value="' . $row['discount_value'] . '" 
                                                            data-start-date="' . ($row['start_date'] && $row['start_date'] != '0000-00-00' ? $row['start_date'] : '') . '" 
                                                            data-end-date="' . ($row['end_date'] && $row['end_date'] != '0000-00-00' ? $row['end_date'] : '') . '" 
                                                            data-status="' . $row['status'] . '" 
                                                            title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>';
                                                    echo '<button type="button" class="btn btn-sm btn-soft-danger delete-discount-btn" data-discount-id="' . $row['id'] . '" title="Delete"><i class="fas fa-trash"></i></button>';
                                                    echo '</div>';
                                                    echo '</td>';
                                                    echo '</tr>';
                                                }
                                                mysqli_free_result($result);
                                            } else {
                                                echo '<tr><td colspan="10" class="text-center text-muted">No discounts found</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div><!--end card-body-->
                        </div><!--end card-->
                    </div> <!--end col-->
                </div><!--end row-->

                <!-- Add Discount Modal -->
                <div class="modal fade" id="addDiscountModal" tabindex="-1" aria-labelledby="addDiscountModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addDiscountModalLabel">Add New Discount</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="">
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label for="product_id" class="form-label">Select Product *</label>
                                                <select class="form-select" id="product_id" name="product_id" required>
                                                    <option value="">Choose a product...</option>
                                                    <?php
                                                    // Fetch active products with category info
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
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="discount_type" class="form-label">Discount Type *</label>
                                                <select class="form-select" id="discount_type" name="discount_type" required>
                                                    <option value="">Select discount type...</option>
                                                    <option value="percentage">Percentage (%)</option>
                                                    <option value="fixed">Fixed Amount (₹)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="discount_value" class="form-label">Discount Value *</label>
                                                <input type="number" class="form-control" id="discount_value" name="discount_value" min="0" step="0.01" required>
                                                <div class="form-text" id="discount_value_help">Enter percentage (0-100) or fixed amount</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="start_date" class="form-label">Start Date</label>
                                                <input type="date" class="form-control" id="start_date" name="start_date">
                                                <div class="form-text">Leave empty for immediate activation</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="end_date" class="form-label">End Date</label>
                                                <input type="date" class="form-control" id="end_date" name="end_date">
                                                <div class="form-text">Leave empty for no expiration</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="status" class="form-label">Status *</label>
                                                <select class="form-select" id="status" name="status" required>
                                                    <option value="active">Active</option>
                                                    <option value="inactive">Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Note:</strong> Discounts will be applied to the selected product during checkout. Make sure the dates are set correctly for scheduled discounts.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </button>
                                    <button type="submit" name="add_discount" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Add Discount
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

               <!-- Edit Discount Modal -->
                <div class="modal fade" id="editDiscountModal" tabindex="-1" aria-labelledby="editDiscountModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editDiscountModalLabel">Edit Discount</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="">
                                <input type="hidden" id="edit_discount_id" name="discount_id">
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label for="edit_product_id" class="form-label">Select Product *</label>
                                                <select class="form-select" id="edit_product_id" name="product_id" required>
                                                    <option value="">Choose a product...</option>
                                                    <?php
                                                    if (!isset($conn)) { require_once '../db_config.php'; }
                                                    $products_sql_edit = "SELECT p.id, p.name, p.size, p.color, c.name as category_name
                                                                          FROM products p
                                                                          LEFT JOIN categories c ON p.category_id = c.id
                                                                          WHERE p.status = 'active'
                                                                          ORDER BY p.name ASC";
                                                    $products_result_edit = mysqli_query($conn, $products_sql_edit);
                                                    while ($product = mysqli_fetch_assoc($products_result_edit)):
                                                    ?>
                                                        <option value="<?php echo $product['id']; ?>">
                                                            <?php echo htmlspecialchars($product['name'] . ' (' . $product['size'] . ' - ' . $product['color'] . ') - ' . $product['category_name']); ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="edit_discount_type" class="form-label">Discount Type *</label>
                                                <select class="form-select" id="edit_discount_type" name="discount_type" required>
                                                    <option value="">Select discount type...</option>
                                                    <option value="percentage">Percentage (%)</option>
                                                    <option value="fixed">Fixed Amount (₹)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="edit_discount_value" class="form-label">Discount Value *</label>
                                                <input type="number" class="form-control" id="edit_discount_value" name="discount_value" min="0" step="0.01" required>
                                                <div class="form-text" id="edit_discount_value_help">Enter percentage (0-100) or fixed amount</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="edit_start_date" class="form-label">Start Date</label>
                                                <input type="date" class="form-control" id="edit_start_date" name="start_date">
                                                <div class="form-text">Leave empty for immediate activation</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="edit_end_date" class="form-label">End Date</label>
                                                <input type="date" class="form-control" id="edit_end_date" name="end_date">
                                                <div class="form-text">Leave empty for no expiration</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="edit_status" class="form-label">Status *</label>
                                                <select class="form-select" id="edit_status" name="status" required>
                                                    <option value="active">Active</option>
                                                    <option value="inactive">Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </button>
                                    <button type="submit" name="edit_discount" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Update Discount
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
        // Initialize DataTable and wire up add/edit discount behaviors
        document.addEventListener('DOMContentLoaded', function () {
            const dataTable = new simpleDatatables.DataTable("#datatable_1", {
                searchable: true,
                fixedHeight: true,
                perPage: 10,
                perPageSelect: [5, 10, 15, 20, 25],
                sortable: true,
                pagination: true,
                labels: {
                    placeholder: "Search Discounts...",
                    searchTitle: "Search within table",
                    pageTitle: "Page {page}",
                    perPage: "discounts per page",
                    noRows: "No discounts found",
                    info: "Showing {start} to {end} of {rows} discounts"
                }
            });
            // Common 'today' string for date inputs
            const today = new Date().toISOString().split('T')[0];

            // Add Discount modal: type change handler
            const addType = document.getElementById('discount_type');
            if (addType) {
                addType.addEventListener('change', function() {
                    const valueInput = document.getElementById('discount_value');
                    const helpText = document.getElementById('discount_value_help');
                    if (!valueInput || !helpText) return;
                    if (this.value === 'percentage') {
                        valueInput.min = 0; valueInput.max = 100; valueInput.step = 0.01;
                        helpText.textContent = 'Enter percentage (0-100)';
                    } else if (this.value === 'fixed') {
                        valueInput.min = 0; valueInput.removeAttribute('max'); valueInput.step = 0.01;
                        helpText.textContent = 'Enter fixed amount in rupees';
                    } else {
                        valueInput.min = 0; valueInput.removeAttribute('max'); valueInput.step = 0.01;
                        helpText.textContent = 'Enter percentage (0-100) or fixed amount';
                    }
                });
            }

            // Add Discount modal: date min handling
            const addStartDate = document.getElementById('start_date');
            const addEndDate = document.getElementById('end_date');
            if (addStartDate) {
                addStartDate.min = today;
                addStartDate.addEventListener('change', function() {
                    if (!addEndDate) return;
                    const startDate = this.value;
                    addEndDate.min = startDate || today;
                    if (addEndDate.value && startDate && addEndDate.value < startDate) {
                        addEndDate.value = '';
                    }
                });
            }

            // Edit Discount modal: type change handler
            const editType = document.getElementById('edit_discount_type');
            if (editType) {
                editType.addEventListener('change', function() {
                    const valueInput = document.getElementById('edit_discount_value');
                    const helpText = document.getElementById('edit_discount_value_help');
                    if (!valueInput || !helpText) return;
                    if (this.value === 'percentage') {
                        valueInput.min = 0; valueInput.max = 100; valueInput.step = 0.01;
                        helpText.textContent = 'Enter percentage (0-100)';
                    } else if (this.value === 'fixed') {
                        valueInput.min = 0; valueInput.removeAttribute('max'); valueInput.step = 0.01;
                        helpText.textContent = 'Enter fixed amount in rupees';
                    } else {
                        valueInput.min = 0; valueInput.removeAttribute('max'); valueInput.step = 0.01;
                        helpText.textContent = 'Enter percentage (0-100) or fixed amount';
                    }
                });
            }

            // Edit Discount modal: date min handling
            const editStartDate = document.getElementById('edit_start_date');
            const editEndDate = document.getElementById('edit_end_date');
            if (editStartDate) {
                editStartDate.min = today;
                editStartDate.addEventListener('change', function() {
                    if (!editEndDate) return;
                    const startDate = this.value;
                    editEndDate.min = startDate || today;
                    if (editEndDate.value && startDate && editEndDate.value < startDate) {
                        editEndDate.value = '';
                    }
                });
            }

            // Wire up Edit buttons to populate and show modal
            document.querySelectorAll('.edit-discount-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const productId = this.getAttribute('data-product-id');
                    const type = this.getAttribute('data-discount-type');
                    const value = this.getAttribute('data-discount-value');
                    const startDate = this.getAttribute('data-start-date') || '';
                    const endDate = this.getAttribute('data-end-date') || '';
                    const status = this.getAttribute('data-status');

                    const idInput = document.getElementById('edit_discount_id');
                    const productSelect = document.getElementById('edit_product_id');
                    const typeSelect = document.getElementById('edit_discount_type');
                    const valueInput = document.getElementById('edit_discount_value');
                    const startInput = document.getElementById('edit_start_date');
                    const endInput = document.getElementById('edit_end_date');
                    const statusSelect = document.getElementById('edit_status');
                    if (!idInput || !productSelect || !typeSelect || !valueInput || !startInput || !endInput || !statusSelect) return;

                    idInput.value = id || '';

                    // Select product option
                    for (let i = 0; i < productSelect.options.length; i++) {
                        productSelect.options[i].selected = (productSelect.options[i].value === String(productId));
                    }

                    // Set type and trigger constraints update
                    typeSelect.value = type || '';
                    typeSelect.dispatchEvent(new Event('change'));

                    // Set value
                    valueInput.value = value || '';

                    // Dates
                    startInput.value = startDate;
                    endInput.value = endDate;
                    endInput.min = (startDate || today);

                    // Status
                    statusSelect.value = status || 'inactive';

                    const modal = new bootstrap.Modal(document.getElementById('editDiscountModal'));
                    modal.show();
                });
            });

            // Wire up Delete buttons with confirmation
            document.querySelectorAll('.delete-discount-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const discountId = this.getAttribute('data-discount-id');
                    
                    if (confirm('Are you sure you want to delete this discount? This action cannot be undone.')) {
                        // Create a form and submit it
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';
                        
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'discount_id';
                        input.value = discountId;
                        form.appendChild(input);
                        
                        const deleteInput = document.createElement('input');
                        deleteInput.type = 'hidden';
                        deleteInput.name = 'delete_discount';
                        deleteInput.value = '1';
                        form.appendChild(deleteInput);
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });

            // Form validation before submission (for both add and edit forms)
            document.querySelectorAll('form[action=""]').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const startDate = this.querySelector('[name="start_date"]').value;
                    const endDate = this.querySelector('[name="end_date"]').value;

                    if (startDate && endDate) {
                        const start = new Date(startDate);
                        const end = new Date(endDate);

                        if (start > end) {
                            e.preventDefault();
                            alert('End date must be after start date!');
                            return false;
                        }
                    }

                    // Ensure discount value is valid
                    const discountType = this.querySelector('[name="discount_type"]').value;
                    const discountValue = parseFloat(this.querySelector('[name="discount_value"]').value);

                    if (discountType === 'percentage' && (discountValue < 0 || discountValue > 100)) {
                        e.preventDefault();
                        alert('Percentage discount must be between 0 and 100!');
                        return false;
                    }

                    if (discountType === 'fixed' && discountValue < 0) {
                        e.preventDefault();
                        alert('Fixed discount amount cannot be negative!');
                        return false;
                    }
                });
            });

          
            
        });
    </script>

    <script src="assets/js/app.js"></script>

    <!-- Theme Manager for Dark Mode Persistence -->
    <script src="assets/js/theme-manager.js"></script>

    
</body>
<!--end body-->

</html>