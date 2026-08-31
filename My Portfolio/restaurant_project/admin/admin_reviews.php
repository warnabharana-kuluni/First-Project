<?php
// ============================================================
// ADMIN REVIEWS - Global + Individual Toggle
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
require_once __DIR__ . '/../includes/db.php';
if (!isset($conn) || !$conn) die("Database connection failed.");

// ============================================================
// 4. ENSURE is_visible COLUMN EXISTS
// ============================================================
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM reviews LIKE 'is_visible'");
if (mysqli_num_rows($col_check) == 0) {
    mysqli_query($conn, "ALTER TABLE reviews ADD COLUMN is_visible TINYINT(1) DEFAULT 1 AFTER rating");
}

// ============================================================
// 5. ENSURE settings TABLE EXISTS
// ============================================================
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'settings'");
if (mysqli_num_rows($table_check) == 0) {
    mysqli_query($conn, "CREATE TABLE settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('show_reviews_on_home', '1')");
}

// ============================================================
// 6. HANDLE GLOBAL TOGGLE
// ============================================================
$global_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_global'])) {
    $new_value = isset($_POST['global_show']) ? '1' : '0';
    $update = "UPDATE settings SET setting_value = '$new_value' WHERE setting_key = 'show_reviews_on_home'";
    if (mysqli_query($conn, $update)) {
        $global_msg = '<div class="msg-success"><i class="fas fa-check-circle"></i> Global setting updated!</div>';
    } else {
        $global_msg = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Update failed.</div>';
    }
}

// ============================================================
// 7. HANDLE INDIVIDUAL TOGGLE
// ============================================================
$individual_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_review'])) {
    $review_id = (int)$_POST['review_id'];
    $new_status = isset($_POST['is_visible']) ? 1 : 0;
    $update = "UPDATE reviews SET is_visible = $new_status WHERE id = $review_id";
    if (mysqli_query($conn, $update)) {
        $individual_msg = '<div class="msg-success"><i class="fas fa-check-circle"></i> Review status updated!</div>';
    } else {
        $individual_msg = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Update failed.</div>';
    }
}

// ============================================================
// 8. GET CURRENT SETTINGS
// ============================================================
$global_value = '1';
$setting_result = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = 'show_reviews_on_home'");
if ($setting_result && $row = mysqli_fetch_assoc($setting_result)) {
    $global_value = $row['setting_value'];
}
$global_checked = ($global_value == '1') ? 'checked' : '';

// ============================================================
// 9. FETCH ALL REVIEWS
// ============================================================
$sql = "SELECT 
            r.*, 
            c.name as customer_name, 
            c.email as customer_email,
            res.name as reservation_name,
            res.reservation_date
        FROM reviews r
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN reservations res ON r.reservation_id = res.id
        ORDER BY r.created_at DESC";
