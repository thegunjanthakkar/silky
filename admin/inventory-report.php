<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once '../db_config.php';

// Date Filtering for Sales-dependent metrics (Top Selling, Dead Stock)
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$date_filter_orders = "";
$date_filter_str = "";
if ($start_date && $end_date) {
    $start = mysqli_real_escape_string($conn, $start_date) . ' 00:00:00';
    $end = mysqli_real_escape_string($conn, $end_date) . ' 23:59:59';
    $date_filter_orders = " AND o.created_at BETWEEN '$start' AND '$end'";
    $date_filter_str = " (Filtered)";
}

// Total Products In Stock
$in_stock_query = "SELECT COUNT(DISTINCT product_id) as count, SUM(quantity) as total_units FROM stock WHERE quantity > 0";
$in_stock_result = mysqli_query($conn, $in_stock_query);
$in_stock_data = mysqli_fetch_assoc($in_stock_result);
$in_stock_products = $in_stock_data['count'] ?? 0;
$total_units = $in_stock_data['total_units'] ?? 0;

// Out of Stock Products
$out_stock_query = "SELECT COUNT(*) as count FROM stock WHERE quantity <= 0";
$out_stock_result = mysqli_query($conn, $out_stock_query);
$out_stock_products = $out_stock_result ? mysqli_fetch_assoc($out_stock_result)['count'] : 0;

// Inventory Valuation
$valuation_query = "
    SELECT SUM(s.quantity * COALESCE(p.cost_price, 0)) as total_value
    FROM stock s
    JOIN products p ON s.product_id = p.id
    WHERE s.quantity > 0
";
$valuation_result = mysqli_query($conn, $valuation_query);
$inventory_value = $valuation_result ? mysqli_fetch_assoc($valuation_result)['total_value'] : 0;

// Stock Levels by Category
$cat_stock_query = "
    SELECT COALESCE(c.name, 'Uncategorized') as category, SUM(s.quantity) as stock 
    FROM stock s 
    LEFT JOIN products p ON s.product_id = p.id 
    LEFT JOIN categories c ON p.category_id = c.id 
    GROUP BY p.category_id
";
$cat_stock_result = mysqli_query($conn, $cat_stock_query);
$categories = [];
$category_stocks = [];
if ($cat_stock_result) {
    while ($row = mysqli_fetch_assoc($cat_stock_result)) {
        $categories[] = $row['category'];
        $category_stocks[] = (int)($row['stock'] ?? 0);
    }
}

// Low Stock Alerts (Threshold <= 10)
$low_stock_query = "
    SELECT p.name, p.product_code, s.quantity as stock_quantity, p.price 
    FROM stock s 
    LEFT JOIN products p ON s.product_id = p.id 
    WHERE s.quantity <= 10 AND s.quantity > 0 
    ORDER BY s.quantity ASC 
    LIMIT 10
";
$low_stock_result = mysqli_query($conn, $low_stock_query);
$low_stock_items = [];
if ($low_stock_result) {
    while ($row = mysqli_fetch_assoc($low_stock_result)) {
        $low_stock_items[] = $row;
    }
}

// Top Selling Products
$top_products_query = "
    SELECT p.name, p.product_code, SUM(oi.quantity) as total_sold, SUM(oi.subtotal) as total_revenue 
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE (o.payment_status = 'paid' OR o.order_status = 'confirmed') $date_filter_orders
    GROUP BY oi.product_id 
    ORDER BY total_sold DESC 
    LIMIT 10
";
$top_products_result = mysqli_query($conn, $top_products_query);
$top_products = [];
if ($top_products_result) {
    while ($row = mysqli_fetch_assoc($top_products_result)) {
        $top_products[] = $row;
    }
}

// Dead/Slow Stock (Products in stock with 0 sales in the filtered period)
$dead_stock_query = "
    SELECT p.name, p.product_code, s.quantity as stock_quantity, p.price
    FROM stock s
    JOIN products p ON s.product_id = p.id
    WHERE s.quantity > 0 
    AND p.id NOT IN (
        SELECT oi.product_id 
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE (o.payment_status = 'paid' OR o.order_status = 'confirmed') $date_filter_orders
    )
    ORDER BY s.quantity DESC
    LIMIT 10
