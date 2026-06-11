<?php
if (!isset($_SESSION)) session_start();

require_once __DIR__ . '/../task/_task_helper.php';

$user_id  = (int)($_SESSION['user_id'] ?? 0);
$task_bal = user_balance($conn, $user_id);
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap');

/* ═══ TASK WALLET CARD ═══ */
.task-card-wrap * { box-sizing: border-box; }

.task-card-wrap {
  font-family: 'Outfit', sans-serif;
  margin-bottom: 24px;
}

.task-card {
  position: relative;
  border-radius: 28px;
  overflow: hidden;
  padding: 2px;
  background: linear-gradient(135deg, #11998e, #38ef7d, #11998e, #0bd39c);
  background-size: 300% 300%;
  animation: taskBorderFlow 5s linear infinite;
  box-shadow:
    0 0 0 1px rgba(56,239,125,0.1),
    0 32px 80px rgba(17,153,142,0.22),
    0 8px 32px rgba(11,211,156,0.15);
}

@keyframes taskBorderFlow {
  0%   { background-position: 0% 50%; }
  50%  { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}

.task-card-inner {
  background: #04110e;
  border-radius: 27px;
  padding: 28px 28px 24px;
  position: relative;
  overflow: hidden;
}

/* ── Atmospheric background ── */
.task-card-inner::before {
  content: '';
  position: absolute;
  top: -60px; right: -60px;
  width: 280px; height: 280px;
  background: radial-gradient(circle,
    rgba(56,239,125,0.1) 0%,
    rgba(17,153,142,0.05) 40%,
    transparent 70%);
  pointer-events: none;
  animation: taskOrb 7s ease-in-out infinite alternate;
}

.task-card-inner::after {
  content: '';
  position: absolute;
  bottom: -50px; left: -50px;
  width: 220px; height: 220px;
  background: radial-gradient(circle,
    rgba(11,211,156,0.08) 0%,
    transparent 70%);
  pointer-events: none;
  animation: taskOrb 9s ease-in-out infinite alternate-reverse;
}

@keyframes taskOrb {
  from { transform: scale(1); opacity: 0.6; }
  to   { transform: scale(1.3) translate(8px,-8px); opacity: 1; }
}

/* Dot grid texture */
.task-dotgrid {
  position: absolute; inset: 0;
  background-image: radial-gradient(rgba(56,239,125,0.08) 1px, transparent 1px);
  background-size: 24px 24px;
  pointer-events: none;
  border-radius: 27px;
  opacity: 0.5;
}

/* ── Top row ── */
.task-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 22px;
  position: relative;
  z-index: 2;
}

.task-icon-wrap {
  width: 48px; height: 48px;
  border-radius: 14px;
  background: linear-gradient(135deg, rgba(56,239,125,0.12), rgba(17,153,142,0.08));
  border: 1px solid rgba(56,239,125,0.2);
  display: flex; align-items: center; justify-content: center;
  font-size: 22px;
  position: relative;
}
.task-icon-wrap::after {
  content: '';
  position: absolute; inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,0.06), transparent);
  border-radius: inherit;
}

.task-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 2.5px;
  text-transform: uppercase;
  color: rgba(255,255,255,0.3);
  margin-bottom: 3px;
}

.task-title {
  font-size: 17px;
  font-weight: 800;
  color: #fff;
  letter-spacing: -0.3px;
}

.task-active-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(56,239,125,0.08);
  border: 1px solid rgba(56,239,125,0.2);
  border-radius: 99px;
  padding: 5px 12px;
  font-size: 11px;
  font-weight: 700;
  color: #38ef7d;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.task-active-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  background: #38ef7d;
  box-shadow: 0 0 8px #38ef7d;
  animation: taskDotPulse 2s ease-in-out infinite;
}

