<?php
// ============================================================
// ADMIN DASHBOARD - ADVANCED VERSION
// ============================================================
// Error Reporting - Only for development
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production: 0

// ============================================================
// 1. SECURITY & SESSION
// ============================================================
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// ============================================================
// 2. DATABASE CONNECTION
// ============================================================
require_once __DIR__ . '/../includes/db.php';

// Check database connection
$db_connected = false;
$db_error = '';
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $db_connected = true;
} else {
    $db_error = $conn->connect_error ?? 'Database connection failed';
}

// ============================================================
// 3. FUNCTIONS
// ============================================================
function columnExists($table, $column) {
    global $conn;
    if (!$conn) return false;
    try {
        $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $result && mysqli_num_rows($result) > 0;
    } catch (Exception $e) {
        return false;
    }
}

function getCount($query) {
    global $conn;
    if (!$conn) return 0;
    try {
        $result = mysqli_query($conn, $query);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            return (int)($row['c'] ?? 0);
        }
        return 0;
    } catch (Exception $e) {
        return 0;
    }
}

function safeHtml($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function getServerInfo() {
    return [
        'php_version' => phpversion(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
        'server_name' => $_SERVER['SERVER_NAME'] ?? 'localhost',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
    ];
}

function getSystemUptime() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return 'Windows Server';
    } else {
        $uptime = @file_get_contents('/proc/uptime');
        if ($uptime) {
            $seconds = (int)explode(' ', $uptime)[0];
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $days . 'd ' . $hours . 'h ' . $minutes . 'm';
        }
        return 'N/A';
    }
}

// ============================================================
// 4. COLUMN CHECKS
// ============================================================
$has_status = columnExists('reservations', 'status');
$has_is_active = columnExists('offers', 'is_active');
$has_created_at = columnExists('reservations', 'created_at');
$has_msg_status = columnExists('messages', 'status');

// ============================================================
// 5. GET STATS WITH ERROR HANDLING
// ============================================================
$stats = [
    'reservations' => 0,
    'menu' => 0,
    'messages' => 0,
    'offers' => 0,
    'pending' => 0,
    'today_reservations' => 0,
    'total_messages' => 0,
    'std_count' => 0,
    'prem_count' => 0,
    'lux_count' => 0,
];

try {
    $stats['reservations'] = getCount("SELECT COUNT(*) as c FROM reservations");
    $stats['menu'] = getCount("SELECT COUNT(*) as c FROM menu");
    $stats['total_messages'] = getCount("SELECT COUNT(*) as c FROM messages");
    
    if ($has_msg_status) {
        $stats['messages'] = getCount("SELECT COUNT(*) as c FROM messages WHERE status='unread'");
    } else {
        $stats['messages'] = $stats['total_messages'];
    }
    
    if ($has_is_active) {
        $stats['offers'] = getCount("SELECT COUNT(*) as c FROM offers WHERE is_active=1");
    } else {
        $stats['offers'] = getCount("SELECT COUNT(*) as c FROM offers");
    }
    
    if ($has_status) {
        $stats['pending'] = getCount("SELECT COUNT(*) as c FROM reservations WHERE status='pending'");
    }
    
    $today = date('Y-m-d');
    $stats['today_reservations'] = getCount("SELECT COUNT(*) as c FROM reservations WHERE date='$today'");
    
    $stats['std_count'] = getCount("SELECT COUNT(*) as c FROM menu WHERE category='Standard'");
    $stats['prem_count'] = getCount("SELECT COUNT(*) as c FROM menu WHERE category='Premium'");
    $stats['lux_count'] = getCount("SELECT COUNT(*) as c FROM menu WHERE category='Luxury'");
} catch (Exception $e) {
    // Silently fail - stats remain 0
}

// ============================================================
// 6. GET RECENT RESERVATIONS
// ============================================================
$recent_reservations = [];
try {
    $recent_sql = "SELECT * FROM reservations ORDER BY id DESC LIMIT 5";
    $recent_result = mysqli_query($conn, $recent_sql);
    if ($recent_result && mysqli_num_rows($recent_result) > 0) {
        while ($row = mysqli_fetch_assoc($recent_result)) {
            $recent_reservations[] = $row;
        }
    }
} catch (Exception $e) {
    // No recent reservations
}

