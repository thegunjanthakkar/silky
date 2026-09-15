<?php
session_start();
require_once 'db_config.php';

// Check if order number is provided
if (!isset($_GET['order']) || empty($_GET['order'])) {
    die('Order number is required');
}

$order_number = mysqli_real_escape_string($conn, $_GET['order']);

// Fetch general settings (business email, phone, address)
$settings = [];
$settings_sql = "SELECT setting_key, setting_value FROM general_settings WHERE setting_key IN ('business_email', 'business_phone', 'business_address')";
$settings_result = mysqli_query($conn, $settings_sql);
if ($settings_result) {
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Set default values if settings don't exist
$business_email = $settings['business_email'] ?? 'info@silkysaree.com';
$business_phone = $settings['business_phone'] ?? '+91 1234567890';
$business_address = $settings['business_address'] ?? '';

// Fetch order details
$order_sql = "SELECT * FROM orders WHERE order_number = '$order_number'";
$order_result = mysqli_query($conn, $order_sql);

if (!$order_result || mysqli_num_rows($order_result) == 0) {
    die('Order not found');
}

$order = mysqli_fetch_assoc($order_result);

// Require logged in user and verify the order belongs to them (or allow admin access)
$is_admin = (!empty($_SESSION['admin_logged_in'])) || (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_role']));
$is_owner = isset($_SESSION['user_id']) && $order['user_id'] == $_SESSION['user_id'];

if (!$is_admin && !$is_owner) {
    die('Unauthorized access');
}

// Fetch order items with product_code
$items_sql = "SELECT oi.*, p.product_code 
              FROM order_items oi 
              LEFT JOIN products p ON oi.product_id = p.id 
              WHERE oi.order_id = " . $order['id'];
$items_result = mysqli_query($conn, $items_sql);
$order_items = [];
while ($item = mysqli_fetch_assoc($items_result)) {
    $order_items[] = $item;
}

// Parse shipping address
$shipping_address = json_decode($order['shipping_address'], true);

// Capture HTML output into buffer so we can render to PDF if requested
ob_start();
?>
<!DOCTYPE html>
<body lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo htmlspecialchars($order_number); ?></title>
    <style>
@page { size: A4; margin: 16mm; }

body {
    font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
    color: #111;
    font-size: 12px;
}

.invoice-container {
    width: 100%;
}

/* HEADER */
.header {
    display: table;
    width: 100%;
    margin-bottom: 20px;
}
.header-left,
.header-right {
    display: table-cell;
    vertical-align: top;
}
.header-right {
    text-align: right;
}

.company-name {
    font-size: 22px;
    font-weight: 700;
}
.company-sub {
    color: #666;
    margin-top: 4px;
}

.invoice-title {
    font-size: 20px;
    font-weight: 700;
}

/* ADDRESSES */
.address-section {
    display: table;
    width: 100%;
    margin-bottom: 20px;
}
.address-box {
    display: table-cell;
    width: 50%;
    vertical-align: top;
}
.address-box h4 {
    font-size: 11px;
    margin-bottom: 6px;
    text-transform: uppercase;
    color: #333;
}

/* TABLE */
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
}
th {
    text-align: left;
    border-bottom: 1px solid #ccc;
    padding: 8px 6px;
}
td {
    padding: 8px 6px;
    border-bottom: 1px solid #eee;
}

.text-right { text-align: right; }
.text-center { text-align: center; }

/* TOTALS */
.totals {
    width: 40%;
    margin-left: auto;
}
.totals table td {
    padding: 6px;
}
.totals .grand {
    font-weight: bold;
    border-top: 2px solid #000;
}

/* PAYMENT INFO */
.payment-box {
    margin-top: 20px;
    background: #f6f6f6;
    padding: 12px;
}
.payment-box h4 {
    margin-bottom: 8px;
}

/* FOOTER */
.footer {
    margin-top: 25px;
    text-align: center;
    color: #666;
    font-size: 11px;
}
.status-pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    background: #e5e5e5;
    font-size: 11px;
}
</style>

