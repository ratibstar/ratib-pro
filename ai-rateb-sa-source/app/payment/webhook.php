<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../schema.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$cfg = require __DIR__ . '/../../config/ai.php';
$secret = (string) ($cfg['moyasar_secret_key'] ?? '');
$token = (string) ($_GET['token'] ?? ($_SERVER['HTTP_X_MOYASAR_TOKEN'] ?? ''));
if ($secret === '' || $token === '' || !hash_equals($secret, $token)) {
    http_response_code(401);
    exit('Unauthorized');
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$paymentId = is_array($payload) ? (string) ($payload['id'] ?? '') : '';
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
if ($http !== 200 || !is_array($payment) || (string) ($payment['status'] ?? '') !== 'paid') {
    http_response_code(422);
    exit('Unpaid');
}

$meta = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];
$userId = (int) ($meta['user_id'] ?? 0);
$package = (string) ($meta['package'] ?? '');
$amount = (int) ($payment['amount'] ?? 0);
$expected = ['starter' => 9900, 'pro' => 24900];
$credits = ['starter' => 500, 'pro' => 2500];
if ($userId < 1 || !isset($expected[$package]) || $amount !== $expected[$package]) {
    http_response_code(422);
    exit('Unexpected payment');
}

$pdo = db();
ensure_campaign_schema($pdo);
$pdo->beginTransaction();
try {
    $known = $pdo->prepare('SELECT id FROM payment_receipts WHERE moyasar_id = ?');
    $known->execute([$paymentId]);
    if ($known->fetch()) {
        $pdo->commit();
        http_response_code(200);
        echo 'ok';
        exit;
    }
    $pdo->prepare('INSERT INTO payment_receipts (moyasar_id, user_id, package_type, amount) VALUES (?, ?, ?, ?)')
        ->execute([$paymentId, $userId, $package, $amount]);
    $pdo->prepare('UPDATE users SET package_type = ?, credits_balance = credits_balance + ? WHERE id = ?')
        ->execute([$package, $credits[$package], $userId]);
    $pdo->prepare('INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted) VALUES (?, NULL, ?, 1, \'completed\', ?, 0)')
        ->execute([$userId, 'payment_' . $package, 'payment_' . $package]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    exit('Could not record payment');
}

http_response_code(200);
echo 'ok';
