<?php
// ============================================================
// PROCESS RATING - Save customer ratings and reviews
// ============================================================
session_start();
require_once __DIR__ . "/../includes/db.php";

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    $path = dirname(dirname($script_name));
    return $protocol . '://' . $host . $path . '/';
}
if (!defined('BASE_URL')) { define('BASE_URL', getBaseUrl()); }

// ============================================================
// CHECK IF CUSTOMER IS LOGGED IN
// ============================================================
if (!isset($_SESSION['customer_id']) || empty($_SESSION['customer_id'])) {
    header("Location: " . BASE_URL . "public/login.php?redirect=rate");
    exit();
}

$customer_id = (int)$_SESSION['customer_id'];
$reservation_id = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;

if (!$reservation_id) {
    header("Location: " . BASE_URL . "public/rate.php?error=no_reservation");
    exit();
}

// ============================================================
// GET DISH NAMES AND RATINGS
// ============================================================
$dish_names = $_POST['dish_names'] ?? [];
$success_count = 0;
$error_count = 0;

if (empty($dish_names)) {
    header("Location: " . BASE_URL . "public/rate.php?error=no_dishes");
    exit();
}

foreach ($dish_names as $index => $dish_name) {
    $dish_name = trim($dish_name);
    $field_suffix = preg_replace('/[^a-zA-Z0-9_]/', '_', $dish_name);
    
    $rating_key = 'rating_' . $field_suffix;
    $review_key = 'review_' . $field_suffix;
    
    $rating = isset($_POST[$rating_key]) ? (int)$_POST[$rating_key] : 0;
    $review = isset($_POST[$review_key]) ? trim($_POST[$review_key]) : '';
    
    // Only insert if rating is valid (1-5)
    if ($rating >= 1 && $rating <= 5) {
        // Check if already rated this dish for this reservation
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM reviews WHERE customer_id = ? AND reservation_id = ? AND dish_name = ?");
        mysqli_stmt_bind_param($check_stmt, "iis", $customer_id, $reservation_id, $dish_name);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) == 0) {
            // Insert review
            $stmt = mysqli_prepare($conn, "INSERT INTO reviews (customer_id, reservation_id, dish_name, rating, review) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iisis", $customer_id, $reservation_id, $dish_name, $rating, $review);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_count++;
            } else {
                $error_count++;
            }
            mysqli_stmt_close($stmt);
        } else {
            // Already rated - skip
            $success_count++;
        }
        mysqli_stmt_close($check_stmt);
    }
}

// ============================================================
// REDIRECT WITH STATUS
// ============================================================
if ($success_count > 0) {
    header("Location: " . BASE_URL . "public/rate.php?rated=success");
} else {
    header("Location: " . BASE_URL . "public/rate.php?error=no_ratings");
}
exit();
?>