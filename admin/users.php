<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'includes/permission-manager.php';
checkPageAccess();
if (!isset($conn)) {
    require_once '../db_config.php';
}
if (function_exists('ensureCreatedByColumn')) {
    ensureCreatedByColumn();
}

// Inline status toggle (no separate file): users.php?toggle_id=X flips active <-> inactive
if (isset($_GET['toggle_id'])) {
    $tid = intval($_GET['toggle_id']);
    if ($tid <= 0) {
        $_SESSION['error'] = 'Invalid user ID.';
        header('Location: users.php');
        exit;
    }
    $__current_id = (int)($_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);
    // Central rule: self never; Admin targets need Admin actor; others need creator or Admin
    [$__ok, $__reason] = canManageUser($__current_id, $tid);
    if (!$__ok) {
        $_SESSION['error'] = $__reason;
        header('Location: users.php');
        exit;
    }
    $__t_res = mysqli_query($conn, "SELECT status FROM admin_users WHERE id = $tid LIMIT 1");
    if (!$__t_res || mysqli_num_rows($__t_res) === 0) {
        $_SESSION['error'] = 'User not found.';
        header('Location: users.php');
        exit;
    }
    $__t_row = mysqli_fetch_assoc($__t_res);
    $new_status = (strtolower($__t_row['status']) === 'active') ? 'inactive' : 'active';
    if (mysqli_query($conn, "UPDATE admin_users SET status = '" . mysqli_real_escape_string($conn, $new_status) . "' WHERE id = $tid LIMIT 1")) {
        if (function_exists('logActivity')) {
            @logActivity('update', 'Users', 'Changed status to ' . $new_status . ' for admin user (ID: ' . $tid . ')');
        }
        $_SESSION['success'] = 'User ' . $new_status . ' successfully.';
    } else {
        $_SESSION['error'] = 'Error updating user status: ' . mysqli_error($conn);
    }
    header('Location: users.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <title>Users | Silky Admin</title>
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
</head>

<body>
    <?php
    // Start session first
    session_start();
    
    // Check permission for this page
    require_once 'includes/permission-manager.php';
    checkPageAccess();
    ?>

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
                            <h4 class="page-title">Users Management</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="#">User Management</a></li>
                                    <li class="breadcrumb-item active">Users</li>
                                </ol>
                            </div>
                        </div><!--end page-title-box-->
                    </div><!--end col-->
                </div><!--end row-->

                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h4 class="card-title">Users Details</h4>
                                    </div><!--end col-->
                                    <div class="col-auto">
                                        <a href="add-user.php" class="btn btn-primary">
                                            <i class="iconoir-plus me-2"></i>Add New User
                                        </a>
                                    </div><!--end col-->
                                </div> <!--end row-->
                            </div><!--end card-header-->
                            <div class="card-body">
                                <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($_SESSION['success'])): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                <div class="table-responsive">
                                    <table class="table datatable" id="datatable_1">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Email</th>
                                                <th>First Name</th>
                                                <th>Last Name</th>
                                                <th>Phone</th>
                                                <th>Status</th>
                                                <th>Role</th>
                                                <th data-type="date" data-format="DD/MM/YYYY">Created At</th>
                                                <th data-type="date" data-format="DD/MM/YYYY">Last Login</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            require_once 'includes/permission-manager.php';
                                            if (!isset($conn)) {
                                                require_once '../db_config.php';
                                            }
                                            // Who am I? Only "Admin" may manage Admin users; normal users manageable by creator OR Admin. Never self.
                                            $__current_id = (int)($_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);
                                            $__i_am_admin = function_exists('isStrictAdminById') ? isStrictAdminById($__current_id) : false;
                                            // created_by may not exist on very old DBs if migration couldn't run - fall back gracefully
                                            $__has_cb = false;
                                            $__cb_chk = @mysqli_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = 'created_by' LIMIT 1");
                                            if ($__cb_chk && mysqli_num_rows($__cb_chk) > 0) { $__has_cb = true; }
                                            $__cb_select = $__has_cb ? 'u.created_by' : 'NULL AS created_by';
                                            $result = mysqli_query($conn, "SELECT u.id, u.email, u.first_name, u.last_name, u.phone, u.status, u.role_id, u.created_at, u.last_login, r.role_name, $__cb_select FROM admin_users u LEFT JOIN admin_roles r ON u.role_id = r.id ORDER BY u.id ASC");
                                            if ($result && mysqli_num_rows($result) > 0) {
                                                $rowIndex = 0;
                                                while ($row = mysqli_fetch_assoc($result)) {
                                                    $rowIndex++;
                                                    
                                                    // Format phone number
                                                    $phone = $row['phone'];
                                                    if ($phone) {
                                                        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
                                                        if (strlen($clean_phone) == 10) {
                                                            $phone = '+91' . $clean_phone;
                                                        } elseif (strlen($clean_phone) == 12 && substr($clean_phone, 0, 2) == '91') {
                                                            $phone = '+' . $clean_phone;
                                                        } elseif (substr($clean_phone, 0, 1) == '0' && strlen($clean_phone) == 11) {
                                                            $phone = '+91' . substr($clean_phone, 1);
                                                        } elseif (substr($phone, 0, 1) !== '+') {
                                                            $phone = '+' . $phone;
                                                        }
                                                    }
                                                    
                                                    echo '<tr>';
                                                    echo '<td>' . $rowIndex . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['first_name']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['last_name']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($phone) . '</td>';
                                                    echo '<td>';
                                                    if (strtolower($row['status']) === 'active') {
                                                        echo '<span class="badge bg-success-subtle text-success"><i class="fas fa-check me-1"></i>Active</span>';
                                                    } else {
                                                        echo '<span class="badge bg-danger-subtle text-danger"><i class="fas fa-xmark me-1"></i>Inactive</span>';
                                                    }
                                                    echo '</td>';
                                                    echo '<td><span class="badge bg-primary-subtle text-primary">' . htmlspecialchars($row['role_name'] ?: 'No Role') . '</span></td>';
                                                    echo '<td>' . ($row['created_at'] ? date('d/m/Y', strtotime($row['created_at'])) : '-') . '</td>';
                                                    echo '<td>' . ($row['last_login'] ? date('d/m/Y h:i A', strtotime($row['last_login'])) : '-') . '</td>';
                                                    echo '<td>';
                                                    $is_target_admin = function_exists('isTargetAdminRole') ? isTargetAdminRole($row['role_id'] ?? 0, $row['role_name'] ?? '') : ((int)$row['role_id'] === 1);
                                                    $is_own_profile = ($__current_id > 0 && (int)$row['id'] === $__current_id);
                                                    $is_creator = (!$is_target_admin && isset($row['created_by']) && $row['created_by'] !== null && $__current_id > 0 && (int)$row['created_by'] === $__current_id);
                                                    // Edit + activate/inactivate + delete: never self; Admin targets need Admin;
                                                    // normal users need creator OR Admin
                                                    $can_edit = (!$is_own_profile && ($is_target_admin ? $__i_am_admin : ($__i_am_admin || $is_creator)));
                                                    $can_danger = $can_edit;
                                                    echo '<div class="btn-group" role="group">';
                                                    if ($can_danger) {
                                                        $is_active = (strtolower($row['status']) === 'active');
                                                        if ($is_active) {
                                                            echo '<button type="button" class="btn btn-sm btn-soft-warning btn-toggle-status" data-id="' . $row['id'] . '" data-action="deactivate" title="Deactivate user"><i class="fas fa-toggle-on"></i></button>';
                                                        } else {
                                                            echo '<button type="button" class="btn btn-sm btn-soft-success btn-toggle-status" data-id="' . $row['id'] . '" data-action="activate" title="Activate user"><i class="fas fa-toggle-off"></i></button>';
                                                        }
                                                    }
                                                    if ($can_edit) {
                                                        echo '<button type="button" class="btn btn-sm btn-soft-secondary btn-edit-user" data-id="' . $row['id'] . '" title="Edit"><i class="fas fa-edit"></i></button>';
                                                    }
                                                    // Reset password: never self (use Change Password); Admin/Super Admin
                                                    // only for users created by them (Super Admin fallback: anyone).
                                                    $__can_reset = false;
                                                    if (!$is_own_profile && function_exists('canResetUserPassword')) {
                                                        [$__can_reset] = canResetUserPassword($__current_id, (int)$row['id']);
                                                    }
                                                    if ($__can_reset) {
                                                        echo '<button type="button" class="btn btn-sm btn-soft-warning btn-reset-password" data-id="' . $row['id'] . '" title="Reset password"><i class="fas fa-key"></i></button>';
                                                    }
                                                    if ($can_danger) {
                                                        echo '<button type="button" class="btn btn-sm btn-soft-danger btn-delete-user" data-id="' . $row['id'] . '" title="Delete"><i class="fas fa-trash"></i></button>';
                                                    }
                                                    if (!$can_edit) {
                                                        $__lock_title = $is_own_profile ? 'You cannot edit/delete your own profile' : ($is_target_admin ? 'Only an Admin can manage another Admin user' : 'Only the creator or an Admin can manage this user');
                                                        echo '<span class="btn btn-sm btn-soft-dark disabled" title="' . $__lock_title . '"><i class="fas fa-lock"></i></span>';
                                                    }
                                                    echo '</div>';
                                                    echo '</td>';
                                                    echo '</tr>';
                                                }
                                                mysqli_free_result($result);
                                            } else {
                                                echo '<tr><td colspan="10" class="text-center text-muted">No users found</td></tr>';
                                            }
                                            ?>
                                                            
                                                    </div>
                                                </td>
                                            </tr>
                                           
                                        </tbody>
                                    </table>
                                </div>
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

    <!-- DataTable js -->
    <script src="assets/libs/simple-datatables/umd/simple-datatables.js"></script>
    <script>
        // Initialize DataTable
        document.addEventListener('DOMContentLoaded', function () {
            const dataTable = new simpleDatatables.DataTable("#datatable_1", {
                searchable: true,
                fixedHeight: true,
                perPage: 10,
                perPageSelect: [5, 10, 15, 20, 25],
                sortable: true,
                pagination: true,
                labels: {
                    placeholder: "Search users...",
                    searchTitle: "Search within table",
                    pageTitle: "Page {page}",
                    perPage: "users per page",
                    noRows: "No users found",
                    info: "Showing {start} to {end} of {rows} users"
                }
            });
        });
    </script>

    <script src="assets/js/app.js"></script>

    <!-- Theme Manager for Dark Mode Persistence -->
    <script src="assets/js/theme-manager.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.btn-edit-user').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var userId = this.getAttribute('data-id');
                if (userId) {
                    window.location.href = 'edit-user.php?id=' + userId;
                }
            });
        });
            document.querySelectorAll('.btn-reset-password').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var userId = this.getAttribute('data-id');
                    if (userId) {
                        window.location.href = 'reset-password.php?id=' + userId;
                    }
                });
            });
            document.querySelectorAll('.btn-delete-user').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var userId = this.getAttribute('data-id');
                    if (userId) {
                        themeConfirm('Are you sure you want to delete this user?', function() {
                            window.location.href = 'delete-user.php?id=' + userId;
                        });
                    }
                });
            });
            document.querySelectorAll('.btn-toggle-status').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var userId = this.getAttribute('data-id');
                    var action = this.getAttribute('data-action') || 'activate';
                    if (userId) {
                        var msg = (action === 'activate') ? 'Activate this user?' : 'Deactivate this user?';
                        themeConfirm(msg, function() {
                            window.location.href = 'users.php?toggle_id=' + userId;
                        });
                    }
                });
            });
    });
    </script>
</body>
<!--end body-->

</html>