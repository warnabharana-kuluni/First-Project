<?php
// ============================================================
// OFFERS PAGE - Professional Version
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

if (!defined('BASE_URL')) {
    define('BASE_URL', getBaseUrl());
}

// ============================================================
// 3. DATABASE CONNECTION
// ============================================================
require_once __DIR__ . "/../includes/db.php";

// ============================================================
// 4. HELPER FUNCTIONS
// ============================================================
function safeHtml($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function getImageUrl($path) {
    $base_url = BASE_URL;
    if (empty($path)) {
        return $base_url . 'assets/default-offer.jpg';
    }
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        return $path;
    }
    $clean = str_replace('assets/', '', $path);
    $clean = str_replace(' ', '%20', $clean);
    return $base_url . 'assets/' . $clean;
}

function getPriceClass($price) {
    $price = (float)$price;
    if ($price < 2000) return 'standard';
    if ($price >= 2000 && $price <= 5000) return 'premium';
    return 'luxury';
}

function getTierIcon($tier) {
    switch ($tier) {
        case 'standard': return 'fa-star';
        case 'premium': return 'fa-fire';
        case 'luxury': return 'fa-crown';
        default: return 'fa-tag';
    }
}

function getTierLabel($tier) {
    switch ($tier) {
        case 'standard': return 'Standard';
        case 'premium': return 'Premium';
        case 'luxury': return 'Luxury';
        default: return 'Special';
    }
}

// ============================================================
// 5. FETCH OFFERS FROM DATABASE
// ============================================================
$all_offers = [];
$sql = "SELECT * FROM offers WHERE is_active = 1 ORDER BY id DESC";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $all_offers[] = $row;
    }
}

// Group offers by tier (based on discount_price)
$standard_offers = [];
$premium_offers = [];
$luxury_offers = [];

foreach ($all_offers as $offer) {
    $price = (float)$offer['discount_price'];
    $tier = getPriceClass($price);
    if ($tier == 'standard') {
        $standard_offers[] = $offer;
    } elseif ($tier == 'premium') {
        $premium_offers[] = $offer;
    } else {
        $luxury_offers[] = $offer;
    }
}

