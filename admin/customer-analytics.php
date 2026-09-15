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

$date_filter_users = "";
$date_filter_orders = "";
$date_filter_str = "";

if ($start_date && $end_date) {
    $start = mysqli_real_escape_string($conn, $start_date) . ' 00:00:00';
    $end = mysqli_real_escape_string($conn, $end_date) . ' 23:59:59';
    $date_filter_users = " AND created_at BETWEEN '$start' AND '$end'";
    $date_filter_orders = " AND created_at BETWEEN '$start' AND '$end'";
    $date_filter_str = " (Filtered)";
}

// Total Registered Users
$users_query = "SELECT COUNT(*) as total_users FROM users WHERE 1=1 $date_filter_users";
$users_result = mysqli_query($conn, $users_query);
$total_users = $users_result ? mysqli_fetch_assoc($users_result)['total_users'] : 0;

// Total Unique Buying Customers (from orders)
$buyers_query = "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(shipping_address, '$.email'))) as total_buyers FROM orders WHERE 1=1 $date_filter_orders";
$buyers_result = mysqli_query($conn, $buyers_query);
$total_buyers = $buyers_result ? mysqli_fetch_assoc($buyers_result)['total_buyers'] : 0;

// New vs Returning Customers
$customer_counts_query = "
    SELECT 
        SUM(CASE WHEN order_count = 1 THEN 1 ELSE 0 END) as new_customers,
        SUM(CASE WHEN order_count > 1 THEN 1 ELSE 0 END) as returning_customers
    FROM (
        SELECT COUNT(id) as order_count 
        FROM orders 
        WHERE (payment_status = 'paid' OR order_status = 'confirmed') $date_filter_orders
        GROUP BY JSON_UNQUOTE(JSON_EXTRACT(shipping_address, '$.email'))
    ) as customer_orders
";
$customer_counts_result = mysqli_query($conn, $customer_counts_query);
$customer_counts = mysqli_fetch_assoc($customer_counts_result);
$new_customers = $customer_counts['new_customers'] ?? 0;
$returning_customers = $customer_counts['returning_customers'] ?? 0;
$total_active_buyers = $new_customers + $returning_customers;
$repeat_rate = $total_active_buyers > 0 ? ($returning_customers / $total_active_buyers) * 100 : 0;


// Monthly Registrations Trend (Dynamic)
$monthly_users_query = "SELECT DATE_FORMAT(created_at, '%b %Y') as month_name, COUNT(*) as new_users FROM users WHERE 1=1 $date_filter_users GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY MIN(created_at) ASC";
if (!$start_date) {
    // Default to last 6 months if no filter
    $monthly_users_query = "SELECT DATE_FORMAT(created_at, '%b %Y') as month_name, COUNT(*) as new_users FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY MIN(created_at) ASC";
}
$monthly_users_result = mysqli_query($conn, $monthly_users_query);
$months = [];
$monthly_users = [];
while ($row = mysqli_fetch_assoc($monthly_users_result)) {
    $months[] = $row['month_name'];
    $monthly_users[] = (int)$row['new_users'];
}

// Top Spenders
$spenders_query = "
    SELECT 
        JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address, '$.first_name')) as first_name,
        JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address, '$.last_name')) as last_name,
        JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address, '$.email')) as email,
        SUM(o.total_amount) as total_spent,
        COUNT(o.id) as total_orders,
        MIN(u.id) as user_id
    FROM orders o
    LEFT JOIN users u ON u.email = JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address, '$.email'))
    WHERE (o.payment_status = 'paid' OR o.order_status = 'confirmed') " . str_replace("created_at", "o.created_at", $date_filter_orders) . "
    GROUP BY JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address, '$.email'))
    ORDER BY total_spent DESC 
    LIMIT 10
