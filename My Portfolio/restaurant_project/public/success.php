<?php
// ============================================================
// SUCCESS PAGE - Payment Confirmation & Invoice Creation
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

function generateInvoiceNumber() {
    return 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function createInvoice($conn, $data) {
    $invoice_number = generateInvoiceNumber();
    
    $customer_name = mysqli_real_escape_string($conn, $data['customer_name']);
    $customer_email = mysqli_real_escape_string($conn, $data['customer_email']);
    $customer_phone = mysqli_real_escape_string($conn, $data['customer_phone'] ?? '');
    $items_json = mysqli_real_escape_string($conn, json_encode($data['items']));
    $subtotal = (float)$data['subtotal'];
    $tax = (float)$data['tax'];
    $total = (float)$data['total'];
    $payment_method = mysqli_real_escape_string($conn, $data['payment_method']);
    $payment_status = mysqli_real_escape_string($conn, $data['payment_status'] ?? 'Pending');
    $booking_id = (int)($data['booking_id'] ?? 0);
    
    $query = "INSERT INTO invoices (
        booking_id, 
        customer_name, 
        customer_email, 
        customer_phone, 
        invoice_number, 
        items, 
        subtotal, 
        tax, 
        total, 
        payment_method, 
        payment_status, 
        invoice_date
    ) VALUES (
        $booking_id,
        '$customer_name',
        '$customer_email',
        '$customer_phone',
        '$invoice_number',
        '$items_json',
        $subtotal,
        $tax,
        $total,
        '$payment_method',
        '$payment_status',
        NOW()
    )";
    
    if (mysqli_query($conn, $query)) {
        return mysqli_insert_id($conn);
    }
    return false;
}

// ============================================================
// HANDLE FORM SUBMISSION (from payment.php)
// ============================================================
$payment_method = $_POST['payment_method'] ?? $_SESSION['payment_method'] ?? 'Not specified';
$total_amount = $_POST['total_amount'] ?? $_SESSION['payment_total'] ?? 0;
$selected_dishes = $_POST['selected_dishes'] ?? $_SESSION['payment_dishes'] ?? '';
$custom_dish = $_POST['custom_dish'] ?? $_SESSION['payment_custom'] ?? '';
$invoice_id = $_POST['invoice_created'] ?? 0;

// Get customer info from session
$customer_name = $_SESSION['res_name'] ?? 'Guest';
$customer_email = $_SESSION['res_email'] ?? '';
$customer_phone = $_SESSION['res_phone'] ?? '';
$booking_id = $_SESSION['booking_id'] ?? 0;
$guest_count = $_SESSION['res_guests'] ?? 1;
$res_date = $_SESSION['res_date'] ?? date('Y-m-d H:i:s');

// ============================================================
// 🔥 UPDATE RESERVATION WITH PAYMENT METHOD
// ============================================================
if ($booking_id > 0) {
    $update_query = "UPDATE reservations SET 
                        payment_method = '" . mysqli_real_escape_string($conn, $payment_method) . "',
                        total_amount = " . (float)$total_amount . "
                     WHERE id = $booking_id AND (payment_method IS NULL OR payment_method = '' OR payment_method = 'Not specified')";
    mysqli_query($conn, $update_query);
} else {
    $res_name = mysqli_real_escape_string($conn, $customer_name);
    $res_email = mysqli_real_escape_string($conn, $customer_email);
    $res_date_only = date('Y-m-d', strtotime($res_date));
    $res_time_only = date('H:i:s', strtotime($res_date));
    
    $find_query = "SELECT id FROM reservations 
                   WHERE name = '$res_name' 
                     AND email = '$res_email' 
                     AND reservation_date = '$res_date_only' 
                     AND reservation_time = '$res_time_only' 
                     AND status = 'pending'
                   ORDER BY id DESC LIMIT 1";
    $find_result = mysqli_query($conn, $find_query);
    if ($find_result && mysqli_num_rows($find_result) > 0) {
        $found = mysqli_fetch_assoc($find_result);
        $booking_id = $found['id'];
        $_SESSION['booking_id'] = $booking_id;
        
        $update_query = "UPDATE reservations SET 
                            payment_method = '" . mysqli_real_escape_string($conn, $payment_method) . "',
                            total_amount = " . (float)$total_amount . "
                         WHERE id = $booking_id";
        mysqli_query($conn, $update_query);
    }
}

