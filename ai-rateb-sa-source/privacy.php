<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/app/nav.php';

$loggedIn = isset($_SESSION['user_id']);
$arabic = ui_language() === 'ar';
$inbox = rateb_support_inbox();
?>
<!DOCTYPE html>
<html lang="<?= $arabic ? 'ar' : 'en' ?>" dir="<?= $arabic ? 'rtl' : 'ltr' ?>" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RATEB AI — <?= $arabic ? 'الخصوصية' : 'Privacy' ?></title>
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css?v=nav52">
</head>
<body class="studio page-legal">
<?php rateb_site_header('/privacy', $loggedIn, !$loggedIn); ?>
<main class="wrap legal-page">
  <section class="panel legal-copy">
    <p class="eyebrow" data-i18n="nav_privacy"><?= $arabic ? 'الخصوصية' : 'Privacy' ?></p>
    <h1 data-i18n="privacy_title"><?= $arabic ? 'سياسة الخصوصية' : 'Privacy policy' ?></h1>
    <p class="muted"><?= $arabic ? 'آخر تحديث: 22 نوفمبر 2026' : 'Last updated: 22 November 2026' ?></p>
    <?php if ($arabic): ?>
      <h2>ما نجمعه</h2>
      <p>بيانات الحساب (الاسم والبريد)، ملخصات الحملات، الملفات التي ترفعها، وسجلات الاستخدام اللازمة لتشغيل الاستوديو والفوترة.</p>
      <h2>كيف نستخدمها</h2>
      <p>لتشغيل RATEB AI، إنشاء المحتوى الذي تطلبه، حفظ حملاتك، ودعم حسابك. لا نبيع بياناتك الشخصية.</p>
      <h2>المعالجون</h2>
      <p>قد نمرّر نصوصاً أو وسائط إلى مزودي ذكاء اصطناعي وصوت وفيديو ومضيف البريد لإكمال طلبك فقط.</p>
      <h2>الاحتفاظ والأمان</h2>
      <p>نحتفظ بالبيانات طالما حسابك نشط أو كما يقتضي القانون. نطبق ضوابط وصول معقولة على الخوادم.</p>
      <h2>حقوقك</h2>
      <p>يمكنك طلب تصحيح بياناتك أو حذف الحساب عبر <a href="/contact">صفحة التواصل</a> أو <?= e($inbox) ?>.</p>
    <?php else: ?>
      <h2>What we collect</h2>
      <p>Account details (name and email), campaign briefs, files you upload, and usage records needed to run the studio and billing.</p>
      <h2>How we use it</h2>
      <p>To operate RATEB AI, create the content you request, store your campaigns, and support your account. We do not sell your personal data.</p>
      <h2>Processors</h2>
      <p>We may send text or media to AI, voice, video, and mail providers solely to complete your request.</p>
      <h2>Retention and security</h2>
      <p>We keep data while your account is active or as required by law. Reasonable access controls apply on our servers.</p>
      <h2>Your rights</h2>
      <p>You may ask to correct your data or delete your account through the <a href="/contact">contact page</a> or <?= e($inbox) ?>.</p>
    <?php endif; ?>
  </section>
</main>
<?php rateb_site_footer('/privacy'); ?>
<?php if (!$loggedIn) { rateb_auth_dialog('/privacy'); } ?>
<script src="/public/assets/js/app.js?v=nav52"></script>
</body>
</html>
