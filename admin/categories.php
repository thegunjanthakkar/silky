<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <title>Categories | Silky Admin</title>
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
                            <h4 class="page-title">Categories</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="#">Categories</a></li>
                                    <li class="breadcrumb-item active">All Categories</li>
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
                                        <h4 class="card-title">Categories</h4>
                                    </div><!--end col-->
                                    <div class="col-auto">
                                        <a href="add-category.php" class="btn btn-primary">
                                            <i class="iconoir-plus me-2"></i>Add New Category
                                        </a>
                                    </div><!--end col-->
                                </div> <!--end row-->
                            </div><!--end card-header-->
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table datatable" id="datatable_1">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 40px;"></th>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                                <th>Created by</th>
                                                <th data-type="date" data-format="DD/MM/YYYY">Created at</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="sortable-categories">
                                            <?php
                                            require_once 'includes/permission-manager.php';
                                            if (!isset($conn)) {
                                                require_once '../db_config.php';
                                            }
                                            $result = mysqli_query($conn, "SELECT c.*, au.first_name, au.last_name, CONCAT(IFNULL(au.first_name, ''), ' ', IFNULL(au.last_name, '')) AS created_by_name FROM categories c LEFT JOIN admin_users au ON c.created_by = au.id ORDER BY c.display_order ASC, c.created_at ASC");
                                            if ($result && mysqli_num_rows($result) > 0) {
                                                $rowIndex = 0;
                                                while ($row = mysqli_fetch_assoc($result)) {
                                                    $rowIndex++;

                                                    echo '<tr data-id="' . $row['id'] . '">';
                                                    echo '<td class="drag-handle" style="cursor: grab;"><i class="fas fa-grip-vertical text-muted"></i></td>';
                                                    echo '<td>' . $rowIndex . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['description']) . '</td>';
                                                    echo '<td>';
                                                    if (strtolower($row['status']) === 'active') {
                                                        echo '<span class="badge bg-success-subtle text-success"><i class="fas fa-check me-1"></i>Active</span>';
                                                    } else {
                                                        echo '<span class="badge bg-danger-subtle text-danger"><i class="fas fa-xmark me-1"></i>Inactive</span>';
                                                    }
                                                    echo '</td>';

                                                    // Debug the created_by value
                                                    $createdByName = trim($row['created_by_name'] ?? '');
                                                    if (empty($createdByName) || $createdByName === ' ') {
                                                        if (!empty($row['first_name'])) {
                                                            $createdByName = trim($row['first_name'] . ' ' . ($row['last_name'] ?? ''));
                                                        } else {
                                                            $createdByName = 'Unknown User';
                                                        }
                                                    }
                                                    echo '<td>' . htmlspecialchars($createdByName) . '</td>';
                                                    echo '<td>' . ($row['created_at'] ? date('d/m/Y h:i A', strtotime($row['created_at'])) : '-') . '</td>';

                                                    echo '<td>';
                                                    echo '<div class="btn-group" role="group">';
                                                    echo '<button type="button" class="btn btn-sm btn-soft-primary" title="View"><i class="fas fa-eye"></i></button>';
                                                    echo '<button type="button" class="btn btn-sm btn-soft-secondary btn-edit-user" data-id="' . $row['id'] . '" title="Edit"><i class="fas fa-edit"></i></button>';
                                                    echo '<button type="button" class="btn btn-sm btn-soft-danger btn-delete-user" data-id="' . $row['id'] . '" title="Delete"><i class="fas fa-trash"></i></button>';
                                                    echo '</div>';
                                                    echo '</td>';
                                                    echo '</tr>';
                                                }
                                                mysqli_free_result($result);
                                            } else {
                                                echo '<tr><td colspan="10" class="text-center text-muted">No categories found</td></tr>';
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

    <!-- Sortable JS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

    <script>
        // Disable simple-datatables for drag-and-drop to work nicely across all items
        // Or if you want it, initialize without sorting and pagination
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize Sortable
            const el = document.getElementById('sortable-categories');
            if (el) {
                new Sortable(el, {
                    handle: '.drag-handle',
                    animation: 150,
                    onEnd: function () {
                        // Collect new order
                        const order = [];
                        el.querySelectorAll('tr').forEach(function (row) {
                            order.push(row.getAttribute('data-id'));
                        });

                        // Send to server
                        fetch('update-category-order', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ order: order })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Optional: show a toast notification
                                console.log('Order updated successfully');
                            } else {
                                alert('Error updating order: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while updating order');
                        });
                    }
                });
            }
        });
    </script>

    <script src="assets/js/app.js"></script>

    <!-- Theme Manager for Dark Mode Persistence -->
    <script src="assets/js/theme-manager.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.btn-edit-user').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var categoryId = this.getAttribute('data-id');
                    if (categoryId) {
                        window.location.href = 'edit-category.php?id=' + categoryId;
                    }
                });
            });
            document.querySelectorAll('.btn-delete-user').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var categoryId = this.getAttribute('data-id');
                    if (categoryId && confirm('Are you sure you want to delete this category?')) {
                        window.location.href = 'delete-category.php?id=' + categoryId;
                    }
                });
            });
        });
    </script>
</body>
<!--end body-->

</html>