
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>Tasks | Umarae</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<?php include '../includes/loader.php'; ?>

<!-- TOP NAVBAR -->
<nav class="topbar">
  <div class="topbar-inner">
    <a href="dashboard.php" class="logo">
      <div class="logo-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
      </div>
      <span class="logo-text">Umarae</span>
    </a>
    <div class="topbar-right">
      <div class="balance-pill">
        <div class="balance-item">
          <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          <span class="uqx"><?= number_format((float)(isset($user_balance) ? $user_balance : 0), 2) ?> UQX</span>
        </div>
        <div class="balance-divider"></div>
        <div class="balance-item">
          <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          <span class="usd">$<?= number_format((float)(isset($user_usd_balance) ? $user_usd_balance : 0), 2) ?></span>
        </div>
      </div>
      <button class="theme-btn" onclick="toggleTheme()" aria-label="Toggle theme">
        <svg id="themeIcon" class="icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
      <button class="menu-btn" onclick="toggleMobileMenu()" aria-label="Menu">
        <svg class="icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
    </div>
  </div>
</nav>

<!-- MOBILE BOTTOM NAVIGATION -->
<nav class="bottom-nav">
  <div class="bottom-nav-inner">
    <a href="dashboard.php" class="bottom-nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Home
    </a>
    <a href="available_tasks.php" class="bottom-nav-item active">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
      Tasks
    </a>
    <a href="my_submissions.php" class="bottom-nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      Done
    </a>
    <a href="saved_tasks.php" class="bottom-nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
      Saved
    </a>
  </div>
</nav>

<!-- FLOATING ACTION BUTTON -->
<button class="fab" onclick="window.location.href='task_submit.php'" title="Quick Submit">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
</button>

<main class="container">

<?php if ($restricted): ?>
<div class="restriction-alert">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  <span>Your account is restricted. Task submissions and saving are disabled.</span>
  <a href="../includes/user_chat.php" target="_blank">Contact Help</a>
</div>
<?php endif; ?>

<div class="page-header">
  <div>
    <h1>Available Tasks</h1>
    <p>Complete tasks and earn UQX or USD rewards</p>
  </div>
  <div class="count-badge">
    <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
    <?= $totalRows ?> tasks
  </div>
</div>

