<?php
session_start();
include 'db.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = $_POST['password'];

    // Email එක කලින් පාවිච්චි කරලද බලන්න
    $check_email = "SELECT id FROM customers WHERE email = '$email'";
    $res = mysqli_query($conn, $check_email);

    if (mysqli_num_rows($res) > 0) {
        $error = "This email is already registered!";
    } else {
        // Password එක සේෆ් විදිහට hash කිරීම
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        $sql = "INSERT INTO customers (name, email, phone, password) VALUES ('$name', '$email', '$phone', '$hashed_password')";
        if (mysqli_query($conn, $sql)) {
            $success = "Registration successful! You can now login.";
            header("refresh:2; url=login.php");
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Registration | Gourmet</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Playfair+Display:wght@700&display=swap');
        body { background: #050e0c; color: #fff; font-family: 'Poppins', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .auth-card { background: #0a1914; padding: 40px; border-radius: 20px; border: 1px solid #1e4538; width: 100%; max-width: 400px; box-shadow: 0 15px 30px rgba(0,0,0,0.5); }
        h2 { color: #D4AF37; font-family: 'Playfair Display', serif; text-align: center; margin-bottom: 30px; }
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; color: #b0c4b1; font-size: 13px; margin-bottom: 8px; text-transform: uppercase; }
        .input-group input { width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid #1e4538; border-radius: 8px; color: #fff; font-family: 'Poppins', sans-serif; box-sizing: border-box; }
        .input-group input:focus { outline: none; border-color: #D4AF37; background: rgba(255,255,255,0.1); }
        .btn { width: 100%; padding: 15px; background: #D4AF37; color: #0a1914; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; transition: 0.3s; font-size: 16px; margin-top: 10px; }
        .btn:hover { background: #fff; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4); }
        .msg { padding: 10px; border-radius: 8px; text-align: center; margin-bottom: 15px; font-size: 14px; }
        .error { background: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid #e74c3c; }
        .success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
        .link { text-align: center; margin-top: 20px; font-size: 14px; color: #b0c4b1; }
        .link a { color: #D4AF37; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="auth-card">
    <h2>Create Account</h2>
    <?php if(!empty($error)) echo "<div class='msg error'>$error</div>"; ?>
    <?php if(!empty($success)) echo "<div class='msg success'>$success</div>"; ?>
    
    <form method="POST" action="">
        <div class="input-group">
            <label>Full Name</label>
            <input type="text" name="name" required placeholder="John Doe">
        </div>
        <div class="input-group">
            <label>Email Address</label>
            <input type="email" name="email" required placeholder="john@example.com">
        </div>
        <div class="input-group">
            <label>Phone Number</label>
            <input type="text" name="phone" required placeholder="0771234567">
        </div>
        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="••••••••">
        </div>
        <button type="submit" class="btn">Register</button>
    </form>
    <div class="link">Already have an account? <a href="login.php">Login Now</a></div>
</div>
</body>
</html>