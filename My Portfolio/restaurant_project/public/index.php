<?php
// ============================================================
// HOME PAGE - Professional Version with Dynamic Reviews & Sold Out
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

// ============================================================
// 4. FETCH DATA FOR HOMEPAGE
// ============================================================

// Get menu items for highlights (limit 3)
$menu_highlights = [];
$menu_result = mysqli_query($conn, "SELECT * FROM menu ORDER BY id DESC LIMIT 3");
if ($menu_result && mysqli_num_rows($menu_result) > 0) {
    while ($row = mysqli_fetch_assoc($menu_result)) {
        $menu_highlights[] = $row;
    }
}

// Get active offer
$active_offer = null;
if (columnExists('offers', 'is_active')) {
    $offer_result = mysqli_query($conn, "SELECT * FROM offers WHERE is_active=1 LIMIT 1");
    if ($offer_result && mysqli_num_rows($offer_result) > 0) {
        $active_offer = mysqli_fetch_assoc($offer_result);
    }
}

// Get total menu count
$menu_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM menu"))['c'] ?? 0;

// ============================================================
// 5. CHECK SETTING: Show Reviews on Home?
// ============================================================
$show_reviews_on_home = true; // default
$setting_query = "SELECT setting_value FROM settings WHERE setting_key = 'show_reviews_on_home'";
$setting_res = mysqli_query($conn, $setting_query);
if ($setting_res && $row = mysqli_fetch_assoc($setting_res)) {
    $show_reviews_on_home = ($row['setting_value'] == '1');
}

// ============================================================
// 6. FETCH REVIEWS (only if enabled) - WITH is_visible = 1
// ============================================================
$reviews_list = [];
if ($show_reviews_on_home) {
    $reviews_query = "SELECT 
                            r.*, 
                            c.name as customer_name, 
                            res.name as reservation_name 
                        FROM reviews r
                        LEFT JOIN customers c ON r.customer_id = c.id
                        LEFT JOIN reservations res ON r.reservation_id = res.id
                        WHERE r.is_visible = 1
                        ORDER BY r.created_at DESC LIMIT 3";
    $reviews_result = mysqli_query($conn, $reviews_query);
    if ($reviews_result && mysqli_num_rows($reviews_result) > 0) {
        while ($row = mysqli_fetch_assoc($reviews_result)) {
            $reviews_list[] = $row;
        }
    }
}

// ============================================================
// 7. GET RATING STATS (only if enabled) - WITH is_visible = 1
// ============================================================
$rating_stats = ['avg' => 0, 'count' => 0];
if ($show_reviews_on_home) {
    $stats_query = "SELECT COALESCE(AVG(rating), 0) as avg_rating, COUNT(*) as total 
                    FROM reviews 
                    WHERE is_visible = 1";
    $stats_result = mysqli_query($conn, $stats_query);
    if ($stats_result && $row = mysqli_fetch_assoc($stats_result)) {
        $rating_stats['avg'] = round($row['avg_rating'], 1);
        $rating_stats['count'] = (int)$row['total'];
    }
}

// ============================================================
// 8. HELPER FUNCTIONS
// ============================================================
function columnExists($table, $column) {
    global $conn;
    if (!$conn) return false;
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

function getImageUrl($path) {
    $base_url = defined('BASE_URL') ? BASE_URL : '/restaurant_project/';
    if (empty($path)) {
        return $base_url . 'assets/default.jpg';
    }
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        return $path;
    }
    $clean = str_replace('assets/', '', $path);
    $clean = str_replace(' ', '%20', $clean);
    return $base_url . 'assets/' . $clean;
}

