<?php
// ============================================================
// ADD MENU ITEM - Admin Panel
// ============================================================
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_item'])) {
    // Sanitize inputs
    $item_name = mysqli_real_escape_string($conn, trim($_POST['item_name']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $price = (float)$_POST['price'];
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    
    // ============================================================
    // IMAGE HANDLING: Upload from PC OR Use URL
    // ============================================================
    $image_url = '';
    $errors = [];
    
    // Check if a file was uploaded
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['image_file']['tmp_name'];
        $file_name = basename($_FILES['image_file']['name']);
        $file_size = $_FILES['image_file']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file_ext, $allowed_exts)) {
            $errors[] = 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP, SVG';
        } elseif ($file_size > $max_size) {
            $errors[] = 'File size too large. Maximum is 5MB.';
        } else {
            $new_file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file_name);
            $upload_path = __DIR__ . '/../assets/' . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                $image_url = 'assets/' . $new_file_name;
            } else {
                $errors[] = 'Failed to upload image. Please check folder permissions.';
            }
        }
    } elseif (!empty($_POST['image_url'])) {
        $image_url = mysqli_real_escape_string($conn, trim($_POST['image_url']));
    } else {
        $errors[] = 'Please upload an image or provide an image URL.';
    }
    
    if (empty($item_name)) {
        $errors[] = 'Item name is required.';
    }
    if ($price <= 0) {
        $errors[] = 'Price must be greater than 0.';
    }
    if (empty($category)) {
        $errors[] = 'Please select a category.';
    }
    
    if (empty($errors)) {
        $check_sql = "SELECT * FROM menu WHERE item_name = '$item_name'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $message = '❌ An item with this name already exists!';
            $message_type = 'error';
        } else {
            $sql = "INSERT INTO menu (item_name, description, price, category, image_url) 
                    VALUES ('$item_name', '$description', $price, '$category', '$image_url')";
            
            if (mysqli_query($conn, $sql)) {
                $message = '✅ Menu item added successfully!';
                $message_type = 'success';
                $_POST = array();
                $_FILES = array();
            } else {
                $message = '❌ Error: ' . mysqli_error($conn);
                $message_type = 'error';
            }
        }
    } else {
        $message = '❌ ' . implode('<br>', $errors);
        $message_type = 'error';
    }
}

$total_items = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM menu"))['c'] ?? 0;

