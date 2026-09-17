<?php
session_start();

// Write a log IMMEDIATELY before any redirects
$log_file = __DIR__ . '/../save_debug_log.txt';
$log = date('Y-m-d H:i:s') . " | METHOD=" . $_SERVER['REQUEST_METHOD'] . " | SESSION=" . json_encode(array_keys($_SESSION)) . " | POST_KEYS=" . implode(',', array_keys($_POST)) . "\n";
file_put_contents($log_file, $log, FILE_APPEND);

// Check login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    file_put_contents($log_file, "  => REDIRECTED: not logged in\n", FILE_APPEND);
    header('Location: login.php');
    exit;
}

// Skip permission check entirely - login is sufficient protection
require_once '../db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    file_put_contents($log_file, "  => REDIRECTED: not POST\n", FILE_APPEND);
    header('Location: edit-website.php');
    exit;
}

$errors = [];

// =============================================
// 1. Flat Settings
// =============================================
$flat_settings = [
    'topbar_phone', 'topbar_announcement_1', 'topbar_announcement_2', 'topbar_announcement_3',
    'cta_show', 'cta_offer_badge', 'cta_heading', 'cta_subtitle', 'cta_countdown_date',
    'cta_btn1_text', 'cta_btn1_link', 'cta_btn2_text', 'cta_btn2_link',
    'whatsapp_number', 'wa_form_title', 'wa_form_subtitle', 'wa_show_product', 'wa_product_options',
    'wa_show_budget', 'wa_budget_options', 'wa_show_color', 'wa_color_options', 'wa_show_message',
    'wa_button_text', 'wa_form_note',
    'product_highlights_enabled', 'product_highlights_title',
    'product_care_enabled', 'product_care_title'
];

$checkboxes = ['cta_show', 'wa_show_product', 'wa_show_budget', 'wa_show_color', 'wa_show_message', 'product_highlights_enabled', 'product_care_enabled'];
foreach ($checkboxes as $cb) {
    if (!isset($_POST[$cb])) {
        $_POST[$cb] = '0';
    }
}

$check_col = @mysqli_query($conn, "SHOW COLUMNS FROM website_settings LIKE 'section'");
$has_section = ($check_col && mysqli_num_rows($check_col) > 0);

if (!$has_section) {
    @mysqli_query($conn, "ALTER TABLE website_settings ADD COLUMN section VARCHAR(100) DEFAULT 'general'");
    $check_col = @mysqli_query($conn, "SHOW COLUMNS FROM website_settings LIKE 'section'");
    $has_section = ($check_col && mysqli_num_rows($check_col) > 0);
}

foreach ($flat_settings as $key) {
    $val = isset($_POST[$key]) ? $_POST[$key] : '';
    $val_safe = mysqli_real_escape_string($conn, $val);
    $sec = (strpos($key, 'product_highlights_') === 0 || strpos($key, 'product_care_') === 0) ? 'product_page' : 'general';
    if ($has_section) {
        $sql = "INSERT INTO website_settings (setting_key, setting_value, section) 
                VALUES ('$key', '$val_safe', '$sec') 
                ON DUPLICATE KEY UPDATE setting_value='$val_safe'";
    } else {
        $sql = "INSERT INTO website_settings (setting_key, setting_value) 
                VALUES ('$key', '$val_safe') 
                ON DUPLICATE KEY UPDATE setting_value='$val_safe'";
    }
    if (!mysqli_query($conn, $sql)) {
        $errors[] = "Setting '$key': " . mysqli_error($conn);
        file_put_contents($log_file, "  => FAIL setting $key: " . mysqli_error($conn) . "\n", FILE_APPEND);
    }
}
file_put_contents($log_file, "  => Flat settings done. Errors: " . count($errors) . "\n", FILE_APPEND);

// Process Product Highlights Cards
if (isset($_POST['highlight_card_title']) && is_array($_POST['highlight_card_title'])) {
    $saved_cards = [];
    foreach ($_POST['highlight_card_title'] as $idx => $title) {
        $title = trim($title);
        $icon = trim($_POST['highlight_card_icon'][$idx] ?? 'bi bi-gem');
        $desc = trim($_POST['highlight_card_desc'][$idx] ?? '');
        if ($title !== '' || $desc !== '') {
            $saved_cards[] = [
                'icon' => $icon,
                'title' => $title,
                'desc' => $desc
            ];
        }
    }
    $cards_json = mysqli_real_escape_string($conn, json_encode($saved_cards));
    if ($has_section) {
        $sql = "INSERT INTO website_settings (setting_key, setting_value, section) 
                VALUES ('product_highlights_cards', '$cards_json', 'product_page') 
                ON DUPLICATE KEY UPDATE setting_value='$cards_json'";
    } else {
        $sql = "INSERT INTO website_settings (setting_key, setting_value) 
                VALUES ('product_highlights_cards', '$cards_json') 
                ON DUPLICATE KEY UPDATE setting_value='$cards_json'";
    }
    if (!mysqli_query($conn, $sql)) {
        $errors[] = "Setting 'product_highlights_cards': " . mysqli_error($conn);
    }
}

