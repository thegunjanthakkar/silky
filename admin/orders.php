<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Handle inline single Order Status Update (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    header('Content-Type: application/json; charset=utf-8');
    require_once 'includes/permission-manager.php';
    require_once '../db_config.php';
    checkPageAccess();

    $orderId = intval($_POST['order_id'] ?? 0);
    $newStatus = strtolower(trim($_POST['order_status'] ?? ''));

    $validStatuses = ['pending', 'placed', 'processing', 'confirmed', 'out for delivery', 'delivered', 'cancelled', 'returned'];

    if ($orderId <= 0 || !in_array($newStatus, $validStatuses, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID or status.']);
        exit;
    }

    // Fetch current status
    $stmt = mysqli_prepare($conn, "SELECT order_status FROM orders WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $orderId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $current = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    $oldStatus = strtolower(trim($current['order_status'] ?? ''));

    if ($oldStatus === $newStatus) {
        echo json_encode(['success' => true, 'message' => 'Order status already set to ' . ucwords($newStatus) . '.', 'new_status' => $newStatus]);
        exit;
    }

    $updateStmt = mysqli_prepare($conn, "UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($updateStmt, 'si', $newStatus, $orderId);
    $updated = mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    if (!$updated) {
        echo json_encode(['success' => false, 'message' => 'Failed to update order status in database.']);
        exit;
    }

    // Stock adjustments
    require_once '../includes/stock-functions.php';
    if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
        restoreOrderStock($conn, $orderId);
    } elseif ($oldStatus === 'cancelled' && $newStatus !== 'cancelled') {
        deductOrderStock($conn, $orderId);
    }

    // Send status-tailored email notification
    require_once '../includes/email-functions.php';
    $emailSent = sendOrderStatusUpdateEmail($conn, $orderId);

    $msg = 'Order #' . $orderId . ' status updated to ' . ucwords($newStatus) . '.';
    if ($emailSent) {
        $msg .= ' Status email sent to customer.';
    } else {
        $msg .= ' (Email notification could not be delivered).';
    }

    echo json_encode([
        'success' => true,
        'message' => $msg,
        'new_status' => $newStatus,
        'email_sent' => $emailSent
    ]);
    exit;
}

// Handle Add Order POST (inline modal form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_order') {
    require_once 'includes/permission-manager.php';
    require_once '../db_config.php';
    checkPageAccess();

    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $paymentStatus = strtolower(trim($_POST['payment_status'] ?? 'pending'));
    $orderStatus = strtolower(trim($_POST['order_status'] ?? 'pending'));

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $street = trim($_POST['street_address'] ?? '');
    $apartment = trim($_POST['apartment'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip = trim($_POST['zip_code'] ?? '');
    $country = trim($_POST['country'] ?? '');

    $notes = '';
    $billingAddress = '';

    $itemNames = $_POST['item_name'] ?? [];
    $itemPrices = $_POST['item_price'] ?? [];
    $itemQtys = $_POST['item_qty'] ?? [];
    $itemColors = $_POST['item_color'] ?? [];
    $itemSizes = $_POST['item_size'] ?? [];
    
    $includeShipping = isset($_POST['include_shipping']) && $_POST['include_shipping'] === '1';
    $includeTax = isset($_POST['include_tax']) && $_POST['include_tax'] === '1';

    $errors = [];
    if ($paymentMethod === '') $errors[] = 'Payment method is required.';
    if (empty($itemNames)) $errors[] = 'At least one item is required.';

    $orderTotal = 0.0;
    $items = [];
    for ($i = 0; $i < count($itemNames); $i++) {
        $name = trim($itemNames[$i]);
        $price = floatval($itemPrices[$i] ?? 0);
        $qty = intval($itemQtys[$i] ?? 0);
        $color = trim($itemColors[$i] ?? '');
        $size = trim($itemSizes[$i] ?? '');

        if ($name === '' || $qty <= 0 || $price < 0) {
            continue;
        }
        $subtotal = $price * $qty;
        $orderTotal += $subtotal;
        $variantText = '';
        if ($color !== '' && $size !== '') {
            $variantText = $color . ' | ' . $size;
        } elseif ($color !== '') {
            $variantText = $color;
        } elseif ($size !== '') {
            $variantText = $size;
        }

        $items[] = [
            'name' => $name,
            'price' => $price,
            'qty' => $qty,
            'subtotal' => $subtotal,
            'variant' => json_encode(['color' => $color, 'size' => $size]),
            'variant_info' => $variantText
        ];
    }

    if (empty($items)) $errors[] = 'At least one valid item is required.';

    if (empty($errors)) {
        // Generate 4-digit sequential numeric order number (0001, 0002, 0003...)
        $max_res = @mysqli_query($conn, "SELECT MAX(id) as max_id FROM orders");
        $next_id = 1;
        if ($max_res && $max_row = mysqli_fetch_assoc($max_res)) {
            if (!empty($max_row['max_id'])) {
                $next_id = intval($max_row['max_id']) + 1;
            }
        }
        $orderNumber = sprintf('%04d', $next_id);

        // Fetch shipping cost and tax rate from general_settings
        $shipping_cost_setting = 9.99; // Default fallback
        $tax_rate = 0.10; // Default fallback (10%)
        
        $settings_query = mysqli_query($conn, "SELECT setting_key, setting_value FROM general_settings WHERE setting_key IN ('shipping_cost', 'tax_percentage')");
        if ($settings_query) {
            while ($setting = mysqli_fetch_assoc($settings_query)) {
                if ($setting['setting_key'] === 'shipping_cost') {
                    $shipping_cost_setting = round(floatval($setting['setting_value']), 2);
                } elseif ($setting['setting_key'] === 'tax_percentage') {
                    $tax_rate = round(floatval($setting['setting_value']) / 100, 4); // Convert percentage to decimal with 4 decimal precision
                }
            }
        }

        // Calculate order breakdown (matching checkout.php structure)
        $subtotal = round($orderTotal, 2);
        $shipping_cost = ($subtotal > 0 && $includeShipping) ? $shipping_cost_setting : 0;
        $taxable_amount = $subtotal + $shipping_cost_setting; // Always use shipping cost in tax calculation
        $tax_amount = ($subtotal > 0 && $includeTax) ? round($taxable_amount * $tax_rate, 2) : 0;
        $discount_amount = 0;
        $total_amount = round($subtotal + $shipping_cost + $tax_amount - $discount_amount, 2);

        $shippingJson = json_encode([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'street_address' => $street,
            'apartment' => $apartment,
            'city' => $city,
            'state' => $state,
            'zip_code' => $zip,
            'country' => $country
        ]);

        // Escape all string values for SQL
        $orderNumber = mysqli_real_escape_string($conn, $orderNumber);
        $paymentMethod = mysqli_real_escape_string($conn, $paymentMethod);
        $paymentStatus = mysqli_real_escape_string($conn, $paymentStatus);
        $orderStatus = mysqli_real_escape_string($conn, $orderStatus);
        $shippingJson = mysqli_real_escape_string($conn, $shippingJson);
        $notes = mysqli_real_escape_string($conn, $notes);

        // Insert order (matching checkout.php structure)
        $insert_order = "INSERT INTO orders (
            user_id, order_number, total_amount, subtotal, shipping_cost,
            tax_amount, payment_method, payment_status, order_status, shipping_address, customer_notes
        ) VALUES (
            NULL, '$orderNumber', $total_amount, $subtotal, $shipping_cost,
            $tax_amount, '$paymentMethod', '$paymentStatus', '$orderStatus', '$shippingJson', '$notes'
        )";

        if (mysqli_query($conn, $insert_order)) {
            $orderId = mysqli_insert_id($conn);
            $orderNumber = sprintf('%04d', $orderId);
            @mysqli_query($conn, "UPDATE orders SET order_number = '$orderNumber' WHERE id = $orderId");
            
            // Insert order items (matching checkout.php structure)
            $items_inserted = 0;
            foreach ($items as $it) {
                $product_name = mysqli_real_escape_string($conn, $it['name']);
                $product_price = floatval($it['price']);
                $quantity = intval($it['qty']);
                $item_subtotal = floatval($it['subtotal']);
                
                // Try to find product_id by name (if exists)
                $product_id = null;
                $product_check = mysqli_query($conn, "SELECT id FROM products WHERE name = '$product_name' LIMIT 1");
                if ($product_check && mysqli_num_rows($product_check) > 0) {
                    $product_row = mysqli_fetch_assoc($product_check);
                    $product_id = intval($product_row['id']);
                }
                
                $v_info = mysqli_real_escape_string($conn, $it['variant_info'] ?? '');
                // Insert with or without product_id
                if ($product_id) {
                    $insert_item = "INSERT INTO order_items (
                        order_id, product_id, product_name, product_price, quantity, subtotal, variant_info
                    ) VALUES (
                        $orderId, $product_id, '$product_name', $product_price, $quantity, $item_subtotal, '$v_info'
                    )";
                } else {
                    $insert_item = "INSERT INTO order_items (
                        order_id, product_id, product_name, product_price, quantity, subtotal, variant_info
                    ) VALUES (
                        $orderId, NULL, '$product_name', $product_price, $quantity, $item_subtotal, '$v_info'
                    )";
                }

                if (mysqli_query($conn, $insert_item)) {
                    $items_inserted++;
                } else {
                    error_log("Failed to insert order item: " . mysqli_error($conn));
                }
            }

            // Deduct stock for new order
            require_once '../includes/stock-functions.php';
            deductOrderStock($conn, $orderId);

            $_SESSION['add_order_success'] = "Order created successfully! Order #" . htmlspecialchars($orderNumber) . " with $items_inserted items.";
        } else {
            $error_msg = mysqli_error($conn);
            error_log("Failed to create order: $error_msg | SQL: $insert_order");
            $_SESSION['add_order_error'] = 'Failed to create order: ' . htmlspecialchars($error_msg);
        }
    } else {
        $_SESSION['add_order_error'] = implode(' ', $errors);
    }

    header('Location: orders.php');
    exit;
}

// Product search for modal autocomplete (no external file)
if (isset($_GET['action']) && $_GET['action'] === 'product_search') {
    require_once 'includes/permission-manager.php';
    require_once '../db_config.php';
    checkPageAccess();

    $q = trim($_GET['q'] ?? '');
    $results = [];
    if ($q !== '') {
        $like = '%' . $q . '%';
        $stmt = mysqli_prepare($conn, "SELECT p.name, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.name LIKE ? ORDER BY p.name ASC LIMIT 10");
        mysqli_stmt_bind_param($stmt, 's', $like);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $label = $row['name'];
                if (!empty($row['category_name'])) {
                    $label .= '-' . $row['category_name'];
                }
                $results[] = $label;
            }
        }
        mysqli_stmt_close($stmt);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($results);
    exit;
}

