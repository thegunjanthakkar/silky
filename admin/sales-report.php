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
$date_filter_returns = "";
$date_filter_str = "";

if ($start_date && $end_date) {
    $start = mysqli_real_escape_string($conn, $start_date) . ' 00:00:00';
    $end = mysqli_real_escape_string($conn, $end_date) . ' 23:59:59';
    $date_filter_orders = " AND created_at BETWEEN '$start' AND '$end'";
    $date_filter_returns = " AND created_at BETWEEN '$start' AND '$end'";
    $date_filter_str = " (Filtered)";
}

// Revenue metrics
$revenue_query = "SELECT SUM(total_amount) as total_revenue, AVG(total_amount) as aov, SUM(tax_amount) as total_tax, SUM(discount_amount) as total_discount FROM orders WHERE (payment_status = 'paid' OR order_status = 'confirmed') $date_filter_orders";
$revenue_result = mysqli_query($conn, $revenue_query);
$revenue_data = mysqli_fetch_assoc($revenue_result);
$total_revenue = $revenue_data['total_revenue'] ?? 0;
$aov = $revenue_data['aov'] ?? 0;
$total_tax = $revenue_data['total_tax'] ?? 0;
$total_discount = $revenue_data['total_discount'] ?? 0;

// Refund / Return metrics
$refund_query = "SELECT SUM(total_amount) as total_refunds FROM orders WHERE order_status = 'refunded' $date_filter_orders";
$refund_result = mysqli_query($conn, $refund_query);
$total_refunds = mysqli_fetch_assoc($refund_result)['total_refunds'] ?? 0;

$net_sales = $total_revenue - $total_refunds; // assuming total_revenue already factors in discounts

// Monthly Revenue Trend (Dynamic)
$monthly_query = "SELECT DATE_FORMAT(created_at, '%b %Y') as month_name, SUM(total_amount) as monthly_revenue FROM orders WHERE (payment_status = 'paid' OR order_status = 'confirmed') $date_filter_orders GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY MIN(created_at) ASC";
if (!$start_date) {
    // Default to last 6 months if no filter
    $monthly_query = "SELECT DATE_FORMAT(created_at, '%b %Y') as month_name, SUM(total_amount) as monthly_revenue FROM orders WHERE (payment_status = 'paid' OR order_status = 'confirmed') AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY MIN(created_at) ASC";
}
$monthly_result = mysqli_query($conn, $monthly_query);
$months = [];
$monthly_revenues = [];
while ($row = mysqli_fetch_assoc($monthly_result)) {
    $months[] = $row['month_name'];
    $monthly_revenues[] = (float)$row['monthly_revenue'];
}

// Payment Methods
$payment_query = "SELECT payment_method, COUNT(*) as count FROM orders WHERE 1=1 $date_filter_orders GROUP BY payment_method";
$payment_result = mysqli_query($conn, $payment_query);
$payment_methods = [];
$payment_counts = [];
while ($row = mysqli_fetch_assoc($payment_result)) {
    $method = trim($row['payment_method']);
    $payment_methods[] = !empty($method) ? ucfirst($method) : 'Other';
    $payment_counts[] = (int)$row['count'];
}

// Top Products by Sales (Integrating Product Code)
$top_products_query = "
    SELECT p.name, p.product_code, SUM(oi.quantity) as units_sold, SUM(oi.subtotal) as total_revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE (o.payment_status = 'paid' OR o.order_status = 'confirmed') " . str_replace("created_at", "o.created_at", $date_filter_orders) . "
    GROUP BY oi.product_id
    ORDER BY total_revenue DESC LIMIT 5
";
$top_products_result = mysqli_query($conn, $top_products_query);
$top_products = [];
if($top_products_result) {
    while($row = mysqli_fetch_assoc($top_products_result)) {
        $top_products[] = $row;
    }
}

