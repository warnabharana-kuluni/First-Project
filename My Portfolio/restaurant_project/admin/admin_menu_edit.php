<?php
// ============================================================
// EDIT MENU ITEM - Admin Panel (Dropdown + Auto-fill)
// ============================================================
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';

// ============================================================
// CSRF Token - Session start with check
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ============================================================
// AJAX: GET ITEM DETAILS (for dropdown auto-fill)
// ============================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_item' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM menu WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
    }
    mysqli_stmt_close($stmt);
    exit();
}

// ============================================================
// GET ALL MENU ITEMS FOR DROPDOWN
// ============================================================
$all_items = [];
$result = mysqli_query($conn, "SELECT id, item_name FROM menu ORDER BY item_name");
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $all_items[] = $row;
    }
}

// ============================================================
// GET ITEM TO EDIT
// ============================================================
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$item = null;

if ($edit_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM menu WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $edit_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) > 0) {
        $item = mysqli_fetch_assoc($result);
    }
    mysqli_stmt_close($stmt);
}

// If no item found and no edit id, load first item
if (!$item && !empty($all_items)) {
    $edit_id = $all_items[0]['id'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM menu WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $edit_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) > 0) {
        $item = mysqli_fetch_assoc($result);
    }
    mysqli_stmt_close($stmt);
}

