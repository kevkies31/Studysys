<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = api_require_user($pdo);
    unset($user['token_id']);
    api_response(['user' => $user]);
}

api_method('POST');
$input = api_input();
$action = $input['action'] ?? '';

if ($action === 'logout') {
    $user = api_require_user($pdo);
    $stmt = $pdo->prepare('DELETE FROM api_tokens WHERE id = ?');
    $stmt->execute([$user['token_id']]);
    api_response(['message' => 'Signed out.']);
}

if ($action === 'register') {
    $name = trim((string)($input['name'] ?? ''));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $password = (string)($input['password'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        api_error('Enter a name, valid email, and password of at least 6 characters.', 422);
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        api_error('An account with that email already exists.', 409);
    }

    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int)$pdo->lastInsertId();
    $categoryStmt = $pdo->prepare('INSERT INTO budget_categories (user_id, name, icon) VALUES (?, ?, ?)');
    foreach ([['Food', 'utensils'], ['Transportation', 'bus'], ['School Supplies', 'book'], ['Load/Internet', 'wifi']] as $category) {
        $categoryStmt->execute([$userId, $category[0], $category[1]]);
    }
    api_response([
        'token' => api_issue_token($pdo, $userId),
        'user' => ['id' => $userId, 'name' => $name, 'email' => $email],
    ], 201);
}

if ($action === 'login') {
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !password_verify((string)($input['password'] ?? ''), $user['password_hash'])) {
        api_error('Incorrect email or password.', 401);
    }
    api_response([
        'token' => api_issue_token($pdo, (int)$user['id']),
        'user' => ['id' => (int)$user['id'], 'name' => $user['name'], 'email' => $user['email']],
    ]);
}

api_error('Unknown authentication action.', 400);
