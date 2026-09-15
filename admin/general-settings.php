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
        'social_whatsapp' => mysqli_real_escape_string($conn, trim($_POST['social_whatsapp'] ?? ''))
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
        
        if ($saved_count > 0) {
            $success_message = 'Settings saved successfully!';
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

                            <!-- Save Button -->
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
        });
    </script>
</body>

</html>