<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/app/plans.php';
require __DIR__ . '/app/nav.php';
require_login();

$arabic = ui_language() === 'ar';
$configured = rateb_moyasar_configured(require __DIR__ . '/config/ai.php');
$wallet = rateb_account_credits($pdo, (int) $_SESSION['user_id']);
$history = $pdo->prepare("SELECT operation, created_at FROM usage_events WHERE user_id = ? AND operation IN ('welcome', 'payment_starter', 'payment_pro') ORDER BY id DESC LIMIT 20");
$history->execute([(int) $_SESSION['user_id']]);
$rows = $history->fetchAll();
$names = [
    'starter' => $arabic ? 'باقة الانطلاق' : 'Starter',
    'pro' => $arabic ? 'الباقة الاحترافية' : 'Pro',
];
$events = [
    'welcome' => $arabic ? 'نقاط الترحيب' : 'Welcome credits',
    'payment_starter' => $arabic ? 'شراء باقة الانطلاق' : 'Starter purchase',
    'payment_pro' => $arabic ? 'شراء الباقة الاحترافية' : 'Pro purchase',
];
?>
<!doctype html>
<html lang="<?= $arabic ? 'ar' : 'en' ?>" dir="<?= $arabic ? 'rtl' : 'ltr' ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $arabic ? 'شراء رصيد' : 'Buy credits' ?> — RATEB AI</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css?v=nav40">
</head>
<body class="studio page-dash">
<?php rateb_site_header('/credits.php', true); ?>
<main class="container studio-page">
  <p class="eyebrow"><?= $arabic ? 'الدفع عبر ميسر' : 'Payments by Moyasar' ?></p>
  <h1><?= $arabic ? 'شراء رصيد' : 'Buy credits' ?></h1>
  <p class="muted"><?= $arabic ? 'الرصيد الحالي' : 'Current balance' ?>: <?= (int) $wallet['credits_balance'] ?></p>
  <?php if (!$configured): ?>
    <p class="alert"><?= $arabic ? 'الدفع الإلكتروني غير متاح مؤقتًا. لا يمكن إتمام الشراء الآن.' : 'Online payment is temporarily unavailable. Purchases cannot be completed right now.' ?></p>
  <?php endif; ?>
  <div class="price-grid">
    <?php foreach (['starter', 'pro'] as $code): ?>
      <?php $package = rateb_paid_package($code); ?>
      <article class="price-card<?= $code === 'starter' ? ' is-featured' : '' ?>">
        <p class="eyebrow"><?= e($names[$code]) ?></p>
        <p class="price-amount"><b><?= number_format($package['amount'] / 100, 0) ?></b> <span><?= $arabic ? 'ر.س' : 'SAR' ?></span></p>
        <p><?= (int) $package['credits'] ?> <?= $arabic ? 'نقطة' : 'credits' ?></p>
        <?php if ($configured): ?>
          <a class="button" href="/app/payment/checkout.php?package=<?= e($code) ?>"><?= $arabic ? 'شراء الباقة' : 'Buy package' ?></a>
        <?php else: ?>
          <button class="button" type="button" disabled><?= $arabic ? 'شراء الباقة' : 'Buy package' ?></button>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
  <h2><?= $arabic ? 'السجل' : 'History' ?></h2>
  <?php if (!$rows): ?>
    <p class="muted"><?= $arabic ? 'لا توجد عمليات بعد.' : 'No credit activity yet.' ?></p>
  <?php else: ?>
    <ul>
      <?php foreach ($rows as $row): ?>
        <li><?= e($events[(string) $row['operation']] ?? (string) $row['operation']) ?> — <?= e((string) $row['created_at']) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</main>
<?php rateb_auth_dialog('/credits.php'); ?>
</body>
</html>
