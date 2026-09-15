<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../db_config.php';

// Handle AJAX status update / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $review_id = intval($_POST['review_id'] ?? 0);
    $action    = $_POST['action'];

    if ($review_id > 0 && in_array($action, ['approve', 'reject', 'delete'])) {
        if ($action === 'delete') {
            $stmt = mysqli_prepare($conn, "DELETE FROM reviews WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $review_id);
        } else {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $stmt = mysqli_prepare($conn, "UPDATE reviews SET status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $status, $review_id);
        }
        echo json_encode(mysqli_stmt_execute($stmt)
            ? ['success' => true,  'message' => 'Review updated successfully.']
            : ['success' => false, 'message' => 'Database error.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    }
    exit;
}

// ── Filters ──────────────────────────────────────────────────────────────────
$filter_status  = isset($_GET['status'])  && in_array($_GET['status'],  ['approved','pending','rejected']) ? $_GET['status']  : '';
$filter_rating  = isset($_GET['rating'])  && in_array($_GET['rating'],  ['1','2','3','4','5'])             ? $_GET['rating']  : '';
$filter_product = isset($_GET['product']) ? intval($_GET['product'])                                        : 0;
$filter_search  = isset($_GET['search'])  ? trim($_GET['search'])                                           : '';
$filter_date    = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : '';

// Build WHERE
$where_parts = [];
$types = '';
$params = [];

if ($filter_status)  { $where_parts[] = "r.status = ?";               $types .= 's'; $params[] = $filter_status; }
if ($filter_rating)  { $where_parts[] = "r.rating = ?";               $types .= 'i'; $params[] = intval($filter_rating); }
if ($filter_product) { $where_parts[] = "r.product_id = ?";           $types .= 'i'; $params[] = $filter_product; }
if ($filter_search)  { $where_parts[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR p.name LIKE ?)";
                       $types .= 'ssss'; $like = '%'.$filter_search.'%';
                       $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; }
if ($filter_date)    { $where_parts[] = "DATE(r.created_at) >= ?";    $types .= 's'; $params[] = $filter_date; }
if ($filter_date_to) { $where_parts[] = "DATE(r.created_at) <= ?";    $types .= 's'; $params[] = $filter_date_to; }

$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// Fetch reviews
$sql = "SELECT r.id, r.rating, r.review_text, r.status, r.created_at,
               u.id AS user_id, u.first_name, u.last_name, u.email,
               p.name AS product_name, p.slug AS product_slug, p.id AS product_id
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        JOIN products p ON r.product_id = p.id
        $where_sql
        ORDER BY r.created_at DESC";

$reviews = [];
if ($types) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
} else {
    $res = mysqli_query($conn, $sql);
}
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) $reviews[] = $row;
}

// Counts (unfiltered)
$counts = ['approved' => 0, 'pending' => 0, 'rejected' => 0, 'total' => 0];
$count_res = mysqli_query($conn, "SELECT status, COUNT(*) as cnt FROM reviews GROUP BY status");
if ($count_res) {
    while ($row = mysqli_fetch_assoc($count_res)) {
        $counts[$row['status']] = intval($row['cnt']);
        $counts['total'] += intval($row['cnt']);
    }
}

// Products list for filter dropdown
$products_list = [];
$prod_res = mysqli_query($conn, "SELECT DISTINCT p.id, p.name FROM reviews r JOIN products p ON r.product_id = p.id ORDER BY p.name ASC");
if ($prod_res) {
    while ($row = mysqli_fetch_assoc($prod_res)) $products_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">
<head>
    <meta charset="utf-8" />
    <title>Customer Reviews | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">
    <link href="assets/libs/simple-datatables/style.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <script>
        (function () {
            const t = localStorage.getItem('silky_admin_theme');
            if (t) document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    <style>
        .star-filled { color: #f5a623; }
        .star-empty  { color: #ddd; }
        .badge-approved { background: rgba(25,135,84,0.12);  color: #198754; }
        .badge-pending  { background: rgba(255,193,7,0.15);  color: #997404; }
        .badge-rejected { background: rgba(220,53,69,0.12);  color: #dc3545; }
        .filter-link        { text-decoration: none; color: var(--bs-secondary-color); padding: 6px 14px; border-radius: 20px; font-size: .85rem; transition: all .2s; }
        .filter-link:hover  { background: var(--bs-tertiary-bg); }
        .filter-link.active { background: var(--bs-primary); color: #fff !important; font-weight: 600; }
        .filter-link.active-success { background: #198754; color: #fff !important; font-weight: 600; }
        .filter-link.active-warning { background: #997404; color: #fff !important; font-weight: 600; }
        .filter-link.active-danger  { background: #dc3545; color: #fff !important; font-weight: 600; }
        .review-cell { max-width: 220px; }
        .review-short { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; font-size: .87rem; color: #666; cursor: pointer; }
        .review-short:hover { text-decoration: underline; color: #0d6efd; }
        .customer-link { cursor: pointer; text-decoration: none; color: inherit; }
        .customer-link:hover .customer-name { text-decoration: underline; color: #0d6efd; }
        .summary-card { transition: transform .2s; }
        .summary-card:hover { transform: translateY(-3px); }
    </style>
</head>
<body>
    <?php
    require_once 'includes/permission-manager.php';
    checkPageAccess();
    ?>
    <?php include 'topbar.php'; ?>
    <?php include 'leftbar.php'; ?>

    <div class="page-wrapper">
        <div class="page-content">
            <div class="container-fluid">

                <!-- Page title -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                            <h4 class="page-title">Reviews</h4>
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                <li class="breadcrumb-item"><a href="#">Ecommerce</a></li>
                                <li class="breadcrumb-item active">Reviews</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Summary cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-3">
                        <div class="card summary-card text-center py-3 h-100 border-0 shadow-sm">
                            <div class="fs-3 fw-bold text-primary mb-1"><?php echo $counts['total']; ?></div>
                            <div class="text-muted small">Total Reviews</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="card summary-card text-center py-3 h-100 border-0 shadow-sm">
                            <div class="fs-3 fw-bold text-success mb-1"><?php echo $counts['approved']; ?></div>
                            <div class="text-muted small">Approved</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="card summary-card text-center py-3 h-100 border-0 shadow-sm">
                            <div class="fs-3 fw-bold text-warning mb-1"><?php echo $counts['pending']; ?></div>
                            <div class="text-muted small">Pending</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="card summary-card text-center py-3 h-100 border-0 shadow-sm">
                            <div class="fs-3 fw-bold text-danger mb-1"><?php echo $counts['rejected']; ?></div>
                            <div class="text-muted small">Rejected</div>
                        </div>
                    </div>
                </div>

                <!-- Filter Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body py-3">
                        <form method="GET" action="reviews" id="filterForm" class="row g-2 align-items-end">
                            <!-- Status tabs -->
                            <div class="col-12 mb-1">
                                <div class="d-flex flex-wrap gap-1">
                                    <a href="reviews" class="filter-link <?php echo $filter_status === '' ? 'active' : ''; ?>">All (<?php echo $counts['total']; ?>)</a>
                                    <a href="reviews?status=approved<?php echo $filter_rating ? '&rating='.$filter_rating : ''; ?><?php echo $filter_product ? '&product='.$filter_product : ''; ?>" class="filter-link text-success <?php echo $filter_status === 'approved' ? 'active-success' : ''; ?>">✓ Approved (<?php echo $counts['approved']; ?>)</a>
                                    <a href="reviews?status=pending<?php echo $filter_rating ? '&rating='.$filter_rating : ''; ?><?php echo $filter_product ? '&product='.$filter_product : ''; ?>"  class="filter-link text-warning <?php echo $filter_status === 'pending'  ? 'active-warning' : ''; ?>">⏳ Pending (<?php echo $counts['pending']; ?>)</a>
                                    <a href="reviews?status=rejected<?php echo $filter_rating ? '&rating='.$filter_rating : ''; ?><?php echo $filter_product ? '&product='.$filter_product : ''; ?>" class="filter-link text-danger  <?php echo $filter_status === 'rejected' ? 'active-danger'  : ''; ?>">✕ Rejected (<?php echo $counts['rejected']; ?>)</a>
                                </div>
                            </div>

                            <?php if ($filter_status): ?>
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                            <?php endif; ?>

                            <!-- Search -->
                            <div class="col-12 col-sm-4 col-md-3">
                                <label class="form-label mb-1 small fw-semibold">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Name, email, product…" value="<?php echo htmlspecialchars($filter_search); ?>">
                            </div>

                            <!-- Rating -->
                            <div class="col-6 col-sm-3 col-md-2">
                                <label class="form-label mb-1 small fw-semibold">Rating</label>
                                <select name="rating" class="form-select form-select-sm">
                                    <option value="">All Ratings</option>
                                    <?php for($r=5;$r>=1;$r--): ?>
                                    <option value="<?php echo $r; ?>" <?php echo $filter_rating == $r ? 'selected' : ''; ?>><?php echo str_repeat('★',$r) . str_repeat('☆',5-$r); ?> (<?php echo $r; ?>)</option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <!-- Product -->
                            <div class="col-6 col-sm-4 col-md-3">
                                <label class="form-label mb-1 small fw-semibold">Product</label>
                                <select name="product" class="form-select form-select-sm">
                                    <option value="">All Products</option>
                                    <?php foreach ($products_list as $pl): ?>
                                    <option value="<?php echo $pl['id']; ?>" <?php echo $filter_product == $pl['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($pl['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Date From -->
                            <div class="col-6 col-sm-3 col-md-2">
                                <label class="form-label mb-1 small fw-semibold">From</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_date); ?>">
                            </div>

                            <!-- Date To -->
                            <div class="col-6 col-sm-3 col-md-2">
                                <label class="form-label mb-1 small fw-semibold">To</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                            </div>

                            <!-- Buttons -->
                            <div class="col-auto d-flex gap-2 align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fas fa-filter me-1"></i>Filter</button>
                                <a href="reviews" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Table Card -->
                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h4 class="card-title mb-0">
                                    Reviews
                                    <span class="badge bg-secondary ms-2"><?php echo count($reviews); ?></span>
                                </h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="reviews-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3">#</th>
                                                <th>Customer</th>
                                                <th>Product</th>
                                                <th>Rating</th>
                                                <th>Review</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th class="text-end pe-3">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($reviews)): ?>
                                                <tr><td colspan="8" class="text-center text-muted py-5"><i class="fas fa-star-half-alt fa-2x mb-2 d-block opacity-25"></i>No reviews found.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($reviews as $i => $r): ?>
                                                <tr id="review-row-<?php echo $r['id']; ?>">
                                                    <td class="ps-3 text-muted small"><?php echo $i + 1; ?></td>

                                                    <!-- Customer (clickable → opens modal) -->
                                                    <td>
                                                        <span class="customer-link btn-view-customer d-flex align-items-center gap-2" data-id="<?php echo $r['user_id']; ?>" style="cursor:pointer;">
                                                            <div class="avatar-box thumb-xs bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:32px;height:32px;font-size:.85rem;flex-shrink:0;">
                                                                <?php echo strtoupper(substr($r['first_name'],0,1)); ?>
                                                            </div>
                                                            <div>
                                                                <div class="customer-name fw-semibold" style="font-size:.88rem;"><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?></div>
                                                                <div class="text-muted" style="font-size:.78rem;"><?php echo htmlspecialchars($r['email']); ?></div>
                                                            </div>
                                                        </span>
                                                    </td>

                                                    <!-- Product -->
                                                    <td>
                                                        <a href="../product-details?slug=<?php echo htmlspecialchars($r['product_slug']); ?>" target="_blank" class="text-primary text-decoration-none" style="font-size:.88rem;">
                                                            <?php echo htmlspecialchars($r['product_name']); ?>
                                                            <i class="fas fa-external-link-alt ms-1" style="font-size:.7rem;opacity:.6;"></i>
                                                        </a>
                                                    </td>

                                                    <!-- Rating stars -->
                                                    <td class="text-nowrap">
                                                        <?php for($s=1;$s<=5;$s++): ?>
                                                            <i class="fas fa-star <?php echo $s<=$r['rating'] ? 'star-filled' : 'star-empty'; ?>" style="font-size:.8rem;"></i>
                                                        <?php endfor; ?>
                                                        <span class="text-muted small ms-1"><?php echo $r['rating']; ?>/5</span>
                                                    </td>

                                                    <!-- Review text (truncated, click to see full) -->
                                                    <td class="review-cell">
                                                        <?php $text = htmlspecialchars($r['review_text']); $needsModal = strlen($r['review_text']) > 80; ?>
                                                        <?php if ($needsModal): ?>
                                                            <span class="review-short btn-view-review"
                                                                  data-id="<?php echo $r['id']; ?>"
                                                                  data-text="<?php echo $text; ?>"
                                                                  data-rating="<?php echo $r['rating']; ?>"
                                                                  data-product="<?php echo htmlspecialchars($r['product_name']); ?>"
                                                                  data-customer="<?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?>"
                                                                  data-date="<?php echo date('d M Y', strtotime($r['created_at'])); ?>"
                                                                  title="Click to read full review">
                                                                <?php echo $text; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span style="font-size:.87rem;color:#555;"><?php echo $text; ?></span>
                                                        <?php endif; ?>
                                                    </td>

                                                    <!-- Status badge -->
                                                    <td>
                                                        <span class="badge badge-<?php echo $r['status']; ?> status-badge px-2 py-1" id="badge-<?php echo $r['id']; ?>">
                                                            <?php echo ucfirst($r['status']); ?>
                                                        </span>
                                                    </td>

                                                    <!-- Date -->
                                                    <td class="text-nowrap text-muted small"><?php echo date('d M Y', strtotime($r['created_at'])); ?></td>

                                                    <!-- Actions -->
                                                    <td class="text-end pe-3">
                                                        <div class="d-flex gap-1 justify-content-end flex-nowrap">
                                                            <?php if ($r['status'] !== 'approved'): ?>
                                                            <button class="btn btn-sm btn-soft-success action-btn"
                                                                    data-id="<?php echo $r['id']; ?>" data-action="approve" title="Approve">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                            <?php if ($r['status'] !== 'rejected'): ?>
                                                            <button class="btn btn-sm btn-soft-warning action-btn"
                                                                    data-id="<?php echo $r['id']; ?>" data-action="reject" title="Reject">
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                            <button class="btn btn-sm btn-soft-danger action-btn"
                                                                    data-id="<?php echo $r['id']; ?>" data-action="delete" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
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

            </div><!-- /container-fluid -->
        </div><!-- /page-content -->
        <?php include 'footer.php'; ?>
    </div><!-- /page-wrapper -->

    <!-- ═══════════ Customer Detail Modal (same as customer-analytics) ═══════════ -->
    <div class="modal fade" id="viewCustomerModal" tabindex="-1" aria-labelledby="viewCustomerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewCustomerModalLabel">Customer Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="avatar-box thumb-xl bg-primary-subtle text-primary rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center fs-2 fw-semibold" id="customerInitial">C</div>
                        <h4 class="mb-1" id="customerFullName">Loading…</h4>
                        <p class="text-muted mb-0" id="customerEmail"></p>
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

    <!-- ═══════════ Full Review Modal ═══════════ -->
    <div class="modal fade" id="viewReviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-1">
                    <h5 class="modal-title fw-bold" id="reviewModalProduct">Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-2">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <div class="fw-semibold" id="reviewModalCustomer"></div>
                            <div class="text-muted small" id="reviewModalDate"></div>
                        </div>
                        <div id="reviewModalStars" class="fs-5"></div>
                    </div>
                    <hr class="my-2">
                    <p id="reviewModalText" class="mb-0" style="line-height:1.75; white-space:pre-line;"></p>
                </div>
                <div class="modal-footer border-0 pt-1">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index:1090;">
        <div id="adminToast" class="toast align-items-center border-0" role="alert" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-semibold" id="adminToastMsg"></div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/libs/simple-datatables/umd/simple-datatables.js"></script>
    <script src="assets/js/app.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        /* ── Toast ── */
        function showToast(msg, type) {
            const el = document.getElementById('adminToast');
            const msgEl = document.getElementById('adminToastMsg');
            el.classList.remove('bg-success','bg-danger','bg-warning','text-white');
            if (type === 'success') el.classList.add('bg-success','text-white');
            else if (type === 'error') el.classList.add('bg-danger','text-white');
            else el.classList.add('bg-warning');
            msgEl.textContent = msg;
            bootstrap.Toast.getOrCreateInstance(el, { delay: 3000 }).show();
        }

        /* ── Customer modal (identical to customer-analytics) ── */
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-view-customer');
            if (btn) {
                const customerId = btn.getAttribute('data-id');
                const modal = new bootstrap.Modal(document.getElementById('viewCustomerModal'));

                document.getElementById('customerFullName').innerText = 'Loading…';
                document.getElementById('customerEmail').innerText    = '';
                document.getElementById('customerPhone').innerText    = '-';
                document.getElementById('customerDate').innerText     = '-';
                document.getElementById('customerGender').innerText   = '-';
                document.getElementById('customerBadges').innerHTML   = '';
                document.getElementById('customerOrders').innerText   = '0';
                document.getElementById('customerSpent').innerText    = '₹0';
                modal.show();

                fetch('get-customer-details.php?id=' + customerId)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const c = data.customer;
                            const fullName = (c.first_name + ' ' + (c.last_name || '')).trim() || 'Guest';
                            document.getElementById('customerFullName').innerText = fullName;
                            document.getElementById('customerInitial').innerText  = fullName.charAt(0).toUpperCase();
                            document.getElementById('customerEmail').innerText    = c.email || '-';
                            document.getElementById('customerPhone').innerText    = c.phone || '-';
                            document.getElementById('customerDate').innerText     = c.formatted_date || '-';
                            document.getElementById('customerGender').innerText   = c.gender ? c.gender.charAt(0).toUpperCase() + c.gender.slice(1) : '-';

                            let badges = '';
                            badges += c.status?.toLowerCase() === 'active'
                                ? '<span class="badge bg-success-subtle text-success">Active</span>'
                                : '<span class="badge bg-danger-subtle text-danger">Inactive</span>';
                            badges += c.email_verified == 1
                                ? '<span class="badge bg-success-subtle text-success ms-1">Verified</span>'
                                : '<span class="badge bg-warning-subtle text-warning ms-1">Unverified</span>';
                            document.getElementById('customerBadges').innerHTML = badges;
                            document.getElementById('customerOrders').innerText = c.order_count;
                            document.getElementById('customerSpent').innerText  = '₹' + parseFloat(c.total_spent).toFixed(2);
                        } else {
                            showToast(data.message || 'Error loading customer details', 'error');
                        }
                    })
                    .catch(() => showToast('Failed to fetch customer data.', 'error'));
            }
        });

        /* ── Full review modal ── */
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-view-review');
            if (btn) {
                const rating   = parseInt(btn.dataset.rating);
                let stars = '';
                for (let s = 1; s <= 5; s++) {
                    stars += `<i class="fas fa-star ${s <= rating ? 'star-filled' : 'star-empty'}"></i>`;
                }
                document.getElementById('reviewModalProduct').textContent  = btn.dataset.product;
                document.getElementById('reviewModalCustomer').textContent = btn.dataset.customer;
                document.getElementById('reviewModalDate').textContent     = btn.dataset.date;
                document.getElementById('reviewModalStars').innerHTML      = stars + ` <small class="text-muted ms-1">(${rating}/5)</small>`;
                document.getElementById('reviewModalText').textContent     = btn.dataset.text;
                new bootstrap.Modal(document.getElementById('viewReviewModal')).show();
            }
        });

        /* ── Review actions (approve / reject / delete) ── */
        const badgeMap = {
            approve: { cls: 'badge-approved', label: 'Approved' },
            reject:  { cls: 'badge-rejected', label: 'Rejected'  }
        };

        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.action-btn');
            if (!btn) return;

            const id     = btn.dataset.id;
            const action = btn.dataset.action;

            if (action === 'delete' && !confirm('Are you sure you want to permanently delete this review?')) return;

            btn.disabled = true;
            const orig = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            const fd = new FormData();
            fd.append('review_id', id);
            fd.append('action', action);

            fetch('reviews.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    if (data.success) {
                        showToast(data.message, 'success');
                        if (action === 'delete') {
                            document.getElementById('review-row-' + id)?.remove();
                        } else {
                            const badge = document.getElementById('badge-' + id);
                            if (badge) {
                                badge.className = 'badge px-2 py-1 status-badge ' + badgeMap[action].cls;
                                badge.textContent = badgeMap[action].label;
                            }
                            setTimeout(() => location.reload(), 900);
                        }
                    } else {
                        showToast(data.message || 'Error', 'error');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    showToast('Network error. Please try again.', 'error');
                });
        });
    });
    </script>
</body>
</html>
