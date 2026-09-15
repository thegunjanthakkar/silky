<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get role name from database
$role_name = 'Administrator'; // Default fallback
$role_id = $_SESSION['admin_role'] ?? $_SESSION['user_role'] ?? null;
if ($role_id && is_numeric($role_id)) {
    require_once '../db_config.php';
    $role_id = (int) $role_id;
    $role_query = "SELECT role_name FROM admin_roles WHERE id = $role_id";
    $role_result = mysqli_query($conn, $role_query);
    if ($role_result && mysqli_num_rows($role_result) > 0) {
        $role_row = mysqli_fetch_assoc($role_result);
        $role_name = htmlspecialchars($role_row['role_name']);
    }
}

// ── NOTIFICATION DATA ─────────────────────────────────────
if (!isset($conn) || !$conn) { require_once '../db_config.php'; }

// Build notifications array (up to 5 most urgent)
$notifications = [];

// Out-of-stock
$n_oos = mysqli_query($conn, "SELECT COUNT(*) as c FROM products p LEFT JOIN stock s ON p.id = s.product_id WHERE p.status='active' AND (s.quantity IS NULL OR s.quantity=0)");
$oos_count = $n_oos ? (int)mysqli_fetch_assoc($n_oos)['c'] : 0;
if ($oos_count > 0) {
    $notifications[] = ['type'=>'danger','icon'=>'la-times-circle','text'=>"$oos_count product(s) are out of stock",'link'=>'notifications.php#out-of-stock','time'=>'Now'];
}

// Low stock
$n_low = mysqli_query($conn, "SELECT COUNT(*) as c FROM products p JOIN stock s ON p.id = s.product_id WHERE p.status='active' AND s.quantity > 0 AND s.quantity < 5");
$low_count = $n_low ? (int)mysqli_fetch_assoc($n_low)['c'] : 0;
if ($low_count > 0) {
    $notifications[] = ['type'=>'warning','icon'=>'la-exclamation-triangle','text'=>"$low_count product(s) running low on stock",'link'=>'notifications.php#low-stock','time'=>'Now'];
}

// Stale payments >24h
$n_stale = mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE payment_status='pending' AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stale_count = $n_stale ? (int)mysqli_fetch_assoc($n_stale)['c'] : 0;
if ($stale_count > 0) {
    $notifications[] = ['type'=>'danger','icon'=>'la-clock','text'=>"$stale_count order(s) unpaid for over 24 hours",'link'=>'notifications.php#stale-payments','time'=>'Now'];
}

// Cancellations last 7d
$n_cancel = mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE order_status='cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$cancel_count = $n_cancel ? (int)mysqli_fetch_assoc($n_cancel)['c'] : 0;
if ($cancel_count > 0) {
    $notifications[] = ['type'=>'secondary','icon'=>'la-ban','text'=>"$cancel_count order(s) cancelled in the last 7 days",'link'=>'notifications.php#cancellations','time'=>'This week'];
}

// New orders today
$n_today = mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE DATE(created_at) = CURDATE()");
$today_orders_count = $n_today ? (int)mysqli_fetch_assoc($n_today)['c'] : 0;
if ($today_orders_count > 0) {
    $notifications[] = ['type'=>'success','icon'=>'la-shopping-bag','text'=>"$today_orders_count new order(s) received today",'link'=>'orders.php','time'=>'Today'];
}

// New customers last 7d
$n_cust = mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$new_cust_count = $n_cust ? (int)mysqli_fetch_assoc($n_cust)['c'] : 0;
if ($new_cust_count > 0) {
    $notifications[] = ['type'=>'info','icon'=>'la-user-plus','text'=>"$new_cust_count new customer(s) registered this week",'link'=>'customers.php','time'=>'This week'];
}

// Slow movers (have stock, no sale 30d)
$n_slow = mysqli_query($conn, "SELECT COUNT(*) as c FROM products p JOIN stock s ON p.id = s.product_id WHERE p.status='active' AND s.quantity > 0 AND p.id NOT IN (SELECT DISTINCT oi.product_id FROM order_items oi JOIN orders o ON oi.order_id=o.id WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND oi.product_id IS NOT NULL)");
$slow_count = $n_slow ? (int)mysqli_fetch_assoc($n_slow)['c'] : 0;
if ($slow_count > 0) {
    $notifications[] = ['type'=>'secondary','icon'=>'la-hourglass-half','text'=>"$slow_count product(s) haven't sold in 30+ days",'link'=>'notifications.php#slow-movers','time'=>'Insight'];
}

$total_notif = count($notifications);

// --- Notification Badge Logic ---
$is_notif_page = (basename($_SERVER['PHP_SELF']) == 'notifications.php');
if ($is_notif_page) {
    $_SESSION['seen_notifications_count'] = $total_notif;
}

