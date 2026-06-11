
<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../task/_task_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.html');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$min_withdraw = 5.00;

$error = $success = null;

// Get current balances
$bal = user_balance($conn, $user_id);
$available = $bal['balance'] ?? 0.0;

// Fetch user for restriction check
$stmt = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle withdrawal POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($user['restricted'])) {
    $amount = round((float)($_POST['amount'] ?? 0), 2);
    $method = trim($_POST['method'] ?? '');
    $account = trim($_POST['account_details'] ?? '');

    if ($amount < $min_withdraw) $error = "Minimum withdraw is $".$min_withdraw;
    elseif ($amount > $available) $error = "Insufficient balance";
    elseif ($method === '' || $account === '') $error = "Method and account details required";
    else {
        $conn->begin_transaction();
        try {
            $ins = $conn->prepare("INSERT INTO task_withdrawals (user_id, amount, method, account_details, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
            $ins->bind_param("idss", $user_id, $amount, $method, $account);
            if(!$ins->execute()) throw new Exception("Failed to submit withdraw request");
            $conn->commit();

            // Prevent resubmission
            $_SESSION['withdraw_success'] = "Withdraw request submitted successfully.";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        } catch(Throwable $e) {
            if($conn->in_transaction()) $conn->rollback();
            $error = "Something went wrong. Try again.";
        }
    }
}

// Get success message after redirect
if(!empty($_SESSION['withdraw_success'])){
    $success = $_SESSION['withdraw_success'];
    unset($_SESSION['withdraw_success']);
}
?>
