<?php
// ============================================================
// ADMIN LOGIN PAGE
// ============================================================
session_start();
require_once __DIR__ . '/../includes/db.php';

$error = '';
$success = '';

// Check for logout message
if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
    $success = 'You have been logged out successfully.';
}

// Check for session expired message
if (isset($_GET['error']) && $_GET['error'] == 'session_expired') {
    $error = 'Your session has expired. Please login again.';
}

// Check for unauthorized access
if (isset($_GET['error']) && $_GET['error'] == 'unauthorized') {
    $error = 'Please login to access the admin panel.';
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = md5($_POST['password']);
    $remember = isset($_POST['remember']) ? true : false;
    
    $sql = "SELECT * FROM admins WHERE email = '$email' AND password = '$password'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) == 1) {
        $admin = mysqli_fetch_assoc($result);
        
        // Set session variables
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['created_at'] = time();
        $_SESSION['session_regenerated'] = false;
        
        // Remember me - set cookie for 7 days
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + (7 * 24 * 60 * 60);
            setcookie('admin_remember_token', $token, $expiry, '/', '', false, true);
        }
        
        // Redirect to dashboard or previous page
        $redirect = $_SESSION['redirect_after_login'] ?? 'admin.php';
        unset($_SESSION['redirect_after_login']);
        header("Location: $redirect");
        exit();
    } else {
        $error = 'Invalid email or password!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Gourmet</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
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
            max-width: 420px;
            padding: 20px;
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
            font-size: 56px;
            margin-bottom: 5px;
        }
        .login-box h2 {
            font-family: 'Playfair Display', serif;
            color: #D4AF37;
            text-align: center;
            font-size: 32px;
            letter-spacing: 2px;
        }
        .login-box .subtitle {
            text-align: center;
            color: #888;
            font-size: 14px;
            margin-bottom: 30px;
            margin-top: 5px;
        }

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

        .btn-login {
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
        .btn-login:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(212, 175, 55, 0.3);
        }
        .btn-login i {
            font-size: 18px;
        }

        .error-msg {
            color: #e74c3c;
            font-size: 14px;
            padding: 12px 16px;
            background: rgba(231, 76, 60, 0.10);
            border: 1px solid rgba(231, 76, 60, 0.20);
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .error-msg i {
            font-size: 16px;
        }

        .success-msg {
            color: #2ecc71;
            font-size: 14px;
            padding: 12px 16px;
            background: rgba(46, 204, 113, 0.10);
            border: 1px solid rgba(46, 204, 113, 0.20);
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .success-msg i {
            font-size: 16px;
        }

        .footer-text {
            text-align: center;
            color: #555;
            font-size: 13px;
            margin-top: 20px;
        }
        .footer-text a {
            color: #D4AF37;
            text-decoration: none;
        }
        .footer-text a:hover {
            text-decoration: underline;
        }

        .security-badge {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            color: #444;
            font-size: 12px;
        }
        .security-badge i {
            color: #2ecc71;
        }

        /* Animation */
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
            animation: fadeInUp 0.6s ease forwards;
        }

        @media (max-width: 480px) {
            .login-box {
                padding: 30px 20px 30px;
            }
            .login-box h2 {
                font-size: 26px;
            }
            .logo-icon {
                font-size: 40px;
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
            <h2>GOURMET</h2>
            <p class="subtitle">Admin Control Panel</p>

            <!-- Error / Success Messages -->
            <?php if ($error): ?>
                <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-msg"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" placeholder="admin@email.com" required autofocus>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>

                <div class="form-options">
                    <label>
                        <input type="checkbox" name="remember">
                        <i class="fas fa-check-circle" style="color:#D4AF37;"></i> Remember Me
                    </label>
                    <!-- 🔥 මෙය වෙනස් කර ඇත - admin_forgot_password.php -->
                    <a href="admin_forgot_password.php"><i class="fas fa-key"></i> Forgot Password?</a>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login Securely
                </button>
            </form>

            <div class="security-badge">
                <span><i class="fas fa-shield-alt"></i> Secure</span>
                <span><i class="fas fa-lock"></i> Encrypted</span>
            </div>

            <div class="footer-text">
                <i class="fas fa-crown" style="color:#D4AF37;"></i>
                Gourmet Restaurant Management System
            </div>
        </div>
    </div>

</body>
</html>