<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/app/plans.php';
require __DIR__ . '/app/nav.php';
require_login();

$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    header('Location: /login?next=/account');
    exit;
}

$arabic = ui_language() === 'ar';
$hasPassword = trim((string) ($user['password_hash'] ?? '')) !== '';
$flash = (string) ($_GET['ok'] ?? '');
$error = (string) ($_GET['error'] ?? '');
$wallet = rateb_account_credits($pdo, $userId);
?>
<!DOCTYPE html>
<html lang="<?= $arabic ? 'ar' : 'en' ?>" dir="<?= $arabic ? 'rtl' : 'ltr' ?>" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RATEB AI — <?= $arabic ? 'حسابي' : 'My account' ?></title>
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css?v=nav53">
</head>
<body class="studio page-account">
<?php rateb_site_header('/account', true); ?>
<main class="wrap legal-page">
  <section class="panel">
    <p class="eyebrow" data-i18n="nav_account"><?= $arabic ? 'الحساب' : 'Account' ?></p>
    <h1 data-i18n="account_title"><?= $arabic ? 'حسابي' : 'My account' ?></h1>
    <p class="muted" data-i18n="account_lead"><?= $arabic ? 'بيانات الدخول الخاصة بك. البريد ثابت؛ يمكنك تحديث الاسم وكلمة المرور.' : 'Your sign-in details. Email stays fixed; you can update your name and password.' ?></p>
    <p class="muted"><?= $arabic ? 'الباقة:' : 'Plan:' ?> <?= e((string) $wallet['package_type']) ?> · <?= $arabic ? 'الرصيد:' : 'Credits:' ?> <?= (int) $wallet['credits_balance'] ?>
      · <a href="/billing.php" data-i18n="nav_billing"><?= $arabic ? 'الفوترة' : 'Billing' ?></a>
      · <a href="/brand.php" data-i18n="brand_kit"><?= $arabic ? 'هوية العلامة' : 'Brand kit' ?></a>
    </p>

    <?php if ($flash === 'profile'): ?><p class="notice" data-i18n="account_saved"><?= $arabic ? 'تم حفظ الاسم.' : 'Name saved.' ?></p><?php endif; ?>
    <?php if ($flash === 'password'): ?><p class="notice" data-i18n="account_password_saved"><?= $arabic ? 'تم تحديث كلمة المرور.' : 'Password updated.' ?></p><?php endif; ?>
    <?php if ($error === 'name'): ?><p class="notice is-error" data-i18n="account_name_invalid"><?= $arabic ? 'اكتب اسماً صالحاً.' : 'Enter a valid name.' ?></p><?php endif; ?>
    <?php if ($error === 'password_current'): ?><p class="notice is-error" data-i18n="account_password_current"><?= $arabic ? 'كلمة المرور الحالية غير صحيحة.' : 'Current password is incorrect.' ?></p><?php endif; ?>
    <?php if ($error === 'password_short'): ?><p class="notice is-error" data-i18n="account_password_short"><?= $arabic ? 'كلمة المرور الجديدة تحتاج 8 أحرف على الأقل.' : 'New password needs at least 8 characters.' ?></p><?php endif; ?>
    <?php if ($error === 'password_match'): ?><p class="notice is-error" data-i18n="account_password_match"><?= $arabic ? 'تأكيد كلمة المرور غير متطابق.' : 'Password confirmation does not match.' ?></p><?php endif; ?>
    <?php if ($error === 'password_missing'): ?><p class="notice is-error" data-i18n="account_password_missing"><?= $arabic ? 'هذا الحساب بلا كلمة مرور محلية. عيّن كلمة مرور جديدة مباشرة.' : 'This account has no local password yet. Set a new password directly.' ?></p><?php endif; ?>
    <?php if ($error === 'csrf' || $error === 'save'): ?><p class="notice is-error" data-i18n="account_save_failed"><?= $arabic ? 'تعذر الحفظ. حاول مرة أخرى.' : 'Could not save. Try again.' ?></p><?php endif; ?>

    <form method="post" action="/app/account/save.php" class="legal-form" data-account-profile>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="profile">
      <label>
        <span data-i18n="name"><?= $arabic ? 'الاسم' : 'Name' ?></span>
        <input name="name" required maxlength="120" value="<?= e((string) $user['name']) ?>">
      </label>
      <label>
        <span data-i18n="email"><?= $arabic ? 'البريد' : 'Email' ?></span>
        <input type="email" value="<?= e((string) $user['email']) ?>" readonly>
      </label>
      <p class="muted" data-i18n="account_email_note"><?= $arabic ? 'لتغيير البريد تواصل مع الدعم من صفحة التواصل.' : 'To change email, contact support from the contact page.' ?></p>
      <button class="button studio-cta" type="submit" data-i18n="account_save_name"><?= $arabic ? 'حفظ الاسم' : 'Save name' ?></button>
    </form>
  </section>

  <section class="panel">
    <h2 data-i18n="account_password_title"><?= $arabic ? 'كلمة المرور' : 'Password' ?></h2>
    <form method="post" action="/app/account/save.php" class="legal-form" data-account-password>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="password">
      <?php if ($hasPassword): ?>
      <label>
        <span data-i18n="account_current_password"><?= $arabic ? 'كلمة المرور الحالية' : 'Current password' ?></span>
        <input type="password" name="current_password" required autocomplete="current-password">
      </label>
      <?php else: ?>
      <p class="muted" data-i18n="account_set_password_lead"><?= $arabic ? 'حسابك دخل عبر مزود خارجي. يمكنك تعيين كلمة مرور للدخول بالبريد.' : 'You signed in with an external provider. You can set a password for email login.' ?></p>
      <?php endif; ?>
      <label>
        <span data-i18n="account_new_password"><?= $arabic ? 'كلمة المرور الجديدة' : 'New password' ?></span>
        <input type="password" name="new_password" minlength="8" required autocomplete="new-password">
      </label>
      <label>
        <span data-i18n="account_confirm_password"><?= $arabic ? 'تأكيد كلمة المرور' : 'Confirm password' ?></span>
        <input type="password" name="confirm_password" minlength="8" required autocomplete="new-password">
      </label>
      <button class="button primary" type="submit" data-i18n="account_save_password"><?= $arabic ? 'تحديث كلمة المرور' : 'Update password' ?></button>
    </form>
  </section>
</main>
<?php rateb_site_footer('/account'); ?>
<script src="/public/assets/js/app.js?v=nav53"></script>
</body>
</html>
