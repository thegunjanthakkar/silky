<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'includes/permission-manager.php';
checkPageAccess();
require_once '../db_config.php';

// Get order ID from query string
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($order_id <= 0) {
    $_SESSION['error'] = 'Invalid order ID.';
    header('Location: orders.php');
    exit;
}

// Fetch order details
$sql = "SELECT * FROM orders WHERE id = $order_id LIMIT 1";
$result = mysqli_query($conn, $sql);
if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = 'Order not found.';
    header('Location: orders.php');
    exit;
}
$order = mysqli_fetch_assoc($result);

// Decode shipping address JSON
$shipping = [];
if (!empty($order['shipping_address'])) {
    $decoded = json_decode($order['shipping_address'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $shipping = $decoded;
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">
<head>
    <meta charset="utf-8" />
    <title>Edit Order | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">
    
    <!-- App CSS -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <!-- Dark Mode State Check Script -->
    <script>
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
                            <h4 class="page-title">Edit Order #<?php echo htmlspecialchars($order['order_number']); ?></h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="#">Order Management</a></li>
                                    <li class="breadcrumb-item"><a href="orders.php">Orders</a></li>
                                    <li class="breadcrumb-item active">Edit Order</li>
                                </ol>
                            </div>
                        </div><!--end page-title-box-->
                    </div><!--end col-->
                </div><!--end row-->

                <div class="row justify-content-center">
                    <div class="col-12 col-lg-10">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h4 class="card-title">Edit Order Details</h4>
                                    </div><!--end col-->
                                    <div class="col-auto">
                                        <a href="orders.php" class="btn btn-outline-secondary">
                                            <i class="iconoir-arrow-left me-2"></i>Back to Orders
                                        </a>
                                    </div><!--end col-->
                                </div> <!--end row-->
                            </div><!--end card-header-->
                            <div class="card-body">
                                <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($_SESSION['success'])): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <form action="save-updated-order.php" method="post" class="needs-validation" novalidate>
                                    <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                                    <input type="hidden" name="action" value="update">
                                    
                                    <h5 class="mb-3">Order Statuses</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="payment_status" class="form-label">Payment Status <span class="text-danger">*</span></label>
                                                <select class="form-select" id="payment_status" name="payment_status" required>
                                                    <option value="pending" <?php if (strtolower($order['payment_status']) == 'pending') echo 'selected'; ?>>Pending</option>
                                                    <option value="paid" <?php if (strtolower($order['payment_status']) == 'paid') echo 'selected'; ?>>Paid</option>
                                                    <option value="failed" <?php if (strtolower($order['payment_status']) == 'failed') echo 'selected'; ?>>Failed</option>
                                                </select>
                                                <div class="invalid-feedback">Please select a payment status.</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="order_status" class="form-label">Order Status <span class="text-danger">*</span></label>
                                                <select class="form-select" id="order_status" name="order_status" required>
                                                    <option value="pending" <?php if (strtolower($order['order_status']) == 'pending') echo 'selected'; ?>>Pending</option>
                                                    <option value="placed" <?php if (strtolower($order['order_status']) == 'placed') echo 'selected'; ?>>Placed</option>
                                                    <option value="processing" <?php if (strtolower($order['order_status']) == 'processing') echo 'selected'; ?>>Processing</option>
                                                    <option value="confirmed" <?php if (strtolower($order['order_status']) == 'confirmed') echo 'selected'; ?>>Confirmed</option>
                                                    <option value="out for delivery" <?php if (strtolower($order['order_status']) == 'out for delivery') echo 'selected'; ?>>Out for Delivery</option>
                                                    <option value="delivered" <?php if (strtolower($order['order_status']) == 'delivered') echo 'selected'; ?>>Delivered</option>
                                                    <option value="cancelled" <?php if (strtolower($order['order_status']) == 'cancelled') echo 'selected'; ?>>Cancelled</option>
                                                    <option value="returned" <?php if (strtolower($order['order_status']) == 'returned') echo 'selected'; ?>>Returned</option>
                                                </select>
                                                <div class="invalid-feedback">Please select an order status.</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-4">
                                    <h5 class="mb-3">Customer Information</h5>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                                       value="<?php echo htmlspecialchars($shipping['first_name'] ?? ''); ?>" required>
                                                <div class="invalid-feedback">Please provide a first name.</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                                       value="<?php echo htmlspecialchars($shipping['last_name'] ?? ''); ?>" required>
                                                <div class="invalid-feedback">Please provide a last name.</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($shipping['email'] ?? ''); ?>" required>
                                                <div class="invalid-feedback">Please provide a valid email.</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                                <input type="tel" class="form-control" id="phone" name="phone" 
                                                       value="<?php echo htmlspecialchars($shipping['phone'] ?? ''); ?>" required>
                                                <div class="invalid-feedback">Please provide a valid phone number.</div>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-4">
                                    <h5 class="mb-3">Shipping Address</h5>
                                    
                                    <div class="mb-3">
                                        <label for="street_address" class="form-label">Street Address <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="street_address" name="street_address" 
                                               value="<?php echo htmlspecialchars($shipping['street_address'] ?? ($shipping['address'] ?? '')); ?>" required>
                                        <div class="invalid-feedback">Please provide a street address.</div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="apartment" class="form-label">Apartment, suite, etc. (optional)</label>
                                                <input type="text" class="form-control" id="apartment" name="apartment" 
                                                       value="<?php echo htmlspecialchars($shipping['apartment'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="city" name="city" 
                                                       value="<?php echo htmlspecialchars($shipping['city'] ?? ''); ?>" required>
                                                <div class="invalid-feedback">Please provide a city.</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="state" class="form-label">State <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="state" name="state" 
                                                       value="<?php echo htmlspecialchars($shipping['state'] ?? ''); ?>" required>
                                                <div class="invalid-feedback">Please provide a state.</div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="zip_code" class="form-label">Zip Code <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="zip_code" name="zip_code" 
                                                       value="<?php echo htmlspecialchars($shipping['zip_code'] ?? ($shipping['pincode'] ?? '')); ?>" required>
                                                <div class="invalid-feedback">Please provide a zip code.</div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="country" class="form-label">Country <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="country" name="country" 
                                                       value="<?php echo htmlspecialchars($shipping['country'] ?? ''); ?>" required>
                                                <div class="invalid-feedback">Please provide a country.</div>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-4">
                                    <h5 class="mb-3">Additional Information</h5>
                                    <div class="mb-3">
                                        <label for="customer_notes" class="form-label">Customer Notes / Internal Notes</label>
                                        <textarea class="form-control" id="customer_notes" name="customer_notes" rows="4"><?php echo htmlspecialchars($order['customer_notes'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="iconoir-check me-2"></i>Save Changes
                                                </button>
                                                <a href="orders.php" class="btn btn-secondary">
                                                    <i class="iconoir-cancel me-2"></i>Cancel
                                                </a>
                                            </div>
                                        </div>
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
        <!-- end page content -->
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
    (function() {
        'use strict';
        window.addEventListener('load', function() {
            var forms = document.getElementsByClassName('needs-validation');
            var validation = Array.prototype.filter.call(forms, function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        }, false);
    })();
    </script>

</body>
</html>
