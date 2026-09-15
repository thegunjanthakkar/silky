<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Handle form submissions before any output
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_coupon'])) {
        require_once '../db_config.php';

        $coupon_code = strtoupper(trim($_POST['coupon_code']));
        $discount_type = $_POST['discount_type'];
        $discount_value = (float) $_POST['discount_value'];
        $min_purchase = !empty($_POST['min_purchase']) ? (float) $_POST['min_purchase'] : 0;
        $max_discount = !empty($_POST['max_discount']) ? (float) $_POST['max_discount'] : null;
        $usage_limit = !empty($_POST['usage_limit']) ? (int) $_POST['usage_limit'] : null;
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $status = $_POST['status'];
        $created_by = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? 1;

        // Validate coupon code
        if (empty($coupon_code)) {
            $_SESSION['error'] = "Coupon code is required.";
            header('Location: coupons.php');
            exit;
        }

        // Check if coupon code already exists
        $check_sql = "SELECT id FROM coupons WHERE coupon_code = '" . mysqli_real_escape_string($conn, $coupon_code) . "'";
        $check_result = mysqli_query($conn, $check_sql);
        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['error'] = "Coupon code already exists!";
            header('Location: coupons.php');
            exit;
        }

        // Validate dates
        if ($start_date && !strtotime($start_date)) {
            $_SESSION['error'] = "Invalid start date format.";
            header('Location: coupons.php');
            exit;
        }
        if ($end_date && !strtotime($end_date)) {
            $_SESSION['error'] = "Invalid end date format.";
            header('Location: coupons.php');
            exit;
        }
        if ($start_date && $end_date && strtotime($start_date) > strtotime($end_date)) {
            $_SESSION['error'] = "End date must be after start date.";
            header('Location: coupons.php');
            exit;
        }

        // Validate discount value based on type
        if ($discount_type === 'percentage' && ($discount_value < 0 || $discount_value > 100)) {
            $_SESSION['error'] = "Percentage discount must be between 0 and 100.";
            header('Location: coupons.php');
            exit;
        } elseif ($discount_type === 'fixed' && $discount_value < 0) {
            $_SESSION['error'] = "Fixed discount amount cannot be negative.";
            header('Location: coupons.php');
            exit;
        }

        // Insert coupon record
        $start_date_value = !empty($_POST['start_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['start_date']) . "'" : "NULL";
        $end_date_value = !empty($_POST['end_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['end_date']) . "'" : "NULL";
        $max_discount_value = !empty($max_discount) ? $max_discount : "NULL";
        $usage_limit_value = !empty($usage_limit) ? $usage_limit : "NULL";

        $sql = "INSERT INTO coupons (coupon_code, discount_type, discount_value, min_purchase, max_discount, usage_limit, start_date, end_date, status, created_by) 
                VALUES ('" . mysqli_real_escape_string($conn, $coupon_code) . "', '$discount_type', $discount_value, $min_purchase, $max_discount_value, $usage_limit_value, $start_date_value, $end_date_value, '$status', $created_by)";

        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Coupon added successfully!";
        } else {
            $_SESSION['error'] = "Error adding coupon: " . mysqli_error($conn);
        }
        header('Location: coupons.php');
        exit;
    }

    // Handle edit coupon form submission
    if (isset($_POST['edit_coupon'])) {
        require_once '../db_config.php';

        $coupon_id = (int) $_POST['coupon_id'];
        $coupon_code = strtoupper(trim($_POST['coupon_code']));
        $discount_type = $_POST['discount_type'];
        $discount_value = (float) $_POST['discount_value'];
        $min_purchase = !empty($_POST['min_purchase']) ? (float) $_POST['min_purchase'] : 0;
        $max_discount = !empty($_POST['max_discount']) ? (float) $_POST['max_discount'] : null;
        $usage_limit = !empty($_POST['usage_limit']) ? (int) $_POST['usage_limit'] : null;
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $status = $_POST['status'];

        // Validate coupon code
        if (empty($coupon_code)) {
            $_SESSION['error'] = "Coupon code is required.";
            header('Location: coupons.php');
            exit;
        }

        // Check if coupon code already exists (excluding current coupon)
        $check_sql = "SELECT id FROM coupons WHERE coupon_code = '" . mysqli_real_escape_string($conn, $coupon_code) . "' AND id != $coupon_id";
        $check_result = mysqli_query($conn, $check_sql);
        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['error'] = "Coupon code already exists!";
            header('Location: coupons.php');
            exit;
        }

        // Validate dates
        if ($start_date && !strtotime($start_date)) {
            $_SESSION['error'] = "Invalid start date format.";
            header('Location: coupons.php');
            exit;
        }
        if ($end_date && !strtotime($end_date)) {
            $_SESSION['error'] = "Invalid end date format.";
            header('Location: coupons.php');
            exit;
        }
        if ($start_date && $end_date && strtotime($start_date) > strtotime($end_date)) {
            $_SESSION['error'] = "End date must be after start date.";
            header('Location: coupons.php');
            exit;
        }

        // Validate discount value based on type
        if ($discount_type === 'percentage' && ($discount_value < 0 || $discount_value > 100)) {
            $_SESSION['error'] = "Percentage discount must be between 0 and 100.";
            header('Location: coupons.php');
            exit;
        } elseif ($discount_type === 'fixed' && $discount_value < 0) {
            $_SESSION['error'] = "Fixed discount amount cannot be negative.";
            header('Location: coupons.php');
            exit;
        }

        // Update coupon record
        $start_date_value = !empty($_POST['start_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['start_date']) . "'" : "NULL";
        $end_date_value = !empty($_POST['end_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['end_date']) . "'" : "NULL";
        $max_discount_value = !empty($max_discount) ? $max_discount : "NULL";
        $usage_limit_value = !empty($usage_limit) ? $usage_limit : "NULL";

        $sql = "UPDATE coupons SET 
                coupon_code = '" . mysqli_real_escape_string($conn, $coupon_code) . "',
                discount_type = '$discount_type',
                discount_value = $discount_value,
                min_purchase = $min_purchase,
                max_discount = $max_discount_value,
                usage_limit = $usage_limit_value,
                start_date = $start_date_value,
                end_date = $end_date_value,
                status = '$status'
                WHERE id = $coupon_id";

        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Coupon updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating coupon: " . mysqli_error($conn);
        }
        header('Location: coupons.php');
        exit;
    }

    // Handle delete coupon
    if (isset($_POST['delete_coupon'])) {
        require_once '../db_config.php';
        $coupon_id = (int) $_POST['coupon_id'];

        $sql = "DELETE FROM coupons WHERE id = $coupon_id";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Coupon deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting coupon: " . mysqli_error($conn);
        }
        header('Location: coupons.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <title>Coupons | Silky Admin</title>
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
                            <h4 class="page-title">Coupons</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="#">Coupons</a></li>
                                    <li class="breadcrumb-item active">All Coupons</li>
                                </ol>
                            </div>
                        </div><!--end page-title-box-->
                    </div><!--end col-->
                </div><!--end row-->

                <?php
                // Display success or error messages
                if (isset($_SESSION['success'])) {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            ' . $_SESSION['success'] . '
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
                    unset($_SESSION['success']);
                }
                if (isset($_SESSION['error'])) {
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            ' . $_SESSION['error'] . '
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
                    unset($_SESSION['error']);
                }
                ?>

                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h4 class="card-title">Coupons Management</h4>
                                    </div><!--end col-->
                                    <div class="col-auto">
                                        <button class="btn btn-primary" data-bs-toggle="modal"
                                            data-bs-target="#addCouponModal">
                                            <i class="iconoir-plus me-2"></i>Add New Coupon
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
                                                <th>Coupon Code</th>
                                                <th>Discount Type</th>
                                                <th>Discount Value</th>
                                                <th>Min Purchase</th>
                                                <th>Max Discount</th>
                                                <th>Usage Limit</th>
                                                <th>Used Count</th>
                                                <th>Valid From</th>
                                                <th>Valid Until</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            require_once '../db_config.php';
                                            $sql = "SELECT * FROM coupons ORDER BY id DESC";
                                            $result = mysqli_query($conn, $sql);

                                            if (mysqli_num_rows($result) > 0) {
                                                while ($row = mysqli_fetch_assoc($result)) {
                                                    // Status badge with icon
                                                    $status_badge = $row['status'] == 'active'
                                                        ? '<span class="badge bg-success-subtle text-success"><i class="fas fa-check me-1"></i>Active</span>'
                                                        : '<span class="badge bg-danger-subtle text-danger"><i class="fas fa-xmark me-1"></i>Inactive</span>';

                                                    $discount_display = $row['discount_type'] == 'percentage' ? $row['discount_value'] . '%' : '₹' . number_format($row['discount_value'], 2);
                                                    $min_purchase = $row['min_purchase'] > 0 ? '₹' . number_format($row['min_purchase'], 2) : 'None';
                                                    $max_discount = !empty($row['max_discount']) ? '₹' . number_format($row['max_discount'], 2) : 'None';
                                                    $usage_limit = !empty($row['usage_limit']) ? $row['usage_limit'] : 'Unlimited';
                                                    $start_date = !empty($row['start_date']) && $row['start_date'] != '0000-00-00' ? date('d/m/Y', strtotime($row['start_date'])) : '-';
                                                    $end_date = !empty($row['end_date']) && $row['end_date'] != '0000-00-00' ? date('d/m/Y', strtotime($row['end_date'])) : '-';

                                                    echo "<tr>";
                                                    echo "<td>" . $row['id'] . "</td>";
                                                    echo "<td><strong>" . htmlspecialchars($row['coupon_code']) . "</strong></td>";
                                                    echo "<td><span class='text-capitalize'>" . $row['discount_type'] . "</span></td>";
                                                    echo "<td><strong>" . $discount_display . "</strong></td>";
                                                    echo "<td>" . $min_purchase . "</td>";
                                                    echo "<td>" . $max_discount . "</td>";
                                                    echo "<td>" . $usage_limit . "</td>";
                                                    echo "<td>" . $row['used_count'] . "</td>";
                                                    echo "<td>" . $start_date . "</td>";
                                                    echo "<td>" . $end_date . "</td>";
                                                    echo "<td>" . $status_badge . "</td>";
                                                    echo "<td>";
                                                    echo '<div class="btn-group" role="group">';
                                                    echo '<button type="button" class="btn btn-sm btn-soft-secondary" onclick="editCoupon(' . htmlspecialchars(json_encode($row), ENT_QUOTES) . ')" data-bs-toggle="modal" data-bs-target="#editCouponModal" title="Edit">';
                                                    echo '<i class="fas fa-edit"></i>';
                                                    echo '</button>';
                                                    echo '<button type="button" class="btn btn-sm btn-soft-danger" onclick="confirmDelete(' . $row['id'] . ', \'' . addslashes($row['coupon_code']) . '\')" title="Delete"><i class="fas fa-trash"></i></button>';
                                                    echo '</div>';
                                                    echo "</td>";
                                                    echo "</tr>";
                                                }
                                            } else {
                                                echo "<tr><td colspan='12' class='text-center text-muted'>No coupons found</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div><!--end card-body-->
                        </div><!--end card-->
                    </div> <!--end col-->
                </div><!--end row-->

            </div><!-- container -->

            <?php include 'footer.php'; ?>
            <!--end footer-->
        </div>
        <!-- end page content -->
    </div>
    <!--end page-wrapper-->

    <!-- Add Coupon Modal -->
    <div class="modal fade" id="addCouponModal" tabindex="-1" aria-labelledby="addCouponModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCouponModalLabel">Add New Coupon</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="coupon_code" class="form-label">Coupon Code <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="coupon_code" name="coupon_code" required
                                    style="text-transform: uppercase;"
                                    oninput="this.value = this.value.replace(/\s/g, '')"
                                    onkeydown="if(event.key === ' ') return false;">
                                <small class="text-muted">Use uppercase letters and numbers (e.g., SAVE20)</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="discount_type" class="form-label">Discount Type <span
                                        class="text-danger">*</span></label>
                                <select class="form-select" id="discount_type" name="discount_type" required>
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed">Fixed Amount (₹)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="discount_value" class="form-label">Discount Value <span
                                        class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="discount_value" name="discount_value"
                                    step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="min_purchase" class="form-label">Minimum Purchase (₹)</label>
                                <input type="number" class="form-control" id="min_purchase" name="min_purchase"
                                    step="0.01" min="0" value="0">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="max_discount" class="form-label">Maximum Discount (₹)</label>
                                <input type="number" class="form-control" id="max_discount" name="max_discount"
                                    step="0.01" min="0" placeholder="Optional">
                                <small class="text-muted">Only for percentage discounts</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="usage_limit" class="form-label">Usage Limit</label>
                                <input type="number" class="form-control" id="usage_limit" name="usage_limit" min="1"
                                    placeholder="Unlimited">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Valid From</label>
                                <input type="date" class="form-control" id="start_date" name="start_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">Valid Until</label>
                                <input type="date" class="form-control" id="end_date" name="end_date">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" name="add_coupon" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Add Coupon
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Coupon Modal -->
    <div class="modal fade" id="editCouponModal" tabindex="-1" aria-labelledby="editCouponModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCouponModalLabel">Edit Coupon</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" id="edit_coupon_id" name="coupon_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_coupon_code" class="form-label">Coupon Code <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_coupon_code" name="coupon_code"
                                    required style="text-transform: uppercase;"
                                    oninput="this.value = this.value.replace(/\s/g, '')"
                                    onkeydown="if(event.key === ' ') return false;">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_discount_type" class="form-label">Discount Type <span
                                        class="text-danger">*</span></label>
                                <select class="form-select" id="edit_discount_type" name="discount_type" required>
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed">Fixed Amount (₹)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_discount_value" class="form-label">Discount Value <span
                                        class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_discount_value" name="discount_value"
                                    step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_min_purchase" class="form-label">Minimum Purchase (₹)</label>
                                <input type="number" class="form-control" id="edit_min_purchase" name="min_purchase"
                                    step="0.01" min="0" value="0">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_max_discount" class="form-label">Maximum Discount (₹)</label>
                                <input type="number" class="form-control" id="edit_max_discount" name="max_discount"
                                    step="0.01" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_usage_limit" class="form-label">Usage Limit</label>
                                <input type="number" class="form-control" id="edit_usage_limit" name="usage_limit"
                                    min="1">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_start_date" class="form-label">Valid From</label>
                                <input type="date" class="form-control" id="edit_start_date" name="start_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_end_date" class="form-label">Valid Until</label>
                                <input type="date" class="form-control" id="edit_end_date" name="end_date">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                           

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="edit_coupon" class="btn btn-primary">Update Coupon</button>
                        </div>
                </form>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="assets/libs/jquery/jquery.min.js"></script>
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>

    <!-- DataTable JS -->
    <script src="assets/libs/simple-datatables/umd/simple-datatables.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (document.getElementById("datatable_1")) {
                const dataTable = new simpleDatatables.DataTable("#datatable_1", {
                    searchable: true,
                    fixedHeight: false,
                    perPage: 10
                });
            }
        });

        function editCoupon(coupon) {
            document.getElementById('edit_coupon_id').value = coupon.id;
            document.getElementById('edit_coupon_code').value = coupon.coupon_code;
            document.getElementById('edit_discount_type').value = coupon.discount_type;
            document.getElementById('edit_discount_value').value = coupon.discount_value;
            document.getElementById('edit_min_purchase').value = coupon.min_purchase;
            document.getElementById('edit_max_discount').value = coupon.max_discount || '';
            document.getElementById('edit_usage_limit').value = coupon.usage_limit || '';
            document.getElementById('edit_start_date').value = coupon.start_date || '';
            document.getElementById('edit_end_date').value = coupon.end_date || '';
            document.getElementById('edit_status').value = coupon.status;
        }

        function confirmDelete(id, code) {
            if (confirm('Are you sure you want to delete coupon "' + code + '"?\n\nThis action cannot be undone.')) {
                // Create and submit form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'coupon_id';
                inputId.value = id;
                form.appendChild(inputId);
                
                const inputDelete = document.createElement('input');
                inputDelete.type = 'hidden';
                inputDelete.name = 'delete_coupon';
                inputDelete.value = '1';
                form.appendChild(inputDelete);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

    <!-- App js -->
    <script src="assets/js/app.js"></script>
</body>




</html>