
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
<meta charset="UTF-8">
<title>User Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="../includes/uqxreminder.css">
<link rel="stylesheet" href="/task/Dashboard-t.css">

<script>
// ---- Theme bootstrap (runs before paint) ----
(function(){
  const saved = localStorage.getItem('theme');
  let theme = saved ? saved : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  document.documentElement.setAttribute('data-theme', theme);
})();
</script>

</head>
<body>
 <?php include '../includes/loader.php'; ?>
 <!--reminder pupup-->
 
 <div class="reminder-box" id="miningReminder">
  <span class="popup-close">&times;</span>
  
  <!-- Logo -->
  <img src="../uqxmining/icons/icon-v2.png" alt="Umarae Logo">
  <span style='color:white; font-weight: bold;'>Hi, <?php echo htmlspecialchars($name); ?></span>

  
  <!-- Mining message -->
  <h2 id="miningHeading"></h2>
  <p id="miningText"></p>
</div>
<div class="navbar">
   <div class="nav-center">
 <div class="brand-name">
  <img src="../uqxmining/icons/logo.png" alt="Umarae" class="brand-logo">
  <strong class="brand-gradient">Umarae</strong></div>
  </div>
  <div class="nav-icons">

    
    <!-- theme toggle -->
    <i class="fa" id="themeToggle" title="Toggle theme" style="cursor:pointer"></i>
 <!-- 🔔 Motivational Notification Dropdown -->
<div class="notif-wrapper">
  <button class="notification-btn">
    <i class="fa fa-bell" title="Notifications"></i>
    <span class="notif-badge"></span>
  </button>

  <div class="notification-content">
    <!-- Header -->
    <div class="notif-header">
      <span>🔔 System Notification</span>
      <span class="notif-close">✖</span>
    </div>

    <!-- Motivational Message -->
    <div class="notif-item" id="rotating-message">
      Loading messages...
    </div>

    <!-- View All -->
    <a href="../announcement/user_notifications.php" class="view-all">View All Notifications</a>
  </div>
</div>


    <div class="profile-wrap">
    <?php include '../includes/profile_pic.php'; ?>
    </div>
        <i class="fa fa-bars menu-btn" onclick="openSidebar()" aria-hidden="true" title="Menu"></i>
  </div>
</div>





<!-- mobile backdrop (closes sidebar when tapped) -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

<div class="wrapper">
  <!-- Sidebar -->
  <nav class="sidebar" id="sidebar" aria-label="Main navigation">
   <a href="dashboard.php" class="active"><i class="fa fa-home" style="color:white;"></i><span style="margin-left:8px">Home</span></a>
<a href="../user/dashboard.php"><i class="fa fa-tachometer" style="color:#28a745;"></i><span style="margin-left:8px">Dashboard</span></a>
<a href="tasks.php"><i class="fa fa-tasks" style="color:#ffc107;"></i><span style="margin-left:8px">Available Tasks</span></a>
<a href="../pages/serveys.php"><i class="fa fa-tasks" style="color:#ffc107;"></i><span style="margin-left:8px">Available Serveys</span></a>
<a href="withdraw.php"><i class="fa fa-hand-holding-dollar" style="color:#fd7e14;"></i><span style="margin-left:8px">Withdraw Funds</span></a>
<a href="referral_dashboard.php"><i class="fa fa-users" style="color:#e83e8c;"></i><span style="margin-left:8px">View Referrals </span></a>
<a href="../includes/wallet.php"><i class="fa fa-wallet" style="color:purple;"></i><span style="margin-left:8px">Wallet</span></a>
<!-- <a href="task_submit.php?id=<?php echo isset($subs[0]['id']) ? (int)$subs[0]['id'] : 0; ?>"><i class="fa fa-upload" style="color:#17a2b8;"></i><span style="margin-left:8px">Submit Proof</span></a> -->
<a href="history.php"><i class="fa fa-list" style="color:#6f42c1;"></i><span style="margin-left:8px">Task History</span></a>

<a href="withdraw_history.php"><i class="fa fa-clock-rotate-left" style="color:#20c997;"></i><span style="margin-left:8px">Withdraw History</span></a>

