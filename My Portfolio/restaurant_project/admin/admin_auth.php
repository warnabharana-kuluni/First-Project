<?php
// ============================================================
// ADMIN AUTHENTICATION SYSTEM
// ============================================================
// මෙම file එක admin pages සියල්ලටම include කරන්න
// එවිට login නොවී pages වලට පිවිසිය නොහැක
// ============================================================

// ============================================================
// 1. SESSION START
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// 2. SESSION SECURITY SETTINGS
// ============================================================
// Session timeout - minutes
define('SESSION_TIMEOUT', 30); // 30 minutes

// Session security
if (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
}

// Check session timeout
if (isset($_SESSION['created_at']) && (time() - $_SESSION['created_at'] > SESSION_TIMEOUT * 60)) {
    // Session expired - clear and redirect
    $_SESSION = array();
    session_destroy();
    header("Location: admin_login.php?error=session_expired");
    exit();
}

// ============================================================
// 3. CHECK IF ADMIN IS LOGGED IN
// ============================================================
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && 
           $_SESSION['admin_logged_in'] === true && 
           isset($_SESSION['admin_email']) && 
           !empty($_SESSION['admin_email']);
}

// ============================================================
// 4. CHECK AUTHENTICATION (Redirect if not logged in)
// ============================================================
if (!isAdminLoggedIn()) {
    // Store the requested URL to redirect back after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to login page
    header("Location: admin_login.php?error=unauthorized");
    exit();
}

// ============================================================
// 5. REGENERATE SESSION ID FOR SECURITY (Prevent Session Fixation)
// ============================================================
if (!isset($_SESSION['session_regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['session_regenerated'] = true;
}

// ============================================================
// 6. UPDATE LAST ACTIVITY TIMESTAMP
// ============================================================
$_SESSION['last_activity'] = time();

// ============================================================
// 7. GET ADMIN INFORMATION (Optional - for display)
// ============================================================
function getAdminInfo($conn) {
    $email = $_SESSION['admin_email'] ?? '';
    if (empty($email)) return null;
    
    $sql = "SELECT * FROM admins WHERE email = '$email'";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

// ============================================================
// 8. LOGOUT FUNCTION (Call this from admin_logout.php)
// ============================================================
function adminLogout() {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    // Redirect to login
    header("Location: admin_login.php?logout=success");
    exit();
}

// ============================================================
// 9. CHECK IP ADDRESS FOR EXTRA SECURITY (Optional)
// ============================================================
// Uncomment this if you want to restrict admin access by IP
/*
function isAllowedIP() {
    $allowed_ips = ['127.0.0.1', '::1']; // Add your IPs here
    $client_ip = $_SERVER['REMOTE_ADDR'];
    return in_array($client_ip, $allowed_ips);
}

if (!isAllowedIP()) {
    header("HTTP/1.0 403 Forbidden");
    die("Access denied from your IP address.");
}
*/

// ============================================================
// 10. CHECK USER-AGENT FOR EXTRA SECURITY (Optional)
// ============================================================
// Uncomment this if you want to restrict by browser
/*
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
} elseif ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    // User agent changed - possible hijacking
    adminLogout();
}
*/

// ============================================================
// 11. SUCCESSFUL AUTHENTICATION - ALL CHECKS PASSED
// ============================================================
// Admin is authenticated and session is valid
// Continue to the requested page
?>