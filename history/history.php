
<?php
session_start();

define('PROOF_URL','/uploads/proofs');

if(!isset($_SESSION['user_id'])) { header('Location: ../public/login.html'); exit; }
$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT s.id,s.proof_text,s.proof_image,s.status,s.created_at,s.reviewer_note,
           t.title,t.description,t.reward_amount,t.proof_type
    FROM task_submissions s
    JOIN tasks t ON t.id=s.task_id
    WHERE s.user_id=? ORDER BY s.id DESC
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while($row=$res->fetch_assoc()) $rows[]=$row;

function sentenceCase($string) {
    $string = strtolower($string);
    return ucfirst($string);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Task History</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* Body & Headings */
body{
    font-family:'Inter',sans-serif;
    background:#f0f4f8;
    margin:0;
    padding:20px;
}
h2{
    color:#2d89ef;
    text-align:center;
    margin-bottom:20px;
    font-weight:700;
}
/* Dashboard button */
.dashboard-btn{
    display:block;
    max-width:160px; /* PC size */
    margin:0 0 25px 0; /* left align by default */
    text-align:center;
    padding:8px 14px; /* PC padding */
    font-size:14px;
    background:#2d89ef;
    color:#fff;
    font-weight:600;
    border-radius:8px;
    text-decoration:none;
    transition:0.2s;
}
.dashboard-btn:hover{
    background:#1d4ed8;
}

/* Mobile adjustments */
@media(max-width:600px){
    .dashboard-btn{
        max-width:160px;  /* smaller on mobile */
        padding:6px 12px; /* smaller padding */
        font-size:13px;   /* smaller text */
        margin:0 auto 20px auto; /* centered */
    }
    
    
}

/* Search bar */
.search-bar{
    max-width:800px;
    margin:0 auto 25px;
    display:flex;
    gap:10px;
}
.search-bar input{
    flex:1;
    padding:10px 14px;
    border:1px solid #ccc;
    border-radius:20px;
    font-size:14px;
}
.search-bar button{
    padding:10px 20px;
    background:#2d89ef;
    color:#fff;
    border:none;
    border-radius:20px;
    cursor:pointer;
    transition:0.2s;
}
.search-bar button:hover{
    background:#1d4ed8;
}

/* Task container */
.task-container{
    max-width:1000px;
    margin:0 auto;
    padding:0 10px; /* add padding on mobile */
}

/* Task cards */
.task-card{
    background:#fff;
    border-radius:12px;
    box-shadow:0 4px 20px rgba(0,0,0,0.08);
    margin-bottom:25px;
    overflow:hidden;
    transition:transform 0.2s;
}
.task-card:hover{
    transform:translateY(-3px);
}

/* Top row */
.top-row{
    display:flex;
    flex-wrap:wrap;
    padding:16px 20px;
    background:#eef2ff;
    gap:10px;
    align-items:center;
}
.top-row div{
    flex:1 1 18%;
    font-size:14px;
    margin:6px 0;
    line-height:1.5;
}
.top-row div strong{display:block;}
.top-row .admin-note-label{color:#1d4ed8;}
.top-row .reward-label{color:#d97706;}
.top-row .status-label{color:#dc2626;}
.top-row .submitted-label{color:#6b7280;}
.top-row .action-label{color:#9333ea;}
.top-row div span{color:#000;}

/* Status & Action */
.status-badge{
    padding:6px 12px;
    border-radius:20px;
    font-weight:600;
    display:inline-block;
    text-align:center;
}
.status-approved{background:#16a34a;color:#fff !important;}
.status-rejected{background:#dc2626;color:#fff !important;}
.status-pending{background:#f59e0b;color:#000 !important;}
.completed{background:#16a34a;color:#fff !important; border-radius:20px !important;}
.rejected{background:#dc2626;color:#fff !important; border-radius:20px !important;}
.pending{background:#fbbf24;color:#000 !important; border-radius:20px !important;}

/* Bottom row */
.bottom-row{
    display:flex;
    flex-wrap:wrap;
    padding:16px 20px;
    border-top:1px solid #e2e8f0;
    background:#f9fafb;
    gap:15px;
}
.bottom-row div{
    flex:1 1 30%;
    margin-bottom:12px;
}
.label-title{font-weight:700;color:#2563eb;margin-bottom:4px;}
.label-desc{font-weight:700;color:#d97706;margin-bottom:4px;}
.label-proof{font-weight:700;color:#6b7280;margin-bottom:4px;}
.details-proof div{color:#000;}
.details-proof img{
    max-height:60px;
    border-radius:6px;
    object-fit:cover;
    cursor:pointer;
    border:2px solid #2d89ef;
}

/* Action column always last */
.top-row .action-label{
    margin-left:auto;
}
.top-row .action-label span{
    padding:6px 12px;
    border-radius:8px;
    font-weight:600;
    display:inline-block;
}

/* Responsive */
@media(max-width:900px){
    .top-row div{flex:1 1 45%;}
    .bottom-row div{flex:1 1 100%;}
    .details-proof img{max-height:50px;}
}
@media(max-width:600px){
    .top-row{flex-direction:column;align-items:flex-start;gap:10px;}
    .top-row div{
        display:flex;
        justify-content:space-between;
        flex:1 1 100%;
    }
    .top-row div strong{margin-right:10px;}
    .top-row .action-label{
        margin-left:0;
        margin-top:8px;
        
    }
    .bottom-row div{flex:1 1 100%;}
    .details-proof img{max-height:40px;}
    .task-container{padding:0 15px;} /* add gap from sides */
}
</style>
<script>
function filterTasks(){
  let input=document.getElementById("searchInput").value.toLowerCase();
  let cards=document.querySelectorAll(".task-card");
  cards.forEach(card=>{
    let text=card.innerText.toLowerCase();
    card.style.display=text.includes(input)?"block":"none";
  });
}
</script>
</head>
<body>
     <?php include '../includes/loader.php'; ?>
<?php include "../includes/header.php"; ?>



<div class="task-container">
    
    <!-- ✅ Dashboard Button -->
<a href="dashboard.php" class="dashboard-btn">← Back to Dashboard</a>

<h2>Task Submission History</h2>

<div class="search-bar">
  <input type="text" id="searchInput" placeholder="Search by title, status, or note..." onkeyup="filterTasks()">
  <button onclick="filterTasks()">Search</button>
</div>
<?php if(!empty($rows)): ?>
<?php foreach($rows as $r): ?>
<div class="task-card">
 <div class="top-row">
    <div class="admin-note-label">
      <strong>Admin Note:</strong><br>
      <span><?= htmlspecialchars($r['reviewer_note'] ?: '—') ?></span>
    </div>
    <div class="reward-label">
      <strong>Reward:</strong><br>
      <span><?= number_format($r['reward_amount'],2) ?></span>
    </div>
    <div class="status-label">
      <strong>Status:</strong><br>
      <span class="status-badge 
        <?= strtolower($r['status'])==='approved'?'status-approved':
           (strtolower($r['status'])==='rejected'?'status-rejected':'status-pending') ?>">
        <?= htmlspecialchars($r['status']) ?>
      </span>
    </div>
    <div class="submitted-label">
      <strong>Submitted:</strong><br>
      <span><?= htmlspecialchars($r['created_at']) ?></span>
    </div>
    <div class="action-label">
      <strong>Action:</strong><br>
      <?php if($r['status']==='Pending'): ?>
        <span class="pending">Pending</span>
      <?php elseif($r['status']==='Approved'): ?>
        <span class="completed">✅ Completed</span>
      <?php elseif($r['status']==='Rejected'): ?>
        <span class="rejected">❌ Completed</span>
      <?php endif; ?>
    </div>
</div>

<div class="bottom-row">
    <div>
      <div class="label-title">Title</div>
      <div><?= htmlspecialchars($r['title']) ?></div>
    </div>
    <div>
      <div class="label-desc">Description</div>
      <div><?= nl2br(htmlspecialchars($r['description'])) ?></div>
    </div>
    <div class="details-proof">
      <div class="label-proof">Proof</div>
      <?php if($r['proof_type']==='Screenshot' && $r['proof_image']):
        $images = explode(',', $r['proof_image']);
        foreach($images as $img):
          if($img): ?>
            <a href="<?= PROOF_URL.'/'.htmlspecialchars($img) ?>" target="_blank">
              <img src="<?= PROOF_URL.'/'.htmlspecialchars($img) ?>">
            </a>
          <?php endif;
        endforeach;
      elseif($r['proof_text']): ?>
        <div><?= htmlspecialchars(sentenceCase($r['proof_text'])) ?></div>
      <?php endif; ?>
    </div>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<p style="text-align:center;color:#555;">No submissions yet.</p>
<?php endif; ?>
</div>

<!-- Add this inside <body> after task cards -->
<div id="loadMoreContainer" style="text-align:center; margin:20px 0; display:none;">
    <button id="loadMoreBtn" style="
        padding:8px 16px;
        border:none;
        background:#2d89ef;
        color:#fff;
        border-radius:8px;
        cursor:pointer;
        font-weight:600;
    ">Load More</button>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const cards = document.querySelectorAll(".task-card");
    const loadMoreContainer = document.getElementById("loadMoreContainer");
    const loadMoreBtn = document.getElementById("loadMoreBtn");
    const defaultVisible = 10; // default shown
    let visibleCount = defaultVisible;

    // Hide all beyond default
    cards.forEach((card, i) => {
        if(i >= defaultVisible) card.style.display = "none";
    });

    // Show Load More if more than default
    if(cards.length > defaultVisible) loadMoreContainer.style.display = "block";

    // Load more on click
    loadMoreBtn.addEventListener("click", function() {
        const remaining = cards.length - visibleCount;
        const next = remaining >= defaultVisible ? defaultVisible : remaining;
        for(let i=visibleCount; i<visibleCount+next; i++){
            cards[i].style.display = "block";
        }
        visibleCount += next;

        // Hide button if all visible
        if(visibleCount >= cards.length){
            loadMoreContainer.style.display = "none";
        }
    });
});
</script>

 <?php include '../includes/task_dashboard_fottor.php'; ?>
</body>
</html>
