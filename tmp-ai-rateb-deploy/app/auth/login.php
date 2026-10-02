<?php
require __DIR__ . '/../../config/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$message = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $message = 'invalid_login';
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        header('Location: /dashboard.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RATEB AI — Login</title>
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css">
</head>
<body>
<div class="auth-shell">
  <div class="card auth-card">
    <div class="auth-tools">
      <button type="button" id="lang" class="icon">عربي</button>
      <button type="button" id="theme" class="icon">☾</button>
    </div>
    <div class="brand">RATEB <span>AI</span></div>
    <h1 data-i18n="welcome_back">Welcome back</h1>
    <p class="muted" data-i18n="login_lead">Login to your RATEB AI Campaign workspace.</p>
    <?php if ($message): ?><div class="alert" data-i18n="<?= e($message) ?>">Invalid email or password.</div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label data-i18n="email">Email</label>
      <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
      <label data-i18n="password">Password</label>
      <input type="password" name="password" required autocomplete="current-password">
      <button type="submit" data-i18n="login_button">Login</button>
    </form>
    <p class="footer-link"><span data-i18n="no_account">Don't have an account?</span> <a href="/register.php" data-i18n="create_one">Create one</a></p>
  </div>
</div>
<script src="/public/assets/js/app.js"></script>
</body>
</html>
