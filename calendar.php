<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$activePage = 'calendar';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Calendar - StudySys</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main">
    <header>
      <div>
        <h1>Calendar</h1>
        <p>Pulls from both your schedule and budget due-dates.</p>
      </div>
    </header>
    <div class="panel-block">
      <div class="empty-state">Calendar view not built yet. This is step 4 in our plan.</div>
    </div>
  </div>
</div>
  <script src="assets/js/main.js"></script>
</body>
</html>
