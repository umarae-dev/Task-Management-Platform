
<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();

if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true){
    header("Location: admin_login.php");
    exit;
}
define('PROOF_URL','/uploads/proofs');

// -------------------- Approve Submission --------------------
if(isset($_POST['approve_id'])){
    $approve_id = (int)$_POST['approve_id'];
    $note = trim($_POST['approve_note'] ?? '');
    $admin_id = (int)($_SESSION['admin_id'] ?? 0);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("
            SELECT s.*, t.reward_amount, t.reward_currency, u.name AS user_name, u.task_referrer_id
            FROM task_submissions s
            JOIN tasks t ON t.id = s.task_id
            JOIN users u ON u.id = s.user_id
            WHERE s.id = ? FOR UPDATE
        ");
        $stmt->bind_param("i",$approve_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $sub = $res->fetch_assoc();
        if(!$sub) throw new Exception("Submission not found");
        if(($sub['status'] ?? 'Pending') !== 'Pending') throw new Exception("Already handled");

        $reward = floatval($sub['reward_amount']);
        if($reward <= 0) throw new Exception("Invalid reward amount");

        // Optional: high fraud warning
        $fraud_score = intval($sub['fraud_score'] ?? 0);
        if($fraud_score >= 100){
            // Admin should double check before approve
            // Could send alert / log
        }

        $u = $conn->prepare("UPDATE task_submissions SET status='Approved', reviewer_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?");
        $u->bind_param("sii",$note,$admin_id,$approve_id);
        $u->execute();
        $u->close();

        $task_currency = strtoupper($sub['reward_currency'] ?? 'USD');
        if ($task_currency === 'UQX') {
            // Credit UQX to user's UQX wallet
            $ux = $conn->prepare("UPDATE uqx_wallet SET balance = balance + ? WHERE user_id = ?");
            $ux->bind_param("di", $reward, $sub['user_id']);
            $ux->execute();
            $ux->close();
            // Also log in wallet_ledger for record with UQX marker
            $desc_user = "Task UQX reward for submission #$approve_id";
            $i = $conn->prepare("
                INSERT INTO wallet_ledger (user_id, source_type, source_id, direction, amount, description, created_at)
                VALUES (?, 'Task_UQX', ?, 'credit', ?, ?, NOW())
            ");
            $i->bind_param("iids",$sub['user_id'],$approve_id,$reward,$desc_user);
            $i->execute();
            $i->close();
        } else {
            // Standard USD credit
            $desc_user = "Task reward for submission #$approve_id";
            $i = $conn->prepare("
                INSERT INTO wallet_ledger (user_id, source_type, source_id, direction, amount, description, created_at)
                VALUES (?, 'Task', ?, 'credit', ?, ?, NOW())
            ");
            $i->bind_param("iids",$sub['user_id'],$approve_id,$reward,$desc_user);
            $i->execute();
            $i->close();
        }

        // Referral earnings
        $referrer_id = intval($sub['task_referrer_id']);
        if($referrer_id > 0){
            $ref_reward = round($reward*0.05,2);
            $r = $conn->prepare("
                INSERT INTO task_referral_earnings (referrer_id, referred_id, amount, status, transferred, created_at)
                VALUES (?, ?, ?, 'approved', 0, NOW())
            ");
            $r->bind_param("iid",$referrer_id,$sub['user_id'],$ref_reward);
            $r->execute();
            $r->close();
        }

        $conn->commit();
        header("Location: submissions.php?approved=1");
        exit;

    } catch(Throwable $e){
        $conn->rollback();
        error_log("Approve error: ".$e->getMessage());
        header("Location: submissions.php?error=1");
        exit;
    }
}

// -------------------- Reject Submission --------------------
if(isset($_POST['reject_id'])){
    $reject_id = (int)$_POST['reject_id'];
    $note = trim($_POST['reject_note'] ?? '');
    $admin_id = (int)($_SESSION['admin_id'] ?? 0);

    $conn->begin_transaction();
    try{
        $stmt = $conn->prepare("SELECT * FROM task_submissions WHERE id=? FOR UPDATE");
        $stmt->bind_param("i",$reject_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $sub = $res->fetch_assoc();
        if(!$sub) throw new Exception("Submission not found");
        if(($sub['status'] ?? 'Pending') !== 'Pending') throw new Exception("Already handled");

        $stmt = $conn->prepare("UPDATE task_submissions SET status='Rejected', reviewer_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?");
        $stmt->bind_param("sii",$note,$admin_id,$reject_id);
        $stmt->execute();

        $conn->commit();
        header("Location: submissions.php?rejected=1");
        exit;
    } catch(Throwable $e){
        $conn->rollback();
        error_log("Reject error: ".$e->getMessage());
        header("Location: submissions.php?error=1");
        exit;
    }
}

// -------------------- Fetch All Submissions --------------------
$subs = [];
$q = "
SELECT
    s.id AS submission_id,
    s.user_id, s.task_id,
    s.proof_text, s.proof_image, s.status,
    COALESCE(s.created_at, s.submitted_at) AS submitted_at,
    s.reviewer_note, s.reviewed_by, s.reviewed_at,
    s.ip_address, s.ip_type, s.country, s.city, s.vpn_proxy_tor, s.recaptcha_score,
    s.device_fp, s.fraud_score, s.click_verified,
    t.title AS task_title, t.reward_amount,
    u.name AS user_name,
    u.task_referrer_id
FROM task_submissions s
JOIN tasks t ON t.id = s.task_id
JOIN users u ON u.id = s.user_id
ORDER BY s.id DESC
";
$res = $conn->query($q);
if($res && $res->num_rows > 0){
    while($r = $res->fetch_assoc()){
        $subs[] = $r;
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Admin — Task Submissions</title>
<style>
:root{
  --bg:#f4f6f8; --card:#ffffff; --primary:#2d89ef; --muted:#6b7280;
  --success:#16a34a; --danger:#dc2626; --warning:#f59e0b; --accent:#f3f4f6;
  font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial;
}
body{margin:0;background:var(--bg);color:#111;}
.container{max-width:1200px;margin:28px auto;padding:16px;}
.header{display:flex;flex-direction:column;align-items:center;gap:12px;margin-bottom:18px;}
.header h1{margin:0;font-size:22px;color:var(--primary);}
.toolbar{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-bottom:16px;}
.input, select, button{padding:10px 14px;border-radius:20px;border:1px solid #dfe6ee;font-size:14px;}
.input{min-width:240px;}
button{background:var(--primary);color:#fff;border:none;cursor:pointer;font-weight:600;transition:0.2s;}
button:hover{background:#1c5bbf;}
.card{background:var(--card);padding:14px;border-radius:12px;box-shadow:0 6px 18px rgba(16,24,40,0.06);}
.table-wrap{overflow:auto;border-radius:10px;}
table{width:100%;border-collapse:collapse;font-size:14px;min-width:1200px;}
th,td{padding:12px 14px;border-bottom:1px solid #eef2f7;text-align:left;vertical-align:middle;}
th{background:var(--accent);color:#374151;font-weight:600;font-size:13px;}
td.ip-col {max-width: 160px; white-space: normal; word-wrap: break-word; overflow-wrap: break-word; line-height: 1.4; font-size: 13px;}
.badge{display:inline-block;padding:6px 8px;border-radius:999px;font-size:13px;color:#fff;}
.badge.pending{background:var(--warning);}
.badge.approved{background:var(--success);}
.badge.rejected{background:var(--danger);}
.badge.warning{background:#f59e0b;}
.badge.vpn{background:#dc2626;}
.badge.no-vpn{background:#16a34a;}
.badge.high-fraud{background:#991b1b;}
.preview-img{max-height:60px;border-radius:6px;object-fit:cover;}
.modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);z-index:60;}
.modal .box{width:100%;max-width:520px;background:#fff;padding:18px;border-radius:10px;}
.modal.show{display:flex;}
textarea{width:100%;padding:10px;border-radius:8px;border:1px solid #e6e9ee;font-size:14px;min-height:100px;resize:vertical;}
.center{text-align:center;}
</style>
</head>
<body>
 <?php include '../../includes/loader.php'; ?>
<?php include "../../includes/header.php"; ?>
<div class="container">
  <div class="header">
    <h1>Task Submissions</h1>
    <a href="admin_dashboard.php" class="link-dashboard">⬅ Back to Dashboard</a>
    <div class="toolbar">
      <input type="text" placeholder="Search tasks..." id="searchInput" class="input">
      <select id="filterStatus">
        <option value="">All Status</option>
        <option value="Pending">Pending</option>
        <option value="Approved">Approved</option>
        <option value="Rejected">Rejected</option>
      </select>
      <select id="bulkAction">
        <option value="">--Bulk Action--</option>
        <option value="approve">Approve Selected</option>
        <option value="reject">Reject Selected</option>
        <option value="delete">Delete Selected</option>
      </select>
      <button id="applyBulk">Apply</button>
    </div>
  </div>

  <!-- Table Card -->
  <div class="card table-wrap">
    <table id="subsTable">
      <thead>
        <tr>
          <th><input type="checkbox" id="selectAll"></th>
          <th>#</th>
          <th>User</th>
          <th>Task</th>
          <th>Proof</th>
          <th>Reward</th>
          <th>Status</th>
          <th>Submitted</th>
          <th>Reviewed</th>
          <th>IP</th>
          <th>Device FP</th>
          <th>Type</th>
          <th>Country</th>
          <th>City</th>
          <th>VPN</th>
          <th>reCAPTCHA</th>
          <th>Click Verified</th>
          <th>Fraud Score</th>
          <th class="center">Action</th>
        </tr>
      </thead>
      <tbody id="subsBody">
        <?php foreach ($subs as $s): 
          $status = $s['status'] ?? 'Pending';
          $badgeClass = $status === 'Pending' ? 'pending' : ($status === 'Approved' ? 'approved' : 'rejected');
          $vpnBadge = $s['vpn_proxy_tor'] === 'Yes' ? 'vpn' : 'no-vpn';
          $recScore = floatval($s['recaptcha_score']);
          $recBadge = $recScore>=0.5 ? 'approved' : 'rejected';
          $fraud = intval($s['fraud_score'] ?? 0);
          if($fraud <= 30) $fraudClass='approved';
          elseif($fraud<=60) $fraudClass='warning';
          elseif($fraud<=100) $fraudClass='rejected';
          else $fraudClass='high-fraud';
          $clickVerified = intval($s['click_verified'] ?? 0);
        ?>
        <tr>
          <td><input type="checkbox" class="rowCheck" value="<?= $s['submission_id'] ?>"></td>
          <td><?= $s['submission_id'] ?></td>
          <td><?= htmlspecialchars($s['user_name']) ?> (ID: <?= $s['user_id'] ?>)</td>
          <td><?= htmlspecialchars($s['task_title']) ?></td>
          <td>
            <?php if($s['proof_image']): ?>
              <a href="<?= PROOF_URL.'/'.htmlspecialchars($s['proof_image']) ?>" target="_blank">
                <img src="<?= PROOF_URL.'/'.htmlspecialchars($s['proof_image']) ?>" class="preview-img">
              </a>
            <?php else: ?>
              <?= htmlspecialchars($s['proof_text'] ?: '—') ?>
            <?php endif; ?>
          </td>
          <td><?= number_format($s['reward_amount'],2) ?></td>
          <td><span class="badge <?= $badgeClass ?>"><?= $status ?></span></td>
          <td><?= htmlspecialchars($s['submitted_at']) ?></td>
          <td>
            <?php if($s['reviewed_at']): ?>
              <div style="font-weight:600"><?= date('Y-m-d H:i', strtotime($s['reviewed_at'])) ?></div>
              <?php if($s['reviewer_note']): ?>
                <div style="font-size:13px;color:#555;">Comment: <?= htmlspecialchars($s['reviewer_note']) ?></div>
              <?php endif; ?>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td class="ip-col"><?= htmlspecialchars($s['ip_address'] ?: '—') ?></td>
          <td><?= htmlspecialchars($s['device_fp'] ?? '—') ?></td>
          <td><?= htmlspecialchars($s['ip_type'] ?: '—') ?></td>
          <td><?= htmlspecialchars($s['country'] ?: '—') ?></td>
          <td><?= htmlspecialchars($s['city'] ?: '—') ?></td>
          <td><span class="badge <?= $vpnBadge ?>"><?= $s['vpn_proxy_tor'] ?></span></td>
          <td><span class="badge <?= $recBadge ?>"><?= $recScore ?></span></td>
          <td class="center"><?= $clickVerified ? '✅' : '❌' ?></td>
          <td class="center"><span class="badge <?= $fraudClass ?>"><?= $fraud ?></span></td>
          <td class="center">
            <?php if($status==='Pending'): ?>
              <button class="btn" onclick="openApprove(<?= $s['submission_id'] ?>)">✅ Approve</button>
              <button class="btn" style="background:#e5e7eb;color:#111;" onclick="openReject(<?= $s['submission_id'] ?>)">❌ Reject</button>
              <?php if($fraudClass==='high-fraud'): ?>
                <div style="color:#991b1b;font-weight:bold;font-size:12px;">⚠ High Fraud!</div>
              <?php endif; ?>
            <?php elseif($status==='Approved'): ?>
              ✅ <span style="color:green;font-weight:bold;">Completed</span>
            <?php else: ?>
              ❌ <span style="color:red;font-weight:bold;">Completed</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="center" style="margin-top:12px;">
      <button id="loadMore">Load More</button>
    </div>
  </div>

  <!-- Charts Dashboard -->
  <div class="card" style="margin-top:24px;padding:16px;">
    <h2>Analytics Dashboard</h2>
    <div class="charts-grid">
      <div class="chart-card"><canvas id="statusChart"></canvas><button class="exportBtn" data-chart="statusChart">Download PNG</button></div>
      <div class="chart-card"><canvas id="rewardChart"></canvas><button class="exportBtn" data-chart="rewardChart">Download PNG</button></div>
      <div class="chart-card"><canvas id="volumeChart"></canvas><button class="exportBtn" data-chart="volumeChart">Download PNG</button></div>
      <div class="chart-card"><canvas id="fraudChart"></canvas><button class="exportBtn" data-chart="fraudChart">Download PNG</button></div>
      <div class="chart-card"><canvas id="clickChart"></canvas><button class="exportBtn" data-chart="clickChart">Download PNG</button></div>
      <div class="chart-card"><canvas id="vpnChart"></canvas><button class="exportBtn" data-chart="vpnChart">Download PNG</button></div>
      <div class="chart-card"><canvas id="countryChart"></canvas><button class="exportBtn" data-chart="countryChart">Download PNG</button></div>
      <div class="chart-card"><canvas id="deviceChart"></canvas><button class="exportBtn" data-chart="deviceChart">Download PNG</button></div>
    </div>
  </div>

</div> <!-- container end -->

<!-- Approve Modal -->
<div id="modalApprove" class="modal">
  <div class="box">
    <h3>Approve Submission</h3>
    <form method="post">
      <input type="hidden" name="approve_id" id="approve_id">
      <textarea name="approve_note" placeholder="Comment (optional)"></textarea>
      <div style="margin-top:10px;text-align:right">
        <button type="submit" class="btn">Approve</button>
      </div>
    </form>
  </div>
</div>

<!-- Reject Modal -->
<div id="modalReject" class="modal">
  <div class="box">
    <h3>Reject Submission</h3>
    <form method="post">
      <input type="hidden" name="reject_id" id="reject_id">
      <textarea name="reject_note" placeholder="Reason for rejection (optional)"></textarea>
      <div style="margin-top:10px;text-align:right">
        <button type="submit" style="background:#dc2626;color:#fff;">Reject</button>
      </div>
    </form>
  </div>
</div>

<!-- Chart.js & Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
/* --------------------- Table & Modal Scripts --------------------- */
let allRows = Array.from(document.querySelectorAll('#subsBody tr'));
let visibleCount = 10;
function updateTable(){
  const searchTerm = document.getElementById('searchInput').value.toLowerCase();
  const filterStatus = document.getElementById('filterStatus').value.toLowerCase();
  allRows.forEach((tr,i)=>{
    const status = tr.children[6].textContent.toLowerCase();
    const title = tr.children[3].textContent.toLowerCase();
    tr.style.display = (title.includes(searchTerm) && (filterStatus === '' || status===filterStatus)) && i<visibleCount ? '' : 'none';
  });
  updateCharts();
}
document.getElementById('searchInput').addEventListener('input',updateTable);
document.getElementById('filterStatus').addEventListener('change',updateTable);
document.getElementById('loadMore').addEventListener('click',()=>{visibleCount+=10;updateTable();});
document.getElementById('selectAll').addEventListener('change', function(){document.querySelectorAll('.rowCheck').forEach(cb=>cb.checked=this.checked);});
document.getElementById('applyBulk').addEventListener('click',()=>{
  const action = document.getElementById('bulkAction').value;
  const selected = Array.from(document.querySelectorAll('.rowCheck:checked')).map(cb=>cb.value);
  if(!action){ alert('Select action'); return; }
  if(selected.length===0){ alert('Select submissions'); return; }
  const form = document.createElement('form'); form.method='post';
  selected.forEach(id=>{
    const inp = document.createElement('input'); inp.type='hidden'; inp.value=id;
    if(action==='approve') inp.name='approve_id';
    if(action==='reject') inp.name='reject_id';
    if(action==='delete') inp.name='delete_id';
    form.appendChild(inp);
  });
  document.body.appendChild(form); form.submit();
});
function openApprove(id){document.getElementById('approve_id').value=id;document.getElementById('modalApprove').classList.add('show');}
function openReject(id){document.getElementById('reject_id').value=id;document.getElementById('modalReject').classList.add('show');}

/* --------------------- Charts --------------------- */
const charts = {};
function parseTable(){
  const rows = Array.from(document.querySelectorAll('#subsBody tr')).filter(r=>r.style.display!=='none');
  const data = {status:{},reward:{},volume:{},fraud:{},click:{},vpn:{},country:{},device:{}};
  rows.forEach(r=>{
    const status = r.children[6].textContent;
    const reward = parseFloat(r.children[5].textContent)||0;
    const submitted = r.children[7].textContent.split(' ')[0];
    const fraud = parseInt(r.children[17].textContent)||0;
    const click = r.children[16].textContent==='✅'?'Verified':'Unverified';
    const vpn = r.children[14].textContent==='Yes'?'VPN':'No VPN';
    const country = r.children[12].textContent || 'Unknown';
    const device = r.children[10].textContent || 'Unknown';

    data.status[status]=(data.status[status]||0)+1;
    if(reward<=10) data.reward['$0-10']=(data.reward['$0-10']||0)+1;
    else if(reward<=50) data.reward['$10-50']=(data.reward['$10-50']||0)+1;
    else if(reward<=100) data.reward['$50-100']=(data.reward['$50-100']||0)+1;
    else data.reward['$100+']=(data.reward['$100+']||0)+1;
    data.volume[submitted]=(data.volume[submitted]||0)+1;
    if(fraud<=30) data.fraud['Low']=(data.fraud['Low']||0)+1;
    else if(fraud<=60) data.fraud['Medium']=(data.fraud['Medium']||0)+1;
    else if(fraud<=100) data.fraud['High']=(data.fraud['High']||0)+1;
    else data.fraud['Very High']=(data.fraud['Very High']||0)+1;
    data.click[click]=(data.click[click]||0)+1;
    data.vpn[vpn]=(data.vpn[vpn]||0)+1;
    data.country[country]=(data.country[country]||0)+1;
    data.device[device]=(data.device[device]||0)+1;
  });
  return data;
}
function createCharts(){
  const data = parseTable();
  const chartOptions={responsive:true,plugins:{legend:{position:'bottom'}}};

  charts.status = new Chart(document.getElementById('statusChart'),{
    type:'doughnut',
    data:{labels:Object.keys(data.status),datasets:[{data:Object.values(data.status),backgroundColor:['#16a34a','#dc2626','#f59e0b']}]},
    options:chartOptions
  });

  charts.reward = new Chart(document.getElementById('rewardChart'),{
    type:'bar',
    data:{labels:Object.keys(data.reward),datasets:[{label:'Reward Count',data:Object.values(data.reward),backgroundColor:'#2d89ef'}]},
    options:chartOptions
  });

  charts.volume = new Chart(document.getElementById('volumeChart'),{
    type:'line',
    data:{labels:Object.keys(data.volume),datasets:[{label:'Submissions',data:Object.values(data.volume),fill:true,backgroundColor:'rgba(45,137,239,0.2)',borderColor:'#2d89ef'}]},
    options:chartOptions
  });

  charts.fraud = new Chart(document.getElementById('fraudChart'),{
    type:'bar',
    data:{labels:Object.keys(data.fraud),datasets:[{label:'Count',data:Object.values(data.fraud),backgroundColor:['#16a34a','#f59e0b','#dc2626','#991b1b']}]},
    options:chartOptions
  });

  charts.click = new Chart(document.getElementById('clickChart'),{
    type:'doughnut',
    data:{labels:Object.keys(data.click),datasets:[{data:Object.values(data.click),backgroundColor:['#16a34a','#dc2626']}]},
    options:chartOptions
  });

  charts.vpn = new Chart(document.getElementById('vpnChart'),{
    type:'doughnut',
    data:{labels:Object.keys(data.vpn),datasets:[{data:Object.values(data.vpn),backgroundColor:['#dc2626','#16a34a']}]},
    options:chartOptions
  });

  charts.country = new Chart(document.getElementById('countryChart'),{
    type:'bar',
    data:{labels:Object.keys(data.country),datasets:[{label:'Submissions',data:Object.values(data.country),backgroundColor:'#2d89ef'}]},
    options:chartOptions
  });

  charts.device = new Chart(document.getElementById('deviceChart'),{
    type:'doughnut',
    data:{labels:Object.keys(data.device),datasets:[{data:Object.values(data.device),backgroundColor:['#16a34a','#f59e0b','#dc2626','#2d89ef','#8b5cf6']}]},
    options:chartOptions
  });
}
function updateCharts(){
  Object.values(charts).forEach(c=>c.destroy());
  createCharts();
}
createCharts();

/* --------------------- Export Buttons --------------------- */
document.querySelectorAll('.exportBtn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const chart = charts[btn.dataset.chart];
    if(chart) chart.toBase64Image();
    const link = document.createElement('a');
    link.href = chart.toBase64Image();
    link.download = btn.dataset.chart+'.png';
    link.click();
  });
});
updateTable();
</script>

<style>
.charts-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-top:12px;}
.chart-card{background:#fff;padding:12px;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.05);position:relative;}
.chart-card canvas{width:100%!important;height:250px!important;}
.exportBtn{position:absolute;top:8px;right:8px;padding:4px 8px;font-size:12px;border:none;background:#2d89ef;color:#fff;border-radius:6px;cursor:pointer;transition:0.2s;}
.exportBtn:hover{background:#1c5bbf;}
</style>

</body>
</html>