@keyframes taskDotPulse {
  0%,100% { transform: scale(1); box-shadow: 0 0 6px #38ef7d; }
  50%      { transform: scale(1.5); box-shadow: 0 0 14px #38ef7d; }
}

/* ── Balance blocks ── */
.task-balances {
  position: relative;
  z-index: 2;
  margin-bottom: 20px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.task-bal-card {
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 16px;
  padding: 14px 16px;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.task-bal-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
  border-radius: 16px 16px 0 0;
}

.task-bal-card.available::before {
  background: linear-gradient(90deg, #38ef7d, #11998e);
}

.task-bal-card.pending::before {
  background: linear-gradient(90deg, #f59e0b, #ef4444);
}

.task-bal-card:hover {
  background: rgba(56,239,125,0.04);
  border-color: rgba(56,239,125,0.15);
  transform: translateY(-2px);
}

.task-bal-card.pending:hover {
  background: rgba(245,158,11,0.04);
  border-color: rgba(245,158,11,0.15);
}

.task-bal-key {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: rgba(255,255,255,0.25);
  margin-bottom: 8px;
}

.task-bal-val {
  font-family: 'JetBrains Mono', monospace;
  font-size: 22px;
  font-weight: 700;
  line-height: 1;
}

.task-bal-val.green {
  background: linear-gradient(135deg, #38ef7d, #0bd39c);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.task-bal-val.amber {
  background: linear-gradient(135deg, #f59e0b, #ef4444);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.task-bal-sub {
  font-size: 10px;
  color: rgba(255,255,255,0.2);
  margin-top: 3px;
}

/* ── Progress ── */
.task-progress-section {
  position: relative;
  z-index: 2;
  margin-bottom: 20px;
}

.task-progress-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 7px;
}

.task-progress-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: rgba(255,255,255,0.25);
}

.task-progress-val {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  font-weight: 700;
  color: #38ef7d;
}

.task-progress-track {
  height: 5px;
  background: rgba(255,255,255,0.05);
  border-radius: 99px;
  overflow: hidden;
}

.task-progress-fill {
  height: 100%;
  border-radius: 99px;
  background: linear-gradient(90deg, #11998e, #38ef7d);
  box-shadow: 0 0 10px rgba(56,239,125,0.5);
  transition: width 1.5s cubic-bezier(0.34,1.56,0.64,1);
}

/* ── Divider ── */
.task-divider {
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(56,239,125,0.15), transparent);
  margin-bottom: 18px;
  position: relative;
  z-index: 2;
}

/* ── Buttons ── */
.task-btn-row {
  display: flex;
  gap: 10px;
  position: relative;
  z-index: 2;
}

.task-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 12px 16px;
  border-radius: 14px;
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
  letter-spacing: 0.3px;
  transition: all 0.35s cubic-bezier(0.34,1.56,0.64,1);
  position: relative;
  overflow: hidden;
  font-family: 'Outfit', sans-serif;
}

.task-btn::before {
  content: '';
  position: absolute; inset: 0;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
  transform: translateX(-100%);
  transition: transform 0.5s ease;
}
.task-btn:hover::before { transform: translateX(100%); }

.task-btn-primary {
  background: linear-gradient(135deg, #11998e, #38ef7d);
  color: #04110e;
  font-weight: 800;
  box-shadow: 0 4px 20px rgba(56,239,125,0.3);
}
.task-btn-primary:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 30px rgba(56,239,125,0.4);
}

.task-btn-ghost {
  background: rgba(56,239,125,0.05);
  color: rgba(56,239,125,0.7);
  border: 1px solid rgba(56,239,125,0.15);
}
.task-btn-ghost:hover {
  background: rgba(56,239,125,0.1);
  border-color: rgba(56,239,125,0.3);
  color: #38ef7d;
  transform: translateY(-3px);
}

.task-btn:active { transform: scale(0.97); }
</style>

<div class="task-card-wrap">
  <div class="task-card">
    <div class="task-card-inner">
      <div class="task-dotgrid"></div>

      <!-- Top Row -->
      <div class="task-top">
        <div style="display:flex;align-items:center;gap:13px;">
          <div class="task-icon-wrap">💼</div>
          <div>
            <div class="task-label">Wallet</div>
            <div class="task-title">Task Earnings</div>
          </div>
        </div>
        <div class="task-active-badge">
          <div class="task-active-dot"></div>
          Active
        </div>
      </div>

      <!-- Balance Blocks -->
      <div class="task-balances">
        <div class="task-bal-card available">
          <div class="task-bal-key">Available</div>
          <div class="task-bal-val green" id="task-avail">
            <?= number_format($task_bal['balance'], 2) ?>
          </div>
          <div class="task-bal-sub">USD · Ready to withdraw</div>
        </div>
        <div class="task-bal-card pending">
          <div class="task-bal-key">Pending</div>
          <div class="task-bal-val amber" id="task-pend">
            <?= number_format($task_bal['pending_withdraw'], 2) ?>
          </div>
          <div class="task-bal-sub">USD · Processing</div>
        </div>
      </div>

      <!-- Progress -->
      <?php
        $avail = (float)$task_bal['balance'];
        $pend  = (float)$task_bal['pending_withdraw'];
        $total = $avail + $pend;
        $pct   = $total > 0 ? round(($avail / $total) * 100) : 0;
      ?>
      <div class="task-progress-section">
        <div class="task-progress-row">
          <span class="task-progress-label">💰 Available Ratio</span>
          <span class="task-progress-val" id="task-pct"><?= $pct ?>%</span>
        </div>
        <div class="task-progress-track">
          <div class="task-progress-fill" id="task-bar" style="width:0%"></div>
        </div>
      </div>

      <div class="task-divider"></div>

      <!-- Buttons -->
      <div class="task-btn-row">
        <a href="../task/dashboard.php" class="task-btn task-btn-ghost">
          📋 Task Dashboard
        </a>
        <a href="../task/withdraw.php" class="task-btn task-btn-primary">
          💸 Withdraw
        </a>
      </div>

    </div>
  </div>
</div>

<script>
(function(){
  const avail = <?= (float)$task_bal['balance'] ?>;
  const pend  = <?= (float)$task_bal['pending_withdraw'] ?>;
  const pct   = <?= $pct ?>;

  function countUp(el, end, prefix='$', decimals=2) {
    if (!el) return;
    const dur = 1800, fps = 60, total = Math.round(dur/(1000/fps));
    let step = 0;
    const ease = t => 1 - Math.pow(1-t, 4);
    const timer = setInterval(() => {
      step++;
      el.textContent = prefix + (end * ease(step/total)).toFixed(decimals);
      if (step >= total) { clearInterval(timer); el.textContent = prefix + end.toFixed(decimals); }
    }, 1000/fps);
  }

  document.addEventListener('DOMContentLoaded', () => {
    countUp(document.getElementById('task-avail'), avail, '$');
    countUp(document.getElementById('task-pend'),  pend,  '$');

    /* Animate progress bar */
    setTimeout(() => {
      const bar = document.getElementById('task-bar');
      if (bar) bar.style.width = pct + '%';
    }, 300);
  });
})();
</script>
