<?php
// ============================================================
// UPDATE RESERVATION STATUS
// ============================================================
// Admin authentication - must be logged in
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';

// ============================================================
// 1. CHECK AND VALIDATE INPUT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || !isset($_POST['status'])) {
    header("Location: admin_reservations.php?error=invalid_request");
    exit();
}

$id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
$status = trim($_POST['status']);

// Validate status
$allowed_statuses = ['pending', 'confirmed', 'cancelled'];
if (!$id || $id <= 0 || !in_array($status, $allowed_statuses)) {
    header("Location: admin_reservations.php?error=invalid_data");
    exit();
}

// ============================================================
// 2. FETCH RESERVATION DETAILS
// ============================================================
$stmt = mysqli_prepare($conn, "SELECT name, email, reservation_date, reservation_time FROM reservations WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$row = mysqli_fetch_assoc($result)) {
    mysqli_stmt_close($stmt);
    header("Location: admin_reservations.php?error=not_found");
    exit();
}
mysqli_stmt_close($stmt);

$user_name = $row['name'];
$user_email = $row['email'];
$res_date = $row['reservation_date'];
$res_time = $row['reservation_time'];

// ============================================================
// 3. UPDATE STATUS IN DATABASE
// ============================================================
$update_stmt = mysqli_prepare($conn, "UPDATE reservations SET status = ? WHERE id = ?");
mysqli_stmt_bind_param($update_stmt, "si", $status, $id);

if (mysqli_stmt_execute($update_stmt)) {
    $updated = true;
} else {
    $updated = false;
    $error = mysqli_error($conn);
}
mysqli_stmt_close($update_stmt);

// ============================================================
// 4. SEND EMAIL NOTIFICATION (if status is confirmed or cancelled)
// ============================================================
$email_sent = false;
if ($updated && in_array($status, ['confirmed', 'cancelled'])) {
    $to = $user_email;
    $subject = "Reservation Status Update - Gourmet Restaurant";

    // Status color and message
    if ($status == 'confirmed') {
        $status_color = '#2e7d32';
        $status_message = 'We are excited to serve you! Please arrive 10 minutes prior to your booking time.';
    } else { // cancelled
        $status_color = '#c62828';
        $status_message = 'We regret to inform you that we cannot accommodate your request at this time. Please contact us for further assistance.';
    }

    // HTML Email Template (matches admin theme)
    $message = "
    <html>
    <head>
        <title>Reservation Update</title>
    </head>
    <body style='font-family: Arial, sans-serif; background-color: #050e0c; padding: 20px; color: #ffffff;'>
        <div style='max-width: 600px; margin: 0 auto; background: #0a1914; padding: 30px; border-radius: 15px; border: 1px solid #1e4538; text-align: center;'>
            <h2 style='color: #D4AF37; margin-bottom: 20px; font-family: \"Playfair Display\", serif;'>✨ GOURMET RESTAURANT</h2>
            <hr style='border: 0; border-top: 1px solid #1e4538; margin-bottom: 20px;'>
            
            <p style='font-size: 16px; color: #b0c4b1; text-align: left;'>Dear <strong>$user_name</strong>,</p>
            <p style='font-size: 15px; color: #b0c4b1; text-align: left; line-height: 1.6;'>
                Your reservation for <strong>$res_date</strong> at <strong>$res_time</strong> has been updated by our management.
            </p>
            
            <div style='margin: 30px 0; padding: 15px; background: $status_color; color: #fff; font-size: 20px; font-weight: bold; border-radius: 8px; text-transform: uppercase; display: inline-block; letter-spacing: 2px;'>
                $status
            </div>
            
            <p style='font-size: 14px; color: #b0c4b1; line-height: 1.6;'>$status_message</p>
            
            <hr style='border: 0; border-top: 1px solid #1e4538; margin-top: 30px;'>
            <p style='font-size: 12px; color: #557a6e; margin-top: 15px;'>
                This is an automated system email. Please do not reply directly to this message.
            </p>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Gourmet Restaurant <gourmet@restaurant.com>" . "\r\n";

    // Send email (suppress errors)
    $email_sent = @mail($to, $subject, $message, $headers);
}

// ============================================================
// 5. REDIRECT WITH STATUS MESSAGE
// ============================================================
if ($updated) {
    $redirect = "admin_reservations.php?success=1";
    if ($email_sent) {
        $redirect .= "&email=1";
    }
    header("Location: $redirect");
    exit();
} else {
    header("Location: admin_reservations.php?error=update_failed");
    exit();
}
?>