$result = mysqli_query($conn, $sql);
$reviews = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $reviews[] = $row;
    }
}
$total_reviews = count($reviews);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Ratings | Gourmet Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #050e0c; color: #fff; display: flex; min-height: 100vh; }

        .sidebar {
            width: 260px; min-height: 100vh; background: #0a1914; border-right: 2px solid #1e4538;
            padding: 40px 20px; position: fixed; top: 0; left: 0; height: 100%; overflow-y: auto; z-index: 1000; transition: transform 0.3s ease;
        }
        .sidebar h2 { font-family: 'Playfair Display', serif; font-size: 28px; color: #D4AF37; letter-spacing: 2px; margin-bottom: 40px; text-align: center; }
        .sidebar a { display: flex; align-items: center; gap: 12px; color: #b0c4b1; text-decoration: none; padding: 14px 20px; margin-bottom: 8px; border-radius: 12px; transition: all 0.3s ease; font-weight: 400; }
        .sidebar a i { width: 22px; font-size: 18px; text-align: center; }
        .sidebar a:hover, .sidebar a.active { background: #1e4538; color: #D4AF37; }
        .sidebar a.active { font-weight: 600; }
        .sidebar a.logout { color: #e74c3c; margin-top: 30px; border-top: 1px solid #1e4538; padding-top: 20px; }
        .sidebar a.logout:hover { background: #e74c3c; color: #fff; }

        .main-content { margin-left: 300px; padding: 40px 50px 60px; width: calc(100% - 300px); min-height: 100vh; transition: margin-left 0.3s ease; }

        .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 25px; }
        .page-header h1 { font-family: 'Playfair Display', serif; font-size: 34px; color: #fff; }
        .page-header h1 span { color: #D4AF37; }
        .page-header h1 i { margin-right: 10px; color: #D4AF37; }
        .page-header .badge-count { background: #0a1914; padding: 8px 18px; border-radius: 20px; border: 1px solid #1e4538; font-size: 14px; color: #D4AF37; }

        .msg-success, .msg-error { padding: 12px 16px; border-radius: 10px; margin-bottom: 18px; font-weight: 500; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .msg-success { background: rgba(46,204,113,0.12); border: 1px solid rgba(46,204,113,0.25); color: #2ecc71; }
        .msg-error { background: rgba(231,76,60,0.12); border: 1px solid rgba(231,76,60,0.25); color: #e74c3c; }

        /* Global Toggle Box */
        .toggle-box {
            background: #0a1914; padding: 15px 20px; border-radius: 14px; border: 1px solid #1e4538;
            margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
        }
        .toggle-box .info { display: flex; flex-direction: column; gap: 4px; }
        .toggle-box .info span { color: #D4AF37; font-weight: 600; font-size: 16px; }
        .toggle-box .info small { color: #888; font-size: 13px; }
        .toggle-box form { display: flex; align-items: center; gap: 12px; }
        .toggle-box form .switch { position: relative; display: inline-block; width: 50px; height: 28px; cursor: pointer; }
        .toggle-box form .switch input { opacity:0; width:0; height:0; }
        .toggle-box form .switch .slider { position: absolute; top:0; left:0; right:0; bottom:0; background: #2a2a2a; border-radius: 28px; transition: 0.3s; }
        .toggle-box form .switch .slider::before { content:''; position: absolute; top:3px; left:3px; width:22px; height:22px; background: #fff; border-radius: 50%; transition: 0.3s; }
        .toggle-box form .switch input:checked + .slider { background: #D4AF37; }
        .toggle-box form .switch input:checked + .slider::before { transform: translateX(22px); }
        .toggle-box form .save-btn { background: transparent; border: none; color: #D4AF37; cursor: pointer; font-size: 14px; font-weight: 500; transition: 0.3s; }
        .toggle-box form .save-btn:hover { color: #fff; }

        /* Table */
        .table-card { background: #0a1914; padding: 25px 20px 20px; border-radius: 20px; border: 1px solid #1e4538; overflow-x: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
        table { width: 100%; border-collapse: collapse; min-width: 850px; }
        thead th { color: #D4AF37; padding: 14px 16px; text-align: left; border-bottom: 2px solid #1e4538; font-size: 13px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; }
        thead th i { margin-right: 6px; font-size: 14px; }
        tbody td { padding: 14px 16px; border-bottom: 1px solid #1e4538; font-size: 14px; vertical-align: middle; }
        tbody tr:hover td { background: rgba(30,69,56,0.20); }

        .stars { display: flex; gap: 3px; color: #D4AF37; font-size: 16px; }
        .stars .empty { color: #444; }

        .comment-box { background: #111; padding: 8px 12px; border-radius: 8px; color: #b0c4b1; font-size: 13px; max-width: 200px; word-wrap: break-word; border-left: 2px solid #D4AF37; }

        .toggle-switch { position: relative; display: inline-block; width: 40px; height: 22px; cursor: pointer; }
        .toggle-switch input { opacity:0; width:0; height:0; }
        .toggle-switch .slider { position: absolute; top:0; left:0; right:0; bottom:0; background: #2a2a2a; border-radius: 22px; transition: 0.3s; }
        .toggle-switch .slider::before { content:''; position: absolute; top:2px; left:2px; width:18px; height:18px; background: #fff; border-radius: 50%; transition: 0.3s; }
        .toggle-switch input:checked + .slider { background: #D4AF37; }
        .toggle-switch input:checked + .slider::before { transform: translateX(18px); }

        .no-data { text-align: center; padding: 50px 0; color: #666; }
        .no-data i { font-size: 48px; display: block; margin-bottom: 12px; color: #333; }
        .no-data p { font-size: 16px; }

        .table-footer { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-top: 20px; padding-top: 15px; border-top: 1px solid #1e4538; }
        .total-items { color: #888; font-size: 14px; }
        .total-items strong { color: #D4AF37; font-size: 18px; }
        .total-items i { margin-right: 6px; color: #D4AF37; }

        .hamburger { display: none; background: transparent; border: none; color: #D4AF37; font-size: 28px; cursor: pointer; padding: 5px 10px; border-radius: 8px; transition: 0.3s; position: fixed; top: 15px; left: 15px; z-index: 1001; }
        .hamburger:hover { background: rgba(212,175,55,0.1); }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); width: 260px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; padding: 30px 25px 50px; }
            .hamburger { display: flex !important; }
        }
        @media (max-width: 600px) {
            table { min-width: 700px; }
            .comment-box { max-width: 120px; }
            .toggle-box { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>

    <button class="hamburger" id="hamburgerBtn" aria-label="Toggle Sidebar">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar" id="sidebar">
        <h2>✨ GOURMET</h2>
        <a href="admin.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="admin_reservations.php"><i class="fas fa-calendar-check"></i> Reservations</a>
        <a href="admin_menu.php"><i class="fas fa-utensils"></i> Menu</a>
        <a href="admin_reviews.php" class="active"><i class="fas fa-star"></i> Ratings</a>
        <a href="admin_messages.php"><i class="fas fa-envelope"></i> Messages</a>
        <a href="admin_offers.php"><i class="fas fa-tags"></i> Offers</a>
        <a href="admin_logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">

        <div class="page-header">
            <h1><i class="fas fa-star"></i> <span>Customer Ratings</span></h1>
            <span class="badge-count"><i class="fas fa-list"></i> Total: <?php echo $total_reviews; ?></span>
        </div>

        <!-- Global Toggle -->
        <div class="toggle-box">
            <div class="info">
                <span><i class="fas fa-home"></i> Show Reviews on Home Page</span>
                <small>Enable or disable displaying customer reviews on the home page.</small>
            </div>
            <form method="POST">
                <label class="switch">
                    <input type="checkbox" name="global_show" value="1" <?php echo $global_checked; ?> onchange="this.form.submit()">
                    <span class="slider"></span>
                </label>
                <input type="hidden" name="toggle_global" value="1">
                <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
            </form>
        </div>
        <?php if ($global_msg) echo $global_msg; ?>
        <?php if ($individual_msg) echo $individual_msg; ?>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> ID</th>
                        <th><i class="fas fa-user"></i> Customer</th>
                        <th><i class="fas fa-utensils"></i> Dish</th>
                        <th><i class="fas fa-star"></i> Rating</th>
                        <th><i class="fas fa-comment"></i> Comment</th>
                        <th><i class="fas fa-calendar-alt"></i> Date</th>
                        <th><i class="fas fa-hashtag"></i> Reservation</th>
                        <th><i class="fas fa-eye"></i> Show</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $row): 
                            $rating = (int)$row['rating'];
                            $customer_name = !empty($row['customer_name']) ? htmlspecialchars($row['customer_name']) : 'Guest';
                            $customer_email = !empty($row['customer_email']) ? htmlspecialchars($row['customer_email']) : '—';
                            $dish = htmlspecialchars($row['dish_name']);
                            $comment = htmlspecialchars($row['comment'] ?? '');
                            $date = date('d M Y, h:i A', strtotime($row['created_at']));
                            $reservation_id = $row['reservation_id'] ?? '—';
                            $is_visible = isset($row['is_visible']) ? (int)$row['is_visible'] : 1;
                            $checked = ($is_visible == 1) ? 'checked' : '';
                        ?>
                        <tr>
                            <td><strong>#<?php echo $row['id']; ?></strong></td>
                            <td>
                                <strong style="color:#fff;"><?php echo $customer_name; ?></strong><br>
                                <span style="color:#666; font-size:12px;"><?php echo $customer_email; ?></span>
                            </td>
                            <td><span style="color:#D4AF37;"><?php echo $dish; ?></span></td>
                            <td>
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $rating): ?>
                                            <i class="fas fa-star"></i>
                                        <?php else: ?>
                                            <i class="fas fa-star empty"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <span style="color:#888; font-size:12px; margin-left:6px;">(<?php echo $rating; ?>)</span>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($comment)): ?>
                                    <div class="comment-box"><?php echo $comment; ?></div>
                                <?php else: ?>
                                    <span style="color:#444;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#888; font-size:13px;"><?php echo $date; ?></td>
                            <td>
                                <?php if ($reservation_id !== '—'): ?>
                                    <a href="admin_reservations.php" style="color:#D4AF37; text-decoration:none;">#<?php echo str_pad($reservation_id, 6, '0', STR_PAD_LEFT); ?></a>
                                <?php else: ?>
                                    <span style="color:#444;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="review_id" value="<?php echo $row['id']; ?>">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="is_visible" value="1" <?php echo $checked; ?> onchange="this.form.submit()">
                                        <span class="slider"></span>
                                    </label>
                                    <input type="hidden" name="toggle_review" value="1">
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="no-data">
                                    <i class="fas fa-star" style="color:#333;"></i>
                                    <p>No ratings submitted yet. <br>Customers will rate their dishes here.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="table-footer">
                <div class="total-items"><i class="fas fa-list-ul"></i> Total Ratings: <strong id="totalCount"><?php echo $total_reviews; ?></strong></div>
                <div style="font-size:13px; color:#555;"><i class="fas fa-arrow-up"></i> Latest ratings shown first</div>
            </div>
        </div>

    </div>

    <script>
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