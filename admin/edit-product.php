<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'includes/permission-manager.php';
checkPageAccess();
require_once '../db_config.php';

// Auto-ensure custom_care columns exist in products table
$col_care_check = @mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'custom_care_enabled'");
if ($col_care_check && mysqli_num_rows($col_care_check) == 0) {
    @mysqli_query($conn, "ALTER TABLE `products` ADD COLUMN `custom_care_enabled` TINYINT(1) NOT NULL DEFAULT 0");
    @mysqli_query($conn, "ALTER TABLE `products` ADD COLUMN `custom_care_title` VARCHAR(255) NULL");
    @mysqli_query($conn, "ALTER TABLE `products` ADD COLUMN `custom_care_cards` TEXT NULL");
}

$product_id = intval($_GET['id'] ?? 0);
if ($product_id <= 0) {
    $_SESSION['error'] = 'Invalid product ID.';
    header('Location: products.php');
    exit;
}

// Fetch product data
$sql = "SELECT * FROM products WHERE id = '" . mysqli_real_escape_string($conn, $product_id) . "'";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = 'Product not found.';
    header('Location: products.php');
    exit;
}

$product = mysqli_fetch_assoc($result);

// Parse existing images (handle both old and new format)
$existingImages = [];
if (!empty($product['image'])) {
    // Check if it's JSON (new format) or single image path (old format)
    $imageData = json_decode($product['image'], true);
    if (is_array($imageData)) {
        $existingImages = $imageData;
    } else {
        // Old format - single image path
        $existingImages = [$product['image']];
    }
}

// Fetch categories for dropdown
$categories_sql = "SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC";
$categories_result = mysqli_query($conn, $categories_sql);

// Fetch colors for dropdown
$colors_sql = "SELECT id, color_name, color_code FROM colors WHERE status = 'active' ORDER BY color_name ASC";
$colors_result = mysqli_query($conn, $colors_sql);

// Fetch sizes for dropdown
$sizes_sql = "SELECT id, size_label, description FROM sizes WHERE status = 'active' ORDER BY FIELD(size_label, 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'Free Size', 'Custom'), size_label";
$sizes_result = mysqli_query($conn, $sizes_sql);

// Fetch selected colors for this product
$selected_colors_sql = "SELECT color_id FROM product_colors WHERE product_id = $product_id";
$selected_colors_result = mysqli_query($conn, $selected_colors_sql);
$selected_colors = [];
while ($row = mysqli_fetch_assoc($selected_colors_result)) {
    $selected_colors[] = $row['color_id'];
}

// Fetch selected sizes for this product
$selected_sizes_sql = "SELECT size_id FROM product_sizes WHERE product_id = $product_id";
$selected_sizes_result = mysqli_query($conn, $selected_sizes_sql);
$selected_sizes = [];
while ($row = mysqli_fetch_assoc($selected_sizes_result)) {
    $selected_sizes[] = $row['size_id'];
}

// Fetch existing variants
$variants_sql = "SELECT color_id, size_id, price, compare_price, grams, stock_quantity FROM product_variants WHERE product_id = $product_id";
$variants_result = mysqli_query($conn, $variants_sql);
$existing_variants = [];
while ($row = mysqli_fetch_assoc($variants_result)) {
    $existing_variants[$row['color_id'] . '_' . $row['size_id']] = [
        'price' => $row['price'],
        'compare_price' => $row['compare_price'],
        'grams' => $row['grams'],
        'stock' => $row['stock_quantity']
    ];
}

// Auto-ensure addon columns exist
$col_addon_check = @mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'addon_products'");
if ($col_addon_check && mysqli_num_rows($col_addon_check) == 0) {
    @mysqli_query($conn, "ALTER TABLE `products` ADD COLUMN `addon_title` VARCHAR(255) NULL");
    @mysqli_query($conn, "ALTER TABLE `products` ADD COLUMN `addon_products` TEXT NULL");
}

// Auto-ensure measurement fields columns exist
$col_cmf = @mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'custom_measurement_fields'");
if ($col_cmf && mysqli_num_rows($col_cmf) == 0) {
    @mysqli_query($conn, "ALTER TABLE `products` ADD COLUMN `custom_measurement_fields` TEXT NULL");
}

// Parse existing custom measurement fields
$existing_custom_meas_fields = [];
if (!empty($product['custom_measurement_fields'])) {
    $existing_custom_meas_fields = json_decode($product['custom_measurement_fields'], true) ?: [];
}

if (!function_exists('getAdminImagePath')) {
    function getAdminImagePath($path) {
        if (empty($path)) return 'assets/images/products/default.png';
        $p = trim($path);
        if (strpos($p, 'http') === 0 || strpos($p, 'data:') === 0) return $p;
        $clean = ltrim($p, './');
        if (strpos($clean, 'uploads/') === 0) {
            return '../' . $clean;
        }
        if (strpos($clean, 'assets/') === 0 && file_exists(__DIR__ . '/' . $clean)) {
            return $clean;
        }
        return '../' . $clean;
    }
}

// Fetch all other active products for addon selection
$all_catalog_products = [];
$catalog_sql = "SELECT id, name, price, image FROM products WHERE status = 'active' AND id != $product_id ORDER BY name ASC";
$catalog_res = mysqli_query($conn, $catalog_sql);
if ($catalog_res) {
    while ($cp = mysqli_fetch_assoc($catalog_res)) {
        $cp_img = 'assets/images/products/default.png';
        if (!empty($cp['image'])) {
            $dec = json_decode($cp['image'], true);
            $cp_img = (is_array($dec) && !empty($dec)) ? $dec[0] : trim(explode(',', $cp['image'])[0]);
        }
        $cp['resolved_image'] = $cp_img;
        $all_catalog_products[] = $cp;
    }
}

