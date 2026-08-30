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
<link rel="stylesheet" href="assets/css/style.css">
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
  <script src="assets/js/main.js"></script>
</body>
</html>
