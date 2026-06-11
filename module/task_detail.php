
<?php
// task/task_detail.php
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id'])) { header('Location: ../backend/login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];

// --- AJAX toggle save/bookmark ---
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])){
    header('Content-Type: application/json');
    $action = $_POST['action'];
    if ($action==='toggle_save' && isset($_POST['task_id'])){
        $task_id=(int)$_POST['task_id'];
        $check=$conn->prepare("SELECT id FROM saved_tasks WHERE task_id=? AND user_id=?");
        $check->bind_param("ii",$task_id,$user_id); $check->execute();
        $r=$check->get_result()->fetch_assoc();
        if($r){
            $del=$conn->prepare("DELETE FROM saved_tasks WHERE id=?"); $del->bind_param("i",$r['id']); $ok=$del->execute();
            echo json_encode(['status'=>$ok?'removed':'error']); exit;
        }else{
            $ins=$conn->prepare("INSERT INTO saved_tasks (task_id,user_id,created_at) VALUES (?,?,NOW())");
            $ins->bind_param("ii",$task_id,$user_id); $ok=$ins->execute();
            echo json_encode(['status'=>$ok?'saved':'error']); exit;
        }
    }
    echo json_encode(['status'=>'unknown']); exit;
}

// --- Get task ID ---
$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($task_id<=0){ echo "Invalid Task ID"; exit; }

// Fetch task
$stmt=$conn->prepare("SELECT * FROM tasks WHERE id=? AND status='Active'");
$stmt->bind_param("i",$task_id); $stmt->execute(); $task=$stmt->get_result()->fetch_assoc();
if(!$task){ echo "Task not found or inactive."; exit; }

// Submissions progress
$submissions = (int)$conn->query("SELECT COUNT(*) FROM task_submissions WHERE task_id=$task_id")->fetch_row()[0];
$max_sub = (int)($task['max_submissions']??0);
$progress_pct = ($max_sub>0)?min(100,(int)round($submissions/$max_sub*100)):0;

// Time left
function time_left_text($deadline){
    if(empty($deadline)) return 'No deadline';
    $now = new DateTimeImmutable('now');
    $d = DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$deadline)?:new DateTimeImmutable($deadline);
    $diff = $d->getTimestamp()-$now->getTimestamp();
    if($diff<=0) return 'Expired';
    $days=floor($diff/86400); $hours=floor(($diff%86400)/3600);
    return $days>0?"{$days} day(s) left":"{$hours} hour(s) left";
}
$remaining = time_left_text($task['deadline']??'');

// Leaderboard
$lb_stmt=$conn->prepare("SELECT u.id,u.name,COUNT(s.id) as completed FROM users u JOIN task_submissions s ON s.user_id=u.id GROUP BY u.id ORDER BY completed DESC LIMIT 5");
$lb_stmt->execute(); $leaderboard=$lb_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Saved Tasks
$saved_q=$conn->prepare("SELECT t.id,t.title FROM tasks t JOIN saved_tasks st ON st.task_id=t.id WHERE st.user_id=? ORDER BY st.created_at DESC LIMIT 5");
$saved_q->bind_param("i",$user_id); $saved_q->execute(); $res_saved=$saved_q->get_result();
$saved_tasks=[]; while($s=$res_saved->fetch_assoc()) $saved_tasks[]=$s;

// Check if saved
$is_saved=$conn->prepare("SELECT 1 FROM saved_tasks WHERE task_id=? AND user_id=? LIMIT 1");
$is_saved->bind_param("ii",$task_id,$user_id); $is_saved->execute(); $is_saved = (bool)$is_saved->get_result()->fetch_assoc();
?>
