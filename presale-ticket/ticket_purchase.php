
<?php
// ================================================================
// ✅ ticket_purchase.php  — UMARAE Premium Ticket Purchase Page (Fixed)
// ================================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Database file include (Matches your dashboard file path)


// Auth guard 
if (empty($_SESSION['user_id'])) {
    header("Location: ../public/login.html");
    exit;
}

$userId  = intval($_SESSION['user_id']);
$message  = '';
$msgType  = '';

// ---- Fetch current user details ----
$stmtUser = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

if (!$user) {
    die("❌ User not found.");
}

// ---- Fetch existing ticket request ----
$stmtTicket = $conn->prepare("SELECT * FROM tickets_purchased WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmtTicket->bind_param("i", $userId);
$stmtTicket->execute();
$existingTicket = $stmtTicket->get_result()->fetch_assoc();
$stmtTicket->close();

// ---- Handle Form Submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!$existingTicket || $existingTicket['status'] === 'Rejected')) {

    $fullName      = trim(htmlspecialchars($_POST['full_name']      ?? '', ENT_QUOTES, 'UTF-8'));
    $walletAddress = trim(htmlspecialchars($_POST['wallet_address'] ?? '', ENT_QUOTES, 'UTF-8'));
    $paymentMethod = in_array($_POST['payment_method'] ?? '', ['bank', 'usdt']) ? $_POST['payment_method'] : '';
    $transactionId = trim(htmlspecialchars($_POST['transaction_id'] ?? '', ENT_QUOTES, 'UTF-8'));
    $screenshotPath = null;

    // ---- Basic validation ----
    if (!$fullName || !$walletAddress || !$paymentMethod || !$transactionId) {
        $message = 'Please fill in all required fields.';
        $msgType = 'error';
    } else {
        // ---- Handle screenshot upload ----
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            
            // Secure fallback mime check
            if (function_exists('mime_content_type')) {
                $mime = mime_content_type($_FILES['screenshot']['tmp_name']);
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($_FILES['screenshot']['tmp_name']);
            }

            if (!in_array($mime, $allowedMime)) {
                $message = 'Invalid file type. Only JPG, PNG, WEBP or GIF allowed.';
                $msgType = 'error';
            } elseif ($_FILES['screenshot']['size'] > 5 * 1024 * 1024) {
                $message = 'Screenshot must be under 5 MB.';
                $msgType = 'error';
            } else {
                $uploadDir = '../uploads/tickets/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext  = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
                $fname = 'txn_' . $userId . '_' . time() . '.' . strtolower($ext);
                move_uploaded_file($_FILES['screenshot']['tmp_name'], $uploadDir . $fname);
                $screenshotPath = $uploadDir . $fname;
            }
        }

        if ($msgType !== 'error') {
            // ---- Save wallet address on user record too ----
            $stmtUp = $conn->prepare("UPDATE users SET wallet_address = ? WHERE id = ?");
            $stmtUp->bind_param("si", $walletAddress, $userId);
            $stmtUp->execute();
            $stmtUp->close();

            // ---- Insert ticket request ----
            $ins = $conn->prepare("INSERT INTO tickets_purchased (user_id, user_name, payment_method, transaction_id, screenshot_path, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
            $ins->bind_param("issss", $userId, $fullName, $paymentMethod, $transactionId, $screenshotPath);
            $ins->execute();
            $ins->close();

            $message = 'Your ticket request has been submitted! Admin will verify and approve within 24 hours.';
            $msgType = 'success';

            // Refresh ticket data
            $stmtTicket = $conn->prepare("SELECT * FROM tickets_purchased WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $stmtTicket->bind_param("i", $userId);
            $stmtTicket->execute();
            $existingTicket = $stmtTicket->get_result()->fetch_assoc();
            $stmtTicket->close();
        }
    }
}
?>

<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buy Project Ticket &mdash; UMARAE (UQX)</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;500;600;700;800;900&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
<script src="https://cdn.tailwindcss.com"></script>
<style>
:root{
  --gold:#F5C518;--gold-dk:#A07808;--gold-lt:#FFD95A;
  --bg:#06060A;--surface:#0E0E18;--surface-2:#131320;--surface-3:#1A1A2C;
  --border:rgba(255,255,255,0.07);--border-g:rgba(245,197,24,0.18);
  --t1:#EEEEF5;--t2:#A8A8C0;--t3:#60607A;
  --green:#22C55E;--green-bg:rgba(34,197,94,0.10);
  --red:#EF4444;--red-bg:rgba(239,68,68,0.10);
  --yellow:#EAB308;--yellow-bg:rgba(234,179,8,0.10);
  --blue:#3B82F6;--blue-bg:rgba(59,130,246,0.08);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  font-family:'Manrope',sans-serif;background:var(--bg);color:var(--t1);
  min-height:100vh;line-height:1.6;-webkit-font-smoothing:antialiased;
  background-image:
    radial-gradient(ellipse 80% 50% at 50% -15%,rgba(245,197,24,0.07) 0%,transparent 60%),
    radial-gradient(ellipse 40% 30% at 85% 85%,rgba(245,197,24,0.03) 0%,transparent 50%);
}
.xf{font-family:'Exo 2',sans-serif}

/* --- NAV --- */
.nav{
  position:sticky;top:0;z-index:100;
  background:rgba(6,6,10,0.88);
  backdrop-filter:blur(24px) saturate(160%);
  -webkit-backdrop-filter:blur(24px) saturate(160%);
  border-bottom:1px solid var(--border);
}
.nav-i{display:flex;align-items:center;justify-content:space-between;height:60px;max-width:1100px;margin:0 auto;padding:0 1.25rem;gap:1rem}
.logo{font-family:'Exo 2',sans-serif;font-weight:900;font-size:1.2rem;letter-spacing:.1em;color:var(--gold);text-decoration:none;text-shadow:0 0 30px rgba(245,197,24,.3);transition:text-shadow .2s}
.logo:hover{text-shadow:0 0 50px rgba(245,197,24,.55)}
.nav-back{display:inline-flex;align-items:center;gap:.45rem;color:var(--t3);font-size:.78rem;font-weight:600;text-decoration:none;padding:6px 12px;border-radius:8px;border:1px solid transparent;transition:all .2s}
.nav-back:hover{color:var(--t1);border-color:var(--border);background:var(--surface)}
.badge-vip{display:inline-flex;align-items:center;gap:.4rem;font-family:'Exo 2',sans-serif;font-weight:700;font-size:.68rem;letter-spacing:.1em;padding:4px 12px;border-radius:999px;background:linear-gradient(135deg,rgba(245,197,24,.14),rgba(245,197,24,.05));border:1px solid rgba(245,197,24,.35);color:var(--gold)}
.badge-std{display:inline-flex;align-items:center;gap:.4rem;font-family:'Exo 2',sans-serif;font-weight:700;font-size:.68rem;letter-spacing:.1em;padding:4px 12px;border-radius:999px;background:rgba(255,255,255,.04);border:1px solid var(--border);color:var(--t2)}

/* --- CONTAINER --- */
.wrap{max-width:1100px;margin:0 auto;padding:2.5rem 1.25rem 4rem;position:relative;z-index:1}

/* --- CARDS --- */
.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:1.5rem;position:relative;overflow:hidden}
.card::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(245,197,24,.1),transparent)}
.card-g{background:var(--surface);border:1px solid var(--border-g);border-radius:16px;padding:1.5rem;position:relative;overflow:hidden}
.card-g::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(245,197,24,.28),transparent)}
@media(max-width:640px){.card,.card-g{padding:1.1rem;border-radius:12px}}