// Recent Sales
$sales_query = "SELECT order_number, created_at, total_amount, payment_status, order_status FROM orders WHERE 1=1 $date_filter_orders ORDER BY created_at DESC LIMIT 10";
$sales_result = mysqli_query($conn, $sales_query);
$recent_sales = [];
while ($row = mysqli_fetch_assoc($sales_result)) {
    $recent_sales[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light" id="html-root">

<head>
    <meta charset="utf-8" />
    <title>Sales Report | Silky Admin</title>
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
                            <h4 class="page-title">Sales Report <?php echo $date_filter_str; ?></h4>
                            <div class="">
                                <form method="GET" class="d-flex align-items-center gap-2">
                                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>" required>
                                    <span>to</span>
                                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>" required>
                                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                                    <a href="sales-report.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Metrics Row -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card report-card">
                            <div class="card-body">
                                <div class="row d-flex justify-content-center">
                                    <div class="col">
                                        <p class="text-secondary mb-0 fw-semibold">Net Sales</p>
                                        <h3 class="my-1 fs-20 text-success">₹<?php echo number_format($net_sales, 2); ?></h3>
                                        <p class="mb-0 text-truncate text-muted">Gross - Refunds</p>
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
                                        <p class="text-secondary mb-0 fw-semibold">Gross Revenue</p>
                                        <h3 class="my-1 fs-20 text-primary">₹<?php echo number_format($total_revenue, 2); ?></h3>
                                        <p class="mb-0 text-truncate text-muted">Confirmed Orders</p>
                                    </div>
                                    <div class="col-auto align-self-center">
                                        <div class="flex-shrink-0 bg-primary-subtle text-primary thumb-md rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="iconoir-wallet fs-4"></i>
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
                                        <p class="text-secondary mb-0 fw-semibold">Refunds</p>
                                        <h3 class="my-1 fs-20 text-danger">₹<?php echo number_format($total_refunds, 2); ?></h3>
                                        <p class="mb-0 text-truncate text-muted">Total Refunded</p>
                                    </div>
                                    <div class="col-auto align-self-center">
                                        <div class="flex-shrink-0 bg-danger-subtle text-danger thumb-md rounded-circle d-flex align-items-center justify-content-center">
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
                                        <p class="text-secondary mb-0 fw-semibold">Total Tax</p>
                                        <h3 class="my-1 fs-20 text-warning">₹<?php echo number_format($total_tax, 2); ?></h3>
                                        <p class="mb-0 text-truncate text-muted">GST Collected</p>
                                    </div>
                                    <div class="col-auto align-self-center">
                                        <div class="flex-shrink-0 bg-warning-subtle text-warning thumb-md rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="iconoir-percentage fs-4"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Revenue Trend <?php echo $start_date ? "(Filtered)" : "(Last 6 Months)"; ?></h4>
                            </div>
                            <div class="card-body">
                                <div id="revenueTrendChart" style="height: 320px;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Orders by Payment Method</h4>
                            </div>
                            <div class="card-body">
                                <div id="paymentMethodsChart" style="height: 320px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Table Row -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Top Products by Sales</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Product</th>
                                                <th>Code</th>
                                                <th>Units Sold</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($top_products)): ?>
                                            <tr><td colspan="4" class="text-center py-3 text-muted">No product sales found</td></tr>
                                            <?php else: ?>
                                            <?php foreach ($top_products as $prod): ?>
                                            <tr>
                                                <td class="fw-medium"><?php echo htmlspecialchars($prod['name']); ?></td>
                                                <td><span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($prod['product_code']); ?></span></td>
                                                <td><?php echo $prod['units_sold']; ?></td>
                                                <td class="fw-bold text-success">₹<?php echo number_format($prod['total_revenue'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Recent Sales</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Order</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($recent_sales)): ?>
                                            <tr><td colspan="3" class="text-center py-3 text-muted">No recent sales found</td></tr>
                                            <?php else: ?>
                                            <?php foreach ($recent_sales as $sale): 
                                                $orderStatus = strtolower($sale['order_status'] ?? '');
                                                if (in_array($orderStatus, ['delivered', 'completed'])) {
                                                    $orderClass = 'bg-success-subtle text-success border border-success-subtle';
                                                } elseif (in_array($orderStatus, ['cancelled', 'failed', 'refunded'])) {
                                                    $orderClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                                                } elseif (in_array($orderStatus, ['pending', 'awaiting'])) {
                                                    $orderClass = 'bg-warning-subtle text-warning border border-warning-subtle';
                                                } else {
                                                    $orderClass = 'bg-primary-subtle text-primary border border-primary-subtle';
                                                }
                                            ?>
                                            <tr>
                                                <td class="fw-semibold text-primary">
                                                    #<?php echo htmlspecialchars($sale['order_number']); ?><br>
                                                    <small class="text-muted fw-normal"><?php echo date('M d, Y', strtotime($sale['created_at'])); ?></small>
                                                </td>
                                                <td class="fw-bold">₹<?php echo number_format($sale['total_amount'], 2); ?></td>
                                                <td>
                                                    <span class="badge rounded-pill px-2 py-1 <?php echo $orderClass; ?>">
                                                        <?php echo ucfirst($sale['order_status'] ?: 'Pending'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
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

        // Revenue Trend Chart
        var trendOptions = {
            series: [{
                name: 'Revenue',
                data: <?php echo json_encode($monthly_revenues); ?>
            }],
            chart: {
                type: 'area',
                height: 320,
                background: 'transparent',
                toolbar: { show: false }
            },
            theme: {
                mode: getCurrentThemeMode()
            },
            colors: ['#6f6af8'],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.45,
                    opacityTo: 0.05,
                    stops: [0, 90, 100]
                }
            },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 3 },
            grid: {
                borderColor: 'rgba(132, 145, 183, 0.15)',
                strokeDashArray: 3
            },
            xaxis: {
                categories: <?php echo json_encode($months); ?>,
                axisBorder: { color: 'rgba(132, 145, 183, 0.2)' },
                axisTicks: { color: 'rgba(132, 145, 183, 0.2)' }
            },
            yaxis: {
                labels: {
                    formatter: function (value) { return "₹" + Number(value).toLocaleString(); }
                }
            },
            tooltip: {
                theme: getCurrentThemeMode(),
                y: {
                    formatter: function (value) { return "₹" + Number(value).toLocaleString(); }
                }
            }
        };
        var trendChart = new ApexCharts(document.querySelector("#revenueTrendChart"), trendOptions);
        trendChart.render();
        window.reportCharts.push(trendChart);

        // Payment Methods Chart
        var paymentOptions = {
            series: <?php echo json_encode($payment_counts); ?>,
            chart: { 
                type: 'donut', 
                height: 320,
                background: 'transparent'
            },
            theme: {
                mode: getCurrentThemeMode()
            },
            labels: <?php echo json_encode($payment_methods); ?>,
            colors: ['#6f6af8', '#16cdc7', '#ffb822', '#22b783', '#fd3c97'],
            plotOptions: { 
                pie: { 
                    donut: { 
                        size: '72%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total Orders',
                                formatter: function(w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                }
                            }
                        }
                    } 
                } 
            },
            stroke: {
                colors: ['var(--bs-card-bg, #ffffff)']
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
        var paymentChart = new ApexCharts(document.querySelector("#paymentMethodsChart"), paymentOptions);
        paymentChart.render();
        window.reportCharts.push(paymentChart);

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
