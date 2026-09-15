<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once '../db_config.php';

// Date Filtering
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$date_filter_orders = "";
$date_filter_str = "";

if ($start_date && $end_date) {
    $start = mysqli_real_escape_string($conn, $start_date) . ' 00:00:00';
    $end = mysqli_real_escape_string($conn, $end_date) . ' 23:59:59';
    $date_filter_orders = " AND created_at BETWEEN '$start' AND '$end'";
    $date_filter_str = " (Filtered)";
}

// Fulfillment Status (Order Status)
$status_query = "SELECT order_status, COUNT(*) as count FROM orders WHERE 1=1 $date_filter_orders GROUP BY order_status";
$status_result = mysqli_query($conn, $status_query);
$statuses = [];
$status_counts = [];
if ($status_result) {
    while ($row = mysqli_fetch_assoc($status_result)) {
        $st = trim($row['order_status']);
        $statuses[] = !empty($st) ? ucfirst($st) : 'Unknown';
        $status_counts[] = (int)$row['count'];
    }
}

// Discount & Coupon Performance
$discount_query = "
    SELECT 
        COUNT(id) as total_orders, 
        SUM(CASE WHEN discount_amount > 0 THEN 1 ELSE 0 END) as discounted_orders, 
        SUM(discount_amount) as total_discounts_given 
    FROM orders
    WHERE 1=1 $date_filter_orders
";
$discount_result = mysqli_query($conn, $discount_query);
$discount_data = mysqli_fetch_assoc($discount_result);
$total_orders = (int)($discount_data['total_orders'] ?? 0);
$discounted_orders = (int)($discount_data['discounted_orders'] ?? 0);
$total_discounts_given = (float)($discount_data['total_discounts_given'] ?? 0);

$discount_rate = ($total_orders > 0) ? round(($discounted_orders / $total_orders) * 100, 1) : 0;
$standard_orders = max(0, $total_orders - $discounted_orders);

// Cancellation & Refund Rate
$cancel_query = "SELECT SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders, SUM(CASE WHEN order_status = 'refunded' THEN 1 ELSE 0 END) as refunded_orders FROM orders WHERE 1=1 $date_filter_orders";
$cancel_result = mysqli_query($conn, $cancel_query);
$cancel_data = mysqli_fetch_assoc($cancel_result);
$cancelled_orders = $cancel_data ? (int)$cancel_data['cancelled_orders'] : 0;
$refunded_orders = $cancel_data ? (int)$cancel_data['refunded_orders'] : 0;
$cancellation_rate = ($total_orders > 0) ? round(($cancelled_orders / $total_orders) * 100, 1) : 0;
$return_rate = ($total_orders > 0) ? round(($refunded_orders / $total_orders) * 100, 1) : 0;

// Gross Profit Calculation
$profit_query = "
    SELECT SUM(oi.subtotal) as total_revenue, SUM(oi.quantity * COALESCE(oi.cost_price, 0)) as total_cost
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE (o.payment_status = 'paid' OR o.order_status = 'confirmed') 
    " . str_replace("created_at", "o.created_at", $date_filter_orders) . "
