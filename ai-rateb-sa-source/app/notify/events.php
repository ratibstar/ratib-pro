<?php
declare(strict_types=1);

require_once __DIR__ . '/mail.php';

function rateb_user_lang(?array $user): string
{
    unset($user);
    return (($_COOKIE['rateb_ui_lang'] ?? '') === 'en') ? 'en' : 'ar';
}

function rateb_notify_payment_paid(PDO $pdo, int $userId, int $receiptId, string $packageCode, int $amountHalalas): void
{
    $stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return;
    }
    $sar = number_format($amountHalalas / 100, 2, '.', '');
    $invoiceUrl = 'https://ai.rateb.sa/app/payment/invoice.php?id=' . $receiptId;
    $ar = rateb_user_lang($user) === 'ar';
    $subject = $ar ? 'RATEB AI — تم استلام دفعتك' : 'RATEB AI — Payment received';
    $body = $ar
        ? "مرحباً {$user['name']},\n\nتم استلام دفعتك بنجاح.\nالباقة: {$packageCode}\nالمبلغ: {$sar} ر.س\nفاتورة: {$invoiceUrl}\n\nشكراً لاستخدامك RATEB AI."
        : "Hi {$user['name']},\n\nYour payment was received.\nPackage: {$packageCode}\nAmount: {$sar} SAR\nInvoice: {$invoiceUrl}\n\nThank you for using RATEB AI.";
    rateb_mail_send($pdo, (string) $user['email'], $subject, $body, 'payment_paid', $userId, 'receipt:' . $receiptId);
}

function rateb_notify_campaign_ready(PDO $pdo, int $userId, int $campaignId, string $title): void
{
    $stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return;
    }
    $link = 'https://ai.rateb.sa/campaign.php?id=' . $campaignId;
    $ar = rateb_user_lang($user) === 'ar';
    $subject = $ar ? 'RATEB AI — حملتك جاهزة' : 'RATEB AI — Your campaign is ready';
    $body = $ar
        ? "مرحباً {$user['name']},\n\nحملتك «{$title}» جاهزة للمراجعة.\n{$link}\n"
        : "Hi {$user['name']},\n\nYour campaign \"{$title}\" is ready.\n{$link}\n";
    rateb_mail_send($pdo, (string) $user['email'], $subject, $body, 'campaign_ready', $userId, 'campaign:' . $campaignId);
}

function rateb_notify_low_credits(PDO $pdo, int $userId, int $balance): void
{
    if ($balance > 50) {
        return;
    }
    static $sentThisRequest = [];
    if (isset($sentThisRequest[$userId])) {
        return;
    }
    $sentThisRequest[$userId] = true;
    $stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return;
    }
    $link = 'https://ai.rateb.sa/billing.php';
    $ar = rateb_user_lang($user) === 'ar';
    $subject = $ar ? 'RATEB AI — رصيدك منخفض' : 'RATEB AI — Low credit balance';
    $body = $ar
        ? "مرحباً {$user['name']},\n\nرصيدك الحالي: {$balance} نقطة.\nاشحن أو رقِّ الباقة: {$link}\n"
        : "Hi {$user['name']},\n\nYour balance is {$balance} credits.\nTop up or upgrade: {$link}\n";
    rateb_mail_send($pdo, (string) $user['email'], $subject, $body, 'low_credits', $userId, 'balance:' . $balance);
}

function rateb_notify_subscription_ending(PDO $pdo, int $userId, string $planCode, string $periodEnd): void
{
    $stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return;
    }
    $link = 'https://ai.rateb.sa/billing.php';
    $ar = rateb_user_lang($user) === 'ar';
    $subject = $ar ? 'RATEB AI — اشتراكك ينتهي قريباً' : 'RATEB AI — Subscription ending soon';
    $body = $ar
        ? "مرحباً {$user['name']},\n\nباقتك ({$planCode}) تنتهي في {$periodEnd}.\nجدد من: {$link}\n"
        : "Hi {$user['name']},\n\nYour plan ({$planCode}) ends on {$periodEnd}.\nRenew at: {$link}\n";
    rateb_mail_send($pdo, (string) $user['email'], $subject, $body, 'subscription_ending', $userId, 'plan:' . $planCode);
}