// Parse existing saved addons
$existing_addons = [];
if (!empty($product['addon_products'])) {
    $existing_addons = json_decode($product['addon_products'], true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light" id="html-root">

<head>
    <meta charset="utf-8" />
    <title>Edit Product | Silky Admin</title>
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
        (function () {
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
    <script>
        // Variant generation logic
        document.addEventListener('DOMContentLoaded', function() {
            const sizeCheckboxes = document.querySelectorAll('.size-checkbox');
            const tbody = document.getElementById('variants-tbody');
            const variantsSection = document.getElementById('variants-section');
            const basePriceInput = document.getElementById('price');
            
            let variantData = <?php echo json_encode($existing_variants); ?>; 

            function updateVariantsTable() {
                const colorCheckboxes = document.querySelectorAll('.color-checkbox');
                const selectedColors = Array.from(colorCheckboxes).filter(c => c.checked).map(c => ({id: c.value, name: c.dataset.name}));
                const selectedSizes = Array.from(sizeCheckboxes).filter(s => s.checked).map(s => ({id: s.value, name: s.dataset.name, isCustom: s.dataset.isCustom === '1' || s.dataset.name === 'Custom'}));
                
                const customNotice = document.getElementById('custom-size-admin-notice');
                if (customNotice) {
                    const hasCustom = selectedSizes.some(s => s.isCustom);
                    customNotice.style.display = hasCustom ? 'block' : 'none';
                }

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
            window.updateVariantsTable = updateVariantsTable;

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

            document.querySelectorAll('.color-checkbox').forEach(c => c.addEventListener('change', updateVariantsTable));
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

            // Initialize table on load
            updateVariantsTable();

            // If arrived from stocks.php with #variants-section, scroll to it smoothly
            if (window.location.hash === '#variants-section') {
                setTimeout(() => {
                    const vs = document.getElementById('variants-section');
                    if (vs) {
                        vs.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }, 300);
            }
        });
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
                            <h4 class="page-title">Edit Product</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="index.php">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                                    <li class="breadcrumb-item active">Edit Product</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
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
                                <form id="productForm" action="save-updated-product.php" method="POST">
                                    <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="name" class="form-label">Product Name *</label>
                                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="product_code" class="form-label">Product Code</label>
                                                <input type="text" class="form-control" id="product_code" name="product_code" value="<?php echo htmlspecialchars($product['product_code'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description *</label>
                                        <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="category_id" class="form-label">Category *</label>
                                                <select class="form-select" id="category_id" name="category_id" required>
                                                    <option value="">Select Category</option>
                                                    <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                                                        <option value="<?php echo $category['id']; ?>" <?php echo ($product['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($category['name']); ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="price" class="form-label">Price (₹) *</label>
                                                <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" value="<?php echo $product['price']; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="compare_price" class="form-label">Compare Price (₹)</label>
                                                <input type="number" class="form-control" id="compare_price" name="compare_price" min="0" step="0.01" value="<?php echo isset($product['compare_price']) ? $product['compare_price'] : ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label for="grams" class="form-label">Weight (Grams)</label>
                                                <input type="number" class="form-control" id="grams" name="grams" min="0" step="0.01" value="<?php echo isset($product['grams']) ? $product['grams'] : ''; ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <div class="d-flex align-items-center justify-content-between mb-2">
                                                    <label class="form-label mb-0 fw-bold">Available Colors * <span id="colorCountBadge" class="badge bg-primary-subtle text-primary ms-1 rounded-pill"><?php echo count($selected_colors); ?> selected</span></label>
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
                                                            $isSelected = in_array($color['id'], $selected_colors);
                                                            $cCode = !empty($color['color_code']) ? $color['color_code'] : '#cccccc';
                                                            if ($cCode[0] !== '#') $cCode = '#' . $cCode;
                                                        ?>
                                                            <div class="color-palette-card <?php echo $isSelected ? 'selected' : ''; ?>" data-color-id="<?php echo $color['id']; ?>" data-name="<?php echo htmlspecialchars(strtolower($color['color_name'])); ?>" data-hex="<?php echo htmlspecialchars(strtolower($cCode)); ?>" onclick="toggleColorCard(this)">
                                                                <input type="checkbox" class="color-checkbox d-none" name="colors[]" value="<?php echo $color['id']; ?>" id="color_<?php echo $color['id']; ?>" data-name="<?php echo htmlspecialchars($color['color_name']); ?>" <?php echo $isSelected ? 'checked' : ''; ?>>
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
                                                    <?php while ($size = mysqli_fetch_assoc($sizes_result)): 
                                                        $isCustom = (strtolower(trim($size['size_label'])) === 'custom');
                                                    ?>
                                                        <div class="form-check mb-2 <?php echo $isCustom ? 'p-2 rounded bg-light border border-primary-subtle' : ''; ?>">
                                                            <input class="form-check-input size-checkbox" type="checkbox" name="sizes[]" value="<?php echo $size['id']; ?>" id="size_<?php echo $size['id']; ?>" data-name="<?php echo htmlspecialchars($size['size_label']); ?>" <?php echo $isCustom ? 'data-is-custom="1"' : ''; ?> <?php echo in_array($size['id'], $selected_sizes) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label w-100" for="size_<?php echo $size['id']; ?>">
                                                                <strong><?php echo htmlspecialchars($size['size_label']); ?></strong>
                                                                <?php if ($isCustom): ?>
                                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1"><i class="fas fa-ruler-combined me-1"></i>Custom Tailoring</span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($size['description'])): ?>
                                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($size['description']); ?></small>
                                                                <?php elseif ($isCustom): ?>
                                                                    <small class="text-muted d-block">Prompts customers to submit body measurements in a modal on the website.</small>
                                                                <?php endif; ?>
                                                            </label>
                                                        </div>
                                                    <?php endwhile; ?>
                                                </div>
                                                <!-- Custom Measurement Fields Config Panel -->
                                                <div id="custom-size-admin-notice" class="mt-2" style="<?php
                                                    // Check if Custom size is currently selected
                                                    $custom_selected = false;
                                                    $tmpSizes = mysqli_query($conn, "SELECT s.size_label FROM product_sizes ps JOIN sizes s ON ps.size_id = s.id WHERE ps.product_id = $product_id");
                                                    if ($tmpSizes) { while ($ts = mysqli_fetch_assoc($tmpSizes)) { if (strtolower(trim($ts['size_label'])) === 'custom') { $custom_selected = true; break; } } }
                                                    echo $custom_selected ? '' : 'display:none;';
                                                ?>">
                                                    <div class="card border border-primary shadow-sm mb-0">
                                                        <div class="card-header py-2 bg-primary text-white d-flex align-items-center justify-content-between flex-wrap gap-2">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <i class="fas fa-ruler-combined"></i>
                                                                <span class="fw-bold fs-13">Custom Size Measurements <span class="badge bg-warning text-dark ms-1">Preset Required</span></span>
                                                            </div>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <label class="form-label text-white mb-0 fs-12 fw-semibold" for="cust_meas_preset">Preset *:</label>
                                                                <select id="cust_meas_preset" class="form-select form-select-sm" style="max-width:210px;" onchange="loadMeasurementPreset('cust-meas-fields-list', this.value)">
                                                                    <option value="">-- Choose Preset * --</option>
                                                                    <option value="indowestern">Indo-Western</option>
                                                                    <option value="lehenga">Lehenga</option>
                                                                    <option value="saree">Saree (Ready to Wear)</option>
                                                                    <option value="dresses">Dresses</option>
                                                                    <option value="blouse">Blouse / Choli</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="card-body py-2 px-3 bg-light-subtle">
                                                            <small class="text-muted d-block mb-2">
                                                                <i class="fas fa-info-circle text-primary me-1"></i>Select a preset above to load body measurement fields for this custom size garment (all in <strong>Inches</strong>). Customers will fill these in the measurements modal on the website.
                                                            </small>
                                                            <div id="cust-meas-fields-list" class="mb-2">
                                                                <?php foreach ($existing_custom_meas_fields as $mf): ?>
                                                                <div class="d-flex align-items-center gap-2 mb-1 meas-field-row">
                                                                    <input type="text" class="form-control form-control-sm meas-field-input" value="<?php echo htmlspecialchars($mf); ?>" placeholder="e.g. Chest" style="max-width:280px;">
                                                                    <span class="badge bg-secondary-subtle text-secondary border fs-11 px-2" style="white-space:nowrap;">in</span>
                                                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removeMeasurementField(this)" title="Remove"><i class="fas fa-trash font-11"></i></button>
                                                                </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="addMeasurementField('cust-meas-fields-list')">
                                                                <i class="fas fa-plus me-1"></i> Add Measurement Field
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <small class="text-muted">Select at least one size</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="active" <?php echo ($product['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo ($product['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                <option value="draft" <?php echo ($product['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3 d-flex flex-column justify-content-center">
                                            <label class="form-label mb-2">Best Seller</label>
                                            <div class="form-check form-switch form-switch-warning">
                                                <input class="form-check-input" type="checkbox" name="is_bestseller" value="1" id="is_bestseller" <?php echo (!empty($product['is_bestseller']) && $product['is_bestseller'] == 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label fw-semibold fs-13" for="is_bestseller"><i class="fas fa-star text-warning me-1"></i>Mark as Best Seller</label>
                                            </div>
                                            <small class="text-muted">Featured in the Best Sellers section on the website.</small>
                                        </div>
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
                                                        <th style="width:120px;">Stock *</th>
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

                                    $product_has_custom_hl = !empty($product['custom_highlights_enabled']);
                                    $product_hl_title = $product['custom_highlights_title'] ?? '';
                                    $product_hl_cards = [];
                                    if (!empty($product['custom_highlights_cards'])) {
                                        $product_hl_cards = json_decode($product['custom_highlights_cards'], true) ?: [];
                                    }
                                    if (empty($product_hl_cards)) {
                                        $product_hl_cards = $global_hl_cards;
                                    }
                                    ?>
                                    <div class="card border mb-4">
                                        <div class="card-header d-flex justify-content-between align-items-center py-2">
                                            <div>
                                                <h4 class="card-title mb-0 fs-15"><i class="bi bi-stars text-warning me-1"></i> Overview Highlights / Feature Cards</h4>
                                                <small class="text-muted">Cards shown in the "Why Choose Our Sarees?" section on this product's page</small>
                                            </div>
                                            <div class="form-check form-switch form-switch-success ms-3">
                                                <input class="form-check-input" type="checkbox" name="custom_highlights_enabled" value="1" id="custom_highlights_enabled" <?php echo $product_has_custom_hl ? 'checked' : ''; ?> onchange="toggleCustomHighlights(this.checked)">
                                                <label class="form-check-label fw-semibold fs-13" for="custom_highlights_enabled">Customize for this product</label>
                                            </div>
                                        </div>
                                        <div class="card-body" id="custom-highlights-panel" style="display: <?php echo $product_has_custom_hl ? 'block' : 'none'; ?>;">
                                            <div class="alert alert-info py-2 fs-13 d-flex justify-content-between align-items-center mb-3">
                                                <span><i class="fas fa-info-circle me-1"></i> When customized, these specific cards override the global default highlights on this product's page.</span>
                                                <button type="button" class="btn btn-sm btn-soft-primary text-nowrap ms-2" onclick="resetToGlobalDefaults()"><i class="fas fa-undo me-1"></i> Reset to Global Defaults</button>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-semibold fs-13">Custom Section Heading</label>
                                                <input type="text" class="form-control" name="custom_highlights_title" id="custom_highlights_title" value="<?php echo htmlspecialchars($product_hl_title); ?>" placeholder="Leave blank to use global heading (e.g. Why Choose Our Sarees?)">
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label class="form-label fw-semibold fs-13 mb-0">Feature Cards</label>
                                                <button type="button" class="btn btn-sm btn-soft-primary" onclick="addProductHighlightCard()">
                                                    <i class="fas fa-plus me-1"></i> Add Card
                                                </button>
                                            </div>

                                            <div class="row g-3" id="product-highlights-cards-list">
                                                <?php foreach ($product_hl_cards as $idx => $card): 
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
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCardIcon(this, 'bi bi-gem')"><i class="bi bi-gem me-1 text-success"></i>Gem</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCardIcon(this, 'bi bi-palette2')"><i class="bi bi-palette2 me-1 text-primary"></i>Palette</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCardIcon(this, 'bi bi-shield-check')"><i class="bi bi-shield-check me-1 text-success"></i>Shield</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCardIcon(this, 'bi bi-stars')"><i class="bi bi-stars me-1 text-warning"></i>Stars</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCardIcon(this, 'bi bi-award')"><i class="bi bi-award me-1 text-warning"></i>Award</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCardIcon(this, 'bi bi-truck')"><i class="bi bi-truck me-1 text-info"></i>Truck</span>
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
                                        <div class="card-body py-2 text-muted fs-12 border-top" id="custom-highlights-placeholder" style="display: <?php echo $product_has_custom_hl ? 'none' : 'block'; ?>;">
                                            <i class="fas fa-check-circle me-1 text-success"></i> This product uses the <strong>global default highlights</strong> configured in <a href="edit-website.php#highlights" target="_blank" class="fw-semibold text-primary">Edit Website &rarr; Product Highlights</a>.
                                        </div>
                                    </div>

                                    <!-- Care Instructions Section -->
                                    <?php
                                    // Fetch global care settings for reference/fallbacks
                                    $global_care_res = @mysqli_query($conn, "SELECT setting_value FROM website_settings WHERE setting_key = 'product_care_cards'");
                                    $global_care_cards = [];
                                    if ($global_care_res && $r = mysqli_fetch_assoc($global_care_res)) {
                                        $global_care_cards = json_decode($r['setting_value'], true) ?: [];
                                    }
                                    if (empty($global_care_cards)) {
                                        $global_care_cards = [
                                            ['icon' => 'bi bi-droplet-half', 'color' => '#0dcaf0', 'title' => 'Washing', 'desc' => 'Dry clean only for best results'],
                                            ['icon' => 'bi bi-brightness-high', 'color' => '#ffc107', 'title' => 'Drying', 'desc' => 'Avoid drying in direct sunlight'],
                                            ['icon' => 'bi bi-archive', 'color' => '#0e2187', 'title' => 'Storage', 'desc' => 'Store in a cool, dry place'],
                                            ['icon' => 'bi bi-thermometer-half', 'color' => '#dc3545', 'title' => 'Ironing', 'desc' => 'Iron on reverse side on low heat']
                                        ];
                                    }

                                    $product_has_custom_care = !empty($product['custom_care_enabled']);
                                    $product_care_title = $product['custom_care_title'] ?? '';
                                    $product_care_cards = [];
                                    if (!empty($product['custom_care_cards'])) {
                                        $product_care_cards = json_decode($product['custom_care_cards'], true) ?: [];
                                    }
                                    if (empty($product_care_cards)) {
                                        $product_care_cards = $global_care_cards;
                                    }
                                    ?>
                                    <div class="card border mb-4">
                                        <div class="card-header d-flex justify-content-between align-items-center py-2">
                                            <div>
                                                <h4 class="card-title mb-0 fs-15"><i class="bi bi-droplet-half text-info me-1"></i> Specifications - Care Instructions Cards</h4>
                                                <small class="text-muted">Cards shown in the "Care Instructions" section within the Specifications tab on this product's page</small>
                                            </div>
                                            <div class="form-check form-switch form-switch-success ms-3">
                                                <input class="form-check-input" type="checkbox" name="custom_care_enabled" value="1" id="custom_care_enabled" <?php echo $product_has_custom_care ? 'checked' : ''; ?> onchange="toggleCustomCare(this.checked)">
                                                <label class="form-check-label fw-semibold fs-13" for="custom_care_enabled">Customize for this product</label>
                                            </div>
                                        </div>
                                        <div class="card-body" id="custom-care-panel" style="display: <?php echo $product_has_custom_care ? 'block' : 'none'; ?>;">
                                            <div class="alert alert-info py-2 fs-13 d-flex justify-content-between align-items-center mb-3">
                                                <span><i class="fas fa-info-circle me-1"></i> When customized, these specific care cards override the global default care instructions on this product's page.</span>
                                                <button type="button" class="btn btn-sm btn-soft-primary text-nowrap ms-2" onclick="resetCareToGlobalDefaults()"><i class="fas fa-undo me-1"></i> Reset to Global Defaults</button>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-semibold fs-13">Custom Section Heading</label>
                                                <input type="text" class="form-control" name="custom_care_heading" id="custom_care_heading" value="<?php echo htmlspecialchars($product_care_title); ?>" placeholder="Leave blank to use global heading (e.g. Care Instructions)">
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label class="form-label fw-semibold fs-13 mb-0">Care Instruction Cards</label>
                                                <button type="button" class="btn btn-sm btn-soft-primary" onclick="addProductCareCard()">
                                                    <i class="fas fa-plus me-1"></i> Add Card
                                                </button>
                                            </div>

                                            <div class="row g-3" id="product-care-cards-list">
                                                <?php foreach ($product_care_cards as $idx => $card): 
                                                    $cIcon = !empty($card['icon']) ? $card['icon'] : 'bi bi-droplet-half';
                                                    $cColor = !empty($card['color']) ? $card['color'] : '#0dcaf0';
                                                    $cTitle = $card['title'] ?? '';
                                                    $cDesc = $card['desc'] ?? '';
                                                ?>
                                                <div class="col-md-6 prod-care-card-item">
                                                    <div class="card border mb-0 h-100 shadow-none">
                                                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
                                                            <span class="fw-bold fs-12 text-primary">Card #<span class="care-pcard-num"><?php echo $idx + 1; ?></span></span>
                                                            <button type="button" class="btn btn-sm btn-soft-danger py-0 px-2" onclick="removeProductCareCard(this)" title="Remove"><i class="fas fa-trash font-11"></i></button>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div class="mb-2">
                                                                <label class="form-label fs-12 mb-1">Icon &amp; Color</label>
                                                                <div class="input-group input-group-sm mb-1">
                                                                    <span class="input-group-text prod-care-icon-preview-box" style="font-size: 1.1rem; color: <?php echo htmlspecialchars($cColor); ?>; width: 40px; justify-content: center;">
                                                                        <i class="<?php echo htmlspecialchars($cIcon); ?>"></i>
                                                                    </span>
                                                                    <input type="text" class="form-control form-control-sm prod-care-card-icon-input" name="custom_care_card_icon[]" value="<?php echo htmlspecialchars($cIcon); ?>" oninput="updateProdCareIcon(this)" placeholder="e.g. bi bi-droplet-half">
                                                                    <input type="color" class="form-control form-control-color prod-care-card-color-input" name="custom_care_card_color[]" value="<?php echo htmlspecialchars($cColor); ?>" onchange="updateProdCareColor(this)" title="Choose color" style="max-width: 40px; padding: 2px;">
                                                                </div>
                                                                <div class="d-flex flex-wrap gap-1">
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCareIcon(this, 'bi bi-droplet-half', '#0dcaf0')"><i class="bi bi-droplet-half me-1" style="color:#0dcaf0;"></i>Washing</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCareIcon(this, 'bi bi-brightness-high', '#ffc107')"><i class="bi bi-brightness-high me-1" style="color:#ffc107;"></i>Drying</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCareIcon(this, 'bi bi-archive', '#0e2187')"><i class="bi bi-archive me-1" style="color:#0e2187;"></i>Storage</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCareIcon(this, 'bi bi-thermometer-half', '#dc3545')"><i class="bi bi-thermometer-half me-1" style="color:#dc3545;"></i>Ironing</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCareIcon(this, 'bi bi-shield-check', '#198754')"><i class="bi bi-shield-check me-1 text-success"></i>Care</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCareIcon(this, 'bi bi-wind', '#6c757d')"><i class="bi bi-wind me-1 text-secondary"></i>Air</span>
                                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCareIcon(this, 'bi bi-stars', '#ffc107')"><i class="bi bi-stars me-1 text-warning"></i>Sparkle</span>
                                                                </div>
                                                            </div>
                                                            <div class="mb-2">
                                                                <label class="form-label fs-12 mb-1">Title</label>
                                                                <input type="text" class="form-control form-control-sm" name="custom_care_card_title[]" value="<?php echo htmlspecialchars($cTitle); ?>" placeholder="e.g. Washing">
                                                            </div>
                                                            <div>
                                                                <label class="form-label fs-12 mb-1">Description</label>
                                                                <textarea class="form-control form-control-sm" name="custom_care_card_desc[]" rows="2" placeholder="Brief care instructions"><?php echo htmlspecialchars($cDesc); ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="card-body py-2 text-muted fs-12 border-top" id="custom-care-placeholder" style="display: <?php echo $product_has_custom_care ? 'none' : 'block'; ?>;">
                                            <i class="fas fa-check-circle me-1 text-success"></i> This product uses the <strong>global default care instructions</strong> configured in <a href="edit-website.php#care" target="_blank" class="fw-semibold text-primary">Edit Website &rarr; Care Instructions</a>.
                                        </div>
                                    </div>

                                    <!-- Add-on Products Section -->
                                    <div class="card border mb-4">
                                        <div class="card-header py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                            <div>
                                                <h4 class="card-title mb-0 fs-15"><i class="fas fa-puzzle-piece text-primary me-1"></i> Add-on Products (Frequently Added Together)</h4>
                                                <small class="text-muted">Add catalog products or create custom add-ons with images. Enable measurement collection per add-on.</small>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold fs-13">Section Heading</label>
                                                <input type="text" class="form-control" name="addon_title" id="addon_title" value="<?php echo htmlspecialchars($product['addon_title'] ?? ''); ?>" placeholder="e.g. Frequently Added Together, Complete the Look, Matching Add-ons">
                                                <small class="text-muted">Leave blank to use default heading "Frequently Added Together".</small>
                                            </div>


                                            <!-- Picker row -->
                                            <div class="row g-2 align-items-end mb-2">
                                                <div class="col-md-7">
                                                    <label class="form-label fw-semibold fs-13 mb-1">Pick from Catalog</label>
                                                    <select id="addon_picker" class="form-select">
                                                        <option value="">-- Choose a product --</option>
                                                        <?php foreach ($all_catalog_products as $cp): ?>
                                                        <option value="<?php echo $cp['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($cp['name']); ?>"
                                                                data-price="<?php echo htmlspecialchars($cp['price']); ?>"
                                                                data-image="<?php echo htmlspecialchars($cp['resolved_image']); ?>">
                                                            <?php echo htmlspecialchars($cp['name']); ?> (₹<?php echo number_format($cp['price'], 2); ?>)
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="button" class="btn btn-soft-primary w-100" onclick="addSelectedAddon()">
                                                        <i class="fas fa-plus me-1"></i> Add
                                                    </button>
                                                </div>
                                                <div class="col-md-3">
                                                    <button type="button" class="btn btn-soft-success w-100" data-bs-toggle="modal" data-bs-target="#customAddonModal">
                                                        <i class="fas fa-paint-brush me-1"></i> Add Custom
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="table-responsive">
                                                <table class="table table-bordered table-sm align-middle mb-0" id="addon-products-table" style="<?php echo empty($existing_addons) ? 'display:none;' : ''; ?>">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="width:50px;" class="text-center">Image</th>
                                                            <th>Name / Type</th>
                                                            <th style="width:130px;">Price</th>
                                                            <th style="width:200px;">Add-on Price (₹)</th>
                                                            <th style="width:110px;" class="text-center">Measurement</th>
                                                            <th style="width:50px;" class="text-center">Del</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="addon-products-tbody">
                                                        <?php
                                                        $catalog_by_id = [];
                                                        foreach ($all_catalog_products as $cp) {
                                                            $catalog_by_id[$cp['id']] = $cp;
                                                        }
                                                        $ep_idx = 0;
                                                        foreach ($existing_addons as $ad):
                                                            $atype = $ad['type'] ?? (isset($ad['product_id']) ? 'catalog' : 'custom');
                                                            $needsMeas = !empty($ad['needs_measurement']) ? 'checked' : '';
                                                            $cPrice = isset($ad['custom_price']) && is_numeric($ad['custom_price']) && floatval($ad['custom_price']) > 0 ? floatval($ad['custom_price']) : '';
                                                            if ($atype === 'catalog'):
                                                                $aid = intval($ad['product_id'] ?? 0);
                                                                if (!isset($catalog_by_id[$aid])) { $ep_idx++; continue; }
                                                                $item = $catalog_by_id[$aid];
                                                        ?>
                                                        <tr data-addon-row="<?php echo $ep_idx; ?>" data-product-id="<?php echo $aid; ?>">
                                                            <td class="text-center">
                                                                <input type="hidden" name="addon_type[]" value="catalog">
                                                                <input type="hidden" name="addon_product_id[]" value="<?php echo $aid; ?>">
                                                                <input type="hidden" name="addon_custom_name[]" value="">
                                                                <input type="hidden" name="addon_custom_image_path[]" value="">
                                                                <input type="hidden" name="addon_custom_color[]" value="">
                                                                <input type="hidden" name="addon_custom_size[]" value="">
                                                                <img src="<?php echo htmlspecialchars(getAdminImagePath($item['resolved_image'])); ?>" class="rounded border" style="width:40px;height:40px;object-fit:cover;" onerror="this.onerror=null; this.src='assets/images/products/default.png'">
                                                            </td>
                                                            <td>
                                                                <strong class="fs-13"><?php echo htmlspecialchars($item['name']); ?></strong>
                                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:10px;">Catalog</span>
                                                            </td>
                                                            <td><span class="text-muted">₹<?php echo number_format($item['price'], 2); ?></span></td>
                                                            <td>
                                                                <div class="input-group input-group-sm">
                                                                    <span class="input-group-text">₹</span>
                                                                    <input type="number" step="0.01" min="0" class="form-control" name="addon_custom_price[]" value="<?php echo htmlspecialchars($cPrice); ?>" placeholder="₹<?php echo number_format($item['price'], 2); ?>">
                                                                </div>
                                                            </td>
                                                            <td class="text-center">
                                                                <div class="form-check d-flex justify-content-center mb-0">
                                                                    <input class="form-check-input" type="checkbox" name="addon_needs_measurement[]" value="<?php echo $ep_idx; ?>" id="addon_meas_<?php echo $ep_idx; ?>" <?php echo $needsMeas; ?> title="Require measurements from customer">
                                                                    <label class="form-check-label ms-1" for="addon_meas_<?php echo $ep_idx; ?>" style="font-size:11px;cursor:pointer;"><i class="fas fa-ruler-combined text-info"></i></label>
                                                                </div>
                                                            </td>
                                                            <td class="text-center">
                                                                <button type="button" class="btn btn-sm btn-soft-danger py-0 px-2" onclick="removeAddonRow(this)" title="Remove"><i class="fas fa-trash font-11"></i></button>
                                                            </td>
                                                        </tr>
                                                        <?php else:
                                                            $cName = htmlspecialchars($ad['custom_name'] ?? '');
                                                            $rawCImg = $ad['custom_image'] ?? '';
                                                            $displayCImg = getAdminImagePath($rawCImg);
                                                            $cPriceDisp = number_format(floatval($ad['custom_price'] ?? 0), 2);
                                                        ?>
                                                        <tr data-addon-row="<?php echo $ep_idx; ?>" data-product-id="">
                                                            <td class="text-center">
                                                                <input type="hidden" name="addon_type[]" value="custom">
                                                                <input type="hidden" name="addon_product_id[]" value="">
                                                                <input type="hidden" name="addon_custom_name[]" value="<?php echo $cName; ?>">
                                                                <input type="hidden" name="addon_custom_image_path[]" value="<?php echo htmlspecialchars($rawCImg); ?>">
                                                                <input type="hidden" name="addon_custom_color[]" value="<?php echo htmlspecialchars($ad['custom_color'] ?? ''); ?>">
                                                                <input type="hidden" name="addon_custom_size[]" value="<?php echo htmlspecialchars($ad['custom_size'] ?? ''); ?>">
                                                                <img src="<?php echo htmlspecialchars($displayCImg); ?>" class="rounded border" style="width:40px;height:40px;object-fit:cover;" onerror="this.onerror=null; this.src='assets/images/products/default.png'">
                                                            </td>
                                                            <td>
                                                                <strong class="fs-13"><?php echo $cName; ?></strong>
                                                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:10px;">Custom</span>
                                                                <?php if (!empty($ad['custom_color']) || !empty($ad['custom_size'])): ?>
                                                                <small class="text-muted d-block" style="font-size:10px;">
                                                                    <?php if (!empty($ad['custom_color'])) echo '🎨 ' . htmlspecialchars($ad['custom_color']); ?>
                                                                    <?php if (!empty($ad['custom_color']) && !empty($ad['custom_size'])) echo ' &nbsp;|&nbsp; '; ?>
                                                                    <?php if (!empty($ad['custom_size'])) echo '📐 ' . htmlspecialchars($ad['custom_size']); ?>
                                                                </small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><span class="text-muted">₹<?php echo $cPriceDisp; ?></span></td>
                                                            <td>
                                                                <div class="input-group input-group-sm">
                                                                    <span class="input-group-text">₹</span>
                                                                    <input type="number" step="0.01" min="0" class="form-control" name="addon_custom_price[]" value="<?php echo htmlspecialchars($cPrice !== '' ? $cPrice : $cPriceDisp); ?>" placeholder="₹<?php echo $cPriceDisp; ?>">
                                                                </div>
                                                            </td>
                                                            <td class="text-center">
                                                                <div class="form-check d-flex justify-content-center mb-0">
                                                                    <input class="form-check-input" type="checkbox" name="addon_needs_measurement[]" value="<?php echo $ep_idx; ?>" id="addon_meas_<?php echo $ep_idx; ?>" <?php echo $needsMeas; ?> title="Require measurements from customer">
                                                                    <label class="form-check-label ms-1" for="addon_meas_<?php echo $ep_idx; ?>" style="font-size:11px;cursor:pointer;"><i class="fas fa-ruler-combined text-info"></i></label>
                                                                </div>
                                                            </td>
                                                            <td class="text-center">
                                                                <button type="button" class="btn btn-sm btn-soft-danger py-0 px-2" onclick="removeAddonRow(this)" title="Remove"><i class="fas fa-trash font-11"></i></button>
                                                            </td>
                                                        </tr>
                                                        <?php endif; $ep_idx++; endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div id="addon-empty-placeholder" class="text-muted small py-3 text-center border rounded bg-light" style="<?php echo !empty($existing_addons) ? 'display:none;' : ''; ?>">
                                                <i class="fas fa-info-circle me-1"></i> No add-on products yet. Pick from catalog or click <strong>Add Custom</strong>.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <a href="products.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left me-1"></i> Back to Products
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Update Product
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

    <!-- Hidden inputs for images and YouTube video ID -->
    <input type="hidden" id="images_data" name="images" form="productForm" value="<?php echo htmlspecialchars(json_encode($existingImages)); ?>">
    <input type="hidden" id="youtube_video_id" name="youtube_video_id" form="productForm" value="<?php echo htmlspecialchars($product['youtube_video_id'] ?? ''); ?>">
    <input type="hidden" id="removed_images" name="removed_images" form="productForm" value="">
    <input type="hidden" id="custom_measurement_fields_json" name="custom_measurement_fields_json" form="productForm" value="">

    <!-- Cropper Modal -->
    <div class="modal fade" id="cropperModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cropperModalTitle">Crop Image</h5>
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

                    <div class="col-lg-4">
                        <!-- Product Images Card -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Product Images (Max 6)</h4>
                            </div>
                            <div class="card-body">
                                <!-- Current Images Display -->
                                <?php if (!empty($existingImages)): ?>
                                    <div id="existing-images" class="mb-3">
                                        <h6>Current Images:</h6>
                                        <div class="row">
                                            <?php foreach ($existingImages as $index => $imagePath): ?>
                                                <div class="col-6 mb-2">
                                                    <div class="position-relative">
                                                        <img src="<?php echo htmlspecialchars(str_replace('./', '/', $imagePath)); ?>" alt="Product Image" class="img-fluid rounded" style="height: 120px; object-fit: cover; width: 100%;">
                                                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" onclick="removeExistingImage(<?php echo $index; ?>)" style="font-size: 12px; padding: 2px 6px;">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                        <div class="position-absolute bottom-0 start-0 bg-dark text-white px-2 py-1 small" style="font-size: 10px;">
                                                            <?php echo $index + 1; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Image Upload Section -->
                                <div id="uppy-product-images"></div>
                                
                                <!-- New Images Preview -->
                                <div id="images-preview" class="mt-3" style="display: none;">
                                    <h6>New Images Added:</h6>
                                    <div id="images-container" class="row"></div>
                                </div>

                                <!-- Upload Guidelines -->
                                <div class="alert alert-info mt-3" role="alert">
                                    <h6 class="alert-heading mb-2"><i class="fas fa-info-circle me-1"></i> Image Guidelines</h6>
                                    <ul class="mb-0 small">
                                        <li><strong>Max Images:</strong> 6 total (including existing)</li>
                                        <li><strong>Size:</strong> Max 1.5 MB per image</li>
                                        <li><strong>Format:</strong> JPG, PNG, WEBP</li>
                                        <li><strong>Ratio:</strong> 9:16 (Portrait)</li>
                                        <li><strong>Dimensions:</strong> 900x1600px recommended</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Product Video Card -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h4 class="card-title">Product Video (Optional)</h4>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="youtube_url" class="form-label">YouTube Video URL</label>
                                    <input type="text" class="form-control" id="youtube_url" placeholder="Paste YouTube video URL here" value="<?php echo !empty($product['youtube_video_id']) ? 'https://www.youtube.com/watch?v=' . htmlspecialchars($product['youtube_video_id']) : ''; ?>">
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
                                <div id="youtube-preview" class="mt-3" style="display: <?php echo !empty($product['youtube_video_id']) ? 'block' : 'none'; ?>;">
                                    <div class="position-relative">
                                        <div class="ratio ratio-16x9">
                                            <iframe id="youtube-iframe" src="<?php echo !empty($product['youtube_video_id']) ? 'https://www.youtube.com/embed/' . htmlspecialchars($product['youtube_video_id']) : ''; ?>" title="YouTube video preview" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2" onclick="removeYouTubeVideo()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <p class="text-success mt-2 mb-0"><i class="fas fa-check me-1"></i> YouTube video linked successfully</p>
                                    <p class="text-muted small mb-0">Video ID: <span id="youtube-video-id"><?php echo htmlspecialchars($product['youtube_video_id'] ?? ''); ?></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Add-on Modal -->
    <div class="modal fade" id="customAddonModal" tabindex="-1" aria-labelledby="customAddonModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="customAddonModalLabel"><i class="fas fa-paint-brush text-success me-2"></i>Add Custom Add-on Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 text-center">
                            <div id="ca_image_preview" class="mb-2" style="display:none;">
                                <img id="ca_image_tag" src="" alt="Preview" class="rounded border" style="width:90px;height:160px;object-fit:cover;">
                            </div>
                            <label class="form-label fw-semibold fs-13 mb-1 d-block">Product Image</label>
                            <input type="file" id="ca_image_file" class="form-control form-control-sm" accept="image/*">
                            <small class="text-muted">Max 1.5 MB &middot; 9:16 ratio will be cropped</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold fs-13 mb-1">Product Name <span class="text-danger">*</span></label>
                            <input type="text" id="ca_name" class="form-control" placeholder="e.g. Matching Dupatta">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold fs-13 mb-1">Price (₹) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" id="ca_price" class="form-control" step="0.01" min="0" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold fs-13 mb-1">Color</label>
                            <input type="text" id="ca_color" class="form-control" placeholder="e.g. Red, Navy Blue">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold fs-13 mb-1">Size</label>
                            <input type="text" id="ca_size" class="form-control" placeholder="e.g. Free Size, S/M/L/XL">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="ca_needs_measurement" role="switch">
                                <label class="form-check-label fw-semibold fs-13" for="ca_needs_measurement">
                                    <i class="fas fa-ruler-combined text-info me-1"></i> Require customer measurements
                                </label>
                            </div>
                            <small class="text-muted">Customer will be asked for Chest, Waist, Hip, Length, Shoulder (in inches)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="addCustomAddon()">
                        <i class="fas fa-plus me-1"></i> Add to List
                    </button>
                </div>
            </div>
        </div>
    </div>

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
        let uploadedImages = <?php echo json_encode($existingImages); ?>;
        let imagesToCrop = [];
        let removedImages = [];
        let youtubeVideoId = '<?php echo $product['youtube_video_id'] ?? ''; ?>';

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

        // Care Instructions JS Functions
        const globalDefaultCareCards = <?php echo json_encode($global_care_cards); ?>;

        function toggleCustomCare(enabled) {
            const panel = document.getElementById('custom-care-panel');
            const placeholder = document.getElementById('custom-care-placeholder');
            if (panel) panel.style.display = enabled ? 'block' : 'none';
            if (placeholder) placeholder.style.display = enabled ? 'none' : 'block';
        }

        function updateProdCareIcon(input) {
            const wrap = input.closest('.mb-2');
            const preview = wrap.querySelector('.prod-care-icon-preview-box i');
            if (preview) {
                let cls = input.value.trim();
                if (cls.startsWith('bi-') && !cls.startsWith('bi bi-')) cls = 'bi ' + cls;
                preview.className = cls || 'bi bi-droplet-half';
            }
        }

        function updateProdCareColor(input) {
            const wrap = input.closest('.mb-2');
            const previewBox = wrap.querySelector('.prod-care-icon-preview-box');
            if (previewBox) {
                previewBox.style.color = input.value;
            }
        }

        function setProdCareIcon(badge, iconClass, colorHex) {
            const wrap = badge.closest('.mb-2');
            const input = wrap.querySelector('.prod-care-card-icon-input');
            const colorInput = wrap.querySelector('.prod-care-card-color-input');
            const previewBox = wrap.querySelector('.prod-care-icon-preview-box');
            const previewIcon = previewBox ? previewBox.querySelector('i') : null;
            if (input) input.value = iconClass;
            if (colorInput) colorInput.value = colorHex;
            if (previewIcon) previewIcon.className = iconClass;
            if (previewBox) previewBox.style.color = colorHex;
        }

        function reindexProductCareCards() {
            const items = document.querySelectorAll('#product-care-cards-list .prod-care-card-item');
            items.forEach((item, idx) => {
                const num = item.querySelector('.care-pcard-num');
                if (num) num.textContent = idx + 1;
            });
        }

        function removeProductCareCard(btn) {
            const item = btn.closest('.prod-care-card-item');
            if (item) {
                item.remove();
                reindexProductCareCards();
            }
        }

        function addProductCareCard(icon = 'bi bi-droplet-half', color = '#0dcaf0', title = '', desc = '') {
            const list = document.getElementById('product-care-cards-list');
            if (!list) return;
            const count = list.querySelectorAll('.prod-care-card-item').length + 1;
            const html = `
                <div class="col-md-6 prod-care-card-item">
                    <div class="card border mb-0 h-100 shadow-none">
                        <div class="card-header d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
                            <span class="fw-bold fs-12 text-primary">Card #<span class="care-pcard-num">${count}</span></span>
                            <button type="button" class="btn btn-sm btn-soft-danger py-0 px-2" onclick="removeProductCareCard(this)" title="Remove"><i class="fas fa-trash font-11"></i></button>
                        </div>
                        <div class="card-body p-3">
                            <div class="mb-2">
                                <label class="form-label fs-12 mb-1">Icon & Color</label>
                                <div class="input-group input-group-sm mb-1">
                                    <span class="input-group-text prod-care-icon-preview-box" style="font-size: 1.1rem; color: ${color}; width: 40px; justify-content: center;">
                                        <i class="${icon}"></i>
                                    </span>
                                    <input type="text" class="form-control form-control-sm prod-care-card-icon-input" name="custom_care_card_icon[]" value="${icon}" oninput="updateProdCareIcon(this)" placeholder="e.g. bi bi-droplet-half">
                                    <input type="color" class="form-control form-control-color prod-care-card-color-input" name="custom_care_card_color[]" value="${color}" onchange="updateProdCareColor(this)" title="Choose color" style="max-width: 40px; padding: 2px;">
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCareIcon(this, 'bi bi-droplet-half', '#0dcaf0')"><i class="bi bi-droplet-half me-1" style="color:#0dcaf0;"></i>Washing</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCareIcon(this, 'bi bi-brightness-high', '#ffc107')"><i class="bi bi-brightness-high me-1" style="color:#ffc107;"></i>Drying</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCareIcon(this, 'bi bi-archive', '#0e2187')"><i class="bi bi-archive me-1" style="color:#0e2187;"></i>Storage</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCareIcon(this, 'bi bi-thermometer-half', '#dc3545')"><i class="bi bi-thermometer-half me-1" style="color:#dc3545;"></i>Ironing</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCareIcon(this, 'bi bi-shield-check', '#198754')"><i class="bi bi-shield-check me-1 text-success"></i>Care</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCareIcon(this, 'bi bi-wind', '#6c757d')"><i class="bi bi-wind me-1 text-secondary"></i>Air</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 cursor-pointer" onclick="setProdCareIcon(this, 'bi bi-stars', '#ffc107')"><i class="bi bi-stars me-1 text-warning"></i>Sparkle</span>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label fs-12 mb-1">Title</label>
                                <input type="text" class="form-control form-control-sm" name="custom_care_card_title[]" value="${title.replace(/"/g, '&quot;')}" placeholder="e.g. Washing">
                            </div>
                            <div>
                                <label class="form-label fs-12 mb-1">Description</label>
                                <textarea class="form-control form-control-sm" name="custom_care_card_desc[]" rows="2" placeholder="Brief care instructions">${desc}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            list.insertAdjacentHTML('beforeend', html);
        }

        function resetCareToGlobalDefaults() {
            if (!confirm('Replace current care cards with the global default care instructions?')) return;
            const list = document.getElementById('product-care-cards-list');
            if (!list) return;
            list.innerHTML = '';
            const headingInput = document.getElementById('custom_care_heading') || document.getElementById('custom_care_title');
            if (headingInput) headingInput.value = '';
            globalDefaultCareCards.forEach(c => {
                addProductCareCard(c.icon || 'bi bi-droplet-half', c.color || '#0dcaf0', c.title || '', c.desc || '');
            });
        }

        // Add-on products handling
        let addonRowIndex = <?php echo max(count($existing_addons), 0); ?>;
        let customAddonImagePath = '';

        function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

        function resolveAdminImgJs(path) {
            if (!path) return 'assets/images/products/default.png';
            if (path.startsWith('http') || path.startsWith('data:')) return path;
            const clean = path.replace(/^\.\//, '');
            if (clean.startsWith('uploads/')) {
                return '../' + clean;
            }
            if (clean.startsWith('assets/')) {
                return clean;
            }
            return clean.startsWith('../') ? clean : '../' + clean;
        }

        function buildAddonRow(idx, type, id, name, catalogPrice, img, customPrice, needsMeasurement, color, size) {
            const priceDisplay = parseFloat(catalogPrice || 0).toFixed(2);
            const customVal    = customPrice ? parseFloat(customPrice).toFixed(2) : '';
            const badge        = type === 'custom'
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:10px;">Custom</span>'
                : '<span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:10px;">Catalog</span>';
            const measChecked  = needsMeasurement ? 'checked' : '';
            const metaLine     = (color || size)
                ? `<small class="text-muted d-block" style="font-size:10px;">${color ? '🎨 '+esc(color) : ''}${color && size ? ' &nbsp;|&nbsp; ' : ''}${size ? '📐 '+esc(size) : ''}</small>`
                : '';
            // Always emit ALL hidden fields — ensures PHP array indices align
            return `
            <tr data-addon-row="${idx}" data-product-id="${type === 'catalog' ? esc(id) : ''}">
                <td class="text-center">
                    <input type="hidden" name="addon_type[]" value="${esc(type)}">
                    <input type="hidden" name="addon_product_id[]" value="${type === 'catalog' ? esc(id) : ''}">
                    <input type="hidden" name="addon_custom_name[]" value="${type === 'custom' ? esc(name) : ''}">
                    <input type="hidden" name="addon_custom_image_path[]" value="${type === 'custom' ? esc(img) : ''}">
                    <input type="hidden" name="addon_custom_color[]" value="${esc(color||'')}"> 
                    <input type="hidden" name="addon_custom_size[]" value="${esc(size||'')}">
                    <img src="${esc(resolveAdminImgJs(img))}" class="rounded border" style="width:40px;height:40px;object-fit:cover;" onerror="this.onerror=null; this.src='assets/images/products/default.png'">
                </td>
                <td><strong class="fs-13">${esc(name)}</strong> ${badge}${metaLine}</td>
                <td><span class="text-muted">₹${priceDisplay}</span></td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">₹</span>
                        <input type="number" step="0.01" min="0" class="form-control" name="addon_custom_price[]" value="${customVal}" placeholder="₹${priceDisplay}">
                    </div>
                </td>
                <td class="text-center">
                    <div class="form-check d-flex justify-content-center mb-0">
                        <input class="form-check-input" type="checkbox" name="addon_needs_measurement[]" value="${idx}" id="addon_meas_${idx}" ${measChecked}>
                        <label class="form-check-label ms-1" for="addon_meas_${idx}" style="font-size:11px;cursor:pointer;"><i class="fas fa-ruler-combined text-info"></i></label>
                    </div>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-soft-danger py-0 px-2" onclick="removeAddonRow(this)"><i class="fas fa-trash font-11"></i></button>
                </td>
            </tr>`;
        }

        function addSelectedAddon() {
            const picker = document.getElementById('addon_picker');
            if (!picker || !picker.value) { alert('Please select a product first.'); return; }
            const opt = picker.options[picker.selectedIndex];
            const id  = opt.value;
            if (document.querySelector(`#addon-products-tbody tr[data-product-id="${id}"]`)) {
                alert('This product is already in the add-ons list.'); return;
            }
            appendAddonRow('catalog', id, opt.dataset.name, opt.dataset.price || 0, opt.dataset.image || 'assets/images/products/default.png', '', false, '', '');
            picker.value = '';
        }

        /* ---- Custom Add-on Modal Logic ---- */
        document.addEventListener('DOMContentLoaded', () => {
            const modalEl = document.getElementById('customAddonModal');
            if (!modalEl) return;
            const cropModalEl = document.getElementById('cropperModal');
            if (!cropModalEl) return;

            modalEl.addEventListener('change', function(e) {
                if (!e.target.matches('#ca_image_file')) return;
                const file = e.target.files[0];
                if (!file) return;
                if (file.size > 1.5 * 1024 * 1024) { alert('Image too large. Max 1.5 MB.'); e.target.value=''; return; }
                const reader = new FileReader();
                reader.onload = ev => {
                    document.getElementById('crop-image').src = ev.target.result;
                    document.querySelector('#cropperModal .modal-title').textContent = 'Crop Add-on Image';
                    window._addonCropCallback = true;
                    bootstrap.Modal.getInstance(modalEl)?.hide();
                    bootstrap.Modal.getOrCreateInstance(cropModalEl).show();
                    if (cropper) { cropper.destroy(); cropper = null; }
                    cropper = new Cropper(document.getElementById('crop-image'), {
                        aspectRatio: 9/16, viewMode: 1, autoCropArea: 1, responsive: true,
                        guides: true, center: true, highlight: false, cropBoxMovable: true,
                        cropBoxResizable: true, toggleDragModeOnDblclick: false
                    });
                };
                reader.readAsDataURL(file);
            });

            cropModalEl.addEventListener('hidden.bs.modal', function() {
                if (window._addonCropCallback) {
                    window._addonCropCallback = false;
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            });

            modalEl.addEventListener('hidden.bs.modal', resetCustomAddonModal);
        });

        function addCustomAddon() {
            const name  = document.getElementById('ca_name').value.trim();
            const price = document.getElementById('ca_price').value.trim();
            const color = document.getElementById('ca_color').value.trim();
            const size  = document.getElementById('ca_size').value.trim();
            const needM = document.getElementById('ca_needs_measurement').checked;
            if (!name)  { alert('Please enter a product name.'); return; }
            if (!price || isNaN(parseFloat(price))) { alert('Please enter a valid price.'); return; }
            const img = customAddonImagePath || 'assets/images/products/default.png';
            appendAddonRow('custom', '', name, price, img, price, needM, color, size);
            bootstrap.Modal.getInstance(document.getElementById('customAddonModal'))?.hide();
        }

        function resetCustomAddonModal() {
            ['ca_name','ca_price','ca_color','ca_size'].forEach(id => { const el=document.getElementById(id); if(el) el.value=''; });
            const mEl = document.getElementById('ca_needs_measurement'); if(mEl) mEl.checked=false;
            const fEl = document.getElementById('ca_image_file'); if(fEl) fEl.value='';
            const prev = document.getElementById('ca_image_preview'); if(prev) prev.style.display='none';
            customAddonImagePath = '';
        }

        function appendAddonRow(type, id, name, price, img, customPrice, needsMeasurement, color, size) {
            const tbody = document.getElementById('addon-products-tbody');
            const table = document.getElementById('addon-products-table');
            const placeholder = document.getElementById('addon-empty-placeholder');
            const idx = addonRowIndex++;
            const tmp = document.createElement('tbody');
            tmp.innerHTML = buildAddonRow(idx, type, id, name, price, img, customPrice, needsMeasurement, color, size);
            tbody.appendChild(tmp.firstElementChild);
            table.style.display = '';
            if (placeholder) placeholder.style.display = 'none';
        }

        function removeAddonRow(btn) {
            const tr = btn.closest('tr');
            if (tr) tr.remove();
            const tbody = document.getElementById('addon-products-tbody');
            const table = document.getElementById('addon-products-table');
            const placeholder = document.getElementById('addon-empty-placeholder');
            if (tbody && tbody.children.length === 0) {
                if (table) table.style.display = 'none';
                if (placeholder) placeholder.style.display = '';
            }
        }

        // YouTube video handling
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
                youtubeVideoId = '';
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
                youtubeVideoId = '';
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

        function removeYouTubeVideo() {
            if (confirm('Are you sure you want to remove this YouTube video?')) {
                youtubeVideoId = '';
                document.getElementById('youtube_url').value = '';
                document.getElementById('youtube_video_id').value = '';
                document.getElementById('youtube-preview').style.display = 'none';
                document.getElementById('youtube-error').style.display = 'none';
            }
        }

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
            note: 'Images only, up to 6 files, 1.5MB each'
        });

        // Handle image uploads
        uppyImages.on('files-added', (files) => {
            // Check total images limit (existing + new)
            const totalImages = uploadedImages.length + files.length;
            if (totalImages > 6) {
                const allowed = 6 - uploadedImages.length;
                alert(`Maximum 6 images allowed. You can add ${allowed} more images.`);
                files.forEach(file => uppyImages.removeFile(file.id));
                return;
            }

            // Add files to crop queue
            files.forEach(file => {
                imagesToCrop.push(file);
                uppyImages.removeFile(file.id);
            });

            // Start cropping process
            if (imagesToCrop.length > 0) {
                startCropping();
            }
        });

        function startCropping() {
            if (imagesToCrop.length === 0) return;

            const file = imagesToCrop[0];
            selectedFile = file;
            
            // Update modal title with progress
            const remaining = imagesToCrop.length;
            const total = uploadedImages.length + imagesToCrop.length;
            const current = total - remaining + 1;
            document.getElementById('cropperModalTitle').textContent = `Crop Image ${current} of ${total}`;

            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('crop-image').src = e.target.result;
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
            reader.readAsDataURL(file.data);
        }

        function cropAndUpload() {
            if (!cropper) return;

            const isAddon = window._addonCropCallback === true;

            cropper.getCroppedCanvas({
                width: 900,
                height: 1600,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            }).toBlob(async (blob) => {
                const formData = new FormData();
                const filename = isAddon
                    ? ('addon_' + Date.now() + '.jpg')
                    : (selectedFile && selectedFile.name ? selectedFile.name : 'product_' + Date.now() + '.jpg');
                formData.append('image', blob, filename);
                formData.append('type', isAddon ? 'addon' : 'product');

                try {
                    const response = await fetch('upload-image.php', { method: 'POST', body: formData });
                    const data = await response.json();

                    if (data.success) {
                        if (isAddon) {
                            // Addon image — update the preview in the addon modal
                            customAddonImagePath = data.path;
                            const prev = document.getElementById('ca_image_preview');
                            const img  = document.getElementById('ca_image_tag');
                            if (prev) prev.style.display = '';
                            if (img)  img.src = resolveAdminImgJs(data.path);
                        } else {
                            // Product image
                            uploadedImages.push(data.path);
                            updateImagesDisplay();
                            updateImagesInput();
                            imagesToCrop.shift();
                            setTimeout(() => { if (imagesToCrop.length > 0) startCropping(); }, 500);
                        }

                        const cropModal = bootstrap.Modal.getInstance(document.getElementById('cropperModal'))
                                       || bootstrap.Modal.getOrCreateInstance(document.getElementById('cropperModal'));
                        if (cropModal) cropModal.hide();

                    } else {
                        alert('Upload failed: ' + data.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Upload failed: ' + error.message);
                }
            }, 'image/jpeg', 0.9);
        }

        function updateImagesDisplay() {
            const container = document.getElementById('images-container');
            container.innerHTML = '';
            
            // Get existing images count to start indexing from there
            const existingCount = <?php echo count($existingImages); ?>;
            
            // Only show newly added images (not existing ones)
            const newImages = uploadedImages.slice(existingCount);
            
            newImages.forEach((imagePath, index) => {
                if (imagePath && !removedImages.includes(imagePath)) {
                    const imageDiv = document.createElement('div');
                    imageDiv.className = 'col-6 mb-2';
                    const actualIndex = existingCount + index;
                    imageDiv.innerHTML = `
                        <div class="position-relative">
                            <img src="${imagePath.replace('./', '/')}" alt="Product Image" class="img-fluid rounded" style="height: 120px; object-fit: cover; width: 100%;">
                            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" onclick="removeImage(${actualIndex})" style="font-size: 12px; padding: 2px 6px;">
                                <i class="fas fa-times"></i>
                            </button>
                            <div class="position-absolute bottom-0 start-0 bg-dark text-white px-2 py-1 small" style="font-size: 10px;">
                                ${actualIndex + 1}
                            </div>
                        </div>
                    `;
                    container.appendChild(imageDiv);
                }
            });
            
            document.getElementById('images-preview').style.display = newImages.length > 0 ? 'block' : 'none';
        }

        function updateImagesInput() {
            // Filter out removed images
            const activeImages = uploadedImages.filter(img => !removedImages.includes(img));
            document.getElementById('images_data').value = JSON.stringify(activeImages);
        }

        function removeImage(index) {
            if (confirm('Are you sure you want to remove this image?')) {
                const imagePath = uploadedImages[index];
                removedImages.push(imagePath);
                updateImagesDisplay();
                updateImagesInput();
                document.getElementById('removed_images').value = JSON.stringify(removedImages);
            }
        }

        function removeExistingImage(index) {
            if (confirm('Are you sure you want to remove this image?')) {
                const imagePath = uploadedImages[index];
                removedImages.push(imagePath);
                // Hide the image element in the existing images section
                const existingImagesContainer = document.querySelector('#existing-images .row');
                const imageElements = existingImagesContainer.children;
                if (imageElements[index]) {
                    imageElements[index].style.display = 'none';
                }
                updateImagesInput();
                document.getElementById('removed_images').value = JSON.stringify(removedImages);
            }
        }

        // Form validation
        document.getElementById('productForm').addEventListener('submit', function(e) {
            console.log('Form submitting...');
            console.log('uploadedImages:', uploadedImages);
            console.log('removedImages:', removedImages);
            
            const activeImages = uploadedImages.filter(img => !removedImages.includes(img));
            
            if (activeImages.length === 0) {
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
            
            // Validate Compare Price
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
                return false;
            }

            // Validate Custom size measurements preset & fields
            const sizeCheckboxes = document.querySelectorAll('.size-checkbox');
            const isCustomChecked = Array.from(sizeCheckboxes).some(s => s.checked && (s.dataset.isCustom === '1' || s.dataset.name === 'Custom'));
            const custFields = getMeasurementFields('cust-meas-fields-list');
            if (isCustomChecked && custFields.length === 0) {
                e.preventDefault();
                alert('Custom size is selected! Please select a Measurement Preset (e.g. Indo-Western, Lehenga, etc.) or add measurement fields for Custom size.');
                const pSelect = document.getElementById('cust_meas_preset');
                if (pSelect) {
                    pSelect.focus();
                    pSelect.classList.add('is-invalid');
                }
                return false;
            }

            // Serialize custom measurement fields into hidden input before submit
            document.getElementById('custom_measurement_fields_json').value = JSON.stringify(custFields);

            // Make sure data is updated before submit
            updateImagesInput();
        });

        // Handle modal close - clear crop queue if user cancels
        document.getElementById('cropperModal').addEventListener('hidden.bs.modal', function() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        });

        // Initialize display on page load
        updateImagesDisplay();
        updateColorBadge();
    </script>

    <script>
        // =============================================
        // MEASUREMENT FIELDS MANAGEMENT
        // =============================================
        const MEASUREMENT_PRESETS = {
            'none':        [],
            'indowestern': ['Bottom Length', 'Bottom Waist', 'Bottom Hips'],
            'lehenga':     ['Lehenga Length', 'Lehenga Waist', 'Lehenga Hips'],
            'saree':       ['Saree Waist', 'Saree Length'],
            'dresses':     ['Bottom Length', 'Bottom Waist', 'Bottom Hips'],
            'blouse':      ['Chest', 'Below Chest', 'Armhole', 'Apex Point', 'Shoulder', 'Front Deep', 'Back Deep',
                           'Sleeves Length', 'Biceps Round', 'Above Elbow Round', 'Wrist Round', 'Additional message / Remarks']
        };

        function buildMeasurementFieldRow(value) {
            const esc = String(value || '').replace(/"/g, '&quot;');
            return `<div class="d-flex align-items-center gap-2 mb-1 meas-field-row">
                <input type="text" class="form-control form-control-sm meas-field-input" value="${esc}" placeholder="e.g. Chest" style="max-width:280px;">
                <span class="badge bg-secondary-subtle text-secondary border fs-11 px-2" style="white-space:nowrap;">in</span>
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removeMeasurementField(this)" title="Remove">
                    <i class="fas fa-trash font-11"></i>
                </button>
            </div>`;
        }

        function renderMeasurementFields(containerId, fields) {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.innerHTML = '';
            fields.forEach(function(field) {
                container.insertAdjacentHTML('beforeend', buildMeasurementFieldRow(field));
            });
        }

        function loadMeasurementPreset(containerId, preset) {
            const pSelect = document.getElementById('cust_meas_preset');
            if (pSelect) pSelect.classList.remove('is-invalid');
            if (!preset || preset === 'none') return;
            const fields = MEASUREMENT_PRESETS[preset] || [];
            renderMeasurementFields(containerId, fields);
        }

        function addMeasurementField(containerId, value = '') {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.insertAdjacentHTML('beforeend', buildMeasurementFieldRow(value));
            const lastInput = container.querySelector('.meas-field-row:last-child .meas-field-input');
            if (lastInput) lastInput.focus();
        }

        function removeMeasurementField(btn) {
            const row = btn.closest('.meas-field-row');
            if (row) row.remove();
        }

        function getMeasurementFields(containerId) {
            const container = document.getElementById(containerId);
            if (!container) return [];
            const fields = [];
            container.querySelectorAll('.meas-field-input').forEach(function(inp) {
                const v = inp.value.trim();
                if (v) fields.push(v);
            });
            return fields;
        }
    </script>

    <script>
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
            if (typeof window.updateVariantsTable === 'function') {
                window.updateVariantsTable();
            }
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
            if (typeof window.updateVariantsTable === 'function') {
                window.updateVariantsTable();
            }
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
            if (typeof window.updateVariantsTable === 'function') {
                window.updateVariantsTable();
            }
        }
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
                        if (typeof window.updateVariantsTable === 'function') {
                            window.updateVariantsTable();
                        }
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