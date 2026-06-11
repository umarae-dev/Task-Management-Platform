<?php
// =======================
// ✅ ALLOW SESSION ON ALL SUBDOMAINS
// =======================
ini_set('session.cookie_domain', '.umarae.com');

session_start();


// =======================
// ✅ INPUTS
// =======================
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';


if (empty($email) || empty($password)) {
    header("Location: ../public/login.html?error=" . urlencode("Please fill all fields."));
    exit;
}

// =======================
// ✅ FETCH USER
// =======================
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if (!$user = $result->fetch_assoc()) {
    header("Location: ../public/login.html?error=" . urlencode("User not found."));
    exit;
}

// =======================
// ✅ VERIFY PASSWORD
// =======================
if (!password_verify($password, $user['password'])) {
    header("Location: ../public/login.html?error=" . urlencode("Incorrect password or email."));
    exit;
}

// =======================
// ✅ SET SESSION
// =======================
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = ucwords(strtolower($user['name']));

// =======================
// ✅ REMEMBER TOKEN
// =======================
$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + (15 * 24 * 60 * 60));
$insert = $conn->prepare("INSERT INTO login_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
$insert->bind_param("iss", $user['id'], $token, $expires);
$insert->execute();
setcookie("remember_token", $token, time() + (15 * 24 * 60 * 60), "/", "", false, true);

// =======================
// ✅ DEVICE HASH
// =======================
$device_hash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
$existing_device = $conn->query("SELECT * FROM user_devices WHERE user_id={$user['id']} AND device_hash='$device_hash' LIMIT 1")->fetch_assoc();

// =======================
// ✅ SEND ALERT IF NEW DEVICE
// =======================
if (!$existing_device) {
    // Save device
    $stmt = $conn->prepare("INSERT INTO user_devices (user_id, device_hash) VALUES (?, ?)");
    $stmt->bind_param("is", $user['id'], $device_hash);
    $stmt->execute();
    $stmt->close();

    // Send alert email
    sendNewDeviceAlert($user['name'], $user['email'], $device_hash);
}

// =======================
// ✅ 2FA CHECK
// =======================
if ($user['twofa_enabled'] == 1 && !empty($user['twofa_secret'])) {
    $_SESSION['twofa_secret'] = $user['twofa_secret'];
    $_SESSION['2fa_verified'] = false;
    header("Location: ../2fa/verify_2fa.php");
    exit;
} else {
    $_SESSION['2fa_verified'] = true;
    setcookie("trusted_2fa", "yes", time() + (15 * 24 * 60 * 60), "/", "", false, true);
    header("Location: ../user/dashboard.php");
    exit;
}

// =======================
// ✅ FUNCTION: SEND NEW DEVICE ALERT WITH GEO
// =======================
function sendNewDeviceAlert($user_name, $user_email, $device_hash) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $time = date('d M Y H:i:s');

    // ===================
    // Geolocation API
    // ===================
    $geo = @json_decode(file_get_contents("http://ip-api.com/json/{$ip}"));
    $city = $geo->city ?? 'Unknown City';
    $country = $geo->country ?? 'Unknown Country';

    // Browser & OS
    $browser = "Unknown Browser";
    $os = "Unknown OS";
    if (preg_match('/MSIE|Trident/', $user_agent)) $browser = 'Internet Explorer';
    elseif (preg_match('/Firefox/', $user_agent)) $browser = 'Firefox';
    elseif (preg_match('/Chrome/', $user_agent)) $browser = 'Chrome';
    elseif (preg_match('/Safari/', $user_agent)) $browser = 'Safari';
    elseif (preg_match('/Opera/', $user_agent)) $browser = 'Opera';
    elseif (preg_match('/Edge/', $user_agent)) $browser = 'Edge';
    
    if (preg_match('/Linux/', $user_agent)) $os = 'Linux';
    elseif (preg_match('/Mac/', $user_agent)) $os = 'Mac';
    elseif (preg_match('/Windows/', $user_agent)) $os = 'Windows';
    elseif (preg_match('/Android/', $user_agent)) $os = 'Android';
    elseif (preg_match('/iPhone|iPad|iPod/', $user_agent)) $os = 'iOS';

    // Device type
    $device_type = 'Desktop';
    if (preg_match('/Mobile|Android|iPhone/', $user_agent)) $device_type = 'Mobile';
    if (preg_match('/iPad|Tablet/', $user_agent)) $device_type = 'Tablet';

    $subject = "🚨 New Login Detected on Your Account!";
    $body = "
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: 'Inter', sans-serif; background: #f4f7fa; margin:0; padding:0; }
            .container { max-width: 700px; margin: 40px auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 40px rgba(0,0,0,0.15); }
            .header { background: linear-gradient(135deg, #ff6b6b, #ff8e53); color: #fff; text-align: center; padding: 30px 15px; }
            .header img { max-width: 120px; margin-bottom: 15px; }
            .header h1 { margin:0; font-size:26px; font-weight:700; }
            .content { padding: 30px 25px; color:#333; line-height:1.7; }
            .content h2 { font-size: 22px; margin-bottom: 15px; color: #d32f2f; }
            .content p { font-size:16px; margin-bottom:12px; }
            .info { background: #f0f0f0; padding:18px; border-radius:10px; margin:15px 0; font-family: monospace; font-size:14px; line-height:1.6; }
            .btn { display:inline-block; padding:14px 30px; background:#ff6b6b; color:#fff; text-decoration:none; border-radius:10px; font-weight:600; margin-top:20px; transition:0.3s; }
            .btn:hover { background:#e64a19; }
            .footer { background:#f0f0f0; text-align:center; padding:20px 15px; font-size:14px; color:#666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <img src='https://umarae.com/uqxmining/icons/logo.png' alt='Umarae Logo'>
                <h1>Umarae</h1>
                <h1>⚠ New Device Login Detected</h1>
            </div>
            <div class='content'>
                <h2>Hello {$user_name},</h2>
                <p>We detected a login to your account from a <strong>new device</strong>. Please verify if this was you. 💡 Stay safe and keep your account secure!</p>
                <div class='info'>
                    IP Address: {$ip}<br>
                    Location: {$city}, {$country}<br>
                    Browser: {$browser}<br>
                    OS: {$os}<br>
                    Device Type: {$device_type}<br>
                    Device ID: {$device_hash}<br>
                    Login Time: {$time}
                </div>
                <p>If this was not you, immediately <strong>change your password</strong> and enable 2FA! 🚀</p>
                <a class='btn' href='https://umarae.com/user/settings.php'>Secure My Account</a>
            </div>
            <div class='footer'>
                © ".date("Y")." Umarae. Stay vigilant and keep your account safe! 🔒
            </div>
        </div>
    </body>
    </html>
    ";

    @mail($user_email, $subject, $body, "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: Umarae <no-reply@umarae.com>");
}
?>
