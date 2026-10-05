<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../media/library.php';
require_once __DIR__ . '/../plans.php';
require_once __DIR__ . '/../watermark.php';
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

set_time_limit(90);
$campaign = media_owned_campaign((int) ($_POST['campaign_id'] ?? 0));
$jobId = (string) ($_POST['job_id'] ?? '');
if ($jobId !== '') {
    if (preg_match('/^[a-f0-9-]{36}$/', $jobId) !== 1) {
        http_response_code(422);
        echo json_encode(['error' => 'Image provider request failed.']);
        exit;
    }
    $saved = $_SESSION['ai_image_jobs'][$jobId] ?? null;
    if (!is_array($saved) || (int) ($saved['campaign_id'] ?? 0) !== (int) $campaign['id']) {
        http_response_code(404);
        echo json_encode(['error' => 'Image provider request failed.']);
        exit;
    }
    if (time() - (int) ($saved['created'] ?? 0) > 900) {
        unset($_SESSION['ai_image_jobs'][$jobId]);
        http_response_code(504);
        echo json_encode(['error' => 'Image generation is still queued on the free provider. Try again.']);
        exit;
    }
    ai_horde_finish_job($campaign, $jobId);
}

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
$gate = rateb_usage_begin(db(), (int) $_SESSION['user_id'], (int) $campaign['id'], 'image');
if (!$gate['ok']) {
    rateb_deny((string) $gate['code']);
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
    rateb_usage_finish(db(), (int) $gate['id'], 'failed');
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
    rateb_usage_finish(db(), (int) $gate['id'], 'failed');
    ai_horde_fail($submit['http'], $submit['body']);
}
$job = json_decode($submit['body'], true);
$jobId = is_array($job) ? (string) ($job['id'] ?? '') : '';
if (preg_match('/^[a-f0-9-]{36}$/', $jobId) !== 1) {
    rateb_usage_finish(db(), (int) $gate['id'], 'failed');
    http_response_code(502);
    echo json_encode(['error' => 'Image provider request failed.']);
    exit;
}
if (!isset($_SESSION['ai_image_jobs']) || !is_array($_SESSION['ai_image_jobs'])) {
    $_SESSION['ai_image_jobs'] = [];
}
$_SESSION['ai_image_jobs'][$jobId] = [
    'campaign_id' => (int) $campaign['id'],
    'created' => time(),
    'usage_id' => (int) $gate['id'],
];
if (count($_SESSION['ai_image_jobs']) > 3) {
    $_SESSION['ai_image_jobs'] = array_slice($_SESSION['ai_image_jobs'], -3, null, true);
}
echo json_encode(['pending' => true, 'job_id' => $jobId], JSON_UNESCAPED_UNICODE);

function ai_horde_finish_job(array $campaign, string $jobId): void
{
    $check = ai_horde_request('GET', 'https://aihorde.net/api/v2/generate/check/' . rawurlencode($jobId));
    $state = json_decode($check['body'], true);
    if (is_array($state) && !empty($state['faulted'])) {
        rateb_usage_finish(db(), (int) ($_SESSION['ai_image_jobs'][$jobId]['usage_id'] ?? 0), 'failed');
        unset($_SESSION['ai_image_jobs'][$jobId]);
        http_response_code(502);
        echo json_encode(['error' => 'Image provider request failed.']);
        exit;
    }
    if (!is_array($state) || empty($state['done'])) {
        echo json_encode(['pending' => true, 'job_id' => $jobId], JSON_UNESCAPED_UNICODE);
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
        rateb_usage_finish(db(), (int) ($_SESSION['ai_image_jobs'][$jobId]['usage_id'] ?? 0), 'failed');
        unset($_SESSION['ai_image_jobs'][$jobId]);
        http_response_code(502);
        echo json_encode(['error' => 'Image provider request failed.']);
        exit;
    }

    $download = ai_horde_request('GET', $imageUrl);
    $finalHost = strtolower((string) parse_url($download['effective'], PHP_URL_HOST));
    if ($download['http'] !== 200 || !str_ends_with($finalHost, '.r2.cloudflarestorage.com')) {
        rateb_usage_finish(db(), (int) ($_SESSION['ai_image_jobs'][$jobId]['usage_id'] ?? 0), 'failed');
        unset($_SESSION['ai_image_jobs'][$jobId]);
        http_response_code(502);
        echo json_encode(['error' => 'Image provider request failed.']);
        exit;
    }
    $bytes = $download['body'];
    $mime = ai_image_mime($bytes);
    $usageId = (int) ($_SESSION['ai_image_jobs'][$jobId]['usage_id'] ?? 0);
    if ($mime === null || strlen($bytes) < 32 || strlen($bytes) > media_max_bytes('image')) {
        rateb_usage_finish(db(), $usageId, 'failed');
        unset($_SESSION['ai_image_jobs'][$jobId]);
        http_response_code(502);
        echo json_encode(['error' => 'Image provider request failed.']);
        exit;
    }
    if (rateb_watermark_required(db(), (int) $_SESSION['user_id'])) {
        try {
            $marked = rateb_apply_watermark($bytes, $mime);
            $bytes = $marked['bytes'];
            $mime = $marked['mime'];
        } catch (Throwable $error) {
            rateb_usage_finish(db(), $usageId, 'failed');
            unset($_SESSION['ai_image_jobs'][$jobId]);
            rateb_deny('watermark');
        }
    }

    try {
        media_store_bytes($campaign, 'image', 'ai', 'ai-image.' . media_kind_mimes()['image'][$mime], $mime, $bytes);
    } catch (Throwable $error) {
        rateb_usage_finish(db(), $usageId, 'failed');
        http_response_code(500);
        echo json_encode(['error' => 'Could not save the image file.']);
        exit;
    }
    rateb_usage_finish(db(), $usageId, 'completed');
    unset($_SESSION['ai_image_jobs'][$jobId]);
    echo json_encode([
        'ok' => true,
        'model' => $modelName,
        'mime' => $mime,
        'redirect' => '/campaign.php?id=' . (int) $campaign['id'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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
