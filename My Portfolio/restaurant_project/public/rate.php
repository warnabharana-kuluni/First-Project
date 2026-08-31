<?php
// ============================================================
// RATE YOUR DISHES - Standalone Page
// ============================================================

// ============================================================
// 1. SESSION START
// ============================================================
session_start();

// ============================================================
// 2. BASE URL
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
// 4. CHECK RESERVATION ID
// ============================================================
$reservation_id = isset($_GET['reservation_id']) ? (int)$_GET['reservation_id'] : 0;

// If no ID, show a form to enter it
if ($reservation_id <= 0) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Rate Your Dishes | Gourmet</title>
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
                justify-content: center;
                align-items: center;
                padding: 30px 20px;
                background-image: radial-gradient(circle at 20% 50%, rgba(212,175,55,0.05) 0%, transparent 60%);
            }
            .rate-container { width:100%; max-width:480px; animation:fadeInUp 0.6s ease forwards; }
            @keyframes fadeInUp { from{opacity:0;transform:translateY(30px);} to{opacity:1;transform:translateY(0);} }
            .rate-card {
                background:#0a1914; padding:40px 35px; border-radius:24px; border:1px solid #1e4538;
                box-shadow:0 20px 60px rgba(0,0,0,0.6); text-align:center; position:relative; overflow:hidden;
            }
            .rate-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,#D4AF37,transparent); }
            .rate-icon { font-size:56px; color:#D4AF37; margin-bottom:10px; display:block; }
            .rate-card h2 { font-family:'Playfair Display',serif; font-size:28px; color:#D4AF37; margin-bottom:6px; }
            .rate-card .sub { color:#888; font-size:14px; margin-bottom:25px; }
            .form-group { margin-bottom:18px; }
            .form-group label { display:block; color:#aaa; font-size:13px; font-weight:500; margin-bottom:5px; text-align:left; }
            .form-group input {
                width:100%; padding:13px 16px; background:#111; border:1px solid #2a2a2a; border-radius:12px;
                color:#fff; font-size:15px; font-family:'Poppins',sans-serif; transition:0.3s;
            }
            .form-group input:focus { border-color:#D4AF37; outline:none; box-shadow:0 0 20px rgba(212,175,55,0.08); }
            .btn-primary {
                width:100%; padding:14px; background:linear-gradient(135deg,#D4AF37,#b8962e); color:#000; border:none;
                border-radius:12px; font-weight:700; font-size:16px; cursor:pointer; transition:0.3s;
                display:flex; align-items:center; justify-content:center; gap:10px;
            }
            .btn-primary:hover { background:#fff; transform:translateY(-2px); box-shadow:0 8px 30px rgba(212,175,55,0.3); }
            .back-link { display:block; text-align:center; color:#888; font-size:14px; margin-top:20px; text-decoration:none; transition:0.3s; }
            .back-link:hover { color:#D4AF37; }
            .footer-text { text-align:center; color:#444; font-size:12px; margin-top:20px; }
            .footer-text i { color:#D4AF37; }
            @media(max-width:480px){ .rate-card{ padding:30px 20px; } }
        </style>
    </head>
    <body>
        <div class="rate-container">
            <div class="rate-card">
                <span class="rate-icon"><i class="fas fa-star"></i></span>
                <h2>Rate Your Dishes</h2>
                <p class="sub">Enter your reservation ID to get started.</p>
                <form method="GET" action="<?php echo BASE_URL; ?>public/rate.php">
                    <div class="form-group">
                        <label><i class="fas fa-hashtag" style="color:#D4AF37;"></i> Reservation ID</label>
                        <input type="number" name="reservation_id" placeholder="e.g. 123456" required min="1">
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-arrow-right"></i> Continue</button>
                </form>
                <a href="<?php echo BASE_URL; ?>public/index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
                <div class="footer-text"><i class="fas fa-crown"></i> Gourmet Restaurant</div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ============================================================
// 5. FETCH RESERVATION
// ============================================================
$stmt = mysqli_prepare($conn, "SELECT * FROM reservations WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $reservation_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$reservation = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$reservation) {
    die("Reservation not found.");
}

// ============================================================
// 6. CHECK IF ALREADY RATED
// ============================================================
$already_rated = false;
$check_stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM reviews WHERE reservation_id = ?");
mysqli_stmt_bind_param($check_stmt, "i", $reservation_id);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_bind_result($check_stmt, $count);
mysqli_stmt_fetch($check_stmt);
mysqli_stmt_close($check_stmt);
if ($count > 0) {
    $already_rated = true;
}

// ============================================================
// 7. HANDLE RATING SUBMISSION
// ============================================================
$rating_success = '';
$rating_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    if ($already_rated) {
        $rating_error = 'You have already rated this reservation.';
    } else {
        $ratings = $_POST['rating'] ?? [];
        $comment = mysqli_real_escape_string($conn, $_POST['comment'] ?? '');
        $customer_id = $_SESSION['customer_id'] ?? null;

        if (empty($ratings)) {
            $rating_error = 'Please rate at least one dish.';
        } else {
            $inserted = 0;
            foreach ($ratings as $dish_name => $rating_val) {
                $dish = mysqli_real_escape_string($conn, $dish_name);
                $rating = (int)$rating_val;
                if ($rating < 1 || $rating > 5) continue;

                $stmt = mysqli_prepare($conn,
                    "INSERT INTO reviews (customer_id, reservation_id, dish_name, rating, comment, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW())"
                );
                $cust_id = $customer_id ?: 0;
                mysqli_stmt_bind_param($stmt, "iisis", $cust_id, $reservation_id, $dish, $rating, $comment);
                if (mysqli_stmt_execute($stmt)) {
                    $inserted++;
                }
                mysqli_stmt_close($stmt);
            }
            if ($inserted > 0) {
                $rating_success = "Thank you! You rated $inserted dish(es). Your feedback helps us improve.";
                $already_rated = true;
            } else {
                $rating_error = 'Could not save your ratings. Please try again.';
            }
        }
    }
}

// ============================================================
// 8. GET DISH LIST FROM RESERVATION
// ============================================================
$dish_list = [];
$dishes = $reservation['selected_dishes'] ?? 'None';
if ($dishes && $dishes !== 'None') {
    $dish_list = array_map('trim', explode(',', $dishes));
}
$custom = $reservation['custom_dish'] ?? '';
if (!empty($custom)) {
    $dish_list[] = $custom . ' (Custom)';
}
$dish_list = array_filter($dish_list);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Your Dishes | Gourmet</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #050e0c;
            color: #fff;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 20px;
            background-image: radial-gradient(circle at 20% 50%, rgba(212,175,55,0.05) 0%, transparent 60%);
        }
        .rate-container {
            width: 100%;
            max-width: 580px;
            animation: fadeInUp 0.6s ease forwards;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .rate-card {
            background: #0a1914;
            padding: 40px 35px 35px;
            border-radius: 24px;
            border: 1px solid #1e4538;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
            position: relative;
            overflow: hidden;
            text-align: center;
        }
        .rate-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #D4AF37, transparent);
        }
        .rate-icon {
            font-size: 56px;
            color: #D4AF37;
            margin-bottom: 10px;
            display: block;
        }
        .rate-card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            color: #D4AF37;
            margin-bottom: 4px;
        }
        .rate-card .sub {
            color: #888;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .reservation-badge {
            display: inline-block;
            background: rgba(212,175,55,0.1);
            border: 1px solid rgba(212,175,55,0.2);
            padding: 4px 18px;
            border-radius: 20px;
            font-size: 13px;
            color: #D4AF37;
            margin-bottom: 20px;
        }
        .reservation-badge i { margin-right: 6px; }

        .dish-rating {
            background: #0d1f18;
            padding: 15px 18px;
            border-radius: 12px;
            margin-bottom: 15px;
            border: 1px solid #1e4538;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 15px;
        }
        .dish-rating .dish-name {
            font-weight: 600;
            font-size: 16px;
            min-width: 120px;
            color: #fff;
        }
        .dish-rating .star-group {
            display: flex;
            gap: 4px;
            direction: rtl; /* for hover effect */
        }
        .dish-rating .star-group input[type="radio"] {
            display: none;
        }
        .dish-rating .star-group label {
            font-size: 28px;
            color: #555;
            cursor: pointer;
            transition: 0.2s;
            padding: 0 2px;
        }
        .dish-rating .star-group label:hover,
        .dish-rating .star-group label:hover ~ label,
        .dish-rating .star-group input[type="radio"]:checked ~ label {
            color: #D4AF37;
        }
        .dish-rating .star-group input[type="radio"]:checked ~ label {
            color: #D4AF37;
        }

        .comment-area {
            width: 100%;
            padding: 12px 16px;
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
            margin-top: 5px;
            transition: 0.3s;
        }
        .comment-area:focus {
            border-color: #D4AF37;
            outline: none;
        }

        .btn-rate {
            background: #D4AF37;
            color: #0a1914;
            border: none;
            padding: 14px 30px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
            width: 100%;
            margin-top: 10px;
        }
        .btn-rate:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212,175,55,0.3);
        }
        .btn-secondary {
            display: inline-block;
            padding: 12px 30px;
            background: transparent;
            color: #D4AF37;
            border: 2px solid #D4AF37;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            transition: 0.3s;
            margin-top: 15px;
        }
        .btn-secondary:hover {
            background: #D4AF37;
            color: #0a1914;
        }

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
        .msg-success {
            background: rgba(46,204,113,0.12);
            border: 1px solid rgba(46,204,113,0.25);
            color: #2ecc71;
        }
        .msg-error {
            background: rgba(231,76,60,0.12);
            border: 1px solid rgba(231,76,60,0.25);
            color: #e74c3c;
        }

        .already-rated {
            text-align: center;
            padding: 30px 20px;
            color: #2ecc71;
            font-size: 16px;
            background: rgba(46,204,113,0.05);
            border: 1px solid rgba(46,204,113,0.2);
            border-radius: 12px;
        }
        .already-rated i {
            font-size: 40px;
            display: block;
            margin-bottom: 10px;
        }

        .no-dishes {
            color: #666;
            text-align: center;
            padding: 20px;
        }

        .footer-text {
            text-align: center;
            color: #444;
            font-size: 12px;
            margin-top: 25px;
        }
        .footer-text i { color: #D4AF37; }

        @media (max-width: 600px) {
            .rate-card { padding: 25px 18px; }
            .dish-rating { flex-direction: column; align-items: stretch; }
            .dish-rating .star-group { justify-content: center; }
            .dish-rating .dish-name { text-align: center; min-width: auto; }
        }
    </style>
</head>
<body>

<div class="rate-container">
    <div class="rate-card">

        <span class="rate-icon"><i class="fas fa-star"></i></span>
        <h2>Rate Your Dishes</h2>
        <p class="sub">Your feedback helps us serve you better.</p>

        <div class="reservation-badge">
            <i class="fas fa-hashtag"></i> Reservation #<?php echo str_pad($reservation_id, 6, '0', STR_PAD_LEFT); ?>
        </div>

        <?php if ($rating_success): ?>
            <div class="msg-success"><i class="fas fa-check-circle"></i> <?php echo $rating_success; ?></div>
        <?php endif; ?>
        <?php if ($rating_error): ?>
            <div class="msg-error"><i class="fas fa-exclamation-circle"></i> <?php echo $rating_error; ?></div>
        <?php endif; ?>

        <?php if ($already_rated && !$rating_success): ?>
            <div class="already-rated">
                <i class="fas fa-check-circle" style="color:#2ecc71;"></i>
                You have already rated this reservation. Thank you for your feedback!
            </div>
        <?php elseif (empty($dish_list)): ?>
            <div class="no-dishes">No dishes to rate. Enjoy your meal!</div>
        <?php else: ?>
            <form method="POST" action="<?php echo BASE_URL; ?>public/rate.php?reservation_id=<?php echo $reservation_id; ?>">
                <?php foreach ($dish_list as $dish): ?>
                    <div class="dish-rating">
                        <span class="dish-name"><?php echo htmlspecialchars($dish); ?></span>
                        <div class="star-group">
                            <?php for ($star = 5; $star >= 1; $star--): ?>
                                <input type="radio" name="rating[<?php echo htmlspecialchars($dish); ?>]" value="<?php echo $star; ?>" id="star_<?php echo md5($dish) . $star; ?>" required>
                                <label for="star_<?php echo md5($dish) . $star; ?>" title="<?php echo $star; ?> stars"><i class="fas fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <textarea name="comment" class="comment-area" placeholder="Any additional feedback? (optional)"></textarea>
                <button type="submit" name="submit_rating" class="btn-rate">
                    <i class="fas fa-paper-plane"></i> Submit Ratings
                </button>
            </form>
        <?php endif; ?>

        <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap; margin-top:15px;">
            <a href="<?php echo BASE_URL; ?>public/index.php" class="btn-secondary" style="padding:10px 25px; font-size:14px;">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="<?php echo BASE_URL; ?>public/reservation.php" class="btn-secondary" style="padding:10px 25px; font-size:14px;">
                <i class="fas fa-calendar-check"></i> Reservations
            </a>
        </div>

        <div class="footer-text">
            <i class="fas fa-crown"></i> Gourmet Restaurant — Where every dish tells a story.
        </div>

    </div>
</div>

<script>
    document.querySelector('form')?.addEventListener('submit', function(e) {
        const radios = this.querySelectorAll('input[type="radio"]');
        let checked = false;
        radios.forEach(r => { if (r.checked) checked = true; });
        if (!checked) {
            e.preventDefault();
            alert('Please rate at least one dish.');
        }
    });
</script>

</body>
</html>