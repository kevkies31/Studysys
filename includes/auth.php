<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Registers a new user. Returns true on success, or an error string on failure.
 */
function registerUser(PDO $pdo, string $name, string $email, string $password) {
    $name = trim($name);
    $email = trim(strtolower($email));

    if ($name === '' || $email === '' || $password === '') {
        return "All fields are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Please enter a valid email.";
    }
    if (strlen($password) < 6) {
        return "Password must be at least 6 characters.";
    }

    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        return "An account with that email already exists.";
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
    $stmt->execute([$name, $email, $hash]);

    // Seed a couple of default budget categories for the new user
    $userId = $pdo->lastInsertId();
    $defaults = [
        ['Food', 'utensils'],
        ['Transportation', 'bus'],
        ['School Supplies', 'book'],
        ['Load/Internet', 'wifi'],
    ];
    $catStmt = $pdo->prepare("INSERT INTO budget_categories (user_id, name, icon) VALUES (?, ?, ?)");
    foreach ($defaults as $d) {
        $catStmt->execute([$userId, $d[0], $d[1]]);
    }

    return true;
}

/**
 * Logs a user in. Returns true on success, or an error string on failure.
 */
function loginUser(PDO $pdo, string $email, string $password) {
    $email = trim(strtolower($email));

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return "Incorrect email or password.";
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];

    return true;
}

function logoutUser() {
    $_SESSION = [];
    session_destroy();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Call at the top of any page that requires login.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}
