<?php
// ============================================================
// ADMIN MENU - Manage Menu Items (List View Only)
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/admin_auth.php';

// ============================================================
// AUTO-DETECT BASE URL
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
// CHECK IF is_available COLUMN EXISTS
// ============================================================
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM menu LIKE 'is_available'");
if (mysqli_num_rows($col_check) == 0) {
    mysqli_query($conn, "ALTER TABLE menu ADD COLUMN is_available TINYINT(1) DEFAULT 1 AFTER price");
}

// ============================================================
// HANDLE AJAX TOGGLE REQUEST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_availability'])) {
    header('Content-Type: application/json');
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $status = isset($_POST['is_available']) ? 1 : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        exit();
    }
    
    // Use prepared statement
    $stmt = mysqli_prepare($conn, "UPDATE menu SET is_available = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $status, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'id' => $id, 'new_status' => $status]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
    mysqli_stmt_close($stmt);
    exit();
}

// ============================================================
// HANDLE DELETE
// ============================================================
$action_msg = '';
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = mysqli_prepare($conn, "DELETE FROM menu WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt)) {
        $action_msg = '<div class="msg-success"><i class="fas fa-check-circle"></i> Item deleted successfully!</div>';
    } else {
        $action_msg = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Error deleting item.</div>';
    }
    mysqli_stmt_close($stmt);
}

// ============================================================
// FETCH ALL MENU ITEMS
// ============================================================
$items = [];
$result = mysqli_query($conn, "SELECT * FROM menu ORDER BY category, item_name");
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
}

