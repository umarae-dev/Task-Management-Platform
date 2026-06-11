
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ================================================================
// ✅ admin_withdrawals.php  — UMARAE Admin: Withdrawal Requests
//
// REQUIRED SQL (run once if not already in DB):
//   ALTER TABLE withdraw_requests ADD COLUMN IF NOT EXISTS tx_hash VARCHAR(255) DEFAULT NULL;
//   ALTER TABLE users ADD COLUMN IF NOT EXISTS last_withdrawal_at DATETIME DEFAULT NULL;
//
// FIXED BUGS:
//   ✅ Reject now resets last_withdrawal_at = NULL → user can re-submit immediately
//   ✅ Added "Reset Lock" button for admin testing
//   ✅ Shows VIP / Standard tier per withdrawal row
// ================================================================
session_start();


// // ---- Admin auth guard ----
// if (!isset($_SESSION['admin_id'])) {
//     header('Location: admin_login.php');
//     exit;
// }

$message = '';
$msgType = '';

// ================================================================
// BACKEND: Handle Approve / Reject actions (MySQLi Safe Transaction)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $withdrawId = (int) ($_POST['withdraw_id'] ?? 0);
    $action     = $_POST['action'] ?? '';

    // ================================================================
    // DIRECT USER COOLDOWN RESET — works without any withdraw record
    // Used when records are deleted from DB or no requests exist
    // ================================================================
    if ($action === 'reset_user_cooldown') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        if ($targetUserId > 0) {
            $stmtDirect = $conn->prepare("UPDATE users SET last_withdrawal_at = NULL WHERE id = ?");
            $stmtDirect->bind_param("i", $targetUserId);
            $stmtDirect->execute();
            $affected = $stmtDirect->affected_rows;
            $stmtDirect->close();
            if ($affected > 0) {
                $message = "🔄 Cooldown cleared for User #{$targetUserId}. They can withdraw immediately.";
                $msgType = 'success';
            } else {
                $message = "User #{$targetUserId} not found or cooldown was already empty.";
                $msgType = 'error';
            }
        } else {
            $message = 'Invalid user ID.';
            $msgType = 'error';
        }
    }
    
    if ($withdrawId && in_array($action, ['approve', 'reject', 'reset_cooldown'])) {
        
        // Fetch withdraw details safely
        $stmtW = $conn->prepare("SELECT * FROM withdraw_requests WHERE id = ? LIMIT 1");
        $stmtW->bind_param("i", $withdrawId);
        $stmtW->execute();
        $request = $stmtW->get_result()->fetch_assoc();
        $stmtW->close();

        if (!$request) {
            $message = 'Withdrawal request not found.';
            $msgType = 'error';

        } elseif ($action === 'reset_cooldown') {
            // ✅ Works on ANY status — admin can reset timer anytime
            $stmtRst = $conn->prepare("UPDATE users SET last_withdrawal_at = NULL WHERE id = ?");
            $stmtRst->bind_param("i", $request['user_id']);
            $stmtRst->execute();
            $stmtRst->close();
            $message = "🔄 Cooldown RESET for User #{$request['user_id']} — timer cleared, can withdraw immediately.";
            $msgType = 'success';

        } elseif ($request['status'] !== 'Pending') {
            $message = 'This request has already been processed.';
            $msgType = 'error';

        } elseif ($action === 'approve') {
            // Require transaction hash for approval
            $txHash = trim($_POST['tx_hash'] ?? '');
            if(empty($txHash)) {
                $message = 'Transaction Hash is required to approve withdrawals.';
                $msgType = 'error';
            } else {
                // Update to Approved
                $stmtApp = $conn->prepare("UPDATE withdraw_requests SET status = 'Approved', tx_hash = ? WHERE id = ?");
                $stmtApp->bind_param("si", $txHash, $withdrawId);
                $stmtApp->execute();
                $stmtApp->close();
                
                $message = "✅ Withdrawal #{$withdrawId} approved successfully!";
                $msgType = 'success';
            }

        } elseif ($action === 'reject') {
            // Safe Database Transaction to Reject, Refund, AND Reset Cooldown
            $conn->begin_transaction();
            try {
                // 1. Mark as rejected
                $stmtRej = $conn->prepare("UPDATE withdraw_requests SET status = 'Rejected' WHERE id = ?");
                $stmtRej->bind_param("i", $withdrawId);
                $stmtRej->execute();
                $stmtRej->close();

                // 2. Refund the balance back to user's UQX Wallet
                $stmtRefund = $conn->prepare("UPDATE uqx_wallet SET balance = balance + ? WHERE user_id = ?");
                $stmtRefund->bind_param("di", $request['amount'], $request['user_id']);
                $stmtRefund->execute();
                $stmtRefund->close();

                // 3. ✅ FIX: Reset cooldown so user can re-submit immediately
                $stmtCooldown = $conn->prepare("UPDATE users SET last_withdrawal_at = NULL WHERE id = ?");
                $stmtCooldown->bind_param("i", $request['user_id']);
                $stmtCooldown->execute();
                $stmtCooldown->close();

                $conn->commit();
                $message = "❌ Withdrawal rejected. " . number_format($request['amount']) . " UQX refunded & cooldown reset. User can re-submit immediately.";
                $msgType = 'warning';
            } catch (Exception $e) {
                $conn->rollback();
                $message = 'Database transaction failed: ' . htmlspecialchars($e->getMessage());
                $msgType = 'error';
            }
        }
    }
}

