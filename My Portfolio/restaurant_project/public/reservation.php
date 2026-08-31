
<?php
// ============================================================
// RESERVATION PAGE - AJAX Submission + Table Selection
// ============================================================

session_start();

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
if (!defined('BASE_URL')) { define('BASE_URL', getBaseUrl()); }

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/table_functions.php";
require_once __DIR__ . "/../includes/loyalty.php";

// ============================================================
// LOGIN CHECK
// ============================================================
if (!isset($_SESSION['customer_id']) || empty($_SESSION['customer_id'])) {
    $_SESSION['redirect_after_login'] = BASE_URL . 'public/reservation.php';
    header("Location: " . BASE_URL . "public/login.php?redirect=reservation");
    exit();
}

$customer_id = (int)$_SESSION['customer_id'];

// ============================================================
// CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ============================================================
// AJAX HANDLER (අලුතින් try-catch එකක් සහිතව)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] == '1') {
    header('Content-Type: application/json');
    try {
        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Security validation failed.');
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $time = trim($_POST['time'] ?? '');
        $guests = isset($_POST['guests']) ? (int)$_POST['guests'] : 1;
        $table_id = isset($_POST['table_id']) ? (int)$_POST['table_id'] : null;

        $errors = [];

        // Validation
        if (empty($name) || strlen($name) < 2) {
            $errors[] = 'Please enter your full name (minimum 2 characters).';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (empty($phone) || !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number.';
        }
        if (empty($date)) {
            $errors[] = 'Please select a reservation date.';
        } elseif (strtotime($date) < strtotime('today')) {
            $errors[] = 'Reservation date must be today or a future date.';
        }
        if (empty($time) || !preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            $errors[] = 'Please select a valid time (HH:MM format).';
        }
        if ($guests < 1 || $guests > 20) {
            $errors[] = 'Guests must be between 1 and 20.';
        }

        if (!$table_id) {
            $errors[] = 'Please select a table from the floor plan.';
        } else {
            // Check table functions exist
            if (!function_exists('getTableById')) {
                throw new Exception('Function getTableById not found. Check table_functions.php.');
            }
            $table = getTableById($conn, $table_id);
            if (!$table) {
                $errors[] = 'Selected table does not exist.';
            } elseif (!isTableAvailable($conn, $table_id, $date, $time)) {
                $errors[] = 'This table is already booked for the selected date and time. Please choose another table.';
            } elseif ($table['capacity'] < $guests) {
                $errors[] = 'Selected table can only accommodate ' . $table['capacity'] . ' guests. Please choose a larger table.';
            }
        }

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit();
        }

        // Calculate price
        $price = 500;
        if ($guests >= 4 && $guests <= 7) $price = 1000;
        else if ($guests >= 8) $price = 1500;
        $total_amount = $price;

        // Store in session
        $_SESSION['res_name'] = $name;
        $_SESSION['res_email'] = $email;
        $_SESSION['res_phone'] = $phone;
        $_SESSION['res_guests'] = $guests;
        $_SESSION['res_date'] = $date . ' ' . $time;
        $_SESSION['res_total'] = $total_amount;
        $_SESSION['reservation_complete'] = true;
        $_SESSION['selected_table_id'] = $table_id;
        unset($_SESSION['reservation_errors']);
        unset($_SESSION['reservation_form_data']);

        $status = 'pending';
        $dishes = isset($_POST['selected_dishes']) ? mysqli_real_escape_string($conn, $_POST['selected_dishes']) : 'None';
        $custom_dish = isset($_POST['custom_dish']) ? mysqli_real_escape_string($conn, $_POST['custom_dish']) : '';

        // Insert query
        $insert_query = "INSERT INTO reservations 
                         (name, email, phone, reservation_date, reservation_time, guests, table_id, selected_dishes, custom_dish, total_amount, payment_method, status, created_at)
                         VALUES (
                             '" . mysqli_real_escape_string($conn, $name) . "',
                             '" . mysqli_real_escape_string($conn, $email) . "',
                             '" . mysqli_real_escape_string($conn, $phone) . "',
                             '" . mysqli_real_escape_string($conn, $date) . "',
                             '" . mysqli_real_escape_string($conn, $time) . "',
                             $guests,
                             " . ($table_id ? $table_id : 'NULL') . ",
                             '" . mysqli_real_escape_string($conn, $dishes) . "',
                             '" . mysqli_real_escape_string($conn, $custom_dish) . "',
                             $total_amount,
                             'Not specified',
                             '$status',
                             NOW()
                         )";

        if (!mysqli_query($conn, $insert_query)) {
            throw new Exception('Database insert error: ' . mysqli_error($conn));
        }

        $res_id = mysqli_insert_id($conn);

        // Loyalty points - with safety check
        if ($customer_id > 0) {
            $loyalty_file = __DIR__ . "/../includes/loyalty.php";
            if (file_exists($loyalty_file)) {
                require_once $loyalty_file;
                if (function_exists('addLoyaltyPoints')) {
                    $points_added = addLoyaltyPoints($conn, $customer_id, $res_id, $total_amount);
                    error_log("Loyalty Points added: " . ($points_added ? 'Yes' : 'No') . " for customer $customer_id, reservation $res_id");
                } else {
                    error_log("Warning: addLoyaltyPoints function not found.");
                }
            } else {
                error_log("Warning: loyalty.php file not found.");
            }
        }

        echo json_encode([
            'success' => true,
            'redirect' => BASE_URL . 'public/menu.php'
        ]);
        exit();

    } catch (Exception $e) {
        // Catch any PHP error and return as JSON
        error_log("Reservation AJAX Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'errors' => ['Server error: ' . $e->getMessage()]]);
        exit();
    }
}

