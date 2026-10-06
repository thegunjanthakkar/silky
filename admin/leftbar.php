<?php
// Permission-gated sidebar: users only see links they are allowed to access.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('hasPermission') && file_exists(__DIR__ . '/includes/permission-manager.php')) {
    require_once __DIR__ . '/includes/permission-manager.php';
}

$__can = function ($perm) {
    if (function_exists('hasPermission')) {
        return hasPermission($perm);
    }
    return true; // fail-open only if permission system missing
};
$__any = function (array $perms) use ($__can) {
    foreach ($perms as $p) {
        if ($__can($p)) return true;
    }
    return false;
};

// Individual page permissions
$can_dashboard       = $__can('dashboard_view');
$can_products_view   = $__can('products_view');
$can_product_add     = $__can('product_add');
$can_categories_view = $__can('categories_view');
$can_category_add    = $__can('category_add');
$can_customers       = $__can('customers_view');
$can_orders_view     = $__can('orders_view');
$can_orders          = $can_orders_view;
$can_stock           = $__can('stock_management');
$can_reviews         = $__can('reviews_manage');

$can_coupons         = $__can('coupons_discounts');
$can_edit_homepage   = $__can('edit_homepage');
$can_contact         = $__can('contact_queries');

$can_users_list      = $__can('users_list');
$can_user_roles      = $__can('user_roles');

$can_general         = $__can('general_settings');
$can_payment         = $__can('payment_settings');
$can_email           = $__can('email_settings');
$can_activity        = $__can('activity_logs');

$can_sales           = $__can('sales_report');
$can_cust_analytics  = $__can('customer_analytics');
$can_inventory       = $__can('inventory_report');
$can_perf            = $__can('performance_metrics');

// Parent-section visibility
$show_products_sub   = ($can_products_view || $can_product_add);
$show_categories_sub = ($can_categories_view || $can_category_add);
$show_ecommerce      = ($show_products_sub || $show_categories_sub || $can_customers || $can_orders || $can_stock || $can_reviews);

$show_marketing       = $can_coupons;
$show_content         = $can_edit_homepage;
$show_marketing_label = ($show_marketing || $show_content);

$show_utilities = $can_contact;

$show_usermgmt = ($can_users_list || $can_user_roles);

