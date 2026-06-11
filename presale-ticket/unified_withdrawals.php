
<?php
// ================================================================
// withdraw.php — UMARAE: UQX Withdrawal Page
// ✅ PHP 7.1+ compatible (no union types)
// ✅ BEP20 (BSC) only — token is on BNB Chain
// ✅ Balance from uqx_wallet (mining + airdrop + ticket combined)
// ✅ VIP ticket_holder = 5% weekly | Standard = 5% monthly
// ================================================================
session_start();


if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.html');
    exit;
}

$userId  = (int)$_SESSION['user_id'];
$message = '';
$msgType = '';

// ----------------------------------------------------------------
// 1. Fetch user
// ----------------------------------------------------------------
$stmtUser = $conn->prepare("
    SELECT id, name, email, user_type, wallet_address, last_withdrawal_at, available_balance
    FROM users WHERE id = ? LIMIT 1
");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

if (!$user) { session_destroy(); header('Location: ../public/login.html'); exit; }

// ----------------------------------------------------------------
// 1b. Fetch other balance sources (portfolio overview)
// ----------------------------------------------------------------
$usdBalance = (float)($user['available_balance'] ?? 0);

// Task referral earnings in USD
$taskUsd = 0;
$tRes = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM task_referral_earnings WHERE referrer_id=$userId AND status IN ('approved','pending')");
if($tRes) $taskUsd = (float)$tRes->fetch_assoc()['t'];

// Ticket referral earnings
$ticketPkr = 0; $ticketUsd = 0;
$tkRes = $conn->query("SELECT COALESCE(SUM(amount_pkr),0) as pkr, COALESCE(SUM(amount_usd),0) as usd FROM ticket_referral_earnings WHERE referrer_id=$userId AND status IN ('approved','pending')");
if($tkRes){ $tkRow=$tkRes->fetch_assoc(); $ticketPkr=(float)$tkRow['pkr']; $ticketUsd=(float)$tkRow['usd']; }

// ----------------------------------------------------------------
// 2. Fetch UQX from uqx_wallet (single source of truth)
// ----------------------------------------------------------------
$stmtW = $conn->prepare("SELECT balance FROM uqx_wallet WHERE user_id = ? LIMIT 1");
$stmtW->bind_param("i", $userId);
$stmtW->execute();
$walletRow = $stmtW->get_result()->fetch_assoc();
$stmtW->close();

if (!$walletRow) {
    $conn->query("INSERT IGNORE INTO uqx_wallet (user_id, balance) VALUES (" . $userId . ", 0)");
    $walletRow = ['balance' => 0];
}
$uqxBalance = (float)$walletRow['balance'];

// ----------------------------------------------------------------
// 3. Config — BEP20 (BSC) only
// ----------------------------------------------------------------
define('MIN_WITHDRAW',   1.00);     // minimum UQX per transaction
define('WITHDRAW_FEE',   1.0);      // 1% fee (BSC is cheap)
define('WITHDRAW_LIMIT', 5);        // 5% of balance per period
define('NETWORK',        'BEP20');  // BSC only

$isVip        = ($user['user_type'] === 'ticket_holder');
$cooldownDays = $isVip ? 7 : 30;
$maxWithdraw  = round($uqxBalance * (WITHDRAW_LIMIT / 100), 2);

// ----------------------------------------------------------------
// 4. Cooldown helpers — PHP 7.1+ compatible (?string NOT string|null)
// ----------------------------------------------------------------
function cooldownLeft(?string $lastAt, int $days): int {
    if (!$lastAt) return 0;
    return max(0, strtotime($lastAt) + ($days * 86400) - time());
}
function fmtCountdown(int $s): string {
    $d = floor($s / 86400);
    $h = floor(($s % 86400) / 3600);
    $m = floor(($s % 3600) / 60);
    return "{$d}d {$h}h {$m}m";
}

$cooldownSecs = cooldownLeft($user['last_withdrawal_at'], $cooldownDays);
$canWithdraw  = ($cooldownSecs === 0) && ($maxWithdraw > 0);
// ✅ Note: last_withdrawal_at is reset to NULL when admin rejects → cooldownSecs=0 → user can re-submit immediately

// ----------------------------------------------------------------
// 5. Process withdrawal
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_withdrawal'])) {

    $amount     = (float)($_POST['amount'] ?? 0);
    $walletAddr = trim($_POST['wallet_address'] ?? '');

    if (!$canWithdraw) {
        $message = 'You are not eligible to withdraw at this time.';
        $msgType = 'error';
    } elseif ($amount < MIN_WITHDRAW) {
        $message = 'Minimum withdrawal is ' . number_format(MIN_WITHDRAW, 0) . ' UQX.';
        $msgType = 'error';
    } elseif ($amount > $maxWithdraw) {
        $message = 'Maximum this period is ' . number_format($maxWithdraw, 2) . ' UQX (5% of balance).';
        $msgType = 'error';
    } elseif ($amount > $uqxBalance) {
        $message = 'Insufficient UQX balance.';
        $msgType = 'error';
    } elseif (strlen($walletAddr) < 26) {
        $message = 'Please enter a valid BEP20 (BSC) wallet address.';
        $msgType = 'error';
    } else {

        $fee    = round($amount * (WITHDRAW_FEE / 100), 2);
        $netPay = round($amount - $fee, 2);

        $conn->begin_transaction();
        try {
            // A. Deduct from uqx_wallet (balance guard prevents overdraft)
            $s1 = $conn->prepare("
                UPDATE uqx_wallet SET balance = balance - ?
                WHERE user_id = ? AND balance >= ?
            ");
            $s1->bind_param("did", $amount, $userId, $amount);
            $s1->execute();
            if ($s1->affected_rows < 1) throw new Exception("Balance deduction failed. Please refresh and try again.");
            $s1->close();

            // B. Set cooldown + save BEP20 wallet address
            $s2 = $conn->prepare("
                UPDATE users SET last_withdrawal_at = NOW(), wallet_address = ? WHERE id = ?
            ");
            $s2->bind_param("si", $walletAddr, $userId);
            $s2->execute();
            $s2->close();

            // C. Insert withdrawal request (no network column — admin knows it's BEP20)
            $s3 = $conn->prepare("
                INSERT INTO withdraw_requests (user_id, amount, wallet_address, status)
                VALUES (?, ?, ?, 'Pending')
            ");
            $s3->bind_param("ids", $userId, $amount, $walletAddr);
            $s3->execute();
            $s3->close();

            $conn->commit();

            // Refresh local state so UI updates instantly
            $uqxBalance -= $amount;
            $maxWithdraw = round($uqxBalance * (WITHDRAW_LIMIT / 100), 2);
            $cooldownSecs = cooldownLeft(date('Y-m-d H:i:s'), $cooldownDays);
            $canWithdraw  = false;
            $user['wallet_address'] = $walletAddr;

            $message = "Withdrawal of {$amount} UQX submitted successfully! You will receive {$netPay} UQX after the " . WITHDRAW_FEE . "% fee. Admin will process within 24-72 hours.";
            $msgType = 'success';

        } catch (Exception $e) {
            $conn->rollback();
            $message = 'Error: ' . htmlspecialchars($e->getMessage());
            $msgType = 'error';
        }
    }
}

// ----------------------------------------------------------------
// 6. Withdrawal history
// ----------------------------------------------------------------
$stmtH = $conn->prepare("
    SELECT * FROM withdraw_requests WHERE user_id = ? ORDER BY id DESC LIMIT 10
");
$stmtH->bind_param("i", $userId);
$stmtH->execute();
$resH = $stmtH->get_result();
$withdrawals = [];
while ($r = $resH->fetch_assoc()) $withdrawals[] = $r;
$stmtH->close();

// ----------------------------------------------------------------
// 7. Balance breakdown (safe — won't crash if tables missing)
// ----------------------------------------------------------------
$mined = 0; $refBonus = 0;
$mRes = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM mining_sessions WHERE user_id=$userId AND status='completed'");
if ($mRes) $mined = (float)$mRes->fetch_assoc()['t'];

$rRes = $conn->query("SELECT COALESCE(SUM(reward),0) as t FROM uqx_referral_earnings WHERE referrer_id=$userId");
if ($rRes) $refBonus = (float)$rRes->fetch_assoc()['t'];

$ticketBonus = max(0, $uqxBalance - $mined - $refBonus);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Withdraw UQX &mdash; UMARAE</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;500;600;700;800;900&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<script src="https://cdn.tailwindcss.com"></script>
<style>
:root{
  --gold:#F5C518;--gold-dk:#B8890B;--gold-lt:#FFD95A;
  --bg:#06060A;--surface:#0E0E18;--surface-2:#131320;--surface-3:#1A1A2C;
  --border:rgba(255,255,255,0.07);--border-g:rgba(245,197,24,0.18);
  --t1:#EEEEF5;--t2:#A8A8C0;--t3:#60607A;
  --green:#22C55E;--green-bg:rgba(34,197,94,0.10);
  --red:#EF4444;--red-bg:rgba(239,68,68,0.10);
  --yellow:#EAB308;--yellow-bg:rgba(234,179,8,0.10);
  --orange:#F97316;
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
.xfont{font-family:'Exo 2',sans-serif}

/* --- NAV --- */
.nav{
  position:sticky;top:0;z-index:100;
  background:rgba(6,6,10,0.88);
  backdrop-filter:blur(24px) saturate(160%);
  -webkit-backdrop-filter:blur(24px) saturate(160%);
  border-bottom:1px solid var(--border);
}
.nav-i{display:flex;align-items:center;justify-content:space-between;height:60px;max-width:1200px;margin:0 auto;padding:0 1.25rem;gap:1rem}
.logo{font-family:'Exo 2',sans-serif;font-weight:900;font-size:1.2rem;letter-spacing:.1em;color:var(--gold);text-decoration:none;text-shadow:0 0 30px rgba(245,197,24,.3);transition:text-shadow .2s}
.logo:hover{text-shadow:0 0 50px rgba(245,197,24,.55)}
.nav-sep{color:var(--t3);font-size:.72rem;font-family:'Exo 2',sans-serif;letter-spacing:.04em}
.badge-vip{display:inline-flex;align-items:center;gap:.4rem;font-family:'Exo 2',sans-serif;font-weight:700;font-size:.68rem;letter-spacing:.1em;padding:4px 12px;border-radius:999px;background:linear-gradient(135deg,rgba(245,197,24,.14),rgba(245,197,24,.05));border:1px solid rgba(245,197,24,.35);color:var(--gold)}
.badge-std{display:inline-flex;align-items:center;gap:.4rem;font-family:'Exo 2',sans-serif;font-weight:700;font-size:.68rem;letter-spacing:.1em;padding:4px 12px;border-radius:999px;background:rgba(255,255,255,.04);border:1px solid var(--border);color:var(--t2)}
.nav-back{display:inline-flex;align-items:center;gap:.45rem;color:var(--t3);font-size:.78rem;font-weight:600;text-decoration:none;padding:6px 12px;border-radius:8px;border:1px solid transparent;transition:all .2s}
.nav-back:hover{color:var(--t1);border-color:var(--border);background:var(--surface)}
.nav-right{display:flex;align-items:center;gap:.75rem}

/* --- CARDS --- */
.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:1.5rem;position:relative;overflow:hidden}
.card::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(245,197,24,.1),transparent)}
.card-g{background:var(--surface);border:1px solid var(--border-g);border-radius:16px;padding:1.5rem;position:relative;overflow:hidden}
.card-g::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(245,197,24,.28),transparent)}
@media(max-width:640px){.card,.card-g{padding:1.1rem;border-radius:12px}}