/* --- TYPOGRAPHY --- */
.sec-label{font-family:'Exo 2',sans-serif;font-weight:700;font-size:.62rem;letter-spacing:.15em;text-transform:uppercase;color:var(--t3)}
.sec-title{font-family:'Exo 2',sans-serif;font-weight:800;font-size:1.05rem;color:var(--t1);display:flex;align-items:center;gap:.6rem}

/* --- PILL --- */
.pill{display:inline-flex;align-items:center;gap:.38rem;font-size:.72rem;font-weight:700;padding:4px 11px;border-radius:999px;font-family:'Exo 2',sans-serif;letter-spacing:.04em}
.pill-green{background:var(--green-bg);border:1px solid rgba(34,197,94,.3);color:#4ADE80}
.pill-red{background:var(--red-bg);border:1px solid rgba(239,68,68,.3);color:#F87171}
.pill-yellow{background:var(--yellow-bg);border:1px solid rgba(234,179,8,.3);color:#FDE047}
.pill-blue{background:var(--blue-bg);border:1px solid rgba(59,130,246,.3);color:#93C5FD}
.pill-gray{background:rgba(255,255,255,.04);border:1px solid var(--border);color:var(--t2)}

/* --- FLASH --- */
.flash{padding:1rem 1.25rem;border-radius:12px;display:flex;align-items:flex-start;gap:.75rem;font-size:.875rem;font-weight:500;line-height:1.55;animation:fadeup .3s ease;margin-bottom:1.5rem}
@keyframes fadeup{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.flash-s{background:var(--green-bg);border:1px solid rgba(34,197,94,.3);color:#4ADE80}
.flash-e{background:var(--red-bg);border:1px solid rgba(239,68,68,.3);color:#F87171}

/* --- FORM --- */
.field{display:flex;flex-direction:column;gap:.45rem;margin-bottom:1.1rem}
.field-label{font-family:'Exo 2',sans-serif;font-weight:700;font-size:.63rem;letter-spacing:.13em;text-transform:uppercase;color:var(--t2)}
.inp{
  width:100%;padding:.78rem 1rem;
  background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.1);
  border-radius:10px;color:var(--t1);
  font-family:'Manrope',sans-serif;font-size:.92rem;font-weight:500;
  outline:none;transition:border-color .2s,box-shadow .2s,background .2s;
  -webkit-appearance:none;appearance:none;
}
.inp:focus{border-color:rgba(245,197,24,.5);box-shadow:0 0 0 3px rgba(245,197,24,.08);background:rgba(255,255,255,.05)}
.inp::placeholder{color:var(--t3)}
.inp-mono{font-family:'Courier New',Courier,monospace;font-size:.82rem}
.field-hint{font-size:.75rem;color:var(--t3);line-height:1.5}

/* --- PAYMENT CARDS --- */
.pay-card{border-radius:14px;padding:1.25rem;border:1px solid;position:relative;overflow:hidden;transition:border-color .2s}
.pay-card-crypto{background:linear-gradient(135deg,rgba(59,130,246,.07),rgba(59,130,246,.02));border-color:rgba(59,130,246,.22)}
.pay-card-bank{background:linear-gradient(135deg,rgba(34,197,94,.07),rgba(34,197,94,.02));border-color:rgba(34,197,94,.22)}
.pay-card:hover{border-color:rgba(245,197,24,.3)}
.option-badge{font-family:'Exo 2',sans-serif;font-weight:700;font-size:.6rem;letter-spacing:.1em;padding:2px 10px;border-radius:999px;display:inline-flex;align-items:center;gap:.3rem}
.addr-box{
  background:rgba(0,0,0,.5);border:1px solid rgba(59,130,246,.2);
  border-radius:8px;padding:.7rem 1rem;
  font-family:'Courier New',Courier,monospace;font-size:.78rem;
  color:#93C5FD;word-break:break-all;cursor:pointer;
  transition:background .2s;position:relative;
}
.addr-box:hover{background:rgba(0,0,0,.7)}
.detail-row{display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid rgba(255,255,255,.04);gap:.5rem;flex-wrap:wrap}
.detail-row:last-child{border-bottom:none}
.detail-lbl{font-size:.8rem;color:var(--t3);display:flex;align-items:center;gap:.45rem;white-space:nowrap}
.detail-val{font-weight:700;font-size:.82rem;text-align:right}

/* --- UPLOAD ZONE --- */
.upload-zone{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  width:100%;min-height:100px;
  background:rgba(255,255,255,.02);border:2px dashed rgba(255,255,255,.1);
  border-radius:10px;cursor:pointer;transition:all .2s;padding:1.25rem;
}
.upload-zone:hover{border-color:rgba(245,197,24,.35);background:rgba(245,197,24,.03)}
.upload-zone input{display:none}

/* --- SUBMIT BUTTON --- */
.btn-submit{
  width:100%;padding:.9rem 1.5rem;
  background:linear-gradient(90deg,var(--gold-dk) 0%,var(--gold) 30%,var(--gold-lt) 55%,var(--gold) 75%,var(--gold-dk) 100%);
  background-size:200% auto;
  animation:shimmer 3s linear infinite;
  color:#050300;font-family:'Exo 2',sans-serif;font-weight:800;
  font-size:.82rem;letter-spacing:.14em;text-transform:uppercase;
  border:none;border-radius:10px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:.65rem;
  box-shadow:0 4px 24px rgba(245,197,24,.2);
  transition:transform .2s,box-shadow .2s;margin-top:.25rem;
}
@keyframes shimmer{0%{background-position:-200% center}100%{background-position:200% center}}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 32px rgba(245,197,24,.35)}
.btn-submit:active{transform:translateY(0)}

/* --- DIVIDER --- */
.gold-div{height:1px;background:linear-gradient(90deg,transparent,rgba(245,197,24,.18),transparent);margin:1.1rem 0}

/* --- COPY TOAST --- */
#copyToast{
  position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%) translateY(20px);
  background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.35);color:#4ADE80;
  font-family:'Exo 2',sans-serif;font-size:.78rem;font-weight:700;letter-spacing:.06em;
  padding:.5rem 1.25rem;border-radius:999px;opacity:0;transition:all .3s;z-index:200;
  display:flex;align-items:center;gap:.5rem;
}
#copyToast.show{opacity:1;transform:translateX(-50%) translateY(0)}

/* ===== STATES ===== */

/* --- PENDING STATE --- */
.state-pending{
  border:1px solid rgba(234,179,8,.25);border-radius:20px;
  background:linear-gradient(135deg,rgba(234,179,8,.06),rgba(234,179,8,.02));
  padding:2.5rem 2rem;text-align:center;position:relative;overflow:hidden;
}
.state-pending::before{
  content:'';position:absolute;inset:0;
  background:repeating-linear-gradient(45deg,transparent,transparent 8px,rgba(234,179,8,.015) 8px,rgba(234,179,8,.015) 16px);
}
.pending-icon-wrap{
  width:80px;height:80px;border-radius:50%;margin:0 auto 1.25rem;
  background:rgba(234,179,8,.1);border:2px solid rgba(234,179,8,.25);
  display:flex;align-items:center;justify-content:center;
  animation:pulse-ring 2.5s ease-in-out infinite;
}
@keyframes pulse-ring{
  0%,100%{box-shadow:0 0 0 0 rgba(234,179,8,.3)}
  50%{box-shadow:0 0 0 14px rgba(234,179,8,0)}
}
.txn-box{
  background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.07);
  border-radius:10px;padding:.75rem 1.25rem;
  display:flex;align-items:center;justify-content:center;gap:.75rem;
  flex-wrap:wrap;margin-top:1.25rem;
}

/* --- APPROVED / TICKET STUB --- */
@keyframes ticket-in{
  from{transform:scale(.88) rotate(-1.5deg);opacity:0}
  to{transform:scale(1) rotate(0deg);opacity:1}
}
.ticket-outer{
  animation:ticket-in .65s cubic-bezier(.34,1.56,.64,1) forwards;
  max-width:520px;margin:0 auto;
  filter:drop-shadow(0 0 40px rgba(245,197,24,.2));
}
@keyframes border-glow{
  0%,100%{box-shadow:0 0 15px rgba(245,197,24,.25),0 0 40px rgba(245,197,24,.1)}
  50%{box-shadow:0 0 30px rgba(245,197,24,.45),0 0 70px rgba(245,197,24,.2)}
}
.ticket-card{
  background:linear-gradient(160deg,#1C1100 0%,#2A1800 45%,#140D00 100%);
  border:2px solid var(--gold);
  border-radius:20px;overflow:hidden;position:relative;
  animation:border-glow 3.5s ease-in-out infinite;
}
.ticket-stripe{
  position:absolute;inset:0;pointer-events:none;
  background:repeating-linear-gradient(45deg,transparent,transparent 5px,rgba(245,197,24,.03) 5px,rgba(245,197,24,.03) 10px);
}
.ticket-header{padding:1.75rem 2rem 1.25rem;text-align:center;position:relative}
.ticket-logo{
  font-family:'Exo 2',sans-serif;font-weight:900;font-size:1.5rem;
  letter-spacing:.2em;color:var(--gold);
  text-shadow:0 0 30px rgba(245,197,24,.5),0 0 60px rgba(245,197,24,.2);
}
.ticket-event{font-family:'Exo 2',sans-serif;font-weight:700;font-size:.68rem;letter-spacing:.18em;text-transform:uppercase;color:rgba(245,197,24,.5);margin-top:.3rem}
.ticket-divider{
  position:relative;margin:0 1.25rem;height:0;
  border-top:2px dashed rgba(245,197,24,.35);
}
.ticket-divider::before,.ticket-divider::after{
  content:'';position:absolute;top:-12px;width:22px;height:22px;
  background:#06060A;border:2px solid rgba(245,197,24,.35);border-radius:50%;
}
.ticket-divider::before{left:-24px}
.ticket-divider::after{right:-24px}
.ticket-body{padding:1.5rem 2rem 2rem;text-align:center;position:relative}
.ticket-holder{font-family:'Exo 2',sans-serif;font-size:.65rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:rgba(245,197,24,.45);margin-bottom:.3rem}
.ticket-name{font-family:'Exo 2',sans-serif;font-weight:800;font-size:1.2rem;color:#fff}
.ticket-email{font-size:.75rem;color:var(--t3);margin-top:.2rem}
.ticket-num-label{font-family:'Exo 2',sans-serif;font-size:.62rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:rgba(245,197,24,.45);margin-top:1.25rem;margin-bottom:.4rem}
.ticket-num{
  font-family:'Exo 2',sans-serif;font-weight:900;font-size:3.2rem;
  letter-spacing:.12em;color:var(--gold);line-height:1;
  text-shadow:0 0 30px rgba(245,197,24,.6),0 0 60px rgba(245,197,24,.25);
  animation:num-glow 2.5s ease-in-out infinite;
}
@keyframes num-glow{
  0%,100%{text-shadow:0 0 20px rgba(245,197,24,.5),0 0 50px rgba(245,197,24,.2)}
  50%{text-shadow:0 0 35px rgba(245,197,24,.8),0 0 80px rgba(245,197,24,.4)}
}
.ticket-barcode{
  display:flex;align-items:flex-end;justify-content:center;
  gap:2px;height:36px;margin:1rem auto .25rem;
}
.ticket-bar{background:rgba(245,197,24,.5);border-radius:1px;width:3px}
.ticket-footer{
  background:rgba(0,0,0,.3);border-top:1px solid rgba(245,197,24,.12);
  padding:.875rem 2rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem
}
.ticket-footer-lbl{font-size:.65rem;font-family:'Exo 2',sans-serif;letter-spacing:.1em;color:rgba(245,197,24,.35);text-transform:uppercase}
.ticket-footer-val{font-family:'Exo 2',sans-serif;font-weight:700;font-size:.78rem}
.confetti{display:flex;justify-content:center;gap:.75rem;margin-bottom:1.25rem}
.confetti-dot{
  width:10px;height:10px;border-radius:50%;animation:cf-bounce .7s ease-in-out infinite alternate;
}
@keyframes cf-bounce{from{transform:translateY(0)}to{transform:translateY(-10px)}}
.tick-badge-area{margin-bottom:1.25rem}

/* --- REJECTED STATE --- */
.state-rejected{
  border:1px solid rgba(239,68,68,.25);border-radius:20px;
  background:rgba(239,68,68,.04);
  padding:2.5rem 2rem;text-align:center;margin-bottom:1.5rem;
}

/* --- ANIMATIONS --- */
.fu{animation:fu .4s ease both}
@keyframes fu{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.fu-1{animation-delay:.04s}.fu-2{animation-delay:.09s}.fu-3{animation-delay:.13s}
.fu-4{animation-delay:.17s}.fu-5{animation-delay:.21s}

/* --- SCROLLBAR --- */
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:var(--surface-3);border-radius:4px}
::-webkit-scrollbar-thumb:hover{background:rgba(245,197,24,.3)}

/* --- RESPONSIVE --- */
@media(max-width:640px){
  .ticket-num{font-size:2.2rem}
  .ticket-header,.ticket-body{padding:1.25rem 1.25rem}
  .ticket-footer{padding:.75rem 1.25rem}
}
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
  <div class="nav-i">
    <div style="display:flex;align-items:center;gap:.75rem">
      <a href="uqxdashboard.php" class="logo xf">UMARAE</a>
      <span style="color:var(--t3);font-family:'Exo 2',sans-serif;font-size:.72rem">/ Project Ticket</span>
    </div>
    <div style="display:flex;align-items:center;gap:.75rem">
      <?php if ($user['user_type'] === 'ticket_holder'): ?>
      <span class="badge-vip xf"><i class="fa-solid fa-crown" style="font-size:.6rem"></i> VIP HOLDER</span>
      <?php else: ?>
      <span class="badge-std xf"><i class="fa-solid fa-user" style="font-size:.6rem"></i> STANDARD</span>
      <?php endif; ?>
      <a href="../uqxmining/uqxdashboard.php" class="nav-back">
        <i class="fa-solid fa-arrow-left" style="font-size:.7rem"></i>
        <span class="hidden sm:inline">Dashboard</span>
      </a>
    </div>
  </div>
</nav>

<!-- WRAPPER -->
<div class="wrap">

  <!-- ===== PAGE HEADER ===== -->
  <div style="text-align:center;margin-bottom:2.75rem" class="fu">
    <div class="pill pill-yellow" style="margin-bottom:1rem;display:inline-flex">
      <i class="fa-solid fa-trophy" style="font-size:.65rem"></i>
      EXCLUSIVE OWNERSHIP OPPORTUNITY
    </div>
    <h1 style="font-family:'Exo 2',sans-serif;font-weight:900;font-size:clamp(1.9rem,6vw,3.2rem);line-height:1.05;letter-spacing:-.02em;color:#fff;margin-bottom:.75rem">
      WIN <span style="color:var(--gold)">98% OWNERSHIP</span><br>OF THE UMARAE PROJECT
    </h1>
    <p style="font-size:.95rem;color:var(--t2);max-width:520px;margin:0 auto;line-height:1.65">
      Purchase one ticket for <strong style="color:var(--gold)">3,000 PKR</strong> and instantly receive
      <strong style="color:var(--gold)">3,000 UQX Tokens</strong> plus VIP status on the platform.
    </p>
  </div>

  <!-- FLASH -->
  <?php if ($message): ?>
  <div class="flash <?= $msgType==='success' ? 'flash-s' : 'flash-e' ?>">
    <i class="fa-solid <?= $msgType==='success' ? 'fa-circle-check' : 'fa-circle-xmark' ?>" style="font-size:.95rem;flex-shrink:0;margin-top:1px"></i>
    <span><?= htmlspecialchars($message) ?></span>
  </div>
  <?php endif; ?>


  <!-- ========== STATE: APPROVED — GOLDEN TICKET ========== -->
  <?php if ($existingTicket && $existingTicket['status'] === 'Approved'): ?>
  <div class="fu" style="margin-bottom:2.5rem">

    <div style="text-align:center;margin-bottom:1.5rem">
      <div class="pill pill-green" style="display:inline-flex;margin-bottom:.75rem">
        <i class="fa-solid fa-circle-check" style="font-size:.65rem"></i>
        TICKET CONFIRMED &amp; ACTIVE
      </div>
      <p style="font-size:.85rem;color:var(--t2)">Your VIP status is active. Keep your ticket number safe for the live draw.</p>
    </div>

    <!-- Confetti dots -->
    <div class="confetti" aria-hidden="true">
      <?php $cc=['#F5C518','#22C55E','#3B82F6','#F97316','#EC4899','#A855F7','#F5C518','#22C55E','#3B82F6']; foreach($cc as $i=>$c): ?>
      <div class="confetti-dot" style="background:<?=$c?>;animation-delay:<?=($i*.08)?>s"></div>
      <?php endforeach; ?>
    </div>

    <!-- TICKET STUB -->
    <div class="ticket-outer">
      <div class="ticket-card">
        <div class="ticket-stripe" aria-hidden="true"></div>

        <!-- Header -->
        <div class="ticket-header">
          <div style="margin-bottom:.85rem">
            <div class="pill pill-green" style="display:inline-flex;font-size:.6rem">
              <i class="fa-solid fa-circle-check" style="font-size:.55rem"></i>
              APPROVED &amp; CONFIRMED
            </div>
          </div>
          <div class="ticket-logo">UMARAE</div>
          <div class="ticket-event">UQX Project Draw &bull; 98% Ownership</div>

          <div style="display:flex;justify-content:center;gap:1.5rem;margin-top:1.25rem;flex-wrap:wrap">
            <div style="text-align:center">
              <div style="font-family:'Exo 2',sans-serif;font-size:.58rem;letter-spacing:.12em;color:rgba(245,197,24,.4);text-transform:uppercase;margin-bottom:.2rem">Holder</div>
              <div style="font-family:'Exo 2',sans-serif;font-weight:700;font-size:.88rem;color:#fff"><?= htmlspecialchars($existingTicket['user_name']) ?></div>
            </div>
            <div style="width:1px;background:rgba(245,197,24,.15)"></div>
            <div style="text-align:center">
              <div style="font-family:'Exo 2',sans-serif;font-size:.58rem;letter-spacing:.12em;color:rgba(245,197,24,.4);text-transform:uppercase;margin-bottom:.2rem">Email</div>
              <div style="font-size:.78rem;color:var(--t3)"><?= htmlspecialchars($user['email']) ?></div>
            </div>
            <div style="width:1px;background:rgba(245,197,24,.15)"></div>
            <div style="text-align:center">
              <div style="font-family:'Exo 2',sans-serif;font-size:.58rem;letter-spacing:.12em;color:rgba(245,197,24,.4);text-transform:uppercase;margin-bottom:.2rem">UQX Bonus</div>
              <div style="font-family:'Exo 2',sans-serif;font-weight:700;font-size:.88rem;color:var(--gold)">+3,000 UQX</div>
            </div>
          </div>
        </div>

        <!-- Perforation -->
        <div class="ticket-divider"></div>

        <!-- Ticket number -->
        <div class="ticket-body">
          <div class="ticket-num-label">Your Ticket Number</div>
          <div class="ticket-num"><?= htmlspecialchars($existingTicket['ticket_number']) ?></div>
          <p style="font-size:.73rem;color:var(--t3);margin-top:.75rem;line-height:1.55">
            <i class="fa-solid fa-shield-halved" style="font-size:.65rem;margin-right:.35rem;color:rgba(245,197,24,.4)"></i>
            Keep this number safe &mdash; it will be used in the physical live draw.
          </p>

          <!-- Decorative barcode -->
          <div class="ticket-barcode" aria-hidden="true">
            <?php
            $bars=[3,5,2,7,4,6,2,8,3,5,7,2,6,4,3,8,5,2,7,4,6,3,5,8,2,7,4,6,3,5];
            foreach($bars as $h):
            $ht=($h/8)*100;
            ?>
            <div class="ticket-bar" style="height:<?=$ht?>%;opacity:<?=0.3+($h/8)*0.5?>"></div>
            <?php endforeach; ?>
          </div>
          <div style="font-family:'Exo 2',sans-serif;font-size:.58rem;letter-spacing:.25em;color:rgba(245,197,24,.3)">
            UMARAE &bull; UQX DRAW &bull; <?= date('Y') ?>
          </div>
        </div>

        <!-- Footer -->
        <div class="ticket-footer">
          <div>
            <div class="ticket-footer-lbl">Payment</div>
            <div class="ticket-footer-val" style="color:var(--t2)"><?= htmlspecialchars(strtoupper($existingTicket['payment_method'])) ?></div>
          </div>
          <div style="text-align:center">
            <div class="ticket-footer-lbl">VIP Status</div>
            <div class="ticket-footer-val" style="color:var(--gold)"><i class="fa-solid fa-crown" style="font-size:.65rem"></i> ACTIVE</div>
          </div>
          <div style="text-align:right">
            <div class="ticket-footer-lbl">Ticket Price</div>
            <div class="ticket-footer-val" style="color:#4ADE80">3,000 PKR</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Benefits row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;margin-top:1.5rem;max-width:520px;margin-left:auto;margin-right:auto">
      <?php foreach([
        ['fa-crown','VIP Status','Active on platform','var(--gold)'],
        ['fa-coins','3,000 UQX','Tokens credited','#4ADE80'],
        ['fa-ticket','Draw Entry','Live physical draw','#93C5FD'],
      ] as [$ic,$tl,$dl,$cl]): ?>
      <div style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:.875rem;text-align:center">
        <i class="fa-solid <?=$ic?>" style="color:<?=$cl?>;font-size:1.15rem;display:block;margin-bottom:.4rem"></i>
        <div style="font-family:'Exo 2',sans-serif;font-weight:700;font-size:.82rem;color:var(--t1)"><?=$tl?></div>
        <div style="font-size:.72rem;color:var(--t3);margin-top:2px"><?=$dl?></div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
  <?php endif; ?>


  <!-- ========== STATE: PENDING ========== -->
  <?php if ($existingTicket && $existingTicket['status'] === 'Pending'): ?>
  <div class="state-pending fu" style="margin-bottom:2rem">
    <div class="pending-icon-wrap">
      <i class="fa-solid fa-hourglass-half" style="color:var(--yellow);font-size:1.75rem"></i>
    </div>
    <div class="pill pill-yellow" style="display:inline-flex;margin-bottom:.875rem">
      <i class="fa-solid fa-clock" style="font-size:.6rem"></i>
      VERIFICATION IN PROGRESS
    </div>
    <h2 style="font-family:'Exo 2',sans-serif;font-weight:800;font-size:1.5rem;color:#fff;margin-bottom:.5rem">Request Submitted!</h2>
    <p style="font-size:.875rem;color:var(--t2);max-width:440px;margin:0 auto;line-height:1.65">
      Your payment is being verified by the admin team. You will receive your ticket number within
      <strong style="color:#fff">24 hours</strong> of confirmation.
    </p>
    <div class="txn-box">
      <i class="fa-solid fa-receipt" style="color:var(--t3);font-size:.85rem"></i>
      <span style="font-size:.78rem;color:var(--t2)">Transaction ID:</span>
      <span style="font-family:'Exo 2',sans-serif;font-weight:700;font-size:.82rem;color:#FDE047;font-variant-numeric:tabular-nums">
        <?= htmlspecialchars($existingTicket['transaction_id']) ?>
      </span>
    </div>
    <div style="display:flex;justify-content:center;gap:1rem;margin-top:1.25rem;flex-wrap:wrap">
      <div style="text-align:center">
        <i class="fa-solid fa-envelope" style="color:var(--t3);font-size:.85rem;display:block;margin-bottom:.3rem"></i>
        <div style="font-size:.73rem;color:var(--t3)">Notification via email once approved</div>
      </div>
    </div>
  </div>
  <?php endif; ?>


  <!-- ========== STATE: REJECTED ========== -->
  <?php if ($existingTicket && $existingTicket['status'] === 'Rejected'): ?>
  <div class="state-rejected fu">
    <div style="width:64px;height:64px;border-radius:50%;background:var(--red-bg);border:2px solid rgba(239,68,68,.3);display:flex;align-items:center;justify-content:center;margin:0 auto 1.1rem">
      <i class="fa-solid fa-circle-xmark" style="color:#F87171;font-size:1.5rem"></i>
    </div>
    <div class="pill pill-red" style="display:inline-flex;margin-bottom:.75rem">
      <i class="fa-solid fa-triangle-exclamation" style="font-size:.6rem"></i>
      REQUEST REJECTED
    </div>
    <h2 style="font-family:'Exo 2',sans-serif;font-weight:800;font-size:1.4rem;color:#F87171;margin-bottom:.4rem">Payment Not Verified</h2>
    <p style="font-size:.875rem;color:var(--t2);max-width:420px;margin:0 auto">
      Your previous submission was rejected. Please re-submit with correct payment proof and a valid transaction ID.
    </p>
  </div>
  <?php endif; ?>


  <!-- ========== PURCHASE FORM (show if no active request OR rejected) ========== -->
  <?php if (!$existingTicket || $existingTicket['status'] === 'Rejected'): ?>
  <div style="display:grid;grid-template-columns:1fr;gap:1.5rem" class="fu fu-2" id="purchaseSection">

    <!-- PAYMENT INSTRUCTIONS -->
    <div class="fu fu-2">
      <div style="margin-bottom:1.1rem">
        <h2 class="sec-title">
          <i class="fa-solid fa-credit-card" style="color:var(--gold)"></i>
          Payment Instructions
        </h2>
        <p style="font-size:.8rem;color:var(--t3);margin-top:.35rem">Choose one of the payment methods below, then submit proof via the form.</p>
      </div>

      <div style="display:grid;grid-template-columns:1fr;gap:1rem">

        <!-- Crypto Option -->
        <div class="pay-card pay-card-crypto">
          <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;flex-wrap:wrap">
            <span class="option-badge pill-blue">
              <i class="fa-solid fa-1" style="font-size:.5rem"></i> OPTION 1
            </span>
            <span style="font-family:'Exo 2',sans-serif;font-weight:700;font-size:.92rem;color:#fff">Crypto &mdash; USDT BEP-20</span>
            <span class="pill pill-blue" style="font-size:.58rem;padding:2px 8px;margin-left:auto">BSC ONLY</span>
          </div>
          <p style="font-size:.8rem;color:var(--t2);margin-bottom:.65rem">
            Send exactly <strong style="color:#fff">~$10.5 USDT</strong> (approx. 3,000 PKR) on BEP-20 network to:
          </p>
          <div class="addr-box" id="cryptoAddr" onclick="copyAddress(this,'cryptoAddr')" title="Click to copy">
            <span id="cryptoAddrText">0xYOUR_BEP20_WALLET_ADDRESS_HERE</span>
            <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:rgba(147,197,253,.5)">
              <i class="fa-regular fa-copy" style="font-size:.8rem"></i>
            </span>
          </div>
          <div style="display:flex;align-items:center;gap:.4rem;margin-top:.6rem">
            <i class="fa-solid fa-triangle-exclamation" style="color:rgba(234,179,8,.7);font-size:.7rem"></i>
            <span style="font-size:.73rem;color:rgba(234,179,8,.7)">BEP-20 (BSC) network only. Wrong network = lost funds.</span>
          </div>
        </div>

        <!-- Bank Option -->
        <div class="pay-card pay-card-bank">
          <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;flex-wrap:wrap">
            <span class="option-badge pill-green">
              <i class="fa-solid fa-2" style="font-size:.5rem"></i> OPTION 2
            </span>
            <span style="font-family:'Exo 2',sans-serif;font-weight:700;font-size:.92rem;color:#fff">Bank Transfer (PKR)</span>
            <span class="pill pill-green" style="font-size:.58rem;padding:2px 8px;margin-left:auto">PAKISTAN</span>
          </div>
          <div>
            <div class="detail-row">
              <span class="detail-lbl"><i class="fa-solid fa-user" style="font-size:.65rem"></i> Account Name</span>
              <span class="detail-val" style="color:#fff">YOUR FULL NAME HERE</span>
            </div>
            <div class="detail-row">
              <span class="detail-lbl"><i class="fa-solid fa-building-columns" style="font-size:.65rem"></i> Bank Name</span>
              <span class="detail-val" style="color:var(--t1)">YOUR BANK NAME</span>
            </div>
            <div class="detail-row">
              <span class="detail-lbl"><i class="fa-solid fa-hashtag" style="font-size:.65rem"></i> Account No.</span>
              <span class="detail-val" style="color:#FDE047;font-family:'Exo 2',sans-serif">XXXX-XXXX-XXXXXXX</span>
            </div>
            <div class="detail-row">
              <span class="detail-lbl"><i class="fa-solid fa-barcode" style="font-size:.65rem"></i> IBAN</span>
              <span class="detail-val" style="color:#FDE047;font-family:'Exo 2',sans-serif;font-size:.75rem">PKXX XXXX XXXX XXXX XXXX XXXX</span>
            </div>
            <div class="detail-row">
              <span class="detail-lbl"><i class="fa-solid fa-location-dot" style="font-size:.65rem"></i> Branch / City</span>
              <span class="detail-val" style="color:var(--t2)">YOUR BRANCH &amp; CITY</span>
            </div>
            <div class="detail-row">
              <span class="detail-lbl"><i class="fa-solid fa-coins" style="font-size:.65rem"></i> Amount</span>
              <span class="detail-val" style="color:#4ADE80">3,000 PKR</span>
            </div>
          </div>
          <div style="background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.15);border-radius:8px;padding:.6rem .875rem;margin-top:.75rem;display:flex;gap:.5rem;align-items:flex-start">
            <i class="fa-solid fa-circle-info" style="color:rgba(74,222,128,.6);font-size:.72rem;margin-top:2px;flex-shrink:0"></i>
            <span style="font-size:.73rem;color:rgba(74,222,128,.7);line-height:1.5">Use your registered name as the payment reference. Screenshot is required for verification.</span>
          </div>
        </div>

      </div><!-- /payment grid -->

      <!-- How it works steps -->
      <div class="card" style="margin-top:1rem">
        <div class="sec-title" style="margin-bottom:1rem;font-size:.9rem">
          <i class="fa-solid fa-circle-info" style="color:var(--t3);font-size:.85rem"></i>
          How to Purchase
        </div>
        <div style="display:flex;flex-direction:column;gap:0">
          <?php foreach([
            ['fa-money-bill-transfer','Pay','Send exactly 3,000 PKR via bank or ~$10.5 USDT via BEP-20.'],
            ['fa-camera','Screenshot','Take a clear screenshot of the completed transaction.'],
            ['fa-file-arrow-up','Submit','Fill the form and upload your payment screenshot.'],
            ['fa-clock','Wait','Admin verifies within 24 hours and approves your ticket.'],
            ['fa-ticket','Receive','Your ticket number is generated and VIP status is activated.'],
          ] as $i=>[$ic,$tl,$dl]): ?>
          <div style="display:flex;gap:.85rem;align-items:flex-start;padding:.65rem 0;border-bottom:1px solid rgba(255,255,255,.04)">
            <div style="width:26px;height:26px;flex-shrink:0;background:rgba(245,197,24,.08);border:1px solid rgba(245,197,24,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Exo 2',sans-serif;font-weight:800;font-size:.68rem;color:var(--gold);margin-top:1px"><?=$i+1?></div>
            <div>
              <p style="font-family:'Exo 2',sans-serif;font-weight:700;font-size:.855rem;color:var(--t1)">
                <i class="fa-solid <?=$ic?>" style="color:var(--gold);font-size:.68rem;margin-right:.35rem"></i><?=$tl?>
              </p>
              <p style="font-size:.77rem;color:var(--t3);margin-top:2px;line-height:1.5"><?=$dl?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div><!-- /payment instructions -->


    <!-- SUBMISSION FORM -->
    <div class="card-g fu fu-3">
      <h2 class="sec-title" style="margin-bottom:1.4rem">
        <i class="fa-solid fa-file-signature" style="color:var(--gold)"></i>
        Submit Your Request
      </h2>

      <form method="POST" enctype="multipart/form-data">

        <div class="field">
          <label class="field-label" for="fullName">
            <i class="fa-solid fa-id-card" style="margin-right:.35rem"></i>Full Real Name <span style="color:var(--red)">*</span>
          </label>
          <input type="text" name="full_name" id="fullName" required class="inp"
            placeholder="As per your ID / for ticket printing">
        </div>

        <div class="field">
          <label class="field-label" for="walletAddr">
            <i class="fa-solid fa-wallet" style="margin-right:.35rem"></i>BNB Smart Chain Wallet (BEP-20) <span style="color:var(--red)">*</span>
          </label>
          <input type="text" name="wallet_address" id="walletAddr" required class="inp inp-mono"
            placeholder="0x..."
            value="<?= htmlspecialchars($user['wallet_address'] ?? '') ?>"
            oninput="validateWallet(this.value)">
          <p class="field-hint" id="walletHint">
            <i class="fa-solid fa-triangle-exclamation" style="font-size:.62rem;margin-right:.3rem;color:rgba(234,179,8,.6)"></i>
            Used for UQX token distribution. Double-check before submitting!
          </p>
        </div>

        <div class="field">
          <label class="field-label" for="payMethod">
            <i class="fa-solid fa-credit-card" style="margin-right:.35rem"></i>Payment Method <span style="color:var(--red)">*</span>
          </label>
          <select name="payment_method" id="payMethod" required class="inp" style="-webkit-appearance:none">
            <option value="" style="background:#0E0E18;color:var(--t3)">&mdash; Select Method &mdash;</option>
            <option value="bank" style="background:#0E0E18">Bank Transfer (PKR)</option>
            <option value="usdt" style="background:#0E0E18">Crypto &mdash; USDT BEP-20</option>
          </select>
        </div>

        <div class="field">
          <label class="field-label" for="txnId">
            <i class="fa-solid fa-receipt" style="margin-right:.35rem"></i>Transaction ID / Reference <span style="color:var(--red)">*</span>
          </label>
          <input type="text" name="transaction_id" id="txnId" required class="inp inp-mono"
            placeholder="TXN ID, Hash, or Reference Number">
        </div>

        <div class="field">
          <label class="field-label">
            <i class="fa-solid fa-image" style="margin-right:.35rem"></i>Payment Screenshot / Receipt
          </label>
          <label class="upload-zone" id="uploadZone">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.75rem;color:var(--t3);margin-bottom:.5rem" id="uploadIcon"></i>
            <span style="font-size:.82rem;color:var(--t2);font-weight:600" id="uploadLabel">Click to upload screenshot</span>
            <span style="font-size:.72rem;color:var(--t3);margin-top:.2rem">JPG, PNG, WEBP &bull; Max 5MB</span>
            <input type="file" name="screenshot" accept="image/*" onchange="handleUpload(this)">
          </label>
        </div>

        <!-- Price summary -->
        <div style="background:rgba(245,197,24,.05);border:1px solid rgba(245,197,24,.15);border-radius:10px;padding:.875rem 1rem;margin-bottom:1rem">
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
            <div style="display:flex;align-items:center;gap:.6rem;font-size:.82rem;color:var(--t2)">
              <i class="fa-solid fa-ticket" style="color:var(--gold);font-size:.8rem"></i>
              1 &times; UMARAE Project Ticket
            </div>
            <div>
              <span style="font-family:'Exo 2',sans-serif;font-weight:800;font-size:1.05rem;color:var(--gold)">3,000 PKR</span>
              <span style="font-size:.73rem;color:var(--t3);margin-left:.35rem">+ 3,000 UQX</span>
            </div>
          </div>
        </div>

        <button type="submit" class="btn-submit">
          <i class="fa-solid fa-paper-plane"></i>
          Submit Ticket Request &mdash; 3,000 PKR
        </button>

      </form>
    </div><!-- /form card -->

  </div><!-- /grid -->
  <?php endif; ?>

</div><!-- /wrap -->

<!-- Footer -->
<footer style="border-top:1px solid var(--border);padding:1.25rem;text-align:center;position:relative;z-index:1">
  <p style="font-size:.72rem;color:var(--t3);font-family:'Exo 2',sans-serif;letter-spacing:.06em">
    &copy; <?= date('Y') ?> UMARAE ECOSYSTEM &mdash; ALL RIGHTS RESERVED
  </p>
</footer>

<!-- COPY TOAST -->
<div id="copyToast">
  <i class="fa-solid fa-circle-check" style="font-size:.72rem"></i>
  Address Copied!
</div>

<script>
function copyAddress(el, id) {
  const txt = document.getElementById('cryptoAddrText').textContent.trim();
  navigator.clipboard.writeText(txt).then(() => {
    const t = document.getElementById('copyToast');
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2200);
  });
}

function handleUpload(inp) {
  const lbl = document.getElementById('uploadLabel');
  const ico = document.getElementById('uploadIcon');
  if (inp.files[0]) {
    lbl.textContent = inp.files[0].name;
    lbl.style.color = '#4ADE80';
    ico.className = 'fa-solid fa-circle-check';
    ico.style.color = '#4ADE80';
    document.getElementById('uploadZone').style.borderColor = 'rgba(34,197,94,.4)';
  }
}

function validateWallet(val) {
  const hint = document.getElementById('walletHint');
  if (!hint) return;
  if (!val) {
    hint.innerHTML = '<i class="fa-solid fa-triangle-exclamation" style="font-size:.62rem;margin-right:.3rem;color:rgba(234,179,8,.6)"></i>Used for UQX token distribution. Double-check before submitting!';
    hint.style.color = 'var(--t3)';
  } else if (!val.startsWith('0x')) {
    hint.innerHTML = '<i class="fa-solid fa-circle-xmark" style="margin-right:.35rem"></i>BEP-20 address must start with 0x';
    hint.style.color = '#F87171';
  } else if (val.length !== 42) {
    hint.innerHTML = '<i class="fa-solid fa-triangle-exclamation" style="margin-right:.35rem"></i>Must be exactly 42 characters (' + val.length + '/42)';
    hint.style.color = '#FDE047';
  } else {
    hint.innerHTML = '<i class="fa-solid fa-circle-check" style="margin-right:.35rem"></i>Valid BEP-20 address format';
    hint.style.color = '#4ADE80';
  }
}
</script>
</body>
</html>