function safeHtml($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// ============================================================
// 9. FUNCTION TO GET RATING DATA FOR A DISH
// ============================================================
function getDishRating($conn, $dish_name) {
    $escaped_name = mysqli_real_escape_string($conn, $dish_name);
    $query = "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE dish_name = '$escaped_name'";
    $result = mysqli_query($conn, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return [
            'avg_rating' => (float)($row['avg_rating'] ?? 0),
            'review_count' => (int)($row['review_count'] ?? 0)
        ];
    }
    return ['avg_rating' => 0, 'review_count' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gourmet Restaurant | Fine Dining</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #050e0c; color: #fff; overflow-x: hidden; }

        /* ===== NAVIGATION ===== */
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
        nav ul li a.user-greeting { color: #D4AF37; }
        nav ul li a.logout-link { color: #e74c3c; }
        nav ul li a.logout-link:hover { color: #ff6b6b; }
        nav ul li a.login-link { color: #D4AF37; font-weight: 600; }

        /* ===== HERO ===== */
        .hero {
            position: relative;
            height: 92vh;
            min-height: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: url('https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') no-repeat center center/cover;
            background-attachment: fixed;
            overflow: hidden;
        }
        .hero::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(5,14,12,0.65); backdrop-filter: blur(2px); }
        .hero-content { position: relative; z-index: 1; max-width: 800px; padding: 20px; }
        .hero-content .badge { display: inline-block; background: rgba(212,175,55,0.15); border: 1px solid rgba(212,175,55,0.3); color: #D4AF37; padding: 6px 20px; border-radius: 50px; font-size: 14px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 20px; }
        .hero-content h1 { font-family: 'Playfair Display', serif; font-size: clamp(2.8rem, 8vw, 5.5rem); color: #D4AF37; text-shadow: 0 4px 30px rgba(212,175,55,0.2); margin-bottom: 20px; letter-spacing: 2px; line-height: 1.1; }
        .hero-content h1 .highlight { color: #fff; }
        .hero-content p { font-size: clamp(1rem, 2vw, 1.3rem); color: #b0c4b1; font-weight: 300; line-height: 1.8; max-width: 600px; margin: 0 auto 30px; }
        .hero-buttons { display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; }
        .btn-primary { display: inline-block; padding: 16px 45px; background: linear-gradient(135deg, #D4AF37, #b8962e); color: #0a1914; border-radius: 50px; font-weight: 700; text-decoration: none; transition: 0.3s; font-size: 16px; border: none; cursor: pointer; }
        .btn-primary:hover { background: #fff; transform: translateY(-4px); box-shadow: 0 12px 40px rgba(212,175,55,0.4); }
        .btn-secondary { display: inline-block; padding: 16px 45px; background: transparent; color: #fff; border: 2px solid rgba(255,255,255,0.3); border-radius: 50px; font-weight: 600; text-decoration: none; transition: 0.3s; font-size: 16px; }
        .btn-secondary:hover { border-color: #D4AF37; color: #D4AF37; transform: translateY(-4px); background: rgba(212,175,55,0.05); }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
        .hero-content .badge { animation: fadeInUp 0.8s ease-out forwards; }
        .hero-content h1 { animation: fadeInUp 1s ease-out forwards; }
        .hero-content p { animation: fadeInUp 1.2s ease-out forwards; }
        .hero-buttons { animation: fadeInUp 1.4s ease-out forwards; }

        /* ===== OFFER BANNER ===== */
        .offer-banner { background: linear-gradient(135deg, #1a2a1f, #0a1914); padding: 25px 30px; border-bottom: 2px solid #D4AF37; text-align: center; position: relative; overflow: hidden; }
        .offer-banner::before { content: '✦'; position: absolute; font-size: 120px; color: rgba(212,175,55,0.04); top: -30px; right: -30px; }
        .offer-banner p { font-size: 16px; color: #b0c4b1; }
        .offer-banner .offer-text { color: #D4AF37; font-weight: 700; }
        .offer-banner a { color: #D4AF37; text-decoration: none; font-weight: 600; transition: 0.3s; }
        .offer-banner a:hover { color: #fff; text-decoration: underline; }

        /* ===== CONTAINER & SECTIONS ===== */
        .container { max-width: 1200px; margin: 0 auto; padding: 60px 20px; }
        .section-title { text-align: center; margin-bottom: 50px; }
        .section-title .subtitle { color: #D4AF37; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 8px; }
        .section-title h2 { font-family: 'Playfair Display', serif; font-size: 40px; color: #D4AF37; letter-spacing: 2px; position: relative; display: inline-block; }
        .section-title h2::after { content: ''; position: absolute; bottom: -10px; left: 50%; transform: translateX(-50%); width: 60px; height: 3px; background: #D4AF37; }
        .section-title p { color: #888; font-size: 16px; margin-top: 20px; }

        /* ===== FEATURES ===== */
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-top: 20px; }
        .feature-card { background: #0a1914; padding: 35px 25px; border-radius: 20px; border: 1px solid #1e4538; text-align: center; transition: 0.4s ease; }
        .feature-card:hover { transform: translateY(-10px); border-color: #D4AF37; box-shadow: 0 15px 40px rgba(212,175,55,0.08); }
        .feature-card .icon { font-size: 48px; color: #D4AF37; margin-bottom: 15px; }
        .feature-card h3 { font-size: 20px; color: #fff; margin-bottom: 8px; }
        .feature-card p { color: #888; font-size: 14px; line-height: 1.6; }

        /* ===== MENU HIGHLIGHTS ===== */
        .menu-highlights { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-top: 20px; }
        .menu-item-card { background: #0a1914; border-radius: 20px; overflow: hidden; border: 1px solid #1e4538; transition: 0.4s ease; }
        .menu-item-card:hover { transform: translateY(-8px); border-color: #D4AF37; box-shadow: 0 15px 40px rgba(212,175,55,0.1); }

        /* 🔥 SOLD OUT OVERLAY FOR MENU ITEMS */
        .menu-item-card .img-wrap {
            position: relative;
            overflow: hidden;
            height: 200px;
        }
        .menu-item-card .img-wrap .sold-out-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            color: #e74c3c;
            text-transform: uppercase;
            letter-spacing: 2px;
            backdrop-filter: blur(2px);
        }
        .menu-item-card .img-wrap .sold-out-overlay span {
            background: rgba(0,0,0,0.6);
            padding: 8px 20px;
            border-radius: 10px;
            border: 2px solid #e74c3c;
        }
        .menu-item-card .img-wrap .sold-out-overlay i {
            margin-right: 6px;
        }
        .menu-item-card.sold-out .info .price {
            opacity: 0.5;
        }
        .menu-item-card.sold-out .info .rating-stars {
            opacity: 0.5;
        }

        .menu-item-card .img-wrap img { width: 100%; height: 100%; object-fit: cover; filter: brightness(0.85); transition: 0.4s; }
        .menu-item-card:hover .img-wrap img { filter: brightness(1); transform: scale(1.03); }
        .menu-item-card .img-wrap .cat-tag { position: absolute; top: 12px; right: 12px; background: rgba(212,175,55,0.85); color: #0a1914; padding: 4px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .menu-item-card .info { padding: 20px 22px 25px; }
        .menu-item-card .info h4 { font-size: 20px; color: #fff; font-weight: 600; margin-bottom: 2px; }
        .menu-item-card .info .category { color: #D4AF37; font-size: 13px; font-weight: 500; }
        .menu-item-card .info .rating-stars { margin: 6px 0 10px; display: flex; align-items: center; gap: 2px; }
        .menu-item-card .info .rating-stars i { font-size: 14px; }
        .menu-item-card .info .rating-stars .star-filled { color: #D4AF37; }
        .menu-item-card .info .rating-stars .star-empty { color: #555; }
        .menu-item-card .info .rating-stars .rating-text { color: #888; font-size: 12px; margin-left: 6px; }
        .menu-item-card .info .rating-stars .rating-count { color: #555; font-size: 11px; margin-left: 4px; }
        .menu-item-card .info .rating-stars .no-reviews { color: #666; font-size: 12px; display: flex; align-items: center; gap: 4px; }
        .menu-item-card .info p { color: #888; font-size: 14px; margin: 8px 0; line-height: 1.5; }
        .menu-item-card .info .price { font-size: 22px; font-weight: 700; color: #D4AF37; }

        /* ===== STATS ===== */
        .stats-section { background: #0a1914; padding: 60px 20px; border-radius: 20px; border: 1px solid #1e4538; margin: 30px 0; }
        .stats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 30px; text-align: center; }
        .stat-item .number { font-size: 48px; font-weight: 700; color: #D4AF37; display: block; font-family: 'Playfair Display', serif; }
        .stat-item .label { font-size: 16px; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-top: 5px; }

        /* ===== TESTIMONIALS ===== */
        .testimonials-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-top: 20px; }
        .testimonial-card { background: #0a1914; padding: 30px 25px; border-radius: 20px; border: 1px solid #1e4538; transition: 0.4s ease; }
        .testimonial-card:hover { border-color: #D4AF37; }
        .testimonial-card .stars { color: #D4AF37; font-size: 18px; margin-bottom: 12px; }
        .testimonial-card .stars .empty { color: #444; }
        .testimonial-card blockquote { color: #b0c4b1; font-size: 15px; line-height: 1.7; font-style: italic; }
        .testimonial-card .author { margin-top: 15px; display: flex; align-items: center; gap: 12px; }
        .testimonial-card .author .avatar { width: 45px; height: 45px; border-radius: 50%; background: #1e4538; display: flex; align-items: center; justify-content: center; color: #D4AF37; font-weight: 700; font-size: 18px; flex-shrink: 0; border: 2px solid #D4AF37; }
        .testimonial-card .author .name { font-weight: 600; font-size: 15px; }
        .testimonial-card .author .title { color: #888; font-size: 13px; }

        /* ===== CTA ===== */
        .cta-section { background: linear-gradient(135deg, #0f1f18, #0a1914); padding: 50px 30px; border-radius: 20px; text-align: center; border: 1px solid #1e4538; margin: 30px 0 10px; }
        .cta-section h2 { font-family: 'Playfair Display', serif; font-size: 34px; color: #D4AF37; margin-bottom: 15px; }
        .cta-section p { color: #aaa; font-size: 16px; margin-bottom: 25px; }

        /* ===== FOOTER ===== */
        footer { background: #0a1914; border-top: 2px solid #1e4538; padding: 30px 20px; text-align: center; margin-top: 40px; }
        footer .socials { display: flex; justify-content: center; gap: 20px; margin-bottom: 15px; }
        footer .socials a { color: #b0c4b1; font-size: 22px; transition: 0.3s; }
        footer .socials a:hover { color: #D4AF37; transform: translateY(-3px); }
        footer p { color: #555; font-size: 14px; }
        footer p i { color: #D4AF37; }

        /* ===== SCROLL ===== */
        .hidden { opacity: 0; transform: translateY(40px); transition: all 0.8s ease; }
        .visible { opacity: 1; transform: translateY(0); }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .features-grid, .menu-highlights, .testimonials-grid { grid-template-columns: 1fr 1fr; }
            .stats-grid { grid-template-columns: repeat(3, 1fr) !important; }
            .hero { height: 70vh; min-height: 400px; }
            nav { padding: 15px 25px; flex-wrap: wrap; }
            nav ul { gap: 15px; justify-content: center; }
            nav ul li a { font-size: 13px; }
        }
        @media (max-width: 600px) {
            .features-grid, .menu-highlights, .testimonials-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 15px; }
            .hero { height: 60vh; min-height: 350px; }
            .hero-content h1 { font-size: 2.2rem; }
            .hero-content p { font-size: 0.95rem; }
            .btn-primary, .btn-secondary { padding: 12px 30px; font-size: 14px; }
            .container { padding: 30px 15px; }
            .section-title h2 { font-size: 30px; }
            .stat-item .number { font-size: 32px; }
            .offer-banner p { font-size: 14px; }
            nav { flex-direction: column; gap: 10px; }
        }
        @media (max-width: 400px) {
            .stats-grid { grid-template-columns: 1fr !important; }
            .hero { height: 50vh; min-height: 300px; }
            .hero-content h1 { font-size: 1.8rem; }
            .hero-buttons { flex-direction: column; align-items: center; }
            .btn-primary, .btn-secondary { width: 80%; text-align: center; }
        }
    </style>
</head>
<body>

    <!-- ===== NAVIGATION ===== -->
    <nav>
        <div class="logo">GOURMET</div>
        <ul>
            <li><a href="<?php echo BASE_URL; ?>public/index.php" class="active">Home</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/about.php">About</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/contact.php">Contact</a></li>
            <li>
                <?php if (isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id'])): ?>
                    <a href="<?php echo BASE_URL; ?>public/reservation.php">Reservation</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>public/login.php?redirect=reservation">Reservation</a>
                <?php endif; ?>
            </li>
            <li><a href="<?php echo BASE_URL; ?>public/menu.php">Menu</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/offers.php">Offers</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/rate.php"><i class="fas fa-star" style="color:#D4AF37;"></i> Rate Us</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/notifications.php">Notifications</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
            <!-- Invoices link removed as requested -->
            <?php if (isset($_SESSION['customer_id']) && isset($_SESSION['res_name'])): ?>
                <li><a href="#" class="user-greeting"><i class="fas fa-user-circle"></i> <?php echo safeHtml(explode(' ', $_SESSION['res_name'])[0]); ?></a></li>
                <li><a href="<?php echo BASE_URL; ?>public/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php else: ?>
                <li><a href="<?php echo BASE_URL; ?>public/login.php" class="login-link"><i class="fas fa-sign-in-alt"></i> Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- ===== CUSTOMER NOTIFICATIONS ===== -->
    <?php
    if (isset($_SESSION['customer_id'])) {
        $cust_id = (int)$_SESSION['customer_id'];
        $notif_query = "SELECT * FROM messages WHERE customer_id = $cust_id AND status = 'unread' ORDER BY created_at DESC LIMIT 5";
        $notif_result = mysqli_query($conn, $notif_query);
        if ($notif_result && mysqli_num_rows($notif_result) > 0) {
            $notif_count = mysqli_num_rows($notif_result);
            ?>
            <div style="max-width:1200px; margin:20px auto; padding:0 20px;">
                <div style="background:rgba(212,175,55,0.05); border:1px solid rgba(212,175,55,0.2); border-radius:12px; padding:15px 20px;">
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                        <span style="color:#D4AF37; font-weight:600; font-size:16px;">
                            <i class="fas fa-bell"></i> Notifications 
                            <span style="background:#e74c3c; color:#fff; font-size:12px; padding:1px 10px; border-radius:20px;"><?php echo $notif_count; ?></span>
                        </span>
                        <a href="<?php echo BASE_URL; ?>public/mark_notifications_read.php" style="color:#888; font-size:13px; text-decoration:none; margin-left:auto;">
                            <i class="fas fa-check-double"></i> Mark all as read
                        </a>
                    </div>
                    <?php while ($notif = mysqli_fetch_assoc($notif_result)): ?>
                    <div style="background:#0a1914; padding:12px 16px; border-radius:8px; border-left:3px solid #D4AF37; margin-bottom:8px;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                            <div style="flex:1;">
                                <strong style="color:#fff; font-size:14px;">
                                    <i class="fas fa-envelope" style="color:#D4AF37; font-size:12px;"></i>
                                    <?php echo htmlspecialchars($notif['subject']); ?>
                                </strong>
                                <p style="color:#b0c4b1; font-size:13px; margin:4px 0 0; line-height:1.5;">
                                    <?php echo htmlspecialchars(strip_tags($notif['message'])); ?>
                                </p>
                            </div>
                            <span style="color:#555; font-size:11px; white-space:nowrap;">
                                <?php echo date('d M Y, h:i A', strtotime($notif['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <?php if ($notif_count > 5): ?>
                    <div style="text-align:center; margin-top:8px;">
                        <a href="<?php echo BASE_URL; ?>public/notifications.php" style="color:#D4AF37; font-size:13px; text-decoration:none;">
                            View all notifications <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
    }
    ?>

    <!-- ===== OFFER BANNER ===== -->
    <?php if ($active_offer): ?>
    <div class="offer-banner">
        <p>
            <i class="fas fa-tag" style="color:#D4AF37;"></i>
            <span class="offer-text"><?php echo safeHtml($active_offer['title']); ?></span>
            &mdash; <?php echo safeHtml($active_offer['description']); ?>
            <?php if (isset($active_offer['discount_percent'])): ?>
                <span style="color:#fff; font-weight:700;">(<?php echo $active_offer['discount_percent']; ?>% OFF)</span>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>public/offers.php">View Offer →</a>
        </p>
    </div>
    <?php endif; ?>

    <!-- ===== HERO ===== -->
    <section class="hero">
        <div class="hero-content">
            <div class="badge">✦ Since 2015</div>
            <h1>Where <span class="highlight">Flavors</span> <br>Become <span class="highlight">Memories</span></h1>
            <p>Indulge in a world of exquisite flavors, crafted with passion and served with elegance. Your table at Gourmet awaits.</p>
            <div class="hero-buttons">
                <a href="<?php echo BASE_URL; ?>public/reservation.php" class="btn-primary">
                    <i class="fas fa-utensils"></i> Reserve a Table
                </a>
                <a href="<?php echo BASE_URL; ?>public/menu.php" class="btn-secondary">
                    <i class="fas fa-book-open"></i> View Menu
                </a>
            </div>
        </div>
    </section>

    <!-- ===== FEATURES ===== -->
    <div class="container">
        <div class="section-title hidden">
            <div class="subtitle">Why Choose Us</div>
            <h2>Exceptional Dining Experience</h2>
            <p>Discover what makes Gourmet the perfect choice for your dining experience.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card hidden">
                <div class="icon"><i class="fas fa-seedling"></i></div>
                <h3>Fresh Ingredients</h3>
                <p>We source only the finest, locally-grown, seasonal ingredients for every dish.</p>
            </div>
            <div class="feature-card hidden">
                <div class="icon"><i class="fas fa-utensils"></i></div>
                <h3>Master Chefs</h3>
                <p>Our award-winning chefs bring creativity and passion to every plate.</p>
            </div>
            <div class="feature-card hidden">
                <div class="icon"><i class="fas fa-crown"></i></div>
                <h3>Elegant Ambiance</h3>
                <p>Experience a sophisticated setting perfect for any special occasion.</p>
            </div>
        </div>
    </div>

    <!-- ===== MENU HIGHLIGHTS ===== -->
    <div class="container" style="padding-top:0;">
        <div class="section-title hidden">
            <div class="subtitle">Our Signature Dishes</div>
            <h2>Featured Menu Items</h2>
            <p>Handcrafted creations that define the art of fine dining.</p>
        </div>
        <div class="menu-highlights">
            <?php if (!empty($menu_highlights)): ?>
                <?php foreach ($menu_highlights as $item): 
                    $rating_data = getDishRating($conn, $item['item_name']);
                    $avg_rating = $rating_data['avg_rating'];
                    $review_count = $rating_data['review_count'];
                    $full_stars = floor($avg_rating);
                    $half_star = ($avg_rating - $full_stars) >= 0.5 ? 1 : 0;
                    $is_available = isset($item['is_available']) ? (int)$item['is_available'] : 1;
                ?>
                    <div class="menu-item-card hidden <?php echo $is_available ? '' : 'sold-out'; ?>">
                        <div class="img-wrap">
                            <img src="<?php echo getImageUrl($item['image_url']); ?>" 
                                 alt="<?php echo safeHtml($item['item_name']); ?>"
                                 loading="lazy"
                                 onerror="this.src='<?php echo BASE_URL; ?>assets/default.jpg'">
                            <span class="cat-tag"><?php echo safeHtml($item['category']); ?></span>
                            <?php if (!$is_available): ?>
                                <div class="sold-out-overlay">
                                    <span><i class="fas fa-times-circle"></i> Sold Out</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="info">
                            <h4><?php echo safeHtml($item['item_name']); ?></h4>
                            <span class="category"><?php echo safeHtml($item['category']); ?></span>
                            <div class="rating-stars">
                                <?php if ($review_count > 0): ?>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $full_stars): ?>
                                            <i class="fas fa-star star-filled"></i>
                                        <?php elseif ($half_star && $i == $full_stars + 1): ?>
                                            <i class="fas fa-star-half-alt star-filled"></i>
                                        <?php else: ?>
                                            <i class="far fa-star star-empty"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <span class="rating-text">(<?php echo number_format($avg_rating, 1); ?>)</span>
                                    <span class="rating-count"><?php echo $review_count; ?> reviews</span>
                                <?php else: ?>
                                    <span class="no-reviews">
                                        <i class="far fa-star star-empty"></i>
                                        <i class="far fa-star star-empty"></i>
                                        <i class="far fa-star star-empty"></i>
                                        <i class="far fa-star star-empty"></i>
                                        <i class="far fa-star star-empty"></i>
                                        <span style="margin-left:6px;">No reviews yet</span>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p><?php echo safeHtml(substr($item['description'], 0, 60)) . '...'; ?></p>
                            <div class="price">LKR <?php echo number_format($item['price'], 2); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="feature-card hidden" style="grid-column:1/-1; text-align:center; padding:40px;">
                    <p style="color:#666;">No menu items available yet. <br>Check back soon for our delicious offerings!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== STATS ===== -->
    <div class="container" style="padding-top:0;">
        <div class="stats-section hidden">
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="number" data-target="<?php echo max(5, $menu_count > 0 ? $menu_count : 50); ?>">0</span>
                    <span class="label"><i class="fas fa-utensils"></i> Menu Items</span>
                </div>
                <div class="stat-item">
                    <span class="number" data-target="<?php echo max(3, (int)($menu_count > 0 ? ceil($menu_count / 8) : 15)); ?>">0</span>
                    <span class="label"><i class="fas fa-calendar-alt"></i> Years</span>
                </div>
                <div class="stat-item">
                    <span class="number" data-target="<?php echo max(2, (int)($menu_count > 0 ? ceil($menu_count / 12) : 8)); ?>">0</span>
                    <span class="label"><i class="fas fa-trophy"></i> Awards Won</span>
                </div>
                <div class="stat-item">
                    <span class="number" data-target="<?php echo max(500, $menu_count * 50); ?>">0</span>
                    <span class="label"><i class="fas fa-smile"></i> Happy Guests</span>
                </div>
                <?php if ($show_reviews_on_home): ?>
                <div class="stat-item">
                    <span class="number"><?php echo $rating_stats['avg'] > 0 ? $rating_stats['avg'] : '—'; ?></span>
                    <span class="label"><i class="fas fa-star"></i> Avg Rating</span>
                </div>
                <div class="stat-item">
                    <span class="number" data-target="<?php echo $rating_stats['count']; ?>">0</span>
                    <span class="label"><i class="fas fa-comment"></i> Reviews</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== TESTIMONIALS ===== -->
    <div class="container" style="padding-top:0;">
        <div class="section-title hidden">
            <div class="subtitle">What Our Guests Say</div>
            <h2><?php echo ($show_reviews_on_home && !empty($reviews_list)) ? 'Real Reviews' : 'Testimonials'; ?></h2>
            <p><?php echo ($show_reviews_on_home && !empty($reviews_list)) ? 'Honest feedback from our valued customers.' : 'Real stories from real people who dined with us.'; ?></p>
        </div>

        <div class="testimonials-grid">
            <?php if ($show_reviews_on_home && !empty($reviews_list)): ?>
                <?php foreach ($reviews_list as $review): 
                    $name = !empty($review['customer_name']) ? $review['customer_name'] : ($review['reservation_name'] ?? 'Guest');
                    $rating = (int)$review['rating'];
                    $comment = htmlspecialchars($review['comment'] ?? 'No comment provided.');
                    if (strlen($comment) > 120) { $comment = substr($comment, 0, 120) . '...'; }
                    $initial = strtoupper(substr($name, 0, 1));
                ?>
                    <div class="testimonial-card hidden">
                        <div class="stars">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <?php if($i <= $rating): ?>
                                    <i class="fas fa-star"></i>
                                <?php else: ?>
                                    <i class="fas fa-star empty"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <blockquote>"<?php echo $comment; ?>"</blockquote>
                        <div class="author">
                            <div class="avatar"><?php echo $initial; ?></div>
                            <div>
                                <div class="name"><?php echo htmlspecialchars($name); ?></div>
                                <div class="title">Rated <?php echo $rating; ?> stars</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="testimonial-card hidden">
                    <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <blockquote>"An unforgettable dining experience! The ambiance, the service, and the food were all absolutely perfect. Highly recommend!"</blockquote>
                    <div class="author">
                        <div class="avatar">J</div>
                        <div>
                            <div class="name">John Anderson</div>
                            <div class="title">Regular Guest</div>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card hidden">
                    <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <blockquote>"The best fine dining in Colombo! Every dish was a masterpiece. The wine pairing was impeccable. Five stars!"</blockquote>
                    <div class="author">
                        <div class="avatar">S</div>
                        <div>
                            <div class="name">Sarah Williams</div>
                            <div class="title">Food Critic</div>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card hidden">
                    <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <blockquote>"From the moment we walked in, we felt like royalty. The staff went above and beyond to make our anniversary special."</blockquote>
                    <div class="author">
                        <div class="avatar">M</div>
                        <div>
                            <div class="name">Michael & Lisa</div>
                            <div class="title">Anniversary Dinner</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== CTA ===== -->
    <div class="container" style="padding-top:0;">
        <div class="cta-section hidden">
            <h2>Ready for an Exceptional Dining Experience?</h2>
            <p>Book your table now and let us create a memorable evening for you.</p>
            <a href="<?php echo BASE_URL; ?>public/reservation.php" class="btn-primary">
                <i class="fas fa-calendar-check"></i> Reserve Now
            </a>
        </div>
    </div>

    <!-- ===== FOOTER ===== -->
    <footer>
        <div class="socials">
            <a href="#"><i class="fab fa-facebook-f"></i></a>
            <a href="#"><i class="fab fa-instagram"></i></a>
            <a href="#"><i class="fab fa-twitter"></i></a>
            <a href="#"><i class="fab fa-youtube"></i></a>
        </div>
        <p><i class="fas fa-crown"></i> Gourmet Restaurant &mdash; Where every dish tells a story.</p>
        <p style="margin-top:5px; font-size:12px; color:#333;">&copy; 2025 Gourmet. All rights reserved.</p>
    </footer>

    <!-- ===== JAVASCRIPT ===== -->
    <script>
        const hiddenElements = document.querySelectorAll('.hidden');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    entry.target.classList.remove('hidden');
                }
            });
        }, { threshold: 0.15 });
        hiddenElements.forEach(el => observer.observe(el));

        const counters = document.querySelectorAll('.number[data-target]');
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const target = parseInt(entry.target.getAttribute('data-target'));
                    let current = 0;
                    const increment = Math.ceil(target / 80);
                    const updateCounter = () => {
                        if (current < target) {
                            current += increment;
                            if (current > target) current = target;
                            entry.target.textContent = current.toLocaleString();
                            requestAnimationFrame(updateCounter);
                        }
                    };
                    updateCounter();
                    counterObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });
        counters.forEach(counter => counterObserver.observe(counter));

        console.log('Welcome to Gourmet Restaurant! 🍽️');
        console.log('BASE_URL: <?php echo BASE_URL; ?>');
    </script>

</body>
</html>