/* --- TYPOGRAPHY --- */
.page-title{font-family:'Exo 2',sans-serif;font-weight:900;font-size:clamp(1.8rem,5vw,2.8rem);line-height:1;letter-spacing:-.02em;color:#fff}
.page-title .accent{color:var(--gold)}
.page-sub{font-size:.85rem;color:var(--t2);margin-top:.4rem;display:flex;align-items:center;gap:.45rem}
.sec-label{font-family:'Exo 2',sans-serif;font-weight:700;font-size:.62rem;letter-spacing:.15em;text-transform:uppercase;color:var(--t3)}
.sec-title{font-family:'Exo 2',sans-serif;font-weight:800;font-size:1rem;color:var(--t1);display:flex;align-items:center;gap:.6rem}

/* --- STAT CHIPS --- */
.chip{text-align:center;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:.55rem .9rem;min-width:90px}
.chip-val{font-family:'Exo 2',sans-serif;font-weight:800;font-size:1.05rem;line-height:1;letter-spacing:-.02em}
.chip-lbl{font-family:'Exo 2',sans-serif;font-size:.58rem;letter-spacing:.14em;text-transform:uppercase;color:var(--t3);margin-top:4px}
@media(max-width:480px){.chip{min-width:72px;padding:.45rem .65rem}.chip-val{font-size:.9rem}}

/* --- BALANCE RING --- */
.ring-wrap{width:148px;height:148px;border-radius:50%;flex-shrink:0;position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;background:radial-gradient(circle,rgba(245,197,24,.07) 0%,transparent 65%)}
.ring-svg{position:absolute;inset:0;width:100%;height:100%}
.ring-dash{animation:spin 18s linear infinite;transform-origin:50% 50%}
.ring-solid{animation:spin-rev 30s linear infinite;transform-origin:50% 50%}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes spin-rev{to{transform:rotate(-360deg)}}
.ring-val{font-family:'Exo 2',sans-serif;font-weight:900;font-size:1.4rem;line-height:1;color:var(--gold);letter-spacing:-.03em}
.ring-unit{font-family:'Exo 2',sans-serif;font-weight:700;font-size:.62rem;letter-spacing:.18em;text-transform:uppercase;color:rgba(245,197,24,.5);margin-top:3px}
@media(max-width:640px){.ring-wrap{width:110px;height:110px}.ring-val{font-size:1.1rem}}

/* --- PROGRESS --- */
.prog-track{height:5px;border-radius:999px;background:rgba(255,255,255,.05);overflow:hidden}
.prog-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,var(--gold-dk),var(--gold));box-shadow:0 0 8px rgba(245,197,24,.35);transition:width .6s ease}

