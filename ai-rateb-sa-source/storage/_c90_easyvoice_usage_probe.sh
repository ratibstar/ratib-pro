#!/bin/bash
set -euo pipefail
php <<'PHP'
<?php
declare(strict_types=1);
require '/home/admin/.secrets/rateb-ai-env.php';
require '/home/admin/domains/ai.rateb.sa/app/bootstrap.php';

$bal = (int) db()->query('SELECT credits_balance FROM users WHERE id=66')->fetchColumn();
$key = trim((string) getenv('EASYVOICE_API_KEY'));
echo 'balance_after=' . $bal . PHP_EOL;
echo 'easyvoice_key_present=' . ($key !== '' ? '1' : '0') . PHP_EOL;
echo 'easyvoice_key_len=' . strlen($key) . PHP_EOL;
echo 'easyvoice_key_prefix=' . ($key !== '' ? substr($key, 0, 3) : '') . PHP_EOL;

if ($key === '' || !str_starts_with($key, 'ev_')) {
    echo "usage_probe=BLOCKED missing_key\n";
    exit(2);
}

$payload = json_encode([
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'tools/call',
    'params' => [
        'name' => 'get_usage',
        'arguments' => new stdClass(),
    ],
], JSON_UNESCAPED_SLASHES);

$ch = curl_init('https://easyvoice.ae/api/mcp');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Accept: application/json, text/event-stream',
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$body = (string) curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo 'http_code=' . $code . PHP_EOL;
if ($err !== '') {
    echo 'curl_error=' . $err . PHP_EOL;
}

// Never echo raw body if it somehow contains the key.
$safe = str_replace($key, '[REDACTED_KEY]', $body);
$decoded = json_decode($safe, true);

$plan = null;
$limit = null;
$remaining = null;
$used = null;
$resetAt = null;

if (is_array($decoded)) {
    $result = is_array($decoded['result'] ?? null) ? $decoded['result'] : null;
    $structured = is_array($result['structuredContent'] ?? null) ? $result['structuredContent'] : null;
    if (is_array($structured)) {
        $plan = $structured['plan'] ?? null;
        $limit = $structured['limit'] ?? null;
        $remaining = $structured['remaining'] ?? null;
        $used = $structured['used'] ?? null;
        $resetAt = $structured['reset_at'] ?? null;
    } else {
        $content = is_array($result) ? ($result['content'] ?? null) : null;
        $text = null;
        if (is_array($content)) {
            foreach ($content as $part) {
                if (is_array($part) && ($part['type'] ?? '') === 'text') {
                    $text = (string) ($part['text'] ?? '');
                    break;
                }
            }
        }
        $inner = json_decode((string) $text, true);
        if (is_array($inner)) {
            $plan = $inner['plan'] ?? null;
            $limit = $inner['limit'] ?? null;
            $remaining = $inner['remaining'] ?? null;
            $used = $inner['used'] ?? null;
            $resetAt = $inner['reset_at'] ?? null;
        }
    }
}

echo 'plan=' . (is_scalar($plan) ? (string) $plan : 'null') . PHP_EOL;
echo 'limit=' . (is_scalar($limit) ? (string) $limit : 'null') . PHP_EOL;
echo 'used=' . (is_scalar($used) ? (string) $used : 'null') . PHP_EOL;
echo 'remaining=' . (is_scalar($remaining) ? (string) $remaining : 'null') . PHP_EOL;
echo 'reset_at=' . (is_scalar($resetAt) ? (string) $resetAt : 'null') . PHP_EOL;

$pass = ($code === 200)
    && ((string) $plan === 'free')
    && ((int) $limit === 5000)
    && $remaining !== null;
echo 'usage_probe=' . ($pass ? 'PASS' : 'BLOCKED') . PHP_EOL;
if (!$pass) {
    echo 'body_safe_preview=' . substr(preg_replace('/\s+/', ' ', $safe) ?? '', 0, 400) . PHP_EOL;
}
PHP
