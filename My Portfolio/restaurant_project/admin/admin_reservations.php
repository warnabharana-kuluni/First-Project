<?php
// ============================================================
// ADMIN RESERVATIONS - Inline Status Update with Notification & Invoice Link
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/admin_auth.php';

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

function columnExists($table, $column) {
    global $conn;
    if (!$conn) return false;
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

function tableExists($tableName) {
    global $conn;
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$tableName'");
    return $result && mysqli_num_rows($result) > 0;
}

// ============================================================
// HELPER: Format custom dish to show quantity outside parentheses
// ============================================================
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

// ============================================================
// HELPER: Format payment method for display
// ============================================================
function formatPaymentMethod($method) {
    if (empty($method)) return 'Not specified';
    
    $method = trim($method);
    $method_lower = strtolower($method);
    
    $map = [
        'pay_at_restaurant' => 'Pay at Restaurant',
        'pay at restaurant' => 'Pay at Restaurant',
        'card' => 'Credit Card',
        'credit_card' => 'Credit Card',
        'credit card' => 'Credit Card',
        'online' => 'Online Payment',
        'online payment' => 'Online Payment',
        'cash' => 'Cash',
        'not specified' => 'Not specified'
    ];
    
    return $map[$method_lower] ?? $method;
}

$has_status = columnExists('reservations', 'status');
$has_payment = columnExists('reservations', 'payment_method');
$has_total = columnExists('reservations', 'total_amount');
$tables_table_exists = tableExists('tables');

// ============================================================
// HANDLE UPDATE STATUS (Inline)
// ============================================================
$action_msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $id = (int)$_POST['id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $allowed = ['pending', 'confirmed', 'cancelled'];
    if (!in_array($status, $allowed)) {
        $action_msg = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Invalid status value.</div>';
    } else {
        $query = "SELECT * FROM reservations WHERE id = $id";
        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_assoc($result);
        if ($row) {
            $update_query = "UPDATE reservations SET status = '$status' WHERE id = $id";
            if (mysqli_query($conn, $update_query)) {
                $action_msg = '<div class="msg-success"><i class="fas fa-check-circle"></i> Status updated successfully!</div>';
                if (in_array($status, ['confirmed', 'cancelled'])) {
                    sendStatusEmail($row['name'], $row['email'], $row['reservation_date'], $row['reservation_time'], $status);
                    $action_msg .= ' <span style="color:#2ecc71;font-size:12px;">📧 Email sent!</span>';
                }
                $status_labels = ['pending' => '⏳ Pending', 'confirmed' => '✅ Confirmed', 'cancelled' => '❌ Cancelled'];
                $status_label = $status_labels[$status] ?? ucfirst($status);
                $customer_id = null;
                $cust_query = "SELECT id FROM customers WHERE email = '{$row['email']}'";
                $cust_result = mysqli_query($conn, $cust_query);
                if ($cust_result && mysqli_num_rows($cust_result) > 0) {
                    $cust_row = mysqli_fetch_assoc($cust_result);
                    $customer_id = $cust_row['id'];
                }
                $res_date = date('d M Y', strtotime($row['reservation_date']));
                $res_time = date('h:i A', strtotime($row['reservation_time']));
                $full_message = "Dear {$row['name']}, your reservation for {$res_date} at {$res_time} has been updated to: <strong>{$status_label}</strong>. " . ($status == 'confirmed' ? 'We look forward to serving you!' : ($status == 'cancelled' ? 'Please contact us for further assistance.' : 'We will notify you once confirmed.'));
                if ($customer_id) {
                    $notif_sql = "INSERT INTO messages (customer_id, reservation_id, name, email, subject, message, status, notification_type, created_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, 'unread', 'reservation_update', NOW())";
                    $stmt_notif = mysqli_prepare($conn, $notif_sql);
                    $subject = "Reservation {$status_label}";
                    mysqli_stmt_bind_param($stmt_notif, "iissss", $customer_id, $id, $row['name'], $row['email'], $subject, $full_message);
                    if (mysqli_stmt_execute($stmt_notif)) {
                        $action_msg .= ' <span style="color:#3498db;font-size:12px;">💬 Notification sent!</span>';
                    } else {
                        $action_msg .= ' <span style="color:#e74c3c;font-size:12px;">⚠️ Notification failed: ' . mysqli_error($conn) . '</span>';
                    }
                    mysqli_stmt_close($stmt_notif);
                } else {
                    $action_msg .= ' <span style="color:#e74c3c;font-size:12px;">⚠️ No customer account found for this email.</span>';
                }
            } else {
                $action_msg = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Error updating status.</div>';
            }
        } else {
            $action_msg = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Reservation not found.</div>';
        }
    }
}

function sendStatusEmail($name, $email, $date, $time, $status) {
    $to = $email;
    $subject = "Reservation Status Update - Gourmet Restaurant";
    $status_color = ($status == 'confirmed') ? '#2e7d32' : '#c62828';
    $status_message = ($status == 'confirmed') ? 'We are excited to serve you! Please arrive 10 minutes prior to your booking time.' : 'We regret to inform you that we cannot accommodate your request at this time. Please contact us for further assistance.';
    $message = "
    <html>
    <head><title>Reservation Update</title></head>
    <body style='font-family: Arial, sans-serif; background-color: #050e0c; padding: 20px; color: #ffffff;'>
        <div style='max-width: 600px; margin: 0 auto; background: #0a1914; padding: 30px; border-radius: 15px; border: 1px solid #1e4538; text-align: center;'>
            <h2 style='color: #D4AF37; margin-bottom: 20px; font-family: \"Playfair Display\", serif;'>✨ GOURMET RESTAURANT</h2>
            <hr style='border: 0; border-top: 1px solid #1e4538; margin-bottom: 20px;'>
            <p style='font-size: 16px; color: #b0c4b1; text-align: left;'>Dear <strong>$name</strong>,</p>
            <p style='font-size: 15px; color: #b0c4b1; text-align: left; line-height: 1.6;'>
                Your reservation for <strong>$date</strong> at <strong>$time</strong> has been updated.
            </p>
            <div style='margin: 30px 0; padding: 15px; background: $status_color; color: #fff; font-size: 20px; font-weight: bold; border-radius: 8px; text-transform: uppercase; display: inline-block; letter-spacing: 2px;'>
                $status
            </div>
            <p style='font-size: 14px; color: #b0c4b1; line-height: 1.6;'>$status_message</p>
            <hr style='border: 0; border-top: 1px solid #1e4538; margin-top: 30px;'>
            <p style='font-size: 12px; color: #557a6e;'>This is an automated email. Please do not reply.</p>
        </div>
    </body>
    </html>
    ";
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: Gourmet Restaurant <gourmet@restaurant.com>\r\n";
    @mail($to, $subject, $message, $headers);
}

// ============================================================
// HANDLE DELETE
// ============================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if (mysqli_query($conn, "DELETE FROM reservations WHERE id = $id")) {
        $action_msg = '<div class="msg-success"><i class="fas fa-check-circle"></i> Reservation deleted successfully!</div>';
    } else {
        $action_msg = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Error deleting reservation.</div>';
    }
}

