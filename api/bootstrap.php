<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_response($data = null, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['data' => $data]);
    exit;
}

function api_error(string $message, int $status): void
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

function api_input(): array
{
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

function api_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        api_error('Method not allowed.', 405);
    }
}

function api_valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function api_valid_time(string $value): bool
{
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
}

function api_require_user(PDO $pdo): array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authorization = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';
    $appToken = $_SERVER['HTTP_X_STUDYSYS_TOKEN'] ?? '';
    foreach ($headers as $name => $value) {
        if ($authorization === '' && strcasecmp($name, 'Authorization') === 0) {
                $authorization = $value;
        } elseif ($appToken === '' && strcasecmp($name, 'X-StudySys-Token') === 0) {
            $appToken = $value;
        }
    }
    if (preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $authorization, $matches)) {
        $token = $matches[1];
    } elseif (preg_match('/^[a-f0-9]{64}$/i', $appToken, $matches)) {
        $token = $matches[0];
    } else {
        api_error('Authentication required.', 401);
    }

    $tokenHash = hash('sha256', strtolower($token));
    $stmt = $pdo->prepare(
        'SELECT u.id, u.name, u.email, t.id AS token_id
         FROM api_tokens t JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = ? AND t.expires_at > CURRENT_TIMESTAMP'
    );
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch();
    if (!$user) {
        api_error('Session expired. Please sign in again.', 401);
    }
    return $user;
}

function api_issue_token(PDO $pdo, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare(
        'INSERT INTO api_tokens (user_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY))'
    );
    $stmt->execute([$userId, hash('sha256', $token)]);
    return $token;
}
