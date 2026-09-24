<?php
// Start session for cart functionality
session_start();

// Include database connection
include 'db_config.php';

// ========== CART API HANDLING ==========
// Check if this is an API request
$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

if (!empty($action)) {
    header('Content-Type: application/json');

    // Prefer using cart_id in cart_items if column exists; fallback to user_id
    $use_cart_id = false;
    $col_check = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cart_items' AND COLUMN_NAME = 'cart_id'");
    if ($col_check) {
      $cc = mysqli_fetch_assoc($col_check);
      if (!empty($cc) && isset($cc['cnt']) && intval($cc['cnt']) > 0) {
        $use_cart_id = true;
      }
    }
    
    // Check sync needed action
    if ($action == "check_sync_needed") {
        if ($user_id > 0) {
            $sync_needed = isset($_SESSION['cart_sync_needed']) && $_SESSION['cart_sync_needed'];
            echo json_encode(['status' => 'success', 'sync_needed' => $sync_needed]);
        } else {
            echo json_encode(['status' => 'success', 'sync_needed' => false]);
        }
        exit;
    }

    // Guest ke liye cart DB me save nahi hoga
    if ($action == "sync_cart" && $user_id > 0) {
        $input = json_decode(file_get_contents("php://input"), true);
        $cart = isset($input['cart']) ? $input['cart'] : [];

        if (empty($cart)) {
            unset($_SESSION['cart_sync_needed']);
            echo json_encode(['status' => 'success', 'message' => 'No items to sync']);
            exit;
        }

        // Ensure cart header exists and use cart_id for items when available
        $cart_result = mysqli_query($conn, "SELECT id FROM cart WHERE user_id = $user_id");
        if ($cart_result && mysqli_num_rows($cart_result) > 0) {
          $cart_row = mysqli_fetch_assoc($cart_result);
          $cart_id = intval($cart_row['id']);
        } else {
          mysqli_query($conn, "INSERT INTO cart (user_id, total_price) VALUES ($user_id, 0)");
          $cart_id = mysqli_insert_id($conn);
        }

        // Clear existing cart items for this cart
        if ($use_cart_id) {
          mysqli_query($conn, "DELETE FROM cart_items WHERE cart_id = $cart_id");
        } else {
          mysqli_query($conn, "DELETE FROM cart_items WHERE user_id = $user_id");
        }

        // Insert cart items
        $synced_count = 0;
        $total_price = 0;
        foreach ($cart as $item) {
            $pid = intval($item['id']);
            $qty = intval($item['quantity']);
            $cart_price = isset($item['price']) ? floatval($item['price']) : 0;
            
            $color = isset($item['color']) ? trim($item['color']) : '';
            $size = isset($item['size']) ? trim($item['size']) : '';
            $variant_info_raw = "";
            if (!empty($item['variant_info'])) {
                $variant_info_raw = trim($item['variant_info']);
            } elseif ($color && $size) {
                $variant_info_raw = $color . " | " . $size;
            } elseif ($color) {
                $variant_info_raw = $color;
            } elseif ($size) {
                $variant_info_raw = $size;
            }

            // If the item has custom_measurements (custom size), encode as JSON for DB
            $has_custom_measurements = !empty($item['custom_measurements']) && is_array($item['custom_measurements']);
            $has_addon_measurements = !empty($item['measurements']) && is_array($item['measurements']);
            $is_addon_item = !empty($item['is_addon']) || (isset($variant_info_raw) && preg_match('/^add-?on/i', $variant_info_raw));
            if ($has_custom_measurements || $has_addon_measurements || $is_addon_item) {
                // Build a JSON object to store rich variant info
                $variant_obj = [
                    'color' => $color,
                    'size'  => $size,
                    'variant_info' => $variant_info_raw,
                ];
                if ($has_custom_measurements) {
                    $variant_obj['custom_measurements'] = $item['custom_measurements'];
                }
                if ($has_addon_measurements) {
                    $variant_obj['measurements'] = $item['measurements'];
                }
                if ($is_addon_item) {
                    $variant_obj['is_addon'] = true;
                    $variant_obj['type'] = 'addon';
                }
                $variant_info = json_encode($variant_obj, JSON_UNESCAPED_UNICODE);
            } else {
                $variant_info = $variant_info_raw;
            }


            $verify_result = mysqli_query($conn, "SELECT id, price FROM products WHERE id = $pid AND status = 'active'");
            if (!$verify_result || mysqli_num_rows($verify_result) == 0) {
                continue;
            }
            
            $product = mysqli_fetch_assoc($verify_result);
            $price = ($cart_price > 0) ? $cart_price : floatval($product['price']);
            $total_price += ($price * $qty);
            
            $variant_info_esc = mysqli_real_escape_string($conn, $variant_info);

            // Insert into cart_items table using cart_id when available
            if ($use_cart_id) {
              $insert_item = mysqli_query($conn, "INSERT INTO cart_items (cart_id, product_id, quantity, price, variant_info, added_at, updated_at) 
                         VALUES ($cart_id, $pid, $qty, $price, '$variant_info_esc', NOW(), NOW())");
            } else {
              $insert_item = mysqli_query($conn, "INSERT INTO cart_items (user_id, product_id, quantity, price, variant_info) 
                         VALUES ($user_id, $pid, $qty, $price, '$variant_info_esc')");
            }
            
            if ($insert_item) {
                $synced_count++;
            }
        }
        
        // Update cart.total_price
        mysqli_query($conn, "UPDATE cart SET total_price = $total_price WHERE id = $cart_id");

        unset($_SESSION['cart_sync_needed']);

        echo json_encode(["status" => "success", "message" => "Cart synced to DB ($synced_count items)"]);
        exit;
    }

    if ($action == "get_cart") {
        if ($user_id > 0) {
                // Get cart items with product details (join cart -> cart_items)
                if ($use_cart_id) {
              $sql = "SELECT ci.product_id, ci.quantity, ci.price as cart_price, ci.variant_info, p.name, p.product_code, p.price, p.image, p.slug, IFNULL(stk.quantity, 0) as stock
                FROM cart c
                INNER JOIN cart_items ci ON c.id = ci.cart_id
                INNER JOIN products p ON ci.product_id = p.id 
                LEFT JOIN stock stk ON p.id = stk.product_id
                WHERE c.user_id = $user_id AND p.status = 'active'";
                } else {
              $sql = "SELECT ci.product_id, ci.quantity, ci.price as cart_price, ci.variant_info, p.name, p.product_code, p.price, p.image, p.slug, IFNULL(stk.quantity, 0) as stock
                FROM cart_items ci
                INNER JOIN products p ON ci.product_id = p.id 
                LEFT JOIN stock stk ON p.id = stk.product_id
                WHERE ci.user_id = $user_id AND p.status = 'active'";
                }
            $res = mysqli_query($conn, $sql);
            
            if (!$res) {
                echo json_encode(["status" => "error", "message" => "Database query failed"]);
                exit;
            }
            
            $cart = [];
            while ($row = mysqli_fetch_assoc($res)) {
                // Parse image JSON to get first image
                $productImages = [];
                if (!empty($row['image'])) {
                    $imageData = json_decode($row['image'], true);
                    if (is_array($imageData)) {
                        $productImages = $imageData;
                    } else {
                        $productImages = [$row['image']];
                    }
                }
                
                // Get main image path
                $mainImage = 'assets/img/product/placeholder.png';
                if (!empty($productImages)) {
                    $possiblePaths = [
                        'uploads/products/' . $productImages[0],
                        'admin/uploads/' . $productImages[0],
                        $productImages[0]
                    ];
                    foreach ($possiblePaths as $path) {
                        if (file_exists($path)) {
                            $mainImage = $path;
                            break;
                        }
                    }
                }
                
                $cart[] = [
                    "id" => $row['product_id'],
                    "name" => $row['name'],
                    "price" => (!empty($row['cart_price']) && $row['cart_price'] > 0) ? $row['cart_price'] : $row['price'],
                    "quantity" => $row['quantity'],
                    "image" => $mainImage,
                    "slug" => isset($row['slug']) ? $row['slug'] : '',
                    "product_code" => isset($row['product_code']) ? $row['product_code'] : '',
                    "stock" => intval($row['stock']),
                    "variant_info" => isset($row['variant_info']) ? $row['variant_info'] : ''
                ];
            }
            
            echo json_encode(["status" => "success", "cart" => $cart, "count" => count($cart)]);
        } else {
            // Guest ke liye server se kuch nahi milega, frontend localStorage use karega
            echo json_encode(["status" => "guest"]);
        }
        exit;
    }

    if ($action == "get_guest_cart") {
        $input = json_decode(file_get_contents("php://input"), true);
        $cart_items = isset($input['cart']) ? $input['cart'] : [];
        $hydrated_cart = [];

        if (!empty($cart_items)) {
            $ids = [];
            foreach ($cart_items as $item) {
                $pid = intval(isset($item['id']) ? $item['id'] : (isset($item['product_id']) ? $item['product_id'] : 0));
                if ($pid > 0) $ids[] = $pid;
            }

            if (!empty($ids)) {
                $ids_str = implode(',', $ids);
                $sql = "SELECT p.id as product_id, p.name, p.product_code, p.price, p.image, p.slug, IFNULL(stk.quantity, 0) as stock
                        FROM products p 
                        LEFT JOIN stock stk ON p.id = stk.product_id
                        WHERE p.id IN ($ids_str) AND p.status = 'active'";
                $res = mysqli_query($conn, $sql);

                $db_products = [];
                if ($res) {
                    while ($row = mysqli_fetch_assoc($res)) {
                        $db_products[$row['product_id']] = $row;
                    }
                }

                foreach ($cart_items as $item) {
                    $pid = intval(isset($item['id']) ? $item['id'] : (isset($item['product_id']) ? $item['product_id'] : 0));
                    if (isset($db_products[$pid])) {
                        $db_p = $db_products[$pid];
                        
                        // Parse image JSON to get first image
                        $productImages = [];
                        if (!empty($db_p['image'])) {
                            $imageData = json_decode($db_p['image'], true);
                            if (is_array($imageData)) {
                                $productImages = $imageData;
                            } else {
                                $productImages = [$db_p['image']];
                            }
                        }
                        
                        $mainImage = 'assets/img/product/placeholder.png';
                        if (!empty($productImages)) {
                            $possiblePaths = [
                                'uploads/products/' . $productImages[0],
                                'admin/uploads/' . $productImages[0],
                                $productImages[0]
                            ];
                            foreach ($possiblePaths as $path) {
                                if (file_exists($path)) {
                                    $mainImage = $path;
                                    break;
                                }
                            }
                        }

                        $hydrated_cart[] = [
                            "id" => $db_p['product_id'],
                            "name" => $db_p['name'],
                            "price" => isset($item['price']) && floatval($item['price']) > 0 ? floatval($item['price']) : $db_p['price'],
                            "quantity" => intval($item['quantity']),
                            "image" => $mainImage,
                            "slug" => isset($db_p['slug']) ? $db_p['slug'] : '',
                            "product_code" => isset($db_p['product_code']) ? $db_p['product_code'] : '',
                            "stock" => intval($db_p['stock']),
                            "variant_info" => (isset($item['color']) && isset($item['size'])) ? ($item['color'] . " | " . $item['size']) : ''
                        ];
                    }
                }
            }
        }
        
        echo json_encode(["status" => "success", "cart" => $hydrated_cart, "count" => count($hydrated_cart)]);
        exit;
    }

    // Add to cart for logged in user
    if ($action == "add_to_cart") {
        // Suppress any output that could break JSON
        @ini_set('display_errors', '0');
        
        // Check if user is logged in
        if ($user_id <= 0) {
            echo json_encode(["status" => "error", "message" => "User not logged in"]);
            exit;
        }
        
        // Get JSON input
        $raw_input = @file_get_contents("php://input");
        $input = json_decode($raw_input, true);
        
        $product_id = isset($input['product_id']) ? intval($input['product_id']) : 0;
        $quantity = isset($input['quantity']) ? intval($input['quantity']) : 1;
        
        if ($product_id <= 0) {
            echo json_encode(["status" => "error", "message" => "Invalid product ID"]);
            exit;
        }
        
        // Verify product exists and get price
        $verify_query = "SELECT id, price FROM products WHERE id = $product_id AND status = 'active'";
        $verify_result = mysqli_query($conn, $verify_query);
        
        if (!$verify_result || mysqli_num_rows($verify_result) == 0) {
            echo json_encode(["status" => "error", "message" => "Product not found or unavailable"]);
            exit;
        }
        
        $product = mysqli_fetch_assoc($verify_result);
        $price = floatval($product['price']);

        // Ensure cart header exists and get cart_id
        $cart_result = mysqli_query($conn, "SELECT id FROM cart WHERE user_id = $user_id");
        if ($cart_result && mysqli_num_rows($cart_result) > 0) {
          $cart_row = mysqli_fetch_assoc($cart_result);
          $cart_id = intval($cart_row['id']);
        } else {
          mysqli_query($conn, "INSERT INTO cart (user_id, total_price) VALUES ($user_id, 0)");
          $cart_id = mysqli_insert_id($conn);
        }

        $color = isset($input['color']) ? trim($input['color']) : '';
        $size = isset($input['size']) ? trim($input['size']) : '';
        $variant_info = '';
        if (!empty($input['variant_info'])) {
            $variant_info = trim($input['variant_info']);
        } elseif ($color && $size) {
            $variant_info = $color . " | " . $size;
        } elseif ($color) {
            $variant_info = $color;
        } elseif ($size) {
            $variant_info = $size;
        }
        $variant_info_esc = mysqli_real_escape_string($conn, $variant_info);

        // Check if product with this variant already exists in cart_items (by cart_id when available)
        if ($use_cart_id) {
          $check_result = mysqli_query($conn, "SELECT id, quantity FROM cart_items WHERE cart_id = $cart_id AND product_id = $product_id AND variant_info = '$variant_info_esc'");
        } else {
          $check_result = mysqli_query($conn, "SELECT id, quantity FROM cart_items WHERE user_id = $user_id AND product_id = $product_id AND variant_info = '$variant_info_esc'");
        }

        if ($check_result && mysqli_num_rows($check_result) > 0) {
            // Product exists, update quantity
            $row = mysqli_fetch_assoc($check_result);
            $new_quantity = $row['quantity'] + $quantity;
          if ($use_cart_id) {
            $update_result = mysqli_query($conn, "UPDATE cart_items SET quantity = $new_quantity WHERE id = " . intval($row['id']));
          } else {
            $update_result = mysqli_query($conn, "UPDATE cart_items SET quantity = $new_quantity WHERE id = " . intval($row['id']));
          }
            
            if ($update_result) {
                // Update cart total_price
                if ($use_cart_id) {
                  $total_result = mysqli_query($conn, "SELECT SUM(p.price * ci.quantity) as total 
                                     FROM cart_items ci 
                                     INNER JOIN products p ON ci.product_id = p.id 
                                     WHERE ci.cart_id = $cart_id");
                } else {
                  $total_result = mysqli_query($conn, "SELECT SUM(p.price * ci.quantity) as total 
                                     FROM cart_items ci 
                                     INNER JOIN products p ON ci.product_id = p.id 
                                     WHERE ci.user_id = $user_id");
                }
                if ($total_result) {
                    $total_row = mysqli_fetch_assoc($total_result);
                    $total_price = floatval($total_row['total']);
                    $cart_check = mysqli_query($conn, "SELECT id FROM cart WHERE user_id = $user_id");
                    if ($cart_check && mysqli_num_rows($cart_check) > 0) {
                        mysqli_query($conn, "UPDATE cart SET total_price = $total_price WHERE user_id = $user_id");
                    } else {
                        mysqli_query($conn, "INSERT INTO cart (user_id, total_price) VALUES ($user_id, $total_price)");
                    }
                }
                echo json_encode(["status" => "success", "message" => "Product quantity updated in cart!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to update cart: " . mysqli_error($conn)]);
            }
        } else {
            // Product doesn't exist, insert new
            if ($use_cart_id) {
              $insert_result = mysqli_query($conn, "INSERT INTO cart_items (cart_id, product_id, quantity, price, variant_info, added_at, updated_at) 
                                  VALUES ($cart_id, $product_id, $quantity, $price, '$variant_info_esc', NOW(), NOW())");
            } else {
              $insert_result = mysqli_query($conn, "INSERT INTO cart_items (user_id, product_id, quantity, price, variant_info) 
                                  VALUES ($user_id, $product_id, $quantity, $price, '$variant_info_esc')");
            }
            
            if ($insert_result) {
                // Update cart total_price
                $total_result = mysqli_query($conn, "SELECT SUM(p.price * ci.quantity) as total 
                                                     FROM cart_items ci 
                                                     INNER JOIN products p ON ci.product_id = p.id 
                                                     WHERE ci.user_id = $user_id");
                if ($total_result) {
                    $total_row = mysqli_fetch_assoc($total_result);
                    $total_price = floatval($total_row['total']);
                    $cart_check = mysqli_query($conn, "SELECT id FROM cart WHERE user_id = $user_id");
                    if ($cart_check && mysqli_num_rows($cart_check) > 0) {
                        mysqli_query($conn, "UPDATE cart SET total_price = $total_price WHERE user_id = $user_id");
                    } else {
                        mysqli_query($conn, "INSERT INTO cart (user_id, total_price) VALUES ($user_id, $total_price)");
                    }
                }
                echo json_encode(["status" => "success", "message" => "Product added to cart!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to add to cart: " . mysqli_error($conn)]);
            }
        }
        exit;
    }

    // Update quantity for logged in user
    if ($action == "update_quantity" && $user_id > 0) {
        $input = json_decode(file_get_contents("php://input"), true);
        $product_id = isset($input['product_id']) ? intval($input['product_id']) : 0;
        $quantity = isset($input['quantity']) ? intval($input['quantity']) : 1;
        
        $cart_id = 0;
        $cart_result = mysqli_query($conn, "SELECT id FROM cart WHERE user_id = $user_id");
        if ($cart_result && mysqli_num_rows($cart_result) > 0) {
            $cart_row = mysqli_fetch_assoc($cart_result);
            $cart_id = intval($cart_row['id']);
        }
        
        if ($quantity <= 0) {
            // Remove item if quantity is 0 or negative
            if ($use_cart_id && $cart_id > 0) {
                $delete_result = mysqli_query($conn, "DELETE FROM cart_items WHERE cart_id = $cart_id AND product_id = $product_id");
            } else {
                $delete_result = mysqli_query($conn, "DELETE FROM cart_items WHERE user_id = $user_id AND product_id = $product_id");
            }
            if ($delete_result) {
                // Update cart total_price
                if ($use_cart_id && $cart_id > 0) {
                    $total_result = mysqli_query($conn, "SELECT SUM(p.price * ci.quantity) as total 
                                                         FROM cart_items ci 
                                                         INNER JOIN products p ON ci.product_id = p.id 
                                                         WHERE ci.cart_id = $cart_id");
                } else {
                    $total_result = mysqli_query($conn, "SELECT SUM(p.price * ci.quantity) as total 
                                                         FROM cart_items ci 
                                                         INNER JOIN products p ON ci.product_id = p.id 
                                                         WHERE ci.user_id = $user_id");
                }
                if ($total_result) {
                    $total_row = mysqli_fetch_assoc($total_result);
                    $total_price = $total_row['total'] ? floatval($total_row['total']) : 0;
                    if ($cart_id > 0) {
                        mysqli_query($conn, "UPDATE cart SET total_price = $total_price WHERE id = $cart_id");
                    } else {
                        mysqli_query($conn, "UPDATE cart SET total_price = $total_price WHERE user_id = $user_id");
                    }
                }
                echo json_encode(["status" => "success", "message" => "Item removed from cart"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to remove item"]);
            }
        } else {
            // Update quantity
            if ($use_cart_id && $cart_id > 0) {
                $update_result = mysqli_query($conn, "UPDATE cart_items SET quantity = $quantity WHERE cart_id = $cart_id AND product_id = $product_id");
            } else {
                $update_result = mysqli_query($conn, "UPDATE cart_items SET quantity = $quantity WHERE user_id = $user_id AND product_id = $product_id");
            }
            if ($update_result) {
                // Update cart total_price
                if ($use_cart_id && $cart_id > 0) {
                    $total_result = mysqli_query($conn, "SELECT SUM(p.price * ci.quantity) as total 
                                                         FROM cart_items ci 
                                                         INNER JOIN products p ON ci.product_id = p.id 
                                                         WHERE ci.cart_id = $cart_id");
                } else {
                    $total_result = mysqli_query($conn, "SELECT SUM(p.price * ci.quantity) as total 
                                                         FROM cart_items ci 
                                                         INNER JOIN products p ON ci.product_id = p.id 
                                                         WHERE ci.user_id = $user_id");
                }
                if ($total_result) {
                    $total_row = mysqli_fetch_assoc($total_result);
                    $total_price = $total_row['total'] ? floatval($total_row['total']) : 0;
                    if ($cart_id > 0) {
                        mysqli_query($conn, "UPDATE cart SET total_price = $total_price WHERE id = $cart_id");
                    } else {
                        mysqli_query($conn, "UPDATE cart SET total_price = $total_price WHERE user_id = $user_id");
                    }
                }
                echo json_encode(["status" => "success", "message" => "Quantity updated"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to update quantity"]);
            }
        }
        exit;
    }

    // Clear cart for logged in user
    if ($action == "clear_cart" && $user_id > 0) {
        $cart_id = 0;
        $cart_result = mysqli_query($conn, "SELECT id FROM cart WHERE user_id = $user_id");
        if ($cart_result && mysqli_num_rows($cart_result) > 0) {
            $cart_row = mysqli_fetch_assoc($cart_result);
            $cart_id = intval($cart_row['id']);
        }
        
        if ($use_cart_id && $cart_id > 0) {
            $clear_res = mysqli_query($conn, "DELETE FROM cart_items WHERE cart_id = $cart_id");
            mysqli_query($conn, "UPDATE cart SET total_price = 0 WHERE id = $cart_id");
        } else {
            $clear_res = mysqli_query($conn, "DELETE FROM cart_items WHERE user_id = $user_id");
            mysqli_query($conn, "UPDATE cart SET total_price = 0 WHERE user_id = $user_id");
        }
        if ($clear_res) {
            echo json_encode(["status" => "success", "message" => "Cart cleared successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to clear cart"]);
        }
        exit;
    }

    // Remove item from cart for logged in user
    if ($action == "remove_item" && $user_id > 0) {
        $input = json_decode(file_get_contents("php://input"), true);
        $product_id = isset($input['product_id']) ? intval($input['product_id']) : 0;
        
      // Remove item using cart_id when available
      $cart_result = mysqli_query($conn, "SELECT id FROM cart WHERE user_id = $user_id");
      if ($cart_result && mysqli_num_rows($cart_result) > 0) {
        $cart_row = mysqli_fetch_assoc($cart_result);
        $cart_id = intval($cart_row['id']);
      } else {
        $cart_id = 0;
      }

      if ($use_cart_id && $cart_id > 0) {
        $delete_result = mysqli_query($conn, "DELETE FROM cart_items WHERE cart_id = $cart_id AND product_id = $product_id");
      } else {
        $delete_result = mysqli_query($conn, "DELETE FROM cart_items WHERE user_id = $user_id AND product_id = $product_id");
      }

      if ($delete_result) {
        // Update cart total_price
        if ($use_cart_id && $cart_id > 0) {
          $total_result = mysqli_query($conn, "SELECT SUM(p.price * ci.quantity) as total 
                             FROM cart_items ci 
                             INNER JOIN products p ON ci.product_id = p.id 
                             WHERE ci.cart_id = $cart_id");
        } else {
          $total_result = mysqli_query($conn, "SELECT SUM(p.price * ci.quantity) as total 
                             FROM cart_items ci 
                             INNER JOIN products p ON ci.product_id = p.id 
                             WHERE ci.user_id = $user_id");
        }
        if ($total_result) {
          $total_row = mysqli_fetch_assoc($total_result);
          $total_price = $total_row['total'] ? floatval($total_row['total']) : 0;
          if ($cart_id > 0) {
            mysqli_query($conn, "UPDATE cart SET total_price = $total_price WHERE id = $cart_id");
          } else {
            mysqli_query($conn, "UPDATE cart SET total_price = $total_price WHERE user_id = $user_id");
          }
        }
        echo json_encode(["status" => "success", "message" => "Item removed from cart"]);
      } else {
        echo json_encode(["status" => "error", "message" => "Failed to remove item"]);
      }
        exit;
    }

    // Get cart items for checkout - fetches from cart_items table
    if ($action == "get_checkout_cart" && $user_id > 0) {
        // Fetch from cart_items table with product details (prefer cart_id join)
        if ($use_cart_id) {
            $sql = "SELECT ci.product_id, ci.quantity, p.name, p.price, p.image, c.total_price as cart_total
              FROM cart c
              INNER JOIN cart_items ci ON c.id = ci.cart_id
              INNER JOIN products p ON ci.product_id = p.id 
              WHERE c.user_id = $user_id AND p.status = 'active'
              ORDER BY ci.added_at ASC";
        } else {
            $sql = "SELECT ci.product_id, ci.quantity, p.name, p.price, p.image
              FROM cart_items ci
              INNER JOIN products p ON ci.product_id = p.id 
              WHERE ci.user_id = $user_id AND p.status = 'active'
              ORDER BY ci.added_at ASC";
        }
        $res = mysqli_query($conn, $sql);
        
        if (!$res) {
            error_log("get_checkout_cart query failed: " . mysqli_error($conn));
            echo json_encode(["status" => "error", "message" => "Database query failed: " . mysqli_error($conn)]);
            exit;
        }
        
        $cart = [];
        $total_amount = 0;
        $cart_total = 0;
        while ($row = mysqli_fetch_assoc($res)) {
          if ($cart_total == 0 && isset($row['cart_total'])) {
            $cart_total = $row['cart_total'];
          }
          // Parse image JSON to get first image
            $productImages = [];
            if (!empty($row['image'])) {
                $imageData = json_decode($row['image'], true);
                if (is_array($imageData)) {
                    $productImages = $imageData;
                } else {
                    $productImages = [$row['image']];
                }
            }
            
            // Get main image path
            $mainImage = 'assets/img/product/placeholder.png';
            if (!empty($productImages)) {
                $possiblePaths = [
                    'uploads/products/' . $productImages[0],
                    'admin/uploads/' . $productImages[0],
                    $productImages[0]
                ];
                foreach ($possiblePaths as $path) {
                    if (file_exists($path)) {
                        $mainImage = $path;
                        break;
                    }
                }
            }
            
            $item_total = floatval($row['price']) * intval($row['quantity']);
            $total_amount += $item_total;
            
            $cart[] = [
                "id" => $row['product_id'],
                "name" => $row['name'],
                "price" => $row['price'],
                "quantity" => $row['quantity'],
                "image" => $mainImage,
                "subtotal" => $item_total
            ];
        }
        
        // Use cart_total from database if available
        $final_total = ($cart_total > 0) ? $cart_total : $total_amount;
        echo json_encode(["status" => "success", "cart" => $cart, "count" => count($cart), "total" => $final_total]);
        exit;
    }

    if ($action == "apply_coupon") {
        $input = json_decode(file_get_contents("php://input"), true);
        $coupon_code = isset($input['coupon_code']) ? strtoupper(trim($input['coupon_code'])) : '';
        $cart_subtotal = isset($input['cart_subtotal']) ? floatval($input['cart_subtotal']) : 0;
        
        if (empty($coupon_code)) {
            echo json_encode(["status" => "error", "message" => "Please enter a coupon code"]);
            exit;
        }
        
        $coupon_code_esc = mysqli_real_escape_string($conn, $coupon_code);
        $sql = "SELECT * FROM coupons WHERE coupon_code = '$coupon_code_esc' AND status = 'active'";
        $result = mysqli_query($conn, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $coupon = mysqli_fetch_assoc($result);
            
            if (!empty($coupon['start_date']) && strtotime($coupon['start_date']) > time()) {
                echo json_encode(["status" => "error", "message" => "This coupon is not active yet"]);
                exit;
            }
            if (!empty($coupon['end_date']) && strtotime($coupon['end_date']) < time()) {
                echo json_encode(["status" => "error", "message" => "This coupon has expired"]);
                exit;
            }
            if ($coupon['min_purchase'] > 0 && $cart_subtotal < $coupon['min_purchase']) {
                echo json_encode(["status" => "error", "message" => "Minimum purchase of ₹" . $coupon['min_purchase'] . " required"]);
                exit;
            }
            
            $coupon_data = [
                "code" => $coupon['coupon_code'],
                "coupon_code" => $coupon['coupon_code'], // for compatibility
                "type" => $coupon['discount_type'],
                "value" => floatval($coupon['discount_value']),
                "max_discount" => floatval($coupon['max_discount'])
            ];
            
            $_SESSION['applied_coupon'] = $coupon_data;
            
            echo json_encode([
                "status" => "success", 
                "message" => "Coupon applied successfully",
                "coupon" => $coupon_data
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid or inactive coupon code"]);
        }
        exit;
    }

    echo json_encode(["status" => "error", "message" => "Invalid action"]);
    exit;
}
// ========== END CART API HANDLING ==========

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// Fetch tax percentage from database
$tax_percentage = 10; // Default fallback
$tax_query = mysqli_query($conn, "SELECT setting_value FROM general_settings WHERE setting_key = 'tax_percentage'");
if ($tax_query && mysqli_num_rows($tax_query) > 0) {
    $tax_row = mysqli_fetch_assoc($tax_query);
    $tax_percentage = floatval($tax_row['setting_value']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Cart - Silky Saree</title>
  <meta name="description" content="">
  <meta name="keywords" content="">

  <!-- Favicons -->
  <link href="assets/img/favicon/favicon.ico" rel="icon" type="image/x-icon">
  <link href="assets/img/favicon/favicon-32x32.png" rel="icon" type="image/png" sizes="32x32">
  <link href="assets/img/favicon/favicon-16x16.png" rel="icon" type="image/png" sizes="16x16">
  <link href="assets/img/favicon/apple-touch-icon.png" rel="apple-touch-icon" sizes="180x180">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="assets/vendor/drift-zoom/drift-basic.css" rel="stylesheet">

  <!-- Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">

  <style>
    /* ===== CART PAGE - PREMIUM DESIGN ===== */
    .cart-page-wrap {
      padding: 40px 0 60px;
    }

    /* Cart Items Panel */
    .cart-panel {
      background: var(--surface-color);
      border-radius: 16px;
      box-shadow: 0 2px 20px rgba(0,0,0,0.06);
      overflow: hidden;
    }

    .cart-panel-header {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr;
      padding: 14px 24px;
      background: linear-gradient(135deg, color-mix(in srgb, var(--accent-color), transparent 92%) 0%, color-mix(in srgb, var(--heading-color), transparent 96%) 100%);
      border-bottom: 1px solid color-mix(in srgb, var(--default-color), transparent 90%);
    }
    .cart-panel-header span {
      font-size: 0.78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--heading-color);
    }
    .cart-panel-header span:not(:first-child) {
      text-align: center;
    }

    /* Individual Item */
    .cart-item-row {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr;
      align-items: center;
      padding: 18px 24px;
      border-bottom: 1px solid color-mix(in srgb, var(--default-color), transparent 92%);
      transition: background 0.2s;
    }
    .cart-item-row:last-child { border-bottom: none; }
    .cart-item-row:hover { background: color-mix(in srgb, var(--accent-color), transparent 97%); }

    .cart-item-product {
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .cart-item-img {
      width: 70px;
      height: 70px;
      border-radius: 10px;
      object-fit: cover;
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 88%);
      flex-shrink: 0;
    }
    .cart-item-info h6 {
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--heading-color);
      margin-bottom: 4px;
      line-height: 1.3;
    }
    .cart-item-remove {
      background: none;
      border: none;
      padding: 0;
      color: #dc3545;
      font-size: 0.78rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 4px;
      transition: opacity 0.2s;
    }
    .cart-item-remove:hover { opacity: 0.7; }

    .cart-item-price {
      text-align: center;
      font-weight: 600;
      color: var(--heading-color);
      font-size: 0.95rem;
    }

    /* Quantity Selector */
    .qty-control {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0;
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 80%);
      border-radius: 8px;
      overflow: hidden;
      width: fit-content;
      margin: 0 auto;
    }
    .qty-btn {
      background: color-mix(in srgb, var(--accent-color), transparent 92%);
      border: none;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      color: var(--heading-color);
      transition: background 0.2s;
      font-size: 1rem;
    }
    .qty-btn:hover { background: var(--accent-color); color: #fff; }
    .qty-input {
      width: 44px;
      height: 32px;
      border: none;
      border-left: 1px solid color-mix(in srgb, var(--default-color), transparent 80%);
      border-right: 1px solid color-mix(in srgb, var(--default-color), transparent 80%);
      text-align: center;
      font-weight: 600;
      font-size: 0.9rem;
      background: var(--surface-color);
      color: var(--heading-color);
    }
    .qty-input:focus { outline: none; }

    .cart-item-total {
      text-align: center;
      font-weight: 700;
      color: var(--accent-color);
      font-size: 1rem;
    }

    /* Cart Actions Bar */
    .cart-actions-bar {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      flex-wrap: wrap;
      gap: 12px;
      padding: 20px 0 0;
    }
    .coupon-group {
      display: flex;
      gap: 0;
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 75%);
      border-radius: 10px;
      overflow: hidden;
      margin: 16px 0;
      width: 100%;
    }
    .coupon-group input {
      border: none;
      padding: 10px 16px;
      font-size: 0.9rem;
      background: var(--surface-color);
      color: var(--default-color);
      flex: 1;
      width: 100%;
    }
    .coupon-group input:focus { outline: none; }
    .btn-coupon {
      background: linear-gradient(135deg, var(--heading-color), var(--accent-color));
      color: #fff;
      border: none;
      padding: 10px 18px;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.2s;
    }
    .btn-coupon:hover { opacity: 0.88; }

    .cart-right-actions { display: flex; gap: 10px; }
    .btn-update-cart {
      background: transparent;
      border: 1.5px solid var(--heading-color);
      color: var(--heading-color);
      padding: 9px 18px;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      display: flex; align-items: center; gap: 6px;
    }
    .btn-update-cart:hover { background: var(--heading-color); color: #fff; }
    .btn-clear-cart {
      background: transparent;
      border: 1.5px solid #dc3545;
      color: #dc3545;
      padding: 9px 18px;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      display: flex; align-items: center; gap: 6px;
    }
    .btn-clear-cart:hover { background: #dc3545; color: #fff; }

    /* Empty Cart */
    .empty-cart-wrap {
      padding: 60px 24px;
      text-align: center;
    }
    .empty-cart-wrap .empty-icon {
      width: 90px;
      height: 90px;
      background: linear-gradient(135deg, color-mix(in srgb, var(--accent-color), transparent 88%), color-mix(in srgb, var(--heading-color), transparent 92%));
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      font-size: 2.2rem;
      color: var(--accent-color);
    }
    .empty-cart-wrap h4 {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--heading-color);
      margin-bottom: 8px;
    }
    .empty-cart-wrap p {
      color: color-mix(in srgb, var(--default-color), transparent 35%);
      margin-bottom: 24px;
    }
    .btn-continue-shop {
      background: linear-gradient(135deg, var(--accent-color), color-mix(in srgb, var(--accent-color), var(--heading-color) 35%));
      color: #fff;
      border: none;
      padding: 12px 28px;
      border-radius: 50px;
      font-weight: 600;
      font-size: 0.95rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s;
      box-shadow: 0 4px 15px color-mix(in srgb, var(--accent-color), transparent 55%);
    }
    .btn-continue-shop:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px color-mix(in srgb, var(--accent-color), transparent 45%);
      color: #fff;
    }

    /* Order Summary Panel */
    .summary-panel {
      background: var(--surface-color);
      border-radius: 16px;
      box-shadow: 0 2px 20px rgba(0,0,0,0.06);
      padding: 28px;
      position: sticky;
      top: 100px;
    }
    .summary-panel h4 {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--heading-color);
      margin-bottom: 20px;
      padding-bottom: 14px;
      border-bottom: 2px solid color-mix(in srgb, var(--accent-color), transparent 80%);
    }
    .summary-line {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 0;
      border-bottom: 1px solid color-mix(in srgb, var(--default-color), transparent 92%);
    }
    .summary-line:last-of-type { border-bottom: none; }
    .summary-line .label {
      font-size: 0.88rem;
      color: color-mix(in srgb, var(--default-color), transparent 25%);
    }
    .summary-line .val {
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--heading-color);
    }
    .summary-total-line {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 0 0;
      margin-top: 6px;
    }
    .summary-total-line .label {
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--heading-color);
    }
    .summary-total-line .val {
      font-size: 1.2rem;
      font-weight: 800;
      color: var(--accent-color);
    }



    /* Gradient Checkout Button */
    .btn-checkout {
      display: block;
      width: 100%;
      background: linear-gradient(135deg, var(--heading-color, #333333), var(--accent-color, #6a9739));
      color: #fff;
      border: none;
      border-radius: 50px;
      padding: 14px 20px;
      font-weight: 700;
      font-size: 1rem;
      text-align: center;
      text-decoration: none;
      transition: all 0.3s;
      box-shadow: 0 4px 18px color-mix(in srgb, var(--accent-color), transparent 55%);
      margin-top: 22px;
      position: relative;
      overflow: hidden;
    }
    .btn-checkout::before {
      content: '';
      position: absolute;
      top: 0; left: -100%;
      width: 100%; height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
      transition: left 0.5s;
    }
    .btn-checkout:hover::before { left: 100%; }
    .btn-checkout:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 28px color-mix(in srgb, var(--accent-color), transparent 42%);
      color: #fff;
    }

    .btn-back-shop {
      display: block;
      text-align: center;
      color: color-mix(in srgb, var(--default-color), transparent 25%);
      font-size: 0.85rem;
      margin-top: 14px;
      text-decoration: none;
      transition: color 0.2s;
    }
    .btn-back-shop:hover { color: var(--accent-color); }

    /* Payment badges */
    .pay-badges {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      margin-top: 18px;
      padding-top: 16px;
      border-top: 1px solid color-mix(in srgb, var(--default-color), transparent 90%);
      color: color-mix(in srgb, var(--default-color), transparent 40%);
      font-size: 1.4rem;
    }
    .pay-badges small {
      display: block;
      font-size: 0.72rem;
      text-align: center;
      color: color-mix(in srgb, var(--default-color), transparent 45%);
      margin-bottom: 8px;
      width: 100%;
    }

    /* Mobile adjustments */
    @media (max-width: 767px) {
      .cart-panel-header { display: none; }
      .cart-item-row {
        grid-template-columns: 1fr;
        gap: 10px;
      }
      .cart-item-price, .cart-item-total { text-align: left; }
      .qty-control { margin: 0; }
      .cart-actions-bar { flex-direction: column; align-items: flex-start; }
    }
  </style>
</head>

<body class="cart-page">

  <header id="header" class="header sticky-top">
    <!-- Top Bar -->
    <?php include 'topbar.php'; ?>

    <!-- Main Header -->
    <?php include 'main-header.php'; ?>

  </header>

  <main class="main">

    <!-- Page Title -->
    <div class="page-title light-background">
      <div class="container d-lg-flex justify-content-between align-items-center">
        <h1 class="mb-2 mb-lg-0">Shopping Cart</h1>
        <nav class="breadcrumbs">
          <ol>
            <li><a href="index.php">Home</a></li>
            <li class="current">Cart</li>
          </ol>
        </nav>
      </div>
    </div>

    <!-- Cart Section -->
    <section id="cart" class="cart section">
      <div class="container cart-page-wrap">
        <div class="row g-4">

          <!-- Left: Cart Items -->
          <div class="col-lg-8">
            <div class="cart-panel">
              <!-- Table Header (desktop) -->
              <div class="cart-panel-header d-none d-md-grid">
                <span>Product</span>
                <span>Price</span>
                <span>Quantity</span>
                <span>Total</span>
              </div>

              <!-- Items injected by JS -->
              <div id="cart-items-container"></div>

              <!-- Empty state -->
              <div id="empty-cart-message" class="empty-cart-wrap" style="display:none;">
                <div class="empty-icon"><i class="bi bi-cart-x"></i></div>
                <h4>Your cart is empty</h4>
                <p>Looks like you haven't added anything yet.</p>
                <a href="index.php" class="btn-continue-shop">
                  <i class="bi bi-arrow-left"></i> Continue Shopping
                </a>
              </div>
            </div>

            <!-- Cart Actions -->
            <div class="cart-actions-bar" id="cart-actions" style="display:none;">
              <div class="cart-right-actions">
                <button class="btn-update-cart" id="update-cart">
                  <i class="bi bi-arrow-clockwise"></i> Update
                </button>
                <button class="btn-clear-cart" id="clear-cart">
                  <i class="bi bi-trash"></i> Clear Cart
                </button>
              </div>
            </div>
          </div>

          <!-- Right: Order Summary -->
          <div class="col-lg-4">
            <div class="summary-panel">
              <h4>Order Summary</h4>

              <div class="summary-line">
                <span class="label">Subtotal</span>
                <span class="val" id="subtotal">₹0.00</span>
              </div>



              <div class="summary-line">
                <?php if ($tax_percentage > 0): ?>
                  <span class="label" id="cart-tax-label">Tax (<?php echo $tax_percentage; ?>%)</span>
                  <span class="val" id="tax">₹0.00</span>
                <?php else: ?>
                  <span class="label" id="cart-tax-label">Tax</span>
                  <span class="val text-success" id="tax" style="font-size:0.9em; font-weight:500;">Included</span>
                <?php endif; ?>
              </div>

              <div class="summary-line">
                <span class="label">Discount</span>
                <span class="val" id="discount">-₹0.00</span>
              </div>

              <div class="coupon-group">
                <input type="text" placeholder="Coupon code" id="coupon-input">
                <button class="btn-coupon" id="apply-coupon">Apply</button>
              </div>

              <div class="summary-total-line">
                <span class="label">Total</span>
                <span class="val" id="total">₹0.00</span>
              </div>

              <a href="checkout.php" class="btn-checkout" id="checkout-btn" style="display:none;">
                Proceed to Checkout &nbsp;<i class="bi bi-arrow-right"></i>
              </a>

              <a href="index.php" class="btn-back-shop">
                <i class="bi bi-arrow-left"></i> Continue Shopping
              </a>

              <div class="pay-badges">
                <small>We Accept</small>
                <i class="bi bi-credit-card"></i>
                <i class="bi bi-paypal"></i>
                <i class="bi bi-wallet2"></i>
                <i class="bi bi-bank"></i>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

  </main>

  <?php include 'footer.php'?>

  <!-- Scroll Top -->
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Preloader -->
  <div id="preloader"></div>

  <!-- Mobile Bottom Navigation -->
  <?php include 'mobile-bottom-nav.php'; ?>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>
  <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
  <script src="assets/vendor/drift-zoom/Drift.min.js"></script>
  <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>

  <!-- Main JS File -->
  <script src="assets/js/main.js"></script>

  <!-- Cart Management Script -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
      let cart = [];
      let appliedCoupon = null;

      const cartContainer  = document.getElementById('cart-items-container');
      const emptyMsg       = document.getElementById('empty-cart-message');
      const subtotalEl     = document.getElementById('subtotal');
      const taxEl          = document.getElementById('tax');
      const totalEl        = document.getElementById('total');
      const checkoutBtn    = document.getElementById('checkout-btn');
      const cartActions    = document.getElementById('cart-actions');

      initCart();

      async function initCart() {
        if (isLoggedIn) {
          const local = JSON.parse(localStorage.getItem('cart')) || [];
          if (local.length > 0) {
            await syncToDb(local);
            localStorage.removeItem('cart');
          }
          await loadFromDb();
        } else {
          const local = JSON.parse(localStorage.getItem('cart')) || [];
          if (local.length > 0) {
            try {
              const r = await fetch('cart.php?action=get_guest_cart', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cart: local })
              });
              const d = await r.json();
              if (d.status === 'success') {
                cart = d.cart;
                localStorage.setItem('cart', JSON.stringify(cart));
              } else {
                cart = local;
              }
            } catch(e) { cart = local; }
          } else {
            cart = [];
          }
        }
        render();
        calcSummary();
      }

      async function syncToDb(localCart) {
        try {
          const r = await fetch('cart.php?action=sync_cart', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cart: localCart })
          });
          const d = await r.json();
          if (d.status !== 'success') toast('Sync warning: ' + d.message, 'warning');
        } catch(e) { /* silent */ }
      }

      async function loadFromDb() {
        try {
          const r = await fetch('cart.php?action=get_cart&t=' + Date.now(), { cache: 'no-store' });
          const d = await r.json();
          cart = (d.status === 'success') ? d.cart : [];
        } catch(e) { cart = []; }
      }

      // ===== RENDER =====
      function render() {
        if (cart.length === 0) {
          if (cartContainer) { cartContainer.innerHTML = ''; cartContainer.style.display = 'none'; }
          if (emptyMsg)    emptyMsg.style.display    = 'block';
          if (checkoutBtn) checkoutBtn.style.display = 'none';
          if (cartActions) cartActions.style.display = 'none';
          return;
        }

        if (cartContainer) { cartContainer.style.display = 'block'; cartContainer.innerHTML = ''; }
        if (emptyMsg)    emptyMsg.style.display    = 'none';
        if (checkoutBtn) checkoutBtn.style.display = 'block';
        if (cartActions) cartActions.style.display = '';

        let hasOutOfStock = false;

        cart.forEach(item => {
          const id  = item.id || item.product_id;
          const img = (item.image || 'assets/img/product/placeholder.png').replace(/"/g, '&quot;');
          const nm  = (item.name  || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
          const pr  = parseFloat(item.price);
          const qt  = parseInt(item.quantity);
          const slg = item.slug || '';
          const stock = item.stock !== undefined ? parseInt(item.stock) : 1;
          const productUrl = slg ? `product-details/${slg}` : `product-details.php?id=${id}`;

          let qtyHtml = '';
          let totalHtml = '';

          if (stock <= 0) {
            hasOutOfStock = true;
            qtyHtml = `<span class="badge bg-danger p-2" style="font-size:0.85rem;">Out of Stock</span>`;
            totalHtml = `<span class="text-danger">₹0.00</span>`;
          } else {
            qtyHtml = `
                <div class="qty-control">
                  <button class="qty-btn decrease" data-product-id="${id}"><i class="bi bi-dash"></i></button>
                  <input type="number" class="qty-input quantity-input" value="${qt}" min="1" max="${stock}" data-product-id="${id}">
                  <button class="qty-btn increase" data-product-id="${id}"><i class="bi bi-plus"></i></button>
                </div>`;
            totalHtml = `₹${(pr * qt).toFixed(2)}`;
          }

          cartContainer.innerHTML += `
            <div class="cart-item-row" data-product-id="${id}">
              <div class="cart-item-product">
                <a href="${productUrl}" class="d-flex align-items-center text-decoration-none" style="gap: 14px;">
                  <img src="${img}" alt="${nm}" class="cart-item-img ${stock <= 0 ? 'opacity-50' : ''}">
                </a>
                <div class="cart-item-info">
                  <a href="${productUrl}" class="text-decoration-none"><h6 class="${stock <= 0 ? 'text-muted text-decoration-line-through' : ''}">${nm}</h6></a>
                  ${item.product_code ? `<div style="font-size: 0.85rem; color: #666; font-family: monospace; margin-bottom: 2px;">Code: ${item.product_code}</div>` : ''}
                  ${item.variant_info ? `<div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">${item.variant_info}</div>` : ''}
                  <button class="cart-item-remove remove-item" data-product-id="${id}">
                    <i class="bi bi-trash3"></i> Remove
                  </button>
                </div>
              </div>
              <div class="cart-item-price ${stock <= 0 ? 'text-muted text-decoration-line-through' : ''}">₹${pr.toFixed(2)}</div>
              <div>
                ${qtyHtml}
              </div>
              <div class="cart-item-total">${totalHtml}</div>
            </div>`;
        });

        if (checkoutBtn) {
          if (hasOutOfStock) {
            checkoutBtn.classList.add('disabled');
            checkoutBtn.style.pointerEvents = 'none';
            checkoutBtn.style.opacity = '0.5';
            checkoutBtn.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i> Remove Out of Stock items';
          } else {
            checkoutBtn.classList.remove('disabled');
            checkoutBtn.style.pointerEvents = 'auto';
            checkoutBtn.style.opacity = '1';
            checkoutBtn.innerHTML = 'Proceed to Checkout <i class="bi bi-arrow-right ms-2"></i>';
          }
        }

        bindEvents();
      }

      function bindEvents() {
        document.querySelectorAll('.remove-item').forEach(b =>
          b.addEventListener('click', () => removeItem(b.dataset.productId)));
        document.querySelectorAll('.qty-btn.increase').forEach(b =>
          b.addEventListener('click', () => changeQty(b.dataset.productId, 1)));
        document.querySelectorAll('.qty-btn.decrease').forEach(b =>
          b.addEventListener('click', () => changeQty(b.dataset.productId, -1)));
        document.querySelectorAll('.quantity-input').forEach(inp =>
          inp.addEventListener('change', () => setQty(inp.dataset.productId, parseInt(inp.value))));
      }

      // ===== ACTIONS =====
      function removeItem(pid) {
        if (isLoggedIn) {
          fetch('cart.php?action=remove_item', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: pid })
          }).then(r => r.json()).then(d => {
            if (d.status === 'success') {
              loadFromDb().then(() => { render(); calcSummary(); badge(); });
              toast('Item removed', 'info');
            } else toast('Error: ' + d.message, 'danger');
          }).catch(() => toast('Network error', 'danger'));
        } else {
          cart = cart.filter(i => i.id != pid);
          localStorage.setItem('cart', JSON.stringify(cart));
          render(); calcSummary(); badge();
          toast('Item removed', 'info');
        }
      }

      function changeQty(pid, delta) {
        const item = cart.find(i => (i.id || i.product_id) == pid);
        if (!item) return;
        const nq = parseInt(item.quantity) + delta;
        if (nq <= 0) { removeItem(pid); return; }
        if (isLoggedIn) {
          fetch('cart.php?action=update_quantity', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: pid, quantity: nq })
          }).then(r => r.json()).then(d => {
            if (d.status === 'success') loadFromDb().then(() => { render(); calcSummary(); badge(); });
            else toast('Error: ' + d.message, 'danger');
          });
        } else {
          item.quantity = nq;
          localStorage.setItem('cart', JSON.stringify(cart));
          render(); calcSummary(); badge();
        }
      }

      function setQty(pid, qty) {
        if (qty <= 0) { removeItem(pid); return; }
        if (isLoggedIn) {
          fetch('cart.php?action=update_quantity', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: pid, quantity: qty })
          }).then(r => r.json()).then(d => {
            if (d.status === 'success') loadFromDb().then(() => { render(); calcSummary(); badge(); });
          });
        } else {
          const item = cart.find(i => (i.id || i.product_id) == pid);
          if (item) { item.quantity = qty; localStorage.setItem('cart', JSON.stringify(cart)); render(); calcSummary(); badge(); }
        }
      }

      // ===== SUMMARY =====
      function calcSummary() {
        const sub  = cart.reduce((s, i) => {
          const stock = i.stock !== undefined ? parseInt(i.stock) : 1;
          if (stock <= 0) return s;
          return s + parseFloat(i.price) * parseInt(i.quantity);
        }, 0);
        
        let discount = 0;
        if (appliedCoupon) {
            if (appliedCoupon.type === 'percentage') {
                discount = sub * (appliedCoupon.value / 100);
                if (appliedCoupon.max_discount > 0 && discount > appliedCoupon.max_discount) {
                    discount = appliedCoupon.max_discount;
                }
            } else if (appliedCoupon.type === 'fixed') {
                discount = appliedCoupon.value;
            }
            if (discount > sub) {
                discount = sub;
            }
        }
        
        const taxRate = <?php echo $tax_percentage; ?> / 100;
        const tax  = sub * taxRate;
        const tot  = sub + tax - discount;
        
        if (subtotalEl) subtotalEl.textContent = `₹${sub.toFixed(2)}`;
        
        const discountEl = document.getElementById('discount');
        if (discountEl) {
            if (discount > 0) {
                discountEl.textContent = `-₹${discount.toFixed(2)}`;
                discountEl.style.color = '#dc3545';
            } else {
                discountEl.textContent = `-₹0.00`;
                discountEl.style.color = 'inherit';
            }
        }
        
        if (taxEl) {
            if (tax > 0) {
                taxEl.textContent = `₹${tax.toFixed(2)}`;
                taxEl.className = 'val';
                taxEl.style.fontSize = '';
            } else {
                taxEl.textContent = 'Included';
                taxEl.className = 'val text-success font-weight-medium';
                taxEl.style.fontSize = '0.9em';
            }
        }
        if (totalEl)    totalEl.textContent    = `₹${tot.toFixed(2)}`;
      }

      function badge() {
        const n = cart.reduce((s, i) => s + parseInt(i.quantity), 0);
        document.querySelectorAll('a[href="cart.php"] .badge, .cart-badge').forEach(b => {
          b.textContent = n;
          b.style.display = n > 0 ? 'inline' : 'none';
        });
      }



      const couponInput = document.getElementById('coupon-input');
      const applyCouponBtn = document.getElementById('apply-coupon');
      
      if (couponInput) {
        couponInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
      }
      
      if (applyCouponBtn && couponInput) {
        applyCouponBtn.addEventListener('click', async function() {
            const code = couponInput.value.trim();
            if (!code) {
                toast('Please enter a coupon code', 'warning');
                return;
            }
            
            const subtotal = cart.reduce((s, i) => {
                const stock = i.stock !== undefined ? parseInt(i.stock) : 1;
                if (stock <= 0) return s;
                return s + parseFloat(i.price) * parseInt(i.quantity);
            }, 0);
            
            try {
                applyCouponBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                applyCouponBtn.disabled = true;
                
                const response = await fetch('cart.php?action=apply_coupon', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ coupon_code: code, cart_subtotal: subtotal })
                });
                
                const data = await response.json();
                if (data.status === 'success') {
                    appliedCoupon = data.coupon;
                    toast(data.message, 'success');
                    calcSummary();
                } else {
                    toast(data.message, 'danger');
                    appliedCoupon = null;
                    calcSummary();
                }
            } catch (error) {
                toast('Error applying coupon', 'danger');
            } finally {
                applyCouponBtn.innerHTML = 'Apply';
                applyCouponBtn.disabled = false;
            }
        });
      }

      document.getElementById('clear-cart')?.addEventListener('click', () => {
        if (!confirm('Clear your entire cart?')) return;
        if (isLoggedIn) {
          fetch('cart.php?action=clear_cart', { method: 'POST' })
            .then(r => r.json()).then(d => {
              if (d.status === 'success') window.location.reload();
              else toast(d.message, 'danger');
            });
        } else {
          cart = []; localStorage.removeItem('cart'); window.location.reload();
        }
      });

      document.getElementById('update-cart')?.addEventListener('click', () => {
        render(); calcSummary(); toast('Cart refreshed', 'success');
      });

      // ===== TOAST =====
      function toast(message, type = 'success') {
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
          toastContainer = document.createElement('div');
          toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
          toastContainer.style.zIndex = '1080';
          document.body.appendChild(toastContainer);
        }
        
        let bgColor, iconClass, textColor;
        if (type === 'success') {
            bgColor = '#8AC53E';
            iconClass = 'bi-check-circle-fill';
            textColor = '#fff';
        } else if (type === 'error' || type === 'danger') {
            bgColor = '#e74c3c';
            iconClass = 'bi-exclamation-triangle-fill';
            textColor = '#fff';
        } else if (type === 'warning' || type === 'secondary') {
            bgColor = '#f39c12';
            iconClass = 'bi-exclamation-circle-fill';
            textColor = '#fff';
        } else {
            bgColor = '#2c3e50';
            iconClass = 'bi-info-circle-fill';
            textColor = '#fff';
        }

        const toast = document.createElement('div');
        toast.className = 'toast align-items-center border-0 mb-3';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.style.background = bgColor;
        toast.style.color = textColor;
        toast.style.borderRadius = '12px';
        toast.style.boxShadow = '0 10px 30px rgba(0,0,0,0.15)';
        toast.style.overflow = 'hidden';
        toast.style.animation = 'slideInUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
        
        toast.innerHTML = `
          <div class="d-flex align-items-center p-3">
            <div class="toast-icon me-3 fs-4 d-flex align-items-center justify-content-center">
              <i class="bi ${iconClass}"></i>
            </div>
            <div class="toast-body flex-grow-1 fs-6 fw-medium p-0 m-0" style="color: inherit; letter-spacing: 0.3px;">
              ${message}
            </div>
            <button type="button" class="btn-close ms-2 m-auto" data-bs-dismiss="toast" aria-label="Close" style="filter: ${textColor === '#fff' ? 'invert(1)' : 'none'}; opacity: 0.8;"></button>
          </div>
        `;
        
        if (!document.getElementById('toast-keyframes')) {
            const style = document.createElement('style');
            style.id = 'toast-keyframes';
            style.innerHTML = `
                @keyframes slideInUp {
                    from { transform: translateY(100%); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
        }

        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => {
          toast.remove();
        });
      }
    });
  </script>

</body>

</html>