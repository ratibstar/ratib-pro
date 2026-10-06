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

set_time_limit(45);
$campaign = media_owned_campaign((int) ($_POST['campaign_id'] ?? 0));
$jobId = (string) ($_POST['job_id'] ?? '');
if ($jobId === '' && isset($_SESSION['ai_image_jobs']) && is_array($_SESSION['ai_image_jobs'])) {
    foreach ($_SESSION['ai_image_jobs'] as $existingId => $saved) {
        if (is_array($saved) && (int) ($saved['campaign_id'] ?? 0) === (int) $campaign['id'] && time() - (int) ($saved['created'] ?? 0) < 180) {
            $jobId = (string) $existingId;
            break;
        }
    }
}
if ($jobId !== '') {
    if (preg_match('/^[a-f0-9-]{36}$/', $jobId) !== 1) {
        http_response_code(422);
        echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $saved = $_SESSION['ai_image_jobs'][$jobId] ?? null;
    if (!is_array($saved) || (int) ($saved['campaign_id'] ?? 0) !== (int) $campaign['id']) {
        http_response_code(404);
        echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (time() - (int) ($saved['created'] ?? 0) > 900) {
        unset($_SESSION['ai_image_jobs'][$jobId]);
        http_response_code(504);
        echo json_encode(['error' => rateb_ui_error('image_workers'), 'code' => 'image_workers'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    ai_horde_finish_job($campaign, $jobId);
}

$prompt = ai_campaign_image_prompt($campaign);
if (function_exists('mb_substr')) {
    $prompt = mb_substr($prompt, 0, 400);
} else {
    $prompt = substr($prompt, 0, 400);
}
if ($prompt === '') {
    http_response_code(422);
    echo json_encode(['error' => rateb_ui_error('brief_missing'), 'code' => 'brief_missing'], JSON_UNESCAPED_UNICODE);
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
    echo json_encode(['error' => rateb_ui_error('image_workers'), 'code' => 'image_workers'], JSON_UNESCAPED_UNICODE);
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
    echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider'], JSON_UNESCAPED_UNICODE);
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
        echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!is_array($state) || empty($state['done'])) {
        $wait = is_array($state) ? (int) ($state['wait_time'] ?? 0) : 0;
        echo json_encode(['pending' => true, 'job_id' => $jobId, 'wait' => $wait], JSON_UNESCAPED_UNICODE);
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
        echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $download = ai_horde_request('GET', $imageUrl);
    $finalHost = strtolower((string) parse_url($download['effective'], PHP_URL_HOST));
    if ($download['http'] !== 200 || !str_ends_with($finalHost, '.r2.cloudflarestorage.com')) {
        rateb_usage_finish(db(), (int) ($_SESSION['ai_image_jobs'][$jobId]['usage_id'] ?? 0), 'failed');
        unset($_SESSION['ai_image_jobs'][$jobId]);
        http_response_code(502);
        echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $bytes = $download['body'];
    $mime = ai_image_mime($bytes);
    $usageId = (int) ($_SESSION['ai_image_jobs'][$jobId]['usage_id'] ?? 0);
    if ($mime === null || strlen($bytes) < 32 || strlen($bytes) > media_max_bytes('image')) {
        rateb_usage_finish(db(), $usageId, 'failed');
        unset($_SESSION['ai_image_jobs'][$jobId]);
        http_response_code(502);
        echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider'], JSON_UNESCAPED_UNICODE);
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
        'redirect' => '/campaign.php?id=' . (int) $campaign['id'] . '#images',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function ai_campaign_image_prompt(array $campaign): string
{
    $rows = db()->prepare('SELECT output_type, content, approval_status FROM campaign_outputs WHERE campaign_id = ? ORDER BY id DESC');
    $rows->execute([(int) $campaign['id']]);
    $strategy = '';
    $copy = [];
    foreach ($rows as $row) {
        $type = (string) $row['output_type'];
        if ($type === 'strategy' && $strategy === '' && (string) $row['approval_status'] === 'approved') {
            $document = rateb_strategy_document((string) $row['content']);
            if ($document !== null) {
                $strategy = json_encode($document, JSON_UNESCAPED_UNICODE);
            }
        } elseif (in_array($type, rateb_copy_types(), true) && (string) $row['approval_status'] === 'approved' && !isset($copy[$type])) {
            $copy[$type] = trim((string) $row['content']);
        }
    }
    if ($strategy === '' || $copy === []) {
        http_response_code(409);
        echo json_encode(['error' => rateb_ui_error('image_locked'), 'code' => 'image_locked'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $headline = trim((string) reset($copy));
    $product = trim((string) ($campaign['product_name'] ?? ''));
    $prompt = trim('Advertising photograph, no written text. ' . $product . '. ' . $headline);
    if (function_exists('mb_substr')) {
        $prompt = mb_substr($prompt, 0, 400);
    } else {
        $prompt = substr($prompt, 0, 400);
    }
    if (trim($prompt) === '') {
        http_response_code(422);
        echo json_encode(['error' => rateb_ui_error('brief_missing'), 'code' => 'brief_missing'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return trim($prompt);
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
        CURLOPT_TIMEOUT => 12,
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
    http_response_code($http >= 400 && $http < 600 ? $http : 502);
    echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider'], JSON_UNESCAPED_UNICODE);
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
