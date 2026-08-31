<?php
// ============================================================
// ALL NOTIFICATIONS PAGE - Auto Mark as Read
// ============================================================
session_start();
require_once __DIR__ . "/../includes/db.php";

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    $path = dirname(dirname($script_name));
    return $protocol . '://' . $host . $path . '/';
}
if (!defined('BASE_URL')) { define('BASE_URL', getBaseUrl()); }

if (!isset($_SESSION['customer_id'])) {
    header("Location: " . BASE_URL . "public/login.php");
    exit();
}

$cust_id = (int)$_SESSION['customer_id'];

// ============================================================
// 🔥 AUTO-MARK ALL NOTIFICATIONS AS READ (when viewing this page)
// ============================================================
mysqli_query($conn, "UPDATE messages SET status = 'read' WHERE customer_id = $cust_id AND status = 'unread'");

// ============================================================
// FETCH NOTIFICATIONS (all - read + unread)
// ============================================================
$notif_query = "SELECT * FROM messages WHERE customer_id = $cust_id ORDER BY created_at DESC";
$notif_result = mysqli_query($conn, $notif_query);
$total_notifs = mysqli_num_rows($notif_result);

// Get unread count for badge (should be 0 after auto-mark)
$unread_query = "SELECT COUNT(*) as total FROM messages WHERE customer_id = $cust_id AND status = 'unread'";
$unread_result = mysqli_query($conn, $unread_query);
$unread_count = mysqli_fetch_assoc($unread_result)['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Gourmet</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #050e0c; color: #fff; padding: 30px 20px; min-height: 100vh; }
        .container { max-width: 900px; margin: 0 auto; }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; padding-bottom: 15px; border-bottom: 1px solid #1e4538; }
        .header-left { display: flex; align-items: center; gap: 15px; }
        .header-left h1 { font-family: 'Playfair Display', serif; color: #D4AF37; font-size: 32px; display: flex; align-items: center; gap: 10px; }
        .header-left .bell-icon { font-size: 32px; color: #D4AF37; position: relative; }
        .header-left .bell-icon .badge { position: absolute; top: -8px; right: -10px; background: #e74c3c; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 50%; min-width: 20px; text-align: center; border: 2px solid #050e0c; }
        .header-left .bell-icon .badge.hidden { display: none; }

        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .header-actions .btn-mark-read { background: #D4AF37; color: #0a1914; padding: 8px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .header-actions .btn-mark-read:hover { background: #fff; transform: translateY(-2px); }
        .header-actions .btn-mark-read:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .back-link { color: #888; text-decoration: none; transition: 0.3s; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1px solid #2a2a2a; border-radius: 8px; }
        .back-link:hover { color: #D4AF37; border-color: #D4AF37; }

        .notification-item {
            background: #0a1914;
            padding: 16px 20px;
            border-radius: 12px;
            border-left: 4px solid #D4AF37;
            margin-bottom: 12px;
            transition: 0.3s ease;
        }
        .notification-item:hover { background: #111; transform: translateX(4px); }
        .notification-item.read { border-left-color: #1e4538; opacity: 0.7; }
        .notification-item.read .subject { color: #888; }
        .notification-item .subject { color: #fff; font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 10px; }
        .notification-item .subject .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .notification-item .subject .status-dot.unread { background: #D4AF37; }
        .notification-item .subject .status-dot.read { background: #1e4538; }
        .notification-item .message { color: #b0c4b1; font-size: 14px; margin: 6px 0 10px; line-height: 1.6; padding-left: 18px; }
        .notification-item .message .highlight { color: #D4AF37; font-weight: 500; }
        .notification-item .footer { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding-left: 18px; }
        .notification-item .footer .time { color: #555; font-size: 12px; }
        .notification-item .footer .time i { margin-right: 4px; }

        .empty-state { text-align: center; padding: 80px 20px; color: #666; }
        .empty-state i { font-size: 70px; display: block; margin-bottom: 20px; color: #333; }
        .empty-state h2 { color: #888; font-size: 24px; margin-bottom: 8px; }
        .empty-state p { font-size: 16px; }

        .toast { position: fixed; bottom: 30px; right: 30px; background: #0a1914; padding: 15px 25px; border-radius: 12px; border: 1px solid #D4AF37; box-shadow: 0 10px 30px rgba(0,0,0,0.5); transform: translateY(100px); opacity: 0; transition: all 0.4s ease; z-index: 999; display: flex; align-items: center; gap: 12px; max-width: 400px; }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.success { border-color: #2ecc71; }
        .toast.success i { color: #2ecc71; }
        .toast.error { border-color: #e74c3c; }
        .toast.error i { color: #e74c3c; }
        .toast i { font-size: 20px; color: #D4AF37; }
        .toast .msg { font-size: 14px; color: #fff; }

        @media (max-width: 768px) {
            body { padding: 20px 12px; }
            .header { flex-direction: column; align-items: stretch; }
            .header-left h1 { font-size: 24px; }
            .header-actions { flex-direction: column; align-items: stretch; }
            .header-actions .btn-mark-read, .header-actions .back-link { text-align: center; justify-content: center; }
            .notification-item { padding: 14px 14px; }
            .notification-item .footer { flex-direction: column; align-items: stretch; }
        }
        @media (max-width: 480px) {
            .header-left h1 { font-size: 20px; }
            .header-left .bell-icon { font-size: 26px; }
            .notification-item .subject { font-size: 13px; }
            .notification-item .message { font-size: 13px; }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="header">
        <div class="header-left">
            <div class="bell-icon">
                <i class="fas fa-bell"></i>
                <span class="badge <?php echo $unread_count > 0 ? '' : 'hidden'; ?>" id="badgeCount"><?php echo $unread_count; ?></span>
            </div>
            <h1>Notifications</h1>
        </div>
        <div class="header-actions">
            <?php if ($total_notifs > 0): ?>
                <button class="btn-mark-read" id="markAllReadBtn" <?php echo $unread_count > 0 ? '' : 'disabled'; ?>>
                    <i class="fas fa-check-double"></i> Mark all as read
                </button>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>public/index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>

    <div id="notificationsList">
        <?php if ($notif_result && mysqli_num_rows($notif_result) > 0): ?>
            <?php while ($notif = mysqli_fetch_assoc($notif_result)): 
                $is_unread = $notif['status'] == 'unread';
                $status_class = $is_unread ? 'unread' : 'read';
            ?>
            <div class="notification-item <?php echo $status_class; ?>" data-id="<?php echo $notif['id']; ?>">
                <div class="subject">
                    <span class="status-dot <?php echo $status_class; ?>"></span>
                    <?php echo htmlspecialchars($notif['subject']); ?>
                    <?php if ($is_unread): ?>
                        <span style="font-size:10px; color:#D4AF37; font-weight:400; background:rgba(212,175,55,0.1); padding:1px 10px; border-radius:20px;">New</span>
                    <?php endif; ?>
                </div>
                <div class="message"><?php echo htmlspecialchars(strip_tags($notif['message'])); ?></div>
                <div class="footer">
                    <span class="time"><i class="far fa-clock"></i> <?php echo date('d M Y, h:i A', strtotime($notif['created_at'])); ?></span>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h2>All Caught Up!</h2>
                <p>You don't have any notifications at the moment.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
    console.log('BASE_URL: <?php echo BASE_URL; ?>');
    console.log('Unread Count: <?php echo $unread_count; ?>');
</script>

</body>
</html>