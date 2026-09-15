<?php
session_start();
// Include permission manager and DB
require_once 'includes/permission-manager.php';
if (!isset($conn)) {
    require_once '../db_config.php';
}
// Permission check
if (!hasPermission('user_roles')) {
    header('Location: access-denied.php');
    exit();
}
// Fetch roles
$roles = [];
try {
    if (isset($conn)) {
        $res = mysqli_query($conn, "SELECT id, role_name, role_description, functionality, created_at FROM admin_roles ORDER BY id ASC");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $funcs = [];
                if (!empty($row['functionality'])) {
                    $decoded = json_decode($row['functionality'], true);
                    if (is_array($decoded)) { $funcs = $decoded; }
                }
                $row['permissions_array'] = $funcs;
                $roles[] = $row;
            }
            mysqli_free_result($res);
        }
    }
} catch (Throwable $e) {
    // Optional logging: error_log($e->getMessage());
}
// Helper to format permission name
function formatPermissionLabel($p) {
    return ucwords(str_replace(['_', '-'], ' ', $p));
}
// Map permission to badge color classes
function permissionBadgeClass($perm) {
    $perm = strtolower($perm);
    // Dashboard
    if ($perm === 'dashboard_view') return ['bg-info-subtle','text-info'];
    // Product & catalog related
    $productKeys = ['products_view','product_add','product_edit','product_delete','stock_management','categories_view','category_add','category_edit'];
    if (in_array($perm, $productKeys, true)) return ['bg-success-subtle','text-success'];
    // Customer related
    $customerKeys = ['customers_view','manage_profiles'];
    if (in_array($perm, $customerKeys, true)) return ['bg-warning-subtle','text-warning'];
    // Order / returns
    $orderKeys = ['orders_view','returns_refunds'];
    if (in_array($perm, $orderKeys, true)) return ['bg-danger-subtle','text-danger'];
    // Settings
    $settingsKeys = ['general_settings','payment_settings','shipping_settings','email_settings'];
    if (in_array($perm, $settingsKeys, true)) return ['bg-primary-subtle','text-primary'];
    // Marketing / content
    $marketingKeys = ['edit_homepage','coupons_discounts','blogs_manage','reviews_manage'];
    if (in_array($perm, $marketingKeys, true)) return ['bg-secondary-subtle','text-secondary'];
    // Support
    if ($perm === 'support_access') return ['bg-info-subtle','text-info'];
    // Fallback
    return ['bg-primary-subtle','text-primary'];
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <title>User Roles | Silky Admin</title>
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

    <style>
        .functionality-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .functionality-item {
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            padding: 15px;
            background: #f8f9fc;
            transition: all 0.3s ease;
        }

        .functionality-item h6 {
            margin-bottom: 10px;
            color: #5a5c69;
            font-weight: 600;
        }

        .form-check {
            margin-bottom: 8px;
        }

        .form-check-input:checked {
            background-color: #0e2187;
            border-color: #0e2187;
        }

        .role-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .role-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.1);
        }

        .functionality-badge {
            font-size: 10px;
            margin: 2px;
        }

        /* Dark Mode Support */
        [data-bs-theme="dark"] .functionality-item {
            border: 1px solid #3a3b45;
            background: #2a2d31;
        }

        [data-bs-theme="dark"] .functionality-item h6 {
            color: #b1b9c7;
        }

        [data-bs-theme="dark"] .form-check-label {
            color: #9ca6b7;
        }

        [data-bs-theme="dark"] .role-card:hover {
            box-shadow: 0 4px 25px rgba(255, 255, 255, 0.1);
        }

        /* Form styling for dark mode */
        [data-bs-theme="dark"] .form-control {
            background-color: #2a2d31;
            border-color: #3a3b45;
            color: #b1b9c7;
        }

        [data-bs-theme="dark"] .form-control:focus {
            background-color: #2a2d31;
            border-color: #0e2187;
            color: #b1b9c7;
            box-shadow: 0 0 0 0.25rem rgba(14, 33, 135, 0.25);
        }

        [data-bs-theme="dark"] .form-control::placeholder {
            color: #6c757d;
        }

        /* Badge styling for dark mode */
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

        [data-bs-theme="dark"] .badge.bg-warning-subtle {
            background-color: rgba(255, 193, 7, 0.3) !important;
            color: #ffda6a !important;
            border: 1px solid rgba(255, 193, 7, 0.5);
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

        /* Generic functionality badge fallback */
        [data-bs-theme="dark"] .functionality-badge {
            font-size: 10px;
            margin: 2px;
        }

        /* Additional dark mode table styling */
        [data-bs-theme="dark"] .table-light {
            background-color: #3a3b45;
            border-color: #4a4d52;
        }

        [data-bs-theme="dark"] .table-light th {
            color: #b1b9c7;
            border-color: #4a4d52;
        }

        /* Avatar styling for dark mode */
        [data-bs-theme="dark"] .avatar-title {
            background-color: rgba(255, 255, 255, 0.1) !important;
        }
    </style>
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
                            <h4 class="page-title">User Roles Management</h4>
                            <div class="d-flex align-items-center gap-2">
                                
                                <div class="">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                        <li class="breadcrumb-item"><a href="#">User Management</a></li>
                                        <li class="breadcrumb-item active">User Roles</li>
                                    </ol>
                                </div>
                            </div>
                        </div><!--end page-title-box-->
                    </div><!--end col-->
                </div><!--end row-->

                <div class="row">
                    <!-- Roles List -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="card-title mb-0">
                                    <i class="iconoir-community me-2 text-success"></i>
                                    All User Roles
                                </h4>
                                <a href="add-role.php" class="btn btn-primary">
                                    <i class="iconoir-plus me-2"></i>Add New Role
                                </a>
                            </div>
                            <div class="card-body">
                                
                                <?php
                                // Display flash messages
                                if (isset($_SESSION['role_flash'])) {
                                    $flash = $_SESSION['role_flash'];
                                    $type = $flash['type'] === 'success' ? 'success' : 'danger';
                                    echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
                                    if (is_array($flash['messages'])) {
                                        foreach ($flash['messages'] as $msg) {
                                            echo htmlspecialchars($msg) . '<br>';
                                        }
                                    } else {
                                        echo htmlspecialchars($flash['messages']);
                                    }
                                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                                    echo '</div>';
                                    unset($_SESSION['role_flash']);
                                }
                                ?>
                                
                                <div class="table-responsive">
                                    <table id="rolesTable" class="table table-centered mb-0 table-nowrap">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Role Name</th>
                                                <th>Functionalities</th>
                                                <th>Created Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($roles)): ?>
                                            <tr><td colspan="5" class="text-center text-muted">No roles found</td></tr>
                                        <?php else: ?>
                                            <?php $rowIndex = 0; foreach ($roles as $r): $rowIndex++; ?>
                                                <tr>
                                                    <td><?php echo $rowIndex; ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0 me-2">
                                                                <div class="avatar-sm">
                                                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                        <i class="fas fa-user-shield"></i>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($r['role_name']); ?></h6>
                                                                <p class="text-muted mb-0"><?php echo htmlspecialchars($r['role_description'] ?: '—'); ?></p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if (empty($r['permissions_array'])): ?>
                                                            <span class="badge bg-secondary-subtle text-secondary functionality-badge">None</span>
                                                        <?php else: ?>
                                                            <?php 
                                                                $count = 0; 
                                                                foreach ($r['permissions_array'] as $perm): 
                                                                    if($count >= 6) { 
                                                                        echo '<span class="badge bg-secondary-subtle text-secondary functionality-badge">+'.(count($r['permissions_array'])-$count).' more</span>'; 
                                                                        break; 
                                                                    }
                                                                    $label = formatPermissionLabel($perm);
                                                                    [$bg,$text] = permissionBadgeClass($perm);
                                                                    echo '<span class="badge '.$bg.' '.$text.' functionality-badge">'.htmlspecialchars($label).'</span>';
                                                                    $count++;
                                                                endforeach; 
                                                            ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($r['created_at']))); ?></td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a type="button" class="btn btn-sm btn-soft-secondary" title="Edit" onclick="editRole(<?php echo (int)$r['id']; ?>)"><i class="fas fa-edit"></i></a>
                                                            <a type="button" class="btn btn-sm btn-soft-danger" title="Delete" onclick="deleteRole(<?php echo (int)$r['id']; ?>)"><i class="fas fa-trash"></i></a>
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

            </div><!--end container-->

            <!-- Footer Start -->
            <?php include 'footer.php'; ?>
            <!-- end Footer -->

        </div>
        <!-- end page content -->
    </div>
    <!-- end page-wrapper -->

    <!-- Javascript  -->
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>

    <!-- DataTable JS -->
    <script src="assets/libs/simple-datatables/umd/simple-datatables.js"></script>

    <script>
        // Initialize DataTable
        document.addEventListener('DOMContentLoaded', function () {
            const dataTable = new simpleDatatables.DataTable("#rolesTable", {
                searchable: true,
                fixedHeight: true,
                perPage: 10,
                perPageSelect: [5, 10, 15, 20, 25],
                sortable: true,
                pagination: true,
                labels: {
                    placeholder: "Search roles...",
                    searchTitle: "Search within table",
                    pageTitle: "Page {page}",
                    perPage: "roles per page",
                    noRows: "No roles found",
                    info: "Showing {start} to {end} of {rows} roles"
                }
            });
        });

        function editRole(roleId) {
            window.location.href = 'edit-role.php?id=' + roleId;
        }

        function deleteRole(roleId) {
            if (confirm('Are you sure you want to delete this role? This action cannot be undone.')) {
                window.location.href = 'delete-role.php?id=' + roleId;
            }
        }
    </script>

    <script src="assets/js/app.js"></script>

    <!-- Theme Manager for Dark Mode Persistence -->
    <script src="assets/js/theme-manager.js"></script>

</body>
<!--end body-->

</html>