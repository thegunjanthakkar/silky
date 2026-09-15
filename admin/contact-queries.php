<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once '../db_config.php';

// Handle action (mark read, delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action === 'read') {
        mysqli_query($conn, "UPDATE contact_messages SET status = 'read' WHERE id = $id");
    } elseif ($action === 'delete') {
        mysqli_query($conn, "DELETE FROM contact_messages WHERE id = $id");
    }
    header('Location: contact-queries.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">
<head>
    <meta charset="utf-8" />
    <title>Contact Queries | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- App favicon -->
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">
    <!-- DataTable CSS -->
    <link href="assets/libs/simple-datatables/style.css" rel="stylesheet" type="text/css" />
    <!-- App css -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <!-- Dark Mode Script -->
    <script>
        (function () {
            const savedTheme = localStorage.getItem('silky_admin_theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
            }
        })();
    </script>
</head>
<body>
    <!-- Top Bar Start -->
    <?php include 'topbar.php'; ?>
    
    <!-- leftbar-tab-menu -->
    <?php include 'leftbar.php'; ?>

    <div class="page-wrapper">
        <!-- Page Content-->
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-12">
                        <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                            <h4 class="page-title">Contact Queries</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                    <li class="breadcrumb-item active">Contact Queries</li>
                                </ol>
                            </div>
                        </div><!--end page-title-box-->
                    </div><!--end col-->
                </div><!--end row-->

                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">All Customer Queries</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table datatable" id="datatable_1">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Subject</th>
                                                <th>Message</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th class="text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $query = "SELECT * FROM contact_messages ORDER BY created_at DESC";
                                            $result = mysqli_query($conn, $query);
                                            while ($row = mysqli_fetch_assoc($result)) {
                                                $status_badge = $row['status'] == 'unread' ? '<span class="badge bg-danger">Unread</span>' : '<span class="badge bg-success">Read</span>';
                                                $short_msg = strlen($row['message']) > 40 ? substr($row['message'], 0, 40) . '...' : $row['message'];
                                                ?>
                                                <tr>
                                                    <td><?php echo $row['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['subject']); ?></td>
                                                    <td title="<?php echo htmlspecialchars($row['message']); ?>"><?php echo htmlspecialchars($short_msg); ?></td>
                                                    <td><?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></td>
                                                    <td><?php echo $status_badge; ?></td>
                                                    <td class="text-end">
                                                        <?php if ($row['status'] == 'unread') { ?>
                                                            <a href="contact-queries.php?action=read&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-soft-primary"><i class="las la-check"></i> Mark Read</a>
                                                        <?php } ?>
                                                        <a href="contact-queries.php?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-soft-danger" onclick="return confirm('Are you sure you want to delete this message?');"><i class="las la-trash"></i></a>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div> <!-- end col -->
                </div> <!-- end row -->

            </div><!-- container -->

            <!--Start Rightbar-->
            <!--Start Rightbar/offcanvas-->
            <div class="offcanvas offcanvas-end" tabindex="-1" id="Appearance" aria-labelledby="AppearanceLabel">
                <div class="offcanvas-header border-bottom justify-content-between">
                  <h5 class="m-0 font-14" id="AppearanceLabel">Appearance</h5>
                  <button type="button" class="btn-close text-reset p-0 m-0 align-self-center" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">  
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="m-0">Theme</h6>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input theme-choice" type="checkbox" id="theme-switch">
                            <label class="form-check-label" for="theme-switch">Dark Mode</label>
                        </div>
                    </div>
                </div>
            </div>
            <!--end Rightbar/offcanvas-->
            <!--end Rightbar-->

        </div>
        <!-- end page content -->
    </div>
    <!-- end page-wrapper -->

    <!-- Javascript  -->
    <script src="assets/libs/simple-datatables/umd/simple-datatables.js"></script>
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('datatable_1')) {
                new simpleDatatables.DataTable("#datatable_1");
            }
            
            // Theme Switch Logic
            const themeSwitch = document.getElementById('theme-switch');
            if (themeSwitch) {
                const currentTheme = document.documentElement.getAttribute('data-bs-theme');
                themeSwitch.checked = currentTheme === 'dark';
                
                themeSwitch.addEventListener('change', function() {
                    const newTheme = this.checked ? 'dark' : 'light';
                    document.documentElement.setAttribute('data-bs-theme', newTheme);
                    localStorage.setItem('silky_admin_theme', newTheme);
                });
            }
        });
    </script>
</body>
</html>
