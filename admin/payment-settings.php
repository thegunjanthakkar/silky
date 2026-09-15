<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'includes/permission-manager.php';
checkPageAccess();
require_once '../db_config.php';

// Create payment_settings table if not exists
$create_table = "CREATE TABLE IF NOT EXISTS payment_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_method VARCHAR(50) NOT NULL UNIQUE,
    is_enabled TINYINT(1) DEFAULT 0,
    api_key VARCHAR(255) DEFAULT NULL,
    api_secret VARCHAR(255) DEFAULT NULL,
    merchant_id VARCHAR(255) DEFAULT NULL,
    test_mode TINYINT(1) DEFAULT 1,
    additional_config TEXT DEFAULT NULL,
    display_name VARCHAR(100) DEFAULT NULL,
    display_order INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT DEFAULT NULL
)";
mysqli_query($conn, $create_table);

// Initialize default payment methods if table is empty
$check_sql = "SELECT COUNT(*) as count FROM payment_settings";
$check_result = mysqli_query($conn, $check_sql);
$row = mysqli_fetch_assoc($check_result);

if ($row['count'] == 0) {
    $default_methods = [
        ['cod', 'Cash on Delivery', 1, 1],
        ['stripe', 'Stripe', 2, 0],
        ['paypal', 'PayPal', 3, 0],
        ['razorpay', 'Razorpay', 4, 0],
        ['bank_transfer', 'Bank Transfer', 5, 0]
    ];
    
    foreach ($default_methods as $method) {
        $insert_sql = "INSERT INTO payment_settings (payment_method, display_name, display_order, is_enabled) 
                       VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt, "ssii", $method[0], $method[1], $method[2], $method[3]);
        mysqli_stmt_execute($stmt);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_settings') {
        $method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
        $test_mode = isset($_POST['test_mode']) ? 1 : 0;
        $api_key = mysqli_real_escape_string($conn, $_POST['api_key'] ?? '');
        $api_secret = mysqli_real_escape_string($conn, $_POST['api_secret'] ?? '');
        $merchant_id = mysqli_real_escape_string($conn, $_POST['merchant_id'] ?? '');
        $display_name = mysqli_real_escape_string($conn, $_POST['display_name'] ?? '');
        
        // Handle additional config (JSON format)
        $additional_config = [];
        if ($method === 'bank_transfer') {
            $additional_config = [
                'bank_name' => $_POST['bank_name'] ?? '',
                'account_name' => $_POST['account_name'] ?? '',
                'account_number' => $_POST['account_number'] ?? '',
                'ifsc_code' => $_POST['ifsc_code'] ?? '',
                'swift_code' => $_POST['swift_code'] ?? ''
            ];
        } elseif ($method === 'cod') {
            $additional_config = [
                'min_order_amount' => $_POST['min_order_amount'] ?? 0,
                'max_order_amount' => $_POST['max_order_amount'] ?? 0,
                'extra_charges' => $_POST['extra_charges'] ?? 0
            ];
        }
        $additional_config_json = json_encode($additional_config);
        
        $update_sql = "UPDATE payment_settings SET 
                       is_enabled = ?, 
                       test_mode = ?, 
                       api_key = ?, 
                       api_secret = ?, 
                       merchant_id = ?,
                       display_name = ?,
                       additional_config = ?,
                       updated_by = ?
                       WHERE payment_method = ?";
        
        $stmt = mysqli_prepare($conn, $update_sql);
        $user_id = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? null;
        mysqli_stmt_bind_param($stmt, "iisssssss", $is_enabled, $test_mode, $api_key, $api_secret, $merchant_id, $display_name, $additional_config_json, $user_id, $method);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Payment settings updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating payment settings: " . mysqli_error($conn);
        }
        
        header('Location: payment-settings.php');
        exit;
    }
}

// Fetch all payment methods
$sql = "SELECT * FROM payment_settings ORDER BY display_order ASC";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light" id="html-root">

