<?php
declare(strict_types=1);

require __DIR__ . '/../../config/bootstrap.php';
require __DIR__ . '/reset-lib.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$requested = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    password_reset_request($pdo, strtolower(trim((string) ($_POST['email'] ?? ''))));
    $requested = true;
}
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RATEB AI — Forgot password</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css?v=ink1">
</head>
<body class="studio page-login">
<div class="auth-shell">
  <div class="card auth-card">
    <div class="auth-tools">
      <button type="button" id="lang" class="icon">عربي</button>
      <button type="button" id="theme" class="icon">☾</button>
    </div>
    <a class="brand" href="/">RATEB <span>AI</span></a>
    <h1 data-i18n="forgot_title">Forgot password</h1>
    <p class="muted" data-i18n="forgot_lead">Enter the email on your account.</p>
    <?php if ($requested): ?><p class="muted" data-i18n="reset_requested">If an account exists for that email, reset instructions will be sent. Email delivery is not configured yet, so no message was sent.</p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label data-i18n="email">Email</label>
      <input type="email" name="email" required autocomplete="email">
      <button type="submit" data-i18n="send_reset">Continue</button>
    </form>
    <p class="footer-link"><a href="/login.php" data-i18n="login">Login</a></p>
  </div>
</div>
<script src="/public/assets/js/app.js?v=reset1"></script>
</body>
</html>
