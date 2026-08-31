<?php
// ============================================================
// CUSTOMER REGISTRATION - Professional Version
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
// 4. GENERATE CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ============================================================
// 5. CHECK FOR SUCCESS/ERROR MESSAGES FROM SESSION
// ============================================================
$error = '';
$success = '';
$form_data = [];

if (isset($_SESSION['register_errors']) && !empty($_SESSION['register_errors'])) {
    $error = implode('<br>', $_SESSION['register_errors']);
    unset($_SESSION['register_errors']);
}

if (isset($_SESSION['register_success'])) {
    $success = $_SESSION['register_success'];
    unset($_SESSION['register_success']);
}

// Restore form data if exists
if (isset($_SESSION['register_form_data'])) {
    $form_data = $_SESSION['register_form_data'];
    unset($_SESSION['register_form_data']);
}

// ============================================================
// 6. HANDLE FORM SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['register_errors'] = ['Security validation failed. Please try again.'];
        header("Location: " . BASE_URL . "public/register.php");
        exit();
    }
    
    // Get and sanitize inputs
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Store for repopulating form
    $form_data = ['name' => $name, 'email' => $email, 'phone' => $phone];
    
    // Validation
    $errors = [];
    
    // Name validation
    if (empty($name) || strlen($name) < 2) {
        $errors[] = 'Please enter your full name (minimum 2 characters).';
    } elseif (strlen($name) > 100) {
        $errors[] = 'Name is too long (maximum 100 characters).';
    } elseif (!preg_match('/^[a-zA-Z\s.\'-]+$/', $name)) {
        $errors[] = 'Name can only contain letters, spaces, dots, and hyphens.';
    }
    
    // Email validation
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (strlen($email) > 100) {
        $errors[] = 'Email is too long (maximum 100 characters).';
    }
    
    // Phone validation (optional but recommended)
    if (empty($phone)) {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number (numbers, spaces, +, -, parentheses).';
    }
    
    // Password validation
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif (strlen($password) > 255) {
        $errors[] = 'Password is too long (maximum 255 characters).';
    }
    
    // Password confirmation
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    // If no validation errors, proceed
    if (empty($errors)) {
        // Check if email already exists using prepared statement
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM customers WHERE email = ?");
        mysqli_stmt_bind_param($check_stmt, "s", $email);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            $errors[] = 'This email is already registered. Please login or use a different email.';
        }
        mysqli_stmt_close($check_stmt);
    }
    
    // If errors still exist, store and redirect back
    if (!empty($errors)) {
        $_SESSION['register_errors'] = $errors;
        $_SESSION['register_form_data'] = $form_data;
        header("Location: " . BASE_URL . "public/register.php");
        exit();
    }
    
    // Hash password and insert
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    
    $insert_stmt = mysqli_prepare($conn, 
        "INSERT INTO customers (name, email, phone, password, created_at) VALUES (?, ?, ?, ?, NOW())"
    );
    mysqli_stmt_bind_param($insert_stmt, "ssss", $name, $email, $phone, $hashed_password);
    
    if (mysqli_stmt_execute($insert_stmt)) {
        // Success
        $_SESSION['register_success'] = 'Registration successful! Redirecting to login...';
        // Clear form data
        unset($_SESSION['register_form_data']);
        mysqli_stmt_close($insert_stmt);
        header("Location: " . BASE_URL . "public/register.php?registered=1");
        exit();
    } else {
        // Database error
        error_log("register.php - Insert error: " . mysqli_error($conn));
        $_SESSION['register_errors'] = ['Registration failed. Please try again later.'];
        $_SESSION['register_form_data'] = $form_data;
        header("Location: " . BASE_URL . "public/register.php");
        exit();
    }
    mysqli_stmt_close($insert_stmt);
}

// If redirect with success parameter, show success message
if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    // Already handled via session, but ensure it's shown
    if (isset($_SESSION['register_success'])) {
        $success = $_SESSION['register_success'];
        unset($_SESSION['register_success']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Gourmet</title>
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

        .register-container {
            width: 100%;
            max-width: 460px;
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

        .register-box {
            background: #0a1914;
            padding: 45px 40px 40px;
            border-radius: 24px;
            border: 1px solid #1e4538;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
            position: relative;
            overflow: hidden;
        }
        .register-box::before {
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
        .register-box h2 {
            font-family: 'Playfair Display', serif;
            color: #D4AF37;
            text-align: center;
            font-size: 30px;
            letter-spacing: 1px;
        }
        .register-box .subtitle {
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
            align-items: flex-start;
            gap: 10px;
            line-height: 1.5;
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
        .msg-error i {
            margin-top: 2px;
        }
        .msg-success i {
            margin-top: 2px;
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
            .register-box {
                padding: 30px 20px 30px;
            }
            .register-box h2 {
                font-size: 24px;
            }
            .logo-icon {
                font-size: 36px;
            }
        }
    </style>
</head>
<body>

    <div class="register-container">
        <div class="register-box">

            <div class="logo-icon">👤</div>
            <h2>Create Account</h2>
            <p class="subtitle">Join Gourmet and start your dining journey</p>

            <!-- Error / Success Messages -->
            <?php if ($error): ?>
                <div class="msg-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="msg-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
                <div style="text-align:center; margin-top:10px;">
                    <a href="<?php echo BASE_URL; ?>public/login.php" style="color:#D4AF37; text-decoration:none; font-weight:600;">
                        <i class="fas fa-sign-in-alt"></i> Click here to login
                    </a>
                </div>
            <?php endif; ?>

            <?php if (!$success): // Only show form if not already successful ?>
            <form method="POST" action="<?php echo BASE_URL; ?>public/register.php">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <label><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                    <input type="text" name="name" placeholder="John Doe" 
                           value="<?php echo isset($form_data['name']) ? htmlspecialchars($form_data['name']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label>
                    <input type="email" name="email" placeholder="john@example.com" 
                           value="<?php echo isset($form_data['email']) ? htmlspecialchars($form_data['email']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone Number <span class="required">*</span></label>
                    <input type="text" name="phone" placeholder="0771234567" 
                           value="<?php echo isset($form_data['phone']) ? htmlspecialchars($form_data['phone']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password <span class="required">*</span></label>
                    <input type="password" name="password" placeholder="Min 6 characters" required minlength="6">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-check-circle"></i> Confirm Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" placeholder="Re-enter password" required minlength="6">
                </div>

                <button type="submit" name="register" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </form>

            <a href="<?php echo BASE_URL; ?>public/login.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Already have an account? Login
            </a>
            <?php endif; ?>

            <div class="footer-text">
                <i class="fas fa-crown"></i> Gourmet Restaurant Management System
            </div>

        </div>
    </div>

</body>
</html>