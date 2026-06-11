<?php
if (!isset($_SESSION)) session_start();

require_once __DIR__ . '/../task/_task_helper.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$bal = user_balance($conn, $user_id);
?>

<div class="wallet-row">
    <div class="wallet-card task-wallet-card">
        <h3 class="wallet-title">💼 Task Wallet</h3>
        <p class="wallet-text">Available Balance:
            <strong class="wallet-plan-amount">$<?= number_format($bal['balance'], 2) ?></strong>
        </p>
        <p class="wallet-text">Pending Withdraw:
            <strong class="wallet-withdraw-amount">$<?= number_format($bal['pending_withdraw'], 2) ?></strong>
        </p>
        <button class="wallet-btn">💰 Withdraw Now</button>
    </div>
</div>

<style>
/* Wallet Row */
.wallet-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: flex-start; /* left aligned */
}

/* Task Wallet Card */
.wallet-card.task-wallet-card {
    flex: 1 1 300px; /* min width 300px, grow as needed */
    background: linear-gradient(135deg, #ffffff, #e0f7fa);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
}

.wallet-card.task-wallet-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
}

.wallet-card.task-wallet-card .wallet-title {
    font-size: 20px;
    color: #007bff;
    margin-bottom: 15px;
    font-weight: 600;
}

.wallet-card.task-wallet-card .wallet-text {
    font-size: 16px;
    margin-bottom: 10px;
}

.wallet-card.task-wallet-card .wallet-plan-amount {
    color: #28a745;
    font-weight: bold;
}

.wallet-card.task-wallet-card .wallet-withdraw-amount {
    color: #d9534f;
    font-weight: bold;
}

.wallet-card.task-wallet-card .wallet-btn {
    margin-top: 15px;
    padding: 12px 25px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    background: linear-gradient(to right, #1f4037, #99f2c8);
    color: #fff;
    cursor: pointer;
    transition: 0.3s;
}

.wallet-card.task-wallet-card .wallet-btn:hover {
    background: linear-gradient(to right, #145a32, #66d1a9);
}

/* Mobile Responsive */
@media(max-width:768px){
    .wallet-card.task-wallet-card {
        flex: 1 1 100%;
    }
}
</style>