// Product price lookup for modal (no external file)
if (isset($_GET['action']) && $_GET['action'] === 'product_price') {
    require_once 'includes/permission-manager.php';
    require_once '../db_config.php';
    checkPageAccess();

    $name = trim($_GET['name'] ?? '');
    $price = null;
    if ($name !== '') {
        $stmt = mysqli_prepare($conn, "SELECT price FROM products WHERE name = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $name);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $price = $row['price'];
        }
        mysqli_stmt_close($stmt);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['price' => $price]);
    exit;
}

// Product color/size lookup for modal (no external file)
if (isset($_GET['action']) && $_GET['action'] === 'product_variants') {
    require_once 'includes/permission-manager.php';
    require_once '../db_config.php';
    checkPageAccess();

    $name = trim($_GET['name'] ?? '');
    $colors = [];
    $sizes = [];
    if ($name !== '') {
        $stmt = mysqli_prepare($conn, "SELECT id, color, size FROM products WHERE name = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $name);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $productId = intval($row['id']);
            if (!empty($row['color'])) $colors[] = $row['color'];
            if (!empty($row['size'])) $sizes[] = $row['size'];

            $cstmt = mysqli_prepare($conn, "SELECT c.color_name FROM product_colors pc JOIN colors c ON pc.color_id = c.id WHERE pc.product_id = ? ORDER BY c.color_name ASC");
            mysqli_stmt_bind_param($cstmt, 'i', $productId);
            mysqli_stmt_execute($cstmt);
            $cres = mysqli_stmt_get_result($cstmt);
            if ($cres) {
                while ($crow = mysqli_fetch_assoc($cres)) {
                    if (!in_array($crow['color_name'], $colors, true)) $colors[] = $crow['color_name'];
                }
            }
            mysqli_stmt_close($cstmt);

            $sstmt = mysqli_prepare($conn, "SELECT s.size_label FROM product_sizes ps JOIN sizes s ON ps.size_id = s.id WHERE ps.product_id = ? ORDER BY s.size_label ASC");
            mysqli_stmt_bind_param($sstmt, 'i', $productId);
            mysqli_stmt_execute($sstmt);
            $sres = mysqli_stmt_get_result($sstmt);
            if ($sres) {
                while ($srow = mysqli_fetch_assoc($sres)) {
                    if (!in_array($srow['size_label'], $sizes, true)) $sizes[] = $srow['size_label'];
                }
            }
            mysqli_stmt_close($sstmt);
        }
        mysqli_stmt_close($stmt);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['colors' => $colors, 'sizes' => $sizes]);
    exit;
}

