<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'includes/permission-manager.php';
checkPageAccess();
require_once '../db_config.php';

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
                                                    <?php while ($size = mysqli_fetch_assoc($sizes_result)): ?>
                                                        <div class="form-check mb-2">
                                                            <input class="form-check-input size-checkbox" type="checkbox" name="sizes[]" value="<?php echo $size['id']; ?>" id="size_<?php echo $size['id']; ?>" data-name="<?php echo htmlspecialchars($size['size_label']); ?>" <?php echo in_array($size['id'], $selected_sizes) ? 'checked' : ''; ?>>
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
                                            <option value="active" <?php echo ($product['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($product['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="draft" <?php echo ($product['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
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
                                                        <th style="width:120px;">Stock *</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="variants-tbody">
                                                    <!-- Generated via JS -->
                                                </tbody>
                                            </table>
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