<div class="filter-bar">
  <input type="text" name="search" placeholder="Search tasks..." value="<?= htmlspecialchars($search) ?>" form="filterForm">
  <select name="category" form="filterForm">
    <option value="">All Categories</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= htmlspecialchars($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="filter" form="filterForm">
    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Newest</option>
    <option value="reward_high" <?= $filter === 'reward_high' ? 'selected' : '' ?>>High Reward</option>
    <option value="reward_low" <?= $filter === 'reward_low' ? 'selected' : '' ?>>Low Reward</option>
    <option value="deadline" <?= $filter === 'deadline' ? 'selected' : '' ?>>Deadline</option>
  </select>
  <select name="per_page" form="filterForm">
    <option value="5" <?= $per_page === 5 ? 'selected' : '' ?>>5</option>
    <option value="10" <?= $per_page === 10 ? 'selected' : '' ?>>10</option>
    <option value="20" <?= $per_page === 20 ? 'selected' : '' ?>>20</option>
    <option value="50" <?= $per_page === 50 ? 'selected' : '' ?>>50</option>
  </select>
  <form method="get" id="filterForm" style="display:none;"></form>
  <button type="submit" class="btn" form="filterForm">
    <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
    Filter
  </button>
</div>

<div class="row">
  <section class="main">
    <?php if (count($tasks) === 0): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <h3>No tasks available</h3>
        <p>Try adjusting your filters or check back later.</p>
      </div>
    <?php endif; ?>

    <div class="task-list">
    <?php foreach ($tasks as $t):
      $remaining = time_left_text($t['deadline'] ?? '');
      $submissions = (int)$t['submissions_count'];
      $max_sub = (int)(isset($t['max_submissions']) ? $t['max_submissions'] : 0);
      $progress_pct = ($max_sub > 0) ? min(100, (int)round($submissions / $max_sub * 100)) : 0;
      $status_info = get_status_info($t['status']);
      $is_expired = $remaining === 'Expired';
      $can_submit = !$restricted && $t['status'] === 'Active' && !$is_expired;
      $reward = get_reward_display($t);
      $deadline_class = $is_expired ? 'expired' : ($remaining !== 'No deadline' && strpos($remaining, 'd') === false ? 'urgent' : '');
    ?>
      <div class="task-row" id="task-<?= (int)$t['id'] ?>">
        <div class="task-row-main" onclick="toggleDetails(<?= (int)$t['id'] ?>)">
          <div class="status-dot <?= $status_info['pulse'] ? 'pulse' : '' ?>" style="background:<?= $status_info['color'] ?>;color:<?= $status_info['color'] ?>"></div>

          <div class="task-info">
            <div class="task-info-top">
              <span class="task-title"><?= htmlspecialchars($t['title']) ?></span>
              <span class="task-category"><?= htmlspecialchars(isset($t['category']) ? $t['category'] : 'General') ?></span>
            </div>
            <div class="task-meta-row">
              <span class="status-pill" style="background:<?= $status_info['bg'] ?>;color:<?= $status_info['color'] ?>">
                <svg class="icon-sm" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                <?= $status_info['label'] ?>
              </span>
              <span>
                <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <?= $submissions ?><?= $max_sub > 0 ? "/$max_sub" : "" ?>
              </span>
              <span>
                <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                <?= htmlspecialchars(isset($t['proof_type']) ? $t['proof_type'] : 'Screenshot') ?>
              </span>
              <?php if (!empty($t['external_url'])): ?>
              <span>
                <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                Link
              </span>
              <?php endif; ?>
            </div>
          </div>

          <div class="task-reward">
            <div class="task-reward-amount"><?= $reward ?></div>
            <div class="task-reward-label">reward</div>
          </div>

          <div class="task-deadline">
            <div class="task-deadline-time <?= $deadline_class ?>">
              <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <?= htmlspecialchars($remaining) ?>
            </div>
            <div class="task-deadline-label">deadline</div>
          </div>

          <div class="task-actions-compact" onclick="event.stopPropagation();">
            <a href="task_submit.php?id=<?= (int)$t['id'] ?>" class="btn-submit <?= !$can_submit ? 'disabled' : '' ?>"
               <?php if (!$can_submit): ?>
               onclick="event.preventDefault(); alert('Cannot submit: <?php echo $restricted ? 'Account restricted' : ($is_expired ? 'Task expired' : 'Not active'); ?>');"
               <?php endif; ?>>
              <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              Submit
            </a>
            <button class="btn-icon <?= $t['is_saved'] ? 'saved' : '' ?>" data-task-id="<?= (int)$t['id'] ?>"
                    <?= $restricted ? 'disabled' : '' ?> onclick="event.stopPropagation(); toggleSave(this);"
                    title="<?= $t['is_saved'] ? 'Remove from saved' : 'Save task' ?>">
              <svg class="icon" viewBox="0 0 24 24" fill="<?= $t['is_saved'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
            </button>
            <a href="task_detail.php?id=<?= (int)$t['id'] ?>" class="btn-icon" onclick="event.stopPropagation();" title="View details">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </a>
          </div>

          <div class="expand-arrow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            <span>Details</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          </div>
        </div>

        <div class="task-details" id="details-<?= (int)$t['id'] ?>">
          <div class="task-details-grid">
            <div class="task-details-left">
              <?= nl2br(htmlspecialchars($t['description'])) ?>
            </div>
            <div class="task-details-right">
              <div class="detail-card">
                <div class="icon-box">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
                </div>
                <div class="detail-card-content">
                  <div class="detail-card-label">Reward Amount</div>
                  <div class="detail-card-value" style="color:var(--gold-500);"><?= $reward ?></div>
                </div>
              </div>
              <div class="detail-card">
                <div class="icon-box">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="detail-card-content">
                  <div class="detail-card-label">Deadline</div>
                  <div class="detail-card-value"><?= htmlspecialchars(isset($t['deadline']) ? $t['deadline'] : 'No deadline') ?> <span style="color:var(--text-muted);font-size:12px;">(<?= htmlspecialchars($remaining) ?>)</span></div>
                </div>
              </div>
              <div class="detail-card">
                <div class="icon-box">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
                <div class="detail-card-content">
                  <div class="detail-card-label">Proof Required</div>
                  <div class="detail-card-value"><?= htmlspecialchars(isset($t['proof_type']) ? $t['proof_type'] : 'Screenshot / Link') ?></div>
                </div>
              </div>
              <?php if (!empty($t['external_url'])): ?>
              <div class="detail-card">
                <div class="icon-box">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                </div>
                <div class="detail-card-content">
                  <div class="detail-card-label">Task Link</div>
                  <div class="detail-card-value"><a href="redirect.php?task_id=<?= (int)$t['id'] ?>" target="_blank" class="detail-link" onclick="event.stopPropagation();"><?= htmlspecialchars($t['external_url']) ?> <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></a></div>
                </div>
              </div>
              <?php endif; ?>
              <?php if ($max_sub > 0): ?>
              <div class="progress-box">
                <div class="progress-header">
                  <span>
                    <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
                    Submissions
                  </span>
                  <small><?= $submissions ?> / <?= $max_sub ?> (<?= $progress_pct ?>%)</small>
                </div>
                <div class="progress-track">
                  <div class="progress-fill" style="width:<?= $progress_pct ?>"></div>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pager">
      <?php if ($page > 1): ?>
        <a href="<?= build_query(['page' => $page - 1]) ?>">
          <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
      <?php endif; ?>
      <?php
      $start = max(1, $page - 2);
      $end = min($totalPages, $page + 2);
      if ($start > 1) echo '<a href="' . build_query(['page' => 1]) . '">1</a>' . ($start > 2 ? '<span style="padding:11px 6px;color:var(--text-muted);font-size:13px;font-weight:700;">...</span>' : '');
      for ($i = $start; $i <= $end; $i++): ?>
        <a href="<?= build_query(['page' => $i]) ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor;
      if ($end < $totalPages) echo ($end < $totalPages - 1 ? '<span style="padding:11px 6px;color:var(--text-muted);font-size:13px;font-weight:700;">...</span>' : '') . '<a href="' . build_query(['page' => $totalPages]) . '">' . $totalPages . '</a>';
      ?>
      <?php if ($page < $totalPages): ?>
        <a href="<?= build_query(['page' => $page + 1]) ?>">
          <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </section>

  <aside class="sidebar">
    <div class="sidebar-card">
      <h4>
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
        Top Performers
      </h4>
      <ul class="leaderboard">
        <?php if (count($leaderboard) > 0): ?>
          <?php foreach ($leaderboard as $index => $lb): ?>
            <li>
              <span class="leaderboard-name">
                <span class="rank-icon">
                  <?php if ($index === 0): ?>🥇
                  <?php elseif ($index === 1): ?>🥈
                  <?php elseif ($index === 2): ?>🥉
                  <?php else: ?><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg><?php endif; ?>
                </span>
                <?= htmlspecialchars($lb['name']) ?>
              </span>
              <span class="leaderboard-score"><?= (int)$lb['completed'] ?> tasks</span>
            </li>
          <?php endforeach; ?>
        <?php else: ?>
          <li style="color:var(--text-muted);justify-content:center;">No data yet</li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="sidebar-card">
      <h4>
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        Saved Tasks
      </h4>
      <ul class="saved-list">
        <?php if (count($saved_tasks) > 0): ?>
          <?php foreach ($saved_tasks as $s): ?>
            <li><a href="task_detail.php?id=<?= (int)$s['id'] ?>">
              <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
              <?= htmlspecialchars($s['title']) ?>
            </a></li>
          <?php endforeach; ?>
        <?php else: ?>
          <li style="color:var(--text-muted);text-align:center;">No saved tasks</li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="sidebar-card">
      <div class="guide-box">
        <div class="guide-title">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.663 17h4.673M12 3v1m0 16v1m-10-9h1m18 0h1M4.22 4.22l.707.707m12.728 12.728.707.707M4.22 19.78l.707-.707m12.728-12.728.707-.707M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10z"/></svg>
          Quick Guide
        </div>
        <ol class="guide-steps">
          <li>Read task details carefully</li>
          <li>Click the external link if provided</li>
          <li>Complete all requirements</li>
          <li>Submit proof using the button</li>
          <li>Earn rewards in UQX or USD</li>
        </ol>
      </div>
    </div>
  </aside>
</div>

</main>
<?php include '../includes/task_dashboard_fottor.php'; ?>
</body>
</html>
