<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Google reCAPTCHA v3 secret
$recaptcha_secret = "...............................";

// Collect inputs
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$phone = trim($_POST['phone'] ?? '');
$country = trim($_POST['country'] ?? '');
$task_ref_code_input = trim($_POST['task_ref'] ?? '');
$inv_ref = trim($_POST['referred_by'] ?? '');
$recaptcha_token = $_POST['recaptcha_token'] ?? '';

// Validate fields
if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($phone) || empty($country)) {
    header("Location: ../public/register.html?error=" . urlencode("Please fill all fields"));
    exit();
}

if ($password !== $confirm_password) {
    header("Location: ../public/register.html?error=" . urlencode("Passwords do not match"));
    exit();
}

// Hash password
$password_hashed = password_hash($password, PASSWORD_BCRYPT);
$created_at = date('Y-m-d H:i:s');
$status = 'Active';

// Generate referral codes
$investment_referral_code = strtoupper(bin2hex(random_bytes(4)));
$task_referral_code = strtoupper(bin2hex(random_bytes(4)));

// Generate short UMR + 5-digit unique user ID
function generateUniqueUserID($conn) {
    do {
        $uid = 'UMR' . random_int(10000, 99999);
        $stmt = $conn->prepare("SELECT id FROM users WHERE unique_user_id = ?");
        $stmt->bind_param("s", $uid);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
    } while ($exists);
    return $uid;
}

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    header("Location: ../public/register.html?error=" . urlencode("Email already exists"));
    exit();
}
$stmt->close();

// Investment Referral Handling
$referred_by_investment = null;
if (!empty($inv_ref)) {
    // Try exact referral_code match first
    $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ? LIMIT 1");
    $stmt->bind_param("s", $inv_ref);
    $stmt->execute();
    $stmt->bind_result($inv_id);
    if ($stmt->fetch()) {
        $referred_by_investment = $inv_id;
    }
    $stmt->close();
    // Fallback: try unique_user_id (e.g. UMR12345) if code lookup failed
    if (!$referred_by_investment && preg_match('/^UMR\d+$/i', $inv_ref)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE unique_user_id = ? LIMIT 1");
        $stmt->bind_param("s", $inv_ref);
        $stmt->execute();
        $stmt->bind_result($inv_id2);
        if ($stmt->fetch()) $referred_by_investment = $inv_id2;
        $stmt->close();
    }
}

// Task Referral Handling
$task_referrer_id = null;
if (!empty($task_ref_code_input)) {
    // Try task_referral_code first
    $stmt = $conn->prepare("SELECT id FROM users WHERE task_referral_code = ? LIMIT 1");
    $stmt->bind_param("s", $task_ref_code_input);
    $stmt->execute();
    $stmt->bind_result($task_ref_id);
    if ($stmt->fetch()) {
        $task_referrer_id = $task_ref_id;
    }
    $stmt->close();
    // Fallback: try referral_code if task code didn't match
    if (!$task_referrer_id) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ? LIMIT 1");
        $stmt->bind_param("s", $task_ref_code_input);
        $stmt->execute();
        $stmt->bind_result($task_ref_id2);
        if ($stmt->fetch()) $task_referrer_id = $task_ref_id2;
        $stmt->close();
    }
}
// UNIFIED: If no task ref found but investment ref exists, use investment referrer for task too
if (empty($task_referrer_id) && !empty($referred_by_investment)) {
    $task_referrer_id = $referred_by_investment;
}

// UNIFIED: If no specific task ref but investment ref exists, use it for all
$ticket_referrer_id = $task_referrer_id;
if (empty($ticket_referrer_id) && !empty($referred_by_investment)) {
    $ticket_referrer_id = $referred_by_investment;
}

// Create unique_user_id
$unique_user_id = generateUniqueUserID($conn);

