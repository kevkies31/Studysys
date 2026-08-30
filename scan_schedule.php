<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ocr.php';
requireLogin();
$activePage = 'schedule';
$userId = $_SESSION['user_id'];
$error = '';
$proposedRows = [];

// ---------- Step 1: Handle image upload + OCR ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_scan') {
    if (empty($_FILES['coe_image']['tmp_name'])) {
        $error = "Please choose an image to upload.";
    } else {
        $uploadDir = __DIR__ . '/uploads/schedules/';
        $rawPath = $uploadDir . $userId . '_' . time() . '_raw.png';
        $processedPath = $uploadDir . $userId . '_' . time() . '_processed.png';

        move_uploaded_file($_FILES['coe_image']['tmp_name'], $rawPath);

        try {
            preprocess_schedule_image($rawPath, $processedPath);
            $imgInfo = getimagesize($processedPath);
            $imgWidth = $imgInfo[0];

            $tsvText = run_tesseract_tsv($processedPath);
            $wordRows = parse_tsv($tsvText);
            $bucketedLines = group_into_lines($wordRows, $imgWidth);
            $subjectRows = extract_subject_rows($bucketedLines);

            // Flatten: one proposed schedule row per (subject, schedule entry)
            foreach ($subjectRows as $subj) {
                foreach ($subj['entries'] as $entry) {
                    $parsed = parse_schedule_text($entry['schedule_raw']);
                    $roomParsed = parse_room_text($entry['room_raw']);
                    $proposedRows[] = [
                        'subject_code' => $subj['subject_code'],
                        'subject_name' => $subj['subject_name'],
                        'faculty' => $subj['faculty'],
                        'day' => $parsed['day'],
                        'start_time' => $parsed['start_time'],
                        'end_time' => $parsed['end_time'],
                        'room' => $roomParsed['room'],
                    ];
                }
            }

            if (empty($proposedRows)) {
                $error = "Couldn't detect a schedule table in that image. Try a clearer, well-lit photo.";
            }
        } catch (Exception $e) {
            $error = "Scan failed: " . $e->getMessage();
        }
    }
}

// ---------- Step 2: Handle confirm & save ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_scanned') {
    $saved = 0;
    foreach ($_POST['rows'] as $row) {
        if (empty($row['include'])) continue; // skipped/unchecked row

        $subject = trim($row['subject'] ?? '');
        $day = $row['day'] ?? '';
        $start = $row['start_time'] ?? '';
        $end = $row['end_time'] ?? '';
        $room = trim($row['room'] ?? '');
        $faculty = trim($row['faculty'] ?? '');

        if ($subject === '' || $start === '' || $end === '' || !in_array($day, ['MON','TUE','WED','THU','FRI','SAT','SUN'])) {
            continue; // skip incomplete rows silently — user will notice missing ones and can add manually
        }

        $stmt = $pdo->prepare("INSERT INTO schedules (user_id, subject, day_of_week, start_time, end_time, room, faculty, source) VALUES (?, ?, ?, ?, ?, ?, ?, 'scanned')");
        $stmt->execute([$userId, $subject, $day, $start, $end, $room, $faculty]);
        $saved++;
    }
    header('Location: schedule.php?scanned=' . $saved);
    exit;
}

$days = ['MON'=>'Mon','TUE'=>'Tue','WED'=>'Wed','THU'=>'Thu','FRI'=>'Fri','SAT'=>'Sat','SUN'=>'Sun'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Scan Schedule - StudySys</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="main">
    <header>
      <div>
        <h1>Scan Schedule</h1>
        <p>Upload a photo of your COE and we'll extract your classes.</p>
      </div>
    </header>

    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (empty($proposedRows)): ?>
      <!-- Upload form -->
      <div class="panel-block" style="max-width:480px;">
        <h2>Upload COE Photo</h2>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload_scan">
          <div class="field">
            <label>Image file (JPG or PNG)</label>
            <input type="file" name="coe_image" accept="image/jpeg,image/png" required>
          </div>
          <button type="submit" class="btn">
            <span class="spinner"></span>
            <span class="btn-label">Scan Image</span>
          </button>
        </form>
        <p style="color:var(--text-dim); font-size:12px; margin-top:12px;">
          This may take a few seconds. A well-lit, straight-on photo of a printed schedule works best.
        </p>
      </div>

    <?php else: ?>
      <!-- Confirm/edit extracted rows before saving -->
      <div class="panel-block">
        <h2>Review Extracted Classes</h2>
        <p style="color:var(--text-dim); font-size:13px; margin-top:-8px; margin-bottom:16px;">
          Check the boxes for rows that look correct, fix any typos, then save. Uncheck or ignore anything wrong.
        </p>

        <form method="POST">
          <input type="hidden" name="action" value="save_scanned">
          <table class="txn-table">
            <thead>
              <tr>
                <th></th>
                <th>Subject</th>
                <th>Day</th>
                <th>Start</th>
                <th>End</th>
                <th>Room</th>
                <th>Faculty</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($proposedRows as $i => $row): ?>
                <tr>
                  <td><input type="checkbox" name="rows[<?= $i ?>][include]" value="1" checked></td>
                  <td>
                    <input type="text" name="rows[<?= $i ?>][subject]"
                           value="<?= htmlspecialchars($row['subject_code'] . ' - ' . $row['subject_name']) ?>"
                           style="width:220px;">
                  </td>
                  <td>
                    <select name="rows[<?= $i ?>][day]">
                      <option value="">—</option>
                      <?php foreach ($days as $code => $label): ?>
                        <option value="<?= $code ?>" <?= $row['day'] === $code ? 'selected' : '' ?>><?= $label ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td><input type="time" name="rows[<?= $i ?>][start_time]" value="<?= htmlspecialchars($row['start_time']) ?>" style="width:110px;"></td>
                  <td><input type="time" name="rows[<?= $i ?>][end_time]" value="<?= htmlspecialchars($row['end_time']) ?>" style="width:110px;"></td>
                  <td><input type="text" name="rows[<?= $i ?>][room]" value="<?= htmlspecialchars($row['room']) ?>" style="width:100px;"></td>
                  <td><input type="text" name="rows[<?= $i ?>][faculty]" value="<?= htmlspecialchars($row['faculty']) ?>" style="width:140px;"></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div style="margin-top:20px; display:flex; gap:10px;">
            <button type="submit" class="btn" style="width:auto; padding:11px 24px;">
              <span class="spinner"></span>
              <span class="btn-label">Save Checked Classes</span>
            </button>
            <a href="scan_schedule.php" class="btn" style="width:auto; padding:11px 24px; background:var(--panel-light); color:var(--text-dim);">Start Over</a>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>