$has_offers = count($all_offers) > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Special Offers | Gourmet Fine Dining</title>
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
            overflow-x: hidden;
        }

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
        .logo {
            font-size: 26px;
            font-weight: 700;
            color: #D4AF37;
            letter-spacing: 2px;
        }
        nav ul {
            display: flex;
            list-style: none;
            gap: 25px;
            flex-wrap: wrap;
            align-items: center;
        }
        nav ul li a {
            text-decoration: none;
            color: #b0c4b1;
            font-weight: 500;
            transition: 0.3s;
            font-size: 14px;
            position: relative;
        }
        nav ul li a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: #D4AF37;
            transition: width 0.3s ease;
        }
        nav ul li a:hover::after,
        nav ul li a.active::after {
            width: 100%;
        }
        nav ul li a:hover,
        nav ul li a.active {
            color: #D4AF37;
        }
        nav ul li a.user-greeting {
            color: #D4AF37;
        }
        nav ul li a.logout-link {
            color: #e74c3c;
        }
        nav ul li a.logout-link:hover {
            color: #ff6b6b;
        }
        nav ul li a.login-link {
            color: #D4AF37;
            font-weight: 600;
        }

        /* ============================================================
                   HERO SECTION
                   ============================================================ */
        .hero {
            position: relative;
            height: 40vh;
            min-height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: url('https://images.unsplash.com/photo-1559339352-11d035aa65de?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') no-repeat center center/cover;
            background-attachment: fixed;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(5, 14, 12, 0.7);
            backdrop-filter: blur(2px);
        }
        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            padding: 20px;
            animation: fadeInUp 1.2s ease-out;
        }
        .hero-content h1 {
            font-family: 'Playfair Display', serif;
            font-size: 48px;
            color: #D4AF37;
            text-shadow: 0 4px 30px rgba(212, 175, 55, 0.3);
            margin-bottom: 15px;
            letter-spacing: 2px;
        }
        .hero-content h1 i {
            margin-right: 10px;
        }
        .hero-content p {
            font-size: 18px;
            color: #b0c4b1;
            font-weight: 300;
            line-height: 1.7;
            max-width: 600px;
            margin: 0 auto;
        }
        .hero-content .breadcrumb {
            margin-top: 15px;
            font-size: 14px;
            color: #888;
        }
        .hero-content .breadcrumb a {
            color: #D4AF37;
            text-decoration: none;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ============================================================
                   MAIN CONTAINER
                   ============================================================ */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px 60px;
        }

        /* ============================================================
                   SECTION TITLE
                   ============================================================ */
        .section-title {
            text-align: center;
            margin-bottom: 40px;
        }
        .section-title .subtitle {
            color: #D4AF37;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 8px;
        }
        .section-title h2 {
            font-family: 'Playfair Display', serif;
            font-size: 40px;
            color: #D4AF37;
            letter-spacing: 2px;
            position: relative;
            display: inline-block;
        }
        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: #D4AF37;
        }
        .section-title p {
            color: #888;
            font-size: 16px;
            margin-top: 20px;
        }

        /* ============================================================
                   SEARCH BAR
                   ============================================================ */
        .search-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            margin-bottom: 40px;
            background: #0a1914;
            padding: 18px 25px;
            border-radius: 16px;
            border: 1px solid #1e4538;
        }
        .search-bar input {
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            padding: 10px 16px;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: 0.3s;
            flex: 1;
            min-width: 200px;
        }
        .search-bar input:focus {
            border-color: #D4AF37;
            outline: none;
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.05);
        }
        .search-bar input::placeholder {
            color: #666;
        }
        .search-bar .search-label {
            color: #888;
            font-size: 13px;
            font-weight: 500;
        }
        .search-bar .search-label i {
            color: #D4AF37;
            margin-right: 4px;
        }
        .search-bar .result-count {
            color: #555;
            font-size: 13px;
            margin-left: auto;
        }
        .search-bar .result-count strong {
            color: #D4AF37;
        }

        /* ============================================================
                   TIERS GRID
                   ============================================================ */
        .tiers-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 10px;
        }

        /* ============================================================
                   TIER COLUMN
                   ============================================================ */
        .tier-column {
            background: #0a1914;
            padding: 30px 25px 35px;
            border-radius: 20px;
            border: 1px solid #1e4538;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transition: 0.4s ease;
            display: flex;
            flex-direction: column;
        }
        .tier-column:hover {
            border-color: #D4AF37;
            box-shadow: 0 15px 40px rgba(212, 175, 55, 0.08);
        }
        .tier-column .tier-header {
            text-align: center;
            padding-bottom: 20px;
            margin-bottom: 20px;
            border-bottom: 2px solid rgba(30, 69, 56, 0.4);
        }
        .tier-column .tier-header .tier-icon {
            font-size: 36px;
            margin-bottom: 8px;
        }
        .tier-column .tier-header h3 {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            color: #D4AF37;
            letter-spacing: 1px;
        }
        .tier-column .tier-header p {
            color: #888;
            font-size: 13px;
            font-style: italic;
            margin-top: 4px;
        }
        .tier-column .tier-header .count-badge {
            display: inline-block;
            background: rgba(212, 175, 55, 0.15);
            color: #D4AF37;
            padding: 2px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
        }

        /* Tier Specific Colors */
        .tier-standard .tier-header .tier-icon { color: #8c92ac; }
        .tier-standard .tier-header .count-badge { color: #8c92ac; background: rgba(140, 146, 172, 0.15); }

        .tier-premium .tier-header .tier-icon { color: #d35400; }
        .tier-premium .tier-header .count-badge { color: #d35400; background: rgba(211, 84, 0, 0.15); }
        .tier-premium .tier-column {
            border-color: rgba(211, 84, 0, 0.3);
        }

        .tier-luxury .tier-header .tier-icon { color: #D4AF37; }
        .tier-luxury .tier-column {
            border-color: rgba(212, 175, 55, 0.3);
            background: linear-gradient(180deg, #0f1f18, #0a1914);
        }

        /* ============================================================
                   OFFERS GRID INSIDE TIER
                   ============================================================ */
        .offers-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            flex: 1;
        }

        /* ============================================================
                   OFFER CARD
                   ============================================================ */
        .offer-card {
            background: #111;
            padding: 22px 20px;
            border-radius: 14px;
            border: 1px solid #1e4538;
            text-align: center;
            transition: 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        .offer-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, #D4AF37, transparent);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        .offer-card:hover::before {
            opacity: 1;
        }
        .offer-card:hover {
            transform: translateY(-5px);
            border-color: #D4AF37;
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.08);
        }
        .offer-card .offer-image {
            width: 100%;
            height: 140px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 12px;
            background: #1a1a1a;
        }
        .offer-card .offer-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: rgba(212, 175, 55, 0.15);
            color: #D4AF37;
            margin-bottom: 10px;
        }
        .offer-card h4 {
            font-size: 18px;
            color: #fff;
            margin-bottom: 6px;
            font-weight: 600;
        }
        .offer-card .description {
            color: #888;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        .offer-card .discount {
            font-size: 22px;
            font-weight: 700;
            color: #D4AF37;
            margin-bottom: 12px;
        }
        .offer-card .category-tag {
            font-size: 12px;
            color: #555;
            margin-bottom: 14px;
        }
        .offer-card .category-tag i {
            color: #D4AF37;
            margin-right: 4px;
        }

        .btn-claim {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 28px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .btn-claim i {
            font-size: 14px;
        }
        .btn-claim:hover {
            transform: translateY(-2px) scale(1.03);
        }

        /* Tier Specific Buttons */
        .tier-standard .btn-claim {
            background: #8c92ac;
            color: #0a1914;
        }
        .tier-standard .btn-claim:hover {
            background: #fff;
            box-shadow: 0 8px 25px rgba(140, 146, 172, 0.3);
        }

        .tier-premium .btn-claim {
            background: #d35400;
            color: #fff;
        }
        .tier-premium .btn-claim:hover {
            background: #e67e22;
            box-shadow: 0 8px 25px rgba(211, 84, 0, 0.3);
        }
        .tier-premium .offer-card .discount { color: #e67e22; }
        .tier-premium .offer-card .offer-badge { color: #e67e22; background: rgba(211, 84, 0, 0.15); }

        .tier-luxury .btn-claim {
            background: #D4AF37;
            color: #0a1914;
        }
        .tier-luxury .btn-claim:hover {
            background: #fff;
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.3);
        }

        /* ============================================================
                   EMPTY STATE
                   ============================================================ */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        .empty-state i {
            font-size: 48px;
            display: block;
            margin-bottom: 12px;
            color: #333;
        }
        .empty-state p {
            font-size: 15px;
        }

        /* ============================================================
                   NO OFFERS - GLOBAL
                   ============================================================ */
        .no-offers-global {
            text-align: center;
            padding: 60px 20px;
            background: #0a1914;
            border-radius: 20px;
            border: 1px solid #1e4538;
        }
        .no-offers-global i {
            font-size: 60px;
            color: #333;
            display: block;
            margin-bottom: 15px;
        }
        .no-offers-global h2 {
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            color: #D4AF37;
            margin-bottom: 10px;
        }
        .no-offers-global p {
            color: #888;
            font-size: 16px;
        }

        /* ============================================================
                   FOOTER
                   ============================================================ */
        footer {
            background: #0a1914;
            border-top: 2px solid #1e4538;
            padding: 30px 20px;
            text-align: center;
            margin-top: 40px;
        }
        footer .socials {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 15px;
        }
        footer .socials a {
            color: #b0c4b1;
            font-size: 22px;
            transition: 0.3s;
        }
        footer .socials a:hover {
            color: #D4AF37;
            transform: translateY(-3px);
        }
        footer p {
            color: #555;
            font-size: 14px;
        }
        footer p i {
            color: #D4AF37;
        }

        /* ============================================================
                   RESPONSIVE
                   ============================================================ */
        @media (max-width: 1024px) {
            .tiers-grid {
                grid-template-columns: 1fr 1fr;
            }
            .hero {
                height: 35vh;
                min-height: 240px;
            }
            .hero-content h1 {
                font-size: 38px;
            }
        }

        @media (max-width: 992px) {
            nav {
                padding: 15px 25px;
                flex-wrap: wrap;
            }
            nav ul {
                gap: 15px;
                justify-content: center;
            }
            nav ul li a {
                font-size: 13px;
            }
        }

        @media (max-width: 768px) {
            .tiers-grid {
                grid-template-columns: 1fr;
                gap: 25px;
            }
            .search-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .search-bar input {
                min-width: 100%;
            }
            .search-bar .result-count {
                margin-left: 0;
                text-align: center;
            }
            .hero-content h1 {
                font-size: 32px;
            }
            .hero-content p {
                font-size: 15px;
            }
            .section-title h2 {
                font-size: 32px;
            }
            .container {
                padding: 30px 15px;
            }
            .offer-card {
                padding: 18px 15px;
            }
            .tier-column {
                padding: 20px 18px 25px;
            }
        }

        @media (max-width: 600px) {
            .hero {
                height: 30vh;
                min-height: 200px;
            }
            .hero-content h1 {
                font-size: 26px;
            }
            .hero-content h1 i {
                display: none;
            }
            .section-title h2 {
                font-size: 26px;
            }
            nav {
                flex-direction: column;
                gap: 10px;
            }
            .btn-claim {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 400px) {
            .hero-content h1 {
                font-size: 22px;
            }
            .tier-column .tier-header h3 {
                font-size: 22px;
            }
            .offer-card .discount {
                font-size: 18px;
            }
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
            <li><a href="<?php echo BASE_URL; ?>public/offers.php" class="active">Offers</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/rate.php"><i class="fas fa-star" style="color:#D4AF37;"></i> Rate Us</a></li>            
            <li><a href="<?php echo BASE_URL; ?>public/notifications.php">Notifications</a></li>
            <?php if (isset($_SESSION['customer_id']) && isset($_SESSION['res_name'])): ?>
                <li><a href="#" class="user-greeting"><i class="fas fa-user-circle"></i> <?php echo safeHtml(explode(' ', $_SESSION['res_name'])[0]); ?></a></li>
                <li><a href="<?php echo BASE_URL; ?>public/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php else: ?>
                <li><a href="<?php echo BASE_URL; ?>public/login.php" class="login-link"><i class="fas fa-sign-in-alt"></i> Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- ============================================================
    HERO SECTION
    ============================================================ -->
    <section class="hero">
        <div class="hero-content">
            <h1><i class="fas fa-tags"></i> Special Offers</h1>
            <p>Exclusive deals and limited-time offers crafted just for you.</p>
            <div class="breadcrumb">
                <a href="<?php echo BASE_URL; ?>public/index.php">Home</a> <span style="color:#555;">/</span> Offers
            </div>
        </div>
    </section>

    <!-- ============================================================
    MAIN CONTENT
    ============================================================ -->
    <div class="container">

        <!-- Section Title -->
        <div class="section-title">
            <div class="subtitle">Exclusive Deals</div>
            <h2>Choose Your Offer</h2>
            <p>Select from our curated selection of special offers and enhance your dining experience.</p>
        </div>

        <?php if ($has_offers): ?>

            <!-- Search Bar -->
            <div class="search-bar">
                <span class="search-label"><i class="fas fa-search"></i> Search</span>
                <input type="text" id="searchInput" placeholder="Search offers by title..." onkeyup="filterOffers()">
                <span class="result-count" id="resultCount">
                    Showing <strong><?php echo count($all_offers); ?></strong> offers
                </span>
            </div>

            <!-- Tiers Grid -->
            <div class="tiers-grid" id="offersGrid">

                <!-- ===== STANDARD TIER ===== -->
                <div class="tier-column tier-standard">
                    <div class="tier-header">
                        <div class="tier-icon"><i class="fas fa-star"></i></div>
                        <h3>Standard</h3>
                        <p>Pocket-friendly deals for everyone</p>
                        <span class="count-badge"><?php echo count($standard_offers); ?> offers</span>
                    </div>
                    <div class="offers-grid">
                        <?php if (count($standard_offers) > 0): ?>
                            <?php foreach ($standard_offers as $offer): ?>
                                <div class="offer-card" data-title="<?php echo strtolower($offer['title']); ?>">
                                    <?php if (!empty($offer['image_url'])): ?>
                                        <img src="<?php echo getImageUrl($offer['image_url']); ?>" 
                                             alt="<?php echo safeHtml($offer['title']); ?>"
                                             class="offer-image"
                                             onerror="this.style.display='none'">
                                    <?php endif; ?>
                                    <span class="offer-badge">Standard</span>
                                    <h4><?php echo safeHtml($offer['title']); ?></h4>
                                    <p class="description"><?php echo safeHtml($offer['description']); ?></p>
                                    <div class="discount">LKR <?php echo number_format($offer['discount_price'], 2); ?></div>
                                    <div class="category-tag">
                                        <i class="fas fa-tag"></i> <?php echo safeHtml($offer['category'] ?? 'Standard'); ?>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>public/apply_offer.php?offer_id=<?php echo $offer['id']; ?>" class="btn-claim">
                                        <i class="fas fa-gift"></i> Claim Offer
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-tag"></i>
                                <p>No standard offers available.<br>Check back soon!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ===== PREMIUM TIER ===== -->
                <div class="tier-column tier-premium">
                    <div class="tier-header">
                        <div class="tier-icon"><i class="fas fa-fire"></i></div>
                        <h3>Premium</h3>
                        <p>Mid-tier value bundles</p>
                        <span class="count-badge"><?php echo count($premium_offers); ?> offers</span>
                    </div>
                    <div class="offers-grid">
                        <?php if (count($premium_offers) > 0): ?>
                            <?php foreach ($premium_offers as $offer): ?>
                                <div class="offer-card" data-title="<?php echo strtolower($offer['title']); ?>">
                                    <?php if (!empty($offer['image_url'])): ?>
                                        <img src="<?php echo getImageUrl($offer['image_url']); ?>" 
                                             alt="<?php echo safeHtml($offer['title']); ?>"
                                             class="offer-image"
                                             onerror="this.style.display='none'">
                                    <?php endif; ?>
                                    <span class="offer-badge">Premium</span>
                                    <h4><?php echo safeHtml($offer['title']); ?></h4>
                                    <p class="description"><?php echo safeHtml($offer['description']); ?></p>
                                    <div class="discount">LKR <?php echo number_format($offer['discount_price'], 2); ?></div>
                                    <div class="category-tag">
                                        <i class="fas fa-tag"></i> <?php echo safeHtml($offer['category'] ?? 'Premium'); ?>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>public/apply_offer.php?offer_id=<?php echo $offer['id']; ?>" class="btn-claim">
                                        <i class="fas fa-gift"></i> Claim Offer
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-tag"></i>
                                <p>No premium offers available.<br>Check back soon!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ===== LUXURY TIER ===== -->
                <div class="tier-column tier-luxury">
                    <div class="tier-header">
                        <div class="tier-icon"><i class="fas fa-crown"></i></div>
                        <h3>Luxury</h3>
                        <p>Elite fine-dining packages</p>
                        <span class="count-badge"><?php echo count($luxury_offers); ?> offers</span>
                    </div>
                    <div class="offers-grid">
                        <?php if (count($luxury_offers) > 0): ?>
                            <?php foreach ($luxury_offers as $offer): ?>
                                <div class="offer-card" data-title="<?php echo strtolower($offer['title']); ?>">
                                    <?php if (!empty($offer['image_url'])): ?>
                                        <img src="<?php echo getImageUrl($offer['image_url']); ?>" 
                                             alt="<?php echo safeHtml($offer['title']); ?>"
                                             class="offer-image"
                                             onerror="this.style.display='none'">
                                    <?php endif; ?>
                                    <span class="offer-badge">Luxury</span>
                                    <h4><?php echo safeHtml($offer['title']); ?></h4>
                                    <p class="description"><?php echo safeHtml($offer['description']); ?></p>
                                    <div class="discount">LKR <?php echo number_format($offer['discount_price'], 2); ?></div>
                                    <div class="category-tag">
                                        <i class="fas fa-tag"></i> <?php echo safeHtml($offer['category'] ?? 'Luxury'); ?>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>public/apply_offer.php?offer_id=<?php echo $offer['id']; ?>" class="btn-claim">
                                        <i class="fas fa-gift"></i> Claim Offer
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-tag"></i>
                                <p>No luxury offers available.<br>Check back soon!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        <?php else: ?>

            <!-- No Offers Available -->
            <div class="no-offers-global">
                <i class="fas fa-tags"></i>
                <h2>No Offers Available</h2>
                <p>We don't have any active offers at the moment.<br>Please check back later for exciting deals!</p>
            </div>

        <?php endif; ?>

    </div>

    <!-- ============================================================
    FOOTER
    ============================================================ -->
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

    <!-- ============================================================
    JAVASCRIPT
    ============================================================ -->
    <script>
        // ============================================================
        // 1. SEARCH OFFERS
        // ============================================================
        function filterOffers() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.offer-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const title = card.getAttribute('data-title') || '';
                if (title.includes(input)) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            document.getElementById('resultCount').innerHTML =
                'Showing <strong>' + visibleCount + '</strong> offers';
        }

        // ============================================================
        // 2. PREVENT MULTIPLE CLAIM CLICKS
        // ============================================================
        document.querySelectorAll('.btn-claim').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (this.classList.contains('clicked')) {
                    e.preventDefault();
                    return;
                }
                this.classList.add('clicked');
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
                setTimeout(() => {
                    this.classList.remove('clicked');
                    this.innerHTML = '<i class="fas fa-gift"></i> Claim Offer';
                }, 3000);
            });
        });

        // ============================================================
        // 3. CONSOLE LOG (Debug)
        // ============================================================
        console.log('BASE_URL: <?php echo BASE_URL; ?>');
        console.log('Total Active Offers: <?php echo count($all_offers); ?>');
    </script>

</body>
</html>