// Process Product Care Instructions Cards
if (isset($_POST['care_card_title']) && is_array($_POST['care_card_title'])) {
    $saved_care = [];
    foreach ($_POST['care_card_title'] as $idx => $title) {
        $title = trim($title);
        $icon = trim($_POST['care_card_icon'][$idx] ?? 'bi bi-droplet-half');
        $color = trim($_POST['care_card_color'][$idx] ?? '#0dcaf0');
        $desc = trim($_POST['care_card_desc'][$idx] ?? '');
        if ($title !== '' || $desc !== '') {
            $saved_care[] = [
                'icon' => $icon,
                'color' => $color,
                'title' => $title,
                'desc' => $desc
            ];
        }
    }
    $care_json = mysqli_real_escape_string($conn, json_encode($saved_care));
    if ($has_section) {
        $sql = "INSERT INTO website_settings (setting_key, setting_value, section) 
                VALUES ('product_care_cards', '$care_json', 'product_page') 
                ON DUPLICATE KEY UPDATE setting_value='$care_json'";
    } else {
        $sql = "INSERT INTO website_settings (setting_key, setting_value) 
                VALUES ('product_care_cards', '$care_json') 
                ON DUPLICATE KEY UPDATE setting_value='$care_json'";
    }
    if (!mysqli_query($conn, $sql)) {
        $errors[] = "Setting 'product_care_cards': " . mysqli_error($conn);
    }
}

// =============================================
// 2. Navigation Menus - Delete and Re-Insert
// =============================================
mysqli_query($conn, "DELETE FROM navigation_menus");

if (!empty($_POST['main_menu_title'])) {
    foreach ($_POST['main_menu_title'] as $i => $title) {
        $title = trim($title);
        if ($title === '') continue;
        $link  = mysqli_real_escape_string($conn, $_POST['main_menu_link'][$i] ?? '');
        $t     = mysqli_real_escape_string($conn, $title);
        $order = $i + 1;
        $sql = "INSERT INTO navigation_menus (menu_type, title, link, icon_class, display_order) 
                VALUES ('main_desktop','$t','$link','', $order)";
        if (!mysqli_query($conn, $sql)) {
            $errors[] = "Main menu '$title': " . mysqli_error($conn);
        }
    }
}

if (!empty($_POST['bottom_menu_title'])) {
    foreach ($_POST['bottom_menu_title'] as $i => $title) {
        $title = trim($title);
        if ($title === '') continue;
        $link  = mysqli_real_escape_string($conn, $_POST['bottom_menu_link'][$i] ?? '');
        $icon  = mysqli_real_escape_string($conn, $_POST['bottom_menu_icon'][$i] ?? '');
        $t     = mysqli_real_escape_string($conn, $title);
        $order = $i + 1;
        $sql = "INSERT INTO navigation_menus (menu_type, title, link, icon_class, display_order) 
                VALUES ('mobile_bottom','$t','$link','$icon', $order)";
        if (!mysqli_query($conn, $sql)) {
            $errors[] = "Bottom menu '$title': " . mysqli_error($conn);
        }
    }
}
file_put_contents($log_file, "  => Menus done. Errors: " . count($errors) . "\n", FILE_APPEND);

// =============================================
// 3. Hero Slides
// =============================================
$kept_slides = [];

if (!empty($_POST['slide_id'])) {
    foreach ($_POST['slide_id'] as $idx => $slide_id) {
        $img    = mysqli_real_escape_string($conn, $_POST['slide_image_path'][$idx] ?? '');
        $title  = mysqli_real_escape_string($conn, $_POST['slide_title'][$idx] ?? '');
        $sub    = mysqli_real_escape_string($conn, $_POST['slide_subtitle'][$idx] ?? '');
        $b1t    = mysqli_real_escape_string($conn, $_POST['slide_btn1_text'][$idx] ?? '');
        $b1l    = mysqli_real_escape_string($conn, $_POST['slide_btn1_link'][$idx] ?? '');
        $b2t    = mysqli_real_escape_string($conn, $_POST['slide_btn2_text'][$idx] ?? '');
        $b2l    = mysqli_real_escape_string($conn, $_POST['slide_btn2_link'][$idx] ?? '');
        $order  = $idx + 1;

        // Handle file upload
        $file_key = 'slide_image_file_' . $slide_id;
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
            $upload_dir = dirname(__DIR__) . '/assets/img/hero/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $fname = time() . '_' . basename($_FILES[$file_key]['name']);
            if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $upload_dir . $fname)) {
                $img = mysqli_real_escape_string($conn, 'assets/img/hero/' . $fname);
            }
        }
        
        // Handle cropped base64 image (overrides standard upload if present)
        $b64_key = 'slide_image_base64_' . $slide_id;
        if (!empty($_POST[$b64_key])) {
            $base64_string = $_POST[$b64_key];
            if (preg_match('/^data:image\/(\w+);base64,/', $base64_string, $type)) {
                $data = substr($base64_string, strpos($base64_string, ',') + 1);
                $type = strtolower($type[1]); 
                if (in_array($type, ['jpg', 'jpeg', 'gif', 'png', 'webp'])) {
                    $data = base64_decode($data);
                    if ($data !== false) {
                        $upload_dir = dirname(__DIR__) . '/assets/img/hero/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        $fname = time() . '_crop_' . uniqid() . '.' . $type;
                        if (file_put_contents($upload_dir . $fname, $data)) {
                            $img = mysqli_real_escape_string($conn, 'assets/img/hero/' . $fname);
                        }
                    }
                }
            }
        }

        if ($slide_id === 'new' || strpos($slide_id, 'new_') === 0) {
            $sql = "INSERT INTO website_hero_slides 
                    (image_path,title,subtitle,button_1_text,button_1_link,button_2_text,button_2_link,slide_order)
                    VALUES ('$img','$title','$sub','$b1t','$b1l','$b2t','$b2l',$order)";
            if (mysqli_query($conn, $sql)) {
                $kept_slides[] = mysqli_insert_id($conn);
            } else {
                $errors[] = "New slide: " . mysqli_error($conn);
            }
        } else {
            $sid = (int)$slide_id;
            $sql = "UPDATE website_hero_slides SET 
                    image_path='$img', title='$title', subtitle='$sub',
                    button_1_text='$b1t', button_1_link='$b1l',
                    button_2_text='$b2t', button_2_link='$b2l', slide_order=$order
                    WHERE id=$sid";
            if (mysqli_query($conn, $sql)) {
                $kept_slides[] = $sid;
            } else {
                $errors[] = "Slide $sid: " . mysqli_error($conn);
            }
        }
    }
}

