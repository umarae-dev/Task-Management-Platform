
<?php
if (!isset($conn)) {
  
}
if (!isset($_SESSION)) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? 0;

// ✅ Default values
$name = '';
$current_plan = 'N/A';
$current_plan_amount = 0;
$joined_date = '';
$wallet_balance = 0;
$referral_earned = 0;
$total_withdrawals = 0;
$investment = 0;
$earning_amount = 0;
$daily_return = 0;
$monthly_return = 0;
$yearly_return = 0;
$latest_earning = null;
$total_earning = 0;

// ✅ Get user info
$stmt = $conn->prepare("SELECT name, current_plan, current_plan_amount, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $current_plan, $current_plan_amount, $joined_at);
$stmt->fetch();
$stmt->close();

$joined_date = date('Y-m-d', strtotime($joined_at ?? ''));
$current_plan_amount = floatval($current_plan_amount ?? 0);

// ✅ Get all user’s approved deposits ordered by date
$deposits = [];
$stmt = $conn->prepare("SELECT id, created_at, amount FROM deposits WHERE user_id = ? AND status = 'approved' ORDER BY created_at ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $deposits[] = [
        'id' => $row['id'],
        'amount' => floatval($row['amount']),
        'start_date' => date('Y-m-d', strtotime($row['created_at']))
    ];
}
$stmt->close();

// ✅ Get all daily earnings
$earnings = [];
$rate_stmt = $conn->query("SELECT date, rate FROM daily_earnings ORDER BY date ASC");
while ($row = $rate_stmt->fetch_assoc()) {
    $earnings[] = [
        'date' => $row['date'],
        'rate' => floatval($row['rate'])
    ];
}

// ✅ Calculate wallet balance from active deposit at each date
$wallet_balance = 0;
$total_investment = 0;
foreach ($earnings as $earning) {
    $earning_date = $earning['date'];
    $rate = $earning['rate'];

    // Find deposit active on this date
    $active_deposit = null;
    foreach (array_reverse($deposits) as $deposit) {
        if ($earning_date >= $deposit['start_date']) {
            $active_deposit = $deposit;
            break;
        }
    }

    if ($active_deposit) {
        $amount = $active_deposit['amount'];
        $wallet_balance += round(($rate / 100) * $amount, 2);
    }
}

// ✅ Get latest earning rate
$latest_rate_stmt = $conn->query("SELECT date, rate FROM daily_earnings ORDER BY date DESC LIMIT 1");
if ($latest = $latest_rate_stmt->fetch_assoc()) {
    $latest_earning = [
        'date' => $latest['date'],
        'rate' => $latest['rate']
    ];

    // Use latest deposit for estimation
    $latest_active = end($deposits);
    if ($latest_active) {
        $earning_amount = round(($latest['rate'] / 100) * $latest_active['amount'], 2);
        $total_investment = $latest_active['amount'];
    }
}

// ✅ Return estimates
$daily_return = round($total_investment * 0.005, 2);
$monthly_return = round($daily_return * 30, 2);
$yearly_return = round($daily_return * 365, 2);

// ✅ Today’s earning exist?
$today = date('Y-m-d');
$check_today = $conn->query("SELECT 1 FROM daily_earnings WHERE date = '$today' LIMIT 1");
$show_notification = ($check_today && $check_today->num_rows > 0);

// ✅ Referral earnings
$stmt = $conn->prepare("SELECT SUM(amount_earned) FROM referral_earnings WHERE referrer_id = ? AND transferred = 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($referral_earned);
$stmt->fetch();
$stmt->close();
$referral_earned = floatval($referral_earned ?? 0);

// ✅ Withdrawals
$stmt = $conn->prepare("SELECT SUM(amount) FROM withdrawals WHERE user_id = ? AND status = 'approved'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($total_withdrawals);
$stmt->fetch();
$stmt->close();
$total_withdrawals = floatval($total_withdrawals ?? 0);

// ✅ Final calculations
$available_balance = max(0, ($wallet_balance + $referral_earned - $total_withdrawals));
$total_earning = $wallet_balance;
?>
