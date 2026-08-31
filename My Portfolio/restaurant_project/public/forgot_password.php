<?php
// ============================================================
// FORGOT PASSWORD - Customer
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
// 4. FUNCTIONS
// ============================================================
function sendResetEmail($email, $token, $base_url) {
    $reset_link = $base_url . 'public/reset_password.php?token=' . $token;
    
    // For localhost - show link on screen
    return $reset_link;
}

// ============================================================
// 5. HANDLE REQUESTS
// ============================================================
$error = '';
$success = '';
$show_reset_form = false;
$token_valid = false;
$user_email = '';

// ----- Step 1: Request Reset Link (Email Submit) -----
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_reset'])) {
    $email = trim($_POST['email']);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email exists
        $stmt = mysqli_prepare($conn, "SELECT * FROM customers WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 1) {
            $row = mysqli_fetch_assoc($result);
            
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Update database with token
            $update_stmt = mysqli_prepare($conn, "UPDATE customers SET reset_token = ?, reset_expiry = ? WHERE email = ?");
            mysqli_stmt_bind_param($update_stmt, "sss", $token, $expiry, $email);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $reset_link = sendResetEmail($email, $token, BASE_URL);
                
                $success = '<div class="msg-success" style="display:block; text-align:left;">
                    <i class="fas fa-check-circle"></i> A password reset link has been generated.<br><br>
                    <strong style="color:#fff;">📩 Reset Link (Copy & Paste in Browser):</strong><br>
                    <a href="' . $reset_link . '" target="_blank" style="color:#D4AF37; word-break:break-all; font-weight:600;">' . $reset_link . '</a>
                    <br><br>
                    <span style="color:#888; font-size:13px;"><i class="fas fa-clock"></i> This link expires in 1 hour.</span>
                </div>';
            } else {
                $error = 'Error generating reset link. Please try again.';
            }
            mysqli_stmt_close($update_stmt);
        } else {
            $error = 'No account found with this email address.';
        }
        mysqli_stmt_close($stmt);
    }
}

// ----- Step 2: Verify Token & Show Reset Form -----
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    $now = date('Y-m-d H:i:s');
    
    $stmt = mysqli_prepare($conn, "SELECT * FROM customers WHERE reset_token = ? AND reset_expiry > ?");
    mysqli_stmt_bind_param($stmt, "ss", $token, $now);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        $user_email = $row['email'];
        $token_valid = true;
        $show_reset_form = true;
    } else {
        $error = 'Invalid or expired reset link. Please request a new one.';
    }
    mysqli_stmt_close($stmt);
}

// ----- Step 3: Reset Password (New Password Submit) -----
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $token = $_POST['token'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in both password fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $now = date('Y-m-d H:i:s');
        
        // Check token again for security
        $stmt = mysqli_prepare($conn, "SELECT * FROM customers WHERE reset_token = ? AND reset_expiry > ?");
        mysqli_stmt_bind_param($stmt, "ss", $token, $now);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 1) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            
            // Update password and clear token
            $update_stmt = mysqli_prepare($conn, "UPDATE customers SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE reset_token = ?");
            mysqli_stmt_bind_param($update_stmt, "ss", $hashed_password, $token);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success = '<div class="msg-success"><i class="fas fa-check-circle"></i> Password reset successfully!</div>';
                $show_reset_form = false;
            } else {
                $error = 'Error resetting password. Please try again.';
            }
            mysqli_stmt_close($update_stmt);
        } else {
            $error = 'Invalid or expired reset link.';
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Gourmet</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ============================================================
                   GLOBAL STYLES (Consistent with login.php)
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
            font-size: 28px;
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
                font-size: 22px;
            }
            .logo-icon {
                font-size: 36px;
            }
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-box">

            <div class="logo-icon">🔑</div>
            <h2>Reset Password</h2>
            <p class="subtitle">Enter your email to receive a reset link</p>

            <!-- Display Errors / Success Messages -->
            <?php if ($error): ?>
                <div class="msg-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <?php echo $success; ?>
            <?php endif; ?>

            <!-- ============================================================
            STEP 1: REQUEST RESET LINK (Email Form)
            ============================================================ -->
            <?php if (!$show_reset_form && empty($success)): ?>
                <form method="POST" action="<?php echo BASE_URL; ?>public/forgot_password.php">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" name="email" placeholder="john@example.com" required autofocus>
                    </div>
                    <button type="submit" name="send_reset" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Reset Link
                    </button>
                </form>
                <a href="<?php echo BASE_URL; ?>public/login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            <?php endif; ?>

            <!-- ============================================================
            STEP 2: RESET PASSWORD FORM (After Token Validation)
            ============================================================ -->
            <?php if ($show_reset_form && $token_valid): ?>
                <form method="POST" action="<?php echo BASE_URL; ?>public/forgot_password.php">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> New Password</label>
                        <input type="password" name="password" placeholder="••••••••" required minlength="6">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-check-circle"></i> Confirm Password</label>
                        <input type="password" name="confirm_password" placeholder="••••••••" required minlength="6">
                    </div>

                    <button type="submit" name="reset_password" class="btn-primary">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                </form>
                <a href="<?php echo BASE_URL; ?>public/login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            <?php endif; ?>

            <!-- ============================================================
            STEP 3: SUCCESS - Redirect to Login Link
            ============================================================ -->
            <?php if (!empty($success) && !$show_reset_form): ?>
                <div style="text-align:center; margin-top:15px;">
                    <a href="<?php echo BASE_URL; ?>public/login.php" style="color:#D4AF37; text-decoration:none; font-weight:600;">
                        <i class="fas fa-sign-in-alt"></i> Click here to login
                    </a>
                </div>
            <?php endif; ?>

            <div class="footer-text">
                <i class="fas fa-crown"></i> Gourmet Restaurant Management System
            </div>

        </div>
    </div>

</body>
</html>