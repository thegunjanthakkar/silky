<?php
/**
 * AJAX Filter Products API
 * Handles dynamic product filtering via AJAX requests
 */

// Start session
session_start();

// Include database configuration
require_once 'db_config.php';
$base_url = defined('BASE_URL') ? BASE_URL : '/';

// Set JSON response header
header('Content-Type: application/json');

// Get filter parameters from request
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categories = isset($_GET['categories']) ? array_filter(explode(',', $_GET['categories'])) : [];
$colors = isset($_GET['colors']) ? array_filter(explode(',', $_GET['colors'])) : [];
$minPrice = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$maxPrice = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 999999999;
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'Featured';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 12;

// Calculate offset for pagination
$offset = ($page - 1) * $perPage;

// Build the base query
$sql = "SELECT DISTINCT p.*, c.name as category_name, p.youtube_video_id,
        GROUP_CONCAT(DISTINCT CONCAT(col.color_name, '|', col.color_code) SEPARATOR '~') as product_colors,
        GROUP_CONCAT(DISTINCT s.size_label SEPARATOR '~') as product_sizes,
        (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND status = 'approved') as review_count,
        (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') as avg_rating
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN product_colors pc ON p.id = pc.product_id
        LEFT JOIN colors col ON pc.color_id = col.id AND col.status = 'active'
        LEFT JOIN product_sizes ps ON p.id = ps.product_id
        LEFT JOIN sizes s ON ps.size_id = s.id AND s.status = 'active'
        WHERE p.status = 'active'";

// Count query (same filters, no pagination)
$countSql = "SELECT COUNT(DISTINCT p.id) as total
             FROM products p 
             LEFT JOIN categories c ON p.category_id = c.id 
             LEFT JOIN product_colors pc ON p.id = pc.product_id
             LEFT JOIN colors col ON pc.color_id = col.id AND col.status = 'active'
             WHERE p.status = 'active'";

$params = [];
$types = '';
$whereConditions = [];

// Search filter
if (!empty($search)) {
    $searchTerm = '%' . $search . '%';
    $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

// Category filter
if (!empty($categories)) {
    $categoryPlaceholders = implode(',', array_fill(0, count($categories), '?'));
    $whereConditions[] = "c.name IN ($categoryPlaceholders)";
    foreach ($categories as $cat) {
        $params[] = $cat;
        $types .= 's';
    }
}

// Price filter
if ($minPrice > 0 || $maxPrice < 999999999) {
    $whereConditions[] = "CAST(p.price AS DECIMAL(15,2)) BETWEEN ? AND ?";
    $params[] = $minPrice;
    $params[] = $maxPrice;
    $types .= 'dd';
}

// Color filter
if (!empty($colors)) {
    $colorPlaceholders = implode(',', array_fill(0, count($colors), '?'));
    $whereConditions[] = "LOWER(col.color_name) IN ($colorPlaceholders)";
    foreach ($colors as $color) {
        $params[] = strtolower($color);
        $types .= 's';
    }
}

// Add WHERE conditions to queries
if (!empty($whereConditions)) {
    $whereClause = " AND " . implode(' AND ', $whereConditions);
    $sql .= $whereClause;
    $countSql .= $whereClause;
}

// Add GROUP BY
$sql .= " GROUP BY p.id";

// Add ORDER BY based on sort option
switch ($sortBy) {
    case 'Price: Low to High':
        $sql .= " ORDER BY CAST(p.price AS DECIMAL(15,2)) ASC";
        break;
    case 'Price: High to Low':
        $sql .= " ORDER BY CAST(p.price AS DECIMAL(15,2)) DESC";
        break;
    case 'Newest Arrivals':
        $sql .= " ORDER BY p.created_at DESC";
        break;
    case 'Customer Rating':
        $sql .= " ORDER BY p.id DESC"; // Placeholder - implement rating when available
        break;
    default: // Featured
        $sql .= " ORDER BY p.created_at DESC";
        break;
}

// Add pagination
$sql .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

// Execute count query first
$countParams = array_slice($params, 0, -2); // Remove LIMIT and OFFSET params
$countTypes = substr($types, 0, -2);

$totalProducts = 0;
if (!empty($countParams)) {
    $countStmt = mysqli_prepare($conn, $countSql);
    if ($countStmt) {
        mysqli_stmt_bind_param($countStmt, $countTypes, ...$countParams);
        mysqli_stmt_execute($countStmt);
        $countResult = mysqli_stmt_get_result($countStmt);
        $countRow = mysqli_fetch_assoc($countResult);
        $totalProducts = $countRow['total'];
        mysqli_stmt_close($countStmt);
    }
} else {
    $countResult = mysqli_query($conn, $countSql);
    if ($countResult) {
        $countRow = mysqli_fetch_assoc($countResult);
        $totalProducts = $countRow['total'];
    }
}

// Execute main query
$products = [];
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($product = mysqli_fetch_assoc($result)) {
            $products[] = formatProduct($product);
        }
        mysqli_stmt_close($stmt);
    } else {
        // If prepared statement fails, try direct query (for debugging)
        error_log("Prepared statement failed: " . mysqli_error($conn));
        error_log("SQL: " . $sql);
    }
} else {
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($product = mysqli_fetch_assoc($result)) {
            $products[] = formatProduct($product);
        }
    }
}