<a href="../backend/logout.php"><i class="fa fa-sign-out-alt" style="color:#dc3545;"></i><span style="margin-left:8px">Logout</span></a>
  </nav>

  <!-- Main -->
  <main class="main" id="main">
    <!-- Top Cards -->
    <div class="cards" role="region" aria-label="Wallet summary">
      <div class="card" role="article" aria-label="Available balance">
        <div class="label">Available Balance</div>
        <div class="value">$<?php echo number_format($bal['balance'],2); ?></div>
        <div class="sub">Pending Withdrawals: $<?php echo number_format($bal['pending_withdraw'],2); ?></div>
      </div>
      <div class="card" role="article" aria-label="Total earned">
        <div class="label">Total Earned</div>
        <div class="value">$<?php echo number_format($bal['credits'],2); ?></div>
        <div class="sub smallmuted">All approved task rewards</div>
      </div>
      <div class="card" role="article" aria-label="Total withdrawn">
        <div class="label">Total Withdrawn</div>
        <div class="value">$<?php echo number_format($bal['debits'],2); ?></div>
        <div class="sub smallmuted">Ledger debits incl. withdrawals</div>
      </div>
      <div class="card" role="article" aria-label="Task counts">
        <div class="label">Tasks: Pending / Approved / Rejected</div>
        <div class="value">
          <span class="badge yellow"><?php echo (int)$counts['pending']; ?> Pending</span>
          <span class="badge green"><?php echo (int)$counts['approved']; ?> Approved</span>
          <span class="badge red"><?php echo (int)$counts['rejected']; ?> Rejected</span>
        </div>
        <div class="sub">Available: <?php echo (int)$counts['available']; ?></div>
      </div>
    </div>

    <!-- Chart + Quick actions -->
    <section class="section" aria-labelledby="earningsTitle">
      <h3 id="earningsTitle"><i class="fa fa-chart-line"></i> Earnings Trend (Last 7 Days)</h3>
      <div class="chart-wrap">
        <canvas id="earnChart" role="img" aria-label="Earnings trend chart"></canvas>
      </div>
      <div class="quick" style="margin-top:10px">
        <a class="qbtn" href="tasks.php"><i class="fa fa-bolt"></i><span>Start a Task</span></a>
        <a class="qbtn" href="withdraw.php"><i class="fa fa-sack-dollar"></i><span>Request Withdraw</span></a>
        <a class="qbtn" href="history.php"><i class="fa fa-clock"></i><span>View Task History</span></a>
        <a class="qbtn" href="withdraw_history.php"><i class="fa fa-wallet"></i><span>Withdraw History</span></a>
      </div>
    </section>

    <!-- Recent Transactions -->
    <section class="section" aria-labelledby="transactionsTitle">
      <h3 id="transactionsTitle"><i class="fa fa-receipt"></i> Recent Transactions</h3>
      <div>
      <table class="table" role="table">
        <thead>
          <tr><th>#</th><th>Type</th><th>Dir</th><th>Amount</th><th>Description</th><th>Date</th></tr>
        </thead>
        <tbody>
          <?php if(!empty($ledger)): foreach($ledger as $l): ?>
            <tr>
              <td><?php echo (int)$l['id']; ?></td>
              <td><?php echo htmlspecialchars($l['source_type']).' #'.(int)$l['source_id']; ?></td>
              <td><?php echo htmlspecialchars($l['direction']); ?></td>
              <td>$<?php echo number_format($l['amount'],2); ?></td>
              <td><?php echo htmlspecialchars($l['description'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($l['created_at']); ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6" class="smallmuted">No transactions yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div>
    </section>

    <!-- Recent Submissions + Withdrawals -->
    <section class="section" style="display:grid;grid-template-columns:1fr;gap:16px" aria-labelledby="recentTitle">
      <div>
        <h3><i class="fa fa-list-check"></i> Recent Task Submissions</h3>
        <table class="table" role="table">
          <thead><tr><th>#</th><th>Title</th><th>Status</th><th>Reward</th><th>Submitted</th></tr></thead>
          <tbody>
            <?php if(!empty($subs)): foreach($subs as $s): ?>
              <?php 
                $st = strtolower($s['status']);
                $cls = $st==='approved'?'green':($st==='rejected'?'red':'yellow');
              ?>
              <tr>
                <td><?php echo (int)$s['id']; ?></td>
                <td><?php echo htmlspecialchars($s['title']); ?></td>
                <td><span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($s['status']); ?></span></td>
                <td>$<?php echo number_format($s['reward_amount'],2); ?></td>
                <td><?php echo htmlspecialchars($s['created_at']); ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="5" class="smallmuted">No submissions yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div>
        <h3><i class="fa fa-hand-holding-dollar"></i> Recent Withdrawals</h3>
        <table class="table" role="table">
          <thead><tr><th>#</th><th>Amount</th><th>Method</th><th>Status</th><th>Requested</th></tr></thead>
          <tbody>
            <?php if(!empty($withdraws)): foreach($withdraws as $w): ?>
              <?php 
                $st = strtolower($w['status']);
                $cls = $st==='approved'?'green':($st==='rejected'?'red':'yellow');
              ?>
              <tr>
                <td><?php echo (int)$w['id']; ?></td>
                <td>$<?php echo number_format($w['amount'],2); ?></td>
                <td><?php echo htmlspecialchars($w['method']); ?></td>
                <td><span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($w['status']); ?></span></td>
                <td><?php echo htmlspecialchars($w['created_at']); ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="5" class="smallmuted">No withdrawal requests yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Tips -->
    <section class="section" aria-labelledby="tipsTitle">
      <h3 id="tipsTitle"><i class="fa fa-lightbulb"></i> Tips</h3>
      <div class="smallmuted">
        • First, complete the “Available Tasks.”<br>
        • Check the proof type before submitting.<br>
        • The minimum withdrawal amount is $5.
      </div>
    </section>
  </main>
</div>

<!-- Baaki HTML content -->
<script src="/task/taskreminder.js"></script>
<?php include '../includes/support.php'; ?>
</body>
</html>