$show_notif_badge = false;
$unread_count = 0;
if ($total_notif > 0) {
    if (!isset($_SESSION['seen_notifications_count'])) {
        $show_notif_badge = true;
        $unread_count = $total_notif;
    } elseif ($total_notif > $_SESSION['seen_notifications_count']) {
        $show_notif_badge = true;
        $unread_count = $total_notif - $_SESSION['seen_notifications_count'];
    } elseif ($total_notif < $_SESSION['seen_notifications_count']) {
        $_SESSION['seen_notifications_count'] = $total_notif;
    }
}

// Show at most 6 in dropdown
$dropdown_notifs = array_slice($notifications, 0, 6);

// Colour map for badge / icons
$type_colors = [
    'danger'    => ['bg'=>'bg-danger-subtle',    'text'=>'text-danger',    'badge'=>'bg-danger'],
    'warning'   => ['bg'=>'bg-warning-subtle',   'text'=>'text-warning',   'badge'=>'bg-warning text-dark'],
    'success'   => ['bg'=>'bg-success-subtle',   'text'=>'text-success',   'badge'=>'bg-success'],
    'info'      => ['bg'=>'bg-info-subtle',      'text'=>'text-info',      'badge'=>'bg-info text-dark'],
    'secondary' => ['bg'=>'bg-secondary-subtle', 'text'=>'text-secondary', 'badge'=>'bg-secondary'],
];
?>


