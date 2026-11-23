<?php
require __DIR__ . '/../../config/bootstrap.php';
require __DIR__ . '/../welcome.php';
require_once __DIR__ . '/account-state.php';

if (!empty($_SESSION['user_id'])) {
    if (rateb_user_is_disabled($pdo, (int) $_SESSION['user_id'])) {
        rateb_clear_disabled_session();
    } else {
        $roleStmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $roleStmt->execute([(int) $_SESSION['user_id']]);
        $sessionRole = (string) ($roleStmt->fetchColumn() ?: 'user');
        $roleForNext = $sessionRole === 'owner' ? 'owner' : null;
        header('Location: ' . rateb_safe_next((string) ($_GET['next'] ?? ''), $roleForNext));
        exit;
    }
}

$message = '';
$email = '';

$authModal = ($_POST['auth_modal'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? $_POST['csrf'] ?? '');
    $expected = (string) ($_SESSION['csrf_token'] ?? $_SESSION['csrf'] ?? '');
    if ($expected === '' || !hash_equals($expected, $token)) {
        if ($authModal) {
            http_response_code(419);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'invalid_login']);
            exit;
        }
        verify_csrf();
    }

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash']) && rateb_user_is_disabled($pdo, (int) $user['id'])) {
        $message = 'account_disabled';
        if ($authModal) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $message]);
            exit;
        }
    } elseif (!$user || !password_verify($password, $user['password_hash'])) {
        $message = 'invalid_login';
        if ($authModal) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $message]);
            exit;
        }
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        try {
            rateb_welcome_recover($pdo, (int) $user['id'], (string) $user['email'], false);
        } catch (Throwable) {
        }
        rateb_stamp_login($pdo, (int) $user['id']);
        $roleForNext = (string) ($user['role'] ?? 'user') === 'owner' ? 'owner' : null;
        $next = rateb_safe_next((string) ($_POST['next'] ?? ''), $roleForNext);
        if ($authModal) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'redirect' => $next]);
            exit;
        }
        header('Location: ' . $next);
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
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css?v=nav40">
</head>
<body class="studio page-login">
<div class="auth-shell">
  <div class="card auth-card">
    <div class="auth-tools">
      <button type="button" id="lang" class="icon">عربي</button>
      <button type="button" id="theme" class="icon">☾</button>
    </div>
    <a class="brand" href="/dashboard">RATEB <span>AI</span></a>
    <h1 data-i18n="welcome_back">Welcome back</h1>
    <p class="muted" data-i18n="login_lead">Login to your RATEB AI Campaign workspace.</p>
    <?php if (($_GET['reset'] ?? '') === '1' && $message === ''): ?><p class="muted" data-i18n="reset_success">Your password was changed. You can log in now.</p><?php endif; ?>
    <?php if ($message === 'account_disabled'): ?><div class="alert">This account is disabled.</div><?php elseif ($message): ?><div class="alert" data-i18n="<?= e($message) ?>">Invalid email or password.</div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="next" value="<?= e(rateb_normalize_next_path((string) ($_GET['next'] ?? ''))) ?>">
      <label data-i18n="email">Email</label>
      <input type="text" name="email" value="<?= e($email) ?>" required autocomplete="username">
      <label data-i18n="password">Password</label>
      <input type="password" name="password" required autocomplete="current-password">
      <button type="submit" data-i18n="login_button">Login</button>
    </form>
    <p class="footer-link"><a href="/forgot-password.php" data-i18n="forgot_password">Forgot password?</a></p>
    <p class="footer-link"><span data-i18n="no_account">Don't have an account?</span> <a href="/register" data-i18n="create_one">Create one</a></p>
  </div>
</div>
<script src="/public/assets/js/app.js?v=nav40"></script>
</body>
</html>
