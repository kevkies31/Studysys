<?php
require_once __DIR__ . '/bootstrap.php';
api_method('GET');
$user = api_require_user($pdo);
$userId = (int)$user['id'];

$incomeStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM transactions
     WHERE user_id = ? AND type = 'income' AND YEAR(txn_date) = YEAR(CURDATE()) AND MONTH(txn_date) = MONTH(CURDATE())"
);
$incomeStmt->execute([$userId]);
$expenseStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM transactions
     WHERE user_id = ? AND type = 'expense' AND YEAR(txn_date) = YEAR(CURDATE()) AND MONTH(txn_date) = MONTH(CURDATE())"
);
$expenseStmt->execute([$userId]);
$scheduleStmt = $pdo->prepare('SELECT * FROM schedules WHERE user_id = ? AND day_of_week = ? ORDER BY start_time');
$scheduleStmt->execute([$userId, strtoupper(date('D'))]);
$eventsStmt = $pdo->prepare(
    'SELECT id, title, event_date, event_time, description
     FROM calendar_events WHERE user_id = ? AND event_date >= CURDATE()
     ORDER BY event_date, event_time LIMIT 5'
);
$eventsStmt->execute([$userId]);

api_response([
    'user' => ['id' => $userId, 'name' => $user['name'], 'email' => $user['email']],
    'income' => (float)$incomeStmt->fetchColumn(),
    'expense' => (float)$expenseStmt->fetchColumn(),
    'today_schedule' => $scheduleStmt->fetchAll(),
    'upcoming_events' => $eventsStmt->fetchAll(),
]);