</head>
<body>
<div class="invoice-container">

    <!-- HEADER -->
    <div class="header">
        <div class="header-left">
            <div class="company-name">Silky Saree</div>
            <div class="company-sub">Premium Saree Collection</div>
            <div>Email: <?php echo htmlspecialchars($business_email); ?></div>
            <div>Phone: <?php echo htmlspecialchars($business_phone); ?></div>
        </div>
        <div class="header-right">
            <div class="invoice-title">INVOICE</div>
            <div><strong>Invoice #:</strong> <?php echo $order['order_number']; ?></div>
            <div><strong>Date:</strong> <?php echo date('F j, Y', strtotime($order['created_at'])); ?></div>
            <div><strong>Status:</strong>
                <span class="status-pill"><?php echo ucfirst($order['payment_status']); ?></span>
            </div>
        </div>
    </div>

    <!-- ADDRESSES -->
    <div class="address-section">
        <div class="address-box">
            <h4>Bill To</h4>
            <strong><?php echo $shipping_address['first_name'].' '.$shipping_address['last_name']; ?></strong><br>
            <?php echo $shipping_address['email']; ?><br>
            <?php echo $shipping_address['phone']; ?>
        </div>
        <div class="address-box">
            <h4>Ship To</h4>
            <strong><?php echo $shipping_address['first_name'].' '.$shipping_address['last_name']; ?></strong><br>
            <?php echo $shipping_address['street_address']; ?><br>
            <?php echo $shipping_address['city']; ?>, <?php echo $shipping_address['state']; ?> <?php echo $shipping_address['zip_code']; ?><br>
            <?php echo $shipping_address['country']; ?>
        </div>
    </div>

    <!-- ITEMS -->
    <table>
        <thead>
            <tr>
                <th>Item Description</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($order_items as $item): ?>
            <tr>
                <td>
                    <?php echo $item['product_name']; ?>
                    <?php if (!empty($item['product_code'])): ?>
                    <div style="font-size: 10px; color: #666; font-family: monospace;">Code: <?php echo htmlspecialchars($item['product_code']); ?></div>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?php echo $item['quantity']; ?></td>
                <td class="text-right">₹<?php echo number_format($item['product_price'],2); ?></td>
                <td class="text-right">₹<?php echo number_format($item['subtotal'],2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- TOTALS -->
    <div class="totals">
        <table>
            <tr>
                <td>Subtotal</td>
                <td class="text-right">₹<?php echo number_format($order['subtotal'],2); ?></td>
            </tr>
            <tr>
                <td>Shipping</td>
                <td class="text-right">₹<?php echo number_format($order['shipping_cost'],2); ?></td>
            </tr>
            <?php if (isset($order['discount_amount']) && $order['discount_amount'] > 0): ?>
            <tr>
                <td>Discount</td>
                <td class="text-right" style="color: #dc3545;">-₹<?php echo number_format($order['discount_amount'],2); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td>Tax</td>
                <td class="text-right">₹<?php echo number_format($order['tax_amount'],2); ?></td>
            </tr>
            <tr class="grand">
                <td>Total</td>
                <td class="text-right">₹<?php echo number_format($order['total_amount'],2); ?></td>
            </tr>
        </table>
    </div>

    <!-- PAYMENT INFO -->
   

    <!-- FOOTER -->
    <div class="footer">
        <strong>Thank you for your order!</strong><br>
        For questions, contact <?php echo htmlspecialchars($business_email); ?>
    </div>

</div>
</body>
</html>
<?php
// Capture HTML output (buffer was started earlier)
$html = ob_get_clean();

if (isset($_GET['download'])) {
    // If dompdf is available, use it to generate a real PDF
    // try multiple possible autoload locations (project root, dompdf subfolder, parent vendor)
    $possible_autoloads = [
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/dompdf/vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
    ];

    $found = false;
    foreach ($possible_autoloads as $a) {
        if (is_readable($a)) {
            require_once $a;
            $found = true;
            break;
        }
    }

    if ($found) {
        // instantiate Dompdf (fully-qualified to avoid namespace issues)
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();
        $filename = 'Invoice-' . preg_replace('/[^A-Za-z0-9-_]/', '', $order_number) . '.pdf';
        $dompdf->stream($filename, ["Attachment" => 1]);
        exit();
    }

    // Fallback: send the HTML with PDF headers (not a true PDF) and trigger print in browser
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Invoice-' . $order_number . '.pdf"');
    echo $html;
    exit();
}

// If not downloading, just output the HTML
echo $html;

?>
