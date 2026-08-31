<?php
// ============================================================
// ADMIN LOGOUT - Redirect to start.php
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
    // admin_logout.php is in /admin/ folder, so go up one level to get root
    $path = dirname(dirname($script_name));
    return $protocol . '://' . $host . $path . '/';
}
if (!defined('BASE_URL')) { define('BASE_URL', getBaseUrl()); }

// ============================================================
// 3. CLEAR ALL SESSION DATA
// ============================================================
$_SESSION = array();

// ============================================================
// 4. DESTROY SESSION COOKIE
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
// 5. CLEAR REMEMBER ME COOKIE
// ============================================================
if (isset($_COOKIE['admin_remember_token'])) {
    setcookie('admin_remember_token', '', time() - 42000, '/', '', false, true);
}

// ============================================================
// 6. DESTROY SESSION
// ============================================================
session_destroy();

// ============================================================
// 7. REDIRECT TO START.PHP (Landing Page)
// ============================================================
header("Location: " . BASE_URL . "start.php?logout=1");
exit();
?>