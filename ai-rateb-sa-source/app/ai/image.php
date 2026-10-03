<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../media/library.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
$csrf = (string) ($_POST['csrf'] ?? $_POST['csrf_token'] ?? '');
$expected = (string) ($_SESSION['csrf_token'] ?? $_SESSION['csrf'] ?? '');
if ($expected === '' || !hash_equals($expected, $csrf)) {
    http_response_code(419);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

set_time_limit(240);
$campaign = media_owned_campaign((int) ($_POST['campaign_id'] ?? 0));
$parts = [];
foreach (['product_name', 'title', 'description'] as $field) {
    $value = trim((string) ($campaign[$field] ?? ''));
    if ($value !== '') {
        $parts[] = $value;
    }
}
$prompt = trim(implode('. ', $parts));
if (function_exists('mb_substr')) {
    $prompt = mb_substr($prompt, 0, 400);
} else {
    $prompt = substr($prompt, 0, 400);
}
if ($prompt === '') {
    http_response_code(422);
    echo json_encode(['error' => 'The campaign has no text to illustrate.']);
    exit;
}

$models = ai_horde_request('GET', 'https://aihorde.net/api/v2/status/models?type=image');
$modelList = json_decode($models['body'], true);
$workers = 0;
if (is_array($modelList)) {
    foreach ($modelList as $row) {
        if (is_array($row) && ($row['name'] ?? '') === 'Deliberate') {
            $workers = (int) ($row['count'] ?? 0);
            break;
        }
    }
}
if ($models['http'] !== 200 || $workers < 1) {
    http_response_code(503);
    echo json_encode(['error' => 'The free image model has no workers right now. Try again later.']);
    exit;
}

$payload = json_encode([
    'prompt' => $prompt,
    'params' => ['width' => 512, 'height' => 512, 'steps' => 15, 'n' => 1],
    'nsfw' => false,
    'censor_nsfw' => true,
    'r2' => true,
    'models' => ['Deliberate'],
], JSON_UNESCAPED_UNICODE);

$submit = ai_horde_request('POST', 'https://aihorde.net/api/v2/generate/async', $payload);
if ($submit['http'] !== 202) {
    ai_horde_fail($submit['http'], $submit['body']);
}
$job = json_decode($submit['body'], true);
$jobId = is_array($job) ? (string) ($job['id'] ?? '') : '';
if (preg_match('/^[a-f0-9-]{36}$/', $jobId) !== 1) {
    http_response_code(502);
    echo json_encode(['error' => 'Image provider request failed.']);
    exit;
}

$done = false;
for ($attempt = 0; $attempt < 90; $attempt++) {
    sleep(2);
    $check = ai_horde_request('GET', 'https://aihorde.net/api/v2/generate/check/' . rawurlencode($jobId));
    $state = json_decode($check['body'], true);
    if (is_array($state) && !empty($state['faulted'])) {
        http_response_code(502);
        echo json_encode(['error' => 'Image provider request failed.']);
        exit;
    }
    if (is_array($state) && !empty($state['done'])) {
        $done = true;
        break;
    }
}
if (!$done) {
    http_response_code(504);
    echo json_encode(['error' => 'Image generation is still queued on the free provider. Try again.']);
    exit;
}

$status = ai_horde_request('GET', 'https://aihorde.net/api/v2/generate/status/' . rawurlencode($jobId));
$result = json_decode($status['body'], true);
$imageUrl = '';
$modelName = '';
if (is_array($result)) {
    $imageUrl = (string) ($result['generations'][0]['img'] ?? '');
    $modelName = (string) ($result['generations'][0]['model'] ?? '');
}
$host = strtolower((string) parse_url($imageUrl, PHP_URL_HOST));
if (parse_url($imageUrl, PHP_URL_SCHEME) !== 'https' || !str_ends_with($host, '.r2.cloudflarestorage.com')) {
    http_response_code(502);
    echo json_encode(['error' => 'Image provider request failed.']);
    exit;
}

$download = ai_horde_request('GET', $imageUrl);
$finalHost = strtolower((string) parse_url($download['effective'], PHP_URL_HOST));
if ($download['http'] !== 200 || !str_ends_with($finalHost, '.r2.cloudflarestorage.com')) {
    http_response_code(502);
    echo json_encode(['error' => 'Image provider request failed.']);
    exit;
}
$bytes = $download['body'];
$mime = ai_image_mime($bytes);
if ($mime === null || strlen($bytes) < 32 || strlen($bytes) > media_max_bytes('image')) {
    http_response_code(502);
    echo json_encode(['error' => 'Image provider request failed.']);
    exit;
}

try {
    media_store_bytes($campaign, 'image', 'ai', 'ai-image.' . media_kind_mimes()['image'][$mime], $mime, $bytes);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not save the image file.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'model' => $modelName,
    'mime' => $mime,
    'redirect' => '/campaign.php?id=' . (int) $campaign['id'],
], JSON_UNESCAPED_UNICODE);

function ai_horde_request(string $method, string $url, ?string $body = null): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json, image/webp, image/jpeg, image/png', 'Client-Agent: rateb-ai-campaign:1.0:ai.rateb.sa'];
    if (strtolower((string) parse_url($url, PHP_URL_HOST)) === 'aihorde.net') {
        $headers[] = 'apikey: 0000000000';
    }
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effective = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return ['http' => $http, 'body' => $response === false ? '' : (string) $response, 'effective' => $effective];
}

function ai_horde_fail(int $http, string $body): void
{
    $decoded = json_decode($body, true);
    $message = is_array($decoded) ? (string) ($decoded['message'] ?? 'Image provider request failed.') : 'Image provider request failed.';
    $message = preg_replace('/sk-[A-Za-z0-9_\-]{6,}/', '[redacted]', $message) ?? $message;
    $message = preg_replace('/gsk_[A-Za-z0-9_\-]{6,}/', '[redacted]', $message) ?? $message;
    http_response_code($http >= 400 && $http < 600 ? $http : 502);
    echo json_encode(['error' => substr($message, 0, 180)]);
    exit;
}

function ai_image_mime(string $bytes): ?string
{
    if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
        $magic = 'image/jpeg';
    } elseif (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
        $magic = 'image/png';
    } elseif (strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
        $magic = 'image/webp';
    } else {
        return null;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detected = strtolower((string) $finfo->buffer($bytes));
    if ($detected !== $magic && !in_array($detected, ['application/octet-stream', 'image/webp', 'image/jpeg', 'image/png'], true)) {
        return null;
    }
    return isset(media_kind_mimes()['image'][$magic]) ? $magic : null;
}