// ============================================================
// GET AVAILABLE TABLES FOR INITIAL DISPLAY
// ============================================================
$selected_date = isset($_SESSION['res_date']) ? date('Y-m-d', strtotime($_SESSION['res_date'])) : date('Y-m-d');
$selected_time = isset($_SESSION['res_date']) ? date('H:i', strtotime($_SESSION['res_date'])) : date('H:i');

// Get all tables with availability status
$tables_data = getTablesWithStatus($conn, $selected_date, $selected_time);

// Group tables by category
$tables_by_category = [];
foreach ($tables_data as $table) {
    $category = $table['category'];
    if (!isset($tables_by_category[$category])) {
        $tables_by_category[$category] = [];
    }
    $tables_by_category[$category][] = $table;
}

// ============================================================
// RESTORE FORM DATA
// ============================================================
$error = '';
$form_data = [];
if (isset($_SESSION['reservation_errors'])) {
    $error = implode('<br>', $_SESSION['reservation_errors']);
    unset($_SESSION['reservation_errors']);
}
if (isset($_SESSION['reservation_form_data'])) {
    $form_data = $_SESSION['reservation_form_data'];
    unset($_SESSION['reservation_form_data']);
}
if (empty($form_data)) {
    $form_data['name'] = isset($_SESSION['res_name']) ? $_SESSION['res_name'] : '';
    $form_data['email'] = isset($_SESSION['res_email']) ? $_SESSION['res_email'] : '';
    $form_data['phone'] = isset($_SESSION['res_phone']) ? $_SESSION['res_phone'] : '';
    $form_data['guests'] = isset($_SESSION['res_guests']) ? $_SESSION['res_guests'] : 1;
    if (isset($_SESSION['res_date']) && !empty($_SESSION['res_date'])) {
        $form_data['date'] = date('Y-m-d', strtotime($_SESSION['res_date']));
        $form_data['time'] = date('H:i', strtotime($_SESSION['res_date']));
    } else {
        $form_data['date'] = '';
        $form_data['time'] = '';
    }
}

