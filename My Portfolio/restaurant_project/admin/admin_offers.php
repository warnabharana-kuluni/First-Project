<?php
// ============================================================
// 1. Database සහ Authentication Include කරන්න
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/admin_auth.php';

// ============================================================
// 2. Check if columns exist in offers table
// ============================================================
function columnExists($table, $column) {
    global $conn;
    if (!$conn) return false;
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

$has_is_active = columnExists('offers', 'is_active');
$has_valid_from = columnExists('offers', 'valid_from');
$has_valid_to = columnExists('offers', 'valid_to');
$has_discount_percent = columnExists('offers', 'discount_percent');

// ============================================================
// 3. Handle Add Offer
// ============================================================
$action_msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_offer'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    
    // Check which price field exists
    $price_field = $has_discount_percent ? 'discount_percent' : 'discount_price';
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    
    $valid_from = isset($_POST['valid_from']) && !empty($_POST['valid_from']) ? "'" . $_POST['valid_from'] . "'" : "NULL";
    $valid_to = isset($_POST['valid_to']) && !empty($_POST['valid_to']) ? "'" . $_POST['valid_to'] . "'" : "NULL";
    
    $sql = "INSERT INTO offers (title, description, $price_field, valid_from, valid_to, is_active) 
            VALUES ('$title', '$desc', $price, $valid_from, $valid_to, 1)";
    
    if (mysqli_query($conn, $sql)) {
        $action_msg = '<div class="msg-success"><i class="fas fa-check-circle"></i> Offer added successfully!</div>';
    } else {
        $action_msg = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Error: ' . mysqli_error($conn) . '</div>';
    }
}

// ============================================================
// 4. Handle Delete Offer
// ============================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if (mysqli_query($conn, "DELETE FROM offers WHERE id = $id")) {
        $action_msg = '<div class="msg-success"><i class="fas fa-check-circle"></i> Offer deleted successfully!</div>';
    } else {
        $action_msg = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Error deleting offer.</div>';
    }
}

// ============================================================
// 5. Handle Toggle Active Status
// ============================================================
if (isset($_GET['toggle']) && is_numeric($_GET['toggle']) && $has_is_active) {
    $id = (int)$_GET['toggle'];
    $sql = "UPDATE offers SET is_active = NOT is_active WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        $action_msg = '<div class="msg-success"><i class="fas fa-check-circle"></i> Offer status toggled successfully!</div>';
    } else {
        $action_msg = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Error toggling status.</div>';
    }
}

// ============================================================
// 6. Get Counts
// ============================================================
$total_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM offers"))['c'] ?? 0;
$active_count = 0;
$inactive_count = 0;

