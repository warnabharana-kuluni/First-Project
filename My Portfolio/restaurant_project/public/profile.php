<?php
// ============================================================
// CUSTOMER PROFILE - View Reservations & Loyalty Points
// ============================================================

// ============================================================
// 1. SESSION START
// ============================================================
session_start();

// ============================================================
// 2. AUTO-DETECT BASE URL
// ============================================================
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    $path = dirname(dirname($script_name));
    return $protocol . '://' . $host . $path . '/';
}
if (!defined('BASE_URL')) define('BASE_URL', getBaseUrl());

// ============================================================
// 3. DATABASE CONNECTION
// ============================================================
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/loyalty.php";

// ============================================================
// 4. LOGIN CHECK
// ============================================================
if (!isset($_SESSION['customer_id']) || empty($_SESSION['customer_id'])) {
    $_SESSION['redirect_after_login'] = BASE_URL . 'public/profile.php';
    header("Location: " . BASE_URL . "public/login.php?redirect=profile");
    exit();
}

$customer_id = (int)$_SESSION['customer_id'];

// ============================================================
// 5. GET CUSTOMER INFO
// ============================================================
$customer_query = "SELECT * FROM customers WHERE id = $customer_id";
$customer_result = mysqli_query($conn, $customer_query);
$customer = mysqli_fetch_assoc($customer_result);

if (!$customer) {
    session_destroy();
    header("Location: " . BASE_URL . "public/login.php");
    exit();
}

// ============================================================
// 6. GET LOYALTY INFO
// ============================================================
$loyalty_info = getCustomerLoyaltyInfo($conn, $customer_id);
$points_history = getPointsHistory($conn, $customer_id, 20);

// ============================================================
// 7. GET RESERVATIONS HISTORY
// ============================================================
$reservations_query = "SELECT * FROM reservations WHERE email = '{$customer['email']}' ORDER BY id DESC LIMIT 20";
$reservations_result = mysqli_query($conn, $reservations_query);
$reservations = [];
if ($reservations_result && mysqli_num_rows($reservations_result) > 0) {
    while ($row = mysqli_fetch_assoc($reservations_result)) {
        $reservations[] = $row;
    }
}

// ============================================================
// 8. HANDLE POINTS REDEMPTION (AJAX)
// ============================================================
$redeem_msg = '';
$redeem_success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_points'])) {
    $points_to_redeem = (int)($_POST['points_to_redeem'] ?? 0);
    
    if ($points_to_redeem > 0) {
        $result = redeemLoyaltyPoints($conn, $customer_id, $points_to_redeem);
        if ($result['success']) {
            $redeem_msg = '<div class="msg-success"><i class="fas fa-check-circle"></i> ' . $result['message'] . '</div>';
            $redeem_success = true;
            // Refresh data
            $loyalty_info = getCustomerLoyaltyInfo($conn, $customer_id);
            $points_history = getPointsHistory($conn, $customer_id, 20);
        } else {
            $redeem_msg = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> ' . $result['message'] . '</div>';
        }
    }
}

