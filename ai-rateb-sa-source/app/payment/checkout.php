<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_login();

$package = (string) ($_GET['package'] ?? '');
$prices = ['starter' => 9900, 'pro' => 24900];
if (!isset($prices[$package])) {
    http_response_code(404);
    exit('Unknown package');
}

$cfg = require __DIR__ . '/../../config/ai.php';
$secret = (string) ($cfg['moyasar_secret_key'] ?? '');
if ($secret === '') {
    http_response_code(503);
    exit(ui_language() === 'ar' ? 'الدفع غير مُعد على الخادم.' : 'Payment is not configured.');
}

$body = json_encode([
    'amount' => $prices[$package],
    'currency' => 'SAR',
    'description' => 'RATEB AI ' . $package,
    'callback_url' => 'https://ai.rateb.sa/app/payment/webhook.php?token=' . rawurlencode($secret),
    'success_url' => 'https://ai.rateb.sa/dashboard.php?paid=1',
    'metadata' => [
        'user_id' => (string) (int) $_SESSION['user_id'],
        'package' => $package,
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
if ($http < 200 || $http >= 300 || !str_starts_with($url, 'https://')) {
    http_response_code(502);
    exit(ui_language() === 'ar' ? 'تعذر فتح الدفع.' : 'Checkout could not be opened.');
}

header('Location: ' . $url);
exit;
