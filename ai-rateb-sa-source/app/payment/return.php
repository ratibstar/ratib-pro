<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../plans.php';
require_once __DIR__ . '/../nav.php';
require_login();

$orderId = (int) ($_GET['order'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$pdo = db();
$order = $pdo->prepare('SELECT id, package_code, amount, credits, currency, status FROM payment_orders WHERE id = ? AND user_id = ? LIMIT 1');
$order->execute([$orderId, $userId]);
$row = $order->fetch();
if (!$row) {
    http_response_code(404);
    exit('Unknown payment');
}

$paymentId = (string) ($_GET['id'] ?? '');
if (preg_match('/^[a-f0-9-]{36}$/', $paymentId) === 1 && (string) $row['status'] === 'pending') {
    $cfg = require __DIR__ . '/../../config/ai.php';
    $secret = (string) ($cfg['moyasar_secret_key'] ?? '');
    if ($secret !== '') {
        $ch = curl_init('https://api.moyasar.com/v1/payments/' . rawurlencode($paymentId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $secret . ':',
            CURLOPT_TIMEOUT => 12,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $payment = json_decode((string) $raw, true);
        if ($http === 200 && is_array($payment) && (string) ($payment['id'] ?? '') === $paymentId) {
            $status = (string) ($payment['status'] ?? '');
            if ($status === 'paid') {
                rateb_payment_record($pdo, $payment);
            } elseif (in_array($status, ['failed', 'canceled', 'cancelled', 'expired', 'voided'], true)) {
                rateb_payment_mark_failed($pdo, $orderId, $userId);
            }
        }
    }
    $order->execute([$orderId, $userId]);
    $row = $order->fetch() ?: $row;
}

$arabic = ui_language() === 'ar';
$labels = [
    'paid' => $arabic ? 'تم الدفع بنجاح' : 'Payment successful',
    'failed' => $arabic ? 'لم يكتمل الدفع' : 'Payment failed or canceled',
    'pending' => $arabic ? 'الدفع قيد التأكيد' : 'Payment pending',
];
$status = (string) $row['status'];
$wallet = rateb_account_credits($pdo, $userId);
echo '<!doctype html><html lang="' . ($arabic ? 'ar' : 'en') . '" dir="' . ($arabic ? 'rtl' : 'ltr') . '" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<title>RATEB AI</title><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800&display=swap"><link rel="stylesheet" href="/public/assets/css/theme.css"><link rel="stylesheet" href="/public/assets/css/app.css?v=nav40"></head>';
echo '<body class="studio">';
rateb_site_header('/credits.php', true);
echo '<main class="container studio-page"><h1>' . e($labels[$status] ?? $labels['pending']) . '</h1>';
echo '<p>' . e((string) $row['package_code']) . ' — ' . number_format(((int) $row['amount']) / 100, 2) . ' SAR — ' . (int) $row['credits'] . ' ' . ($arabic ? 'نقطة' : 'credits') . '</p>';
echo '<p>' . ($arabic ? 'الرصيد الحالي' : 'Current balance') . ': ' . (int) $wallet['credits_balance'] . '</p>';
echo '<p><a class="button" href="/dashboard.php">' . ($arabic ? 'لوحة التحكم' : 'Dashboard') . '</a></p></main>';
echo '<script src="/public/assets/js/app.js?v=nav42"></script></body></html>';
