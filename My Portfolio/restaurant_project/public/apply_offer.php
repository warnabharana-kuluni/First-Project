<?php
// ============================================================
// APPLY OFFER - Apply discount offer to reservation
// ============================================================
session_start();
require_once __DIR__ . "/../includes/db.php";

// ============================================================
// 1. VALIDATE OFFER ID
// ============================================================
if (isset($_GET['offer_id']) && is_numeric($_GET['offer_id'])) {
    $offer_id = (int)$_GET['offer_id'];
    
    // ============================================================
    // 2. FETCH OFFER USING PREPARED STATEMENT (SQL Injection safe)
    // ============================================================
    $stmt = mysqli_prepare($conn, "SELECT * FROM offers WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $offer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($offer = mysqli_fetch_assoc($result)) {
        // ============================================================
        // 3. OFFER FOUND - Store in session
        // ============================================================
        $_SESSION['applied_offer'] = $offer;
        
        // Redirect to reservation page with success parameter
        header("Location: " . BASE_URL . "public/reservation.php?offer_applied=1");
        exit();
    } else {
        // ============================================================
        // 4. OFFER NOT FOUND - Redirect with error
        // ============================================================
        header("Location: " . BASE_URL . "public/reservation.php?offer_error=1");
        exit();
    }
} else {
    // ============================================================
    // 5. INVALID OR MISSING OFFER ID
    // ============================================================
    header("Location: " . BASE_URL . "public/reservation.php?offer_error=1");
    exit();
}
?>