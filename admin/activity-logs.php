<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'includes/permission-manager.php';
checkPageAccess();
require_once '../db_config.php';

// Make sure the table exists
if (function_exists('ensureActivityLogsTable')) {
    ensureActivityLogsTable();
}

// ---------- Filters (GET) ----------
$f_search = trim($_GET['search'] ?? '');
$f_module = trim($_GET['module'] ?? '');
$f_action = trim($_GET['action_filter'] ?? '');
$f_from   = trim($_GET['date_from'] ?? '');
$f_to     = trim($_GET['date_to'] ?? '');

$where = [];
if ($f_search !== '') {
    $s = mysqli_real_escape_string($conn, $f_search);
    $where[] = "(user_name LIKE '%$s%' OR description LIKE '%$s%' OR ip_address LIKE '%$s%')";
}
if ($f_module !== '') {
    $m = mysqli_real_escape_string($conn, $f_module);
    $where[] = "module = '$m'";
}
if ($f_action !== '') {
    $a = mysqli_real_escape_string($conn, $f_action);
    $where[] = "action = '$a'";
}
if ($f_from !== '') {
    $d = mysqli_real_escape_string($conn, $f_from);
    $where[] = "DATE(created_at) >= '$d'";
}
if ($f_to !== '') {
    $d = mysqli_real_escape_string($conn, $f_to);
    $where[] = "DATE(created_at) <= '$d'";
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Module list for the filter dropdown (distinct values present in logs)
$modules = [];
$mres = @mysqli_query($conn, "SELECT DISTINCT module FROM activity_logs ORDER BY module ASC");
if ($mres) {
    while ($mr = mysqli_fetch_assoc($mres)) {
        if (!empty($mr['module'])) { $modules[] = $mr['module']; }
    }
}
foreach (['Auth', 'Users', 'Roles', 'Activity Logs'] as $must) {
    if (!in_array($must, $modules, true)) { $modules[] = $must; }
}
sort($modules);

$actions = ['login', 'logout', 'create', 'update', 'delete'];

// ---------- Backend pagination ----------
$allowed_per_page = [10, 15, 25, 50, 100];
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 15;
if (!in_array($per_page, $allowed_per_page, true)) { $per_page = 15; }
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Total matching records (same filters)
$total_records = 0;
$cres = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM activity_logs $where_sql");
if ($cres && ($crow = mysqli_fetch_assoc($cres))) { $total_records = (int)$crow['c']; }
$total_pages = max(1, (int)ceil($total_records / $per_page));
if ($page > $total_pages) { $page = $total_pages; }
$offset = ($page - 1) * $per_page;

// Fetch one page (newest first)
$logs = [];
$lres = @mysqli_query($conn, "SELECT id, user_id, user_name, role_name, action, module, description, ip_address, created_at FROM activity_logs $where_sql ORDER BY id DESC LIMIT $per_page OFFSET $offset");
if ($lres) {
    while ($lr = mysqli_fetch_assoc($lres)) {
        $logs[] = $lr;
    }
}
$show_from = $total_records ? ($offset + 1) : 0;
$show_to = min($offset + $per_page, $total_records);

// Page-link builder (keeps all active filters)
$base_params = [];
if ($f_search !== '') { $base_params['search'] = $f_search; }
if ($f_module !== '') { $base_params['module'] = $f_module; }
if ($f_action !== '') { $base_params['action_filter'] = $f_action; }
if ($f_from !== '') { $base_params['date_from'] = $f_from; }
if ($f_to !== '') { $base_params['date_to'] = $f_to; }
$base_params['per_page'] = $per_page;
$pageUrl = function ($p) use ($base_params) {
    $q = $base_params;
    $q['page'] = max(1, (int)$p);
    return 'activity-logs.php?' . http_build_query($q);
};

// Sliding window for numbered page links
$win_start = max(1, min($page - 2, $total_pages - 4));
$win_end = min($total_pages, $win_start + 4);
$win_start = max(1, $win_end - 4);

function logActionBadge($action) {
    $a = strtolower((string)$action);
    switch ($a) {
        case 'login':  return '<span class="badge bg-success-subtle text-success">Login</span>';
        case 'logout': return '<span class="badge bg-secondary-subtle text-secondary">Logout</span>';
        case 'create': return '<span class="badge bg-primary-subtle text-primary">Create</span>';
        case 'update': return '<span class="badge bg-info-subtle text-info">Update</span>';
        case 'delete': return '<span class="badge bg-danger-subtle text-danger">Delete</span>';
        default:       return '<span class="badge bg-secondary-subtle text-secondary">' . htmlspecialchars(ucfirst($action)) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">
<head>
    <meta charset="utf-8" />
    <title>Activity Logs | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <script>
        (function () {
            const savedTheme = localStorage.getItem('silky_admin_theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
            }
        })();
    </script>
    <style>
        .filter-card .form-label { font-weight: 600; font-size: 12px; }
        [data-bs-theme="dark"] .form-control, [data-bs-theme="dark"] .form-select {
            background-color: #2a2d31;
            border-color: #3a3b45;
            color: #b1b9c7;
        }
        [data-bs-theme="dark"] .form-control:focus, [data-bs-theme="dark"] .form-select:focus {
            background-color: #2a2d31;
            border-color: #0e2187;
            color: #b1b9c7;
            box-shadow: 0 0 0 0.25rem rgba(14, 33, 135, 0.25);
        }
        [data-bs-theme="dark"] .badge.bg-success-subtle {
            background-color: rgba(25, 135, 84, 0.3) !important;
            color: #75b798 !important;
            border: 1px solid rgba(25, 135, 84, 0.5);
        }
        [data-bs-theme="dark"] .badge.bg-info-subtle {
            background-color: rgba(13, 202, 240, 0.3) !important;
            color: #6edff6 !important;
            border: 1px solid rgba(13, 202, 240, 0.5);
        }
        [data-bs-theme="dark"] .badge.bg-primary-subtle {
            background-color: rgba(14, 33, 135, 0.4) !important;
            color: #7890e7 !important;
            border: 1px solid rgba(14, 33, 135, 0.6);
        }
        [data-bs-theme="dark"] .badge.bg-danger-subtle {
            background-color: rgba(220, 53, 69, 0.3) !important;
            color: #ea868f !important;
            border: 1px solid rgba(220, 53, 69, 0.5);
        }
        [data-bs-theme="dark"] .badge.bg-secondary-subtle {
            background-color: rgba(108, 117, 125, 0.3) !important;
            color: #adb5bd !important;
            border: 1px solid rgba(108, 117, 125, 0.5);
        }
        [data-bs-theme="dark"] .table-light th {
            color: #b1b9c7;
        }
        [data-bs-theme="dark"] .page-link {
            background-color: #2a2d31;
            border-color: #3a3b45;
            color: #b1b9c7;
        }
        [data-bs-theme="dark"] .page-link:hover {
            background-color: #3a3d42;
            border-color: #4a4d52;
            color: #e2e8f0;
        }
        [data-bs-theme="dark"] .page-item.active .page-link {
            background-color: #0e2187;
            border-color: #0e2187;
            color: #fff;
        }
        [data-bs-theme="dark"] .page-item.disabled .page-link {
            background-color: #22252a;
            border-color: #3a3b45;
            color: #6c757d;
        }
    </style>
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
                            <h4 class="page-title">Activity Logs</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="#">Settings</a></li>
                                    <li class="breadcrumb-item active">Activity Logs</li>
                                </ol>
                            </div>
                        </div><!--end page-title-box-->
                    </div><!--end col-->
                </div><!--end row-->

                <!-- Filters -->
                <div class="row">
                    <div class="col-12">
                        <div class="card filter-card">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h4 class="card-title mb-0">
                                    <i class="iconoir-filter me-2 text-primary"></i>Search & Filter
                                </h4>
                                <span class="badge bg-primary-subtle text-primary">Showing <?php echo $show_from; ?>–<?php echo $show_to; ?> of <?php echo $total_records; ?></span>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="activity-logs.php">
                                    <div class="row g-3">
                                        <div class="col-md-2">
                                            <label class="form-label" for="f_search">Search</label>
                                            <input type="text" class="form-control" id="f_search" name="search"
                                                placeholder="User, description or IP..." value="<?php echo htmlspecialchars($f_search); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label" for="f_module">Module</label>
                                            <select class="form-select" id="f_module" name="module">
                                                <option value="">All Modules</option>
                                                <?php foreach ($modules as $mod): ?>
                                                    <option value="<?php echo htmlspecialchars($mod); ?>" <?php echo ($f_module === $mod) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($mod); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label" for="f_action">Action</label>
                                            <select class="form-select" id="f_action" name="action_filter">
                                                <option value="">All Actions</option>
                                                <?php foreach ($actions as $act): ?>
                                                    <option value="<?php echo $act; ?>" <?php echo ($f_action === $act) ? 'selected' : ''; ?>>
                                                        <?php echo ucfirst($act); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label" for="f_from">From Date</label>
                                            <input type="date" class="form-control" id="f_from" name="date_from" value="<?php echo htmlspecialchars($f_from); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label" for="f_to">To Date</label>
                                            <input type="date" class="form-control" id="f_to" name="date_to" value="<?php echo htmlspecialchars($f_to); ?>">
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label" for="f_perpage">Show</label>
                                            <select class="form-select" id="f_perpage" name="per_page">
                                                <?php foreach ($allowed_per_page as $pp): ?>
                                                    <option value="<?php echo $pp; ?>" <?php echo ($per_page === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-1 d-flex align-items-end gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="iconoir-search"></i>
                                            </button>
                                            <a href="activity-logs.php" class="btn btn-secondary" title="Reset filters">
                                                <i class="iconoir-refresh"></i>
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logs table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h4 class="card-title mb-0">
                                    <i class="iconoir-clock me-2 text-success"></i>All Activities
                                </h4>
                                <span class="badge bg-success-subtle text-success"><?php echo $total_records; ?> total record(s)</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="logsTable" class="table table-centered mb-0 table-nowrap">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>User</th>
                                                <th>Role</th>
                                                <th>Action</th>
                                                <th>Module</th>
                                                <th>Description</th>
                                                <th>IP Address</th>
                                                <th>Date & Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($logs)): ?>
                                            <tr><td colspan="8" class="text-center text-muted">No activity found for the selected filters</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($logs as $i => $log): ?>
                                                <tr>
                                                    <td><?php echo $offset + $i + 1; ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0 me-2">
                                                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;">
                                                                    <i class="iconoir-user"></i>
                                                                </span>
                                                            </div>
                                                            <div><?php echo htmlspecialchars($log['user_name'] ?: '—'); ?></div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($log['role_name'] ?: '—'); ?></td>
                                                    <td><?php echo logActionBadge($log['action']); ?></td>
                                                    <td><span class="badge bg-secondary-subtle text-secondary"><?php echo htmlspecialchars($log['module']); ?></span></td>
                                                    <td style="white-space:normal;min-width:220px;"><?php echo htmlspecialchars($log['description'] ?: '—'); ?></td>
                                                    <td><small class="text-muted"><?php echo htmlspecialchars($log['ip_address'] ?: '—'); ?></small></td>
                                                    <td><small><?php echo htmlspecialchars(date('d/m/Y h:i A', strtotime($log['created_at']))); ?></small></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Server-side pagination -->
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 mt-3">
                                    <small class="text-muted">Showing <?php echo $show_from; ?> to <?php echo $show_to; ?> of <?php echo $total_records; ?> entries</small>
                                    <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Activity logs pages">
                                        <ul class="pagination pagination-sm mb-0">
                                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?php echo htmlspecialchars($pageUrl($page - 1)); ?>" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a>
                                            </li>
                                            <?php if ($win_start > 1): ?>
                                                <li class="page-item"><a class="page-link" href="<?php echo htmlspecialchars($pageUrl(1)); ?>">1</a></li>
                                                <?php if ($win_start > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                                            <?php endif; ?>
                                            <?php for ($p = $win_start; $p <= $win_end; $p++): ?>
                                                <li class="page-item <?php echo ($p === $page) ? 'active' : ''; ?>">
                                                    <a class="page-link" href="<?php echo htmlspecialchars($pageUrl($p)); ?>"><?php echo $p; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <?php if ($win_end < $total_pages): ?>
                                                <?php if ($win_end < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                                                <li class="page-item"><a class="page-link" href="<?php echo htmlspecialchars($pageUrl($total_pages)); ?>"><?php echo $total_pages; ?></a></li>
                                            <?php endif; ?>
                                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?php echo htmlspecialchars($pageUrl($page + 1)); ?>" aria-label="Next"><span aria-hidden="true">&raquo;</span></a>
                                            </li>
                                        </ul>
                                    </nav>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!--end container-->
            <?php include 'footer.php'; ?>
        </div>
        <!-- end page content -->
    </div>
    <!-- end page-wrapper -->

    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/theme-manager.js"></script>
</body>
</html>
