<?php
// ============================================================
// MARK ALL NOTIFICATIONS AS READ
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
if (!defined('BASE_URL')) { define('BASE_URL', getBaseUrl()); }

// ============================================================
// 3. DATABASE CONNECTION
// ============================================================
require_once __DIR__ . "/../includes/db.php";

// ============================================================
// 4. CHECK LOGIN - Customer must be logged in
// ============================================================
if (!isset($_SESSION['customer_id']) || empty($_SESSION['customer_id'])) {
    header("Location: " . BASE_URL . "public/login.php?error=login_required");
    exit();
}

// ============================================================
// 5. MARK ALL UNREAD NOTIFICATIONS AS READ
// ============================================================
$customer_id = (int)$_SESSION['customer_id'];

// Update all unread messages for this customer to 'read'
$update_query = "UPDATE messages SET status = 'read' WHERE customer_id = $customer_id AND status = 'unread'";

if (mysqli_query($conn, $update_query)) {
    // Success - redirect to notifications with success message
    header("Location: " . BASE_URL . "public/notifications.php?marked_read=1");
    exit();
} else {
    // Error - redirect with error message
    error_log("mark_notifications_read.php - Database error: " . mysqli_error($conn));
    header("Location: " . BASE_URL . "public/notifications.php?error=db_error");
    exit();
}
?>