<?php
// ============================================================
// ADMIN MESSAGES - Show ONLY Customer Messages
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/admin_auth.php';

// 1. CHECK COLUMNS
function columnExists($table, $column) {
    global $conn;
    if (!$conn) return false;
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

$has_status = columnExists('messages', 'status');
$has_notification_type = columnExists('messages', 'notification_type');

// 2. HANDLE DELETE
$action_msg = '';
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $delete_sql = "DELETE FROM messages WHERE id = $id";
    if ($has_notification_type) $delete_sql .= " AND notification_type = 'customer_message'";
    if (mysqli_query($conn, $delete_sql)) {
        $action_msg = '<div class="msg-success"><i class="fas fa-check-circle"></i> Deleted!</div>';
    } else {
        $action_msg = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Error deleting.</div>';
    }
}

// 3. HANDLE MARK READ/UNREAD
if (isset($_GET['mark']) && isset($_GET['status']) && is_numeric($_GET['mark'])) {
    $id = (int)$_GET['mark'];
    $status = $_GET['status'] == 'read' ? 'read' : 'unread';
    if ($has_status) {
        $update_sql = "UPDATE messages SET status = '$status' WHERE id = $id";
        if ($has_notification_type) $update_sql .= " AND notification_type = 'customer_message'";
        if (mysqli_query($conn, $update_sql)) {
            $action_msg = '<div class="msg-success"><i class="fas fa-check-circle"></i> Marked as ' . $status . '!</div>';
        }
    }
}

