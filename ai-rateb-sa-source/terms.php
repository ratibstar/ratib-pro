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
<title>RATEB AI — <?= $arabic ? 'الشروط' : 'Terms' ?></title>
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css?v=nav52">
</head>
<body class="studio page-legal">
<?php rateb_site_header('/terms', $loggedIn, !$loggedIn); ?>
<main class="wrap legal-page">
  <section class="panel legal-copy">
    <p class="eyebrow" data-i18n="nav_terms"><?= $arabic ? 'الشروط' : 'Terms' ?></p>
    <h1 data-i18n="terms_title"><?= $arabic ? 'شروط الاستخدام' : 'Terms of use' ?></h1>
    <p class="muted"><?= $arabic ? 'آخر تحديث: 22 نوفمبر 2026' : 'Last updated: 22 November 2026' ?></p>
    <?php if ($arabic): ?>
      <h2>الخدمة</h2>
      <p>RATEB AI استوديو لإنشاء حملات تسويقية بمساعدة الذكاء الاصطناعي. النتائج تعتمد على المدخلات وتوفر المزودين.</p>
      <h2>حسابك</h2>
      <p>أنت مسؤول عن سرية الدخول وعن المحتوى الذي ترفعه أو تولّده. لا تستخدم الخدمة لمحتوى غير قانوني أو مضلّل.</p>
      <h2>الملكية</h2>
      <p>تحتفظ بحقوق موادك. تمنحنا ترخيصاً لتشغيل المعالجة اللازمة لإنتاج الحملة. مخرجات AI قد تخضع لشروط المزودين الخارجيين.</p>
      <h2>الباقات والنقاط</h2>
      <p>الحدود والرصيد يظهران في لوحة الحساب. الدفع الإلكتروني يُفعَّل عند اكتمال إعداد التحصيل؛ حتى ذلك الحين قد تكون بعض الباقات للعرض فقط.</p>
      <h2>إخلاء المسؤولية</h2>
      <p>نقدم الخدمة «كما هي». راجع المخرجات قبل النشر. لا نضمن نتائج إعلانية أو قبول منصات التواصل.</p>
      <h2>التواصل</h2>
      <p>للاستفسارات: <a href="/contact">تواصل معنا</a> أو <?= e($inbox) ?>.</p>
    <?php else: ?>
      <h2>The service</h2>
      <p>RATEB AI is a studio for marketing campaigns assisted by AI. Results depend on your inputs and provider availability.</p>
      <h2>Your account</h2>
      <p>You are responsible for login security and for content you upload or generate. Do not use the service for unlawful or misleading content.</p>
      <h2>Ownership</h2>
      <p>You keep rights to your materials. You grant us a license to process them to produce the campaign. AI outputs may also follow third-party provider terms.</p>
      <h2>Plans and credits</h2>
      <p>Limits and balances appear in your account. Online checkout is enabled when payment collection is fully configured; until then some plans may be display-only.</p>
      <h2>Disclaimer</h2>
      <p>The service is provided as available. Review outputs before publishing. We do not guarantee ad performance or platform acceptance.</p>
      <h2>Contact</h2>
      <p>Questions: <a href="/contact">contact us</a> or <?= e($inbox) ?>.</p>
    <?php endif; ?>
  </section>
</main>
<?php rateb_site_footer('/terms'); ?>
<?php if (!$loggedIn) { rateb_auth_dialog('/terms'); } ?>
<script src="/public/assets/js/app.js?v=nav52"></script>
</body>
</html>