function safeHtml($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Menu Item | Gourmet Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ============================================================
                   CSS VARIABLES & RESET
                   ============================================================ */
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
            --shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
            --radius: 16px;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --sidebar-width: 260px;
            --sidebar-width-mobile: 60px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
            line-height: 1.6;
        }

        /* ============================================================
                   SIDEBAR (Premium)
                   ============================================================ */
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            padding: 40px 20px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease, width 0.3s ease;
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 10px; }

        .sidebar h2 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(--gold);
            letter-spacing: 2px;
            margin-bottom: 40px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 20px;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            gap: 14px;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 12px 18px;
            margin-bottom: 6px;
            border-radius: 10px;
            transition: var(--transition);
            font-weight: 400;
            font-size: 14px;
            position: relative;
        }
        .sidebar a i {
            width: 22px;
            font-size: 18px;
            text-align: center;
            color: var(--text-muted);
            transition: var(--transition);
        }
        .sidebar a:hover, .sidebar a.active {
            background: rgba(30, 69, 56, 0.4);
            color: var(--gold);
        }
        .sidebar a:hover i, .sidebar a.active i {
            color: var(--gold);
        }
        .sidebar a.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 20%;
            height: 60%;
            width: 3px;
            background: var(--gold);
            border-radius: 0 4px 4px 0;
        }
        .sidebar a.logout {
            color: #e74c3c;
            margin-top: 30px;
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
        }
        .sidebar a.logout i { color: #e74c3c; }
        .sidebar a.logout:hover { background: #e74c3c; color: #fff; }
        .sidebar a.logout:hover i { color: #fff; }

        /* ============================================================
                   MAIN CONTENT - FULL SCREEN
                   ============================================================ */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px 40px 50px;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            transition: margin-left 0.3s ease, padding 0.3s ease, width 0.3s ease;
            background: var(--bg-body);
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
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        .page-header .title-group {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .page-header h1 i { color: var(--gold); }
        .page-header h1 span { color: var(--gold); }
        .page-header h1 small {
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 400;
            margin-left: 8px;
        }

        .stat-badge {
            background: var(--bg-card);
            padding: 6px 18px;
            border-radius: 50px;
            border: 1px solid var(--border-color);
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            flex-shrink: 0;
        }
        .stat-badge i { color: var(--gold); }
        .stat-badge .num { color: #fff; font-weight: 700; }

        /* ============================================================
                   MESSAGES
                   ============================================================ */
        .msg-success, .msg-error {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid transparent;
            animation: slideDown 0.4s ease forwards;
            width: 100%;
        }
        .msg-success {
            background: rgba(46, 204, 113, 0.08);
            border-color: #2ecc71;
            color: #2ecc71;
        }
        .msg-error {
            background: rgba(231, 76, 60, 0.08);
            border-color: #e74c3c;
            color: #e74c3c;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ============================================================
                   FORM CARD - FULL SCREEN
                   ============================================================ */
        .form-card {
            background: var(--bg-card);
            padding: 30px 35px 35px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 100%;
            transition: var(--transition);
        }
        .form-card:hover {
            border-color: rgba(212, 175, 55, 0.15);
        }
        .form-card .form-title {
            color: var(--gold);
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
        }
        .form-card .form-title i {
            font-size: 22px;
        }
        .form-card .form-title .badge {
            font-size: 11px;
            background: rgba(212, 175, 55, 0.1);
            padding: 2px 12px;
            border-radius: 20px;
            color: var(--gold);
            font-weight: 500;
            margin-left: auto;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 30px;
        }
        .form-grid .full-width {
            grid-column: 1 / -1;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .form-group label {
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
            letter-spacing: 0.3px;
        }
        .form-group label i {
            color: var(--gold);
            margin-right: 6px;
            width: 16px;
        }
        .form-group label .required {
            color: #e74c3c;
            margin-left: 3px;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 12px 16px;
            background: var(--bg-input);
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: var(--transition);
            width: 100%;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.06);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .form-group select option {
            background: #111;
            color: #fff;
        }
        .form-group .hint {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 3px;
            opacity: 0.7;
        }
        .form-group .hint i {
            color: var(--gold);
            margin-right: 4px;
        }

        /* ============================================================
                   UPLOAD AREA (Premium)
                   ============================================================ */
        .upload-area {
            background: rgba(0, 0, 0, 0.3);
            border: 2px dashed #2a2a2a;
            border-radius: 12px;
            padding: 25px 20px;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
        }
        .upload-area:hover {
            border-color: var(--gold);
            background: rgba(212, 175, 55, 0.03);
        }
        .upload-area .upload-icon {
            font-size: 42px;
            color: #444;
            display: block;
            margin-bottom: 8px;
            transition: var(--transition);
        }
        .upload-area:hover .upload-icon {
            color: var(--gold);
        }
        .upload-area .upload-text {
            color: var(--text-muted);
            font-size: 14px;
        }
        .upload-area .upload-text strong {
            color: var(--gold);
            font-weight: 600;
        }
        .upload-area input[type="file"] {
            display: none;
        }
        .upload-area .file-name {
            color: var(--text-muted);
            font-size: 13px;
            margin-top: 6px;
        }
        .upload-area .file-name i {
            color: var(--gold);
            margin-right: 4px;
        }
        .upload-area .file-name .selected {
            color: #2ecc71;
        }
        .upload-area .file-name .selected i {
            color: #2ecc71;
        }

        .divider-text {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #444;
            font-size: 12px;
            margin: 4px 0;
        }
        .divider-text::before,
        .divider-text::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #2a2a2a;
        }

        /* ============================================================
                   IMAGE PREVIEW
                   ============================================================ */
        .image-preview-container {
            grid-column: 1 / -1;
            margin-top: 2px;
        }
        .image-preview-container .preview-box {
            background: rgba(0, 0, 0, 0.3);
            border: 2px dashed #2a2a2a;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        .image-preview-container .preview-box.has-image {
            border-color: var(--gold);
            border-style: solid;
            border-width: 2px;
        }
        .image-preview-container .preview-box img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 10px;
            object-fit: contain;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        .image-preview-container .preview-box .placeholder {
            color: var(--text-muted);
            font-size: 14px;
        }
        .image-preview-container .preview-box .placeholder i {
            font-size: 36px;
            display: block;
            margin-bottom: 8px;
            color: #333;
        }

        /* ============================================================
                   FORM ACTIONS
                   ============================================================ */
        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 14px;
            margin-top: 15px;
            flex-wrap: wrap;
            padding-top: 18px;
            border-top: 1px solid var(--border-color);
        }
        .btn-submit {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: #0a1914;
            padding: 13px 40px;
            border: none;
            border-radius: 50px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            justify-content: center;
            min-width: 150px;
            box-shadow: 0 4px 20px rgba(212, 175, 55, 0.2);
        }
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(212, 175, 55, 0.35);
            background: #fff;
        }
        .btn-submit:active {
            transform: translateY(0);
        }
        .btn-submit i {
            font-size: 16px;
        }

        .btn-cancel {
            background: transparent;
            color: var(--text-muted);
            padding: 13px 28px;
            border: 1px solid #333;
            border-radius: 50px;
            font-weight: 500;
            font-size: 15px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            min-width: 120px;
            justify-content: center;
        }
        .btn-cancel:hover {
            border-color: var(--gold);
            color: var(--gold);
        }
        .btn-cancel i {
            font-size: 14px;
        }

        /* ============================================================
                   FOOTER NOTE
                   ============================================================ */
        .footer-note {
            margin-top: 30px;
            text-align: center;
            color: #444;
            font-size: 13px;
            border-top: 1px solid var(--border-color);
            padding-top: 18px;
        }
        .footer-note i {
            color: var(--gold);
        }

        /* ============================================================
                   HAMBURGER (Mobile)
                   ============================================================ */
        .hamburger {
            display: none;
            background: transparent;
            border: none;
            color: var(--gold);
            font-size: 28px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 10px;
            transition: var(--transition);
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
        }
        .hamburger:hover { background: rgba(212, 175, 55, 0.1); }

        /* ============================================================
                   RESPONSIVE - Full Screen with Device Support
                   ============================================================ */

        /* ===== LARGE DESKTOP ===== */
        @media (min-width: 1400px) {
            .main-content {
                padding: 35px 50px 60px;
            }
            .form-grid {
                gap: 24px 35px;
            }
            .form-card {
                padding: 35px 45px 40px;
            }
        }

        /* ===== DESKTOP ===== */
        @media (max-width: 1200px) {
            .main-content {
                padding: 30px 35px 50px;
            }
            .form-card {
                padding: 28px 30px 32px;
            }
            .form-grid {
                gap: 18px 25px;
            }
        }

        /* ===== TABLET ===== */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 25px 25px 40px;
            }
            .hamburger {
                display: flex !important;
            }
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            .page-header .title-group {
                width: 100%;
            }
            .page-header h1 small {
                display: block;
                margin-left: 0;
                margin-top: 4px;
                font-size: 13px;
            }
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }
            .stat-badge {
                align-self: flex-start;
            }
            .form-card {
                padding: 25px 25px 30px;
            }
        }

        /* ===== SMALL TABLET / LARGE MOBILE ===== */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px 18px 35px;
            }
            .form-card {
                padding: 22px 20px 25px;
                border-radius: 14px;
            }
            .form-card .form-title {
                font-size: 18px;
                margin-bottom: 18px;
                padding-bottom: 12px;
            }
            .form-card .form-title .badge {
                font-size: 10px;
                padding: 2px 10px;
            }
            .form-grid {
                grid-template-columns: 1fr 1fr;
                gap: 14px 16px;
            }
            .form-group input,
            .form-group textarea,
            .form-group select {
                padding: 11px 14px;
                font-size: 13px;
            }
            .form-group label {
                font-size: 12px;
            }
            .upload-area {
                padding: 20px 15px;
            }
            .upload-area .upload-icon {
                font-size: 34px;
            }
            .upload-area .upload-text {
                font-size: 13px;
            }
            .upload-area .file-name {
                font-size: 12px;
            }
            .image-preview-container .preview-box {
                padding: 15px;
                min-height: 100px;
            }
            .image-preview-container .preview-box img {
                max-height: 150px;
            }
            .image-preview-container .preview-box .placeholder {
                font-size: 13px;
            }
            .image-preview-container .preview-box .placeholder i {
                font-size: 30px;
            }
            .btn-submit,
            .btn-cancel {
                padding: 12px 24px;
                font-size: 14px;
                min-width: 120px;
            }
            .page-header h1 {
                font-size: 26px;
            }
            .page-header h1 small {
                font-size: 12px;
            }
            .stat-badge {
                font-size: 12px;
                padding: 4px 14px;
            }
            .footer-note {
                font-size: 12px;
                margin-top: 25px;
                padding-top: 15px;
            }
        }

        /* ===== MOBILE ===== */
        @media (max-width: 600px) {
            .main-content {
                padding: 15px 12px 25px;
            }
            .form-card {
                padding: 18px 14px 22px;
                border-radius: 12px;
            }
            .form-card .form-title {
                font-size: 16px;
                margin-bottom: 14px;
                padding-bottom: 10px;
            }
            .form-card .form-title .badge {
                font-size: 9px;
                padding: 1px 8px;
                margin-left: auto;
            }
            .form-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .form-grid .full-width {
                grid-column: 1;
            }
            .form-group input,
            .form-group textarea,
            .form-group select {
                padding: 10px 12px;
                font-size: 13px;
                border-radius: 8px;
            }
            .form-group textarea {
                min-height: 60px;
            }
            .form-group label {
                font-size: 12px;
            }
            .form-group .hint {
                font-size: 10px;
            }
            .upload-area {
                padding: 16px 12px;
                border-radius: 10px;
            }
            .upload-area .upload-icon {
                font-size: 28px;
            }
            .upload-area .upload-text {
                font-size: 12px;
            }
            .upload-area .file-name {
                font-size: 11px;
            }
            .image-preview-container .preview-box {
                padding: 12px;
                min-height: 80px;
                border-radius: 10px;
            }
            .image-preview-container .preview-box img {
                max-height: 120px;
            }
            .image-preview-container .preview-box .placeholder {
                font-size: 12px;
            }
            .image-preview-container .preview-box .placeholder i {
                font-size: 24px;
            }
            .divider-text {
                font-size: 11px;
                gap: 10px;
                margin: 2px 0;
            }
            .form-actions {
                flex-direction: column;
                gap: 10px;
                padding-top: 14px;
                margin-top: 10px;
            }
            .btn-submit,
            .btn-cancel {
                width: 100%;
                justify-content: center;
                padding: 14px;
                font-size: 14px;
                min-width: unset;
                border-radius: 10px;
            }
            .btn-submit i,
            .btn-cancel i {
                font-size: 15px;
            }
            .page-header {
                gap: 12px;
                margin-bottom: 18px;
                padding-bottom: 12px;
            }
            .page-header .title-group {
                gap: 10px;
                width: 100%;
            }
            .page-header h1 {
                font-size: 22px;
                gap: 8px;
            }
            .page-header h1 small {
                font-size: 11px;
                margin-top: 2px;
                display: block;
                width: 100%;
                margin-left: 0;
            }
            .stat-badge {
                font-size: 11px;
                padding: 4px 12px;
                border-radius: 30px;
            }
            .footer-note {
                font-size: 11px;
                margin-top: 18px;
                padding-top: 12px;
            }
            .msg-success, .msg-error {
                padding: 10px 14px;
                font-size: 13px;
                border-radius: 10px;
            }
        }

        /* ===== VERY SMALL MOBILE ===== */
        @media (max-width: 480px) {
            .sidebar {
                width: var(--sidebar-width-mobile);
                padding: 15px 10px;
            }
            .sidebar h2 {
                display: none;
            }
            .sidebar a {
                padding: 10px;
                justify-content: center;
                font-size: 0;
            }
            .sidebar a span {
                display: none;
            }
            .sidebar a i {
                width: auto;
                font-size: 20px;
                margin: 0;
                color: var(--text-secondary);
            }
            .sidebar a.active::before {
                left: 0;
                top: 10%;
                height: 80%;
                width: 2px;
            }
            .sidebar a.logout {
                padding: 10px;
                margin-top: 20px;
                border-top-width: 1px;
            }
            .sidebar a.logout i {
                color: #e74c3c;
            }

            /* When sidebar is open on very small screens */
            .sidebar.open {
                width: 260px;
            }
            .sidebar.open a {
                font-size: 13px;
                justify-content: flex-start;
                padding: 10px 14px;
            }
            .sidebar.open a span {
                display: inline;
            }
            .sidebar.open a i {
                width: 22px;
                font-size: 17px;
            }
            .sidebar.open h2 {
                display: block;
                font-size: 24px;
                margin-bottom: 30px;
                padding-bottom: 15px;
            }

            .main-content {
                padding: 55px 10px 20px;
            }
            .form-card {
                padding: 14px 10px 18px;
                border-radius: 10px;
            }
            .form-card .form-title {
                font-size: 14px;
                gap: 8px;
                margin-bottom: 12px;
                padding-bottom: 8px;
            }
            .form-card .form-title i {
                font-size: 16px;
            }
            .form-grid {
                gap: 10px;
            }
            .form-group input,
            .form-group textarea,
            .form-group select {
                padding: 9px 10px;
                font-size: 12px;
                border-radius: 6px;
            }
            .form-group textarea {
                min-height: 50px;
            }
            .upload-area {
                padding: 12px 10px;
                border-radius: 8px;
            }
            .upload-area .upload-icon {
                font-size: 22px;
                margin-bottom: 4px;
            }
            .upload-area .upload-text {
                font-size: 11px;
            }
            .upload-area .file-name {
                font-size: 10px;
                margin-top: 4px;
            }
            .image-preview-container .preview-box {
                padding: 10px;
                min-height: 60px;
            }
            .image-preview-container .preview-box img {
                max-height: 80px;
            }
            .image-preview-container .preview-box .placeholder i {
                font-size: 20px;
            }
            .image-preview-container .preview-box .placeholder {
                font-size: 11px;
            }
            .divider-text {
                font-size: 10px;
                gap: 8px;
            }
            .btn-submit,
            .btn-cancel {
                padding: 12px;
                font-size: 13px;
                border-radius: 8px;
            }
            .page-header h1 {
                font-size: 18px;
            }
            .page-header h1 i {
                font-size: 16px;
            }
            .stat-badge {
                font-size: 10px;
                padding: 3px 10px;
                gap: 4px;
            }
            .footer-note {
                font-size: 10px;
                margin-top: 15px;
                padding-top: 10px;
            }
            .msg-success, .msg-error {
                padding: 8px 12px;
                font-size: 12px;
                border-radius: 8px;
                gap: 8px;
            }
            .hamburger {
                top: 10px;
                left: 10px;
                font-size: 22px;
                padding: 6px 10px;
            }
        }
    </style>