// ============================================================
// 9. SAFE HTML HELPER
// ============================================================
function safeHtml($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Gourmet</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ============================================================
                   GLOBAL RESET & BASE
                   ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #050e0c; color: #fff; min-height: 100vh; }

        /* ============================================================
                   NAVIGATION
                   ============================================================ */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 50px;
            background: #0a1914;
            border-bottom: 2px solid #1e4538;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .logo { font-size: 26px; font-weight: 700; color: #D4AF37; letter-spacing: 2px; }
        nav ul { display: flex; list-style: none; gap: 25px; flex-wrap: wrap; align-items: center; }
        nav ul li a { text-decoration: none; color: #b0c4b1; font-weight: 500; transition: 0.3s; font-size: 14px; position: relative; }
        nav ul li a::after { content: ''; position: absolute; bottom: -4px; left: 0; width: 0; height: 2px; background: #D4AF37; transition: width 0.3s ease; }
        nav ul li a:hover::after, nav ul li a.active::after { width: 100%; }
        nav ul li a:hover, nav ul li a.active { color: #D4AF37; }
        nav ul li a.logout-link { color: #e74c3c; }
        nav ul li a.logout-link:hover { color: #ff6b6b; }

        /* ============================================================
                   PROFILE CONTAINER
                   ============================================================ */
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }

        .profile-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 30px;
        }

        /* ============================================================
                   LEFT SIDEBAR
                   ============================================================ */
        .profile-sidebar {
            background: #0a1914;
            border-radius: 20px;
            border: 1px solid #1e4538;
            padding: 30px 25px;
            text-align: center;
            position: sticky;
            top: 100px;
            height: fit-content;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #1e4538;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            border: 3px solid #D4AF37;
        }
        .profile-avatar i {
            font-size: 50px;
            color: #D4AF37;
        }
        .profile-sidebar h3 {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            color: #fff;
            margin-bottom: 4px;
        }
        .profile-sidebar .email {
            color: #888;
            font-size: 14px;
            margin-bottom: 15px;
        }

        /* Loyalty Card */
        .loyalty-card {
            background: rgba(212, 175, 55, 0.05);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 16px;
            padding: 20px;
            margin: 15px 0;
        }
        .loyalty-card .points-number {
            font-size: 48px;
            font-weight: 700;
            color: #D4AF37;
            font-family: 'Playfair Display', serif;
            display: block;
            line-height: 1;
        }
        .loyalty-card .points-label {
            color: #888;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .loyalty-card .stats-row {
            display: flex;
            justify-content: space-around;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #1e4538;
        }
        .loyalty-card .stats-row .stat {
            text-align: center;
        }
        .loyalty-card .stats-row .stat .num {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
        }
        .loyalty-card .stats-row .stat .label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }

        .btn-redeem {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #D4AF37, #b8962e);
            color: #0a1914;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }
        .btn-redeem:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.3);
        }
        .btn-redeem:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .redeem-form {
            margin-top: 12px;
            display: none;
        }
        .redeem-form.show {
            display: block;
        }
        .redeem-form input {
            width: 100%;
            padding: 10px 14px;
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .redeem-form input:focus {
            border-color: #D4AF37;
            outline: none;
        }
        .redeem-form .btn-confirm {
            width: 100%;
            padding: 10px;
            background: #2ecc71;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .redeem-form .btn-confirm:hover {
            background: #27ae60;
        }
        .redeem-form .btn-cancel {
            width: 100%;
            padding: 10px;
            background: transparent;
            color: #888;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 5px;
            transition: 0.3s;
        }
        .redeem-form .btn-cancel:hover {
            color: #fff;
        }

        /* Sidebar Links */
        .sidebar-links {
            margin-top: 20px;
            text-align: left;
        }
        .sidebar-links a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #b0c4b1;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 10px;
            transition: 0.3s;
            font-size: 14px;
        }
        .sidebar-links a:hover {
            background: #1e4538;
            color: #D4AF37;
        }
        .sidebar-links a i {
            width: 20px;
            color: #D4AF37;
        }
        .sidebar-links a.logout {
            color: #e74c3c;
            margin-top: 5px;
            border-top: 1px solid #1e4538;
            padding-top: 15px;
        }
        .sidebar-links a.logout i {
            color: #e74c3c;
        }
        .sidebar-links a.logout:hover {
            background: #e74c3c;
            color: #fff;
        }
        .sidebar-links a.logout:hover i {
            color: #fff;
        }

        /* ============================================================
                   RIGHT CONTENT
                   ============================================================ */
        .profile-content {
            background: #0a1914;
            border-radius: 20px;
            border: 1px solid #1e4538;
            padding: 30px 30px;
        }
        .profile-content .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: #D4AF37;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .profile-content .section-title i {
            font-size: 22px;
        }

        /* Points History */
        .history-list {
            max-height: 250px;
            overflow-y: auto;
        }
        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            border-bottom: 1px solid rgba(30, 69, 56, 0.3);
            font-size: 14px;
        }
        .history-item:last-child {
            border-bottom: none;
        }
        .history-item .points-earned {
            color: #2ecc71;
            font-weight: 700;
        }
        .history-item .points-redeemed {
            color: #e74c3c;
            font-weight: 700;
        }
        .history-item .desc {
            color: #b0c4b1;
            flex: 1;
            margin: 0 15px;
        }
        .history-item .date {
            color: #555;
            font-size: 12px;
            white-space: nowrap;
        }
        .no-history {
            color: #555;
            text-align: center;
            padding: 20px;
        }

        /* Reservations Table */
        .table-wrap {
            overflow-x: auto;
            margin-top: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }
        thead th {
            color: #D4AF37;
            padding: 12px 14px;
            text-align: left;
            border-bottom: 2px solid #1e4538;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tbody td {
            padding: 12px 14px;
            border-bottom: 1px solid #1e4538;
            font-size: 14px;
            vertical-align: middle;
        }
        tbody tr:hover td {
            background: rgba(30, 69, 56, 0.15);
        }

        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .badge-pending { background: rgba(243,156,18,0.2); color: #f39c12; border: 1px solid rgba(243,156,18,0.3); }
        .badge-confirmed { background: rgba(46,204,113,0.2); color: #2ecc71; border: 1px solid rgba(46,204,113,0.3); }
        .badge-cancelled { background: rgba(231,76,60,0.2); color: #e74c3c; border: 1px solid rgba(231,76,60,0.3); }

        .no-reservations {
            text-align: center;
            padding: 30px;
            color: #666;
        }
        .no-reservations i {
            font-size: 40px;
            display: block;
            margin-bottom: 10px;
            color: #333;
        }

        /* Messages */
        .msg-success, .msg-error {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .msg-success { background: rgba(46,204,113,0.12); border: 1px solid rgba(46,204,113,0.25); color: #2ecc71; }
        .msg-error { background: rgba(231,76,60,0.12); border: 1px solid rgba(231,76,60,0.25); color: #e74c3c; }

        /* ============================================================
                   RESPONSIVE
                   ============================================================ */
        @media (max-width: 992px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            .profile-sidebar {
                position: relative;
                top: 0;
            }
            nav { padding: 15px 25px; flex-wrap: wrap; }
            nav ul { gap: 15px; justify-content: center; }
            nav ul li a { font-size: 13px; }
        }
        @media (max-width: 600px) {
            .container { padding: 0 12px; margin-top: 20px; }
            .profile-content { padding: 20px 15px; }
            .loyalty-card .points-number { font-size: 36px; }
            .profile-sidebar { padding: 20px 15px; }
            .history-item { flex-wrap: wrap; gap: 5px; }
            .history-item .desc { margin: 0; width: 100%; }
        }
    </style>
</head>
<body>

    <!-- ============================================================
    NAVIGATION
    ============================================================ -->
    <nav>
        <div class="logo">GOURMET</div>
        <ul>
            <li><a href="<?php echo BASE_URL; ?>public/index.php">Home</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/about.php">About</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/contact.php">Contact</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/reservation.php">Reservation</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/menu.php">Menu</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/offers.php">Offers</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/rate.php"><i class="fas fa-star" style="color:#D4AF37;"></i> Rate Us</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/notifications.php">Notifications</a></li>
            <!-- Invoices link removed from nav bar -->
            <li><a href="<?php echo BASE_URL; ?>public/profile.php" class="active"><i class="fas fa-user-circle"></i> Profile</a></li>
        </ul>
    </nav>

    <!-- ============================================================
    MAIN CONTENT
    ============================================================ -->
    <div class="container">

        <?php if ($redeem_msg) echo $redeem_msg; ?>

        <div class="profile-grid">

            <!-- LEFT SIDEBAR -->
            <div class="profile-sidebar">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h3><?php echo safeHtml($customer['name']); ?></h3>
                <p class="email"><?php echo safeHtml($customer['email']); ?></p>

                <!-- Loyalty Card -->
                <div class="loyalty-card">
                    <span class="points-label">Available Points</span>
                    <span class="points-number"><?php echo number_format($loyalty_info['points']); ?></span>

                    <div class="stats-row">
                        <div class="stat">
                            <div class="num"><?php echo number_format($loyalty_info['total_earned']); ?></div>
                            <div class="label">Earned</div>
                        </div>
                        <div class="stat">
                            <div class="num"><?php echo number_format($loyalty_info['total_redeemed']); ?></div>
                            <div class="label">Used</div>
                        </div>
                        <div class="stat">
                            <div class="num"><?php echo floor($loyalty_info['points'] / 100); ?></div>
                            <div class="label">Discounts</div>
                        </div>
                    </div>
                </div>

                <!-- Redeem Button -->
                <button class="btn-redeem" id="showRedeemBtn" <?php echo $loyalty_info['points'] < 50 ? 'disabled' : ''; ?>>
                    <i class="fas fa-gift"></i> <?php echo $loyalty_info['points'] < 50 ? 'Need 50+ points' : 'Redeem Points'; ?>
                </button>

                <div class="redeem-form" id="redeemForm">
                    <p style="color:#888; font-size:13px; margin-bottom:8px;">
                        1 point = 1 LKR. Minimum 50 points. Max 500 per booking.
                    </p>
                    <form method="POST">
                        <input type="number" name="points_to_redeem" 
                               placeholder="Enter points (e.g. 100)" 
                               min="50" max="500" required>
                        <button type="submit" name="redeem_points" class="btn-confirm">
                            <i class="fas fa-check"></i> Confirm Redemption
                        </button>
                        <button type="button" class="btn-cancel" id="hideRedeemBtn">Cancel</button>
                    </form>
                </div>

                <!-- Sidebar Links -->
                <div class="sidebar-links">
                    <a href="<?php echo BASE_URL; ?>public/profile.php" class="active">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                    <a href="<?php echo BASE_URL; ?>public/reservation.php">
                        <i class="fas fa-calendar-plus"></i> New Reservation
                    </a>
                    <a href="<?php echo BASE_URL; ?>public/rate.php">
                        <i class="fas fa-star"></i> Rate Dishes
                    </a>
                    <a href="<?php echo BASE_URL; ?>public/notifications.php">
                        <i class="fas fa-bell"></i> Notifications
                    </a>
                    
                    <a href="<?php echo BASE_URL; ?>public/logout.php" class="logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- RIGHT CONTENT -->
            <div class="profile-content">

                <!-- Points History -->
                <div class="section-title">
                    <i class="fas fa-clock-rotate"></i> Points History
                </div>
                <div class="history-list">
                    <?php if (!empty($points_history)): ?>
                        <?php foreach ($points_history as $item): ?>
                            <div class="history-item">
                                <?php if ($item['points'] > 0): ?>
                                    <span class="points-earned">+<?php echo $item['points']; ?></span>
                                <?php else: ?>
                                    <span class="points-redeemed"><?php echo $item['points']; ?></span>
                                <?php endif; ?>
                                <span class="desc"><?php echo safeHtml($item['description']); ?></span>
                                <span class="date"><?php echo date('d M Y', strtotime($item['created_at'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-history">No points transactions yet. Book a table to start earning!</div>
                    <?php endif; ?>
                </div>

                <hr style="border-color:#1e4538; margin:25px 0;">

                <!-- Reservations History -->
                <div class="section-title">
                    <i class="fas fa-calendar-check"></i> My Reservations
                </div>

                <?php if (!empty($reservations)): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>#ID</th>
                                    <th>Date & Time</th>
                                    <th>Guests</th>
                                    <th>Dishes</th>
                                    <th>Total</th>
                                    <th>Points Earned</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $res): ?>
                                    <tr>
                                        <td><strong>#<?php echo str_pad($res['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td>
                                            <?php echo date('d M Y', strtotime($res['reservation_date'])); ?><br>
                                            <span style="color:#888; font-size:12px;"><?php echo date('h:i A', strtotime($res['reservation_time'])); ?></span>
                                        </td>
                                        <td><?php echo $res['guests']; ?></td>
                                        <td style="max-width:150px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                            <?php echo safeHtml(substr($res['selected_dishes'] ?? 'None', 0, 30)); ?>
                                        </td>
                                        <td style="color:#D4AF37;">LKR <?php echo number_format($res['total_amount'] ?? 0, 2); ?></td>
                                        <td style="color:#2ecc71;">
                                            <?php echo (int)($res['points_earned'] ?? 0); ?>
                                            <?php if (($res['points_redeemed'] ?? 0) > 0): ?>
                                                <span style="color:#e74c3c; font-size:11px; display:block;">-<?php echo $res['points_redeemed']; ?> used</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $res['status'] ?? 'pending'; ?>">
                                                <?php echo ucfirst($res['status'] ?? 'Pending'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-reservations">
                        <i class="fas fa-calendar-times"></i>
                        <p>You haven't made any reservations yet.</p>
                        <a href="<?php echo BASE_URL; ?>public/reservation.php" style="color:#D4AF37; text-decoration:none; display:inline-block; margin-top:10px;">
                            <i class="fas fa-arrow-right"></i> Book Now
                        </a>
                    </div>
                <?php endif; ?>

            </div>

        </div>

    </div>

    <!-- ============================================================
    FOOTER
    ============================================================ -->
    <footer style="background:#0a1914; border-top:2px solid #1e4538; padding:30px 20px; text-align:center; margin-top:40px;">
        <div style="display:flex; justify-content:center; gap:20px; margin-bottom:15px;">
            <a href="#" style="color:#b0c4b1; font-size:22px; transition:0.3s;"><i class="fab fa-facebook-f"></i></a>
            <a href="#" style="color:#b0c4b1; font-size:22px; transition:0.3s;"><i class="fab fa-instagram"></i></a>
            <a href="#" style="color:#b0c4b1; font-size:22px; transition:0.3s;"><i class="fab fa-twitter"></i></a>
            <a href="#" style="color:#b0c4b1; font-size:22px; transition:0.3s;"><i class="fab fa-youtube"></i></a>
        </div>
        <p style="color:#555; font-size:14px;"><i class="fas fa-crown" style="color:#D4AF37;"></i> Gourmet Restaurant &mdash; Where every dish tells a story.</p>
        <p style="margin-top:5px; font-size:12px; color:#333;">&copy; 2025 Gourmet. All rights reserved.</p>
    </footer>

    <!-- ============================================================
    JAVASCRIPT
    ============================================================ -->
    <script>
        // Redeem Form Toggle
        document.getElementById('showRedeemBtn').addEventListener('click', function() {
            document.getElementById('redeemForm').classList.toggle('show');
            this.style.display = 'none';
        });
        document.getElementById('hideRedeemBtn').addEventListener('click', function() {
            document.getElementById('redeemForm').classList.remove('show');
            document.getElementById('showRedeemBtn').style.display = 'block';
        });

        console.log('BASE_URL: <?php echo BASE_URL; ?>');
        console.log('Customer ID: <?php echo $customer_id; ?>');
    </script>

</body>
</html>