/* --- PILLS / BADGES --- */
.pill{display:inline-flex;align-items:center;gap:.38rem;font-size:.72rem;font-weight:700;padding:4px 11px;border-radius:999px;font-family:'Exo 2',sans-serif;letter-spacing:.04em}
.pill-green{background:var(--green-bg);border:1px solid rgba(34,197,94,.3);color:#4ADE80}
.pill-red{background:var(--red-bg);border:1px solid rgba(239,68,68,.3);color:#F87171}
.pill-yellow{background:var(--yellow-bg);border:1px solid rgba(234,179,8,.3);color:#FDE047}
.pill-gray{background:rgba(255,255,255,.04);border:1px solid var(--border);color:var(--t2)}

/* --- FORM --- */
.field{display:flex;flex-direction:column;gap:.45rem}
.field-label{font-family:'Exo 2',sans-serif;font-weight:700;font-size:.63rem;letter-spacing:.13em;text-transform:uppercase;color:var(--t2)}
.inp-wrap{position:relative}
.inp{
  width:100%;padding:.78rem 1rem;
  background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.1);
  border-radius:10px;color:var(--t1);
  font-family:'Manrope',sans-serif;font-size:.95rem;font-weight:500;
  outline:none;transition:border-color .2s,box-shadow .2s,background .2s;
  -webkit-appearance:none;appearance:none;
}
.inp:focus{border-color:rgba(245,197,24,.5);box-shadow:0 0 0 3px rgba(245,197,24,.08);background:rgba(255,255,255,.05)}
.inp:disabled{opacity:.4;cursor:not-allowed}
.inp::placeholder{color:var(--t3)}
.inp-sfx{position:absolute;right:12px;top:50%;transform:translateY(-50%);font-family:'Exo 2',sans-serif;font-weight:700;font-size:.7rem;color:rgba(245,197,24,.5);letter-spacing:.08em;pointer-events:none}
.inp-badge{position:absolute;right:11px;top:50%;transform:translateY(-50%);background:rgba(243,186,47,.1);border:1px solid rgba(243,186,47,.28);color:#F3BA2F;font-size:.6rem;font-weight:700;font-family:'Exo 2',sans-serif;padding:2px 8px;border-radius:999px;letter-spacing:.08em;pointer-events:none}
.field-hint{font-size:.75rem;color:var(--t3);transition:color .2s;line-height:1.5;margin-top:.15rem}
.field-row{display:flex;justify-content:space-between;align-items:center;margin-top:.4rem}
.field-row-lbl{font-size:.73rem;color:var(--t3)}
.inp-mono{font-family:'Courier New',Courier,monospace;font-size:.82rem;padding-right:90px}

/* --- BUTTONS --- */
.btn{
  width:100%;padding:.875rem 1.5rem;
  background:linear-gradient(135deg,#A07808 0%,#F5C518 45%,#A07808 100%);
  color:#050300;font-family:'Exo 2',sans-serif;font-weight:800;
  font-size:.8rem;letter-spacing:.14em;text-transform:uppercase;
  border:none;border-radius:10px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:.6rem;
  box-shadow:0 4px 24px rgba(245,197,24,.18),inset 0 1px 0 rgba(255,255,255,.15);
  transition:all .2s;position:relative;overflow:hidden;
}
.btn::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,transparent 30%,rgba(255,255,255,.12) 50%,transparent 70%);transform:translateX(-100%);transition:transform .45s ease}
.btn:hover:not(:disabled)::after{transform:translateX(100%)}
.btn:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 6px 32px rgba(245,197,24,.28),inset 0 1px 0 rgba(255,255,255,.15)}
.btn:active:not(:disabled){transform:translateY(0)}
.btn:disabled{background:rgba(255,255,255,.07);color:var(--t3);box-shadow:none;cursor:not-allowed}
.btn-link{background:none;border:none;cursor:pointer;padding:0;color:var(--gold);font-size:.78rem;font-weight:700;font-family:'Manrope',sans-serif;display:inline-flex;align-items:center;gap:.35rem;opacity:.8;transition:opacity .2s}
.btn-link:hover{opacity:1}
.btn-link:disabled{color:var(--t3);cursor:not-allowed;opacity:.5}

/* --- FLASH --- */
.flash{padding:1rem 1.25rem;border-radius:12px;display:flex;align-items:flex-start;gap:.75rem;font-size:.875rem;font-weight:500;line-height:1.55;animation:fadeup .3s ease}
@keyframes fadeup{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.flash-s{background:var(--green-bg);border:1px solid rgba(34,197,94,.3);color:#4ADE80}
.flash-e{background:var(--red-bg);border:1px solid rgba(239,68,68,.3);color:#F87171}
.flash-w{background:var(--yellow-bg);border:1px solid rgba(234,179,8,.3);color:#FDE047}
.flash-icon{font-size:.95rem;margin-top:1px;flex-shrink:0}

/* --- FEE BOX --- */
.fee-box{background:rgba(0,0,0,.3);border:1px solid var(--border);border-radius:12px;overflow:hidden}
.fee-row{display:flex;justify-content:space-between;align-items:center;padding:.55rem 1rem;border-bottom:1px solid var(--border)}
.fee-row:last-child{border-bottom:none;padding:.7rem 1rem}
.fee-lbl{font-size:.8rem;color:var(--t2)}
.fee-val{font-family:'Exo 2',sans-serif;font-weight:700;font-size:.85rem;font-variant-numeric:tabular-nums}

/* --- NETWORK BANNER --- */
.net-banner{display:flex;align-items:center;gap:1rem;background:linear-gradient(135deg,rgba(243,186,47,.07),rgba(243,186,47,.02));border:1px solid rgba(243,186,47,.2);border-radius:12px;padding:.9rem 1.1rem}
.net-badge{display:inline-flex;align-items:center;gap:.3rem;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#4ADE80;font-family:'Exo 2',sans-serif;font-weight:700;font-size:.58rem;letter-spacing:.1em;padding:2px 8px;border-radius:999px;margin-left:auto}

/* --- WARN BOX --- */
.warn{background:rgba(245,197,24,.04);border:1px solid rgba(245,197,24,.1);border-radius:10px;padding:.85rem 1rem;display:flex;gap:.7rem;align-items:flex-start;font-size:.79rem;color:var(--t2);line-height:1.55}

/* --- COOLDOWN BAR --- */
.cd-wrap{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:1rem 1.25rem}
.cd-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:.65rem}
.cd-track{height:6px;border-radius:999px;background:rgba(255,255,255,.05);overflow:hidden}
.cd-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,var(--gold-dk),var(--gold));box-shadow:0 0 6px rgba(245,197,24,.25)}
.cd-note{font-size:.72rem;color:var(--t3);margin-top:.55rem}

/* --- RULES --- */
.rule-row{display:flex;justify-content:space-between;align-items:center;padding:.65rem 0;border-bottom:1px solid rgba(255,255,255,.04);gap:.5rem}
.rule-row:last-child{border-bottom:none}
.rule-left{display:flex;align-items:center;gap:.6rem;font-size:.82rem;color:var(--t2)}
.rule-icon{width:1.1rem;text-align:center;color:var(--t3);font-size:.75rem;flex-shrink:0}
.rule-val{font-weight:700;font-size:.78rem;font-family:'Exo 2',sans-serif;white-space:nowrap}

/* --- STEPS --- */
.step-row{display:flex;gap:.85rem;align-items:flex-start;padding:.55rem 0}
.step-num{width:27px;height:27px;flex-shrink:0;background:rgba(245,197,24,.08);border:1px solid rgba(245,197,24,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Exo 2',sans-serif;font-weight:800;font-size:.68rem;color:var(--gold);margin-top:1px}
.step-t{font-weight:700;font-size:.875rem;color:var(--t1);font-family:'Exo 2',sans-serif}
.step-d{font-size:.77rem;color:var(--t3);margin-top:2px;line-height:1.5}

/* --- BREAKDOWN --- */
.bd-row{display:flex;justify-content:space-between;align-items:center;padding:.65rem .875rem;border-radius:10px;margin-bottom:.4rem}
.bd-row:last-child{margin-bottom:0}
.bd-lbl{font-size:.82rem;display:flex;align-items:center;gap:.5rem}
.bd-val{font-family:'Exo 2',sans-serif;font-weight:700;font-size:.82rem;font-variant-numeric:tabular-nums}

/* --- TABLE --- */
.tbl{width:100%;border-collapse:collapse}
.tbl thead tr{background:rgba(0,0,0,.3)}
.tbl th{padding:.7rem 1.1rem;text-align:left;font-family:'Exo 2',sans-serif;font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--t3);white-space:nowrap}
.tbl td{padding:.8rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.03);font-size:.865rem;vertical-align:middle;color:var(--t1)}
.tbl tbody tr:last-child td{border-bottom:none}
.tbl tbody tr{transition:background .15s}
.tbl tbody tr:hover td{background:rgba(245,197,24,.018)}
.status-pill{display:inline-flex;align-items:center;gap:.35rem;font-family:'Exo 2',sans-serif;font-size:.62rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:3px 10px;border-radius:999px}
.s-pending{background:var(--yellow-bg);border:1px solid rgba(234,179,8,.35);color:#FDE047}
.s-approved{background:var(--green-bg);border:1px solid rgba(34,197,94,.35);color:#4ADE80}
.s-rejected{background:var(--red-bg);border:1px solid rgba(239,68,68,.35);color:#F87171}

/* --- DIVIDER --- */
.gold-div{height:1px;background:linear-gradient(90deg,transparent,rgba(245,197,24,.18),transparent);margin:1.1rem 0}

/* --- LAYOUT --- */
.main-grid{display:grid;grid-template-columns:1fr;gap:1.25rem}
@media(min-width:1024px){.main-grid{grid-template-columns:3fr 2fr}}
.left-col,.right-col{display:flex;flex-direction:column;gap:1.25rem}

/* --- ANIMATIONS --- */
.fu{animation:fu .4s ease both}
@keyframes fu{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.fu-1{animation-delay:.04s}.fu-2{animation-delay:.09s}.fu-3{animation-delay:.13s}
.fu-4{animation-delay:.17s}.fu-5{animation-delay:.21s}.fu-6{animation-delay:.25s}

/* --- MISC --- */
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:var(--surface-3);border-radius:4px}
::-webkit-scrollbar-thumb:hover{background:rgba(245,197,24,.3)}
</style>
</head>
<body>

<!-- ===================== NAV ===================== -->
<nav class="nav">
  <div class="nav-i">
    <div style="display:flex;align-items:center;gap:.75rem">
      <a href="uqxdashboard.php" class="logo">UMARAE</a>
      <span class="nav-sep xfont">/ Withdraw UQX</span>
    </div>
    <div class="nav-right">
      <?php if ($isVip): ?>
      <span class="badge-vip"><i class="fa-solid fa-crown" style="font-size:.6rem"></i> VIP HOLDER</span>
      <?php else: ?>
      <span class="badge-std"><i class="fa-solid fa-user" style="font-size:.6rem"></i> STANDARD</span>
      <?php endif; ?>
      <a href="uqxdashboard.php" class="nav-back">
        <i class="fa-solid fa-arrow-left" style="font-size:.7rem"></i>
        <span class="hidden sm:inline">Dashboard</span>
      </a>
    </div>
  </div>
</nav>

<!-- ===================== MAIN ===================== -->
<div style="max-width:1200px;margin:0 auto;padding:2.25rem 1.25rem 3rem;position:relative;z-index:1">

  <!-- PAGE HEADER -->
  <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:1.25rem;margin-bottom:2rem" class="fu">
    <div>
      <h1 class="page-title">Withdraw <span class="accent">UQX</span></h1>
      <p class="page-sub">
        <?php if ($isVip): ?>
        <i class="fa-solid fa-crown" style="color:var(--gold);font-size:.75rem"></i>
        VIP &mdash; 5% of balance weekly &bull; 7-day cycle
        <?php else: ?>
        <i class="fa-solid fa-user" style="color:var(--t3);font-size:.75rem"></i>
        Standard &mdash; 5% of balance monthly &bull; 30-day cycle
        <?php endif; ?>
      </p>
    </div>
    <div style="display:flex;gap:.75rem">
      <div class="chip">
        <div class="chip-val" style="color:var(--gold)"><?= number_format($uqxBalance, 2) ?></div>
        <div class="chip-lbl">Balance</div>
      </div>
      <div class="chip">
        <div class="chip-val" style="color:#4ADE80"><?= number_format($maxWithdraw, 2) ?></div>
        <div class="chip-lbl">Available</div>
      </div>
    </div>
  </div>

  <!-- PORTFOLIO OVERVIEW -->
  <div class="fu fu-1" style="margin-bottom:1.5rem">
    <div class="card" style="padding:0;overflow:hidden">
      <div style="background:linear-gradient(135deg,rgba(67,97,238,0.08),rgba(0,212,255,0.04));border-bottom:1px solid var(--border);padding:1rem 1.25rem">
        <div class="sec-title" style="margin:0;font-size:.95rem">
          <i class="fa-solid fa-chart-pie" style="color:var(--gold);font-size:.9rem"></i>
          Earnings Portfolio
        </div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0">
        <?php
        $portfolio = [
          ['fa-wallet',     'var(--gold)',    'UQX Wallet',    number_format($uqxBalance,2).' UQX', 'Mining + UQX Tasks + Bonus'],
          ['fa-dollar-sign', 'var(--green)',   'USD Balance',   '$'.number_format($usdBalance,2), 'Deposit + Task USD + Refs'],
          ['fa-ticket',      'var(--orange)',  'Ticket PKR',    '₨'.number_format($ticketPkr,2), 'Ticket Referral Earnings'],
          ['fa-users',       'var(--cyan)',    'Task Ref',      '$'.number_format($taskUsd,2), 'Task Referral Commissions'],
        ];
        foreach($portfolio as $i=>$p):
        ?>
        <div style="padding:1.1rem 1.25rem;border-right:1px solid var(--border);<?php echo ($i==3)?'border-right:none':''; ?>">
          <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.4rem">
            <i class="fa-solid <?=$p[0]?>" style="color:<?=$p[1]?>;font-size:.75rem"></i>
            <span style="font-size:.65rem;color:var(--t3);font-weight:700;letter-spacing:.1em;text-transform:uppercase;font-family:'Exo 2',sans-serif"><?=$p[2]?></span>
          </div>
          <div style="font-family:'Exo 2',sans-serif;font-weight:800;font-size:1.05rem;color:var(--t1);line-height:1"><?=$p[3]?></div>
          <div style="font-size:.68rem;color:var(--t3);margin-top:3px"><?=$p[4]?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="background:rgba(245,197,24,.04);border-top:1px solid rgba(245,197,24,.1);padding:.7rem 1.25rem;display:flex;align-items:center;gap:.6rem">
        <i class="fa-solid fa-circle-info" style="color:var(--gold);font-size:.7rem"></i>
        <span style="font-size:.75rem;color:var(--t2)">
          <strong style="color:var(--t1)">UQX</strong> = BEP20 withdraw. 
          <strong style="color:var(--t1)">USD</strong> & <strong style="color:var(--t1)">PKR</strong> = managed via admin transfer.
        </span>
      </div>
    </div>
  </div>

  <!-- FLASH MESSAGE -->
  <?php if ($message): ?>
  <?php
    $fc = ($msgType==='success') ? 'flash-s' : (($msgType==='warning') ? 'flash-w' : 'flash-e');
    $fi = ($msgType==='success') ? 'fa-circle-check' : (($msgType==='warning') ? 'fa-triangle-exclamation' : 'fa-circle-xmark');
  ?>
  <div class="flash <?= $fc ?> fu" style="margin-bottom:1.5rem">
    <i class="fa-solid <?= $fi ?> flash-icon"></i>
    <span><?= htmlspecialchars($message) ?></span>
  </div>
  <?php endif; ?>

  <!-- MAIN GRID -->
  <div class="main-grid">

    <!-- ======== LEFT COLUMN ======== -->
    <div class="left-col">

      <!-- BALANCE CARD -->
      <div class="card-g fu fu-1">
        <div style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:center">

          <!-- Ring -->
          <div class="ring-wrap">
            <svg class="ring-svg" viewBox="0 0 148 148" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle class="ring-solid" cx="74" cy="74" r="68" stroke="rgba(245,197,24,0.12)" stroke-width="1.5"/>
              <circle class="ring-dash" cx="74" cy="74" r="62" stroke="rgba(245,197,24,0.22)" stroke-width="1" stroke-dasharray="6 5"/>
              <circle cx="74" cy="74" r="56" stroke="rgba(245,197,24,0.08)" stroke-width="1"/>
            </svg>
            <div class="ring-val"><?= number_format($uqxBalance, 0) ?></div>
            <div class="ring-unit">UQX</div>
          </div>

          <!-- Info -->
          <div style="flex:1;min-width:0">
            <div class="sec-label xfont" style="margin-bottom:.4rem">Total UQX Balance</div>
            <div style="font-family:'Exo 2',sans-serif;font-weight:900;font-size:2rem;line-height:1;letter-spacing:-.03em;color:#fff">
              <?= number_format($uqxBalance, 4) ?>
              <span style="font-size:1rem;color:var(--gold);font-weight:700;margin-left:.25rem">UQX</span>
            </div>

            <!-- 5% progress -->
            <div style="margin-top:1rem">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.45rem">
                <span style="font-size:.75rem;color:var(--t3);display:flex;align-items:center;gap:.4rem">
                  <i class="fa-solid fa-chart-simple" style="font-size:.65rem"></i>
                  Withdrawable (5% of balance)
                </span>
                <span style="font-family:'Exo 2',sans-serif;font-weight:700;font-size:.82rem;color:var(--gold)"><?= number_format($maxWithdraw, 2) ?> UQX</span>
              </div>
              <div class="prog-track">
                <div class="prog-fill" style="width:5%"></div>
              </div>
            </div>

            <div class="gold-div"></div>

            <!-- Saved wallet -->
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.875rem;flex-wrap:wrap">
              <span style="font-size:.75rem;color:var(--t3);display:flex;align-items:center;gap:.4rem">
                <i class="fa-solid fa-wallet" style="font-size:.65rem"></i> Saved wallet:
              </span>
              <?php if (!empty($user['wallet_address'])): ?>
              <code style="font-size:.75rem;color:rgba(245,197,24,.75);font-family:'Courier New',monospace" title="<?= htmlspecialchars($user['wallet_address']) ?>">
                <?= htmlspecialchars(substr($user['wallet_address'],0,14)) ?>...<?= htmlspecialchars(substr($user['wallet_address'],-6)) ?>
              </code>
              <span class="pill pill-gray" style="font-size:.58rem;padding:2px 8px">BEP20</span>
              <?php else: ?>
              <span style="font-size:.75rem;color:var(--t3);font-style:italic">Not set &mdash; enter below</span>
              <?php endif; ?>
            </div>

            <!-- Eligibility pills -->
            <div style="display:flex;flex-wrap:wrap;gap:.5rem">
              <span class="pill <?= $isVip ? 'pill-yellow' : 'pill-gray' ?>">
                <i class="fa-solid <?= $isVip ? 'fa-crown' : 'fa-user' ?>" style="font-size:.6rem"></i>
                <?= $isVip ? 'VIP &mdash; 7d cycle' : 'Standard &mdash; 30d cycle' ?>
              </span>
              <span class="pill <?= $maxWithdraw > 0 ? 'pill-green' : 'pill-red' ?>">
                <i class="fa-solid <?= $maxWithdraw > 0 ? 'fa-circle-check' : 'fa-circle-xmark' ?>" style="font-size:.6rem"></i>
                <?= $maxWithdraw > 0 ? 'Balance available' : 'No balance to withdraw' ?>
              </span>
              <span class="pill <?= $cooldownSecs===0 ? 'pill-green' : 'pill-yellow' ?>">
                <i class="fa-solid <?= $cooldownSecs===0 ? 'fa-circle-check' : 'fa-clock' ?>" style="font-size:.6rem"></i>
                <?= $cooldownSecs===0 ? 'Ready' : fmtCountdown($cooldownSecs) ?>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- COOLDOWN BAR -->
      <?php if ($cooldownSecs > 0):
        $totalSecs = $cooldownDays * 86400;
        $elapsed   = $totalSecs - $cooldownSecs;
        $pct       = min(100, round(($elapsed / $totalSecs) * 100, 1));
      ?>
      <div class="cd-wrap fu fu-2">
        <div class="cd-top">
          <div style="display:flex;align-items:center;gap:.55rem">
            <i class="fa-solid fa-hourglass-half" style="color:var(--gold);font-size:.8rem"></i>
            <span class="sec-label xfont">Cooldown &mdash; <?= $cooldownDays ?>-Day <?= $isVip ? 'VIP' : 'Standard' ?> Cycle</span>
          </div>
          <span style="font-family:'Exo 2',sans-serif;font-weight:700;font-size:.8rem;color:var(--yellow)">
            <i class="fa-regular fa-clock" style="font-size:.7rem"></i> <?= fmtCountdown($cooldownSecs) ?> left
          </span>
        </div>
        <div class="cd-track">
          <div class="cd-fill" style="width:<?= $pct ?>%"></div>
        </div>
        <p class="cd-note"><i class="fa-solid fa-circle-info" style="font-size:.65rem;margin-right:.3rem"></i>Cooldown resets after each approved withdrawal.</p>
      </div>
      <?php endif; ?>

      <!-- WITHDRAW FORM -->
      <div class="card fu fu-3">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem">
          <h2 class="sec-title">
            <i class="fa-solid fa-paper-plane" style="color:var(--gold);font-size:.9rem"></i>
            Withdrawal Request
          </h2>
          <!-- BNB badge -->
          <div style="display:flex;align-items:center;gap:.5rem;background:rgba(243,186,47,.07);border:1px solid rgba(243,186,47,.2);border-radius:10px;padding:.45rem .85rem">
            <i class="fa-brands fa-ethereum" style="color:#F3BA2F;font-size:1rem"></i>
            <div>
              <div style="font-family:'Exo 2',sans-serif;font-weight:800;font-size:.65rem;letter-spacing:.08em;color:#F3BA2F">BNB CHAIN</div>
              <div style="font-size:.58rem;color:rgba(243,186,47,.5);letter-spacing:.04em">BSC &bull; BEP20</div>
            </div>
          </div>
        </div>

        <form method="POST" id="withdrawForm">

          <!-- Amount -->
          <div class="field" style="margin-bottom:1.25rem">
            <label class="field-label" for="amountInput">
              <i class="fa-solid fa-coins" style="margin-right:.35rem"></i>Amount <span style="color:var(--red);margin-left:.15rem">*</span>
            </label>
            <div class="inp-wrap">
              <input type="number" name="amount" id="amountInput"
                     class="inp" style="padding-right:60px"
                     placeholder="Enter UQX amount"
                     min="<?= MIN_WITHDRAW ?>"
                     max="<?= $maxWithdraw ?>"
                     step="0.01"
                     <?= !$canWithdraw ? 'disabled' : '' ?>
                     oninput="updateFee(this.value)">
              <span class="inp-sfx">UQX</span>
            </div>
            <div class="field-row">
              <span class="field-row-lbl">
                <i class="fa-solid fa-chart-line" style="font-size:.6rem;margin-right:.3rem"></i>
                Max: 5% of balance = <?= number_format($maxWithdraw, 2) ?> UQX
              </span>
              <button type="button" onclick="setMax()" class="btn-link" <?= !$canWithdraw ? 'disabled' : '' ?>>
                <i class="fa-solid fa-arrow-up-right-dots" style="font-size:.6rem"></i>
                Set Max
              </button>
            </div>
          </div>

          <!-- Fee Preview -->
          <div id="feeBox" style="display:none;margin-bottom:1.25rem">
            <div class="fee-box">
              <div class="fee-row">
                <span class="fee-lbl"><i class="fa-solid fa-coins" style="font-size:.7rem;margin-right:.4rem;color:var(--t3)"></i>Gross amount</span>
                <span class="fee-val" id="feeAmt" style="color:var(--t1)">—</span>
              </div>
              <div class="fee-row">
                <span class="fee-lbl"><i class="fa-solid fa-minus" style="font-size:.7rem;margin-right:.4rem;color:var(--t3)"></i>BSC fee (<?= WITHDRAW_FEE ?>%)</span>
                <span class="fee-val" id="feeDed" style="color:#F87171">—</span>
              </div>
              <div class="fee-row">
                <span style="font-family:'Exo 2',sans-serif;font-weight:800;font-size:.85rem;color:var(--t1)">
                  <i class="fa-solid fa-circle-check" style="font-size:.75rem;margin-right:.4rem;color:var(--green)"></i>You Receive
                </span>
                <span class="fee-val" id="feeNet" style="color:var(--gold);font-size:.95rem">—</span>
              </div>
            </div>
          </div>

          <!-- Network (locked) -->
          <div class="field" style="margin-bottom:1.25rem">
            <label class="field-label">
              <i class="fa-solid fa-network-wired" style="margin-right:.35rem"></i>Network
            </label>
            <div class="net-banner">
              <i class="fa-solid fa-link" style="color:#F3BA2F;font-size:1.2rem"></i>
              <div style="flex:1">
                <p style="font-family:'Exo 2',sans-serif;font-weight:700;font-size:.88rem;color:#F3BA2F">BEP20 &mdash; BNB Smart Chain</p>
                <p style="font-size:.73rem;color:var(--t3);margin-top:2px">UQX token runs exclusively on BSC. Fast and low-cost transfers.</p>
              </div>
              <span class="net-badge"><i class="fa-solid fa-circle-check" style="font-size:.6rem"></i> ONLY</span>
            </div>
          </div>

          <!-- Wallet Address -->
          <div class="field" style="margin-bottom:1.5rem">
            <label class="field-label" for="walletInput">
              <i class="fa-solid fa-address-card" style="margin-right:.35rem"></i>BEP20 Wallet Address <span style="color:var(--red);margin-left:.15rem">*</span>
            </label>
            <div class="inp-wrap">
              <input type="text" name="wallet_address" id="walletInput"
                     class="inp inp-mono"
                     placeholder="0x..."
                     value="<?= htmlspecialchars($user['wallet_address'] ?? '') ?>"
                     <?= !$canWithdraw ? 'disabled' : '' ?>
                     oninput="validateWallet(this.value)">
              <span class="inp-badge">BEP20</span>
            </div>
            <p id="walletHint" class="field-hint">
              <i class="fa-solid fa-shield-halved" style="font-size:.65rem;margin-right:.3rem"></i>
              Must start with <code style="color:var(--t2);background:rgba(255,255,255,.06);padding:1px 5px;border-radius:4px">0x</code> &mdash; 42 characters total. Wrong address = permanent loss.
            </p>
          </div>

          <!-- Warning -->
          <div class="warn" style="margin-bottom:1.25rem">
            <i class="fa-solid fa-triangle-exclamation" style="color:var(--gold);font-size:.85rem;margin-top:2px;flex-shrink:0"></i>
            <span>
              Max withdrawal is <strong style="color:var(--t1)">5%</strong> of your balance per <?= $cooldownDays ?>-day period.
              <strong style="color:var(--t1)"><?= WITHDRAW_FEE ?>%</strong> BSC network fee applies.
              Admin processes within <strong style="color:var(--t1)">24&ndash;72 hours</strong>.
            </span>
          </div>

          <!-- Submit -->
          <button type="submit" name="submit_withdrawal" class="btn" <?= !$canWithdraw ? 'disabled' : '' ?>>
            <?php
            if ($cooldownSecs > 0):
            ?>
            <i class="fa-solid fa-hourglass-half"></i>
            Cooldown &mdash; <?= fmtCountdown($cooldownSecs) ?>
            <?php
            elseif ($maxWithdraw <= 0):
            ?>
            <i class="fa-solid fa-lock"></i>
            No Balance to Withdraw
            <?php
            else:
            ?>
            <i class="fa-solid fa-paper-plane"></i>
            Submit Withdrawal
            <?php
            endif;
            ?>
          </button>
        </form>
      </div>

    </div><!-- /left-col -->

    <!-- ======== RIGHT COLUMN ======== -->
    <div class="right-col">

      <!-- RULES CARD -->
      <div class="card fu fu-2">
        <div style="margin-bottom:1.1rem">
          <div class="sec-title">
            <i class="fa-solid fa-shield-halved" style="color:var(--gold);font-size:.9rem"></i>
            Withdrawal Rules
          </div>
        </div>
        <?php $rules = [
          ['fa-crown',           'text-yellow-400', '#F5C518',  'VIP Cycle',        '5% / 7 days'],
          ['fa-user',            'text-gray-300',   'var(--t2)','Standard Cycle',   '5% / 30 days'],
          ['fa-coins',           'text-gray-300',   'var(--t2)','Min Withdrawal',   '1 UQX (5% of bal)'],
          ['fa-percent',         'text-green-400',  '#4ADE80',  'BSC Fee',          WITHDRAW_FEE.'%'],
          ['fa-link',            'text-orange-400', 'var(--orange)','Network',       'BEP20 (BSC Only)'],
          ['fa-clock',           'text-gray-300',   'var(--t2)','Processing',       '24 – 72 hours'],
        ];
        foreach($rules as [$icon,$cls,$col,$label,$val]): ?>
        <div class="rule-row">
          <span class="rule-left">
            <i class="fa-solid <?= $icon ?> rule-icon"></i>
            <?= $label ?>
          </span>
          <span class="rule-val" style="color:<?= $col ?>"><?= $val ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- BALANCE BREAKDOWN -->
      <div class="card fu fu-3">
        <div style="margin-bottom:1.1rem">
          <div class="sec-title">
            <i class="fa-solid fa-chart-pie" style="color:var(--t3);font-size:.9rem"></i>
            Balance Breakdown
          </div>
        </div>

        <?php if ($mined > 0): ?>
        <div class="bd-row" style="background:rgba(255,255,255,.03);border:1px solid var(--border)">
          <span class="bd-lbl" style="color:var(--t2)">
            <i class="fa-solid fa-hammer" style="color:var(--t3);font-size:.75rem"></i> Mining
          </span>
          <span class="bd-val" style="color:var(--t1)"><?= number_format($mined, 4) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($refBonus > 0): ?>
        <div class="bd-row" style="background:rgba(255,255,255,.03);border:1px solid var(--border)">
          <span class="bd-lbl" style="color:var(--t2)">
            <i class="fa-solid fa-users" style="color:var(--t3);font-size:.75rem"></i> Referrals
          </span>
          <span class="bd-val" style="color:var(--t1)"><?= number_format($refBonus, 4) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($isVip): ?>
        <div class="bd-row" style="background:rgba(245,197,24,.06);border:1px solid rgba(245,197,24,.15)">
          <span class="bd-lbl" style="color:var(--gold)">
            <i class="fa-solid fa-ticket" style="font-size:.75rem"></i> Ticket Bonus
          </span>
          <span class="bd-val" style="color:var(--gold)"><?= number_format($ticketBonus, 4) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($mined == 0 && $refBonus == 0 && !$isVip): ?>
        <div style="text-align:center;padding:1.25rem 0">
          <i class="fa-solid fa-hammer" style="color:var(--t3);font-size:1.4rem;display:block;margin-bottom:.5rem"></i>
          <p style="font-size:.78rem;color:var(--t3)">Start mining to earn UQX</p>
        </div>
        <?php endif; ?>

        <div class="gold-div"></div>
        <div class="bd-row" style="background:rgba(245,197,24,.07);border:1px solid rgba(245,197,24,.18)">
          <span class="bd-lbl" style="font-family:'Exo 2',sans-serif;font-weight:800;color:var(--gold)">
            <i class="fa-solid fa-wallet" style="font-size:.75rem"></i> TOTAL
          </span>
          <span class="bd-val" style="color:var(--gold);font-size:.9rem"><?= number_format($uqxBalance, 4) ?> UQX</span>
        </div>
      </div>

      <!-- HOW IT WORKS -->
      <div class="card fu fu-4">
        <div style="margin-bottom:1.1rem">
          <div class="sec-title">
            <i class="fa-solid fa-circle-info" style="color:var(--t3);font-size:.9rem"></i>
            How It Works
          </div>
        </div>
        <?php foreach ([
          ['fa-piggy-bank',  'Accumulate',  'Mine or earn UQX through referrals and tickets.'],
          ['fa-percent',     '5% Rule',     'Each period you can withdraw up to 5% of your total balance.'],
          ['fa-inbox',       'Request',     'Enter your BEP20 wallet and submit the form.'],
          ['fa-user-shield', 'Review',      'Admin verifies and processes within 24&ndash;72h.'],
          ['fa-circle-down', 'Receive',     'UQX arrives in your BSC / BNB Chain wallet.'],
        ] as $i => [$icon,$title,$desc]): ?>
        <div class="step-row">
          <div class="step-num"><?= $i+1 ?></div>
          <div>
            <p class="step-t">
              <i class="fa-solid <?= $icon ?>" style="color:var(--gold);font-size:.72rem;margin-right:.4rem"></i><?= $title ?>
            </p>
            <p class="step-d"><?= $desc ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    </div><!-- /right-col -->
  </div><!-- /main-grid -->

  <!-- ===================== HISTORY ===================== -->
  <?php if (!empty($withdrawals)): ?>
  <div style="margin-top:2.5rem" class="fu fu-5">
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.1rem">
      <i class="fa-solid fa-rectangle-list" style="color:var(--t3);font-size:1rem"></i>
      <h2 style="font-family:'Exo 2',sans-serif;font-weight:800;font-size:1.25rem;color:var(--t1)">Withdrawal History</h2>
    </div>
    <div class="card" style="padding:0;overflow:hidden">
      <div style="overflow-x:auto">
        <table class="tbl">
          <thead>
            <tr>
              <th><i class="fa-solid fa-hashtag" style="margin-right:.3rem;font-size:.55rem"></i>ID</th>
              <th><i class="fa-solid fa-coins" style="margin-right:.3rem;font-size:.55rem"></i>Amount</th>
              <th><i class="fa-solid fa-wallet" style="margin-right:.3rem;font-size:.55rem"></i>Wallet</th>
              <th><i class="fa-solid fa-circle-dot" style="margin-right:.3rem;font-size:.55rem"></i>Status</th>
              <th><i class="fa-regular fa-calendar" style="margin-right:.3rem;font-size:.55rem"></i>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($withdrawals as $w): ?>
            <tr>
              <td style="color:var(--t3);font-family:'Exo 2',sans-serif;font-size:.78rem">#<?= $w['id'] ?></td>
              <td>
                <span style="font-family:'Exo 2',sans-serif;font-weight:800;color:#fff"><?= number_format((float)$w['amount'], 2) ?></span>
                <span style="color:var(--t3);font-size:.75rem;margin-left:.25rem">UQX</span>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:.5rem">
                  <code style="font-size:.75rem;color:rgba(245,197,24,.7)"><?= htmlspecialchars(substr($w['wallet_address'],0,10)) ?>...<?= htmlspecialchars(substr($w['wallet_address'],-6)) ?></code>
                  <span style="font-size:.55rem;color:var(--orange);background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.2);padding:1px 6px;border-radius:4px;font-family:'Exo 2',sans-serif;font-weight:700;letter-spacing:.06em">BEP20</span>
                </div>
              </td>
              <td>
                <?php $st = strtolower($w['status']); ?>
                <span class="status-pill s-<?= $st ?>">
                  <i class="fa-solid <?= $st==='approved' ? 'fa-circle-check' : ($st==='rejected' ? 'fa-circle-xmark' : 'fa-clock') ?>" style="font-size:.55rem"></i>
                  <?= $w['status'] ?>
                </span>
              </td>
              <td style="color:var(--t3);font-size:.78rem;white-space:nowrap"><?= date('M d, Y', strtotime($w['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /container -->

<!-- FOOTER -->
<footer style="border-top:1px solid var(--border);padding:1.25rem;text-align:center;position:relative;z-index:1">
  <p style="font-size:.72rem;color:var(--t3);font-family:'Exo 2',sans-serif;letter-spacing:.06em">
    &copy; <?= date('Y') ?> UMARAE ECOSYSTEM &mdash; ALL RIGHTS RESERVED
  </p>
</footer>

<script>
const maxW   = <?= (float)$maxWithdraw ?>;
const feePct = <?= WITHDRAW_FEE ?>;

function updateFee(val){
  const amt = parseFloat(val);
  const box  = document.getElementById('feeBox');
  if(!val||isNaN(amt)||amt<=0){box.style.display='none';return}
  box.style.display='block';
  const fee = amt*(feePct/100);
  document.getElementById('feeAmt').textContent = amt.toFixed(2)+' UQX';
  document.getElementById('feeDed').textContent = '-'+fee.toFixed(2)+' UQX';
  document.getElementById('feeNet').textContent = (amt-fee).toFixed(2)+' UQX';
}

function setMax(){
  const inp=document.getElementById('amountInput');
  if(!inp)return;
  inp.value=maxW.toFixed(2);
  updateFee(maxW);
}

function validateWallet(val){
  const hint=document.getElementById('walletHint');
  if(!hint)return;
  if(val.length===0){
    hint.innerHTML='<i class="fa-solid fa-shield-halved" style="font-size:.65rem;margin-right:.3rem"></i>Must start with <code style="color:var(--t2);background:rgba(255,255,255,.06);padding:1px 5px;border-radius:4px">0x</code> &mdash; 42 characters total. Wrong address = permanent loss.';
    hint.style.color='var(--t3)';
  }else if(!val.startsWith('0x')){
    hint.innerHTML='<i class="fa-solid fa-circle-xmark" style="margin-right:.35rem"></i>BEP20 address must start with 0x';
    hint.style.color='#F87171';
  }else if(val.length!==42){
    hint.innerHTML='<i class="fa-solid fa-triangle-exclamation" style="margin-right:.35rem"></i>Address must be exactly 42 characters ('+val.length+'/42)';
    hint.style.color='#FDE047';
  }else{
    hint.innerHTML='<i class="fa-solid fa-circle-check" style="margin-right:.35rem"></i>Valid BEP20 address format';
    hint.style.color='#4ADE80';
  }
}

document.getElementById('withdrawForm')?.addEventListener('submit',function(e){
  const amt  = parseFloat(document.getElementById('amountInput')?.value||0);
  const addr = document.getElementById('walletInput')?.value?.trim();
  if(!addr||!addr.startsWith('0x')||addr.length!==42){
    e.preventDefault();
    alert('Please enter a valid BEP20 address (starts with 0x, 42 characters).');
    return;
  }
  if(amt>maxW){
    e.preventDefault();
    alert('Amount exceeds your 5% limit of '+maxW.toFixed(2)+' UQX.');
    return;
  }
  if(!confirm('Confirm Withdrawal\n\nAmount: '+amt.toFixed(2)+' UQX\nNetwork: BEP20 (BSC)\nTo: '+addr.slice(0,14)+'...'+addr.slice(-6)+'\n\nYou receive: '+(amt-amt*(feePct/100)).toFixed(2)+' UQX after '+feePct+'% fee.\n\nProceed?'))
    e.preventDefault();
});
</script>
</body>
</html>
