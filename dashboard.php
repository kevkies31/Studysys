<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$activePage = 'dashboard';
$userId = $_SESSION['user_id'];

// Budget summary (this month)
$incomeStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM transactions WHERE user_id = ? AND type = 'income' AND MONTH(txn_date) = MONTH(CURDATE()) AND YEAR(txn_date) = YEAR(CURDATE())");
$incomeStmt->execute([$userId]);
$income = $incomeStmt->fetch()['total'];

$expenseStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM transactions WHERE user_id = ? AND type = 'expense' AND MONTH(txn_date) = MONTH(CURDATE()) AND YEAR(txn_date) = YEAR(CURDATE())");
$expenseStmt->execute([$userId]);
$expense = $expenseStmt->fetch()['total'];

$balance = $income - $expense;

// Today's schedule
$todayCode = strtoupper(date('D')); // MON, TUE, etc. PHP gives Mon/Tue -> uppercase 3-letter matches enum
$scheduleStmt = $pdo->prepare("SELECT * FROM schedules WHERE user_id = ? AND day_of_week = ? ORDER BY start_time ASC");
$scheduleStmt->execute([$userId, $todayCode]);
$todaySchedule = $scheduleStmt->fetchAll();

// Upcoming calendar events (next 5)
$eventsStmt = $pdo->prepare("SELECT * FROM calendar_events WHERE user_id = ? AND event_date >= CURDATE() ORDER BY event_date ASC, event_time ASC LIMIT 5");
$eventsStmt->execute([$userId]);
$upcomingEvents = $eventsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard - StudySys</title>
<link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body>
<div class="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="main">
    <header>
      <div>
        <h1>Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?></h1>
        <p><?= date('l, F j, Y') ?></p>
      </div>
    </header>

    <div class="grid-cards">
      <div class="card">
        <div class="label">Income this month</div>
        <div class="value success">₱<?= number_format($income, 2) ?></div>
      </div>
      <div class="card">
        <div class="label">Expenses this month</div>
        <div class="value danger">₱<?= number_format($expense, 2) ?></div>
      </div>
      <div class="card">
        <div class="label">Balance</div>
        <div class="value">₱<?= number_format($balance, 2) ?></div>
      </div>
      <div class="card">
        <div class="label">Classes today</div>
        <div class="value"><?= count($todaySchedule) ?></div>
      </div>
    </div>

    <div class="panel-block" style="margin-bottom:20px;">
      <h2>Spending by Category (This Month)</h2>
      <?php
      $chartStmt = $pdo->prepare("
          SELECT c.name, COALESCE(SUM(t.amount),0) AS spent
          FROM budget_categories c
          LEFT JOIN transactions t ON t.category_id = c.id AND t.type = 'expense'
              AND MONTH(t.txn_date) = MONTH(CURDATE()) AND YEAR(t.txn_date) = YEAR(CURDATE())
          WHERE c.user_id = ?
          GROUP BY c.id, c.name
          ORDER BY spent DESC
          LIMIT 6
      ");
      $chartStmt->execute([$userId]);
      $chartData = $chartStmt->fetchAll();
      $categoryAmounts = array_map('floatval', array_column($chartData, 'spent'));
      $maxSpent = !empty($categoryAmounts) ? max(1.0, max($categoryAmounts)) : 1.0;

      // Last-seven-days expense trend for a quick spending pulse.
      $trendStmt = $pdo->prepare("SELECT txn_date, COALESCE(SUM(amount),0) AS spent FROM transactions WHERE user_id = ? AND type = 'expense' AND txn_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY txn_date ORDER BY txn_date ASC");
      $trendStmt->execute([$userId]);
      $trendByDate = [];
      foreach ($trendStmt->fetchAll() as $row) {
          $trendByDate[$row['txn_date']] = (float)$row['spent'];
      }
      $trendDays = [];
      for ($i = 6; $i >= 0; $i--) {
          $date = date('Y-m-d', strtotime("-$i days"));
          $trendDays[] = ['date' => $date, 'spent' => $trendByDate[$date] ?? 0];
      }
      $maxDailySpent = max(array_column($trendDays, 'spent')) ?: 1;
      $sevenDayTotal = array_sum(array_column($trendDays, 'spent'));
      $topCategory = $chartData[0] ?? null;
      ?>
      <?php if (empty($chartData)): ?>
        <div class="empty-state">No spending data yet.</div>
      <?php else: ?>
        <?php foreach ($chartData as $cd): ?>
          <div style="margin-bottom:10px;">
            <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
              <span><?= htmlspecialchars($cd['name']) ?></span>
              <span style="color:var(--text-dim);">₱<?= number_format($cd['spent'], 0) ?></span>
            </div>
            <div class="bar-track">
              <div class="bar-fill" style="width: <?= ($cd['spent'] / $maxSpent) * 100 ?>%;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="grid-cards" style="grid-template-columns: 1.5fr 1fr; align-items:stretch;">
      <div class="panel-block">
        <h2>Expense Trend (Last 7 Days)</h2>
        <div style="display:flex; align-items:flex-end; gap:10px; height:150px; padding-top:10px;">
          <?php foreach ($trendDays as $day): ?>
            <div style="flex:1; height:100%; display:flex; flex-direction:column; justify-content:flex-end; align-items:center; gap:6px; min-width:0;">
              <span style="font-size:11px; color:var(--text-dim);">₱<?= number_format($day['spent'], 0) ?></span>
              <div title="₱<?= number_format($day['spent'], 2) ?>" style="width:100%; max-width:34px; height:<?= max(4, ($day['spent'] / $maxDailySpent) * 100) ?>%; background:var(--accent); border-radius:5px 5px 2px 2px;"></div>
              <span style="font-size:11px; color:var(--text-dim);"><?= date('D', strtotime($day['date'])) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="panel-block">
        <h2>Analytics Snapshot</h2>
        <div style="display:grid; gap:14px;">
          <div>
            <div class="label">Spent in last 7 days</div>
            <strong style="font-size:22px;">₱<?= number_format($sevenDayTotal, 2) ?></strong>
          </div>
          <div>
            <div class="label">Average per day</div>
            <strong style="font-size:22px;">₱<?= number_format($sevenDayTotal / 7, 2) ?></strong>
          </div>
          <div>
            <div class="label">Top category this month</div>
            <strong><?= $topCategory ? htmlspecialchars($topCategory['name']) . ' · ₱' . number_format($topCategory['spent'], 2) : 'No data yet' ?></strong>
          </div>
        </div>
      </div>
    </div>

    <div class="grid-cards" style="grid-template-columns: 1.2fr 1fr;">
      <div class="panel-block">
        <h2>Today's Schedule</h2>
        <?php if (empty($todaySchedule)): ?>
          <div class="empty-state">No classes scheduled today. <a href="schedule.php" style="color:var(--accent)">Add your schedule &rarr;</a></div>
        <?php else: ?>
          <?php foreach ($todaySchedule as $s): ?>
            <div style="padding:10px 0; border-bottom:1px solid var(--border); display:flex; justify-content:space-between;">
              <div>
                <strong><?= htmlspecialchars($s['subject']) ?></strong><br>
                <span style="color:var(--text-dim); font-size:13px;"><?= htmlspecialchars($s['room'] ?? '') ?> · <?= htmlspecialchars($s['faculty'] ?? '') ?></span>
              </div>
              <div style="color:var(--text-dim); font-size:13px;">
                <?= date('g:i A', strtotime($s['start_time'])) ?> - <?= date('g:i A', strtotime($s['end_time'])) ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="panel-block">
        <h2>Upcoming Events</h2>
        <?php if (empty($upcomingEvents)): ?>
          <div class="empty-state">Nothing coming up yet.</div>
        <?php else: ?>
          <?php foreach ($upcomingEvents as $e): ?>
            <div style="padding:10px 0; border-bottom:1px solid var(--border);">
              <strong><?= htmlspecialchars($e['title']) ?></strong><br>
              <span style="color:var(--text-dim); font-size:13px;"><?= date('M j', strtotime($e['event_date'])) ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
  <script src="assets/js/main.js?v=3"></script>
</body>
</html>
