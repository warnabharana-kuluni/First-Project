<?php
// ============================================================
// GENERATE INVOICE - Printable bill matching success page
// ============================================================

session_start();

// ============================================================
// AUTO-DETECT BASE URL
// ============================================================
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    $path = dirname(dirname($script_name));
    return $protocol . '://' . $host . $path . '/';
}
if (!defined('BASE_URL')) {
    define('BASE_URL', getBaseUrl());
}

require_once __DIR__ . "/../includes/db.php";

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function safeHtml($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function formatCustomDish($input) {
    $input = trim($input);
    if (empty($input)) return '—';

    $qty = 1;
    $name = $input;

    if (preg_match('/\(\s*x(\d+)\s*\)$/', $name, $matches)) {
        $qty = (int)$matches[1];
        $name = trim(preg_replace('/\(\s*x\d+\s*\)$/', '', $name));
    } elseif (preg_match('/\s*x(\d+)$/', $name, $matches)) {
        $qty = (int)$matches[1];
        $name = trim(preg_replace('/\s*x\d+$/', '', $name));
    }

    $name = trim($name, '() ');
    if (empty($name)) return $input;

    return $name . ' x' . $qty;
}

function formatPaymentMethod($method) {
    if (empty($method)) return 'Not specified';
    
    $method = trim($method);
    $method_lower = strtolower($method);
    
    $map = [
        'pay_at_restaurant' => 'Pay at Restaurant',
        'pay at restaurant' => 'Pay at Restaurant',
        'card' => 'Credit Card',
        'card payment' => 'Credit Card',
        'credit_card' => 'Credit Card',
        'credit card' => 'Credit Card',
        'online' => 'Online Payment',
        'online payment' => 'Online Payment',
        'cash' => 'Cash',
        'not specified' => 'Not specified'
    ];
    
    return $map[$method_lower] ?? $method;
}

// ============================================================
// GET INVOICE DATA
// ============================================================
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$download = isset($_GET['download']) ? (int)$_GET['download'] : 0;

if ($invoice_id == 0) {
    // Try to get from session
    $invoice_id = $_SESSION['invoice_id'] ?? 0;
}

if ($invoice_id == 0) {
    die('Invalid invoice ID.');
}

// Fetch invoice details
$query = "SELECT * FROM invoices WHERE id = $invoice_id";
$result = mysqli_query($conn, $query);
$invoice = mysqli_fetch_assoc($result);

if (!$invoice) {
    die('Invoice not found.');
}

// Decode items
$items = json_decode($invoice['items'], true);
if (!is_array($items)) {
    $items = [];
}

// Get reservation details
$booking_id = $invoice['booking_id'];
$reservation = null;
if ($booking_id > 0) {
    $res_query = "SELECT * FROM reservations WHERE id = $booking_id";
    $res_result = mysqli_query($conn, $res_query);
    $reservation = mysqli_fetch_assoc($res_result);
}

// Build dish list from items
$dish_list = [];
$custom_dish_value = '';
$total_amount = 0;

foreach ($items as $item) {
    $desc = $item['description'] ?? '';
    $qty = $item['qty'] ?? 1;
    $price = $item['price'] ?? 0;
    $total_amount += $price;
    
    if (stripos($desc, 'Table Reservation') !== false) {
        // Skip table reservation from dishes list (it's shown separately)
        continue;
    } elseif (stripos($desc, 'Custom Order') !== false || stripos($desc, 'Custom Food') !== false || stripos($desc, 'Custom Juice') !== false) {
        // This is a custom order
        $custom_dish_value = str_replace('Custom Order: ', '', $desc);
        if (empty($custom_dish_value)) {
            $custom_dish_value = $desc;
        }
    } elseif (stripos($desc, 'Menu Items:') !== false) {
        // This is menu items
        $menu_part = str_replace('Menu Items: ', '', $desc);
        $dish_list[] = $menu_part;
    } else {
        // Generic item
        $dish_list[] = $desc . ' (x' . $qty . ')';
    }
}

// If no dishes found, try to get from reservation
if (empty($dish_list) && $reservation) {
    $selected_dishes = $reservation['selected_dishes'] ?? '';
    if (!empty($selected_dishes) && $selected_dishes !== 'None') {
        $items_array = array_map('trim', explode(',', $selected_dishes));
        foreach ($items_array as $item) {
            if (stripos($item, 'Custom') === false) {
                $dish_list[] = $item;
            }
        }
    }
    // Get custom dish from reservation
    if (empty($custom_dish_value) && !empty($reservation['custom_dish'])) {
        $custom_dish_value = formatCustomDish($reservation['custom_dish']);
    }
}

// If still empty, show '—'
$dishes_display = !empty($dish_list) ? implode(', ', $dish_list) : '—';
$custom_display = !empty($custom_dish_value) ? $custom_dish_value : '—';

// Get customer info
$customer_name = $invoice['customer_name'] ?? ($reservation['name'] ?? 'Guest');
$customer_email = $invoice['customer_email'] ?? ($reservation['email'] ?? '');
$customer_phone = $invoice['customer_phone'] ?? ($reservation['phone'] ?? '');
$payment_method = formatPaymentMethod($invoice['payment_method'] ?? ($reservation['payment_method'] ?? 'Not specified'));
$status = ucfirst($invoice['payment_status'] ?? ($reservation['status'] ?? 'Pending'));
$total = $invoice['total'] ?? $total_amount;

// Get date info
$invoice_date = $invoice['invoice_date'] ?? date('Y-m-d H:i:s');
$res_date = $reservation['reservation_date'] ?? date('Y-m-d');
$res_time = $reservation['reservation_time'] ?? date('H:i:s');
$guests = $reservation['guests'] ?? 1;

// Build invoice number
$invoice_number = $invoice['invoice_number'] ?? ('INV-' . date('Ymd') . '-' . str_pad($invoice_id, 4, '0', STR_PAD_LEFT));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo safeHtml($invoice_number); ?> | Gourmet</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #050e0c;
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background-image: radial-gradient(circle at 20% 50%, rgba(212,175,55,0.03) 0%, transparent 60%);
        }
        .invoice-container {
            max-width: 700px;
            width: 100%;
            background: #0a1914;
            border-radius: 24px;
            border: 1px solid #1e4538;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            overflow: hidden;
            animation: fadeInUp 0.6s ease;
        }
        @keyframes fadeInUp { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }

        .invoice-header {
            background: linear-gradient(135deg, #0f1f18, #1a2f26);
            padding: 35px 40px 25px;
            border-bottom: 2px solid #1e4538;
            text-align: center;
        }
        .invoice-header .brand {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            color: #D4AF37;
            letter-spacing: 3px;
        }
        .invoice-header .tagline {
            color: #888;
            font-size: 13px;
            letter-spacing: 2px;
            margin-top: 2px;
        }
        .invoice-header .invoice-title {
            margin-top: 15px;
            font-size: 18px;
            color: #b0c4b1;
            font-weight: 300;
        }
        .invoice-header .invoice-number {
            font-size: 14px;
            color: #D4AF37;
            font-weight: 600;
        }
        .invoice-header .invoice-date {
            font-size: 13px;
            color: #888;
        }

        .invoice-body {
            padding: 30px 40px 35px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 30px;
            margin-bottom: 20px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
            padding: 6px 0;
            border-bottom: 1px solid rgba(30,69,56,0.2);
        }
        .detail-item .label {
            font-size: 11px;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 1px;
            font-weight: 600;
        }
        .detail-item .value {
            font-size: 15px;
            color: #fff;
            font-weight: 500;
            margin-top: 1px;
        }
        .detail-item .value.gold {
            color: #D4AF37;
        }
        .detail-item .value.small {
            font-size: 13px;
        }

        .divider {
            border: none;
            border-top: 1px solid #1e4538;
            margin: 18px 0;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 0 8px;
            border-top: 2px solid rgba(212,175,55,0.25);
            margin-top: 10px;
        }
        .total-row .label {
            font-size: 18px;
            font-weight: 600;
            color: #fff;
        }
        .total-row .value {
            font-size: 32px;
            font-weight: 700;
            color: #D4AF37;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            background: rgba(46,204,113,0.15);
            color: #2ecc71;
            border: 1px solid rgba(46,204,113,0.2);
        }
        .status-badge.pending {
            background: rgba(243,156,18,0.15);
            color: #f39c12;
            border-color: rgba(243,156,18,0.2);
        }
        .status-badge.cancelled {
            background: rgba(231,76,60,0.15);
            color: #e74c3c;
            border-color: rgba(231,76,60,0.2);
        }

        .invoice-footer {
            padding: 20px 40px 30px;
            border-top: 1px solid #1e4538;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
        }
        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #D4AF37, #b8962e);
            color: #0a1914;
        }
        .btn-primary:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212,175,55,0.3);
        }
        .btn-secondary {
            background: transparent;
            color: #b0c4b1;
            border: 1px solid #2a2a2a;
        }
        .btn-secondary:hover {
            border-color: #D4AF37;
            color: #D4AF37;
        }
        .btn-success {
            background: transparent;
            color: #2ecc71;
            border: 1px solid #2ecc71;
        }
        .btn-success:hover {
            background: #2ecc71;
            color: #0a1914;
        }
        .btn i { margin-right: 8px; }

        .print-note {
            text-align: center;
            color: #555;
            font-size: 12px;
            padding: 0 40px 25px;
        }
        .print-note i { color: #D4AF37; margin-right: 4px; }

        @media (max-width: 600px) {
            .invoice-header { padding: 25px 20px 20px; }
            .invoice-body { padding: 20px; }
            .invoice-footer { padding: 15px 20px 20px; flex-direction: column; }
            .detail-grid { grid-template-columns: 1fr; gap: 4px; }
            .total-row .value { font-size: 24px; }
            .btn { width: 100%; text-align: center; justify-content: center; }
            .invoice-header .brand { font-size: 26px; }
        }

        @media print {
            body { background: #fff; padding: 20px; }
            .invoice-container { box-shadow: none; border: 1px solid #ddd; }
            .invoice-header { background: #f8f8f8; border-bottom: 2px solid #ddd; }
            .invoice-header .brand { color: #333; }
            .invoice-header .tagline { color: #999; }
            .invoice-body { background: #fff; }
            .detail-item .value { color: #333; }
            .detail-item .value.gold { color: #D4AF37; }
            .total-row .label { color: #333; }
            .total-row .value { color: #D4AF37; }
            .invoice-footer { border-top: 1px solid #ddd; }
            .btn { display: none; }
            .print-note { display: none; }
            .status-badge { border-color: #ddd; }
            .divider { border-top-color: #ddd; }
            .total-row { border-top-color: #D4AF37; }
        }
    </style>
</head>
<body>

    <div class="invoice-container" id="invoiceContent">
        <!-- HEADER -->
        <div class="invoice-header">
            <div class="brand">GOURMET</div>
            <div class="tagline">Where every dish tells a story</div>
            <div class="invoice-title">Reservation Invoice</div>
            <div class="invoice-number"><?php echo safeHtml($invoice_number); ?></div>
            <div class="invoice-date"><?php echo date('d M Y, h:i A', strtotime($invoice_date)); ?></div>
        </div>

        <!-- BODY -->
        <div class="invoice-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="label"><i class="fas fa-user"></i> Name</span>
                    <span class="value"><?php echo safeHtml($customer_name); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-envelope"></i> Email</span>
                    <span class="value small"><?php echo safeHtml($customer_email); ?></span>
                </div>
                <?php if (!empty($customer_phone)): ?>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value"><?php echo safeHtml($customer_phone); ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-users"></i> Guests</span>
                    <span class="value"><?php echo (int)$guests; ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-calendar-alt"></i> Date</span>
                    <span class="value"><?php echo date('d M Y', strtotime($res_date)); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-clock"></i> Time</span>
                    <span class="value"><?php echo date('h:i A', strtotime($res_time)); ?></span>
                </div>
            </div>

            <hr class="divider">

            <!-- Dishes -->
            <div class="detail-item" style="border-bottom: none; padding-bottom: 8px;">
                <span class="label"><i class="fas fa-utensils"></i> Selected Dishes</span>
                <span class="value small" style="font-weight: 400; color: #b0c4b1;"><?php echo safeHtml($dishes_display); ?></span>
            </div>

            <!-- Custom Order (if present) -->
            <?php if (!empty($custom_dish_value) && $custom_dish_value !== '—'): ?>
            <div class="detail-item" style="border-bottom: none; padding-top: 4px; padding-bottom: 8px;">
                <span class="label"><i class="fas fa-pen"></i> Custom Order</span>
                <span class="value small" style="font-weight: 400; color: #D4AF37;"><?php echo safeHtml($custom_dish_value); ?></span>
            </div>
            <?php endif; ?>

            <hr class="divider">

            <!-- Payment & Status -->
            <div class="detail-grid" style="margin-bottom: 0;">
                <div class="detail-item" style="border-bottom: none;">
                    <span class="label"><i class="fas fa-credit-card"></i> Payment Method</span>
                    <span class="value gold"><?php echo safeHtml($payment_method); ?></span>
                </div>
                <div class="detail-item" style="border-bottom: none;">
                    <span class="label"><i class="fas fa-circle"></i> Status</span>
                    <span class="value"><span class="status-badge <?php echo strtolower($status); ?>"><?php echo safeHtml($status); ?></span></span>
                </div>
            </div>

            <!-- TOTAL -->
            <div class="total-row">
                <span class="label">Total Amount</span>
                <span class="value">LKR <?php echo number_format($total, 2); ?></span>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="invoice-footer">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-download"></i> Download PDF (Print)
            </button>
            <a href="<?php echo BASE_URL; ?>public/index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Return Home
            </a>
            <a href="<?php echo BASE_URL; ?>public/success.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Confirmation
            </a>
        </div>

        <div class="print-note">
            <i class="fas fa-print"></i> Click "Download PDF (Print)" to save as PDF or print this invoice.
        </div>
    </div>

    <script>
        // Auto-print if download parameter is set
        <?php if ($download == 1): ?>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
        <?php endif; ?>
    </script>

</body>
</html>