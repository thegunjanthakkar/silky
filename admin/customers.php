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
    <title>Customers | Silky Admin</title>
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
                            <h4 class="page-title">Customers</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="#">Ecommerce</a></li>
                                    <li class="breadcrumb-item active">Customers</li>
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
                                        <h4 class="card-title">Customers Details</h4>
                                    </div><!--end col-->
                                    <div class="col-auto">
                                        <div class="dropdown">
                                            <button class="btn btn-outline-primary dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-download me-1"></i> Export
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="exportDropdown">
                                                <li><a class="dropdown-item" href="#" onclick="exportTable('csv')"><i class="fas fa-file-csv me-2 text-success"></i>CSV</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="exportTable('xlsx')"><i class="fas fa-file-excel me-2 text-success"></i>Excel (XLSX)</a></li>
                                            </ul>
                                        </div>
                                    </div><!--end col-->
                                </div> <!--end row-->
                            </div><!--end card-header-->
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table datatable" id="datatable_1">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Email</th>
                                                <th>First Name</th>
                                                <th>Last Name</th>
                                                <th>Phone</th>
                                                <th>Gender</th>
                                                <th>Status</th>
                                                <th>Verified</th>
                                                <th data-type="date" data-format="DD/MM/YYYY">Registered On</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if (!isset($conn)) {
                                                require_once '../db_config.php';
                                            }
                                            $result = mysqli_query($conn, "SELECT id, email, first_name, last_name, phone, gender, status, email_verified, created_at FROM users ORDER BY id DESC");
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
                                                    echo '<td>' . htmlspecialchars($row['email'] ?? '') . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['first_name'] ?? '') . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['last_name'] ?? '') . '</td>';
                                                    echo '<td>' . htmlspecialchars($phone ?? '-') . '</td>';
                                                    echo '<td>' . htmlspecialchars(ucfirst($row['gender'] ?? '-')) . '</td>';
                                                    echo '<td>';
                                                    if (strtolower($row['status'] ?? '') === 'active') {
                                                        echo '<span class="badge bg-success-subtle text-success"><i class="fas fa-check me-1"></i>Active</span>';
                                                    } else {
                                                        echo '<span class="badge bg-danger-subtle text-danger"><i class="fas fa-xmark me-1"></i>Inactive</span>';
                                                    }
                                                    echo '</td>';
                                                    echo '<td>';
                                                    if (isset($row['email_verified']) && $row['email_verified'] == 1) {
                                                        echo '<span class="badge bg-success-subtle text-success">Yes</span>';
                                                    } else {
                                                        echo '<span class="badge bg-warning-subtle text-warning">No</span>';
                                                    }
                                                    echo '</td>';
                                                    echo '<td>' . ($row['created_at'] ? date('d/m/Y', strtotime($row['created_at'])) : '-') . '</td>';
                                                    echo '<td>';
                                                    echo '<div class="btn-group" role="group">';
                                                    echo '<button type="button" class="btn btn-sm btn-soft-primary btn-view-customer" data-id="' . $row['id'] . '" title="View"><i class="fas fa-eye"></i></button>';
                                                    if ($phone && $phone != '-') {
                                                        $wa_phone = preg_replace('/[^0-9]/', '', $phone);
                                                        echo '<a href="tel:' . htmlspecialchars($phone) . '" class="btn btn-sm btn-soft-info" title="Call"><i class="fas fa-phone"></i></a>';
                                                        echo '<a href="https://wa.me/' . htmlspecialchars($wa_phone) . '" target="_blank" class="btn btn-sm btn-soft-success" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>';
                                                    }
                                                    echo '</div>';
                                                    echo '</td>';
                                                    echo '</tr>';
                                                }
                                            } else {
                                                echo '<tr><td colspan="10" class="text-center text-muted">No customers found</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                    
                                    <!-- Hidden Table for Exporting all data without pagination/action limits -->
                                    <table id="export_table" style="display:none;">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Email</th>
                                                <th>First Name</th>
                                                <th>Last Name</th>
                                                <th>Phone</th>
                                                <th>Gender</th>
                                                <th>Status</th>
                                                <th>Verified</th>
                                                <th>Registered On</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if (isset($result)) {
                                                mysqli_data_seek($result, 0);
                                                $exportIndex = 0;
                                                while ($row = mysqli_fetch_assoc($result)) {
                                                    $exportIndex++;
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
                                                    echo '<td>' . $exportIndex . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['email'] ?? '') . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['first_name'] ?? '') . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['last_name'] ?? '') . '</td>';
                                                    echo '<td>' . htmlspecialchars($phone ?? '-') . '</td>';
                                                    echo '<td>' . htmlspecialchars(ucfirst($row['gender'] ?? '-')) . '</td>';
                                                    echo '<td>' . (strtolower($row['status'] ?? '') === 'active' ? 'Active' : 'Inactive') . '</td>';
                                                    echo '<td>' . (isset($row['email_verified']) && $row['email_verified'] == 1 ? 'Yes' : 'No') . '</td>';
                                                    echo '<td>' . ($row['created_at'] ? date('d/m/Y', strtotime($row['created_at'])) : '-') . '</td>';
                                                    echo '</tr>';
                                                }
                                                mysqli_free_result($result);
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div><!--end card-body-->
                        </div><!--end card-->
                    </div> <!--end col-->
                </div><!--end row-->

            </div><!-- container -->

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
                    placeholder: "Search customers...",
                    searchTitle: "Search within table",
                    pageTitle: "Page {page}",
                    perPage: "customers per page",
                    noRows: "No customers found",
                    info: "Showing {start} to {end} of {rows} customers"
                }
            });
        });
    </script>

    <script src="assets/js/app.js"></script>

    <!-- Theme Manager for Dark Mode Persistence -->
    <script src="assets/js/theme-manager.js"></script>
    <!-- SheetJS for Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <script>
    function exportTable(format) {
        var table = document.getElementById("export_table");
        var wb = XLSX.utils.table_to_book(table, {sheet: "Customers"});
        
        var date = new Date();
        var dateStr = date.getFullYear() + "-" + (date.getMonth()+1).toString().padStart(2, '0') + "-" + date.getDate().toString().padStart(2, '0');
        
        if (format === 'csv') {
            XLSX.writeFile(wb, 'Silky_Customers_' + dateStr + '.csv');
        } else if (format === 'xlsx') {
            XLSX.writeFile(wb, 'Silky_Customers_' + dateStr + '.xlsx');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
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
    });
    </script>
</body>
<!--end body-->

</html>