";
$spenders_result = mysqli_query($conn, $spenders_query);
$top_spenders = [];
if ($spenders_result) {
    while ($row = mysqli_fetch_assoc($spenders_result)) {
        $top_spenders[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light" id="html-root">

<head>
    <meta charset="utf-8" />
    <title>Customer Analytics | Silky Admin</title>
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
                            <h4 class="page-title">Customer Analytics <?php echo $date_filter_str; ?></h4>
                            <div class="">
                                <form method="GET" class="d-flex align-items-center gap-2">
                                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>" required>
                                    <span>to</span>
                                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>" required>
                                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                                    <a href="customer-analytics.php" class="btn btn-sm btn-outline-secondary">Clear</a>
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
                                        <p class="text-secondary mb-0 fw-semibold">Total Users</p>
                                        <h3 class="my-1 fs-20 text-primary"><?php echo number_format($total_users); ?></h3>
                                        <p class="mb-0 text-truncate text-muted">Registered accounts</p>
                                    </div>
                                    <div class="col-auto align-self-center">
                                        <div class="flex-shrink-0 bg-primary-subtle text-primary thumb-md rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="iconoir-user fs-4"></i>
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
                                        <p class="text-secondary mb-0 fw-semibold">New Buyers</p>
                                        <h3 class="my-1 fs-20 text-success"><?php echo number_format($new_customers); ?></h3>
                                        <p class="mb-0 text-truncate text-muted">Placed 1 order</p>
                                    </div>
                                    <div class="col-auto align-self-center">
                                        <div class="flex-shrink-0 bg-success-subtle text-success thumb-md rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="iconoir-shopping-bag fs-4"></i>
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
                                        <p class="text-secondary mb-0 fw-semibold">Returning Buyers</p>
                                        <h3 class="my-1 fs-20 text-info"><?php echo number_format($returning_customers); ?></h3>
                                        <p class="mb-0 text-truncate text-muted">Placed > 1 orders</p>
                                    </div>
                                    <div class="col-auto align-self-center">
                                        <div class="flex-shrink-0 bg-info-subtle text-info thumb-md rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="iconoir-group fs-4"></i>
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
                                        <p class="text-secondary mb-0 fw-semibold">Repeat Purchase Rate</p>
                                        <h3 class="my-1 fs-20 text-warning"><?php echo number_format($repeat_rate, 1); ?>%</h3>
                                        <p class="mb-0 text-truncate text-muted">Retention metric</p>
                                    </div>
                                    <div class="col-auto align-self-center">
                                        <div class="flex-shrink-0 bg-warning-subtle text-warning thumb-md rounded-circle d-flex align-items-center justify-content-center">
                                            <i class="iconoir-refresh fs-4"></i>
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
                                <h4 class="card-title">User Registrations Trend <?php echo $start_date ? "(Filtered)" : "(Last 6 Months)"; ?></h4>
                            </div>
                            <div class="card-body">
                                <div id="usersTrendChart" style="height: 320px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Table Row -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Top Spenders (Highest Lifetime Value)</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Customer</th>
                                                <th>Email</th>
                                                <th>Total Orders</th>
                                                <th>Total Spent</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($top_spenders)): ?>
                                            <tr><td colspan="4" class="text-center py-3 text-muted">No customer data found</td></tr>
                                            <?php else: ?>
                                            <?php foreach ($top_spenders as $spender): 
                                                $name = trim(($spender['first_name'] ?? '') . ' ' . ($spender['last_name'] ?? ''));
                                                if (empty($name)) $name = 'Guest Customer';
                                                $initial = strtoupper(substr($name, 0, 1));
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="thumb-sm bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center me-2 fw-semibold fs-12">
                                                            <?php echo htmlspecialchars($initial); ?>
                                                        </div>
                                                        <span class="fw-medium">
                                                            <?php if (!empty($spender['user_id'])): ?>
                                                                <a href="javascript:void(0);" class="text-primary btn-view-customer text-decoration-none" data-id="<?php echo $spender['user_id']; ?>">
                                                                    <?php echo htmlspecialchars($name); ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <?php echo htmlspecialchars($name); ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="text-muted"><?php echo htmlspecialchars($spender['email'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1">
                                                        <i class="fas fa-bag-shopping me-1"></i><?php echo (int)$spender['total_orders']; ?> orders
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-monospace fs-13">
                                                        ₹<?php echo number_format($spender['total_spent'], 2); ?>
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

            <!-- Customer View Modal -->
            <div class="modal fade" id="viewCustomerModal" tabindex="-1" aria-labelledby="viewCustomerModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="viewCustomerModalLabel">Customer Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="text-center mb-4">
                                <div class="avatar-box thumb-xl bg-primary-subtle text-primary rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center fs-2 fw-semibold" id="customerInitial">
                                    C
                                </div>
                                <h4 class="mb-1" id="customerFullName">Customer Name</h4>
                                <p class="text-muted mb-0" id="customerEmail">customer@example.com</p>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <p class="text-muted mb-1 fs-12">Phone Number</p>
                                    <h6 class="mb-0" id="customerPhone">-</h6>
                                </div>
                                <div class="col-6 mb-3">
                                    <p class="text-muted mb-1 fs-12">Registered On</p>
                                    <h6 class="mb-0" id="customerDate">-</h6>
                                </div>
                                <div class="col-6 mb-3">
                                    <p class="text-muted mb-1 fs-12">Gender</p>
                                    <h6 class="mb-0" id="customerGender">-</h6>
                                </div>
                                <div class="col-6 mb-3">
                                    <p class="text-muted mb-1 fs-12">Status / Verified</p>
                                    <div class="d-flex gap-1" id="customerBadges"></div>
                                </div>
                            </div>
                            <hr class="hr-dashed my-3">
                            <div class="row text-center">
                                <div class="col-6 border-end">
                                    <p class="text-muted mb-1">Valid Orders</p>
                                    <h4 class="mb-0 text-primary" id="customerOrders">0</h4>
                                </div>
                                <div class="col-6">
                                    <p class="text-muted mb-1">Total Spent</p>
                                    <h4 class="mb-0 text-success" id="customerSpent">₹0</h4>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
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

        // Users Trend Chart
        var userTrendOptions = {
            series: [{
                name: 'New Registered Users',
                data: <?php echo json_encode($monthly_users); ?>
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
            colors: ['#6f6af8'],
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: '35%',
                    endingShape: 'rounded'
                }
            },
            dataLabels: {
                enabled: true,
                offsetY: -20,
                style: {
                    fontSize: '12px',
                    colors: ["var(--bs-body-color, #7a82b1)"]
                }
            },
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
                    formatter: function (val) { return Math.round(val); }
                }
            },
            tooltip: {
                theme: getCurrentThemeMode()
            }
        };
        var userTrendChart = new ApexCharts(document.querySelector("#usersTrendChart"), userTrendOptions);
        userTrendChart.render();
        window.reportCharts.push(userTrendChart);

        // Handle View Customer Button Click
        document.querySelector('body').addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-view-customer');
            if (btn) {
                const customerId = btn.getAttribute('data-id');
                const modal = new bootstrap.Modal(document.getElementById('viewCustomerModal'));
                
                // Set loading state
                document.getElementById('customerFullName').innerText = 'Loading...';
                document.getElementById('customerEmail').innerText = '';
                
                modal.show();

                fetch('get-customer-details.php?id=' + customerId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const c = data.customer;
                            const fullName = (c.first_name + ' ' + (c.last_name || '')).trim() || 'Guest';
                            document.getElementById('customerFullName').innerText = fullName;
                            document.getElementById('customerInitial').innerText = fullName.charAt(0).toUpperCase();
                            document.getElementById('customerEmail').innerText = c.email || '-';
                            document.getElementById('customerPhone').innerText = c.phone || '-';
                            document.getElementById('customerDate').innerText = c.formatted_date || '-';
                            document.getElementById('customerGender').innerText = c.gender ? c.gender.charAt(0).toUpperCase() + c.gender.slice(1) : '-';
                            
                            let badgesHTML = '';
                            if (c.status && c.status.toLowerCase() === 'active') {
                                badgesHTML += '<span class="badge bg-success-subtle text-success">Active</span>';
                            } else {
                                badgesHTML += '<span class="badge bg-danger-subtle text-danger">Inactive</span>';
                            }
                            if (c.email_verified == 1) {
                                badgesHTML += '<span class="badge bg-success-subtle text-success ms-1">Verified</span>';
                            } else {
                                badgesHTML += '<span class="badge bg-warning-subtle text-warning ms-1">Unverified</span>';
                            }
                            document.getElementById('customerBadges').innerHTML = badgesHTML;
                            
                            document.getElementById('customerOrders').innerText = c.order_count;
                            document.getElementById('customerSpent').innerText = '₹' + parseFloat(c.total_spent).toFixed(2);
                        } else {
                            alert(data.message || 'Error loading customer details');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to fetch customer data.');
                    });
            }
        });

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
