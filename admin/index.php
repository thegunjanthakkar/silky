<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Get recent login activities
require_once '../db_config.php';
$activity_query = "SELECT au.first_name, au.last_name, au.last_login, ar.role_name 
                  FROM admin_users au 
                  LEFT JOIN admin_roles ar ON au.role_id = ar.id 
                  WHERE au.last_login IS NOT NULL AND au.status = 'active' 
                  ORDER BY au.last_login DESC LIMIT 6";
$activity_result = mysqli_query($conn, $activity_query);
$recent_activities = [];
if ($activity_result) {
    while ($row = mysqli_fetch_assoc($activity_result)) {
        $recent_activities[] = $row;
    }
}

// Get Total Orders
$orders_query = "SELECT COUNT(*) as total_orders FROM orders";
$orders_result = mysqli_query($conn, $orders_query);
$total_orders = $orders_result ? mysqli_fetch_assoc($orders_result)['total_orders'] : 0;

// Get New Orders Today
$today_orders_query = "SELECT COUNT(*) as today_orders FROM orders WHERE DATE(created_at) = CURDATE()";
$today_orders_result = mysqli_query($conn, $today_orders_query);
$today_orders = $today_orders_result ? mysqli_fetch_assoc($today_orders_result)['today_orders'] : 0;

// Get Revenue
$revenue_query = "SELECT SUM(total_amount) as total_revenue FROM orders WHERE payment_status = 'paid' OR order_status = 'confirmed'";
$revenue_result = mysqli_query($conn, $revenue_query);
$total_revenue = $revenue_result ? mysqli_fetch_assoc($revenue_result)['total_revenue'] : 0;

// Get Weekly Revenue
$weekly_revenue_query = "SELECT SUM(total_amount) as weekly_revenue FROM orders WHERE (payment_status = 'paid' OR order_status = 'confirmed') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$weekly_revenue_result = mysqli_query($conn, $weekly_revenue_query);
$weekly_revenue = $weekly_revenue_result ? mysqli_fetch_assoc($weekly_revenue_result)['weekly_revenue'] : 0;

// Get New Customers
$customers_query = "SELECT COUNT(*) as total_customers FROM users";
$customers_result = mysqli_query($conn, $customers_query);
$total_customers = $customers_result ? mysqli_fetch_assoc($customers_result)['total_customers'] : 0;

// Get New Customers Weekly
$weekly_customers_query = "SELECT COUNT(*) as weekly_customers FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$weekly_customers_result = mysqli_query($conn, $weekly_customers_query);
$weekly_customers = $weekly_customers_result ? mysqli_fetch_assoc($weekly_customers_result)['weekly_customers'] : 0;

// Get Pending Orders
$pending_orders_query = "SELECT COUNT(*) as pending_orders FROM orders WHERE order_status = 'pending'";
$pending_orders_result = mysqli_query($conn, $pending_orders_query);
$pending_orders = $pending_orders_result ? mysqli_fetch_assoc($pending_orders_result)['pending_orders'] : 0;

// Get Recent Orders (3 items)
$recent_orders_query = "SELECT o.id, o.order_number, (SELECT product_name FROM order_items oi WHERE oi.order_id = o.id LIMIT 1) as item_name FROM orders o ORDER BY o.created_at DESC LIMIT 3";
$recent_orders_result = mysqli_query($conn, $recent_orders_query);
$recent_orders = [];
if ($recent_orders_result) {
    while ($row = mysqli_fetch_assoc($recent_orders_result)) {
        $recent_orders[] = $row;
    }
}

// Connection will be closed automatically at script end
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light" id="html-root">

