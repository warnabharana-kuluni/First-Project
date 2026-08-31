<?php
// ============================================================
// ADMIN REGISTRATION PAGE
// ============================================================
session_start();
require_once __DIR__ . '/../includes/db.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    $errors = [];
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    }
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        // Check if username or email already exists
        $check_sql = "SELECT id FROM admins WHERE username = '$username' OR email = '$email'";
        $check_result = mysqli_query($conn, $check_sql);

        if (mysqli_num_rows($check_result) > 0) {
            $error = '❌ Username or Email already exists!';
        } else {
            // Use MD5 to match the login system (admin_login.php uses MD5)
            $hashed_password = md5($password);

            $sql = "INSERT INTO admins (username, email, password) VALUES ('$username', '$email', '$hashed_password')";
            if (mysqli_query($conn, $sql)) {
                $success = '✅ Admin account created successfully! Redirecting to login...';
                header("refresh:2; url=admin_login.php");
            } else {
                $error = '❌ Something went wrong. Please try again.';
            }
        }
    } else {
        $error = '❌ ' . implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration | Gourmet</title>
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
        .form-group label .required {
            color: #e74c3c;
            margin-left: 4px;
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
            margin-top: 5px;
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
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-box">

            <div class="logo-icon">👤</div>
            <h2>Admin Register</h2>
            <p class="subtitle">Create a new administrative account</p>

            <!-- Error / Success Messages -->
            <?php if ($error): ?>
                <div class="msg-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="msg-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username <span class="required">*</span></label>
                    <input type="text" name="username" placeholder="admin_gourmet" 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label>
                    <input type="email" name="email" placeholder="admin@email.com" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password <span class="required">*</span></label>
                    <input type="password" name="password" placeholder="Min 6 characters" required minlength="6">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-check-circle"></i> Confirm Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" placeholder="Re-enter password" required minlength="6">
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Register Admin
                </button>
            </form>

            <a href="admin_login.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Already have an account? Login
            </a>

            <div class="footer-text">
                <i class="fas fa-crown"></i> Gourmet Restaurant Management System
            </div>

        </div>
    </div>

</body>
</html>