// ============================================================
// CREATE INVOICE (if not already created)
// ============================================================
if ($invoice_id == 0) {
    $items = [];
    
    $table_price = $_SESSION['payment_table_price'] ?? 0;
    if ($table_price > 0) {
        $items[] = [
            'description' => 'Table Reservation',
            'qty' => 1,
            'price' => $table_price
        ];
    }
    
    $menu_total = $_SESSION['payment_menu_total'] ?? 0;
    if ($menu_total > 0 && !empty($selected_dishes)) {
        $items[] = [
            'description' => 'Menu Items: ' . $selected_dishes,
            'qty' => 1,
            'price' => $menu_total
        ];
    }
    
    $custom_total = $_SESSION['payment_custom_total'] ?? 0;
    if ($custom_total > 0 && !empty($custom_dish)) {
        $items[] = [
            'description' => 'Custom Order: ' . $custom_dish,
            'qty' => 1,
            'price' => $custom_total
        ];
    }
    
    if (empty($items)) {
        $items[] = [
            'description' => 'Reservation Payment',
            'qty' => 1,
            'price' => $total_amount
        ];
    }
    
    $invoice_data = [
        'customer_name' => $customer_name,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'items' => $items,
        'subtotal' => $total_amount,
        'tax' => 0,
        'total' => $total_amount,
        'payment_method' => $payment_method,
        'payment_status' => 'Paid',
        'booking_id' => $booking_id
    ];
    
    $invoice_id = createInvoice($conn, $invoice_data);
    $_SESSION['invoice_id'] = $invoice_id;
}

// ============================================================
// CLEAR SESSION DATA (keep only what's needed)
// ============================================================
$_SESSION['payment_complete'] = true;
$_SESSION['final_payment_method'] = $payment_method;
$_SESSION['final_total'] = $total_amount;

