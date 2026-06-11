
<?php
ini_set('session.cookie_path', '/');
session_start();


// --- Step 1: Check admin session ---
if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['admin_id'])) {
    // --- Step 2: Check if access gate system is active ---
    if (empty($_SESSION['access_granted'])) {
        header("Location: ../owner/generate_link_admin_task.php");
        exit;
    }
    // --- Step 3: If gate session exists but admin not logged in, send to real admin login ---
    header("Location: admin_login.php");
    exit;
}

// --- Step 4: If everything OK, continue to dashboard ---
$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['admin_role'] ?? '';

// Only allow admins or owners
if (!in_array($admin_role, ['admin','owner'])) {
    session_destroy();
    header("Location: admin_login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Premium Panel</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;transition:all 0.3s cubic-bezier(0.4,0,0.2,1);}
:root{--bg-primary:#f8f9fa;--bg-secondary:#ffffff;--bg-tertiary:#e9ecef;--text-primary:#1a1a2e;--text-secondary:#6c757d;--accent:#667eea;--accent-hover:#5a67d8;--sidebar-bg:#ffffff;--card-bg:#ffffff;--shadow:0 10px 40px rgba(0,0,0,0.08);--shadow-hover:0 20px 60px rgba(0,0,0,0.12);--border:#e9ecef;--gradient:linear-gradient(135deg,#667eea 0%,#764ba2 100%);--success:#10b981;--warning:#f59e0b;--danger:#ef4444;--info:#3b82f6;}
[data-theme="dark"]{--bg-primary:#0f172a;--bg-secondary:#1e293b;--bg-tertiary:#334155;--text-primary:#f1f5f9;--text-secondary:#94a3b8;--sidebar-bg:#1e293b;--card-bg:#1e293b;--shadow:0 10px 40px rgba(0,0,0,0.5);--shadow-hover:0 20px 60px rgba(0,0,0,0.7);--border:#334155;--gradient:linear-gradient(135deg,#667eea 0%,#764ba2 100%);}
body{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg-primary);color:var(--text-primary);display:flex;min-height:100vh;overflow-x:hidden;}
.sidebar{width:280px;background:var(--sidebar-bg);height:100vh;position:fixed;top:0;left:-280px;overflow-y:auto;z-index:1000;box-shadow:var(--shadow);border-right:1px solid var(--border);}
.sidebar.active{left:0;}
.sidebar::-webkit-scrollbar{width:6px;}
.sidebar::-webkit-scrollbar-track{background:transparent;}
.sidebar::-webkit-scrollbar-thumb{background:var(--accent);border-radius:10px;}
.sidebar-header{padding:30px 20px;background:var(--gradient);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:10;}
.sidebar-header h2{color:#fff;font-size:24px;font-weight:700;letter-spacing:-0.5px;}
.logo-icon{width:40px;height:40px;background:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--accent);margin-right:12px;}
.close-btn{background:rgba(255,255,255,0.2);border:none;width:36px;height:36px;border-radius:50%;font-size:20px;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(10px);}
.close-btn:hover{background:rgba(255,255,255,0.3);transform:rotate(90deg);}
.sidebar ul{list-style:none;padding:20px 0;}
.sidebar ul li{margin:5px 15px;}
.sidebar ul li a{display:flex;align-items:center;padding:14px 18px;color:var(--text-primary);text-decoration:none;border-radius:12px;font-weight:500;position:relative;overflow:hidden;}
.sidebar ul li a::before{content:'';position:absolute;left:0;top:0;width:4px;height:100%;background:var(--accent);transform:scaleY(0);transition:transform 0.3s;}
.sidebar ul li a:hover{background:var(--bg-tertiary);transform:translateX(5px);}
.sidebar ul li a:hover::before{transform:scaleY(1);}
.sidebar ul li a.active{background:var(--gradient);color:#fff;}
.sidebar ul li a i{width:24px;margin-right:12px;font-size:18px;}
main{flex:1;margin-left:0;padding:0;width:100%;background:var(--bg-primary);}
.topbar{background:var(--sidebar-bg);padding:20px 30px;box-shadow:var(--shadow);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:999;backdrop-filter:blur(10px);border-bottom:1px solid var(--border);}
.topbar-left{display:flex;align-items:center;gap:20px;}
.menu-btn{background:var(--gradient);border:none;width:45px;height:45px;border-radius:12px;font-size:20px;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 15px rgba(102,126,234,0.4);}
.menu-btn:hover{transform:scale(1.05);box-shadow:0 6px 20px rgba(102,126,234,0.5);}
.topbar h1{font-size:28px;font-weight:700;background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;letter-spacing:-0.5px;}
.topbar-right{display:flex;align-items:center;gap:15px;}
.theme-toggle{background:var(--bg-tertiary);border:none;width:45px;height:45px;border-radius:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-primary);font-size:20px;}
.theme-toggle:hover{background:var(--accent);color:#fff;transform:rotate(180deg);}
.admin-profile{display:flex;align-items:center;gap:12px;padding:10px 16px;background:var(--bg-tertiary);border-radius:12px;cursor:pointer;}
.admin-profile:hover{background:var(--accent);color:#fff;}
.admin-avatar{width:40px;height:40px;border-radius:50%;background:var(--gradient);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:16px;}
.admin-info{display:flex;flex-direction:column;}
.admin-name{font-weight:600;font-size:14px;}
.admin-role{font-size:12px;color:var(--text-secondary);text-transform:uppercase;}
.content{padding:30px;}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:25px;margin-bottom:35px;}
.stat-card{background:var(--card-bg);padding:25px;border-radius:20px;box-shadow:var(--shadow);border:1px solid var(--border);position:relative;overflow:hidden;}
.stat-card::before{content:'';position:absolute;top:-50%;right:-50%;width:200px;height:200px;background:radial-gradient(circle,var(--accent) 0%,transparent 70%);opacity:0.1;}
.stat-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-hover);}
.stat-icon{width:60px;height:60px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:15px;color:#fff;}
.stat-icon.purple{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);}
.stat-icon.green{background:linear-gradient(135deg,#10b981 0%,#059669 100%);}
.stat-icon.orange{background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);}
.stat-icon.blue{background:linear-gradient(135deg,#3b82f6 0%,#2563eb 100%);}
.stat-icon.red{background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%);}
.stat-icon.cyan{background:linear-gradient(135deg,#06b6d4 0%,#0891b2 100%);}
.stat-info h3{font-size:32px;font-weight:700;margin-bottom:5px;color:var(--text-primary);}
.stat-info p{color:var(--text-secondary);font-size:14px;font-weight:500;}
.stat-trend{display:flex;align-items:center;gap:5px;margin-top:10px;font-size:13px;font-weight:600;}
.stat-trend.up{color:var(--success);}
.stat-trend.down{color:var(--danger);}
.cards-section{margin-top:35px;}
.section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;}
.section-title{font-size:22px;font-weight:700;color:var(--text-primary);}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:25px;}
.card{background:var(--card-bg);padding:30px;border-radius:20px;text-align:center;color:var(--text-primary);text-decoration:none;box-shadow:var(--shadow);position:relative;overflow:hidden;border:1px solid var(--border);display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:180px;}
.card::before{content:'';position:absolute;top:0;left:0;width:100%;height:100%;background:var(--gradient);opacity:0;transition:opacity 0.3s;}
.card:hover::before{opacity:1;}
.card:hover{transform:translateY(-8px);box-shadow:var(--shadow-hover);}
.card:hover i,.card:hover h3{color:#fff;z-index:1;}
.card i{font-size:42px;margin-bottom:15px;color:var(--accent);position:relative;z-index:1;}
.card h3{font-size:16px;font-weight:600;position:relative;z-index:1;}
.quick-actions{position:fixed;bottom:30px;right:30px;z-index:100;}
.fab-btn{width:60px;height:60px;border-radius:50%;background:var(--gradient);border:none;color:#fff;font-size:24px;cursor:pointer;box-shadow:0 8px 25px rgba(102,126,234,0.4);display:flex;align-items:center;justify-content:center;}
.fab-btn:hover{transform:scale(1.1) rotate(90deg);box-shadow:0 12px 35px rgba(102,126,234,0.6);}
@media(max-width:768px){.sidebar{width:100%;left:-100%;}.sidebar.active{left:0;}.topbar{padding:15px 20px;}.topbar h1{font-size:20px;display:none;}.content{padding:20px;}.stats-grid{grid-template-columns:1fr;}.cards{grid-template-columns:repeat(2,1fr);}.admin-info{display:none;}.quick-actions{bottom:20px;right:20px;}.fab-btn{width:50px;height:50px;font-size:20px;}}
@media(min-width:769px){.sidebar{left:0;}.close-btn{display:none;}main{margin-left:280px;}.menu-btn{display:none;}}
@media(max-width:480px){.cards{grid-template-columns:1fr;}.stat-card{padding:20px;}.card{padding:25px;min-height:150px;}}
.loader-wrapper{position:fixed;top:0;left:0;width:100%;height:100%;background:var(--bg-primary);display:flex;align-items:center;justify-content:center;z-index:9999;opacity:1;visibility:visible;}
.loader-wrapper.hidden{opacity:0;visibility:hidden;}
.loader{width:60px;height:60px;border:4px solid var(--border);border-top:4px solid var(--accent);border-radius:50%;animation:spin 1s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
</style>
</head>
<body>
<?php include '../includes/loader.php'; ?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div style="display:flex;align-items:center;">
            <div class="logo-icon"><i class="fa fa-crown"></i></div>
            <h2>Admin</h2>
        </div>
        <button class="close-btn" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
    </div>
    <ul>
        <li><a href="admin_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="tasks.php"><i class="fas fa-plus-circle"></i> Create Task</a></li>
        <li><a href="manage_task.php"><i class="fas fa-tasks"></i> Manage Tasks</a></li>
        <li><a href="submissions.php"><i class="fas fa-check-circle"></i> Submissions</a></li>
        <li><a href="withdrawals.php"><i class="fas fa-wallet"></i> Withdrawals</a></li>
        <li><a href="../../uqxmining/uqxadmin.php"><i class="fas fa-coins"></i> UQX Reports</a></li>
        <li><a href="../../uqxmining/admin.php"><i class="fas fa-coins"></i> UQX Distributions</a></li>
        <li><a href="users.php"><i class="fas fa-users"></i> Manage Users</a></li>
        <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports & Analytics</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</aside>

<!-- Main Content -->
<main>
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <h1>Dashboard Overview</h1>
        </div>
        <div class="topbar-right">
            <button class="theme-toggle" onclick="toggleTheme()" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($admin_role, 0, 1)); ?></div>
                <div class="admin-info">
                    <div class="admin-name">Admin Panel</div>
                    <div class="admin-role"><?php echo htmlspecialchars($admin_role); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="content">
       

        <!-- Cards Section -->
        <div class="cards-section">
            <div class="section-header">
                <h2 class="section-title">Quick Actions</h2>
            </div>
            <div class="cards">
                <a href="tasks.php" class="card">
                    <i class="fas fa-plus-circle"></i>
                    <h3>Create New Task</h3>
                </a>
                <a href="manage_task.php" class="card">
                    <i class="fas fa-tasks"></i>
                    <h3>Manage Tasks</h3>
                </a>
                <a href="submissions.php" class="card">
                    <i class="fas fa-check-circle"></i>
                    <h3>Review Submissions</h3>
                </a>
                <a href="withdrawals.php" class="card">
                    <i class="fas fa-wallet"></i>
                    <h3>Withdraw Requests</h3>
                </a>
                <a href="../../uqxmining/admin.php" class="card">
                    <i class="fas fa-coins"></i>
                    <h3>UQX Distributions</h3>
                </a>
                <a href="users.php" class="card">
                    <i class="fas fa-users"></i>
                    <h3>User Management</h3>
                </a>
                <a href="reports.php" class="card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Analytics & Reports</h3>
                </a>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <div class="quick-actions">
        <button class="fab-btn" onclick="location.href='tasks.php'" title="Create Task">
            <i class="fas fa-plus"></i>
        </button>
    </div>
</main>

<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('active');}
function toggleTheme(){const html=document.documentElement;const currentTheme=html.getAttribute('data-theme');const newTheme=currentTheme==='dark'?'light':'dark';html.setAttribute('data-theme',newTheme);localStorage.setItem('theme',newTheme);const icon=document.querySelector('#themeToggle i');icon.className=newTheme==='dark'?'fas fa-sun':'fas fa-moon';}
document.addEventListener('DOMContentLoaded',function(){const savedTheme=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-theme',savedTheme);const icon=document.querySelector('#themeToggle i');icon.className=savedTheme==='dark'?'fas fa-sun':'fas fa-moon';});
</script>

</body>
</html>