$show_settings = ($can_general || $can_payment || $can_email || $can_activity);
$show_reports  = ($can_sales || $can_cust_analytics || $can_inventory || $can_perf);
$show_system_label = ($show_usermgmt || $show_settings || $show_reports);
?>
<div class="startbar d-print-none">
        <!--start brand-->
        <div class="brand">
            <a href="./" class="logo">
                <span>
                    <p alt="Silky Saree" class="logo-sm" title="Dashboard">SILKY SAREE</p>
                </span>
                <span class="">
                    <p alt="Silky Saree" class="logo-lg logo-light" title="Dashboard">SILKY SAREE</p>
                    <p alt="Silky Saree" class="logo-lg logo-dark" title="Dashboard">SILKY SAREE</p>
                </span>
            </a>
        </div>
        <!--end brand-->
        <!--start startbar-menu-->
        <div class="startbar-menu" >
            <div class="startbar-collapse" id="startbarCollapse" data-simplebar>
                <div class="d-flex align-items-start flex-column w-100">
                    <!-- Navigation -->
                    <ul class="navbar-nav mb-auto w-100">
                        <li class="menu-label mt-2">
                            <span>Main Menu</span>
                        </li>

                        <!-- Dashboard : only visible with dashboard_view permission -->
                        <?php if ($can_dashboard): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="./">
                                <i class="iconoir-report-columns menu-icon"></i>
                                <span>Dashboard</span>
                            </a>
                        </li><!--end nav-item-->
                        <?php endif; ?>

                        <?php if ($show_ecommerce): ?>
                        <!-- Ecommerce -->
                        <li class="nav-item">
                            <a class="nav-link" href="#sidebarEcommerce" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarEcommerce">
                                <i class="iconoir-cart-alt menu-icon"></i>
                                <span>Ecommerce</span>
                            </a>
                            <div class="collapse " id="sidebarEcommerce">
                                <ul class="nav flex-column">
                                    <?php if ($show_products_sub): ?>
                                    <!-- Products -->
                                    <li class="nav-item">
                                        <a class="nav-link" href="#sidebarProducts" data-bs-toggle="collapse" role="button"
                                            aria-expanded="false" aria-controls="sidebarProducts">Products</a>
                                        <div class="collapse " id="sidebarProducts">
                                            <ul class="nav flex-column">
                                                <?php if ($can_product_add): ?>
                                                <li class="nav-item">
                                                    <a class="nav-link" href="./add-product">Add Product</a>
                                                </li><!--end nav-item-->
                                                <?php endif; ?>
                                                <?php if ($can_products_view): ?>
                                                <li class="nav-item">
                                                    <a class="nav-link" href="./products">All Products</a>
                                                </li><!--end nav-item-->
                                                <?php endif; ?>
                                            </ul><!--end nav-->
                                        </div>
                                    </li><!--end nav-item-->
                                    <?php endif; ?>

                                    <?php if ($show_categories_sub): ?>
                                    <!-- Categories -->
                                    <li class="nav-item">
                                        <a class="nav-link" href="#sidebarCategories" data-bs-toggle="collapse" role="button"
                                            aria-expanded="false" aria-controls="sidebarCategories">Categories</a>
                                        <div class="collapse " id="sidebarCategories">
                                            <ul class="nav flex-column">
                                                <?php if ($can_category_add): ?>
                                                <li class="nav-item">
                                                    <a class="nav-link" href="./add-category">Add Category</a>
                                                </li><!--end nav-item-->
                                                <?php endif; ?>
                                                <?php if ($can_categories_view): ?>
                                                <li class="nav-item">
                                                    <a class="nav-link" href="./categories">All Categories</a>
                                                </li><!--end nav-item-->
                                                <?php endif; ?>
                                            </ul><!--end nav-->
                                        </div>
                                    </li><!--end nav-item-->
                                    <?php endif; ?>

                                    <?php if ($can_customers): ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="./customers">Customers</a>
                                    </li><!--end nav-item-->
                                    <?php endif; ?>
                                    <?php if ($can_orders): ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="#sidebarOrders" data-bs-toggle="collapse" role="button"
                                            aria-expanded="false" aria-controls="sidebarOrders">Orders</a>
                                        <div class="collapse " id="sidebarOrders">
                                            <ul class="nav flex-column">
                                                <?php if ($can_orders_view): ?>
                                                <li class="nav-item">
                                                    <a class="nav-link" href="./orders">All Orders</a>
                                                </li><!--end nav-item-->
                                                <?php endif; ?>

                                            </ul><!--end nav-->
                                        </div>
                                    </li><!--end nav-item-->
                                    <?php endif; ?>
                                    <?php if ($can_stock): ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="./stocks">Stock Management</a>
                                    </li><!--end nav-item-->
                                    <?php endif; ?>
                                    <?php if ($can_reviews): ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="./reviews">Customer Reviews</a>
                                    </li><!--end nav-item-->
                                    <?php endif; ?>
                                </ul><!--end nav-->
                            </div>
                        </li><!--end nav-item-->
                        <?php endif; ?>

                        <?php if ($show_marketing_label): ?>
                        <li class="menu-label mt-2">
                            <span>Marketing & Content</span>
                        </li>
                        <?php endif; ?>
                        <?php if ($show_marketing): ?>
                        <!-- Marketing -->
                        <li class="nav-item">
                            <a class="nav-link" href="#sidebarMarketing" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarMarketing">
                                <i class="iconoir-megaphone menu-icon"></i>
                                <span>Marketing</span>
                            </a>
                            <div class="collapse " id="sidebarMarketing">
                                <ul class="nav flex-column">
                                    <!-- Offers Submenu -->
                                    <li class="nav-item">
                                        <a class="nav-link" href="#sidebarOffers" data-bs-toggle="collapse" role="button"
                                            aria-expanded="false" aria-controls="sidebarOffers">Offers</a>
                                        <div class="collapse " id="sidebarOffers">
                                            <ul class="nav flex-column">
                                                <li class="nav-item">
                                                    <a class="nav-link" href="./coupons">Coupons</a>
                                                </li><!--end nav-item-->
                                                <li class="nav-item">
                                                    <a class="nav-link" href="./discounts">Discounts</a>
                                                </li><!--end nav-item-->
                                            </ul><!--end nav-->
                                        </div>
                                    </li><!--end nav-item-->
                                </ul><!--end nav-->
                            </div>
                        </li><!--end nav-item-->
                        <?php endif; ?>


                        <?php if ($show_content): ?>
                        <!-- Content -->
                        <li class="nav-item">
                            <a class="nav-link" href="#sidebarContent" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarContent">
                                <i class="iconoir-edit menu-icon"></i>
                                <span>Content</span>
                            </a>
                            <div class="collapse " id="sidebarContent">
                                <ul class="nav flex-column">
                                    <li class="nav-item">
                                        <a class="nav-link" href="./edit-website.php">Edit Website</a>
                                    </li><!--end nav-item-->


                                </ul><!--end nav-->
                            </div>
                        </li><!--end nav-item-->
                        <?php endif; ?>

                        <?php if ($show_utilities): ?>
                        <!-- Utilities -->
                        <li class="nav-item">
                            <a class="nav-link" href="#sidebarUtilities" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarUtilities">
                                <i class="iconoir-tools menu-icon"></i>
                                <span>Utilities</span>
                            </a>
                            <div class="collapse " id="sidebarUtilities">
                                <ul class="nav flex-column">

                                    <li class="nav-item">
                                        <a class="nav-link" href="./contact-queries">Contact Queries</a>
                                    </li><!--end nav-item-->
                                </ul><!--end nav-->
                            </div>
                        </li><!--end nav-item-->
                        <?php endif; ?>

                        <?php if ($show_usermgmt): ?>
                        <!-- User Management -->
                        <li class="nav-item">
                            <a class="nav-link" href="#sidebarUserManagement" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarUserManagement">
                                <i class="iconoir-community menu-icon"></i>
                                <span>User Management</span>
                            </a>
                            <div class="collapse " id="sidebarUserManagement">
                                <ul class="nav flex-column">
                                    <?php if ($can_users_list): ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="./users">User List</a>
                                    </li><!--end nav-item-->
                                    <?php endif; ?>
                                    <!-- <li class="nav-item">
                                        <a class="nav-link" href="./manage-profiles">Manage Profiles</a>
                                    </li>end nav-item -->
                                    <?php if ($can_user_roles): ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="./user-roles">User Roles (Admin / Staff)</a>
                                    </li><!--end nav-item-->
                                    <?php endif; ?>
                                </ul><!--end nav-->
                            </div>
                        </li><!--end nav-item-->
                        <?php endif; ?>


                        <?php if ($show_system_label): ?>
                        <li class="menu-label mt-2">
                            <small class="label-border">
                                <div class="border_left hidden-xs"></div>
                                <div class="border_right"></div>
                            </small>
                            <span>System & Tools</span>
                        </li>
                        <?php endif; ?>

                        <?php if ($show_settings): ?>
                        <!-- Settings -->
                        <li class="nav-item">
                            <a class="nav-link" href="#sidebarSettings" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarSettings">
                                <i class="iconoir-settings menu-icon"></i>
                                <span>Settings</span>
                            </a>
                            <div class="collapse " id="sidebarSettings">
                                <ul class="nav flex-column">
                                    <?php if ($can_general): ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="./general-settings">General Settings</a>
                                    </li><!--end nav-item-->
                                    <?php endif; ?>
                                    <?php if ($can_payment): ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="./payment-settings">Payment Settings</a>
                                    </li><!--end nav-item-->
                                    <?php endif; ?>
                                    <!-- <li class="nav-item">
                                        <a class="nav-link" href="./shipping-settings">Shipping Settings</a>
                                    </li>end nav-item -->
                                    <?php if ($can_email): ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="./email-settings">Email Settings</a>
                                    </li><!--end nav-item-->
                                    <?php endif; ?>
                                    <?php if ($can_activity): ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="./activity-logs">Activity Logs</a>
                                    </li><!--end nav-item-->
                                    <?php endif; ?>
                                </ul><!--end nav-->
                            </div><!--end startbarSettings-->
                        </li><!--end nav-item-->
                        <?php endif; ?>

                        <?php if ($show_reports): ?>
                        <!-- Analytics & Reports -->
                        <li class="nav-item">
                            <a class="nav-link" href="#sidebarReports" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarReports">
                                <i class="iconoir-reports menu-icon"></i>
                                <span>Analytics & Reports</span>
                            </a>
                            <div class="collapse " id="sidebarReports">
                                <ul class="nav flex-column">
                                    <?php if ($can_sales): ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="./sales-report">Sales Report</a>
                                    </li><!--end nav-item-->
                                    <?php endif; ?>
                                    <?php if ($can_cust_analytics): ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="./customer-analytics">Customer Analytics</a>
                                    </li><!--end nav-item-->
                                    <?php endif; ?>
                                    <?php if ($can_inventory): ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="./inventory-report">Inventory Report</a>
                                    </li><!--end nav-item-->
                                    <?php endif; ?>
                                    <?php if ($can_perf): ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="./performance-metrics">Performance Metrics</a>
                                    </li><!--end nav-item-->
                                    <?php endif; ?>
                                </ul><!--end nav-->
                            </div><!--end startbarReports-->
                        </li><!--end nav-item-->
                        <?php endif; ?>


                        <!-- Support -->
                        <!-- <li class="nav-item">
                            <a class="nav-link" href="./support">
                                <i class="iconoir-headset-help menu-icon"></i>
                                <span>Support</span>
                            </a>
                        </li>end nav-item -->

                    </ul><!--end navbar-nav--->

                </div>
            </div><!--end startbar-collapse-->
        </div><!--end startbar-menu-->
    </div><!--end startbar-->
    <div class="startbar-overlay d-print-none"></div>

