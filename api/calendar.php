<?php
require_once __DIR__ . '/bootstrap.php';
$user = api_require_user($pdo);
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $month = filter_var($_GET['month'] ?? date('n'), FILTER_VALIDATE_INT);
    $year = filter_var($_GET['year'] ?? date('Y'), FILTER_VALIDATE_INT);
    if (!$month || $month < 1 || $month > 12 || !$year || $year < 2000 || $year > 2100) {
        api_error('Invalid month or year.', 422);
    }
    $stmt = $pdo->prepare(
        'SELECT id, source, title, event_date, event_time, description FROM calendar_events
         WHERE user_id = ? AND YEAR(event_date) = ? AND MONTH(event_date) = ?
         ORDER BY event_date, event_time'
    );
    $stmt->execute([$userId, $year, $month]);
    $items = $stmt->fetchAll();
    $scheduleStmt = $pdo->prepare('SELECT id, subject, day_of_week, start_time, end_time, room FROM schedules WHERE user_id = ?');
    $scheduleStmt->execute([$userId]);
    $schedulesByDay = [];
    foreach ($scheduleStmt->fetchAll() as $schedule) {
        $schedulesByDay[$schedule['day_of_week']][] = $schedule;
    }
    $daysInMonth = (int)date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $dayCode = strtoupper(date('D', strtotime($date)));
        foreach ($schedulesByDay[$dayCode] ?? [] as $schedule) {
            $items[] = [
                'id' => 'schedule-' . $schedule['id'] . '-' . $date,
                'source' => 'schedule',
                'title' => $schedule['subject'],
                'event_date' => $date,
                'event_time' => $schedule['start_time'],
                'description' => 'Class · ' . substr($schedule['start_time'], 0, 5) . '–' .
                    substr($schedule['end_time'], 0, 5) .
                    ($schedule['room'] ? ' · ' . $schedule['room'] : ''),
            ];
        }
    }
    usort($items, static function (array $first, array $second): int {
        $dateOrder = strcmp($first['event_date'], $second['event_date']);
        return $dateOrder !== 0 ? $dateOrder : strcmp((string)$first['event_time'], (string)$second['event_time']);
    });
    api_response($items);
}

api_method('POST');
$input = api_input();
$action = $input['action'] ?? '';
if ($action === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM calendar_events WHERE id = ? AND user_id = ? AND source = 'manual'");
    $stmt->execute([(int)($input['id'] ?? 0), $userId]);
    if ($stmt->rowCount() === 0) {
        api_error('Manual event not found.', 404);
    }
    api_response(['message' => 'Event deleted.']);
}
if ($action !== 'save') {
    api_error('Unknown calendar action.', 400);
}

$title = trim((string)($input['title'] ?? ''));
$date = (string)($input['event_date'] ?? '');
$time = trim((string)($input['event_time'] ?? ''));
if ($title === '' || !api_valid_date($date) ||
($time !== '' && !api_valid_time($time)) || strlen($title) > 150 ||
strlen((string)($input['description'] ?? '')) > 255) {
    api_error('Enter an event title, valid date, and optional valid time.', 422);
}
$stmt = $pdo->prepare(
    "INSERT INTO calendar_events (user_id, source, title, event_date, event_time, description)
     VALUES (?, 'manual', ?, ?, ?, ?)"
);
$stmt->execute([$userId, $title, $date, $time === '' ? null : $time, trim((string)($input['description'] ?? ''))]);
api_response(['message' => 'Event saved.'], 201);