/**
 * Format product data for JSON response
 */
function formatProduct($product) {
    global $base_url;
    
    // Parse product images
    $productImages = [];
    if (!empty($product['image'])) {
        $imageData = json_decode($product['image'], true);
        if (is_array($imageData)) {
            $productImages = $imageData;
        } else {
            $productImages = [$product['image']];
        }
    }
    
    // Fix image path
    $mainImage = $base_url . 'assets/img/product/placeholder.png';
    if (!empty($productImages)) {
        $possiblePaths = [
            'uploads/products/' . $productImages[0],
            'admin/uploads/' . $productImages[0],
            $productImages[0]
        ];
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $mainImage = $base_url . $path;
                break;
            }
        }
    }

    // Parse colors
    $colors = [];
    if (!empty($product['product_colors'])) {
        $colorArray = explode('~', $product['product_colors']);
        foreach ($colorArray as $colorData) {
            $colorParts = explode('|', $colorData);
            if (count($colorParts) == 2) {
                $colors[] = [
                    'name' => $colorParts[0],
                    'code' => $colorParts[1]
                ];
            }
        }
    }

    // Parse sizes
    $sizes = [];
    if (!empty($product['product_sizes'])) {
        $sizes = explode('~', $product['product_sizes']);
    }

    // Determine badge
    $badge = '';
    $badgeClass = '';
    if (strtotime($product['created_at']) > strtotime('-7 days')) {
        $badge = 'New';
        $badgeClass = 'trending-badge';
    }

    // Format price
    $priceFormatted = '';
    if (!empty($product['compare_price']) && $product['compare_price'] > $product['price']) {
        $priceFormatted = '₹' . number_format($product['price'], 2) . ' <span class="bs3d-old-price" style="text-decoration: line-through; font-size: 0.85em; color: #888; margin-left: 5px;">₹' . number_format($product['compare_price'], 2) . '</span>';
    } elseif (!empty($product['discount_price'])) {
        $priceFormatted = '₹' . number_format($product['discount_price'], 2) . ' <span class="bs3d-old-price" style="text-decoration: line-through; font-size: 0.85em; color: #888; margin-left: 5px;">₹' . number_format($product['price'], 2) . '</span>';
    } else {
        $priceFormatted = '₹' . number_format($product['price'], 2);
    }

    return [
        'id' => $product['id'],
        'slug' => $product['slug'] ?? 'product-' . $product['id'],
        'name' => $product['name'],
        'price' => floatval($product['price']),
        'price_formatted' => $priceFormatted,
        'image' => $mainImage,
        'category' => $product['category_name'] ?? 'Uncategorized',
        'colors' => $colors,
        'sizes' => $sizes,
        'badge' => $badge,
        'badge_class' => $badgeClass,
        'youtube_video_id' => $product['youtube_video_id'] ?? null,
        'avg_rating' => isset($product['avg_rating']) ? round($product['avg_rating'], 1) : 0,
        'review_count' => isset($product['review_count']) ? $product['review_count'] : 0,
        'created_at' => $product['created_at']
    ];
}

// Build response
$response = [
    'success' => true,
    'products' => $products,
    'total' => $totalProducts,
    'page' => $page,
    'per_page' => $perPage,
    'total_pages' => ceil($totalProducts / $perPage),
    'filters_applied' => [
        'search' => $search,
        'categories' => $categories,
        'colors' => $colors,
        'min_price' => $minPrice,
        'max_price' => $maxPrice,
        'sort' => $sortBy
    ]
];

// Return JSON response
echo json_encode($response);

// Close database connection
mysqli_close($conn);
