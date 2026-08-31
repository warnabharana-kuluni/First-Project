<?php
// ============================================================
// FORGOT PASSWORD - Admin Panel
// ============================================================
require_once __DIR__ . '/../includes/db.php';

// ============================================================
// 1. FUNCTIONS
// ============================================================
function sendResetLink($email, $token) {
    // Reset Link එක හදන්න
    $base_url = 'http://localhost/restaurant_project/admin/';
    $reset_link = $base_url . 'admin_forgot_password.php?token=' . $token;
    
    // Localhost වලදී mail() වැඩ නොකරන නිසා, අපි link එක return කරමු
    // (Production එකට මාරු කරන විට mail() function එක use කරන්න)
    return $reset_link;
}

// ============================================================
// 2. HANDLE REQUESTS
// ============================================================
$error = '';
$success = '';
$show_reset_form = false;
$token_valid = false;
$user_email = '';

// ----- Step 1: Request Reset Link (Email Submit) -----
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_reset'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Check if email exists
    $check_sql = "SELECT * FROM admins WHERE email = '$email'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) == 1) {
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // 1 hour expiry
        
        // Update database with token
        $update_sql = "UPDATE admins SET reset_token = '$token', reset_expiry = '$expiry' WHERE email = '$email'";
        if (mysqli_query($conn, $update_sql)) {
            $reset_link = sendResetLink($email, $token);
            
            // Show the link directly on screen (for localhost testing)
            $success = '<div class="msg-success" style="display:block; text-align:left;">
                <i class="fas fa-check-circle"></i> A password reset link has been generated.<br>
                <strong style="color:#fff;">📩 Reset Link (Copy & Paste in Browser):</strong><br>
                <a href="' . $reset_link . '" target="_blank" style="color:#D4AF37; word-break:break-all;">' . $reset_link . '</a>
                <br><small style="color:#888;">(This link expires in 1 hour)</small>
            </div>';
        } else {
            $error = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Error generating reset link. Please try again.</div>';
        }
    } else {
        $error = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> No account found with this email address.</div>';
    }
}

// ----- Step 2: Verify Token & Show Reset Form -----
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = mysqli_real_escape_string($conn, $_GET['token']);
    $now = date('Y-m-d H:i:s');
    
    // Check if token exists and is not expired
    $sql = "SELECT * FROM admins WHERE reset_token = '$token' AND reset_expiry > '$now'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        $user_email = $row['email'];
        $token_valid = true;
        $show_reset_form = true;
    } else {
        $error = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Invalid or expired reset link. Please request a new one.</div>';
    }
}

// ----- Step 3: Reset Password (New Password Submit) -----
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $token = mysqli_real_escape_string($conn, $_POST['token']);
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Please fill in both password fields.</div>';
    } elseif ($new_password !== $confirm_password) {
        $error = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Passwords do not match.</div>';
    } elseif (strlen($new_password) < 6) {
        $error = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Password must be at least 6 characters.</div>';
    } else {
        // Check token again for security
        $now = date('Y-m-d H:i:s');
        $check_sql = "SELECT * FROM admins WHERE reset_token = '$token' AND reset_expiry > '$now'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) == 1) {
            // Hash new password (using MD5 to match your login system)
            $hashed_password = md5($new_password);
            
            // Update password and clear token
            $update_sql = "UPDATE admins SET password = '$hashed_password', reset_token = NULL, reset_expiry = NULL WHERE reset_token = '$token'";
            if (mysqli_query($conn, $update_sql)) {
                $success = '<div class="msg-success"><i class="fas fa-check-circle"></i> Password reset successfully! <a href="admin_login.php" style="color:#D4AF37; font-weight:600;">Click here to login</a></div>';
                $show_reset_form = false;
            } else {
                $error = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Error resetting password. Please try again.</div>';
            }
        } else {
            $error = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> Invalid or expired reset link.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Gourmet Admin</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ============================================================
                   GLOBAL STYLES (Same as admin_login.php)
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
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            word-break: break-word;
        }
        .msg-success {
            background: rgba(46, 204, 113, 0.12);
            border: 1px solid rgba(46, 204, 113, 0.25);
            color: #2ecc71;
        }
        .msg-success i {
            margin-top: 2px;
        }
        .msg-error {
            background: rgba(231, 76, 60, 0.12);
            border: 1px solid rgba(231, 76, 60, 0.25);
            color: #e74c3c;
        }
        .msg-error i {
            margin-top: 2px;
        }

        /* ============================================================
                   FORM ELEMENTS
                   ============================================================ */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #aaa;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
        }
        .form-group label i {
            margin-right: 6px;
            color: #D4AF37;
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
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

            <!-- Icon & Title -->
            <div class="logo-icon">🔑</div>
            <h2>Reset Password</h2>
            <p class="subtitle">Enter your email to receive a reset link</p>

            <!-- Display Errors / Success Messages -->
            <?php echo $error; ?>
            <?php echo $success; ?>

            <!-- ============================================================
            STEP 1: REQUEST RESET LINK (Email Form)
            ============================================================ -->
            <?php if (!$show_reset_form && empty($success)): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" name="email" placeholder="admin@email.com" required autofocus>
                    </div>
                    <button type="submit" name="send_reset" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Reset Link
                    </button>
                </form>
                <a href="admin_login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            <?php endif; ?>

            <!-- ============================================================
            STEP 2: RESET PASSWORD FORM (After Token Validation)
            ============================================================ -->
            <?php if ($show_reset_form && $token_valid): ?>
                <form method="POST" action="">
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
                <a href="admin_login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            <?php endif; ?>

            <!-- Footer -->
            <div class="footer-text">
                <i class="fas fa-crown"></i> Gourmet Restaurant Management System
            </div>

        </div>
    </div>

</body>
</html>