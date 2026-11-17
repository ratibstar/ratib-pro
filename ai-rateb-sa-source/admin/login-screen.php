<?php
declare(strict_types=1);

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
        try {
            verify_csrf();
            $emailRaw = (string) ($_POST['email'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $result = admin_staff_authenticate($pdo, $emailRaw, $password);
            if ($result['ok']) {
                admin_staff_login_session($result['staff']);
                unset($_SESSION['admin_customer_blocked'], $_SESSION['user_id']);
                admin_staff_touch_login($pdo, (int) $result['staff']['id']);
                if (admin_table_has($pdo, 'admin_audit_log', 'id')) {
                    require_once __DIR__ . '/lib.php';
                    $actor = admin_actor_from_staff($result['staff']);
                    admin_audit($pdo, $actor, 'staff_login', 'staff', (int) $result['staff']['id'], '', '');
                }
                header('Location: /admin');
                exit;
            }
            $owner = ['ok' => false, 'code' => 'invalid'];
            if (function_exists('admin_owner_authenticate')) {
                $owner = admin_owner_authenticate($pdo, $emailRaw, $password);
            }
            if ($owner['ok']) {
                admin_owner_login_session($owner['user']);
                unset($_SESSION['admin_customer_blocked']);
                if (function_exists('rateb_stamp_login')) {
                    rateb_stamp_login($pdo, (int) $owner['user']['id']);
                }
                header('Location: /admin');
                exit;
            }
            $failCode = $owner['code'] !== 'invalid' ? $owner['code'] : $result['code'];
            $message = match ($failCode) {
                'disabled' => $adminLang === 'ar' ? 'الحساب معطّل.' : 'This account is disabled.',
                'schema' => $adminLang === 'ar' ? 'الترحيل غير مطبّق.' : 'Staff schema is not applied.',
                'customer' => $adminLang === 'ar' ? 'حساب العميل لا يدخل من هنا — استخدم حساب المالك أو موظف.' : 'Customer accounts cannot use this console.',
                'owner' => $adminLang === 'ar' ? 'حساب المالك يستخدم البريد الكامل (مثل admin@rateb.sa).' : 'Owners must use the full email (e.g. admin@rateb.sa).',
                default => $adminLang === 'ar'
                ? 'بيانات الدخول غير صحيحة. استخدم كلمة مرور الموقع الرئيسي (ليست بالضرورة 123456).'
                : 'Invalid login. Use your main site password (not necessarily 123456).',
            };
        } catch (Throwable) {
            $message = $adminLang === 'ar'
                ? 'تعذّر تسجيل الدخول مؤقتًا. حدّث الصفحة وحاول مرة أخرى.'
                : 'Sign-in failed temporarily. Refresh the page and try again.';
        }
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
        <h1><?= $h($t('Administration console', 'لوحة إدارة المنصة')) ?></h1>
        <p class="note"><?= $h($t(
            'You are on the official admin URL (/admin). After sign-in you get the dashboard, finance, customers, and settings.',
            'أنت على رابط الإدارة الرسمي (/admin). بعد الدخول تظهر اللوحة والتحليلات والمالية والعملاء والإعدادات.'
        )) ?></p>
        <?php if (!empty($_SESSION['admin_customer_blocked'])): ?>
        <p class="flash bad"><?= $h($t(
            'You are signed in on the site as a customer (' . (string) $_SESSION['admin_customer_blocked'] . '). That account cannot open /admin. Sign in below as owner (admin + your password) or staff, or log out of the customer session first.',
            'أنت مسجّل دخولك على الموقع كعميل (' . (string) $_SESSION['admin_customer_blocked'] . '). هذا الحساب لا يفتح /admin. سجّل أدناه كمالك (admin + كلمة المرور) أو كموظف، أو سجّل خروج العميل أولاً.'
        )) ?></p>
        <p class="note" style="margin-bottom:12px"><a class="btn quiet" href="/logout.php?next=/admin"><?= $h($t('Log out customer session', 'تسجيل خروج حساب العميل')) ?></a></p>
        <?php endif; ?>
        <h2 class="login-sub" style="margin:16px 0 8px;font-size:1rem;color:var(--muted,#93a4bd)"><?= $h($t('Platform staff', 'موظفو المنصة')) ?></h2>
        <?php if ($message !== ''): ?><p class="flash bad"><?= $h($message) ?></p><?php endif; ?>
        <form class="stack" method="post" action="/admin">
            <input type="hidden" name="csrf_token" value="<?= $h(csrf_token()) ?>">
            <label><?= $h($t('Email or username', 'البريد أو اسم المستخدم')) ?><input type="text" name="email" required autocomplete="username" inputmode="email" placeholder="admin@rateb.sa"></label>
            <label><?= $h($t('Password', 'كلمة المرور')) ?><input type="password" name="password" required autocomplete="current-password"></label>
            <button type="submit" class="btn primary" style="width:100%"><?= $h($t('Sign in', 'دخول')) ?></button>
        </form>
        <div style="margin-top:22px;padding-top:18px;border-top:1px solid var(--line,#243049);text-align:center">
            <p class="note" style="margin-bottom:10px"><?= $h($t('Platform owner?', 'مالك المنصة؟')) ?> <?= $h($t('Use your main site account — not the staff form above.', 'استخدم حساب الموقع الرئيسي — وليس نموذج الموظفين أعلاه.')) ?></p>
            <a class="btn primary" href="/login?next=/admin"><?= $h($t('Owner sign-in to admin', 'دخول المالك إلى الإدارة')) ?></a>
        </div>
    </div>
</div>
</body>
</html>
    <?php
}