</head>
<body>

    <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>

    <!-- ============================================================
    SIDEBAR
    ============================================================ -->
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

    <!-- ============================================================
    MAIN CONTENT - FULL SCREEN
    ============================================================ -->
    <div class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <div class="title-group">
                <h1>
                    <i class="fas fa-plus-circle"></i>
                    <span>Add</span> New Item
                    <small>Add a delicious dish to your menu</small>
                </h1>
            </div>
            <div class="stat-badge">
                <i class="fas fa-utensils"></i>
                Total Items: <span class="num"><?php echo number_format($total_items); ?></span>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="<?php echo $message_type == 'success' ? 'msg-success' : 'msg-error'; ?>">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Add Form - Full Screen -->
        <div class="form-card">
            <div class="form-title">
                <i class="fas fa-edit"></i>
                Menu Item Details
                <span class="badge"><i class="fas fa-plus-circle"></i> New</span>
            </div>

            <form method="POST" action="" id="menuForm" enctype="multipart/form-data">
                <div class="form-grid">
                    <!-- Item Name -->
                    <div class="form-group full-width">
                        <label><i class="fas fa-tag"></i> Item Name <span class="required">*</span></label>
                        <input type="text" name="item_name" placeholder="e.g. Chicken Biryani" 
                               value="<?php echo isset($_POST['item_name']) ? safeHtml($_POST['item_name']) : ''; ?>" required>
                    </div>

                    <!-- Description -->
                    <div class="form-group full-width">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" placeholder="Describe the dish in detail..."><?php echo isset($_POST['description']) ? safeHtml($_POST['description']) : ''; ?></textarea>
                        <div class="hint">
                            <i class="fas fa-info-circle"></i>
                            Provide a brief, enticing description of the dish.
                        </div>
                    </div>

                    <!-- Price -->
                    <div class="form-group">
                        <label><i class="fas fa-money-bill-wave"></i> Price (LKR) <span class="required">*</span></label>
                        <input type="number" name="price" step="0.01" placeholder="1200.00" 
                               value="<?php echo isset($_POST['price']) ? safeHtml($_POST['price']) : ''; ?>" required>
                    </div>

                    <!-- Category -->
                    <div class="form-group">
                        <label><i class="fas fa-layer-group"></i> Category <span class="required">*</span></label>
                        <select name="category" required>
                            <option value="">— Select Category —</option>
                            <option value="Standard" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Standard') ? 'selected' : ''; ?>>⭐ Standard</option>
                            <option value="Premium" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Premium') ? 'selected' : ''; ?>>🔥 Premium</option>
                            <option value="Luxury" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Luxury') ? 'selected' : ''; ?>>💎 Luxury</option>
                        </select>
                        <div class="hint">
                            <i class="fas fa-info-circle"></i>
                            Choose the appropriate category for this dish.
                        </div>
                    </div>

                    <!-- Image Upload from PC -->
                    <div class="form-group full-width">
                        <label><i class="fas fa-upload"></i> Upload from PC</label>
                        <div class="upload-area" id="uploadArea" onclick="document.getElementById('imageFile').click();">
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <span class="upload-text">
                                <strong>Click</strong> to choose an image from your computer
                            </span>
                            <div class="file-name">
                                <i class="fas fa-file-image"></i>
                                <span id="fileNameDisplay">No file selected</span>
                            </div>
                            <input type="file" name="image_file" id="imageFile" accept="image/*" onchange="handleFileSelect(event)">
                            <div class="hint" style="margin-top:10px;">
                                <i class="fas fa-info-circle"></i>
                                Supported: JPG, PNG, GIF, WEBP, SVG (Max 5MB)
                            </div>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="form-group full-width">
                        <div class="divider-text">OR</div>
                    </div>

                    <!-- Image URL -->
                    <div class="form-group full-width">
                        <label><i class="fas fa-link"></i> Image URL (Internet)</label>
                        <input type="text" name="image_url" id="imageUrl" 
                               placeholder="https://example.com/image.jpg or assets/filename.jpg" 
                               value="<?php echo isset($_POST['image_url']) ? safeHtml($_POST['image_url']) : ''; ?>"
                               oninput="previewFromURL()">
                        <div class="hint">
                            <i class="fas fa-info-circle"></i>
                            Paste a direct image URL from the internet, or leave empty if uploading from PC.
                        </div>
                    </div>

                    <!-- Image Preview -->
                    <div class="image-preview-container">
                        <div class="preview-box" id="previewBox">
                            <div class="placeholder" id="placeholderText">
                                <i class="fas fa-image"></i>
                                Image preview will appear here
                            </div>
                            <img id="previewImage" style="display:none; max-width:100%; max-height:200px; border-radius:10px;" alt="Preview">
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" name="add_item" class="btn-submit">
                            <i class="fas fa-save"></i> Add Item
                        </button>
                        <a href="admin_menu.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="footer-note">
            <i class="fas fa-crown"></i>
            Gourmet Restaurant Management System v2.0
            <span style="color:#333; margin: 0 10px;">|</span>
            <i class="fas fa-utensils"></i>
            Add new dishes to your menu
        </div>

    </div>

    <!-- ============================================================
    JAVASCRIPT
    ============================================================ -->
    <script>
        // ============================================================
        // 1. HANDLE FILE SELECT (PC Upload)
        // ============================================================
        function handleFileSelect(event) {
            const file = event.target.files[0];
            const fileNameDisplay = document.getElementById('fileNameDisplay');
            const previewImg = document.getElementById('previewImage');
            const placeholder = document.getElementById('placeholderText');
            const previewBox = document.getElementById('previewBox');

            if (file) {
                fileNameDisplay.innerHTML = '<span class="selected"><i class="fas fa-check-circle"></i> ' + file.name + '</span> (' + (file.size / 1024).toFixed(1) + ' KB)';
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.style.display = 'block';
                    previewImg.src = e.target.result;
                    placeholder.style.display = 'none';
                    previewBox.classList.add('has-image');
                    document.getElementById('imageUrl').value = '';
                };
                reader.readAsDataURL(file);
            } else {
                fileNameDisplay.textContent = 'No file selected';
                previewImg.style.display = 'none';
                placeholder.style.display = 'block';
                placeholder.innerHTML = '<i class="fas fa-image"></i> Image preview will appear here';
                previewBox.classList.remove('has-image');
            }
        }

        // ============================================================
        // 2. PREVIEW FROM URL (Internet)
        // ============================================================
        function previewFromURL() {
            const url = document.getElementById('imageUrl').value.trim();
            const previewImg = document.getElementById('previewImage');
            const placeholder = document.getElementById('placeholderText');
            const previewBox = document.getElementById('previewBox');

            if (!url) {
                const fileInput = document.getElementById('imageFile');
                if (!fileInput.files || fileInput.files.length === 0) {
                    previewImg.style.display = 'none';
                    placeholder.style.display = 'block';
                    placeholder.innerHTML = '<i class="fas fa-image"></i> Image preview will appear here';
                    previewBox.classList.remove('has-image');
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
        // 3. HAMBURGER MENU TOGGLE
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
        // 4. DOUBLE SUBMIT PREVENTION
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('menuForm');
            if (form) {
                form.addEventListener('submit', function() {
                    const submitBtn = form.querySelector('.btn-submit');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                    setTimeout(function() {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-save"></i> Add Item';
                    }, 5000);
                });
            }
        });

        // ============================================================
        // 5. CONSOLE
        // ============================================================
        console.log('BASE_URL: <?php echo BASE_URL ?? ''; ?>');
        console.log('✨ Gourmet Admin - Add Menu Item');
    </script>

</body>
</html>