// ============================================================
// DISPLAY SUCCESS PAGE
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful | Gourmet Fine Dining</title>
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
            text-align: center;
            background-image: radial-gradient(circle at 20% 50%, rgba(212,175,55,0.05) 0%, transparent 60%);
        }
        .success-card {
            max-width: 550px;
            background: #0a1914;
            padding: 50px 40px;
            border-radius: 24px;
            border: 1px solid #1e4538;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            animation: fadeInUp 0.8s ease;
        }
        @keyframes fadeInUp { from { opacity:0; transform:translateY(40px); } to { opacity:1; transform:translateY(0); } }
        .success-card .icon {
            font-size: 72px;
            color: #2ecc71;
            margin-bottom: 10px;
            display: block;
        }
        .success-card h1 {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            color: #D4AF37;
            margin-bottom: 8px;
        }
        .success-card .subtitle {
            color: #888;
            font-size: 16px;
            margin-bottom: 25px;
        }
        .success-card .divider {
            border: none;
            border-top: 1px solid #1e4538;
            margin: 20px 0;
        }
        .success-card .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
            color: #b0c4b1;
        }
        .success-card .detail-row .label { color: #888; }
        .success-card .detail-row .value { color: #fff; font-weight: 500; }
        .success-card .detail-row .value.gold { color: #D4AF37; }
        .success-card .total-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0 5px;
            border-top: 2px solid rgba(212,175,55,0.2);
            margin-top: 10px;
        }
        .success-card .total-row .label { font-size: 18px; font-weight: 600; color: #fff; }
        .success-card .total-row .value { font-size: 28px; font-weight: 700; color: #D4AF37; }
        .btn-primary {
            display: inline-block;
            padding: 14px 40px;
            background: linear-gradient(135deg, #D4AF37, #b8962e);
            color: #0a1914;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            text-decoration: none;
            transition: 0.3s ease;
            margin-top: 20px;
        }
        .btn-primary:hover { background: #fff; transform: translateY(-3px); box-shadow: 0 10px 30px rgba(212,175,55,0.3); }
        .btn-secondary {
            display: inline-block;
            padding: 12px 30px;
            background: transparent;
            color: #D4AF37;
            border: 2px solid #D4AF37;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: 0.3s ease;
            margin: 10px 6px 0;
        }
        .btn-secondary:hover { background: #D4AF37; color: #0a1914; }
        .btn-download {
            display: inline-block;
            padding: 12px 30px;
            background: transparent;
            color: #2ecc71;
            border: 2px solid #2ecc71;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: 0.3s ease;
            margin: 10px 6px 0;
        }
        .btn-download:hover { background: #2ecc71; color: #0a1914; }
        .print-note { color: #555; font-size: 13px; margin-top: 20px; }
        .print-note i { color: #D4AF37; margin-right: 4px; }
        .button-group {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        @media (max-width: 600px) {
            .success-card { padding: 30px 20px; }
            .success-card h1 { font-size: 26px; }
            .success-card .total-row .value { font-size: 22px; }
            .success-card .detail-row { flex-wrap: wrap; gap: 4px; }
            .btn-primary, .btn-secondary, .btn-download { width: 100%; text-align: center; }
            .button-group { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>
    <div class="success-card">
        <span class="icon"><i class="fas fa-check-circle"></i></span>
        <h1>Payment Successful!</h1>
        <p class="subtitle">Your reservation has been confirmed. We look forward to serving you!</p>

        <hr class="divider">

        <div class="detail-row">
            <span class="label">Customer</span>
            <span class="value"><?php echo safeHtml($customer_name); ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Email</span>
            <span class="value"><?php echo safeHtml($customer_email); ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Payment Method</span>
            <span class="value gold"><?php echo safeHtml($payment_method); ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Date &amp; Time</span>
            <span class="value"><?php echo date('d M Y, h:i A', strtotime($res_date)); ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Guests</span>
            <span class="value"><?php echo (int)$guest_count; ?></span>
        </div>

        <?php if (!empty($selected_dishes)): ?>
        <div class="detail-row" style="flex-wrap:wrap; gap:4px;">
            <span class="label">Dishes</span>
            <span class="value" style="font-size:13px; text-align:right; max-width:60%;"><?php echo safeHtml($selected_dishes); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($custom_dish)): ?>
        <div class="detail-row" style="flex-wrap:wrap; gap:4px;">
            <span class="label">Custom Order</span>
            <span class="value" style="font-size:13px; text-align:right; max-width:60%;"><?php echo safeHtml($custom_dish); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($invoice_id > 0): ?>
        <div class="detail-row">
            <span class="label">Invoice #</span>
            <span class="value gold"><?php echo 'INV-' . date('Ymd') . '-' . str_pad($invoice_id, 4, '0', STR_PAD_LEFT); ?></span>
        </div>
        <?php endif; ?>

        <div class="total-row">
            <span class="label">Total Paid</span>
            <span class="value">LKR <?php echo number_format($total_amount, 2); ?></span>
        </div>

        <div class="button-group">
            <a href="<?php echo BASE_URL; ?>public/index.php" class="btn-primary">
                <i class="fas fa-home"></i> Back to Home
            </a>
            <?php if ($invoice_id > 0): ?>
            <a href="<?php echo BASE_URL; ?>public/generate_invoice.php?id=<?php echo $invoice_id; ?>&download=1" target="_blank" class="btn-download">
                <i class="fas fa-download"></i> Download Bill
            </a>
            <?php endif; ?>
        </div>

        <div class="print-note">
            <i class="fas fa-print"></i> A confirmation email has been sent to your email address.
        </div>
    </div>

    <!-- Clear session reservation data after showing success -->
    <script>
        setTimeout(function() {
            fetch('<?php echo BASE_URL; ?>public/clear_session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clear_reservation' })
            }).catch(() => {});
        }, 3000);
    </script>
</body>
</html>