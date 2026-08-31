<?php
// ============================================================
// INVOICE LIST - Admin Panel
// ============================================================
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';

$invoices = [];
$result = mysqli_query($conn, "SELECT * FROM invoices ORDER BY invoice_date DESC");
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $invoices[] = $row;
    }
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM invoices WHERE id = $id");
    header("Location: invoice.php");
    exit();
}

function safeHtml($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices | Gourmet Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Poppins',sans-serif; background:#050e0c; color:#fff; display:flex; min-height:100vh; }
        .sidebar { width:260px; min-height:100vh; background:#0a1914; border-right:2px solid #1e4538; padding:40px 20px; position:fixed; top:0; left:0; height:100%; overflow-y:auto; z-index:1000; transition:transform 0.3s ease; }
        .sidebar h2 { font-family:'Playfair Display',serif; font-size:28px; color:#D4AF37; letter-spacing:2px; margin-bottom:40px; text-align:center; border-bottom:1px solid #1e4538; padding-bottom:20px; }
        .sidebar a { display:flex; align-items:center; gap:14px; color:#b0c4b1; text-decoration:none; padding:12px 18px; margin-bottom:6px; border-radius:10px; transition:0.3s; font-weight:400; font-size:14px; position:relative; }
        .sidebar a i { width:22px; font-size:18px; text-align:center; color:#888; transition:0.3s; }
        .sidebar a:hover, .sidebar a.active { background:rgba(30,69,56,0.4); color:#D4AF37; }
        .sidebar a:hover i, .sidebar a.active i { color:#D4AF37; }
        .sidebar a.active::before { content:''; position:absolute; left:0; top:20%; height:60%; width:3px; background:#D4AF37; border-radius:0 4px 4px 0; }
        .sidebar a.logout { color:#e74c3c; margin-top:30px; border-top:1px solid #1e4538; padding-top:20px; }
        .sidebar a.logout:hover { background:#e74c3c; color:#fff; }
        .main-content { margin-left:300px; padding:40px 50px 60px; width:calc(100% - 300px); min-height:100vh; transition:margin-left 0.3s ease; }
        .page-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; margin-bottom:30px; padding-bottom:20px; border-bottom:1px solid #1e4538; }
        .page-header h1 { font-family:'Playfair Display',serif; font-size:32px; color:#fff; display:flex; align-items:center; gap:12px; }
        .page-header h1 i { color:#D4AF37; }
        .page-header h1 span { color:#D4AF37; }
        .stat-badge { background:#0f241c; padding:6px 16px; border-radius:50px; border:1px solid #1e4538; font-size:13px; display:inline-flex; align-items:center; gap:8px; color:#b0c4b1; }
        .stat-badge i { color:#D4AF37; }
        .stat-badge .num { color:#fff; font-weight:700; }
        .table-card { background:#0f241c; padding:25px 20px 20px; border-radius:16px; border:1px solid #1e4538; overflow-x:auto; box-shadow:0 15px 40px rgba(0,0,0,0.6); }
        table { width:100%; border-collapse:collapse; min-width:800px; }
        thead th { color:#D4AF37; padding:12px 16px; text-align:left; border-bottom:2px solid #1e4538; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; }
        tbody tr { border-bottom:1px solid rgba(30,69,56,0.3); transition:0.3s; }
        tbody tr:hover td { background:rgba(30,69,56,0.15); }
        tbody td { padding:12px 16px; font-size:14px; color:#b0c4b1; }
        tbody td strong { color:#fff; }
        .badge { padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .badge-success { background:rgba(46,204,113,0.15); color:#2ecc71; border:1px solid rgba(46,204,113,0.3); }
        .badge-warning { background:rgba(241,196,15,0.15); color:#f1c40f; border:1px solid rgba(241,196,15,0.3); }
        .badge-danger { background:rgba(231,76,60,0.15); color:#e74c3c; border:1px solid rgba(231,76,60,0.3); }
        .btn-action { padding:6px 14px; border-radius:6px; font-size:12px; font-weight:500; text-decoration:none; transition:0.3s; display:inline-flex; align-items:center; gap:6px; }
        .btn-view { color:#3498db; border:1px solid rgba(52,152,219,0.3); background:rgba(52,152,219,0.05); }
        .btn-view:hover { background:#3498db; color:#fff; border-color:#3498db; transform:translateY(-2px); }
        .btn-download { color:#2ecc71; border:1px solid rgba(46,204,113,0.3); background:rgba(46,204,113,0.05); }
        .btn-download:hover { background:#2ecc71; color:#fff; border-color:#2ecc71; transform:translateY(-2px); }
        .btn-delete-sm { color:#e74c3c; border:1px solid rgba(231,76,60,0.3); background:rgba(231,76,60,0.05); }
        .btn-delete-sm:hover { background:#e74c3c; color:#fff; border-color:#e74c3c; transform:translateY(-2px); }
        .hamburger { display:none; background:transparent; border:none; color:#D4AF37; font-size:28px; cursor:pointer; padding:8px 12px; border-radius:10px; transition:0.3s; position:fixed; top:15px; left:15px; z-index:1001; }
        .hamburger:hover { background:rgba(212,175,55,0.1); }
        .no-data { text-align:center; padding:50px 0; color:#666; }
        .no-data i { font-size:52px; color:#1e4538; display:block; margin-bottom:15px; }
        @media (max-width:992px) {
            .sidebar { transform:translateX(-100%); width:280px; }
            .sidebar.open { transform:translateX(0); }
            .main-content { margin-left:0; width:100%; padding:30px 25px 50px; }
            .hamburger { display:flex !important; }
            .page-header { flex-direction:column; align-items:stretch; }
        }
        @media (max-width:600px) {
            .main-content { padding:20px 15px 40px; }
            table { min-width:600px; }
            .page-header h1 { font-size:26px; }
        }
    </style>
</head>
<body>

    <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>

    <div class="sidebar" id="sidebar">
        <h2>✨ GOURMET</h2>
        <a href="admin.php"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a>
        <a href="admin_reservations.php"><i class="fas fa-calendar-check"></i> <span>Reservations</span></a>
        <a href="admin_menu.php"><i class="fas fa-utensils"></i> <span>Menu</span></a>
        <a href="admin_reviews.php"><i class="fas fa-star"></i> Ratings</a>
        <a href="admin_messages.php"><i class="fas fa-envelope"></i> <span>Messages</span></a>
        <a href="admin_offers.php"><i class="fas fa-tags"></i> <span>Offers</span></a>
        <a href="invoice.php" class="active"><i class="fas fa-file-invoice"></i> <span>Invoices</span></a>
        <a href="admin_logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>

    <div class="main-content">

        <div class="page-header">
            <h1><i class="fas fa-file-invoice"></i> <span>Invoices</span></h1>
            <span class="stat-badge"><i class="fas fa-file-invoice"></i> Total: <span class="num"><?php echo count($invoices); ?></span></span>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($invoices)): ?>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td><strong><?php echo safeHtml($inv['invoice_number']); ?></strong></td>
                                <td>
                                    <div style="font-weight:600; color:#fff;"><?php echo safeHtml($inv['customer_name']); ?></div>
                                    <div style="font-size:12px; color:#666;"><?php echo safeHtml($inv['customer_email']); ?></div>
                                </td>
                                <td style="color:#D4AF37; font-weight:600;">LKR <?php echo number_format($inv['total'], 2); ?></td>
                                <td>
                                    <?php
                                    $status = $inv['payment_status'];
                                    $badge_class = 'badge-warning';
                                    if ($status == 'Paid') $badge_class = 'badge-success';
                                    elseif ($status == 'Pending') $badge_class = 'badge-warning';
                                    elseif ($status == 'Cancelled') $badge_class = 'badge-danger';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $status; ?></span>
                                </td>
                                <td style="color:#888; font-size:13px;"><?php echo date('d M Y, h:i A', strtotime($inv['invoice_date'])); ?></td>
                                <td>
                                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                        <a href="invoice_view.php?id=<?php echo $inv['id']; ?>" class="btn-action btn-view"><i class="fas fa-eye"></i> View</a>
                                        <a href="invoice_generate.php?id=<?php echo $inv['id']; ?>" class="btn-action btn-download" target="_blank"><i class="fas fa-download"></i> PDF</a>
                                        <a href="?delete=<?php echo $inv['id']; ?>" class="btn-action btn-delete-sm" onclick="return confirm('Delete this invoice?')"><i class="fas fa-trash-alt"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="no-data">
                                    <i class="fas fa-file-invoice"></i>
                                    <p>No invoices found.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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