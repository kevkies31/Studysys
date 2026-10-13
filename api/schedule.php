<?php
require_once __DIR__ . '/bootstrap.php';
$user = api_require_user($pdo);
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT * FROM schedules WHERE user_id = ?
         ORDER BY FIELD(day_of_week, 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'), start_time"
    );
    $stmt->execute([$userId]);
    api_response($stmt->fetchAll());
}

api_method('POST');
$input = api_input();
$action = $input['action'] ?? '';
if ($action === 'delete') {
    $stmt = $pdo->prepare('DELETE FROM schedules WHERE id = ? AND user_id = ?');
    $stmt->execute([(int)($input['id'] ?? 0), $userId]);
    if ($stmt->rowCount() === 0) {
        api_error('Class not found.', 404);
    }
    api_response(['message' => 'Class deleted.']);
}
if ($action !== 'save') {
    api_error('Unknown schedule action.', 400);
}

$subject = trim((string)($input['subject'] ?? ''));
$day = strtoupper((string)($input['day_of_week'] ?? ''));
$start = (string)($input['start_time'] ?? '');
$end = (string)($input['end_time'] ?? '');
$days = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
if ($subject === '' || !in_array($day, $days, true) ||
    !api_valid_time($start) || !api_valid_time($end) || $start >= $end ||
    strlen($subject) > 150 || strlen((string)($input['room'] ?? '')) > 50 ||
    strlen((string)($input['faculty'] ?? '')) > 100) {
    api_error('Enter a subject, valid day, and valid time range.', 422);
}
$id = (int)($input['id'] ?? 0);
if ($id > 0) {
    $check = $pdo->prepare('SELECT id FROM schedules WHERE id = ? AND user_id = ?');
    $check->execute([$id, $userId]);
    if (!$check->fetch()) {
        api_error('Class not found.', 404);
    }
}
$conflict = $pdo->prepare(
    'SELECT subject, start_time, end_time FROM schedules WHERE user_id = ? AND day_of_week = ? AND id != ?
     AND start_time < ? AND end_time > ? LIMIT 1'
);
$conflict->execute([$userId, $day, $id, $end, $start]);
$overlap = $conflict->fetch();
if ($overlap) {
    api_error('This class overlaps with ' . $overlap['subject'] . '.', 409);
}

if ($id > 0) {
    $stmt = $pdo->prepare(
        'UPDATE schedules SET subject = ?, day_of_week = ?, start_time = ?, end_time = ?, room = ?, faculty = ?
         WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$subject, $day, $start, $end, trim((string)($input['room'] ?? '')), trim((string)($input['faculty'] ?? '')), $id, $userId]);
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO schedules (user_id, subject, day_of_week, start_time, end_time, room, faculty, source)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'manual')"
    );
    $stmt->execute([$userId, $subject, $day, $start, $end, trim((string)($input['room'] ?? '')), trim((string)($input['faculty'] ?? ''))]);
}
api_response(['message' => 'Class saved.'], $id > 0 ? 200 : 201);
