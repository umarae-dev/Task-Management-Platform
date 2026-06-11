
<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

session_start();

// ✅ Admin Login Check
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in']!==true){
    header("Location: admin_login.php");
    exit;
}

// ✅ CSRF Token Generate
if(!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Allowed status values
$allowed_status = ['Active','Inactive','Suspended','Deleted'];

// ✅ Handle POST Requests
if($_SERVER['REQUEST_METHOD']==='POST'){

    // CSRF Validation
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        die("Invalid CSRF token!");
    }

    // Update user status
    if(isset($_POST['set_status'],$_POST['user_id'])){
        $status = $_POST['set_status'];
        $id = (int)$_POST['user_id'];
        if(!in_array($status, $allowed_status)) $status = 'Inactive';

        $stmt = $conn->prepare("UPDATE users SET status=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("si",$status,$id);
        $stmt->execute();
        $stmt->close();

        header('Location: users.php?status=1');
        exit;
    }

    // Soft Delete user
    if(isset($_POST['delete_id'])){
        $id = (int)$_POST['delete_id'];
        $stmt = $conn->prepare("UPDATE users SET status='Deleted', updated_at=NOW() WHERE id=?");
        $stmt->bind_param("i",$id);
        $stmt->execute();
        $stmt->close();

        header('Location: users.php?deleted=1');
        exit;
    }

    // Restore user
    if(isset($_POST['restore_id'])){
        $id = (int)$_POST['restore_id'];
        $stmt = $conn->prepare("UPDATE users SET status='Inactive', updated_at=NOW() WHERE id=?");
        $stmt->bind_param("i",$id);
        $stmt->execute();
        $stmt->close();

        header('Location: users.php?restored=1');
        exit;
    }

    // Permanent Delete (optional)
    if(isset($_POST['permanent_delete_id'])){
        $id = (int)$_POST['permanent_delete_id'];
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i",$id);
        $stmt->execute();
        $stmt->close();

        header('Location: users.php?permanent_deleted=1');
        exit;
    }

    // Bulk Status Update
    if(isset($_POST['bulk_status'],$_POST['user_ids']) && is_array($_POST['user_ids'])){
        $status = $_POST['bulk_status'];
        if(!in_array($status,$allowed_status)) $status = 'Inactive';
        foreach($_POST['user_ids'] as $uid){
            $id = (int)$uid;
            $stmt = $conn->prepare("UPDATE users SET status=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("si",$status,$id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: users.php?status=1');
        exit;
    }

    // Bulk Delete (Soft Delete)
    if(isset($_POST['bulk_delete_ids']) && is_array($_POST['bulk_delete_ids'])){
        foreach($_POST['bulk_delete_ids'] as $uid){
            $id = (int)$uid;
            $stmt = $conn->prepare("UPDATE users SET status='Deleted', updated_at=NOW() WHERE id=?");
            $stmt->bind_param("i",$id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: users.php?deleted=1');
        exit;
    }

    // Export CSV
    if(isset($_POST['export_csv'])){
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="users_export.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID','Username','Email','Status','Created','Updated']);
        $result = $conn->query("SELECT * FROM users ORDER BY id DESC");
        while($row=$result->fetch_assoc()){
            fputcsv($output, [$row['id'],$row['name'],$row['email'],$row['status'],$row['created_at'],$row['updated_at']]);
        }
        fclose($output);
        exit;
    }
}

// Fetch users
$result = $conn->query("SELECT * FROM users ORDER BY id DESC");
$users = [];
while($row = $result->fetch_assoc()) $users[] = $row;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Manage Users</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body {font-family:'Inter',sans-serif;background:linear-gradient(160deg,#0f2027,#203a43,#2c5364);color:#fff;margin:0;padding:20px;}
h2{text-align:center;color:#00ffff;margin-bottom:20px;text-shadow:0 0 10px #00ffff;}
a.back-link{color:#0ff;text-decoration:none;margin-bottom:15px;display:inline-block;font-weight:700;}
a.back-link:hover{text-shadow:0 0 8px #0ff;}
.alert{padding:12px 18px;border-radius:12px;margin-bottom:15px;font-weight:600;text-align:center;backdrop-filter: blur(10px);}
.alert.success{background:rgba(0,255,255,0.2); color:#0ff;}
.alert.error{background:rgba(255,0,0,0.2); color:#f00;}
.table-wrapper{overflow-x:auto;margin-top:20px;border-radius:12px;}
table{width:100%;border-collapse:collapse;backdrop-filter:blur(10px);background: rgba(255,255,255,0.05);border-radius:12px;overflow:hidden;}
th, td{padding:14px 12px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.1);vertical-align: middle;}
th{background: rgba(0,255,255,0.2);color:#0ff;text-transform:uppercase;font-size:13px;letter-spacing:1px;cursor:pointer;}
tr:hover{background: rgba(0,255,255,0.1);transform: scale(1.01);transition:0.2s;}
.status-label{font-weight:600;padding:5px 12px;border-radius:12px;display:inline-block;font-size:12px;min-width:80px;text-align:center;text-shadow:0 0 4px #000;}
.status-Active{background:#28a745;color:#fff;box-shadow:0 0 6px #28a745;}
.status-Inactive{background:#ffc107;color:#000;box-shadow:0 0 6px #ffc107;}
.status-Suspended{background:#dc3545;color:#fff;box-shadow:0 0 6px #dc3545;}
.status-Deleted{background:#6c757d;color:#fff;box-shadow:0 0 6px #6c757d;}
button{padding:6px 14px;border:none;border-radius:20px;font-weight:600;cursor:pointer;transition:0.3s;}
button.update{background:linear-gradient(45deg,#00ffff,#0ff);color:#000;box-shadow:0 0 6px #0ff;}
button.update:hover{box-shadow:0 0 14px #0ff;transform:translateY(-2px);}
button.delete{background:linear-gradient(45deg,#ff0066,#ff3399);color:#fff;box-shadow:0 0 6px #ff3399;}
button.delete:hover{box-shadow:0 0 14px #ff3399;transform:translateY(-2px);}
button.restore{background:linear-gradient(45deg,#28a745,#0f0);color:#000;box-shadow:0 0 6px #0f0;}
button.restore:hover{box-shadow:0 0 14px #0f0;transform:translateY(-2px);}
button.permanent{background:linear-gradient(45deg,#000,#555);color:#fff;box-shadow:0 0 6px #555;}
button.permanent:hover{box-shadow:0 0 14px #555;transform:translateY(-2px);}
select,input[type=text]{padding:6px 10px;border-radius:20px;border:1px solid rgba(255,255,255,0.3);background:rgba(0,0,0,0.2);color:#fff;cursor:pointer;font-weight:600;transition:0.3s;}
select option{background:#203a43;color:#fff;}
select option:hover{background:#0ff;color:#000;}
select:hover,input[type=text]:hover,input[type=text]:focus,select:focus{border-color:#0ff;box-shadow:0 0 6px #0ff;}
.actions form{display:inline-block;margin-right:5px;}
.bulk-actions, .filters{text-align:center;margin-bottom:15px;}
.bulk-actions :hover{background:#0ff;color:#000;}
#load-more{margin-top:10px;}
@media(max-width:768px){th, td{font-size:12px;padding:8px;}button{font-size:12px;padding:4px 8px;}select,input[type=text]{font-size:12px;}}
</style>


</head>
<body>
 <?php include '../../includes/loader.php'; ?>

</head>
<body>



</body>
</html>
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
<h2>Manage Users</h2>
<a href="admin_dashboard.php" class="back-link">⬅ Back to Dashboard</a>

<?php if(isset($_GET['status'])): ?><div class="alert success">Status updated successfully!</div><?php endif; ?>
<?php if(isset($_GET['deleted'])): ?><div class="alert success">User deleted successfully!</div><?php endif; ?>
<?php if(isset($_GET['restored'])): ?><div class="alert success">User restored successfully!</div><?php endif; ?>
<?php if(isset($_GET['permanent_deleted'])): ?><div class="alert success">User permanently deleted!</div><?php endif; ?>

<!-- Search & Filter -->
<div class="filters">
    <input type="text" id="user-search" placeholder="Search by ID, Name, Email">
    <select id="status-filter">
        <option value="">All Status</option>
        <option value="Active">Active</option>
        <option value="Inactive">Inactive</option>
        <option value="Suspended">Suspended</option>
        <option value="Deleted">Deleted</option>
    </select>
</div>

<!-- Bulk Actions -->
<div class="bulk-actions">
    <select id="bulk-status">
        <option value="">--Change Status--</option>
        <option value="Active">Active</option>
        <option value="Inactive">Inactive</option>
        <option value="Suspended">Suspended</option>
    </select>
    <button id="apply-bulk-status">Update Status</button>
    <button id="bulk-delete">Delete Selected</button>
    <form method="post" style="display:inline-block;">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <button type="submit" name="export_csv">Export CSV</button>
    </form>
</div>

<div class="table-wrapper">
<table id="users-table">
<thead>
<tr>
<th><input type="checkbox" id="select-all"></th>
<th>ID</th>
<th>Username</th>
<th>Email</th>
<th>Status</th>
<th>Created</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($users as $u): ?>
<tr>
    <td><input type="checkbox" class="user-checkbox" value="<?= (int)$u['id'] ?>"></td>
    <td><?= (int)($u['id'] ?? 0) ?></td>
    <td><?= htmlspecialchars($u['name'] ?? 'N/A') ?></td>
    <td><?= htmlspecialchars($u['email'] ?? 'N/A') ?></td>
    <td>
        <?php $status = $u['status'] ?? 'Active'; ?>
        <span class="status-label status-<?= htmlspecialchars($status) ?>">
            <?= htmlspecialchars($status) ?>
        </span>
    </td>
    <td><?= htmlspecialchars($u['created_at'] ?? 'N/A') ?></td>
    <td class="actions">
        <?php if($status !== 'Deleted'): ?>
        <form method="post" style="display:inline-block; margin-right:5px;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="user_id" value="<?= (int)($u['id'] ?? 0) ?>">
            <select name="set_status">
                <option value="Active" <?= ($status === 'Active') ? 'selected' : '' ?>>Active</option>
                <option value="Inactive" <?= ($status === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                <option value="Suspended" <?= ($status === 'Suspended') ? 'selected' : '' ?>>Suspended</option>
            </select>
            <button type="submit" class="update">Update</button>
        </form>
        <form method="post" onsubmit="return confirm('Delete user?')" style="display:inline-block;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="delete_id" value="<?= (int)($u['id'] ?? 0) ?>">
            <button type="submit" class="delete">Delete</button>
        </form>
        <?php else: ?>
        <form method="post" style="display:inline-block;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="restore_id" value="<?= (int)($u['id'] ?? 0) ?>">
            <button type="submit" class="restore">Restore</button>
        </form>
        <form method="post" onsubmit="return confirm('Permanently delete user?')" style="display:inline-block;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="permanent_delete_id" value="<?= (int)($u['id'] ?? 0) ?>">
            <button type="submit" class="permanent">Delete Permanently</button>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<div style="text-align:center;">
    <button id="load-more">Load More</button>
</div>

<script>
// All user rows
let allRows = Array.from(document.querySelectorAll('#users-table tbody tr'));
let visibleCount = 10;

// Function to update visible rows based on search + filter
function updateRows(){
    const searchTerm = document.getElementById('user-search').value.toLowerCase().trim();
    const filterStatus = document.getElementById('status-filter').value.toLowerCase().trim();

    let count = 0;

    allRows.forEach(tr => {
        const id = tr.children[1].textContent.toLowerCase().trim();
        const name = tr.children[2].textContent.toLowerCase().trim();
        const email = tr.children[3].textContent.toLowerCase().trim();
        const status = tr.querySelector('td:nth-child(5) .status-label').textContent.toLowerCase().trim();

        const matchesSearch = id.includes(searchTerm) || name.includes(searchTerm) || email.includes(searchTerm);
        const matchesStatus = filterStatus === '' || status === filterStatus;

        if(matchesSearch && matchesStatus && count < visibleCount){
            tr.style.display = '';
            count++;
        } else {
            tr.style.display = 'none';
        }
    });

    // Show/hide Load More button
    const filteredCount = allRows.filter(tr => {
        const id = tr.children[1].textContent.toLowerCase().trim();
        const name = tr.children[2].textContent.toLowerCase().trim();
        const email = tr.children[3].textContent.toLowerCase().trim();
        const status = tr.querySelector('td:nth-child(5) .status-label').textContent.toLowerCase().trim();
        return (filterStatus === '' || status === filterStatus) && 
               (id.includes(searchTerm) || name.includes(searchTerm) || email.includes(searchTerm));
    }).length;

    document.getElementById('load-more').style.display = (visibleCount < filteredCount) ? 'inline-block' : 'none';
}

// Initial display
updateRows();

// Select All Checkbox
document.getElementById('select-all').addEventListener('change', function() {
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = this.checked);
});

// Filter by Status
document.getElementById('status-filter').addEventListener('change', function() {
    visibleCount = 10;
    updateRows();
});

// Search by ID, Name, Email
document.getElementById('user-search').addEventListener('input', function() {
    visibleCount = 10;
    updateRows();
});

// Bulk Delete
document.getElementById('bulk-delete').addEventListener('click', function() {
    if(!confirm('Delete selected users?')) return;
    const ids = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
    if(ids.length === 0) return alert('No users selected');
    const form = document.createElement('form');
    form.method = 'post';
    const csrf = document.createElement('input');
    csrf.type='hidden'; csrf.name='csrf_token'; csrf.value='<?= $_SESSION['csrf_token'] ?>';
    form.appendChild(csrf);
    ids.forEach(id=>{
        const input = document.createElement('input');
        input.type='hidden'; input.name='bulk_delete_ids[]'; input.value=id;
        form.appendChild(input);
    });
    document.body.appendChild(form); form.submit();
});

// Bulk Status Update
document.getElementById('apply-bulk-status').addEventListener('click', function() {
    const status = document.getElementById('bulk-status').value;
    if(!status) return alert('Select status first');
    const ids = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
    if(ids.length === 0) return alert('No users selected');
    const form = document.createElement('form');
    form.method='post';
    const csrf = document.createElement('input');
    csrf.type='hidden'; csrf.name='csrf_token'; csrf.value='<?= $_SESSION['csrf_token'] ?>';
    form.appendChild(csrf);
    ids.forEach(id=>{
        const input = document.createElement('input');
        input.type='hidden'; input.name='user_ids[]'; input.value=id;
        form.appendChild(input);
    });
    const inputStatus = document.createElement('input');
    inputStatus.type='hidden'; inputStatus.name='bulk_status'; inputStatus.value=status;
    form.appendChild(inputStatus);
    document.body.appendChild(form); form.submit();
});

// Load More
document.getElementById('load-more').addEventListener('click', function() {
    visibleCount += 10;
    updateRows();
});
</script>

</body>
</html>
