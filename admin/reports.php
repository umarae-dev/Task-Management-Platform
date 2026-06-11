
<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();

// Admin check
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in']!==true){
    header("Location: admin_login.php");
    exit;
}

// Filters
$filter_user = $_GET['user'] ?? '';
$filter_task = $_GET['task'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search_query = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page-1)*$limit;

// Fetch users and tasks for dropdown
$users_list = [];
$res = $conn->query("SELECT id,name,email FROM users ORDER BY name ASC");
while($row = $res->fetch_assoc()) $users_list[] = $row;

$tasks_list = [];
$res = $conn->query("SELECT id,title,reward_amount FROM tasks ORDER BY title ASC");
while($row = $res->fetch_assoc()) $tasks_list[] = $row;

// Build query
$sql = "SELECT ts.id, ts.user_id, ts.task_id, ts.proof_text, ts.proof_image, ts.status, ts.created_at, ts.submitted_at,
        u.name as user_name, u.email as user_email, t.title as task_title, t.reward_amount
        FROM task_submissions ts
        JOIN users u ON ts.user_id=u.id
        JOIN tasks t ON ts.task_id=t.id
        WHERE 1";

if(!empty($filter_user) && $filter_user!=='All') $sql .= " AND ts.user_id=".intval($filter_user);
if(!empty($filter_task) && $filter_task!=='All') $sql .= " AND ts.task_id=".intval($filter_task);
if(!empty($filter_status) && $filter_status!=='All') $sql .= " AND ts.status='". $conn->real_escape_string($filter_status) ."'";
if(!empty($search_query)){
    $esc = $conn->real_escape_string($search_query);
    $sql .= " AND (u.name LIKE '%$esc%' OR u.email LIKE '%$esc%' OR t.title LIKE '%$esc%')";
}

// Count total
$resCount = $conn->query($sql);
$total_submissions = $resCount->num_rows;

// Limit for current page
$sql .= " ORDER BY ts.id DESC LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);
$reports = [];
while($row = $result->fetch_assoc()) $reports[] = $row;

