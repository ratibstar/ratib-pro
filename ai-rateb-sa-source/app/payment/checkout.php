<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../plans.php';
require_once __DIR__ . '/../nav.php';
require_login();

$packageCode = (string) ($_POST['package'] ?? $_GET['package'] ?? '');
$campaignId = (int) ($_POST['campaign'] ?? $_GET['campaign'] ?? 0);
$selected = rateb_paid_package($packageCode);
if ($selected === null) {
    http_response_code(404);
    exit('Unknown package');
}

$cfg = require __DIR__ . '/../../config/ai.php';
$secret = (string) ($cfg['moyasar_secret_key'] ?? '');
$configured = rateb_moyasar_configured($cfg);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$configured) {
    http_response_code(503);
    exit(ui_language() === 'ar' ? 'الدفع غير مُعد على الخادم.' : 'Payment is not configured.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $configured) {
    verify_csrf();
    $pdo = db();
    $pdo->prepare('INSERT INTO payment_orders (user_id, package_code, amount, credits, currency, status) VALUES (?, ?, ?, ?, \'SAR\', \'pending\')')
        ->execute([(int) $_SESSION['user_id'], $packageCode, $selected['amount'], $selected['credits']]);
    $orderId = (int) $pdo->lastInsertId();
    $return = 'https://ai.rateb.sa/app/payment/return.php?order=' . $orderId . ($campaignId > 0 ? '&campaign=' . $campaignId : '');
    $body = json_encode([
        'amount' => $selected['amount'],
        'currency' => 'SAR',
        'description' => 'RATEB AI ' . $packageCode,
        'callback_url' => $return,
        'success_url' => $return,
        'metadata' => [
            'user_id' => (string) (int) $_SESSION['user_id'],
            'package' => $packageCode,
            'order_id' => (string) $orderId,
        ],
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://api.moyasar.com/v1/invoices');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $secret . ':',
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $invoice = json_decode((string) $raw, true);
    $url = is_array($invoice) ? (string) ($invoice['url'] ?? '') : '';
    $invoiceId = is_array($invoice) ? (string) ($invoice['id'] ?? '') : '';
    if ($http < 200 || $http >= 300 || !str_starts_with($url, 'https://')) {
        rateb_payment_mark_failed($pdo, $orderId, (int) $_SESSION['user_id']);
        http_response_code(502);
        exit(ui_language() === 'ar' ? 'تعذر فتح الدفع.' : 'Checkout could not be opened.');
    }
    if ($invoiceId !== '') {
        $pdo->prepare('UPDATE payment_orders SET moyasar_invoice_id = ? WHERE id = ? AND user_id = ?')->execute([$invoiceId, $orderId, (int) $_SESSION['user_id']]);
    }
    header('Location: ' . $url);
    exit;
}

$arabic = ui_language() === 'ar';
$price = number_format($selected['amount'] / 100, 0);
$title = $packageCode === 'starter' ? ($arabic ? 'باقة الانطلاق' : 'Starter') : ($arabic ? 'الباقة الاحترافية' : 'Pro');
echo '<!doctype html><html lang="' . ($arabic ? 'ar' : 'en') . '" dir="' . ($arabic ? 'rtl' : 'ltr') . '" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<title>RATEB AI</title><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800&display=swap"><link rel="stylesheet" href="/public/assets/css/theme.css"><link rel="stylesheet" href="/public/assets/css/app.css?v=nav40"></head>';
echo '<body class="studio">';
rateb_site_header('/credits.php', true);
echo '<main class="container studio-page"><p class="eyebrow">' . ($arabic ? 'الدفع عبر ميسر' : 'Payments by Moyasar') . '</p>';
echo '<h1>' . ($campaignId > 0 ? ($arabic ? 'ادفع لتنزيل ملف الحملة' : 'Pay to download the campaign file') : e($title)) . '</h1><div class="price-grid"><article class="price-card is-featured">';
echo '<p class="price-amount"><b>' . e($price) . '</b> <span>' . ($arabic ? 'ر.س' : 'SAR') . '</span></p>';
echo '<p>' . (int) $selected['credits'] . ' ' . ($arabic ? 'نقطة' : 'credits') . '</p>';
if (!$configured) {
    echo '<p class="alert">' . ($arabic ? 'الدفع الإلكتروني غير متاح مؤقتًا.' : 'Online payment is temporarily unavailable.') . '</p>';
    echo '<button class="button" type="button" disabled>' . ($arabic ? 'تأكيد الشراء' : 'Confirm purchase') . '</button>';
} else {
    echo '<form method="post"><input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '"><input type="hidden" name="package" value="' . e($packageCode) . '"><input type="hidden" name="campaign" value="' . $campaignId . '">';
    echo '<button class="button" type="submit">' . ($arabic ? 'تأكيد الشراء عبر ميسر' : 'Confirm purchase with Moyasar') . '</button></form>';
}
echo '</article></div><p><a href="/credits.php">' . ($arabic ? 'رجوع إلى الباقات' : 'Back to packages') . '</a></p></main>';
echo '<script src="/public/assets/js/app.js?v=nav42"></script></body></html>';
