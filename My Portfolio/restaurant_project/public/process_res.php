<?php
// ============================================================
// PROCESS RESERVATION - Professional Version
// ============================================================

// ============================================================
// 1. SESSION START
// ============================================================
session_start();

// ============================================================
// 2. AUTO-DETECT BASE URL
// ============================================================
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    $path = dirname(dirname($script_name));
    return $protocol . '://' . $host . $path . '/';
}

if (!defined('BASE_URL')) {
    define('BASE_URL', getBaseUrl());
}

// ============================================================
// 3. DATABASE CONNECTION
// ============================================================
require_once __DIR__ . "/../includes/db.php";

// ============================================================
// 4. CHECK REQUEST METHOD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "public/reservation.php?error=invalid_method");
    exit();
}

// ============================================================
// 5. CSRF TOKEN VALIDATION
// ============================================================
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
    $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header("Location: " . BASE_URL . "public/reservation.php?error=security");
    exit();
}

// ============================================================
// 6. GET AND VALIDATE INPUT
// ============================================================
$errors = [];

// Name - required, min 2 chars
$name = trim($_POST['name'] ?? '');
if (empty($name) || strlen($name) < 2) {
    $errors[] = 'Please enter your full name (minimum 2 characters).';
} elseif (strlen($name) > 100) {
    $errors[] = 'Name is too long (maximum 100 characters).';
}

// Email - required, valid format
$email = trim($_POST['email'] ?? '');
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
} elseif (strlen($email) > 100) {
    $errors[] = 'Email is too long (maximum 100 characters).';
}

// Phone - optional but validate if provided
$phone = trim($_POST['phone'] ?? '');
if (!empty($phone) && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
    $errors[] = 'Please enter a valid phone number.';
}

// Date - required, must be future date
$date = trim($_POST['date'] ?? '');
if (empty($date)) {
    $errors[] = 'Please select a reservation date.';
} else {
    $date_timestamp = strtotime($date);
    $today = strtotime('today');
    if ($date_timestamp < $today) {
        $errors[] = 'Reservation date must be today or a future date.';
    }
    // Check if date is too far in future (max 1 year)
    $max_date = strtotime('+1 year');
    if ($date_timestamp > $max_date) {
        $errors[] = 'Reservation date cannot be more than 1 year in advance.';
    }
}

// Time - required
$time = trim($_POST['time'] ?? '');
if (empty($time)) {
    $errors[] = 'Please select a reservation time.';
} else {
    // Validate time format (HH:MM)
    if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
        $errors[] = 'Please select a valid time format.';
    }
}

// Guests - required, between 1 and 20
$guests = isset($_POST['guests']) ? (int)$_POST['guests'] : 0;
if ($guests < 1) {
    $errors[] = 'Minimum 1 guest required.';
} elseif ($guests > 20) {
    $errors[] = 'Maximum 20 guests allowed. Please contact us for larger groups.';
}

// ============================================================
// 7. CHECK FOR DUPLICATE RESERVATIONS (Optional)
// ============================================================
if (empty($errors)) {
    $check_stmt = mysqli_prepare($conn, 
        "SELECT id FROM reservations WHERE email = ? AND reservation_date = ? AND reservation_time = ? AND status != 'cancelled'"
    );
    mysqli_stmt_bind_param($check_stmt, "sss", $email, $date, $time);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        $errors[] = 'You already have a reservation for this date and time.';
    }
    mysqli_stmt_close($check_stmt);
}

// ============================================================
// 8. IF ERRORS EXIST, REDIRECT BACK WITH MESSAGES
// ============================================================
if (!empty($errors)) {
    // Store errors in session to display on reservation page
    $_SESSION['reservation_errors'] = $errors;
    $_SESSION['reservation_form_data'] = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'date' => $date,
        'time' => $time,
        'guests' => $guests
    ];
    header("Location: " . BASE_URL . "public/reservation.php?error=validation");
    exit();
}

// ============================================================
// 9. INSERT RESERVATION INTO DATABASE
// ============================================================
$stmt = mysqli_prepare($conn, 
    "INSERT INTO reservations (name, email, phone, reservation_date, reservation_time, guests, status, created_at) 
     VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())"
);

