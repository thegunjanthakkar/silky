<?php
/**
 * notifications.php — Admin Notifications & Store Insights
 * Fetches live data for: out-of-stock, low-stock, slow-movers,
 * top-sellers, stale payments, cancellations, and new orders.
 */
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'includes/permission-manager.php';
checkPageAccess();
require_once '../db_config.php';

// ── Helper: time-ago ─────────────────────────────────────────
function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)       return 'Just now';
    if ($diff < 3600)     return floor($diff / 60)   . ' min ago';
    if ($diff < 86400)    return floor($diff / 3600)  . ' hrs ago';
    if ($diff < 604800)   return floor($diff / 86400) . ' days ago';
    return date('d M Y', strtotime($datetime));
}

// ── 1. Out-of-stock products ────────────────────────────────
$oos_result = mysqli_query($conn, "
    SELECT p.id, p.name, p.price
    FROM products p
    LEFT JOIN stock s ON p.id = s.product_id
    WHERE p.status = 'active' AND (s.quantity IS NULL OR s.quantity = 0)
    ORDER BY p.name ASC");
$out_of_stock = [];
while ($r = mysqli_fetch_assoc($oos_result)) $out_of_stock[] = $r;

// ── 2. Low-stock products (1-4 units) ──────────────────────
$low_result = mysqli_query($conn, "
    SELECT p.id, p.name, p.price, s.quantity
    FROM products p JOIN stock s ON p.id = s.product_id
    WHERE p.status = 'active' AND s.quantity > 0 AND s.quantity < 5
    ORDER BY s.quantity ASC");
$low_stock = [];
while ($r = mysqli_fetch_assoc($low_result)) $low_stock[] = $r;

// ── 3. Slow-moving products (have stock, no sale in 30d) ───
$slow_result = mysqli_query($conn, "
    SELECT p.id, p.name, p.price,
        COALESCE((SELECT MAX(o.created_at) FROM orders o
                  JOIN order_items oi ON o.id = oi.order_id
                  WHERE oi.product_id = p.id), 'Never') as last_sold
    FROM products p JOIN stock s ON p.id = s.product_id
    WHERE p.status = 'active' AND s.quantity > 0
    AND p.id NOT IN (
        SELECT DISTINCT oi.product_id FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND oi.product_id IS NOT NULL)
    ORDER BY last_sold ASC");
$slow_movers = [];
while ($r = mysqli_fetch_assoc($slow_result)) $slow_movers[] = $r;

// ── 5. Stale pending payments (>24h) ──────────────────────
$stale_result = mysqli_query($conn, "
    SELECT id, order_number, total_amount, created_at
    FROM orders
    WHERE payment_status = 'pending'
    AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC");
$stale_payments = [];
while ($r = mysqli_fetch_assoc($stale_result)) $stale_payments[] = $r;

// ── 6. Recent cancellations (last 7 days) ─────────────────
$cancel_result = mysqli_query($conn, "
    SELECT id, order_number, total_amount, created_at
    FROM orders
    WHERE order_status = 'cancelled'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC");
$cancellations = [];
while ($r = mysqli_fetch_assoc($cancel_result)) $cancellations[] = $r;

// ── Alert counts ─────────────────────────────────────────
$critical_count = count($out_of_stock) + count($stale_payments);
$warning_count  = count($low_stock) + count($cancellations);
$total_count    = $critical_count + $warning_count;
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light" id="html-root">
<head>
    <meta charset="utf-8"/>
    <title>Notifications | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Store notifications and smart alerts for Silky Admin">
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="assets/css/icons.min.css" rel="stylesheet"/>
    <link href="assets/css/app.min.css" rel="stylesheet"/>
    <style>
        .notif-section-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--bs-secondary-color);
            padding: .75rem 1.25rem .25rem;
        }
        .notif-card {
            border-radius: 12px;
            overflow: hidden;
            border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
        }
        .notif-card .list-group-item {
            border-left: none;
            border-right: none;
            padding: .85rem 1.25rem;
            transition: background .15s;
        }
        .notif-card .list-group-item:hover { background: var(--bs-tertiary-bg); }
        .notif-dot {
            width: 9px; height: 9px;
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: 5px;
        }
        .notif-icon-wrap {
            width: 38px; height: 38px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            font-size: 17px;
        }
        .section-badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 20px;
        }
        .empty-state {
            padding: 2.5rem;
            text-align: center;
            color: var(--bs-secondary-color);
        }
    </style>
    <script>
        (function(){
            const t = localStorage.getItem('silky_admin_theme');
            if(t) document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
</head>
<body>
    <?php include 'topbar.php'; ?>
    <?php include 'leftbar.php'; ?>

    <div class="page-wrapper">
        <div class="page-content">
            <div class="container-fluid">

                <!-- Page Header -->
                <div class="row mb-3">
                    <div class="col-sm-12">
                        <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                            <div class="d-flex align-items-center gap-3">
                                <h4 class="page-title mb-0">Notifications &amp; Insights</h4>
                                <?php if ($total_count > 0): ?>
                                <span class="badge bg-danger rounded-pill"><?php echo $total_count; ?> alerts</span>
                                <?php endif; ?>
                            </div>
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                <li class="breadcrumb-item active">Notifications</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Summary Strip -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-6">
                        <div class="card border-0 text-center py-3" style="background:rgba(220,53,69,.08);border-left:3px solid #dc3545!important;">
                            <div class="fw-bold fs-22 text-danger"><?php echo $critical_count; ?></div>
                            <div class="text-muted fs-12">Critical</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-6">
                        <div class="card border-0 text-center py-3" style="background:rgba(255,193,7,.08);border-left:3px solid #ffc107!important;">
                            <div class="fw-bold fs-22 text-warning"><?php echo $warning_count; ?></div>
                            <div class="text-muted fs-12">Warnings</div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">

                    <!-- LEFT COLUMN -->
                    <div class="col-lg-12">

                        <!-- ── CRITICAL: Out of Stock ─────────────── -->
                        <div class="notif-card card mb-4">
                            <div class="card-header d-flex align-items-center justify-content-between py-3 border-bottom" style="background:rgba(220,53,69,.06);">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="notif-icon-wrap" style="background:rgba(220,53,69,.15);">
                                        <i class="las la-times-circle text-danger"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0 fw-semibold">Out of Stock</h5>
                                        <small class="text-muted">Products with zero inventory — restock required</small>
                                    </div>
                                </div>
                                <span class="badge bg-danger section-badge"><?php echo count($out_of_stock); ?> products</span>
                            </div>
                            <?php if (empty($out_of_stock)): ?>
                            <div class="empty-state">
                                <i class="las la-check-circle fs-2 text-success d-block mb-2"></i>
                                All products are well-stocked!
                            </div>
                            <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($out_of_stock as $p): ?>
                                <li class="list-group-item d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="notif-dot bg-danger mt-1"></div>
                                        <div>
                                            <span class="fw-medium"><?php echo htmlspecialchars($p['name']); ?></span>
                                            <div class="text-muted fs-12">₹<?php echo number_format($p['price'], 2); ?></div>
                                        </div>
                                    </div>
                                    <a href="stocks.php" class="btn btn-sm btn-soft-danger">Restock</a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>

                        <!-- ── WARNING: Low Stock ─────────────────── -->
                        <div class="notif-card card mb-4">
                            <div class="card-header d-flex align-items-center justify-content-between py-3 border-bottom" style="background:rgba(255,193,7,.06);">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="notif-icon-wrap" style="background:rgba(255,193,7,.15);">
                                        <i class="las la-exclamation-triangle text-warning"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0 fw-semibold">Low Stock <small class="text-muted fw-normal fs-13">(1–4 units)</small></h5>
                                        <small class="text-muted">Consider restocking soon before running out</small>
                                    </div>
                                </div>
                                <span class="badge bg-warning text-dark section-badge"><?php echo count($low_stock); ?> products</span>
                            </div>
                            <?php if (empty($low_stock)): ?>
                            <div class="empty-state">
                                <i class="las la-check-circle fs-2 text-success d-block mb-2"></i>
                                No critically low stock items right now!
                            </div>
                            <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($low_stock as $p): ?>
                                <li class="list-group-item d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="notif-dot bg-warning mt-1"></div>
                                        <div>
                                            <span class="fw-medium"><?php echo htmlspecialchars($p['name']); ?></span>
                                            <div class="text-muted fs-12">₹<?php echo number_format($p['price'], 2); ?></div>
                                        </div>
                                    </div>
                                    <span class="badge bg-warning-subtle text-warning border border-warning fs-12">
                                        <?php echo (int)$p['quantity']; ?> left
                                    </span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>

                        <!-- ── INFO: Slow Movers ───────────────────── -->
                        <div class="notif-card card mb-4">
                            <div class="card-header d-flex align-items-center justify-content-between py-3 border-bottom" style="background:rgba(108,117,125,.06);">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="notif-icon-wrap" style="background:rgba(108,117,125,.15);">
                                        <i class="las la-hourglass-half text-secondary"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0 fw-semibold">Slow Movers <small class="text-muted fw-normal fs-13">(no sale in 30 days)</small></h5>
                                        <small class="text-muted">Products in stock that haven't sold recently — consider promotions</small>
                                    </div>
                                </div>
                                <span class="badge bg-secondary-subtle text-secondary section-badge"><?php echo count($slow_movers); ?> products</span>
                            </div>
                            <?php if (empty($slow_movers)): ?>
                            <div class="empty-state">
                                <i class="las la-rocket fs-2 text-success d-block mb-2"></i>
                                All products are selling well!
                            </div>
                            <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($slow_movers as $p): ?>
                                <li class="list-group-item d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="notif-dot bg-secondary mt-1"></div>
                                        <div>
                                            <span class="fw-medium"><?php echo htmlspecialchars($p['name']); ?></span>
                                            <div class="text-muted fs-12">
                                                Last sold:
                                                <?php echo ($p['last_sold'] === 'Never')
                                                    ? '<span class="text-danger fw-medium">Never sold</span>'
                                                    : date('d M Y', strtotime($p['last_sold'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="edit-product.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-soft-secondary">Promote</a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>

                        <!-- ── CRITICAL: Stale Payments ───────────── -->
                        <div class="notif-card card mb-4">
                            <div class="card-header d-flex align-items-center justify-content-between py-3 border-bottom" style="background:rgba(253,126,20,.06);">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="notif-icon-wrap" style="background:rgba(253,126,20,.15);">
                                        <i class="las la-clock" style="color:#fd7e14;"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0 fw-semibold">Unpaid Orders &gt;24h</h5>
                                        <small class="text-muted">Orders pending payment for over 24 hours</small>
                                    </div>
                                </div>
                                <span class="badge section-badge" style="background:rgba(253,126,20,.15);color:#fd7e14;"><?php echo count($stale_payments); ?> orders</span>
                            </div>
                            <?php if (empty($stale_payments)): ?>
                            <div class="empty-state">
                                <i class="las la-check-circle fs-2 text-success d-block mb-2"></i>
                                No stale payments — all orders up to date!
                            </div>
                            <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($stale_payments as $ord): ?>
                                <li class="list-group-item d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="notif-dot mt-1" style="background:#fd7e14;"></div>
                                        <div>
                                            <span class="fw-medium"><?php echo htmlspecialchars($ord['order_number']); ?></span>
                                            <div class="text-muted fs-12"><?php echo time_ago($ord['created_at']); ?></div>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-semibold fs-13" style="color:#fd7e14;">₹<?php echo number_format($ord['total_amount'], 2); ?></div>
                                        <a href="orders.php" class="fs-11 text-muted">View →</a>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>

                        <!-- ── WARNING: Cancellations ─────────────── -->
                        <!-- <div class="notif-card card mb-4">
                            <div class="card-header d-flex align-items-center justify-content-between py-3 border-bottom" style="background:rgba(111,66,193,.06);">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="notif-icon-wrap" style="background:rgba(111,66,193,.15);">
                                        <i class="las la-ban" style="color:#6f42c1;"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0 fw-semibold">Recent Cancellations <small class="text-muted fw-normal fs-13">(last 7 days)</small></h5>
                                        <small class="text-muted">Orders cancelled this week — investigate patterns</small>
                                    </div>
                                </div>
                                <span class="badge section-badge" style="background:rgba(111,66,193,.15);color:#6f42c1;"><?php echo count($cancellations); ?> orders</span>
                            </div>
                            <?php if (empty($cancellations)): ?>
                            <div class="empty-state">
                                <i class="las la-thumbs-up fs-2 text-success d-block mb-2"></i>
                                No cancellations this week!
                            </div>
                            <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($cancellations as $ord): ?>
                                <li class="list-group-item d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="notif-dot mt-1" style="background:#6f42c1;"></div>
                                        <div>
                                            <span class="fw-medium"><?php echo htmlspecialchars($ord['order_number']); ?></span>
                                            <div class="text-muted fs-12"><?php echo time_ago($ord['created_at']); ?></div>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-semibold fs-13" style="color:#6f42c1;">₹<?php echo number_format($ord['total_amount'], 2); ?></div>
                                        <a href="orders.php" class="fs-11 text-muted">View →</a>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div> -->

                    </div><!-- /LEFT COLUMN -->

                </div><!-- /row -->

            </div><!-- /container-fluid -->

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/theme-manager.js"></script>
</body>
</html>