if ($has_is_active) {
    $active_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM offers WHERE is_active=1"))['c'] ?? 0;
    $inactive_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM offers WHERE is_active=0"))['c'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offers | Gourmet Admin</title>
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
            display: flex;
            min-height: 100vh;
        }

        /* ============================================================
                   SIDEBAR
                   ============================================================ */
        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: #0a1914;
            border-right: 2px solid #1e4538;
            padding: 40px 20px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        .sidebar h2 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: #D4AF37;
            letter-spacing: 2px;
            margin-bottom: 40px;
            text-align: center;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #b0c4b1;
            text-decoration: none;
            padding: 14px 20px;
            margin-bottom: 8px;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 400;
        }
        .sidebar a i {
            width: 22px;
            font-size: 18px;
            text-align: center;
        }
        .sidebar a:hover,
        .sidebar a.active {
            background: #1e4538;
            color: #D4AF37;
        }
        .sidebar a.active {
            font-weight: 600;
        }
        .sidebar a.logout {
            color: #e74c3c;
            margin-top: 30px;
            border-top: 1px solid #1e4538;
            padding-top: 20px;
        }
        .sidebar a.logout:hover {
            background: #e74c3c;
            color: #fff;
        }

        /* ============================================================
                   MAIN CONTENT
                   ============================================================ */
        .main-content {
            margin-left: 300px;
            padding: 40px 50px 60px;
            width: calc(100% - 300px);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        /* ============================================================
                   PAGE HEADER
                   ============================================================ */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 25px;
        }
        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 34px;
            color: #fff;
        }
        .page-header h1 span {
            color: #D4AF37;
        }
        .page-header h1 i {
            margin-right: 10px;
            color: #D4AF37;
        }
        .page-header .header-stats {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .page-header .header-stats .stat-badge {
            background: #0a1914;
            padding: 8px 18px;
            border-radius: 20px;
            border: 1px solid #1e4538;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .page-header .header-stats .stat-badge i {
            color: #D4AF37;
        }
        .page-header .header-stats .stat-badge .num {
            color: #D4AF37;
            font-weight: 700;
        }
        .page-header .header-stats .stat-badge.active {
            border-color: #2ecc71;
        }
        .page-header .header-stats .stat-badge.active i {
            color: #2ecc71;
        }
        .page-header .header-stats .stat-badge.active .num {
            color: #2ecc71;
        }
        .page-header .header-stats .stat-badge.inactive {
            border-color: #e74c3c;
        }
        .page-header .header-stats .stat-badge.inactive i {
            color: #e74c3c;
        }
        .page-header .header-stats .stat-badge.inactive .num {
            color: #e74c3c;
        }

        /* ============================================================
                   MESSAGES
                   ============================================================ */
        .msg-success,
        .msg-error {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .msg-success {
            background: rgba(46, 204, 113, 0.15);
            border: 1px solid #2ecc71;
            color: #2ecc71;
        }
        .msg-error {
            background: rgba(231, 76, 60, 0.15);
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }

        /* ============================================================
                   SEARCH BAR
                   ============================================================ */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            margin-bottom: 25px;
            background: #0a1914;
            padding: 15px 20px;
            border-radius: 14px;
            border: 1px solid #1e4538;
        }
        .filter-bar input,
        .filter-bar select {
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            padding: 10px 16px;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: 0.3s;
            flex: 1;
            min-width: 150px;
        }
        .filter-bar input:focus,
        .filter-bar select:focus {
            border-color: #D4AF37;
            outline: none;
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.05);
        }
        .filter-bar input::placeholder {
            color: #666;
        }
        .filter-bar select option {
            background: #111;
            color: #fff;
        }

        /* ============================================================
                   ADD OFFER CARD
                   ============================================================ */
        .add-card {
            background: #0a1914;
            padding: 30px 30px 35px;
            border-radius: 20px;
            border: 1px solid #1e4538;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }
        .add-card h3 {
            color: #D4AF37;
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .add-card h3 i {
            font-size: 22px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px 25px;
        }
        .form-grid .full-width {
            grid-column: 1 / -1;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            color: #aaa;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        .form-group label i {
            margin-right: 6px;
            color: #D4AF37;
        }
        .form-group input,
        .form-group textarea {
            padding: 10px 14px;
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: 0.3s;
            width: 100%;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #D4AF37;
            outline: none;
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.05);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .btn-add-offer {
            background: #D4AF37;
            color: #0a1914;
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 5px;
            grid-column: 1 / -1;
            justify-content: center;
        }
        .btn-add-offer:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.3);
        }

        /* ============================================================
                   TABLE CARD
                   ============================================================ */
        .table-card {
            background: #0a1914;
            padding: 25px 20px 20px;
            border-radius: 20px;
            border: 1px solid #1e4538;
            overflow-x: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }
        thead th {
            color: #D4AF37;
            padding: 14px 16px;
            text-align: left;
            border-bottom: 2px solid #1e4538;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        thead th i {
            margin-right: 6px;
            font-size: 14px;
        }
        tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #1e4538;
            font-size: 14px;
            vertical-align: middle;
        }
        tbody tr {
            transition: background 0.2s ease;
        }
        tbody tr:hover td {
            background: rgba(30, 69, 56, 0.20);
        }
        tbody tr.inactive td {
            opacity: 0.6;
        }

        /* Status Badge */
        .badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .badge-active {
            background: rgba(46, 204, 113, 0.20);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.30);
        }
        .badge-inactive {
            background: rgba(231, 76, 60, 0.20);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.30);
        }

        /* Action Buttons */
        .action-group {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .btn-toggle {
            padding: 4px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid transparent;
        }
        .btn-toggle.active {
            color: #2ecc71;
            border-color: #2ecc71;
        }
        .btn-toggle.active:hover {
            background: #2ecc71;
            color: #fff;
        }
        .btn-toggle.inactive {
            color: #f39c12;
            border-color: #f39c12;
        }
        .btn-toggle.inactive:hover {
            background: #f39c12;
            color: #fff;
        }
        .btn-delete-sm {
            color: #e74c3c;
            text-decoration: none;
            padding: 4px 14px;
            border: 1px solid #e74c3c;
            border-radius: 6px;
            font-size: 12px;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-delete-sm:hover {
            background: #e74c3c;
            color: #fff;
        }

        /* No Data */
        .no-data {
            text-align: center;
            padding: 40px 0;
            color: #666;
        }
        .no-data i {
            font-size: 48px;
            display: block;
            margin-bottom: 12px;
            color: #333;
        }
        .no-data p {
            font-size: 16px;
        }

        /* ============================================================
                   TABLE FOOTER
                   ============================================================ */
        .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #1e4538;
        }
        .total-items {
            color: #888;
            font-size: 14px;
        }
        .total-items strong {
            color: #D4AF37;
            font-size: 18px;
        }
        .total-items i {
            margin-right: 6px;
            color: #D4AF37;
        }

        /* ============================================================
                   HAMBURGER MENU (Mobile)
                   ============================================================ */
        .hamburger {
            display: none;
            background: transparent;
            border: none;
            color: #D4AF37;
            font-size: 28px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 8px;
            transition: 0.3s;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
        }
        .hamburger:hover {
            background: rgba(212, 175, 55, 0.1);
        }

        /* ============================================================
                   RESPONSIVE
                   ============================================================ */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 260px;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 30px 25px 50px;
            }
            .hamburger {
                display: flex !important;
            }
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            .page-header .header-stats {
                justify-content: flex-start;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-bar input,
            .filter-bar select {
                min-width: 100%;
            }
            table {
                min-width: 600px;
            }
            .page-header h1 {
                font-size: 26px;
            }
            .page-header .header-stats .stat-badge {
                font-size: 12px;
                padding: 6px 12px;
            }
            .add-card {
                padding: 20px 18px 25px;
            }
            .form-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .btn-add-offer {
                padding: 10px 20px;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                width: 60px;
                padding: 15px 8px;
            }
            .sidebar h2 {
                display: none;
            }
            .sidebar a {
                padding: 10px 8px;
                font-size: 11px;
                text-align: center;
                justify-content: center;
            }
            .sidebar a span {
                display: none;
            }
            .sidebar a i {
                width: auto;
                font-size: 20px;
            }
            .main-content {
                margin-left: 0;
                padding: 20px 12px 40px;
            }
            table {
                min-width: 500px;
            }
            th,
            td {
                padding: 10px 10px;
                font-size: 12px;
            }
            .badge {
                font-size: 10px;
                padding: 2px 10px;
            }
            .page-header .header-stats .stat-badge {
                font-size: 11px;
                padding: 4px 10px;
            }
            .action-group {
                flex-direction: column;
            }
            .btn-toggle,
            .btn-delete-sm {
                font-size: 10px;
                padding: 3px 10px;
            }
            .add-card h3 {
                font-size: 17px;
            }
        }
    </style>
