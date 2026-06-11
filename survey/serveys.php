<?php

// pages/surveys.php

ini_set('display_errors', 1);

error_reporting(E_ALL);

session_start();





// CPX Configuration

define('CPX_APP_ID', 30914);

define('CPX_SECRET', '...................);



// Login check

if (!isset($_SESSION['user_id'])) {

    header('Location: ../public/login.html');

    exit;

}

$user_id = (int)$_SESSION['user_id'];



// Get user data

$stmt = $conn->prepare("SELECT restricted, email, name, balance, total_earned FROM users WHERE id=? LIMIT 1");

$stmt->bind_param("i", $user_id);

$stmt->execute();

$stmt->bind_result($restricted, $user_email, $username, $balance, $total_earned);

$stmt->fetch();

$stmt->close();



// If restricted, redirect

if ($restricted) {

    die("You are restricted from viewing surveys.");

}



// Generate secure hash

$secure_hash = md5($user_id . '-' . CPX_SECRET);



// Get completed surveys count

$stmt = $conn->prepare("SELECT COUNT(*) FROM cpx_transactions WHERE user_id = ? AND status = 1");

$stmt->bind_param("i", $user_id);

$stmt->execute();

$stmt->bind_result($completed_count);

$stmt->fetch();

$stmt->close();



// Handle message_id for redirect messages

$message_id = isset($_GET['message_id']) ? (int)$_GET['message_id'] : 0;

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>UmarAe Surveys - Earn Rewards</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

* {

    margin: 0;

    padding: 0;

    box-sizing: border-box;

    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;

}



body {

    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

    min-height: 100vh;

    color: #2b2b2b;

}



header {

    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);

    padding: 0;

    color: white;

    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);

    position: relative;

    overflow: hidden;

}



header::before {

    content: '';

    position: absolute;

    top: 0;

    left: -100%;

    width: 200%;

    height: 100%;

    background: linear-gradient(90deg, 

        transparent, 

        rgba(255, 175, 32, 0.1), 

        transparent

    );

    animation: shimmer 3s infinite;

}



@keyframes shimmer {

    0% { left: -100%; }

    100% { left: 100%; }

}



.navbar-container {

    max-width: 1400px;

    margin: 0 auto;

    padding: 25px 40px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    position: relative;

    z-index: 10;

}



.logo-section {

    display: flex;

    align-items: center;

    gap: 15px;

}



.logo-icon {

    width: 60px;

    height: 60px;

    background: linear-gradient(135deg, #ffaf20 0%, #ff8c00 100%);

    border-radius: 15px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 32px;

    box-shadow: 0 4px 15px rgba(255, 175, 32, 0.4);

    animation: pulse 2s infinite;

}



@keyframes pulse {

    0%, 100% { transform: scale(1); box-shadow: 0 4px 15px rgba(255, 175, 32, 0.4); }

    50% { transform: scale(1.05); box-shadow: 0 6px 25px rgba(255, 175, 32, 0.6); }

}



.logo-text h1 {

    font-size: 32px;

    font-weight: 800;

    margin: 0;

    background: linear-gradient(135deg, #ffaf20 0%, #ffd700 100%);

    -webkit-background-clip: text;

    -webkit-text-fill-color: transparent;

    background-clip: text;

    letter-spacing: 1px;

}



.logo-text p {

    font-size: 13px;

    color: #aaa;

    margin: 5px 0 0 0;

    font-weight: 400;

}



.nav-links {

    display: flex;

    gap: 15px;

    align-items: center;

}



.nav-btn {

    color: white;

    text-decoration: none;

    font-size: 15px;

    padding: 12px 24px;

    border-radius: 30px;

    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);

    display: inline-flex;

    align-items: center;

    gap: 8px;

    font-weight: 600;

    position: relative;

    overflow: hidden;

}



.nav-btn::before {

    content: '';

    position: absolute;

    top: 50%;

    left: 50%;

    width: 0;

    height: 0;

    border-radius: 50%;

    background: rgba(255, 255, 255, 0.2);

    transition: width 0.6s, height 0.6s, top 0.6s, left 0.6s;

    transform: translate(-50%, -50%);

}



.nav-btn:hover::before {

    width: 300px;

    height: 300px;

}



.nav-btn i {

    font-size: 16px;

    transition: transform 0.3s ease;

}



.nav-btn:hover i {

    transform: translateX(-3px) rotate(-5deg);

}



.nav-btn.primary {

    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

    border: 2px solid transparent;

    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);

}