<head>
    <meta charset="utf-8" />
    <title>Dashboard | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta content="" name="description" />
    <meta content="" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <!-- App favicon -->
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">

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
                            <h4 class="page-title">Dashboard</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./">Silky</a>
                                    </li><!--end nav-item-->
                                    <li class="breadcrumb-item active">Dashboard</li>
                                </ol>
                            </div>
                        </div><!--end page-title-box-->
                    </div><!--end col-->
                </div><!--end row-->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="row justify-content-center">
                            <div class="col-md-6 col-lg-3">
                                <div class="card report-card">
                                    <div class="card-body">
                                        <div class="row d-flex justify-content-center">
                                            <div class="col">
                                                <p class="text-dark mb-0 fw-semibold">Total Orders</p>
                                                <h3 class="my-1 fs-20"><?php echo number_format($total_orders); ?></h3>
                                                <p class="mb-0 text-truncate text-muted"><span class="text-success"><?php echo number_format($today_orders); ?></span> New Orders Today</p>
                                            </div>
                                            <div class="col-auto align-self-center">
                                                <div class="flex-shrink-0 bg-primary-subtle text-primary thumb-md rounded-circle">
                                                    <i class="iconoir-user fs-4"></i>
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
                                                <p class="text-dark mb-0 fw-semibold">Revenue</p>
                                                <h3 class="my-1 fs-20">₹<?php echo number_format($total_revenue, 2); ?></h3>
                                                <p class="mb-0 text-truncate text-muted"><span class="text-success">₹<?php echo number_format($weekly_revenue, 2); ?></span> Weekly Revenue</p>
                                            </div>
                                            <div class="col-auto align-self-center">
                                                <div class="flex-shrink-0 bg-info-subtle text-info thumb-md rounded-circle">
                                                    <i class="iconoir-clock fs-4"></i>
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
                                                <p class="text-dark mb-0 fw-semibold">New Customers</p>
                                                <h3 class="my-1 fs-20"><?php echo number_format($total_customers); ?></h3>
                                                <p class="mb-0 text-truncate text-muted"><span class="text-success"><?php echo number_format($weekly_customers); ?></span> New Customers Weekly</p>
                                            </div>
                                            <div class="col-auto align-self-center">
                                                <div class="flex-shrink-0 bg-pink-subtle text-pink thumb-md rounded-circle">
                                                    <i class="iconoir-activity fs-4"></i>
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
                                                <p class="text-dark mb-0 fw-semibold">Pending Orders</p>
                                                <h3 class="my-1 fs-20"><?php echo number_format($pending_orders); ?></h3>
                                                <p class="mb-0 text-truncate text-muted">Total pending orders</p>
                                            </div>
                                            <div class="col-auto align-self-center">
                                                <div class="flex-shrink-0 bg-warning-subtle text-warning thumb-md rounded-circle">
                                                    <i class="iconoir-handbag fs-4"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div><!--end card-body-->
                                </div><!--end card-->
                            </div> <!--end col-->
                        </div><!--end row-->
                    </div><!--end col-->
                </div><!--end row-->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h4 class="card-title">Recent Orders</h4>
                                    </div><!--end col-->
                                </div> <!--end row-->
                            </div><!--end card-header-->
                            <div class="card-body px-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Order Item</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($recent_orders)): ?>
                                                <?php foreach ($recent_orders as $order): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                                        <td><?php echo htmlspecialchars($order['item_name'] ?? 'Multiple Items'); ?></td>
                                                        <td><a href="orders.php" class="btn btn-sm btn-soft-primary">View</a></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">No recent orders</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div><!--end card-body-->
                        </div><!--end card-->
                        
                    </div> <!--end col-->
                    

                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h4 class="card-title">Activity</h4>
                                    </div><!--end col-->
                                    <div class="col-auto">
                                        <div class="dropdown">
                                            <a href="#" class="btn btn-sm btn-outline-light dropdown-toggle"
                                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                All<i class="las la-angle-down ms-1"></i>
                                            </a>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a class="dropdown-item" href="#">Purchases</a>
                                                <a class="dropdown-item" href="#">Emails</a>
                                            </div>
                                        </div>
                                    </div><!--end col-->
                                </div> <!--end row-->
                            </div><!--end card-header-->
                            <div class="card-body">
                                <div class="analytic-dash-activity" data-simplebar style="height:320px">
                                    <div class="activity">
                                        <?php if (!empty($recent_activities)): ?>
                                            <?php foreach ($recent_activities as $activity): ?>
                                                <div class="activity-info">
                                                    <div class="icon-info-activity">
                                                        <i class="las la-sign-in-alt bg-success-subtle text-success"></i>
                                                    </div>
                                                    <div class="activity-info-text">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <p class="text-muted mb-0 fs-13 w-75">
                                                                <span><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></span>
                                                                logged into the system as 
                                                                <strong><?php echo htmlspecialchars($activity['role_name'] ?: 'Administrator'); ?></strong>
                                                            </p>
                                                            <small class="text-muted">
                                                                <?php 
                                                                $login_time = strtotime($activity['last_login']);
                                                                $now = time();
                                                                $diff = $now - $login_time;
                                                                
                                                                if ($diff < 3600) {
                                                                    echo floor($diff / 60) . ' min ago';
                                                                } elseif ($diff < 86400) {
                                                                    echo floor($diff / 3600) . ' hours ago';
                                                                } else {
                                                                    echo floor($diff / 86400) . ' days ago';
                                                                }
                                                                ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="activity-info">
                                                <div class="icon-info-activity">
                                                    <i class="las la-info-circle bg-info-subtle text-info"></i>
                                                </div>
                                                <div class="activity-info-text">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <p class="text-muted mb-0 fs-13 w-75">
                                                            No recent login activities found
                                                        </p>
                                                        <small class="text-muted">Just now</small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div><!--end activity-->
                                </div><!--end analytics-dash-activity-->
                            </div> <!--end card-body-->
                        </div><!--end card-->
                    </div><!--end col-->
                </div><!--end row-->
               
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

    <script src="assets/libs/apexcharts/apexcharts.min.js"></script>
    <script src="../../../apexcharts.com/samples/assets/stock-prices.js"></script>
    <script src="assets/js/pages/index.init.js"></script>
    <script src="assets/js/app.js"></script>

    <!-- Theme Manager for Dark Mode Persistence -->
    <script src="assets/js/theme-manager.js"></script>
</body>
<!--end body-->


</html>