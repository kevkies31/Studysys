<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$activePage = 'budget';
$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// ---------- Handle: Add Transaction ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_transaction') {
    $amount = trim($_POST['amount'] ?? '');
    $type = $_POST['type'] ?? '';
    $categoryId = $_POST['category_id'] ?: null;
    $note = trim($_POST['note'] ?? '');
    $date = $_POST['txn_date'] ?? date('Y-m-d');

    if (!is_numeric($amount) || $amount <= 0) {
        $error = "Enter a valid amount greater than 0.";
    } elseif (!in_array($type, ['income', 'expense'])) {
        $error = "Invalid transaction type.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, category_id, amount, type, note, txn_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $categoryId, $amount, $type, $note, $date]);
        header('Location: budget.php?added=1');
        exit;
    }
}

// ---------- Handle: Add Category ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $name = trim($_POST['cat_name'] ?? '');
    $limit = $_POST['cat_limit'] ?: 0;

    if ($name === '') {
        $error = "Category name is required.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO budget_categories (user_id, name, monthly_limit) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $name, $limit]);
        header('Location: budget.php?cat_added=1');
        exit;
    }
}

/// ---------- Handle: Delete Transaction ----------
if (isset($_GET['delete'])) {
    // Save the transaction before deleting so we can offer Undo
    $lookup = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
    $lookup->execute([(int)$_GET['delete'], $userId]);
    $toDelete = $lookup->fetch();

    if ($toDelete) {
        $_SESSION['last_deleted_txn'] = $toDelete;
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
        $stmt->execute([(int)$_GET['delete'], $userId]);
    }
    header('Location: budget.php?deleted=1');
    exit;
}

// ---------- Handle: Undo Delete Transaction ----------
if (isset($_GET['undo']) && isset($_SESSION['last_deleted_txn'])) {
    $t = $_SESSION['last_deleted_txn'];
    if ($t['user_id'] == $userId) {
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, category_id, amount, type, note, txn_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$t['user_id'], $t['category_id'], $t['amount'], $t['type'], $t['note'], $t['txn_date']]);
    }
    unset($_SESSION['last_deleted_txn']);
    header('Location: budget.php?restored=1');
    exit;
}

// ---------- Handle: Delete Category ----------
if (isset($_GET['delete_cat'])) {
    $stmt = $pdo->prepare("DELETE FROM budget_categories WHERE id = ? AND user_id = ?");
    $stmt->execute([(int)$_GET['delete_cat'], $userId]);
    header('Location: budget.php?cat_deleted=1');
    exit;
}

if (isset($_GET['added'])) $success = "Transaction added.";
if (isset($_GET['restored'])) $success = "Transaction restored.";
if (isset($_GET['cat_added'])) $success = "Category added.";
if (isset($_GET['cat_deleted'])) $success = "Category deleted. Its transactions are kept, just marked uncategorized.";
$showUndo = isset($_GET['deleted']) && isset($_SESSION['last_deleted_txn']);
if (isset($_GET['deleted'])) $success = "Transaction deleted.";

// ---------- Fetch data ----------
$categories = $pdo->prepare("SELECT * FROM budget_categories WHERE user_id = ? ORDER BY name ASC");
$categories->execute([$userId]);
$categories = $categories->fetchAll();

