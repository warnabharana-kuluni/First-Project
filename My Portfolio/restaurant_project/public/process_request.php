<?php
// ============================================================
// PROCESS SPECIAL REQUEST
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
// 6. VALIDATE SESSION - Reservation ID exists
// ============================================================
if (!isset($_SESSION['reservation_id']) || empty($_SESSION['reservation_id'])) {
    header("Location: " . BASE_URL . "public/reservation.php?error=no_reservation");
    exit();
}

$reservation_id = (int)$_SESSION['reservation_id'];

// ============================================================
// 7. GET AND VALIDATE REQUEST INPUT
// ============================================================
$request = trim($_POST['request'] ?? '');

// Check if request is empty
if (empty($request)) {
    header("Location: " . BASE_URL . "public/reservation.php?error=empty_request");
    exit();
}

// Limit request length (optional)
if (strlen($request) > 1000) {
    $request = substr($request, 0, 1000);
}

// ============================================================
// 8. UPDATE DATABASE USING PREPARED STATEMENT
// ============================================================
$stmt = mysqli_prepare($conn, "UPDATE reservations SET special_requests = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, "si", $request, $reservation_id);

if (mysqli_stmt_execute($stmt)) {
    // Check if any rows were affected
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        // Success - update session with the request
        $_SESSION['special_request'] = $request;
        header("Location: " . BASE_URL . "public/success.php?msg=request_saved");
        exit();
    } else {
        // No rows affected - reservation might not exist or no change
        header("Location: " . BASE_URL . "public/reservation.php?error=no_change");
        exit();
    }
} else {
    // Database error
    error_log("process_request.php - Database error: " . mysqli_error($conn));
    header("Location: " . BASE_URL . "public/reservation.php?error=db_error");
    exit();
}

mysqli_stmt_close($stmt);
?>