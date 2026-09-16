<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'includes/permission-manager.php';
checkPageAccess();
require_once '../db_config.php';

// Fetch categories for dropdown
$categories_sql = "SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC";
$categories_result = mysqli_query($conn, $categories_sql);

// Fetch colors for dropdown
$colors_sql = "SELECT id, color_name, color_code FROM colors WHERE status = 'active' ORDER BY color_name ASC";
$colors_result = mysqli_query($conn, $colors_sql);

// Fetch sizes for dropdown
$sizes_sql = "SELECT id, size_label, description FROM sizes WHERE status = 'active' ORDER BY FIELD(size_label, 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'Free Size', 'Custom'), size_label";
$sizes_result = mysqli_query($conn, $sizes_sql);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light" id="html-root">

<head>
    <meta charset="utf-8" />
    <title>Add Product | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta content="" name="description" />
    <meta content="" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <!-- App favicon -->
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">

    <!-- Uppy CSS -->
    <link href="https://releases.transloadit.com/uppy/v3.25.0/uppy.min.css" rel="stylesheet">
    <!-- Cropper CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">

    <!-- App css -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Dark Mode State Check Script -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('silky_admin_theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
            }
        })();
    </script>
    <style>
        /* Color Selection Palette Component */
        .color-palette-card {
            display: inline-flex;
            align-items: center;
            padding: 7px 12px;
            border-radius: 8px;
            border: 1.5px solid var(--bs-border-color, #e3e6ef);
            background-color: var(--bs-card-bg, #ffffff);
            cursor: pointer;
            user-select: none;
            transition: all 0.2s ease-in-out;
            position: relative;
        }
        .color-palette-card:hover {
            border-color: #3056d3;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(48, 86, 211, 0.12);
        }
        .color-palette-card.selected {
            border-color: #3056d3;
            background-color: rgba(48, 86, 211, 0.08);
            box-shadow: 0 4px 12px rgba(48, 86, 211, 0.16);
        }
        .color-swatch-preview {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 1px solid rgba(0, 0, 0, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            flex-shrink: 0;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.15);
        }
        .color-swatch-preview .check-icon {
            color: #ffffff;
            font-size: 14px;
            font-weight: 900;
            opacity: 0;
            transform: scale(0.5);
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.9);
        }
        .color-palette-card.selected .color-swatch-preview .check-icon {
            opacity: 1;
            transform: scale(1);
        }
        .color-details {
            line-height: 1.25;
        }
        .color-title {
            font-size: 0.825rem;
            font-weight: 600;
        }
        .color-hex {
            font-size: 0.6875rem;
            font-weight: 500;
            opacity: 0.7;
            letter-spacing: 0.4px;
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
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
                            <h4 class="page-title">Add Product</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="index.php">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                                    <li class="breadcrumb-item active">Add Product</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success'];
                        unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error'];
                        unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Product Information</h4>
                            </div>
                            <div class="card-body">
                                <form id="productForm" action="save-product.php" method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="name" class="form-label">Product Name *</label>
                                                <input type="text" class="form-control" id="name" name="name" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="product_code" class="form-label">Product Code</label>
                                                <input type="text" class="form-control" id="product_code" name="product_code">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description *</label>
                                        <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="category_id" class="form-label">Category *</label>
                                                <select class="form-select" id="category_id" name="category_id" required>
                                                    <option value="">Select Category</option>
                                                    <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                                                        <option value="<?php echo $category['id']; ?>">
                                                            <?php echo htmlspecialchars($category['name']); ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="price" class="form-label">Price (₹) *</label>
                                                <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="compare_price" class="form-label">Compare at Price (₹)</label>
                                                <input type="number" class="form-control" id="compare_price" name="compare_price" min="0" step="0.01">
                                                <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Higher price for strikethrough</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="grams" class="form-label">Weight (Grams)</label>
                                                <input type="number" class="form-control" id="grams" name="grams" min="0" step="0.01">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <div class="d-flex align-items-center justify-content-between mb-2">
                                                    <label class="form-label mb-0 fw-bold">Available Colors * <span id="colorCountBadge" class="badge bg-primary-subtle text-primary ms-1 rounded-pill">0 selected</span></label>
                                                    <div class="d-flex align-items-center gap-1">
                                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="selectAllVisibleColors()">Select All</button>
                                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="clearAllColors()">Clear</button>
                                                        <button type="button" class="btn btn-xs btn-primary d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#addColorModal">
                                                            <i class="mdi mdi-plus"></i> New Hex
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="border rounded p-2 bg-light-subtle">
                                                    <div class="mb-2 position-relative">
                                                        <input type="text" class="form-control form-control-sm ps-4" id="colorSearchInput" placeholder="Search color name or hex..." oninput="filterColors(this.value)">
                                                        <i class="mdi mdi-magnify position-absolute top-50 start-0 translate-middle-y ms-2 text-muted" style="font-size: 14px;"></i>
                                                    </div>
                                                    <div class="color-palette-grid d-flex flex-wrap gap-2" id="colorPaletteGrid" style="max-height: 185px; overflow-y: auto;">
                                                        <?php while ($color = mysqli_fetch_assoc($colors_result)): 
                                                            $cCode = !empty($color['color_code']) ? $color['color_code'] : '#cccccc';
                                                            if ($cCode[0] !== '#') $cCode = '#' . $cCode;
                                                        ?>
                                                            <div class="color-palette-card" data-color-id="<?php echo $color['id']; ?>" data-name="<?php echo htmlspecialchars(strtolower($color['color_name'])); ?>" data-hex="<?php echo htmlspecialchars(strtolower($cCode)); ?>" onclick="toggleColorCard(this)">
                                                                <input type="checkbox" class="color-checkbox d-none" name="colors[]" value="<?php echo $color['id']; ?>" id="color_<?php echo $color['id']; ?>" data-name="<?php echo htmlspecialchars($color['color_name']); ?>">
                                                                <div class="color-swatch-preview" style="background-color: <?php echo htmlspecialchars($cCode); ?>;">
                                                                    <i class="mdi mdi-check check-icon"></i>
                                                                </div>
                                                                <div class="color-details ms-2">
                                                                    <div class="color-title"><?php echo htmlspecialchars($color['color_name']); ?></div>
                                                                    <div class="color-hex"><?php echo htmlspecialchars(strtoupper($cCode)); ?></div>
                                                                </div>
                                                            </div>
                                                        <?php endwhile; ?>
                                                    </div>
                                                </div>
                                                <small class="text-muted">Click swatches to select multiple colors.</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Available Sizes *</label>
                                                <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                                    <?php while ($size = mysqli_fetch_assoc($sizes_result)): ?>
                                                        <div class="form-check mb-2">
                                                            <input class="form-check-input size-checkbox" type="checkbox" name="sizes[]" value="<?php echo $size['id']; ?>" id="size_<?php echo $size['id']; ?>" data-name="<?php echo htmlspecialchars($size['size_label']); ?>">
                                                            <label class="form-check-label" for="size_<?php echo $size['id']; ?>">
                                                                <strong><?php echo htmlspecialchars($size['size_label']); ?></strong>
                                                                <?php if (!empty($size['description'])): ?>
                                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($size['description']); ?></small>
                                                                <?php endif; ?>
                                                            </label>
                                                        </div>
                                                    <?php endwhile; ?>
                                                </div>
                                                <small class="text-muted">Select at least one size</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="draft">Draft</option>
                                        </select>
                                    </div>

                                    <div class="mb-4" id="variants-section" style="display: none;">
                                        <h5 class="mb-3">Variant Pricing &amp; Stock</h5>
                                        <div class="alert alert-info py-2 fs-13">
                                            The base price above is a fallback. Specify the exact price and initial stock for each combination below.
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-sm align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Color</th>
                                                        <th>Size</th>
                                                        <th style="width:150px;">Price (₹) *</th>
                                                        <th style="width:150px;">Compare Price (₹)</th>
                                                        <th style="width:100px;">Grams</th>
                                                        <th style="width:120px;">Initial Stock *</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="variants-tbody">
                                                    <!-- Generated via JS -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Overview Highlights Section -->
                                    <?php
                                    // Fetch global highlight settings for reference/fallbacks
                                    $global_hl_res = @mysqli_query($conn, "SELECT setting_value FROM website_settings WHERE setting_key = 'product_highlights_cards'");
                                    $global_hl_cards = [];
                                    if ($global_hl_res && $r = mysqli_fetch_assoc($global_hl_res)) {
                                        $global_hl_cards = json_decode($r['setting_value'], true) ?: [];
                                    }
                                    if (empty($global_hl_cards)) {
                                        $global_hl_cards = [
                                            ['icon' => 'bi bi-gem', 'title' => 'Premium Quality', 'desc' => 'Crafted from the finest fabrics for a luxurious feel and elegant drape.'],
                                            ['icon' => 'bi bi-palette2', 'title' => 'Authentic Design', 'desc' => 'Traditional motifs blended beautifully with contemporary aesthetics.'],
                                            ['icon' => 'bi bi-shield-check', 'title' => 'Long-lasting', 'desc' => 'Woven with precision to ensure your saree lasts for generations.'],
                                            ['icon' => 'bi bi-stars', 'title' => 'Perfect Finish', 'desc' => 'Impeccable finishing and attention to detail in every single thread.']
                                        ];
                                    }
                                    ?>
                                    <div class="card border mb-4">
                                        <div class="card-header d-flex justify-content-between align-items-center py-2">
                                            <div>
                                                <h4 class="card-title mb-0 fs-15"><i class="bi bi-stars text-warning me-1"></i> Overview Highlights / Feature Cards</h4>
                                                <small class="text-muted">Cards shown in the "Why Choose Our Sarees?" section on this product's page</small>
                                            </div>
                                            <div class="form-check form-switch form-switch-success ms-3">
                                                <input class="form-check-input" type="checkbox" name="custom_highlights_enabled" value="1" id="custom_highlights_enabled" onchange="toggleCustomHighlights(this.checked)">
                                                <label class="form-check-label fw-semibold fs-13" for="custom_highlights_enabled">Customize for this product</label>
                                            </div>
                                        </div>
                                        <div class="card-body" id="custom-highlights-panel" style="display: none;">
                                            <div class="alert alert-info py-2 fs-13 d-flex justify-content-between align-items-center mb-3">
                                                <span><i class="fas fa-info-circle me-1"></i> When customized, these specific cards override the global default highlights on this product's page.</span>
                                                <button type="button" class="btn btn-sm btn-soft-primary text-nowrap ms-2" onclick="resetToGlobalDefaults()"><i class="fas fa-undo me-1"></i> Reset to Global Defaults</button>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-semibold fs-13">Custom Section Heading</label>
                                                <input type="text" class="form-control" name="custom_highlights_title" id="custom_highlights_title" value="" placeholder="Leave blank to use global heading (e.g. Why Choose Our Sarees?)">
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label class="form-label fw-semibold fs-13 mb-0">Feature Cards</label>
                                                <button type="button" class="btn btn-sm btn-soft-primary" onclick="addProductHighlightCard()">
                                                    <i class="fas fa-plus me-1"></i> Add Card
                                                </button>
                                            </div>

                                            <div class="row g-3" id="product-highlights-cards-list">
                                                <?php foreach ($global_hl_cards as $idx => $card): 
                                                    $cIcon = !empty($card['icon']) ? $card['icon'] : 'bi bi-gem';
                                                    $cTitle = $card['title'] ?? '';
                                                    $cDesc = $card['desc'] ?? '';
                                                ?>
                                                <div class="col-md-6 prod-highlight-card-item">
                                                    <div class="card border mb-0 h-100 shadow-none">
                                                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
                                                            <span class="fw-bold fs-12 text-primary">Card #<span class="pcard-num"><?php echo $idx + 1; ?></span></span>
                                                            <button type="button" class="btn btn-sm btn-soft-danger py-0 px-2" onclick="removeProductHighlightCard(this)" title="Remove"><i class="fas fa-trash font-11"></i></button>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div class="mb-2">
                                                                <label class="form-label fs-12 mb-1">Icon</label>
                                                                <div class="input-group input-group-sm mb-1">
                                                                    <span class="input-group-text icon-preview-box" style="font-size: 1.1rem; color: #97c51d; width: 40px; justify-content: center;">
                                                                        <i class="<?php echo htmlspecialchars($cIcon); ?>"></i>
                                                                    </span>
                                                                    <input type="text" class="form-control form-control-sm prod-card-icon-input" name="custom_highlight_icon[]" value="<?php echo htmlspecialchars($cIcon); ?>" oninput="updateProdCardIcon(this)" placeholder="e.g. bi bi-gem">
                                                                </div>
                                                                <div class="d-flex flex-wrap gap-1">
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="setProdCardIcon(this, 'bi bi-gem')"><i class="bi bi-gem me-1 text-success"></i>Gem</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="setProdCardIcon(this, 'bi bi-palette2')"><i class="bi bi-palette2 me-1 text-primary"></i>Palette</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="setProdCardIcon(this, 'bi bi-shield-check')"><i class="bi bi-shield-check me-1 text-success"></i>Shield</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="setProdCardIcon(this, 'bi bi-stars')"><i class="bi bi-stars me-1 text-warning"></i>Stars</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="setProdCardIcon(this, 'bi bi-award')"><i class="bi bi-award me-1 text-warning"></i>Award</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="setProdCardIcon(this, 'bi bi-truck')"><i class="bi bi-truck me-1 text-info"></i>Truck</span>
                                                                </div>
                                                            </div>
                                                            <div class="mb-2">
                                                                <label class="form-label fs-12 mb-1">Title</label>
                                                                <input type="text" class="form-control form-control-sm" name="custom_highlight_title[]" value="<?php echo htmlspecialchars($cTitle); ?>" placeholder="e.g. Premium Quality">
                                                            </div>
                                                            <div>
                                                                <label class="form-label fs-12 mb-1">Description</label>
                                                                <textarea class="form-control form-control-sm" name="custom_highlight_desc[]" rows="2" placeholder="Brief description"><?php echo htmlspecialchars($cDesc); ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="card-body py-2 text-muted fs-12 border-top" id="custom-highlights-placeholder">
                                            <i class="fas fa-check-circle me-1 text-success"></i> This product will use the <strong>global default highlights</strong> configured in <a href="edit-website.php#highlights" target="_blank" class="fw-semibold text-primary">Edit Website &rarr; Product Highlights</a>.
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <a href="products.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left me-1"></i> Back to Products
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Save Product
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Product Images (Max 6)</h4>
                            </div>
                            <div class="card-body">
                                <div id="uppy-product-images"></div>

                                <!-- Images Upload Conditions -->
                                <div class="alert alert-info mt-3" role="alert">
                                    <h6 class="alert-heading mb-2"><i class="fas fa-info-circle me-1"></i> Images Upload Guidelines</h6>
                                    <ul class="mb-0 small">
                                        <li><strong>Maximum Images:</strong> 6 images per product</li>
                                        <li><strong>Maximum Size:</strong> 1.5 MB per image</li>
                                        <li><strong>Allowed Formats:</strong> JPG, JPEG, PNG, GIF, WEBP</li>
                                        <li><strong>Aspect Ratio:</strong> 9:16 (Portrait - recommended for products)</li>
                                        <li><strong>Recommended Dimensions:</strong> 900x1600 pixels</li>
                                        <li><strong>Quality:</strong> High resolution for best display</li>
                                        <li><strong>Note:</strong> Images will be automatically cropped to maintain 9:16 ratio</li>
                                    </ul>
                                </div>

                                <!-- Images Preview -->
                                <div id="images-preview" class="mt-3" style="display: none;">
                                    <h6>Uploaded Images:</h6>
                                    <div id="preview-container" class="row g-2"></div>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header">
                                <h4 class="card-title">Product Video (Optional)</h4>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="youtube_url" class="form-label">YouTube Video URL</label>
                                    <input type="text" class="form-control" id="youtube_url" placeholder="Paste YouTube video URL here">
                                    <div class="form-text">Enter a YouTube video URL (e.g., https://www.youtube.com/watch?v=VIDEO_ID, https://youtu.be/VIDEO_ID, or https://youtube.com/shorts/VIDEO_ID)</div>
                                    <div id="youtube-error" class="text-danger mt-2" style="display: none;"></div>
                                </div>

                                <!-- YouTube Video Guidelines -->
                                <div class="alert alert-warning" role="alert">
                                    <h6 class="alert-heading mb-2"><i class="fas fa-video me-1"></i> YouTube Video Guidelines</h6>
                                    <ul class="mb-0 small">
                                        <li><strong>Upload Video:</strong> First upload your video to YouTube</li>
                                        <li><strong>Set as Unlisted:</strong> Make sure the video is set as "Unlisted" for privacy</li>
                                        <li><strong>Copy URL:</strong> Copy the YouTube video URL from your browser</li>
                                        <li><strong>Paste Here:</strong> Paste the complete YouTube URL in the field above</li>
                                        <li><strong>Supported Formats:</strong> Standard YouTube URLs, Shorts, and shortened youtu.be links</li>
                                        <li><strong>Note:</strong> Only valid YouTube URLs will be accepted</li>
                                    </ul>
                                </div>

                                <!-- YouTube Video Preview -->
                                <div id="youtube-preview" class="mt-3" style="display: none;">
                                    <div class="position-relative">
                                        <div class="ratio ratio-16x9">
                                            <iframe id="youtube-iframe" src="" title="YouTube video preview" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2" onclick="removeYouTubeVideo()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <p class="text-success mt-2 mb-0"><i class="fas fa-check me-1"></i> YouTube video linked successfully</p>
                                    <p class="text-muted small mb-0">Video ID: <span id="youtube-video-id"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cropper Modal -->
    <div class="modal fade" id="cropperModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crop Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="img-container">
                        <img id="crop-image" src="" alt="Crop">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="cropAndUpload()">Crop & Upload</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden inputs for images and YouTube video ID -->
    <input type="hidden" id="images_data" name="images" form="productForm">
    <input type="hidden" id="youtube_video_id" name="youtube_video_id" form="productForm">
    <!-- Javascript -->
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/theme-manager.js"></script>

    <!-- Uppy JS -->
    <script src="https://releases.transloadit.com/uppy/v3.25.0/uppy.min.js"></script>
    <!-- Cropper JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>

    <script>
        let cropper;
        let selectedFile;
        let currentImageIndex = 0;
        let uploadedImages = [];
        let imagesToCrop = []; // Queue for images to crop
        let currentCroppingIndex = 0;

        // Initialize Uppy for Images
        const uppyImages = new Uppy.Uppy({
                restrictions: {
                    maxFileSize: 1.5 * 1024 * 1024, // 1.5MB
                    allowedFileTypes: ['image/*'],
                    maxNumberOfFiles: 6
                }
            })
            .use(Uppy.Dashboard, {
                target: '#uppy-product-images',
                inline: true,
                width: '100%',
                height: 200,
                proudlyDisplayPoweredByUppy: false,
                showProgressDetails: true,
                note: 'Portrait images (9:16 ratio), max 6 images, up to 1.5MB each'
            });

        // Global defaults for highlight cards reset
        const globalDefaultCards = <?php echo json_encode($global_hl_cards); ?>;

        function toggleCustomHighlights(checked) {
            const panel = document.getElementById('custom-highlights-panel');
            const placeholder = document.getElementById('custom-highlights-placeholder');
            if (panel) panel.style.display = checked ? 'block' : 'none';
            if (placeholder) placeholder.style.display = checked ? 'none' : 'block';
        }

        function updateProdCardIcon(input) {
            const wrap = input.closest('.mb-2');
            const preview = wrap.querySelector('.icon-preview-box i');
            if (preview) {
                let cls = input.value.trim();
                if (cls.startsWith('bi-') && !cls.startsWith('bi bi-')) cls = 'bi ' + cls;
                preview.className = cls || 'bi bi-gem';
            }
        }

        function setProdCardIcon(badge, iconClass) {
            const wrap = badge.closest('.mb-2');
            const input = wrap.querySelector('.prod-card-icon-input');
            const preview = wrap.querySelector('.icon-preview-box i');
            if (input) input.value = iconClass;
            if (preview) preview.className = iconClass;
        }

        function reindexProductCards() {
            const items = document.querySelectorAll('#product-highlights-cards-list .prod-highlight-card-item');
            items.forEach((item, idx) => {
                const num = item.querySelector('.pcard-num');
                if (num) num.textContent = idx + 1;
            });
        }

        function removeProductHighlightCard(btn) {
            const item = btn.closest('.prod-highlight-card-item');
            if (item) {
                item.remove();
                reindexProductCards();
            }
        }

        function addProductHighlightCard(icon = 'bi bi-stars', title = '', desc = '') {
            const list = document.getElementById('product-highlights-cards-list');
            if (!list) return;
            const count = list.querySelectorAll('.prod-highlight-card-item').length + 1;
            const html = `
                <div class="col-md-6 prod-highlight-card-item">
                    <div class="card border mb-0 h-100 shadow-none">
                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
                            <span class="fw-bold fs-12 text-primary">Card #<span class="pcard-num">${count}</span></span>
                            <button type="button" class="btn btn-sm btn-soft-danger py-0 px-2" onclick="removeProductHighlightCard(this)" title="Remove"><i class="fas fa-trash font-11"></i></button>
                        </div>
                        <div class="card-body p-3">
                            <div class="mb-2">
                                <label class="form-label fs-12 mb-1">Icon</label>
                                <div class="input-group input-group-sm mb-1">
                                    <span class="input-group-text icon-preview-box" style="font-size: 1.1rem; color: #97c51d; width: 40px; justify-content: center;">
                                        <i class="${icon}"></i>
                                    </span>
                                    <input type="text" class="form-control form-control-sm prod-card-icon-input" name="custom_highlight_icon[]" value="${icon}" oninput="updateProdCardIcon(this)" placeholder="e.g. bi bi-gem">
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="setProdCardIcon(this, 'bi bi-gem')"><i class="bi bi-gem me-1 text-success"></i>Gem</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="setProdCardIcon(this, 'bi bi-palette2')"><i class="bi bi-palette2 me-1 text-primary"></i>Palette</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="setProdCardIcon(this, 'bi bi-shield-check')"><i class="bi bi-shield-check me-1 text-success"></i>Shield</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="setProdCardIcon(this, 'bi bi-stars')"><i class="bi bi-stars me-1 text-warning"></i>Stars</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="setProdCardIcon(this, 'bi bi-award')"><i class="bi bi-award me-1 text-warning"></i>Award</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1" style="cursor: pointer;" onclick="setProdCardIcon(this, 'bi bi-truck')"><i class="bi bi-truck me-1 text-info"></i>Truck</span>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label fs-12 mb-1">Title</label>
                                <input type="text" class="form-control form-control-sm" name="custom_highlight_title[]" value="${title.replace(/"/g, '&quot;')}" placeholder="e.g. Premium Quality">
                            </div>
                            <div>
                                <label class="form-label fs-12 mb-1">Description</label>
                                <textarea class="form-control form-control-sm" name="custom_highlight_desc[]" rows="2" placeholder="Brief description">${desc}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            list.insertAdjacentHTML('beforeend', html);
        }

        function resetToGlobalDefaults() {
            if (!confirm('Replace current cards with the global default highlights?')) return;
            const list = document.getElementById('product-highlights-cards-list');
            if (!list) return;
            list.innerHTML = '';
            document.getElementById('custom_highlights_title').value = '';
            globalDefaultCards.forEach(c => {
                addProductHighlightCard(c.icon || 'bi bi-gem', c.title || '', c.desc || '');
            });
        }

        // YouTube video handling
        let youtubeVideoId = null;

        // Extract YouTube video ID from URL
        function extractYouTubeVideoId(url) {
            if (!url) return null;

            // Remove whitespace
            url = url.trim();

            // Regular expressions for different YouTube URL formats
            const patterns = [
                /(?:youtube\.com\/watch\?v=)([a-zA-Z0-9_-]{11})/, // https://www.youtube.com/watch?v=VIDEO_ID
                /(?:youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/, // https://www.youtube.com/embed/VIDEO_ID
                /(?:youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/, // https://youtube.com/shorts/VIDEO_ID
                /(?:youtu\.be\/)([a-zA-Z0-9_-]{11})/, // https://youtu.be/VIDEO_ID
                /(?:youtube\.com\/v\/)([a-zA-Z0-9_-]{11})/ // https://www.youtube.com/v/VIDEO_ID
            ];

            for (let pattern of patterns) {
                const match = url.match(pattern);
                if (match && match[1]) {
                    return match[1];
                }
            }

            return null;
        }

        // Validate and process YouTube URL
        document.getElementById('youtube_url').addEventListener('blur', function() {
            const url = this.value.trim();
            const errorDiv = document.getElementById('youtube-error');
            const previewDiv = document.getElementById('youtube-preview');

            if (!url) {
                // Empty URL is acceptable (video is optional)
                errorDiv.style.display = 'none';
                previewDiv.style.display = 'none';
                youtubeVideoId = null;
                document.getElementById('youtube_video_id').value = '';
                return;
            }

            // Extract video ID
            const videoId = extractYouTubeVideoId(url);

            if (!videoId) {
                // Invalid YouTube URL
                errorDiv.textContent = 'Invalid YouTube URL. Please enter a valid YouTube video URL.';
                errorDiv.style.display = 'block';
                previewDiv.style.display = 'none';
                youtubeVideoId = null;
                document.getElementById('youtube_video_id').value = '';
            } else {
                // Valid YouTube URL
                errorDiv.style.display = 'none';
                youtubeVideoId = videoId;
                document.getElementById('youtube_video_id').value = videoId;
                document.getElementById('youtube-video-id').textContent = videoId;
                document.getElementById('youtube-iframe').src = 'https://www.youtube.com/embed/' + videoId;
                previewDiv.style.display = 'block';
            }
        });

        // Handle multiple image uploads
        uppyImages.on('file-added', (file) => {
            if (uploadedImages.length + imagesToCrop.length >= 6) {
                alert('Maximum 6 images allowed!');
                uppyImages.removeFile(file.id);
                return;
            }

            // Add to queue for cropping
            imagesToCrop.push(file);
            uppyImages.removeFile(file.id);

            // Start cropping if not already in progress
            if (imagesToCrop.length === 1) {
                startCropping();
            }
        });

        function startCropping() {
            if (imagesToCrop.length === 0) return;

            selectedFile = imagesToCrop[0];
            currentCroppingIndex = 0;

            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('crop-image').src = e.target.result;

                // Update modal title to show progress
                const modalTitle = document.querySelector('#cropperModal .modal-title');
                modalTitle.textContent = `Crop Image ${uploadedImages.length + 1} of ${uploadedImages.length + imagesToCrop.length}`;

                bootstrap.Modal.getOrCreateInstance(document.getElementById('cropperModal')).show();

                // Initialize cropper with 9:16 aspect ratio
                if (cropper) {
                    cropper.destroy();
                }
                cropper = new Cropper(document.getElementById('crop-image'), {
                    aspectRatio: 9 / 16,
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
            };
            reader.readAsDataURL(selectedFile.data);
        }



        function cropAndUpload() {
            if (!cropper) return;

            cropper.getCroppedCanvas({
                width: 900,
                height: 1600,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            }).toBlob((blob) => {
                const formData = new FormData();
                formData.append('image', blob, selectedFile.name);
                formData.append('type', 'product');

                fetch('upload-image.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            uploadedImages.push(data.path);
                            updateImagesDisplay();
                            updateImagesInput();

                            // Remove current image from queue
                            imagesToCrop.shift();

                            const cropModal = bootstrap.Modal.getInstance(document.getElementById('cropperModal')) || bootstrap.Modal.getOrCreateInstance(document.getElementById('cropperModal'));
                            if (cropModal) cropModal.hide();

                            // Process next image if available
                            setTimeout(() => {
                                if (imagesToCrop.length > 0) {
                                    startCropping();
                                }
                            }, 500);

                        } else {
                            alert('Upload failed: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Upload failed: ' + error.message);
                    });
            }, 'image/jpeg', 0.9);
        }

        function updateImagesDisplay() {
            const container = document.getElementById('preview-container');
            container.innerHTML = '';

            uploadedImages.forEach((imagePath, index) => {
                const col = document.createElement('div');
                col.className = 'col-6 col-md-4';
                col.innerHTML = `
                    <div class="position-relative">
                        <img src="${imagePath.replace('./', '/')}" alt="Preview ${index + 1}" class="img-fluid rounded" style="height: 120px; width: 100%; object-fit: cover;">
                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" onclick="removeImage(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                        ${index === 0 ? '<span class="badge bg-primary position-absolute bottom-0 start-0 m-1">Main</span>' : ''}
                    </div>
                `;
                container.appendChild(col);
            });

            document.getElementById('images-preview').style.display = uploadedImages.length > 0 ? 'block' : 'none';
        }

        function updateImagesInput() {
            document.getElementById('images_data').value = JSON.stringify(uploadedImages);
        }

        function removeImage(index) {
            if (confirm('Are you sure you want to remove this image?')) {
                uploadedImages.splice(index, 1);
                updateImagesDisplay();
                updateImagesInput();
            }
        }

        function removeYouTubeVideo() {
            if (confirm('Are you sure you want to remove this YouTube video?')) {
                youtubeVideoId = null;
                document.getElementById('youtube_url').value = '';
                document.getElementById('youtube_video_id').value = '';
                document.getElementById('youtube-preview').style.display = 'none';
                document.getElementById('youtube-error').style.display = 'none';
            }
        }

        // Form validation - allow less than 6 images
        document.getElementById('productForm').addEventListener('submit', function(e) {
            console.log('Form submitting...');
            console.log('uploadedImages:', uploadedImages);
            console.log('images_data value:', document.getElementById('images_data').value);

            if (uploadedImages.length === 0) {
                e.preventDefault();
                alert('Please upload at least 1 product image!');
                return false;
            }

            // Check if any images are still in crop queue
            if (imagesToCrop.length > 0) {
                e.preventDefault();
                alert('Please finish cropping all images before submitting!');
                return false;
            }

            // Validate colors selection
            const selectedColors = document.querySelectorAll('input[name="colors[]"]:checked');
            if (selectedColors.length === 0) {
                e.preventDefault();
                alert('Please select at least one color!');
                return false;
            }

            // Validate sizes selection
            const selectedSizes = document.querySelectorAll('input[name="sizes[]"]:checked');
            if (selectedSizes.length === 0) {
                e.preventDefault();
                alert('Please select at least one size!');
                return false;
            }

            // Make sure images_data is updated before submit
            updateImagesInput();
            console.log('Final images_data value:', document.getElementById('images_data').value);
        });

        // Handle modal close - clear crop queue if user cancels
        document.getElementById('cropperModal').addEventListener('hidden.bs.modal', function() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        });
        
        // Variant generation logic
        const sizeCheckboxes = document.querySelectorAll('.size-checkbox');
        const tbody = document.getElementById('variants-tbody');
        const variantsSection = document.getElementById('variants-section');
        const basePriceInput = document.getElementById('price');
        
        let variantData = {}; 

        function toggleColorCard(cardEl) {
            const cb = cardEl.querySelector('.color-checkbox');
            if (!cb) return;
            cb.checked = !cb.checked;
            if (cb.checked) {
                cardEl.classList.add('selected');
            } else {
                cardEl.classList.remove('selected');
            }
            updateColorBadge();
            updateVariantsTable();
        }

        function updateColorBadge() {
            const checkedCount = document.querySelectorAll('.color-checkbox:checked').length;
            const badge = document.getElementById('colorCountBadge');
            if (badge) {
                badge.textContent = checkedCount + ' selected';
            }
        }

        function filterColors(query) {
            const q = query.toLowerCase().trim();
            document.querySelectorAll('.color-palette-card').forEach(card => {
                const name = (card.dataset.name || '').toLowerCase();
                const hex = (card.dataset.hex || '').toLowerCase();
                if (!q || name.includes(q) || hex.includes(q)) {
                    card.style.display = 'inline-flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function selectAllVisibleColors() {
            document.querySelectorAll('.color-palette-card').forEach(card => {
                if (card.style.display !== 'none') {
                    const cb = card.querySelector('.color-checkbox');
                    if (cb) {
                        cb.checked = true;
                        card.classList.add('selected');
                    }
                }
            });
            updateColorBadge();
            updateVariantsTable();
        }

        function clearAllColors() {
            document.querySelectorAll('.color-palette-card').forEach(card => {
                const cb = card.querySelector('.color-checkbox');
                if (cb) {
                    cb.checked = false;
                    card.classList.remove('selected');
                }
            });
            updateColorBadge();
            updateVariantsTable();
        }

        function updateVariantsTable() {
            const colorCheckboxes = document.querySelectorAll('.color-checkbox');
            const selectedColors = Array.from(colorCheckboxes).filter(c => c.checked).map(c => ({id: c.value, name: c.dataset.name}));
            const selectedSizes = Array.from(sizeCheckboxes).filter(s => s.checked).map(s => ({id: s.value, name: s.dataset.name}));
            
            if (selectedColors.length === 0 || selectedSizes.length === 0) {
                variantsSection.style.display = 'none';
                tbody.innerHTML = '';
                basePriceInput.readOnly = false;
                return;
            }

            // Save current input values
            document.querySelectorAll('.variant-price').forEach(input => {
                if(!variantData[input.dataset.key]) variantData[input.dataset.key] = {};
                variantData[input.dataset.key].price = input.value;
            });
            document.querySelectorAll('.variant-compare-price').forEach(input => {
                if(!variantData[input.dataset.key]) variantData[input.dataset.key] = {};
                variantData[input.dataset.key].compare_price = input.value;
            });
            document.querySelectorAll('.variant-grams').forEach(input => {
                if(!variantData[input.dataset.key]) variantData[input.dataset.key] = {};
                variantData[input.dataset.key].grams = input.value;
            });
            document.querySelectorAll('.variant-stock').forEach(input => {
                if(!variantData[input.dataset.key]) variantData[input.dataset.key] = {};
                variantData[input.dataset.key].stock = input.value;
            });

            variantsSection.style.display = 'block';
            basePriceInput.readOnly = true;
            tbody.innerHTML = '';

            const basePrice = basePriceInput.value || 0;
            const baseComparePrice = document.getElementById('compare_price').value || '';
            const baseGrams = document.getElementById('grams').value || '';

            selectedColors.forEach(color => {
                selectedSizes.forEach(size => {
                    const key = `${color.id}_${size.id}`;
                    const savedPrice = (variantData[key] && variantData[key].price !== undefined) ? variantData[key].price : basePrice;
                    const savedComparePrice = (variantData[key] && variantData[key].compare_price !== undefined) ? variantData[key].compare_price : baseComparePrice;
                    const savedGrams = (variantData[key] && variantData[key].grams !== undefined) ? variantData[key].grams : baseGrams;
                    const savedStock = (variantData[key] && variantData[key].stock !== undefined) ? variantData[key].stock : 0;
                    
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${color.name}</td>
                        <td>${size.name}</td>
                        <td>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm variant-price" 
                                name="variant_price[${color.id}][${size.id}]" data-key="${key}" value="${savedPrice}" required>
                        </td>
                        <td>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm variant-compare-price" 
                                name="variant_compare_price[${color.id}][${size.id}]" data-key="${key}" value="${savedComparePrice}">
                        </td>
                        <td>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm variant-grams" 
                                name="variant_grams[${color.id}][${size.id}]" data-key="${key}" value="${savedGrams}">
                        </td>
                        <td>
                            <input type="number" min="0" class="form-control form-control-sm variant-stock" 
                                name="variant_stock[${color.id}][${size.id}]" data-key="${key}" value="${savedStock}" required>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            });

            // Add event listeners to new variant price inputs
            document.querySelectorAll('.variant-price').forEach(input => {
                input.addEventListener('input', updateBasePriceFromVariants);
            });
            updateBasePriceFromVariants();
        }

        function updateBasePriceFromVariants() {
            let minPrice = Infinity;
            let hasPrice = false;
            document.querySelectorAll('.variant-price').forEach(input => {
                let val = parseFloat(input.value);
                if (!isNaN(val)) {
                    minPrice = Math.min(minPrice, val);
                    hasPrice = true;
                }
            });
            if (hasPrice && minPrice !== Infinity) {
                basePriceInput.value = minPrice;
            }
        }

        sizeCheckboxes.forEach(s => s.addEventListener('change', updateVariantsTable));
        basePriceInput.addEventListener('change', function() {
            document.querySelectorAll('.variant-price').forEach(input => {
                if (!input.value || input.value == 0) {
                    input.value = this.value;
                }
            });
            if(document.querySelectorAll('.variant-price').length > 0) {
                updateBasePriceFromVariants();
            }
        });

        document.getElementById('productForm').addEventListener('submit', function(e) {
            let isValid = true;
            let errorMessage = '';

            const mainPrice = parseFloat(document.getElementById('price').value) || 0;
            const mainComparePriceStr = document.getElementById('compare_price').value;
            if (mainComparePriceStr !== '') {
                const mainComparePrice = parseFloat(mainComparePriceStr);
                if (mainComparePrice <= mainPrice) {
                    isValid = false;
                    errorMessage += 'Main Compare Price must be greater than Main Price.\n';
                }
            }

            document.querySelectorAll('#variants-tbody tr').forEach(tr => {
                const priceInput = tr.querySelector('.variant-price');
                const cpInput = tr.querySelector('.variant-compare-price');
                if (priceInput && cpInput && cpInput.value !== '') {
                    const vp = parseFloat(priceInput.value) || 0;
                    const vcp = parseFloat(cpInput.value);
                    if (vcp <= vp) {
                        isValid = false;
                        errorMessage += 'Variant Compare Price must be greater than Variant Price.\n';
                    }
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert(errorMessage.trim());
            }
        });
    </script>

    <!-- Modal for adding custom color with hex code -->
    <div class="modal fade" id="addColorModal" tabindex="-1" aria-labelledby="addColorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2" id="addColorModalLabel">
                        <i class="mdi mdi-palette-outline text-primary"></i> Add Custom Color
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_color_name" class="form-label fw-medium">Color Name *</label>
                        <input type="text" class="form-control" id="new_color_name" placeholder="e.g. Royal Blue, Crimson, Mint Green" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_color_hex" class="form-label fw-medium">Color Hex Code *</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color p-1" id="new_color_picker" value="#3056D3" title="Choose color" style="max-width: 50px; height: 38px; cursor: pointer;" oninput="syncColorPickerToHex()" onchange="syncColorPickerToHex()">
                            <input type="text" class="form-control" id="new_color_hex" value="#3056D3" placeholder="#HEXCODE" pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$" oninput="syncHexToColorPicker()" onchange="syncHexToColorPicker()" required>
                        </div>
                        <small class="text-muted">Use color picker wheel or enter a custom hex code.</small>
                    </div>
                    <div class="p-3 rounded border bg-light-subtle d-flex align-items-center gap-3 mt-3">
                        <div id="new_color_preview" class="rounded-circle border" style="width: 36px; height: 36px; background-color: #3056D3; box-shadow: 0 2px 5px rgba(0,0,0,0.15);"></div>
                        <div>
                            <div class="fw-bold fs-13">Preview Palette Swatch</div>
                            <div class="text-muted fs-12">This is how your color will appear in the selection list.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveNewColorBtn">Save &amp; Select</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function syncColorPickerToHex() {
            const picker = document.getElementById('new_color_picker');
            const hex = document.getElementById('new_color_hex');
            const preview = document.getElementById('new_color_preview');
            if (!picker || !hex) return;
            const val = picker.value.toUpperCase();
            hex.value = val;
            if (preview) {
                preview.style.backgroundColor = val;
            }
        }

        function syncHexToColorPicker() {
            const picker = document.getElementById('new_color_picker');
            const hex = document.getElementById('new_color_hex');
            const preview = document.getElementById('new_color_preview');
            if (!picker || !hex) return;
            let val = hex.value.trim();
            if (val && !val.startsWith('#')) {
                val = '#' + val;
                hex.value = val;
            }
            if (/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(val)) {
                picker.value = val;
                if (preview) {
                    preview.style.backgroundColor = val;
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const addColorModalEl = document.getElementById('addColorModal');
            if (addColorModalEl) {
                addColorModalEl.addEventListener('shown.bs.modal', function() {
                    syncColorPickerToHex();
                    const nameInp = document.getElementById('new_color_name');
                    if (nameInp) nameInp.focus();
                });
            }

            document.getElementById('saveNewColorBtn').addEventListener('click', function() {
                const nameInput = document.getElementById('new_color_name');
                const hexInput = document.getElementById('new_color_hex');
                const colorPicker = document.getElementById('new_color_picker');
                const colorName = nameInput ? nameInput.value.trim() : '';
                
                let colorCode = hexInput ? hexInput.value.trim() : '';
                if (!colorCode || colorCode === '#') {
                    colorCode = colorPicker ? colorPicker.value : '#3056D3';
                }
                if (colorCode && !colorCode.startsWith('#')) {
                    colorCode = '#' + colorCode;
                }

                if (!colorName) {
                    alert('Please enter a color name.');
                    return;
                }

                const formData = new FormData();
                formData.append('color_name', colorName);
                formData.append('color_code', colorCode);

                fetch('add-color-ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const color = data.color;
                        const grid = document.getElementById('colorPaletteGrid');
                        
                        let existingCard = grid.querySelector(`.color-palette-card[data-color-id="${color.id}"]`);
                        if (existingCard) {
                            const cb = existingCard.querySelector('.color-checkbox');
                            cb.checked = true;
                            existingCard.classList.add('selected');
                        } else {
                            const card = document.createElement('div');
                            card.className = 'color-palette-card selected';
                            card.dataset.colorId = color.id;
                            card.dataset.name = color.color_name.toLowerCase();
                            card.dataset.hex = color.color_code.toLowerCase();
                            card.setAttribute('onclick', 'toggleColorCard(this)');
                            card.innerHTML = `
                                <input type="checkbox" class="color-checkbox d-none" name="colors[]" value="${color.id}" id="color_${color.id}" data-name="${escapeHtml(color.color_name)}" checked>
                                <div class="color-swatch-preview" style="background-color: ${escapeHtml(color.color_code)};">
                                    <i class="mdi mdi-check check-icon"></i>
                                </div>
                                <div class="color-details ms-2">
                                    <div class="color-title">${escapeHtml(color.color_name)}</div>
                                    <div class="color-hex">${escapeHtml(color.color_code.toUpperCase())}</div>
                                </div>
                            `;
                            grid.appendChild(card);
                        }

                        const bsModal = bootstrap.Modal.getInstance(addColorModalEl) || bootstrap.Modal.getOrCreateInstance(addColorModalEl);
                        if (bsModal) bsModal.hide();
                        nameInput.value = '';

                        updateColorBadge();
                        updateVariantsTable();
                    } else {
                        alert(data.message || 'Error adding color.');
                    }
                })
                .catch(err => {
                    console.error('Error adding color:', err);
                    alert('An unexpected error occurred.');
                });
            });
        });

        function escapeHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    </script>
</body>

</html>