.nav-btn.primary:hover {

    transform: translateY(-2px);

    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);

}



.nav-btn.secondary {

    background: linear-gradient(135deg, #ffaf20 0%, #ff8c00 100%);

    border: 2px solid transparent;

    box-shadow: 0 4px 15px rgba(255, 175, 32, 0.4);

}



.nav-btn.secondary:hover {

    transform: translateY(-2px) scale(1.05);

    box-shadow: 0 8px 25px rgba(255, 175, 32, 0.6);

}



.nav-btn.outline {

    background: transparent;

    border: 2px solid rgba(255, 255, 255, 0.3);

    backdrop-filter: blur(10px);

}



.nav-btn.outline:hover {

    background: rgba(255, 255, 255, 0.1);

    border-color: rgba(255, 255, 255, 0.6);

    transform: translateY(-2px);

}



.mobile-menu-toggle {

    display: none;

    background: linear-gradient(135deg, #ffaf20 0%, #ff8c00 100%);

    border: none;

    padding: 12px 16px;

    border-radius: 10px;

    cursor: pointer;

    font-size: 24px;

    color: white;

    transition: all 0.3s ease;

}



.mobile-menu-toggle:hover {

    transform: rotate(90deg);

}



@media (max-width: 768px) {

    .navbar-container {

        padding: 20px 20px;

        flex-wrap: wrap;

    }

    

    .logo-icon {

        width: 50px;

        height: 50px;

        font-size: 24px;

    }

    

    .logo-text h1 {

        font-size: 24px;

    }

    

    .logo-text p {

        font-size: 11px;

    }

    

    .nav-links {

        display: none;

        width: 100%;

        flex-direction: column;

        gap: 10px;

        margin-top: 20px;

    }

    

    .nav-links.active {

        display: flex;

    }

    

    .nav-btn {

        width: 100%;

        justify-content: center;

    }

    

    .mobile-menu-toggle {

        display: block;

    }

}



.container {

    max-width: 1200px;

    margin: 40px auto;

    padding: 20px;

}



.stats-bar {

    background: white;

    border-radius: 15px;

    padding: 20px;

    margin-bottom: 30px;

    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);

    display: grid;

    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));

    gap: 20px;

}



.stat-item {

    text-align: center;

    padding: 20px;

    border-radius: 15px;

    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

    position: relative;

    overflow: hidden;

    transition: all 0.3s ease;

}



.stat-item::before {

    content: '';

    position: absolute;

    top: -50%;

    left: -50%;

    width: 200%;

    height: 200%;

    background: linear-gradient(45deg, 

        transparent, 

        rgba(255, 255, 255, 0.1), 

        transparent

    );

    transform: rotate(45deg);

    transition: all 0.5s ease;

}



.stat-item:hover::before {

    left: 100%;

}



.stat-item:hover {

    transform: translateY(-5px);

    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);

}



.stat-item i {

    font-size: 40px;

    color: white;

    margin-bottom: 15px;

    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));

}



.stat-item h3 {

    font-size: 36px;

    color: white;

    margin-bottom: 8px;

    font-weight: 800;

    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);

}



.stat-item p {

    color: rgba(255, 255, 255, 0.9);

    font-size: 14px;

    font-weight: 500;

    text-transform: uppercase;

    letter-spacing: 0.5px;

}



.intro-section {

    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);

    border-radius: 20px;

    padding: 40px;

    margin-bottom: 30px;

    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);

    text-align: center;

    border: 1px solid rgba(255, 175, 32, 0.2);

    position: relative;

    overflow: hidden;

}



