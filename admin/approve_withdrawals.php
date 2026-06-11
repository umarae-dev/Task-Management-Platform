
<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

session_start();
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in']!==true){
    header("Location: admin_login.php"); exit;
}

// Approve withdrawal
if(isset($_POST['approve_id'])){
    $id = (int)$_POST['approve_id'];
    $conn->begin_transaction();
    try {
        $w_res = $conn->query("SELECT * FROM task_withdrawals WHERE id=$id FOR UPDATE");
        $w = $w_res->fetch_assoc();

        if(!$w || $w['status']!=='Pending') throw new Exception("Invalid withdrawal");

        $user_id = (int)$w['user_id'];
        $amount = (float)$w['amount'];

        // Check available wallet balance
        $bal_res = $conn->query("
            SELECT IFNULL(SUM(CASE WHEN direction='credit' THEN amount ELSE 0 END),0)
                 - IFNULL(SUM(CASE WHEN direction='debit' THEN amount ELSE 0 END),0) AS available
            FROM wallet_ledger
            WHERE user_id=$user_id FOR UPDATE
        ");
        $available = (float)$bal_res->fetch_assoc()['available'];
        if($amount > $available) throw new Exception("Insufficient wallet balance");

        $admin_id = (int)($_SESSION['admin_id'] ?? 0);

        // Update withdrawal status
        $conn->query("UPDATE task_withdrawals SET status='Approved', processed_by=$admin_id, processed_at=NOW() WHERE id=$id");

        // Deduct wallet
        $stmt = $conn->prepare("INSERT INTO wallet_ledger (user_id, source_type, source_id, direction, amount, description, created_at) VALUES (?, 'Withdraw', ?, 'debit', ?, 'Withdrawal paid', NOW())");
        $stmt->bind_param("iid", $user_id, $id, $amount);
        $stmt->execute();

        $conn->commit();
    } catch(Exception $e){
        if($conn->in_transaction()) $conn->rollback();
        header("Location: withdrawals.php?error=".$e->getMessage());
        exit;
    }

    header("Location: withdrawals.php?approved=1"); exit;
}

// Reject withdrawal
if(isset($_POST['reject_id'])){
    $id = (int)$_POST['reject_id'];
    $note = $conn->real_escape_string(trim($_POST['note'] ?? ''));
    $admin_id = (int)($_SESSION['admin_id'] ?? 0);

    $conn->query("UPDATE task_withdrawals SET status='Rejected', admin_note='$note', processed_by=$admin_id, processed_at=NOW() WHERE id=$id AND status='Pending'");

    // NOTE: No wallet change on reject

    header("Location: withdrawals.php?rejected=1"); exit;
}

// Fetch withdrawals for admin display
$status_filter = $_GET['status'] ?? 'all';
$where = in_array($status_filter,['Pending','Approved','Rejected']) ? "WHERE w.status='$status_filter'" : '';

$items = [];
$res = $conn->query("
    SELECT w.*, u.name AS user_name
    FROM task_withdrawals w
    JOIN users u ON u.id=w.user_id
    $where
    ORDER BY w.id DESC
");
while($row = $res->fetch_assoc()) $items[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Withdrawals</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
<style>
body { font-family: 'Segoe UI', sans-serif; background: #f4f6f8; margin:0; padding:20px; }
h2 { margin-bottom:20px; text-align:center; }
a.dashboard-btn { display:inline-block; text-decoration:none; color:#fff; background:#2d89ef; padding:8px 14px; border-radius:5px; margin-bottom:15px; }
.table-wrap { overflow-x:auto; }
table { width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.05); }
th, td { padding:12px 10px; border-bottom:1px solid #ddd; text-align:left; vertical-align:middle; }
th { background:#2d89ef; color:#fff; text-align:center; }
tr:nth-child(even) { background:#f9f9f9; }
button { padding:6px 12px; border:none; border-radius:4px; cursor:pointer; margin:2px; }
button.approve { background:#28a745; color:#fff; }
button.reject { background:#dc3545; color:#fff; }
input[type=text] { padding:4px 6px; margin:2px; width:120px; border-radius:4px; border:1px solid #ccc; }
form.inline { display:inline-block; vertical-align:middle; }

/* Modern status labels */
.status-label { font-weight:600; padding:4px 8px; border-radius:6px; display:inline-block; margin-top:6px; text-align:center; font-size:13px; min-width:80px; }
.status-pending { background:#ffc107; color:#000; }
.status-approved { background:#28a745; color:#fff; }
.status-rejected { background:#dc3545; color:#fff; }

/* Completed labels */
.completed-label { font-weight:600; padding:4px 8px; border-radius:6px; display:inline-block; font-size:13px; min-width:80px; text-align:center; margin-top:5px; }
.completed-approved { background:#28a745; color:#fff; }
.completed-rejected { background:#dc3545; color:#fff; }
.completed-pending { background:#ffc107; color:#000; }

/* Filters */
.filters { margin-bottom:25px; text-align:center; }
.filters a { padding:6px 12px; margin-right:5px; border-radius:4px; text-decoration:none; color:#fff; background:#2d89ef; }
.filters a.active { background:#1a5fb4; }
.dashboard-btn:{}
/* Centered Action Column */
.action-cell { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px; }

#loadMoreBtn { display:block; margin:15px auto; padding:8px 16px; background:#2d89ef; color:#fff; border:none; border-radius:5px; cursor:pointer; }
#alertMsg { text-align:center; font-weight:bold; padding:10px; border-radius:5px; margin-bottom:10px; display:none; }

@media(max-width:768px) { 
    th, td { font-size:14px; } 
    input[type=text]{ width:80px; } 
    .status-label, .completed-label { font-size:12px; padding:3px 6px; }
}
</style>
</head>
<body>
     <?php include '../../includes/loader.php'; ?>
<?php include "../../includes/header.php"; ?>
<h2>Withdraw Requests</h2>
<a href="admin_dashboard.php" class="dashboard-btn">⬅ Back To Dashboard</a>

<!-- Status Filter -->
<div class="filters">
    <a href="?status=all" class="<?= $status_filter=='all'?'active':'' ?>">All</a>
    <a href="?status=Pending" class="<?= $status_filter=='Pending'?'active':'' ?>">Pending</a>
    <a href="?status=Approved" class="<?= $status_filter=='Approved'?'active':'' ?>">Approved</a>
    <a href="?status=Rejected" class="<?= $status_filter=='Rejected'?'active':'' ?>">Rejected</a>
</div>

<div id="alertMsg"></div>

<div class="table-wrap">
<table>
<thead>
<tr>
<th>#</th>
<th>User</th>
<th>Amount</th>
<th>Method</th>
<th>Account</th>
<th>Status</th>
<th>Requested</th>
<th>Action</th>
</tr>
</thead>
<tbody id="withdrawTbody">
<?php foreach ($items as $it): ?>
<tr class="withdraw-row">
<td><?= (int)$it['id'] ?></td>
<td><?= htmlspecialchars($it['user_name']) ?> (ID: <?= (int)$it['user_id'] ?>)</td>
<td><?= number_format($it['amount'],2) ?></td>
<td><?= htmlspecialchars($it['method']) ?></td>
<td><?= htmlspecialchars($it['account_details']) ?></td>
<td>
<?php
$status_class = 'status-pending';
if ($it['status']=='Approved') $status_class='status-approved';
elseif ($it['status']=='Rejected') $status_class='status-rejected';
?>
<span class="status-label <?= $status_class ?>"><?= htmlspecialchars($it['status']) ?></span>
<?php if ($it['admin_note']): ?>
<div style="font-size:12px; margin-top:2px; color:#555;">Note: <?= htmlspecialchars($it['admin_note']) ?></div>
<?php endif; ?>
</td>
<td><?= $it['created_at'] ?></td>
<td class="action-cell">
<?php if ($it['status']=='Pending'): ?>
<form method="post" style="margin:0;">
<input type="hidden" name="approve_id" value="<?= (int)$it['id'] ?>">
<button type="submit" class="approve">Approve</button>
</form>
<form method="post" style="margin:0;">
<input type="hidden" name="reject_id" value="<?= (int)$it['id'] ?>">
<input type="text" name="note" placeholder="Reason (optional)">
<button type="submit" class="reject">Reject</button>
</form>
<?php endif; ?>
<span class="completed-label <?= $status_class ?>"><?= htmlspecialchars($it['status']=='Pending'?'Pending':'Completed') ?></span>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php if(count($items) > 10): ?>
<button id="loadMoreBtn">Load More</button>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function(){
    const rows = document.querySelectorAll(".withdraw-row");
    const loadBtn = document.getElementById("loadMoreBtn");
    const defaultVisible = 10;
    let visibleCount = defaultVisible;

    rows.forEach((row,i)=>{ if(i>=defaultVisible) row.style.display="none"; });

    if(loadBtn){
        loadBtn.addEventListener("click", function(){
            const remaining = rows.length - visibleCount;
            const next = remaining>=defaultVisible? defaultVisible : remaining;
            for(let i=visibleCount;i<visibleCount+next;i++){
                if(rows[i]) rows[i].style.display="table-row";
            }
            visibleCount += next;
            if(visibleCount>=rows.length) loadBtn.style.display="none";
        });
    }

    // Show alert based on GET param
    const urlParams = new URLSearchParams(window.location.search);
    const alertMsg = document.getElementById('alertMsg');
    if(urlParams.get('approved')){ alertMsg.textContent='Withdrawal Approved Successfully!'; alertMsg.style.background='#28a745'; alertMsg.style.color='#fff'; alertMsg.style.display='block'; }
    if(urlParams.get('rejected')){ alertMsg.textContent='Withdrawal Rejected Successfully!'; alertMsg.style.background='#dc3545'; alertMsg.style.color='#fff'; alertMsg.style.display='block'; }
    if(urlParams.get('error')){ alertMsg.textContent=urlParams.get('error'); alertMsg.style.background='#ffc107'; alertMsg.style.color='#000'; alertMsg.style.display='block'; }
});
</script>

</body>
</html>