// ============================================================
// GET COUNTS
// ============================================================
$total_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM reservations"))['c'] ?? 0;
$pending_count = 0;
$confirmed_count = 0;
$cancelled_count = 0;
if ($has_status) {
    $pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM reservations WHERE status='pending'"))['c'] ?? 0;
    $confirmed_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM reservations WHERE status='confirmed'"))['c'] ?? 0;
    $cancelled_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM reservations WHERE status='cancelled'"))['c'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations | Gourmet Admin</title>
    <meta http-equiv="refresh" content="30">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0d0d0d;
            color: #f0f0f0;
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: #0a1914;
            border-right: 2px solid #1e4538;
            padding: 40px 20px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        .sidebar h2 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: #D4AF37;
            letter-spacing: 2px;
            margin-bottom: 40px;
            text-align: center;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #b0c4b1;
            text-decoration: none;
            padding: 14px 20px;
            margin-bottom: 8px;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 400;
        }
        .sidebar a i { width: 22px; font-size: 18px; text-align: center; }
        .sidebar a:hover, .sidebar a.active {
            background: #1e4538;
            color: #D4AF37;
        }
        .sidebar a.active { font-weight: 600; }
        .sidebar a.logout {
            color: #e74c3c;
            margin-top: 30px;
            border-top: 1px solid #1e4538;
            padding-top: 20px;
        }
        .sidebar a.logout:hover { background: #e74c3c; color: #fff; }
        .main-content {
            margin-left: 300px;
            padding: 40px 50px 60px;
            width: calc(100% - 300px);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 25px;
        }
        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 34px;
            color: #fff;
        }
        .page-header h1 span { color: #D4AF37; }
        .page-header h1 i { margin-right: 10px; color: #D4AF37; }
        .page-header .header-stats {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .page-header .header-stats .stat-badge {
            background: #0a1914;
            padding: 8px 18px;
            border-radius: 20px;
            border: 1px solid #1e4538;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .page-header .header-stats .stat-badge i { color: #D4AF37; }
        .page-header .header-stats .stat-badge .num { color: #D4AF37; font-weight: 700; }
        .page-header .header-stats .stat-badge.pending { border-color: #f39c12; }
        .page-header .header-stats .stat-badge.pending i { color: #f39c12; }
        .page-header .header-stats .stat-badge.pending .num { color: #f39c12; }
        .page-header .header-stats .stat-badge.confirmed { border-color: #2ecc71; }
        .page-header .header-stats .stat-badge.confirmed i { color: #2ecc71; }
        .page-header .header-stats .stat-badge.confirmed .num { color: #2ecc71; }
        .page-header .header-stats .stat-badge.cancelled { border-color: #e74c3c; }
        .page-header .header-stats .stat-badge.cancelled i { color: #e74c3c; }
        .page-header .header-stats .stat-badge.cancelled .num { color: #e74c3c; }
        .msg-success, .msg-error {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .msg-success { background: rgba(46, 204, 113, 0.15); border: 1px solid #2ecc71; color: #2ecc71; }
        .msg-error { background: rgba(231, 76, 60, 0.15); border: 1px solid #e74c3c; color: #e74c3c; }
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            margin-bottom: 25px;
            background: #0a1914;
            padding: 15px 20px;
            border-radius: 14px;
            border: 1px solid #1e4538;
        }
        .filter-bar input, .filter-bar select {
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            padding: 10px 16px;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: 0.3s;
            flex: 1;
            min-width: 150px;
        }
        .filter-bar input:focus, .filter-bar select:focus {
            border-color: #D4AF37;
            outline: none;
            box-shadow: 0 0 20px rgba(212,175,55,0.05);
        }
        .filter-bar input::placeholder { color: #666; }
        .filter-bar select option { background: #111; color: #fff; }
        .table-wrapper {
            background: #1a1a1a;
            border-radius: 16px;
            padding: 20px 0;
            border: 1px solid #2a2a2a;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 1200px;
        }
        thead {
            border-bottom: 1px solid #2a2a2a;
        }
        th {
            text-align: left;
            padding: 12px 16px;
            color: #888;
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        th i { margin-right: 6px; }
        td {
            padding: 16px 16px;
            border-bottom: 1px solid #252525;
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        .badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .badge-pending { background: rgba(243,156,18,0.20); color: #f39c12; border: 1px solid rgba(243,156,18,0.30); }
        .badge-confirmed { background: rgba(46,204,113,0.20); color: #2ecc71; border: 1px solid rgba(46,204,113,0.30); }
        .badge-cancelled { background: rgba(231,76,60,0.20); color: #e74c3c; border: 1px solid rgba(231,76,60,0.30); }
        .status-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .status-form select {
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            padding: 6px 12px;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            transition: 0.3s;
            cursor: pointer;
        }
        .status-form select:focus { border-color: #D4AF37; outline: none; }
        .status-form select option { background: #111; color: #fff; }
        .btn-update {
            background: #D4AF37;
            color: #0a1914;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-update:hover { background: #fff; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(212,175,55,0.3); }
        .action-group {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
        }
        .btn-delete-sm {
            color: #e74c3c;
            text-decoration: none;
            padding: 4px 12px;
            border: 1px solid #e74c3c;
            border-radius: 6px;
            font-size: 12px;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-delete-sm:hover { background: #e74c3c; color: #fff; }
        .btn-invoice-sm {
            color: #D4AF37;
            text-decoration: none;
            padding: 4px 12px;
            border: 1px solid #D4AF37;
            border-radius: 6px;
            font-size: 12px;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: transparent;
        }
        .btn-invoice-sm:hover { background: #D4AF37; color: #0a1914; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(212,175,55,0.3); }
        .no-data {
            text-align: center;
            padding: 50px 0;
            color: #666;
        }
        .no-data i { font-size: 48px; display: block; margin-bottom: 12px; color: #333; }
        .no-data p { font-size: 16px; }
        .custom-name {
            font-weight: 500;
            color: #D4AF37;
        }
        .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #1e4538;
        }
        .total-items { color: #888; font-size: 14px; }
        .total-items strong { color: #D4AF37; font-size: 18px; }
        .total-items i { margin-right: 6px; color: #D4AF37; }
        .auto-refresh-note { font-size: 12px; color: #557a6e; }
        .summary {
            display: flex;
            gap: 40px;
            margin-top: 24px;
            padding: 16px 20px;
            background: #1a1a1a;
            border-radius: 12px;
            border: 1px solid #2a2a2a;
        }
        .summary-item {
            display: flex;
            align-items: baseline;
            gap: 8px;
        }
        .summary-item .label { color: #888; font-size: 14px; }
        .summary-item .value { font-size: 20px; font-weight: 600; color: #f0f0f0; }
        .summary-item .value.pending-val { color: #D4AF37; }
        .hamburger {
            display: none;
            background: transparent;
            border: none;
            color: #D4AF37;
            font-size: 28px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 8px;
            transition: 0.3s;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
        }
        .hamburger:hover { background: rgba(212,175,55,0.1); }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); width: 260px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; padding: 30px 25px 50px; }
            .hamburger { display: flex !important; }
            .page-header { flex-direction: column; align-items: stretch; }
            .page-header .header-stats { justify-content: flex-start; }
        }
        @media (max-width: 768px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar input, .filter-bar select { min-width: 100%; }
            table { min-width: 900px; }
            .page-header h1 { font-size: 26px; }
            .page-header .header-stats .stat-badge { font-size: 12px; padding: 6px 12px; }
            .status-form { flex-direction: column; align-items: stretch; }
            .btn-update { justify-content: center; }
        }
        @media (max-width: 480px) {
            .sidebar { width: 60px; padding: 15px 8px; }
            .sidebar h2 { display: none; }
            .sidebar a { padding: 10px 8px; font-size: 11px; text-align: center; justify-content: center; }
            .sidebar a span { display: none; }
            .sidebar a i { width: auto; font-size: 20px; }
            .main-content { margin-left: 0; padding: 20px 12px 40px; }
            table { min-width: 800px; }
            th, td { padding: 10px 10px; font-size: 12px; }
            .badge { font-size: 10px; padding: 2px 10px; }
            .page-header .header-stats .stat-badge { font-size: 11px; padding: 4px 10px; }
            .action-group { flex-direction: column; align-items: stretch; }
            .btn-delete-sm, .btn-invoice-sm { justify-content: center; }
        }
    </style>
</head>
<body>
    <button class="hamburger" id="hamburgerBtn" aria-label="Toggle Sidebar">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar" id="sidebar">
        <h2>✨ GOURMET</h2>
        <a href="admin.php"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a>
        <a href="admin_reservations.php" class="active"><i class="fas fa-calendar-check"></i> <span>Reservations</span></a>
        <a href="admin_menu.php"><i class="fas fa-utensils"></i> <span>Menu</span></a>
        <a href="admin_reviews.php"><i class="fas fa-star"></i> Ratings</a>
        <a href="admin_messages.php"><i class="fas fa-envelope"></i> <span>Messages</span></a>
        <a href="admin_offers.php"><i class="fas fa-tags"></i> <span>Offers</span></a>
        <a href="admin_logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-calendar-check"></i> <span>Reservations</span></h1>
            <div class="header-stats">
                <span class="stat-badge"><i class="fas fa-list"></i> Total: <span class="num"><?php echo $total_count; ?></span></span>
                <?php if ($has_status): ?>
                    <span class="stat-badge pending"><i class="fas fa-clock"></i> Pending: <span class="num"><?php echo $pending_count; ?></span></span>
                    <span class="stat-badge confirmed"><i class="fas fa-check-circle"></i> Confirmed: <span class="num"><?php echo $confirmed_count; ?></span></span>
                    <span class="stat-badge cancelled"><i class="fas fa-times-circle"></i> Cancelled: <span class="num"><?php echo $cancelled_count; ?></span></span>
                <?php endif; ?>
            </div>
        </div>

        <?php echo $action_msg; ?>

        <div class="filter-bar">
            <input type="text" id="searchInput" placeholder="🔍 Search by name, email or dishes..." onkeyup="filterTable()">
            <?php if ($has_status): ?>
                <select id="statusFilter" onchange="filterTable()">
                    <option value="all">📂 All Status</option>
                    <option value="pending">⏳ Pending</option>
                    <option value="confirmed">✅ Confirmed</option>
                    <option value="cancelled">❌ Cancelled</option>
                </select>
            <?php endif; ?>
        </div>

        <div class="table-wrapper">
            <table id="reservationsTable">
                <thead>
                    <tr>
                        <th><i class="fas fa-user"></i> Name</th>
                        <th><i class="fas fa-envelope"></i> Email</th>
                        <th><i class="fas fa-calendar-day"></i> Date & Time</th>
                        <th><i class="fas fa-users"></i> Guests</th>
                        <th><i class="fas fa-chair"></i> Table</th>
                        <th><i class="fas fa-utensils"></i> Dishes</th>
                        <th><i class="fas fa-pen"></i> Custom</th>
                        <?php if ($has_total): ?><th><i class="fas fa-money-bill-wave"></i> Total</th><?php endif; ?>
                        <?php if ($has_payment): ?><th><i class="fas fa-credit-card"></i> Payment</th><?php endif; ?>
                        <?php if ($has_status): ?><th><i class="fas fa-circle"></i> Status</th><?php endif; ?>
                        <th><i class="fas fa-file-invoice"></i> Invoice</th>
                        <th><i class="fas fa-cog"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // ---------- BUILD QUERY ----------
                    if ($tables_table_exists) {
                        $sql = "SELECT 
                                    r.id,
                                    r.name, 
                                    r.email, 
                                    r.reservation_date, 
                                    r.reservation_time, 
                                    r.guests,
                                    r.custom_dish,
                                    r.payment_method,
                                    r.status,
                                    r.total_amount,
                                    r.phone,
                                    r.selected_dishes,
                                    r.table_id,
                                    t.table_name,
                                    t.table_number
                                FROM reservations r 
                                LEFT JOIN tables t ON r.table_id = t.id 
                                ORDER BY r.reservation_date DESC, r.reservation_time DESC";
                    } else {
                        $sql = "SELECT 
                                    id,
                                    name, 
                                    email, 
                                    reservation_date, 
                                    reservation_time, 
                                    guests,
                                    custom_dish,
                                    payment_method,
                                    status,
                                    total_amount,
                                    phone,
                                    selected_dishes,
                                    table_id
                                FROM reservations 
                                ORDER BY reservation_date DESC, reservation_time DESC";
                    }
                    $result = mysqli_query($conn, $sql);

                    if ($result && mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $row_status = $has_status && isset($row['status']) ? $row['status'] : 'pending';
                            $badge_class = 'badge-' . $row_status;
                            if (!in_array($row_status, ['pending', 'confirmed', 'cancelled'])) {
                                $badge_class = 'badge-pending';
                            }

                            // ---- EXTRACT MENU ITEMS (Dishes) ----
                            $menu_items = [];
                            $custom_entries = [];
                            $selected_dishes_raw = $row['selected_dishes'] ?? '';

                            if (!empty($selected_dishes_raw) && $selected_dishes_raw !== 'None') {
                                $items = array_map('trim', explode(',', $selected_dishes_raw));
                                foreach ($items as $item) {
                                    if (empty($item)) continue;
                                    // Check if it's a custom entry
                                    if (stripos($item, 'Custom Food') !== false || stripos($item, 'Custom Juice') !== false || stripos($item, 'Custom') !== false) {
                                        $custom_entries[] = $item;
                                    } else {
                                        $menu_items[] = $item;
                                    }
                                }
                            }

                            // If there is a custom_dish value, we use it for the Custom column (even if not in selected_dishes)
                            $custom_dish_value = $row['custom_dish'] ?? '';
                            $custom_display = '—';
                            if (!empty($custom_dish_value)) {
                                $custom_display = formatCustomDish($custom_dish_value);
                            } elseif (!empty($custom_entries)) {
                                // Fallback: use the first custom entry from selected_dishes
                                $custom_display = formatCustomDish($custom_entries[0]);
                            }

                            // Dishes column: join menu items
                            $dishes_display = !empty($menu_items) ? implode(', ', $menu_items) : '—';

                            // ---- TOTAL ----
                            $total = isset($row['total_amount']) && is_numeric($row['total_amount']) ? 'LKR ' . number_format((float)$row['total_amount'], 2) : '—';

                            // ---- PAYMENT METHOD ----
                            $payment_raw = $row['payment_method'] ?? '';
                            $payment = formatPaymentMethod($payment_raw);

                            // ---- TABLE DISPLAY ----
                            $table_display = '—';
                            if ($tables_table_exists) {
                                if (!empty($row['table_id']) && !empty($row['table_name'])) {
                                    $table_display = htmlspecialchars($row['table_name'] . ' (' . $row['table_number'] . ')');
                                } elseif (!empty($row['table_id'])) {
                                    $table_display = 'Table #' . $row['table_id'];
                                }
                            } else {
                                if (!empty($row['table_id'])) {
                                    $table_display = 'Table #' . $row['table_id'];
                                }
                            }
                            $res_id = $row['id'];
                    ?>
                            <tr data-status="<?php echo $row_status; ?>">
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td>
                                    <?php
                                    $date = isset($row['reservation_date']) ? $row['reservation_date'] : '';
                                    $time = isset($row['reservation_time']) ? $row['reservation_time'] : '';
                                    echo htmlspecialchars($date . ' ' . $time);
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['guests']); ?></td>
                                <td><?php echo $table_display; ?></td>
                                <td><?php echo htmlspecialchars($dishes_display); ?></td>
                                <td><span class="custom-name"><?php echo htmlspecialchars($custom_display); ?></span></td>
                                <?php if ($has_total): ?><td><?php echo htmlspecialchars($total); ?></td><?php endif; ?>
                                <?php if ($has_payment): ?><td><?php echo htmlspecialchars($payment); ?></td><?php endif; ?>
                                <?php if ($has_status): ?><td><span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($row_status); ?></span></td><?php endif; ?>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>public/generate_invoice.php?id=<?php echo $res_id; ?>" target="_blank" class="btn-invoice-sm">
                                        <i class="fas fa-file-pdf"></i> View PDF
                                    </a>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <?php if ($has_status): ?>
                                            <form action="" method="POST" class="status-form">
                                                <input type="hidden" name="id" value="<?php echo $res_id; ?>">
                                                <select name="status">
                                                    <option value="pending" <?php echo $row_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="confirmed" <?php echo $row_status == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                    <option value="cancelled" <?php echo $row_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                                <button type="submit" name="update_status" class="btn-update">
                                                    <i class="fas fa-sync-alt"></i> Update
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="?delete=<?php echo $res_id; ?>" class="btn-delete-sm" onclick="return confirm('Delete this reservation?')">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                    <?php
                        }
                    } else {
                        $colspan = 9;
                        if ($has_total) $colspan++;
                        if ($has_payment) $colspan++;
                        if ($has_status) $colspan++;
                        echo '<tr><td colspan="' . $colspan . '"><div class="no-data">
                                <i class="fas fa-calendar-times"></i>
                                <p>No reservations found.</p>
                              </div></td></tr>';
                    }
                    ?>
                </tbody>
            </table>

            <div class="table-footer">
                <div class="total-items"><i class="fas fa-list-ul"></i> Total Reservations: <strong id="totalCount">0</strong></div>
                <div style="font-size:13px; color:#555;">
                    <i class="fas fa-arrow-up"></i> Latest reservations shown first &nbsp; 
                    <span class="auto-refresh-note"><i class="fas fa-sync-alt fa-fw"></i> Auto-refreshes every 30s</span>
                </div>
            </div>
        </div>

        <div class="summary">
            <div class="summary-item">
                <span class="label">Total</span>
                <span class="value"><?php echo $total_count; ?></span>
            </div>
            <div class="summary-item">
                <span class="label">Pending</span>
                <span class="value pending-val"><?php echo $pending_count; ?></span>
            </div>
        </div>
    </div>

    <script>
        function filterTable() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter')?.value.toLowerCase() || 'all';
            const rows = document.querySelectorAll('#reservationsTable tbody tr');
            let visibleCount = 0;
            rows.forEach(row => {
                if (row.querySelector('.no-data')) return;
                const name = row.querySelector('td:nth-child(1)')?.textContent.toLowerCase() || '';
                const email = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                const dishes = row.querySelector('td:nth-child(6)')?.textContent.toLowerCase() || '';
                const rowStatus = row.getAttribute('data-status') || '';
                const matchSearch = name.includes(input) || email.includes(input) || dishes.includes(input);
                const matchStatus = (statusFilter === 'all' || rowStatus === statusFilter);
                if (matchSearch && matchStatus) { row.style.display = ''; visibleCount++; } 
                else { row.style.display = 'none'; }
            });
            document.getElementById('totalCount').textContent = visibleCount;
        }
        document.addEventListener('DOMContentLoaded', filterTable);

        document.getElementById('hamburgerBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const hamburger = document.getElementById('hamburgerBtn');
            if (window.innerWidth <= 992) {
                if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                document.getElementById('sidebar').classList.remove('open');
            }
        });
    </script>
</body>
</html>