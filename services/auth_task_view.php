
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../backend/login.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT restricted, name, balance, usd_balance FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($restricted, $user_name, $user_balance, $user_usd_balance);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if ($restricted) { echo json_encode(['status'=>'restricted']); exit; }
    $action = $_POST['action'];
    if ($action === 'toggle_save' && isset($_POST['task_id'])) {
        $task_id = (int)$_POST['task_id'];
        $check = $conn->prepare("SELECT id FROM saved_tasks WHERE task_id=? AND user_id=? LIMIT 1");
        $check->bind_param("ii", $task_id, $user_id);
        $check->execute();
        $r = $check->get_result()->fetch_assoc();
        if ($r) {
            $del = $conn->prepare("DELETE FROM saved_tasks WHERE id=?");
            $del->bind_param("i", $r['id']);
            $ok = $del->execute();
            echo json_encode(['status'=>$ok?'removed':'error']);
        } else {
            $ins = $conn->prepare("INSERT INTO saved_tasks (task_id, user_id, created_at) VALUES (?, ?, NOW())");
            $ins->bind_param("ii", $task_id, $user_id);
            $ok = $ins->execute();
            echo json_encode(['status'=>$ok?'saved':'error']);
        }
        exit;
    }
    echo json_encode(['status'=>'unknown_action']);
    exit;
}

$search   = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter   = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page_input = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$per_page = in_array($per_page_input, [5,10,20,50], true) ? $per_page_input : 10;
$offset = ($page - 1) * $per_page;

$order_sql = "t.id DESC";
if ($filter === 'reward_high') $order_sql = "t.reward_amount DESC";
elseif ($filter === 'reward_low') $order_sql = "t.reward_amount ASC";
elseif ($filter === 'deadline') $order_sql = "t.deadline ASC";

$where_clauses = ["t.status IN ('Active','Draft','Paused','Archived')"];
$params = []; $types = "";
if ($search !== '') {
    $where_clauses[] = "(t.title LIKE ? OR t.description LIKE ? OR t.category LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}
if ($category !== '') {
    $where_clauses[] = "TRIM(LOWER(t.category)) = LOWER(?)";
    $params[] = $category; $types .= "s";
}
$where_clauses[] = "NOT EXISTS (SELECT 1 FROM task_submissions s WHERE s.task_id = t.id AND s.user_id = ?)";
$params[] = $user_id; $types .= "i";
$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$count_sql = "SELECT COUNT(*) AS total FROM tasks t {$where_sql}";
$count_stmt = $conn->prepare($count_sql);
$totalRows = 0;
if ($count_stmt) {
    if (!empty($params)) $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $res = $count_stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) $totalRows = (int)$row['total'];
}
$totalPages = ($per_page > 0) ? max(1, ceil($totalRows / $per_page)) : 1;

$list_sql = "SELECT t.*, (SELECT COUNT(*) FROM task_submissions s WHERE s.task_id = t.id) AS submissions_count, (SELECT EXISTS(SELECT 1 FROM saved_tasks st WHERE st.task_id = t.id AND st.user_id = ?)) AS is_saved FROM tasks t {$where_sql} ORDER BY {$order_sql} LIMIT $per_page OFFSET $offset";
$list_stmt = $conn->prepare($list_sql);
$bind_params = $params;
array_unshift($bind_params, $user_id);
$bind_types = "i" . $types;
if (!empty($bind_params)) $list_stmt->bind_param($bind_types, ...$bind_params);
$list_stmt->execute();
$res = $list_stmt->get_result();
$tasks = [];
if (method_exists($res, 'fetch_all')) {
    $tasks = $res->fetch_all(MYSQLI_ASSOC);
} else {
    while ($row = $res->fetch_assoc()) $tasks[] = $row;
}

$cats_q = $conn->query("SELECT DISTINCT category FROM tasks WHERE status='Active' ORDER BY category ASC");
$categories = [];
while($c = $cats_q->fetch_assoc()) $categories[] = $c['category'];

$lb_stmt = $conn->prepare("SELECT u.id, u.name, COUNT(s.id) as completed FROM users u JOIN task_submissions s ON s.user_id = u.id GROUP BY u.id ORDER BY completed DESC LIMIT 5");
$lb_stmt->execute();
$lb_res = $lb_stmt->get_result();
$leaderboard = [];
if (method_exists($lb_res, 'fetch_all')) {
    $leaderboard = $lb_res->fetch_all(MYSQLI_ASSOC);
} else {
    while ($row = $lb_res->fetch_assoc()) $leaderboard[] = $row;
}

$saved_q = $conn->prepare("SELECT t.id, t.title FROM tasks t JOIN saved_tasks st ON st.task_id=t.id WHERE st.user_id=? ORDER BY st.created_at DESC LIMIT 5");
$saved_q->bind_param("i", $user_id);
$saved_q->execute();
$res_saved = $saved_q->get_result();
$saved_tasks = [];
while ($s = $res_saved->fetch_assoc()) $saved_tasks[] = $s;

function build_query(array $overrides = []) {
    $qs = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($qs[$k]);
        else $qs[$k] = $v;
    }
    return htmlspecialchars('?' . http_build_query($qs));
}

function time_left_text($deadline) {
    if (empty($deadline)) return 'No deadline';
    try {
        $tz = date_default_timezone_get() ?: 'UTC';
        $now = new DateTimeImmutable('now', new DateTimeZone($tz));
        $d = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $deadline);
        if (!$d) $d = new DateTimeImmutable($deadline);
        $diff = $d->getTimestamp() - $now->getTimestamp();
        if ($diff <= 0) return 'Expired';
        $days = floor($diff / 86400);
        $hours = floor(($diff % 86400) / 3600);
        if ($days > 0) return "{$days}d";
        if ($hours > 0) return "{$hours}h";
        return "<1h";
    } catch (Exception $e) { return 'Invalid'; }
}

function get_reward_display($task) {
    $currency = isset($task['reward_currency']) ? strtoupper($task['reward_currency']) : 'USD';
    $amount = (float)(isset($task['reward_amount']) ? $task['reward_amount'] : 0);
    if ($currency === 'USD') return '$' . number_format($amount, 2);
    if ($currency === 'UQX') return number_format($amount, 2) . ' UQX';
    return number_format($amount, 2) . ' ' . htmlspecialchars($currency);
}

function get_status_info($status) {
    $s = strtolower($status);
    $map = [
        'active' => ['color'=>'#10b981','bg'=>'rgba(16,185,129,0.15)','label'=>'Active','pulse'=>true],
        'draft' => ['color'=>'#6366f1','bg'=>'rgba(99,102,241,0.15)','label'=>'Draft','pulse'=>false],
        'paused' => ['color'=>'#f59e0b','bg'=>'rgba(245,158,11,0.15)','label'=>'Paused','pulse'=>false],
        'archived' => ['color'=>'#6b7280','bg'=>'rgba(107,114,128,0.15)','label'=>'Archived','pulse'=>false]
    ];
    return isset($map[$s]) ? $map[$s] : ['color'=>'#6b7280','bg'=>'rgba(107,114,128,0.15)','label'=>ucfirst($s),'pulse'=>false];
}
?>
