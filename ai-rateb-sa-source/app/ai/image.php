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
$now = time();
$userId = (int) $_SESSION['user_id'];
$campaignId = (int) $campaign['id'];
$postedJobId = strtolower(trim((string) ($_POST['job_id'] ?? '')));
try {
    $decision = rateb_image_jobs_transaction($userId, static function (array $jobs) use ($userId, $campaignId, $postedJobId, $now): array {
        $decision = rateb_image_jobs_decide(ai_image_jobs_include_session($jobs, $userId), $userId, $campaignId, $postedJobId, $now);
        foreach ($decision['finish'] as $usageId) {
            rateb_usage_finish(db(), (int) $usageId, 'failed');
        }
        return ['jobs' => $decision['jobs'], 'result' => $decision];
    });
} catch (RuntimeException $error) {
    if ($error->getMessage() !== 'image_job_store') {
        throw $error;
    }
    http_response_code(503);
    echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider', 'state' => 'failed'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!is_array($decision)) {
    $decision = ['action' => 'reject', 'code' => 'image_provider'];
}
ai_image_dispatch($campaign, $decision, $postedJobId, $now);

$ratio = (string) ($_POST['ratio'] ?? '1:1');
$ratioSize = match ($ratio) {
    '4:5' => [512, 640],
    '9:16' => [576, 1024],
    default => [512, 512],
};
if (!in_array($ratio, ['1:1', '4:5', '9:16'], true)) {
    $ratio = '1:1';
}
$prompt = ai_campaign_image_prompt($campaign);
$prompt = 'ratio ' . $ratio . '. ' . $prompt;
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
$reservedId = 0;
try {
    $decision = rateb_image_jobs_transaction($userId, static function (array $jobs) use ($userId, $campaignId, $postedJobId, $now, &$reservedId): array {
        $decision = rateb_image_jobs_decide(ai_image_jobs_include_session($jobs, $userId), $userId, $campaignId, $postedJobId, $now);
        foreach ($decision['finish'] as $usageId) {
            rateb_usage_finish(db(), (int) $usageId, 'failed');
        }
        if (!rateb_image_job_allows_new_reservation($decision)) {
            return ['jobs' => $decision['jobs'], 'result' => $decision];
        }
        $gate = rateb_usage_begin(db(), $userId, $campaignId, 'image');
        if (!$gate['ok']) {
            $decision['action'] = 'denied';
            $decision['code'] = (string) $gate['code'];
            return ['jobs' => $decision['jobs'], 'result' => $decision];
        }
        $decision = rateb_image_jobs_claim($decision['jobs'], $userId, $campaignId, $postedJobId, $now, (int) $gate['id']);
        if (!rateb_image_job_allows_new_reservation($decision)) {
            rateb_usage_finish(db(), (int) $gate['id'], 'failed');
            return ['jobs' => $decision['jobs'], 'result' => $decision];
        }
        $reservedId = (int) $gate['id'];
        return ['jobs' => $decision['jobs'], 'result' => $decision];
    });
} catch (RuntimeException $error) {
    if ($error->getMessage() !== 'image_job_store') {
        throw $error;
    }
    if ($reservedId > 0) {
        rateb_usage_finish(db(), $reservedId, 'failed');
    }
    http_response_code(503);
    echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider', 'state' => 'failed'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!is_array($decision)) {
    $decision = ['action' => 'reject', 'code' => 'image_provider'];
}
if ((string) ($decision['action'] ?? '') === 'denied') {
    rateb_deny((string) ($decision['code'] ?? 'plan_inactive'));
}
ai_image_dispatch($campaign, $decision, $postedJobId, $now);
$usageId = (int) ($decision['usage_id'] ?? 0);
if ($usageId < 1 || !rateb_image_job_allows_new_reservation($decision)) {
    http_response_code(503);
    echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider', 'state' => 'failed'], JSON_UNESCAPED_UNICODE);
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
    ai_image_release($userId, '', $usageId, 'failed');
    http_response_code(503);
    echo json_encode(['error' => rateb_ui_error('image_workers'), 'code' => 'image_workers'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_encode([
    'prompt' => $prompt,
    'params' => ['width' => $ratioSize[0], 'height' => $ratioSize[1], 'steps' => 15, 'n' => 1],
    'nsfw' => false,
    'censor_nsfw' => true,
    'r2' => true,
    'models' => ['Deliberate'],
], JSON_UNESCAPED_UNICODE);

$submit = ai_horde_request('POST', 'https://aihorde.net/api/v2/generate/async', $payload);
if ($submit['http'] !== 202) {
    ai_image_release($userId, '', $usageId, 'failed');
    ai_horde_fail($submit['http'], $submit['body']);
}
$job = json_decode($submit['body'], true);
$jobId = is_array($job) ? strtolower((string) ($job['id'] ?? '')) : '';
if ($postedJobId === '' && !rateb_check_and_deduct_credits($userId, 'ai_image', $campaignId)) {
    ai_image_release($userId, '', $usageId, 'failed');
    rateb_credit_stop();
}
if (preg_match('/^[a-f0-9-]{36}$/', $jobId) !== 1) {
    if ($postedJobId === '') {
        rateb_refund_credits($userId, 'ai_image', $campaignId);
    }
    ai_image_release($userId, '', $usageId, 'failed');
    http_response_code(502);
    echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider'], JSON_UNESCAPED_UNICODE);
    exit;
}
$record = rateb_image_job_record($jobId, $userId, $campaignId, $usageId, $now, 'pending');
try {
    rateb_image_jobs_transaction($userId, static function (array $jobs) use ($record): array {
        return ['jobs' => rateb_image_jobs_attach($jobs, $record), 'result' => null];
    });
} catch (RuntimeException $error) {
    if ($error->getMessage() !== 'image_job_store') {
        throw $error;
    }
    ai_image_bind($record);
    ai_image_pending($jobId);
}
ai_image_bind($record);
if (!isset($_SESSION['ai_image_jobs']) || !is_array($_SESSION['ai_image_jobs'])) {
    $_SESSION['ai_image_jobs'] = [];
}
$_SESSION['ai_image_jobs'] = rateb_image_session_trim($_SESSION['ai_image_jobs'], 3);
ai_image_pending($jobId);

function ai_image_jobs_include_session(array $jobs, int $userId): array
{
    $session = $_SESSION['ai_image_jobs'] ?? [];
    if (!is_array($session)) {
        return $jobs;
    }
    foreach ($session as $jobId => $saved) {
        if (!is_array($saved)) {
            continue;
        }
        $record = rateb_image_job_record((string) $jobId, $userId, (int) ($saved['campaign_id'] ?? 0), (int) ($saved['usage_id'] ?? 0), (int) ($saved['created'] ?? 0));
        if ($record['job_id'] === '' || $record['usage_id'] < 1 || isset($jobs[$record['job_id']])) {
            continue;
        }
        $jobs[$record['job_id']] = $record;
    }
    return $jobs;
}

function ai_image_bind(array $job): void
{
    $jobId = (string) ($job['job_id'] ?? '');
    if ($jobId === '') {
        return;
    }
    if (!isset($_SESSION['ai_image_jobs']) || !is_array($_SESSION['ai_image_jobs'])) {
        $_SESSION['ai_image_jobs'] = [];
    }
    $_SESSION['ai_image_jobs'][$jobId] = [
        'campaign_id' => (int) ($job['campaign_id'] ?? 0),
        'created' => (int) ($job['created'] ?? 0),
        'usage_id' => (int) ($job['usage_id'] ?? 0),
    ];
}

function ai_image_release(int $userId, string $jobId, int $usageId, string $status): void
{
    if ($usageId > 0) {
        rateb_usage_finish(db(), $usageId, $status);
    }
    if ($jobId !== '') {
        unset($_SESSION['ai_image_jobs'][$jobId]);
    }
    if ($userId < 1) {
        return;
    }
    rateb_image_jobs_transaction($userId, static function (array $jobs) use ($jobId, $usageId): array {
        return ['jobs' => rateb_image_jobs_remove($jobs, $jobId, $usageId), 'result' => null];
    });
}

/** @param array<string,mixed> $decision */
function ai_image_dispatch(array $campaign, array $decision, string $postedJobId, int $now): void
{
    $action = (string) ($decision['action'] ?? '');
    if ($action === 'begin' || $action === 'denied') {
        return;
    }
    if ($action === 'resume' && is_array($decision['job'] ?? null)) {
        ai_image_bind($decision['job']);
        ai_horde_finish_job($campaign, (string) $decision['job']['job_id']);
    }
    if ($action === 'wait') {
        $created = (int) (is_array($decision['job'] ?? null) ? ($decision['job']['created'] ?? $now) : $now);
        echo json_encode([
            'pending' => true,
            'state' => 'pending',
            'job_id' => '',
            'wait' => 3,
            'expires_at' => $created + rateb_image_job_ttl(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'expire') {
        http_response_code(504);
        echo json_encode(['error' => rateb_ui_error('image_timeout'), 'code' => 'image_timeout', 'state' => 'timeout'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(preg_match('/^[a-f0-9-]{36}$/', $postedJobId) === 1 ? 404 : 422);
    echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider', 'state' => 'failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

function ai_horde_finish_job(array $campaign, string $jobId): void
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $usageId = (int) ($_SESSION['ai_image_jobs'][$jobId]['usage_id'] ?? 0);
    $check = ai_horde_request('GET', 'https://aihorde.net/api/v2/generate/check/' . rawurlencode($jobId));
    $state = json_decode($check['body'], true);
    if (is_array($state) && !empty($state['faulted'])) {
        ai_image_release($userId, $jobId, $usageId, 'failed');
        http_response_code(502);
        echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!is_array($state) || empty($state['done'])) {
        $wait = is_array($state) ? (int) ($state['wait_time'] ?? 0) : 0;
        ai_image_pending($jobId, $wait);
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
        ai_image_release($userId, $jobId, $usageId, 'failed');
        http_response_code(502);
        echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $download = ai_horde_request('GET', $imageUrl);
    $finalHost = strtolower((string) parse_url($download['effective'], PHP_URL_HOST));
    if ($download['http'] !== 200 || !str_ends_with($finalHost, '.r2.cloudflarestorage.com')) {
        ai_image_release($userId, $jobId, $usageId, 'failed');
        http_response_code(502);
        echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $bytes = $download['body'];
    $mime = ai_image_mime($bytes);
    if ($mime === null || strlen($bytes) < 32 || strlen($bytes) > media_max_bytes('image')) {
        ai_image_release($userId, $jobId, $usageId, 'failed');
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
            ai_image_release($userId, $jobId, $usageId, 'failed');
            rateb_deny('watermark');
        }
    }

    try {
        $ratioName = str_replace(':', 'x', in_array((string) ($_POST['ratio'] ?? '1:1'), ['1:1', '4:5', '9:16'], true) ? (string) $_POST['ratio'] : '1:1');
        media_store_bytes($campaign, 'image', 'ai', 'ratio-' . $ratioName . '-ai-image.' . media_kind_mimes()['image'][$mime], $mime, $bytes);
    } catch (Throwable $error) {
        rateb_usage_finish(db(), $usageId, 'failed');
        http_response_code(500);
        echo json_encode(['error' => 'Could not save the image file.']);
        exit;
    }
    ai_image_release($userId, $jobId, $usageId, 'completed');
    echo json_encode([
        'ok' => true,
        'state' => 'ready',
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
    $document = rateb_strategy_document($strategy) ?? [];
    $prefer = (string) (rateb_output_language(db(), $campaign)['speech'] ?? 'ar');
    $copyLine = '';
    foreach (array_merge(['headline'], rateb_copy_types()) as $type) {
        if (empty($copy[$type])) {
            continue;
        }
        $copyLine = rateb_prompt_plain($copy[$type], $prefer);
        if ($copyLine !== '') {
            break;
        }
    }
    $brand = [];
    $brandStmt = db()->prepare('SELECT brand_name, tone, primary_color, secondary_color FROM brand_kits WHERE user_id = ?');
    $brandStmt->execute([(int) ($campaign['user_id'] ?? 0)]);
    $brandRow = $brandStmt->fetch();
    if ($brandRow) {
        $brand = $brandRow;
    }
    $prompt = rateb_visual_prompt(
        array_merge(['Advertising photograph'], rateb_visual_lines($campaign, $document, $copyLine, $brand, $prefer)),
        'No written text and no logo.',
        400
    );
    if (trim($prompt) === '') {
        http_response_code(422);
        echo json_encode(['error' => rateb_ui_error('brief_missing'), 'code' => 'brief_missing'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return trim($prompt);
}

function ai_image_pending(string $jobId, int $wait = 0): void
{
    $created = (int) ($_SESSION['ai_image_jobs'][$jobId]['created'] ?? time());
    echo json_encode([
        'pending' => true,
        'state' => 'pending',
        'job_id' => $jobId,
        'wait' => $wait,
        'expires_at' => $created + rateb_image_job_ttl(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function ai_horde_request(string $method, string $url, ?string $body = null): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json, image/webp, image/jpeg, image/png', 'Client-Agent: rateb-ai-campaign:1.0:ai.rateb.sa'];
    if (strtolower((string) parse_url($url, PHP_URL_HOST)) === 'aihorde.net') {
        $ai = require __DIR__ . '/../../config/ai.php';
        $hordeKey = trim((string) ($ai['horde_api_key'] ?? ''));
        if ($hordeKey === '') {
            curl_close($ch);
            return ['http' => 503, 'body' => '', 'effective' => $url];
        }
        $headers[] = 'apikey: ' . $hordeKey;
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
