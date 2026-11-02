<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../plans.php';
require_login();

$packageCode = (string) ($_POST['package'] ?? $_GET['package'] ?? '');
$selected = rateb_paid_package($packageCode);
if ($selected === null) {
    http_response_code(404);
    exit('Unknown package');
}

$cfg = require __DIR__ . '/../../config/ai.php';
$secret = (string) ($cfg['moyasar_secret_key'] ?? '');
if ($secret === '') {
    http_response_code(503);
    exit(ui_language() === 'ar' ? 'الدفع غير مُعد على الخادم.' : 'Payment is not configured.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pdo = db();
    $pdo->prepare('INSERT INTO payment_orders (user_id, package_code, amount, credits, currency, status) VALUES (?, ?, ?, ?, \'SAR\', \'pending\')')
        ->execute([(int) $_SESSION['user_id'], $packageCode, $selected['amount'], $selected['credits']]);
    $orderId = (int) $pdo->lastInsertId();
    $return = 'https://ai.rateb.sa/app/payment/return.php?order=' . $orderId;
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
$price = number_format($selected['amount'] / 100, 2);
$title = $packageCode === 'starter' ? ($arabic ? 'باقة الانطلاق' : 'Starter') : ($arabic ? 'الباقة الاحترافية' : 'Pro');
echo '<!doctype html><html lang="' . ($arabic ? 'ar' : 'en') . '" dir="' . ($arabic ? 'rtl' : 'ltr') . '"><head><meta charset="utf-8"><title>RATEB AI</title></head><body>';
echo '<h1>' . e($title) . '</h1>';
echo '<p>' . e($price) . ' SAR</p>';
echo '<p>' . (int) $selected['credits'] . ' ' . ($arabic ? 'نقطة' : 'credits') . '</p>';
echo '<form method="post"><input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '"><input type="hidden" name="package" value="' . e($packageCode) . '">';
echo '<button type="submit">' . ($arabic ? 'المتابعة إلى الدفع' : 'Continue to payment') . '</button></form>';
echo '<p><a href="/dashboard.php">' . ($arabic ? 'رجوع' : 'Back') . '</a></p></body></html>';