// Customer search for modal suggestions (no external file)
if (isset($_GET['action']) && $_GET['action'] === 'customer_search') {
    require_once 'includes/permission-manager.php';
    require_once '../db_config.php';
    checkPageAccess();

    $q = trim($_GET['q'] ?? '');
    $qDigits = preg_replace('/\D+/', '', $q);
    $results = [];
    $seen = []; // Track unique customers by email+phone
    if ($q !== '') {
        $qEsc = mysqli_real_escape_string($conn, $q);
        $qLike = '%' . $qEsc . '%';
        $where = "shipping_address LIKE '$qLike'";
        if ($qDigits !== '') {
            $qDigEsc = mysqli_real_escape_string($conn, $qDigits);
            $where .= " OR shipping_address LIKE '%$qDigEsc%'";
        }

        $sql = "SELECT id, shipping_address FROM orders WHERE $where ORDER BY id DESC LIMIT 50";
        $res = mysqli_query($conn, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $decoded = json_decode($row['shipping_address'] ?? '', true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                    continue;
                }
                
                $email = strtolower(trim((string)($decoded['email'] ?? '')));
                $phone = preg_replace('/\D+/', '', (string)($decoded['phone'] ?? ''));
                
                // Create unique key based on email or phone (whichever is available)
                $uniqueKey = '';
                if ($email !== '') {
                    $uniqueKey = 'email_' . $email;
                } elseif ($phone !== '') {
                    $uniqueKey = 'phone_' . $phone;
                } else {
                    continue; // Skip if no email or phone
                }
                
                // Skip if already added this customer
                if (isset($seen[$uniqueKey])) {
                    continue;
                }
                
                $seen[$uniqueKey] = true;
                $results[] = [
                    'order_id' => $row['id'],
                    'first_name' => (string)($decoded['first_name'] ?? ''),
                    'last_name' => (string)($decoded['last_name'] ?? ''),
                    'email' => (string)($decoded['email'] ?? ''),
                    'phone' => (string)($decoded['phone'] ?? ''),
                    'street_address' => (string)($decoded['street_address'] ?? ($decoded['address'] ?? '')),
                    'apartment' => (string)($decoded['apartment'] ?? ''),
                    'city' => (string)($decoded['city'] ?? ''),
                    'state' => (string)($decoded['state'] ?? ''),
                    'zip_code' => (string)($decoded['zip_code'] ?? ($decoded['pincode'] ?? '')),
                    'country' => (string)($decoded['country'] ?? '')
                ];
                
                // Stop after 10 unique customers
                if (count($results) >= 10) {
                    break;
                }
            }
            mysqli_free_result($res);
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($results);
    exit;
}

// Customer details from order shipping_address (no external file)
if (isset($_GET['action']) && $_GET['action'] === 'customer_details') {
    require_once 'includes/permission-manager.php';
    require_once '../db_config.php';
    checkPageAccess();

    $orderId = intval($_GET['order_id'] ?? 0);
    $payload = [
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'phone' => '',
        'street_address' => '',
        'apartment' => '',
        'city' => '',
        'state' => '',
        'zip_code' => '',
        'country' => ''
    ];

    if ($orderId > 0) {
        $sql = "SELECT shipping_address FROM orders WHERE id = $orderId LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $decoded = json_decode($row['shipping_address'] ?? '', true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $payload['first_name'] = (string)($decoded['first_name'] ?? '');
                $payload['last_name'] = (string)($decoded['last_name'] ?? '');
                $payload['email'] = (string)($decoded['email'] ?? '');
                $payload['phone'] = (string)($decoded['phone'] ?? '');
                $payload['street_address'] = (string)($decoded['street_address'] ?? ($decoded['address'] ?? ''));
                $payload['apartment'] = (string)($decoded['apartment'] ?? '');
                $payload['city'] = (string)($decoded['city'] ?? '');
                $payload['state'] = (string)($decoded['state'] ?? '');
                $payload['zip_code'] = (string)($decoded['zip_code'] ?? ($decoded['pincode'] ?? ''));
                $payload['country'] = (string)($decoded['country'] ?? '');
            }
        }
        if ($res) mysqli_free_result($res);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

// Serve modal fragment inline when requested (no external file)
if (isset($_GET['action']) && $_GET['action'] === 'modal_order' && isset($_GET['id'])) {
    require_once 'includes/permission-manager.php';
    require_once '../db_config.php';
    checkPageAccess();

    $orderId = intval($_GET['id']);
    if ($orderId <= 0) {
        echo '<div class="alert alert-warning">Invalid order ID.</div>';
        exit;
    }

    $stmt = mysqli_prepare($conn, "SELECT id, user_id, order_number, total_amount, payment_method, payment_status, order_status, shipping_address, billing_address, customer_notes, created_at, updated_at FROM orders WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $orderId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if (!$res || mysqli_num_rows($res) === 0) {
        echo '<div class="alert alert-warning">Order not found.</div>';
        exit;
    }
    $order = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    $user = null;
    if (!empty($order['user_id'])) {
        $uid = intval($order['user_id']);
        $u_stmt = mysqli_prepare($conn, "SELECT id, email, first_name, last_name, phone FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($u_stmt, 'i', $uid);
        mysqli_stmt_execute($u_stmt);
        $u_res = mysqli_stmt_get_result($u_stmt);
        if ($u_res && mysqli_num_rows($u_res) > 0) $user = mysqli_fetch_assoc($u_res);
        mysqli_stmt_close($u_stmt);
    }

    $items = [];
    $i_stmt = mysqli_prepare($conn, "SELECT oi.id, oi.product_id, oi.product_name, p.product_code, oi.product_price, oi.quantity, oi.subtotal, oi.variant_info FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ? ORDER BY oi.id ASC");
    mysqli_stmt_bind_param($i_stmt, 'i', $orderId);
    mysqli_stmt_execute($i_stmt);
    $i_res = mysqli_stmt_get_result($i_stmt);
    if ($i_res) {
        while ($it = mysqli_fetch_assoc($i_res)) $items[] = $it;
    }
    mysqli_stmt_close($i_stmt);

    // Prepare shipping address as plain text if stored as JSON/object
    $rawShip = $order['shipping_address'] ?? '';
    $shipText = '';
    if ($rawShip !== '') {
        $decoded = json_decode($rawShip, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Only include address-related fields (street, apartment, city, state, zip, country)
            $addrParts = [];
            if (!empty($decoded['street_address'])) $addrParts[] = $decoded['street_address'];
            if (!empty($decoded['apartment'])) $addrParts[] = $decoded['apartment'];
            $cityLine = trim((string)($decoded['city'] ?? ''));
            if (!empty($decoded['state'])) $cityLine .= ($cityLine !== '' ? ', ' : '') . $decoded['state'];
            if (!empty($decoded['zip_code'])) $cityLine .= ($cityLine !== '' ? ' ' : '') . $decoded['zip_code'];
            if ($cityLine !== '') $addrParts[] = $cityLine;
            if (!empty($decoded['country'])) $addrParts[] = $decoded['country'];
            $shipText = implode(', ', array_filter($addrParts, function($v){ return trim($v) !== ''; }));
        } else {
            // Fallback: strip whitespace/newlines and HTML
            $shipText = preg_replace('/\s+/', ' ', strip_tags($rawShip));
        }
    }

    $html = '<div class="container-fluid">';
    $html .= '<div class="row"><div class="col-12">';
    $modalStatuses = [
        'pending' => 'Pending',
        'placed' => 'Placed',
        'processing' => 'Processing',
        'confirmed' => 'Confirmed',
        'out for delivery' => 'Out for Delivery',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'returned' => 'Returned'
    ];
    $curOrdStatus = strtolower(trim($order['order_status'] ?? ''));

    $html .= '<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom">';
    $html .= '  <div>';
    $html .= '    <h5 class="mb-1">Order #' . htmlspecialchars($order['order_number']) . '</h5>';
    $html .= '    <small class="text-muted">Placed: ' . date('d/m/Y h:i A', strtotime($order['created_at'])) . '</small>';
    $html .= '  </div>';
    $html .= '  <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">';
    $html .= '    <label class="form-label mb-0 fw-semibold text-nowrap">Order Status:</label>';
    $html .= '    <select class="form-select form-select-sm order-status-select" data-order-id="' . $orderId . '" data-prev-status="' . htmlspecialchars($curOrdStatus) . '" style="width: auto;">';
    foreach ($modalStatuses as $stk => $stl) {
        $sel = ($curOrdStatus === $stk) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($stk) . '"' . $sel . '>' . htmlspecialchars($stl) . '</option>';
    }
    $html .= '    </select>';
    $html .= '  </div>';
    $html .= '</div>';
    $html .= '<div class="mb-3 text-muted"><strong>Payment:</strong> ' . htmlspecialchars($order['payment_method']) . ' &nbsp;|&nbsp; <strong>Payment Status:</strong> <span class="badge bg-light text-dark border">' . htmlspecialchars(ucfirst($order['payment_status'])) . '</span></div>';
    $html .= '<h6>Shipping Address</h6><p>' . htmlspecialchars($shipText !== '' ? $shipText : ($order['shipping_address'] ?? '')) . '</p>';
    if (!empty($order['billing_address'])) { $html .= '<h6>Billing Address</h6><p>' . nl2br(htmlspecialchars($order['billing_address'])) . '</p>'; }
    if (!empty($order['customer_notes'])) { $html .= '<h6>Customer Notes</h6><p>' . nl2br(htmlspecialchars($order['customer_notes'])) . '</p>'; }
    $html .= '<hr /><h6>Items</h6><div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>#</th><th>Product</th><th>Color</th><th>Size</th><th class="text-end">Unit Price</th><th class="text-end">Qty</th><th class="text-end">Subtotal</th></tr></thead><tbody>';
    if (empty($items)) {
        $html .= '<tr><td colspan="7" class="text-center text-muted">No items found for this order.</td></tr>';
    } else {
        $idx = 0;
        foreach ($items as $it) {
            $idx++;
            $variant = trim($it['variant_info'] ?? '');
            $isAddon = false;
            $isCustom = false;
            $colorText = '';
            $sizeText = '';
            $measurements = [];
            $notes = '';

            $dec = json_decode($variant, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($dec)) {
                if (!empty($dec['is_addon']) || (isset($dec['type']) && strtolower($dec['type']) === 'addon')) {
                    $isAddon = true;
                }
                $colorText = $dec['color'] ?? $dec['colour'] ?? $dec['Color'] ?? '';
                $sizeText = $dec['size'] ?? $dec['Size'] ?? '';
                if (stripos($sizeText, 'custom') !== false) {
                    $isCustom = true;
                }
                if (!empty($dec['measurements']) && is_array($dec['measurements'])) {
                    foreach ($dec['measurements'] as $mk => $mv) {
                        if ($mv !== '' && $mv !== null) {
                            if (strtolower($mk) === 'notes') {
                                $notes = (string)$mv;
                            } else {
                                $measurements[ucfirst(str_replace('_', ' ', $mk))] = (string)$mv;
                            }
                        }
                    }
                }
                if (!empty($dec['custom_measurements']) && is_array($dec['custom_measurements'])) {
                    $isCustom = true;
                    foreach ($dec['custom_measurements'] as $mk => $mv) {
                        if ($mv !== '' && $mv !== null) {
                            if (strtolower($mk) === 'notes') {
                                $notes = (string)$mv;
                            } else {
                                $measurements[ucfirst(str_replace('_', ' ', $mk))] = (string)$mv;
                            }
                        }
                    }
                }
                if (!empty($dec['notes'])) {
                    $notes = (string)$dec['notes'];
                }
            } elseif ($variant !== '') {
                if (preg_match('/\badd-?on\b/i', $variant)) {
                    $isAddon = true;
                }
                if (preg_match('/\bcustom\b/i', $variant)) {
                    $isCustom = true;
                }

                $parenContent = '';
                if (preg_match('/\(([^)]+)\)/', $variant, $pm)) {
                    $parenContent = trim($pm[1]);
                    $baseString = trim(str_replace($pm[0], '', $variant));
                } else {
                    $baseString = $variant;
                }

                if ($parenContent !== '') {
                    $measPairs = preg_split('/[•\x{2022},;|\n\r]+/u', $parenContent);
                    foreach ($measPairs as $pair) {
                        $pair = trim($pair);
                        if ($pair === '') continue;
                        if (strpos($pair, ':') !== false) {
                            list($mk, $mv) = explode(':', $pair, 2);
                            $mk = trim($mk);
                            $mv = trim($mv);
                            if (strtolower($mk) === 'notes' || strtolower($mk) === 'special instructions') {
                                $notes = $mv;
                            } else {
                                $measurements[ucfirst($mk)] = $mv;
                            }
                        } else {
                            if ($notes === '') $notes = $pair;
                            else $notes .= ', ' . $pair;
                        }
                    }
                }

                // Fallback: if no parens were found but string has custom measurements
                if (empty($measurements) && preg_match('/(bust|waist|hips?|chest|length|shoulder|sleeve|armhole|front\s*neck)\s*:/i', $variant)) {
                    $measPairs = preg_split('/[•\x{2022},;|\n\r]+/u', $variant);
                    foreach ($measPairs as $pair) {
                        $pair = trim($pair);
                        if ($pair === '') continue;
                        if (strpos($pair, ':') !== false) {
                            list($mk, $mv) = explode(':', $pair, 2);
                            $mk = trim($mk);
                            $mv = trim($mv);
                            $lowerK = strtolower($mk);
                            if ($lowerK === 'notes' || $lowerK === 'special instructions') {
                                $notes = $mv;
                            } elseif (in_array($lowerK, ['bust', 'chest', 'waist', 'hip', 'hips', 'length', 'shoulder', 'sleeve', 'armhole', 'front neck', 'fit'])) {
                                $measurements[ucfirst($mk)] = $mv;
                            }
                        }
                    }
                }

                $baseString = trim(preg_replace('/\s*\|\s*\|\s*/', ' | ', $baseString), " |");
                if (strpos($baseString, '|') !== false) {
                    $parts = array_map('trim', explode('|', $baseString));
                    $parts = array_values(array_filter($parts, function($p){ return $p !== ''; }));
                    if (count($parts) === 2) {
                        $colorText = $parts[0];
                        $sizeText = $parts[1];
                    } elseif (count($parts) >= 3) {
                        if (preg_match('/^add-?on$/i', $parts[0])) {
                            $isAddon = true;
                            $colorText = $parts[1];
                            $sizeText = $parts[2];
                        } else {
                            $colorText = $parts[0];
                            $sizeText = implode(' / ', array_slice($parts, 1));
                        }
                    } elseif (count($parts) === 1) {
                        $baseString = $parts[0];
                    }
                }

                if ($colorText === '' && $sizeText === '') {
                    if (preg_match('/^add-?on$/i', $baseString)) {
                        $isAddon = true;
                        $sizeText = '';
                        $colorText = '';
                    } elseif (preg_match('/^custom$/i', $baseString)) {
                        $isCustom = true;
                        $sizeText = 'Custom';
                    } else {
                        if (preg_match('/color\s*[:=]\s*([^,;\/|]+)/i', $baseString, $m)) $colorText = trim($m[1]);
                        if (preg_match('/size\s*[:=]\s*([^,;\/|]+)/i', $baseString, $m2)) $sizeText = trim($m2[1]);
                        
                        if ($colorText === '' && $sizeText === '') {
                            if (!$isAddon) {
                                $legacyParts = preg_split('/[\/,;]+/', $baseString);
                                if (count($legacyParts) >= 2) {
                                    $colorText = trim($legacyParts[0]);
                                    $sizeText = trim($legacyParts[1]);
                                } else {
                                    $sizeText = trim($baseString);
                                }
                            }
                        }
                    }
                }
            }

            // Product column display
            $prodHtml = '<div>';
            $prodHtml .= '<span class="fw-semibold text-dark">' . htmlspecialchars($it['product_name']) . '</span>';
            if (!empty($it['product_code'])) {
                $prodHtml .= '<br><small class="text-muted">Code: ' . htmlspecialchars($it['product_code']) . '</small>';
            }
            if ($isAddon) {
                $prodHtml .= '<div class="mt-1">';
                $prodHtml .= '<span class="badge bg-warning-subtle text-dark border border-warning-subtle px-2 py-1"><i class="fas fa-puzzle-piece text-warning me-1"></i>Add-on Product</span>';
                $prodHtml .= '</div>';
            } elseif ($isCustom) {
                $prodHtml .= '<div class="mt-1">';
                $prodHtml .= '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1"><i class="bi bi-scissors me-1"></i>Custom Made-to-Measure</span>';
                $prodHtml .= '</div>';
            }

            if (!empty($measurements) || !empty($notes)) {
                $prodHtml .= '<div class="mt-2 p-2 rounded bg-light border" style="font-size: 11.5px; max-width: 520px;">';
                $prodHtml .= '  <div class="fw-semibold text-secondary mb-1 d-flex align-items-center gap-1">';
                $prodHtml .= '    <i class="fas fa-ruler-combined text-primary"></i> <span>Measurements (Inches):</span>';
                $prodHtml .= '  </div>';
                if (!empty($measurements)) {
                    $prodHtml .= '  <div class="d-flex flex-wrap gap-1 mb-1">';
                    foreach ($measurements as $mk => $mv) {
                        $prodHtml .= '    <span class="badge bg-white text-dark border px-2 py-1 shadow-sm" style="font-size: 11px; font-weight: normal;">';
                        $prodHtml .= '      <span class="text-muted">' . htmlspecialchars($mk) . ':</span> <strong class="text-dark">' . htmlspecialchars($mv) . '</strong>';
                        $prodHtml .= '    </span>';
                    }
                    $prodHtml .= '  </div>';
                }
                if (!empty($notes)) {
                    $prodHtml .= '  <div class="text-muted mt-1 pt-1 border-top" style="font-size: 11px;">';
                    $prodHtml .= '    <i class="bi bi-chat-left-text me-1 text-info"></i><strong>Notes:</strong> ' . htmlspecialchars($notes);
                    $prodHtml .= '  </div>';
                }
                $prodHtml .= '</div>';
            }
            $prodHtml .= '</div>';

            // Color display
            $colorHtml = $colorText !== '' ? htmlspecialchars($colorText) : '<span class="text-muted">—</span>';

            // Size display
            if ($sizeText !== '') {
                if (strtolower($sizeText) === 'custom') {
                    $sizeHtml = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1"><i class="bi bi-scissors me-1"></i>Custom</span>';
                } else {
                    $sizeHtml = '<span class="badge bg-light text-dark border px-2 py-1">' . htmlspecialchars($sizeText) . '</span>';
                }
            } elseif ($isAddon) {
                $sizeHtml = '<span class="badge bg-warning-subtle text-dark border border-warning-subtle px-2 py-1"><i class="fas fa-puzzle-piece text-warning me-1"></i>Add-on</span>';
            } else {
                $sizeHtml = '<span class="text-muted">—</span>';
            }

            $html .= '<tr>';
            $html .= '<td class="align-middle">' . $idx . '</td>';
            $html .= '<td class="align-middle">' . $prodHtml . '</td>';
            $html .= '<td class="align-middle">' . $colorHtml . '</td>';
            $html .= '<td class="align-middle">' . $sizeHtml . '</td>';
            $html .= '<td class="align-middle text-end">' . htmlspecialchars(number_format((float)$it['product_price'], 2)) . '</td>';
            $html .= '<td class="align-middle text-end">' . intval($it['quantity']) . '</td>';
            $html .= '<td class="align-middle text-end">' . htmlspecialchars(number_format((float)$it['subtotal'], 2)) . '</td>';
            $html .= '</tr>';
        }
    }
    $html .= '</tbody><tfoot><tr><th colspan="6" class="text-end">Total</th><th class="text-end">' . htmlspecialchars(number_format((float)$order['total_amount'],2)) . '</th></tr></tfoot></table></div>';
    $html .= '<hr /><div><h6>Customer</h6>';
    if (!empty($user)) {
        $html .= '<p class="mb-0"><strong>' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</strong><br>' . htmlspecialchars($user['email']) . '<br>' . htmlspecialchars($user['phone'] ?? '') . '</p>';
    } else {
        $html .= '<p class="text-muted mb-0">Guest / no account</p>';
    }
    $html .= '</div></div></div></div>';
    echo $html;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-startbar="light" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <title>Orders | Silky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta content="" name="description" />
    <meta content="" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <!-- App favicon -->
    <link rel="shortcut icon" href="../assets/img/silky-jpg.jpg">

    <!-- DataTable CSS -->
    <link href="assets/libs/simple-datatables/style.css" rel="stylesheet" type="text/css" />

    <!-- App css -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />

    <!-- Dark Mode State Check Script -->
    <script>
        // Check and apply saved theme before page loads
        (function () {
            const savedTheme = localStorage.getItem('silky_admin_theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
            }
        })();
    </script>

    <style>
        /* Order Status Dropdown & Badge Styling */
        .order-status-select {
            display: inline-block;
            width: auto;
            min-width: 130px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 1.6rem 0.25rem 0.65rem;
            border-radius: 4px;
            cursor: pointer;
            line-height: 1.4;
            background-size: 10px 10px;
            background-position: right 0.5rem center;
            box-shadow: none !important;
            transition: all 0.15s ease-in-out;
        }

        .order-status-select:focus {
            box-shadow: 0 0 0 0.15rem rgba(14, 33, 135, 0.25) !important;
        }

        /* Options list in Light Mode */
        .order-status-select option {
            background-color: #ffffff !important;
            color: #1e293b !important;
            padding: 6px 12px;
        }

        /* Options list in Dark Mode (matches Dastone dark theme) */
        [data-bs-theme="dark"] .order-status-select option,
        body[data-bs-theme="dark"] .order-status-select option,
        html[data-bs-theme="dark"] .order-status-select option {
            background-color: #1a202c !important;
            color: #f1f5f9 !important;
        }

        /* Vivid text colors for badges in dark mode */
        [data-bs-theme="dark"] .order-status-select.text-success { color: #4ade80 !important; }
        [data-bs-theme="dark"] .order-status-select.text-warning { color: #facc15 !important; }
        [data-bs-theme="dark"] .order-status-select.text-primary { color: #60a5fa !important; }
        [data-bs-theme="dark"] .order-status-select.text-info    { color: #38bdf8 !important; }
        [data-bs-theme="dark"] .order-status-select.text-danger  { color: #f87171 !important; }
        [data-bs-theme="dark"] .order-status-select.text-secondary { color: #94a3b8 !important; }
    </style>
</head>

<body>
    <?php
    // Start session first
    session_start();
    
    // Check permission for this page
    require_once 'includes/permission-manager.php';
    checkPageAccess();
    
    // Fetch settings for JavaScript
    require_once '../db_config.php';
    $js_shipping_cost = 9.99; // Default fallback
    $js_tax_rate = 0.10; // Default fallback (10%)
    
    $js_settings_query = mysqli_query($conn, "SELECT setting_key, setting_value FROM general_settings WHERE setting_key IN ('shipping_cost', 'tax_percentage')");
    if ($js_settings_query) {
        while ($js_setting = mysqli_fetch_assoc($js_settings_query)) {
            if ($js_setting['setting_key'] === 'shipping_cost') {
                $js_shipping_cost = round(floatval($js_setting['setting_value']), 2);
            } elseif ($js_setting['setting_key'] === 'tax_percentage') {
                $js_tax_rate = round(floatval($js_setting['setting_value']) / 100, 4);
            }
        }
    }
    
    // Calculate yearly confirmed total
    $yearly_sql = "SELECT SUM(total_amount) as yearly_total FROM orders WHERE LOWER(order_status) = 'confirmed' AND YEAR(created_at) = YEAR(CURDATE())";
    $yearly_result = mysqli_query($conn, $yearly_sql);
    $yearly_total = 0;
    if ($yearly_result && $row = mysqli_fetch_assoc($yearly_result)) {
        $yearly_total = (float)$row['yearly_total'];
    }
    ?>

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
                            <h4 class="page-title">Orders Management</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./">Silky</a></li>
                                    <li class="breadcrumb-item"><a href="#">Order Management</a></li>
                                    <li class="breadcrumb-item active">Orders</li>
                                </ol>
                            </div>
                        </div><!--end page-title-box-->
                    </div><!--end col-->
                </div><!--end row-->

                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h4 class="card-title">Orders Details</h4>
                                    </div><!--end col-->
                                    <div class="col-auto">
                                        <div class="dropdown d-inline-block me-2">
                                            <button class="btn btn-outline-primary dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-download me-1"></i> Export Data
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="exportDropdown">
                                                <li><h6 class="dropdown-header">Export Selected</h6></li>
                                                <li><a class="dropdown-item" href="#" onclick="exportSelectedOrders('csv')"><i class="fas fa-file-csv me-2 text-success"></i>CSV</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="exportSelectedOrders('xlsx')"><i class="fas fa-file-excel me-2 text-success"></i>Excel (XLSX)</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><h6 class="dropdown-header">Export All</h6></li>
                                                <li><a class="dropdown-item" href="#" onclick="exportAllOrders('csv')"><i class="fas fa-file-csv me-2 text-primary"></i>CSV</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="exportAllOrders('xlsx')"><i class="fas fa-file-excel me-2 text-primary"></i>Excel (XLSX)</a></li>
                                            </ul>
                                        </div>
                                        <button type="button" class="btn btn-primary" id="open-add-order" data-bs-toggle="modal" data-bs-target="#addOrderModal">
                                            <i class="iconoir-plus me-2"></i>Add New Order
                                        </button>
                                    </div><!--end col-->
                                </div> <!--end row-->
                            </div><!--end card-header-->
                            <div class="card-body">
                                <?php
                                if (isset($_SESSION['add_order_success'])) {
                                    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['add_order_success']) . '</div>';
                                    unset($_SESSION['add_order_success']);
                                }
                                if (isset($_SESSION['add_order_error'])) {
                                    echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['add_order_error']) . '</div>';
                                    unset($_SESSION['add_order_error']);
                                }
                                ?>
                                <?php
                                // Handle bulk update POST
                                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_update') {
                                    $selected = $_POST['selected_ids'] ?? [];
                                    $bulkPayment = $_POST['bulk_payment_status'] ?? '';
                                    $bulkOrder = $_POST['bulk_order_status'] ?? '';

                                    // normalize to lowercase
                                    $bulkPayment = $bulkPayment !== '' ? strtolower($bulkPayment) : '';
                                    $bulkOrder = $bulkOrder !== '' ? strtolower($bulkOrder) : '';

                                    // mapping rules
                                    $payToOrder = [
                                        'paid' => 'placed',
                                        'pending' => 'pending'
                                    ];
                                    $orderToPay = array_flip($payToOrder);

                                    $updated = 0;
                                    if (!empty($selected) && is_array($selected)) {
                                        // Fetch current payment_status for selected orders to preserve when needed
                                        $ids = array_map('intval', $selected);
                                        $idList = implode(',', $ids);
                                        $currentPayments = [];
                                        $q = mysqli_query($conn, "SELECT id, payment_status, order_status FROM orders WHERE id IN ($idList)");
                                        if ($q) {
                                            while ($r = mysqli_fetch_assoc($q)) {
                                                $currentPayments[intval($r['id'])] = $r['payment_status'];
                                                $currentOrders[intval($r['id'])] = $r['order_status'];
                                            }
                                            mysqli_free_result($q);
                                        }

                                        $protectedOrderStatuses = ['placed'];
                                        
                                        foreach ($selected as $oidRaw) {
                                            $oid = intval($oidRaw);
                                            $newPay = $bulkPayment;
                                            $newOrder = $bulkOrder;

                                            // Check if status actually changed
                                            $oldPay = $currentPayments[$oid] ?? '';
                                            $oldOrder = $currentOrders[$oid] ?? '';
                                            $actuallyChanged = false;

                                            // Execute update: if both have values, update both; if only one has value, update that one
                                            if ($newPay && $newOrder) {
                                                if ($newPay !== $oldPay || $newOrder !== $oldOrder) $actuallyChanged = true;
                                                $stmt = mysqli_prepare($conn, "UPDATE orders SET payment_status = ?, order_status = ?, updated_at = NOW() WHERE id = ?");
                                                mysqli_stmt_bind_param($stmt, 'ssi', $newPay, $newOrder, $oid);
                                                if (mysqli_stmt_execute($stmt)) $updated++;
                                                mysqli_stmt_close($stmt);
                                            } elseif ($newOrder) {
                                                if ($newOrder !== $oldOrder) $actuallyChanged = true;
                                                $stmt = mysqli_prepare($conn, "UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?");
                                                mysqli_stmt_bind_param($stmt, 'si', $newOrder, $oid);
                                                if (mysqli_stmt_execute($stmt)) $updated++;
                                                mysqli_stmt_close($stmt);
                                            } elseif ($newPay) {
                                                if ($newPay !== $oldPay) $actuallyChanged = true;
                                                $stmt = mysqli_prepare($conn, "UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?");
                                                mysqli_stmt_bind_param($stmt, 'si', $newPay, $oid);
                                                if (mysqli_stmt_execute($stmt)) $updated++;
                                                mysqli_stmt_close($stmt);
                                            }
                                            
                                            // Send email if changed
                                            if ($actuallyChanged) {
                                                require_once '../includes/email-functions.php';
                                                sendOrderStatusUpdateEmail($conn, $oid);

                                                require_once '../includes/stock-functions.php';
                                                if ($newOrder === 'cancelled') {
                                                    restoreOrderStock($conn, $oid);
                                                } elseif ($oldOrder === 'cancelled' && $newOrder !== '' && $newOrder !== 'cancelled') {
                                                    deductOrderStock($conn, $oid);
                                                }
                                            }
                                        }
                                    }
                                    if ($updated > 0) {
                                        echo '<div class="alert alert-success">' . $updated . ' orders updated successfully.</div>';
                                    } else {
                                        echo '<div class="alert alert-info">No orders were updated.</div>';
                                    }
                                }
                                ?>

                                <form id="bulk-action-form" method="POST" action="">
                                    <input type="hidden" name="action" value="bulk_update">
                                    <div class="d-flex align-items-center mb-3 gap-2">
                                        <div>
                                            <label class="form-label mb-0">Payment Status</label>
                                            <select name="bulk_payment_status" id="bulk-payment-status" class="form-select">
                                                <option value="">-- No Change --</option>
                                                <option value="pending">pending</option>
                                                <option value="paid">paid</option>
                                                <option value="failed">failed</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-0">Order Status</label>
                                            <select name="bulk_order_status" id="bulk-order-status" class="form-select">
                                                <option value="">-- No Change --</option>
                                                <option value="pending">pending</option>
                                                <option value="placed">placed</option>
                                                <option value="processing">processing</option>
                                                <option value="confirmed">confirmed</option>
                                                <option value="out for delivery">out for delivery</option>
                                                <option value="delivered">delivered</option>
                                                <option value="cancelled">cancelled</option>
                                                <option value="returned">returned</option>
                                            </select>
                                        </div>
                                        <div class="align-self-end">
                                            <button type="submit" id="apply-bulk" class="btn btn-success" disabled>Apply to selected</button>
                                        </div>
                                    </div>
                                <div class="table-responsive">
                                    <table class="table datatable" id="datatable_1">
                                        <thead class="table-light">
                                            <tr>
                                                    <th data-sortable="false"><input type="checkbox" id="select-all"></th>
                                                    <th>ID</th>
                                                    <th>Order #</th>
                                                    <th>Email</th>
                                                    <th>First Name</th>
                                                    <th>Last Name</th>
                                                    <th>Phone</th>
                                                    <th>Total</th>
                                                    <th>Payment</th>
                                                    <th>Payment Status</th>
                                                    <th>Order Status</th>
                                                    <th data-type="date" data-format="DD/MM/YYYY">Created At</th>
                                                    <th data-sortable="false">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                                require_once 'includes/permission-manager.php';
                                                if (!isset($conn)) {
                                                    require_once '../db_config.php';
                                                }

                                                // Fetch orders joined with orders (if available)
                                                $sql = "SELECT o.id, o.order_number, o.total_amount, o.payment_method, o.payment_status, o.order_status, o.shipping_address, o.created_at, u.email, u.first_name, u.last_name, u.phone FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC";
                                                $result = mysqli_query($conn, $sql);

                                                // Display phone number exactly as stored in the database
                                                // No reformatting is applied.

                                                if ($result && mysqli_num_rows($result) > 0) {
                                                    $rowIndex = 0;
                                                    while ($row = mysqli_fetch_assoc($result)) {
                                                        $rowIndex++;
                                                        $phone = $row['phone'] ?? '';

                                                        echo '<tr>';
                                                        echo '<td><input type="checkbox" class="select-row" name="selected_ids[]" value="' . htmlspecialchars($row['id']) . '"></td>';
                                                        echo '<td>' . $rowIndex . '</td>';
                                                        echo '<td>' . htmlspecialchars($row['order_number'] ?: '-') . '</td>';
                                                        echo '<td>' . htmlspecialchars($row['email'] ?? '-') . '</td>';
                                                        echo '<td>' . htmlspecialchars($row['first_name'] ?? '-') . '</td>';
                                                        echo '<td>' . htmlspecialchars($row['last_name'] ?? '-') . '</td>';
                                                        echo '<td>' . htmlspecialchars($phone) . '</td>';
                                                        echo '<td>' . htmlspecialchars(number_format((float)($row['total_amount'] ?? 0), 2)) . '</td>';
                                                        echo '<td>' . htmlspecialchars($row['payment_method'] ?? '-') . '</td>';
                                                        $paymentStatus = strtolower(trim($row['payment_status'] ?? ''));
                                                        $orderStatus = strtolower(trim($row['order_status'] ?? ''));

                                                        $paymentBadge = 'bg-secondary-subtle text-secondary';
                                                        if ($paymentStatus === 'paid') $paymentBadge = 'bg-success-subtle text-success';
                                                        elseif ($paymentStatus === 'pending') $paymentBadge = 'bg-warning-subtle text-warning';
                                                        elseif ($paymentStatus === 'failed') $paymentBadge = 'bg-danger-subtle text-danger';

                                                        $statusBadgeClasses = [
                                                            'confirmed' => 'bg-success-subtle text-success border border-success-subtle',
                                                            'delivered' => 'bg-success-subtle text-success border border-success-subtle',
                                                            'cancelled' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                                            'placed' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                                            'out for delivery' => 'bg-info-subtle text-info border border-info-subtle',
                                                            'processing' => 'bg-warning-subtle text-warning border border-warning-subtle',
                                                            'pending' => 'bg-warning-subtle text-warning border border-warning-subtle',
                                                            'returned' => 'bg-secondary-subtle text-secondary border border-secondary-subtle'
                                                        ];
                                                        $currentBadgeClass = $statusBadgeClasses[$orderStatus] ?? 'bg-secondary-subtle text-secondary border border-secondary-subtle';

                                                        $orderStatusesList = [
                                                            'pending' => 'Pending',
                                                            'placed' => 'Placed',
                                                            'processing' => 'Processing',
                                                            'confirmed' => 'Confirmed',
                                                            'out for delivery' => 'Out for Delivery',
                                                            'delivered' => 'Delivered',
                                                            'cancelled' => 'Cancelled',
                                                            'returned' => 'Returned'
                                                        ];

                                                        $paymentLabel = $paymentStatus !== '' ? ucfirst($paymentStatus) : '-';

                                                        echo '<td><span class="badge ' . $paymentBadge . '">' . htmlspecialchars($paymentLabel) . '</span></td>';
                                                        echo '<td>';
                                                        echo '<select class="form-select form-select-sm order-status-select ' . $currentBadgeClass . '" data-order-id="' . htmlspecialchars($row['id']) . '" data-prev-status="' . htmlspecialchars($orderStatus) . '">';
                                                        foreach ($orderStatusesList as $stKey => $stLabel) {
                                                            $sel = ($orderStatus === $stKey) ? ' selected' : '';
                                                            echo '<option value="' . htmlspecialchars($stKey) . '"' . $sel . '>' . htmlspecialchars($stLabel) . '</option>';
                                                        }
                                                        echo '</select>';
                                                        echo '</td>';
                                                        echo '<td>' . ($row['created_at'] ? date('d/m/Y h:i A', strtotime($row['created_at'])) : '-') . '</td>';
                                                        echo '<td>';
                                                        echo '<div class="btn-group" role="group">';
                                                        echo '<button type="button" class="btn btn-sm btn-soft-primary view-order-btn" data-id="' . htmlspecialchars($row['id']) . '" data-ordernumber="' . htmlspecialchars($row['order_number'] ?: '') . '" title="View"><i class="fas fa-eye"></i></button>';
                                                        echo '<a href="edit-order.php?id=' . urlencode($row['id']) . '" class="btn btn-sm btn-soft-secondary" title="Edit"><i class="fas fa-edit"></i></a>';
                                                        echo '<a href="delete-order.php?id=' . urlencode($row['id']) . '" class="btn btn-sm btn-soft-danger" title="Delete" onclick="return confirm(\'Are you sure you want to delete this order?\')"><i class="fas fa-trash"></i></a>';
                                                        echo '</div>';
                                                        echo '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="12" class="text-center text-muted">No orders found</td></tr>';
                                                }
                                            ?>
                                                           
                                        </tbody>
                                    </table>
                                    
                                    <!-- Hidden Table for Exporting all data -->
                                    <table id="export_table_all" style="display:none;">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Order #</th>
                                                <th>Email</th>
                                                <th>First Name</th>
                                                <th>Last Name</th>
                                                <th>Phone</th>
                                                <th>Total</th>
                                                <th>Payment</th>
                                                <th>Payment Status</th>
                                                <th>Order Status</th>
                                                <th>Created At</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if (isset($result) && mysqli_num_rows($result) > 0) {
                                                mysqli_data_seek($result, 0);
                                                $exportIndex = 0;
                                                while ($row = mysqli_fetch_assoc($result)) {
                                                    $exportIndex++;
                                                    $phone = $row['phone'] ?? '';
                                                    echo '<tr>';
                                                    echo '<td>' . $exportIndex . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['order_number'] ?: '-') . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['email'] ?? '-') . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['first_name'] ?? '-') . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['last_name'] ?? '-') . '</td>';
                                                    echo '<td>' . htmlspecialchars($phone) . '</td>';
                                                    echo '<td>' . htmlspecialchars(number_format((float)($row['total_amount'] ?? 0), 2)) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['payment_method'] ?? '-') . '</td>';
                                                    echo '<td>' . htmlspecialchars(ucfirst(trim($row['payment_status'] ?? '-'))) . '</td>';
                                                    echo '<td>' . htmlspecialchars(ucfirst(trim($row['order_status'] ?? '-'))) . '</td>';
                                                    echo '<td>' . ($row['created_at'] ? date('d/m/Y h:i A', strtotime($row['created_at'])) : '-') . '</td>';
                                                    echo '</tr>';
                                                }
                                                mysqli_free_result($result);
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                </form>

                                <!-- Summary Section -->
                                <div class="d-flex justify-content-end mt-3">
                                    <div class="text-end">
                                        <p class="mb-1 text-muted">Visible Confirmed Total: <strong class="text-primary fs-15">₹<span id="visible-confirmed-total">0.00</span></strong></p>
                                        <p class="mb-0 text-muted">Yearly Confirmed Total (<?php echo date('Y'); ?>): <strong class="text-success fs-15">₹<?php echo number_format($yearly_total, 2); ?></strong></p>
                                    </div>
                                </div>
                                
                            </div><!--end card-body-->
                        </div><!--end card-->
                    </div> <!--end col-->
                </div><!--end row-->

            </div><!-- container -->

            <!--Start Footer-->
            <?php include 'footer.php'; ?>
            <!--end footer-->
        </div>
        <!-- end page content -->
    </div>
    <!-- end page-wrapper -->

    <!-- Javascript  -->
    <!-- vendor js -->
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>

    <!-- DataTable js -->
    <script src="assets/libs/simple-datatables/umd/simple-datatables.js"></script>
    <script>
        // Initialize DataTable
        document.addEventListener('DOMContentLoaded', function () {
            const dataTable = new simpleDatatables.DataTable("#datatable_1", {
                searchable: true,
                fixedHeight: true,
                perPage: 10,
                perPageSelect: [5, 10, 15, 20, 25],
                sortable: true,
                pagination: true,
                labels: {
                    placeholder: "Search orders...",
                    searchTitle: "Search within table",
                    pageTitle: "Page {page}",
                    perPage: "orders per page",
                    noRows: "No orders found",
                    info: "Showing {start} to {end} of {rows} orders"
                }
            });
        });
    </script>

    <script>
    // Bulk action UI behavior
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('select-all');
        const applyBtn = document.getElementById('apply-bulk');
        const rowChecks = () => document.querySelectorAll('.select-row');

        function updateApplyState() {
            const anyChecked = Array.from(rowChecks()).some(ch => ch.checked);
            applyBtn.disabled = !anyChecked;
        }

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                rowChecks().forEach(ch => ch.checked = selectAll.checked);
                updateApplyState();
            });
        }

        document.addEventListener('change', function(e) {
            if (e.target && e.target.classList && e.target.classList.contains('select-row')) {
                updateApplyState();
            }
        });

        // Mapping between payment and order statuses (client-side)
        const payToOrder = {
            'paid': 'placed',
            'pending': 'pending'
        };
        const orderToPay = {};
        Object.keys(payToOrder).forEach(k => { orderToPay[payToOrder[k]] = k; });

        const bulkPay = document.getElementById('bulk-payment-status');
        const bulkOrder = document.getElementById('bulk-order-status');

        if (bulkPay && bulkOrder) {
            bulkPay.addEventListener('change', function() {
                const v = bulkPay.value;
                if (v && payToOrder[v]) {
                    bulkOrder.value = payToOrder[v];
                }
            });
            bulkOrder.addEventListener('change', function() {
                const v = bulkOrder.value;
                if (v && orderToPay[v]) {
                    bulkPay.value = orderToPay[v];
                }
            });
        }
    });
    </script>

    <script src="assets/js/app.js"></script>

    <!-- Theme Manager for Dark Mode Persistence -->
    <script src="assets/js/theme-manager.js"></script>

        <!-- Order Modal -->
        <div class="modal fade" id="orderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Order Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="order-modal-body">
                        <div class="text-center py-4">Loading...</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <a href="#" id="modal-download-invoice-btn" class="btn btn-primary" target="_blank" style="display:none;"><i class="fas fa-download me-1"></i> Download Invoice</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Order Modal -->
        <div class="modal fade" id="addOrderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="" id="add-order-form">
                            <input type="hidden" name="action" value="add_order">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Payment Method</label>
                                    <select name="payment_method" class="form-select" required>
                                        <option value="">Select</option>
                                        <option value="Cash">Cash</option>
                                        <option value="Online">Online</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Payment Status</label>
                                    <select name="payment_status" id="add-payment-status" class="form-select">
                                        <option value="paid">paid</option>
                                        <option value="pending" selected>pending</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Order Status</label>
                                    <select name="order_status" id="add-order-status" class="form-select">
                                        <option value="placed">placed</option>
                                        <option value="pending" selected>pending</option>
                                    </select>
                                </div>
                            </div>

                            <hr>
                            <h6 class="mb-2">Customer Info</h6>
                            <div class="row g-3">
                                <div class="col-md-3"><input type="text" name="first_name" class="form-control customer-search" placeholder="First name"></div>
                                <div class="col-md-3"><input type="text" name="last_name" class="form-control customer-search" placeholder="Last name"></div>
                                <div class="col-md-3"><input type="email" name="email" class="form-control customer-search" placeholder="Email"></div>
                                <div class="col-md-3"><input type="text" name="phone" class="form-control customer-search" placeholder="Phone"></div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <div id="customer-suggestions" class="list-group mt-2" style="display: none;"></div>
                                </div>
                            </div>

                            <hr>
                            <h6 class="mb-2">Shipping Address</h6>
                            <div class="row g-3">
                                <div class="col-md-4"><input type="text" name="street_address" class="form-control" placeholder="Street address"></div>
                                <div class="col-md-4"><input type="text" name="apartment" class="form-control" placeholder="Apartment"></div>
                                <div class="col-md-4"><input type="text" name="city" class="form-control" placeholder="City"></div>
                                <div class="col-md-4"><input type="text" name="state" class="form-control" placeholder="State"></div>
                                <div class="col-md-4"><input type="text" name="zip_code" class="form-control" placeholder="Zip code"></div>
                                <div class="col-md-4"><input type="text" name="country" class="form-control" placeholder="Country"></div>
                            </div>

                            <hr>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">Items</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="add-item-row">Add Item</button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="items-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Color</th>
                                            <th>Size</th>
                                            <th class="text-end">Price</th>
                                            <th class="text-end">Qty</th>
                                            <th class="text-end">Subtotal</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><input type="text" name="item_name[]" class="form-control product-name" list="product-suggestions" required></td>
                                            <td><input type="text" name="item_color[]" class="form-control"></td>
                                            <td><input type="text" name="item_size[]" class="form-control"></td>
                                            <td><input type="number" step="0.01" name="item_price[]" class="form-control text-end" value="0"></td>
                                            <td><input type="number" step="1" name="item_qty[]" class="form-control text-end" value="1"></td>
                                            <td class="text-end subtotal-cell">0.00</td>
                                            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-item">Remove</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Order Summary -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">Order Summary</h6>
                                    <div class="row mb-2">
                                        <div class="col-6"><strong>Subtotal:</strong></div>
                                        <div class="col-6 text-end" id="summary-subtotal">₹0.00</div>
                                    </div>
                                    <div class="row mb-2 align-items-center">
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="include-shipping" checked>
                                                <label class="form-check-label" for="include-shipping">
                                                    <strong>Shipping:</strong>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-6 text-end" id="summary-shipping">₹<?php echo number_format($js_shipping_cost, 2); ?></div>
                                    </div>
                                    <div class="row mb-2 align-items-center">
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="include-tax" checked>
                                                <label class="form-check-label" for="include-tax">
                                                    <strong>Tax (<?php echo number_format($js_tax_rate * 100, 0); ?>%):</strong>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-6 text-end" id="summary-tax">₹0.00</div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-6"><strong>Total:</strong></div>
                                        <div class="col-6 text-end"><strong id="summary-total">₹<?php echo number_format($js_shipping_cost, 2); ?></strong></div>
                                    </div>
                                    <input type="hidden" name="include_shipping" id="include-shipping-input" value="1">
                                    <input type="hidden" name="include_tax" id="include-tax-input" value="1">
                                </div>
                            </div>

                            <datalist id="product-suggestions"></datalist>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" form="add-order-form" class="btn btn-primary">Create Order</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
                const modalEl = document.getElementById('orderModal');
                const modalBody = document.getElementById('order-modal-body');
                const bsModal = new bootstrap.Modal(modalEl);

                function loadOrder(id) {
                        modalBody.innerHTML = '<div class="text-center py-4">Loading...</div>';
                        fetch('orders.php?action=modal_order&id=' + encodeURIComponent(id))
                                .then(res => res.text())
                                .then(html => {
                                        modalBody.innerHTML = html;
                                })
                                .catch(err => {
                                        modalBody.innerHTML = '<div class="alert alert-danger">Failed to load order details.</div>';
                                });
                }

                document.querySelectorAll('.view-order-btn').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                                const id = this.getAttribute('data-id');
                                const orderNum = this.getAttribute('data-ordernumber');
                                if (!id) return;
                                loadOrder(id);
                                
                                const downloadBtn = document.getElementById('modal-download-invoice-btn');
                                if (downloadBtn) {
                                    if (orderNum) {
                                        downloadBtn.href = '../generate-invoice.php?order=' + encodeURIComponent(orderNum) + '&download=1';
                                        downloadBtn.style.display = 'inline-block';
                                    } else {
                                        downloadBtn.style.display = 'none';
                                    }
                                }
                                
                                bsModal.show();
                        });
                });

                const addModalEl = document.getElementById('addOrderModal');
                if (addModalEl) {
                    addModalEl.addEventListener('shown.bs.modal', function() {
                        // Reset form when modal opens
                        const form = document.getElementById('add-order-form');
                        if (form) form.reset();
                        const tbody = document.querySelector('#items-table tbody');
                        if (tbody) {
                            tbody.innerHTML = '';
                            addItemRow();
                        }
                        // Update summary after reset
                        if (typeof updateOrderSummary === 'function') {
                            setTimeout(function() {
                                updateOrderSummary();
                            }, 100);
                        }
                    });
                }
        });
        </script>

    <script>
    // Auto-change order status when payment status changes in Add Order modal
    document.addEventListener('DOMContentLoaded', function () {
        const addPayStatus = document.getElementById('add-payment-status');
        const addOrdStatus = document.getElementById('add-order-status');
        
        if (addPayStatus && addOrdStatus) {
            addPayStatus.addEventListener('change', function() {
                if (addPayStatus.value === 'paid') {
                    addOrdStatus.value = 'placed';
                }
            });
        }
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const itemsTable = document.getElementById('items-table');
        const addBtn = document.getElementById('add-item-row');
        
        // Fetch settings from PHP with precise decimal values
        const shippingCost = parseFloat('<?php echo number_format($js_shipping_cost, 2, '.', ''); ?>');
        const taxRate = parseFloat('<?php echo number_format($js_tax_rate, 4, '.', ''); ?>');

        function updateRowSubtotal(row) {
            const price = parseFloat(row.querySelector('input[name="item_price[]"]').value || '0');
            const qty = parseInt(row.querySelector('input[name="item_qty[]"]').value || '0', 10);
            const subtotal = price * qty;
            row.querySelector('.subtotal-cell').textContent = subtotal.toFixed(2);
            updateOrderSummary();
        }

        function updateOrderSummary() {
            let subtotal = 0;
            document.querySelectorAll('.subtotal-cell').forEach(cell => {
                subtotal += parseFloat(cell.textContent || '0');
            });

            const includeShippingCheck = document.getElementById('include-shipping');
            const includeTaxCheck = document.getElementById('include-tax');
            const includeShipping = includeShippingCheck ? includeShippingCheck.checked : true;
            const includeTax = includeTaxCheck ? includeTaxCheck.checked : true;

            const shipping = (subtotal > 0 && includeShipping) ? shippingCost : 0;
            const taxableAmount = subtotal + shippingCost; // Always include shipping cost in tax calculation
            const tax = (subtotal > 0 && includeTax) ? taxableAmount * taxRate : 0;
            const total = subtotal + shipping + tax;

            document.getElementById('summary-subtotal').textContent = '₹' + subtotal.toFixed(2);
            document.getElementById('summary-shipping').textContent = '₹' + shipping.toFixed(2);
            document.getElementById('summary-tax').textContent = '₹' + tax.toFixed(2);
            document.getElementById('summary-total').textContent = '₹' + total.toFixed(2);
            
            // Update hidden inputs
            const shippingInput = document.getElementById('include-shipping-input');
            const taxInput = document.getElementById('include-tax-input');
            if (shippingInput) shippingInput.value = includeShipping ? '1' : '0';
            if (taxInput) taxInput.value = includeTax ? '1' : '0';
        }

        window.addItemRow = function() {
            if (!itemsTable) return;
            const tbody = itemsTable.querySelector('tbody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" name="item_name[]" class="form-control product-name" list="product-suggestions" required></td>
                <td><input type="text" name="item_color[]" class="form-control"></td>
                <td><input type="text" name="item_size[]" class="form-control"></td>
                <td><input type="number" step="0.01" name="item_price[]" class="form-control text-end" value="0"></td>
                <td><input type="number" step="1" name="item_qty[]" class="form-control text-end" value="1"></td>
                <td class="text-end subtotal-cell">0.00</td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-item">Remove</button></td>
            `;
            tbody.appendChild(row);
            updateRowSubtotal(row);
        };

        if (addBtn) {
            addBtn.addEventListener('click', function() {
                window.addItemRow();
            });
        }

        // Add event listeners for include shipping/tax checkboxes
        const includeShippingCheck = document.getElementById('include-shipping');
        const includeTaxCheck = document.getElementById('include-tax');
        if (includeShippingCheck) {
            includeShippingCheck.addEventListener('change', updateOrderSummary);
        }
        if (includeTaxCheck) {
            includeTaxCheck.addEventListener('change', updateOrderSummary);
        }

        if (itemsTable) {
            itemsTable.addEventListener('input', function(e) {
                const row = e.target.closest('tr');
                if (row && (e.target.name === 'item_price[]' || e.target.name === 'item_qty[]')) {
                    updateRowSubtotal(row);
                }
            });

            let searchTimer = null;
            itemsTable.addEventListener('input', function(e) {
                if (!e.target.classList.contains('product-name')) return;
                const query = e.target.value.trim();
                if (searchTimer) clearTimeout(searchTimer);
                if (query.length < 2) return;
                searchTimer = setTimeout(function() {
                    fetch('orders.php?action=product_search&q=' + encodeURIComponent(query))
                        .then(res => res.json())
                        .then(list => {
                            const dl = document.getElementById('product-suggestions');
                            if (!dl) return;
                            dl.innerHTML = '';
                            list.forEach(item => {
                                const opt = document.createElement('option');
                                opt.value = item;
                                dl.appendChild(opt);
                            });
                        })
                        .catch(() => {});
                }, 250);
            });

            itemsTable.addEventListener('change', function(e) {
                if (!e.target.classList.contains('product-name')) return;
                const row = e.target.closest('tr');
                const raw = e.target.value.trim();
                if (!row || raw.length === 0) return;
                // Expect format: ProductName-Category; use name part for lookup
                const namePart = raw.split('-').slice(0, -1).join('-').trim() || raw;
                fetch('orders.php?action=product_price&name=' + encodeURIComponent(namePart))
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.price !== null && data.price !== undefined) {
                            const priceInput = row.querySelector('input[name="item_price[]"]');
                            if (priceInput) {
                                priceInput.value = parseFloat(data.price).toFixed(2);
                                updateRowSubtotal(row);
                            }
                        }
                    })
                    .catch(() => {});

                fetch('orders.php?action=product_variants&name=' + encodeURIComponent(namePart))
                    .then(res => res.json())
                    .then(data => {
                        if (!data) return;
                        const colorInput = row.querySelector('input[name="item_color[]"]');
                        const sizeInput = row.querySelector('input[name="item_size[]"]');

                        if (colorInput) {
                            const listId = 'color-list-' + Date.now() + Math.floor(Math.random() * 1000);
                            const dl = document.createElement('datalist');
                            dl.id = listId;
                            (data.colors || []).forEach(c => {
                                const opt = document.createElement('option');
                                opt.value = c;
                                dl.appendChild(opt);
                            });
                            colorInput.setAttribute('list', listId);
                            colorInput.parentElement.appendChild(dl);
                        }

                        if (sizeInput) {
                            const listId = 'size-list-' + Date.now() + Math.floor(Math.random() * 1000);
                            const dl = document.createElement('datalist');
                            dl.id = listId;
                            (data.sizes || []).forEach(s => {
                                const opt = document.createElement('option');
                                opt.value = s;
                                dl.appendChild(opt);
                            });
                            sizeInput.setAttribute('list', listId);
                            sizeInput.parentElement.appendChild(dl);
                        }
                    })
                    .catch(() => {});
            });

            itemsTable.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('remove-item')) {
                    const row = e.target.closest('tr');
                    row.remove();
                    updateOrderSummary();
                }
            });
        }

        // Initialize summary on page load
        updateOrderSummary();
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const suggestionBox = document.getElementById('customer-suggestions');
        const inputs = document.querySelectorAll('.customer-search');
        let custTimer = null;

        function renderSuggestions(list, query) {
            if (!suggestionBox) return;
            suggestionBox.innerHTML = '';
            if (!list || list.length === 0) {
                suggestionBox.style.display = 'none';
                console.debug('renderSuggestions: no results', list);
                return;
            }
            console.debug('renderSuggestions results:', list);
            const q = query.toLowerCase();
            list.forEach(item => {
                const orderId = item.order_id || '';
                const name = (item.first_name || '') + ' ' + (item.last_name || '');
                const email = item.email || '';
                const phone = item.phone || '';
                let displayValue = '';
                if (email.toLowerCase().includes(q)) displayValue = email;
                else if (phone.toLowerCase().includes(q)) displayValue = phone;
                else if ((item.first_name || '').toLowerCase().includes(q)) displayValue = item.first_name || '';
                else if ((item.last_name || '').toLowerCase().includes(q)) displayValue = item.last_name || '';
                else displayValue = email || phone || '';

                const phoneDisplay = phone ? ' | ' + phone : '';
                const label = name.trim() + ' | ' + displayValue + phoneDisplay;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action';
                btn.textContent = label;
                btn.dataset.orderId = orderId;
                btn.addEventListener('click', function () {
                    console.log('=== SUGGESTION CLICKED ===');
                    console.log('Item data:', item);
                    
                    // helper to set a form field by finding input directly
                    function setField(name, value) {
                        // Query directly using attribute selector
                        var el = document.querySelector('input[name="'+name+'"]');
                        console.log('Setting', name, '→', value, '| Element found:', el !== null);
                        if (!el) {
                            console.warn('⚠️ Could not find input for:', name);
                            return false;
                        }
                        try {
                            el.value = value || '';
                            console.log('✓ Set', name, 'to:', el.value);
                            return true;
                        } catch (e) {
                            console.error('❌ Failed to set field', name, e);
                            return false;
                        }
                    }

                    console.log('--- Setting basic fields ---');
                    ['first_name','last_name','email','phone'].forEach(function(n){ setField(n, item[n]); });

                    // If suggestion already contains address info, use it and skip extra fetch
                    const hasAddress = (item.street_address && item.street_address.trim() !== '') || (item.city && item.city.trim() !== '') || (item.zip_code && item.zip_code.trim() !== '');
                    if (hasAddress) {
                        console.log('--- Setting address fields from suggestion ---');
                        ['street_address','apartment','city','state','zip_code','country'].forEach(function(n){ setField(n, item[n]); });
                        suggestionBox.style.display = 'none';
                        console.log('✓ All fields populated from suggestion');
                        return;
                    }

                    const id = this.dataset.orderId || '';
                    if (!id) { 
                        suggestionBox.style.display = 'none';
                        console.log('No order ID, hiding suggestions');
                        return;
                    }
                    
                    console.log('--- Fetching full address for order', id, '---');
                    fetch(window.location.pathname + '?action=customer_details&order_id=' + encodeURIComponent(id) + '&t=' + Date.now())
                        .then(res => {
                            if (!res.ok) {
                                console.error('❌ customer_details fetch failed', res.status);
                                return null;
                            }
                            const ct = res.headers.get('content-type') || '';
                            if (!ct.includes('application/json')) {
                                return res.text().then(t => { console.error('❌ Expected JSON, got:', t); return null; });
                            }
                            return res.json();
                        })
                        .then(data => {
                            console.log('Customer details response:', data);
                            if (!data) return;
                            console.log('--- Setting all fields from API ---');
                            ['first_name','last_name','email','phone','street_address','apartment','city','state','zip_code','country'].forEach(function(n){ setField(n, data[n]); });
                            suggestionBox.style.display = 'none';
                            console.log('✓ All fields populated from API');
                        })
                        .catch(err => { console.error('❌ Request error:', err); });
                });
                suggestionBox.appendChild(btn);
            });
            suggestionBox.style.display = 'block';
        }

        inputs.forEach(input => {
            input.addEventListener('input', function () {
                const query = this.value.trim();
                if (custTimer) clearTimeout(custTimer);
                if (query.length < 2) {
                    if (suggestionBox) suggestionBox.style.display = 'none';
                    return;
                }
                custTimer = setTimeout(function () {
                    fetch(window.location.pathname + '?action=customer_search&q=' + encodeURIComponent(query) + '&t=' + Date.now())
                        .then(res => {
                            if (!res.ok) {
                                console.error('customer_search fetch failed', res.status);
                                return null;
                            }
                            const ct = res.headers.get('content-type') || '';
                            if (!ct.includes('application/json')) {
                                return res.text().then(t => { console.error('customer_search expected json, got:', t); return null; });
                            }
                            return res.json();
                        })
                        .then(list => { if (list) renderSuggestions(list, query); else if (suggestionBox) suggestionBox.style.display = 'none'; })
                        .catch(err => { console.error(err); if (suggestionBox) suggestionBox.style.display = 'none'; });
                }, 250);
            });
        });

        document.addEventListener('click', function (e) {
            if (!suggestionBox) return;
            if (!suggestionBox.contains(e.target) && !e.target.classList.contains('customer-search')) {
                suggestionBox.style.display = 'none';
            }
        });
    });
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
    function exportSelectedOrders(format) {
        var selectedCheckboxes = document.querySelectorAll('.select-row:checked');
        if (selectedCheckboxes.length === 0) {
            alert('Please select at least one order to export.');
            return;
        }

        var exportTable = document.createElement('table');
        var thead = document.createElement('thead');
        var tbody = document.createElement('tbody');
        
        var headerRow = document.querySelector('#datatable_1 thead tr');
        var newHeaderRow = document.createElement('tr');
        // Skip 0 (checkbox) and length-1 (actions)
        for (var i = 1; i < headerRow.cells.length - 1; i++) {
            var newTh = document.createElement('th');
            newTh.innerText = headerRow.cells[i].innerText;
            newHeaderRow.appendChild(newTh);
        }
        thead.appendChild(newHeaderRow);
        exportTable.appendChild(thead);
        
        selectedCheckboxes.forEach(function(cb) {
            var row = cb.closest('tr');
            var newRow = document.createElement('tr');
            for (var i = 1; i < row.cells.length - 1; i++) {
                var newTd = document.createElement('td');
                newTd.innerText = row.cells[i].innerText.trim();
                newRow.appendChild(newTd);
            }
            tbody.appendChild(newRow);
        });
        exportTable.appendChild(tbody);
        
        var wb = XLSX.utils.table_to_book(exportTable, {sheet: "Selected Orders"});
        var date = new Date();
        var dateStr = date.getFullYear() + "-" + (date.getMonth()+1).toString().padStart(2, '0') + "-" + date.getDate().toString().padStart(2, '0');
        
        if (format === 'csv') {
            XLSX.writeFile(wb, 'Silky_Selected_Orders_' + dateStr + '.csv');
        } else if (format === 'xlsx') {
            XLSX.writeFile(wb, 'Silky_Selected_Orders_' + dateStr + '.xlsx');
        }
    }
    
    function exportAllOrders(format) {
        var table = document.getElementById("export_table_all");
        if (!table) return;
        
        var wb = XLSX.utils.table_to_book(table, {sheet: "All Orders"});
        
        var date = new Date();
        var dateStr = date.getFullYear() + "-" + (date.getMonth()+1).toString().padStart(2, '0') + "-" + date.getDate().toString().padStart(2, '0');
        
        if (format === 'csv') {
            XLSX.writeFile(wb, 'Silky_All_Orders_' + dateStr + '.csv');
        } else if (format === 'xlsx') {
            XLSX.writeFile(wb, 'Silky_All_Orders_' + dateStr + '.xlsx');
        }
    }
    
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('select-all');
        const applyBulkBtn = document.getElementById('apply-bulk');

        function updateApplyButton() {
            if (applyBulkBtn) {
                const anyChecked = document.querySelectorAll('.select-row:checked').length > 0;
                applyBulkBtn.disabled = !anyChecked;
            }
        }

        // Use event delegation for #select-all since DataTables might rebuild the DOM
        document.addEventListener('change', function(e) {
            if (e.target && e.target.id === 'select-all') {
                const checkboxes = document.querySelectorAll('.select-row');
                checkboxes.forEach(cb => cb.checked = e.target.checked);
                updateApplyButton();
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('select-row')) {
                const checkboxes = document.querySelectorAll('.select-row');
                const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
                const someChecked = Array.from(checkboxes).some(cb => cb.checked);
                
                if (selectAll) {
                    selectAll.checked = allChecked;
                    selectAll.indeterminate = someChecked && !allChecked;
                }
                updateApplyButton();
            }
        });
        
        // DataTables pagination event to uncheck select-all when page changes
        const dataTableContainer = document.querySelector('.dataTable-container');
        if (dataTableContainer) {
            dataTableContainer.addEventListener('click', function(e) {
                if (e.target.closest('.dataTable-pagination') && selectAll) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                    updateApplyButton();
                }
            });
        }
        
        // Calculate Visible Confirmed Orders Total
        function updateVisibleTotal() {
            let total = 0;
            const visibleRows = document.querySelectorAll('#datatable_1 tbody tr');
            visibleRows.forEach(row => {
                if(row.querySelector('.dataTables-empty') || row.innerText.includes('No orders found')) return;
                // Index 10 is Order Status
                const statusCell = row.cells[10];
                if(statusCell) {
                    const sel = statusCell.querySelector('select');
                    const statusText = sel ? sel.value.toLowerCase().trim() : statusCell.innerText.toLowerCase().trim();
                    if(statusText === 'confirmed') {
                        // Index 7 is Total
                        const totalCell = row.cells[7];
                        if(totalCell) {
                            const val = parseFloat(totalCell.innerText.replace(/[^0-9.]/g, ''));
                            if(!isNaN(val)) total += val;
                        }
                    }
                }
            });
            const totalEl = document.getElementById('visible-confirmed-total');
            if(totalEl) {
                totalEl.innerText = total.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
        }
        
        // Use a small delay for initial calculate to allow DataTables to initialize
        setTimeout(updateVisibleTotal, 500);
        
        // Observe tbody for changes (pagination, sorting, filtering)
        const observer = new MutationObserver(function(mutations) {
            updateVisibleTotal();
        });
        
        // We need to wait for DataTables to potentially replace the tbody
        setTimeout(() => {
            const tbody = document.querySelector('#datatable_1 tbody');
            if(tbody) {
                observer.observe(tbody, { childList: true, subtree: true });
            }
        }, 500);
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.btn-edit-user').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var userId = this.getAttribute('data-id');
                if (userId) {
                    window.location.href = 'edit-user.php?id=' + userId;
                }
            });
        });
            document.querySelectorAll('.btn-delete-user').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var userId = this.getAttribute('data-id');
                    if (userId && confirm('Are you sure you want to delete this user?')) {
                        window.location.href = 'delete-user.php?id=' + userId;
                    }
                });
            });
    });
    </script>

    <!-- Toast Notification -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index:1090;">
        <div id="adminToast" class="toast align-items-center border-0 shadow-lg" role="alert" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-semibold" id="adminToastMsg"></div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script>
    // Toast helper and Order Status Ajax Handler
    (function () {
        function showToast(msg, type) {
            const el = document.getElementById('adminToast');
            const msgEl = document.getElementById('adminToastMsg');
            if (!el || !msgEl) return;
            el.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'text-white');
            if (type === 'success') el.classList.add('bg-success', 'text-white');
            else if (type === 'error') el.classList.add('bg-danger', 'text-white');
            else el.classList.add('bg-warning');
            msgEl.textContent = msg;
            bootstrap.Toast.getOrCreateInstance(el, { delay: 4000 }).show();
        }

        const badgeClasses = {
            'confirmed': 'bg-success-subtle text-success border border-success-subtle',
            'delivered': 'bg-success-subtle text-success border border-success-subtle',
            'cancelled': 'bg-danger-subtle text-danger border border-danger-subtle',
            'placed': 'bg-primary-subtle text-primary border border-primary-subtle',
            'out for delivery': 'bg-info-subtle text-info border border-info-subtle',
            'processing': 'bg-warning-subtle text-warning border border-warning-subtle',
            'pending': 'bg-warning-subtle text-warning border border-warning-subtle',
            'returned': 'bg-secondary-subtle text-secondary border border-secondary-subtle'
        };

        function applyStatusSelectClass(selectEl, status) {
            Object.values(badgeClasses).forEach(clsStr => {
                clsStr.split(' ').forEach(c => selectEl.classList.remove(c));
            });
            selectEl.classList.remove('border-secondary', 'text-secondary', 'bg-light');
            
            const newClasses = badgeClasses[status] || 'bg-secondary-subtle text-secondary border border-secondary-subtle';
            newClasses.split(' ').forEach(c => selectEl.classList.add(c));
        }

        // Event delegation for all .order-status-select dropdowns (table and modal)
        document.addEventListener('change', function(e) {
            const select = e.target.closest('.order-status-select');
            if (!select) return;

            const orderId = select.getAttribute('data-order-id');
            const newStatus = select.value;
            const prevStatus = select.getAttribute('data-prev-status') || '';

            if (!orderId || !newStatus) return;

            select.disabled = true;

            const formData = new FormData();
            formData.append('action', 'update_order_status');
            formData.append('order_id', orderId);
            formData.append('order_status', newStatus);

            fetch('orders.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                select.disabled = false;
                if (data.success) {
                    select.setAttribute('data-prev-status', newStatus);
                    applyStatusSelectClass(select, newStatus);
                    showToast(data.message || 'Order status updated.', 'success');

                    // Synchronize any other select for this order (e.g. if updated in modal, sync in table, or vice versa)
                    document.querySelectorAll(`.order-status-select[data-order-id="${orderId}"]`).forEach(other => {
                        if (other !== select) {
                            other.value = newStatus;
                            other.setAttribute('data-prev-status', newStatus);
                            applyStatusSelectClass(other, newStatus);
                        }
                    });

                    // Update visible total
                    if (typeof updateVisibleTotal === 'function') {
                        updateVisibleTotal();
                    }
                } else {
                    showToast(data.message || 'Failed to update order status.', 'error');
                    if (prevStatus) {
                        select.value = prevStatus;
                        applyStatusSelectClass(select, prevStatus);
                    }
                }
            })
            .catch(err => {
                select.disabled = false;
                showToast('Network error while updating status.', 'error');
                if (prevStatus) {
                    select.value = prevStatus;
                    applyStatusSelectClass(select, prevStatus);
                }
            });
        });
    })();
    </script>
</body>
<!--end body-->

</html>