<style>
/* Logo styling with brand colors and gradient */
.startbar .brand {
    padding: 12px 16px;
    overflow: hidden;
    width: 100%;
    box-sizing: border-box;
}

.startbar .brand .logo {
    display: flex;
    align-items: center;
    gap: 4px;
    width: 100%;
    overflow: hidden;
}

/* Common logo styles */
.startbar .brand .logo .logo-sm,
.startbar .brand .logo .logo-lg {
    margin: 0;
    padding: 4px 8px;
    border-radius: 6px;
    white-space: nowrap;
    font-weight: 800;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    /* Brand gradient colors */
    background: linear-gradient(135deg, #0e2187 0%, #97c51d 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    /* Fallback for unsupported browsers */
    color: #0e2187;
}

/* Base sizes for mobile */
.startbar .brand .logo .logo-sm {
    font-size: 14px;
    line-height: 1.1;
    max-width: 120px;
}
.startbar .brand .logo .logo-lg {
    font-size: 16px;
    line-height: 1.1;
    max-width: 160px;
}

/* Medium screens */
@media (min-width: 768px) {
    .startbar .brand .logo .logo-sm {
        font-size: 16px;
        max-width: 140px;
    }
    .startbar .brand .logo .logo-lg {
        font-size: 18px;
        max-width: 180px;
    }
}

/* Large screens */
@media (min-width: 1200px) {
    .startbar .brand .logo .logo-sm {
        font-size: 18px;
        max-width: 160px;
    }
    .startbar .brand .logo .logo-lg {
        font-size: 20px;
        max-width: 200px;
    }
}

/* Extra large screens */
@media (min-width: 1400px) {
    .startbar .brand .logo .logo-sm {
        font-size: 20px;
        max-width: 180px;
    }
    .startbar .brand .logo .logo-lg {
        font-size: 22px;
        max-width: 220px;
    }
}

/* Mobile adjustments */
@media (max-width: 575.98px) {
    .startbar .brand {
        padding: 8px 12px;
    }
    .startbar .brand .logo .logo-sm {
        font-size: 12px;
        max-width: 100px;
    }
    .startbar .brand .logo .logo-lg {
        font-size: 14px;
        max-width: 120px;
    }
}

/* Collapsed sidebar behavior */
body[data-sidebar-size="collapsed"] .startbar .brand {
    padding: 8px 12px;
    text-align: center;
}
body[data-sidebar-size="collapsed"] .startbar .brand .logo .logo-lg {
    display: none;
}
body[data-sidebar-size="collapsed"] .startbar .brand .logo .logo-sm {
    display: inline-block;
    font-size: 12px;
    max-width: 60px;
    padding: 2px 4px;
}

/* Dark mode adjustments */
[data-bs-theme="dark"] .startbar .brand .logo .logo-sm,
[data-bs-theme="dark"] .startbar .brand .logo .logo-lg {
    /* Brighter gradient for dark mode */
    background: linear-gradient(135deg, #4a69bd 0%, #a4de6c 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    color: #4a69bd;
}

/* Hover effect */
.startbar .brand .logo:hover .logo-sm,
.startbar .brand .logo:hover .logo-lg {
    transform: scale(1.05);
    transition: transform 0.2s ease;
}
</style>
