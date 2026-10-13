<?php
require_once __DIR__ . '/bootstrap.php';
$user = api_require_user($pdo);
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $categoryStmt = $pdo->prepare(
        "SELECT c.id, c.name, c.icon, c.monthly_limit,
         COALESCE(SUM(CASE WHEN t.type = 'expense'
             AND YEAR(t.txn_date) = YEAR(CURDATE()) AND MONTH(t.txn_date) = MONTH(CURDATE())
             THEN t.amount ELSE 0 END), 0) AS spent
         FROM budget_categories c
         LEFT JOIN transactions t ON t.category_id = c.id AND t.user_id = c.user_id
         WHERE c.user_id = ? GROUP BY c.id, c.name, c.icon, c.monthly_limit ORDER BY c.name"
    );
    $categoryStmt->execute([$userId]);
    $transactionStmt = $pdo->prepare(
        'SELECT t.id, t.category_id, c.name AS category_name, t.amount, t.type, t.note, t.txn_date
         FROM transactions t LEFT JOIN budget_categories c ON c.id = t.category_id
         WHERE t.user_id = ? ORDER BY t.txn_date DESC, t.id DESC LIMIT 50'
    );
    $transactionStmt->execute([$userId]);
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
    api_response([
        'categories' => $categoryStmt->fetchAll(),
        'transactions' => $transactionStmt->fetchAll(),
        'monthly_income' => (float)$incomeStmt->fetchColumn(),
        'monthly_expense' => (float)$expenseStmt->fetchColumn(),
    ]);
}

api_method('POST');
$input = api_input();
$action = $input['action'] ?? '';

if ($action === 'save_transaction') {
    $amount = filter_var($input['amount'] ?? null, FILTER_VALIDATE_FLOAT);
    $type = $input['type'] ?? '';
    $date = $input['txn_date'] ?? '';
    $categoryId = isset($input['category_id']) && $input['category_id'] !== '' ? (int)$input['category_id'] : null;
    if ($amount === false || $amount <= 0 || !in_array($type, ['income', 'expense'], true) ||
        !is_string($date) || !api_valid_date($date) || strlen((string)($input['note'] ?? '')) > 255) {
        api_error('Enter a valid amount, type, and date.', 422);
    }
    if ($categoryId !== null) {
        $check = $pdo->prepare('SELECT id FROM budget_categories WHERE id = ? AND user_id = ?');
        $check->execute([$categoryId, $userId]);
        if (!$check->fetch()) {
            api_error('Invalid category.', 422);
        }
    }
    $id = (int)($input['id'] ?? 0);
    if ($id > 0) {
        $check = $pdo->prepare('SELECT id FROM transactions WHERE id = ? AND user_id = ?');
        $check->execute([$id, $userId]);
        if (!$check->fetch()) {
            api_error('Transaction not found.', 404);
        }
        $stmt = $pdo->prepare(
            'UPDATE transactions SET category_id = ?, amount = ?, type = ?, note = ?, txn_date = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$categoryId, $amount, $type, trim((string)($input['note'] ?? '')), $date, $id, $userId]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO transactions (user_id, category_id, amount, type, note, txn_date) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $categoryId, $amount, $type, trim((string)($input['note'] ?? '')), $date]);
    }
    api_response(['message' => 'Transaction saved.'], $id > 0 ? 200 : 201);
}

if ($action === 'save_category') {
    $name = trim((string)($input['name'] ?? ''));
    $limit = filter_var($input['monthly_limit'] ?? 0, FILTER_VALIDATE_FLOAT);
    if ($name === '' || strlen($name) > 100 || $limit === false || $limit < 0) {
        api_error('Enter a category name and a non-negative monthly limit.', 422);
    }
    $stmt = $pdo->prepare('INSERT INTO budget_categories (user_id, name, monthly_limit) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $name, $limit]);
    api_response(['message' => 'Category saved.'], 201);
}

if ($action === 'delete_transaction' || $action === 'delete_category') {
    $table = $action === 'delete_transaction' ? 'transactions' : 'budget_categories';
    $id = (int)($input['id'] ?? 0);
    if ($id < 1) {
        api_error('A valid id is required.', 422);
    }
    $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    if ($stmt->rowCount() === 0) {
        api_error('Record not found.', 404);
    }
    api_response(['message' => 'Record deleted.']);
}

api_error('Unknown budget action.', 400);
