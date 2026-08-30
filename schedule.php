<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$activePage = 'schedule';
$userId = $_SESSION['user_id'];
$error = '';
$success = '';

$days = ['MON' => 'Monday', 'TUE' => 'Tuesday', 'WED' => 'Wednesday', 'THU' => 'Thursday', 'FRI' => 'Friday', 'SAT' => 'Saturday', 'SUN' => 'Sunday'];

// ---------- Handle: Add or Update Schedule ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_schedule') {
    $subject = trim($_POST['subject'] ?? '');
    $day = $_POST['day_of_week'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endTime = $_POST['end_time'] ?? '';
    $room = trim($_POST['room'] ?? '');
    $faculty = trim($_POST['faculty'] ?? '');
    $editId = $_POST['edit_id'] ?? '';

    if ($subject === '') {
        $error = "Subject name is required.";
    } elseif (!array_key_exists($day, $days)) {
        $error = "Please select a valid day.";
    } elseif ($startTime === '' || $endTime === '') {
        $error = "Start and end time are required.";
    } elseif ($startTime >= $endTime) {
        $error = "End time must be after start time.";
    } else {
        if ($editId !== '') {
            // Update existing
            $stmt = $pdo->prepare("UPDATE schedules SET subject=?, day_of_week=?, start_time=?, end_time=?, room=?, faculty=? WHERE id=? AND user_id=?");
            $stmt->execute([$subject, $day, $startTime, $endTime, $room, $faculty, (int)$editId, $userId]);
            header('Location: schedule.php?updated=1');
        } else {
            // Insert new
            $stmt = $pdo->prepare("INSERT INTO schedules (user_id, subject, day_of_week, start_time, end_time, room, faculty, source) VALUES (?, ?, ?, ?, ?, ?, ?, 'manual')");
            $stmt->execute([$userId, $subject, $day, $startTime, $endTime, $room, $faculty]);
            header('Location: schedule.php?added=1');
        }
        exit;
    }
}

// ---------- Handle: Delete ----------
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM schedules WHERE id = ? AND user_id = ?");
    $stmt->execute([(int)$_GET['delete'], $userId]);
    header('Location: schedule.php?deleted=1');
    exit;
}

if (isset($_GET['added'])) $success = "Class added.";
if (isset($_GET['updated'])) $success = "Class updated.";
if (isset($_GET['deleted'])) $success = "Class deleted.";
if (isset($_GET['scanned'])) $success = (int)$_GET['scanned'] . " class(es) saved from scan.";


// ---------- If editing, load that row to prefill the form ----------
$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM schedules WHERE id = ? AND user_id = ?");
    $stmt->execute([(int)$_GET['edit'], $userId]);
    $editRow = $stmt->fetch();
}

// ---------- Fetch all schedules, grouped by day ----------
$stmt = $pdo->prepare("SELECT * FROM schedules WHERE user_id = ? ORDER BY FIELD(day_of_week,'MON','TUE','WED','THU','FRI','SAT','SUN'), start_time ASC");
$stmt->execute([$userId]);
$allSchedules = $stmt->fetchAll();

$grouped = [];
foreach ($days as $code => $label) {
    $grouped[$code] = [];
}
foreach ($allSchedules as $s) {
    $grouped[$s['day_of_week']][] = $s;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Schedule - StudySys</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="main">
    <header>
      <div>
        <h1>Schedule</h1>
        <p>Your weekly class schedule</p>
      </div>
    </header>

    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="grid-cards" style="grid-template-columns: 1fr 1.6fr; align-items:start;">

      <!-- Add / Edit form -->
      <div class="panel-block">
        <h2 style="display:flex; justify-content:space-between; align-items:center;">
  <?= $editRow ? 'Edit Class' : 'Add Class' ?>
  <a href="scan_schedule.php" style="font-size:15px; color:var(--accent); font-weight:600;"> Scan COR</a>
</h2>
        <form method="POST">
          <input type="hidden" name="action" value="save_schedule">
          <input type="hidden" name="edit_id" value="<?= $editRow ? $editRow['id'] : '' ?>">

          <div class="field">
            <label>Subject</label>
            <input type="text" name="subject" placeholder="e.g. IT2222 - Information Systems"
                   value="<?= $editRow ? htmlspecialchars($editRow['subject']) : '' ?>" required>
          </div>

          <div class="field">
            <label>Day</label>
            <select name="day_of_week" required>
              <option value="">— Select day —</option>
              <?php foreach ($days as $code => $label): ?>
                <option value="<?= $code ?>" <?= ($editRow && $editRow['day_of_week'] === $code) ? 'selected' : '' ?>>
                  <?= $label ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Start Time</label>
            <input type="time" name="start_time" value="<?= $editRow ? substr($editRow['start_time'],0,5) : '' ?>" required>
          </div>

          <div class="field">
            <label>End Time</label>
            <input type="time" name="end_time" value="<?= $editRow ? substr($editRow['end_time'],0,5) : '' ?>" required>
          </div>

          <div class="field">
            <label>Room</label>
            <input type="text" name="room" placeholder="e.g. Rm 305"
                   value="<?= $editRow ? htmlspecialchars($editRow['room']) : '' ?>">
          </div>

          <div class="field">
            <label>Faculty</label>
            <input type="text" name="faculty" placeholder="e.g. Prof. Santos"
                   value="<?= $editRow ? htmlspecialchars($editRow['faculty']) : '' ?>">
          </div>

          <button type="submit" class="btn">
            <span class="spinner"></span>
            <span class="btn-label"><?= $editRow ? 'Update Class' : 'Add Class' ?></span>
          </button>

          <?php if ($editRow): ?>
            <a href="schedule.php" class="btn" style="background:var(--panel-light); color:var(--text-dim); margin-top:8px;">Cancel Edit</a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Weekly view -->
      <div class="panel-block">
        <h2>Weekly Schedule</h2>
        <?php if (empty($allSchedules)): ?>
          <div class="empty-state">No classes added yet. Use the form to add your first class.</div>
        <?php else: ?>
          <?php foreach ($days as $code => $label): ?>
            <?php if (empty($grouped[$code])) continue; ?>
            <div style="margin-bottom:18px;">
              <div style="font-size:13px; font-weight:700; color:var(--accent); text-transform:uppercase; letter-spacing:0.04em; margin-bottom:8px;">
                <?= $label ?>
              </div>
              <?php foreach ($grouped[$code] as $s): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border);">
                  <div>
                    <strong><?= htmlspecialchars($s['subject']) ?></strong><br>
                    <span style="color:var(--text-dim); font-size:12px;">
                      <?= date('g:i A', strtotime($s['start_time'])) ?> - <?= date('g:i A', strtotime($s['end_time'])) ?>
                      <?php if ($s['room']): ?> · <?= htmlspecialchars($s['room']) ?><?php endif; ?>
                      <?php if ($s['faculty']): ?> · <?= htmlspecialchars($s['faculty']) ?><?php endif; ?>
                    </span>
                  </div>
                  <div style="white-space:nowrap;">
                    <a href="schedule.php?edit=<?= $s['id'] ?>" class="del-link" style="color:var(--accent);">Edit</a>
                    <a href="schedule.php?delete=<?= $s['id'] ?>" class="del-link"
                       onclick="return confirm('Delete this class?');">✕</a>
                  </div>
                </div>
              <?php endforeach; ?>
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