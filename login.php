<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = loginUser($pdo, $_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($result === true) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = $result;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Log In - StudySys</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="auth-wrap">
    <div class="auth-card">
      <h1>Welcome back</h1>
      <p class="subtitle">Log in to continue.</p>

      <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn">
          <span class="spinner"></span>
          <span class="btn-label">Log In</span>
        </button>
      </form>

      <div class="switch-link">
        Don't have an account? <a href="signup.php">Sign up</a>
      </div>
    </div>
  </div>
  <script src="assets/js/main.js"></script>
</body>
</html>