";
$profit_result = mysqli_query($conn, $profit_query);
$profit_data = mysqli_fetch_assoc($profit_result);
$gross_profit = ($profit_data['total_revenue'] ?? 0) - ($profit_data['total_cost'] ?? 0);
$profit_margin = ($profit_data['total_revenue'] > 0) ? round(($gross_profit / $profit_data['total_revenue']) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light" id="html-root">

<head>
    <meta charset="utf-8" />
    <title>Performance Metrics | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <script src="assets/libs/apexcharts/apexcharts.min.js"></script>

    <!-- Dark Mode State Check Script -->
    <script>
        (function () {
            const savedTheme = localStorage.getItem('silky_admin_theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
                document.documentElement.setAttribute('data-startbar', savedTheme);
            }
        })();
    </script>
</head>

<body>
    <?php include 'topbar.php'; ?>
    <?php include 'leftbar.php'; ?>

    <div class="page-wrapper">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-12">
                        <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                            <h4 class="page-title">Performance Metrics <?php echo $date_filter_str; ?></h4>
                            <div class="">
                                <form method="GET" class="d-flex align-items-center gap-2">
                                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>" required>
                                    <span>to</span>
                                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>" required>
                                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                                    <a href="performance-metrics.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Key Performance Indicators Row -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card report-card">
                            <div class="card-body">
                                <div class="row d-flex justify-content-center">
                                    <div class="col">
                                        <p class="text-secondary mb-0 fw-semibold">Gross Profit Margin</p>
                                        <h3 class="my-1 fs-20 text-success"><?php echo $profit_margin; ?>%</h3>
                                        <p class="mb-0 text-truncate text-muted">₹<?php echo number_format($gross_profit, 2); ?> profit</p>
                                    </div>
                                    <div class="col-auto align-self-center">
                                        <div class="flex-shrink-0 bg-success-subtle text-success thumb-md rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="iconoir-dollar fs-4"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card report-card">
                            <div class="card-body">
                                <div class="row d-flex justify-content-center">
                                    <div class="col">
                                        <p class="text-secondary mb-0 fw-semibold">Return / Refund Rate</p>
                                        <h3 class="my-1 fs-20 <?php echo $return_rate > 5 ? 'text-danger' : 'text-primary'; ?>"><?php echo $return_rate; ?>%</h3>
                                        <p class="mb-0 text-truncate text-muted"><?php echo $refunded_orders; ?> refunded orders</p>
                                    </div>
                                    <div class="col-auto align-self-center">
                                        <div class="flex-shrink-0 <?php echo $return_rate > 5 ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary'; ?> thumb-md rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="iconoir-undo fs-4"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card report-card">
                            <div class="card-body">
                                <div class="row d-flex justify-content-center">
                                    <div class="col">
                                        <p class="text-secondary mb-0 fw-semibold">Cancellation Rate</p>
                                        <h3 class="my-1 fs-20 <?php echo $cancellation_rate > 5 ? 'text-danger' : 'text-warning'; ?>"><?php echo $cancellation_rate; ?>%</h3>
                                        <p class="mb-0 text-truncate text-muted"><?php echo $cancelled_orders; ?> cancelled</p>
                                    </div>
                                    <div class="col-auto align-self-center">
                                        <div class="flex-shrink-0 bg-warning-subtle text-warning thumb-md rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="iconoir-cancel fs-4"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card report-card">
                            <div class="card-body">
                                <div class="row d-flex justify-content-center">
                                    <div class="col">
                                        <p class="text-secondary mb-0 fw-semibold">Total Processed</p>
                                        <h3 class="my-1 fs-20 text-info"><?php echo number_format($total_orders); ?></h3>
                                        <p class="mb-0 text-truncate text-muted">Order count</p>
                                    </div>
                                    <div class="col-auto align-self-center">
                                        <div class="flex-shrink-0 bg-info-subtle text-info thumb-md rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="iconoir-package fs-4"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Order Fulfillment Status</h4>
                            </div>
                            <div class="card-body">
                                <div id="fulfillmentStatusChart" style="height: 320px;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Discounted Orders vs Standard Orders</h4>
                            </div>
                            <div class="card-body">
                                <div id="discountComparisonChart" style="height: 320px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Javascript -->
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/theme-manager.js"></script>
    <script src="assets/js/app.js"></script>

    <script>
        window.reportCharts = [];

        function getCurrentThemeMode() {
            return document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
        }

        // Fulfillment Status Chart (Pie)
        var statusOptions = {
            series: <?php echo json_encode($status_counts); ?>,
            chart: { 
                type: 'pie', 
                height: 320,
                background: 'transparent'
            },
            theme: {
                mode: getCurrentThemeMode()
            },
            labels: <?php echo json_encode($statuses); ?>,
            colors: ['#16cdc7', '#ffb822', '#22b783', '#f5325c', '#6f6af8'],
            stroke: {
                colors: ['var(--bs-card-bg, #ffffff)']
            },
            dataLabels: { 
                enabled: true,
                formatter: function (val, opts) {
                    return opts.w.config.series[opts.seriesIndex];
                }
            },
            legend: { 
                position: 'bottom',
                horizontalAlign: 'center',
                offsetY: 6
            },
            tooltip: {
                theme: getCurrentThemeMode()
            }
        };
        var statusChart = new ApexCharts(document.querySelector("#fulfillmentStatusChart"), statusOptions);
        statusChart.render();
        window.reportCharts.push(statusChart);

        // Discount Comparison Chart (Donut)
        var discountOptions = {
            series: [<?php echo $standard_orders; ?>, <?php echo $discounted_orders; ?>],
            chart: { 
                type: 'donut', 
                height: 320,
                background: 'transparent'
            },
            theme: {
                mode: getCurrentThemeMode()
            },
            labels: ['Standard Orders', 'Discounted Orders'],
            colors: ['#6f6af8', '#16cdc7'],
            stroke: {
                colors: ['var(--bs-card-bg, #ffffff)']
            },
            plotOptions: { 
                pie: { 
                    donut: { 
                        size: '72%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total Orders',
                                formatter: function() {
                                    return <?php echo $total_orders; ?>;
                                }
                            }
                        }
                    } 
                } 
            },
            dataLabels: { enabled: false },
            legend: { 
                position: 'bottom',
                horizontalAlign: 'center',
                offsetY: 6
            },
            tooltip: {
                theme: getCurrentThemeMode()
            }
        };
        var discountChart = new ApexCharts(document.querySelector("#discountComparisonChart"), discountOptions);
        discountChart.render();
        window.reportCharts.push(discountChart);

        // Dynamic theme synchronization observer
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'data-bs-theme') {
                    const newMode = getCurrentThemeMode();
                    window.reportCharts.forEach(function(chartInstance) {
                        if (chartInstance && typeof chartInstance.updateOptions === 'function') {
                            chartInstance.updateOptions({
                                theme: { mode: newMode },
                                tooltip: { theme: newMode }
                            });
                        }
                    });
                }
            });
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
    </script>
</body>
</html>