.intro-section::before {

    content: '';

    position: absolute;

    top: 0;

    left: 0;

    right: 0;

    height: 4px;

    background: linear-gradient(90deg, #ffaf20, #ff8c00, #667eea, #764ba2, #ffaf20);

    background-size: 200% 100%;

    animation: gradient-shift 3s linear infinite;

}



@keyframes gradient-shift {

    0% { background-position: 0% 50%; }

    100% { background-position: 200% 50%; }

}



.intro-section h2 {

    color: #1a1a2e;

    margin-bottom: 20px;

    font-size: 28px;

    font-weight: 800;

    position: relative;

    display: inline-block;

}



.intro-section h2::after {

    content: '';

    position: absolute;

    bottom: -10px;

    left: 50%;

    transform: translateX(-50%);

    width: 60px;

    height: 4px;

    background: linear-gradient(90deg, #ffaf20, #ff8c00);

    border-radius: 2px;

}



.intro-section p {

    color: #555;

    line-height: 1.8;

    font-size: 16px;

    max-width: 800px;

    margin: 20px auto 0;

}



.intro-section strong {

    color: #ffaf20;

    font-weight: 700;

}



.fullwidth-section {

    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);

    border-radius: 20px;

    padding: 40px;

    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);

    margin-bottom: 30px;

    border: 1px solid rgba(255, 175, 32, 0.2);

    position: relative;

    overflow: hidden;

}



.fullwidth-section::before {

    content: '';

    position: absolute;

    top: 0;

    left: 0;

    right: 0;

    height: 5px;

    background: linear-gradient(90deg, #ffaf20, #ff8c00, #667eea, #764ba2);

    background-size: 200% 100%;

    animation: gradient-shift 3s linear infinite;

}



.fullwidth-section h2 {

    color: #1a1a2e;

    margin-bottom: 25px;

    font-size: 28px;

    text-align: center;

    font-weight: 800;

    position: relative;

    display: inline-block;

    width: 100%;

}



.fullwidth-section h2 i {

    color: #ffaf20;

    margin-right: 10px;

    filter: drop-shadow(0 2px 4px rgba(255, 175, 32, 0.3));

}



#fullscreen {

    min-height: 400px;

    margin-top: 15px;

}



.surveys-grid {

    display: grid;

    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));

    gap: 25px;

    margin-bottom: 30px;

}



.card {

    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);

    border-radius: 20px;

    padding: 30px;

    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);

    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);

    position: relative;

    overflow: hidden;

    border: 1px solid rgba(0, 0, 0, 0.05);

}



.card::before {

    content: '';

    position: absolute;

    top: 0;

    left: 0;

    width: 100%;

    height: 5px;

    background: linear-gradient(90deg, #ffaf20, #ff8c00, #667eea);

    background-size: 200% 100%;

    animation: gradient-shift 3s linear infinite;

}



.card::after {

    content: '';

    position: absolute;

    top: 50%;

    left: 50%;

    width: 0;

    height: 0;

    background: radial-gradient(circle, rgba(255, 175, 32, 0.1) 0%, transparent 70%);

    border-radius: 50%;

    transform: translate(-50%, -50%);

    transition: width 0.6s ease, height 0.6s ease;

}



.card:hover::after {

    width: 500px;

    height: 500px;

}



.card:hover {

    transform: translateY(-10px) scale(1.02);

    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);

    border-color: rgba(255, 175, 32, 0.3);

}



.card h2 {

    margin-bottom: 15px;

    color: #1a1a2e;

    font-size: 24px;

    display: flex;

    align-items: center;

    gap: 12px;

    font-weight: 700;

    position: relative;

    z-index: 1;

}



.card h2 i {

    font-size: 28px;

    color: #ffaf20;

    background: linear-gradient(135deg, #ffaf20 0%, #ff8c00 100%);

    -webkit-background-clip: text;

    -webkit-text-fill-color: transparent;

    background-clip: text;

    filter: drop-shadow(0 2px 4px rgba(255, 175, 32, 0.3));

}



.card p {

    margin-bottom: 20px;

    color: #666;

    line-height: 1.6;

    position: relative;

    z-index: 1;

}



#sidebar {

    height: 450px;

    margin-top: 15px;

}



#single {

    width: 100%;

    height: 180px;

    margin-top: 15px;

}



