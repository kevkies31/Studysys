<?php
// Expects $activePage to be set by the including page (e.g. 'dashboard', 'budget', 'schedule', 'calendar')
$activePage = $activePage ?? '';
?>
<div class="sidebar">
  <div class="brand">Study<span>Sys</span></div>
  <nav>
    <a href="dashboard.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
    <a href="budget.php" class="<?= $activePage === 'budget' ? 'active' : '' ?>">Budget Tracker</a>
    <a href="schedule.php" class="<?= $activePage === 'schedule' ? 'active' : '' ?>">Schedule</a>
    <a href="calendar.php" class="<?= $activePage === 'calendar' ? 'active' : '' ?>">Calendar</a>
  </nav>
  <div class="logout">
    <a href="logout.php">&larr; Log out</a>
  </div>
</div>
