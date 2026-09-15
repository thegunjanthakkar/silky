<?php
session_start();
require_once '../db_config.php';
// Check permission for this page
require_once 'includes/permission-manager.php';
checkPageAccess();

// Get role ID from query string
$role_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($role_id <= 0) {
    $_SESSION['error'] = 'Invalid role ID.';
    header('Location: user-roles.php');
    exit;
}

// Fetch role details
$sql = "SELECT * FROM admin_roles WHERE id = '" . mysqli_real_escape_string($conn, $role_id) . "'";
$result = mysqli_query($conn, $sql);
if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = 'Role not found.';
    header('Location: user-roles.php');
    exit;
}
$role = mysqli_fetch_assoc($result);

// Decode permissions
$permissions = [];
if (!empty($role['functionality'])) {
    $decoded = json_decode($role['functionality'], true);
    if (is_array($decoded)) {
        $permissions = $decoded;
    }
}

?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <title>Edit Role | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta content="" name="description" />
    <meta content="" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <!-- App favicon -->
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">

    <!-- App css -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />

    <!-- Dark Mode State Check Script -->
    <script>
        // Check and apply saved theme before page loads
        (function() {
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

        .select-all-btn {
            font-size: 11px;
            padding: 2px 8px;
            margin-left: 8px;
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
                            <h4 class="page-title">Edit Role</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="user-roles.php">Roles</a></li>
                                    <li class="breadcrumb-item active">Edit Role</li>
                                </ol>
                            </div>
                        </div><!--end page-title-box-->
                    </div><!--end col-->
                </div><!--end row-->

                <div class="row justify-content-center">
                    <!-- Edit Role Form -->
                    <div class="col-12" style="padding-left:0;padding-right:0;">
                        <div class="card" style="margin-left:0;margin-right:0;padding-left:0;padding-right:0;">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="card-title mb-0">
                                    <i class="iconoir-user-crown me-2 text-primary"></i>
                                    <span id="form-title">Edit Role - <?php echo htmlspecialchars($role['role_name']); ?></span>
                                </h4>
                                <a href="user-roles.php" class="btn btn-secondary">
                                    <i class="iconoir-arrow-left me-2"></i>Back to Roles
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if(isset($_SESSION['role_flash'])): $flash = $_SESSION['role_flash']; unset($_SESSION['role_flash']); ?>
                                    <div class="alert alert-<?php echo $flash['type']==='success'?'success':'danger'; ?>">
                                        <?php foreach($flash['messages'] as $m): ?>
                                            <div><?php echo htmlspecialchars($m); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <form id="roleForm" method="POST" action="includes/save-role.php">
                                    <input type="hidden" id="roleId" name="role_id" value="<?php echo $role['id']; ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="_submit_flag" value="1">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="roleName" class="form-label">Role Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="roleName" name="role_name"
                                                    placeholder="e.g., Website Manager, Product Manager" value="<?php echo htmlspecialchars($role['role_name']); ?>" required>
                                                <small class="text-muted">Enter a descriptive name for this role</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="roleDescription" class="form-label">Description</label>
                                                <input type="text" class="form-control" id="roleDescription" name="role_description"
                                                    placeholder="Brief description of this role" value="<?php echo htmlspecialchars($role['role_description']); ?>">
                                                <small class="text-muted">Optional description for this role</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <label class="form-label mb-0">Functionalities & Permissions</label>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllPermissions()">
                                                    Select All
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="clearAllPermissions()">
                                                    Clear All
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="functionality-grid">

                                            <!-- Dashboard -->
                                            <div class="functionality-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6><i class="iconoir-report-columns me-2"></i>Dashboard</h6>
                                                    <button type="button" class="btn btn-xs btn-outline-info select-all-btn" 
                                                        onclick="toggleCategorySelection('dashboard')">
                                                        Select All
                                                    </button>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input dashboard-check" type="checkbox" name="permissions[]"
                                                        value="dashboard_view" id="dashboard_view" <?php echo in_array('dashboard_view', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="dashboard_view">View Dashboard</label>
                                                </div>
                                            </div>

                                            <!-- Ecommerce - Products -->
                                            <div class="functionality-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6><i class="iconoir-cart-alt me-2"></i>Products</h6>
                                                    <button type="button" class="btn btn-xs btn-outline-info select-all-btn" 
                                                        onclick="toggleCategorySelection('products')">
                                                        Select All
                                                    </button>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input products-check" type="checkbox" name="permissions[]" value="products_view"
                                                        id="products_view" <?php echo in_array('products_view', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="products_view">View All Products</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input products-check" type="checkbox" name="permissions[]"
                                                        value="product_add" id="product_add" <?php echo in_array('product_add', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="product_add">Add Product</label>
                                                </div>
                                            </div>

                                            <!-- Categories -->
                                            <div class="functionality-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6><i class="iconoir-tag me-2"></i>Categories</h6>
                                                    <button type="button" class="btn btn-xs btn-outline-info select-all-btn" 
                                                        onclick="toggleCategorySelection('categories')">
                                                        Select All
                                                    </button>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input categories-check" type="checkbox" name="permissions[]" value="categories_view"
                                                        id="categories_view" <?php echo in_array('categories_view', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="categories_view">View All Categories</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input categories-check" type="checkbox" name="permissions[]"
                                                        value="category_add" id="category_add" <?php echo in_array('category_add', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="category_add">Add Category</label>
                                                </div>
                                            </div>

                                            <!-- Customers & Orders -->
                                            <div class="functionality-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6><i class="iconoir-user me-2"></i>Customers & Orders</h6>
                                                    <button type="button" class="btn btn-xs btn-outline-info select-all-btn" 
                                                        onclick="toggleCategorySelection('orders')">
                                                        Select All
                                                    </button>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input orders-check" type="checkbox" name="permissions[]" value="customers_view"
                                                        id="customers_view" <?php echo in_array('customers_view', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="customers_view">View Customers</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input orders-check" type="checkbox" name="permissions[]" value="orders_view"
                                                        id="orders_view" <?php echo in_array('orders_view', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="orders_view">View Orders</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input orders-check" type="checkbox" name="permissions[]"
                                                        value="returns_refunds" id="returns_refunds" <?php echo in_array('returns_refunds', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="returns_refunds">Returns & Refunds</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input orders-check" type="checkbox" name="permissions[]" value="stock_management"
                                                        id="stock_management" <?php echo in_array('stock_management', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="stock_management">Stock Management</label>
                                                </div>
                                            </div>

                                            <!-- Marketing & Content -->
                                            <div class="functionality-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6><i class="iconoir-megaphone me-2"></i>Marketing & Content</h6>
                                                    <button type="button" class="btn btn-xs btn-outline-info select-all-btn" 
                                                        onclick="toggleCategorySelection('marketing')">
                                                        Select All
                                                    </button>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input marketing-check" type="checkbox" name="permissions[]" value="edit_homepage"
                                                        id="edit_homepage" <?php echo in_array('edit_homepage', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="edit_homepage">Edit Homepage</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input marketing-check" type="checkbox" name="permissions[]"
                                                        value="coupons_discounts" id="coupons_discounts" <?php echo in_array('coupons_discounts', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="coupons_discounts">Coupons & Discounts</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input marketing-check" type="checkbox" name="permissions[]" value="blogs_manage"
                                                        id="blogs_manage" <?php echo in_array('blogs_manage', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="blogs_manage">Blogs Management</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input marketing-check" type="checkbox" name="permissions[]"
                                                        value="reviews_manage" id="reviews_manage" <?php echo in_array('reviews_manage', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="reviews_manage">Reviews Management</label>
                                                </div>
                                            </div>

                                            <!-- Utilities -->
                                            <div class="functionality-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6><i class="iconoir-tools me-2"></i>Utilities</h6>
                                                    <button type="button" class="btn btn-xs btn-outline-info select-all-btn" 
                                                        onclick="toggleCategorySelection('utilities')">
                                                        Select All
                                                    </button>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input utilities-check" type="checkbox" name="permissions[]" value="cart_wishlist"
                                                        id="cart_wishlist" <?php echo in_array('cart_wishlist', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="cart_wishlist">Cart & Wishlist</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input utilities-check" type="checkbox" name="permissions[]"
                                                        value="contact_queries" id="contact_queries" <?php echo in_array('contact_queries', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="contact_queries">Contact Queries</label>
                                                </div>
                                            </div>

                                            <!-- User Management -->
                                            <div class="functionality-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6><i class="iconoir-group me-2"></i>User Management</h6>
                                                    <button type="button" class="btn btn-xs btn-outline-info select-all-btn" 
                                                        onclick="toggleCategorySelection('users')">
                                                        Select All
                                                    </button>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input users-check" type="checkbox" name="permissions[]" value="users_list"
                                                        id="users_list" <?php echo in_array('users_list', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="users_list">View Users List</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input users-check" type="checkbox" name="permissions[]" value="manage_profiles"
                                                        id="manage_profiles" <?php echo in_array('manage_profiles', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="manage_profiles">Manage Profiles</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input users-check" type="checkbox" name="permissions[]" value="user_roles"
                                                        id="user_roles" <?php echo in_array('user_roles', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="user_roles">User Roles & Permissions</label>
                                                </div>
                                            </div>

                                            <!-- Settings -->
                                            <div class="functionality-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6><i class="iconoir-settings me-2"></i>Settings</h6>
                                                    <button type="button" class="btn btn-xs btn-outline-info select-all-btn" 
                                                        onclick="toggleCategorySelection('settings')">
                                                        Select All
                                                    </button>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input settings-check" type="checkbox" name="permissions[]" value="general_settings"
                                                        id="general_settings" <?php echo in_array('general_settings', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="general_settings">General Settings</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input settings-check" type="checkbox" name="permissions[]" value="payment_settings"
                                                        id="payment_settings" <?php echo in_array('payment_settings', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="payment_settings">Payment Settings</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input settings-check" type="checkbox" name="permissions[]"
                                                        value="shipping_settings" id="shipping_settings" <?php echo in_array('shipping_settings', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="shipping_settings">Shipping Settings</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input settings-check" type="checkbox" name="permissions[]" value="email_settings"
                                                        id="email_settings" <?php echo in_array('email_settings', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="email_settings">Email Settings</label>
                                                </div>
                                            </div>

                                            <!-- Analytics & Reports -->
                                            <div class="functionality-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6><i class="iconoir-graph-up me-2"></i>Analytics & Reports</h6>
                                                    <button type="button" class="btn btn-xs btn-outline-info select-all-btn" 
                                                        onclick="toggleCategorySelection('reports')">
                                                        Select All
                                                    </button>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input reports-check" type="checkbox" name="permissions[]" value="sales_report"
                                                        id="sales_report" <?php echo in_array('sales_report', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="sales_report">Sales Reports</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input reports-check" type="checkbox" name="permissions[]"
                                                        value="customer_analytics" id="customer_analytics" <?php echo in_array('customer_analytics', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="customer_analytics">Customer Analytics</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input reports-check" type="checkbox" name="permissions[]" value="inventory_report"
                                                        id="inventory_report" <?php echo in_array('inventory_report', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="inventory_report">Inventory Reports</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input reports-check" type="checkbox" name="permissions[]"
                                                        value="performance_metrics" id="performance_metrics" <?php echo in_array('performance_metrics', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="performance_metrics">Performance Metrics</label>
                                                </div>
                                            </div>

                                            <!-- Support -->
                                            <div class="functionality-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6><i class="iconoir-lifebelt me-2"></i>Support</h6>
                                                    <button type="button" class="btn btn-xs btn-outline-info select-all-btn" 
                                                        onclick="toggleCategorySelection('support')">
                                                        Select All
                                                    </button>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input support-check" type="checkbox" name="permissions[]" value="support_access"
                                                        id="support_access" <?php echo in_array('support_access', $permissions) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="support_access">Support Access</label>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-12">
                                            <div class="d-flex gap-2 justify-content-end">
                                                <a href="user-roles.php" class="btn btn-secondary">
                                                    <i class="iconoir-arrow-left me-2"></i>Cancel
                                                </a>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="iconoir-check me-2"></i>
                                                    <span id="submit-btn-text">Update Role</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
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

    <script>
        function selectAllPermissions() {
            const boxes = document.querySelectorAll('input[name="permissions[]"]');
            if(!boxes.length) return;
            boxes.forEach(cb => cb.checked = true);
        }

        function clearAllPermissions() {
            document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = false);
        }

        function toggleCategorySelection(category) {
            const boxes = document.querySelectorAll('.' + category + '-check');
            if(!boxes.length) return;
            const shouldCheck = Array.from(boxes).some(b => !b.checked); // if any unchecked -> check all
            boxes.forEach(b => b.checked = shouldCheck);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('roleForm');

            form.addEventListener('submit', function(e) {
                const roleName = document.getElementById('roleName').value.trim();
                const checkedBoxes = document.querySelectorAll('input[name="permissions[]"]:checked');
                if (!roleName) {
                    alert('Please enter a role name.');
                    e.preventDefault();
                    return;
                }
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one functionality for this role.');
                    e.preventDefault();
                }
            });
        });
    </script>

    <script src="assets/js/app.js"></script>

    <!-- Theme Manager for Dark Mode Persistence -->
    <script src="assets/js/theme-manager.js"></script>

</body>
</html>
