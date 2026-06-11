
<?php
// task/_task_helper.php
if (!isset($conn)) {
  
}
if (session_status() === PHP_SESSION_NONE) session_start();

define('PROOF_DIR', __DIR__ . '/../uploads/proofs');    
define('PROOF_URL', '/uploads/proofs');                 

function require_user() {
    if (empty($_SESSION['user_id'])) { http_response_code(401); exit('Login required'); }
}

function require_admin() {
    if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        http_response_code(403); exit('Admin only');
    }
}

function safe_file_upload(array $file): array {
    if (!is_dir(PROOF_DIR)) { @mkdir(PROOF_DIR, 0775, true); }
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return [false, 'Invalid file upload'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) return [false, 'Only JPG, PNG, WEBP allowed'];
    if ($file['size'] > 5 * 1024 * 1024) return [false, 'Max size 5MB'];
    $ext = $allowed[$mime];
    $name = 'proof_' . uniqid() . '.' . $ext;
    $dest = PROOF_DIR . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return [false, 'Move failed'];
    return [true, $name];
}

function approve_submission_and_credit($conn, int $submission_id, int $admin_id = null): bool {
    $conn->begin_transaction();
    try {
        // Lock row for update
        $stmt = $conn->prepare("SELECT s.*, t.reward_amount FROM task_submissions s JOIN tasks t ON t.id = s.task_id WHERE s.id=? FOR UPDATE");
        if (!$stmt) throw new Exception($conn->error);
        $stmt->bind_param("i", $submission_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if (!$row || $row['status'] !== 'Pending') { $conn->rollback(); return false; }

        // Approve submission
        $stmt = $conn->prepare("UPDATE task_submissions SET status='Approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?");
        if (!$stmt) throw new Exception($conn->error);
        $stmt->bind_param("ii", $admin_id, $submission_id);
        $stmt->execute();

        // Ledger credit
        $stmt = $conn->prepare("INSERT INTO wallet_ledger (user_id, source_type, source_id, direction, amount, description) VALUES (?,?,?,?,?,?)");
        if (!$stmt) throw new Exception($conn->error);
        $source_type = 'Task';
        $direction = 'credit';
        $desc = 'Task reward credited';
        $stmt->bind_param("isissd", $row['user_id'], $source_type, $submission_id, $direction, $row['reward_amount'], $desc);
        $stmt->execute();

        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

function reject_submission($conn, int $submission_id, int $admin_id = null, string $note = null): bool {
    $stmt = $conn->prepare("UPDATE task_submissions SET status='Rejected', reviewer_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=? AND status='Pending'");
    if (!$stmt) return false;
    $stmt->bind_param("sii", $note, $admin_id, $submission_id);
    return $stmt->execute();
}

function user_balance($conn, int $user_id): array {
    // Credits & debits
    $stmt = $conn->prepare("SELECT 
        SUM(CASE WHEN direction='credit' THEN amount ELSE 0 END) AS credits,
        SUM(CASE WHEN direction='debit'  THEN amount ELSE 0 END) AS debits
        FROM wallet_ledger WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc() ?: ['credits'=>0,'debits'=>0];
    $credits = (float)$row['credits'];
    $debits = (float)$row['debits'];
    $balance = max(0,$credits-$debits);

    // Pending withdrawals total
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS pending FROM task_withdrawals WHERE user_id=? AND status='Pending'");
    $stmt->bind_param("i",$user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $pending_withdraw = (float)$res->fetch_assoc()['pending'];

    return compact('credits','debits','balance','pending_withdraw');
}
