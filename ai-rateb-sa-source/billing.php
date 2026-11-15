<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/app/plans.php';
require_once __DIR__ . '/app/payment/commerce.php';
require_once __DIR__ . '/app/notify/events.php';
require __DIR__ . '/app/nav.php';
require_login();

$userId = (int) $_SESSION['user_id'];
$user = current_user($pdo);
if (!$user) {
    header('Location: /login?next=/billing.php');
    exit;
}

$wallet = rateb_account_credits($pdo, $userId);
rateb_notify_low_credits($pdo, $userId, (int) $wallet['credits_balance']);

$sub = $pdo->prepare("SELECT s.period_end, s.status, p.code, p.name_ar, p.name_en, p.price_sar
    FROM subscriptions s INNER JOIN plans p ON p.id = s.plan_id
    WHERE s.user_id = ? AND s.status = 'active' ORDER BY s.id DESC LIMIT 1");
$sub->execute([$userId]);
$subscription = $sub->fetch();
if ($subscription && (string) $subscription['code'] !== 'free') {
    $end = strtotime((string) $subscription['period_end']);
    if ($end !== false && $end < strtotime('+7 days') && $end > time()) {
        rateb_notify_subscription_ending($pdo, $userId, (string) $subscription['code'], (string) $subscription['period_end']);
    }
}

$receipts = $pdo->prepare('SELECT id, created_at, package_type, amount, moyasar_id FROM payment_receipts WHERE user_id = ? ORDER BY id DESC LIMIT 50');
$receipts->execute([$userId]);
$receiptRows = $receipts->fetchAll();

$refunds = [];
if (commerce_table_has($pdo, 'payment_refunds', 'receipt_id')) {
    $refStmt = $pdo->prepare('SELECT receipt_id, SUM(amount_halalas) AS refunded FROM payment_refunds WHERE user_id = ? GROUP BY receipt_id');
    $refStmt->execute([$userId]);
    foreach ($refStmt->fetchAll() as $r) {
        $refunds[(int) $r['receipt_id']] = (int) $r['refunded'];
    }
}

$ar = ui_language() === 'ar';
$daysLeft = null;
$renewCode = (string) $wallet['package_type'];
if ($subscription && ($subscription['period_end'] ?? '') !== '') {
    $endTs = strtotime((string) $subscription['period_end']);
    if ($endTs !== false) {
        $daysLeft = (int) floor(($endTs - time()) / 86400);
    }
    if ((string) $subscription['code'] !== 'free') {
        $renewCode = (string) $subscription['code'];
    }
}
?><!doctype html>
<html lang="<?= $ar ? 'ar' : 'en' ?>" dir="<?= $ar ? 'rtl' : 'ltr' ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RATEB AI — <?= $ar ? 'الفواتير والاشتراك' : 'Billing' ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css?v=nav44">
</head>
<body class="studio page-dash">
<?php rateb_site_header('/billing.php', true); ?>
<main class="container studio-page" style="max-width:920px;margin:24px auto">
<h1><?= $ar ? 'الفواتير والاشتراك' : 'Billing & subscription' ?></h1>
<section class="studio-hero" style="padding:20px;margin-bottom:20px">
<p><?= $ar ? 'الباقة' : 'Plan' ?>: <strong><?= e((string) $wallet['package_type']) ?></strong> · <?= $ar ? 'الرصيد' : 'Credits' ?>: <strong><?= (int) $wallet['credits_balance'] ?></strong></p>
<?php if ($subscription): ?>
<p><?= $ar ? 'ينتهي الاشتراك' : 'Period ends' ?>: <?= e((string) $subscription['period_end']) ?>
<?php if ($daysLeft !== null): ?> · <?= $daysLeft >= 0 ? (($ar ? 'متبقي ' : '') . $daysLeft . ($ar ? ' يوم' : ' days left')) : ($ar ? 'منتهٍ' : 'Expired') ?><?php endif; ?></p>
<?php endif; ?>
<p>
<a class="button" href="/app/payment/checkout.php?package=<?= rawurlencode(in_array($renewCode, ['starter', 'pro'], true) ? $renewCode : 'starter') ?>"><?= $ar ? 'تجديد / ترقية' : 'Renew / upgrade' ?></a>
<a class="btn quiet" style="margin-inline-start:8px" href="/credits.php"><?= $ar ? 'كل الباقات' : 'All plans' ?></a>
</p>
</section>
<h2><?= $ar ? 'إيصالات الدفع' : 'Payment receipts' ?></h2>
<table style="width:100%;border-collapse:collapse">
<tr style="border-bottom:1px solid #ccc"><th style="text-align:start;padding:8px"><?= $ar ? 'التاريخ' : 'Date' ?></th><th><?= $ar ? 'الباقة' : 'Package' ?></th><th>SAR</th><th></th></tr>
<?php foreach ($receiptRows as $row): ?>
<?php $rid = (int) $row['id']; $ref = $refunds[$rid] ?? 0; ?>
<tr style="border-bottom:1px solid #eee">
<td style="padding:8px"><?= e((string) $row['created_at']) ?></td>
<td><?= e((string) $row['package_type']) ?><?php if ($ref > 0): ?> <span class="muted">(<?= $ar ? 'مسترد' : 'refunded' ?> <?= number_format($ref / 100, 2) ?>)</span><?php endif; ?></td>
<td><?= number_format((int) $row['amount'] / 100, 2) ?></td>
<td><a href="/app/payment/invoice.php?id=<?= $rid ?>"><?= $ar ? 'فاتورة' : 'Invoice' ?></a></td>
</tr>
<?php endforeach; ?>
<?php if ($receiptRows === []): ?>
<tr><td colspan="4" class="muted" style="padding:16px"><?= $ar ? 'لا إيصالات بعد.' : 'No receipts yet.' ?></td></tr>
<?php endif; ?>
</table>
</main>
</body>
</html>