<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$activePage = 'calendar';
$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// ---------- Handle: Add manual event ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_event') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $eventDate = $_POST['event_date'] ?? '';
        $eventTime = $_POST['event_time'] ?: null;
        $description = trim($_POST['description'] ?? '');

        if ($title === '' || $eventDate === '') {
            $error = "Title and date are required.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO calendar_events (user_id, source, title, event_date, event_time, description) VALUES (?, 'manual', ?, ?, ?, ?)");
            $stmt->execute([$userId, $title, $eventDate, $eventTime, $description]);
            header('Location: calendar.php?added=1&month=' . date('n', strtotime($eventDate)) . '&year=' . date('Y', strtotime($eventDate)));
            exit;
        }
    }
}

// ---------- Handle: Delete manual event ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_event' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $stmt = $pdo->prepare("DELETE FROM calendar_events WHERE id = ? AND user_id = ? AND source = 'manual'");
    $stmt->execute([(int)($_POST['event_id'] ?? 0), $userId]);
    header('Location: calendar.php?deleted=1');
    exit;
}

if (isset($_GET['added'])) $success = "Event added.";
if (isset($_GET['deleted'])) $success = "Event deleted.";

// ---------- Determine which month to show ----------
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// clamp to valid month range, rolling into prev/next year if needed
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstDayTimestamp = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstDayTimestamp);
$firstWeekday = (int)date('N', $firstDayTimestamp); // 1 (Mon) - 7 (Sun)

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

// ---------- Load all recurring class schedules, grouped by day code ----------
$schedStmt = $pdo->prepare("SELECT * FROM schedules WHERE user_id = ? ORDER BY start_time ASC");
$schedStmt->execute([$userId]);
$schedulesByDay = ['MON'=>[],'TUE'=>[],'WED'=>[],'THU'=>[],'FRI'=>[],'SAT'=>[],'SUN'=>[]];
foreach ($schedStmt->fetchAll() as $s) {
    $schedulesByDay[$s['day_of_week']][] = $s;
}

// ---------- Load manual/budget calendar events for this month ----------
$eventStmt = $pdo->prepare("SELECT * FROM calendar_events WHERE user_id = ? AND MONTH(event_date) = ? AND YEAR(event_date) = ? ORDER BY event_time ASC");
$eventStmt->execute([$userId, $month, $year]);
$eventsByDate = [];
foreach ($eventStmt->fetchAll() as $e) {
    $eventsByDate[$e['event_date']][] = $e;
}

$dayCodeMap = [1=>'MON', 2=>'TUE', 3=>'WED', 4=>'THU', 5=>'FRI', 6=>'SAT', 7=>'SUN'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Calendar - StudySys</title>
<link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body>
<div class="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="main">
    <header>
      <div>
        <h1>Calendar</h1>
        <p></p>
      </div>
    </header>

    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="grid-cards" style="grid-template-columns: 2.4fr 1fr; align-items:start;">

      <!-- Calendar grid -->
      <div class="panel-block">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
          <a href="calendar.php?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn" style="width:auto; padding:8px 14px; background:var(--panel-light); color:var(--text);">&larr;</a>
          <h2 style="margin:0;"><?= date('F Y', $firstDayTimestamp) ?></h2>
          <a href="calendar.php?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn" style="width:auto; padding:8px 14px; background:var(--panel-light); color:var(--text);">&rarr;</a>
        </div>

        <div class="cal-grid">
          <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dLabel): ?>
            <div class="cal-head"><?= $dLabel ?></div>
          <?php endforeach; ?>

          <?php
          // leading blanks before day 1
          for ($i = 1; $i < $firstWeekday; $i++): ?>
            <div class="cal-cell cal-empty"></div>
          <?php endfor; ?>

          <?php for ($d = 1; $d <= $daysInMonth; $d++):
              $cellTimestamp = mktime(0,0,0,$month,$d,$year);
              $weekdayNum = (int)date('N', $cellTimestamp);
              $dayCode = $dayCodeMap[$weekdayNum];
              $dateStr = date('Y-m-d', $cellTimestamp);
              $isToday = $dateStr === date('Y-m-d');
              $classesToday = $schedulesByDay[$dayCode];
              $eventsToday = $eventsByDate[$dateStr] ?? [];
          ?>
            <div class="cal-cell <?= $isToday ? 'cal-today' : '' ?>">
              <div class="cal-daynum"><?= $d ?></div>
              <?php foreach ($classesToday as $c): ?>
                <div class="cal-chip cal-chip-class" title="<?= htmlspecialchars($c['subject']) ?>">
                  <?= date('g:i A', strtotime($c['start_time'])) ?> <?= htmlspecialchars($c['subject']) ?>
                </div>
              <?php endforeach; ?>
              <?php foreach ($eventsToday as $e): ?>
                <div class="cal-chip cal-chip-event" title="<?= htmlspecialchars($e['title']) ?>">
                  <?= htmlspecialchars($e['title']) ?>
                   <form method="POST" style="display:inline; float:right;">
                     <input type="hidden" name="action" value="delete_event">
                     <input type="hidden" name="event_id" value="<?= $e['id'] ?>">
                     <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                     <button type="submit" onclick="return confirm('Delete this event?');" style="color:inherit; background:none; border:0; padding:0; opacity:0.7;">✕</button>
                   </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Add manual event -->
      <div class="panel-block">
        <h2>Add Event</h2>
        <form method="POST">
          <input type="hidden" name="action" value="add_event">
          <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
          <div class="field">
            <label>Title</label>
            <input type="text" name="title" placeholder="e.g. Project deadline" required>
          </div>
          <div class="field">
            <label>Date</label>
            <input type="date" name="event_date" required>
          </div>
          <div class="field">
            <label>Time (optional)</label>
            <input type="time" name="event_time">
          </div>
          <div class="field">
            <label>Notes (optional)</label>
            <input type="text" name="description" placeholder="e.g. Submit via portal">
          </div>
          <button type="submit" class="btn">
            <span class="spinner"></span>
            <span class="btn-label">Add Event</span>
          </button>
        </form>

        <hr style="border-color:var(--border); margin:20px 0;">
        <div style="font-size:12px; color:var(--text-dim); line-height:1.6;">
          <span class="cal-chip cal-chip-class" style="display:inline-block; margin-bottom:4px;">Class</span> — from your Schedule, shown automatically every week<br>
          <span class="cal-chip cal-chip-event" style="display:inline-block; margin-top:4px;">Event</span> — added manually here
        </div>
      </div>

    </div>
  </div>
</div>
<script src="assets/js/main.js?v=3"></script>
</body>
</html>
