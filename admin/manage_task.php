
<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();

if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in']!==true){
    header("Location: admin_login_in.php");
    exit;
}

// Handle Add / Edit Task
if(isset($_POST['task_action'])){
    $title = $conn->real_escape_string($_POST['title']);
    $desc = $conn->real_escape_string($_POST['description']);
    $reward = floatval($_POST['reward_amount']);
    $proof = $conn->real_escape_string($_POST['proof_type']);
    $status = $conn->real_escape_string($_POST['status']);
    $task_id = intval($_POST['task_id'] ?? 0);

    if($task_id > 0){
        $conn->query("UPDATE tasks SET title='$title', description='$desc', reward_amount=$reward, proof_type='$proof', status='$status', updated_at=NOW() WHERE id=$task_id");
    } else {
        $conn->query("INSERT INTO tasks (title, description, reward_amount, proof_type, status, created_at) VALUES ('$title','$desc',$reward,'$proof','$status',NOW())");
    }
    header("Location: manage_task.php?updated=1");
    exit;
}

// Handle Delete (single or bulk)
if(isset($_POST['delete_id'])){
    $ids = $_POST['delete_id'];
    if(is_array($ids)) $ids = array_map('intval', $ids);
    else $ids = [(int)$ids];

    // Delete related clicks first
    $ids_str = implode(',', $ids);
    $conn->query("DELETE FROM task_clicks WHERE task_id IN ($ids_str)");

    // Then delete tasks
    $conn->query("DELETE FROM tasks WHERE id IN ($ids_str)");
    header("Location: manage_task.php?deleted=1");
    exit;
}

// Handle Status Update (single or bulk)
if(isset($_POST['set_status'], $_POST['task_id'])){
    $status = $conn->real_escape_string($_POST['set_status']);
    $ids = $_POST['task_id'];
    if(is_array($ids)) $ids = implode(',', array_map('intval',$ids));
    else $ids = intval($ids);
    $conn->query("UPDATE tasks SET status='$status', updated_at=NOW() WHERE id IN ($ids)");
    header("Location: manage_task.php?status=1");
    exit;
}

// Filters & Search
$filter_status = $_GET['status'] ?? '';
$search = $conn->real_escape_string($_GET['search'] ?? '');
$where = "1";
if($filter_status && in_array($filter_status,['Active','Draft','Paused','Archived'])){
    $where .= " AND status='$filter_status'";
}
if($search){
    $where .= " AND (title LIKE '%$search%' OR description LIKE '%$search%' OR proof_type LIKE '%$search%')";
}

// Pagination
$per_page = 10;
$page = intval($_GET['page'] ?? 1);
$offset = ($page-1)*$per_page;

// Fetch total tasks count
$total_res = $conn->query("SELECT COUNT(*) as total FROM tasks WHERE $where");
$total_row = $total_res->fetch_assoc();
$total_tasks = intval($total_row['total']);
$total_pages = ceil($total_tasks / $per_page);