mysqli_stmt_bind_param($stmt, "sssssi", $name, $email, $phone, $date, $time, $guests);

if (mysqli_stmt_execute($stmt)) {
    $reservation_id = mysqli_insert_id($conn);
    
    // Store reservation data in session
    $_SESSION['reservation_id'] = $reservation_id;
    $_SESSION['res_name'] = $name;
    $_SESSION['res_email'] = $email;
    $_SESSION['res_phone'] = $phone;
    $_SESSION['res_date'] = $date;
    $_SESSION['res_time'] = $time;
    $_SESSION['res_guests'] = $guests;
    
    // Clear any previous form errors
    unset($_SESSION['reservation_errors']);
    unset($_SESSION['reservation_form_data']);
    
    // ============================================================
    // 10. SEND CONFIRMATION EMAIL (Optional)
    // ============================================================
    $email_sent = false;
    if (!empty($email)) {
        $email_sent = sendConfirmationEmail($name, $email, $date, $time, $guests, $reservation_id);
    }
    
    // ============================================================
    // 11. REDIRECT TO SUCCESS PAGE
    // ============================================================
    $redirect_url = BASE_URL . "public/success.php?reservation=success&id=" . $reservation_id;
    if ($email_sent) {
        $redirect_url .= "&email=1";
    }
    header("Location: " . $redirect_url);
    exit();
    
} else {
    // Database error
    error_log("process_res.php - Database error: " . mysqli_error($conn));
    $_SESSION['reservation_errors'] = ['We encountered a technical issue. Please try again later.'];
    header("Location: " . BASE_URL . "public/reservation.php?error=db_error");
    exit();
}

mysqli_stmt_close($stmt);

// ============================================================
// 12. EMAIL FUNCTION
// ============================================================
function sendConfirmationEmail($name, $email, $date, $time, $guests, $reservation_id) {
    $subject = "Reservation Confirmation - Gourmet Restaurant";
    $base_url = defined('BASE_URL') ? BASE_URL : '/restaurant_project/';
    
    $message = "
    <html>
    <head><title>Reservation Confirmation</title></head>
    <body style='font-family: Arial, sans-serif; background-color: #050e0c; padding: 20px; color: #ffffff;'>
        <div style='max-width: 600px; margin: 0 auto; background: #0a1914; padding: 30px; border-radius: 15px; border: 1px solid #1e4538; text-align: center;'>
            <h2 style='color: #D4AF37; margin-bottom: 20px; font-family: \"Playfair Display\", serif;'>✨ GOURMET RESTAURANT</h2>
            <hr style='border: 0; border-top: 1px solid #1e4538; margin-bottom: 20px;'>
            
            <p style='font-size: 16px; color: #b0c4b1; text-align: left;'>Dear <strong>$name</strong>,</p>
            <p style='font-size: 15px; color: #b0c4b1; text-align: left; line-height: 1.6;'>
                Thank you for choosing Gourmet Restaurant. Your reservation has been confirmed!
            </p>
            
            <div style='margin: 25px 0; padding: 20px; background: #111; border-radius: 12px; border: 1px solid #1e4538; text-align: left;'>
                <p><strong style='color: #D4AF37;'>📅 Date:</strong> $date</p>
                <p><strong style='color: #D4AF37;'>⏰ Time:</strong> $time</p>
                <p><strong style='color: #D4AF37;'>👥 Guests:</strong> $guests</p>
                <p><strong style='color: #D4AF37;'>🔢 Reservation ID:</strong> #$reservation_id</p>
            </div>
            
            <p style='font-size: 14px; color: #b0c4b1; line-height: 1.6;'>
                Please arrive 10 minutes prior to your booking time. 
                We look forward to serving you!
            </p>
            
            <hr style='border: 0; border-top: 1px solid #1e4538; margin-top: 25px;'>
            <p style='font-size: 12px; color: #557a6e; margin-top: 15px;'>
                This is an automated email. Please do not reply directly to this message.
            </p>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Gourmet Restaurant <reservations@gourmet.com>" . "\r\n";
    
    return @mail($email, $subject, $message, $headers);
}
?>