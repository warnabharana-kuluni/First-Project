<?php
// ============================================================
// CONTACT PAGE - PREMIUM REDESIGN
// ============================================================
// All functions working: Database insert, Validation, Clean UI
// ============================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Session & Base URL setup for navigation ---
session_start();
if (!defined('BASE_URL')) {
    define('BASE_URL', '/restaurant_project/');
}

// Database Connection
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'restaurant_db';

$conn = mysqli_connect($host, $user, $password, $database);
if (!$conn) {
    die("❌ Database connection failed: " . mysqli_connect_error());
}

// Handle Form Submission
$success = '';
$error = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_msg'])) {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    $form_data = compact('name', 'email', 'subject', 'message');

    $errors = [];
    if (empty($name) || strlen($name) < 2) $errors[] = 'Please enter your full name (min. 2 characters).';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (empty($message) || strlen($message) < 10) $errors[] = 'Message must be at least 10 characters.';

    if (empty($errors)) {
        $name_safe    = mysqli_real_escape_string($conn, $name);
        $email_safe   = mysqli_real_escape_string($conn, $email);
        $subject_safe = mysqli_real_escape_string($conn, $subject);
        $message_safe = mysqli_real_escape_string($conn, $message);

        $insert_query = "INSERT INTO messages (name, email, subject, message, notification_type, status, created_at) 
                         VALUES ('$name_safe', '$email_safe', '$subject_safe', '$message_safe', 'customer_message', 'unread', NOW())";

        if (mysqli_query($conn, $insert_query)) {
            $success = '✅ Your message has been sent successfully! We will get back to you shortly.';
            $form_data = [];
        } else {
            $error = '❌ Failed to save message. Please try again.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Gourmet Restaurant</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ============================================================
                   ROOT VARIABLES
                   ============================================================ */
        :root {
            --gold: #C9A84C;
            --gold-light: #E8D5A3;
            --gold-dark: #A8892E;
            --dark-primary: #0A0F0E;
            --dark-secondary: #141C19;
            --dark-card: rgba(20, 28, 25, 0.92);
            --text-primary: #F5F0E8;
            --text-secondary: #B8B0A0;
            --text-muted: #6A6A5E;
            --border-gold: rgba(201, 168, 76, 0.25);
            --border-glow: rgba(201, 168, 76, 0.15);
            --shadow-gold: rgba(201, 168, 76, 0.3);
            --radius: 20px;
            --transition: 0.5s cubic-bezier(0.22, 1, 0.36, 1);
        }

        /* ============================================================
                   RESET & BASE
                   ============================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark-primary);
            color: var(--text-primary);
            line-height: 1.7;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        ::selection {
            background: var(--gold);
            color: var(--dark-primary);
        }

        /* ============================================================
                   ANIMATIONS
                   ============================================================ */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes shimmer {
            0% {
                background-position: -200% center;
            }
            100% {
                background-position: 200% center;
            }
        }

        @keyframes floatGlow {
            0%,
            100% {
                box-shadow: 0 0 30px rgba(201, 168, 76, 0.1);
            }
            50% {
                box-shadow: 0 0 60px rgba(201, 168, 76, 0.25);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-40px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(40px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulseRing {
            0% {
                transform: scale(1);
                opacity: 0.7;
            }
            100% {
                transform: scale(1.5);
                opacity: 0;
            }
        }

        .animate-on-scroll {
            opacity: 0;
            transform: translateY(40px);
            transition: all 0.8s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ============================================================
                   SCROLLBAR
                   ============================================================ */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--dark-secondary);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--gold);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--gold-dark);
        }

        /* ============================================================
                   NAVIGATION (replaced with user's provided code)
                   ============================================================ */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 60px;
            background: rgba(5, 14, 12, 0.95);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .logo {
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            font-weight: 800;
            color: var(--gold);
            letter-spacing: 3px;
            text-shadow: 0 0 10px rgba(212, 175, 55, 0.5);
            animation: glowPulse 3s infinite;
        }
        nav ul {
            display: flex;
            list-style: none;
            gap: 35px;
            flex-wrap: wrap;
            align-items: center;
        }
        nav ul a {
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 15px;
            position: relative;
            padding: 8px 0;
            transition: color 0.3s;
            white-space: nowrap;
        }
        nav ul a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gold);
            transition: width 0.3s ease;
        }
        nav ul a:hover,
        nav ul a.active {
            color: var(--gold);
        }
        nav ul a:hover::after,
        nav ul a.active::after {
            width: 100%;
        }

        /* ============================================================
                   HERO SECTION
                   ============================================================ */
        .hero {
            position: relative;
            height: 50vh;
            min-height: 380px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
            background: var(--dark-primary);
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            background:
                url('https://images.unsplash.com/photo-1514362545857-3bc16c4c7d1b?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') no-repeat center center/cover;
            opacity: 0.35;
            transform: scale(1.05);
            transition: transform 8s ease;
        }

        .hero:hover .hero-bg {
            transform: scale(1);
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse at center, rgba(10, 15, 14, 0.3) 0%, rgba(10, 15, 14, 0.85) 100%);
        }

        /* Decorative gold lines */
        .hero-lines {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
        }
        .hero-lines::before,
        .hero-lines::after {
            content: '';
            position: absolute;
            width: 200%;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            opacity: 0.2;
        }
        .hero-lines::before {
            top: 20%;
            left: -50%;
            transform: rotate(-5deg);
        }
        .hero-lines::after {
            bottom: 20%;
            left: -50%;
            transform: rotate(5deg);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            padding: 0 20px;
            animation: fadeInUp 1s ease forwards;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(201, 168, 76, 0.15);
            border: 1px solid var(--border-gold);
            padding: 6px 24px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 500;
            color: var(--gold-light);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }

        .hero-content h1 {
            font-family: 'Playfair Display', serif;
            font-size: 72px;
            font-weight: 900;
            color: #fff;
            line-height: 1.1;
            margin-bottom: 12px;
            text-shadow: 0 4px 30px rgba(0, 0, 0, 0.5);
        }

        .hero-content h1 .highlight {
            background: linear-gradient(135deg, var(--gold), var(--gold-light), var(--gold));
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer 4s linear infinite;
        }

        .hero-content p {
            font-size: 20px;
            font-weight: 300;
            color: var(--text-secondary);
            max-width: 560px;
            margin: 0 auto;
            letter-spacing: 0.5px;
        }

        .hero-scroll-indicator {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
            animation: floatGlow 3s infinite;
        }
        .hero-scroll-indicator i {
            font-size: 20px;
            color: var(--gold);
            opacity: 0.6;
            animation: floatGlow 2s infinite;
        }

        /* ============================================================
                   MAIN CONTAINER
                   ============================================================ */
        .main-wrapper {
            max-width: 1300px;
            margin: -60px auto 0;
            padding: 0 30px 60px;
            position: relative;
            z-index: 3;
        }

        /* ============================================================
                   ALERT NOTIFICATIONS
                   ============================================================ */
        .alert {
            padding: 20px 28px;
            border-radius: var(--radius);
            margin-bottom: 40px;
            display: flex;
            align-items: flex-start;
            gap: 18px;
            backdrop-filter: blur(20px);
            animation: fadeInUp 0.6s ease;
            border: 1px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .alert::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: var(--radius);
            padding: 1px;
            background: linear-gradient(135deg, transparent, rgba(255, 255, 255, 0.05), transparent);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }

        .alert i {
            font-size: 28px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.12);
            border-color: rgba(46, 204, 113, 0.25);
            color: #2ecc71;
        }
        .alert-success i {
            color: #2ecc71;
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.12);
            border-color: rgba(231, 76, 60, 0.25);
            color: #e74c3c;
        }
        .alert-error i {
            color: #e74c3c;
        }

        .alert .alert-content {
            flex: 1;
        }
        .alert .alert-content strong {
            display: block;
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 2px;
        }
        .alert .alert-content p {
            font-weight: 400;
            opacity: 0.9;
            font-size: 15px;
        }

        /* ============================================================
                   CONTACT GRID
                   ============================================================ */
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 50px;
            align-items: start;
        }

        /* ============================================================
                   GLASS CARD
                   ============================================================ */
        .glass-card {
            background: var(--dark-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-gold);
            border-radius: var(--radius);
            padding: 48px 44px;
            box-shadow:
                0 25px 60px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.03);
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            opacity: 0.4;
        }

        .glass-card:hover {
            transform: translateY(-6px);
            box-shadow:
                0 35px 80px rgba(0, 0, 0, 0.6),
                0 0 40px rgba(201, 168, 76, 0.05);
            border-color: rgba(201, 168, 76, 0.4);
        }

        .glass-card .card-badge {
            display: inline-block;
            background: rgba(201, 168, 76, 0.1);
            border: 1px solid var(--border-gold);
            padding: 4px 16px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            color: var(--gold);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 14px;
        }

        .glass-card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 34px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 8px;
            line-height: 1.2;
        }

        .glass-card .subtitle {
            color: var(--text-secondary);
            font-size: 15px;
            margin-bottom: 30px;
            font-weight: 300;
        }

        /* ============================================================
                   INFO ITEMS
                   ============================================================ */
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 16px 18px;
            border-radius: 14px;
            transition: all 0.3s ease;
            cursor: default;
        }

        .info-item:hover {
            background: rgba(201, 168, 76, 0.05);
            transform: translateX(6px);
        }

        .info-icon {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(201, 168, 76, 0.1);
            border: 1px solid rgba(201, 168, 76, 0.15);
            color: var(--gold);
            font-size: 20px;
            transition: all 0.4s ease;
        }

        .info-item:hover .info-icon {
            background: var(--gold);
            color: var(--dark-primary);
            transform: scale(1.05) rotate(-3deg);
            box-shadow: 0 10px 30px rgba(201, 168, 76, 0.3);
        }

        .info-details h4 {
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 2px;
            letter-spacing: 0.3px;
        }

        .info-details p,
        .info-details a {
            font-size: 14px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .info-details a:hover {
            color: var(--gold);
        }

        /* ============================================================
                   SOCIAL LINKS
                   ============================================================ */
        .social-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid var(--border-gold);
        }

        .social-section .label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 14px;
            display: block;
        }

        .social-links {
            display: flex;
            gap: 12px;
        }

        .social-links a {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            font-size: 18px;
            transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
            text-decoration: none;
        }

        .social-links a:hover {
            background: var(--gold);
            color: var(--dark-primary);
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 12px 30px rgba(201, 168, 76, 0.35);
            border-color: var(--gold);
        }

        /* ============================================================
                   FORM STYLES
                   ============================================================ */
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }

        .form-group label .required {
            color: #e74c3c;
            font-size: 16px;
        }

        .form-group .input-wrap {
            position: relative;
        }

        .form-group .input-wrap .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 15px;
            transition: color 0.3s ease;
            pointer-events: none;
        }

        .form-group .input-wrap textarea~.input-icon {
            top: 22px;
            transform: none;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px 18px 14px 48px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 14px;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            transition: all 0.4s ease;
            outline: none;
        }

        .form-group textarea {
            padding: 16px 18px 16px 48px;
            min-height: 140px;
            resize: vertical;
            line-height: 1.6;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--gold);
            background: rgba(201, 168, 76, 0.04);
            box-shadow: 0 0 0 4px rgba(201, 168, 76, 0.06), 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .form-group input:focus~.input-icon,
        .form-group textarea:focus~.input-icon {
            color: var(--gold);
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: var(--text-muted);
            font-weight: 300;
            font-size: 14px;
        }

        /* ============================================================
                   SUBMIT BUTTON
                   ============================================================ */
        .submit-btn {
            width: 100%;
            padding: 16px 28px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--dark-primary);
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-top: 6px;
            position: relative;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), transparent);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .submit-btn:hover::before {
            opacity: 1;
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(201, 168, 76, 0.4);
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
        }

        .submit-btn:active {
            transform: translateY(0px) scale(0.98);
        }

        .submit-btn i {
            transition: transform 0.4s ease;
            font-size: 18px;
        }

        .submit-btn:hover i {
            transform: translateX(6px) rotate(-6deg);
        }

        /* ============================================================
                   MAP SECTION
                   ============================================================ */
        .map-section {
            margin-top: 60px;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.6);
            border: 1px solid var(--border-gold);
            transition: all var(--transition);
            position: relative;
        }

        .map-section:hover {
            border-color: rgba(201, 168, 76, 0.5);
            box-shadow: 0 40px 90px rgba(0, 0, 0, 0.7), 0 0 60px rgba(201, 168, 76, 0.05);
        }

        .map-section iframe {
            width: 100%;
            height: 380px;
            border: none;
            display: block;
            filter: grayscale(0.3) contrast(1.05);
            transition: filter 0.6s ease;
        }

        .map-section:hover iframe {
            filter: grayscale(0) contrast(1);
        }

        /* ============================================================
                   FOOTER
                   ============================================================ */
        .footer {
            background: rgba(5, 10, 8, 0.98);
            border-top: 1px solid var(--border-gold);
            padding: 50px 20px 30px;
            text-align: center;
            margin-top: 40px;
        }

        .footer .footer-brand {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(--gold);
            font-weight: 800;
            letter-spacing: 4px;
        }

        .footer .footer-brand span {
            color: var(--text-muted);
            font-weight: 300;
            font-size: 14px;
            letter-spacing: 2px;
            font-family: 'Inter', sans-serif;
        }

        .footer p {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 12px;
            letter-spacing: 0.5px;
        }

        .footer .footer-divider {
            width: 60px;
            height: 2px;
            background: var(--gold);
            margin: 16px auto;
            opacity: 0.4;
            border-radius: 2px;
        }

        /* ============================================================
                   RESPONSIVE
                   ============================================================ */
        @media (max-width: 1200px) {
            .contact-grid {
                gap: 40px;
            }
        }

        @media (max-width: 1024px) {
            nav {
                padding: 14px 30px;
            }
            nav ul {
                gap: 20px;
            }
            .contact-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            .hero-content h1 {
                font-size: 52px;
            }
            .glass-card {
                padding: 36px 30px;
            }
            .main-wrapper {
                padding: 0 20px 40px;
            }
        }

        @media (max-width: 768px) {
            nav {
                padding: 12px 20px;
                flex-wrap: wrap;
            }
            .logo {
                font-size: 22px;
            }
            nav ul {
                gap: 12px;
                margin-top: 10px;
                justify-content: center;
                width: 100%;
            }
            nav ul a {
                font-size: 13px;
                white-space: nowrap;
            }
            .hero {
                height: 40vh;
                min-height: 300px;
            }
            .hero-content h1 {
                font-size: 38px;
            }
            .hero-content p {
                font-size: 16px;
            }
            .hero-badge {
                font-size: 11px;
                padding: 4px 16px;
            }
            .hero-scroll-indicator {
                display: none;
            }
            .main-wrapper {
                margin-top: -40px;
                padding: 0 16px 30px;
            }
            .glass-card {
                padding: 28px 20px;
            }
            .glass-card h2 {
                font-size: 28px;
            }
            .info-item {
                padding: 12px 14px;
            }
            .form-group input,
            .form-group textarea {
                padding: 12px 14px 12px 42px;
                font-size: 14px;
            }
            .form-group textarea {
                min-height: 110px;
            }
            .submit-btn {
                font-size: 14px;
                padding: 14px 20px;
            }
            .map-section iframe {
                height: 250px;
            }
            .alert {
                padding: 16px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .alert i {
                font-size: 22px;
            }
            .footer .footer-brand {
                font-size: 22px;
            }
        }

        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 30px;
            }
            .hero-content p {
                font-size: 14px;
            }
            .glass-card h2 {
                font-size: 24px;
            }
            .social-links a {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            .info-icon {
                width: 40px;
                height: 40px;
                min-width: 40px;
                font-size: 17px;
            }
            nav ul {
                gap: 8px;
            }
            nav ul a {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>

    <!-- ============================================================
    NAVIGATION (replaced with user's provided code)
    ============================================================ -->
    <nav>
        <div class="logo">GOURMET</div>
        <ul>
            <li><a href="<?php echo BASE_URL; ?>public/index.php">Home</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/about.php">About</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/contact.php" class="active">Contact</a></li>
            <li>
                <?php if (isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id'])): ?>
                    <a href="<?php echo BASE_URL; ?>public/reservation.php">Reservation</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>public/login.php?redirect=reservation">Reservation</a>
                <?php endif; ?>
            </li>
            <li><a href="<?php echo BASE_URL; ?>public/menu.php">Menu</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/offers.php">Offers</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/rate.php"><i class="fas fa-star" style="color:#D4AF37;"></i> Rate Us</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/notifications.php">Notifications</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
            <!-- Invoices link removed as requested -->
            <?php if (isset($_SESSION['customer_id']) && isset($_SESSION['res_name'])): ?>
                <li><a href="#" class="user-greeting"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars(explode(' ', $_SESSION['res_name'])[0]); ?></a></li>
                <li><a href="<?php echo BASE_URL; ?>public/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php else: ?>
                <li><a href="<?php echo BASE_URL; ?>public/login.php" class="login-link"><i class="fas fa-sign-in-alt"></i> Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- ============================================================
    HERO
    ============================================================ -->
    <section class="hero">
        <div class="hero-bg"></div>
        <div class="hero-overlay"></div>
        <div class="hero-lines"></div>

        <div class="hero-content">
            <div class="hero-badge">
                <i class="fas fa-envelope" style="margin-right:8px;"></i> Get in Touch
            </div>
            <h1>
                Let's <span class="highlight">Connect</span>
            </h1>
            <p>We'd love to hear from you. Whether you have a question, feedback, or just want to say hello.</p>
        </div>

        <div class="hero-scroll-indicator">
            <span>Scroll</span>
            <i class="fas fa-chevron-down"></i>
        </div>
    </section>

    <!-- ============================================================
    MAIN
    ============================================================ -->
    <div class="main-wrapper">

        <!-- ===== ALERTS ===== -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div class="alert-content">
                    <strong>Message Sent!</strong>
                    <p><?php echo $success; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div class="alert-content">
                    <strong>Oops! Something went wrong</strong>
                    <p><?php echo $error; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- ===== CONTACT GRID ===== -->
        <div class="contact-grid">

            <!-- LEFT: Info -->
            <div class="glass-card animate-on-scroll" style="animation-delay:0.1s;">
                <div class="card-badge">
                    <i class="fas fa-address-card" style="margin-right:6px;"></i> Contact Details
                </div>
                <h2>Reach Out</h2>
                <p class="subtitle">We're here to help and answer any questions you might have.</p>

                <div class="info-list">
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-map-pin"></i></div>
                        <div class="info-details">
                            <h4>Our Address</h4>
                            <p>123 Gourmet Lane, Colombo 07, Sri Lanka</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-phone-alt"></i></div>
                        <div class="info-details">
                            <h4>Phone</h4>
                            <a href="tel:+94112345678">+94 11 234 5678</a>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-envelope"></i></div>
                        <div class="info-details">
                            <h4>Email</h4>
                            <a href="mailto:info@gourmet.lk">info@gourmet.lk</a>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-clock"></i></div>
                        <div class="info-details">
                            <h4>Opening Hours</h4>
                            <p>Mon – Sat: 11:00 AM – 11:00 PM<br>Sunday: Closed</p>
                        </div>
                    </div>
                </div>

                <div class="social-section">
                    <span class="label"><i class="fas fa-share-alt" style="margin-right:8px;"></i> Follow Us</span>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                        <a href="#" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Form -->
            <div class="glass-card animate-on-scroll" style="animation-delay:0.2s;">
                <div class="card-badge">
                    <i class="fas fa-paper-plane" style="margin-right:6px;"></i> Send a Message
                </div>
                <h2>Get in Touch</h2>
                <p class="subtitle">Fill in the form and we'll respond within 24 hours.</p>

                <form method="POST" action="" id="contactForm" novalidate>
                    <div class="form-group">
                        <label>
                            Your Name
                            <span class="required">*</span>
                        </label>
                        <div class="input-wrap">
                            <input type="text" name="name" placeholder="e.g. John Doe"
                            value="<?php echo isset($form_data['name']) ? htmlspecialchars($form_data['name']) : ''; ?>"
                            required>
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            Email Address
                            <span class="required">*</span>
                        </label>
                        <div class="input-wrap">
                            <input type="email" name="email" placeholder="e.g. john@example.com"
                            value="<?php echo isset($form_data['email']) ? htmlspecialchars($form_data['email']) : ''; ?>"
                            required>
                            <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Subject</label>
                        <div class="input-wrap">
                            <input type="text" name="subject" placeholder="e.g. Reservation Inquiry"
                            value="<?php echo isset($form_data['subject']) ? htmlspecialchars($form_data['subject']) : ''; ?>">
                            <span class="input-icon"><i class="fas fa-tag"></i></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            Your Message
                            <span class="required">*</span>
                        </label>
                        <div class="input-wrap">
                            <textarea name="message" placeholder="Write your message here..." required><?php echo isset($form_data['message']) ? htmlspecialchars($form_data['message']) : ''; ?></textarea>
                            <span class="input-icon"><i class="fas fa-pencil-alt"></i></span>
                        </div>
                    </div>

                    <button type="submit" name="send_msg" class="submit-btn">
                        <i class="fas fa-paper-plane"></i>
                        Send Message
                    </button>
                </form>
            </div>
        </div>

        <!-- ===== MAP ===== -->
        <div class="map-section animate-on-scroll">
            <iframe
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d63371.80392249164!2d79.81547632167967!3d6.921837391333491!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae253d10f7a7003%3A0x320b2e4a32e0d36!2sColombo%2007%2C%20Colombo!5e0!3m2!1sen!2slk!4v1689000000000"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
        title="Gourmet Restaurant Location">
    </iframe>
</div>
</div>

<!-- ============================================================
FOOTER
============================================================ -->
<footer class="footer">
    <div class="footer-brand">
        GOURMET <span>✦ fine dining</span>
    </div>
    <div class="footer-divider"></div>
    <p>&copy; 2025 Gourmet Restaurant. All rights reserved.</p>
    <p style="font-size:12px; margin-top:6px; color:#4a4a40;">
        <i class="fas fa-crown" style="color:var(--gold);"></i>
        Where every dish tells a story.
    </p>
</footer>

<!-- ============================================================
SCRIPTS
============================================================ -->
<script>
    // Intersection Observer for scroll animations
    const observerOptions = {
        threshold: 0.15,
        rootMargin: '0px 0px -40px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, observerOptions);

    document.querySelectorAll('.animate-on-scroll').forEach(el => {
        observer.observe(el);
    });

    // Form auto-focus on first empty field
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('contactForm');
        if (form) {
            const inputs = form.querySelectorAll('input, textarea');
            for (let input of inputs) {
                if (input.hasAttribute('required') && !input.value.trim()) {
                    input.focus();
                    break;
                }
            }
        }
    });
</script>

</body>
</html>