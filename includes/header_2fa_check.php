<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
if (!isset($_SESSION)) session_start();


// Modern Helper function for blocked page
function renderBlockLogoutPage($title, $message, $btn_text, $btn_link, $bg_gradient, $text_color){
    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>'.$title.'</title><style>
body{margin:0;padding:0;font-family:"Poppins",sans-serif;background:'.$bg_gradient.';color:'.$text_color.';display:flex;justify-content:center;align-items:center;height:100vh;text-align:center;}
.container{background:rgba(20,20,20,0.97);padding:50px 35px;border-radius:25px;box-shadow:0 0 50px rgba(255,215,0,0.4);max-width:500px;width:90%;animation:fadeIn 0.8s ease;backdrop-filter:blur(10px);position:relative;}
.alert-icon{font-size:60px;margin-bottom:20px;color:gold;animation:pulseIcon 1.5s infinite;}
.container h1{font-size:2.4em;margin-bottom:20px;color:gold;text-shadow:0 0 8px gold;animation:pulseText 2s infinite;font-weight:700;}
.container p{font-size:1.1em;line-height:1.6;margin-bottom:35px;color:#ccc;}
.button{display:inline-block;text-decoration:none;padding:14px 30px;font-weight:700;color:#111;background:linear-gradient(90deg,#FFD700,#FFA500,#FFD700);border-radius:12px;transition:0.3s;position:relative;overflow:hidden;box-shadow:0 0 25px rgba(255,215,0,0.4);}
.button:hover{transform:scale(1.08);box-shadow:0 0 40px rgba(255,215,0,0.8);background:linear-gradient(90deg,#FFA500,#FFD700,#FFA500);}
@keyframes fadeIn{from{opacity:0;transform:translateY(-20px);}to{opacity:1;transform:translateY(0);}}
@keyframes pulseText{0%,100%{text-shadow:0 0 8px gold;}50%{text-shadow:0 0 25px gold;}}
@keyframes pulseIcon{0%,100%{text-shadow:0 0 10px gold;}50%{text-shadow:0 0 30px gold;}}
@media(max-width:480px){.container{padding:35px 25px;}.alert-icon{font-size:45px;}.container h1{font-size:1.9em;}.container p{font-size:14px;}.button{font-size:14px;padding:12px 25px;}}
</style></head><body>
<div class="container">
<div class="alert-icon">⚠</div>
<h1>'.$title.'</h1>
<p>'.$message.'</p>
<a class="button" href="'.$btn_link.'">'.$btn_text.'</a>
</div>
</body></html>';
}

// Normal user checks
$user_id = $_SESSION['user_id'] ?? 0;
if($user_id){
    $stmt = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i",$user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // Blocked account
    if(!empty($user['status']) && $user['status']=='Blocked'){
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
        die(renderBlockLogoutPage(
            "⛔ Account Temporarily Blocked",
            "Your account has been temporarily blocked by <b>Umarae Security</b> due to suspicious activity or policy violations. All funds and data are safe, but Withdrawals, Deposits, Transfers, Wallet & Referrals are disabled. Please contact support immediately to resolve the issue.",
            "💬 Contact Support",
            "../includes/user_chat.php",
            "linear-gradient(135deg,#111,#333)",
            "#FFD700"
        ));
    }

    // 2FA enforcement
    if(!empty($user['twofa_enabled']) && (empty($_SESSION['2fa_verified']) || $_SESSION['2fa_verified']!==true)){
        header("Location: /2fa/verify_2fa.php"); 
        exit;
    }


 

// Admin impersonation check
if(!empty($_SESSION['impersonating']) && !empty($_SESSION['impersonated_user_id'])){
    $impersonated_id = (int)$_SESSION['impersonated_user_id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i",$impersonated_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    echo '<div style="
        position: fixed; top: 60px; left: 50%; transform: translateX(-50%);
        background: linear-gradient(90deg, #ffce00, #ff7f50); color: #222;
        padding: 15px 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        font-family: Arial, sans-serif; font-weight: bold; z-index: 9999; max-width: 90%;
        text-align: center; animation: slideDown 0.5s ease;">
        You are being impersonated by Admin ID '.htmlspecialchars($_SESSION['impersonator_admin_id']).'.
        <a href="/admin/stop_impersonate.php" style="background: #fff;color: #ff4d4d;padding: 5px 12px;border-radius: 6px;margin-left: 10px;text-decoration: none;font-weight: normal;">Exit impersonation</a>
    </div>
    <style>@keyframes slideDown {0% { transform: translate(-50%, -50px); opacity: 0; } 100% { transform: translate(-50%, 0); opacity: 1;}}</style>';

    $_SESSION['2fa_verified'] = true;
    return;
}



// Account restriction alert & selective link disable
if(!empty($user['restricted'])){
    echo '
    <div class="restricted-alert-box">
        <img src="/uqxmining/icons/icon-v2.png" alt="Umarae Logo">
        <h2>⚠ Account Restricted!</h2>
        <p>Your account has been temporarily restricted by <b>Umarae Security</b> due to suspicious activity or policy violations.<br>
        All withdrawals, deposits, transfers, Wallet & Referrals are <b>currently disabled</b>.</p>
        <div class="cta">
            <a href="/includes/user_chat.php" class="support-btn human-btn">💬 Contact Help Center</a>
        </div>
        <span id="close-alert" class="close-alert">✖</span>
    </div>

    <style>
    .restricted-alert-box {
        position: fixed; top: 60px; left: 50%; transform: translateX(-50%);
        background: rgba(12,18,30,0.97); border: 2px solid rgba(255,215,0,0.5);
        border-radius: 20px; box-shadow: 0 0 40px rgba(255,215,0,0.2);
        padding: 40px 25px; max-width: 420px; width: 90%; text-align: center;
        z-index: 99999; animation: fadeInAlert 0.8s ease-in-out;
        backdrop-filter: blur(10px); font-family: "Poppins", sans-serif;
        position: fixed; top: 60px;
    }
    .restricted-alert-box img {
        width: 80px; height: 80px; margin-bottom: 15px;
        animation: glowLogo 3s ease-in-out infinite;
    }
    @keyframes glowLogo {
        0%,100%{ filter: drop-shadow(0 0 8px gold);}
        50%{ filter: drop-shadow(0 0 20px gold);}
    }
    .restricted-alert-box h2 {
        color: gold; font-size: 22px; margin-bottom: 10px; font-weight: 700;
        animation: pulseText 2s infinite;
    }
    @keyframes pulseText {
        0%,100%{ text-shadow: 0 0 8px gold; }
        50%{ text-shadow: 0 0 18px gold; }
    }
    .restricted-alert-box p {
        color: #ccc; font-size: 15px; line-height:1.6; margin-bottom: 20px;
    }
    .cta { display:flex; justify-content:center; gap:12px; flex-wrap:wrap; }
    .support-btn {
        padding: 12px 22px; border-radius: 12px; font-weight:600; text-decoration:none; color:#000;
        transition:0.3s; display:inline-block; font-size:15px;
    }
    .human-btn { 
        background: linear-gradient(90deg, gold, #f6c300, #ffd700);
        box-shadow:0 0 20px rgba(255,215,0,0.4);
        animation: glowBtn 2s infinite alternate;
    }
    .human-btn:hover { transform: scale(1.07); box-shadow:0 0 30px rgba(255,215,0,0.7);}
    @keyframes glowBtn {
        0% { box-shadow: 0 0 12px rgba(255,215,0,0.4);}
        100% { box-shadow: 0 0 25px rgba(255,215,0,0.7);}
    }
    .close-alert { position:absolute; top:12px; right:16px; cursor:pointer; font-size:20px; color:#fff; font-weight:bold;}
    @keyframes fadeInAlert { from {opacity:0; transform: translate(-50%, -20px);} to {opacity:1; transform: translate(-50%,0);} }

    /* Disable links/buttons for restricted users */
    a.deposit-link, a.withdraw-link, a.referral-link, button {
        pointer-events:none; opacity:0.4; cursor:not-allowed;
    }

    /* Mobile responsive */
    @media (max-width:480px){
        .restricted-alert-box { padding: 25px 15px; max-width: 90%; }
        .restricted-alert-box img { width: 60px; height: 60px; }
        .restricted-alert-box h2 { font-size: 20px; }
        .restricted-alert-box p { font-size: 14px; }
        .support-btn { font-size: 14px; padding: 10px 18px; }
    }
    </style>

    <script>
    document.getElementById("close-alert").addEventListener("click", function(){
        this.parentElement.style.display="none";
    });
    </script>
    ';
}

// Allow dashboard once after login
if(!empty($_GET['allow_dashboard'])){
    $_SESSION['allow_dashboard'] = true;
    header("Location: ../user/sdashboard.php"); 
    exit;
}
}

?>