$initial_guests = $form_data['guests'] ?? 1;
$initial_price = 500;
if ($initial_guests >= 4 && $initial_guests <= 7) $initial_price = 1000;
else if ($initial_guests >= 8) $initial_price = 1500;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation | Gourmet Fine Dining</title>
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
            flex-direction: column;
            align-items: center;
            padding: 0;
            background-image: radial-gradient(circle at 20% 50%, rgba(212, 175, 55, 0.05) 0%, transparent 60%);
        }

        nav {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 50px;
            background: #0a1914;
            border-bottom: 2px solid #1e4538;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .logo { font-size: 26px; font-weight: 700; color: #D4AF37; letter-spacing: 2px; }
        nav ul { display: flex; list-style: none; gap: 25px; flex-wrap: wrap; align-items: center; }
        nav ul li a { text-decoration: none; color: #b0c4b1; font-weight: 500; transition: 0.3s; font-size: 14px; position: relative; }
        nav ul li a::after { content: ''; position: absolute; bottom: -4px; left: 0; width: 0; height: 2px; background: #D4AF37; transition: width 0.3s ease; }
        nav ul li a:hover::after, nav ul li a.active::after { width: 100%; }
        nav ul li a:hover, nav ul li a.active { color: #D4AF37; }
        nav ul li a i { margin-right: 4px; }

        .reservation-container {
            width: 100%;
            max-width: 1200px;
            padding: 30px 20px 60px;
            animation: fadeInUp 0.8s ease forwards;
        }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        .reservation-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        /* ============================================================
                   LEFT: FLOOR PLAN
                   ============================================================ */
        .floor-plan-card {
            background: #0a1914;
            padding: 25px 20px 30px;
            border-radius: 20px;
            border: 1px solid #1e4538;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
            position: relative;
            overflow: hidden;
        }
        .floor-plan-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #D4AF37, transparent);
        }
        .floor-plan-card .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: #D4AF37;
            text-align: center;
            margin-bottom: 15px;
        }
        .floor-plan-card .section-title i {
            margin-right: 8px;
        }

        .floor-plan-wrapper {
            overflow-x: auto;
            padding: 10px 5px;
        }

        .floor-plan {
            position: relative;
            min-width: 1000px;
            min-height: 750px;
            background: #0d1f18;
            border-radius: 16px;
            border: 2px solid #1e4538;
            padding: 15px;
            margin: 0 auto;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(212,175,55,0.03) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(212,175,55,0.03) 0%, transparent 50%);
        }

        .floor-plan .restaurant-outline {
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 2px dashed rgba(212, 175, 55, 0.10);
            border-radius: 12px;
            pointer-events: none;
        }

        /* Category Labels */
        .category-label {
            position: absolute;
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.08);
            text-transform: uppercase;
            letter-spacing: 4px;
            pointer-events: none;
            z-index: 0;
        }

        .floor-plan-legend {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 18px 30px;
            margin-top: 15px;
            padding: 12px 20px;
            background: #0d0d0d;
            border-radius: 10px;
            border: 1px solid #1e4538;
        }
        .floor-plan-legend .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #b0c4b1;
        }
        .floor-plan-legend .legend-item .dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid transparent;
        }
        .floor-plan-legend .legend-item .dot.available { background: #2ecc71; border-color: #1a8a4a; }
        .floor-plan-legend .legend-item .dot.booked { background: #e74c3c; border-color: #a93226; }
        .floor-plan-legend .legend-item .dot.selected { background: #D4AF37; border-color: #b8962e; box-shadow: 0 0 20px rgba(212, 175, 55, 0.5); }

        /* ============================================================
                   TABLE ITEMS
                   ============================================================ */
        .table-item {
            position: absolute;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 12px;
            font-weight: 600;
            z-index: 2;
            border: 3px solid transparent;
            user-select: none;
            padding: 4px;
        }
        .table-item .table-label {
            font-size: 11px;
            font-weight: 700;
            text-align: center;
            line-height: 1.1;
        }
        .table-item .table-capacity {
            font-size: 9px;
            opacity: 0.7;
            margin-top: 1px;
        }
        .table-item.available {
            background: rgba(46, 204, 113, 0.18);
            border-color: #2ecc71;
            color: #2ecc71;
        }
        .table-item.available:hover {
            transform: scale(1.10);
            box-shadow: 0 0 40px rgba(46, 204, 113, 0.3);
            z-index: 5;
        }
        .table-item.booked {
            background: rgba(231, 76, 60, 0.18);
            border-color: #e74c3c;
            color: #e74c3c;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .table-item.selected {
            background: rgba(212, 175, 55, 0.25);
            border-color: #D4AF37;
            color: #D4AF37;
            transform: scale(1.08);
            box-shadow: 0 0 50px rgba(212, 175, 55, 0.3);
            z-index: 10;
        }
        .table-item.selected .table-label {
            color: #D4AF37;
        }
        .table-item .category-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            font-size: 8px;
            padding: 2px 7px;
            border-radius: 10px;
            background: rgba(0,0,0,0.85);
            border: 1px solid rgba(255,255,255,0.08);
            white-space: nowrap;
        }
        .table-item.booked .category-badge { display: none; }

        /* Category specific styles */
        .table-item.window { border-style: solid; }
        .table-item.couple { border-style: dashed; border-width: 2px; }
        .table-item.vip { border-width: 4px; }
        .table-item.vip .table-label { font-size: 14px; }
        .table-item.vip .table-capacity { font-size: 11px; }
        .table-item.outdoor { border-style: dotted; }

        /* ============================================================
                   RIGHT: FORM CARD
                   ============================================================ */
        .reservation-card {
            background: #0a1914;
            padding: 30px 30px 35px;
            border-radius: 20px;
            border: 1px solid #1e4538;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
            position: relative;
            overflow: hidden;
        }
        .reservation-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #D4AF37, transparent);
        }

        .card-header { text-align: center; margin-bottom: 25px; }
        .card-header .icon { font-size: 40px; color: #D4AF37; margin-bottom: 4px; }
        .card-header h2 { font-family: 'Playfair Display', serif; font-size: 28px; color: #D4AF37; letter-spacing: 1px; }
        .card-header p { color: #888; font-size: 14px; margin-top: 2px; }

        .selected-table-display {
            background: rgba(212, 175, 55, 0.05);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 10px;
            padding: 10px 15px;
            margin-bottom: 18px;
            text-align: center;
            display: none;
        }
        .selected-table-display.show { display: block; }
        .selected-table-display .table-info {
            color: #D4AF37;
            font-weight: 600;
            font-size: 14px;
        }
        .selected-table-display .table-info i { margin-right: 6px; }
        .selected-table-display .table-info small {
            color: #888;
            font-weight: 400;
        }

        .msg-error {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-weight: 500;
            font-size: 14px;
            display: none;
            align-items: flex-start;
            gap: 10px;
            background: rgba(231, 76, 60, 0.12);
            border: 1px solid rgba(231, 76, 60, 0.25);
            color: #e74c3c;
            flex-wrap: wrap;
        }
        .msg-error.show { display: flex; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; color: #aaa; font-size: 13px; font-weight: 500; margin-bottom: 4px; }
        .form-group label i { margin-right: 6px; color: #D4AF37; }
        .form-group label .required { color: #e74c3c; margin-left: 4px; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 14px;
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            color: #fff;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #D4AF37;
            outline: none;
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.08);
        }
        .form-group input::placeholder { color: #555; }
        .form-group select option { background: #111; color: #fff; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }

        .price-preview {
            background: rgba(212, 175, 55, 0.05);
            border: 1px solid rgba(212, 175, 55, 0.15);
            border-radius: 10px;
            padding: 12px 16px;
            margin: 5px 0 18px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }
        .price-preview .label { color: #888; font-size: 13px; }
        .price-preview .label i { color: #D4AF37; margin-right: 4px; }
        .price-preview .amount { font-size: 20px; font-weight: 700; color: #D4AF37; }
        .price-preview .hint { font-size: 11px; color: #555; width: 100%; text-align: center; border-top: 1px solid rgba(30,69,56,0.3); padding-top: 8px; margin-top: 4px; }

        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #D4AF37, #b8962e);
            color: #0a1914;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 5px;
        }
        .btn-primary:hover { background: #fff; transform: translateY(-3px); box-shadow: 0 10px 30px rgba(212,175,55,0.3); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .secure-badge { text-align: center; margin-top: 12px; font-size: 12px; color: #555; }
        .secure-badge i { color: #D4AF37; }

        /* ============================================================
                   RESPONSIVE
                   ============================================================ */
        @media (max-width: 1100px) {
            .reservation-container { max-width: 100%; }
        }

        @media (max-width: 992px) {
            .reservation-layout { grid-template-columns: 1fr; }
            .floor-plan { min-width: 750px; min-height: 550px; }
            .reservation-container { padding: 20px 15px 40px; }
        }

        @media (max-width: 768px) {
            .reservation-card { padding: 25px 18px 25px; }
            .floor-plan-card { padding: 18px 12px 20px; }
            .floor-plan { min-width: 600px; min-height: 450px; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .card-header h2 { font-size: 24px; }
            nav { padding: 12px 20px; }
            nav ul { gap: 12px; }
            nav ul li a { font-size: 12px; }
            .table-item .table-label { font-size: 9px; }
            .table-item .table-capacity { font-size: 8px; }
            .table-item.vip .table-label { font-size: 11px; }
        }

        @media (max-width: 480px) {
            .floor-plan { min-width: 450px; min-height: 350px; }
            .card-header .icon { font-size: 32px; }
            .card-header h2 { font-size: 20px; }
            .price-preview .amount { font-size: 17px; }
            .btn-primary { font-size: 14px; padding: 12px; }
            .table-item .table-label { font-size: 8px; }
            .table-item .table-capacity { font-size: 7px; }
            .table-item { padding: 2px; }
            .floor-plan-legend .legend-item { font-size: 11px; }
            .floor-plan-legend .legend-item .dot { width: 16px; height: 16px; }
        }
    </style>
</head>
<body>

<nav>
    <div class="logo">GOURMET</div>
    <ul>
        <li><a href="<?php echo BASE_URL; ?>public/index.php">Home</a></li>
        <li><a href="<?php echo BASE_URL; ?>public/about.php">About</a></li>
        <li><a href="<?php echo BASE_URL; ?>public/contact.php">Contact</a></li>
        <li><a href="<?php echo BASE_URL; ?>public/reservation.php" class="active">Reservation</a></li>
        <li><a href="<?php echo BASE_URL; ?>public/menu.php">Menu</a></li>
        <li><a href="<?php echo BASE_URL; ?>public/offers.php">Offers</a></li>
        <li><a href="<?php echo BASE_URL; ?>public/rate.php"><i class="fas fa-star" style="color:#D4AF37;"></i> Rate Us</a></li>
        <li><a href="<?php echo BASE_URL; ?>public/notifications.php">Notifications</a></li>
        <li><a href="<?php echo BASE_URL; ?>public/profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
    </ul>
</nav>

<div class="reservation-container">

    <div class="reservation-layout">

        <!-- LEFT: FLOOR PLAN -->
        <div class="floor-plan-card">
            <div class="section-title">
                <i class="fas fa-chair"></i> Choose Your Table
            </div>

            <div style="display:flex; gap:10px; margin-bottom:12px; flex-wrap:wrap;">
                <div style="flex:1; min-width:120px;">
                    <input type="date" id="planDate" value="<?php echo $selected_date; ?>" 
                           style="width:100%; padding:8px 10px; background:#111; border:1px solid #2a2a2a; border-radius:8px; color:#fff; font-size:13px;">
                </div>
                <div style="flex:1; min-width:100px;">
                    <input type="time" id="planTime" value="<?php echo $selected_time; ?>" 
                           style="width:100%; padding:8px 10px; background:#111; border:1px solid #2a2a2a; border-radius:8px; color:#fff; font-size:13px;">
                </div>
                <button id="refreshPlanBtn" style="padding:8px 18px; background:#D4AF37; color:#0a1914; border:none; border-radius:8px; font-weight:600; cursor:pointer;">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>

            <div class="floor-plan-wrapper">
                <div class="floor-plan" id="floorPlan">
                    <div class="restaurant-outline"></div>
                </div>
            </div>

            <div class="floor-plan-legend">
                <span class="legend-item"><span class="dot available"></span> Available</span>
                <span class="legend-item"><span class="dot booked"></span> Booked</span>
                <span class="legend-item"><span class="dot selected"></span> Selected</span>
                <span class="legend-item" style="color:#888; font-size:12px;">
                    <i class="fas fa-people-group"></i> Click a green table to select
                </span>
            </div>
        </div>

        <!-- RIGHT: FORM -->
        <div class="reservation-card">

            <div class="card-header">
                <div class="icon"><i class="fas fa-calendar-check"></i></div>
                <h2>Book Your Table</h2>
                <p>Select a table from the floor plan and fill your details</p>
            </div>

            <div class="selected-table-display" id="selectedTableDisplay">
                <span class="table-info" id="selectedTableInfo">
                    <i class="fas fa-check-circle"></i> <span id="selectedTableName">No table selected</span>
                    <small id="selectedTableCapacity"></small>
                </span>
            </div>

            <div class="msg-error" id="errorContainer">
                <i class="fas fa-exclamation-circle"></i>
                <span id="errorMessage"></span>
            </div>

            <form id="reservationForm" method="POST" action="<?php echo BASE_URL; ?>public/reservation.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="table_id" id="selectedTableId" value="">

                <div class="form-group">
                    <label><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                    <input type="text" name="name" id="resName" placeholder="John Doe" 
                           value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label>
                    <input type="email" name="email" id="resEmail" placeholder="john@example.com" 
                           value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone Number <span class="required">*</span></label>
                    <input type="text" name="phone" id="resPhone" placeholder="0771234567" 
                           value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Date <span class="required">*</span></label>
                        <input type="date" name="date" id="resDate" 
                               value="<?php echo htmlspecialchars($form_data['date'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> Time <span class="required">*</span></label>
                        <input type="time" name="time" id="resTime" 
                               value="<?php echo htmlspecialchars($form_data['time'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-users"></i> Number of Guests <span class="required">*</span></label>
                    <select name="guests" id="resGuests" required onchange="updatePricePreview(this.value)">
                        <?php for ($i = 1; $i <= 20; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo (isset($form_data['guests']) && $form_data['guests'] == $i) ? 'selected' : ''; ?>>
                                <?php echo $i; ?> Guest<?php echo $i > 1 ? 's' : ''; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="price-preview">
                    <span class="label"><i class="fas fa-chair"></i> Table Price</span>
                    <span class="amount" id="priceAmount">LKR <?php echo number_format($initial_price, 2); ?></span>
                    <div class="hint">
                        <i class="fas fa-info-circle"></i> Price based on guests (1-3: 500, 4-7: 1000, 8+: 1500)
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="btn-primary">
                    <i class="fas fa-utensils"></i> Continue to Menu
                </button>
            </form>

            <div class="secure-badge">
                <i class="fas fa-lock"></i> Your information is secure
            </div>

        </div>

    </div>

</div>

<!-- ============================================================
JAVASCRIPT
============================================================ -->
<script>
    // ============================================================
    // 1. TABLE DATA FROM PHP
    // ============================================================
    const tableData = <?php echo json_encode($tables_data); ?>;
    const categories = <?php echo json_encode(array_keys($tables_by_category)); ?>;

    // ============================================================
    // 2. RENDER FLOOR PLAN
    // ============================================================
    function renderFloorPlan(tables) {
        const container = document.getElementById('floorPlan');
        const outline = container.querySelector('.restaurant-outline');
        container.innerHTML = '';
        if (outline) container.appendChild(outline);
        
        if (!tables || tables.length === 0) {
            container.innerHTML = '<div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); color:#555; font-size:16px;">No tables available</div>';
            return;
        }

        const containerWidth = container.clientWidth || 1000;
        const containerHeight = container.clientHeight || 750;
        const baseWidth = 750;
        const baseHeight = 550;
        const scaleX = (containerWidth - 30) / baseWidth;
        const scaleY = (containerHeight - 30) / baseHeight;
        const scale = Math.min(scaleX, scaleY, 1.0);

        // Category labels
        const labels = [
            { text: 'WINDOW', x: 350, y: 10 },
            { text: 'COUPLE', x: 150, y: 160 },
            { text: 'REGULAR', x: 500, y: 160 },
            { text: 'VIP', x: 550, y: 10 },
            { text: 'OUTDOOR', x: 100, y: 520 }
        ];
        labels.forEach(lb => {
            const el = document.createElement('div');
            el.className = 'category-label';
            el.style.left = (lb.x * scale) + 15 + 'px';
            el.style.top = (lb.y * scale) + 15 + 'px';
            el.textContent = lb.text;
            container.appendChild(el);
        });

        tables.forEach(table => {
            const div = document.createElement('div');
            div.className = `table-item ${table.category} ${table.is_booked ? 'booked' : 'available'}`;
            div.dataset.tableId = table.id;
            div.dataset.capacity = table.capacity;
            div.dataset.category = table.category;
            
            const left = (table.position_x * scale) + 15;
            const top = (table.position_y * scale) + 15;
            const width = Math.max(45, table.width * scale);
            const height = Math.max(45, table.height * scale);
            
            div.style.left = left + 'px';
            div.style.top = top + 'px';
            div.style.width = width + 'px';
            div.style.height = height + 'px';
            div.style.minWidth = '40px';
            div.style.minHeight = '40px';
            
            const label = document.createElement('span');
            label.className = 'table-label';
            label.textContent = table.table_name || table.table_number;
            div.appendChild(label);
            
            const cap = document.createElement('span');
            cap.className = 'table-capacity';
            cap.textContent = `👤 ${table.capacity}`;
            div.appendChild(cap);
            
            if (!table.is_booked) {
                const badge = document.createElement('span');
                badge.className = 'category-badge';
                const catMap = {
                    'window': 'Win',
                    'couple': 'Cpl',
                    'vip': 'VIP',
                    'regular': 'Reg',
                    'outdoor': 'Out'
                };
                badge.textContent = catMap[table.category] || table.category;
                div.appendChild(badge);
            }
            
            if (!table.is_booked) {
                div.addEventListener('click', function() {
                    selectTable(this.dataset.tableId);
                });
            }
            
            container.appendChild(div);
        });
        
        const newOutline = document.createElement('div');
        newOutline.className = 'restaurant-outline';
        container.appendChild(newOutline);
    }

    // ============================================================
    // 3. SELECT TABLE
    // ============================================================
    let selectedTableId = null;

    function selectTable(tableId) {
        document.querySelectorAll('.table-item.selected').forEach(el => {
            el.classList.remove('selected');
        });
        
        if (selectedTableId === tableId) {
            selectedTableId = null;
            document.getElementById('selectedTableId').value = '';
            document.getElementById('selectedTableDisplay').classList.remove('show');
            return;
        }
        
        selectedTableId = tableId;
        document.getElementById('selectedTableId').value = tableId;
        
        document.querySelectorAll('.table-item').forEach(el => {
            if (el.dataset.tableId == tableId) {
                el.classList.add('selected');
            }
        });
        
        const table = tableData.find(t => t.id == tableId);
        if (table) {
            const catMap = {
                'window': 'Window Side',
                'couple': 'Couple Table',
                'vip': 'VIP Lounge',
                'regular': 'Regular',
                'outdoor': 'Outdoor'
            };
            document.getElementById('selectedTableName').textContent = 
                `${table.table_name} (${table.table_number})`;
            document.getElementById('selectedTableCapacity').textContent = 
                `• ${table.capacity} guests • ${catMap[table.category] || table.category}`;
            document.getElementById('selectedTableDisplay').classList.add('show');
        }
    }

    // ============================================================
    // 4. UPDATE FLOOR PLAN
    // ============================================================
    function updateFloorPlan() {
        const date = document.getElementById('planDate').value;
        const time = document.getElementById('planTime').value;
        
        if (!date || !time) return;
        
        document.getElementById('resDate').value = date;
        document.getElementById('resTime').value = time;
        
        fetch('<?php echo BASE_URL; ?>public/get_tables.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `date=${date}&time=${time}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const newTables = data.tables;
                tableData.length = 0;
                newTables.forEach(t => tableData.push(t));
                renderFloorPlan(tableData);
                selectedTableId = null;
                document.getElementById('selectedTableId').value = '';
                document.getElementById('selectedTableDisplay').classList.remove('show');
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // ============================================================
    // 5. PRICE PREVIEW
    // ============================================================
    function updatePricePreview(guests) {
        guests = parseInt(guests);
        let price = 500;
        if (guests >= 4 && guests <= 7) price = 1000;
        else if (guests >= 8) price = 1500;
        document.getElementById('priceAmount').textContent = 'LKR ' + price.toFixed(2);
    }

    // ============================================================
    // 6. SET MINIMUM DATE
    // ============================================================
    document.addEventListener('DOMContentLoaded', function() {
        const dateInput = document.getElementById('resDate');
        if (dateInput) {
            const today = new Date().toISOString().split('T')[0];
            dateInput.setAttribute('min', today);
        }
        renderFloorPlan(tableData);
    });

    // ============================================================
    // 7. EVENT LISTENERS
    // ============================================================
    document.getElementById('refreshPlanBtn').addEventListener('click', updateFloorPlan);
    document.getElementById('planDate').addEventListener('change', updateFloorPlan);
    document.getElementById('planTime').addEventListener('change', updateFloorPlan);

    document.getElementById('resDate').addEventListener('change', function() {
        document.getElementById('planDate').value = this.value;
        updateFloorPlan();
    });
    document.getElementById('resTime').addEventListener('change', function() {
        document.getElementById('planTime').value = this.value;
        updateFloorPlan();
    });

    // ============================================================
    // 8. AJAX FORM SUBMISSION (අලුත් catch block එක සමඟ)
    // ============================================================
    document.getElementById('reservationForm').addEventListener('submit', function(e) {
        e.preventDefault();

        if (!document.getElementById('selectedTableId').value) {
            const errorContainer = document.getElementById('errorContainer');
            const errorMessage = document.getElementById('errorMessage');
            errorMessage.innerHTML = 'Please select a table from the floor plan.';
            errorContainer.classList.add('show');
            errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        const submitBtn = document.getElementById('submitBtn');
        const errorContainer = document.getElementById('errorContainer');
        const errorMessage = document.getElementById('errorMessage');

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        errorContainer.classList.remove('show');
        errorMessage.innerHTML = '';

        const formData = new FormData(this);

        fetch('<?php echo BASE_URL; ?>public/reservation.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = data.redirect;
            } else {
                errorMessage.innerHTML = data.errors.join('<br>');
                errorContainer.classList.add('show');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-utensils"></i> Continue to Menu';
                errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            // සිංහල දෝෂ පණිවිඩය සහිතව
            errorMessage.innerHTML = 'සේවාදායක දෝෂයක් ඇතිවිය. කරුණාකර නැවත උත්සාහ කරන්න. (Server error: ' + error.message + ')';
            errorContainer.classList.add('show');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-utensils"></i> Continue to Menu';
        });
    });

    console.log('BASE_URL: <?php echo BASE_URL; ?>');
    console.log('Tables loaded:', tableData.length);
</script>

</body>
</html>