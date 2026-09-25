<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
// Permission check handled by session only - edit-website is allowed for all logged-in admins
require_once '../db_config.php';

// Fetch settings
$settings = [];
$res = @mysqli_query($conn, "SELECT * FROM website_settings");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Fetch menus
$main_menus = [];
$bottom_menus = [];
$res = @mysqli_query($conn, "SELECT * FROM navigation_menus ORDER BY display_order ASC");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        if ($row['menu_type'] == 'main_desktop') {
            $main_menus[] = $row;
        } else {
            $bottom_menus[] = $row;
        }
    }
}

// Fetch Hero slides
$hero_slides = [];
$res = @mysqli_query($conn, "SELECT * FROM website_hero_slides ORDER BY slide_order ASC");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $hero_slides[] = $row;
    }
}

// Fetch Reviews
$reviews = [];
$res = @mysqli_query($conn, "SELECT * FROM client_reviews ORDER BY id DESC");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $reviews[] = $row;
    }
}

$success_message = isset($_GET['success']) ? 'Settings saved successfully!' : '';
$error_message = isset($_GET['error']) ? 'Error saving settings.' : '';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light" id="html-root">

<head>
    <meta charset="utf-8" />
    <title>Edit Website | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- App favicon -->
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">
    <!-- App css -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Cropper.js -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">

    <!-- Dark Mode State Check Script -->
    <script>
        // Check and apply saved theme before page loads
        (function () {
            const savedTheme = localStorage.getItem('silky_admin_theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
                document.documentElement.setAttribute('data-startbar', savedTheme);
            }
        })();
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        .list-group-item { cursor: grab; }
        .list-group-item:active { cursor: grabbing; }
        .handle { cursor: grab; padding-right: 15px; color: #888; }
        .card { margin-bottom: 20px; }

        /* ── Chip / Tag Editor ── */
        .wa-chip-editor {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 10px 12px;
            border: 1px solid var(--bs-border-color, #dee2e6);
            border-radius: 8px;
            min-height: 56px;
            background: var(--bs-tertiary-bg, var(--bs-body-bg, #fff));
            cursor: text;
            align-items: center;
            transition: border-color .2s, box-shadow .2s;
        }
        .wa-chip-editor:focus-within {
            border-color: var(--bs-primary, #3d78e3);
            box-shadow: 0 0 0 3px rgba(61,120,227,.18);
        }

        /* ── Default chip (product / budget) ── */
        .wa-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(61,120,227,.12);
            color: var(--bs-primary, #3d78e3);
            border: 1px solid rgba(61,120,227,.28);
            border-radius: 20px;
            padding: 4px 10px 4px 13px;
            font-size: 0.80rem;
            font-weight: 500;
            line-height: 1.4;
            white-space: nowrap;
            animation: chipIn .15s cubic-bezier(.34,1.56,.64,1);
        }
        /* Dark-mode chips stay legible */
        [data-bs-theme="dark"] .wa-chip {
            background: rgba(61,120,227,.22);
            border-color: rgba(61,120,227,.45);
        }

        /* ── Colour chips ── */
        .wa-chip-color {
            background: rgba(255,165,0,.12);
            color: #d97700;
            border-color: rgba(255,165,0,.35);
        }
        [data-bs-theme="dark"] .wa-chip-color {
            background: rgba(255,165,0,.18);
            color: #ffb830;
            border-color: rgba(255,165,0,.45);
        }

        @keyframes chipIn {
            from { transform: scale(.6); opacity: 0; }
            to   { transform: scale(1);  opacity: 1; }
        }

        .wa-chip-remove {
            background: none;
            border: none;
            color: inherit;
            opacity: .5;
            font-size: 1.05rem;
            line-height: 1;
            padding: 0 0 0 2px;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: opacity .15s, transform .15s;
        }
        .wa-chip-remove:hover { opacity: 1; transform: scale(1.2); }

        .wa-chip-input-wrap { flex: 1; min-width: 140px; }
        .wa-chip-input {
            border: none;
            outline: none;
            background: transparent;
            font-size: 0.84rem;
            width: 100%;
            color: var(--bs-body-color, #333);
            padding: 3px 0;
        }
        .wa-chip-input::placeholder { color: var(--bs-secondary-color, #adb5bd); }

        /* ── Field card hover ── */
        .card.border:hover { border-color: var(--bs-primary, #3d78e3) !important; transition: border-color .2s; }

        kbd {
            font-size: 0.70rem;
            padding: 1px 5px;
            border-radius: 4px;
            background: var(--bs-secondary-bg, #e9ecef);
            color: var(--bs-body-color, #444);
            border: 1px solid var(--bs-border-color, #ced4da);
        }
    </style>
</head>

<body>
    <!-- Top Bar Start -->
    <?php include 'topbar.php'; ?>
    <!-- Top Bar End -->

    <!-- Left Sidebar Start -->
    <?php include 'leftbar.php'; ?>
    <!-- Left Sidebar End -->

    <div class="page-wrapper">
        <div class="page-content">
            <div class="container-fluid">
                
                <div class="row">
                    <div class="col-sm-12">
                        <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                            <h4 class="page-title">Edit Website</h4>
                        </div>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                        <?php if (isset($_SESSION['save_errors'])): ?>
                            <hr><small><?php echo $_SESSION['save_errors']; unset($_SESSION['save_errors']); ?></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <ul class="nav nav-tabs card-header-tabs" id="websiteTabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="topbar-tab" data-bs-toggle="tab" href="#topbar" role="tab">Top Bar</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="nav-tab" data-bs-toggle="tab" href="#nav" role="tab">Navigation</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="hero-tab" data-bs-toggle="tab" href="#hero" role="tab">Hero Slider</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="cta-tab" data-bs-toggle="tab" href="#cta" role="tab">Call To Action</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="reviews-tab" data-bs-toggle="tab" href="#reviews" role="tab">Reviews</a>
                                    </li>
                                    <li class="nav-item">
                                         <a class="nav-link" id="whatsapp-tab" data-bs-toggle="tab" href="#whatsapp" role="tab">WhatsApp</a>
                                     </li>
                                     <li class="nav-item">
                                         <a class="nav-link" id="highlights-tab" data-bs-toggle="tab" href="#highlights" role="tab"><i class="bi bi-stars me-1"></i> Product Highlights</a>
                                     </li>
                                     <li class="nav-item">
                                         <a class="nav-link" id="care-tab" data-bs-toggle="tab" href="#care" role="tab"><i class="bi bi-droplet-half me-1"></i> Care Instructions</a>
                                     </li>
                                     <li class="nav-item">
                                         <a class="nav-link" id="benefits-tab" data-bs-toggle="tab" href="#benefits" role="tab"><i class="bi bi-shield-check me-1"></i> Product Benefits</a>
                                     </li>
                                 </ul>
                            </div>
                            <div class="card-body">
                                <form id="editWebsiteForm" action="save-website.php" method="POST" enctype="multipart/form-data" data-ajax="false" novalidate>
                                    <div class="tab-content" id="websiteTabsContent">
                                        
                                        <!-- Top Bar Tab -->
                                        <div class="tab-pane fade show active" id="topbar" role="tabpanel">
                                            <h5 class="card-title">Top Bar Settings</h5>
                                            <div class="mb-3">
                                                <label class="form-label">Phone Number</label>
                                                <input type="text" class="form-control" name="topbar_phone" value="<?php echo htmlspecialchars($settings['topbar_phone'] ?? ''); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Announcement 1</label>
                                                <input type="text" class="form-control" name="topbar_announcement_1" value="<?php echo htmlspecialchars($settings['topbar_announcement_1'] ?? ''); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Announcement 2</label>
                                                <input type="text" class="form-control" name="topbar_announcement_2" value="<?php echo htmlspecialchars($settings['topbar_announcement_2'] ?? ''); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Announcement 3</label>
                                                <input type="text" class="form-control" name="topbar_announcement_3" value="<?php echo htmlspecialchars($settings['topbar_announcement_3'] ?? ''); ?>">
                                            </div>
                                        </div>

                                        <!-- Navigation Tab -->
                                        <div class="tab-pane fade" id="nav" role="tabpanel">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h5 class="card-title">Main Desktop Navigation</h5>
                                                    <p class="text-muted small">Drag to reorder. Click <i class="bi bi-trash text-danger"></i> to remove a link.</p>
                                                    <ul class="list-group" id="main-nav-list">
                                                        <?php foreach ($main_menus as $menu): ?>
                                                                                                                                                                        <li class="list-group-item d-flex align-items-center">
                                                            <i class="fas fa-grip-vertical handle"></i>
                                                            <input type="hidden" name="main_menu_id[]" value="<?php echo $menu['id']; ?>">
                                                            <input type="text" name="main_menu_title[]" class="form-control me-2" value="<?php echo htmlspecialchars($menu['title']); ?>" placeholder="Title">
                                                            <input type="text" name="main_menu_link[]" class="form-control" value="<?php echo htmlspecialchars($menu['link']); ?>" placeholder="Link">
                                                            <button type="button" class="btn btn-danger btn-sm ms-2" onclick="this.closest('.list-group-item').remove()" title="Delete"><i class="bi bi-trash"></i></button>
                                                        </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addMainMenuItem()">+ Add Item</button>
                                                </div>

                                                <div class="col-md-6">
                                                    <h5 class="card-title mt-4 mt-md-0">Mobile Bottom Navigation</h5>
                                                    <p class="text-muted small">Max 5 items. Drag to reorder. Click <i class="bi bi-trash text-danger"></i> to remove.</p>
                                                    <ul class="list-group" id="bottom-nav-list">
                                                        <?php foreach ($bottom_menus as $menu): ?>
                                                                                                                                                                        <li class="list-group-item d-flex align-items-center">
                                                            <i class="fas fa-grip-vertical handle"></i>
                                                            <input type="hidden" name="bottom_menu_id[]" value="<?php echo $menu['id']; ?>">
                                                            <input type="text" name="bottom_menu_title[]" class="form-control me-2" value="<?php echo htmlspecialchars($menu['title']); ?>" placeholder="Title">
                                                            <input type="text" name="bottom_menu_link[]" class="form-control me-2" value="<?php echo htmlspecialchars($menu['link']); ?>" placeholder="Link">
                                                            <select name="bottom_menu_icon[]" class="form-select w-auto">
                                                                <option value="bi bi-house-fill" <?php echo $menu['icon_class'] == 'bi bi-house-fill' ? 'selected' : ''; ?>>Home</option>
                                                                <option value="bi bi-search" <?php echo $menu['icon_class'] == 'bi bi-search' ? 'selected' : ''; ?>>Search</option>
                                                                <option value="bi bi-grid-3x3-gap-fill" <?php echo $menu['icon_class'] == 'bi bi-grid-3x3-gap-fill' ? 'selected' : ''; ?>>Grid/Collections</option>
                                                                <option value="bi bi-person-fill" <?php echo $menu['icon_class'] == 'bi bi-person-fill' ? 'selected' : ''; ?>>Person/Account</option>
                                                                <option value="bi bi-cart-fill" <?php echo $menu['icon_class'] == 'bi bi-cart-fill' ? 'selected' : ''; ?>>Cart</option>
                                                                <option value="bi bi-heart-fill" <?php echo $menu['icon_class'] == 'bi bi-heart-fill' ? 'selected' : ''; ?>>Heart/Wishlist</option>
                                                                <option value="bi bi-bag-fill" <?php echo $menu['icon_class'] == 'bi bi-bag-fill' ? 'selected' : ''; ?>>Bag</option>
                                                                <option value="bi bi-envelope-fill" <?php echo $menu['icon_class'] == 'bi bi-envelope-fill' ? 'selected' : ''; ?>>Envelope</option>
                                                            </select>
                                                            <button type="button" class="btn btn-danger btn-sm ms-2" onclick="this.closest('.list-group-item').remove()" title="Delete"><i class="bi bi-trash"></i></button>
                                                        </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addBottomMenuItem()">+ Add Item</button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Hero Slider Tab -->
                                        <div class="tab-pane fade" id="hero" role="tabpanel">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <h5 class="card-title mb-0">Hero Slides</h5>
                                                    <p class="text-muted small mb-0">Drag to reorder. Images are stored in <code>assets/img/hero/</code></p>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addHeroSlideItem()"><i class="bi bi-plus-circle me-1"></i>Add New Slide</button>
                                            </div>
                                            <ul class="list-group" id="hero-slider-list">
                                                <?php foreach ($hero_slides as $slide): ?>
                                                <li class="list-group-item py-3">
                                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <i class="fas fa-grip-vertical handle text-muted"></i>
                                                            <strong>Slide #<?php echo $slide['id']; ?></strong>
                                                            <input type="hidden" name="slide_id[]" value="<?php echo $slide['id']; ?>">
                                                        </div>
                                                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.list-group-item').remove()">
                                                            <i class="bi bi-trash me-1"></i>Delete Slide
                                                        </button>
                                                    </div>
                                                    <div class="row g-3">
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-semibold">Banner Image</label>
                                                            <?php
                                                            $img_path = htmlspecialchars($slide['image_path']);
                                                            $img_preview = $img_path ? '../' . $img_path : '';
                                                            ?>
                                                            <!-- Current image preview -->
                                                            <div class="mb-2 img-preview-wrap"<?php if (!$img_path) echo ' style="display:none;"'; ?>>
                                                                <img src="<?php echo $img_path ? '../' . $img_path : ''; ?>" class="img-thumbnail" style="max-height:80px;object-fit:cover" onerror="this.style.display='none'">
                                                            </div>
                                                            <input type="hidden" name="slide_image_path[]" class="slide-img-path" value="<?php echo $img_path; ?>">
                                                            <input type="hidden" name="slide_image_base64_<?php echo $slide['id']; ?>" class="slide-base64" value="">
                                                            <div class="btn-group w-100 mb-2" role="group">
                                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openDirectoryPicker(this)">
                                                                    <i class="bi bi-folder2-open me-1"></i>Select
                                                                </button>
                                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="this.closest('.col-md-4').querySelector('.upload-trigger').click()">
                                                                    <i class="bi bi-upload me-1"></i>Upload & Crop
                                                                </button>
                                                            </div>
                                                            <input type="file" class="upload-trigger d-none" accept="image/*" onchange="previewUpload(this)">
                                                            <div class="form-text text-muted font-11">Recommended resolution 1920 x 1080px, Crop (16:9) & convert to WebP (up to 50MB).</div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-semibold">Title</label>
                                                            <input type="text" class="form-control mb-2" name="slide_title[]" value="<?php echo htmlspecialchars($slide['title']); ?>" placeholder="e.g. Elegant Sarees Collection">
                                                            <label class="form-label fw-semibold">Subtitle</label>
                                                            <input type="text" class="form-control" name="slide_subtitle[]" value="<?php echo htmlspecialchars($slide['subtitle']); ?>" placeholder="Short description...">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-semibold">Button 1</label>
                                                            <div class="input-group mb-2">
                                                                <input type="text" class="form-control" name="slide_btn1_text[]" value="<?php echo htmlspecialchars($slide['button_1_text']); ?>" placeholder="Text">
                                                                <input type="text" class="form-control" name="slide_btn1_link[]" value="<?php echo htmlspecialchars($slide['button_1_link']); ?>" placeholder="Link">
                                                            </div>
                                                            <label class="form-label fw-semibold">Button 2</label>
                                                            <div class="input-group">
                                                                <input type="text" class="form-control" name="slide_btn2_text[]" value="<?php echo htmlspecialchars($slide['button_2_text']); ?>" placeholder="Text">
                                                                <input type="text" class="form-control" name="slide_btn2_link[]" value="<?php echo htmlspecialchars($slide['button_2_link']); ?>" placeholder="Link">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>

                                        <!-- Call To Action Tab -->
                                        <div class="tab-pane fade" id="cta" role="tabpanel">
                                            <h5 class="card-title">Call To Action Settings</h5>
                                            <div class="row">
                                                <div class="col-md-12 mb-3">
                                                    <div class="form-check form-switch form-switch-success form-switch-md">
                                                        <input class="form-check-input" type="checkbox" id="cta_show" name="cta_show" value="1" <?php echo ($settings['cta_show'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="cta_show">Show Call To Action Section on Main Website</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Offer Badge</label>
                                                    <input type="text" class="form-control" name="cta_offer_badge" value="<?php echo htmlspecialchars($settings['cta_offer_badge'] ?? ''); ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Heading</label>
                                                    <input type="text" class="form-control" name="cta_heading" value="<?php echo htmlspecialchars($settings['cta_heading'] ?? ''); ?>">
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label">Subtitle</label>
                                                    <textarea class="form-control" name="cta_subtitle" rows="3"><?php echo htmlspecialchars($settings['cta_subtitle'] ?? ''); ?></textarea>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Countdown Target Date</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                                                        <?php 
                                                            $db_date = $settings['cta_countdown_date'] ?? ''; 
                                                            $formatted_date = '';
                                                            if (!empty($db_date)) {
                                                                // Convert slashes to hyphens so strtotime parses correctly (dd-mm-yyyy or yyyy-mm-dd)
                                                                $date_val = str_replace('/', '-', $db_date); 
                                                                $ts = strtotime($date_val);
                                                                if ($ts !== false) {
                                                                    $formatted_date = date('Y-m-d', $ts);
                                                                } else {
                                                                    $formatted_date = $db_date;
                                                                }
                                                            }
                                                        ?>
                                                        <input type="date" class="form-control" id="cta_countdown_date" name="cta_countdown_date" value="<?php echo htmlspecialchars($formatted_date); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Btn 1 Text</label>
                                                    <input type="text" class="form-control" name="cta_btn1_text" value="<?php echo htmlspecialchars($settings['cta_btn1_text'] ?? ''); ?>">
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Btn 1 Link</label>
                                                    <input type="text" class="form-control" name="cta_btn1_link" value="<?php echo htmlspecialchars($settings['cta_btn1_link'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Reviews Tab -->
                                        <div class="tab-pane fade" id="reviews" role="tabpanel">
                                            
                                            <!-- Overall Rating & Review Summary Settings -->
                                            <div class="card border mb-4">
                                                <div class="card-header bg-light-subtle d-flex justify-content-between align-items-center py-2 px-3">
                                                    <h6 class="card-title mb-0 fw-bold">
                                                        <i class="bi bi-star-fill text-warning me-1"></i> Customer Stories - Overall Rating &amp; Review Summary
                                                    </h6>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Customer Stories Header</span>
                                                </div>
                                                <div class="card-body p-3">
                                                    <p class="text-muted small mb-3">
                                                        Configure the overall score rating (e.g. <code>4.9</code>), star icons, and the review count badge (e.g. <code>Based on 4,500+ reviews</code>) shown next to the Customer Stories section on the website.
                                                    </p>
                                                    
                                                    <div class="row align-items-center">
                                                        <!-- Settings Form Fields -->
                                                        <div class="col-lg-7">
                                                            <div class="row g-3">
                                                                <div class="col-md-6">
                                                                    <label class="form-label fw-semibold">Overall Rating Score</label>
                                                                    <div class="input-group">
                                                                        <span class="input-group-text"><i class="bi bi-star-half text-warning"></i></span>
                                                                        <input type="text" class="form-control" name="reviews_summary_rating" id="reviews_summary_rating" 
                                                                            value="<?php echo htmlspecialchars($settings['reviews_summary_rating'] ?? '4.9'); ?>" 
                                                                            placeholder="e.g. 4.9" oninput="updateReviewSummaryPreview()">
                                                                    </div>
                                                                    <small class="text-muted">Displayed as the big rating number (e.g. 4.9, 5.0)</small>
                                                                </div>

                                                                <div class="col-md-6">
                                                                    <label class="form-label fw-semibold">Star Icons Display</label>
                                                                    <select class="form-select" name="reviews_summary_stars" id="reviews_summary_stars" onchange="updateReviewSummaryPreview()">
                                                                        <?php $sel_stars = $settings['reviews_summary_stars'] ?? 'auto'; ?>
                                                                        <option value="auto" <?php echo $sel_stars === 'auto' ? 'selected' : ''; ?>>⚡ Auto (Calculated from Score)</option>
                                                                        <option value="5.0" <?php echo $sel_stars === '5.0' ? 'selected' : ''; ?>>★★★★★ (5 Full Stars)</option>
                                                                        <option value="4.5" <?php echo $sel_stars === '4.5' ? 'selected' : ''; ?>>★★★★½ (4 Full + 1 Half Star)</option>
                                                                        <option value="4.0" <?php echo $sel_stars === '4.0' ? 'selected' : ''; ?>>★★★★☆ (4 Full + 1 Empty Star)</option>
                                                                        <option value="3.5" <?php echo $sel_stars === '3.5' ? 'selected' : ''; ?>>★★★½☆ (3 Full + 1 Half Star)</option>
                                                                        <option value="3.0" <?php echo $sel_stars === '3.0' ? 'selected' : ''; ?>>★★★☆☆ (3 Full Stars)</option>
                                                                    </select>
                                                                    <small class="text-muted">Choose exact stars or let it auto-calculate from score</small>
                                                                </div>

                                                                <div class="col-12">
                                                                    <label class="form-label fw-semibold">Total Ratings / Reviews Text</label>
                                                                    <div class="input-group">
                                                                        <span class="input-group-text"><i class="bi bi-chat-heart text-danger"></i></span>
                                                                        <input type="text" class="form-control" name="reviews_summary_count" id="reviews_summary_count" 
                                                                            value="<?php echo htmlspecialchars($settings['reviews_summary_count'] ?? 'Based on 4,500+ reviews'); ?>" 
                                                                            placeholder="e.g. Based on 4,500+ reviews" oninput="updateReviewSummaryPreview()">
                                                                    </div>
                                                                    <small class="text-muted">Text displayed under the stars (e.g. "Based on 4,500+ reviews", "Over 10,000+ happy buyers")</small>
                                                                </div>

                                                                <div class="col-md-6">
                                                                    <label class="form-label fw-semibold small text-muted">Section Heading</label>
                                                                    <input type="text" class="form-control form-control-sm" name="reviews_section_title" id="reviews_section_title" 
                                                                        value="<?php echo htmlspecialchars($settings['reviews_section_title'] ?? 'What Our Customers Say'); ?>" 
                                                                        placeholder="What Our Customers Say" oninput="updateReviewSummaryPreview()">
                                                                </div>

                                                                <div class="col-md-6">
                                                                    <label class="form-label fw-semibold small text-muted">Section Subtitle</label>
                                                                    <input type="text" class="form-control form-control-sm" name="reviews_section_subtitle" id="reviews_section_subtitle" 
                                                                        value="<?php echo htmlspecialchars($settings['reviews_section_subtitle'] ?? 'Loved by thousands of women across India'); ?>" 
                                                                        placeholder="Loved by thousands of women across India" oninput="updateReviewSummaryPreview()">
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Live Preview Box -->
                                                        <div class="col-lg-5 mt-3 mt-lg-0">
                                                            <div class="card bg-body-tertiary border p-3">
                                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-eye me-1"></i> Live Website Preview</span>
                                                                    <small class="text-muted">Real-time update</small>
                                                                </div>
                                                                
                                                                <div class="p-3 rounded bg-white border" style="background: #ffffff;">
                                                                    <span class="badge bg-light text-success border mb-1" style="font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase;">CUSTOMER STORIES</span>
                                                                    <h5 class="fw-bold mb-1" style="color: #0e2187;" id="preview_reviews_title">What Our Customers Say</h5>
                                                                    <p class="text-muted small mb-3" id="preview_reviews_subtitle">Loved by thousands of women across India</p>

                                                                    <!-- The Summary Card Component -->
                                                                    <div class="d-inline-flex align-items-center gap-3 p-3 rounded-3 border shadow-sm" style="background: #fff; min-width: 220px;">
                                                                        <span class="fw-bold" style="font-size: 36px; color: #0e2187; line-height: 1;" id="preview_big_rating">4.9</span>
                                                                        <div>
                                                                            <div class="text-warning mb-1" style="color: #f59e0b; font-size: 14px;" id="preview_stars">
                                                                                <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i>
                                                                            </div>
                                                                            <small class="text-muted fw-semibold" style="font-size: 12px;" id="preview_count">Based on 4,500+ reviews</small>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div>
                                                    <h5 class="card-title mb-0">Client Reviews</h5>
                                                    <p class="text-muted small mb-0">Edit individual testimonials. Leave author name empty to delete.</p>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addReviewItem()">+ Add New Review</button>
                                            </div>
                                            <div id="reviews-list">
                                                <?php foreach ($reviews as $rev): ?>
                                                                                                <div class="card border mb-3 p-3">
                                                    <div class="d-flex justify-content-end">
                                                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.card').remove()"><i class="bi bi-trash"></i></button>
                                                    </div>
                                                    <input type="hidden" name="review_id[]" value="<?php echo $rev['id']; ?>">
                                                    <div class="row">
                                                        <div class="col-md-9 mb-2">
                                                            <label>Author</label>
                                                            <input type="text" class="form-control" name="review_author[]" value="<?php echo htmlspecialchars($rev['author_name']); ?>">
                                                        </div>
                                                        <div class="col-md-3 mb-2">
                                                            <label>Rating (Max 5.0)</label>
                                                            <input type="number" step="0.1" max="5.0" min="0" class="form-control" name="review_rating[]" value="<?php echo htmlspecialchars($rev['rating']); ?>">
                                                        </div>
                                                        <div class="col-md-12">
                                                            <label>Review Text</label>
                                                            <textarea class="form-control" name="review_text[]"><?php echo htmlspecialchars($rev['review_text']); ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addReviewItem()">+ Add New Review</button>
                                        </div>

                                        <!-- WhatsApp Tab -->
                                        <div class="tab-pane fade" id="whatsapp" role="tabpanel">
                                            <h5 class="card-title mb-3">WhatsApp Enquiry Form Settings</h5>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-semibold">WhatsApp Recipient Number</label>
                                                    <input type="text" class="form-control" name="whatsapp_number" value="<?php echo htmlspecialchars($settings['whatsapp_number'] ?? '918799582279'); ?>" placeholder="e.g. 918799582279">
                                                    <small class="text-muted">Include country code without '+' or spaces (e.g. 918799582279)</small>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-semibold">Form Title</label>
                                                    <input type="text" class="form-control" name="wa_form_title" value="<?php echo htmlspecialchars($settings['wa_form_title'] ?? 'Quick Enquiry'); ?>" placeholder="Quick Enquiry">
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label fw-semibold">Form Subtitle</label>
                                                    <input type="text" class="form-control" name="wa_form_subtitle" value="<?php echo htmlspecialchars($settings['wa_form_subtitle'] ?? "Send us your details — we'll reply on WhatsApp instantly!"); ?>" placeholder="Subtitle description...">
                                                </div>
                                            </div>

                                            <hr class="my-3">
                                            <h6 class="fw-bold mb-3"><i class="bi bi-sliders me-1"></i> Form Fields &amp; Dropdown Options</h6>

                                            <div class="row">
                                                <!-- Product Interest Field -->
                                                <div class="col-md-6 mb-4">
                                                    <div class="card border p-3 h-100">
                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <div>
                                                                <label class="form-label fw-semibold mb-0"><i class="bi bi-bag me-1 text-primary"></i> Product Interest Field</label>
                                                                <div class="small text-muted">Dropdown shown to customer</div>
                                                            </div>
                                                            <div class="form-check form-switch form-switch-success">
                                                                <input class="form-check-input" type="checkbox" name="wa_show_product" value="1" id="wa_show_product" <?php echo ($settings['wa_show_product'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                                                <label class="form-check-label fw-semibold" for="wa_show_product">Show</label>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        $default_products = "Kanjivaram Silk Saree\nBanarasi Silk Saree\nChiffon Saree\nMysore Silk Saree\nPaithani Saree\nDesigner Lehenga\nCotton Saree\nGeorgette Saree\nOther / Custom";
                                                        $product_opts_raw = $settings['wa_product_options'] ?? $default_products;
                                                        $product_opts_arr = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $product_opts_raw)));
                                                        ?>
                                                        <!-- Hidden textarea synced by JS -->
                                                        <textarea class="d-none" name="wa_product_options" id="wa_product_options_hidden"><?php echo htmlspecialchars($product_opts_raw); ?></textarea>
                                                        <!-- Chip Editor -->
                                                        <div class="wa-chip-editor" id="wa_product_chips">
                                                            <?php foreach ($product_opts_arr as $opt): ?>
                                                            <span class="wa-chip"><?php echo htmlspecialchars($opt); ?><button type="button" class="wa-chip-remove" title="Remove">&times;</button></span>
                                                            <?php endforeach; ?>
                                                            <div class="wa-chip-input-wrap">
                                                                <input type="text" class="wa-chip-input" placeholder="Type &amp; press Enter to add…">
                                                            </div>
                                                        </div>
                                                        <small class="text-muted mt-1 d-block"><i class="bi bi-info-circle me-1"></i>Type an option and press <kbd>Enter</kbd> or <kbd>,</kbd> to add. Click &times; to remove.</small>
                                                    </div>
                                                </div>

                                                <!-- Budget Range Field -->
                                                <div class="col-md-6 mb-4">
                                                    <div class="card border p-3 h-100">
                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <div>
                                                                <label class="form-label fw-semibold mb-0"><i class="bi bi-currency-rupee me-1 text-success"></i> Budget Range Field</label>
                                                                <div class="small text-muted">Dropdown shown to customer</div>
                                                            </div>
                                                            <div class="form-check form-switch form-switch-success">
                                                                <input class="form-check-input" type="checkbox" name="wa_show_budget" value="1" id="wa_show_budget" <?php echo ($settings['wa_show_budget'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                                                <label class="form-check-label fw-semibold" for="wa_show_budget">Show</label>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        $default_budgets = "Under ₹1,000\n₹1,000 – ₹3,000\n₹3,000 – ₹5,000\n₹5,000 – ₹10,000\nAbove ₹10,000";
                                                        $budget_opts_raw = $settings['wa_budget_options'] ?? $default_budgets;
                                                        $budget_opts_arr = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $budget_opts_raw)));
                                                        ?>
                                                        <textarea class="d-none" name="wa_budget_options" id="wa_budget_options_hidden"><?php echo htmlspecialchars($budget_opts_raw); ?></textarea>
                                                        <div class="wa-chip-editor" id="wa_budget_chips">
                                                            <?php foreach ($budget_opts_arr as $opt): ?>
                                                            <span class="wa-chip"><?php echo htmlspecialchars($opt); ?><button type="button" class="wa-chip-remove" title="Remove">&times;</button></span>
                                                            <?php endforeach; ?>
                                                            <div class="wa-chip-input-wrap">
                                                                <input type="text" class="wa-chip-input" placeholder="Type &amp; press Enter to add…">
                                                            </div>
                                                        </div>
                                                        <small class="text-muted mt-1 d-block"><i class="bi bi-info-circle me-1"></i>Type an option and press <kbd>Enter</kbd> or <kbd>,</kbd> to add. Click &times; to remove.</small>
                                                    </div>
                                                </div>

                                                <!-- Colour Preference Field -->
                                                <div class="col-md-6 mb-4">
                                                    <div class="card border p-3 h-100">
                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <div>
                                                                <label class="form-label fw-semibold mb-0"><i class="bi bi-palette me-1 text-warning"></i> Colour Preference Field</label>
                                                                <div class="small text-muted">Dropdown of colour choices for customer</div>
                                                            </div>
                                                            <div class="form-check form-switch form-switch-success">
                                                                <input class="form-check-input" type="checkbox" name="wa_show_color" value="1" id="wa_show_color" <?php echo ($settings['wa_show_color'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                                                <label class="form-check-label fw-semibold" for="wa_show_color">Show</label>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        $default_colors = "Red\nPink\nBlue\nGreen\nYellow\nOrange\nPurple\nGold\nSilver\nWhite\nBlack\nMulticolor";
                                                        $color_opts_raw = $settings['wa_color_options'] ?? $default_colors;
                                                        $color_opts_arr = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $color_opts_raw)));
                                                        ?>
                                                        <textarea class="d-none" name="wa_color_options" id="wa_color_options_hidden"><?php echo htmlspecialchars($color_opts_raw); ?></textarea>
                                                        <div class="wa-chip-editor" id="wa_color_chips">
                                                            <?php foreach ($color_opts_arr as $color): ?>
                                                            <span class="wa-chip wa-chip-color"><?php echo htmlspecialchars($color); ?><button type="button" class="wa-chip-remove" title="Remove">&times;</button></span>
                                                            <?php endforeach; ?>
                                                            <div class="wa-chip-input-wrap">
                                                                <input type="text" class="wa-chip-input" placeholder="e.g. Red, Gold…">
                                                            </div>
                                                        </div>
                                                        <small class="text-muted mt-1 d-block"><i class="bi bi-info-circle me-1"></i>Type a colour name and press <kbd>Enter</kbd> to add. Click &times; to remove.</small>
                                                    </div>
                                                </div>

                                                <!-- Additional Message Field -->
                                                <div class="col-md-6 mb-4">
                                                    <div class="card border p-3 h-100">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <label class="form-label fw-semibold mb-0"><i class="bi bi-chat-left-text me-1 text-info"></i> Additional Message Field</label>
                                                                <div class="small text-muted">Textarea for additional customer notes</div>
                                                            </div>
                                                            <div class="form-check form-switch form-switch-success">
                                                                <input class="form-check-input" type="checkbox" name="wa_show_message" value="1" id="wa_show_message" <?php echo ($settings['wa_show_message'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                                                <label class="form-check-label fw-semibold" for="wa_show_message">Show</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Button & Note -->
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-semibold">Submit Button Text</label>
                                                    <input type="text" class="form-control" name="wa_button_text" value="<?php echo htmlspecialchars($settings['wa_button_text'] ?? 'Send Enquiry on WhatsApp'); ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label fw-semibold">Privacy / Safety Note</label>
                                                    <input type="text" class="form-control" name="wa_form_note" value="<?php echo htmlspecialchars($settings['wa_form_note'] ?? 'Your details are safe and will only be used to assist your enquiry.'); ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Product Highlights Tab -->
                                        <div class="tab-pane fade" id="highlights" role="tabpanel">
                                            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                                                <div>
                                                    <h5 class="card-title mb-1">Product Details - Overview Highlight Cards</h5>
                                                    <p class="text-muted small mb-0">These cards appear under the <strong>Overview</strong> section on product pages (e.g. "Why Choose Our Sarees?").</p>
                                                </div>
                                                <div class="form-check form-switch form-switch-success">
                                                    <input class="form-check-input" type="checkbox" name="product_highlights_enabled" value="1" id="product_highlights_enabled" <?php echo ($settings['product_highlights_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label fw-semibold" for="product_highlights_enabled">Show on Product Pages</label>
                                                </div>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label fw-bold">Section Heading</label>
                                                <input type="text" class="form-control form-control-lg" name="product_highlights_title" value="<?php echo htmlspecialchars($settings['product_highlights_title'] ?? 'Why Choose Our Sarees?'); ?>" placeholder="e.g. Why Choose Our Sarees?">
                                                <small class="text-muted">Heading displayed directly above the 4 highlight cards.</small>
                                            </div>

                                            <?php
                                            $default_hl_cards = [
                                                ['icon' => 'bi bi-gem', 'title' => 'Premium Quality', 'desc' => 'Crafted from the finest fabrics for a luxurious feel and elegant drape.'],
                                                ['icon' => 'bi bi-palette2', 'title' => 'Authentic Design', 'desc' => 'Traditional motifs blended beautifully with contemporary aesthetics.'],
                                                ['icon' => 'bi bi-shield-check', 'title' => 'Long-lasting', 'desc' => 'Woven with precision to ensure your saree lasts for generations.'],
                                                ['icon' => 'bi bi-stars', 'title' => 'Perfect Finish', 'desc' => 'Impeccable finishing and attention to detail in every single thread.']
                                            ];
                                            $hl_cards = $default_hl_cards;
                                            if (!empty($settings['product_highlights_cards'])) {
                                                $decoded_hl = json_decode($settings['product_highlights_cards'], true);
                                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_hl) && count($decoded_hl) > 0) {
                                                    $hl_cards = $decoded_hl;
                                                }
                                            }
                                            ?>

                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="fw-bold mb-0">Highlight Cards (Default for all products)</h6>
                                                <button type="button" class="btn btn-sm btn-soft-primary" onclick="addHighlightCard()">
                                                    <i class="fas fa-plus me-1"></i> Add Card
                                                </button>
                                            </div>

                                            <div class="row g-3" id="highlights-cards-list">
                                                <?php foreach ($hl_cards as $idx => $card): 
                                                    $cIcon = !empty($card['icon']) ? $card['icon'] : 'bi bi-gem';
                                                    $cTitle = $card['title'] ?? '';
                                                    $cDesc = $card['desc'] ?? '';
                                                ?>
                                                <div class="col-md-6 highlight-card-item">
                                                    <div class="card border shadow-none mb-0 h-100">
                                                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
                                                            <span class="fw-bold fs-12 text-primary"><i class="fas fa-grip-vertical handle me-2"></i>Card #<span class="card-num"><?php echo $idx + 1; ?></span></span>
                                                            <button type="button" class="btn btn-sm btn-soft-danger py-0 px-2" onclick="removeHighlightCard(this)" title="Delete Card"><i class="fas fa-trash font-11"></i></button>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold">Icon</label>
                                                                <div class="input-group mb-2">
                                                                    <span class="input-group-text icon-preview-box" style="font-size: 1.3rem; color: #97c51d; width: 48px; justify-content: center;">
                                                                        <i class="<?php echo htmlspecialchars($cIcon); ?>"></i>
                                                                    </span>
                                                                    <input type="text" class="form-control card-icon-input" name="highlight_card_icon[]" value="<?php echo htmlspecialchars($cIcon); ?>" oninput="updateCardIconPreview(this)" placeholder="e.g. bi bi-gem">
                                                                </div>
                                                                <div class="d-flex flex-wrap gap-1">
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCardIcon(this, 'bi bi-gem')"><i class="bi bi-gem me-1 text-success"></i>Gem</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCardIcon(this, 'bi bi-palette2')"><i class="bi bi-palette2 me-1 text-primary"></i>Palette</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCardIcon(this, 'bi bi-shield-check')"><i class="bi bi-shield-check me-1 text-success"></i>Shield</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCardIcon(this, 'bi bi-stars')"><i class="bi bi-stars me-1 text-warning"></i>Stars</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCardIcon(this, 'bi bi-award')"><i class="bi bi-award me-1 text-warning"></i>Award</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCardIcon(this, 'bi bi-truck')"><i class="bi bi-truck me-1 text-info"></i>Truck</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCardIcon(this, 'bi bi-patch-check')"><i class="bi bi-patch-check me-1 text-success"></i>Certified</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCardIcon(this, 'bi bi-heart')"><i class="bi bi-heart me-1 text-danger"></i>Heart</span>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold">Card Title *</label>
                                                                <input type="text" class="form-control" name="highlight_card_title[]" value="<?php echo htmlspecialchars($cTitle); ?>" placeholder="e.g. Premium Quality" required>
                                                            </div>
                                                            <div class="mb-2">
                                                                <label class="form-label small fw-semibold">Card Description *</label>
                                                                <textarea class="form-control" name="highlight_card_desc[]" rows="2" placeholder="Brief 1-2 sentence description" required><?php echo htmlspecialchars($cDesc); ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <!-- Care Instructions Tab -->
                                        <div class="tab-pane fade" id="care" role="tabpanel">
                                            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                                                <div>
                                                    <h5 class="card-title mb-1">Product Details - Specifications Care Instructions Cards</h5>
                                                    <p class="text-muted small mb-0">These cards appear in the <strong>Specifications</strong> tab under the Care Instructions section on product pages.</p>
                                                </div>
                                                <div class="form-check form-switch form-switch-success">
                                                    <input class="form-check-input" type="checkbox" name="product_care_enabled" value="1" id="product_care_enabled" <?php echo ($settings['product_care_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label fw-semibold" for="product_care_enabled">Show on Product Pages</label>
                                                </div>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label fw-bold">Section Heading</label>
                                                <input type="text" class="form-control form-control-lg" name="product_care_title" value="<?php echo htmlspecialchars($settings['product_care_title'] ?? 'Care Instructions'); ?>" placeholder="e.g. Care Instructions">
                                                <small class="text-muted">Heading displayed above the care cards in the Specifications tab.</small>
                                            </div>

                                            <?php
                                            $default_care_cards = [
                                                ['icon' => 'bi bi-droplet-half', 'color' => '#0dcaf0', 'title' => 'Washing', 'desc' => 'Dry clean only for best results'],
                                                ['icon' => 'bi bi-brightness-high', 'color' => '#ffc107', 'title' => 'Drying', 'desc' => 'Avoid drying in direct sunlight'],
                                                ['icon' => 'bi bi-archive', 'color' => '#0e2187', 'title' => 'Storage', 'desc' => 'Store in a cool, dry place'],
                                                ['icon' => 'bi bi-thermometer-half', 'color' => '#dc3545', 'title' => 'Ironing', 'desc' => 'Iron on reverse side on low heat']
                                            ];
                                            $care_cards = $default_care_cards;
                                            if (!empty($settings['product_care_cards'])) {
                                                $decoded_care = json_decode($settings['product_care_cards'], true);
                                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_care) && count($decoded_care) > 0) {
                                                    $care_cards = $decoded_care;
                                                }
                                            }
                                            ?>

                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="fw-bold mb-0">Care Instruction Cards (Default for all products)</h6>
                                                <button type="button" class="btn btn-sm btn-soft-primary" onclick="addCareCard()">
                                                    <i class="fas fa-plus me-1"></i> Add Card
                                                </button>
                                            </div>

                                            <div class="row g-3" id="care-cards-list">
                                                <?php foreach ($care_cards as $idx => $card): 
                                                    $cIcon = !empty($card['icon']) ? $card['icon'] : 'bi bi-droplet-half';
                                                    $cColor = !empty($card['color']) ? $card['color'] : '#0dcaf0';
                                                    $cTitle = $card['title'] ?? '';
                                                    $cDesc = $card['desc'] ?? '';
                                                ?>
                                                <div class="col-md-6 care-card-item">
                                                    <div class="card border shadow-none mb-0 h-100">
                                                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
                                                            <span class="fw-bold fs-12 text-primary"><i class="fas fa-grip-vertical handle me-2"></i>Card #<span class="care-card-num"><?php echo $idx + 1; ?></span></span>
                                                            <button type="button" class="btn btn-sm btn-soft-danger py-0 px-2" onclick="removeCareCard(this)" title="Delete Card"><i class="fas fa-trash font-11"></i></button>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold">Icon &amp; Color</label>
                                                                <div class="input-group mb-2">
                                                                    <span class="input-group-text care-icon-preview-box" style="font-size: 1.3rem; color: <?php echo htmlspecialchars($cColor); ?>; width: 48px; justify-content: center;">
                                                                        <i class="<?php echo htmlspecialchars($cIcon); ?>"></i>
                                                                    </span>
                                                                    <input type="text" class="form-control care-card-icon-input" name="care_card_icon[]" value="<?php echo htmlspecialchars($cIcon); ?>" oninput="updateCareIconPreview(this)" placeholder="e.g. bi bi-droplet-half">
                                                                    <input type="color" class="form-control form-control-color care-card-color-input" name="care_card_color[]" value="<?php echo htmlspecialchars($cColor); ?>" onchange="updateCareColorPreview(this)" title="Choose icon color" style="max-width: 48px;">
                                                                </div>
                                                                <div class="d-flex flex-wrap gap-1">
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCarePreset(this, 'bi bi-droplet-half', '#0dcaf0')"><i class="bi bi-droplet-half me-1" style="color:#0dcaf0;"></i>Washing</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCarePreset(this, 'bi bi-brightness-high', '#ffc107')"><i class="bi bi-brightness-high me-1" style="color:#ffc107;"></i>Drying</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCarePreset(this, 'bi bi-archive', '#0e2187')"><i class="bi bi-archive me-1" style="color:#0e2187;"></i>Storage</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCarePreset(this, 'bi bi-thermometer-half', '#dc3545')"><i class="bi bi-thermometer-half me-1" style="color:#dc3545;"></i>Ironing</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCarePreset(this, 'bi bi-shield-check', '#198754')"><i class="bi bi-shield-check me-1 text-success"></i>Care</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCarePreset(this, 'bi bi-wind', '#6c757d')"><i class="bi bi-wind me-1 text-secondary"></i>Air</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCarePreset(this, 'bi bi-stars', '#ffc107')"><i class="bi bi-stars me-1 text-warning"></i>Sparkle</span>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold">Card Title *</label>
                                                                <input type="text" class="form-control" name="care_card_title[]" value="<?php echo htmlspecialchars($cTitle); ?>" placeholder="e.g. Washing" required>
                                                            </div>
                                                            <div class="mb-2">
                                                                <label class="form-label small fw-semibold">Card Description *</label>
                                                                <textarea class="form-control" name="care_card_desc[]" rows="2" placeholder="Brief 1-2 sentence instructions" required><?php echo htmlspecialchars($cDesc); ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <!-- Product Benefits Tab -->
                                        <div class="tab-pane fade" id="benefits" role="tabpanel">
                                            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                                                <div>
                                                    <h5 class="card-title mb-1">Product Details - Trust &amp; Benefit Badges</h5>
                                                    <p class="text-muted small mb-0">These badge cards appear directly under the Add to Cart / Buy Now buttons on product details pages.</p>
                                                </div>
                                                <div class="form-check form-switch form-switch-success">
                                                    <input class="form-check-input" type="checkbox" name="product_benefits_enabled" value="1" id="product_benefits_enabled" <?php echo ($settings['product_benefits_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label fw-semibold" for="product_benefits_enabled">Show on Product Pages</label>
                                                </div>
                                            </div>

                                            <?php
                                            $default_benefit_cards = [
                                                ['icon' => 'bi bi-truck', 'title' => 'Free delivery over ₹999'],
                                                ['icon' => 'bi bi-arrow-repeat', 'title' => 'Easy 7-day returns'],
                                                ['icon' => 'bi bi-shield-check', 'title' => '100% authentic product'],
                                                ['icon' => 'bi bi-headset', 'title' => '24/7 customer support']
                                            ];
                                            $benefit_cards = $default_benefit_cards;
                                            if (!empty($settings['product_benefits_cards'])) {
                                                $decoded_benefits = json_decode($settings['product_benefits_cards'], true);
                                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_benefits) && count($decoded_benefits) > 0) {
                                                    $benefit_cards = $decoded_benefits;
                                                }
                                            }
                                            ?>

                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="fw-bold mb-0">Benefit Cards (Default for all products)</h6>
                                                <button type="button" class="btn btn-sm btn-soft-primary" onclick="addBenefitCard()">
                                                    <i class="fas fa-plus me-1"></i> Add Card
                                                </button>
                                            </div>

                                            <div class="row g-3" id="benefits-cards-list">
                                                <?php foreach ($benefit_cards as $idx => $card): 
                                                    $bIcon = !empty($card['icon']) ? $card['icon'] : 'bi bi-shield-check';
                                                    $bTitle = $card['title'] ?? '';
                                                ?>
                                                <div class="col-md-6 benefit-card-item">
                                                    <div class="card border shadow-none mb-0 h-100">
                                                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
                                                            <span class="fw-bold fs-12 text-primary"><i class="fas fa-grip-vertical handle me-2"></i>Card #<span class="benefit-card-num"><?php echo $idx + 1; ?></span></span>
                                                            <button type="button" class="btn btn-sm btn-soft-danger py-0 px-2" onclick="removeBenefitCard(this)" title="Delete Card"><i class="fas fa-trash font-11"></i></button>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold">Icon</label>
                                                                <div class="input-group mb-2">
                                                                    <span class="input-group-text benefit-icon-preview-box" style="font-size: 1.3rem; color: #97c51d; width: 48px; justify-content: center;">
                                                                        <i class="<?php echo htmlspecialchars($bIcon); ?>"></i>
                                                                    </span>
                                                                    <input type="text" class="form-control benefit-card-icon-input" name="benefit_card_icon[]" value="<?php echo htmlspecialchars($bIcon); ?>" oninput="updateBenefitIconPreview(this)" placeholder="e.g. bi bi-truck">
                                                                </div>
                                                                <div class="d-flex flex-wrap gap-1">
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectBenefitIcon(this, 'bi bi-truck')"><i class="bi bi-truck me-1 text-success"></i>Delivery</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectBenefitIcon(this, 'bi bi-arrow-repeat')"><i class="bi bi-arrow-repeat me-1 text-primary"></i>Returns</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectBenefitIcon(this, 'bi bi-shield-check')"><i class="bi bi-shield-check me-1 text-success"></i>Authentic</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectBenefitIcon(this, 'bi bi-headset')"><i class="bi bi-headset me-1 text-warning"></i>Support</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectBenefitIcon(this, 'bi bi-lock')"><i class="bi bi-lock me-1 text-danger"></i>Secure</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectBenefitIcon(this, 'bi bi-credit-card')"><i class="bi bi-credit-card me-1 text-info"></i>Payment</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectBenefitIcon(this, 'bi bi-box-seam')"><i class="bi bi-box-seam me-1 text-primary"></i>Packaging</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectBenefitIcon(this, 'bi bi-patch-check')"><i class="bi bi-patch-check me-1 text-success"></i>Quality</span>
                                                                </div>
                                                            </div>
                                                            <div class="mb-2">
                                                                <label class="form-label small fw-semibold">Card Text *</label>
                                                                <input type="text" class="form-control" name="benefit_card_title[]" value="<?php echo htmlspecialchars($bTitle); ?>" placeholder="e.g. Free delivery over ₹999" required>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <hr>
                                    <input type="submit" class="btn btn-primary btn-lg px-5" value="Save All Changes">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- container -->
            
            <?php include 'footer.php'; ?>
        </div>
        <!-- end page content -->
    </div>
    <!-- end page-wrapper -->

    <!-- Javascript  -->
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/theme-manager.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <!-- Cropper.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Sortable for Drag and Drop
            new Sortable(document.getElementById('main-nav-list'), { handle: '.handle', animation: 150 });
            new Sortable(document.getElementById('bottom-nav-list'), { handle: '.handle', animation: 150 });
            new Sortable(document.getElementById('hero-slider-list'), { handle: '.handle', animation: 150 });
            const hlList = document.getElementById('highlights-cards-list');
            if (hlList) {
                new Sortable(hlList, { 
                    handle: '.handle', 
                    animation: 150,
                    onEnd: reindexHighlightCards
                });
            }
            const careList = document.getElementById('care-cards-list');
            if (careList) {
                new Sortable(careList, { 
                    handle: '.handle', 
                    animation: 150,
                    onEnd: reindexCareCards
                });
            }
            const benefitsList = document.getElementById('benefits-cards-list');
            if (benefitsList) {
                new Sortable(benefitsList, { 
                    handle: '.handle', 
                    animation: 150,
                    onEnd: reindexBenefitCards
                });
            }
            if (typeof updateReviewSummaryPreview === 'function') {
                updateReviewSummaryPreview();
            }
        });

        function updateBenefitIconPreview(input) {
            const wrap = input.closest('.mb-3');
            const preview = wrap.querySelector('.benefit-icon-preview-box i');
            if (preview) {
                let cls = input.value.trim();
                if (cls.startsWith('bi-') && !cls.startsWith('bi bi-')) cls = 'bi ' + cls;
                preview.className = cls || 'bi bi-shield-check';
            }
        }

        function selectBenefitIcon(badge, iconClass) {
            const wrap = badge.closest('.mb-3');
            const input = wrap.querySelector('.benefit-card-icon-input');
            const preview = wrap.querySelector('.benefit-icon-preview-box i');
            if (input) input.value = iconClass;
            if (preview) preview.className = iconClass;
        }

        function reindexBenefitCards() {
            const list = document.querySelectorAll('#benefits-cards-list .benefit-card-item');
            list.forEach((card, idx) => {
                const num = card.querySelector('.benefit-card-num');
                if (num) num.textContent = idx + 1;
            });
        }

        function removeBenefitCard(btn) {
            const item = btn.closest('.benefit-card-item');
            if (item) {
                if (confirm('Are you sure you want to remove this card?')) {
                    item.remove();
                    reindexBenefitCards();
                }
            }
        }

        function addBenefitCard() {
            const list = document.getElementById('benefits-cards-list');
            if (!list) return;
            const count = list.querySelectorAll('.benefit-card-item').length + 1;
            const html = `
                <div class="col-md-6 benefit-card-item">
                    <div class="card border shadow-none mb-0 h-100">
                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
                            <span class="fw-bold fs-12 text-primary"><i class="fas fa-grip-vertical handle me-2"></i>Card #<span class="benefit-card-num">${count}</span></span>
                            <button type="button" class="btn btn-sm btn-soft-danger py-0 px-2" onclick="removeBenefitCard(this)" title="Delete Card"><i class="fas fa-trash font-11"></i></button>
                        </div>
                        <div class="card-body p-3">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Icon</label>
                                <div class="input-group mb-2">
                                    <span class="input-group-text benefit-icon-preview-box" style="font-size: 1.3rem; color: #97c51d; width: 48px; justify-content: center;">
                                        <i class="bi bi-shield-check"></i>
                                    </span>
                                    <input type="text" class="form-control benefit-card-icon-input" name="benefit_card_icon[]" value="bi bi-shield-check" oninput="updateBenefitIconPreview(this)" placeholder="e.g. bi bi-truck">
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectBenefitIcon(this, 'bi bi-truck')"><i class="bi bi-truck me-1 text-success"></i>Delivery</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectBenefitIcon(this, 'bi bi-arrow-repeat')"><i class="bi bi-arrow-repeat me-1 text-primary"></i>Returns</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectBenefitIcon(this, 'bi bi-shield-check')"><i class="bi bi-shield-check me-1 text-success"></i>Authentic</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectBenefitIcon(this, 'bi bi-headset')"><i class="bi bi-headset me-1 text-warning"></i>Support</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectBenefitIcon(this, 'bi bi-lock')"><i class="bi bi-lock me-1 text-danger"></i>Secure</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectBenefitIcon(this, 'bi bi-credit-card')"><i class="bi bi-credit-card me-1 text-info"></i>Payment</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectBenefitIcon(this, 'bi bi-box-seam')"><i class="bi bi-box-seam me-1 text-primary"></i>Packaging</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectBenefitIcon(this, 'bi bi-patch-check')"><i class="bi bi-patch-check me-1 text-success"></i>Quality</span>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Card Text *</label>
                                <input type="text" class="form-control" name="benefit_card_title[]" value="" placeholder="e.g. Free delivery over ₹999" required>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            list.insertAdjacentHTML('beforeend', html);
        }

        function updateCardIconPreview(input) {
            const wrap = input.closest('.mb-3');
            const preview = wrap.querySelector('.icon-preview-box i');
            if (preview) {
                let cls = input.value.trim();
                if (cls.startsWith('bi-') && !cls.startsWith('bi bi-')) cls = 'bi ' + cls;
                preview.className = cls || 'bi bi-gem';
            }
        }

        function selectCardIcon(badge, iconClass) {
            const wrap = badge.closest('.mb-3');
            const input = wrap.querySelector('.card-icon-input');
            const preview = wrap.querySelector('.icon-preview-box i');
            if (input) input.value = iconClass;
            if (preview) preview.className = iconClass;
        }

        function reindexHighlightCards() {
            const list = document.querySelectorAll('#highlights-cards-list .highlight-card-item');
            list.forEach((card, idx) => {
                const num = card.querySelector('.card-num');
                if (num) num.textContent = idx + 1;
            });
        }

        function removeHighlightCard(btn) {
            const item = btn.closest('.highlight-card-item');
            if (item) {
                if (confirm('Are you sure you want to remove this card?')) {
                    item.remove();
                    reindexHighlightCards();
                }
            }
        }

        function addHighlightCard() {
            const list = document.getElementById('highlights-cards-list');
            if (!list) return;
            const count = list.querySelectorAll('.highlight-card-item').length + 1;
            const html = `
                <div class="col-md-6 highlight-card-item">
                    <div class="card border shadow-none mb-0 h-100">
                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
                            <span class="fw-bold fs-12 text-primary"><i class="fas fa-grip-vertical handle me-2"></i>Card #<span class="card-num">${count}</span></span>
                            <button type="button" class="btn btn-sm btn-soft-danger py-0 px-2" onclick="removeHighlightCard(this)" title="Delete Card"><i class="fas fa-trash font-11"></i></button>
                        </div>
                        <div class="card-body p-3">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Icon</label>
                                <div class="input-group mb-2">
                                    <span class="input-group-text icon-preview-box" style="font-size: 1.3rem; color: #97c51d; width: 48px; justify-content: center;">
                                        <i class="bi bi-stars"></i>
                                    </span>
                                    <input type="text" class="form-control card-icon-input" name="highlight_card_icon[]" value="bi bi-stars" oninput="updateCardIconPreview(this)" placeholder="e.g. bi bi-stars">
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCardIcon(this, 'bi bi-gem')"><i class="bi bi-gem me-1 text-success"></i>Gem</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCardIcon(this, 'bi bi-palette2')"><i class="bi bi-palette2 me-1 text-primary"></i>Palette</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCardIcon(this, 'bi bi-shield-check')"><i class="bi bi-shield-check me-1 text-success"></i>Shield</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCardIcon(this, 'bi bi-stars')"><i class="bi bi-stars me-1 text-warning"></i>Stars</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCardIcon(this, 'bi bi-award')"><i class="bi bi-award me-1 text-warning"></i>Award</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCardIcon(this, 'bi bi-truck')"><i class="bi bi-truck me-1 text-info"></i>Truck</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCardIcon(this, 'bi bi-patch-check')"><i class="bi bi-patch-check me-1 text-success"></i>Certified</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCardIcon(this, 'bi bi-heart')"><i class="bi bi-heart me-1 text-danger"></i>Heart</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Card Title *</label>
                                <input type="text" class="form-control" name="highlight_card_title[]" value="" placeholder="e.g. Handcrafted Elegance" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Card Description *</label>
                                <textarea class="form-control" name="highlight_card_desc[]" rows="2" placeholder="Brief 1-2 sentence description" required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            list.insertAdjacentHTML('beforeend', html);
        }

        function updateCareIconPreview(input) {
            const wrap = input.closest('.mb-3');
            const preview = wrap.querySelector('.care-icon-preview-box i');
            if (preview) {
                let cls = input.value.trim();
                if (cls.startsWith('bi-') && !cls.startsWith('bi bi-')) cls = 'bi ' + cls;
                preview.className = cls || 'bi bi-droplet-half';
            }
        }

        function updateCareColorPreview(input) {
            const wrap = input.closest('.mb-3');
            const previewBox = wrap.querySelector('.care-icon-preview-box');
            if (previewBox) {
                previewBox.style.color = input.value;
            }
        }

        function selectCarePreset(badge, iconClass, colorHex) {
            const wrap = badge.closest('.mb-3');
            const iconInput = wrap.querySelector('.care-card-icon-input');
            const colorInput = wrap.querySelector('.care-card-color-input');
            const previewBox = wrap.querySelector('.care-icon-preview-box');
            const previewIcon = previewBox ? previewBox.querySelector('i') : null;
            if (iconInput) iconInput.value = iconClass;
            if (colorInput) colorInput.value = colorHex;
            if (previewIcon) previewIcon.className = iconClass;
            if (previewBox) previewBox.style.color = colorHex;
        }

        function reindexCareCards() {
            const list = document.querySelectorAll('#care-cards-list .care-card-item');
            list.forEach((card, idx) => {
                const num = card.querySelector('.care-card-num');
                if (num) num.textContent = idx + 1;
            });
        }

        function removeCareCard(btn) {
            const item = btn.closest('.care-card-item');
            if (item) {
                if (confirm('Are you sure you want to remove this care card?')) {
                    item.remove();
                    reindexCareCards();
                }
            }
        }

        function addCareCard() {
            const list = document.getElementById('care-cards-list');
            if (!list) return;
            const count = list.querySelectorAll('.care-card-item').length + 1;
            const html = `
                <div class="col-md-6 care-card-item">
                    <div class="card border shadow-none mb-0 h-100">
                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
                            <span class="fw-bold fs-12 text-primary"><i class="fas fa-grip-vertical handle me-2"></i>Card #<span class="care-card-num">${count}</span></span>
                            <button type="button" class="btn btn-sm btn-soft-danger py-0 px-2" onclick="removeCareCard(this)" title="Delete Card"><i class="fas fa-trash font-11"></i></button>
                        </div>
                        <div class="card-body p-3">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Icon & Color</label>
                                <div class="input-group mb-2">
                                    <span class="input-group-text care-icon-preview-box" style="font-size: 1.3rem; color: #0dcaf0; width: 48px; justify-content: center;">
                                        <i class="bi bi-droplet-half"></i>
                                    </span>
                                    <input type="text" class="form-control care-card-icon-input" name="care_card_icon[]" value="bi bi-droplet-half" oninput="updateCareIconPreview(this)" placeholder="e.g. bi bi-droplet-half">
                                    <input type="color" class="form-control form-control-color care-card-color-input" name="care_card_color[]" value="#0dcaf0" onchange="updateCareColorPreview(this)" title="Choose icon color" style="max-width: 48px;">
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCarePreset(this, 'bi bi-droplet-half', '#0dcaf0')"><i class="bi bi-droplet-half me-1" style="color:#0dcaf0;"></i>Washing</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCarePreset(this, 'bi bi-brightness-high', '#ffc107')"><i class="bi bi-brightness-high me-1" style="color:#ffc107;"></i>Drying</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCarePreset(this, 'bi bi-archive', '#0e2187')"><i class="bi bi-archive me-1" style="color:#0e2187;"></i>Storage</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCarePreset(this, 'bi bi-thermometer-half', '#dc3545')"><i class="bi bi-thermometer-half me-1" style="color:#dc3545;"></i>Ironing</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCarePreset(this, 'bi bi-shield-check', '#198754')"><i class="bi bi-shield-check me-1 text-success"></i>Care</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCarePreset(this, 'bi bi-wind', '#6c757d')"><i class="bi bi-wind me-1 text-secondary"></i>Air</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="selectCarePreset(this, 'bi bi-stars', '#ffc107')"><i class="bi bi-stars me-1 text-warning"></i>Sparkle</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Card Title *</label>
                                <input type="text" class="form-control" name="care_card_title[]" value="" placeholder="e.g. Washing" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Card Description *</label>
                                <textarea class="form-control" name="care_card_desc[]" rows="2" placeholder="Brief 1-2 sentence instructions" required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            list.insertAdjacentHTML('beforeend', html);
        }

        // Force native POST submit - bypasses any JS framework interceptors
        function doSave() {
            var form = document.getElementById('editWebsiteForm');
            if (!form) { alert('Form not found!'); return; }
            
            // Clear the unsaved changes flag since we are saving
            window.hasUnsavedChanges = false;
            
            // Force the method and action just in case
            form.method = 'POST';
            form.action = 'save-website.php';
            
            // Use native submit to bypass any jQuery/Bootstrap event handlers
            HTMLFormElement.prototype.submit.call(form);
        }

                        function addMainMenuItem() {
            const html = `
                <li class="list-group-item d-flex align-items-center">
                    <i class="fas fa-grip-vertical handle"></i>
                    <input type="hidden" name="main_menu_id[]" value="new">
                    <input type="text" name="main_menu_title[]" class="form-control me-2" placeholder="Title">
                    <input type="text" name="main_menu_link[]" class="form-control" placeholder="Link">
                    <button type="button" class="btn btn-danger btn-sm ms-2" onclick="this.closest('.list-group-item').remove()" title="Delete"><i class="bi bi-trash"></i></button>
                </li>
            `;
            document.getElementById('main-nav-list').insertAdjacentHTML('beforeend', html);
        }

        function addBottomMenuItem() {
            const html = `
                <li class="list-group-item d-flex align-items-center">
                    <i class="fas fa-grip-vertical handle"></i>
                    <input type="hidden" name="bottom_menu_id[]" value="new">
                    <input type="text" name="bottom_menu_title[]" class="form-control me-2" placeholder="Title">
                    <input type="text" name="bottom_menu_link[]" class="form-control me-2" placeholder="Link">
                    <select name="bottom_menu_icon[]" class="form-select w-auto">
                        <option value="bi bi-house-fill">Home</option>
                        <option value="bi bi-search">Search</option>
                        <option value="bi bi-grid-3x3-gap-fill">Grid/Collections</option>
                        <option value="bi bi-person-fill">Person/Account</option>
                        <option value="bi bi-cart-fill">Cart</option>
                        <option value="bi bi-heart-fill">Heart/Wishlist</option>
                        <option value="bi bi-bag-fill">Bag</option>
                        <option value="bi bi-envelope-fill">Envelope</option>
                    </select>
                    <button type="button" class="btn btn-danger btn-sm ms-2" onclick="this.closest('.list-group-item').remove()" title="Delete"><i class="bi bi-trash"></i></button>
                </li>
            `;
            document.getElementById('bottom-nav-list').insertAdjacentHTML('beforeend', html);
        }

        function addHeroSlideItem() {
            const id = 'new_' + Date.now();
            const html = `
                <li class="list-group-item py-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-grip-vertical handle text-muted"></i>
                            <strong>New Slide</strong>
                            <input type="hidden" name="slide_id[]" value="${id}">
                        </div>
                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.list-group-item').remove()">
                            <i class="bi bi-trash me-1"></i>Delete Slide
                        </button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Banner Image</label>
                            <div class="mb-2 img-preview-wrap" style="display:none;">
                                <img src="" class="img-thumbnail" style="max-height:80px;object-fit:cover" onerror="this.style.display='none'">
                            </div>
                            <input type="hidden" name="slide_image_path[]" class="slide-img-path" value="">
                            <input type="hidden" name="slide_image_base64_${id}" class="slide-base64" value="">
                            <div class="btn-group w-100 mb-2" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openDirectoryPicker(this)">
                                    <i class="bi bi-folder2-open me-1"></i>Select
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="this.closest('.col-md-4').querySelector('.upload-trigger').click()">
                                    <i class="bi bi-upload me-1"></i>Upload & Crop
                                </button>
                            </div>
                            <input type="file" class="upload-trigger d-none" accept="image/*" onchange="previewUpload(this)">
                            <div class="form-text text-muted font-11">Crop (16:9) & convert to WebP (up to 50MB).</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Title</label>
                            <input type="text" class="form-control mb-2" name="slide_title[]" placeholder="e.g. Designer Collection">
                            <label class="form-label fw-semibold">Subtitle</label>
                            <input type="text" class="form-control" name="slide_subtitle[]" placeholder="Short description...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Button 1</label>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" name="slide_btn1_text[]" placeholder="Shop Now">
                                <input type="text" class="form-control" name="slide_btn1_link[]" placeholder="#products">
                            </div>
                            <label class="form-label fw-semibold">Button 2</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="slide_btn2_text[]" placeholder="Explore">
                                <input type="text" class="form-control" name="slide_btn2_link[]" placeholder="#categories">
                            </div>
                        </div>
                    </div>
                </li>
            `;
            document.getElementById('hero-slider-list').insertAdjacentHTML('beforeend', html);
        }

        function addReviewItem() {
            const html = `
                <div class="card border mb-3 p-3">
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.card').remove()"><i class="bi bi-trash"></i></button>
                    </div>
                    <input type="hidden" name="review_id[]" value="new">
                    <div class="row">
                        <div class="col-md-9 mb-2">
                            <label>Author</label>
                            <input type="text" class="form-control" name="review_author[]" placeholder="Author Name">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label>Rating (Max 5.0)</label>
                            <input type="number" step="0.1" max="5.0" min="0" class="form-control" name="review_rating[]" value="5.0">
                        </div>
                        <div class="col-md-12">
                            <label>Review Text</label>
                            <textarea class="form-control" name="review_text[]" placeholder="Customer review..."></textarea>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('reviews-list').insertAdjacentHTML('beforeend', html);
        }

        function renderStarsHtml(score, starsMode) {
            let rating = parseFloat(score);
            if (isNaN(rating) || rating <= 0) rating = 4.9;

            let targetRating = rating;
            if (starsMode && starsMode !== 'auto') {
                targetRating = parseFloat(starsMode);
                if (isNaN(targetRating)) targetRating = rating;
            }

            let starsHtml = '';
            for (let i = 1; i <= 5; i++) {
                if (targetRating >= i) {
                    starsHtml += '<i class="bi bi-star-fill"></i>';
                } else if (targetRating >= (i - 0.75)) {
                    starsHtml += '<i class="bi bi-star-half"></i>';
                } else {
                    starsHtml += '<i class="bi bi-star"></i>';
                }
            }
            return starsHtml;
        }

        function updateReviewSummaryPreview() {
            const ratingInput = document.getElementById('reviews_summary_rating');
            const starsSelect = document.getElementById('reviews_summary_stars');
            const countInput = document.getElementById('reviews_summary_count');
            const titleInput = document.getElementById('reviews_section_title');
            const subtitleInput = document.getElementById('reviews_section_subtitle');

            const previewRating = document.getElementById('preview_big_rating');
            const previewStars = document.getElementById('preview_stars');
            const previewCount = document.getElementById('preview_count');
            const previewTitle = document.getElementById('preview_reviews_title');
            const previewSubtitle = document.getElementById('preview_reviews_subtitle');

            if (previewRating && ratingInput) {
                previewRating.textContent = ratingInput.value.trim() || '4.9';
            }
            if (previewCount && countInput) {
                previewCount.textContent = countInput.value.trim() || 'Based on 4,500+ reviews';
            }
            if (previewTitle && titleInput) {
                previewTitle.textContent = titleInput.value.trim() || 'What Our Customers Say';
            }
            if (previewSubtitle && subtitleInput) {
                previewSubtitle.textContent = subtitleInput.value.trim() || 'Loved by thousands of women across India';
            }
            if (previewStars && ratingInput && starsSelect) {
                previewStars.innerHTML = renderStarsHtml(ratingInput.value.trim(), starsSelect.value);
            }
        }

        // ── Directory Picker ──────────────────────────────────────────
        let _pickerTarget = null;

        function openDirectoryPicker(btn) {
            _pickerTarget = btn.closest('.col-md-4');
            const modal = new bootstrap.Modal(document.getElementById('heroBannerModal'));
            const grid = document.getElementById('heroBannerGrid');
            grid.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Loading images...</p></div>';
            modal.show();

            fetch('get-hero-banners.php')
                .then(r => r.json())
                .then(images => {
                    if (!images.length) {
                        grid.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-images" style="font-size:2rem"></i><p class="mt-2">No images in hero directory.<br>Upload images to <code>assets/img/hero/</code></p></div>';
                        return;
                    }
                    grid.innerHTML = images.map(img => `
                        <div class="col-4 col-md-3 col-lg-2">
                            <div class="card h-100 border picker-card" style="cursor:pointer" onclick="selectBannerImage('${img.path}', '../${img.path}')" title="${img.name}">
                                <img src="../${img.path}" class="card-img-top" style="height:90px;object-fit:cover" onerror="this.src='assets/img/no-image.png'">
                                <div class="card-body p-1 text-center">
                                    <small class="text-muted d-block text-truncate" style="font-size:10px">${img.name}</small>
                                    <small class="text-muted" style="font-size:10px">${img.size}</small>
                                </div>
                            </div>
                        </div>
                    `).join('');
                })
                .catch(() => { grid.innerHTML = '<p class="text-danger p-3">Error loading images.</p>'; });
        }

        function selectBannerImage(path, previewSrc) {
            if (!_pickerTarget) return;
            // Set hidden input (and clear any previous base64 crop)
            const input = _pickerTarget.querySelector('.slide-img-path');
            if (input) input.value = path;
            const b64 = _pickerTarget.querySelector('.slide-base64');
            if (b64) b64.value = '';

            // Remove any temporary WebP badge
            const oldBadge = _pickerTarget.querySelector('.crop-webp-badge');
            if (oldBadge) oldBadge.remove();

            // Show/update preview
            let wrap = _pickerTarget.querySelector('.img-preview-wrap');
            if (!wrap) {
                wrap = document.createElement('div');
                wrap.className = 'img-preview-wrap mb-2';
                wrap.innerHTML = '<img class="img-thumbnail" style="max-height:80px;object-fit:cover">';
                if (input) {
                    input.before(wrap);
                } else {
                    _pickerTarget.prepend(wrap);
                }
            }
            wrap.style.display = '';
            const previewImg = wrap.querySelector('img');
            if (previewImg) {
                previewImg.src = previewSrc;
                previewImg.style.display = '';
            }
            // Highlight selected
            document.querySelectorAll('.picker-card').forEach(c => c.classList.remove('border-primary', 'border-2'));
            event.currentTarget.querySelector('.picker-card')?.classList.add('border-primary','border-2');
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('heroBannerModal')).hide();
        }

        // ── Cropper & WebP Conversion ─────────────────────────
        let _cropTarget = null;
        let _cropper = null;
        let _activeCropObjectUrl = null;
        let _selectedCropFileName = '';

        function previewUpload(input) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];

            // 50MB safety limit
            const maxBytes = 50 * 1024 * 1024;
            if (file.size > maxBytes) {
                alert('Image is too large. Maximum 50 MB allowed.');
                input.value = '';
                return;
            }

            _cropTarget = input.closest('.col-md-4');
            _selectedCropFileName = file.name || ('hero_' + Date.now());

            if (_activeCropObjectUrl) {
                URL.revokeObjectURL(_activeCropObjectUrl);
                _activeCropObjectUrl = null;
            }
            _activeCropObjectUrl = URL.createObjectURL(file);

            const cropModalEl = document.getElementById('cropModal');
            const cropModal = bootstrap.Modal.getOrCreateInstance(cropModalEl);
            const cropImg = document.getElementById('cropImageToCrop');

            function onShown() {
                cropModalEl.removeEventListener('shown.bs.modal', onShown);
                if (_cropper) {
                    _cropper.destroy();
                    _cropper = null;
                }
                _cropper = new Cropper(cropImg, {
                    aspectRatio: 16 / 9,
                    viewMode: 1,
                    autoCropArea: 1,
                    responsive: true,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false
                });
            }

            cropModalEl.addEventListener('shown.bs.modal', onShown);
            cropImg.src = _activeCropObjectUrl;
            cropModal.show();

            // Clear input so same file selection triggers change event if chosen again
            input.value = '';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const cropModalEl = document.getElementById('cropModal');
            if (cropModalEl) {
                cropModalEl.addEventListener('hidden.bs.modal', function () {
                    if (_cropper) {
                        _cropper.destroy();
                        _cropper = null;
                    }
                    if (_activeCropObjectUrl) {
                        URL.revokeObjectURL(_activeCropObjectUrl);
                        _activeCropObjectUrl = null;
                    }
                });
            }
        });

        async function performCrop() {
            if (!_cropper || !_cropTarget) return;

            const cropBtn = document.getElementById('btn-crop-upload');
            const originalBtnHtml = cropBtn ? cropBtn.innerHTML : '<i class="bi bi-check-lg me-1"></i>Crop & Convert to WebP';
            if (cropBtn) {
                cropBtn.disabled = true;
                cropBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Converting to WebP...';
            }

            // Export high-resolution 16:9 canvas (1920x1080)
            const canvas = _cropper.getCroppedCanvas({
                width: 1920,
                height: 1080,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });

            if (!canvas) {
                if (cropBtn) {
                    cropBtn.disabled = false;
                    cropBtn.innerHTML = originalBtnHtml;
                }
                alert('Could not generate cropped canvas.');
                return;
            }

            canvas.toBlob(async (blob) => {
                if (!blob) {
                    if (cropBtn) {
                        cropBtn.disabled = false;
                        cropBtn.innerHTML = originalBtnHtml;
                    }
                    alert('Error creating WebP image. Please try again.');
                    return;
                }

                const formData = new FormData();
                let baseName = 'hero_' + Date.now();
                if (_selectedCropFileName) {
                    baseName = _selectedCropFileName.replace(/\.[^/.]+$/, '').replace(/[^a-zA-Z0-9_-]/g, '_');
                }
                const filename = baseName + '.webp';

                formData.append('image', blob, filename);
                formData.append('type', 'hero');

                try {
                    const response = await fetch('upload-image.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        const pathInput = _cropTarget.querySelector('.slide-img-path');
                        if (pathInput) pathInput.value = data.path;

                        // Clear base64 since file is successfully uploaded and saved as WebP directly
                        const b64Input = _cropTarget.querySelector('.slide-base64');
                        if (b64Input) b64Input.value = '';

                        // Show/update preview
                        let wrap = _cropTarget.querySelector('.img-preview-wrap');
                        if (!wrap) {
                            wrap = document.createElement('div');
                            wrap.className = 'img-preview-wrap mb-2';
                            wrap.innerHTML = '<img class="img-thumbnail" style="max-height:80px;object-fit:cover">';
                            if (pathInput) {
                                pathInput.before(wrap);
                            } else {
                                _cropTarget.prepend(wrap);
                            }
                        }
                        wrap.style.display = '';
                        const previewImg = wrap.querySelector('img');
                        if (previewImg) {
                            previewImg.src = '../' + data.path + '?t=' + Date.now();
                            previewImg.style.display = '';
                        }

                        // Badge indicator
                        let badge = _cropTarget.querySelector('.crop-webp-badge');
                        if (!badge) {
                            badge = document.createElement('div');
                            badge.className = 'crop-webp-badge mb-2';
                            wrap.after(badge);
                        }
                        badge.innerHTML = '<span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>WebP Image Ready (' + (data.filename || filename) + ')</span>';

                        const cropModal = bootstrap.Modal.getInstance(document.getElementById('cropModal'));
                        if (cropModal) cropModal.hide();
                    } else {
                        // Fallback: convert to WebP base64 so save-website.php can save it on form submit
                        const webpB64 = canvas.toDataURL('image/webp', 0.88);
                        const b64Input = _cropTarget.querySelector('.slide-base64');
                        if (b64Input) b64Input.value = webpB64;
                        const pathInput = _cropTarget.querySelector('.slide-img-path');
                        if (pathInput) pathInput.value = '';

                        let wrap = _cropTarget.querySelector('.img-preview-wrap');
                        if (!wrap) {
                            wrap = document.createElement('div');
                            wrap.className = 'img-preview-wrap mb-2';
                            wrap.innerHTML = '<img class="img-thumbnail" style="max-height:80px;object-fit:cover">';
                            if (pathInput) pathInput.before(wrap);
                        }
                        wrap.style.display = '';
                        const previewImg = wrap.querySelector('img');
                        if (previewImg) {
                            previewImg.src = webpB64;
                            previewImg.style.display = '';
                        }

                        const cropModal = bootstrap.Modal.getInstance(document.getElementById('cropModal'));
                        if (cropModal) cropModal.hide();
                    }
                } catch (error) {
                    console.error('Upload error:', error);
                    // Fallback to WebP base64 so save-website.php can process it
                    const webpB64 = canvas.toDataURL('image/webp', 0.88);
                    const b64Input = _cropTarget.querySelector('.slide-base64');
                    if (b64Input) b64Input.value = webpB64;
                    const pathInput = _cropTarget.querySelector('.slide-img-path');
                    if (pathInput) pathInput.value = '';

                    let wrap = _cropTarget.querySelector('.img-preview-wrap');
                    if (!wrap) {
                        wrap = document.createElement('div');
                        wrap.className = 'img-preview-wrap mb-2';
                        wrap.innerHTML = '<img class="img-thumbnail" style="max-height:80px;object-fit:cover">';
                        if (pathInput) pathInput.before(wrap);
                    }
                    wrap.style.display = '';
                    const previewImg = wrap.querySelector('img');
                    if (previewImg) {
                        previewImg.src = webpB64;
                        previewImg.style.display = '';
                    }

                    const cropModal = bootstrap.Modal.getInstance(document.getElementById('cropModal'));
                    if (cropModal) cropModal.hide();
                } finally {
                    if (cropBtn) {
                        cropBtn.disabled = false;
                        cropBtn.innerHTML = originalBtnHtml;
                    }
                }
            }, 'image/webp', 0.88);
        }
    </script>

    <!-- Hero Banner Directory Picker Modal -->
    <div class="modal fade" id="heroBannerModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-folder2-open me-2"></i>Select Hero Banner Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Images from <code>assets/img/hero/</code> — click any image to select it.</p>
                    <div class="row g-2" id="heroBannerGrid">
                        <!-- Images loaded via AJAX -->
                    </div>
                </div>
                <div class="modal-footer">
                    <small class="text-muted me-auto">To add more images, upload them directly to <code>assets/img/hero/</code></small>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Crop Modal -->
    <div class="modal fade" id="cropModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cropModalTitle"><i class="bi bi-crop me-2"></i>Crop Hero Banner (16:9)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 bg-dark text-center">
                    <div style="height: 60vh; width: 100%; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <img id="cropImageToCrop" style="max-width: 100%; max-height: 100%; display:block; margin: 0 auto;" alt="Crop Image">
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Ratio fixed at 16:9. Image is automatically converted to WebP format.</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="btn-crop-upload" onclick="performCrop()">
                            <i class="bi bi-check-lg me-1"></i>Crop & Convert to WebP
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Chip Editor JS -->
    <script>
    (function () {
        // Map: chip-editor-id -> hidden-textarea-id
        var chipSets = [
            { editorId: 'wa_product_chips',  hiddenId: 'wa_product_options_hidden' },
            { editorId: 'wa_budget_chips',   hiddenId: 'wa_budget_options_hidden'  },
            { editorId: 'wa_color_chips',    hiddenId: 'wa_color_options_hidden'   }
        ];

        function syncToHidden(editor, hidden) {
            var chips = editor.querySelectorAll('.wa-chip');
            var values = Array.from(chips).map(function (c) {
                // text content minus the × button text
                return c.childNodes[0].textContent.trim();
            });
            hidden.value = values.join('\n');
        }

        function addChip(editor, hidden, text) {
            text = text.trim();
            if (!text) return;
            // prevent duplicates
            var existing = Array.from(editor.querySelectorAll('.wa-chip')).map(function (c) {
                return c.childNodes[0].textContent.trim().toLowerCase();
            });
            if (existing.includes(text.toLowerCase())) return;

            var isColor = editor.id === 'wa_color_chips';
            var chip = document.createElement('span');
            chip.className = 'wa-chip' + (isColor ? ' wa-chip-color' : '');
            chip.appendChild(document.createTextNode(text));
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'wa-chip-remove';
            btn.title = 'Remove';
            btn.innerHTML = '&times;';
            btn.addEventListener('click', function () {
                chip.remove();
                syncToHidden(editor, hidden);
            });
            chip.appendChild(btn);

            // Insert before the input wrapper
            var inputWrap = editor.querySelector('.wa-chip-input-wrap');
            editor.insertBefore(chip, inputWrap);
            syncToHidden(editor, hidden);
        }

        chipSets.forEach(function (set) {
            var editor = document.getElementById(set.editorId);
            var hidden = document.getElementById(set.hiddenId);
            if (!editor || !hidden) return;

            // Remove buttons on existing chips
            editor.querySelectorAll('.wa-chip-remove').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    btn.closest('.wa-chip').remove();
                    syncToHidden(editor, hidden);
                });
            });

            var input = editor.querySelector('.wa-chip-input');

            // Click anywhere on editor to focus input
            editor.addEventListener('click', function () { input.focus(); });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    var val = input.value.replace(/,$/, '').trim();
                    addChip(editor, hidden, val);
                    input.value = '';
                } else if (e.key === 'Backspace' && input.value === '') {
                    var chips = editor.querySelectorAll('.wa-chip');
                    if (chips.length) {
                        chips[chips.length - 1].remove();
                        syncToHidden(editor, hidden);
                    }
                }
            });

            // Also split on paste with commas/newlines
            input.addEventListener('paste', function (e) {
                e.preventDefault();
                var text = (e.clipboardData || window.clipboardData).getData('text');
                text.split(/[\n,]+/).forEach(function (t) { addChip(editor, hidden, t); });
                input.value = '';
            });
        });
    })();
    </script>
</body>
</html>
