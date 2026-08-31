<?php
// ============================================================
// PAYMENT PAGE - Full Advanced Version with Auto Invoice
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
if (!defined('BASE_URL')) { define('BASE_URL', getBaseUrl()); }

require_once __DIR__ . "/../includes/db.php";

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function safeHtml($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function getCardIcon($number) {
    $first = substr($number, 0, 1);
    $firstTwo = substr($number, 0, 2);
    if ($first == '4') return 'fab fa-cc-visa';
    if (in_array($firstTwo, ['51','52','53','54','55']) || ($firstTwo >= '22' && $firstTwo <= '27')) return 'fab fa-cc-mastercard';
    if (in_array($firstTwo, ['34','37'])) return 'fab fa-cc-amex';
    return 'fas fa-credit-card';
}

// ============================================================
// 🔥 INVOICE GENERATION FUNCTION
// ============================================================
function generateInvoiceNumber() {
    return 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function createInvoice($conn, $data) {
    // Generate unique invoice number
    $invoice_number = generateInvoiceNumber();
    
    // Escape data
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
// CHECK CART
// ============================================================
$has_items = false;
$guest_name = $_SESSION['res_name'] ?? 'Guest';
$guest_email = $_SESSION['res_email'] ?? '';
$guest_phone = $_SESSION['res_phone'] ?? '';
$table_price = $_SESSION['res_total'] ?? 0;
$guest_count = $_SESSION['res_guests'] ?? 1;
$res_date = $_SESSION['res_date'] ?? date('Y-m-d H:i:s');
$booking_id = $_SESSION['booking_id'] ?? 0;

// ============================================================
// CALCULATE TOTALS - 🔥 Grand Total = Table + Menu + Custom
// ============================================================
$selected_dishes = [];
$selected_items_array = []; // For invoice items
$total_menu_price = 0;
$custom_dish_value = '';
$custom_item_total = 0;
$grand_total = $table_price; // Start with table price

// 1. Process menu items
if (isset($_POST['selected_menu']) && is_array($_POST['selected_menu'])) {
    foreach ($_POST['selected_menu'] as $dish_name) {
        $dish_qty = isset($_POST['quantity'][$dish_name]) ? (int)$_POST['quantity'][$dish_name] : 1;
        if ($dish_qty < 1) $dish_qty = 1;
        $stmt = mysqli_prepare($conn, "SELECT price FROM menu WHERE item_name = ?");
        mysqli_stmt_bind_param($stmt, "s", $dish_name);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $item_price = (float)$row['price'];
            $total_menu_price += ($item_price * $dish_qty);
            $selected_dishes[] = $dish_name . " (x" . $dish_qty . ")";
            // Add to invoice items
            $selected_items_array[] = [
                'description' => $dish_name,
                'qty' => $dish_qty,
                'price' => $item_price
            ];
        }
        mysqli_stmt_close($stmt);
    }
    $grand_total += $total_menu_price; // Add menu items to grand total
    $has_items = true;
}

// 2. Process custom order
if (isset($_POST['is_custom_order']) && $_POST['is_custom_order'] == '1') {
    $custom_type = $_POST['custom_type'] ?? 'food';
    $custom_qty = max(1, (int)($_POST['custom_dish_qty'] ?? 1));
    $custom_base_price = ($custom_type === 'juice') ? 250.00 : 500.00;
    $ingredients_total = 0;
    $chosen_ingredients = [];
    $ingredient_prices = ['Chicken'=>800,'Cheese'=>400,'Seafood'=>1200,'Fresh Fruit'=>300,'Honey'=>100,'Ice Cream'=>250];
    if (isset($_POST['custom_ingredients']) && is_array($_POST['custom_ingredients'])) {
        foreach ($_POST['custom_ingredients'] as $ing) {
            if (isset($ingredient_prices[$ing])) {
                $chosen_ingredients[] = $ing;
                $ingredients_total += $ingredient_prices[$ing];
            }
        }
    }
    $single_price = $custom_base_price + $ingredients_total;
    $custom_item_total = $single_price * $custom_qty;
    $grand_total += $custom_item_total; // Add custom order to grand total
    
    $type_label = ($custom_type === 'juice') ? 'Custom Juice' : 'Custom Food';
    $ing_str = empty($chosen_ingredients) ? 'No Add-ons' : implode(', ', $chosen_ingredients);
    $notes = !empty($_POST['custom_menu_requests']) ? ' | Notes: ' . $_POST['custom_menu_requests'] : '';
    $custom_dish_value = $type_label . ' (' . $ing_str . ')' . $notes . ' (Qty: ' . $custom_qty . ')';
    $selected_dishes[] = $type_label . ' (x' . $custom_qty . ')';
    
    // Add to invoice items
    $selected_items_array[] = [
        'description' => $type_label . ' - ' . $ing_str,
        'qty' => $custom_qty,
        'price' => $single_price
    ];
    
    $has_items = true;
}

// If no items, redirect back to menu
if (!$has_items) {
    header("Location: " . BASE_URL . "public/menu.php?error=empty_cart");
    exit();
}

// ============================================================
// ✅ FINAL GRAND TOTAL = Table Price + Menu Items + Custom Order
// ============================================================
$dishes_string = implode(", ", $selected_dishes);
$formatted_amount = number_format($grand_total, 2, '.', '');
$grand_total_display = number_format($grand_total, 2);
$csrf_token = generateCSRFToken();

// Store in session for success page
$_SESSION['payment_total'] = $grand_total;
$_SESSION['payment_dishes'] = $dishes_string;
$_SESSION['payment_custom'] = $custom_dish_value;
$_SESSION['payment_items'] = $selected_items_array; // For invoice
$_SESSION['payment_table_price'] = $table_price;
$_SESSION['payment_menu_total'] = $total_menu_price;
$_SESSION['payment_custom_total'] = $custom_item_total;
$_SESSION['payment_booking_id'] = $booking_id;

// ============================================================
// 🔥 INVOICE PROCESSING - Create invoice right before redirect
// ============================================================
// This will be called when payment is confirmed (in form submission)
// Not on page load - to prevent duplicate invoices

// Check if invoice already created in this session
$invoice_created = isset($_SESSION['invoice_created']) && $_SESSION['invoice_created'] === true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment | Gourmet Fine Dining</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ===== GLOBAL ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #050e0c;
            color: #fff;
            min-height: 100vh;
            padding: 30px 20px;
            background-image: radial-gradient(circle at 20% 50%, rgba(212,175,55,0.03) 0%, transparent 60%);
        }
        .container { max-width: 1100px; margin: 0 auto; animation: fadeInUp 0.8s ease; }
        @keyframes fadeInUp { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }

        /* ===== HEADER ===== */
        .payment-header { text-align: center; margin-bottom: 35px; }
        .payment-header .icon { font-size: 52px; color: #D4AF37; margin-bottom: 6px; display:block; }
        .payment-header h1 { font-family: 'Playfair Display', serif; font-size: 38px; color: #D4AF37; letter-spacing: 2px; }
        .payment-header p { color: #888; font-size: 15px; }
        .payment-steps { display: flex; justify-content: center; gap: 30px; margin-top: 20px; flex-wrap: wrap; }
        .payment-steps .step { display: flex; align-items: center; gap: 8px; color: #555; font-size: 13px; }
        .payment-steps .step .num { width: 28px; height: 28px; border-radius:50%; background:#1e4538; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px; color:#888; }
        .payment-steps .step.active .num { background:#D4AF37; color:#0a1914; }
        .payment-steps .step.active { color:#D4AF37; }
        .payment-steps .step.done .num { background:#2ecc71; color:#fff; }
        .payment-steps .step .line { width:30px; height:2px; background:#1e4538; }
        .payment-steps .step.active .line { background:#D4AF37; }

        /* ===== PAYMENT GRID ===== */
        .payment-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 35px; }

        /* ===== SUMMARY CARD ===== */
        .summary-card {
            background: #0a1914;
            padding: 30px 28px;
            border-radius: 20px;
            border: 1px solid #1e4538;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            position: sticky;
            top: 30px;
            height: fit-content;
        }
        .summary-card h2 { font-family: 'Playfair Display', serif; font-size: 24px; color: #D4AF37; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .summary-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(30,69,56,0.3); font-size: 14px; }
        .summary-item:last-child { border-bottom: none; }
        .summary-item .label { color: #b0c4b1; }
        .summary-item .value { color: #fff; font-weight: 500; }
        .summary-item .value.gold { color: #D4AF37; }
        .summary-divider { border: none; border-top: 2px solid rgba(212,175,55,0.2); margin: 15px 0; }
        .summary-total { display: flex; justify-content: space-between; align-items: center; padding: 15px 0 5px; }
        .summary-total .label { font-size: 18px; font-weight: 600; color: #fff; }
        .summary-total .value { font-size: 32px; font-weight: 700; color: #D4AF37; }
        
        /* 🔥 Grand Total Breakdown */
        .total-breakdown { margin-top: 10px; padding: 12px 15px; background: rgba(212,175,55,0.05); border-radius: 10px; border: 1px solid rgba(212,175,55,0.1); }
        .total-breakdown .breakdown-item { display: flex; justify-content: space-between; font-size: 13px; color: #888; padding: 4px 0; }
        .total-breakdown .breakdown-item .amount { color: #b0c4b1; }
        .total-breakdown .breakdown-item.total { border-top: 1px solid rgba(212,175,55,0.2); margin-top: 6px; padding-top: 8px; font-weight: 600; color: #D4AF37; }
        .total-breakdown .breakdown-item.total .amount { color: #D4AF37; font-size: 16px; }

        .booking-summary {
            margin-top: 15px;
            padding: 15px;
            background: rgba(212,175,55,0.05);
            border-radius: 12px;
            border: 1px solid rgba(212,175,55,0.1);
        }
        .booking-summary .label { color: #888; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
        .booking-summary .name { font-size: 16px; font-weight: 600; color: #fff; }
        .booking-summary .details { display: flex; flex-wrap: wrap; gap: 15px; margin-top: 5px; font-size: 13px; color: #888; }
        .booking-summary .details i { color: #D4AF37; margin-right: 4px; }

        /* ===== PAYMENT OPTIONS ===== */
        .options-card {
            background: #0a1914;
            padding: 30px 28px;
            border-radius: 20px;
            border: 1px solid #1e4538;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .options-card h2 { font-family: 'Playfair Display', serif; font-size: 24px; color: #D4AF37; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .options-card .subtitle { color: #888; font-size: 14px; margin-bottom: 25px; }

        .payment-option {
            background: #111;
            border: 2px solid #1e4538;
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 12px;
            transition: 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .payment-option:hover { border-color: #D4AF37; background: rgba(212,175,55,0.05); transform: translateX(4px); }
        .payment-option .icon { font-size: 28px; color: #D4AF37; width: 45px; text-align: center; }
        .payment-option .info h4 { font-size: 16px; color: #fff; font-weight: 600; }
        .payment-option .info p { font-size: 13px; color: #888; }
        .payment-option .arrow { margin-left: auto; color: #555; transition: 0.3s; }
        .payment-option:hover .arrow { color: #D4AF37; transform: translateX(4px); }

        .btn-primary {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #D4AF37, #b8962e);
            color: #0a1914;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 18px;
            cursor: pointer;
            transition: 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 10px;
        }
        .btn-primary:hover { background: #fff; transform: translateY(-3px); box-shadow: 0 10px 30px rgba(212,175,55,0.3); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .btn-restaurant {
            width: 100%;
            padding: 16px;
            background: transparent;
            color: #D4AF37;
            border: 2px solid #D4AF37;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 10px;
        }
        .btn-restaurant:hover { background: #D4AF37; color: #0a1914; transform: translateY(-2px); }

        .secure-badge { text-align: center; margin-top: 20px; color: #555; font-size: 13px; }
        .secure-badge i { color: #2ecc71; margin-right: 4px; }

        /* ===== MODAL ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(6px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: modalFadeIn 0.4s ease;
        }
        .modal-overlay.active { display: flex; }
        @keyframes modalFadeIn { from { opacity:0; transform:scale(0.95); } to { opacity:1; transform:scale(1); } }

        .modal-content {
            background: #0a1914;
            padding: 35px 30px 30px;
            border-radius: 24px;
            border: 1px solid #1e4538;
            max-width: 500px;
            width: 92%;
            box-shadow: 0 30px 80px rgba(0,0,0,0.6);
            position: relative;
            overflow: hidden;
        }
        .modal-content::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #D4AF37, transparent);
        }

        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { font-family: 'Playfair Display', serif; font-size: 24px; color: #D4AF37; display: flex; align-items: center; gap: 10px; }
        .modal-header .close-btn { color: #888; font-size: 28px; cursor: pointer; transition: 0.3s; background: none; border: none; }
        .modal-header .close-btn:hover { color: #D4AF37; transform: rotate(90deg); }

        .modal-amount { text-align: center; margin-bottom: 25px; padding: 15px; background: rgba(212,175,55,0.05); border-radius: 12px; border: 1px solid rgba(212,175,55,0.1); }
        .modal-amount .amount-label { color: #888; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; }
        .modal-amount .amount-value { font-size: 36px; font-weight: 700; color: #D4AF37; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; color: #aaa; font-size: 13px; font-weight: 500; margin-bottom: 5px; }
        .form-group label i { margin-right: 6px; color: #D4AF37; }
        .form-group label .required { color: #e74c3c; margin-left: 4px; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 16px;
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            color: #fff;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: 0.3s;
        }
        .form-group input:focus, .form-group select:focus { border-color: #D4AF37; outline: none; box-shadow: 0 0 20px rgba(212,175,55,0.05); }
        .form-group input::placeholder { color: #555; }
        .form-group select option { background: #111; color: #fff; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }

        .card-type-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 4px;
        }
        .card-type-badge.visa { background: rgba(26,115,232,0.2); color: #1a73e8; border: 1px solid rgba(26,115,232,0.3); }
        .card-type-badge.mastercard { background: rgba(235,45,65,0.2); color: #eb2d41; border: 1px solid rgba(235,45,65,0.3); }
        .card-type-badge.amex { background: rgba(16,94,164,0.2); color: #105ea4; border: 1px solid rgba(16,94,164,0.3); }
        .card-type-badge.unknown { background: rgba(255,255,255,0.05); color: #666; border: 1px solid #2a2a2a; }

        .card-icon-display { font-size: 32px; margin-left: 10px; color: #555; transition: 0.3s; }
        .card-icon-display.active { color: #D4AF37; }

        .card-input-wrapper { display: flex; align-items: center; gap: 10px; }

        #processing-screen { display: none; text-align: center; padding: 30px 0; }
        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(212,175,55,0.15);
            border-top-color: #D4AF37;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        #processing-screen h3 { color: #D4AF37; font-size: 22px; margin-bottom: 5px; }
        #processing-screen p { color: #888; font-size: 15px; }

        .payment-success {
            display: none;
            text-align: center;
            padding: 20px 0;
        }
        .payment-success .check {
            font-size: 70px;
            color: #2ecc71;
            animation: checkPop 0.6s ease;
        }
        @keyframes checkPop { 0% { transform: scale(0) rotate(-180deg); opacity:0; } 100% { transform: scale(1) rotate(0deg); opacity:1; } }

        .back-link { display: inline-block; margin-top: 20px; color: #888; text-decoration: none; font-size: 14px; transition: 0.3s; }
        .back-link:hover { color: #D4AF37; }
        .back-link i { margin-right: 6px; }

        @media (max-width: 992px) {
            .payment-grid { grid-template-columns: 1fr; gap: 25px; }
            .summary-card { position: static; }
        }
        @media (max-width: 768px) {
            .payment-header h1 { font-size: 28px; }
            .payment-steps { gap: 15px; font-size: 12px; }
            .payment-steps .step .line { width: 15px; }
            .summary-total .value { font-size: 24px; }
            .modal-content { padding: 25px 18px; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .booking-summary .details { flex-direction: column; gap: 5px; }
            .container { padding: 0; }
        }
        @media (max-width: 480px) {
            .payment-header .icon { font-size: 38px; }
            .payment-header h1 { font-size: 22px; }
            .summary-card, .options-card { padding: 18px 14px; }
            .summary-total .value { font-size: 20px; }
            .payment-option { padding: 12px 14px; gap: 10px; }
            .payment-option .icon { font-size: 22px; width: 32px; }
            .modal-amount .amount-value { font-size: 28px; }
            .btn-primary, .btn-restaurant { font-size: 14px; padding: 12px; }
            .payment-steps .step { font-size: 10px; }
            .payment-steps .step .num { width: 22px; height: 22px; font-size: 10px; }
        }
    </style>
</head>
<body>

<div class="container">

    <!-- ===== HEADER ===== -->
    <div class="payment-header">
        <span class="icon"><i class="fas fa-credit-card"></i></span>
        <h1>Secure Payment</h1>
        <p>Complete your reservation with a secure payment</p>
        <div class="payment-steps">
            <span class="step done"><span class="num">✓</span> Reservation</span>
            <span class="step done"><span class="num">✓</span> Menu</span>
            <span class="step active"><span class="num">3</span> Payment</span>
            <span class="step"><span class="num">4</span> Confirmation</span>
        </div>
    </div>

    <!-- ===== PAYMENT GRID ===== -->
    <div class="payment-grid">

        <!-- LEFT: Order Summary -->
        <div class="summary-card">
            <h2><i class="fas fa-receipt"></i> Order Summary</h2>

            <div class="summary-item">
                <span class="label"><i class="fas fa-user"></i> Customer</span>
                <span class="value"><?php echo safeHtml($guest_name); ?></span>
            </div>
            <?php if (!empty($guest_email)): ?>
            <div class="summary-item">
                <span class="label"><i class="fas fa-envelope"></i> Email</span>
                <span class="value"><?php echo safeHtml($guest_email); ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-item">
                <span class="label"><i class="fas fa-users"></i> Guests</span>
                <span class="value"><?php echo (int)$guest_count; ?></span>
            </div>

            <hr class="summary-divider">

            <!-- 🔥 Table Charge -->
            <?php if ($table_price > 0): ?>
            <div class="summary-item">
                <span class="label"><i class="fas fa-chair"></i> Table Charge</span>
                <span class="value">LKR <?php echo number_format($table_price, 2); ?></span>
            </div>
            <?php endif; ?>

            <!-- 🔥 Menu Items Total -->
            <?php if ($total_menu_price > 0): ?>
            <div class="summary-item">
                <span class="label"><i class="fas fa-utensils"></i> Menu Items</span>
                <span class="value gold">LKR <?php echo number_format($total_menu_price, 2); ?></span>
            </div>
            <?php endif; ?>

            <!-- 🔥 Custom Order Total -->
            <?php if ($custom_item_total > 0): ?>
            <div class="summary-item">
                <span class="label"><i class="fas fa-pen"></i> Custom Order</span>
                <span class="value gold">LKR <?php echo number_format($custom_item_total, 2); ?></span>
            </div>
            <?php endif; ?>

            <!-- 🔥 Selected Dishes List -->
            <?php if (!empty($selected_dishes)): ?>
            <div class="summary-item" style="flex-wrap:wrap; gap:5px;">
                <span class="label"><i class="fas fa-list"></i> Items</span>
                <span class="value" style="font-size:12px; text-align:right; max-width:55%;"><?php echo safeHtml($dishes_string); ?></span>
            </div>
            <?php endif; ?>

            <!-- 🔥 Custom Dish Details -->
            <?php if (!empty($custom_dish_value)): ?>
            <div class="summary-item">
                <span class="label"><i class="fas fa-pen"></i> Custom Details</span>
                <span class="value" style="font-size:12px; text-align:right; max-width:55%;"><?php echo safeHtml($custom_dish_value); ?></span>
            </div>
            <?php endif; ?>

            <hr class="summary-divider">

            <!-- 🔥 GRAND TOTAL with Breakdown -->
            <div class="summary-total">
                <span class="label">Grand Total</span>
                <span class="value">LKR <?php echo $grand_total_display; ?></span>
            </div>

            <!-- 🔥 Breakdown Display -->
            <div class="total-breakdown">
                <div class="breakdown-item">
                    <span>Table Price</span>
                    <span class="amount">LKR <?php echo number_format($table_price, 2); ?></span>
                </div>
                <?php if ($total_menu_price > 0): ?>
                <div class="breakdown-item">
                    <span>Menu Items</span>
                    <span class="amount">LKR <?php echo number_format($total_menu_price, 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($custom_item_total > 0): ?>
                <div class="breakdown-item">
                    <span>Custom Order</span>
                    <span class="amount">LKR <?php echo number_format($custom_item_total, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="breakdown-item total">
                    <span>Total Amount</span>
                    <span class="amount">LKR <?php echo $grand_total_display; ?></span>
                </div>
            </div>

            <!-- Booking Summary -->
            <div class="booking-summary">
                <div class="label"><i class="fas fa-user-check"></i> Reservation For</div>
                <div class="name"><?php echo safeHtml($guest_name); ?></div>
                <div class="details">
                    <span><i class="fas fa-calendar-alt"></i> <?php echo date('d M Y', strtotime($res_date)); ?></span>
                    <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($res_date)); ?></span>
                    <span><i class="fas fa-users"></i> <?php echo (int)$guest_count; ?> guests</span>
                </div>
            </div>
        </div>

        <!-- RIGHT: Payment Options -->
        <div class="options-card">
            <h2><i class="fas fa-wallet"></i> Payment Options</h2>
            <p class="subtitle">Choose your preferred payment method</p>

            <!-- Card Payment -->
            <div class="payment-option" onclick="openPaymentModal()">
                <div class="icon"><i class="fas fa-credit-card"></i></div>
                <div class="info">
                    <h4>Pay with Card</h4>
                    <p>Visa, MasterCard, Amex</p>
                </div>
                <div class="arrow"><i class="fas fa-chevron-right"></i></div>
            </div>

            <!-- PayPal -->
            <div class="payment-option" onclick="alert('PayPal payment will be available soon. Please use Card or Pay at Restaurant.')">
                <div class="icon"><i class="fab fa-paypal"></i></div>
                <div class="info">
                    <h4>PayPal</h4>
                    <p>Coming soon</p>
                </div>
                <div class="arrow"><i class="fas fa-chevron-right"></i></div>
            </div>

            <!-- 🔥 Pay at Restaurant - Modified to include invoice data -->
            <form method="post" action="<?php echo BASE_URL; ?>public/success.php" id="restaurantPaymentForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="payment_method" value="Pay at Restaurant">
                <input type="hidden" name="total_amount" value="<?php echo $formatted_amount; ?>">
                <input type="hidden" name="selected_dishes" value="<?php echo safeHtml($dishes_string); ?>">
                <input type="hidden" name="custom_dish" value="<?php echo safeHtml($custom_dish_value); ?>">
                <input type="hidden" name="invoice_created" id="invoiceCreatedRestaurant" value="0">
                <button type="submit" class="btn-restaurant" id="restaurantPayBtn">
                    <i class="fas fa-building"></i> Pay at Restaurant
                </button>
            </form>

            <div class="secure-badge">
                <i class="fas fa-lock"></i> Secure &bull; Encrypted &bull; <i class="fas fa-shield-alt" style="color:#D4AF37;"></i> Protected
            </div>

            <a href="<?php echo BASE_URL; ?>public/menu.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Menu
            </a>
        </div>

    </div>

</div>

<!-- ============================================================
CARD PAYMENT MODAL
============================================================ -->
<div class="modal-overlay" id="paymentModal">
    <div class="modal-content">

        <div id="payment-form-section">
            <div class="modal-header">
                <h3><i class="fas fa-credit-card"></i> Card Payment</h3>
                <button class="close-btn" onclick="closePaymentModal()">&times;</button>
            </div>

            <div class="modal-amount">
                <div class="amount-label">Amount to Pay</div>
                <div class="amount-value">LKR <?php echo $grand_total_display; ?></div>
            </div>

            <form id="cardPaymentForm" onsubmit="processPayment(event)">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="payment_method" value="Card Payment">
                <input type="hidden" name="total_amount" value="<?php echo $formatted_amount; ?>">
                <input type="hidden" name="selected_dishes" value="<?php echo safeHtml($dishes_string); ?>">
                <input type="hidden" name="custom_dish" value="<?php echo safeHtml($custom_dish_value); ?>">
                <input type="hidden" name="invoice_created" id="invoiceCreatedCard" value="0">

                <div class="form-group">
                    <label><i class="fas fa-credit-card"></i> Card Number <span class="required">*</span></label>
                    <div class="card-input-wrapper">
                        <input type="text" id="cardNumber" placeholder="0000 0000 0000 0000" maxlength="19" required
                               oninput="formatCardNumber(this)" autocomplete="cc-number" style="flex:1;">
                        <span class="card-icon-display" id="cardIcon"><i class="fas fa-credit-card"></i></span>
                    </div>
                    <div id="cardTypeBadge" class="card-type-badge unknown" style="display:none;">Unknown</div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-user"></i> Cardholder Name <span class="required">*</span></label>
                    <input type="text" id="cardName" placeholder="John Doe" 
                           value="<?php echo safeHtml($guest_name); ?>" required autocomplete="cc-name">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Expiry Date <span class="required">*</span></label>
                        <input type="text" id="expiryDate" placeholder="MM/YY" maxlength="5" required
                               oninput="formatExpiry(this)" autocomplete="cc-exp">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> CVV <span class="required">*</span></label>
                        <input type="password" id="cvv" placeholder="•••" maxlength="4" required
                               oninput="this.value = this.value.replace(/[^0-9]/g, '')" autocomplete="cc-csc">
                    </div>
                </div>

                <button type="submit" id="payBtn" class="btn-primary">
                    <i class="fas fa-lock"></i> Pay LKR <?php echo $grand_total_display; ?>
                </button>
            </form>
        </div>

        <div id="processing-screen">
            <div class="spinner"></div>
            <h3>Processing Payment</h3>
            <p>Please wait while we secure your transaction...</p>
        </div>

        <div class="payment-success" id="paymentSuccess">
            <div class="check"><i class="fas fa-check-circle"></i></div>
            <h3 style="color:#2ecc71; margin-top:10px;">Payment Successful!</h3>
            <p style="color:#888;">Redirecting to confirmation...</p>
        </div>

    </div>
</div>

<!-- Hidden Form for Card Success -->
<form id="successCardForm" method="post" action="<?php echo BASE_URL; ?>public/success.php" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="payment_method" value="Card Payment">
    <input type="hidden" name="total_amount" value="<?php echo $formatted_amount; ?>">
    <input type="hidden" name="selected_dishes" value="<?php echo safeHtml($dishes_string); ?>">
    <input type="hidden" name="custom_dish" value="<?php echo safeHtml($custom_dish_value); ?>">
    <input type="hidden" name="invoice_created" id="invoiceCreatedHidden" value="0">
</form>

<script>
// ============================================================
// 1. MODAL CONTROLS
// ============================================================
function openPaymentModal() {
    document.getElementById('paymentModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    document.getElementById('paymentSuccess').style.display = 'none';
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.remove('active');
    document.body.style.overflow = '';
    document.getElementById('payment-form-section').style.display = 'block';
    document.getElementById('processing-screen').style.display = 'none';
    document.getElementById('paymentSuccess').style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePaymentModal();
});

document.getElementById('paymentModal').addEventListener('click', function(e) {
    if (e.target === this) closePaymentModal();
});

// ============================================================
// 2. CARD NUMBER FORMATTING & VALIDATION
// ============================================================
function formatCardNumber(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length > 16) value = value.slice(0, 16);
    let formatted = '';
    for (let i = 0; i < value.length; i++) {
        if (i > 0 && i % 4 === 0) formatted += ' ';
        formatted += value[i];
    }
    input.value = formatted;
    detectCardType(value);
    updateCardIcon(value);
}

function detectCardType(number) {
    const badge = document.getElementById('cardTypeBadge');
    if (number.length === 0) { badge.style.display = 'none'; return; }
    let label = 'Unknown', className = 'unknown';
    if (number.charAt(0) === '4') { label = 'Visa'; className = 'visa'; }
    else if (['51','52','53','54','55'].includes(number.substring(0,2)) || (number.substring(0,2) >= '22' && number.substring(0,2) <= '27')) {
        label = 'MasterCard'; className = 'mastercard';
    }
    else if (['34','37'].includes(number.substring(0,2))) { label = 'American Express'; className = 'amex'; }
    badge.textContent = label;
    badge.className = 'card-type-badge ' + className;
    badge.style.display = 'block';
}

function updateCardIcon(number) {
    const icon = document.getElementById('cardIcon');
    if (number.length === 0) { icon.innerHTML = '<i class="fas fa-credit-card"></i>'; icon.className = 'card-icon-display'; return; }
    let cls = 'fas fa-credit-card';
    if (number.charAt(0) === '4') cls = 'fab fa-cc-visa';
    else if (['51','52','53','54','55'].includes(number.substring(0,2)) || (number.substring(0,2) >= '22' && number.substring(0,2) <= '27')) cls = 'fab fa-cc-mastercard';
    else if (['34','37'].includes(number.substring(0,2))) cls = 'fab fa-cc-amex';
    icon.innerHTML = '<i class="' + cls + '"></i>';
    icon.className = 'card-icon-display active';
}

function formatExpiry(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length >= 2) {
        const month = parseInt(value.substring(0,2));
        if (month > 12) value = '12' + value.substring(2);
        if (value.length >= 2) value = value.substring(0,2) + '/' + value.substring(2);
    }
    input.value = value;
}

// ============================================================
// 3. 🔥 CREATE INVOICE VIA AJAX
// ============================================================
function createInvoice(paymentMethod, callback) {
    const formData = new FormData();
    formData.append('action', 'create_invoice');
    formData.append('payment_method', paymentMethod);
    formData.append('total_amount', '<?php echo $formatted_amount; ?>');
    formData.append('selected_dishes', '<?php echo safeHtml($dishes_string); ?>');
    formData.append('custom_dish', '<?php echo safeHtml($custom_dish_value); ?>');
    formData.append('customer_name', '<?php echo safeHtml($guest_name); ?>');
    formData.append('customer_email', '<?php echo safeHtml($guest_email); ?>');
    formData.append('customer_phone', '<?php echo safeHtml($guest_phone); ?>');
    formData.append('booking_id', '<?php echo (int)$booking_id; ?>');
    formData.append('table_price', '<?php echo $table_price; ?>');
    formData.append('menu_total', '<?php echo $total_menu_price; ?>');
    formData.append('custom_total', '<?php echo $custom_item_total; ?>');
    formData.append('csrf_token', '<?php echo $csrf_token; ?>');

    fetch('<?php echo BASE_URL; ?>public/success.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            callback(data.invoice_id);
        } else {
            alert('Error creating invoice: ' + (data.error || 'Unknown error'));
            callback(false);
        }
    })
    .catch(error => {
        console.error('Invoice creation error:', error);
        callback(false);
    });
}

// ============================================================
// 4. PROCESS PAYMENT - Card Payment
// ============================================================
function processPayment(e) {
    e.preventDefault();

    const cardNumber = document.getElementById('cardNumber').value.replace(/\s/g, '');
    const cardName = document.getElementById('cardName').value.trim();
    const expiry = document.getElementById('expiryDate').value.trim();
    const cvv = document.getElementById('cvv').value.trim();
    const payBtn = document.getElementById('payBtn');

    // Card Number Validation
    if (cardNumber.length < 15 || cardNumber.length > 16) {
        alert('Please enter a valid card number (15-16 digits).');
        return;
    }

    // Cardholder Name
    if (cardName.length < 2) {
        alert('Please enter the cardholder name.');
        return;
    }

    // Expiry Validation
    if (!expiry.match(/^\d{2}\/\d{2}$/)) {
        alert('Please enter a valid expiry date in MM/YY format.');
        return;
    }
    const parts = expiry.split('/');
    const expMonth = parseInt(parts[0]);
    const expYear = parseInt(parts[1]);
    if (expMonth < 1 || expMonth > 12) {
        alert('Please enter a valid month (01-12).');
        return;
    }
    const now = new Date();
    const currentYear = now.getFullYear() % 100;
    const currentMonth = now.getMonth() + 1;
    if (expYear < currentYear || (expYear === currentYear && expMonth < currentMonth)) {
        alert('This card has expired. Please use a valid card.');
        return;
    }

    // CVV Validation
    if (cvv.length < 3 || cvv.length > 4) {
        alert('Please enter a valid CVV (3-4 digits).');
        return;
    }

    // Process
    payBtn.disabled = true;
    payBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    document.getElementById('payment-form-section').style.display = 'none';
    document.getElementById('processing-screen').style.display = 'block';

    // 🔥 Create invoice first
    createInvoice('Card Payment', function(invoiceId) {
        if (invoiceId) {
            document.getElementById('invoiceCreatedCard').value = invoiceId;
            document.getElementById('invoiceCreatedHidden').value = invoiceId;
        }
        
        // Simulate payment processing
        setTimeout(function() {
            document.getElementById('processing-screen').style.display = 'none';
            document.getElementById('paymentSuccess').style.display = 'block';
            setTimeout(function() {
                document.getElementById('successCardForm').submit();
            }, 1500);
        }, 1500);
    });
}

// ============================================================
// 5. 🔥 RESTAURANT PAYMENT - Create invoice before submit
// ============================================================
document.getElementById('restaurantPayBtn').addEventListener('click', function(e) {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    // Create invoice via AJAX
    createInvoice('Pay at Restaurant', function(invoiceId) {
        if (invoiceId) {
            document.getElementById('invoiceCreatedRestaurant').value = invoiceId;
        }
        // Submit the form
        setTimeout(function() {
            document.getElementById('restaurantPaymentForm').submit();
        }, 300);
    });
});

// ============================================================
// 6. PREVENT DOUBLE SUBMIT (fallback)
// ============================================================
document.querySelectorAll('.btn-restaurant').forEach(btn => {
    btn.addEventListener('click', function(e) {
        // Already handled above
    });
});

console.log('BASE_URL: <?php echo BASE_URL; ?>');
console.log('Grand Total: <?php echo $grand_total_display; ?>');
console.log('✨ Invoice will be created automatically on payment completion');
</script>

</body>
</html>