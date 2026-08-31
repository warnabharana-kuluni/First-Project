<?php
// ============================================================
// INVOICE VIEW - View Invoice in HTML
// ============================================================
session_start();

// Auto-detect BASE URL
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    $path = dirname(dirname($script_name));
    return $protocol . '://' . $host . $path . '/';
}
if (!defined('BASE_URL')) define('BASE_URL', getBaseUrl());

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/invoice_functions.php";

// Check login
if (!isset($_SESSION['customer_id']) || empty($_SESSION['customer_id'])) {
    header("Location: " . BASE_URL . "public/login.php");
    exit();
}

$reservation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($reservation_id <= 0) {
    die("Invalid reservation ID.");
}

// Get reservation data and verify ownership
$reservation = getInvoiceData($conn, $reservation_id);
if (!$reservation) {
    die("Reservation not found.");
}

// Verify the logged-in user owns this reservation
$customer_id = (int)$_SESSION['customer_id'];
$cust_query = "SELECT id FROM customers WHERE email = '{$reservation['email']}'";
$cust_result = mysqli_query($conn, $cust_query);
if ($cust_result && $row = mysqli_fetch_assoc($cust_result)) {
    if ($row['id'] != $customer_id) {
        die("You don't have permission to view this invoice.");
    }
} else {
    die("You don't have permission to view this invoice.");
}

// Generate invoice if not already generated
if ($reservation['invoice_generated'] == 0) {
    markInvoiceGenerated($conn, $reservation_id);
    $reservation = getInvoiceData($conn, $reservation_id);
}

$invoice_num = $reservation['invoice_number'] ?? generateInvoiceNumber($reservation_id);
$status = $reservation['status'] ?? 'pending';
$status_class = 'status-' . $status;
$status_text = ucfirst($status);