// Get counts
$total_count = count($items);
$available_count = 0;
$unavailable_count = 0;
foreach ($items as $item) {
    if ($item['is_available']) $available_count++;
    else $unavailable_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management | Gourmet Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --gold: #D4AF37;
            --gold-dark: #b8962e;
            --bg-body: #050e0c;
            --bg-sidebar: #0a1914;
            --bg-card: #0f241c;
            --bg-input: #111;
            --border-color: #1e4538;
            --text-primary: #fff;
            --text-secondary: #b0c4b1;
            --text-muted: #888;
            --shadow: 0 15px 40px rgba(0,0,0,0.6);
            --radius: 16px;
            --transition: 0.3s cubic-bezier(0.4,0,0.2,1);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Poppins',sans-serif; background:var(--bg-body); color:var(--text-primary); display:flex; min-height:100vh; line-height:1.6; }
        .sidebar { width:260px; min-height:100vh; background:var(--bg-sidebar); border-right:1px solid var(--border-color); padding:40px 20px; position:fixed; top:0; left:0; height:100%; overflow-y:auto; z-index:1000; transition:transform 0.3s ease; }
        .sidebar::-webkit-scrollbar { width:4px; }
        .sidebar::-webkit-scrollbar-track { background:transparent; }
        .sidebar::-webkit-scrollbar-thumb { background:var(--gold); border-radius:10px; }
        .sidebar h2 { font-family:'Playfair Display',serif; font-size:28px; color:var(--gold); letter-spacing:2px; margin-bottom:40px; text-align:center; border-bottom:1px solid var(--border-color); padding-bottom:20px; }
        .sidebar a { display:flex; align-items:center; gap:14px; color:var(--text-secondary); text-decoration:none; padding:12px 18px; margin-bottom:6px; border-radius:10px; transition:var(--transition); font-weight:400; font-size:14px; position:relative; }
        .sidebar a i { width:22px; font-size:18px; text-align:center; color:var(--text-muted); transition:var(--transition); }
        .sidebar a:hover, .sidebar a.active { background:rgba(30,69,56,0.4); color:var(--gold); }
        .sidebar a:hover i, .sidebar a.active i { color:var(--gold); }
        .sidebar a.active::before { content:''; position:absolute; left:0; top:20%; height:60%; width:3px; background:var(--gold); border-radius:0 4px 4px 0; }
        .sidebar a.logout { color:#e74c3c; margin-top:30px; border-top:1px solid var(--border-color); padding-top:20px; }
        .sidebar a.logout i { color:#e74c3c; }
        .sidebar a.logout:hover { background:#e74c3c; color:#fff; }
        .sidebar a.logout:hover i { color:#fff; }
        .main-content { margin-left:300px; padding:40px 50px 60px; width:calc(100% - 300px); min-height:100vh; transition:margin-left 0.3s ease; }
        .page-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; margin-bottom:30px; padding-bottom:20px; border-bottom:1px solid var(--border-color); }
        .page-header .title-group { display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
        .page-header h1 { font-family:'Playfair Display',serif; font-size:32px; color:#fff; display:flex; align-items:center; gap:12px; }
        .page-header h1 i { color:var(--gold); }
        .page-header h1 span { color:var(--gold); }
        .btn-action { padding:10px 22px; border:none; border-radius:50px; font-weight:600; font-size:14px; cursor:pointer; transition:var(--transition); text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
        .btn-action i { font-size:16px; }
        .btn-add { background:linear-gradient(135deg,#2ecc71,#27ae60); color:#fff; box-shadow:0 4px 15px rgba(46,204,113,0.25); }
        .btn-add:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(46,204,113,0.4); background:#fff; color:#27ae60; }
        .btn-edit-menu { background:linear-gradient(135deg,#3498db,#2980b9); color:#fff; box-shadow:0 4px 15px rgba(52,152,219,0.25); }
        .btn-edit-menu:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(52,152,219,0.4); background:#fff; color:#2980b9; }
        .header-stats { display:flex; gap:12px; flex-wrap:wrap; }
        .stat-badge { background:var(--bg-card); padding:6px 16px; border-radius:50px; border:1px solid var(--border-color); font-size:13px; display:inline-flex; align-items:center; gap:8px; color:var(--text-secondary); transition:var(--transition); }
        .stat-badge i { color:var(--gold); font-size:14px; }
        .stat-badge .num { color:#fff; font-weight:700; margin-left:2px; }
        .stat-badge.available { border-color:rgba(46,204,113,0.3); }
        .stat-badge.available i { color:#2ecc71; }
        .stat-badge.available .num { color:#2ecc71; }
        .stat-badge.unavailable { border-color:rgba(231,76,60,0.3); }
        .stat-badge.unavailable i { color:#e74c3c; }
        .stat-badge.unavailable .num { color:#e74c3c; }
        .msg-success, .msg-error { padding:14px 20px; border-radius:12px; margin-bottom:20px; font-weight:500; display:flex; align-items:center; gap:12px; border-left:4px solid transparent; }
        .msg-success { background:rgba(46,204,113,0.08); border-color:#2ecc71; color:#2ecc71; }
        .msg-error { background:rgba(231,76,60,0.08); border-color:#e74c3c; color:#e74c3c; }
        .table-card { background:var(--bg-card); padding:25px 20px 20px; border-radius:var(--radius); border:1px solid var(--border-color); overflow-x:auto; box-shadow:var(--shadow); }
        .table-card::-webkit-scrollbar { height:6px; }
        .table-card::-webkit-scrollbar-track { background:transparent; }
        .table-card::-webkit-scrollbar-thumb { background:var(--gold); border-radius:10px; }
        table { width:100%; border-collapse:collapse; min-width:750px; }
        thead th { color:var(--gold); padding:14px 16px; text-align:left; border-bottom:2px solid var(--border-color); font-size:11px; font-weight:600; letter-spacing:1px; text-transform:uppercase; }
        thead th i { margin-right:8px; font-size:13px; }
        tbody tr { transition:var(--transition); border-bottom:1px solid rgba(30,69,56,0.3); }
        tbody tr:last-child { border-bottom:none; }
        tbody tr:hover td { background:rgba(30,69,56,0.15); }
        tbody td { padding:14px 16px; font-size:14px; vertical-align:middle; color:var(--text-secondary); }
        tbody td strong { color:#fff; }
        .menu-image { width:48px; height:48px; object-fit:cover; border-radius:10px; border:1px solid var(--border-color); background:#1a1a1a; }
        .price { color:var(--gold); font-weight:600; font-size:15px; }
        .status-container { display:flex; align-items:center; gap:12px; }
        .status-dot { width:8px; height:8px; border-radius:50%; display:inline-block; flex-shrink:0; }
        .status-dot.available { background:#2ecc71; box-shadow:0 0 10px rgba(46,204,113,0.3); }
        .status-dot.unavailable { background:#e74c3c; box-shadow:0 0 10px rgba(231,76,60,0.3); }
        .status-label { font-size:13px; font-weight:500; }
        .status-label.available { color:#2ecc71; }
        .status-label.unavailable { color:#e74c3c; }
        .toggle-switch { position:relative; display:inline-block; width:44px; height:24px; flex-shrink:0; }
        .toggle-switch input { opacity:0; width:0; height:0; }
        .toggle-switch .slider { position:absolute; top:0; left:0; right:0; bottom:0; background:#2a2a2a; border-radius:24px; transition:var(--transition); cursor:pointer; border:1px solid #333; }
        .toggle-switch .slider::before { content:''; position:absolute; top:2px; left:2px; width:18px; height:18px; background:#fff; border-radius:50%; transition:var(--transition); box-shadow:0 2px 5px rgba(0,0,0,0.3); }
        .toggle-switch input:checked + .slider { background:#2ecc71; border-color:#2ecc71; }
        .toggle-switch input:checked + .slider::before { transform:translateX(20px); background:#fff; }
        .toggle-switch input:disabled + .slider { opacity:0.5; cursor:not-allowed; }
        .action-group { display:flex; gap:8px; flex-wrap:wrap; }
        .btn-delete-sm { padding:5px 14px; border-radius:6px; font-size:12px; font-weight:500; text-decoration:none; transition:var(--transition); display:inline-flex; align-items:center; gap:6px; border:1px solid rgba(231,76,60,0.3); background:rgba(231,76,60,0.05); color:#e74c3c; }
        .btn-delete-sm:hover { background:#e74c3c; color:#fff; border-color:#e74c3c; transform:translateY(-2px); box-shadow:0 4px 12px rgba(231,76,60,0.3); }
        .no-data { text-align:center; padding:50px 0; color:var(--text-muted); }
        .no-data i { font-size:52px; color:#1e4538; display:block; margin-bottom:15px; }
        .no-data strong { color:var(--gold); }
        .table-footer { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-top:20px; padding-top:15px; border-top:1px solid var(--border-color); }
        .total-items { color:var(--text-muted); font-size:14px; }
        .total-items strong { color:var(--gold); font-size:18px; }
        .table-footer .hint { font-size:13px; color:#444; }
        .table-footer .hint i { color:var(--gold); }
        .hamburger { display:none; background:transparent; border:none; color:var(--gold); font-size:28px; cursor:pointer; padding:8px 12px; border-radius:10px; transition:var(--transition); position:fixed; top:15px; left:15px; z-index:1001; }
        .hamburger:hover { background:rgba(212,175,55,0.1); }
        @media (max-width:992px) {
            .sidebar { transform:translateX(-100%); width:280px; }
            .sidebar.open { transform:translateX(0); }
            .main-content { margin-left:0; width:100%; padding:30px 25px 50px; }
            .hamburger { display:flex !important; }
            .page-header { flex-direction:column; align-items:stretch; }
            .header-stats { justify-content:flex-start; }
        }
        @media (max-width:600px) {
            .main-content { padding:20px 15px 40px; }
            table { min-width:600px; }
            .page-header h1 { font-size:26px; }
            .btn-action { width:100%; justify-content:center; padding:12px; }
            .title-group { width:100%; }
            .status-container { flex-wrap:wrap; }
            .action-group { flex-direction:column; align-items:stretch; }
            .btn-delete-sm { justify-content:center; }
            .header-stats .stat-badge { font-size:11px; padding:4px 12px; }
        }
        @media (max-width:480px) {
            .sidebar { width:60px; padding:15px 10px; }
            .sidebar h2 { display:none; }
            .sidebar a { padding:10px; justify-content:center; }
            .sidebar a span { display:none; }
            .sidebar a i { width:auto; font-size:20px; margin:0; }
            .sidebar a.active::before { left:0; top:10%; height:80%; }
            .main-content { padding:60px 12px 30px; }
        }
    </style>
</head>
<body>

    <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>

    <div class="sidebar" id="sidebar">
        <h2>✨ GOURMET</h2>
        <a href="admin.php"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a>
        <a href="admin_reservations.php"><i class="fas fa-calendar-check"></i> <span>Reservations</span></a>
        <a href="admin_menu.php" class="active"><i class="fas fa-utensils"></i> <span>Menu</span></a>
        <a href="admin_reviews.php"><i class="fas fa-star"></i> Ratings</a>
        <a href="admin_messages.php"><i class="fas fa-envelope"></i> <span>Messages</span></a>
        <a href="admin_offers.php"><i class="fas fa-tags"></i> <span>Offers</span></a>
        <a href="admin_logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>

    <div class="main-content">

        <div class="page-header">
            <div class="title-group">
                <h1><i class="fas fa-utensils"></i> <span>Menu</span> Management</h1>
                <a href="admin_menu_add.php" class="btn-action btn-add">
                    <i class="fas fa-plus-circle"></i> Add New Item
                </a>
                <a href="admin_menu_edit.php" class="btn-action btn-edit-menu">
                    <i class="fas fa-pen"></i> Edit Menu
                </a>
            </div>
            <div class="header-stats">
                <span class="stat-badge"><i class="fas fa-list"></i> Total: <span class="num"><?php echo $total_count; ?></span></span>
                <span class="stat-badge available"><i class="fas fa-check-circle"></i> Available: <span class="num"><?php echo $available_count; ?></span></span>
                <span class="stat-badge unavailable"><i class="fas fa-times-circle"></i> Out of Stock: <span class="num"><?php echo $unavailable_count; ?></span></span>
            </div>
        </div>

        <?php echo $action_msg; ?>

        <div class="table-card">
            <table id="menuTable">
                <thead>
                    <tr>
                        <th><i class="fas fa-image"></i> Image</th>
                        <th><i class="fas fa-utensils"></i> Name</th>
                        <th><i class="fas fa-tag"></i> Category</th>
                        <th><i class="fas fa-align-left"></i> Description</th>
                        <th><i class="fas fa-money-bill-wave"></i> Price</th>
                        <th><i class="fas fa-toggle-on"></i> Status</th>
                        <th><i class="fas fa-trash-alt"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $item): 
                            $img_src = !empty($item['image_url']) ? $item['image_url'] : 'assets/default.jpg';
                            if (!filter_var($img_src, FILTER_VALIDATE_URL)) {
                                $img_src = BASE_URL . 'assets/' . str_replace('assets/', '', $img_src);
                            }
                            $is_available = $item['is_available'] ?? 1;
                            $status_class = $is_available ? 'available' : 'unavailable';
                            $status_text = $is_available ? 'Available' : 'Out of Stock';
                        ?>
                            <tr>
                                <td><img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>" class="menu-image" loading="lazy" onerror="this.src='<?php echo BASE_URL; ?>assets/default.jpg'"></td>
                                <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                <td><span style="color:var(--text-secondary);"><?php echo htmlspecialchars($item['category']); ?></span></td>
                                <td style="max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:var(--text-muted);">
                                    <?php echo htmlspecialchars($item['description']); ?>
                                </td>
                                <td class="price">LKR <?php echo number_format($item['price'], 2); ?></td>
                                <td>
                                    <div class="status-container">
                                        <label class="toggle-switch">
                                            <input type="checkbox" class="availability-toggle" data-id="<?php echo $item['id']; ?>" <?php echo $is_available ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <span class="status-dot <?php echo $status_class; ?>"></span>
                                        <span class="status-label <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <a href="?delete=<?php echo $item['id']; ?>" class="btn-delete-sm" onclick="return confirm('Delete this item?')"><i class="fas fa-trash-alt"></i> Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7">
                            <div class="no-data">
                                <i class="fas fa-utensils"></i>
                                <p>No menu items added yet. <br> Click <strong>"Add New Item"</strong> to get started.</p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="table-footer">
                <div class="total-items">
                    <i class="fas fa-list-ul"></i> Total Items: <strong><?php echo $total_count; ?></strong>
                </div>
                <div class="hint">
                    <i class="fas fa-arrow-up"></i> Click toggle to change availability instantly
                </div>
            </div>
        </div>

    </div>

    <script>
        // ============================================================
        // 1. HAMBURGER MENU TOGGLE
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
        // 2. AVAILABILITY TOGGLE - හරියට වැඩ කරයි
        // ============================================================
        document.querySelectorAll('.availability-toggle').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const id = this.dataset.id;
                const isChecked = this.checked ? 1 : 0;
                const row = this.closest('tr');
                const statusDot = row.querySelector('.status-dot');
                const statusLabel = row.querySelector('.status-label');
                
                // Disable toggle while processing
                this.disabled = true;
                
                console.log('🔄 Toggling item ID:', id, 'to:', isChecked);
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'toggle_availability=1&id=' + id + '&is_available=' + isChecked
                })
                .then(response => {
                    console.log('📡 Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('📦 Response data:', data);
                    
                    if (data.success) {
                        // Update UI
                        if (isChecked) {
                            statusDot.className = 'status-dot available';
                            statusLabel.className = 'status-label available';
                            statusLabel.textContent = 'Available';
                        } else {
                            statusDot.className = 'status-dot unavailable';
                            statusLabel.className = 'status-label unavailable';
                            statusLabel.textContent = 'Out of Stock';
                        }
                        this.disabled = false;
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                        this.checked = !this.checked;
                        this.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('❌ Fetch error:', error);
                    alert('Something went wrong. Please try again.');
                    this.checked = !this.checked;
                    this.disabled = false;
                });
            });
        });

        console.log('BASE_URL: <?php echo BASE_URL; ?>');
        console.log('✨ Gourmet Admin Menu Loaded');
    </script>

</body>
</html>