// 4. GET COUNTS
$where_clause = $has_notification_type ? "WHERE notification_type = 'customer_message'" : "";
$total_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM messages $where_clause"))['c'] ?? 0;
if ($has_status) {
    $unread_where = $has_notification_type ? "notification_type = 'customer_message' AND status='unread'" : "status='unread'";
    $unread_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM messages WHERE $unread_where"))['c'] ?? 0;
} else {
    $unread_count = $total_count;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Messages | Gourmet Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ===== ALL CSS – SAME AS BEFORE ===== */
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
        .page-header .header-stats { display: flex; gap: 20px; flex-wrap: wrap; }
        .page-header .header-stats .stat-badge { background: #0a1914; padding: 8px 18px; border-radius: 20px; border: 1px solid #1e4538; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }
        .page-header .header-stats .stat-badge i { color: #D4AF37; }
        .page-header .header-stats .stat-badge .num { color: #D4AF37; font-weight: 700; }
        .page-header .header-stats .stat-badge.unread { border-color: #e74c3c; }
        .page-header .header-stats .stat-badge.unread i { color: #e74c3c; }
        .page-header .header-stats .stat-badge.unread .num { color: #e74c3c; }

        .msg-success, .msg-error { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 12px; }
        .msg-success { background: rgba(46,204,113,0.15); border: 1px solid #2ecc71; color: #2ecc71; }
        .msg-error { background: rgba(231,76,60,0.15); border: 1px solid #e74c3c; color: #e74c3c; }

        .filter-bar { display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-bottom: 25px; background: #0a1914; padding: 15px 20px; border-radius: 14px; border: 1px solid #1e4538; }
        .filter-bar input, .filter-bar select { background: #111; border: 1px solid #2a2a2a; border-radius: 10px; padding: 10px 16px; color: #fff; font-family: 'Poppins', sans-serif; font-size: 14px; transition: 0.3s; flex: 1; min-width: 150px; }
        .filter-bar input:focus, .filter-bar select:focus { border-color: #D4AF37; outline: none; box-shadow: 0 0 20px rgba(212,175,55,0.05); }
        .filter-bar input::placeholder { color: #666; }
        .filter-bar select option { background: #111; color: #fff; }

        .table-card { background: #0a1914; padding: 25px 20px 20px; border-radius: 20px; border: 1px solid #1e4538; overflow-x: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        thead th { color: #D4AF37; padding: 14px 16px; text-align: left; border-bottom: 2px solid #1e4538; font-size: 13px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; }
        thead th i { margin-right: 6px; font-size: 14px; }
        tbody td { padding: 14px 16px; border-bottom: 1px solid #1e4538; font-size: 14px; vertical-align: middle; }
        tbody tr:hover td { background: rgba(30,69,56,0.20); }
        tbody tr.unread td { border-left: 3px solid #D4AF37; }
        tbody tr.unread .msg-subject { font-weight: 600; color: #fff; }

        .msg-preview { max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #aaa; }
        .badge { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
        .badge-unread { background: rgba(231,76,60,0.20); color: #e74c3c; border: 1px solid rgba(231,76,60,0.30); }
        .badge-read { background: rgba(46,204,113,0.20); color: #2ecc71; border: 1px solid rgba(46,204,113,0.30); }

        .action-group { display: flex; gap: 6px; flex-wrap: wrap; }
        .btn-action { padding: 4px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 500; transition: 0.3s; display: inline-flex; align-items: center; gap: 4px; border: 1px solid transparent; }
        .btn-read { color: #2ecc71; border-color: #2ecc71; }
        .btn-read:hover { background: #2ecc71; color: #fff; }
        .btn-unread { color: #f39c12; border-color: #f39c12; }
        .btn-unread:hover { background: #f39c12; color: #fff; }
        .btn-delete-sm { color: #e74c3c; border-color: #e74c3c; }
        .btn-delete-sm:hover { background: #e74c3c; color: #fff; }

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
            .page-header { flex-direction: column; align-items: stretch; }
            .page-header .header-stats { justify-content: flex-start; }
        }
        @media (max-width: 768px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar input, .filter-bar select { min-width: 100%; }
            table { min-width: 600px; }
            .page-header h1 { font-size: 26px; }
            .msg-preview { max-width: 150px; }
            .page-header .header-stats .stat-badge { font-size: 12px; padding: 6px 12px; }
        }
        @media (max-width: 480px) {
            .sidebar { width: 60px; padding: 15px 8px; }
            .sidebar h2 { display: none; }
            .sidebar a { padding: 10px 8px; font-size: 11px; text-align: center; justify-content: center; }
            .sidebar a span { display: none; }
            .sidebar a i { width: auto; font-size: 20px; }
            .main-content { margin-left: 0; padding: 20px 12px 40px; }
            table { min-width: 500px; }
            th, td { padding: 10px 10px; font-size: 12px; }
            .action-group { flex-direction: column; }
            .btn-action { font-size: 10px; padding: 3px 8px; }
            .badge { font-size: 10px; padding: 2px 10px; }
            .msg-preview { max-width: 100px; }
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
        <a href="admin_reservations.php"><i class="fas fa-calendar-check"></i> <span>Reservations</span></a>
        <a href="admin_menu.php"><i class="fas fa-utensils"></i> <span>Menu</span></a>
        <a href="admin_reviews.php"><i class="fas fa-star"></i> Ratings</a>
        <a href="admin_messages.php" class="active"><i class="fas fa-envelope"></i> <span>All Messages</span></a>
        <a href="admin_offers.php"><i class="fas fa-tags"></i> <span>Offers</span></a>
        <a href="admin_logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>

    <div class="main-content">

        <div class="page-header">
            <h1><i class="fas fa-envelope"></i> <span>All Messages</span></h1>
            <div class="header-stats">
                <span class="stat-badge">
                    <i class="fas fa-envelope"></i> Total: <span class="num"><?php echo $total_count; ?></span>
                </span>
                <?php if ($has_status): ?>
                    <span class="stat-badge unread">
                        <i class="fas fa-circle"></i> Unread: <span class="num"><?php echo $unread_count; ?></span>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php echo $action_msg; ?>

        <div class="filter-bar">
            <input type="text" id="searchInput" placeholder="🔍 Search by name, email or subject..." onkeyup="filterTable()">
            <select id="statusFilter" onchange="filterTable()">
                <option value="all">📂 All Status</option>
                <?php if ($has_status): ?>
                    <option value="unread">📩 Unread</option>
                    <option value="read">📨 Read</option>
                <?php endif; ?>
            </select>
        </div>

        <div class="table-card">
            <table id="messagesTable">
                <thead>
                    <tr>
                        <th><i class="fas fa-user"></i> Name</th>
                        <th><i class="fas fa-envelope"></i> Email</th>
                        <th><i class="fas fa-tag"></i> Subject</th>
                        <th><i class="fas fa-comment"></i> Message</th>
                        <th><i class="fas fa-clock"></i> Date</th>
                        <?php if ($has_status): ?>
                            <th><i class="fas fa-circle"></i> Status</th>
                        <?php endif; ?>
                        <th><i class="fas fa-cog"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // ✅ SHOW ONLY CUSTOMER MESSAGES
                    $sql = "SELECT * FROM messages";
                    if ($has_notification_type) {
                        $sql .= " WHERE notification_type = 'customer_message'";
                    }
                    $sql .= " ORDER BY id DESC";
                    
                    $result = mysqli_query($conn, $sql);
                    if ($result && mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $row_status = $has_status && isset($row['status']) ? $row['status'] : 'read';
                            $unread_class = ($has_status && $row_status == 'unread') ? 'unread' : '';
                            $badge_class = $row_status == 'unread' ? 'badge-unread' : 'badge-read';
                            $subject = htmlspecialchars($row['subject'] ?? 'No Subject');
                    ?>
                            <tr class="<?php echo $unread_class; ?>" data-status="<?php echo $row_status; ?>">
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td class="msg-subject"><?php echo $subject; ?></td>
                                <td>
                                    <div class="msg-preview">
                                        <?php echo htmlspecialchars(substr($row['message'], 0, 60)); ?>
                                        <?php if (strlen($row['message']) > 60): ?>
                                            <span style="color:#555;">...</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                                <?php if ($has_status): ?>
                                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo $row_status; ?></span></td>
                                <?php endif; ?>
                                <td>
                                    <div class="action-group">
                                        <?php if ($has_status): ?>
                                            <?php if ($row_status == 'unread'): ?>
                                                <a href="?mark=<?php echo $row['id']; ?>&status=read" class="btn-action btn-read">
                                                    <i class="fas fa-check"></i> Read
                                                </a>
                                            <?php else: ?>
                                                <a href="?mark=<?php echo $row['id']; ?>&status=unread" class="btn-action btn-unread">
                                                    <i class="fas fa-undo"></i> Unread
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <a href="?delete=<?php echo $row['id']; ?>" class="btn-action btn-delete-sm" onclick="return confirm('Delete this message?')">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                    <?php
                        }
                    } else {
                        echo '<tr><td colspan="' . ($has_status ? 7 : 6) . '"><div class="no-data">
                                <i class="fas fa-inbox"></i>
                                <p>No customer messages found.</p>
                                <p style="font-size:12px; color:#555;">📩 Send a message from contact.php first!</p>
                              </div></td></tr>';
                    }
                    ?>
                </tbody>
            </table>

            <div class="table-footer">
                <div class="total-items">
                    <i class="fas fa-list-ul"></i> Total Messages: <strong id="totalCount">0</strong>
                </div>
                <div style="font-size:13px; color:#555;">
                    <i class="fas fa-arrow-up"></i> Latest messages shown first
                </div>
            </div>
        </div>

    </div>

    <script>
        function filterTable() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const rows = document.querySelectorAll('#messagesTable tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                if (row.querySelector('.no-data')) return;

                const name = row.querySelector('td:nth-child(1)')?.textContent.toLowerCase() || '';
                const email = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                const subject = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
                const message = row.querySelector('td:nth-child(4)')?.textContent.toLowerCase() || '';
                const rowStatus = row.getAttribute('data-status') || '';

                const matchSearch = name.includes(input) || email.includes(input) || subject.includes(input) || message.includes(input);
                const matchStatus = (statusFilter === 'all' || rowStatus === statusFilter);

                if (matchSearch && matchStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('totalCount').textContent = visibleCount;
        }

        document.addEventListener('DOMContentLoaded', function() {
            filterTable();
        });

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