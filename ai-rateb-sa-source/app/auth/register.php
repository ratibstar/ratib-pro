<?php
require __DIR__ . '/../../config/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$message = '';
$oldName = '';
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $oldName = trim($_POST['name'] ?? '');
    $oldEmail = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($oldName === '' || !filter_var($oldEmail, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $message = 'register_invalid';
    } else {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->execute([$oldEmail]);

        if ($check->fetch()) {
            $message = 'email_taken';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([$oldName, $oldEmail, $hash]);

            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            session_regenerate_id(true);
            header('Location: /dashboard.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RATEB AI — Register</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css?v=waves">
</head>
<body class="studio">
<div class="auth-shell">
  <div class="card auth-card">
    <div class="auth-tools">
      <button type="button" id="lang" class="icon">عربي</button>
      <button type="button" id="theme" class="icon">☾</button>
    </div>
    <a class="brand" href="/">RATEB <span>AI</span></a>
    <h1 data-i18n="create_title">Create your account</h1>
    <p class="muted" data-i18n="register_lead">Start creating AI-powered marketing campaigns.</p>
    <?php if ($message === 'register_invalid'): ?><div class="alert" data-i18n="register_invalid">Please enter valid information. Password must be at least 8 characters.</div><?php endif; ?>
    <?php if ($message === 'email_taken'): ?><div class="alert" data-i18n="email_taken">Email already registered.</div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label data-i18n="name">Name</label>
      <input name="name" value="<?= e($oldName) ?>" required autocomplete="name">
      <label data-i18n="email">Email</label>
      <input type="email" name="email" value="<?= e($oldEmail) ?>" required autocomplete="email">
      <label data-i18n="password">Password</label>
      <input type="password" name="password" minlength="8" required autocomplete="new-password">
      <button type="submit" data-i18n="create_button">Create Account</button>
    </form>
    <p class="footer-link"><span data-i18n="have_account">Already have an account?</span> <a href="/login.php" data-i18n="login">Login</a></p>
  </div>
</div>
<script src="/public/assets/js/app.js"></script>
</body>
</html>
