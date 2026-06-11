
<?php
// redirect.php
session_start();
include __DIR__ . '/../backend/db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

$user_id = (int)$_SESSION['user_id'];
$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;

if ($task_id <= 0) {
    die("Invalid task.");
}

// get task url
$stmt = $conn->prepare("SELECT external_url FROM tasks WHERE id=? LIMIT 1");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res) {
    die("Task not found.");
}
$target_url = $res['external_url'];

// log visit
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ref = $_SERVER['HTTP_REFERER'] ?? '';
$uri = $_SERVER['REQUEST_URI'] ?? '';

$log = $conn->prepare("INSERT INTO task_clicks (task_id, user_id, ip_address, user_agent, http_referrer, request_uri, clicked_at) 
                       VALUES (?,?,?,?,?,?,NOW())");
$log->bind_param("iissss", $task_id, $user_id, $ip, $ua, $ref, $uri);
$log->execute();

// redirect
header("Location: " . $target_url);
exit;
?>
