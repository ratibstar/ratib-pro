<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/app/plans.php';
require_once __DIR__ . '/app/notify/mail.php';
require __DIR__ . '/app/nav.php';

$loggedIn = isset($_SESSION['user_id']);
$arabic = ui_language() === 'ar';
$inbox = rateb_support_inbox();
$notice = '';
$error = '';
$name = '';
$email = '';
$message = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120) {
        $error = $arabic ? 'اكتب اسمك.' : 'Enter your name.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = $arabic ? 'أدخل بريداً صالحاً.' : 'Enter a valid email.';
    } elseif ($message === '' || mb_strlen($message) < 10) {
        $error = $arabic ? 'اكتب رسالتك بوضوح.' : 'Write a clear message.';
    } elseif (mb_strlen($message) > 4000) {
        $error = $arabic ? 'الرسالة طويلة جداً.' : 'The message is too long.';
    } else {
        $body = "Support request from RATEB AI\n\nName: {$name}\nEmail: {$email}\n\n{$message}\n";
        $ok = rateb_mail_send(
            $pdo,
            $inbox,
            'RATEB AI support — ' . mb_substr($name, 0, 80),
            $body,
            'support_contact',
            $loggedIn ? (int) $_SESSION['user_id'] : null,
            $email
        );
        if ($ok) {
            $notice = $arabic ? 'وصلت رسالتك. نرد عبر البريد.' : 'Your message was sent. We will reply by email.';
            $name = '';
            $email = '';
            $message = '';
        } else {
            $error = $arabic
                ? ('تعذر الإرسال الآن. راسلنا مباشرة: ' . $inbox)
                : ('Could not send right now. Email us at ' . $inbox);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $arabic ? 'ar' : 'en' ?>" dir="<?= $arabic ? 'rtl' : 'ltr' ?>" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RATEB AI — <?= $arabic ? 'تواصل معنا' : 'Contact' ?></title>
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css?v=nav52">
</head>
<body class="studio page-legal">
<?php rateb_site_header('/contact', $loggedIn, !$loggedIn); ?>
<main class="wrap legal-page">
  <section class="panel">
    <p class="eyebrow" data-i18n="nav_contact"><?= $arabic ? 'تواصل' : 'Contact' ?></p>
    <h1 data-i18n="contact_title"><?= $arabic ? 'كيف نساعدك؟' : 'How can we help?' ?></h1>
    <p class="muted" data-i18n="contact_lead"><?= $arabic ? 'للاستفسارات عن الحساب أو الحملات أو الباقات، أرسل رسالتك وسنرد عبر البريد.' : 'For account, campaign, or plan questions, send a message and we will reply by email.' ?></p>
    <p class="muted"><?= $arabic ? 'البريد المباشر:' : 'Direct email:' ?> <a href="mailto:<?= e($inbox) ?>"><?= e($inbox) ?></a></p>
    <?php if ($notice !== ''): ?><p class="notice"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="notice is-error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="legal-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label><span data-i18n="name"><?= $arabic ? 'الاسم' : 'Name' ?></span>
        <input name="name" required maxlength="120" value="<?= e($name ?? '') ?>">
      </label>
      <label><span data-i18n="email"><?= $arabic ? 'البريد' : 'Email' ?></span>
        <input type="email" name="email" required value="<?= e($email ?? '') ?>">
      </label>
      <label><span data-i18n="contact_message"><?= $arabic ? 'الرسالة' : 'Message' ?></span>
        <textarea name="message" rows="6" required maxlength="4000"><?= e($message ?? '') ?></textarea>
      </label>
      <button class="button studio-cta" type="submit" data-i18n="contact_send"><?= $arabic ? 'أرسل الرسالة' : 'Send message' ?></button>
    </form>
  </section>
</main>
<?php rateb_site_footer('/contact'); ?>
<?php if (!$loggedIn) { rateb_auth_dialog('/contact'); } ?>
<script src="/public/assets/js/app.js?v=nav52"></script>
</body>
</html>
