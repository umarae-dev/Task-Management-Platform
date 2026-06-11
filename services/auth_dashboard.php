
<?php
// task/dashboard.php
ini_set('display_errors',1);
error_reporting(E_ALL);

session_start();

// Include 2FA & login check
include '../includes/header_2fa_check.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: ../public/login.html');
  exit;
}

// DB connection & helpers

require_once __DIR__ . '/_task_helper.php';

// ------------------ USER INFO ------------------ //
$user_id = (int)$_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT name, is_email_verified, twofa_enabled, twofa_secret, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) die("❌ User not found.");

// Sanitize name & session store
$name = !empty($user['name']) ? ucwords(strtolower($user['name'])) : "User";
$_SESSION['user_name'] = $name;

// Optional: format join date
$joined_date = (!empty($user['created_at']) && $user['created_at'] !== '0000-00-00 00:00:00') 
    ? date("d M Y", strtotime($user['created_at'])) 
    : "Not Available";

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];


// --- WALLET STATS ---
$bal = user_balance($conn, $user_id); // credits, debits, balance, pending_withdraw

// --- TASK COUNTS ---
$counts = [
  'pending' => 0, 'approved' => 0, 'rejected' => 0, 'available' => 0
];

// Submissions by status
$stmt = $conn->prepare("SELECT status, COUNT(*) c 
                        FROM task_submissions 
                        WHERE user_id=? 
                        GROUP BY status");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
  $s = strtolower($r['status']);
  if ($s === 'pending')  $counts['pending']  = (int)$r['c'];
  if ($s === 'approved') $counts['approved'] = (int)$r['c'];
  if ($s === 'rejected') $counts['rejected'] = (int)$r['c'];
}

// Available (Active & not submitted)
$stmt = $conn->prepare("
  SELECT COUNT(*) c
  FROM tasks t
  WHERE t.status='Active'
    AND NOT EXISTS (
      SELECT 1 FROM task_submissions s 
      WHERE s.task_id=t.id AND s.user_id=?
    )
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$counts['available'] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);

// --- RECENT TRANSACTIONS (Last 10) ---
$stmt = $conn->prepare("SELECT id, source_type, source_id, direction, amount, description, created_at 
                        FROM wallet_ledger 
                        WHERE user_id=? 
                        ORDER BY id DESC 
                        LIMIT 7");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$res = $stmt->get_result();
$ledger = $res->fetch_all(MYSQLI_ASSOC);

// --- RECENT SUBMISSIONS (Last 5) ---
$stmt = $conn->prepare("
  SELECT s.id, s.status, s.created_at, t.title, t.reward_amount, t.proof_type
  FROM task_submissions s
  JOIN tasks t ON t.id=s.task_id
  WHERE s.user_id=?
  ORDER BY s.id DESC
  LIMIT 5
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$res = $stmt->get_result();
$subs = $res->fetch_all(MYSQLI_ASSOC);

// --- WITHDRAWALS (Last 5) ---
$stmt = $conn->prepare("
  SELECT id, amount, method, status, created_at
  FROM task_withdrawals
  WHERE user_id=?
  ORDER BY id DESC
  LIMIT 5
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$res = $stmt->get_result();
$withdraws = $res->fetch_all(MYSQLI_ASSOC);

// --- EARNING TREND (last 7 days credits/debits) ---
$trendLabels = [];
$trendCredit = [];
$trendDebit  = [];
for ($i=6; $i>=0; $i--) {
  $trendLabels[] = date('Y-m-d', strtotime("-$i days"));
}

$mapCredit = array_fill_keys($trendLabels, 0.0);
$mapDebit  = array_fill_keys($trendLabels, 0.0);

// Credits group by date
$stmt = $conn->prepare("
  SELECT DATE(created_at) d, COALESCE(SUM(amount),0) s
  FROM wallet_ledger 
  WHERE user_id=? AND direction='credit' 
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
  GROUP BY DATE(created_at)
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()){
  $mapCredit[$r['d']] = (float)$r['s'];
}

// Debits group by date
$stmt = $conn->prepare("
  SELECT DATE(created_at) d, COALESCE(SUM(amount),0) s
  FROM wallet_ledger 
  WHERE user_id=? AND direction='debit' 
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
  GROUP BY DATE(created_at)
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()){
  $mapDebit[$r['d']] = (float)$r['s'];
}

foreach ($trendLabels as $d) {
  $trendCredit[] = $mapCredit[$d];
  $trendDebit[]  = $mapDebit[$d];
}

?>