// ============================================================
// 7. SERVER & SYSTEM INFO
// ============================================================
$server_info = getServerInfo();
$system_uptime = getSystemUptime();
$db_status = $db_connected ? 'Connected ✅' : '❌ ' . $db_error;
$db_status_class = $db_connected ? 'success' : 'error';

// ============================================================
// 8. ADMIN INFO
// ============================================================
$admin_email = $_SESSION['admin_email'] ?? 'Admin';
$admin_id = $_SESSION['admin_id'] ?? 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Gourmet</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ============================================================
                   GLOBAL RESET & BASE
                   ============================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: #050e0c;
            color: #fff;
            display: flex;
            min-height: 100vh;
        }

        /* ============================================================
                   SIDEBAR
                   ============================================================ */
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
        .sidebar a i {
            width: 22px;
            font-size: 18px;
            text-align: center;
        }
        .sidebar a:hover,
        .sidebar a.active {
            background: #1e4538;
            color: #D4AF37;
        }
        .sidebar a.active {
            font-weight: 600;
        }
        .sidebar a.logout {
            color: #e74c3c;
            margin-top: 30px;
            border-top: 1px solid #1e4538;
            padding-top: 20px;
        }
        .sidebar a.logout:hover {
            background: #e74c3c;
            color: #fff;
        }

        /* ============================================================
                   MAIN CONTENT
                   ============================================================ */
        .main-content {
            margin-left: 300px;
            padding: 40px 50px 60px;
            width: calc(100% - 300px);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        /* ============================================================
                   HEADER
                   ============================================================ */
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 10px;
        }
        .header-top h1 {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            color: #fff;
        }
        .header-top h1 span {
            color: #D4AF37;
        }
        .header-top h1 i {
            margin-right: 10px;
            color: #D4AF37;
        }
        .header-top .greeting {
            color: #888;
            font-size: 15px;
            margin-top: 5px;
        }
        .header-top .greeting i {
            margin-right: 6px;
            color: #D4AF37;
        }

        /* Date/Time Badge */
        .datetime-badge {
            background: #0a1914;
            border: 1px solid #1e4538;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 14px;
            color: #aaa;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
        }
        .datetime-badge i {
            color: #D4AF37;
        }

        /* ============================================================
                   SYSTEM STATUS BAR
                   ============================================================ */
        .system-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 15px 25px;
            background: #0a1914;
            padding: 12px 20px;
            border-radius: 12px;
            border: 1px solid #1e4538;
            margin: 15px 0 25px;
            font-size: 13px;
            color: #888;
        }
        .system-bar .item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .system-bar .item i {
            color: #D4AF37;
            font-size: 14px;
        }
        .system-bar .item .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .system-bar .item .status-dot.success {
            background: #2ecc71;
        }
        .system-bar .item .status-dot.error {
            background: #e74c3c;
        }
        .system-bar .item .status-dot.warning {
            background: #f39c12;
        }

        /* ============================================================
                   STATS CARDS
                   ============================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 10px;
        }
        .stat-card {
            background: #0a1914;
            padding: 24px 20px;
            border-radius: 18px;
            border: 1px solid #1e4538;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            cursor: default;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #D4AF37, transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .stat-card:hover::after {
            opacity: 1;
        }
        .stat-card:hover {
            border-color: #D4AF37;
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        .stat-card .icon {
            font-size: 28px;
            margin-bottom: 6px;
            display: block;
        }
        .stat-card h4 {
            color: #888;
            font-size: 13px;
            font-weight: 400;
            letter-spacing: 0.5px;
        }
        .stat-card .number {
            font-size: 34px;
            font-weight: 700;
            color: #D4AF37;
            margin-top: 4px;
        }
        .stat-card .trend {
            font-size: 12px;
            color: #2ecc71;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .stat-card .trend.down {
            color: #e74c3c;
        }
        .stat-card .trend.warning {
            color: #f39c12;
        }

        /* ============================================================
                   WELCOME BOX / QUICK ACTIONS
                   ============================================================ */
        .welcome-box {
            background: linear-gradient(135deg, #0f1f18, #0a1914);
            padding: 28px 32px;
            border-radius: 18px;
            border-left: 4px solid #D4AF37;
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .welcome-box .content h3 {
            color: #D4AF37;
            font-size: 20px;
        }
        .welcome-box .content p {
            color: #aaa;
            font-size: 14px;
            margin-top: 4px;
        }
        .welcome-box .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .welcome-box .actions a {
            color: #D4AF37;
            text-decoration: none;
            padding: 8px 16px;
            border: 1px solid rgba(212, 175, 55, 0.25);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .welcome-box .actions a:hover {
            background: #D4AF37;
            color: #0a1914;
            border-color: #D4AF37;
            transform: translateY(-2px);
        }

        /* ============================================================
                   RECENT ACTIVITY / QUICK STATS
                   ============================================================ */
        .recent-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-top: 30px;
        }
        .recent-card {
            background: #0a1914;
            padding: 22px 22px 18px;
            border-radius: 18px;
            border: 1px solid #1e4538;
        }
        .recent-card h3 {
            color: #D4AF37;
            font-size: 17px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .recent-card h3 i {
            font-size: 17px;
        }

        .recent-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 0;
            border-bottom: 1px solid rgba(30, 69, 56, 0.3);
        }
        .recent-item:last-child {
            border-bottom: none;
        }
        .recent-item .info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .recent-item .info .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }
        .recent-item .info .dot.green {
            background: #2ecc71;
        }
        .recent-item .info .dot.orange {
            background: #f39c12;
        }
        .recent-item .info .dot.red {
            background: #e74c3c;
        }
        .recent-item .info .dot.gold {
            background: #D4AF37;
        }
        .recent-item .info .name {
            font-size: 14px;
            color: #fff;
        }
        .recent-item .info .detail {
            font-size: 12px;
            color: #666;
        }
        .recent-item .time {
            font-size: 12px;
            color: #555;
            flex-shrink: 0;
        }

        /* Quick Stats Mini */
        .quick-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(30, 69, 56, 0.3);
        }
        .quick-stat:last-child {
            border-bottom: none;
        }
        .quick-stat .label {
            color: #aaa;
            font-size: 13px;
        }
        .quick-stat .value {
            color: #D4AF37;
            font-weight: 600;
            font-size: 15px;
        }

        /* ============================================================
                   HAMBURGER MENU (Mobile)
                   ============================================================ */
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
        .hamburger:hover {
            background: rgba(212, 175, 55, 0.1);
        }

        /* ============================================================
                   RESPONSIVE
                   ============================================================ */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 260px;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 30px 25px 50px;
            }
            .hamburger {
                display: flex !important;
            }
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            .recent-section {
                grid-template-columns: 1fr;
            }
            .header-top {
                flex-direction: column;
                align-items: stretch;
            }
            .datetime-badge {
                align-self: flex-start;
            }
            .system-bar {
                flex-direction: column;
                gap: 8px;
            }
        }

        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            .stat-card {
                padding: 16px 14px;
            }
            .stat-card .number {
                font-size: 26px;
            }
            .header-top h1 {
                font-size: 26px;
            }
            .welcome-box {
                flex-direction: column;
                align-items: stretch;
                padding: 22px 18px;
            }
            .welcome-box .actions {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                width: 60px;
                padding: 15px 8px;
            }
            .sidebar h2 {
                display: none;
            }
            .sidebar a {
                padding: 10px 8px;
                font-size: 11px;
                text-align: center;
                justify-content: center;
            }
            .sidebar a span {
                display: none;
            }
            .sidebar a i {
                width: auto;
                font-size: 20px;
            }
            .main-content {
                margin-left: 0;
                padding: 18px 10px 35px;
            }
            .stats-row {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            .stat-card {
                padding: 12px 10px;
                border-radius: 12px;
            }
            .stat-card .number {
                font-size: 20px;
            }
            .stat-card .icon {
                font-size: 20px;
            }
            .stat-card h4 {
                font-size: 11px;
            }
            .header-top h1 {
                font-size: 20px;
            }
            .datetime-badge {
                font-size: 11px;
                padding: 5px 12px;
            }
            .recent-card {
                padding: 15px 12px;
            }
            .recent-item .info .name {
                font-size: 12px;
            }
            .welcome-box .content h3 {
                font-size: 16px;
            }
            .welcome-box .actions a {
                font-size: 11px;
                padding: 6px 12px;
            }
            .system-bar {
                font-size: 11px;
                padding: 8px 14px;
            }
        }
    </style>
