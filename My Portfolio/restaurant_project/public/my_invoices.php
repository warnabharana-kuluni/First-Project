<?php
// ============================================================
// MY INVOICES - View All Customer Invoices
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
    $_SESSION['redirect_after_login'] = BASE_URL . 'public/my_invoice.php';
    header("Location: " . BASE_URL . "public/login.php");
    exit();
}

$customer_id = (int)$_SESSION['customer_id'];

// Get customer email
$cust_query = "SELECT email, name FROM customers WHERE id = $customer_id";
$cust_result = mysqli_query($conn, $cust_query);
if ($cust_result && $cust_row = mysqli_fetch_assoc($cust_result)) {
    $customer_email = $cust_row['email'];
    $customer_name = $cust_row['name'];
} else {
    header("Location: " . BASE_URL . "public/logout.php");
    exit();
}

// Get all invoices for this customer
$invoices = getCustomerInvoices($conn, $customer_email);
$total_invoices = count($invoices);

// Get counts by status
$pending_count = 0;
$confirmed_count = 0;
$cancelled_count = 0;
foreach ($invoices as $inv) {
    $status = $inv['status'] ?? 'pending';
    if ($status == 'pending') $pending_count++;
    elseif ($status == 'confirmed') $confirmed_count++;
    elseif ($status == 'cancelled') $cancelled_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Invoices | Gourmet</title>
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

        .container {
            max-width: 1000px;
            width: 100%;
            animation: fadeInUp 0.6s ease forwards;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            color: #D4AF37;
        }
        .page-header p {
            color: #888;
            font-size: 15px;
            margin-top: 4px;
        }

        .stats-row {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }
        .stat-box {
            background: #0a1914;
            padding: 10px 20px;
            border-radius: 12px;
            border: 1px solid #1e4538;
            text-align: center;
            min-width: 100px;
        }
        .stat-box .num { font-size: 24px; font-weight: 700; color: #D4AF37; display: block; }
        .stat-box .label { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px; }
        .stat-box.pending .num { color: #f39c12; }
        .stat-box.confirmed .num { color: #2ecc71; }
        .stat-box.cancelled .num { color: #e74c3c; }

        .invoice-card {
            background: #0a1914;
            border-radius: 16px;
            border: 1px solid #1e4538;
            padding: 20px 25px;
            margin-bottom: 15px;
            transition: 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .invoice-card:hover {
            border-color: #D4AF37;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .invoice-card .info {
            flex: 1;
            min-width: 200px;
        }
        .invoice-card .info .inv-num {
            font-weight: 600;
            font-size: 16px;
            color: #D4AF37;
        }
        .invoice-card .info .inv-detail {
            color: #888;
            font-size: 13px;
            margin-top: 2px;
        }
        .invoice-card .info .inv-detail i { color: #D4AF37; margin-right: 4px; }

        .badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-pending { background: rgba(243,156,18,0.2); color: #f39c12; border: 1px solid rgba(243,156,18,0.3); }
        .badge-confirmed { background: rgba(46,204,113,0.2); color: #2ecc71; border: 1px solid rgba(46,204,113,0.3); }
        .badge-cancelled { background: rgba(231,76,60,0.2); color: #e74c3c; border: 1px solid rgba(231,76,60,0.3); }

        .inv-actions {
            display: flex;
            gap: 8px;
        }
        .btn-sm {
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-view {
            background: #D4AF37;
            color: #0a1914;
        }
        .btn-view:hover {
            background: #fff;
            transform: translateY(-2px);
        }
        .btn-download {
            background: transparent;
            color: #D4AF37;
            border: 1px solid #D4AF37;
        }
        .btn-download:hover {
            background: #D4AF37;
            color: #0a1914;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        .empty-state i {
            font-size: 60px;
            display: block;
            margin-bottom: 15px;
            color: #333;
        }
        .empty-state a {
            color: #D4AF37;
            text-decoration: none;
        }
        .empty-state a:hover {
            text-decoration: underline;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #888;
            text-decoration: none;
            transition: 0.3s;
        }
        .back-link:hover {
            color: #D4AF37;
        }

        @media (max-width: 600px) {
            .invoice-card { flex-direction: column; align-items: stretch; text-align: center; }
            .inv-actions { justify-content: center; }
            .stats-row .stat-box { min-width: 80px; padding: 8px 14px; }
            .stats-row .stat-box .num { font-size: 18px; }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="page-header">
        <h1><i class="fas fa-file-invoice"></i> My Invoices</h1>
        <p>Welcome back, <strong style="color:#D4AF37;"><?php echo htmlspecialchars($customer_name); ?></strong></p>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-box">
            <span class="num"><?php echo $total_invoices; ?></span>
            <span class="label">Total</span>
        </div>
        <div class="stat-box pending">
            <span class="num"><?php echo $pending_count; ?></span>
            <span class="label">Pending</span>
        </div>
        <div class="stat-box confirmed">
            <span class="num"><?php echo $confirmed_count; ?></span>
            <span class="label">Confirmed</span>
        </div>
        <div class="stat-box cancelled">
            <span class="num"><?php echo $cancelled_count; ?></span>
            <span class="label">Cancelled</span>
        </div>
    </div>

    <!-- Invoices List -->
    <?php if (!empty($invoices)): ?>
        <?php foreach ($invoices as $inv):
            $status = $inv['status'] ?? 'pending';
            $invoice_num = $inv['invoice_number'] ?? generateInvoiceNumber($inv['id']);
            $date = date('d M Y', strtotime($inv['reservation_date']));
            $time = date('h:i A', strtotime($inv['reservation_time']));
        ?>
            <div class="invoice-card">
                <div class="info">
                    <div class="inv-num">
                        <i class="fas fa-file-invoice"></i> <?php echo $invoice_num; ?>
                    </div>
                    <div class="inv-detail">
                        <i class="fas fa-calendar-alt"></i> <?php echo $date; ?> &bull;
                        <i class="fas fa-clock"></i> <?php echo $time; ?> &bull;
                        <i class="fas fa-users"></i> <?php echo $inv['guests']; ?> guests &bull;
                        <i class="fas fa-money-bill-wave"></i> LKR <?php echo number_format($inv['total_amount'] ?? 0, 2); ?>
                    </div>
                </div>
                <div>
                    <span class="badge badge-<?php echo $status; ?>">
                        <?php echo ucfirst($status); ?>
                    </span>
                </div>
                <div class="inv-actions">
                    <a href="<?php echo BASE_URL; ?>public/invoice.php?id=<?php echo $inv['id']; ?>" class="btn-sm btn-view">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <a href="<?php echo BASE_URL; ?>public/generate_invoice.php?id=<?php echo $inv['id']; ?>" class="btn-sm btn-download" target="_blank">
                        <i class="fas fa-download"></i> PDF
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <p>You don't have any invoices yet.</p>
            <a href="<?php echo BASE_URL; ?>public/reservation.php">Book a table</a> to get started.
        </div>
    <?php endif; ?>

    <div style="text-align:center;">
        <a href="<?php echo BASE_URL; ?>public/profile.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Profile
        </a>
    </div>

</div>

</body>
</html>