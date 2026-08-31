<?php
// ============================================================
// DATABASE CONNECTION - BASE_URL already defined in db.php
// ============================================================
require_once __DIR__ . "/../includes/db.php";

// 🔥 BASE_URL දැනටමත් db.php එකේ define කරලා තියෙනවා
// ඒ නිසා අපි නැවත define කරන්නේ නැහැ
// අවශ්‍ය නම්, එය use කරන්න පුළුවන්: <?php echo BASE_URL; ?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | Gourmet Fine Dining</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ============================================================
                   GLOBAL RESET & BASE
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
            overflow-x: hidden;
        }

        /* ============================================================
                   NAVIGATION (Consistent with other pages)
                   ============================================================ */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 50px;
            background: #0a1914;
            border-bottom: 2px solid #1e4538;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .logo {
            font-size: 26px;
            font-weight: 700;
            color: #D4AF37;
            letter-spacing: 2px;
        }
        nav ul {
            display: flex;
            list-style: none;
            gap: 30px;
        }
        nav ul li a {
            text-decoration: none;
            color: #b0c4b1;
            font-weight: 500;
            transition: 0.3s;
            font-size: 15px;
            position: relative;
        }
        nav ul li a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: #D4AF37;
            transition: width 0.3s ease;
        }
        nav ul li a:hover::after,
        nav ul li a.active::after {
            width: 100%;
        }
        nav ul li a:hover,
        nav ul li a.active {
            color: #D4AF37;
        }

        /* ============================================================
                   HERO SECTION (Parallax)
                   ============================================================ */
        .hero {
            position: relative;
            height: 65vh;
            min-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: url('https://images.unsplash.com/photo-1559339352-11d035aa65de?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') no-repeat center center/cover;
            background-attachment: fixed;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(5, 14, 12, 0.7);
            backdrop-filter: blur(2px);
        }
        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            padding: 20px;
            animation: fadeInUp 1.2s ease-out;
        }
        .hero-content h1 {
            font-family: 'Playfair Display', serif;
            font-size: 60px;
            color: #D4AF37;
            text-shadow: 0 4px 30px rgba(212, 175, 55, 0.3);
            margin-bottom: 15px;
            letter-spacing: 3px;
        }
        .hero-content p {
            font-size: 20px;
            color: #b0c4b1;
            font-weight: 300;
            line-height: 1.7;
            max-width: 600px;
            margin: 0 auto;
        }
        .hero-content .breadcrumb {
            margin-top: 20px;
            font-size: 14px;
            color: #888;
        }
        .hero-content .breadcrumb a {
            color: #D4AF37;
            text-decoration: none;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ============================================================
                   MAIN CONTAINER
                   ============================================================ */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 20px;
        }

        /* ============================================================
                   SECTION TITLES
                   ============================================================ */
        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }
        .section-title h2 {
            font-family: 'Playfair Display', serif;
            font-size: 40px;
            color: #D4AF37;
            letter-spacing: 2px;
            position: relative;
            display: inline-block;
        }
        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: #D4AF37;
        }
        .section-title p {
            color: #888;
            font-size: 16px;
            margin-top: 20px;
        }

        /* ============================================================
                   STORY & MISSION (Grid)
                   ============================================================ */
        .story-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 30px;
        }
        .story-card {
            background: #0a1914;
            padding: 40px 30px;
            border-radius: 20px;
            border: 1px solid #1e4538;
            transition: all 0.4s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        .story-card:hover {
            transform: translateY(-8px);
            border-color: #D4AF37;
            box-shadow: 0 15px 40px rgba(212, 175, 55, 0.1);
        }
        .story-card .icon {
            font-size: 40px;
            color: #D4AF37;
            margin-bottom: 15px;
        }
        .story-card h3 {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            color: #D4AF37;
            margin-bottom: 12px;
        }
        .story-card p {
            color: #b0c4b1;
            line-height: 1.7;
            font-size: 15px;
        }

        /* ============================================================
                   STATS COUNTER
                   ============================================================ */
        .stats-section {
            background: #0a1914;
            padding: 60px 20px;
            border-radius: 20px;
            border: 1px solid #1e4538;
            margin: 50px 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            text-align: center;
        }
        .stat-item {
            padding: 20px;
        }
        .stat-item .number {
            font-size: 48px;
            font-weight: 700;
            color: #D4AF37;
            display: block;
            font-family: 'Playfair Display', serif;
        }
        .stat-item .label {
            font-size: 16px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 5px;
        }

        /* ============================================================
                   TEAM SECTION
                   ============================================================ */
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .team-card {
            background: #0a1914;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #1e4538;
            transition: 0.4s ease;
            text-align: center;
            padding: 30px 20px;
        }
        .team-card:hover {
            transform: translateY(-10px);
            border-color: #D4AF37;
            box-shadow: 0 15px 40px rgba(212, 175, 55, 0.1);
        }
        .team-card img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #D4AF37;
            margin-bottom: 15px;
            background: #1e4538;
        }
        .team-card h4 {
            font-size: 20px;
            color: #fff;
            font-weight: 600;
        }
        .team-card .role {
            color: #D4AF37;
            font-size: 14px;
            font-weight: 400;
            margin: 5px 0;
        }
        .team-card p {
            color: #888;
            font-size: 13px;
            line-height: 1.5;
            margin-top: 8px;
        }

        /* ============================================================
                   FEATURES (Icon Cards)
                   ============================================================ */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .feature-item {
            background: #0a1914;
            padding: 30px 20px;
            border-radius: 16px;
            border: 1px solid #1e4538;
            text-align: center;
            transition: 0.3s ease;
        }
        .feature-item:hover {
            transform: translateY(-5px);
            border-color: #D4AF37;
            background: rgba(212, 175, 55, 0.05);
        }
        .feature-item i {
            font-size: 40px;
            color: #D4AF37;
            margin-bottom: 15px;
        }
        .feature-item h3 {
            font-size: 18px;
            color: #fff;
            margin-bottom: 5px;
        }
        .feature-item p {
            color: #888;
            font-size: 14px;
        }

        /* ============================================================
                   CTA SECTION
                   ============================================================ */
        .cta-section {
            background: linear-gradient(135deg, #0f1f18, #0a1914);
            padding: 50px 30px;
            border-radius: 20px;
            text-align: center;
            border: 1px solid #1e4538;
            margin: 50px 0 20px;
        }
        .cta-section h2 {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            color: #D4AF37;
            margin-bottom: 15px;
        }
        .cta-section p {
            color: #aaa;
            font-size: 16px;
            margin-bottom: 25px;
        }
        .btn-cta {
            display: inline-block;
            padding: 14px 40px;
            background: #D4AF37;
            color: #0a1914;
            border-radius: 50px;
            font-weight: 700;
            text-decoration: none;
            transition: 0.3s;
            font-size: 16px;
            border: none;
            cursor: pointer;
        }
        .btn-cta:hover {
            background: #fff;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3);
        }

        /* ============================================================
                   FOOTER
                   ============================================================ */
        footer {
            background: #0a1914;
            border-top: 2px solid #1e4538;
            padding: 30px 20px;
            text-align: center;
            margin-top: 40px;
        }
        footer .socials {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 15px;
        }
        footer .socials a {
            color: #b0c4b1;
            font-size: 22px;
            transition: 0.3s;
        }
        footer .socials a:hover {
            color: #D4AF37;
            transform: translateY(-3px);
        }
        footer p {
            color: #555;
            font-size: 14px;
        }
        footer p i {
            color: #D4AF37;
        }

        /* ============================================================
                   RESPONSIVE
                   ============================================================ */
        @media (max-width: 992px) {
            .story-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            .hero-content h1 {
                font-size: 44px;
            }
            nav {
                padding: 15px 25px;
                flex-wrap: wrap;
            }
            nav ul {
                gap: 15px;
                flex-wrap: wrap;
                justify-content: center;
            }
            nav ul li a {
                font-size: 13px;
            }
        }

        @media (max-width: 600px) {
            .hero {
                height: 50vh;
                min-height: 300px;
            }
            .hero-content h1 {
                font-size: 32px;
            }
            .hero-content p {
                font-size: 16px;
            }
            .section-title h2 {
                font-size: 30px;
            }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            .stat-item .number {
                font-size: 32px;
            }
            .team-grid {
                grid-template-columns: 1fr 1fr;
            }
            .container {
                padding: 30px 15px;
            }
            .cta-section h2 {
                font-size: 26px;
            }
            .btn-cta {
                padding: 12px 30px;
                font-size: 14px;
            }
        }

        @media (max-width: 400px) {
            .team-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ============================================================
                   SCROLL ANIMATION CLASSES (for JS)
                   ============================================================ */
        .hidden {
            opacity: 0;
            transform: translateY(40px);
            transition: all 0.8s ease;
        }
        .visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>

    <!-- ============================================================
    NAVIGATION
    ============================================================ -->
    <nav>
        <div class="logo">GOURMET</div>
        <ul>
            <li><a href="<?php echo BASE_URL; ?>public/index.php">Home</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/about.php" class="active">About</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/contact.php">Contact</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/reservation.php">Reservation</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/menu.php">Menu</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/offers.php">Offers</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/rate.php"><i class="fas fa-star" style="color:#D4AF37;"></i> Rate Us</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/notifications.php">Notifications</a></li>
            <li><a href="<?php echo BASE_URL; ?>public/profile.php">Profile</a></li>
            <li><a href="#">bak</a></li> <!-- 'bak' as shown in the image -->
            <li><a href="<?php echo BASE_URL; ?>public/logout.php">Logout</a></li>
        </ul>
    </nav>

    <!-- ============================================================
    HERO SECTION
    ============================================================ -->
    <section class="hero">
        <div class="hero-content">
            <h1>About Gourmet</h1>
            <p>Where culinary artistry meets unforgettable dining experiences.</p>
            <div class="breadcrumb">
                <a href="<?php echo BASE_URL; ?>public/index.php">Home</a> <span style="color:#555;">/</span> About
            </div>
        </div>
    </section>

    <!-- ============================================================
    MAIN CONTENT
    ============================================================ -->
    <div class="container">

        <!-- ===== STORY & MISSION ===== -->
        <div class="section-title">
            <h2>Our Story</h2>
            <p>Discover the passion behind every plate.</p>
        </div>

        <div class="story-grid">
            <div class="story-card hidden">
                <div class="icon"><i class="fas fa-seedling"></i></div>
                <h3>Our Journey</h3>
                <p>Founded in 2015, Gourmet began with a simple belief: that great food brings people together. From a small family kitchen to a renowned fine-dining destination, we have stayed true to our roots — using only the freshest, locally sourced ingredients and time-honored recipes.</p>
            </div>
            <div class="story-card hidden">
                <div class="icon"><i class="fas fa-flag"></i></div>
                <h3>Our Mission</h3>
                <p>We strive to create memorable dining experiences that celebrate the rich tapestry of global cuisine. Every dish is crafted with precision, passion, and a commitment to excellence. Our mission is to delight your senses and leave you with a lasting impression.</p>
            </div>
        </div>

        <!-- ===== STATS COUNTER ===== -->
        <div class="stats-section">
            <div class="stats-grid">
                <div class="stat-item hidden">
                    <span class="number" data-target="12">0</span>
                    <span class="label">Years of Excellence</span>
                </div>
                <div class="stat-item hidden">
                    <span class="number" data-target="150">0</span>
                    <span class="label">Signature Dishes</span>
                </div>
                <div class="stat-item hidden">
                    <span class="number" data-target="15">0</span>
                    <span class="label">Awards Won</span>
                </div>
                <div class="stat-item hidden">
                    <span class="number" data-target="5000">0</span>
                    <span class="label">Happy Guests</span>
                </div>
            </div>
        </div>

        <!-- ===== TEAM SECTION ===== -->
        <div class="section-title">
            <h2>Meet Our Team</h2>
            <p>The talented people behind your dining experience.</p>
        </div>

        <div class="team-grid">
            <div class="team-card hidden">
                <img src="https://images.unsplash.com/photo-1557862921-37829c3f9ae8?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80" alt="Chef">
                <h4>Chef Marco</h4>
                <div class="role">Executive Chef</div>
                <p>25 years of culinary expertise, trained in Michelin-starred kitchens.</p>
            </div>
            <div class="team-card hidden">
                <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80" alt="Manager">
                <h4>Sarah Johnson</h4>
                <div class="role">Restaurant Manager</div>
                <p>Passionate about hospitality, ensuring every guest feels welcomed.</p>
            </div>
            <div class="team-card hidden">
                <img src="https://images.unsplash.com/photo-1583394293214-28ded15ee548?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80" alt="Sous Chef">
                <h4>David Chen</h4>
                <div class="role">Sous Chef</div>
                <p>Creative innovator, specializing in fusion cuisine and presentation.</p>
            </div>
            <div class="team-card hidden">
                <img src="https://images.unsplash.com/photo-1551836022-d5d88e9218df?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80" alt="Pastry Chef">
                <h4>Elena Rossi</h4>
                <div class="role">Pastry Chef</div>
                <p>Artisan of desserts, turning simple ingredients into edible art.</p>
            </div>
        </div>

        <!-- ===== FEATURES ===== -->
        <div class="section-title" style="margin-top:60px;">
            <h2>Why Choose Us</h2>
            <p>Exceptional quality, service, and ambiance.</p>
        </div>

        <div class="features-grid">
            <div class="feature-item hidden">
                <i class="fas fa-utensils"></i>
                <h3>Exquisite Cuisine</h3>
                <p>Menus crafted with seasonal, locally-sourced ingredients.</p>
            </div>
            <div class="feature-item hidden">
                <i class="fas fa-wine-glass-alt"></i>
                <h3>Curated Wine List</h3>
                <p>Over 200 labels from the world's finest vineyards.</p>
            </div>
            <div class="feature-item hidden">
                <i class="fas fa-crown"></i>
                <h3>Elegant Ambiance</h3>
                <p>A sophisticated setting perfect for any occasion.</p>
            </div>
            <div class="feature-item hidden">
                <i class="fas fa-star"></i>
                <h3>Exceptional Service</h3>
                <p>Professional staff dedicated to your comfort.</p>
            </div>
        </div>

        <!-- ===== CTA ===== -->
        <div class="cta-section hidden">
            <h2>Ready to Dine?</h2>
            <p>Book your table now and experience the art of fine dining.</p>
            <a href="<?php echo BASE_URL; ?>public/reservation.php" class="btn-cta">Reserve a Table</a>
        </div>

    </div>

    <!-- ============================================================
    FOOTER
    ============================================================ -->
    <footer>
        <div class="socials">
            <a href="#"><i class="fab fa-facebook-f"></i></a>
            <a href="#"><i class="fab fa-instagram"></i></a>
            <a href="#"><i class="fab fa-twitter"></i></a>
            <a href="#"><i class="fab fa-youtube"></i></a>
        </div>
        <p><i class="fas fa-crown"></i> Gourmet Restaurant &mdash; Where every dish tells a story.</p>
        <p style="margin-top:5px; font-size:12px; color:#333;">&copy; 2025 Gourmet. All rights reserved.</p>
    </footer>

    <!-- ============================================================
    JAVASCRIPT - SCROLL ANIMATIONS & COUNTER
    ============================================================ -->
    <script>
        // ============================================================
        // 1. SCROLL REVEAL (Intersection Observer)
        // ============================================================
        const hiddenElements = document.querySelectorAll('.hidden');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    entry.target.classList.remove('hidden');
                }
            });
        }, {
            threshold: 0.15
        });

        hiddenElements.forEach(el => observer.observe(el));

        // ============================================================
        // 2. COUNTER ANIMATION (Stats)
        // ============================================================
        const counters = document.querySelectorAll('.number');

        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const target = parseInt(entry.target.getAttribute('data-target'));
                    let current = 0;
                    const increment = Math.ceil(target / 80);
                    const updateCounter = () => {
                        if (current < target) {
                            current += increment;
                            if (current > target) current = target;
                            entry.target.textContent = current;
                            requestAnimationFrame(updateCounter);
                        }
                    };
                    updateCounter();
                    counterObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });

        counters.forEach(counter => counterObserver.observe(counter));
    </script>

</body>
</html>