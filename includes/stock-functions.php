<?php
/**
 * Stock Management Functions
 * Handles stock deduction and restoration for specific product variants and overall stock.
 */

if (!function_exists('ensureStockDeductedColumn')) {
    function ensureStockDeductedColumn($conn) {
        static $checked = false;
        if ($checked) return;
        $col_check = mysqli_query($conn, "SHOW COLUMNS FROM `orders` LIKE 'stock_deducted'");
        if ($col_check && mysqli_num_rows($col_check) == 0) {
            @mysqli_query($conn, "ALTER TABLE `orders` ADD COLUMN `stock_deducted` TINYINT(1) DEFAULT 0");
        }
        $checked = true;
    }
}

if (!function_exists('deductOrderStock')) {
    /**
     * Deducts stock for all items in an order.
     * Decrements the specific variant's stock in `product_variants` and synchronizes `stock`.
     * If product has no variants, decrements from `stock` table directly.
     * Prevents double deduction using orders.stock_deducted flag.
     *
     * @param mysqli $conn
     * @param int $order_id
     * @return bool
     */
    function deductOrderStock($conn, $order_id) {
        $order_id = intval($order_id);
        if ($order_id <= 0) return false;

        ensureStockDeductedColumn($conn);

        // Check if stock was already deducted for this order
        $order_res = mysqli_query($conn, "SELECT id, stock_deducted FROM orders WHERE id = $order_id LIMIT 1");
        if (!$order_res || mysqli_num_rows($order_res) == 0) {
            error_log("deductOrderStock: Order ID $order_id not found.");
            return false;
        }

        $order_row = mysqli_fetch_assoc($order_res);
        if (intval($order_row['stock_deducted']) === 1) {
            error_log("deductOrderStock: Stock already deducted for Order ID $order_id. Skipping to prevent double deduction.");
            return true;
        }

        // Fetch all order items
        $items_query = mysqli_query($conn, "SELECT id, product_id, quantity, variant_info FROM order_items WHERE order_id = $order_id");
        if (!$items_query || mysqli_num_rows($items_query) == 0) {
            error_log("deductOrderStock: No order items found for Order ID $order_id.");
            // Mark as deducted to avoid repeatedly querying empty orders
            mysqli_query($conn, "UPDATE orders SET stock_deducted = 1 WHERE id = $order_id");
            return true;
        }

        while ($item = mysqli_fetch_assoc($items_query)) {
            $product_id = intval($item['product_id']);
            $qty = intval($item['quantity']);
            $variant_info = trim($item['variant_info'] ?? '');

            if ($product_id <= 0 || $qty <= 0) {
                continue;
            }

            // Check if product has any variants in product_variants
            $pv_check = mysqli_query($conn, "SELECT COUNT(*) as count FROM product_variants WHERE product_id = $product_id");
            $has_variants = false;
            if ($pv_check && $pv_row = mysqli_fetch_assoc($pv_check)) {
                $has_variants = (intval($pv_row['count']) > 0);
            }

            $variant_id = 0;

            if ($has_variants && !empty($variant_info)) {
                // Try matching Color | Size
                if (strpos($variant_info, '|') !== false) {
                    $parts = explode('|', $variant_info);
                    $color_part = trim($parts[0]);
                    $size_part = trim($parts[1]);

                    $c_esc = mysqli_real_escape_string($conn, $color_part);
                    $s_esc = mysqli_real_escape_string($conn, $size_part);

                    $v_find_sql = "SELECT pv.id 
                                   FROM product_variants pv
                                   JOIN colors c ON pv.color_id = c.id
                                   JOIN sizes s ON pv.size_id = s.id
                                   WHERE pv.product_id = $product_id 
                                     AND (LOWER(TRIM(c.color_name)) = LOWER('$c_esc') OR LOWER(TRIM(c.color_code)) = LOWER('$c_esc'))
                                     AND LOWER(TRIM(s.size_label)) = LOWER('$s_esc')
                                   LIMIT 1";
                    $v_res = mysqli_query($conn, $v_find_sql);
                    if ($v_res && $v_row = mysqli_fetch_assoc($v_res)) {
                        $variant_id = intval($v_row['id']);
                    }
                } else {
                    // Try matching single attribute (either color or size)
                    $single_esc = mysqli_real_escape_string($conn, $variant_info);

                    // Try matching color first
                    $v_color_sql = "SELECT pv.id 
                                    FROM product_variants pv
                                    JOIN colors c ON pv.color_id = c.id
                                    WHERE pv.product_id = $product_id 
                                      AND (LOWER(TRIM(c.color_name)) = LOWER('$single_esc') OR LOWER(TRIM(c.color_code)) = LOWER('$single_esc'))
                                    LIMIT 1";
                    $vc_res = mysqli_query($conn, $v_color_sql);
                    if ($vc_res && $vc_row = mysqli_fetch_assoc($vc_res)) {
                        $variant_id = intval($vc_row['id']);
                    } else {
                        // Try matching size
                        $v_size_sql = "SELECT pv.id 
                                       FROM product_variants pv
                                       JOIN sizes s ON pv.size_id = s.id
                                       WHERE pv.product_id = $product_id 
                                         AND LOWER(TRIM(s.size_label)) = LOWER('$single_esc')
                                       LIMIT 1";
                        $vs_res = mysqli_query($conn, $v_size_sql);
                        if ($vs_res && $vs_row = mysqli_fetch_assoc($vs_res)) {
                            $variant_id = intval($vs_row['id']);
                        }
                    }
                }
            }

            if ($variant_id > 0) {
                // 1. Deduct specifically from the matched variant
                mysqli_query($conn, "UPDATE product_variants SET stock_quantity = GREATEST(0, stock_quantity - $qty) WHERE id = $variant_id");
                error_log("deductOrderStock: Deducted $qty from product_variants ID $variant_id (Product ID $product_id, Variant: $variant_info)");

                // 2. Synchronize aggregate stock in `stock` table
                $sum_res = mysqli_query($conn, "SELECT SUM(stock_quantity) as total_stock FROM product_variants WHERE product_id = $product_id");
                if ($sum_res && $sum_row = mysqli_fetch_assoc($sum_res)) {
                    $new_total = intval($sum_row['total_stock']);
                    $update_stk = mysqli_query($conn, "UPDATE stock SET quantity = $new_total, last_updated = NOW() WHERE product_id = $product_id");
                    if (mysqli_affected_rows($conn) == 0) {
                        $chk = mysqli_query($conn, "SELECT id FROM stock WHERE product_id = $product_id");
                        if (mysqli_num_rows($chk) == 0) {
                            mysqli_query($conn, "INSERT INTO stock (product_id, quantity, last_updated) VALUES ($product_id, $new_total, NOW())");
                        }
                    }
                }
            } elseif ($has_variants) {
                // Product has variants, but no specific variant matched or variant_info was empty
                // Fallback: Deduct from the first available variant with stock > 0
                $fallback_res = mysqli_query($conn, "SELECT id FROM product_variants WHERE product_id = $product_id ORDER BY (stock_quantity > 0) DESC, id ASC LIMIT 1");
                if ($fallback_res && $fb_row = mysqli_fetch_assoc($fallback_res)) {
                    $fb_id = intval($fb_row['id']);
                    mysqli_query($conn, "UPDATE product_variants SET stock_quantity = GREATEST(0, stock_quantity - $qty) WHERE id = $fb_id");
                    error_log("deductOrderStock: Fallback deducted $qty from product_variants ID $fb_id (Product ID $product_id)");
                    
                    $sum_res = mysqli_query($conn, "SELECT SUM(stock_quantity) as total_stock FROM product_variants WHERE product_id = $product_id");
                    if ($sum_res && $sum_row = mysqli_fetch_assoc($sum_res)) {
                        $new_total = intval($sum_row['total_stock']);
                        mysqli_query($conn, "UPDATE stock SET quantity = $new_total, last_updated = NOW() WHERE product_id = $product_id");
                    }
                } else {
                    mysqli_query($conn, "UPDATE stock SET quantity = GREATEST(0, quantity - $qty), last_updated = NOW() WHERE product_id = $product_id");
                }
            } else {
                // Simple product without variants -> deduct directly from stock table
                mysqli_query($conn, "UPDATE stock SET quantity = GREATEST(0, quantity - $qty), last_updated = NOW() WHERE product_id = $product_id");
                error_log("deductOrderStock: Deducted $qty from stock table for Product ID $product_id");
            }
        }

        // Mark stock as deducted
        mysqli_query($conn, "UPDATE orders SET stock_deducted = 1 WHERE id = $order_id");
        error_log("deductOrderStock: Successfully completed stock deduction for Order ID $order_id.");
        return true;
    }
}