if (!$item) {
    header("Location: admin_menu.php");
    exit();
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$message = '';
$message_type = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_item'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = '❌ Invalid CSRF token. Please try again.';
        $message_type = 'error';
    } else {
        $edit_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        $item_name = trim($_POST['item_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
        $category = trim($_POST['category'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');

        // ----- VALIDATION -----
        if (empty($item_name)) $errors[] = 'Item name is required.';
        if ($price <= 0) $errors[] = 'Price must be greater than 0.';
        if (empty($category)) $errors[] = 'Please select a category.';

        // ----- FILE UPLOAD -----
        if (empty($errors) && isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['image_file']['tmp_name'];
            $file_name = basename($_FILES['image_file']['name']);
            $file_size = $_FILES['image_file']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            $max_size = 5 * 1024 * 1024;

            if (!in_array($file_ext, $allowed_exts)) {
                $errors[] = 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP, SVG';
            } elseif ($file_size > $max_size) {
                $errors[] = 'File size too large. Maximum is 5MB.';
            } else {
                $new_file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file_name);
                $upload_path = __DIR__ . '/../assets/' . $new_file_name;
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    if (!empty($item['image_url']) && $item['image_url'] !== 'assets/default.jpg') {
                        $old_path = __DIR__ . '/../' . $item['image_url'];
                        if (strpos($item['image_url'], 'http') !== 0 && file_exists($old_path)) {
                            unlink($old_path);
                        }
                    }
                    $image_url = 'assets/' . $new_file_name;
                } else {
                    $errors[] = 'Failed to upload image.';
                }
            }
        } elseif (!empty($_POST['image_url'])) {
            $new_url = trim($_POST['image_url']);
            if (filter_var($new_url, FILTER_VALIDATE_URL)) {
                if (!empty($item['image_url']) && $item['image_url'] !== 'assets/default.jpg') {
                    $old_path = __DIR__ . '/../' . $item['image_url'];
                    if (strpos($item['image_url'], 'http') !== 0 && file_exists($old_path)) {
                        unlink($old_path);
                    }
                }
                $image_url = $new_url;
            } else {
                $errors[] = 'Invalid image URL.';
            }
        }

        // ----- DUPLICATE CHECK -----
        if (empty($errors) && $edit_id > 0) {
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM menu WHERE item_name = ? AND id != ?");
            mysqli_stmt_bind_param($check_stmt, 'si', $item_name, $edit_id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $errors[] = 'Another item with this name already exists!';
            }
            mysqli_stmt_close($check_stmt);
        }

        // ----- UPDATE -----
        if (empty($errors) && $edit_id > 0) {
            $update_sql = "UPDATE menu SET 
                            item_name = ?,
                            description = ?,
                            price = ?,
                            category = ?,
                            image_url = ?
                        WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, 'ssdssi', 
                $item_name, $description, $price, $category, $image_url, $edit_id
            );
            if (mysqli_stmt_execute($update_stmt)) {
                $_SESSION['flash_message'] = '✅ Menu item updated successfully!';
                $_SESSION['flash_type'] = 'success';
                header("Location: admin_menu_edit.php?edit=" . $edit_id);
                exit();
            } else {
                $errors[] = 'Database error: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($update_stmt);
        } elseif (empty($errors)) {
            $errors[] = 'Invalid item selected.';
        }

        if (!empty($errors)) {
            $message = '❌ ' . implode('<br>', $errors);
            $message_type = 'error';
            // Reload item data to show current values
            $stmt = mysqli_prepare($conn, "SELECT * FROM menu WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $edit_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result && mysqli_num_rows($result) > 0) {
                $item = mysqli_fetch_assoc($result);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Flash messages
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Total items count
$total_items = 0;
$count_result = mysqli_query($conn, "SELECT COUNT(*) as c FROM menu");
if ($count_result) {
    $total_items = (int)mysqli_fetch_assoc($count_result)['c'];
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
    <title>Edit Menu Item | Gourmet Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #050e0c; color: #fff; display: flex; min-height: 100vh; }

        .sidebar {
            width: 260px; min-height: 100vh; background: #0a1914; border-right: 2px solid #1e4538;
            padding: 40px 20px; position: fixed; top: 0; left: 0; height: 100%; overflow-y: auto; z-index: 1000; transition: transform 0.3s ease;
        }
        .sidebar h2 { font-family: 'Playfair Display', serif; font-size: 28px; color: #D4AF37; letter-spacing: 2px; margin-bottom: 40px; text-align: center; border-bottom: 1px solid #1e4538; padding-bottom: 20px; }
        .sidebar a { display: flex; align-items: center; gap: 14px; color: #b0c4b1; text-decoration: none; padding: 12px 18px; margin-bottom: 6px; border-radius: 10px; transition: 0.3s; font-weight: 400; font-size: 14px; position: relative; }
        .sidebar a i { width: 22px; font-size: 18px; text-align: center; color: #888; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background: rgba(30,69,56,0.4); color: #D4AF37; }
        .sidebar a:hover i, .sidebar a.active i { color: #D4AF37; }
        .sidebar a.active::before { content: ''; position: absolute; left: 0; top: 20%; height: 60%; width: 3px; background: #D4AF37; border-radius: 0 4px 4px 0; }
        .sidebar a.logout { color: #e74c3c; margin-top: 30px; border-top: 1px solid #1e4538; padding-top: 20px; }
        .sidebar a.logout:hover { background: #e74c3c; color: #fff; }

        .main-content { margin-left: 300px; padding: 40px 50px 60px; width: calc(100% - 300px); min-height: 100vh; transition: margin-left 0.3s ease; }

        .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #1e4538; }
        .page-header h1 { font-family: 'Playfair Display', serif; font-size: 32px; color: #fff; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #D4AF37; }
        .page-header h1 span { color: #D4AF37; }
        .stat-badge { background: #0f241c; padding: 6px 16px; border-radius: 50px; border: 1px solid #1e4538; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; color: #b0c4b1; }
        .stat-badge i { color: #D4AF37; }
        .stat-badge .num { color: #fff; font-weight: 700; }

        .msg-success, .msg-error { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 12px; border-left: 4px solid transparent; }
        .msg-success { background: rgba(46,204,113,0.08); border-color: #2ecc71; color: #2ecc71; }
        .msg-error { background: rgba(231,76,60,0.08); border-color: #e74c3c; color: #e74c3c; }

        .form-card { background: #0f241c; padding: 35px 35px 40px; border-radius: 16px; border: 1px solid #1e4538; box-shadow: 0 15px 40px rgba(0,0,0,0.6); max-width: 750px; }
        .form-card .form-title { color: #D4AF37; font-size: 20px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 25px; }
        .form-grid .full-width { grid-column: 1 / -1; }
        .form-group { display: flex; flex-direction: column; gap: 4px; }
        .form-group label { color: #b0c4b1; font-size: 13px; font-weight: 500; }
        .form-group label i { color: #D4AF37; margin-right: 6px; }
        .form-group label .required { color: #e74c3c; margin-left: 4px; }
        .form-group input, .form-group textarea, .form-group select {
            padding: 11px 14px; background: #111; border: 1px solid #2a2a2a; border-radius: 10px;
            color: #fff; font-family: 'Poppins', sans-serif; font-size: 14px; transition: 0.3s; width: 100%;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            border-color: #D4AF37; outline: none; box-shadow: 0 0 0 4px rgba(212,175,55,0.08);
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group .hint { font-size: 11px; color: #555; margin-top: 4px; }

        .upload-area { background: #0a0a0a; border: 2px dashed #2a2a2a; border-radius: 12px; padding: 20px; text-align: center; transition: 0.3s; cursor: pointer; }
        .upload-area:hover { border-color: #D4AF37; }
        .upload-area .upload-icon { font-size: 40px; color: #444; display: block; margin-bottom: 8px; }
        .upload-area input[type="file"] { display: none; }
        .upload-area .file-name { color: #888; font-size: 13px; margin-top: 5px; }
        .upload-area .file-name .selected { color: #2ecc71; }

        .divider-text { display: flex; align-items: center; gap: 15px; color: #555; font-size: 13px; margin: 5px 0; }
        .divider-text::before, .divider-text::after { content: ''; flex: 1; height: 1px; background: #2a2a2a; }

        .image-preview-container { grid-column: 1 / -1; margin-top: 5px; }
        .image-preview-container .preview-box { background: #0a0a0a; border: 2px dashed #2a2a2a; border-radius: 12px; padding: 20px; text-align: center; min-height: 120px; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: 0.3s; }
        .image-preview-container .preview-box.has-image { border-color: #D4AF37; border-style: solid; }
        .image-preview-container .preview-box img { max-width: 100%; max-height: 180px; border-radius: 8px; object-fit: contain; }
        .image-preview-container .preview-box .placeholder { color: #555; font-size: 14px; }
        .image-preview-container .preview-box .placeholder i { font-size: 36px; display: block; margin-bottom: 8px; color: #333; }

        .form-actions { grid-column: 1 / -1; display: flex; gap: 12px; margin-top: 10px; flex-wrap: wrap; }
        .btn-submit {
            background: linear-gradient(135deg, #D4AF37, #b8962e); color: #0a1914; padding: 13px 35px;
            border: none; border-radius: 50px; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.3s;
            display: inline-flex; align-items: center; gap: 10px; flex: 1; justify-content: center; min-width: 140px;
            box-shadow: 0 4px 15px rgba(212,175,55,0.25);
        }
        .btn-submit:hover { background: #fff; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(212,175,55,0.4); }
        .btn-cancel {
            background: transparent; color: #888; padding: 13px 25px; border: 1px solid #2a2a2a;
            border-radius: 50px; font-weight: 500; font-size: 15px; cursor: pointer; transition: 0.3s;
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none; min-width: 120px; justify-content: center;
        }
        .btn-cancel:hover { border-color: #D4AF37; color: #D4AF37; }

        .hamburger { display: none; background: transparent; border: none; color: #D4AF37; font-size: 28px; cursor: pointer; padding: 8px 12px; border-radius: 10px; transition: 0.3s; position: fixed; top: 15px; left: 15px; z-index: 1001; }
        .hamburger:hover { background: rgba(212,175,55,0.1); }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; padding: 30px 25px 50px; }
            .hamburger { display: flex !important; }
            .page-header { flex-direction: column; align-items: stretch; }
            .form-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .main-content { padding: 20px 15px 40px; }
            .form-card { padding: 25px 18px 30px; }
            .form-actions { flex-direction: column; }
            .btn-submit, .btn-cancel { width: 100%; justify-content: center; }
            .page-header h1 { font-size: 26px; }
        }
        @media (max-width: 480px) {
            .sidebar { width: 60px; padding: 15px 10px; }
            .sidebar h2 { display: none; }
            .sidebar a { padding: 10px; justify-content: center; }
            .sidebar a span { display: none; }
            .sidebar a i { width: auto; font-size: 20px; margin: 0; }
            .main-content { padding: 60px 12px 30px; }
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
            <h1><i class="fas fa-edit"></i> <span>Edit</span> Menu Item</h1>
            <span class="stat-badge"><i class="fas fa-utensils"></i> Total Items: <span class="num"><?php echo number_format($total_items); ?></span></span>
        </div>

        <?php if ($message): ?>
            <div class="<?php echo $message_type == 'success' ? 'msg-success' : 'msg-error'; ?>">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <div class="form-title"><i class="fas fa-pen"></i> Editing: <span id="editingName"><?php echo safeHtml($item['item_name']); ?></span></div>

            <form method="POST" action="" id="menuForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="item_id" id="itemId" value="<?php echo $item['id']; ?>">

                <div class="form-grid">
                    <!-- 🔥 Item Name as Dropdown -->
                    <div class="form-group full-width">
                        <label><i class="fas fa-list"></i> Select Menu Item <span class="required">*</span></label>
                        <select name="item_name_select" id="itemNameSelect" required>
                            <option value="">— Select an Item —</option>
                            <?php foreach ($all_items as $opt): ?>
                                <option value="<?php echo $opt['id']; ?>" <?php echo ($opt['id'] == $item['id']) ? 'selected' : ''; ?>>
                                    <?php echo safeHtml($opt['item_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="hint"><i class="fas fa-arrow-up"></i> Select an item to edit its details</div>
                    </div>

                    <!-- Hidden field to store item name for form submission -->
                    <input type="hidden" name="item_name" id="itemNameHidden" value="<?php echo safeHtml($item['item_name']); ?>">

                    <div class="form-group full-width">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" id="itemDescription" placeholder="Describe the dish in detail..."><?php echo safeHtml($item['description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-money-bill-wave"></i> Price (LKR) <span class="required">*</span></label>
                        <input type="number" name="price" id="itemPrice" step="0.01" placeholder="1200.00" 
                               value="<?php echo isset($item['price']) ? $item['price'] : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-layer-group"></i> Category <span class="required">*</span></label>
                        <select name="category" id="itemCategory" required>
                            <option value="">— Select Category —</option>
                            <option value="Standard" <?php echo (isset($item['category']) && $item['category'] == 'Standard') ? 'selected' : ''; ?>>⭐ Standard</option>
                            <option value="Premium" <?php echo (isset($item['category']) && $item['category'] == 'Premium') ? 'selected' : ''; ?>>🔥 Premium</option>
                            <option value="Luxury" <?php echo (isset($item['category']) && $item['category'] == 'Luxury') ? 'selected' : ''; ?>>💎 Luxury</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label><i class="fas fa-upload"></i> Upload New Image (optional)</label>
                        <div class="upload-area" id="uploadArea" onclick="document.getElementById('imageFile').click();">
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <span style="color:#888; font-size:14px;">Click to choose new image</span>
                            <div class="file-name"><i class="fas fa-file-image"></i> <span id="fileNameDisplay">Current: <?php echo isset($item['image_url']) ? basename($item['image_url']) : 'No image'; ?></span></div>
                            <input type="file" name="image_file" id="imageFile" accept="image/*" onchange="handleFileSelect(event)">
                            <div class="hint" style="margin-top:8px;"><i class="fas fa-info-circle"></i> Supported: JPG, PNG, GIF, WEBP, SVG (Max 5MB)</div>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <div class="divider-text">OR</div>
                    </div>

                    <div class="form-group full-width">
                        <label><i class="fas fa-link"></i> Image URL (Internet)</label>
                        <input type="text" name="image_url" id="imageUrl" 
                               placeholder="https://example.com/image.jpg" 
                               value="<?php echo isset($item['image_url']) ? safeHtml($item['image_url']) : ''; ?>"
                               oninput="previewFromURL()">
                        <div class="hint"><i class="fas fa-info-circle"></i> Enter a new URL to change the image, or leave as is.</div>
                    </div>

                    <div class="image-preview-container">
                        <div class="preview-box" id="previewBox">
                            <img id="previewImage" style="display:block; max-width:100%; max-height:180px; border-radius:8px; object-fit:contain;" 
                                 src="<?php echo isset($item['image_url']) ? safeHtml($item['image_url']) : ''; ?>" alt="Current Image">
                            <div class="placeholder" id="placeholderText" style="display:none;"><i class="fas fa-image"></i> Image preview will appear here</div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_item" class="btn-submit"><i class="fas fa-save"></i> Update Item</button>
                        <a href="admin_menu.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </div>
            </form>
        </div>

        <div style="margin-top: 30px; text-align: center; color: #444; font-size: 13px; border-top: 1px solid #1e4538; padding-top: 20px;">
            <i class="fas fa-crown" style="color:#D4AF37;"></i> Gourmet Restaurant Management System v2.0
        </div>

    </div>

    <script>
        // ============================================================
        // DROPDOWN CHANGE - Auto fill form fields
        // ============================================================
        document.getElementById('itemNameSelect').addEventListener('change', function() {
            const id = this.value;
            if (!id) {
                document.getElementById('itemId').value = '';
                document.getElementById('itemNameHidden').value = '';
                document.getElementById('editingName').textContent = '— Select an Item —';
                document.getElementById('itemDescription').value = '';
                document.getElementById('itemPrice').value = '';
                document.getElementById('itemCategory').value = '';
                document.getElementById('imageUrl').value = '';
                document.getElementById('previewImage').src = '';
                document.getElementById('fileNameDisplay').textContent = 'No image selected';
                return;
            }

            const select = this;
            select.disabled = true;
            select.style.opacity = '0.6';

            fetch('admin_menu_edit.php?action=get_item&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const item = data.data;
                        document.getElementById('itemId').value = item.id;
                        document.getElementById('itemNameHidden').value = item.item_name;
                        document.getElementById('editingName').textContent = item.item_name;
                        document.getElementById('itemDescription').value = item.description || '';
                        document.getElementById('itemPrice').value = item.price || '';
                        document.getElementById('itemCategory').value = item.category || '';

                        const previewImg = document.getElementById('previewImage');
                        const placeholder = document.getElementById('placeholderText');
                        const previewBox = document.getElementById('previewBox');
                        if (item.image_url) {
                            previewImg.src = item.image_url;
                            previewImg.style.display = 'block';
                            placeholder.style.display = 'none';
                            previewBox.classList.add('has-image');
                            document.getElementById('imageUrl').value = item.image_url || '';
                        } else {
                            previewImg.style.display = 'none';
                            placeholder.style.display = 'block';
                            placeholder.innerHTML = '<i class="fas fa-image"></i> No image available';
                            previewBox.classList.remove('has-image');
                            document.getElementById('imageUrl').value = '';
                        }

                        document.getElementById('fileNameDisplay').textContent = 'Current: ' + (item.image_url ? item.image_url.split('/').pop() : 'No image');
                        document.title = 'Edit Menu Item | Gourmet Admin - ' + item.item_name;
                    } else {
                        alert('Error: ' + (data.message || 'Could not load item details'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Something went wrong. Please try again.');
                })
                .finally(() => {
                    select.disabled = false;
                    select.style.opacity = '1';
                });
        });

        // ============================================================
        // FILE UPLOAD HANDLER
        // ============================================================
        function handleFileSelect(event) {
            const file = event.target.files[0];
            const fileNameDisplay = document.getElementById('fileNameDisplay');
            const previewImg = document.getElementById('previewImage');
            const placeholder = document.getElementById('placeholderText');
            const previewBox = document.getElementById('previewBox');

            if (file) {
                fileNameDisplay.innerHTML = '<span class="selected">' + file.name + '</span> (' + (file.size / 1024).toFixed(1) + ' KB)';
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.style.display = 'block';
                    previewImg.src = e.target.result;
                    placeholder.style.display = 'none';
                    previewBox.classList.add('has-image');
                    document.getElementById('imageUrl').value = '';
                };
                reader.readAsDataURL(file);
            }
        }

        // ============================================================
        // IMAGE URL PREVIEW
        // ============================================================
        function previewFromURL() {
            const url = document.getElementById('imageUrl').value.trim();
            const previewImg = document.getElementById('previewImage');
            const placeholder = document.getElementById('placeholderText');
            const previewBox = document.getElementById('previewBox');

            if (!url) {
                const currentId = document.getElementById('itemId').value;
                if (currentId) {
                    const existingSrc = previewImg.src;
                    if (existingSrc && !existingSrc.includes('blob:')) {
                        previewImg.style.display = 'block';
                        previewImg.src = existingSrc;
                        placeholder.style.display = 'none';
                        previewBox.classList.add('has-image');
                    }
                }
                return;
            }

            previewImg.style.display = 'block';
            previewImg.src = url;
            placeholder.style.display = 'none';
            previewBox.classList.add('has-image');

            previewImg.onerror = function() {
                previewImg.style.display = 'none';
                placeholder.style.display = 'block';
                placeholder.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#e74c3c;"></i> Invalid image URL';
                previewBox.classList.remove('has-image');
            };
        }

        // ============================================================
        // HAMBURGER MENU
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
        // DISABLE DOUBLE SUBMIT
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('menuForm');
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('.btn-submit');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                setTimeout(function() {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Item';
                }, 5000);
            });
        });
    </script>

</body>
</html>