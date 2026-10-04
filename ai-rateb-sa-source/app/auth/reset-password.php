<?php
declare(strict_types=1);

require __DIR__ . '/../../config/bootstrap.php';
require __DIR__ . '/reset-lib.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
    $token = '';
}
$valid = $token !== '' && password_reset_find($pdo, $token) !== null;
$short = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    if (strlen($password) < 8) {
        $short = true;
    } elseif (password_reset_apply($pdo, $token, $password)) {
        header('Location: /login.php?reset=1');
        exit;
    } else {
        $valid = false;
        $token = '';
    }
}
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RATEB AI — Reset password</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css?v=waves2">
</head>
<body class="studio page-login">
<div class="auth-shell">
  <div class="card auth-card">
    <div class="auth-tools">
      <button type="button" id="lang" class="icon">عربي</button>
      <button type="button" id="theme" class="icon">☾</button>
    </div>
    <a class="brand" href="/">RATEB <span>AI</span></a>
    <h1 data-i18n="reset_title">Choose a new password</h1>
    <?php if ($short): ?>
      <p class="alert" data-i18n="register_invalid">Check the details and use at least 8 characters.</p>
    <?php elseif (!$valid): ?>
      <p class="alert" data-i18n="reset_invalid">This reset link is invalid or has expired.</p>
    <?php else: ?>
      <p class="muted" data-i18n="reset_lead">Use at least 8 characters.</p>
    <?php endif; ?>
    <?php if ($valid): ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label data-i18n="password">Password</label>
        <input type="password" name="password" minlength="8" required autocomplete="new-password">
        <button type="submit" data-i18n="save_password">Save password</button>
      </form>
    <?php endif; ?>
    <p class="footer-link"><a href="/login.php" data-i18n="login">Login</a></p>
  </div>
</div>
<script src="/public/assets/js/app.js?v=reset1"></script>
</body>
</html>
