
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($task['title']) ?> — Task Detail | Umarae</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

<style>
:root{--primary:#2563eb;--muted:#6b7280;--card:#fff;--shadow:0 8px 24px rgba(15,23,42,.1)}
body{font-family:Inter,system-ui;margin:0;padding:18px;background:#f4f6fb;color:#111;}
.container{max-width:1100px;margin:0 auto}
.row{display:flex;gap:20px;align-items:flex-start}
.main{flex:1}
.sidebar{width:280px}
.task-card{background:var(--card);border-radius:14px;padding:0;margin-bottom:18px;box-shadow:var(--shadow);}
.task-head{background:var(--primary);color:#fff;padding:14px 18px;font-weight:700;font-size:18px;}
.table{width:100%;border-collapse:collapse;margin:0}
.th{width:160px;padding:12px 16px;background:#f8fafc;font-weight:700;border-bottom:1px solid #eef2f6;font-size:14px;}
.td{padding:12px 16px;border-bottom:1px solid #eef2f6;font-size:14px;}
.badge{display:inline-block;padding:6px 10px;border-radius:10px;font-weight:700;font-size:13px;}
.reward{background:#e6f4ea;color:#166534;}
.proof{background:#fff4e6;color:#b45309;}
.link{background:#e6f0ff;color:#1d4ed8;text-decoration:none;padding:4px 8px;border-radius:6px;}
.meta{font-size:13px;color:var(--muted);}
.actions{text-align:center;padding:12px;}
.progress{height:10px;background:#eef2f6;border-radius:10px;overflow:hidden;margin-top:6px;}
.progress > i{display:block;height:100%;background:linear-gradient(90deg,#10b981,#06b6d4);width:0%;transition:width .6s ease;}
.save-btn{background:transparent;border:1px solid #e5e7eb;padding:8px 12px;border-radius:10px;cursor:pointer;transition:all .2s;}
.save-btn.saved{background:#fff4e6;border-color:#f59e0b;color:#b45309;margin-left:20px;}
.btn{padding:10px 14px;border-radius:10px;border:0;background:var(--primary);color:#fff;font-weight:600;cursor:pointer;transition:all .2s;}
.btn.secondary{background:#f3f4f6;color:#111;}
.sidebar .card{background:#fff;border-radius:14px;padding:14px;margin-bottom:14px;box-shadow:var(--shadow);}
.leaderboard li, .saved li{padding:8px 0;border-bottom:1px dashed #f1f5f9;}
.leaderboard li:last-child, .saved li:last-child{border-bottom:none;}
@media(max-width:1000px){.row{flex-direction:column}.sidebar{width:100%}}

.completed-steps-card {
    margin-top: 15px;
}

.completed-steps {
    border: 1px solid #e0e0e0;
    padding: 12px 16px;
    border-radius: 6px;
    background: #f9fff9; /* light greenish background */
}

.completed-message {
    font-weight: 600;
    color: #007bfff;
    margin-bottom: 8px;
}

.steps-list {
    padding-left: 20px;
    margin: 0 0 10px 0;
    color: #333;
    font-size: 14px;
}

.steps-list li {
    margin-bottom: 4px;
}


/* Task Card Border Radius */
.task-card{
  border-radius:20px;
}
.task-head{
  border-top-left-radius:20px;
  border-top-right-radius:20px;
}

</style>
</head>
<body>
     <?php include '../includes/loader.php'; ?>
<main class="container">
<div class="row">
<section class="main">
<article class="task-card">
<div class="task-head"><?= htmlspecialchars($task['title']) ?><span style="float:right;font-weight:600;font-size:13px"><?= htmlspecialchars($task['category']??'') ?></span></div>
<table class="table">
<tr><td class="th">Description</td><td class="td"><?= nl2br(htmlspecialchars($task['description'])) ?></td></tr>
<tr><td class="th">Reward</td><td class="td"><span class="badge reward"><?php if(($task['reward_type']??'')==='cash') echo '$'.number_format((float)$task['reward_amount'],2); else echo htmlspecialchars($task['reward_amount']).' '.htmlspecialchars($task['reward_type']??''); ?></span></td></tr>
<?php if(!empty($task['external_url'])): ?><tr><td class="th">Link</td><td class="td"><a class="link" href="<?= htmlspecialchars($task['external_url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($task['external_url']) ?></a></td></tr><?php endif; ?>
<tr><td class="th">Proof</td><td class="td"><span class="badge proof"><?= htmlspecialchars($task['proof_type']??'Screenshot / Link') ?></span></td></tr>
<tr><td class="th">Deadline</td><td class="td"><?= htmlspecialchars($task['deadline']??'—') ?> · <strong><?= htmlspecialchars($remaining) ?></strong></td></tr>
<tr><td class="th">Submissions</td><td class="td"><div class="meta"><?= $submissions ?><?= $max_sub>0?" / $max_sub":"" ?></div><div class="progress"><i style="width:<?= $progress_pct ?>%"></i></div></td></tr>
<tr><td class="th">Go Back <i class="fa fa-arrow-circle-right"></i> </td><td class="td actions">
<a class="btn" href="tasks.php">Back to tasks <i class="fa-solid fa-arrow-circle-right"></i> </a>
<button class="save-btn <?= $is_saved?'saved':'' ?>" data-task-id="<?= (int)$task['id'] ?>"><?= $is_saved?'Saved':'Save' ?></button>
</td></tr>
</table>
</article>
</section>

 <aside class="sidebar">
    <div class="card">
      <h4>Leaderboard</h4>
      <ul class="leaderboard">
        <?php foreach($leaderboard as $lb): ?>
          <li><?= htmlspecialchars($lb['name']) ?> — <?= (int)$lb['completed'] ?> tasks</li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card">
      <h4>Saved Tasks</h4>
      <ul>
        <?php
        $saved_q = $conn->prepare("SELECT t.id, t.title FROM tasks t JOIN saved_tasks st ON st.task_id=t.id WHERE st.user_id=? ORDER BY st.created_at DESC LIMIT 5");
        $saved_q->bind_param("i", $user_id); $saved_q->execute(); $res_saved=$saved_q->get_result();
        while($s=$res_saved->fetch_assoc()): ?>
          <li><a href="task_detail.php?id=<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['title']) ?></a></li>
        <?php endwhile; ?>
      </ul>
    </div>

    <!-- Completed Steps Box -->
    <div class="card completed-steps-card">
      <div class="completed-steps">
        <div class="completed-message">
          ✅ Steps complete! Follow these instructions to submit your proof:
        </div>
        <ol class="steps-list">
          <li>Click the Task link above.</li>
          <li>Read the title and description carefully.</li>
          <li>Complete all required steps.</li>
          <li>Scroll down and click the "Submit Proof" button below to submit your task.</li>
        </ol>
   
      </div>
    </div>
</aside>

</main>

<script>
document.querySelectorAll('.save-btn').forEach(btn=>{
  btn.addEventListener('click',function(){
    const taskId=this.dataset.taskId,el=this;
    fetch(location.href,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=toggle_save&task_id='+encodeURIComponent(taskId)})
    .then(r=>r.json()).then(j=>{
      if(j.status==='saved'){el.classList.add('saved');el.textContent='Saved';}
      else if(j.status==='removed'){el.classList.remove('saved');el.textContent='Save';}
      else alert('Error saving task');
    }).catch(e=>alert('Error: '+e));
  });
});
</script>
</body>
</html>