</head>
<body>

    <!-- ============================================================
    HAMBURGER MENU (Mobile)
    ============================================================ -->
    <button class="hamburger" id="hamburgerBtn" aria-label="Toggle Sidebar">
        <i class="fas fa-bars"></i>
    </button>

    <!-- ============================================================
    SIDEBAR
    ============================================================ -->
    <div class="sidebar" id="sidebar">
        <h2>✨ GOURMET</h2>
        <a href="admin.php"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a>
        <a href="admin_reservations.php"><i class="fas fa-calendar-check"></i> <span>Reservations</span></a>
        <a href="admin_menu.php"><i class="fas fa-utensils"></i> <span>Menu</span></a>
        <a href="admin_reviews.php"><i class="fas fa-star"></i> Ratings</a>
        <a href="admin_messages.php"><i class="fas fa-envelope"></i> <span>Messages</span></a>
        <a href="admin_offers.php" class="active"><i class="fas fa-tags"></i> <span>Offers</span></a>
        <a href="admin_logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>

    <!-- ============================================================
    MAIN CONTENT
    ============================================================ -->
    <div class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-tags"></i> <span>Offers</span> Management</h1>
            <div class="header-stats">
                <span class="stat-badge">
                    <i class="fas fa-list"></i> Total: <span class="num"><?php echo $total_count; ?></span>
                </span>
                <?php if ($has_is_active): ?>
                    <span class="stat-badge active">
                        <i class="fas fa-check-circle"></i> Active: <span class="num"><?php echo $active_count; ?></span>
                    </span>
                    <span class="stat-badge inactive">
                        <i class="fas fa-times-circle"></i> Inactive: <span class="num"><?php echo $inactive_count; ?></span>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Messages -->
        <?php echo $action_msg; ?>

        <!-- ============================================================
        ADD OFFER FORM
        ============================================================ -->
        <div class="add-card">
            <h3><i class="fas fa-plus-circle"></i> Add New Offer</h3>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label><i class="fas fa-heading"></i> Offer Title *</label>
                        <input type="text" name="title" placeholder="e.g. Summer Special Discount" required>
                    </div>

                    <div class="form-group full-width">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" placeholder="Describe the offer..."></textarea>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-percent"></i> <?php echo $has_discount_percent ? 'Discount %' : 'Discount Price (LKR)'; ?> *</label>
                        <input type="number" name="price" step="<?php echo $has_discount_percent ? '1' : '0.01'; ?>" placeholder="<?php echo $has_discount_percent ? 'e.g. 20' : 'e.g. 500'; ?>" required>
                    </div>

                    <?php if ($has_valid_from): ?>
                        <div class="form-group">
                            <label><i class="fas fa-calendar-plus"></i> Valid From</label>
                            <input type="date" name="valid_from">
                        </div>
                    <?php endif; ?>

                    <?php if ($has_valid_to): ?>
                        <div class="form-group">
                            <label><i class="fas fa-calendar-minus"></i> Valid To</label>
                            <input type="date" name="valid_to">
                        </div>
                    <?php endif; ?>

                    <button type="submit" name="add_offer" class="btn-add-offer">
                        <i class="fas fa-plus-circle"></i> Add Offer
                    </button>
                </div>
            </form>
        </div>

        <!-- ============================================================
        SEARCH BAR
        ============================================================ -->
        <div class="filter-bar">
            <input type="text" id="searchInput" placeholder="🔍 Search offers by title..." onkeyup="filterTable()">
            <?php if ($has_is_active): ?>
                <select id="statusFilter" onchange="filterTable()">
                    <option value="all">📂 All Offers</option>
                    <option value="1">✅ Active</option>
                    <option value="0">❌ Inactive</option>
                </select>
            <?php endif; ?>
        </div>

        <!-- ============================================================
        OFFERS TABLE
        ============================================================ -->
        <div class="table-card">
            <table id="offersTable">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-heading"></i> Title</th>
                        <th><i class="fas fa-align-left"></i> Description</th>
                        <th><i class="fas fa-percent"></i> <?php echo $has_discount_percent ? 'Discount %' : 'Price'; ?></th>
                        <?php if ($has_valid_from && $has_valid_to): ?>
                            <th><i class="fas fa-calendar-alt"></i> Validity</th>
                        <?php endif; ?>
                        <?php if ($has_is_active): ?>
                            <th><i class="fas fa-circle"></i> Status</th>
                        <?php endif; ?>
                        <th><i class="fas fa-cog"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT * FROM offers ORDER BY id DESC";
                    $result = mysqli_query($conn, $sql);

                    if ($result && mysqli_num_rows($result) > 0) {
                        $counter = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            $is_active = $has_is_active && isset($row['is_active']) ? (int)$row['is_active'] : 1;
                            $status_text = $is_active ? 'Active' : 'Inactive';
                            $badge_class = $is_active ? 'badge-active' : 'badge-inactive';
                            $row_class = $is_active ? '' : 'inactive';
                            $toggle_text = $is_active ? 'Deactivate' : 'Activate';
                            $toggle_class = $is_active ? 'active' : 'inactive';
                            
                            $discount = $has_discount_percent ? ($row['discount_percent'] ?? 0) : ($row['discount_price'] ?? 0);
                            $discount_display = $has_discount_percent ? $discount . '%' : 'LKR ' . number_format($discount, 2);
                            
                            $valid_display = '';
                            if ($has_valid_from && $has_valid_to) {
                                $from = isset($row['valid_from']) && !empty($row['valid_from']) ? date('d M Y', strtotime($row['valid_from'])) : '—';
                                $to = isset($row['valid_to']) && !empty($row['valid_to']) ? date('d M Y', strtotime($row['valid_to'])) : '—';
                                $valid_display = $from . ' → ' . $to;
                            }
                    ?>
                            <tr class="<?php echo $row_class; ?>" data-status="<?php echo $is_active; ?>">
                                <td><?php echo $counter++; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars(substr($row['description'] ?? '', 0, 50)) . (strlen($row['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                <td><?php echo $discount_display; ?></td>
                                <?php if ($has_valid_from && $has_valid_to): ?>
                                    <td style="font-size:12px; color:#888;"><?php echo $valid_display; ?></td>
                                <?php endif; ?>
                                <?php if ($has_is_active): ?>
                                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span></td>
                                <?php endif; ?>
                                <td>
                                    <div class="action-group">
                                        <?php if ($has_is_active): ?>
                                            <a href="?toggle=<?php echo $row['id']; ?>" class="btn-toggle <?php echo $toggle_class; ?>">
                                                <i class="fas fa-<?php echo $is_active ? 'pause' : 'play'; ?>"></i>
                                                <?php echo $toggle_text; ?>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?delete=<?php echo $row['id']; ?>" class="btn-delete-sm" onclick="return confirm('Delete this offer?')">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                    <?php
                        }
                    } else {
                        echo '<tr><td colspan="' . (($has_is_active ? 1 : 0) + ($has_valid_from && $has_valid_to ? 1 : 0) + 4) . '"><div class="no-data">
                                <i class="fas fa-tags"></i>
                                <p>No offers found. Create your first offer above!</p>
                              </div></td></tr>';
                    }
                    ?>
                </tbody>
            </table>

            <!-- Table Footer -->
            <div class="table-footer">
                <div class="total-items">
                    <i class="fas fa-list-ul"></i> Total Offers: <strong id="totalCount">0</strong>
                </div>
                <div style="font-size:13px; color:#555;">
                    <i class="fas fa-arrow-up"></i> Latest offers shown first
                </div>
            </div>
        </div>

    </div>

    <!-- ============================================================
    JAVASCRIPT - Search & Filter, Hamburger
    ============================================================ -->
    <script>
        // ============================================================
        // 1. SEARCH & FILTER
        // ============================================================
        function filterTable() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter')?.value || 'all';
            const rows = document.querySelectorAll('#offersTable tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                if (row.querySelector('.no-data')) return;

                const title = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                const desc = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
                const rowStatus = row.getAttribute('data-status') || '';

                const matchSearch = title.includes(input) || desc.includes(input);
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

        document.addEventListener('DOMContentLoaded', filterTable);

        // ============================================================
        // 2. HAMBURGER MENU TOGGLE (Mobile)
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
    </script>

</body>
</html>