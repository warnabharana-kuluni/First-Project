<?php
// ============================================================
// MENU PAGE - Professional Version with Dish Ratings & Availability
// + Custom Dish Persistence (saves to reservation.custom_dish)
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
// 4. AJAX ENDPOINT - Check for updates (polling)
// ============================================================
if (isset($_GET['check_updates']) && $_GET['check_updates'] == 1) {
    header('Content-Type: application/json');
    $items_data = [];
    $result = mysqli_query($conn, "SELECT id, item_name, is_available FROM menu");
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $items_data[] = [
                'id' => (int)$row['id'],
                'item_name' => $row['item_name'],
                'is_available' => (int)$row['is_available']
            ];
        }
    }
    echo json_encode(['success' => true, 'data' => $items_data]);
    exit();
}

// ============================================================
// 5. HANDLE CUSTOM DISH UPDATE (AJAX from frontend)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_custom_dish') {
    header('Content-Type: application/json');
    
    // Validate session has reservation data
    if (!isset($_SESSION['res_name']) || !isset($_SESSION['res_email']) || 
        !isset($_SESSION['res_date']) || !isset($_SESSION['res_guests'])) {
        echo json_encode(['success' => false, 'message' => 'No reservation found in session.']);
        exit();
    }
    
    $name = mysqli_real_escape_string($conn, $_SESSION['res_name']);
    $email = mysqli_real_escape_string($conn, $_SESSION['res_email']);
    $date = date('Y-m-d', strtotime($_SESSION['res_date']));
    $time = date('H:i:s', strtotime($_SESSION['res_date']));
    $guests = (int)$_SESSION['res_guests'];
    
    // Get custom dish fields from POST
    $custom_name = isset($_POST['custom_dish_name']) ? trim($_POST['custom_dish_name']) : '';
    $custom_type = isset($_POST['custom_type']) ? trim($_POST['custom_type']) : 'Food';
    $custom_qty = isset($_POST['custom_qty']) ? (int)$_POST['custom_qty'] : 1;
    $addons = isset($_POST['custom_addons']) ? (array)$_POST['custom_addons'] : [];
    $addons_str = !empty($addons) ? ' (' . implode(', ', $addons) . ')' : '';
    
    if (empty($custom_name)) {
        echo json_encode(['success' => false, 'message' => 'Custom dish name is required.']);
        exit();
    }
    
    // Build the full custom dish description
    $custom_dish_description = $custom_name . ' [' . $custom_type . ']' . $addons_str . ' x' . $custom_qty;
    
    // Find the reservation that matches the session data (pending, same details)
    $query = "SELECT id FROM reservations 
              WHERE name = '$name' 
                AND email = '$email' 
                AND reservation_date = '$date' 
                AND reservation_time = '$time' 
                AND guests = $guests 
                AND status = 'pending'
              ORDER BY id DESC LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found.']);
        exit();
    }
    
    $row = mysqli_fetch_assoc($result);
    $res_id = $row['id'];
    
    // Update the custom_dish column
    $update = "UPDATE reservations SET custom_dish = '" . mysqli_real_escape_string($conn, $custom_dish_description) . "' WHERE id = $res_id";
    if (mysqli_query($conn, $update)) {
        echo json_encode(['success' => true, 'message' => 'Custom dish saved.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
    exit();
}

// ============================================================
// 6. HELPER FUNCTIONS
// ============================================================
function getImageUrl($path) {
    $base_url = BASE_URL;
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
// 7. FUNCTION TO GET RATING DATA FOR A DISH
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

// ============================================================
// 8. FETCH MENU ITEMS
// ============================================================
$items = [];
$result = mysqli_query($conn, "SELECT * FROM menu ORDER BY item_name");
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
}

// Get category counts for filter
$category_counts = [];
$cat_result = mysqli_query($conn, "SELECT category, COUNT(*) as count FROM menu GROUP BY category");
if ($cat_result && mysqli_num_rows($cat_result) > 0) {
    while ($row = mysqli_fetch_assoc($cat_result)) {
        $category_counts[$row['category']] = $row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Menu | Gourmet Fine Dining</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Poppins',sans-serif; background:#050e0c; color:#fff; overflow-x:hidden; }
        nav { display:flex; justify-content:space-between; align-items:center; padding:18px 50px; background:#0a1914; border-bottom:2px solid #1e4538; position:sticky; top:0; z-index:1000; }
        .logo { font-size:26px; font-weight:700; color:#D4AF37; letter-spacing:2px; }
        nav ul { display:flex; list-style:none; gap:25px; flex-wrap:wrap; align-items:center; }
        nav ul li a { text-decoration:none; color:#b0c4b1; font-weight:500; transition:0.3s; font-size:14px; position:relative; }
        nav ul li a::after { content:''; position:absolute; bottom:-4px; left:0; width:0; height:2px; background:#D4AF37; transition:width 0.3s ease; }
        nav ul li a:hover::after, nav ul li a.active::after { width:100%; }
        nav ul li a:hover, nav ul li a.active { color:#D4AF37; }
        nav ul li a.user-greeting { color:#D4AF37; }
        nav ul li a.logout-link { color:#e74c3c; }
        nav ul li a.logout-link:hover { color:#ff6b6b; }
        nav ul li a.login-link { color:#D4AF37; font-weight:600; }
        .hero { position:relative; height:40vh; min-height:280px; display:flex; align-items:center; justify-content:center; text-align:center; background:url('https://images.unsplash.com/photo-1559339352-11d035aa65de?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') no-repeat center center/cover; background-attachment:fixed; overflow:hidden; }
        .hero::before { content:''; position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(5,14,12,0.7); backdrop-filter:blur(2px); }
        .hero-content { position:relative; z-index:1; max-width:800px; padding:20px; animation:fadeInUp 1.2s ease-out; }
        .hero-content h1 { font-family:'Playfair Display',serif; font-size:48px; color:#D4AF37; text-shadow:0 4px 30px rgba(212,175,55,0.3); margin-bottom:15px; letter-spacing:2px; }
        .hero-content p { font-size:18px; color:#b0c4b1; font-weight:300; line-height:1.7; max-width:600px; margin:0 auto; }
        .hero-content .breadcrumb { margin-top:15px; font-size:14px; color:#888; }
        .hero-content .breadcrumb a { color:#D4AF37; text-decoration:none; }
        @keyframes fadeInUp { from { opacity:0; transform:translateY(40px); } to { opacity:1; transform:translateY(0); } }
        .container { max-width:1200px; margin:0 auto; padding:40px 20px 60px; }
        .filter-bar { display:flex; flex-wrap:wrap; gap:15px; align-items:center; margin-bottom:40px; background:#0a1914; padding:18px 25px; border-radius:16px; border:1px solid #1e4538; }
        .filter-bar input, .filter-bar select { background:#111; border:1px solid #2a2a2a; border-radius:10px; padding:10px 16px; color:#fff; font-family:'Poppins',sans-serif; font-size:14px; transition:0.3s; flex:1; min-width:150px; }
        .filter-bar input:focus, .filter-bar select:focus { border-color:#D4AF37; outline:none; box-shadow:0 0 20px rgba(212,175,55,0.05); }
        .filter-bar input::placeholder { color:#666; }
        .filter-bar select option { background:#111; color:#fff; }
        .filter-bar .filter-label { color:#888; font-size:13px; font-weight:500; }
        .filter-bar .filter-label i { color:#D4AF37; margin-right:4px; }
        .menu-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:30px; }
        .menu-card { background:#0a1914; border-radius:20px; overflow:hidden; border:1px solid #1e4538; transition:0.4s ease; display:flex; flex-direction:column; }
        .menu-card:hover { transform:translateY(-10px); border-color:#D4AF37; box-shadow:0 15px 40px rgba(212,175,55,0.1); }
        .menu-card .image-wrapper { position:relative; overflow:hidden; height:220px; }
        .menu-card .image-wrapper img { width:100%; height:100%; object-fit:cover; transition:0.5s ease; background:#1a1a1a; }
        .menu-card:hover .image-wrapper img { transform:scale(1.05); filter:brightness(1.1); }
        .menu-card .image-wrapper .category-badge { position:absolute; top:15px; right:15px; background:rgba(0,0,0,0.75); backdrop-filter:blur(4px); padding:4px 14px; border-radius:20px; font-size:12px; font-weight:600; color:#D4AF37; border:1px solid rgba(212,175,55,0.3); }
        .menu-card .image-wrapper .sold-out-overlay { position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); display:flex; align-items:center; justify-content:center; font-size:24px; font-weight:700; color:#e74c3c; text-transform:uppercase; letter-spacing:2px; backdrop-filter:blur(2px); transition:opacity 0.3s ease; }
        .menu-card .image-wrapper .sold-out-overlay span { background:rgba(0,0,0,0.6); padding:10px 25px; border-radius:12px; border:2px solid #e74c3c; }
        .menu-card .image-wrapper .sold-out-overlay i { margin-right:8px; }
        .menu-card.sold-out .info .price { opacity:0.5; }
        .menu-card.sold-out .info .order-area label, .menu-card.sold-out .info .order-area input[type="number"] { display:none; }
        .menu-card.sold-out .info .order-area .sold-out-text { display:block !important; color:#e74c3c; font-weight:600; }
        .menu-card .info .order-area .sold-out-text { display:none; }
        .menu-card .info { padding:20px 22px 25px; flex:1; display:flex; flex-direction:column; justify-content:space-between; }
        .menu-card .info h3 { font-family:'Playfair Display',serif; font-size:22px; color:#fff; margin-bottom:2px; }
        .rating-stars { margin:6px 0 10px; display:flex; align-items:center; gap:2px; flex-wrap:wrap; }
        .rating-stars i { font-size:14px; }
        .rating-stars .star-filled { color:#D4AF37; }
        .rating-stars .star-empty { color:#555; }
        .rating-stars .rating-text { color:#888; font-size:12px; margin-left:6px; }
        .rating-stars .rating-count { color:#555; font-size:11px; margin-left:4px; }
        .no-reviews { color:#666; font-size:12px; display:flex; align-items:center; gap:4px; padding:2px 0; }
        .no-reviews .text { color:#888; font-style:italic; margin-left:4px; }
        .no-reviews .text i { color:#D4AF37; margin-right:4px; }
        .menu-card .info .description { color:#888; font-size:14px; line-height:1.6; margin:6px 0 12px; flex:1; }
        .menu-card .info .price { font-size:22px; font-weight:700; color:#D4AF37; margin-bottom:12px; }
        .menu-card .info .order-area { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; border-top:1px solid rgba(30,69,56,0.4); padding-top:14px; }
        .menu-card .info .order-area label { display:flex; align-items:center; gap:8px; color:#b0c4b1; font-size:14px; cursor:pointer; }
        .menu-card .info .order-area label input[type="checkbox"] { accent-color:#D4AF37; transform:scale(1.2); cursor:pointer; }
        .menu-card .info .order-area input[type="number"] { width:65px; padding:6px 8px; background:#111; border:1px solid #2a2a2a; border-radius:8px; color:#fff; text-align:center; font-size:14px; font-family:'Poppins',sans-serif; }
        .menu-card .info .order-area input[type="number"]:focus { border-color:#D4AF37; outline:none; }
        .empty-state { grid-column:1 / -1; text-align:center; padding:60px 20px; color:#666; }
        .empty-state i { font-size:60px; display:block; margin-bottom:15px; color:#333; }
        .empty-state p { font-size:18px; }
        .custom-section { margin-top:60px; background:linear-gradient(145deg,#0a1914,#0f1f18); border:2px dashed rgba(212,175,55,0.3); border-radius:20px; padding:40px 30px; text-align:center; }
        .custom-section h2 { font-family:'Playfair Display',serif; font-size:32px; color:#D4AF37; margin-bottom:8px; }
        .custom-section .subtitle { color:#888; font-size:15px; margin-bottom:25px; }
        .custom-section textarea { width:100%; max-width:700px; min-height:110px; background:#111; border:1px solid #2a2a2a; border-radius:12px; padding:15px; color:#fff; font-family:'Poppins',sans-serif; font-size:14px; resize:vertical; margin:0 auto; display:block; transition:0.3s; }
        .custom-section textarea:focus { border-color:#D4AF37; outline:none; box-shadow:0 0 20px rgba(212,175,55,0.05); }
        .custom-options { display:flex; flex-wrap:wrap; justify-content:center; align-items:center; gap:20px 30px; margin:25px 0 10px; background:#0a0a0a; padding:20px 30px; border-radius:14px; border:1px solid #1e4538; }
        .custom-options label { display:flex; align-items:center; gap:8px; color:#b0c4b1; font-size:14px; cursor:pointer; }
        .custom-options label input[type="checkbox"] { accent-color:#D4AF37; transform:scale(1.1); }
        .custom-options select, .custom-options input[type="number"] { background:#111; border:1px solid #2a2a2a; color:#fff; padding:6px 12px; border-radius:8px; font-size:14px; font-family:'Poppins',sans-serif; }
        .custom-options select:focus, .custom-options input[type="number"]:focus { border-color:#D4AF37; outline:none; }
        .custom-options .addon-group { display:flex; flex-wrap:wrap; gap:12px 20px; }
        .custom-options .addon-group span { display:none; }
        .custom-options .addon-group.active { display:flex; }
        .btn-proceed { background:linear-gradient(135deg,#D4AF37,#b8962e); color:#0a1914; padding:16px 50px; border:none; border-radius:50px; font-size:20px; font-weight:700; cursor:pointer; transition:0.3s; box-shadow:0 6px 25px rgba(212,175,55,0.3); margin-top:30px; display:inline-flex; align-items:center; gap:12px; }
        .btn-proceed:hover { background:#fff; color:#000; transform:translateY(-4px) scale(1.02); box-shadow:0 10px 40px rgba(212,175,55,0.4); }
        .btn-proceed i { font-size:22px; }
        footer { background:#0a1914; border-top:2px solid #1e4538; padding:30px 20px; text-align:center; margin-top:40px; }
        footer .socials { display:flex; justify-content:center; gap:20px; margin-bottom:15px; }
        footer .socials a { color:#b0c4b1; font-size:22px; transition:0.3s; }
        footer .socials a:hover { color:#D4AF37; transform:translateY(-3px); }
        footer p { color:#555; font-size:14px; }
        footer p i { color:#D4AF37; }
        .live-indicator { font-size:12px; color:#2ecc71; margin-left:10px; display:inline-flex; align-items:center; gap:5px; }
        .live-indicator .dot { width:8px; height:8px; background:#2ecc71; border-radius:50%; display:inline-block; animation:pulse-dot 1.5s ease-in-out infinite; }
        @keyframes pulse-dot { 0%,100% { opacity:1; transform:scale(1); } 50% { opacity:0.3; transform:scale(0.7); } }
        @media (max-width:992px) { .hero { height:35vh; min-height:240px; } .hero-content h1 { font-size:38px; } nav { padding:15px 25px; flex-wrap:wrap; } nav ul { gap:15px; justify-content:center; } nav ul li a { font-size:13px; } }
        @media (max-width:768px) { .menu-grid { grid-template-columns:1fr 1fr; } .filter-bar { flex-direction:column; align-items:stretch; } .filter-bar input, .filter-bar select { min-width:100%; } .custom-section { padding:30px 20px; } .custom-section h2 { font-size:26px; } .custom-options { flex-direction:column; align-items:stretch; } .custom-options .addon-group { justify-content:center; } .btn-proceed { padding:14px 40px; font-size:17px; width:100%; justify-content:center; } }
        @media (max-width:600px) { .hero { height:30vh; min-height:200px; } .hero-content h1 { font-size:30px; } .hero-content p { font-size:15px; } .menu-grid { grid-template-columns:1fr; } .menu-card .info .order-area { flex-direction:column; align-items:stretch; } .menu-card .info .order-area label { justify-content:center; } .menu-card .info .order-area input[type="number"] { width:100%; } .container { padding:20px 12px 40px; } nav { flex-direction:column; gap:10px; } }
        @media (max-width:400px) { .hero-content h1 { font-size:24px; } .custom-section h2 { font-size:22px; } }
        
        .refresh-btn {
            background: linear-gradient(135deg, #D4AF37, #b8962e);
            color: #0a1914;
            border: none;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: 10px;
        }
        .refresh-btn:hover { background: #fff; transform: scale(1.05); }
        .refresh-btn i { font-size: 12px; }
    </style>
</head>
<body>

    <!-- NAVIGATION -->
    <nav>
        <div class="logo">GOURMET</div>
        <ul>
            <li><a href="<?php echo BASE_URL; ?>public/index.php">Home</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/about.php">About</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/contact.php">Contact</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/reservation.php">Reservation</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/menu.php" class="active">Menu</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/offers.php">Offers</a></li>
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

    <!-- HERO -->
    <section class="hero">
        <div class="hero-content">
            <h1>Our Signature Dishes</h1>
            <p>Explore a world of exquisite flavors crafted with passion and precision.</p>
            <div class="breadcrumb">
                <a href="<?php echo BASE_URL; ?>public/index.php">Home</a> <span style="color:#555;">/</span> Menu
                <span class="live-indicator"><span class="dot"></span> Live</span>
                <button class="refresh-btn" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
    </section>

    <!-- RESERVATION SUMMARY -->
    <?php if (isset($_SESSION['reservation_complete']) && $_SESSION['reservation_complete'] === true): ?>
    <div style="max-width:1200px; margin:-30px auto 30px; padding:0 20px;">
        <div style="background:rgba(212,175,55,0.05); border:1px solid rgba(212,175,55,0.15); border-radius:12px; padding:15px 25px; display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:15px;">
            <div style="display:flex; flex-wrap:wrap; gap:20px; font-size:13px; color:#888;">
                <span><i class="fas fa-user" style="color:#D4AF37;"></i> <strong style="color:#fff;"><?php echo safeHtml($_SESSION['res_name'] ?? 'Guest'); ?></strong></span>
                <span><i class="fas fa-users" style="color:#D4AF37;"></i> <?php echo (int)($_SESSION['res_guests'] ?? 1); ?> guests</span>
                <span><i class="fas fa-calendar-alt" style="color:#D4AF37;"></i> <?php echo date('d M Y', strtotime($_SESSION['res_date'] ?? 'now')); ?></span>
                <span><i class="fas fa-clock" style="color:#D4AF37;"></i> <?php echo date('h:i A', strtotime($_SESSION['res_date'] ?? 'now')); ?></span>
                <span><i class="fas fa-chair" style="color:#D4AF37;"></i> Table: LKR <?php echo number_format($_SESSION['res_total'] ?? 0, 2); ?></span>
            </div>
            <a href="<?php echo BASE_URL; ?>public/reservation.php" style="color:#D4AF37; font-size:13px; text-decoration:none;">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- MAIN CONTENT -->
    <div class="container">

        <!-- FILTER & SEARCH -->
        <div class="filter-bar">
            <span class="filter-label"><i class="fas fa-search"></i> Search</span>
            <input type="text" id="searchInput" placeholder="Search by dish name..." onkeyup="filterMenu()">

            <span class="filter-label"><i class="fas fa-filter"></i> Category</span>
            <select id="categoryFilter" onchange="filterMenu()">
                <option value="all">All Categories</option>
                <?php foreach ($category_counts as $cat => $count): ?>
                    <option value="<?php echo strtolower($cat); ?>">
                        <?php echo htmlspecialchars($cat); ?> (<?php echo $count; ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <span style="color:#555; font-size:13px; margin-left:auto;" id="resultCount">
                Showing <strong style="color:#D4AF37;"><?php echo count($items); ?></strong> items
            </span>
        </div>

        <!-- MENU GRID -->
        <form action="payment.php" method="POST" id="menuForm">

            <!-- Hidden fields to pass reservation identification -->
            <input type="hidden" name="res_name" value="<?php echo safeHtml($_SESSION['res_name'] ?? ''); ?>">
            <input type="hidden" name="res_email" value="<?php echo safeHtml($_SESSION['res_email'] ?? ''); ?>">
            <input type="hidden" name="res_date" value="<?php echo safeHtml($_SESSION['res_date'] ?? ''); ?>">
            <input type="hidden" name="res_guests" value="<?php echo (int)($_SESSION['res_guests'] ?? 1); ?>">

            <div class="menu-grid" id="menuGrid">
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $item):
                        $img_src = getImageUrl($item['image_url']);
                        $category = safeHtml($item['category']);
                        $rating_data = getDishRating($conn, $item['item_name']);
                        $avg_rating = $rating_data['avg_rating'];
                        $review_count = $rating_data['review_count'];
                        $full_stars = floor($avg_rating);
                        $half_star = ($avg_rating - $full_stars) >= 0.5 ? 1 : 0;
                        $is_available = isset($item['is_available']) ? (int)$item['is_available'] : 1;
                        $sold_out_class = $is_available ? '' : 'sold-out';
                    ?>
                        <div class="menu-card <?php echo $sold_out_class; ?>" 
                             data-id="<?php echo $item['id']; ?>"
                             data-category="<?php echo strtolower($category); ?>" 
                             data-name="<?php echo strtolower($item['item_name']); ?>"
                             data-available="<?php echo $is_available; ?>">
                            <div class="image-wrapper">
                                <img src="<?php echo htmlspecialchars($img_src); ?>"
                                     alt="<?php echo safeHtml($item['item_name']); ?>"
                                     loading="lazy"
                                     onerror="this.src='<?php echo BASE_URL; ?>assets/default.jpg'">
                                <span class="category-badge"><?php echo $category; ?></span>
                                <div class="sold-out-overlay" style="<?php echo $is_available ? 'display:none;' : ''; ?>">
                                    <span><i class="fas fa-times-circle"></i> Sold Out</span>
                                </div>
                            </div>
                            <div class="info">
                                <div>
                                    <h3><?php echo safeHtml($item['item_name']); ?></h3>

                                    <!-- Rating Display -->
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

                                    <p class="description"><?php echo safeHtml($item['description']); ?></p>
                                    <div class="price">LKR <?php echo number_format($item['price'], 2); ?></div>
                                </div>
                                <div class="order-area">
                                    <?php if ($is_available): ?>
                                        <label>
                                            <input type="checkbox" name="selected_menu[]" value="<?php echo safeHtml($item['item_name']); ?>">
                                            Select
                                        </label>
                                        <input type="number" name="quantity[<?php echo safeHtml($item['item_name']); ?>]" value="1" min="1">
                                        <span class="sold-out-text"><i class="fas fa-times-circle"></i> Not available</span>
                                    <?php else: ?>
                                        <label style="opacity:0.5; pointer-events:none;">
                                            <input type="checkbox" disabled> Select
                                        </label>
                                        <input type="number" disabled style="opacity:0.5;">
                                        <span class="sold-out-text"><i class="fas fa-times-circle"></i> Not available</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-utensils"></i>
                        <p>No menu items available at the moment.<br>Please check back later.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- CUSTOM ORDER -->
            <div class="custom-section">
                <h2>✨ Create Your Custom Dish</h2>
                <p class="subtitle">Can't find what you're looking for? Tell us your special request.</p>

                <textarea name="custom_menu_requests" placeholder="E.g., Custom Seafood Pasta with less spice, or a special juice..."></textarea>

                <div class="custom-options">
                    <label>
                        <input type="checkbox" id="enableCustomDish" name="is_custom_order" value="1">
                        Enable Custom Order
                    </label>

                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="color:#aaa;">Type:</span>
                        <select id="customType" name="custom_type">
                            <option value="Food">Food</option>
                            <option value="Juice">Juice</option>
                        </select>
                    </div>

                    <div class="addon-group" id="foodAddons">
                        <label><input type="checkbox" name="custom_addons[]" value="Chicken"> Chicken (+800)</label>
                        <label><input type="checkbox" name="custom_addons[]" value="Cheese"> Cheese (+400)</label>
                        <label><input type="checkbox" name="custom_addons[]" value="Seafood"> Seafood (+1200)</label>
                    </div>

                    <div class="addon-group" id="juiceAddons" style="display:none;">
                        <label><input type="checkbox" name="custom_addons[]" value="Fresh Fruit"> Fresh Fruit (+300)</label>
                        <label><input type="checkbox" name="custom_addons[]" value="Honey"> Honey (+100)</label>
                        <label><input type="checkbox" name="custom_addons[]" value="Ice Cream"> Ice Cream (+250)</label>
                    </div>

                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="color:#aaa;">Qty:</span>
                        <input type="number" name="custom_qty" id="customQty" value="1" min="1">
                    </div>
                </div>

                <div style="text-align:center;">
                    <button type="submit" id="proceedBtn" class="btn-proceed">
                        <i class="fas fa-credit-card"></i> Proceed to Payment
                    </button>
                </div>
            </div>

        </form>

    </div>

    <!-- FOOTER -->
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

    <!-- JAVASCRIPT -->
    <script>
        // ============================================================
        // 1. MENU FILTER (Search + Category)
        // ============================================================
        function filterMenu() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const category = document.getElementById('categoryFilter').value.toLowerCase();
            const cards = document.querySelectorAll('.menu-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const name = card.getAttribute('data-name') || '';
                const cat = card.getAttribute('data-category') || '';
                const matchName = name.includes(input);
                const matchCategory = (category === 'all' || cat === category);

                if (matchName && matchCategory) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            document.getElementById('resultCount').innerHTML =
                'Showing <strong style="color:#D4AF37;">' + visibleCount + '</strong> items';
        }

        // ============================================================
        // 2. CUSTOM ORDER TYPE TOGGLE
        // ============================================================
        document.getElementById('customType').addEventListener('change', function() {
            const food = document.getElementById('foodAddons');
            const juice = document.getElementById('juiceAddons');
            if (this.value === 'Juice') {
                food.style.display = 'none';
                juice.style.display = 'inline-flex';
            } else {
                food.style.display = 'inline-flex';
                juice.style.display = 'none';
            }
            // Uncheck all addons when switching
            document.querySelectorAll('input[name="custom_addons[]"]').forEach(cb => cb.checked = false);
        });

        // ============================================================
        // 3. CUSTOM DISH TOGGLE VISIBILITY FOR TEXTAREA (optional)
        // ============================================================
        document.getElementById('enableCustomDish').addEventListener('change', function() {
            const textarea = document.querySelector('textarea[name="custom_menu_requests"]');
            // We'll use the textarea for additional notes; it's always visible.
            // No change needed.
        });

        // ============================================================
        // 4. FORM SUBMISSION - Save custom dish via AJAX first
        // ============================================================
        document.getElementById('menuForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const proceedBtn = document.getElementById('proceedBtn');
            proceedBtn.disabled = true;
            proceedBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving custom dish...';

            // Check if custom dish is enabled
            const isCustomEnabled = document.getElementById('enableCustomDish').checked;
            
            // Gather data for the AJAX call
            const formData = new FormData(this);
            const customName = document.querySelector('textarea[name="custom_menu_requests"]').value.trim();

            // If custom dish is enabled but name is empty, show error
            if (isCustomEnabled && !customName) {
                alert('Please enter your custom dish description.');
                proceedBtn.disabled = false;
                proceedBtn.innerHTML = '<i class="fas fa-credit-card"></i> Proceed to Payment';
                return;
            }

            // Build the custom dish data to send
            const customData = {
                action: 'update_custom_dish',
                custom_dish_name: customName,
                custom_type: document.getElementById('customType').value,
                custom_qty: document.getElementById('customQty').value,
                custom_addons: []
            };

            // Collect checked addons
            document.querySelectorAll('input[name="custom_addons[]"]:checked').forEach(cb => {
                customData.custom_addons.push(cb.value);
            });

            // If custom dish is not enabled, we can skip AJAX and submit directly
            if (!isCustomEnabled) {
                // Just submit the form to payment.php
                this.submit();
                return;
            }

            // Send AJAX to save custom dish
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(customData).toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Now submit the form to payment.php
                    document.getElementById('menuForm').submit();
                } else {
                    alert('Error saving custom dish: ' + data.message);
                    proceedBtn.disabled = false;
                    proceedBtn.innerHTML = '<i class="fas fa-credit-card"></i> Proceed to Payment';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Server error. Please try again.');
                proceedBtn.disabled = false;
                proceedBtn.innerHTML = '<i class="fas fa-credit-card"></i> Proceed to Payment';
            });
        });

        // ============================================================
        // 5. LIVE POLLING (unchanged)
        // ============================================================
        const currentUrl = window.location.href.split('?')[0];
        const pollingUrl = currentUrl + '?check_updates=1';
        
        console.log('🔄 Polling URL: ' + pollingUrl);
        console.log('📍 Current page: ' + currentUrl);

        let isUpdating = false;

        function checkUpdates() {
            if (isUpdating) return;
            isUpdating = true;

            fetch(pollingUrl, {
                cache: 'no-cache',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                isUpdating = false;
                if (data.success && data.data) {
                    let hasUpdates = false;
                    data.data.forEach(updatedItem => {
                        const card = document.querySelector('.menu-card[data-id="' + updatedItem.id + '"]');
                        if (card) {
                            const currentAvailable = card.getAttribute('data-available') === '1';
                            const newAvailable = updatedItem.is_available === 1;

                            if (currentAvailable !== newAvailable) {
                                hasUpdates = true;
                                
                                card.setAttribute('data-available', newAvailable ? '1' : '0');

                                if (newAvailable) {
                                    card.classList.remove('sold-out');
                                } else {
                                    card.classList.add('sold-out');
                                }

                                const overlay = card.querySelector('.sold-out-overlay');
                                if (overlay) {
                                    overlay.style.display = newAvailable ? 'none' : '';
                                }

                                const orderArea = card.querySelector('.order-area');
                                if (orderArea) {
                                    const checkbox = orderArea.querySelector('input[type="checkbox"]');
                                    const qtyInput = orderArea.querySelector('input[type="number"]');
                                    const soldOutText = orderArea.querySelector('.sold-out-text');

                                    if (newAvailable) {
                                        if (checkbox) { 
                                            checkbox.disabled = false; 
                                            const label = checkbox.closest('label');
                                            if (label) { label.style.opacity = ''; label.style.pointerEvents = ''; }
                                        }
                                        if (qtyInput) { qtyInput.disabled = false; qtyInput.style.opacity = ''; }
                                        if (soldOutText) soldOutText.style.display = 'none';
                                    } else {
                                        if (checkbox) { 
                                            checkbox.disabled = true; 
                                            const label = checkbox.closest('label');
                                            if (label) { label.style.opacity = '0.5'; label.style.pointerEvents = 'none'; }
                                        }
                                        if (qtyInput) { qtyInput.disabled = true; qtyInput.style.opacity = '0.5'; }
                                        if (soldOutText) soldOutText.style.display = 'block';
                                    }
                                }

                                console.log('✅ Update: Item "' + updatedItem.item_name + '" is now ' + (newAvailable ? 'Available' : 'Sold Out'));
                            }
                        }
                    });
                    if (hasUpdates) {
                        console.log('🔄 Menu updated successfully!');
                    }
                }
            })
            .catch(error => {
                isUpdating = false;
                console.error('❌ Polling error:', error.message);
                console.log('💡 Make sure the file is accessible at: ' + pollingUrl);
            });
        }

        const intervalId = setInterval(checkUpdates, 2000);
        console.log('⏱️ Polling every 2 seconds');

        setTimeout(checkUpdates, 500);

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(intervalId);
                console.log('⏸️ Polling paused (page hidden)');
            } else {
                console.log('▶️ Polling resumed');
                checkUpdates();
            }
        });

        console.log('✨ Gourmet Menu Loaded - Live updates enabled!');
    </script>

</body>
</html>