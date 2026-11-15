<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/app/plans.php';
require_once __DIR__ . '/app/nav.php';
require_login();

$ar = ui_language() === 'ar';
$wallet = rateb_account_credits($pdo, (int) $_SESSION['user_id']);
$packages = [
    ['code' => 'starter', 'price' => '99', 'credits' => 500],
    ['code' => 'pro', 'price' => '249', 'credits' => 2500],
];
?><!doctype html>
<html lang="<?= $ar ? 'ar' : 'en' ?>" dir="<?= $ar ? 'rtl' : 'ltr' ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RATEB AI — <?= $ar ? 'الباقات' : 'Plans' ?></title>
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css?v=nav44">
</head>
<body class="studio page-dash">
<?php rateb_site_header('/credits.php', true); ?>
<main class="container studio-page">
<h1><?= $ar ? 'الباقات والترقية' : 'Plans & upgrade' ?></h1>
<p><?= $ar ? 'باقتك الحالية' : 'Current plan' ?>: <strong><?= e((string) $wallet['package_type']) ?></strong> · <?= $ar ? 'الرصيد' : 'Credits' ?>: <strong><?= (int) $wallet['credits_balance'] ?></strong></p>
<div class="price-grid">
<?php foreach ($packages as $pkg): ?>
<article class="price-card<?= $wallet['package_type'] === $pkg['code'] ? ' is-featured' : '' ?>">
<p class="eyebrow"><?= e($pkg['code']) ?></p>
<p class="price-amount"><b><?= e($pkg['price']) ?></b> <span><?= $ar ? 'ر.س' : 'SAR' ?></span></p>
<p><?= (int) $pkg['credits'] ?> <?= $ar ? 'نقطة' : 'credits' ?></p>
<a class="button studio-cta" href="/app/payment/checkout.php?package=<?= rawurlencode($pkg['code']) ?>"><?= $ar ? 'اشترك / جدّد' : 'Subscribe / renew' ?></a>
</article>
<?php endforeach; ?>
</div>
<p><a href="/billing.php"><?= $ar ? 'الفواتير والاشتراك' : 'Billing & receipts' ?></a> · <a href="/dashboard"><?= $ar ? 'لوحة التحكم' : 'Dashboard' ?></a></p>
</main>
</body>
</html>