// ================================================================
// Fetch Filters & Summary counts
// ================================================================
$filter = $_GET['filter'] ?? 'Pending';
$allowedFilters = ['Pending','Approved','Rejected','all'];
if (!in_array($filter, $allowedFilters)) {
    $filter = 'Pending';
}

// Fetch requests
// Fetch requests - UPDATED TO LEFT JOIN TO ENSURE RECORDS SHOW UP
if ($filter === 'all') {
    $stmtFetch = $conn->prepare("
        SELECT w.*, u.email, u.user_name, u.user_type, u.last_withdrawal_at AS user_last_wd
        FROM withdraw_requests w
        LEFT JOIN users u ON u.id = w.user_id
        ORDER BY w.id DESC
    ");
} else {
    $stmtFetch = $conn->prepare("
        SELECT w.*, u.email, u.name, u.user_type, u.last_withdrawal_at AS user_last_wd
        FROM withdraw_requests w
        LEFT JOIN users u ON u.id = w.user_id
        WHERE w.status = ?
        ORDER BY w.id DESC
    ");
    $stmtFetch->bind_param("s", $filter);
}
$stmtFetch->execute();
$resFetch = $stmtFetch->get_result();

$withdrawals = [];
while ($row = $resFetch->fetch_assoc()) {
    $withdrawals[] = $row;
}
$stmtFetch->close();

// Fetch summary counts
$countsQuery = $conn->query("SELECT status, COUNT(*) as cnt FROM withdraw_requests GROUP BY status");
$counts = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
if ($countsQuery) {
    while ($row = $countsQuery->fetch_assoc()) {
        $counts[$row['status']] = (int)$row['cnt'];
    }
}
$pendingCount  = $counts['Pending'];
$approvedCount = $counts['Approved'];
$rejectedCount = $counts['Rejected'];

// ================================================================
// Fetch users with ACTIVE cooldown (last_withdrawal_at IS NOT NULL)
// This works even when withdraw_requests table is empty/deleted
// ================================================================
$lockedUsersQuery = $conn->query("
    SELECT id, name, email, user_type, last_withdrawal_at,
           TIMESTAMPDIFF(SECOND, last_withdrawal_at,
               DATE_ADD(last_withdrawal_at, INTERVAL IF(user_type='ticket_holder',7,30) DAY)
           ) - TIMESTAMPDIFF(SECOND, last_withdrawal_at, NOW()) AS secs_left,
           IF(user_type='ticket_holder', 7, 30) AS cycle_days
    FROM users
    WHERE last_withdrawal_at IS NOT NULL
    ORDER BY last_withdrawal_at DESC
");
$lockedUsers = [];
if ($lockedUsersQuery) {
    while ($row = $lockedUsersQuery->fetch_assoc()) {
        $secsLeft = max(0, (int)$row['secs_left']);
        $row['secs_left'] = $secsLeft;
        $row['is_locked'] = $secsLeft > 0;
        $lockedUsers[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Withdrawals | UMARAE</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root { --gold: #F5C518; --gold-dark: #B8860B; }
  body { font-family: 'Rajdhani', sans-serif; background: #060609; }
  .cinzel { font-family: 'Cinzel', serif; }
  
  .badge-pending   { background: rgba(234,179,8,0.12); border: 1px solid rgba(234,179,8,0.3); color: #EAB308; }
  .badge-approved  { background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.3); color: #22C55E; }
  .badge-rejected  { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3); color: #EF4444; }

  table { border-collapse: separate; border-spacing: 0; }
  tr:hover td { background: rgba(245,197,24,0.02) !important; }
  
  ::-webkit-scrollbar { width: 6px; height: 6px; }
  ::-webkit-scrollbar-track { background: #060609; }
  ::-webkit-scrollbar-thumb { background: #1a1a26; border-radius: 4px; }
  ::-webkit-scrollbar-thumb:hover { background: var(--gold-dark); }
</style>
</head>
<body class="min-h-screen text-gray-100 flex flex-col justify-between">

<!-- ADMIN NAV -->
<nav class="border-b border-gray-800/80 bg-black/80 backdrop-blur-md sticky top-0 z-40">
  <div class="max-w-7xl mx-auto px-4 py-3.5 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <span class="cinzel text-xl font-black text-yellow-400 tracking-wider">UMARAE</span>
      <span class="bg-purple-500/10 border border-purple-500/20 text-purple-400 text-[10px] px-2 py-0.5 rounded font-bold cinzel tracking-widest uppercase">Admin Withdrawals</span>
    </div>
    <div class="flex items-center gap-3 text-sm">
      <a href="admin_dashboard.php" class="text-gray-400 hover:text-white text-xs font-medium px-3 py-2 rounded-lg bg-gray-950/40 border border-gray-800 transition-colors">
        ← Back to Dashboard
      </a>
    </div>
  </div>
</nav>

<div class="max-w-7xl mx-auto px-4 py-8 w-full flex-grow">

  <!-- PAGE HEADER & LIVE SEARCH -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
    <div>
      <h1 class="cinzel text-3xl font-black text-white">💸 Payouts & Withdrawals</h1>
      <p class="text-gray-500 mt-1 text-sm">Manage UQX token withdrawal requests from miners and airdrop participants.</p>
    </div>
    <div class="relative w-full md:w-80">
      <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">🔍</span>
      <input type="text" id="adminSearch" onkeyup="filterAdminTable()" 
             placeholder="Search by name, email, wallet..." 
             class="w-full bg-black/50 border border-gray-800 focus:border-purple-500 rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder-gray-600 transition-all outline-none">
    </div>
  </div>

  <!-- SUMMARY STATS -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
    <div class="bg-gradient-to-br from-yellow-950/10 via-black to-black border border-yellow-800/20 rounded-2xl p-6">
      <div class="text-yellow-400 text-xs font-bold cinzel tracking-wider">PENDING PAYOUTS</div>
      <div class="text-4xl font-black text-white mt-1.5 cinzel"><?= number_format($pendingCount) ?></div>
    </div>
    <div class="bg-gradient-to-br from-green-950/10 via-black to-black border border-green-800/20 rounded-2xl p-6">
      <div class="text-green-400 text-xs font-bold cinzel tracking-wider">COMPLETED</div>
      <div class="text-4xl font-black text-white mt-1.5 cinzel"><?= number_format($approvedCount) ?></div>
    </div>
    <div class="bg-gradient-to-br from-red-950/10 via-black to-black border border-red-800/20 rounded-2xl p-6">
      <div class="text-red-400 text-xs font-bold cinzel tracking-wider">REJECTED (REFUNDED)</div>
      <div class="text-4xl font-black text-white mt-1.5 cinzel"><?= number_format($rejectedCount) ?></div>
    </div>
  </div>

  <!-- FLASH MESSAGES -->
  <?php if ($message): ?>
  <div class="mb-6 rounded-xl p-4 flex items-center gap-3 border transition-all animate-pulse <?=
    $msgType === 'success' ? 'bg-green-900/10 border-green-500/25 text-green-400' : 
    ($msgType === 'warning' ? 'bg-yellow-900/10 border-yellow-500/25 text-yellow-400' : 'bg-red-900/10 border-red-500/25 text-red-400') ?>">
    <p class="font-medium text-sm"><?= $message ?></p>
  </div>
  <?php endif; ?>

  <!-- ================================================================ -->
  <!-- COOLDOWN MANAGER — Works directly on users table                  -->
  <!-- Visible even when withdraw_requests is empty or records deleted    -->
  <!-- ================================================================ -->
  <?php if (!empty($lockedUsers)): ?>
  <div class="mb-8 rounded-2xl border border-blue-900/40 bg-blue-950/10 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-3.5 border-b border-blue-900/30 bg-blue-950/20">
      <div class="flex items-center gap-2.5">
        <span class="text-lg">🔒</span>
        <div>
          <h3 class="cinzel font-bold text-blue-300 text-sm tracking-wider">USER COOLDOWN MANAGER</h3>
          <p class="text-blue-500/60 text-xs">Direct reset — works even if withdraw records are deleted</p>
        </div>
      </div>
      <span class="bg-blue-500/15 border border-blue-500/30 text-blue-400 text-xs px-2.5 py-1 rounded-full cinzel font-bold">
        <?= count(array_filter($lockedUsers, fn($u) => $u['is_locked'])) ?> LOCKED
      </span>
    </div>
    <div class="divide-y divide-blue-900/20">
      <?php foreach ($lockedUsers as $lu):
        $d = floor($lu['secs_left'] / 86400);
        $h = floor(($lu['secs_left'] % 86400) / 3600);
        $m = floor(($lu['secs_left'] % 3600) / 60);
        $timeStr = $lu['is_locked'] ? "{$d}d {$h}h {$m}m remaining" : "✅ Unlocked";
        $cycleLabel = ($lu['user_type'] === 'ticket_holder') ? '⭐ VIP 7d' : '👤 Std 30d';
      ?>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-3.5 hover:bg-blue-950/10 transition-colors">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-8 h-8 rounded-full bg-gray-800 border border-gray-700 flex items-center justify-center text-xs font-black text-gray-400 cinzel flex-shrink-0">
            <?= $lu['id'] ?>
          </div>
          <div class="min-w-0">
            <div class="font-bold text-white text-sm truncate"><?= htmlspecialchars($lu['name'] ?? '—') ?></div>
            <div class="text-gray-500 text-xs truncate"><?= htmlspecialchars($lu['email'] ?? '—') ?></div>
          </div>
          <span class="flex-shrink-0 text-[10px] px-2 py-0.5 rounded-full border <?= ($lu['user_type']==='ticket_holder') ? 'bg-yellow-900/20 border-yellow-700/40 text-yellow-400' : 'bg-gray-800 border-gray-700 text-gray-400' ?> cinzel font-bold">
            <?= $cycleLabel ?>
          </span>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
          <div class="text-right">
            <div class="<?= $lu['is_locked'] ? 'text-red-400' : 'text-green-400' ?> text-xs font-bold">
              <?= $lu['is_locked'] ? '🔒 ' . $timeStr : $timeStr ?>
            </div>
            <div class="text-gray-600 text-[10px]">
              Last wd: <?= date('M d, H:i', strtotime($lu['last_withdrawal_at'])) ?>
            </div>
          </div>
          <form method="POST">
            <input type="hidden" name="action" value="reset_user_cooldown">
            <input type="hidden" name="target_user_id" value="<?= $lu['id'] ?>">
            <button type="submit"
              onclick="return confirm('Reset cooldown for <?= htmlspecialchars(addslashes($lu['name'] ?? 'this user')) ?>?\nThey can withdraw immediately after this.')"
              class="bg-blue-600/15 border border-blue-500/40 text-blue-400 hover:bg-blue-600/30 active:scale-95 transition-all text-[11px] font-bold px-4 py-2 rounded-lg cinzel whitespace-nowrap">
              🔄 Reset Lock
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
  <!-- Show a subtle note when no users are locked -->
  <div class="mb-6 flex items-center gap-2.5 text-xs text-gray-700 bg-gray-900/30 border border-gray-800/40 rounded-xl px-4 py-3">
    <span>🔓</span>
    <span>No users currently have an active cooldown lock.</span>
  </div>
  <?php endif; ?>

  <!-- FILTER TABS -->
  <div class="flex flex-wrap gap-2 mb-6 border-b border-gray-900 pb-4">
    <?php foreach (['Pending'=>'⏳','Approved'=>'✅','Rejected'=>'❌','all'=>'📋'] as $f => $icon): ?>
    <a href="?filter=<?= $f ?>"
       class="cinzel text-xs font-bold px-4 py-2.5 rounded-xl transition-all border <?= $filter === $f ? 'bg-purple-500/10 border-purple-500/40 text-purple-400' : 'text-gray-500 hover:text-gray-300 border-transparent bg-transparent' ?>">
      <?= $icon ?> <?= ucfirst($f) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- TABLE -->
  <div class="rounded-2xl border border-gray-900 bg-gray-950/40 backdrop-blur-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-black/60 text-left border-b border-gray-900 text-gray-500">
            <th class="px-5 py-3.5 cinzel text-xs font-normal">ID</th>
            <th class="px-5 py-3.5 cinzel text-xs font-normal">USER DETAILS</th>
            <th class="px-5 py-3.5 cinzel text-xs font-normal">TIER</th>
            <th class="px-5 py-3.5 cinzel text-xs font-normal">AMOUNT (UQX)</th>
            <th class="px-5 py-3.5 cinzel text-xs font-normal">DESTINATION WALLET</th>
            <th class="px-5 py-3.5 cinzel text-xs font-normal">STATUS</th>
            <th class="px-5 py-3.5 cinzel text-xs font-normal">DATE</th>
            <th class="px-5 py-3.5 text-right cinzel text-xs font-normal">ACTIONS / TXN</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-900/60 text-gray-300">
          <?php if (empty($withdrawals)): ?>
          <tr><td colspan="7" class="px-5 py-16 text-center text-gray-600 font-bold">No records found.</td></tr>
          <?php else: foreach ($withdrawals as $w): ?>
          <tr class="table-row-data transition-colors">
            <td class="px-5 py-4 font-mono text-xs text-gray-600">#<?= $w['id'] ?></td>
            <td class="px-5 py-4">
              <div class="font-bold text-white font-name"><?= htmlspecialchars($w['name'] ?? $w['user_name'] ?? '—') ?></div>
              <div class="text-gray-500 text-xs font-email"><?= htmlspecialchars($w['email'] ?? '—') ?></div>
            </td>
            <td class="px-5 py-4">
              <?php if (($w['user_type'] ?? '') === 'ticket_holder'): ?>
              <span class="bg-yellow-900/25 border border-yellow-600/40 text-yellow-400 text-[10px] px-2 py-0.5 rounded-full font-bold cinzel">⭐ VIP</span>
              <div class="text-yellow-700 text-[9px] mt-1">7-day cycle</div>
              <?php else: ?>
              <span class="bg-gray-800 border border-gray-700 text-gray-400 text-[10px] px-2 py-0.5 rounded-full font-bold cinzel">👤 STD</span>
              <div class="text-gray-600 text-[9px] mt-1">30-day cycle</div>
              <?php endif; ?>
            </td>
            <td class="px-5 py-4 text-purple-400 font-bold tracking-wider">
              <?= number_format((float)$w['amount'], 2) ?> UQX
            </td>
            <td class="px-5 py-4 font-mono text-xs text-blue-400 font-wallet break-all max-w-[200px]">
              <?= htmlspecialchars($w['wallet_address']) ?>
            </td>
            <td class="px-5 py-4">
              <span class="badge-<?= strtolower($w['status']) ?> text-[10px] px-2.5 py-1 rounded font-bold uppercase cinzel"><?= $w['status'] ?></span>
            </td>
            <td class="px-5 py-4 text-gray-500 text-xs whitespace-nowrap">
              <?= date('M d, Y', strtotime($w['created_at'])) ?>
            </td>
            <td class="px-5 py-4 text-right">
              <?php if ($w['status'] === 'Pending'): ?>
                <div class="flex flex-col items-end gap-2">
                  <!-- Approve Form -->
                  <form method="POST" class="flex items-center gap-2">
                    <input type="hidden" name="withdraw_id" value="<?= $w['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="text" name="tx_hash" required placeholder="Paste TX Hash here" class="bg-black border border-gray-800 text-xs px-2 py-1.5 rounded w-32 focus:border-green-500 outline-none">
                    <button type="submit" class="bg-green-600/10 border border-green-600/40 text-green-400 hover:bg-green-600/25 text-[10px] font-bold px-3 py-1.5 rounded cinzel">Approve</button>
                  </form>
                  <!-- Reject Form -->
                  <form method="POST" class="inline">
                    <input type="hidden" name="withdraw_id" value="<?= $w['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" onclick="return confirm('Reject & refund balance? Cooldown will also reset so user can re-submit.')" class="bg-red-600/10 border border-red-600/40 text-red-400 hover:bg-red-600/25 text-[10px] font-bold px-3 py-1 rounded cinzel">Reject & Refund</button>
                  </form>
                  <!-- Reset Cooldown -->
                  <form method="POST" class="inline">
                    <input type="hidden" name="withdraw_id" value="<?= $w['id'] ?>">
                    <input type="hidden" name="action" value="reset_cooldown">
                    <button type="submit" onclick="return confirm('Reset this user\'s cooldown timer? They can withdraw immediately after this.')" class="bg-blue-600/10 border border-blue-600/30 text-blue-400 hover:bg-blue-600/20 text-[10px] font-bold px-3 py-1 rounded cinzel">🔄 Reset Lock</button>
                  </form>
                </div>
              <?php elseif ($w['status'] === 'Approved'): ?>
                <div class="flex flex-col items-end gap-2">
                  <div class="text-xs text-green-500 font-mono break-all max-w-[180px] ml-auto">
                    TX: <?= htmlspecialchars($w['tx_hash'] ?? '—') ?>
                  </div>
                  <!-- Reset Cooldown even after Approved -->
                  <form method="POST" class="inline">
                    <input type="hidden" name="withdraw_id" value="<?= $w['id'] ?>">
                    <input type="hidden" name="action" value="reset_cooldown">
                    <button type="submit" onclick="return confirm('Reset cooldown for this user? For testing only.')" class="bg-blue-600/10 border border-blue-600/30 text-blue-400 hover:bg-blue-600/20 text-[10px] font-bold px-3 py-1 rounded cinzel">🔄 Reset Lock</button>
                  </form>
                </div>
              <?php else: ?>
                <div class="flex flex-col items-end gap-2">
                  <span class="text-gray-600 text-xs italic">Refunded to User</span>
                  <!-- Reset Cooldown even after Rejected -->
                  <form method="POST" class="inline">
                    <input type="hidden" name="withdraw_id" value="<?= $w['id'] ?>">
                    <input type="hidden" name="action" value="reset_cooldown">
                    <button type="submit" onclick="return confirm('Reset cooldown for this user?')" class="bg-blue-600/10 border border-blue-600/30 text-blue-400 hover:bg-blue-600/20 text-[10px] font-bold px-3 py-1 rounded cinzel">🔄 Reset Lock</button>
                  </form>
                </div>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function filterAdminTable() {
  const input = document.getElementById("adminSearch");
  const filterVal = input.value.toLowerCase().trim();
  const rows = document.querySelectorAll(".table-row-data");

  rows.forEach(row => {
    const name = row.querySelector(".font-name")?.textContent.toLowerCase() || "";
    const email = row.querySelector(".font-email")?.textContent.toLowerCase() || "";
    const wallet = row.querySelector(".font-wallet")?.textContent.toLowerCase() || "";

    if (name.includes(filterVal) || email.includes(filterVal) || wallet.includes(filterVal)) {
      row.style.display = "";
    } else {
      row.style.display = "none";
    }
  });
}
</script>
</body>
</html>