</head>
<body>

    <!-- ============================================================
    HAMBURGER MENU (Mobile)
    ============================================================ -->
    <button class="hamburger" id="hamburgerBtn" aria-label="Toggle Sidebar">
        <i class="fas fa-bars"></i>
    </button>

    <!-- ============================================================
    SIDEBAR
    ============================================================ -->
    <div class="sidebar" id="sidebar">
        <h2>✨ GOURMET</h2>
        <a href="admin.php" class="active"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a>
        <a href="admin_reservations.php"><i class="fas fa-calendar-check"></i> <span>Reservations</span></a>
        <a href="admin_menu.php"><i class="fas fa-utensils"></i> <span>Menu</span></a>
        <a href="admin_reviews.php"><i class="fas fa-star"></i> Ratings</a>
        <a href="admin_messages.php"><i class="fas fa-envelope"></i> <span>Messages</span></a>
        <a href="admin_offers.php"><i class="fas fa-tags"></i> <span>Offers</span></a>
        <a href="admin_register.php"><i class="fas fa-user-plus"></i> <span>Register</span></a>
        <a href="admin_logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>

    <!-- ============================================================
    MAIN CONTENT
    ============================================================ -->
    <div class="main-content">

        <!-- Header -->
        <div class="header-top">
            <div>
                <h1><i class="fas fa-chart-pie"></i> <span>Dashboard</span></h1>
                <div class="greeting">
                    <i class="fas fa-user-circle"></i> Welcome back, <?php echo safeHtml($admin_email); ?>!
                    <span style="color:#555; font-size:13px; margin-left:10px;">
                        <i class="fas fa-id-badge"></i> ID: <?php echo safeHtml($admin_id); ?>
                    </span>
                </div>
            </div>
            <div class="datetime-badge">
                <i class="fas fa-calendar-alt"></i>
                <?php echo date('l, d M Y'); ?>
                <span style="color:#555;">|</span>
                <i class="fas fa-clock"></i>
                <?php echo date('h:i A'); ?>
            </div>
        </div>

        <!-- System Status Bar -->
        <div class="system-bar">
            <span class="item">
                <i class="fas fa-database"></i> DB:
                <span class="status-dot <?php echo $db_status_class; ?>"></span>
                <?php echo $db_status; ?>
            </span>
            <span class="item">
                <i class="fas fa-server"></i> PHP: <?php echo safeHtml($server_info['php_version']); ?>
            </span>
            <span class="item">
                <i class="fas fa-clock"></i> Uptime: <?php echo $system_uptime; ?>
            </span>
            <span class="item">
                <i class="fas fa-tag"></i> v2.0.0
            </span>
            <span class="item">
                <i class="fas fa-shield-alt" style="color:#2ecc71;"></i> Secure
            </span>
        </div>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <span class="icon">📅</span>
                <h4>Total Reservations</h4>
                <div class="number"><?php echo number_format($stats['reservations']); ?></div>
                <?php if ($has_status && $stats['pending'] > 0): ?>
                    <div class="trend warning"><i class="fas fa-circle" style="color:#f39c12;font-size:8px;"></i> <?php echo $stats['pending']; ?> pending</div>
                <?php else: ?>
                    <div class="trend"><i class="fas fa-check-circle"></i> All processed</div>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <span class="icon">🍽️</span>
                <h4>Menu Items</h4>
                <div class="number"><?php echo number_format($stats['menu']); ?></div>
                <div class="trend"><i class="fas fa-tag"></i> <?php echo $stats['std_count']; ?> Standard</div>
            </div>
            <div class="stat-card">
                <span class="icon">💬</span>
                <h4><?php echo $has_msg_status ? 'Unread' : 'Total'; ?> Messages</h4>
                <div class="number"><?php echo number_format($stats['messages']); ?></div>
                <?php if ($stats['messages'] > 0 && $has_msg_status): ?>
                    <div class="trend warning"><i class="fas fa-exclamation-circle" style="color:#e74c3c;"></i> Need attention</div>
                <?php else: ?>
                    <div class="trend"><i class="fas fa-check-circle"></i> <?php echo $stats['messages'] > 0 ? 'All read' : 'No messages'; ?></div>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <span class="icon">🏷️</span>
                <h4>Active Offers</h4>
                <div class="number"><?php echo number_format($stats['offers']); ?></div>
                <div class="trend"><i class="fas fa-percent"></i> Promotions</div>
            </div>
        </div>

        <!-- Welcome Box / Quick Actions -->
        <div class="welcome-box">
            <div class="content">
                <h3>✨ Quick Actions</h3>
                <p>Manage your restaurant efficiently with these shortcuts.</p>
            </div>
            <div class="actions">
                <a href="add_menu.php"><i class="fas fa-plus-circle"></i> Add Menu</a>
                <a href="admin_offers.php"><i class="fas fa-plus-circle"></i> Create Offer</a>
                <a href="admin_reservations.php"><i class="fas fa-eye"></i> View Reservations</a>
                <a href="admin_messages.php"><i class="fas fa-envelope"></i> Messages</a>
            </div>
        </div>

        <!-- Recent Activity Section -->
        <div class="recent-section">

            <!-- Recent Reservations -->
            <div class="recent-card">
                <h3><i class="fas fa-clock-rotate-left"></i> Recent Reservations</h3>
                <?php if (!empty($recent_reservations)): ?>
                    <?php foreach ($recent_reservations as $row): ?>
                        <?php
                        if ($has_status && isset($row['status'])) {
                            $dot_color = $row['status'] == 'pending' ? 'orange' : ($row['status'] == 'confirmed' ? 'green' : 'red');
                            $status_text = ucfirst($row['status']);
                        } else {
                            $dot_color = 'gold';
                            $status_text = 'New';
                        }
                        $res_date = isset($row['reservation_date']) ? $row['reservation_date'] : ($row['date'] ?? '');
                        $guests = $row['guests'] ?? '0';
                        ?>
                        <div class="recent-item">
                            <div class="info">
                                <span class="dot <?php echo $dot_color; ?>"></span>
                                <div>
                                    <div class="name"><?php echo safeHtml($row['name']); ?></div>
                                    <div class="detail"><?php echo date('d M Y', strtotime($res_date)); ?> • <?php echo $guests; ?> guests</div>
                                </div>
                            </div>
                            <span class="time"><?php echo $status_text; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="color:#555; text-align:center; padding:20px 0;">
                        <i class="fas fa-calendar-times" style="font-size:32px; display:block; margin-bottom:8px; color:#333;"></i>
                        No recent reservations
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Stats -->
            <div class="recent-card">
                <h3><i class="fas fa-chart-simple"></i> Quick Stats</h3>
                
                <div class="quick-stat">
                    <span class="label">⭐ Standard Items</span>
                    <span class="value"><?php echo number_format($stats['std_count']); ?></span>
                </div>
                <div class="quick-stat">
                    <span class="label">🔥 Premium Items</span>
                    <span class="value"><?php echo number_format($stats['prem_count']); ?></span>
                </div>
                <div class="quick-stat">
                    <span class="label">💎 Luxury Items</span>
                    <span class="value"><?php echo number_format($stats['lux_count']); ?></span>
                </div>
                <div class="quick-stat" style="border-bottom: none;">
                    <span class="label">📅 Today's Reservations</span>
                    <span class="value"><?php echo number_format($stats['today_reservations']); ?></span>
                </div>
            </div>

        </div>

        <!-- Footer Note -->
        <div style="margin-top: 30px; text-align: center; color: #444; font-size: 13px; border-top: 1px solid #1e4538; padding-top: 20px;">
            <i class="fas fa-crown" style="color:#D4AF37;"></i>
            Gourmet Restaurant Management System v2.0.0
            <span style="color:#333; margin: 0 10px;">|</span>
            <i class="fas fa-database"></i> <?php echo $db_connected ? 'Connected' : 'Disconnected'; ?>
            <span style="color:#333; margin: 0 10px;">|</span>
            <i class="fas fa-user-shield"></i> <?php echo safeHtml($admin_email); ?>
        </div>

    </div>

    <!-- ============================================================
    JAVASCRIPT - Hamburger Menu & Live Clock
    ============================================================ -->
    <script>
        // ============================================================
        // HAMBURGER MENU TOGGLE (Mobile)
        // ============================================================
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

        // ============================================================
        // LIVE CLOCK UPDATE
        // ============================================================
        function updateClock() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
            const dateStr = now.toLocaleDateString('en-US', {
                weekday: 'long',
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
            const badge = document.querySelector('.datetime-badge');
            if (badge) {
                badge.innerHTML = `
                    <i class="fas fa-calendar-alt"></i> ${dateStr}
                    <span style="color:#555;">|</span>
                    <i class="fas fa-clock"></i> ${timeStr}
                `;
            }
        }
        setInterval(updateClock, 30000);
        document.addEventListener('DOMContentLoaded', updateClock);
    </script>

</body>
</html>