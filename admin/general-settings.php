<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'includes/permission-manager.php';
checkPageAccess();
require_once '../db_config.php';

// Create table if not exists
$create_table_sql = "CREATE TABLE IF NOT EXISTS `general_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_table_sql);

// Initialize variables
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    // Define all settings to save
    $settings_to_save = [
        'shipping_cost_gujarat' => mysqli_real_escape_string($conn, trim($_POST['shipping_cost_gujarat'] ?? '')),
        'shipping_cost_other_states' => mysqli_real_escape_string($conn, trim($_POST['shipping_cost_other_states'] ?? '')),
        'tax_percentage' => mysqli_real_escape_string($conn, trim($_POST['tax_percentage'] ?? '')),
        'business_email' => mysqli_real_escape_string($conn, trim($_POST['business_email'] ?? '')),
        'business_phone' => mysqli_real_escape_string($conn, trim($_POST['business_phone'] ?? '')),
        'business_address' => mysqli_real_escape_string($conn, trim($_POST['business_address'] ?? '')),
        'business_timings' => mysqli_real_escape_string($conn, trim($_POST['business_timings'] ?? '')),
        'website_tagline' => mysqli_real_escape_string($conn, trim($_POST['website_tagline'] ?? '')),
        'social_facebook' => mysqli_real_escape_string($conn, trim($_POST['social_facebook'] ?? '')),
        'social_instagram' => mysqli_real_escape_string($conn, trim($_POST['social_instagram'] ?? '')),
        'social_twitter' => mysqli_real_escape_string($conn, trim($_POST['social_twitter'] ?? '')),
        'social_pinterest' => mysqli_real_escape_string($conn, trim($_POST['social_pinterest'] ?? '')),
        'social_youtube' => mysqli_real_escape_string($conn, trim($_POST['social_youtube'] ?? '')),
        'social_whatsapp' => mysqli_real_escape_string($conn, trim($_POST['social_whatsapp'] ?? '')),
        'terms_and_conditions' => mysqli_real_escape_string($conn, trim($_POST['terms_and_conditions'] ?? '')),
        'privacy_policy' => mysqli_real_escape_string($conn, trim($_POST['privacy_policy'] ?? ''))
    ];
    
    // Validate numeric fields
    $has_error = false;
    if (!empty($settings_to_save['shipping_cost_gujarat']) && !is_numeric(str_replace(',', '', $settings_to_save['shipping_cost_gujarat']))) {
        $error_message = 'Shipping cost for Gujarat must be a valid number';
        $has_error = true;
    }
    if (!empty($settings_to_save['shipping_cost_other_states']) && !is_numeric(str_replace(',', '', $settings_to_save['shipping_cost_other_states']))) {
        $error_message = 'Shipping cost for other states must be a valid number';
        $has_error = true;
    }
    if (!empty($settings_to_save['tax_percentage']) && !is_numeric($settings_to_save['tax_percentage'])) {
        $error_message = 'Tax percentage must be a valid number';
        $has_error = true;
    }
    
    // Save settings if no errors
    if (!$has_error) {
        $saved_count = 0;
        
        foreach ($settings_to_save as $key => $value) {
            // Check if setting exists
            $check_sql = "SELECT id FROM general_settings WHERE setting_key = '$key'";
            $check_result = mysqli_query($conn, $check_sql);
            
            if (mysqli_num_rows($check_result) > 0) {
                // Update existing setting
                $update_sql = "UPDATE general_settings SET setting_value = '$value', updated_at = NOW() WHERE setting_key = '$key'";
                if (mysqli_query($conn, $update_sql)) {
                    $saved_count++;
                }
            } else {
                // Insert new setting
                $insert_sql = "INSERT INTO general_settings (setting_key, setting_value) VALUES ('$key', '$value')";
                if (mysqli_query($conn, $insert_sql)) {
                    $saved_count++;
                }
            }
        }
        
        // Save directly to tos.php and privacy.php files
        if (isset($_POST['terms_and_conditions'])) {
            if (saveLegalPolicyToFile('tos.php', 'Terms & Conditions', $_POST['terms_and_conditions'])) {
                $saved_count++;
            }
        }
        if (isset($_POST['privacy_policy'])) {
            if (saveLegalPolicyToFile('privacy.php', 'Privacy Policy', $_POST['privacy_policy'])) {
                $saved_count++;
            }
        }
        
        if ($saved_count > 0) {
            $success_message = 'Settings saved successfully! tos.php and privacy.php have been updated.';
        } else {
            $error_message = 'No settings were updated';
        }
    }
}