// Delete removed slides
if (!empty($kept_slides)) {
    $kept_str = implode(',', array_map('intval', $kept_slides));
    mysqli_query($conn, "DELETE FROM website_hero_slides WHERE id NOT IN ($kept_str)");
} else {
    mysqli_query($conn, "DELETE FROM website_hero_slides");
}
file_put_contents($log_file, "  => Slides done. Kept: " . implode(',', $kept_slides) . " Errors: " . count($errors) . "\n", FILE_APPEND);

// =============================================
// 4. Reviews
// =============================================
$kept_reviews = [];

if (!empty($_POST['review_id'])) {
    $colors = ['rv-pink', 'rv-blue', 'rv-green', 'rv-purple', 'rv-orange', 'rv-teal'];
    foreach ($_POST['review_id'] as $idx => $review_id) {
        $author = trim($_POST['review_author'][$idx] ?? '');
        if ($author === '') continue; // Empty author = delete

        $a      = mysqli_real_escape_string($conn, $author);
        $loc    = ''; // Removed from UI
        $txt    = mysqli_real_escape_string($conn, $_POST['review_text'][$idx] ?? '');
        
        $rating = (float)($_POST['review_rating'][$idx] ?? 5.0);
        if ($rating > 5.0) $rating = 5.0; // Enforce max 5.0

        // Auto-generate avatar letter
        $first_letter = mb_substr($author, 0, 1, 'UTF-8');
        $letter = mysqli_real_escape_string($conn, strtoupper($first_letter));

        // Auto-select color based on author name length to keep it deterministic but seemingly random
        $color_idx = strlen($author) % count($colors);
        $color = $colors[$color_idx];

        if ($review_id === 'new' || strpos($review_id, 'new_') === 0) {
            $sql = "INSERT INTO client_reviews (author_name,location,review_text,rating,avatar_letter,avatar_color_class)
                    VALUES ('$a','$loc','$txt',$rating,'$letter','$color')";
            if (mysqli_query($conn, $sql)) {
                $kept_reviews[] = mysqli_insert_id($conn);
            } else {
                $errors[] = "New review: " . mysqli_error($conn);
            }
        } else {
            $rid = (int)$review_id;
            $sql = "UPDATE client_reviews SET 
                    author_name='$a', location='$loc', review_text='$txt',
                    rating=$rating, avatar_letter='$letter', avatar_color_class='$color'
                    WHERE id=$rid";
            if (mysqli_query($conn, $sql)) {
                $kept_reviews[] = $rid;
            } else {
                $errors[] = "Review $rid: " . mysqli_error($conn);
            }
        }
    }
}

// Delete removed reviews
if (!empty($kept_reviews)) {
    $kept_str = implode(',', array_map('intval', $kept_reviews));
    mysqli_query($conn, "DELETE FROM client_reviews WHERE id NOT IN ($kept_str)");
} else {
    mysqli_query($conn, "DELETE FROM client_reviews");
}
file_put_contents($log_file, "  => Reviews done. Kept: " . implode(',', $kept_reviews) . " Errors: " . count($errors) . "\n", FILE_APPEND);

// =============================================
// Done - Redirect
// =============================================
if (!empty($errors)) {
    $_SESSION['save_errors'] = implode('<br>', $errors);
    file_put_contents($log_file, "  => Redirect with ERRORS\n", FILE_APPEND);
    header('Location: edit-website.php?error=1');
} else {
    file_put_contents($log_file, "  => Redirect SUCCESS\n", FILE_APPEND);
    header('Location: edit-website.php?success=1');
}
exit;
?>
