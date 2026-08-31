<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gourmet Restaurant | Welcome</title>
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
            color: #fff;
            height: 100vh;
            overflow: hidden;
            background: #050e0c;
        }

        /* ============================================================
                   VIDEO BACKGROUND
                   ============================================================ */
        .video-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .video-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.40) saturate(0.9);
        }

        /* Fallback overlay if video doesn't load */
        .video-container .fallback {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #050e0c 0%, #0a1914 50%, #1a2a1f 100%);
            z-index: 0;
        }

        /* ============================================================
                   OVERLAY CONTENT
                   ============================================================ */
        .overlay {
            position: relative;
            z-index: 1;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 20px;
            animation: fadeIn 1.5s ease forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .brand-icon {
            font-size: 80px;
            color: #D4AF37;
            margin-bottom: 10px;
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }

        /* ============================================================
                   🔥 GOURMET - GOLD THEME (Updated)
                   ============================================================ */
        .brand-title {
            font-family: 'Playfair Display', serif;
            font-size: 82px;
            font-weight: 700;
            letter-spacing: 6px;
            background: linear-gradient(180deg, #f5e56b 0%, #D4AF37 40%, #b8962e 70%, #8a6f1f 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 40px rgba(212, 175, 55, 0.25), 0 0 80px rgba(212, 175, 55, 0.10);
            position: relative;
            display: inline-block;
        }

        /* Gold underline decoration */
        .brand-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 3px;
            background: linear-gradient(90deg, transparent, #D4AF37, transparent);
            border-radius: 2px;
        }

        .brand-subtitle {
            font-size: 20px;
            font-weight: 300;
            color: #b0c4b1;
            margin-top: 18px;
            letter-spacing: 8px;
            text-transform: uppercase;
        }

        .brand-tagline {
            font-size: 16px;
            color: #888;
            margin-top: 18px;
            max-width: 500px;
            line-height: 1.7;
        }

        .brand-tagline span {
            color: #D4AF37;
            font-weight: 600;
        }

        /* ============================================================
                   START BUTTON
                   ============================================================ */
        .btn-start {
            margin-top: 45px;
            padding: 18px 60px;
            background: linear-gradient(135deg, #D4AF37, #b8962e);
            color: #0a1914;
            border: none;
            border-radius: 50px;
            font-size: 20px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s ease;
            box-shadow: 0 8px 30px rgba(212, 175, 55, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 12px;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
        }

        .btn-start::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .btn-start:hover::before {
            left: 100%;
        }

        .btn-start:hover {
            background: #fff;
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 12px 40px rgba(212, 175, 55, 0.5);
        }

        .btn-start i {
            font-size: 22px;
            transition: transform 0.3s ease;
        }

        .btn-start:hover i {
            transform: translateX(6px);
        }

        /* ============================================================
                   ROLE MODAL
                   ============================================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: modalFadeIn 0.4s ease;
        }

        .modal-overlay.active {
            display: flex;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-box {
            background: #0a1914;
            padding: 50px 45px 40px;
            border-radius: 24px;
            border: 1px solid #1e4538;
            max-width: 480px;
            width: 90%;
            text-align: center;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.6);
            position: relative;
            overflow: hidden;
        }

        .modal-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #D4AF37, transparent);
        }

        .modal-box .modal-icon {
            font-size: 48px;
            color: #D4AF37;
            margin-bottom: 10px;
        }

        .modal-box h2 {
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            color: #D4AF37;
            margin-bottom: 6px;
        }

        .modal-box .modal-desc {
            color: #888;
            font-size: 15px;
            margin-bottom: 30px;
        }

        .role-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .role-card {
            background: #111;
            padding: 30px 18px;
            border-radius: 16px;
            border: 2px solid #1e4538;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .role-card:hover {
            border-color: #D4AF37;
            transform: translateY(-6px);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.15);
            background: #0f1f18;
        }

        .role-card .role-icon {
            font-size: 40px;
            color: #D4AF37;
        }

        .role-card .role-name {
            font-size: 18px;
            font-weight: 600;
        }

        .role-card .role-desc {
            font-size: 12px;
            color: #666;
        }

        .role-card.admin .role-icon {
            color: #D4AF37;
        }

        .role-card.user .role-icon {
            color: #2ecc71;
        }

        .role-card.user:hover {
            border-color: #2ecc71;
        }

        .role-card.user:hover .role-icon {
            color: #2ecc71;
        }

        .modal-close {
            margin-top: 20px;
            background: transparent;
            border: none;
            color: #555;
            font-size: 14px;
            cursor: pointer;
            transition: 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        .modal-close:hover {
            color: #D4AF37;
        }

        /* ============================================================
                   FOOTER
                   ============================================================ */
        .footer-text {
            position: absolute;
            bottom: 25px;
            left: 0;
            width: 100%;
            text-align: center;
            color: #444;
            font-size: 13px;
            z-index: 1;
            letter-spacing: 1px;
        }

        .footer-text i {
            color: #D4AF37;
        }

        /* ============================================================
                   RESPONSIVE
                   ============================================================ */
        @media (max-width: 768px) {
            .brand-title {
                font-size: 48px;
                letter-spacing: 4px;
            }
            .brand-title::after {
                width: 80px;
                bottom: -6px;
            }
            .brand-subtitle {
                font-size: 15px;
                letter-spacing: 5px;
            }
            .brand-icon {
                font-size: 56px;
            }
            .brand-tagline {
                font-size: 14px;
                padding: 0 15px;
            }
            .btn-start {
                padding: 15px 40px;
                font-size: 17px;
            }
            .modal-box {
                padding: 35px 25px 30px;
            }
            .modal-box h2 {
                font-size: 24px;
            }
            .role-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .role-card {
                padding: 20px 16px;
                flex-direction: row;
                justify-content: center;
                gap: 15px;
            }
            .role-card .role-icon {
                font-size: 28px;
            }
            .footer-text {
                font-size: 11px;
                bottom: 15px;
            }
        }

        @media (max-width: 480px) {
            .brand-title {
                font-size: 34px;
                letter-spacing: 2px;
            }
            .brand-title::after {
                width: 60px;
                bottom: -4px;
            }
            .brand-subtitle {
                font-size: 12px;
                letter-spacing: 3px;
            }
            .brand-icon {
                font-size: 40px;
            }
            .btn-start {
                padding: 14px 32px;
                font-size: 15px;
                margin-top: 30px;
            }
            .btn-start i {
                font-size: 18px;
            }
            .modal-box {
                padding: 28px 18px 25px;
            }
            .modal-box h2 {
                font-size: 20px;
            }
            .role-card .role-name {
                font-size: 15px;
            }
        }
    </style>
</head>
<body>

    <!-- ============================================================
    VIDEO BACKGROUND
    ============================================================ -->
    <div class="video-container">
        <!-- 🎥 Local Video File (assets/bg.mp4) -->
        <video autoplay muted loop playsinline poster="" id="bgVideo">
            <!-- 🔥 ඔබගේ assets folder එකේ තියෙන bg.mp4 -->
            <source src="assets/bg.mp4" type="video/mp4">
            
            <!-- 🎥 Online Fallback Videos -->
            <source src="https://videos.pexels.com/video-files/3184345/3184345-uhd_2560_1440_25fps.mp4" type="video/mp4">
            <source src="https://videos.pexels.com/video-files/3117076/3117076-uhd_2560_1440_24fps.mp4" type="video/mp4">
            
            <!-- Fallback for unsupported browsers -->
            <div class="fallback"></div>
        </video>
        <!-- Fallback overlay (video load නොවුනොත්) -->
        <div class="fallback" style="display:block;" id="fallbackBg"></div>
    </div>

    <!-- ============================================================
    OVERLAY CONTENT
    ============================================================ -->
    <div class="overlay">
        <div class="brand-icon">🍽️</div>
        <h1 class="brand-title">GOURMET</h1>
        <p class="brand-subtitle">Fine Dining Experience</p>
        <p class="brand-tagline">
            <span>✦</span> Where every dish tells a story <span>✦</span><br>
            Indulge in the art of exquisite cuisine.
        </p>

        <!-- START BUTTON -->
        <button class="btn-start" id="startBtn">
            <span>Start Your Journey</span>
            <i class="fas fa-arrow-right"></i>
        </button>

        <div class="footer-text">
            <i class="fas fa-crown"></i> Gourmet Restaurant Management System v2.0
        </div>
    </div>

    <!-- ============================================================
    ROLE SELECTION MODAL
    ============================================================ -->
    <div class="modal-overlay" id="roleModal">
        <div class="modal-box">
            <div class="modal-icon">👤</div>
            <h2>Select Your Role</h2>
            <p class="modal-desc">Choose how you want to access the system</p>

            <div class="role-grid">
                <!-- Admin -->
                <a href="admin/admin_login.php" class="role-card admin">
                    <div class="role-icon"><i class="fas fa-user-shield"></i></div>
                    <div>
                        <div class="role-name">Admin</div>
                        <div class="role-desc">Manage restaurant</div>
                    </div>
                </a>

                <!-- User / Customer -->
                <a href="public/index.php" class="role-card user">
                    <div class="role-icon"><i class="fas fa-user"></i></div>
                    <div>
                        <div class="role-name">User</div>
                        <div class="role-desc">Browse &amp; order</div>
                    </div>
                </a>
            </div>

            <button class="modal-close" id="closeModal">
                <i class="fas fa-times-circle"></i> Close
            </button>
        </div>
    </div>

    <!-- ============================================================
    JAVASCRIPT
    ============================================================ -->
    <script>
        // ============================================================
        // 1. SHOW MODAL ON START BUTTON CLICK
        // ============================================================
        const startBtn = document.getElementById('startBtn');
        const roleModal = document.getElementById('roleModal');
        const closeModal = document.getElementById('closeModal');

        startBtn.addEventListener('click', function() {
            roleModal.classList.add('active');
        });

        // ============================================================
        // 2. CLOSE MODAL
        // ============================================================
        closeModal.addEventListener('click', function() {
            roleModal.classList.remove('active');
        });

        roleModal.addEventListener('click', function(e) {
            if (e.target === roleModal) {
                roleModal.classList.remove('active');
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                roleModal.classList.remove('active');
            }
        });

        // ============================================================
        // 3. VIDEO BACKGROUND - හරියට වැඩ කරන ක්‍රමය
        // ============================================================
        const video = document.getElementById('bgVideo');
        const fallbackBg = document.getElementById('fallbackBg');

        // Start with fallback hidden
        fallbackBg.style.display = 'none';

        // Function to show fallback
        function showFallback() {
            video.style.display = 'none';
            fallbackBg.style.display = 'block';
        }

        // Try to play video
        function tryPlayVideo() {
            video.play().then(function() {
                // Video playing successfully
                console.log('Video playing');
            }).catch(function(error) {
                console.log('Autoplay blocked:', error);
                // User needs to interact - we'll try on click
            });
        }

        // If video loads successfully
        video.addEventListener('canplaythrough', function() {
            tryPlayVideo();
        });

        // If video has error
        video.addEventListener('error', function(e) {
            console.log('Video error:', e);
            // Try next source (online fallback)
            const sources = video.querySelectorAll('source');
            let found = false;
            
            for (let i = 0; i < sources.length; i++) {
                if (sources[i].src.includes('pexels.com') && !found) {
                    video.src = sources[i].src;
                    video.load();
                    found = true;
                    break;
                }
            }
            
            // If no online fallback works, show gradient
            if (!found) {
                showFallback();
            }
        });

        // Timeout - if video doesn't load in 5 seconds, show fallback
        setTimeout(function() {
            if (video.readyState === 0 || video.readyState === 1) {
                // Video hasn't loaded enough
                console.log('Video loading timeout - showing fallback');
                // Try online fallback first
                const sources = video.querySelectorAll('source');
                let found = false;
                for (let i = 0; i < sources.length; i++) {
                    if (sources[i].src.includes('pexels.com') && !found) {
                        video.src = sources[i].src;
                        video.load();
                        found = true;
                        break;
                    }
                }
                if (!found) {
                    showFallback();
                }
            }
        }, 5000);

        // Try to play when user clicks anywhere (for autoplay restrictions)
        document.addEventListener('click', function() {
            if (video.paused) {
                tryPlayVideo();
            }
        });

        // Also try on start button click
        startBtn.addEventListener('click', function() {
            if (video.paused) {
                tryPlayVideo();
            }
        });

        // ============================================================
        // 4. FIX FOR SAFARI / iOS
        // ============================================================
        // Safari needs user interaction to play video
        if (navigator.userAgent.indexOf('Safari') !== -1) {
            document.addEventListener('touchstart', function() {
                if (video.paused) {
                    tryPlayVideo();
                }
            }, { once: true });
        }
    </script>

</body>
</html>