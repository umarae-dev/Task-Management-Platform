<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.html'); 
    exit;
}

// Correct path to task helper
require_once __DIR__ . '/../task/_task_helper.php'; 

$user_id = (int)$_SESSION['user_id'];

// Get user balance (credits, debits, pending withdrawals)
$bal = user_balance($conn, $user_id);

// Recent wallet ledger (last 50 transactions)
$stmt = $conn->prepare("SELECT * FROM wallet_ledger WHERE user_id=? ORDER BY id DESC LIMIT 50");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$ledger = [];
while ($row = $res->fetch_assoc()) {
    $ledger[] = $row;
}

// PROOF_URL define (agar task reward ke proofs dikhana ho)

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Wallet Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<style>
:root {
  --bg: #f5f7fa;
  --text: #111;
  --card-bg: #fff;
  --accent: #2d89ef;
  --nav-bg: #fff;
  --nav-text: #111;
  --shadow: rgba(0,0,0,0.1);
}

/* 🌙 Dark theme */
body.dark {
  --bg: #0f1317;
  --text: #e9eef2;
  --card-bg: #1b1f24;
  --accent: #25d366;
  --nav-bg: #14181b;
  --nav-text: #e9eef2;
  --shadow: rgba(255,255,255,0.05);
}

/* Reset */
* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
  font-family: 'Segoe UI', sans-serif;
}

body {
  background: var(--bg);
  color: var(--text);
  transition: background 0.3s, color 0.3s;
}

/* 🌐 Navbar */
.navbar {
  position: sticky;
  top: 0;
  z-index: 999;
  background: var(--nav-bg);
  color: var(--nav-text);
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 20px;
  box-shadow: 0 2px 8px var(--shadow);
}

.navbar .logo {
  font-size: 22px;
  font-weight: bold;
  color: var(--accent);
}

.navbar .left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.navbar .back-btn {
  background: none;
  border: none;
  color: var(--nav-text);
  font-size: 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: color 0.3s;
}
.navbar .back-btn:hover {
  color: var(--accent);
}

.navbar .right {
  display: flex;
  align-items: center;
  gap: 15px;
}

.theme-toggle {
  font-size: 20px;
  cursor: pointer;
  color: var(--nav-text);
  transition: color 0.3s;
}
.theme-toggle:hover {
  color: var(--accent);
}

/* 💳 Wallet */
.wallet-wrap {
  max-width: 1000px;
  margin: 40px auto;
  padding: 0 15px;
}

.cards {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
  margin-bottom: 25px;
}

/* 🌈 Gradient border with 5 colors */
.card {
  flex: 1 1 200px;
  background: var(--card-bg);
  padding: 18px;
  border-radius: 12px;
  position: relative;
  overflow: hidden;
  box-shadow: 0 2px 6px var(--shadow);
  transition: background 0.3s, transform 0.2s;
}
.card::before {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 12px;
  padding: 2px;
  background: linear-gradient(90deg, #ff512f, #f09819, #2ebf91, #1e90ff, #9b2fff);
  -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor;
          mask-composite: exclude;
}
.card:hover {
  transform: translateY(-3px);
}
.card .label {
  font-size: 14px;
  color: #777;
}
body.dark .card .label {
  color: #aaa;
}
.card .value {
  font-size: 22px;
  font-weight: bold;
  margin-top: 5px;
  color: var(--accent);
}

/* 📊 Table */
.table-wrap {
  overflow-x: auto;
  background: var(--card-bg);
  padding: 15px;
  border-radius: 10px;
  box-shadow: 0 2px 6px var(--shadow);
}

table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  padding: 12px 10px;
  text-align: left;
  border-bottom: 1px solid rgba(0,0,0,0.05);
  font-size: 14px;
}
body.dark th, body.dark td {
  border-bottom: 1px solid rgba(255,255,255,0.05);
}

th {
  background: #f7f7f7;
}
body.dark th {
  background: #1f2429;
}
.empty {
  text-align: center;
  color: #999;
  padding: 25px 0;
}

/* 📱 Responsive Design */
@media (max-width: 768px) {
  .navbar {
    padding: 12px 15px;
  }
  .cards {
    flex-direction: column;
  }
  .card {
    min-height: auto;
    padding: 15px;
  }
  .wallet-wrap {
    margin: 20px auto;
  }
}

@media (max-width: 420px) {
  .navbar .logo {
    font-size: 18px;
  }
  .theme-toggle {
    font-size: 18px;
  }
  .navbar .back-btn {
    font-size: 14px;
  }
  .card {
    padding: 14px;
  }
  .card .value {
    font-size: 20px;
  }
}
</style>
</head>
<body>

<!-- 🌐 Navbar -->
<div class="navbar">
  <div class="left">
    <button class="back-btn" onclick="window.location.href='../task/dashboard.php'">
      <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
    </button>
  </div>
  <div class="logo">Umarae Wallet</div>
  <div class="right">
    <i class="fa-solid fa-moon theme-toggle" id="themeToggle"></i>
  </div>
</div>

<!-- 💳 Wallet Section -->
<div class="wallet-wrap">
  <div class="cards">
    <div class="card">
      <div class="label">Available Balance</div>
      <div class="value"><?= number_format($bal['balance'],2) ?></div>
    </div>
    <div class="card">
      <div class="label">Total Earned</div>
      <div class="value"><?= number_format($bal['credits'],2) ?></div>
    </div>
    <div class="card">
      <div class="label">Total Withdrawn</div>
      <div class="value"><?= number_format($bal['debits'],2) ?></div>
    </div>
    <div class="card">
      <div class="label">Pending Withdrawals</div>
      <div class="value"><?= number_format($bal['pending_withdraw'],2) ?></div>
    </div>
  </div>

  <h3 style="margin-bottom:10px;">Recent Transactions</h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Type</th>
          <th>Direction</th>
          <th>Amount</th>
          <th>Description</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ledger as $l): ?>
          <tr>
            <td><?= (int)$l['id'] ?></td>
            <td><?= htmlspecialchars($l['source_type']) ?> #<?= (int)$l['source_id'] ?></td>
            <td><?= htmlspecialchars($l['direction']) ?></td>
            <td><?= number_format($l['amount'],2) ?></td>
            <td><?= htmlspecialchars($l['description'] ?: '') ?></td>
            <td><?= $l['created_at'] ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($ledger)): ?>
          <tr><td colspan="6" class="empty">No transactions yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// 🌗 Dark Mode Toggle
const themeToggle = document.getElementById('themeToggle');
themeToggle.addEventListener('click', () => {
  document.body.classList.toggle('dark');
  themeToggle.classList.toggle('fa-moon');
  themeToggle.classList.toggle('fa-sun');
  localStorage.setItem('theme', document.body.classList.contains('dark') ? 'dark' : 'light');
});

// Load saved theme
if (localStorage.getItem('theme') === 'dark') {
  document.body.classList.add('dark');
  themeToggle.classList.replace('fa-moon', 'fa-sun');
}
</script>

</body>
</html>