<div class="topbar d-print-none">
        <div class="container-fluid">
            <nav class="topbar-custom d-flex justify-content-between" id="topbar-custom">    
        

                <ul class="topbar-item list-unstyled d-inline-flex align-items-center mb-0">                        
                    <li>
                        <button class="nav-link mobile-menu-btn nav-icon" id="togglemenu">
                            <i class="iconoir-menu"></i>
                        </button>
                    </li> 
                    <li class="mx-2 welcome-text">
                        <!-- <a class=" btn btn-sm btn-soft-primary" href="#" role="button"><i class="fas fa-plus me-2"></i>New Task</a> -->
                        <?php
                        // Dynamic greeting based on current time
                        date_default_timezone_set('Asia/Kolkata');
                        $hour = (int)date('G');
                        if ($hour >= 4 && $hour < 12) {
                            $greeting = 'Good Morning';
                        } elseif ($hour >= 12 && $hour < 17) {
                            $greeting = 'Good Afternoon';
                        } elseif ($hour >= 17 && $hour < 22) {
                            $greeting = 'Good Evening';
                        } else {
                            $greeting = 'Good Night';
                        }
                        $name = (isset($_SESSION['first_name']) && trim($_SESSION['first_name']) !== '') ? htmlspecialchars($_SESSION['first_name']) : 'User';
                        ?>
                        <h5 class="mb-0 fw-semibold text-truncate"><?php echo $greeting . ', ' . $name . '!'; ?></h5>
                        <!-- <h6 class="mb-0 fw-normal text-muted text-truncate fs-14">Here's your overview this week.</h6> -->
                    </li>                   
                </ul>
                <ul class="topbar-item list-unstyled d-inline-flex align-items-center mb-0">
                    <!-- <li class="hide-phone app-search">
                        <form id="topSearchForm" role="search" action="#" method="get" autocomplete="off">
                            <input type="search" name="search" class="form-control top-search mb-0" placeholder="Search here...">
                            <button type="submit"><i class="iconoir-search"></i></button>
                        </form>
                    </li>      -->
                  
        
                    <!-- Notification Bell -->
                    <li class="dropdown topbar-item">
                        <a class="nav-link nav-icon position-relative" data-bs-toggle="dropdown" href="#" role="button"
                            aria-haspopup="true" aria-expanded="false" data-bs-offset="0,19" id="notifBell">
                            <i class="las la-bell fs-22"></i>
                            <?php if ($show_notif_badge && $unread_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                                  style="font-size:9px;padding:3px 5px;transform:translate(-60%,-20%)!important;">
                                <?php echo $unread_count > 9 ? '9+' : $unread_count; ?>
                                <span class="visually-hidden">notifications</span>
                            </span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end py-0" style="width:340px;max-width:95vw;">
                            <!-- Header -->
                            <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom bg-secondary-subtle">
                                <h6 class="mb-0 fw-semibold">Notifications</h6>
                                <?php if ($show_notif_badge && $unread_count > 0): ?>
                                <span class="badge bg-danger rounded-pill"><?php echo $unread_count; ?></span>
                                <?php else: ?>
                                <span class="badge bg-success rounded-pill">All clear</span>
                                <?php endif; ?>
                            </div>
                            <!-- Items -->
                            <?php if (!$show_notif_badge || $unread_count == 0): ?>
                            <div class="text-center py-4 px-3 text-muted">
                                <i class="las la-check-circle fs-2 text-success d-block mb-1"></i>
                                <small>No new alerts right now. Everything looks good!</small>
                            </div>
                            <?php else: ?>
                            <div style="max-height:320px;overflow-y:auto;">
                                <?php foreach ($dropdown_notifs as $n):
                                    $c = $type_colors[$n['type']] ?? $type_colors['secondary'];
                                ?>
                                <a href="<?php echo htmlspecialchars($n['link']); ?>" class="dropdown-item d-flex align-items-start gap-3 py-2 px-3 border-bottom">
                                    <div class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center <?php echo $c['bg']; ?>"
                                         style="width:34px;height:34px;font-size:16px;">
                                        <i class="las <?php echo $n['icon']; ?> <?php echo $c['text']; ?>"></i>
                                    </div>
                                    <div class="flex-grow-1 overflow-hidden">
                                        <p class="mb-0 fs-13 text-wrap"><?php echo htmlspecialchars($n['text']); ?></p>
                                        <small class="text-muted"><?php echo $n['time']; ?></small>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <!-- Footer -->
                            <div class="text-center py-2 border-top">
                                <a href="notifications.php" class="fs-13 text-primary fw-medium">View All Notifications →</a>
                            </div>
                        </div>
                    </li>

                    <li class="topbar-item">
                        <a class="nav-link nav-icon" href="javascript:void(0);" id="light-dark-mode">
                            <i class="iconoir-half-moon dark-mode"></i>
                            <i class="iconoir-sun-light light-mode"></i>
                        </a>                    
                    </li>
    
                    
    
                    <li class="dropdown topbar-item">
                        <a class="nav-link dropdown-toggle arrow-none nav-icon" data-bs-toggle="dropdown" href="#" role="button"
                            aria-haspopup="false" aria-expanded="false" data-bs-offset="0,19">
                            <i class="iconoir-user thumb-md rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-size: 20px;"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end py-0">
                            <div class="d-flex align-items-center dropdown-item py-2 bg-secondary-subtle">
                                <div class="flex-shrink-0">
                                    <i class="iconoir-user thumb-md rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-size: 20px;"></i>
                                </div>
                                <div class="flex-grow-1 ms-2 text-truncate align-self-center">
                                    <h6 class="my-0 fw-medium text-dark fs-13"><?php 
                                        $firstName = isset($_SESSION['admin_first_name']) ? htmlspecialchars($_SESSION['admin_first_name']) : (isset($_SESSION['first_name']) ? htmlspecialchars($_SESSION['first_name']) : 'Admin');
                                        $lastName = isset($_SESSION['last_name']) ? htmlspecialchars($_SESSION['last_name']) : '';
                                        $fullName = trim($firstName . ' ' . $lastName);
                                        echo $fullName ?: $firstName;
                                    ?></h6>
                                    <small class="text-muted mb-0"><?php echo ucfirst($role_name); ?></small>
                                </div><!--end media-body-->
                            </div>
                            <div class="dropdown-divider mt-0"></div>
                            <small class="text-muted px-2 pb-1 d-block">Account</small>
                            <a class="dropdown-item" href="#"><i class="las la-user fs-18 me-1 align-text-bottom"></i> Profile</a>
                            <a class="dropdown-item" href="#"><i class="las la-wallet fs-18 me-1 align-text-bottom"></i> Earning</a>
                            <small class="text-muted px-2 py-1 d-block">Settings</small>                        
                            <a class="dropdown-item" href="#"><i class="las la-cog fs-18 me-1 align-text-bottom"></i>Account Settings</a>
                            <a class="dropdown-item" href="#"><i class="las la-lock fs-18 me-1 align-text-bottom"></i> Security</a>
                            <a class="dropdown-item" href="#"><i class="las la-question-circle fs-18 me-1 align-text-bottom"></i> Help Center</a>                       
                            <div class="dropdown-divider mb-0"></div>
                            <a class="dropdown-item text-danger" href="#" onclick="confirmLogout(); return false;"><i class="las la-power-off fs-18 me-1 align-text-bottom"></i> Logout</a>
                        </div>
                    </li>
                </ul><!--end topbar-nav-->
            </nav>
            <!-- end navbar-->
        </div>
    </div>

<!-- Sweet Alert CSS -->
<link href="assets/libs/sweetalert2/sweetalert2.min.css" rel="stylesheet" type="text/css">

<!-- Sweet Alert JS -->
<script src="assets/libs/sweetalert2/sweetalert2.min.js"></script>
<script>
function confirmLogout() {
    Swal.fire({
        title: 'Are you sure?',
        text: "You will be logged out of your account!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, logout!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php';
        }
    });
}
</script>