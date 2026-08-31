<?php
// ============================================================
// LOGOUT - Customer Logout (Redirect to start.php)
// ============================================================

// ============================================================
// 1. SESSION START
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// 2. AUTO-DETECT BASE URL
// ============================================================
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    $path = dirname(dirname($script_name)); // Go up one level from /public/
    return $protocol . '://' . $host . $path . '/';
}
if (!defined('BASE_URL')) { define('BASE_URL', getBaseUrl()); }

// ============================================================
// 3. DETERMINE USER TYPE
// ============================================================
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_customer = isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id']);

// ============================================================
// 4. CLEAR SESSION DATA
// ============================================================
$_SESSION = array();

// ============================================================
// 5. CLEAR SESSION COOKIE
// ============================================================
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// ============================================================
// 6. CLEAR REMEMBER ME COOKIES
// ============================================================
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 42000, '/', '', false, true);
}
if (isset($_COOKIE['admin_remember_token'])) {
    setcookie('admin_remember_token', '', time() - 42000, '/', '', false, true);
}

// ============================================================
// 7. DESTROY SESSION
// ============================================================
session_destroy();

// ============================================================
// 8. REDIRECT TO START.PHP (or admin login for admin)
// ============================================================
if ($is_admin) {
    // Admin logout - redirect to admin login
    header("Location: " . BASE_URL . "admin/admin_login.php?logout=success");
} else {
    // Customer or any other user - redirect to start.php (landing page)
    header("Location: " . BASE_URL . "start.php?logout=1");
}
exit();
?>