// Fetch tasks
$result = $conn->query("SELECT * FROM tasks WHERE $where ORDER BY id DESC LIMIT $offset, $per_page");
$tasks = [];
while($row = $result->fetch_assoc()) $tasks[] = $row;
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Manage Tasks</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',sans-serif;margin:0;padding:20px;background:#0f2027;color:#fff;}
h2{text-align:center;color:#00ffff;margin-bottom:20px;text-shadow:0 0 10px #0ff;}
a{color:#0ff;text-decoration:none;font-weight:700;margin-bottom:15px;display:inline-block;}
a:hover{text-shadow:0 0 8px #0ff;}
.alert{padding:12px 18px;border-radius:12px;margin-bottom:15px;font-weight:600;text-align:center;}
.alert.success{background:rgba(0,255,255,0.2);color:#0ff;}
.alert.error{background:rgba(255,0,0,0.2);color:#f00;}
.table-wrapper{overflow-x:auto;margin-top:20px;border-radius:12px;}
table{width:100%;border-collapse:collapse;background: rgba(255,255,255,0.05);border-radius:12px;}
th,td{padding:12px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1);}
th{background: rgba(0,255,255,0.2);color:#0ff;text-transform:uppercase;font-size:13px;letter-spacing:1px;}
tr:hover{background: rgba(0,255,255,0.1);transform: scale(1.01);transition:0.2s;}
.status-label{font-weight:600;padding:5px 12px;border-radius:12px;display:inline-block;font-size:12px;text-shadow:0 0 4px #000;}
.status-Active{background:#28a745;color:#fff;box-shadow:0 0 6px #28a745;}
.status-Draft{background:#ffc107;color:#000;box-shadow:0 0 6px #ffc107;}
.status-Paused{background:#dc3545;color:#fff;box-shadow:0 0 6px #dc3545;}
.status-Archived{background:#6c757d;color:#fff;box-shadow:0 0 6px #6c757d;}
button{padding:6px 14px;border:none;border-radius:12px;font-weight:600;cursor:pointer;transition:0.3s;}
button.update{background: linear-gradient(45deg,#00ffff,#0ff);color:#000;box-shadow:0 0 6px #0ff;}
button.update:hover{box-shadow:0 0 14px #0ff;transform:translateY(-2px);}
button.delete{background: linear-gradient(45deg,#ff0066,#ff3399);color:#fff;box-shadow:0 0 6px #ff3399;}
button.delete:hover{box-shadow:0 0 14px #ff3399;transform:translateY(-2px);}
button.set-status{background: linear-gradient(45deg,#ffc107,#ffcc00);color:#000;box-shadow:0 0 6px #ffc107;}
button.set-status:hover{box-shadow:0 0 14px #ffc107;transform:translateY(-2px);}
select{padding:6px 10px;border-radius:8px;border:1px solid rgba(255,255,255,0.3);background: rgba(0,0,0,0.2);color:#fff;cursor:pointer;font-weight:600;}
select option{background:#203a43;color:#fff;}
select option:hover{background:#0ff;color:#000;}
.actions{display:flex;justify-content:flex-end;gap:6px;flex-wrap:wrap;}
.actions form{display:inline;}
@media(max-width:768px){table,th,td{font-size:12px;padding:8px;}button{font-size:12px;padding:4px 8px;}select{font-size:12px;}}
.pagination{margin-top:15px;text-align:center;}
.pagination a{margin:0 5px;padding:6px 12px;background:rgba(0,255,255,0.2);color:#0ff;text-decoration:none;border-radius:6px;}
.pagination a.active{background:#0ff;color:#000;}
.pagination a:hover{background:#00ffff;color:#000;}
.bulk-actions{margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;}
.bulk-actions button{padding:6px 10px;border-radius:6px;}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);align-items:center;justify-content:center;}
.modal-content{background:#0b0c10;padding:20px;border-radius:12px;max-width:400px;width:90%;}
.modal-content input, .modal-content select, .modal-content textarea{width:100%;padding:8px;margin-bottom:10px;border-radius:6px;border:none;background:rgba(255,255,255,0.1);color:#fff;}
.modal-content button{width:100%;padding:8px;border-radius:6px;border:none;background:#0ff;color:#000;cursor:pointer;font-weight:600;}
</style>
</head>
<body>
     <?php include '../../includes/loader.php'; ?>
<h2>Manage Tasks</h2>
<a href="admin_dashboard.php">⬅ Back to Dashboard</a>

<?php if(isset($_GET['updated'])): ?><div class="alert success">Task saved successfully!</div><?php endif; ?>
<?php if(isset($_GET['deleted'])): ?><div class="alert success">Task deleted successfully!</div><?php endif; ?>
<?php if(isset($_GET['status'])): ?><div class="alert success">Task status updated!</div><?php endif; ?>

<div>
<form method="GET" style="margin-bottom:10px;display:flex;gap:10px;flex-wrap:wrap;">
<input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="padding:6px 10px;border-radius:6px;border:none;flex:1;">
<select name="status">
<option value="">All Status</option>
<option value="Active" <?= $filter_status==='Active'?'selected':'' ?>>Active</option>
<option value="Draft" <?= $filter_status==='Draft'?'selected':'' ?>>Draft</option>
<option value="Paused" <?= $filter_status==='Paused'?'selected':'' ?>>Paused</option>
<option value="Archived" <?= $filter_status==='Archived'?'selected':'' ?>>Archived</option>
</select>
<button type="submit" style="padding:6px 12px;border-radius:6px;background:#0ff;color:#000;border:none;">Filter</button>
<a href="manage_task.php" style="padding:6px 12px;border-radius:6px;background:#fff;color:#000;text-decoration:none;">Reset</a>
</form>
<a href="tasks.php" style="padding:6px 12px;border-radius:6px;background:#0ff;color:#000;text-decoration:none;display:inline-block;margin-bottom:10px;">+ Add New Task</a>
</div>

<form method="post" id="bulkForm">
<div class="bulk-actions">
<select id="bulkActionSelect" name="bulk_action">
<option value="">Bulk Actions</option>
<option value="delete">Delete</option>
<option value="Active">Set Active</option>
<option value="Draft">Set Draft</option>
<option value="Paused">Set Paused</option>
<option value="Archived">Set Archived</option>
</select>
<button type="button" onclick="applyBulkAction()">Apply</button>
</div>

<div class="table-wrapper">
<table>
<thead>
<tr>
<th><input type="checkbox" id="checkAll"></th>
<th>ID</th>
<th>Title</th>
<th>Description</th>
<th>Reward</th>
<th>Proof</th>
<th>Status</th>
<th>Created</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($tasks as $t): ?>
<tr>
<td><input type="checkbox" name="task_id[]" value="<?= (int)$t['id'] ?>"></td>
<td><?= (int)$t['id'] ?></td>
<td><?= htmlspecialchars($t['title']) ?></td>
<td><?= htmlspecialchars($t['description']) ?></td>
<td>$<?= number_format($t['reward_amount'],2) ?></td>
<td><?= htmlspecialchars($t['proof_type']) ?></td>
<td><span class="status-label status-<?= $t['status'] ?>"><?= htmlspecialchars($t['status']) ?></span></td>
<td><?= htmlspecialchars($t['created_at']) ?></td>
<td class="actions">
<!-- Edit -->
<button type="button" onclick="openEditModal(<?= (int)$t['id'] ?>,'<?= htmlspecialchars(addslashes($t['title'])) ?>','<?= htmlspecialchars(addslashes($t['description'])) ?>',<?= $t['reward_amount'] ?>,'<?= htmlspecialchars(addslashes($t['proof_type'])) ?>','<?= $t['status'] ?>')">Edit</button>

<!-- Delete -->
<form method="post" style="display:inline;" onsubmit="return confirm('Delete this task?');">
  <input type="hidden" name="delete_id[]" value="<?= (int)$t['id'] ?>">
  <button type="submit" class="delete">Delete</button>
</form>

<!-- Status Change -->
<form method="post" style="display:inline;">
  <input type="hidden" name="task_id[]" value="<?= (int)$t['id'] ?>">
  <select name="set_status">
    <option value="Active" <?= $t['status']=='Active'?'selected':'' ?>>Active</option>
    <option value="Draft" <?= $t['status']=='Draft'?'selected':'' ?>>Draft</option>
    <option value="Paused" <?= $t['status']=='Paused'?'selected':'' ?>>Paused</option>
    <option value="Archived" <?= $t['status']=='Archived'?'selected':'' ?>>Archived</option>
  </select>
  <button type="submit" class="set-status">Set</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</form>

<div class="pagination">
<?php for($i=1;$i<=$total_pages;$i++): ?>
<a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $filter_status ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
<?php endfor; ?>
</div>

<!-- Edit Modal -->
<div class="modal" id="editModal">
<div class="modal-content">
<h3>Edit Task</h3>
<form method="post">
<input type="hidden" name="task_id" id="modalTaskId">
<input type="text" name="title" id="modalTitle" placeholder="Title" required>
<textarea name="description" id="modalDescription" placeholder="Description" required></textarea>
<input type="number" name="reward_amount" id="modalReward" placeholder="Reward Amount" step="0.01" required>
<input type="text" name="proof_type" id="modalProof" placeholder="Proof Type" required>
<select name="status" id="modalStatus">
<option value="Active">Active</option>
<option value="Draft">Draft</option>
<option value="Paused">Paused</option>
<option value="Archived">Archived</option>
</select>
<input type="hidden" name="task_action" value="edit">
<button type="submit">Save Changes</button>
<button type="button" onclick="closeModal()" style="margin-top:5px;background:#ff0066;color:#fff;">Cancel</button>
</form>
</div>
</div>

<script>
function openEditModal(id,title,desc,reward,proof,status){
    document.getElementById('modalTaskId').value = id;
    document.getElementById('modalTitle').value = title;
    document.getElementById('modalDescription').value = desc;
    document.getElementById('modalReward').value = reward;
    document.getElementById('modalProof').value = proof;
    document.getElementById('modalStatus').value = status;
    document.getElementById('editModal').style.display = 'flex';
}
function applyBulkAction(){
    let action = document.getElementById('bulkActionSelect').value;
    if(!action){alert('Select action');return;}
    let selected = Array.from(document.querySelectorAll('input[name="task_id[]"]:checked'));
    if(selected.length===0){alert('Select tasks');return;}
    if(confirm('Apply "'+action+'" to selected tasks?')){
        let form = document.getElementById('bulkForm');
        
        // purane hidden inputs delete kar do
        form.querySelectorAll('input[name="task_id[]"], input[name="set_status"], input[name="delete_id[]"]').forEach(e=>e.remove());

        // har selected task ka hidden input banao
        selected.forEach(cb=>{
            let hidden = document.createElement('input');
            hidden.type='hidden';
            hidden.name='task_id[]';
            hidden.value=cb.value;  // yeh asal ID hai
            form.appendChild(hidden);
        });

        if(action==='delete'){
            // ab har selected ke liye delete_id[] banao
            selected.forEach(cb=>{
                let hidden = document.createElement('input');
                hidden.type='hidden';
                hidden.name='delete_id[]';
                hidden.value=cb.value;
                form.appendChild(hidden);
            });
        } else {
            let hidden = document.createElement('input');
            hidden.type='hidden';
            hidden.name='set_status';
            hidden.value=action;
            form.appendChild(hidden);
        }

        form.submit();
    }
}
</script>
</body>
</html>
