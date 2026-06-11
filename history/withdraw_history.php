
<?php
// task/withdraw_history.php
session_start();


if (!isset($_SESSION['user_id'])) {
    header('Location: ../backend/login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

/* ---- Input (GET) ---- */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$sort   = isset($_GET['sort'])   ? trim($_GET['sort'])   : 'newest';
$page   = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit  = 10;
$offset = ($page - 1) * $limit;

/* ---- Sort mapping (whitelist) ---- */
$sort_map = [
    'newest' => 'id DESC',
    'oldest' => 'id ASC',
    'high'   => 'amount DESC',
    'low'    => 'amount ASC',
];
$order_by = isset($sort_map[$sort]) ? $sort_map[$sort] : $sort_map['newest'];

/* ---- Build WHERE with bindings ---- */
$where  = " WHERE user_id=? ";
$types  = "i";
$params = [$user_id];

if ($status !== '' && in_array($status, ['Pending','Approved','Rejected'], true)) {
    $where  .= " AND status = ? ";
    $types  .= "s";
    $params[] = $status;
}

if ($search !== '') {
    $like = "%{$search}%";
    // method/status/account_details/admin_note/created_at میں سرچ
    $where  .= " AND (method LIKE ? OR status LIKE ? OR account_details LIKE ? OR admin_note LIKE ? OR created_at LIKE ?) ";
    $types  .= "sssss";
    array_push($params, $like, $like, $like, $like, $like);
}

/* ---- CSV Export (full filtered set, no LIMIT) ---- */
if (isset($_GET['export']) && $_GET['export'] === '1') {
    $sql = "SELECT id, amount, method, account_details, status, admin_note, created_at
            FROM task_withdrawals {$where} ORDER BY {$order_by}";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=withdraw_history.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Amount','Method','Account Details','Status','Admin Note','Created At']);
    while ($r = $result->fetch_assoc()) {
        fputcsv($out, [
            $r['id'],
            number_format((float)$r['amount'], 2),
            $r['method'],
            $r['account_details'],
            $r['status'],
            $r['admin_note'],
            $r['created_at']
        ]);
    }
    fclose($out);
    exit;
}

/* ---- Total count for pagination (same filters) ---- */
$count_sql = "SELECT COUNT(*) AS total FROM task_withdrawals {$where}";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$totalRows = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$totalPages = max(1, (int)ceil($totalRows / $limit));

/* ---- Fetch page rows ---- */
$list_sql = "SELECT id, amount, method, account_details, status, admin_note, created_at
             FROM task_withdrawals {$where} ORDER BY {$order_by} LIMIT ? OFFSET ?";
$list_stmt = $conn->prepare($list_sql);
$list_types  = $types . "ii";
$list_params = array_merge($params, [$limit, $offset]);
$list_stmt->bind_param($list_types, ...$list_params);
$list_stmt->execute();
$res = $list_stmt->get_result();

$rows = [];
while($row = $res->fetch_assoc()) $rows[] = $row;

/* ---- Helper: build query string preserving filters ---- */
function build_query($overrides = []) {
    $qs = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($qs[$k]);
        else $qs[$k] = $v;
    }
    return htmlspecialchars('?' . http_build_query($qs));
}
?>
