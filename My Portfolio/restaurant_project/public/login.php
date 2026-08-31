<?php
// ============================================================
// CUSTOMER LOGIN - Professional Version
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
// 4. REDIRECT IF ALREADY LOGGED IN
// ============================================================
if (isset($_SESSION['customer_id']) && isset($_SESSION['res_name'])) {
    header("Location: " . BASE_URL . "public/reservation.php");
    exit();
}

// ============================================================
// 5. CHECK FOR LOGOUT/REDIRECT MESSAGES
// ============================================================
$success_message = '';
if (isset($_GET['logged_out']) && $_GET['logged_out'] == 1) {
    $success_message = 'You have been logged out successfully.';
}
if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $success_message = 'Registration successful! Please login.';
}

// ============================================================
// 6. HANDLE LOGIN FORM SUBMISSION
// ============================================================
$error = '';
$email = '';
$remember = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    
    // CSRF Token Check (basic)
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Security validation failed. Please try again.';
    } else {
        // Get and sanitize inputs
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) ? true : false;
        
        // Validation
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (empty($password)) {
            $error = 'Please enter your password.';
        } else {
            // Use prepared statement to prevent SQL injection
            $stmt = mysqli_prepare($conn, "SELECT id, name, email, phone, password FROM customers WHERE email = ?");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($row = mysqli_fetch_assoc($result)) {
                // Verify password
                if (password_verify($password, $row['password'])) {
                    // Regenerate session ID to prevent fixation
                    session_regenerate_id(true);
                    
                    // Set session variables
                    $_SESSION['customer_id'] = $row['id'];
                    $_SESSION['res_name'] = $row['name'];
                    $_SESSION['res_email'] = $row['email'];
                    $_SESSION['res_phone'] = $row['phone'];
                    $_SESSION['login_time'] = time();
                    
                    // Remember Me - set cookie for 30 days
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = time() + (30 * 24 * 60 * 60);
                        setcookie('remember_token', $token, $expiry, '/', '', false, true);
                        // Store token in database (optional - add remember_token column)
                        // mysqli_query($conn, "UPDATE customers SET remember_token = '$token' WHERE id = " . $row['id']);
                    }
                    
                    // ============================================================
                    // 🔥 RATING REDIRECT CHECK (Added)
                    // ============================================================
                    if (isset($_GET['redirect']) && $_GET['redirect'] == 'rate') {
                        header("Location: " . BASE_URL . "public/rate.php");
                        exit();
                    }
                    
                    // Redirect to reservation page or previous page
                    $redirect_url = $_SESSION['redirect_after_login'] ?? BASE_URL . 'public/reservation.php';
                    unset($_SESSION['redirect_after_login']);
                    header("Location: " . $redirect_url);
                    exit();
                } else {
                    $error = 'Invalid password. Please try again.';
                }
            } else {
                $error = 'No account found with this email address.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// ============================================================
// 7. STORE CURRENT URL FOR REDIRECT AFTER LOGIN
// ============================================================
if (!isset($_SESSION['redirect_after_login']) && !isset($_GET['logged_out']) && !isset($_GET['registered']) && !isset($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['HTTP_REFERER'] ?? BASE_URL . 'public/reservation.php';
}

// ============================================================
// 8. GENERATE CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Gourmet</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ============================================================
                   GLOBAL STYLES (Consistent with register/forgot password)
                   ============================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: #050e0c;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: radial-gradient(circle at 20% 50%, rgba(212, 175, 55, 0.05) 0%, transparent 60%);
        }

        .login-container {
            width: 100%;
            max-width: 440px;
            padding: 20px;
            animation: fadeInUp 0.6s ease forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-box {
            background: #0a1914;
            padding: 45px 40px 40px;
            border-radius: 24px;
            border: 1px solid #1e4538;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
            position: relative;
            overflow: hidden;
        }
        .login-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #D4AF37, transparent);
        }

        .logo-icon {
            text-align: center;
            font-size: 48px;
            margin-bottom: 5px;
        }
        .login-box h2 {
            font-family: 'Playfair Display', serif;
            color: #D4AF37;
            text-align: center;
            font-size: 30px;
            letter-spacing: 1px;
        }
        .login-box .subtitle {
            text-align: center;
            color: #888;
            font-size: 14px;
            margin-bottom: 25px;
            margin-top: 4px;
        }

        /* ============================================================
                   MESSAGES
                   ============================================================ */
        .msg-success,
        .msg-error {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .msg-success {
            background: rgba(46, 204, 113, 0.12);
            border: 1px solid rgba(46, 204, 113, 0.25);
            color: #2ecc71;
        }
        .msg-error {
            background: rgba(231, 76, 60, 0.12);
            border: 1px solid rgba(231, 76, 60, 0.25);
            color: #e74c3c;
        }

        /* ============================================================
                   FORM ELEMENTS
                   ============================================================ */
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            color: #aaa;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        .form-group label i {
            margin-right: 6px;
            color: #D4AF37;
        }
        .form-group input {
            width: 100%;
            padding: 13px 16px;
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            color: #fff;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }
        .form-group input:focus {
            border-color: #D4AF37;
            outline: none;
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.08);
        }
        .form-group input::placeholder {
            color: #555;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 8px 0 25px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .form-options label {
            color: #888;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-options label input[type="checkbox"] {
            accent-color: #D4AF37;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        .form-options a {
            color: #D4AF37;
            text-decoration: none;
            font-size: 13px;
            transition: 0.3s;
        }
        .form-options a:hover {
            text-decoration: underline;
        }

        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #D4AF37, #b8962e);
            color: #000;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-primary:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(212, 175, 55, 0.3);
        }
        .btn-primary i {
            font-size: 18px;
        }

        .back-link {
            display: block;
            text-align: center;
            color: #888;
            font-size: 14px;
            margin-top: 20px;
            text-decoration: none;
            transition: 0.3s;
        }
        .back-link:hover {
            color: #D4AF37;
        }
        .back-link i {
            margin-right: 6px;
        }

        .footer-text {
            text-align: center;
            color: #444;
            font-size: 12px;
            margin-top: 20px;
        }
        .footer-text i {
            color: #D4AF37;
        }

        /* ============================================================
                   RESPONSIVE
                   ============================================================ */
        @media (max-width: 480px) {
            .login-box {
                padding: 30px 20px 30px;
            }
            .login-box h2 {
                font-size: 24px;
            }
            .logo-icon {
                font-size: 36px;
            }
            .form-options {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-box">

            <div class="logo-icon">🍽️</div>
            <h2>Welcome Back</h2>
            <p class="subtitle">Sign in to continue your reservation</p>

            <!-- Success Messages -->
            <?php if ($success_message): ?>
                <div class="msg-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>

            <!-- Error Messages -->
            <?php if ($error): ?>
                <div class="msg-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="<?php echo BASE_URL; ?>public/login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" placeholder="john@example.com" 
                           value="<?php echo htmlspecialchars($email); ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>

                <div class="form-options">
                    <label>
                        <input type="checkbox" name="remember" <?php echo $remember ? 'checked' : ''; ?>>
                        <i class="fas fa-check-circle" style="color:#D4AF37;"></i> Remember Me
                    </label>
                    <a href="<?php echo BASE_URL; ?>public/forgot_password.php"><i class="fas fa-key"></i> Forgot Password?</a>
                </div>

                <button type="submit" name="login" class="btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <div style="text-align:center; margin-top:15px; color:#555; font-size:13px;">
                <span>or</span>
            </div>

            <div style="text-align:center; margin-top:8px;">
                <a href="<?php echo BASE_URL; ?>public/register.php" style="color:#D4AF37; text-decoration:none; font-weight:600;">
                    <i class="fas fa-user-plus"></i> Create New Account
                </a>
            </div>

            <a href="<?php echo BASE_URL; ?>public/index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>

            <div class="footer-text">
                <i class="fas fa-crown"></i> Gourmet Restaurant Management System
            </div>

        </div>
    </div>

</body>
</html>