// Next page check
$next_page = ($offset + $limit) < $total_submissions ? $page+1 : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Task Reports</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body { font-family:'Inter',sans-serif; background:#0f2027;color:#fff;margin:0;padding:20px;}
h2{text-align:center;color:#00ffff;margin-bottom:80px; text-shadow:0 0 10px #00ffff;}
a.back-link{color:#0ff;text-decoration:none;margin-bottom:15px;display:block;text-align:center;font-weight:700;}
a.back-link:hover{text-shadow:0 0 8px #0ff;}
.table-wrapper{overflow-x:auto;margin-top:20px;border-radius:12px;}
table{width:100%;border-collapse:collapse;backdrop-filter:blur(10px);background: rgba(255,255,255,0.05);border-radius:12px;overflow:hidden;}
th, td{padding:14px 12px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.1);vertical-align: middle;}
th{background: rgba(0,255,255,0.2);color:#0ff;text-transform:uppercase;font-size:13px;letter-spacing:1px;}
tr:hover{background: rgba(0,255,255,0.1);transform: scale(1.01);transition:0.2s;}
select,input[type=text]{padding:6px 10px;border-radius:8px;border:1px solid rgba(255,255,255,0.3);background: rgba(0,0,0,0.2);color:#fff;font-weight:600;margin-right:5px;}
select option{background:#203a43;color:#fff;}
select option:hover{background:#0ff;color:#000;}
button{padding:6px 14px;border:none;border-radius:12px;font-weight:600;cursor:pointer;transition:0.3s;}
button.update{background: linear-gradient(45deg,#00ffff,#0ff);color:#000;box-shadow:0 0 6px #0ff;}
button.update:hover{box-shadow:0 0 14px #0ff;transform:translateY(-2px);}
button.load-more{display:block;margin:20px auto;background:#0ff;color:#000;font-weight:700;}
.status-label{font-weight:600;padding:5px 12px;border-radius:12px;display:inline-block;font-size:12px;min-width:80px;text-align:center;text-shadow:0 0 4px #000;}
.status-Pending{background:#f59e0b;color:#fff;box-shadow:0 0 6px #f59e0b;}
.status-Approved{background:#28a745;color:#fff;box-shadow:0 0 6px #28a745;}
.status-Rejected{background:#dc3545;color:#fff;box-shadow:0 0 6px #dc3545;}
.completed-text{font-weight:600;margin-left:5px;}
@media(max-width:768px){th, td{font-size:12px;padding:8px;}button{font-size:12px;padding:4px 8px;}select,input[type=text]{font-size:12px;}}
.filter-form{margin-bottom:20px;text-align:center;}
</style>
</head>
<body>
     <?php include '../../includes/loader.php'; ?>
<nav style="position:fixed;top:0;left:0;width:100%;height:80px;display:flex;align-items:center;justify-content:center;background:linear-gradient(90deg,#0f2027,#203a43,#2c5364);box-shadow:0 4px 12px rgba(0,0,0,0.5);z-index:999;">
  <a href="#" style="display:flex;align-items:center;text-decoration:none;">
    <!-- Slightly Larger 3D Ultra Logo -->
    <svg width="400" height="80" viewBox="0 0 800 120" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="neonTop" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#00ffff"/>
          <stop offset="50%" stop-color="#0ff"/>
          <stop offset="100%" stop-color="#0088ff"/>
        </linearGradient>
        <linearGradient id="deepExtrude" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#001122" stop-opacity="0.95"/>
          <stop offset="100%" stop-color="#000000" stop-opacity="1"/>
        </linearGradient>
        <linearGradient id="highlight" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#ffffff" stop-opacity="0.2"/>
          <stop offset="100%" stop-color="#00ffff" stop-opacity="0.1"/>
        </linearGradient>
        <filter id="glow" x="-50%" y="-50%" width="200%" height="200%">
          <feGaussianBlur stdDeviation="2.5" result="blur"/>
          <feMerge>
            <feMergeNode in="blur"/>
            <feMergeNode in="SourceGraphic"/>
          </feMerge>
        </filter>
      </defs>

      <g font-family="Inter, sans-serif" font-weight="900" font-size="60" text-anchor="middle" dominant-baseline="middle" transform="translate(400,60)">
        <!-- 10-layer extrusion for depth -->
        <text x="10" y="10" fill="url(#deepExtrude)">Umarae</text>
        <text x="8" y="8" fill="url(#deepExtrude)">Umarae</text>
        <text x="6" y="6" fill="url(#deepExtrude)">Umarae</text>
        <text x="4" y="4" fill="url(#deepExtrude)">Umarae</text>
        <text x="2" y="2" fill="url(#deepExtrude)">Umarae</text>

        <!-- Neon top face with glow -->
        <text x="0" y="0" fill="url(#neonTop)" style="filter:url(#glow)">Umarae</text>

        <!-- Cinematic highlights -->
        <text x="0" y="0" fill="url(#highlight)">Umarae</text>
      </g>
    </svg>
  </a>
</nav>
<h2>Task Reports</h2>
<a href="admin_dashboard.php" class="back-link">⬅ Back to Dashboard</a>

<form method="get" class="filter-form">
    <select name="user">
        <option value="All">All Users</option>
        <?php foreach($users_list as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= ($filter_user==(int)$u['id'])?'selected':'' ?>>
                <?= htmlspecialchars($u['name'].' ('.$u['email'].')') ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="task">
        <option value="All">All Tasks</option>
        <?php foreach($tasks_list as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= ($filter_task==(int)$t['id'])?'selected':'' ?>>
                <?= htmlspecialchars($t['title'].' ($'.$t['reward_amount'].')') ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="status">
        <option value="All">All Status</option>
        <option value="Pending" <?= ($filter_status==='Pending')?'selected':'' ?>>Pending</option>
        <option value="Approved" <?= ($filter_status==='Approved')?'selected':'' ?>>Approved</option>
        <option value="Rejected" <?= ($filter_status==='Rejected')?'selected':'' ?>>Rejected</option>
    </select>

    <input type="text" name="search" placeholder="Search user/task..." value="<?= htmlspecialchars($search_query) ?>">
    <button type="submit" class="update">Search</button>
</form>

<div class="table-wrapper">
<table>
<thead>
<tr>
<th>ID</th>
<th>User</th>
<th>Email</th>
<th>Task</th>
<th>Proof Text</th>
<th>Proof Image</th>
<th>Status</th>
<th>Created At</th>
<th>Submitted At</th>
<th>Reward Amount</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach($reports as $r): ?>
<tr>
    <td><?= (int)($r['id'] ?? 0) ?></td>
    <td><?= htmlspecialchars($r['user_name'] ?? 'N/A') ?></td>
    <td><?= htmlspecialchars($r['user_email'] ?? 'N/A') ?></td>
    <td><?= htmlspecialchars($r['task_title'] ?? 'N/A') ?></td>
    <td><?= htmlspecialchars($r['proof_text'] ?? 'N/A') ?></td>
    <td>
       <?php 
$img_path = !empty($r['proof_image']) ? '/uploads/proofs/' . $r['proof_image'] : '';
if($img_path && file_exists(__DIR__ . '/../uploads/proofs/' . $r['proof_image'])): ?>
    <a href="<?= htmlspecialchars($img_path) ?>" target="_blank">View Image</a>
<?php else: ?>
    N/A
<?php endif; ?>
    </td>
    <?php $status = $r['status'] ?? 'Pending'; ?>
    <td><span class="status-label status-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></span></td>
    <td><?= htmlspecialchars($r['created_at'] ?? 'N/A') ?></td>
    <td><?= htmlspecialchars($r['submitted_at'] ?? 'N/A') ?></td>
    <td>$<?= htmlspecialchars($r['reward_amount'] ?? 0) ?></td>
    <td style="text-align:center;">
        <?php if($status==='Pending'): ?>
            <span>—</span>
        <?php else: ?>
            <span class="completed-text" style="color:<?= $status==='Approved'?'#28a745':'#dc3545' ?>;">Completed</span>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php if($next_page): ?>
<form method="get" style="text-align:center;">
    <input type="hidden" name="user" value="<?= htmlspecialchars($filter_user) ?>">
    <input type="hidden" name="task" value="<?= htmlspecialchars($filter_task) ?>">
    <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
    <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
    <input type="hidden" name="page" value="<?= $next_page ?>">
    <button type="submit" class="load-more">Load More</button>
</form>
<?php endif; ?>

</body>
</html>
