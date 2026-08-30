<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = registerUser($pdo, $_POST['name'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($result === true) {
        // Auto-login after signup
        loginUser($pdo, $_POST['email'], $_POST['password']);
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
<title>Sign Up - StudySys</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="auth-wrap">
    <div class="auth-card">
      <h1>Create your account</h1>
      <p class="subtitle">Track your budget & schedule in one place.</p>

      <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="field">
          <label>Full Name</label>
          <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" required minlength="6">
        </div>
        <button type="submit" class="btn">
          <span class="spinner"></span>
          <span class="btn-label">Sign Up</span>
        </button>
      </form>

      <div class="switch-link">
        Already have an account? <a href="login.php">Log in</a>
      </div>
    </div>
  </div>
  <script src="assets/js/main.js"></script>
</body>
</html>
