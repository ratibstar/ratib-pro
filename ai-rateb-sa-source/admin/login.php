<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
require __DIR__ . '/auth.php';

function admin_staff_login_screen(object $pdo): void
{
    if (!empty($_SESSION['admin_staff_id'])) {
        header('Location: /admin');
        exit;
    }
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $role = (string) ($stmt->fetchColumn() ?: '');
        if ($role === 'owner') {
            header('Location: /admin');
            exit;
        }
    }

    $adminLang = (($_COOKIE['rateb_admin_lang'] ?? '') === 'en') ? 'en' : 'ar';
    $dir = $adminLang === 'ar' ? 'rtl' : 'ltr';
    $message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $result = admin_staff_authenticate($pdo, (string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($result['ok']) {
            admin_staff_login_session($result['staff']);
            admin_staff_touch_login($pdo, (int) $result['staff']['id']);
            if (admin_table_has($pdo, 'admin_audit_log', 'id')) {
                require __DIR__ . '/lib.php';
                $actor = admin_actor_from_staff($result['staff']);
                admin_audit($pdo, $actor, 'staff_login', 'staff', (int) $result['staff']['id'], '', '');
            }
            header('Location: /admin');
            exit;
        }
        $message = match ($result['code']) {
            'disabled' => $adminLang === 'ar' ? 'الحساب معطّل.' : 'This staff account is disabled.',
            'schema' => $adminLang === 'ar' ? 'الترحيل غير مطبّق.' : 'Staff schema is not applied.',
            default => $adminLang === 'ar' ? 'بيانات الدخول غير صحيحة.' : 'Invalid login.',
        };
    }

    $h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $t = static fn (string $en, string $ar): string => $adminLang === 'ar' ? $ar : $en;
    ?>
<!doctype html>
<html lang="<?= $h($adminLang) ?>" dir="<?= $h($dir) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $h($t('Admin sign-in', 'دخول الإدارة')) ?> — RATEB AI</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">
    <link rel="stylesheet" href="/admin/admin.css?v=14">
</head>
<body class="admin-app">
<div class="login-screen">
    <div class="login-card">
        <p class="login-brand">RATEB <span style="color:var(--cyan)">AI</span></p>
        <h1><?= $h($t('Staff sign-in', 'دخول الموظفين')) ?></h1>
        <p class="note"><?= $h($t('Owner accounts use the main site login.', 'حساب المالك يستخدم تسجيل الدخول الرئيسي للموقع.')) ?></p>
        <?php if ($message !== ''): ?><p class="flash bad"><?= $h($message) ?></p><?php endif; ?>
        <form class="stack" method="post" action="/admin">
            <input type="hidden" name="csrf_token" value="<?= $h(csrf_token()) ?>">
            <label><?= $h($t('Email', 'البريد')) ?><input type="email" name="email" required autocomplete="username"></label>
            <label><?= $h($t('Password', 'كلمة المرور')) ?><input type="password" name="password" required autocomplete="current-password"></label>
            <button type="submit" class="btn primary" style="width:100%"><?= $h($t('Sign in', 'دخول')) ?></button>
        </form>
        <p style="margin-top:18px;text-align:center"><a class="btn quiet" href="/login?next=/admin"><?= $h($t('Owner login', 'دخول المالك')) ?></a></p>
    </div>
</div>
</body>
</html>
    <?php
}

$script = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
if ($script === 'login.php') {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        header('Location: /admin', true, 301);
        exit;
    }
    admin_staff_login_screen($pdo);
    exit;
}