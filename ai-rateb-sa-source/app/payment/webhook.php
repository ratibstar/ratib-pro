<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../schema.php';
require_once __DIR__ . '/../plans.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$cfg = require __DIR__ . '/../../config/ai.php';
$secret = (string) ($cfg['moyasar_secret_key'] ?? '');
$webhookSecret = (string) ($cfg['moyasar_webhook_secret'] ?? '');
$payload = json_decode((string) file_get_contents('php://input'), true);
$provided = (string) ($_SERVER['HTTP_X_MOYASAR_TOKEN'] ?? '');
if ($provided === '' && is_array($payload)) {
    $provided = (string) ($payload['secret_token'] ?? '');
}
if (!rateb_moyasar_webhook_authorized($webhookSecret, $provided) || $secret === '') {
    http_response_code(401);
    exit('Unauthorized');
}

$data = is_array($payload) && is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
$paymentId = is_array($data) ? (string) ($data['id'] ?? '') : '';
if (!preg_match('/^[a-f0-9-]{36}$/', $paymentId)) {
    http_response_code(422);
    exit('Invalid payment');
}

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
if ($http !== 200 || !is_array($payment) || (string) ($payment['id'] ?? '') !== $paymentId) {
    http_response_code(422);
    exit('Unpaid');
}

$pdo = db();
ensure_campaign_schema($pdo);
$status = (string) ($payment['status'] ?? '');
if ($status !== 'paid') {
    $meta = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];
    if (in_array($status, ['failed', 'canceled', 'cancelled', 'expired', 'voided'], true)) {
        rateb_payment_mark_failed($pdo, (int) ($meta['order_id'] ?? 0), (int) ($meta['user_id'] ?? 0));
    }
    http_response_code(200);
    echo 'pending';
    exit;
}

try {
    $result = rateb_payment_record($pdo, $payment);
} catch (Throwable $error) {
    http_response_code(500);
    exit('Could not record payment');
}
if ($result !== 'ok' && $result !== 'duplicate') {
    http_response_code(422);
    exit('Unexpected payment');
}

http_response_code(200);
echo 'ok';