<head>
    <meta charset="utf-8" />
    <title>Payment Settings | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta content="Payment Gateway Configuration" name="description" />
    <meta content="" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <!-- App favicon -->
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">

    <!-- App css -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />

    <style>
        .payment-card {
            transition: all 0.3s ease;
            border: 2px solid #e3e6f0;
        }
        .payment-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .payment-card.disabled {
            opacity: 0.6;
            background-color: #f8f9fa;
        }
        .payment-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .config-section {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        .config-section.show {
            display: block;
        }
    </style>

    <!-- Dark Mode State Check Script -->
    <script>
        (function() {
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
                            <h4 class="page-title">Payment Settings</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="index.php">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="#">Settings</a></li>
                                    <li class="breadcrumb-item active">Payment Settings</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Payment Methods Grid -->
                <div class="row">
                    <?php 
                    $payment_icons = [
                        'cod' => 'fas fa-money-bill-wave text-success',
                        'stripe' => 'fab fa-cc-stripe text-primary',
                        'paypal' => 'fab fa-paypal text-info',
                        'razorpay' => 'fas fa-credit-card text-warning',
                        'bank_transfer' => 'fas fa-university text-secondary'
                    ];
                    
                    while ($payment = mysqli_fetch_assoc($result)): 
                        $config = json_decode($payment['additional_config'], true) ?? [];
                    ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="card payment-card <?php echo !$payment['is_enabled'] ? 'disabled' : ''; ?>">
                            <div class="card-body position-relative">
                                <span class="status-badge badge bg-<?php echo $payment['is_enabled'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $payment['is_enabled'] ? 'Active' : 'Inactive'; ?>
                                </span>
                                
                                <div class="text-center">
                                    <div class="payment-icon">
                                        <i class="<?php echo $payment_icons[$payment['payment_method']] ?? 'fas fa-credit-card'; ?>"></i>
                                    </div>
                                    <h4 class="mt-0"><?php echo htmlspecialchars($payment['display_name']); ?></h4>
                                    <p class="text-muted mb-3">
                                        <?php 
                                        switch($payment['payment_method']) {
                                            case 'cod':
                                                echo 'Accept cash payments on delivery';
                                                break;
                                            case 'stripe':
                                                echo 'Credit/Debit cards via Stripe';
                                                break;
                                            case 'paypal':
                                                echo 'PayPal checkout integration';
                                                break;
                                            case 'razorpay':
                                                echo 'Indian payment gateway';
                                                break;
                                            case 'bank_transfer':
                                                echo 'Direct bank transfer';
                                                break;
                                            default:
                                                echo 'Payment method';
                                        }
                                        ?>
                                    </p>
                                    
                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                            onclick="toggleConfig('<?php echo $payment['payment_method']; ?>')">
                                        <i class="fas fa-cog me-1"></i>Configure
                                    </button>
                                </div>

                                <!-- Configuration Form -->
                                <div id="config-<?php echo $payment['payment_method']; ?>" class="config-section">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="update_settings">
                                        <input type="hidden" name="payment_method" value="<?php echo $payment['payment_method']; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Display Name</label>
                                            <input type="text" class="form-control" name="display_name" 
                                                   value="<?php echo htmlspecialchars($payment['display_name']); ?>" required>
                                        </div>

                                        <div class="mb-3 form-check form-switch">
                                            <input type="checkbox" class="form-check-input" id="enabled-<?php echo $payment['payment_method']; ?>" 
                                                   name="is_enabled" <?php echo $payment['is_enabled'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enabled-<?php echo $payment['payment_method']; ?>">
                                                Enable this payment method
                                            </label>
                                        </div>

                                        <?php if ($payment['payment_method'] !== 'cod' && $payment['payment_method'] !== 'bank_transfer'): ?>
                                        <div class="mb-3 form-check form-switch">
                                            <input type="checkbox" class="form-check-input" id="test-<?php echo $payment['payment_method']; ?>" 
                                                   name="test_mode" <?php echo $payment['test_mode'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="test-<?php echo $payment['payment_method']; ?>">
                                                Test Mode (Sandbox)
                                            </label>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">API Key / Public Key</label>
                                            <input type="text" class="form-control" name="api_key" 
                                                   value="<?php echo htmlspecialchars($payment['api_key'] ?? ''); ?>"
                                                   placeholder="Enter API key">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">API Secret / Private Key</label>
                                            <input type="password" class="form-control" name="api_secret" 
                                                   value="<?php echo htmlspecialchars($payment['api_secret'] ?? ''); ?>"
                                                   placeholder="Enter API secret">
                                            <small class="text-muted">Leave blank to keep existing value</small>
                                        </div>

                                        <?php if ($payment['payment_method'] === 'razorpay'): ?>
                                        <div class="mb-3">
                                            <label class="form-label">Merchant ID</label>
                                            <input type="text" class="form-control" name="merchant_id" 
                                                   value="<?php echo htmlspecialchars($payment['merchant_id'] ?? ''); ?>"
                                                   placeholder="Enter merchant ID">
                                        </div>
                                        <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($payment['payment_method'] === 'cod'): ?>
                                        <div class="mb-3">
                                            <label class="form-label">Minimum Order Amount (₹)</label>
                                            <input type="number" class="form-control" name="min_order_amount" 
                                                   value="<?php echo $config['min_order_amount'] ?? 0; ?>" step="0.01">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Maximum Order Amount (₹)</label>
                                            <input type="number" class="form-control" name="max_order_amount" 
                                                   value="<?php echo $config['max_order_amount'] ?? 0; ?>" step="0.01">
                                            <small class="text-muted">0 for no limit</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Extra COD Charges (₹)</label>
                                            <input type="number" class="form-control" name="extra_charges" 
                                                   value="<?php echo $config['extra_charges'] ?? 0; ?>" step="0.01">
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($payment['payment_method'] === 'bank_transfer'): ?>
                                        <div class="mb-3">
                                            <label class="form-label">Bank Name</label>
                                            <input type="text" class="form-control" name="bank_name" 
                                                   value="<?php echo htmlspecialchars($config['bank_name'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Account Name</label>
                                            <input type="text" class="form-control" name="account_name" 
                                                   value="<?php echo htmlspecialchars($config['account_name'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Account Number</label>
                                            <input type="text" class="form-control" name="account_number" 
                                                   value="<?php echo htmlspecialchars($config['account_number'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">IFSC Code</label>
                                            <input type="text" class="form-control" name="ifsc_code" 
                                                   value="<?php echo htmlspecialchars($config['ifsc_code'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">SWIFT Code (Optional)</label>
                                            <input type="text" class="form-control" name="swift_code" 
                                                   value="<?php echo htmlspecialchars($config['swift_code'] ?? ''); ?>">
                                        </div>
                                        <?php endif; ?>

                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i>Save Settings
                                            </button>
                                            <button type="button" class="btn btn-secondary" 
                                                    onclick="toggleConfig('<?php echo $payment['payment_method']; ?>')">
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

                

            </div><!-- container-fluid -->

            <?php include 'footer.php'; ?>
        </div>
        <!-- end page content -->
    </div>
    <!-- end page-wrapper -->

    <!-- Javascript  -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>

    <script>
        function toggleConfig(method) {
            const configDiv = document.getElementById('config-' + method);
            const allConfigs = document.querySelectorAll('.config-section');
            
            // Close all other config sections
            allConfigs.forEach(div => {
                if (div.id !== 'config-' + method) {
                    div.classList.remove('show');
                }
            });
            
            // Toggle current config section
            configDiv.classList.toggle('show');
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>

</html>