// Get customer name
$customer_name = $reservation['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $invoice_num; ?> | Gourmet</title>
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
            padding: 30px 20px;
        }

        .invoice-container {
            max-width: 800px;
            width: 100%;
            animation: fadeInUp 0.6s ease forwards;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .invoice-card {
            background: #0a1914;
            border-radius: 20px;
            border: 1px solid #1e4538;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
            overflow: hidden;
        }

        /* Header */
        .invoice-header {
            background: linear-gradient(135deg, #0f241c, #0a1914);
            padding: 30px 35px 25px;
            border-bottom: 3px solid #D4AF37;
            text-align: center;
        }
        .invoice-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            color: #D4AF37;
            letter-spacing: 3px;
        }
        .invoice-header .sub {
            color: #888;
            font-size: 14px;
            margin-top: 2px;
        }
        .invoice-header .invoice-number {
            display: inline-block;
            background: rgba(212,175,55,0.1);
            border: 1px solid rgba(212,175,55,0.2);
            padding: 4px 20px;
            border-radius: 20px;
            font-size: 14px;
            color: #D4AF37;
            font-weight: 600;
            margin-top: 10px;
        }

        /* Body */
        .invoice-body { padding: 30px 35px; }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 18px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .status-pending { background: rgba(243,156,18,0.2); color: #f39c12; border: 1px solid rgba(243,156,18,0.3); }
        .status-confirmed { background: rgba(46,204,113,0.2); color: #2ecc71; border: 1px solid rgba(46,204,113,0.3); }
        .status-cancelled { background: rgba(231,76,60,0.2); color: #e74c3c; border: 1px solid rgba(231,76,60,0.3); }

        /* Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 15px 0 20px;
        }
        .detail-item {
            background: #111;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #1e4538;
        }
        .detail-item .label {
            font-size: 11px;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 1px;
        }
        .detail-item .value {
            font-size: 15px;
            font-weight: 500;
            color: #fff;
            margin-top: 2px;
        }
        .detail-item .value.gold { color: #D4AF37; }
        .detail-item .value.green { color: #2ecc71; }
        .detail-item.full-width { grid-column: 1 / -1; }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0 20px;
        }
        .items-table th {
            color: #D4AF37;
            padding: 10px 12px;
            text-align: left;
            border-bottom: 2px solid #1e4538;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .items-table td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(30,69,56,0.3);
            font-size: 14px;
            color: #b0c4b1;
        }
        .items-table tr:last-child td { border-bottom: none; }
        .items-table .item-name { color: #fff; font-weight: 500; }
        .items-table .item-detail { color: #888; font-size: 12px; }
        .items-table .price { color: #D4AF37; font-weight: 600; }

        /* Total */
        .total-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-top: 2px solid #D4AF37;
            margin-top: 10px;
        }
        .total-section .label { color: #888; font-size: 16px; font-weight: 600; }
        .total-section .amount { font-size: 28px; font-weight: 700; color: #D4AF37; }

        /* Actions */
        .invoice-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #1e4538;
        }
        .btn {
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #D4AF37, #b8962e);
            color: #0a1914;
        }
        .btn-primary:hover {
            background: #fff;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(212,175,55,0.3);
        }
        .btn-secondary {
            background: transparent;
            color: #D4AF37;
            border: 2px solid #D4AF37;
        }
        .btn-secondary:hover {
            background: #D4AF37;
            color: #0a1914;
            transform: translateY(-3px);
        }
        .btn-outline {
            background: transparent;
            color: #888;
            border: 1px solid #333;
        }
        .btn-outline:hover {
            border-color: #D4AF37;
            color: #D4AF37;
        }

        /* Footer */
        .invoice-footer {
            text-align: center;
            padding: 15px 35px 20px;
            border-top: 1px solid #1e4538;
            color: #444;
            font-size: 12px;
        }
        .invoice-footer i { color: #D4AF37; }

        @media (max-width: 600px) {
            .invoice-body { padding: 20px; }
            .invoice-header { padding: 20px; }
            .details-grid { grid-template-columns: 1fr; }
            .total-section { flex-direction: column; gap: 5px; text-align: center; }
            .invoice-actions { flex-direction: column; }
            .btn { justify-content: center; }
        }
    </style>
</head>
<body>

<div class="invoice-container">

    <div class="invoice-card">

        <!-- Header -->
        <div class="invoice-header">
            <h1>✨ GOURMET</h1>
            <div class="sub">Fine Dining Restaurant</div>
            <div class="invoice-number">
                <i class="fas fa-file-invoice"></i> <?php echo $invoice_num; ?>
            </div>
        </div>

        <!-- Body -->
        <div class="invoice-body">

            <!-- Status -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
                <div>
                    <span style="color:#888; font-size:13px;">Invoice Date:</span>
                    <span style="color:#fff; font-size:14px;"><?php echo date('d M Y, h:i A', strtotime($reservation['created_at'])); ?></span>
                </div>
                <span class="status-badge <?php echo $status_class; ?>">
                    <i class="fas fa-circle" style="font-size:8px; margin-right:6px;"></i>
                    <?php echo $status_text; ?>
                </span>
            </div>

            <!-- Customer Details -->
            <div style="background:#111; padding:15px 18px; border-radius:10px; border:1px solid #1e4538; margin-bottom:20px;">
                <div style="color:#888; font-size:12px; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">
                    <i class="fas fa-user" style="color:#D4AF37;"></i> Customer Details
                </div>
                <div style="color:#fff; font-weight:500;"><?php echo htmlspecialchars($reservation['name']); ?></div>
                <div style="color:#888; font-size:13px;"><?php echo htmlspecialchars($reservation['email']); ?></div>
                <div style="color:#888; font-size:13px;"><?php echo htmlspecialchars($reservation['phone']); ?></div>
            </div>

            <!-- Reservation Details -->
            <div class="details-grid">
                <div class="detail-item">
                    <div class="label"><i class="fas fa-calendar-alt"></i> Date</div>
                    <div class="value gold"><?php echo date('d M Y', strtotime($reservation['reservation_date'])); ?></div>
                </div>
                <div class="detail-item">
                    <div class="label"><i class="fas fa-clock"></i> Time</div>
                    <div class="value gold"><?php echo date('h:i A', strtotime($reservation['reservation_time'])); ?></div>
                </div>
                <div class="detail-item">
                    <div class="label"><i class="fas fa-users"></i> Guests</div>
                    <div class="value"><?php echo $reservation['guests']; ?></div>
                </div>
                <div class="detail-item">
                    <div class="label"><i class="fas fa-credit-card"></i> Payment</div>
                    <div class="value"><?php echo htmlspecialchars($reservation['payment_method'] ?? 'Not specified'); ?></div>
                </div>
                <?php if (!empty($reservation['selected_dishes']) && $reservation['selected_dishes'] != 'None'): ?>
                <div class="detail-item full-width">
                    <div class="label"><i class="fas fa-utensils"></i> Selected Dishes</div>
                    <div class="value"><?php echo htmlspecialchars($reservation['selected_dishes']); ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($reservation['custom_dish'])): ?>
                <div class="detail-item full-width">
                    <div class="label"><i class="fas fa-pen"></i> Custom Order</div>
                    <div class="value" style="color:#D4AF37;"><?php echo htmlspecialchars($reservation['custom_dish']); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th style="width:100px;">Qty</th>
                        <th style="width:130px;">Price</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="item-name">Table Reservation</div>
                            <div class="item-detail">
                                <?php echo date('d M Y', strtotime($reservation['reservation_date'])); ?> • 
                                <?php echo date('h:i A', strtotime($reservation['reservation_time'])); ?> • 
                                <?php echo $reservation['guests']; ?> guests
                            </div>
                        </td>
                        <td>1</td>
                        <td class="price">LKR <?php echo number_format($reservation['total_amount'] ?? 0, 2); ?></td>
                    </tr>
                    <?php if (!empty($reservation['selected_dishes']) && $reservation['selected_dishes'] != 'None'): ?>
                    <tr>
                        <td>
                            <div class="item-name">Selected Dishes</div>
                            <div class="item-detail"><?php echo htmlspecialchars($reservation['selected_dishes']); ?></div>
                        </td>
                        <td>1</td>
                        <td class="price">Included</td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($reservation['custom_dish'])): ?>
                    <tr>
                        <td>
                            <div class="item-name">Custom Order</div>
                            <div class="item-detail"><?php echo htmlspecialchars($reservation['custom_dish']); ?></div>
                        </td>
                        <td>1</td>
                        <td class="price">Included</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Total -->
            <div class="total-section">
                <span class="label">Total Amount</span>
                <span class="amount">LKR <?php echo number_format($reservation['total_amount'] ?? 0, 2); ?></span>
            </div>

            <!-- Actions -->
            <div class="invoice-actions">
                <a href="<?php echo BASE_URL; ?>public/generate_invoice.php?id=<?php echo $reservation_id; ?>" class="btn btn-primary" target="_blank">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </a>
                <a href="<?php echo BASE_URL; ?>public/index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="<?php echo BASE_URL; ?>public/profile.php" class="btn btn-outline">
                    <i class="fas fa-user"></i> My Profile
                </a>
            </div>

        </div>

        <!-- Footer -->
        <div class="invoice-footer">
            <i class="fas fa-crown"></i> Gourmet Restaurant — Where every dish tells a story.
            <br><span style="color:#333;">&copy; <?php echo date('Y'); ?> Gourmet. All rights reserved.</span>
        </div>

    </div>

</div>

</body>
</html>