// Insert new user WITH ticket_referrer_id
$stmt = $conn->prepare("INSERT INTO users 
(name, email, password, phone, country, referral_code, task_referral_code, status, twofa_enabled, twofa_secret, referred_by, task_referrer_id, ticket_referrer_id, unique_user_id)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$twofa_enabled = 0;
$twofa_secret = '';

$stmt->bind_param("ssssssssisiiis",
    $name,
    $email,
    $password_hashed,
    $phone,
    $country,
    $investment_referral_code,
    $task_referral_code,
    $status,
    $twofa_enabled,
    $twofa_secret,
    $referred_by_investment,
    $task_referrer_id,
    $ticket_referrer_id,
    $unique_user_id
);

if (!$stmt->execute()) {
    $stmt->close();
    header("Location: ../public/register.html?error=" . urlencode("Registration failed"));
    exit();
}

$user_id = $stmt->insert_id;
$stmt->close();

// Default profile image
$default_image = '../public/uploads/profile/default.png';
$update_img = $conn->prepare("UPDATE users SET image = ? WHERE id = ?");
$update_img->bind_param("si", $default_image, $user_id);
$update_img->execute();
$update_img->close();

// Optional task referral earning (placeholder record)
if ($task_referrer_id) {
    $stmt2 = $conn->prepare("INSERT INTO task_referral_earnings (referrer_id, referred_id, amount, status, created_at) VALUES (?, ?, ?, ?, ?)");
    $reward_amount = 0;
    $status_earning = 'pending';
    $stmt2->bind_param("iisss", $task_referrer_id, $user_id, $reward_amount, $status_earning, $created_at);
    $stmt2->execute();
    $stmt2->close();
}

// Ticket referral placeholder (so dashboard shows count even before ticket purchase)
if ($ticket_referrer_id) {
    $stmtTkt = $conn->prepare("INSERT IGNORE INTO ticket_referral_earnings (referrer_id, referred_id, amount_pkr, amount_usd, usd_rate, status, created_at) VALUES (?, ?, 0, 0, 0, 'pending', ?)");
    $stmtTkt->bind_param("iis", $ticket_referrer_id, $user_id, $created_at);
    $stmtTkt->execute();
    $stmtTkt->close();
}

// UQX Referral System
$uqx_ref = 0;
if (!empty($_POST['uqx_ref'])) {
    if (is_numeric($_POST['uqx_ref'])) {
        $uqx_ref = intval($_POST['uqx_ref']);
    } else {
        // uqx_ref might be a referral CODE string (e.g. from ?ref=CODE)
        // Look up the ID from referral_code
        $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ? LIMIT 1");
        $stmt->bind_param("s", $_POST['uqx_ref']);
        $stmt->execute();
        $stmt->bind_result($uqx_ref_id);
        if ($stmt->fetch()) $uqx_ref = $uqx_ref_id;
        $stmt->close();
    }
}

// If no uqx_ref but we have investment ref, use that for UQX too
if ($uqx_ref == 0 && !empty($referred_by_investment)) {
    $uqx_ref = $referred_by_investment;
}

if ($uqx_ref > 0 && $uqx_ref != $user_id) {
    $stmt3 = $conn->prepare("INSERT IGNORE INTO uqx_referrals (referrer_id, referred_id) VALUES (?, ?)");
    $stmt3->bind_param("ii", $uqx_ref, $user_id);
    $stmt3->execute();
    $stmt3->close();
}

// Fetch user details for email
$stmt = $conn->prepare("SELECT name, email, unique_user_id, referral_code, task_referral_code FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Send Welcome Email
sendWelcomeEmail(
    $user_row['name'],
    $user_row['email'],
    $user_row['unique_user_id'],
    $user_row['referral_code']
);

function sendWelcomeEmail($name, $email, $unique_id, $inv_code) {
    $subject = "Welcome to Umarae – Your Account is Ready!";
    $referral_link = "https://umarae.com/public/register.html?ref=" . urlencode($inv_code);
    $body = "
    <html>
    <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
    body{font-family:'Inter',sans-serif;margin:0;padding:0;background:#f4f7fa;}
    .container{max-width:700px;margin:40px auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.1);}
    .header{background:linear-gradient(135deg,#ff6b6b,#ff8e53);text-align:center;padding:30px 20px;color:#fff;}
    .header img{max-width:120px;margin-bottom:15px;}
    .header h1{font-size:28px;margin:0;font-weight:700;}
    .content{padding:30px 25px;color:#333;line-height:1.7;}
    .content h2{font-size:22px;color:#ff6b6b;margin-bottom:15px;}
    .content p{font-size:16px;margin-bottom:12px;}
    .codes{background:#f0f0f0;padding:20px;border-radius:12px;margin:20px 0;font-family:monospace;font-size:14px;}
    .ref-link{background:#fff3e0;border:2px dashed #ff6b6b;padding:14px 20px;border-radius:12px;margin:16px 0;word-break:break-all;font-family:monospace;font-size:13px;color:#c0392b;}
    .btn{display:inline-block;padding:14px 30px;background:#ff6b6b;color:#fff;text-decoration:none;border-radius:12px;font-weight:600;transition:0.3s;margin-top:15px;}
    .footer{background:#f0f0f0;text-align:center;padding:20px 15px;font-size:14px;color:#666;}
    </style></head>
    <body>
    <div class='container'>
      <div class='header'>
        <img src='https://umarae.com/uqxmining/icons/logo.png' alt='Umarae Logo'>
        <h1>Welcome to Umarae, {$name}!</h1>
      </div>
      <div class='content'>
        <h2>Your Account is Ready!</h2>
        <p>Your account has been created successfully. Here's your account info:</p>
        <div class='codes'>
          User ID: {$unique_id}<br>
          Your Referral Code: <strong>{$inv_code}</strong>
        </div>
        <p><strong>Your Referral Link</strong> (share with friends to earn commission on every Deposit, Task, Mining &amp; Ticket they do):</p>
        <div class='ref-link'>{$referral_link}</div>
        <a class='btn' href='https://umarae.com/user/dashboard.php'>Go to My Dashboard</a>
      </div>
      <div class='footer'>
        &copy; ".date("Y")." Umarae. Keep referring and earning!
      </div>
    </div></body></html>
    ";
    @mail($email, $subject, $body, "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: Umarae <no-reply@umarae.com>");
}

header("Location: ../public/login.html?success=1");
exit();
?>