footer {

    text-align: center;

    padding: 25px 0;

    background: linear-gradient(135deg, #2b2b2b 0%, #1a1a1a 100%);

    color: white;

    margin-top: 50px;

}



footer p {

    margin-bottom: 10px;

}



footer a {

    color: #ffaf20;

    text-decoration: none;

    transition: all 0.3s ease;

}



footer a:hover {

    color: #ff8c00;

}



@media (max-width: 768px) {

    .surveys-grid {

        grid-template-columns: 1fr;

    }

    

    header h1 {

        font-size: 22px;

    }

    

    header a {

        font-size: 14px;

        padding: 6px 12px;

        margin: 5px;

    }

}



.success-message {

    background: #d4edda;

    border: 1px solid #c3e6cb;

    color: #155724;

    padding: 15px;

    border-radius: 10px;

    margin-bottom: 20px;

    display: none;

}



.success-message.show {

    display: block;

}

</style>

</head>

<body>



<header>

    <div class="navbar-container">

        <div class="logo-section">

            <div class="logo-icon">

                <i class="fas fa-poll-h"></i>

            </div>

            <div class="logo-text">

                <h1>UmarAe Surveys</h1>

                <p>Earn rewards with every opinion</p>

            </div>

        </div>

        

        <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">

            <i class="fas fa-bars"></i>

        </button>

        

        <nav class="nav-links" id="navLinks">

            <a href="dashboard.php" class="nav-btn primary">

                <i class="fas fa-tachometer-alt"></i>

                Dashboard

            </a>

            <a href="https://wall.cpx-research.com/index.php?app_id=<?php echo CPX_APP_ID; ?>&ext_user_id=<?php echo $user_id; ?>&secure_hash=<?php echo $secure_hash; ?>&username=<?php echo urlencode($username); ?>&email=<?php echo urlencode($user_email); ?>" target="_blank" class="nav-btn secondary">

                <i class="fas fa-external-link-alt"></i>

                Full Survey Wall

            </a>

            <a href="guide.php" class="nav-btn outline">

                <i class="fa-solid fa-user"></i> 

                Guidelines

            </a>

        </nav>

    </div>

</header>



<script>

function toggleMobileMenu() {

    const navLinks = document.getElementById('navLinks');

    navLinks.classList.toggle('active');

}

</script>



<div class="container">

    

    <!-- Stats Bar -->

    <div class="stats-bar">

        <div class="stat-item">

            <i class="fas fa-clipboard-list"></i>

            <h3 id="survey-count">0</h3>

            <p>Available Surveys</p>

        </div>

        <div class="stat-item">

            <i class="fas fa-coins"></i>

            <h3><?php echo number_format($balance, 0); ?></h3>

            <p>Current Balance (Coins)</p>

        </div>

        <div class="stat-item">

            <i class="fas fa-trophy"></i>

            <h3><?php echo number_format($total_earned, 0); ?></h3>

            <p>Total Earned (Coins)</p>

        </div>

        <div class="stat-item">

            <i class="fas fa-check-circle"></i>

            <h3><?php echo $completed_count; ?></h3>

            <p>Completed Surveys</p>

        </div>

    </div>



    <!-- Success Message -->

    <div id="success-message" class="success-message">

        <i class="fas fa-check-circle"></i> Survey completed successfully! Your reward has been added.

    </div>



    <!-- Intro Section -->

    <div class="intro-section">

        <h2><i class="fas fa-star"></i> Welcome to Umarae Research Surveys</h2>

        <p>Complete surveys to earn coins! Exchange rate: <strong>$1 USD = 1000 Coins</strong>. All surveys are verified and safe. Your opinions help shape products worldwide!</p>

    </div>



    <!-- Full Content Widget Section -->

    <div class="fullwidth-section">

        <h2><i class="fas fa-trophy"></i> Featured High-Paying Surveys</h2>

        <div id="fullscreen"></div>

    </div>



    <!-- Grid Layout for Multiple Widgets -->

    <div class="surveys-grid">

        

        <!-- Multi Sidebar Widget -->

        <div class="card">

            <h2><i class="fas fa-list"></i> Quick Surveys</h2>

            <p>Fast and easy surveys that take just a few minutes to complete.</p>

            <div id="sidebar"></div>

        </div>



        <!-- Single Sidebar Widget -->

        <div class="card">

            <h2><i class="fas fa-bolt"></i> Featured Survey</h2>

            <p>Top recommended survey with the best rewards!</p>

            <div id="single"></div>

        </div>

        

    </div>



</div>



<!-- Notification Widgets (Hidden) -->

<div id="notification"></div>

<div id="notification2"></div>



<!-- Message ID Frame for Redirect Messages -->

<?php if ($message_id > 0): ?>

<iframe style="display:none;" src="https://wall.cpx-research.com/index.php?app_id=<?php echo CPX_APP_ID; ?>&ext_user_id=<?php echo $user_id; ?>&secure_hash=<?php echo $secure_hash; ?>&username=<?php echo urlencode($username); ?>&email=<?php echo urlencode($user_email); ?>&message_id=<?php echo $message_id; ?>"></iframe>

<?php endif; ?>



<!-- CPX Research Configuration Script -->

<script>

// CPX Configuration

const script1 = {

    div_id: "fullscreen",

    theme_style: 1,

    order_by: 2, // Sort by best money

    limit_surveys: 10

};



const script2 = {

    div_id: "sidebar",

    theme_style: 2,

    order_by: 1

};



const script3 = {

    div_id: "single",

    theme_style: 3,

    display_mode: 1

};



const script4 = {

    div_id: "notification",

    theme_style: 4,

    position: 5, // Bottom right

    text: "",

    link: "",

    newtab: true

};



const script5 = {

    div_id: "notification2",

    theme_style: 4,

    position: 6, // Bottom center

    text: "New surveys available! Click to earn coins!",

    link: "https://wall.cpx-research.com/index.php?app_id=<?php echo CPX_APP_ID; ?>&ext_user_id=<?php echo $user_id; ?>&secure_hash=<?php echo $secure_hash; ?>&username=<?php echo urlencode($username); ?>&email=<?php echo urlencode($user_email); ?>",

    newtab: true

};



// Main Configuration

const config = {

    general_config: {

        app_id: <?php echo CPX_APP_ID; ?>,

        ext_user_id: "<?php echo $user_id; ?>",

        email: "<?php echo $user_email; ?>",

        username: "<?php echo $username; ?>",

        secure_hash: "<?php echo $secure_hash; ?>",

        subid_1: "web",

        subid_2: "<?php echo date('Y-m-d'); ?>"

    },

    style_config: {

        text_color: "#2b2b2b",

        survey_box: {

            topbar_background_color: "#ffaf20",

            box_background_color: "white",

            rounded_borders: true,

            stars_filled: "#ffaf20"

        }

    },

    script_config: [script1, script2, script3, script4, script5],

    debug: false, // Set to true for debugging

    useIFrame: true,

    iFramePosition: 1,

    functions: {

        no_surveys_available: () => {

            console.log("⚠️ No surveys available");

            document.getElementById('survey-count').textContent = '0';

        },

        count_new_surveys: (count) => {

            console.log("✅ Surveys available:", count);

            document.getElementById('survey-count').textContent = count;

        },

        get_all_surveys: (surveys) => {

            console.log("📋 All surveys:", surveys);

            if (surveys && surveys.length > 0) {

                surveys.forEach((s, i) => {

                    console.log(`Survey ${i+1}:`, {

                        id: s.id,

                        payout: s.payout_usd + ' USD',

                        time: s.loi + ' min',

                        rate: s.conversion_rate + '%'

                    });

                });

            }

        },

        get_transaction: (trans) => {

            console.log("💰 Transaction:", trans);

            // Show success message

            const msg = document.getElementById('success-message');

            if (msg) {

                msg.classList.add('show');

                setTimeout(() => {

                    msg.classList.remove('show');

                    // Reload to update balance

                    window.location.reload();

                }, 3000);

            }

        }

    }

};



window.config = config;

</script>



<!-- CPX Research Main Script -->

<script type="text/javascript" src="https://cdn.cpx-research.com/assets/js/script_tag_v2.0.js"></script>



<footer>

    <p>&copy; 2026 UmarAe. All rights reserved.</p>

    

</footer>



</body>

</html>
