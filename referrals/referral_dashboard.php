
<?php
// task_referral_dashboard_final_loadmore.php
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: ../public/login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user for restriction check
$stmt = $conn->prepare("SELECT task_referral_code, restricted FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$stmt->bind_result($task_referral_code, $restricted);
$stmt->fetch();
$stmt->close();

$referral_link = "https://umarae.com/public/register.html?ref=" . $task_referral_code;

// Initialize transfer message
$transfer_msg = '';

// Handle transfer request
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['transfer_amount']) && !$restricted){
    $transfer_amount = floatval($_POST['transfer_amount']);

    $earn_stmt = $conn->prepare("SELECT id, amount FROM task_referral_earnings WHERE referrer_id=? AND status='approved' AND transferred=0 ORDER BY created_at ASC");
    $earn_stmt->bind_param("i",$user_id);
    $earn_stmt->execute();
    $res = $earn_stmt->get_result();

    $available_balance = 0;
    $approved_earnings = [];
    while($row = $res->fetch_assoc()){
        $approved_earnings[] = $row;
        $available_balance += $row['amount'];
    }
    $earn_stmt->close();

    if($transfer_amount >= 10 && $transfer_amount <= $available_balance){
        $remaining = $transfer_amount;
        foreach($approved_earnings as $earn){
            if($remaining <= 0) break;
            $deduct = min($earn['amount'],$remaining);

            $upd_stmt = $conn->prepare("UPDATE task_referral_earnings SET transferred=1 WHERE id=?");
            $upd_stmt->bind_param("i",$earn['id']);
            $upd_stmt->execute();
            $upd_stmt->close();
            $remaining -= $deduct;
        }

        $wallet_desc = "Referral earnings transfer of $$transfer_amount";
        $w_stmt = $conn->prepare("
            INSERT INTO wallet_ledger 
            (user_id, source_type, source_id, direction, amount, description, created_at) 
            VALUES (?, 'Task Referral', 0, 'credit', ?, ?, NOW())
        ");
        $w_stmt->bind_param("ids",$user_id,$transfer_amount,$wallet_desc);
        $w_stmt->execute();
        $w_stmt->close();

        $transfer_msg = "Successfully transferred $$transfer_amount to your wallet!";
    } else {
        $transfer_msg = "Insufficient balance or minimum transfer is $10.";
    }
}

// Fetch all referral earnings
$earnings_stmt = $conn->prepare("
SELECT tre.id, u.name AS referred_name, tre.amount, tre.status, tre.transferred, tre.created_at
FROM task_referral_earnings tre
JOIN users u ON u.id = tre.referred_id
WHERE tre.referrer_id=?
ORDER BY tre.created_at DESC
");
$earnings_stmt->bind_param("i",$user_id);
$earnings_stmt->execute();
$earnings_result = $earnings_stmt->get_result();

$total_earned = 0;
$available_balance = 0;
$earnings_data = [];

while($row = $earnings_result->fetch_assoc()){
    $earnings_data[] = $row;
    $total_earned += $row['amount'];
    if($row['transferred']==0 && $row['status']=='approved'){
        $available_balance += $row['amount'];
    }
}
$earnings_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Task Referral Dashboard</title>
<!-- Google Fonts for Modern Look -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

<style>
    /* --- CSS Variables for Theming --- */
    :root {
        /* Default Dark Theme */
        --bg-color: #0b0c10;
        --card-bg: rgba(31, 40, 51, 0.6);
        --text-color: #e0e0e0;
        --text-muted: #a0a0a0;
        --accent-color: #45a29e;
        --highlight: #66fcf1;
        --nav-bg: rgba(11, 12, 16, 0.95);
        --border-color: rgba(255, 255, 255, 0.1);
        --input-bg: rgba(0, 0, 0, 0.3);
        --success: #2ecc71;
        --danger: #e74c3c;
        --shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        --gold: #f1c40f;
    }

    /* Light Theme Variables */
    body.light-mode {
        --bg-color: #f4f6f8;
        --card-bg: #ffffff;
        --text-color: #333333;
        --text-muted: #666666;
        --accent-color: #0083b0;
        --highlight: #00b4db;
        --nav-bg: rgba(255, 255, 255, 0.95);
        --border-color: rgba(0, 0, 0, 0.1);
        --input-bg: #f0f2f5;
        --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        --gold: #f39c12;
    }

    * { box-sizing: border-box; transition: background 0.3s, color 0.3s; }
    
    body {
        margin: 0;
        padding: 0;
        font-family: 'Poppins', sans-serif;
        background: var(--bg-color);
        color: var(--text-color);
        padding-top: 70px; /* Space for fixed navbar */
        padding-bottom: 40px;
    }

    /* --- Modern Navbar --- */
    header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 60px;
        background: var(--nav-bg);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 15px;
        z-index: 1000;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .nav-left a {
        text-decoration: none;
        color: var(--text-color);
        font-weight: 600;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(255,255,255,0.05);
        padding: 8px 12px;
        border-radius: 50px;
        border: 1px solid var(--border-color);
    }
    
    .nav-left a:hover {
        background: var(--highlight);
        color: #000;
    }

    .nav-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--highlight);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .theme-toggle {
        background: none;
        border: none;
        color: var(--text-color);
        font-size: 18px;
        cursor: pointer;
        padding: 8px;
        border-radius: 50%;
    }
    .theme-toggle:hover { background: rgba(255,255,255,0.1); }

    /* --- Layout & Cards --- */
    .container {
        max-width: 800px; /* More readable width */
        margin: 0 auto;
        padding: 15px;
    }

    .card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 16px;
        box-shadow: var(--shadow);
    }

    .card h3 {
        margin: 0 0 15px 0;
        color: var(--highlight);
        font-size: 18px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* --- Stats Grid --- */
    .stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 20px;
    }
    .stat-box {
        background: rgba(255,255,255,0.03);
        padding: 15px;
        border-radius: 12px;
        text-align: center;
        border: 1px solid var(--border-color);
    }
    .stat-value { font-size: 20px; font-weight: 700; color: var(--success); }
    .stat-label { font-size: 12px; color: var(--text-muted); margin-top: 5px; }

    /* --- New Commission Info Box --- */
    .commission-alert {
        background: rgba(46, 204, 113, 0.1);
        border: 1px solid rgba(46, 204, 113, 0.3);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 15px;
    }
    .commission-alert .icon-box {
        font-size: 24px;
        color: var(--gold);
        background: rgba(241, 196, 15, 0.1);
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .commission-alert .text-box strong {
        display: block;
        color: var(--success);
        font-size: 16px;
        margin-bottom: 5px;
    }
    .commission-alert .text-box p {
        margin: 0;
        font-size: 13px;
        color: var(--text-color);
        line-height: 1.5;
    }
    .step-list {
        margin-top: 10px;
        padding-left: 15px;
        font-size: 13px;
        color: var(--text-muted);
    }
    .step-list li { margin-bottom: 4px; }

    /* --- Forms & Inputs --- */
    .input-group {
        display: flex;
        gap: 10px;
        position: relative;
    }
    
    input.copy-link {
        width: 100%;
        padding: 12px 15px;
        border-radius: 10px;
        border: 1px solid var(--border-color);
        background: var(--input-bg);
        color: var(--text-color);
        font-family: monospace;
        font-size: 14px;
        outline: none;
    }

    button.btn-primary {
        background: linear-gradient(135deg, var(--accent-color), var(--highlight));
        color: #000;
        font-weight: 600;
        border: none;
        padding: 12px 20px;
        border-radius: 10px;
        cursor: pointer;
        white-space: nowrap;
        box-shadow: 0 4px 15px rgba(102, 252, 241, 0.2);
    }
    button.btn-primary:hover { transform: translateY(-2px); }
    button.btn-primary:active { transform: translateY(0); }

    /* --- Table --- */
    .table-wrapper {
        overflow-x: auto;
        border-radius: 12px;
        border: 1px solid var(--border-color);
    }
    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 500px; /* Forces scroll on small screens */
    }
    th, td { padding: 12px 15px; text-align: left; font-size: 14px; }
    th {
        background: rgba(255,255,255,0.05);
        color: var(--highlight);
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    td { border-bottom: 1px solid var(--border-color); color: var(--text-color); }
    tr:last-child td { border-bottom: none; }
    
    .status-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    .status-approved { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
    .status-transferred { background: rgba(128, 128, 128, 0.2); color: #a0a0a0; }

    /* --- Alerts --- */
    .alert {
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 20px;
        font-size: 14px;
        text-align: center;
    }
    .alert-success { background: rgba(46, 204, 113, 0.2); border: 1px solid #2ecc71; color: #2ecc71; }
    .alert-restrict { background: rgba(231, 76, 60, 0.2); border: 1px solid #e74c3c; color: #e74c3c; }

    .filter-select {
        width: 100%;
        padding: 10px;
        margin-bottom: 15px;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        color: var(--text-color);
        border-radius: 8px;
        outline: none;
    }

    #loadMore {
        width: 100%;
        margin-top: 15px;
        background: transparent;
        border: 1px solid var(--highlight);
        color: var(--highlight);
        padding: 10px;
        border-radius: 8px;
        cursor: pointer;
    }
    #loadMore:hover { background: rgba(102, 252, 241, 0.1); }

    /* --- Mobile Tweaks --- */
    @media (max-width: 480px) {
        .nav-title { display: none; } /* Hide title on very small screens to save space */
        .container { padding: 10px; }
        .card { padding: 15px; }
        .input-group { flex-direction: column; }
        button.btn-primary { width: 100%; }
        .nav-left span { display: none; } /* Hide 'Dashboard' text on mobile, keep icon */
    }
</style>
</head>
<body>

<?php include '../includes/loader.php'; ?>

<!-- Navbar -->
<header>
    <div class="nav-left">
        <a href="dashboard.php">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Dashboard</span>
        </a>
    </div>
    <div class="nav-title">Referral Center</div>
    <div class="nav-right">
        <button class="theme-toggle" id="themeToggle">
            <i class="fa-solid fa-sun" id="themeIcon"></i>
        </button>
    </div>
</header>

<div class="container">

    <!-- Restriction Alert -->
    <?php if($restricted): ?>
    <div class="alert alert-restrict">
        <i class="fa fa-exclamation-triangle"></i> Account Restricted. 
        <br>
        <a href="../includes/user_chat.php" style="color:inherit; text-decoration:underline;">Contact Support</a>
    </div>
    <?php endif; ?>

    <!-- Success Message -->
    <?php if($transfer_msg): ?>
        <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?php echo $transfer_msg; ?></div>
    <?php endif; ?>

    <!-- Stats Summary Cards -->
    <div class="card">
        <h3><i class="fa-solid fa-chart-pie"></i> Overview</h3>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value">$<?php echo number_format($total_earned,2); ?></div>
                <div class="stat-label">Total Earned</div>
            </div>
            <div class="stat-box">
                <div class="stat-value" style="color: var(--highlight);">$<?php echo number_format($available_balance,2); ?></div>
                <div class="stat-label">Available</div>
            </div>
        </div>
        
        <!-- Transfer Logic -->
         <?php if(!$restricted): ?>
            <?php if($available_balance >= 10): ?>
            <form method="POST" action="">
                <input type="hidden" name="transfer_amount" value="<?php echo $available_balance; ?>">
                <button type="submit" class="btn-primary" style="width:100%">
                    Transfer to Wallet <i class="fa fa-arrow-right"></i>
                </button>
            </form>
            <?php else: ?>
                <div style="text-align:center; font-size:12px; color:var(--text-muted);">
                    Minimum $10 required to transfer.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Referral Link Section -->
    <div class="card">
        <h3><i class="fa-solid fa-share-nodes"></i> Share & Earn</h3>
        
        <!-- Info Alert Box -->
        <div class="commission-alert">
            <div class="icon-box">
                <i class="fa-solid fa-gift"></i>
            </div>
            <div class="text-box">
                <strong>Earn 5% Commission!</strong>
                <p>Invite friends and earn money when they complete tasks.</p>
                <ol class="step-list">
                    <li>Copy your unique link below.</li>
                    <li>Share it on WhatsApp, Telegram, or Facebook.</li>
                    <li>You get <b>5%</b> of their reward when their task is <span style="color:var(--success)">Approved</span>.</li>
                </ol>
            </div>
        </div>

        <div class="input-group">
            <input type="text" class="copy-link" value="<?php echo htmlspecialchars($referral_link); ?>" id="refLink" readonly>
            <button class="btn-primary" onclick="copyLink()">
                <i class="fa fa-copy"></i> Copy Link
            </button>
        </div>
        
        <div style="margin-top:10px; font-size:12px; color:var(--text-muted); text-align:center;">
            <i class="fa-solid fa-circle-info"></i> Earnings are credited automatically upon approval.
        </div>
    </div>

    <!-- Earnings History -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0;"><i class="fa-solid fa-list"></i> History</h3>
        </div>
        
        <select class="filter-select" id="statusFilter" <?= $restricted ? 'disabled' : '' ?>>
            <option value="all">Filter: Show All</option>
            <option value="approved">Approved Only</option>
            <option value="transferred">Transferred Only</option>
        </select>

        <div class="table-wrapper">
            <table id="earningsTable">
                <thead>
                    <tr>
                        <th width="10%">#</th>
                        <th>User</th>
                        <th>Earned</th>
                        <th>Status</th>
                        <th style="text-align:right">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $count=1;
                    foreach($earnings_data as $row): 
                        $statusClass = $row['transferred'] ? 'transferred' : 'approved';
                        $statusText = $row['transferred'] ? 'Transferred' : 'Approved';
                    ?>
                    <tr class="earning-row">
                        <td><?php echo $count++; ?></td>
                        <td>
                            <i class="fa fa-user-circle" style="margin-right:5px; opacity:0.7;"></i>
                            <?php echo htmlspecialchars($row['referred_name']); ?>
                        </td>
                        <td style="color:var(--success); font-weight:600;">+$<?php echo number_format($row['amount'],2); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $statusClass; ?>">
                                <?php echo $statusText; ?>
                            </span>
                        </td>
                        <td style="text-align:right; font-size:12px; color:var(--text-muted);">
                            <?php echo date('M d', strtotime($row['created_at'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button id="loadMore" <?= $restricted ? 'disabled' : '' ?>>Show More Records</button>
    </div>

</div>

<script>
// --- Theme Toggle Logic ---
const themeToggle = document.getElementById('themeToggle');
const themeIcon = document.getElementById('themeIcon');
const body = document.body;

// Check Local Storage
if(localStorage.getItem('theme') === 'light'){
    body.classList.add('light-mode');
    themeIcon.classList.replace('fa-sun', 'fa-moon');
}

themeToggle.addEventListener('click', () => {
    body.classList.toggle('light-mode');
    if(body.classList.contains('light-mode')){
        localStorage.setItem('theme', 'light');
        themeIcon.classList.replace('fa-sun', 'fa-moon');
    } else {
        localStorage.setItem('theme', 'dark');
        themeIcon.classList.replace('fa-moon', 'fa-sun');
    }
});

// --- Copy Function ---
function copyLink(){
    const copyText = document.getElementById("refLink");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    
    // Modern Clipboard API fallback
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(copyText.value);
    } else {
        document.execCommand("copy");
    }
    
    // Visual Feedback (Button changes momentarily)
    const btn = document.querySelector('.btn-primary i.fa-copy');
    const originalClass = btn.className;
    btn.className = 'fa fa-check';
    btn.innerHTML = ' Copied!';
    setTimeout(() => { 
        btn.className = originalClass; 
        btn.innerHTML = '<i class="fa fa-copy"></i> Copy Link';
    }, 2000);
}

// --- Filter Logic ---
document.getElementById('statusFilter').addEventListener('change', function(){
    const val = this.value;
    const rows = document.querySelectorAll('#earningsTable tbody tr.earning-row');
    rows.forEach(row => {
        const statusText = row.querySelector('.status-badge').innerText.toLowerCase();
        if(val === 'all' || statusText.includes(val)){
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// --- Load More Logic ---
let rowsToShow = 8;
const tableRows = document.querySelectorAll('.earning-row');
// Hide rows initially
tableRows.forEach((row, index) => {
    if(index >= rowsToShow) row.style.display = 'none';
});

document.getElementById('loadMore').addEventListener('click', function(){
    let currentlyVisible = 0;
    tableRows.forEach(row => {
        if(row.style.display !== 'none') currentlyVisible++;
    });

    let nextLimit = currentlyVisible + 8;
    for(let i = currentlyVisible; i < nextLimit && i < tableRows.length; i++){
        tableRows[i].style.display = 'table-row';
    }

    if(nextLimit >= tableRows.length){
        this.style.display = 'none';
    }
});
</script>

<?php include '../includes/task_dashboard_fottor.php'; ?>
</body>
</html>