if (!function_exists('restoreOrderStock')) {
    /**
     * Restores stock for all items in an order (e.g., when order is cancelled or refunded).
     * Re-increments the specific variant's stock in `product_variants` and synchronizes `stock`.
     *
     * @param mysqli $conn
     * @param int $order_id
     * @return bool
     */
    function restoreOrderStock($conn, $order_id) {
        $order_id = intval($order_id);
        if ($order_id <= 0) return false;

        ensureStockDeductedColumn($conn);

        // Only restore if stock was actually deducted
        $order_res = mysqli_query($conn, "SELECT id, stock_deducted FROM orders WHERE id = $order_id LIMIT 1");
        if (!$order_res || mysqli_num_rows($order_res) == 0) return false;

        $order_row = mysqli_fetch_assoc($order_res);
        if (intval($order_row['stock_deducted']) === 0) {
            error_log("restoreOrderStock: Stock was not deducted for Order ID $order_id. Skipping restoration.");
            return true;
        }

        $items_query = mysqli_query($conn, "SELECT id, product_id, quantity, variant_info FROM order_items WHERE order_id = $order_id");
        if (!$items_query || mysqli_num_rows($items_query) == 0) {
            mysqli_query($conn, "UPDATE orders SET stock_deducted = 0 WHERE id = $order_id");
            return true;
        }

        while ($item = mysqli_fetch_assoc($items_query)) {
            $product_id = intval($item['product_id']);
            $qty = intval($item['quantity']);
            $variant_info = trim($item['variant_info'] ?? '');

            if ($product_id <= 0 || $qty <= 0) continue;

            $pv_check = mysqli_query($conn, "SELECT COUNT(*) as count FROM product_variants WHERE product_id = $product_id");
            $has_variants = false;
            if ($pv_check && $pv_row = mysqli_fetch_assoc($pv_check)) {
                $has_variants = (intval($pv_row['count']) > 0);
            }

            $variant_id = 0;

            if ($has_variants && !empty($variant_info)) {
                if (strpos($variant_info, '|') !== false) {
                    $parts = explode('|', $variant_info);
                    $color_part = trim($parts[0]);
                    $size_part = trim($parts[1]);

                    $c_esc = mysqli_real_escape_string($conn, $color_part);
                    $s_esc = mysqli_real_escape_string($conn, $size_part);

                    $v_find_sql = "SELECT pv.id 
                                   FROM product_variants pv
                                   JOIN colors c ON pv.color_id = c.id
                                   JOIN sizes s ON pv.size_id = s.id
                                   WHERE pv.product_id = $product_id 
                                     AND (LOWER(TRIM(c.color_name)) = LOWER('$c_esc') OR LOWER(TRIM(c.color_code)) = LOWER('$c_esc'))
                                     AND LOWER(TRIM(s.size_label)) = LOWER('$s_esc')
                                   LIMIT 1";
                    $v_res = mysqli_query($conn, $v_find_sql);
                    if ($v_res && $v_row = mysqli_fetch_assoc($v_res)) {
                        $variant_id = intval($v_row['id']);
                    }
                } else {
                    $single_esc = mysqli_real_escape_string($conn, $variant_info);

                    $v_color_sql = "SELECT pv.id 
                                    FROM product_variants pv
                                    JOIN colors c ON pv.color_id = c.id
                                    WHERE pv.product_id = $product_id 
                                      AND (LOWER(TRIM(c.color_name)) = LOWER('$single_esc') OR LOWER(TRIM(c.color_code)) = LOWER('$single_esc'))
                                    LIMIT 1";
                    $vc_res = mysqli_query($conn, $v_color_sql);
                    if ($vc_res && $vc_row = mysqli_fetch_assoc($vc_res)) {
                        $variant_id = intval($vc_row['id']);
                    } else {
                        $v_size_sql = "SELECT pv.id 
                                       FROM product_variants pv
                                       JOIN sizes s ON pv.size_id = s.id
                                       WHERE pv.product_id = $product_id 
                                         AND LOWER(TRIM(s.size_label)) = LOWER('$single_esc')
                                       LIMIT 1";
                        $vs_res = mysqli_query($conn, $v_size_sql);
                        if ($vs_res && $vs_row = mysqli_fetch_assoc($vs_res)) {
                            $variant_id = intval($vs_row['id']);
                        }
                    }
                }
            }

            if ($variant_id > 0) {
                // 1. Restore specific variant stock
                mysqli_query($conn, "UPDATE product_variants SET stock_quantity = stock_quantity + $qty WHERE id = $variant_id");
                error_log("restoreOrderStock: Restored $qty to product_variants ID $variant_id");

                // 2. Synchronize stock table
                $sum_res = mysqli_query($conn, "SELECT SUM(stock_quantity) as total_stock FROM product_variants WHERE product_id = $product_id");
                if ($sum_res && $sum_row = mysqli_fetch_assoc($sum_res)) {
                    $new_total = intval($sum_row['total_stock']);
                    mysqli_query($conn, "UPDATE stock SET quantity = $new_total, last_updated = NOW() WHERE product_id = $product_id");
                }
            } elseif ($has_variants) {
                $fallback_res = mysqli_query($conn, "SELECT id FROM product_variants WHERE product_id = $product_id ORDER BY id ASC LIMIT 1");
                if ($fallback_res && $fb_row = mysqli_fetch_assoc($fallback_res)) {
                    $fb_id = intval($fb_row['id']);
                    mysqli_query($conn, "UPDATE product_variants SET stock_quantity = stock_quantity + $qty WHERE id = $fb_id");
                    
                    $sum_res = mysqli_query($conn, "SELECT SUM(stock_quantity) as total_stock FROM product_variants WHERE product_id = $product_id");
                    if ($sum_res && $sum_row = mysqli_fetch_assoc($sum_res)) {
                        $new_total = intval($sum_row['total_stock']);
                        mysqli_query($conn, "UPDATE stock SET quantity = $new_total, last_updated = NOW() WHERE product_id = $product_id");
                    }
                }
            } else {
                mysqli_query($conn, "UPDATE stock SET quantity = quantity + $qty, last_updated = NOW() WHERE product_id = $product_id");
                error_log("restoreOrderStock: Restored $qty to stock table for Product ID $product_id");
            }
        }

        // Reset flag
        mysqli_query($conn, "UPDATE orders SET stock_deducted = 0 WHERE id = $order_id");
        error_log("restoreOrderStock: Successfully completed stock restoration for Order ID $order_id.");
        return true;
    }
}