// Fetch existing settings
$settings = [];
$fetch_sql = "SELECT setting_key, setting_value FROM general_settings";
$fetch_result = mysqli_query($conn, $fetch_sql);
if ($fetch_result) {
    while ($row = mysqli_fetch_assoc($fetch_result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Helper function to get setting value
function getSetting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? $settings[$key] : $default;
}

// Helper functions to read and write directly to tos.php and privacy.php
function getLegalPolicyFromFile($filename, $defaultSettingKey = '') {
    // If setting exists in database and is non-empty, prioritize it
    $dbVal = getSetting($defaultSettingKey, '');
    if (!empty(trim($dbVal))) {
        return trim($dbVal);
    }

    $filePath = dirname(__DIR__) . '/' . $filename;
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        if (preg_match('/<!--\s*LEGAL_CONTENT_START\s*-->(.*?)<!--\s*LEGAL_CONTENT_END\s*-->/s', $content, $matches)) {
            $inner = trim($matches[1]);
            // Strip outer <div class="legal-content" id="legalContent"> if present
            $inner = preg_replace('/^<div[^>]*id=["\']legalContent["\'][^>]*>\s*/i', '', $inner);
            $inner = preg_replace('/\s*<\/div>\s*$/i', '', $inner);
            
            // If it contains the PHP conditional fallback from tos.php or privacy.php, extract the fallback HTML
            if (preg_match('/<\?php else: \?>(.*?)<\?php endif; \?>/s', $inner, $defaultMatches)) {
                $inner = trim($defaultMatches[1]);
            }
            return trim($inner);
        }
        return trim($content);
    }
    return $dbVal;
}

function saveLegalPolicyToFile($filename, $title, $rawContent) {
    $filePath = dirname(__DIR__) . '/' . $filename;
    $rawContent = trim($rawContent);
    
    // If text was submitted without any HTML tags, wrap paragraphs in <p> tags
    $htmlContent = $rawContent;
    if (strip_tags($rawContent) === $rawContent && !empty($rawContent)) {
        $paragraphs = array_filter(array_map('trim', preg_split('/\r\n\r\n|\n\n/', $rawContent)));
        $formatted = '';
        foreach ($paragraphs as $p) {
            $formatted .= "<p>" . nl2br(htmlspecialchars($p)) . "</p>\n";
        }
        $htmlContent = $formatted;
    }
    
    if (file_exists($filePath)) {
        $existing = file_get_contents($filePath);
        if (strpos($existing, '<!-- LEGAL_CONTENT_START -->') !== false && strpos($existing, '<!-- LEGAL_CONTENT_END -->') !== false) {
            $pattern = '/<!--\s*LEGAL_CONTENT_START\s*-->.*?<!--\s*LEGAL_CONTENT_END\s*-->/s';
            $updated = preg_replace_callback($pattern, function() use ($htmlContent) {
                return "<!-- LEGAL_CONTENT_START -->\n            <div class=\"legal-content\" id=\"legalContent\">\n                " . $htmlContent . "\n            </div>\n            <!-- LEGAL_CONTENT_END -->";
            }, $existing);
            
            // Also update Last Updated date
            $updated = preg_replace('/<span id="lastUpdated">.*?<\/span>/', '<span id="lastUpdated">' . date('F d, Y') . '</span>', $updated);
            
            return @file_put_contents($filePath, $updated) !== false;
        }
    }
    return false;
}

/*
 * ========================================
 * HOW TO USE SETTINGS IN OTHER FILES
 * ========================================
 * 
 * STEP 1: Include this logic in any PHP file where you need settings:
 * 
 *   require_once 'db_config.php';
 *   $settings_query = "SELECT setting_key, setting_value FROM general_settings";
 *   $settings_result = mysqli_query($conn, $settings_query);
 *   $settings = [];
 *   while ($row = mysqli_fetch_assoc($settings_result)) {
 *       $settings[$row['setting_key']] = $row['setting_value'];
 *   }
 * 
 * STEP 2: Access settings using the array:
 * 
 *   $shipping = $settings['shipping_cost'] ?? 0;
 *   $tax = $settings['tax_percentage'] ?? 0;
 *   $email = $settings['business_email'] ?? '';
 * 
 * STEP 3: Use in calculations:
 * 
 *   // Checkout calculation
 *   $subtotal = 1500;
 *   // Shipping logic now dynamically calculated based on state and total weight in kg
 *   // This is handled via AJAX to calculate_shipping.php
 *   $tax_rate = floatval($settings['tax_percentage'] ?? 0);
 *   $tax_amount = ($subtotal * $tax_rate) / 100;
 *   $total = $subtotal + $shipping_amount + $tax_amount;
 * 
 * STEP 4: Use in display (invoice, footer, etc):
 * 
 *   echo $settings['business_email'];
 *   echo $settings['business_phone'];
 *   echo nl2br($settings['business_address']);
 *   echo $settings['website_tagline'];
 * 
 * ========================================
 */

?>








<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light" id="html-root">

<head>
    <meta charset="utf-8" />
    <title>General Settings | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta content="Configure shipping, tax, business information and website settings" name="description" />
    <meta content="" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <!-- App favicon -->
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- App css -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <style>
        .card { margin-bottom: 20px; }
        
        /* Social links styling */
        .social-link-item .input-group-text {
            min-width: 45px;
            justify-content: center;
        }
        .social-link-item .input-group-text i {
            font-size: 1.2rem;
        }
        .social-link-item .btn-outline-danger:hover {
            background-color: #dc3545;
            color: white;
        }
        #social-links-container .text-muted {
            padding: 20px;
            text-align: center;
        }

        /* Policy WYSIWYG Visual Editor & Formatting Toolbar Styles */
        .editor-toolbar-wrap {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px 12px;
            position: sticky;
            top: 70px;
            z-index: 10;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
        }
        .editor-toolbar-wrap .btn {
            font-size: 0.82rem;
            padding: 5px 11px;
            border-radius: 6px;
            font-weight: 500;
            background: #ffffff;
            border: 1px solid #d1d5db;
            color: #334155;
            transition: all 0.15s ease;
            user-select: none;
        }
        .editor-toolbar-wrap .btn:hover {
            background-color: #eef2ff !important;
            border-color: #0e2187 !important;
            color: #0e2187 !important;
        }
        .editor-toolbar-wrap .btn:active {
            background-color: #e0e7ff !important;
            transform: scale(0.97);
        }
        [data-bs-theme="dark"] .editor-toolbar-wrap {
            background-color: #1e293b;
            border-color: #334155;
        }
        [data-bs-theme="dark"] .editor-toolbar-wrap .btn {
            background: #0f172a;
            border-color: #334155;
            color: #e2e8f0;
        }
        [data-bs-theme="dark"] .editor-toolbar-wrap .btn:hover {
            background-color: #1e3a8a !important;
            border-color: #60a5fa !important;
            color: #ffffff !important;
        }

        .rich-visual-editor {
            min-height: 480px;
            max-height: 720px;
            overflow-y: auto;
            padding: 26px 30px;
            border: 1px solid #ced4da;
            border-radius: 10px;
            background-color: #ffffff;
            color: #1e293b;
            font-family: 'Poppins', sans-serif;
            font-size: 0.97rem;
            line-height: 1.8;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .rich-visual-editor:focus {
            border-color: #0e2187;
            box-shadow: 0 0 0 3px rgba(14, 33, 135, 0.12);
        }
        .rich-visual-editor:empty:before {
            content: attr(data-placeholder);
            color: #94a3b8;
            pointer-events: none;
            display: block;
        }
        [data-bs-theme="dark"] .rich-visual-editor {
            background-color: #1a2234;
            border-color: #2e3a54;
            color: #e2e8f0;
        }
        .rich-visual-editor h2 {
            font-family: 'Montserrat', sans-serif;
            color: #0e2187;
            font-weight: 700;
            font-size: 1.45rem;
            border-left: 4px solid #97c51d;
            padding-left: 12px;
            margin-top: 28px;
            margin-bottom: 14px;
            line-height: 1.35;
        }
        [data-bs-theme="dark"] .rich-visual-editor h2 {
            color: #93c5fd;
            border-left-color: #97c51d;
        }
        .rich-visual-editor h3 {
            font-family: 'Montserrat', sans-serif;
            color: #0e2187;
            font-weight: 600;
            font-size: 1.22rem;
            margin-top: 22px;
            margin-bottom: 10px;
            line-height: 1.35;
        }
        [data-bs-theme="dark"] .rich-visual-editor h3 {
            color: #a5b4fc;
        }
        .rich-visual-editor p {
            color: #334155;
            font-size: 0.97rem;
            margin-bottom: 16px;
            line-height: 1.8;
        }
        [data-bs-theme="dark"] .rich-visual-editor p {
            color: #cbd5e1;
        }
        .rich-visual-editor strong, .rich-visual-editor b {
            font-weight: 700;
            color: #0f172a;
        }
        [data-bs-theme="dark"] .rich-visual-editor strong, [data-bs-theme="dark"] .rich-visual-editor b {
            color: #ffffff;
        }
        .rich-visual-editor em, .rich-visual-editor i {
            font-style: italic;
        }
        .rich-visual-editor ul, .rich-visual-editor ol {
            color: #334155;
            font-size: 0.97rem;
            padding-left: 28px;
            margin-bottom: 20px;
        }
        [data-bs-theme="dark"] .rich-visual-editor ul, [data-bs-theme="dark"] .rich-visual-editor ol {
            color: #cbd5e1;
        }
        .rich-visual-editor li {
            margin-bottom: 8px;
            line-height: 1.65;
        }
        .rich-visual-editor a {
            color: #0e2187;
            text-decoration: underline;
        }
        [data-bs-theme="dark"] .rich-visual-editor a {
            color: #93c5fd;
        }
        .rich-visual-editor hr {
            border: 0;
            border-top: 1px solid #e2e8f0;
            margin: 24px 0;
        }
        [data-bs-theme="dark"] .rich-visual-editor hr {
            border-top-color: #334155;
        }

        .legal-preview-box {
            padding: 30px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
            max-height: 700px;
            overflow-y: auto;
            font-family: 'Poppins', sans-serif;
        }
        [data-bs-theme="dark"] .legal-preview-box {
            background-color: #1a2234;
            border-color: #2e3a54;
            color: #e2e8f0;
        }
        .legal-preview-box h2 {
            font-family: 'Montserrat', sans-serif;
            color: #0e2187;
            font-weight: 700;
            font-size: 1.45rem;
            border-left: 4px solid #97c51d;
            padding-left: 12px;
            margin-top: 28px;
            margin-bottom: 14px;
        }
        [data-bs-theme="dark"] .legal-preview-box h2 {
            color: #93c5fd;
        }
        .legal-preview-box h3 {
            font-family: 'Montserrat', sans-serif;
            color: #0e2187;
            font-weight: 600;
            font-size: 1.22rem;
            margin-top: 22px;
            margin-bottom: 10px;
        }
        [data-bs-theme="dark"] .legal-preview-box h3 {
            color: #a5b4fc;
        }
        .legal-preview-box p {
            color: #334155;
            font-size: 0.97rem;
            margin-bottom: 16px;
            line-height: 1.8;
        }
        [data-bs-theme="dark"] .legal-preview-box p {
            color: #cbd5e1;
        }
        .legal-preview-box ul, .legal-preview-box ol {
            color: #334155;
            font-size: 0.97rem;
            padding-left: 28px;
            margin-bottom: 20px;
        }
        [data-bs-theme="dark"] .legal-preview-box ul, [data-bs-theme="dark"] .legal-preview-box ol {
            color: #cbd5e1;
        }
        .legal-preview-box li {
            margin-bottom: 8px;
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
                <!-- Page Title -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                            <h4 class="page-title">General Settings</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="index.php">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="#">Settings</a></li>
                                    <li class="breadcrumb-item active">General Settings</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (!empty($success_message)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Settings Form -->
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-lg-12">
                            <!-- Financial Settings -->
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title mb-0">
                                        <i class="fas fa-money-bill-wave me-2"></i>Financial Settings
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="shipping_cost_gujarat" class="form-label">
                                                    Shipping Cost (per kg) - Gujarat <span class="text-muted">(₹)</span>
                                                </label>
                                                <input type="number" 
                                                       class="form-control" 
                                                       id="shipping_cost_gujarat" 
                                                       name="shipping_cost_gujarat" 
                                                       value="<?php echo htmlspecialchars(getSetting('shipping_cost_gujarat')); ?>"
                                                       step="0.01"
                                                       min="0"
                                                       placeholder="e.g., 50.00">
                                                <small class="text-muted">Shipping rate per kg for orders within Gujarat.</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="shipping_cost_other_states" class="form-label">
                                                    Shipping Cost (per kg) - Other States <span class="text-muted">(₹)</span>
                                                </label>
                                                <input type="number" 
                                                       class="form-control" 
                                                       id="shipping_cost_other_states" 
                                                       name="shipping_cost_other_states" 
                                                       value="<?php echo htmlspecialchars(getSetting('shipping_cost_other_states')); ?>"
                                                       step="0.01"
                                                       min="0"
                                                       placeholder="e.g., 100.00">
                                                <small class="text-muted">Shipping rate per kg for all other states.</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="tax_percentage" class="form-label">
                                                    Tax Percentage <span class="text-muted">(%)</span>
                                                </label>
                                                <input type="number" 
                                                       class="form-control" 
                                                       id="tax_percentage" 
                                                       name="tax_percentage" 
                                                       value="<?php echo htmlspecialchars(getSetting('tax_percentage')); ?>"
                                                       step="0.01"
                                                       min="0"
                                                       max="100"
                                                       placeholder="e.g., 18">
                                                <small class="text-muted">Tax rate percentage (e.g., 5, 12, 18)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Business Information -->
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title mb-0">
                                        <i class="fas fa-building me-2"></i>Business Information
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="business_email" class="form-label">
                                                    Business Email
                                                </label>
                                                <input type="email" 
                                                       class="form-control" 
                                                       id="business_email" 
                                                       name="business_email" 
                                                       value="<?php echo htmlspecialchars(getSetting('business_email')); ?>"
                                                       placeholder="e.g., info@silkysaree.com">
                                                <small class="text-muted">Primary official email for invoices and communications</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="business_phone" class="form-label">
                                                    Business Phone Number
                                                </label>
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="business_phone" 
                                                       name="business_phone" 
                                                       value="<?php echo htmlspecialchars(getSetting('business_phone')); ?>"
                                                       placeholder="e.g., +91 9876543210">
                                                <small class="text-muted">Primary contact number with country code</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label for="business_address" class="form-label">
                                                    Business Address
                                                </label>
                                                <textarea class="form-control" 
                                                          id="business_address" 
                                                          name="business_address" 
                                                          rows="4"
                                                          placeholder="Enter full business address&#10;Line 1&#10;Line 2&#10;City, State - Pincode"><?php echo htmlspecialchars(getSetting('business_address')); ?></textarea>
                                                <small class="text-muted">Full multiline address for invoices and website footer</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label for="business_timings" class="form-label">
                                                    Business Timings
                                                </label>
                                                <textarea class="form-control" 
                                                          id="business_timings" 
                                                          name="business_timings" 
                                                          rows="3"
                                                          placeholder="e.g., Monday-Saturday: 11:30am-9pm&#10;Sunday: 12:00pm-8:00pm"><?php echo htmlspecialchars(getSetting('business_timings')); ?></textarea>
                                                <small class="text-muted">Operating hours displayed in website footer</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Social Media Links -->
                            <div class="card">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-share-alt me-2"></i>Social Media Links
                                        </h4>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="showAddSocialModal()">
                                            <i class="fas fa-plus me-1"></i>Add Link
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="social-links-container" class="row">
                                        <?php
                                        // Display existing social links
                                        $social_platforms = ['facebook', 'instagram', 'twitter', 'pinterest', 'youtube', 'whatsapp'];
                                        $has_links = false;
                                        foreach ($social_platforms as $platform) {
                                            $value = getSetting('social_' . $platform);
                                            if (!empty($value)) {
                                                $has_links = true;
                                                $platform_data = getSocialPlatformData($platform);
                                                echo '<div class="col-md-6 mb-3 social-link-item">';
                                                echo '<div class="input-group">';
                                                echo '<span class="input-group-text"><i class="' . $platform_data['icon'] . ' ' . $platform_data['color'] . '"></i></span>';
                                                echo '<input type="url" class="form-control" name="social_' . $platform . '" value="' . htmlspecialchars($value) . '" placeholder="' . $platform_data['placeholder'] . '">';
                                                echo '<button type="button" class="btn btn-outline-danger" onclick="removeSocialLink(this)"><i class="fas fa-trash"></i></button>';
                                                echo '</div>';
                                                echo '<small class="text-muted">' . $platform_data['label'] . '</small>';
                                                echo '</div>';
                                            }
                                        }
                                        if (!$has_links) {
                                            echo '<div class="col-12"><p class="text-muted mb-0">No social media links added yet. Click "Add Link" to get started.</p></div>';
                                        }
                                        
                                        function getSocialPlatformData($platform) {
                                            $platforms = [
                                                'facebook' => ['label' => 'Facebook', 'icon' => 'fab fa-facebook', 'color' => 'text-primary', 'placeholder' => 'https://facebook.com/yourpage'],
                                                'instagram' => ['label' => 'Instagram', 'icon' => 'fab fa-instagram', 'color' => 'text-danger', 'placeholder' => 'https://instagram.com/yourprofile'],
                                                'twitter' => ['label' => 'Twitter/X', 'icon' => 'fab fa-twitter', 'color' => 'text-info', 'placeholder' => 'https://twitter.com/yourhandle'],
                                                'pinterest' => ['label' => 'Pinterest', 'icon' => 'fab fa-pinterest', 'color' => 'text-danger', 'placeholder' => 'https://pinterest.com/yourprofile'],
                                                'youtube' => ['label' => 'YouTube', 'icon' => 'fab fa-youtube', 'color' => 'text-danger', 'placeholder' => 'https://youtube.com/yourchannel'],
                                                'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'fab fa-whatsapp', 'color' => 'text-success', 'placeholder' => 'https://wa.me/919876543210']
                                            ];
                                            return $platforms[$platform] ?? ['label' => ucfirst($platform), 'icon' => 'fas fa-link', 'color' => '', 'placeholder' => 'https://'];
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Add Social Link Modal -->
                            <div class="modal fade" id="addSocialModal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Add Social Media Link</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label for="social-platform-select" class="form-label">Select Platform</label>
                                                <select class="form-select" id="social-platform-select" onchange="updatePlatformPreview()">
                                                    <option value="">-- Choose Platform --</option>
                                                    <option value="facebook" data-icon="fab fa-facebook" data-color="text-primary">Facebook</option>
                                                    <option value="instagram" data-icon="fab fa-instagram" data-color="text-danger">Instagram</option>
                                                    <option value="twitter" data-icon="fab fa-twitter" data-color="text-info">Twitter/X</option>
                                                    <option value="pinterest" data-icon="fab fa-pinterest" data-color="text-danger">Pinterest</option>
                                                    <option value="youtube" data-icon="fab fa-youtube" data-color="text-danger">YouTube</option>
                                                    <option value="whatsapp" data-icon="fab fa-whatsapp" data-color="text-success">WhatsApp</option>
                                                </select>
                                            </div>
                                            <div class="mb-3" id="url-input-container" style="display:none;">
                                                <label for="social-url-input" class="form-label">
                                                    <span id="platform-icon-preview"></span>
                                                    <span id="platform-name-preview"></span> URL
                                                </label>
                                                <input type="url" class="form-control" id="social-url-input" placeholder="https://">
                                                <small class="text-muted" id="platform-hint"></small>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="button" class="btn btn-primary" onclick="addSocialLink()">Add Link</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Website Settings -->
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title mb-0">
                                        <i class="fas fa-globe me-2"></i>Website Settings
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label for="website_tagline" class="form-label">
                                                    Website Tagline
                                                </label>
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="website_tagline" 
                                                       name="website_tagline" 
                                                       value="<?php echo htmlspecialchars(getSetting('website_tagline')); ?>"
                                                       maxlength="200"
                                                       placeholder='e.g., "Where Elegance Meets Tradition"'>
                                                <small class="text-muted">Short branding line displayed in website header/footer</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Legal & Policy Pages (Terms & Conditions and Privacy Policy) -->
                            <div class="card">
                                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-file-contract me-2 text-primary"></i>Legal & Policy Pages
                                        </h4>
                                        <small class="text-muted">Easily manage website policies with one-click formatting tools — saves directly into <code>tos.php</code> &amp; <code>privacy.php</code></small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="../tos.php" target="_blank" class="btn btn-sm btn-outline-primary" title="View live Terms & Conditions page in new tab">
                                            <i class="fas fa-external-link-alt me-1"></i> View Live tos.php
                                        </a>
                                        <a href="../privacy.php" target="_blank" class="btn btn-sm btn-outline-info" title="View live Privacy Policy page in new tab">
                                            <i class="fas fa-external-link-alt me-1"></i> View Live privacy.php
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Policy Selection Tabs -->
                                    <ul class="nav nav-tabs nav-tabs-custom mb-3" id="legalPolicyTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active fw-semibold" id="tos-tab" data-bs-toggle="tab" data-bs-target="#tos-pane" type="button" role="tab" aria-controls="tos-pane" aria-selected="true">
                                                <i class="fas fa-gavel me-1 text-primary"></i> Terms &amp; Conditions <span class="badge bg-primary-subtle text-primary border ms-1">tos.php</span>
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link fw-semibold" id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy-pane" type="button" role="tab" aria-controls="privacy-pane" aria-selected="false">
                                                <i class="fas fa-user-shield me-1 text-info"></i> Privacy Policy <span class="badge bg-info-subtle text-info border ms-1">privacy.php</span>
                                            </button>
                                        </li>
                                    </ul>

                                    <!-- One-Click Non-Technical Formatting Toolbar -->
                                    <div class="editor-toolbar-wrap d-flex flex-wrap align-items-center gap-1 mb-3">
                                        <span class="text-muted small fw-bold me-2">
                                            <i class="fas fa-magic text-primary me-1"></i> Visual Formatting:
                                        </span>

                                        <!-- Titles & Headings -->
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn" onmousedown="event.preventDefault();" onclick="applyVisualFormat('heading')" title="Convert selected line into a large section title">
                                                <i class="fas fa-heading text-primary me-1"></i> Section Title
                                            </button>
                                            <button type="button" class="btn" onmousedown="event.preventDefault();" onclick="applyVisualFormat('subheading')" title="Convert selected line into a medium subheading">
                                                <i class="fas fa-heading fa-xs text-secondary me-1"></i> Subheading
                                            </button>
                                        </div>

                                        <!-- Text Styles -->
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn fw-bold" onmousedown="event.preventDefault();" onclick="applyVisualFormat('bold')" title="Make selected text bold">
                                                <i class="fas fa-bold me-1"></i> Bold
                                            </button>
                                            <button type="button" class="btn fst-italic" onmousedown="event.preventDefault();" onclick="applyVisualFormat('italic')" title="Make selected text italic">
                                                <i class="fas fa-italic me-1"></i> Italic
                                            </button>
                                            <button type="button" class="btn" onmousedown="event.preventDefault();" onclick="applyVisualFormat('paragraph')" title="Change heading or line back to normal body text">
                                                <i class="fas fa-paragraph text-muted me-1"></i> Normal Text
                                            </button>
                                        </div>

                                        <!-- Lists -->
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn" onmousedown="event.preventDefault();" onclick="applyVisualFormat('bulletList')" title="Turn lines into bulleted list">
                                                <i class="fas fa-list-ul text-success me-1"></i> Bullet Points
                                            </button>
                                            <button type="button" class="btn" onmousedown="event.preventDefault();" onclick="applyVisualFormat('numberList')" title="Turn lines into numbered list">
                                                <i class="fas fa-list-ol text-info me-1"></i> Numbered List
                                            </button>
                                        </div>

                                        <!-- Links & Dividers -->
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn" onmousedown="event.preventDefault();" onclick="applyVisualFormat('link')" title="Turn selected text into a clickable link">
                                                <i class="fas fa-link text-primary me-1"></i> Link
                                            </button>
                                            <button type="button" class="btn" onmousedown="event.preventDefault();" onclick="applyVisualFormat('divider')" title="Insert horizontal divider line">
                                                <i class="fas fa-minus text-muted me-1"></i> Divider
                                            </button>
                                        </div>

                                        <!-- Clear Style -->
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn text-danger" onmousedown="event.preventDefault();" onclick="applyVisualFormat('clear')" title="Clear formatting from selected text">
                                                <i class="fas fa-eraser me-1"></i> Clear Style
                                            </button>
                                        </div>

                                        <!-- Full Visitor Preview Toggle -->
                                        <div class="ms-auto">
                                            <button type="button" class="btn btn-primary text-white" id="togglePreviewBtn" onclick="toggleLivePreview()" title="See how the page looks to visitors without any editing tools">
                                                <i class="fas fa-eye me-1"></i> <span id="togglePreviewText">Full Visitor Preview</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Full Visitor Preview Box (Hidden by default) -->
                                    <div id="previewContainer" class="d-none mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                                            <span class="badge bg-success-subtle text-success border px-2 py-1">
                                                <i class="fas fa-check-circle me-1"></i> Visitor View Preview
                                            </span>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleLivePreview()">
                                                <i class="fas fa-pen me-1"></i> Return to Visual Editor
                                            </button>
                                        </div>
                                        <div class="legal-preview-box" id="previewContent">
                                            <!-- Dynamically rendered visual content -->
                                        </div>
                                    </div>

                                    <div class="tab-content" id="legalPolicyTabContent">
                                        <!-- Terms & Conditions Pane -->
                                        <div class="tab-pane fade show active policy-editor-container" id="tos-pane" role="tabpanel" aria-labelledby="tos-tab">
                                            <div class="mb-2">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <label class="form-label fw-bold mb-0">
                                                        <i class="fas fa-gavel text-primary me-1"></i> Terms &amp; Conditions Visual Editor
                                                    </label>
                                                    <span class="text-muted small"><i class="fas fa-file-code me-1 text-primary"></i> Target file: <code>silky/tos.php</code></span>
                                                </div>
                                                <!-- Visual WYSIWYG Editor (Live Formatted - No HTML Tags Visible) -->
                                                <div class="rich-visual-editor" 
                                                     id="tos_visual_editor" 
                                                     contenteditable="true" 
                                                     spellcheck="true"
                                                     data-placeholder="Write or format your Terms &amp; Conditions here... Type text, highlight it, and click formatting buttons above."><?php echo getLegalPolicyFromFile('tos.php', 'terms_and_conditions'); ?></div>
                                                <textarea name="terms_and_conditions" id="terms_and_conditions" class="d-none"></textarea>
                                                <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-check-circle text-success me-1"></i> <strong>Visual WYSIWYG Editor:</strong> Text appears exactly as it will look to customers on your website. No coding tags! Highlight any text and click the buttons above to format instantly.
                                                    </small>
                                                    <a href="../tos.php" target="_blank" class="text-primary small fw-semibold text-decoration-none">
                                                        Open live tos.php <i class="fas fa-arrow-right ms-1"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Privacy Policy Pane -->
                                        <div class="tab-pane fade policy-editor-container" id="privacy-pane" role="tabpanel" aria-labelledby="privacy-tab">
                                            <div class="mb-2">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <label class="form-label fw-bold mb-0">
                                                        <i class="fas fa-user-shield text-info me-1"></i> Privacy Policy Visual Editor
                                                    </label>
                                                    <span class="text-muted small"><i class="fas fa-file-code me-1 text-info"></i> Target file: <code>silky/privacy.php</code></span>
                                                </div>
                                                <!-- Visual WYSIWYG Editor (Live Formatted - No HTML Tags Visible) -->
                                                <div class="rich-visual-editor" 
                                                     id="privacy_visual_editor" 
                                                     contenteditable="true" 
                                                     spellcheck="true"
                                                     data-placeholder="Write or format your Privacy Policy here... Type text, highlight it, and click formatting buttons above."><?php echo getLegalPolicyFromFile('privacy.php', 'privacy_policy'); ?></div>
                                                <textarea name="privacy_policy" id="privacy_policy" class="d-none"></textarea>
                                                <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-check-circle text-success me-1"></i> <strong>Visual WYSIWYG Editor:</strong> Text appears exactly as it will look to customers on your website. No coding tags! Highlight any text and click the buttons above to format instantly.
                                                    </small>
                                                    <a href="../privacy.php" target="_blank" class="text-info small fw-semibold text-decoration-none">
                                                        Open live privacy.php <i class="fas fa-arrow-right ms-1"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="text-muted">
                                            <small><i class="fas fa-info-circle me-1"></i>These settings will be used in checkout, invoices, and website display</small>
                                        </div>
                                        <div>
                                            <button type="reset" class="btn btn-secondary me-2">
                                                <i class="fas fa-undo me-1"></i>Reset
                                            </button>
                                            <button type="submit" name="save_settings" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i>Save Settings
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

            </div><!-- container-fluid -->

            <!-- Footer Start -->
            <?php include 'footer.php'; ?>
            <!-- end Footer -->

        </div><!-- page-content -->
    </div><!-- page-wrapper -->

    <!-- Javascript  -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/theme-manager.js"></script>

    <script>
        // Platform configuration
        const platformConfig = {
            facebook: {
                icon: 'fab fa-facebook',
                color: 'text-primary',
                label: 'Facebook',
                placeholder: 'https://facebook.com/yourpage',
                hint: 'Enter your Facebook page URL',
                pattern: /^https?:\/\/(www\.)?(facebook\.com|fb\.com)\/.+/i
            },
            instagram: {
                icon: 'fab fa-instagram',
                color: 'text-danger',
                label: 'Instagram',
                placeholder: 'https://instagram.com/yourprofile',
                hint: 'Enter your Instagram profile URL',
                pattern: /^https?:\/\/(www\.)?instagram\.com\/.+/i
            },
            twitter: {
                icon: 'fab fa-twitter',
                color: 'text-info',
                label: 'Twitter/X',
                placeholder: 'https://twitter.com/yourhandle',
                hint: 'Enter your Twitter/X profile URL',
                pattern: /^https?:\/\/(www\.)?(twitter\.com|x\.com)\/.+/i
            },
            pinterest: {
                icon: 'fab fa-pinterest',
                color: 'text-danger',
                label: 'Pinterest',
                placeholder: 'https://pinterest.com/yourprofile',
                hint: 'Enter your Pinterest profile URL',
                pattern: /^https?:\/\/(www\.)?pinterest\.com\/.+/i
            },
            youtube: {
                icon: 'fab fa-youtube',
                color: 'text-danger',
                label: 'YouTube',
                placeholder: 'https://youtube.com/yourchannel',
                hint: 'Enter your YouTube channel URL',
                pattern: /^https?:\/\/(www\.)?(youtube\.com|youtu\.be)\/.+/i
            },
            whatsapp: {
                icon: 'fab fa-whatsapp',
                color: 'text-success',
                label: 'WhatsApp',
                placeholder: 'https://wa.me/919876543210',
                hint: 'Enter your WhatsApp chat or wa.me link',
                pattern: /^https?:\/\/(wa\.me|api\.whatsapp\.com|chat\.whatsapp\.com)\/.+/i
            }
        };

        // Show add social modal
        function showAddSocialModal() {
            $('#social-platform-select').val('');
            $('#social-url-input').val('');
            $('#url-input-container').hide();
            $('#addSocialModal').modal('show');
        }

        // Update platform preview in modal
        function updatePlatformPreview() {
            const select = $('#social-platform-select');
            const platform = select.val();
            
            if (platform && platformConfig[platform]) {
                const config = platformConfig[platform];
                $('#platform-icon-preview').html('<i class="' + config.icon + ' ' + config.color + ' me-1"></i>');
                $('#platform-name-preview').text(config.label);
                $('#social-url-input').attr('placeholder', config.placeholder);
                $('#platform-hint').text(config.hint);
                $('#url-input-container').show();
            } else {
                $('#url-input-container').hide();
            }
        }

        // Add social link
        function addSocialLink() {
            const platform = $('#social-platform-select').val();
            const url = $('#social-url-input').val().trim();
            
            if (!platform) {
                alert('Please select a platform');
                return;
            }
            
            if (!url) {
                alert('Please enter a URL');
                return;
            }
            
            // Validate URL format
            const config = platformConfig[platform];
            if (!config.pattern.test(url)) {
                alert('Please enter a valid ' + config.label + ' URL');
                return;
            }
            
            // Check if platform already exists
            const existingInput = $('input[name="social_' + platform + '"]');
            if (existingInput.length > 0 && existingInput.val()) {
                if (!confirm('A link for ' + config.label + ' already exists. Do you want to replace it?')) {
                    return;
                }
                existingInput.val(url);
                $('#addSocialModal').modal('hide');
                return;
            }
            
            // Remove "no links" message if exists
            $('#social-links-container').find('.col-12 p.text-muted').parent().remove();
            
            // Create new link element
            const linkHtml = `
                <div class="col-md-6 mb-3 social-link-item">
                    <div class="input-group">
                        <span class="input-group-text"><i class="${config.icon} ${config.color}"></i></span>
                        <input type="url" class="form-control" name="social_${platform}" value="${url}" placeholder="${config.placeholder}">
                        <button type="button" class="btn btn-outline-danger" onclick="removeSocialLink(this)"><i class="fas fa-trash"></i></button>
                    </div>
                    <small class="text-muted">${config.label}</small>
                </div>
            `;
            
            $('#social-links-container').append(linkHtml);
            $('#addSocialModal').modal('hide');
            
            // Add change indicator
            $('.card').has('#social-links-container').addClass('border-warning');
        }

        // Remove social link
        function removeSocialLink(button) {
            if (confirm('Are you sure you want to remove this social media link?')) {
                $(button).closest('.social-link-item').remove();
                
                // Show "no links" message if no links left
                if ($('#social-links-container .social-link-item').length === 0) {
                    $('#social-links-container').html('<div class="col-12"><p class="text-muted mb-0">No social media links added yet. Click "Add Link" to get started.</p></div>');
                }
                
                // Add change indicator
                $('.card').has('#social-links-container').addClass('border-warning');
            }
        }

        // Auto-hide alerts after 5 seconds
        $(document).ready(function() {
            setTimeout(function() {
                $('.alert').fadeOut('slow', function() {
                    $(this).remove();
                });
            }, 5000);

            // Form validation
            $('form').on('submit', function(e) {
                var isValid = true;
                var errorMsg = '';

                // Validate shipping cost
                var shippingCost = $('#shipping_cost').val();
                if (shippingCost && (isNaN(shippingCost) || parseFloat(shippingCost) < 0)) {
                    isValid = false;
                    errorMsg = 'Shipping cost must be a valid positive number';
                }

                // Validate tax percentage
                var taxPercentage = $('#tax_percentage').val();
                if (taxPercentage && (isNaN(taxPercentage) || parseFloat(taxPercentage) < 0 || parseFloat(taxPercentage) > 100)) {
                    isValid = false;
                    errorMsg = 'Tax percentage must be between 0 and 100';
                }

                // Validate email
                var email = $('#business_email').val();
                if (email) {
                    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailPattern.test(email)) {
                        isValid = false;
                        errorMsg = 'Please enter a valid email address';
                    }
                }

                // Validate social media URLs
                $('input[name^="social_"]').each(function() {
                    var url = $(this).val();
                    if (url) {
                        var platform = $(this).attr('name').replace('social_', '');
                        if (platformConfig[platform] && !platformConfig[platform].pattern.test(url)) {
                            isValid = false;
                            errorMsg = 'Invalid ' + platformConfig[platform].label + ' URL';
                            return false;
                        }
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    alert(errorMsg);
                    return false;
                }
            });

            // Add change indicators
            $('input, textarea, select').on('change', function() {
                $(this).closest('.card').addClass('border-warning');
            });

            // Numeric input formatting
            $('#shipping_cost').on('blur', function() {
                var val = $(this).val();
                if (val && !isNaN(val)) {
                    $(this).val(parseFloat(val).toFixed(2));
                }
            });

            // Auto-update live preview when switching policy tabs
            const policyTabBtns = document.querySelectorAll('#legalPolicyTabs button[data-bs-toggle="tab"]');
            policyTabBtns.forEach(function(btn) {
                btn.addEventListener('shown.bs.tab', function() {
                    const previewContainer = document.getElementById('previewContainer');
                    if (previewContainer && !previewContainer.classList.contains('d-none')) {
                        const editor = getActiveVisualEditor();
                        const previewContent = document.getElementById('previewContent');
                        if (editor && previewContent) {
                            previewContent.innerHTML = editor.innerHTML;
                        }
                    }
                });
            });

            // Set default paragraph separator to <p>
            try {
                document.execCommand('defaultParagraphSeparator', false, 'p');
            } catch (e) {}

            // Initial sync of visual editors to hidden form inputs
            syncVisualEditors();

            // Real-time synchronization on input, paste, and blur
            ['tos_visual_editor', 'privacy_visual_editor'].forEach(function(id) {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('input', function() {
                        syncVisualEditors();
                        $(this).closest('.card').addClass('border-warning');
                    });
                    el.addEventListener('blur', syncVisualEditors);
                    el.addEventListener('paste', function() {
                        setTimeout(syncVisualEditors, 50);
                    });
                }
            });

            // Ensure synchronization before form submission
            $('form').on('submit', function() {
                syncVisualEditors();
            });

            // Revert visual editors when form reset button is pressed
            $('form').on('reset', function() {
                setTimeout(function() {
                    const tosEditor = document.getElementById('tos_visual_editor');
                    const tosHidden = document.getElementById('terms_and_conditions');
                    if (tosEditor && tosHidden) {
                        tosEditor.innerHTML = tosHidden.defaultValue;
                    }
                    const privEditor = document.getElementById('privacy_visual_editor');
                    const privHidden = document.getElementById('privacy_policy');
                    if (privEditor && privHidden) {
                        privEditor.innerHTML = privHidden.defaultValue;
                    }
                }, 20);
            });
        });

        // Helper to get active policy visual editor canvas
        function getActiveVisualEditor() {
            const tosTab = document.getElementById('tos-tab');
            if (tosTab && (tosTab.classList.contains('active') || tosTab.getAttribute('aria-selected') === 'true')) {
                return document.getElementById('tos_visual_editor');
            }
            return document.getElementById('privacy_visual_editor');
        }

        // Apply visual formatting directly (Live WYSIWYG formatting without showing any raw HTML tags)
        function applyVisualFormat(action) {
            const editor = getActiveVisualEditor();
            if (!editor) return;

            // If visitor preview is active, switch back to visual editor first
            const previewContainer = document.getElementById('previewContainer');
            if (previewContainer && !previewContainer.classList.contains('d-none')) {
                toggleLivePreview();
            }

            // Ensure editor is focused
            const sel = window.getSelection();
            if (!sel || !sel.rangeCount || !editor.contains(sel.anchorNode)) {
                editor.focus();
            }

            switch (action) {
                case 'heading':
                    // Main section title (h2)
                    try {
                        document.execCommand('formatBlock', false, '<h2>');
                    } catch (e) {
                        document.execCommand('formatBlock', false, 'h2');
                    }
                    break;

                case 'subheading':
                    // Subheading (h3)
                    try {
                        document.execCommand('formatBlock', false, '<h3>');
                    } catch (e) {
                        document.execCommand('formatBlock', false, 'h3');
                    }
                    break;

                case 'paragraph':
                    // Change back to normal body text (p)
                    try {
                        document.execCommand('formatBlock', false, '<p>');
                    } catch (e) {
                        document.execCommand('formatBlock', false, 'p');
                    }
                    break;

                case 'bold':
                    // Toggle bold text visually
                    document.execCommand('bold', false, null);
                    break;

                case 'italic':
                    // Toggle italic text visually
                    document.execCommand('italic', false, null);
                    break;

                case 'bulletList':
                    // Turn lines into visual bullet points (ul)
                    document.execCommand('insertUnorderedList', false, null);
                    break;

                case 'numberList':
                    // Turn lines into visual numbered list (ol)
                    document.execCommand('insertOrderedList', false, null);
                    break;

                case 'link':
                    // Add clickable link to selected text or insert link
                    const currentSel = window.getSelection();
                    const selText = currentSel ? currentSel.toString().trim() : '';
                    const defaultUrl = 'https://silkysaree.in';
                    const inputUrl = prompt('Enter website link or page URL (e.g., https://silkysaree.in or contact.php):', defaultUrl);
                    if (inputUrl === null) return; // cancelled
                    const finalUrl = inputUrl.trim() || defaultUrl;

                    if (selText.length === 0) {
                        document.execCommand('insertHTML', false, '<a href="' + finalUrl + '" target="_blank">' + finalUrl + '</a>');
                    } else {
                        document.execCommand('createLink', false, finalUrl);
                    }
                    break;

                case 'divider':
                    // Insert horizontal divider line
                    document.execCommand('insertHorizontalRule', false, null);
                    break;

                case 'clear':
                    // Clear all formatting and revert line to regular text
                    document.execCommand('removeFormat', false, null);
                    try {
                        document.execCommand('formatBlock', false, '<p>');
                    } catch (e) {
                        document.execCommand('formatBlock', false, 'p');
                    }
                    break;
            }

            // Sync visual editor HTML to hidden textarea for form submission
            syncVisualEditors();

            // Mark form as modified
            $(editor).closest('.card').addClass('border-warning');
        }

        // Synchronize visual editor contents into hidden form textareas
        function syncVisualEditors() {
            const tosEditor = document.getElementById('tos_visual_editor');
            const tosTextarea = document.getElementById('terms_and_conditions');
            if (tosEditor && tosTextarea) {
                tosTextarea.value = tosEditor.innerHTML.trim();
            }

            const privEditor = document.getElementById('privacy_visual_editor');
            const privTextarea = document.getElementById('privacy_policy');
            if (privEditor && privTextarea) {
                privTextarea.value = privEditor.innerHTML.trim();
            }
        }

        // Toggle Full Visitor Preview
        function toggleLivePreview() {
            const previewContainer = document.getElementById('previewContainer');
            const previewContent = document.getElementById('previewContent');
            const toggleBtn = document.getElementById('togglePreviewBtn');
            const toggleText = document.getElementById('togglePreviewText');
            const editorPanes = document.querySelectorAll('.policy-editor-container');

            if (!previewContainer) return;

            const isHidden = previewContainer.classList.contains('d-none');
            if (isHidden) {
                syncVisualEditors();
                const editor = getActiveVisualEditor();
                previewContent.innerHTML = editor ? editor.innerHTML : '';

                editorPanes.forEach(function(el) { el.classList.add('d-none'); });
                previewContainer.classList.remove('d-none');

                if (toggleBtn) {
                    toggleBtn.classList.remove('btn-primary');
                    toggleBtn.classList.add('btn-success');
                }
                if (toggleText) toggleText.textContent = 'Return to Visual Editor';
            } else {
                previewContainer.classList.add('d-none');
                editorPanes.forEach(function(el) { el.classList.remove('d-none'); });

                if (toggleBtn) {
                    toggleBtn.classList.remove('btn-success');
                    toggleBtn.classList.add('btn-primary');
                }
                if (toggleText) toggleText.textContent = 'Full Visitor Preview';
            }
        }
    </script>
</body>

</html>