";
$dead_stock_result = mysqli_query($conn, $dead_stock_query);
$dead_stock_items = [];
if ($dead_stock_result) {
    while ($row = mysqli_fetch_assoc($dead_stock_result)) {
        $dead_stock_items[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light" id="html-root">

<head>
    <meta charset="utf-8" />
    <title>Inventory Report | Silky Admin</title>
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
                            <h4 class="page-title">Inventory Report <?php echo $date_filter_str; ?></h4>
                            <div class="">
                                <form method="GET" class="d-flex align-items-center gap-2">
                                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>" required>
                                    <span>to</span>
                                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>" required>
                                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                                    <a href="inventory-report.php" class="btn btn-sm btn-outline-secondary">Clear</a>
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
                                        <p class="text-secondary mb-0 fw-semibold">Products In Stock</p>
                                        <h3 class="my-1 fs-20 text-success"><?php echo number_format($in_stock_products); ?></h3>
                                        <p class="mb-0 text-truncate text-muted">Active items</p>
                                    </div>
                                    <div class="col-auto align-self-center">
                                        <div class="flex-shrink-0 bg-success-subtle text-success thumb-md rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="iconoir-check-circle fs-4"></i>
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
                                        <p class="text-secondary mb-0 fw-semibold">Total Stock Units</p>
                                        <h3 class="my-1 fs-20 text-primary"><?php echo number_format($total_units); ?></h3>
                                        <p class="mb-0 text-truncate text-muted">Physical units</p>
                                    </div>
                                    <div class="col-auto align-self-center">
                                        <div class="flex-shrink-0 bg-primary-subtle text-primary thumb-md rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="iconoir-box fs-4"></i>
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
                                        <p class="text-secondary mb-0 fw-semibold">Inventory Value</p>
                                        <h3 class="my-1 fs-20 text-info">₹<?php echo number_format($inventory_value, 2); ?></h3>
                                        <p class="mb-0 text-truncate text-muted">Cost price based</p>
                                    </div>
                                    <div class="col-auto align-self-center">
                                        <div class="flex-shrink-0 bg-info-subtle text-info thumb-md rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="iconoir-calculator fs-4"></i>
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
                                        <p class="text-secondary mb-0 fw-semibold">Out of Stock</p>
                                        <h3 class="my-1 fs-20 text-danger"><?php echo number_format($out_stock_products); ?></h3>
                                        <p class="mb-0 text-truncate text-muted">Needs restock</p>
                                    </div>
                                    <div class="col-auto align-self-center">
                                        <div class="flex-shrink-0 bg-danger-subtle text-danger thumb-md rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="iconoir-warning-triangle fs-4"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Stock Levels by Category</h4>
                            </div>
                            <div class="card-body">
                                <div id="categoryStockChart" style="height: 320px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Table Row -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Low Stock Alerts (≤ 10 Units)</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Product Name</th>
                                                <th>Code</th>
                                                <th>Current Stock</th>
                                                <th>Price</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($low_stock_items)): ?>
                                            <tr><td colspan="4" class="text-center py-3 text-success"><i class="fas fa-circle-check me-1"></i>All products are well stocked!</td></tr>
                                            <?php else: ?>
                                            <?php foreach ($low_stock_items as $item): ?>
                                            <tr>
                                                <td class="fw-medium text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($item['name'] ?? 'Product'); ?></td>
                                                <td><span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($item['product_code']); ?></span></td>
                                                <td>
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1">
                                                        <i class="fas fa-triangle-exclamation me-1"></i><?php echo $item['stock_quantity']; ?> left
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-monospace">
                                                        ₹<?php echo number_format($item['price'], 2); ?>
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
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Top Selling Products</h4>
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
                                            <tr><td colspan="4" class="text-center py-3 text-muted">No sales data found</td></tr>
                                            <?php else: ?>
                                            <?php foreach ($top_products as $product): ?>
                                            <tr>
                                                <td class="fw-medium text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td><span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($product['product_code']); ?></span></td>
                                                <td>
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1">
                                                        <i class="fas fa-cart-shopping me-1"></i><?php echo (int)$product['total_sold']; ?> units
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-monospace fs-13">
                                                        ₹<?php echo number_format($product['total_revenue'], 2); ?>
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

                <!-- Dead Stock Row -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Dead / Slow Moving Stock (0 Sales)</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Product</th>
                                                <th>Code</th>
                                                <th>Unsold Stock</th>
                                                <th>Price</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($dead_stock_items)): ?>
                                            <tr><td colspan="4" class="text-center py-3 text-success">No dead stock found!</td></tr>
                                            <?php else: ?>
                                            <?php foreach ($dead_stock_items as $product): ?>
                                            <tr>
                                                <td class="fw-medium text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td><span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($product['product_code']); ?></span></td>
                                                <td>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1">
                                                        <?php echo (int)$product['stock_quantity']; ?> units stuck
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-monospace fs-13">
                                                        ₹<?php echo number_format($product['price'], 2); ?>
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

        // Category Stock Chart
        var catStockOptions = {
            series: [{
                name: 'Total Units Available',
                data: <?php echo json_encode($category_stocks); ?>
            }],
            chart: {
                type: 'bar',
                height: 320,
                background: 'transparent',
                toolbar: { show: false }
            },
            theme: {
                mode: getCurrentThemeMode()
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: '40%',
                    distributed: true
                }
            },
            colors: ['#6f6af8', '#16cdc7', '#ffb822', '#22b783', '#fd3c97', '#1761fd'],
            dataLabels: { 
                enabled: true,
                offsetY: -20,
                style: {
                    fontSize: '12px',
                    colors: ["var(--bs-body-color, #7a82b1)"]
                }
            },
            legend: {
                show: false
            },
            grid: {
                borderColor: 'rgba(132, 145, 183, 0.15)',
                strokeDashArray: 3
            },
            xaxis: {
                categories: <?php echo json_encode($categories); ?>,
                axisBorder: { color: 'rgba(132, 145, 183, 0.2)' },
                axisTicks: { color: 'rgba(132, 145, 183, 0.2)' },
                labels: {
                    style: {
                        colors: "var(--bs-body-color, #7a82b1)",
                    }
                }
            },
            yaxis: {
                labels: {
                    formatter: function(val) { return Math.round(val); },
                    style: {
                        colors: "var(--bs-body-color, #7a82b1)",
                    }
                }
            },
            tooltip: {
                theme: getCurrentThemeMode()
            }
        };
        var catStockChart = new ApexCharts(document.querySelector("#categoryStockChart"), catStockOptions);
        catStockChart.render();
        window.reportCharts.push(catStockChart);

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
