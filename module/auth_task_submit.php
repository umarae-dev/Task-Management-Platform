
<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();


define('PROOF_DIR', __DIR__.'/../uploads/proofs');
define('PROOF_URL','/uploads/proofs');
$RECAPTCHA_SECRET = '....................................';

if (!isset($_SESSION['user_id'])) { header('Location: ../backend/login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];
$task_id = (int)($_GET['id'] ?? 0);

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ip_type = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'IPv6' : 'IPv4';

// --- Geo ---
$country = $city = 'Unknown';
$geo = @json_decode(@file_get_contents("http://ip-api.com/json/{$ip}?fields=country,city"), true);
if($geo && !empty($geo['country'])) $country = $geo['country'];
if($geo && !empty($geo['city']))    $city    = $geo['city'];

// --- VPN ---
$vpn_str = 'No';
$vpn_resp = @file_get_contents("https://vpnapi.io/api/{$ip}?key=free");
$vpn_data = $vpn_resp ? json_decode($vpn_resp,true) : null;
if($vpn_data && (($vpn_data['security']['vpn']??false) || ($vpn_data['security']['proxy']??false) || ($vpn_data['security']['tor']??false))) {
    $vpn_str='Yes';
}

// --- Blacklist ---
$blacklist = ['1.2.3.4','5.6.7.8'];
if(in_array($ip,$blacklist,true)) exit('Your IP is blacklisted.');

// --- Task ---
$stmt = $conn->prepare("SELECT * FROM tasks WHERE id=? AND status='Active'");
$stmt->bind_param("i",$task_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();
if(!$task){ http_response_code(404); exit('Task not found'); }

// --- Duplicate check ---
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM task_submissions WHERE task_id=? AND user_id=?");
$stmt->bind_param("ii",$task_id,$user_id);
$stmt->execute();
$cnt = ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt->close();
if($cnt>=1){ header('Location: history.php?already=1'); exit; }

$error = null;

function safe_file_upload($file){
    if(!is_dir(PROOF_DIR)) mkdir(PROOF_DIR,0775,true);
    if(!isset($file['tmp_name']) || $file['error']!==UPLOAD_ERR_OK) return [false,'Invalid upload'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($allowed[$mime])) return [false,'Only JPG, PNG, WEBP allowed'];
    if(($file['size']??0) > 5*1024*1024) return [false,'Max 5MB'];
    $ext = $allowed[$mime];
    $name='proof_'.bin2hex(random_bytes(8)).'.'.$ext;
    $dest = PROOF_DIR.'/'.$name;
    if(!move_uploaded_file($file['tmp_name'],$dest)) return [false,'Move failed'];
    return [true,$name];
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $recaptcha_token = $_POST['g-recaptcha-response'] ?? '';
    $recaptcha_score = 0.0;
    if(empty($recaptcha_token)){
        $error='reCAPTCHA validation failed. Please try again.';
    }else{
        $resp=@file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=".urlencode($RECAPTCHA_SECRET)."&response=".urlencode($recaptcha_token)."&remoteip=".$ip);
        $respData = $resp ? json_decode($resp,true) : null;
        if(!$respData || empty($respData['success']) || ($respData['score']??0)<0.5){
            $error='reCAPTCHA verification failed. Please try again.';
        }
        $recaptcha_score = (float)($respData['score']??0);
    }

    $proof_image=$proof_text=null;
    $device_fp = trim($_POST['device_fp']??'');

    if(!$error && ($device_fp==='' || $device_fp==='0' || !preg_match('/^[a-z0-9\-]{8,64}$/i',$device_fp))){
        $error='Device fingerprint missing/invalid. Please reload and try again.';
    }

    if(!$error){
        if(stripos($task['proof_type'],'Screenshot')!==false){
            if(!isset($_FILES['proof_image'])) $error='Upload screenshot';
            else {
                [$ok,$res_file]=safe_file_upload($_FILES['proof_image']);
                if($ok) $proof_image=$res_file; else $error=$res_file;
            }
        }
        if(stripos($task['proof_type'],'Text')!==false){
            $proof_text=trim($_POST['proof_text']??'');
            if($proof_text==='') $error='Provide proof text';
        }
    }

    // Country change check
    if(!$error){
        $stmt = $conn->prepare("SELECT country, created_at FROM task_submissions WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $last = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if($last){
            $last_country = $last['country'];
            $last_time    = strtotime($last['created_at']);
            $now_time     = time();
            if($last_country !== $country && ($now_time - $last_time) < 300){
                $error = "Suspicious activity: Multiple countries too quickly. Blocked.";
            }
        }
    }

    // Device reuse
    if(!$error){
        $stmt = $conn->prepare("SELECT user_id FROM task_submissions WHERE device_fp = ? AND user_id <> ? LIMIT 1");
        $stmt->bind_param("si", $device_fp, $user_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if($existing){
            $error = "This device has already been used with another account.";
        }
    }

    $click_verified = 0; $fraud_score = 0;
    if(!$error){
        $clickCheck = $conn->prepare("SELECT id FROM task_clicks WHERE task_id=? AND user_id=? LIMIT 1");
        $clickCheck->bind_param("ii", $task_id, $user_id);
        $clickCheck->execute();
        $clickResult = $clickCheck->get_result();
        $clickCheck->close();
        if ($clickResult->num_rows > 0) {
            $click_verified = 1;
        } else {
            $fraud_score += 50;
            $error = "❌ Please click the task link first before submitting proof.";
        }
        if ($vpn_str === 'Yes') $fraud_score += 40;
        if ($recaptcha_score < 0.5) $fraud_score += 30;
    }

    if(!$error){
        $stmt = $conn->prepare("
            INSERT INTO task_submissions
            (task_id, user_id, proof_image, proof_text, created_at, status, ip_address, ip_type, country, city, vpn_proxy_tor, recaptcha_score, device_fp, click_verified, fraud_score)
            VALUES (?, ?, ?, ?, NOW(), 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisssssssdsii", $task_id, $user_id, $proof_image, $proof_text, $ip, $ip_type, $country, $city, $vpn_str, $recaptcha_score, $device_fp, $click_verified, $fraud_score);
        $stmt->execute();
        $stmt->close();
        header('Location: history.php?submitted=1');
        exit;
    }
}

$rc = strtoupper($task['reward_currency'] ?? 'USD');
$isUqx = ($rc === 'UQX');
$reward_display = $isUqx ? number_format((float)$task['reward_amount'],2).' UQX' : '$'.number_format((float)$task['reward_amount'],2);
?>