$txnStmt = $pdo->prepare("
    SELECT t.*, c.name AS category_name
    FROM transactions t
    LEFT JOIN budget_categories c ON t.category_id = c.id
    WHERE t.user_id = ?
    ORDER BY t.txn_date DESC, t.id DESC
    LIMIT 50
");
$txnStmt->execute([$userId]);
$transactions = $txnStmt->fetchAll();

// This month totals
$incomeStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM transactions WHERE user_id = ? AND type = 'income' AND MONTH(txn_date) = MONTH(CURDATE()) AND YEAR(txn_date) = YEAR(CURDATE())");
$incomeStmt->execute([$userId]);
$income = $incomeStmt->fetch()['total'];

$expenseStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM transactions WHERE user_id = ? AND type = 'expense' AND MONTH(txn_date) = MONTH(CURDATE()) AND YEAR(txn_date) = YEAR(CURDATE())");
$expenseStmt->execute([$userId]);
$expense = $expenseStmt->fetch()['total'];

$balance = $income - $expense;

// Spending per category (this month, expenses only)
$catSpendStmt = $pdo->prepare("
    SELECT c.id, c.name, c.monthly_limit, COALESCE(SUM(t.amount),0) AS spent
    FROM budget_categories c
    LEFT JOIN transactions t ON t.category_id = c.id AND t.type = 'expense'
        AND MONTH(t.txn_date) = MONTH(CURDATE()) AND YEAR(t.txn_date) = YEAR(CURDATE())
    WHERE c.user_id = ?
    GROUP BY c.id, c.name, c.monthly_limit
    ORDER BY spent DESC
");
$catSpendStmt->execute([$userId]);
$categorySpend = $catSpendStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Budget - StudySys</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="main">
    <header>
      <div>
        <h1>Budget Tracker</h1>
        <p><?= date('F Y') ?> overview</p>
      </div>
    </header>

    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success">
    <?= htmlspecialchars($success) ?>
    <?php if ($showUndo): ?>
      <a href="budget.php?undo=1" style="color:#86efac; font-weight:700; text-decoration:underline; margin-left:6px;">Undo</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

    <div class="grid-cards">
      <div class="card">
        <div class="label">Income this month</div>
        <div class="value success">₱<?= number_format($income, 2) ?></div>
      </div>
      <div class="card">
        <div class="label">Expenses this month</div>
        <div class="value danger">₱<?= number_format($expense, 2) ?></div>
      </div>
      <div class="card">
        <div class="label">Balance</div>
        <div class="value"><?= $balance < 0 ? '-' : '' ?>₱<?= number_format(abs($balance), 2) ?></div>
      </div>
    </div>

    <div class="grid-cards" style="grid-template-columns: 1fr 1.4fr; align-items:start;">

      <!-- Add Transaction -->
      <div class="panel-block">
        <h2>Add Transaction</h2>
        <form method="POST">
          <input type="hidden" name="action" value="add_transaction">

          <div class="field">
            <label>Type</label>
            <select name="type" required>
              <option value="expense">Expense</option>
              <option value="income">Income</option>
            </select>
          </div>

          <div class="field">
            <label>Amount (₱)</label>
            <input type="number" step="0.01" min="0.01" name="amount" required>
          </div>

          <div class="field">
            <label>Category</label>
            <select name="category_id">
              <option value="">— None —</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Note</label>
            <input type="text" name="note" placeholder="e.g. Jeepney fare">
          </div>

          <div class="field">
            <label>Date</label>
            <input type="date" name="txn_date" value="<?= date('Y-m-d') ?>" required>
          </div>

          <button type="submit" class="btn">
            <span class="spinner"></span>
            <span class="btn-label">Add Transaction</span>
          </button>
        </form>

        <hr style="border-color:var(--border); margin:24px 0;">

        <h2>Add Category</h2>
        <form method="POST">
          <input type="hidden" name="action" value="add_category">
          <div class="field">
            <label>Category Name</label>
            <input type="text" name="cat_name" placeholder="e.g. Print/Photocopy" required>
          </div>
          <div class="field">
            <label>Monthly Limit (₱, optional)</label>
            <input type="number" step="0.01" min="0" name="cat_limit" placeholder="0">
          </div>
          <button type="submit" class="btn">
            <span class="spinner"></span>
            <span class="btn-label">Add Category</span>
          </button>
        </form>
      </div>

      <!-- Right column: category breakdown + transaction list -->
      <div>
        <div class="panel-block" style="margin-bottom:16px;">
          <h2>Spending by Category</h2>
          <?php if (empty($categorySpend)): ?>
            <div class="empty-state">No categories yet.</div>
          <?php else: ?>
            <?php foreach ($categorySpend as $cs):
                $pct = $cs['monthly_limit'] > 0 ? min(100, ($cs['spent'] / $cs['monthly_limit']) * 100) : 0;
                $over = $cs['monthly_limit'] > 0 && $cs['spent'] > $cs['monthly_limit'];
            ?>
              <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:5px;">
  <span>
    <?= htmlspecialchars($cs['name']) ?>
    <a href="budget.php?delete_cat=<?= $cs['id'] ?>"
       class="del-link"
       style="margin-left:4px;"
       onclick="return confirm('Delete category \'<?= htmlspecialchars($cs['name'], ENT_QUOTES) ?>\'? Its transactions will stay but become uncategorized.');">✕</a>
  </span>
  <span style="color:<?= $over ? 'var(--danger)' : 'var(--text-dim)' ?>"></span>
                <?php if ($cs['monthly_limit'] > 0): ?>
                <div class="bar-track">
                  <div class="bar-fill <?= $over ? 'bar-over' : '' ?>" style="width: <?= $pct ?>%;"></div>
                </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="panel-block">
          <h2>Recent Transactions</h2>
          <?php if (empty($transactions)): ?>
            <div class="empty-state">No transactions yet. Add your first one on the left.</div>
          <?php else: ?>
            <table class="txn-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Note / Category</th>
                  <th>Amount</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($transactions as $t): ?>
                  <tr>
                    <td><?= date('M j', strtotime($t['txn_date'])) ?></td>
                    <td>
                      <?= htmlspecialchars($t['note'] ?: ($t['category_name'] ?? '—')) ?>
                      <?php if ($t['category_name'] && $t['note']): ?>
                        <div style="color:var(--text-dim); font-size:12px;"><?= htmlspecialchars($t['category_name']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="<?= $t['type'] === 'income' ? 'amt-income' : 'amt-expense' ?>">
                      <?= $t['type'] === 'income' ? '+' : '-' ?>₱<?= number_format($t['amount'], 2) ?>
                    </td>
                    <td>
                      <a href="budget.php?delete=<?= $t['id'] ?>"
                         class="del-link"
                         onclick="return confirm('Delete this transaction?');">✕</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
