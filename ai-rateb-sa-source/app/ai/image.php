<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../media/library.php';
require_once __DIR__ . '/../plans.php';
require_once __DIR__ . '/../watermark.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $campaign = media_owned_campaign((int) ($_GET['campaign_id'] ?? 0));
    $since = (int) ($_GET['since_id'] ?? 0);
    $latest = db()->prepare('SELECT id FROM campaign_media WHERE campaign_id = ? AND user_id = ? AND kind = ? AND source = ? AND id > ? ORDER BY id DESC LIMIT 1');
    $latest->execute([(int) $campaign['id'], (int) $_SESSION['user_id'], 'image', 'ai', $since]);
    $mediaId = (int) $latest->fetchColumn();
    echo json_encode($mediaId > 0
        ? ['ok' => true, 'ready' => true, 'redirect' => '/campaign.php?id=' . (int) $campaign['id'] . '#images']
        : ['ready' => false], JSON_UNESCAPED_UNICODE);
    exit;
}
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

set_time_limit(120);
$campaign = media_owned_campaign((int) ($_POST['campaign_id'] ?? 0));
$now = time();
$userId = (int) $_SESSION['user_id'];
$campaignId = (int) $campaign['id'];
$postedJobId = strtolower(trim((string) ($_POST['job_id'] ?? '')));
rateb_reservation_sweep(db(), $userId, 'image', 'ai_image', rateb_image_reservation_max_age());
try {
    $decision = rateb_image_jobs_transaction($userId, static function (array $jobs) use ($userId, $campaignId, $postedJobId, $now): array {
        $decision = rateb_image_jobs_decide(ai_image_jobs_include_session($jobs, $userId), $userId, $campaignId, $postedJobId, $now);
        ai_image_settle_dropped($userId, $decision);
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
$ratioLead = 'ratio ' . $ratio . '. ';
$prompt = $ratioLead . ai_campaign_image_prompt($campaign, 900 - mb_strlen($ratioLead));
if ($prompt === $ratioLead) {
    http_response_code(422);
    echo json_encode(['error' => rateb_ui_error('brief_missing'), 'code' => 'brief_missing'], JSON_UNESCAPED_UNICODE);
    exit;
}
$imageSettings = ai_image_settings();
if ($imageSettings['provider'] === 'cloudflare' && !$imageSettings['configured']) {
    http_response_code(503);
    echo json_encode(['error' => rateb_ui_error('image_workers'), 'code' => 'image_workers'], JSON_UNESCAPED_UNICODE);
    exit;
}
$reservedId = 0;
try {
    $decision = rateb_image_jobs_transaction($userId, static function (array $jobs) use ($userId, $campaignId, $postedJobId, $now, &$reservedId): array {
        $decision = rateb_image_jobs_decide(ai_image_jobs_include_session($jobs, $userId), $userId, $campaignId, $postedJobId, $now);
        ai_image_settle_dropped($userId, $decision);
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

if ($imageSettings['provider'] === 'cloudflare') {
    ai_cloudflare_generate($campaign, $userId, $campaignId, $usageId, $prompt, $imageSettings);
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

$charge = rateb_reservation_charge(db(), $usageId, $userId, 'ai_image', $campaignId);
if ($charge !== 'ok') {
    ai_image_release($userId, '', $usageId, 'failed');
    if ($charge === 'credits') {
        rateb_credit_stop($userId, 'ai_image');
    }
    http_response_code(409);
    echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider', 'state' => 'failed'], JSON_UNESCAPED_UNICODE);
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
if (preg_match('/^[a-f0-9-]{36}$/', $jobId) !== 1) {
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
    if ($jobId !== '') {
        unset($_SESSION['ai_image_jobs'][$jobId]);
    }
    $usageIds = $usageId > 0 ? [$usageId] : [];
    if ($userId > 0) {
        try {
            $removed = rateb_image_jobs_transaction($userId, static function (array $jobs) use ($jobId, $usageId): array {
                $taken = rateb_image_jobs_take($jobs, $jobId, $usageId);
                return ['jobs' => $taken['jobs'], 'result' => $taken['removed']];
            });
            foreach (is_array($removed) ? $removed : [] as $job) {
                $usageIds[] = (int) ($job['usage_id'] ?? 0);
            }
        } catch (RuntimeException $error) {
            if ($error->getMessage() !== 'image_job_store') {
                throw $error;
            }
        }
    }
    foreach (array_unique($usageIds) as $id) {
        rateb_reservation_settle(db(), (int) $id, $userId, $status, 'ai_image');
    }
}

/** @param array{finish?:array<int,int>} $decision */
function ai_image_settle_dropped(int $userId, array $decision): void
{
    foreach ($decision['finish'] ?? [] as $usageId) {
        rateb_reservation_settle(db(), (int) $usageId, $userId, 'failed', 'ai_image');
    }
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
        $prefix = ai_image_settings()['provider'] === 'cloudflare' ? 'hold-' : '';
        media_store_bytes($campaign, 'image', 'ai', $prefix . 'ratio-' . $ratioName . '-ai-image.' . media_kind_mimes()['image'][$mime], $mime, $bytes);
    } catch (Throwable $error) {
        ai_image_release($userId, $jobId, $usageId, 'failed');
        http_response_code(500);
        echo json_encode(['error' => rateb_ui_error('image_storage'), 'code' => 'image_storage', 'state' => 'failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    ai_image_release($userId, $jobId, $usageId, 'completed');
    echo json_encode([
        'ok' => true,
        'state' => 'ready',
        'model' => $modelName,
        'mime' => $mime,
        'redirect' => '/campaign.php?id=' . (int) $campaign['id'] . '&preview=1#images',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function ai_subject_lock(string $facts): string
{
    $locks = [
        'استقدام' => 'a recruitment company office in Riyadh, a consultant in a white thobe and red shemagh at a desk, an open laptop showing a hiring dashboard of blank cards and simple charts, stacks of beige applicant folders on the desk, daylight through office windows',
        'recruit' => 'a recruitment company office, a consultant at a desk, an open laptop showing a hiring dashboard of blank cards and simple charts, stacks of applicant folders, daylight through office windows',
        'عقار' => 'a real estate agent showing a property to clients',
        'مطعم' => 'a restaurant table with the dishes this business serves',
        'عيادة' => 'a clinic consultation with a doctor and a patient',
        'مدرسة' => 'a classroom with a teacher and students',
        'متجر' => 'a shop counter with the products this business sells',
    ];
    foreach ($locks as $needle => $scene) {
        if (mb_stripos($facts, $needle) !== false) {
            return $scene;
        }
    }
    return '';
}

function ai_english_photo_scene(array $facts): string
{
    $facts = array_values(array_filter(array_map(static fn (string $line): string => trim($line), $facts)));
    if ($facts === []) {
        http_response_code(422);
        echo json_encode(['error' => rateb_ui_error('brief_missing'), 'code' => 'brief_missing'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $cfg = require __DIR__ . '/../../config/ai.php';
    if (trim((string) ($cfg['api_key'] ?? '')) === '') {
        http_response_code(503);
        echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $body = json_encode([
        'model' => $cfg['model'],
        'temperature' => 0.2,
        'max_tokens' => 180,
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => 'Return JSON {"scene":"..."} only. scene is one English sentence, at most 35 words, describing a real photograph. Name the visible people, place, and objects that belong to this business. If it is software or a service, show staff using it in that workplace. Do not ask for printed words, signs, or logos.'],
            ['role' => 'user', 'content' => implode("\n", $facts)],
        ],
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init(rtrim((string) ($cfg['base_url'] ?? ''), '/') . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['api_key']],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string) $raw, true);
    $content = is_array($data) ? (string) ($data['choices'][0]['message']['content'] ?? '') : '';
    $parsed = json_decode($content, true);
    $scene = is_array($parsed) ? trim((string) ($parsed['scene'] ?? '')) : '';
    $scene = trim(preg_replace('/\s+/u', ' ', $scene) ?? '');
    if ($code < 200 || $code >= 300 || $scene === '' || preg_match('/\p{Arabic}/u', $scene) === 1 || preg_match('/[A-Za-z]/', $scene) !== 1) {
        http_response_code(502);
        echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider', 'state' => 'failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $scene;
}

function ai_campaign_image_prompt(array $campaign, int $limit = 400): string
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
    $facts = [];
    $product = trim((string) ($campaign['product_name'] ?? ''));
    if ($product === '') {
        $product = trim((string) ($campaign['title'] ?? ''));
    }
    if ($product !== '') {
        $facts[] = 'Product: ' . $product;
    }
    foreach (['audience', 'core_message', 'creative_direction', 'positioning'] as $key) {
        $english = rateb_prompt_plain($document[$key] ?? '', 'en');
        $arabic = rateb_prompt_plain($document[$key] ?? '', 'ar');
        $line = $english !== '' ? $english : $arabic;
        if ($line !== '') {
            $facts[] = $key . ': ' . $line;
        }
    }
        foreach (['headline', 'ad_copy', 'product_description'] as $type) {
        if (empty($copy[$type])) {
            continue;
        }
        $line = rateb_prompt_plain($copy[$type], 'en');
        if ($line === '') {
            $line = rateb_prompt_plain($copy[$type], 'ar');
        }
        if ($line !== '') {
            $facts[] = $type . ': ' . $line;
            break;
        }
    }
    $locked = ai_subject_lock(implode("\n", $facts));
    $scene = $locked !== '' ? $locked : ai_english_photo_scene($facts);
    $prompt = $locked !== ''
        ? 'Photorealistic photograph of ' . $locked . '. No written text.'
        : rateb_visual_prompt(
            ['Photorealistic photograph of ' . $scene, 'No letters, no words, no signs, no logos'],
            'Nothing in the picture is written.',
            min($limit, 320)
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

/** @return array{provider:string,configured:bool,account_id:string,token:string,model:string} */
function ai_image_settings(): array
{
    $ai = require __DIR__ . '/../../config/ai.php';
    $provider = strtolower(trim((string) ($ai['image_provider'] ?? 'horde')));
    if (!in_array($provider, ['horde', 'cloudflare'], true)) {
        $provider = 'horde';
    }
    $account = trim((string) ($ai['cloudflare_account_id'] ?? ''));
    $token = trim((string) ($ai['cloudflare_api_token'] ?? ''));
    $model = trim((string) ($ai['image_model'] ?? '@cf/black-forest-labs/flux-1-schnell'));
    if ($model === '') {
        $model = '@cf/black-forest-labs/flux-1-schnell';
    }
    $configured = $provider !== 'cloudflare'
        || (preg_match('/^[a-f0-9]{32}$/', $account) === 1 && $token !== '' && preg_match('/^@cf\/[A-Za-z0-9._-]+\/[A-Za-z0-9._-]+$/', $model) === 1);
    return [
        'provider' => $provider,
        'configured' => $configured,
        'account_id' => $account,
        'token' => $token,
        'model' => $model,
    ];
}

/** @param array{account_id:string,token:string,model:string} $settings */
function ai_cloudflare_generate(array $campaign, int $userId, int $campaignId, int $usageId, string $prompt, array $settings): void
{
    $waiting = db()->prepare('SELECT id, stored_name FROM campaign_media WHERE campaign_id = ? AND user_id = ? AND kind = ? AND source = ? AND original_name LIKE ?');
    $waiting->execute([$campaignId, $userId, 'image', 'ai', 'hold-%']);
    foreach ($waiting->fetchAll() as $old) {
        $stored = (string) ($old['stored_name'] ?? '');
        if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $stored) === 1) {
            $path = MEDIA_ROOT . '/' . $campaignId . '/' . $stored;
            if (is_file($path)) {
                unlink($path);
            }
        }
        db()->prepare('DELETE FROM campaign_media WHERE id = ? AND user_id = ? AND original_name LIKE ?')
            ->execute([(int) $old['id'], $userId, 'hold-%']);
    }
    $cost = rateb_get_feature_cost('ai_image');
    $balance = db()->prepare('SELECT credits_balance FROM users WHERE id = ?');
    $balance->execute([$userId]);
    if ((int) $balance->fetchColumn() < $cost) {
        ai_image_release($userId, '', $usageId, 'failed');
        rateb_credit_stop($userId, 'ai_image');
    }
    if (str_contains($prompt, 'recruitment company office')) {
        $personPrompt = 'Professional photograph of two presenters explaining a business program in a bright Riyadh office: a Saudi man in a white thobe and red shemagh and a woman in a navy blazer, waist up, one hand gesturing, both looking at the camera, soft daylight, plain wall, no text, no logos, no screens';
        $personUrl = 'https://api.cloudflare.com/client/v4/accounts/' . $settings['account_id'] . '/ai/run/' . $settings['model'];
        $personResponse = ai_horde_request('POST', $personUrl, json_encode(['prompt' => $personPrompt, 'steps' => 8], JSON_UNESCAPED_UNICODE), [
            'Authorization: Bearer ' . $settings['token'],
        ], 55);
        $person = $personResponse['http'] === 200 ? (ai_cloudflare_image_bytes($personResponse['body']) ?? '') : '';
        $poster = ai_recruitment_poster($person);
        if ($poster !== '') {
            ai_image_store($campaign, $userId, '', $usageId, $poster, 'image/png', 'rateb-dashboard');
        }
    }
    $url = 'https://api.cloudflare.com/client/v4/accounts/' . $settings['account_id'] . '/ai/run/' . $settings['model'];
    $response = ai_horde_request('POST', $url, json_encode(['prompt' => $prompt, 'steps' => 8], JSON_UNESCAPED_UNICODE), [
        'Authorization: Bearer ' . $settings['token'],
    ]);
    if ($response['http'] === 429) {
        ai_image_release($userId, '', $usageId, 'failed');
        http_response_code(429);
        echo json_encode(['error' => rateb_ui_error('image_workers'), 'code' => 'image_workers', 'state' => 'failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $bytes = $response['http'] === 200 ? (ai_cloudflare_image_bytes($response['body']) ?? '') : '';
    $mime = $bytes !== '' ? ai_image_mime($bytes) : null;
    if ($mime === null || strlen($bytes) < 32 || strlen($bytes) > media_max_bytes('image')) {
        ai_image_release($userId, '', $usageId, 'failed');
        http_response_code(502);
        echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider', 'state' => 'failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    ai_image_store($campaign, $userId, '', $usageId, $bytes, $mime, $settings['model']);
}

function ai_ar_visual(string $text): string
{
    $text = preg_replace('/\p{M}/u', '', $text) ?? $text;
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if ($chars === false) {
        return $text;
    }
    $forms = [
        'ا' => ['ﺍ', 'ﺎ'], 'أ' => ['ﺃ', 'ﺄ'], 'إ' => ['ﺇ', 'ﺈ'], 'آ' => ['ﺁ', 'ﺂ'],
        'ب' => ['ﺏ', 'ﺐ', 'ﺑ', 'ﺒ'], 'ت' => ['ﺕ', 'ﺖ', 'ﺗ', 'ﺘ'], 'ث' => ['ﺙ', 'ﺚ', 'ﺛ', 'ﺜ'],
        'ج' => ['ﺝ', 'ﺞ', 'ﺟ', 'ﺠ'], 'ح' => ['ﺡ', 'ﺢ', 'ﺣ', 'ﺤ'], 'خ' => ['ﺥ', 'ﺦ', 'ﺧ', 'ﺨ'],
        'د' => ['ﺩ', 'ﺪ'], 'ذ' => ['ﺫ', 'ﺬ'], 'ر' => ['ﺭ', 'ﺮ'], 'ز' => ['ﺯ', 'ﺰ'],
        'س' => ['ﺱ', 'ﺲ', 'ﺳ', 'ﺴ'], 'ش' => ['ﺵ', 'ﺶ', 'ﺷ', 'ﺸ'], 'ص' => ['ﺹ', 'ﺺ', 'ﺻ', 'ﺼ'],
        'ض' => ['ﺽ', 'ﺾ', 'ﺿ', 'ﻀ'], 'ط' => ['ﻁ', 'ﻂ', 'ﻃ', 'ﻄ'], 'ظ' => ['ﻅ', 'ﻆ', 'ﻇ', 'ﻈ'],
        'ع' => ['ﻉ', 'ﻊ', 'ﻋ', 'ﻌ'], 'غ' => ['ﻍ', 'ﻎ', 'ﻏ', 'ﻐ'], 'ف' => ['ﻑ', 'ﻒ', 'ﻓ', 'ﻔ'],
        'ق' => ['ﻕ', 'ﻖ', 'ﻗ', 'ﻘ'], 'ك' => ['ﻙ', 'ﻚ', 'ﻛ', 'ﻜ'], 'ل' => ['ﻝ', 'ﻞ', 'ﻟ', 'ﻠ'],
        'م' => ['ﻡ', 'ﻢ', 'ﻣ', 'ﻤ'], 'ن' => ['ﻥ', 'ﻦ', 'ﻧ', 'ﻨ'], 'ه' => ['ﻩ', 'ﻪ', 'ﻫ', 'ﻬ'],
        'و' => ['ﻭ', 'ﻮ'], 'ؤ' => ['ﺅ', 'ﺆ'], 'ة' => ['ﺓ', 'ﺔ'], 'ى' => ['ﻯ', 'ﻰ'],
        'ي' => ['ﻱ', 'ﻲ', 'ﻳ', 'ﻴ'], 'ئ' => ['ﺉ', 'ﺊ', 'ﺋ', 'ﺌ'],
    ];
    $lamAlef = ['ا' => ['ﻻ', 'ﻼ'], 'أ' => ['ﻷ', 'ﻸ'], 'إ' => ['ﻹ', 'ﻺ'], 'آ' => ['ﻵ', 'ﻶ']];
    $joins = static function (string $char) use ($forms): bool {
        return isset($forms[$char]) && count($forms[$char]) === 4;
    };
    $shaped = [];
    $count = count($chars);
    for ($i = 0; $i < $count; $i++) {
        $char = $chars[$i];
        if (!isset($forms[$char])) {
            $shaped[] = $char;
            continue;
        }
        $prev = $i > 0 ? $chars[$i - 1] : '';
        $next = $i + 1 < $count ? $chars[$i + 1] : '';
        $prevJoins = $joins($prev);
        if ($char === 'ل' && isset($lamAlef[$next])) {
            $shaped[] = $lamAlef[$next][$prevJoins ? 1 : 0];
            $i++;
            continue;
        }
        $nextJoins = isset($forms[$next]);
        $form = $forms[$char];
        if ($prevJoins && $nextJoins && isset($form[3])) {
            $shaped[] = $form[3];
        } elseif ($prevJoins && isset($form[1])) {
            $shaped[] = $form[1];
        } elseif ($nextJoins && isset($form[2])) {
            $shaped[] = $form[2];
        } else {
            $shaped[] = $form[0];
        }
    }
    return implode('', array_reverse($shaped));
}

function ai_draw_ar($image, int $size, int $x, int $y, string $text, int $color, string $font, int $centerWidth = 0): void
{
    if (!function_exists('imagettftext') || !is_file($font)) {
        return;
    }
    $visual = ai_ar_visual($text);
    if ($centerWidth > 0) {
        $box = imagettfbbox($size, 0, $font, $visual);
        $width = is_array($box) ? abs($box[2] - $box[0]) : 0;
        $x += (int) (($centerWidth - $width) / 2);
    }
    imagettftext($image, $size, 0, $x, $y, $color, $font, $visual);
}

function ai_recruitment_dashboard_png(): string
{
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
        return '';
    }
    $image = imagecreatetruecolor(1024, 1024);
    if ($image === false) {
        return '';
    }
    $paint = static function (int $r, int $g, int $b) use ($image): int {
        return imagecolorallocate($image, $r, $g, $b);
    };
    imagefilledrectangle($image, 0, 0, 1023, 1023, $paint(241, 245, 249));
    imagefilledrectangle($image, 790, 0, 1023, 1023, $paint(255, 255, 255));
    imagefilledrectangle($image, 0, 0, 789, 72, $paint(255, 255, 255));
    imagefilledrectangle($image, 28, 96, 762, 168, $paint(34, 168, 85));
    $colors = [
        [30, 58, 95], [197, 201, 214], [46, 158, 79], [46, 158, 79],
        [240, 180, 41], [47, 111, 237], [226, 59, 59], [240, 138, 36],
        [226, 59, 59], [47, 111, 237], [46, 158, 79], [232, 90, 173],
        [107, 77, 224], [240, 180, 41],
    ];
    $numbers = ['8', '5', '5', '0', '5', '36', '1', '1', '1', '0', '6', '0', '0', '0'];
    $labels = ['عقود الاستقدام', 'عقود الضمان', 'عقود التأجير', 'نقل الكفالة', 'الإيواء', 'العمالة المتاحة', 'الوكلاء', 'طلبات الاستقدام', 'التأشيرات', 'المتابعة', 'الإقامة', 'الحسابات', 'طلبات الموقع', 'المعاملات المعلقة'];
    $font = __DIR__ . '/../fonts/DejaVuSans.ttf';
    $dark = $paint(31, 41, 55);
    $white = $paint(255, 255, 255);
    ai_draw_ar($image, 22, 28, 148, 'مرحباً بك في برنامج رتب لشركات الاستقدام', $white, $font, 734);
    for ($i = 0; $i < 14; $i++) {
        $col = $i % 4;
        $row = intdiv($i, 4);
        $x = 36 + $col * 186;
        $y = 196 + $row * 168;
        imagefilledrectangle($image, $x, $y, $x + 170, $y + 150, $white);
        $color = $colors[$i];
        imagefilledellipse($image, $x + 85, $y + 48, 64, 64, $paint($color[0], $color[1], $color[2]));
        imagestring($image, 5, $x + (strlen($numbers[$i]) === 1 ? 78 : 72), $y + 40, $numbers[$i], $white);
        ai_draw_ar($image, 12, $x + 6, $y + 128, $labels[$i], $dark, $font, 158);
    }
    imagefilledrectangle($image, 36, 860, 390, 990, $white);
    imagefilledrectangle($image, 408, 860, 762, 990, $white);
    ai_draw_ar($image, 13, 36, 888, 'عدد العقود حسب الأشهر', $dark, $font, 354);
    ai_draw_ar($image, 13, 408, 888, 'عدد طلبات الاستقدام', $dark, $font, 354);
    $bars = [40, 70, 55, 90, 60, 110];
    foreach ($bars as $index => $height) {
        imagefilledrectangle($image, 58 + $index * 52, 978 - $height, 90 + $index * 52, 978, $paint(47, 111, 237));
        imagefilledrectangle($image, 430 + $index * 52, 978 - (int) ($height * 0.7), 462 + $index * 52, 978, $paint(34, 168, 85));
    }
    $menu = ['لوحة التحكم', 'عقود الاستقدام', 'الإيواء', 'قسم التأجير', 'نقل الخدمات', 'طلبات الاستقدام', 'العمالة', 'الوكلاء'];
    foreach ($menu as $i => $item) {
        imagefilledrectangle($image, 808, 110 + $i * 78, 1004, 164 + $i * 78, $paint(241, 245, 249));
        ai_draw_ar($image, 14, 808, 146 + $i * 78, $item, $dark, $font, 196);
    }
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    return is_string($bytes) ? $bytes : '';
}

function ai_recruitment_poster(string $personBytes): string
{
    $boardBytes = ai_recruitment_dashboard_png();
    if ($boardBytes === '' || !function_exists('imagecreatetruecolor') || !function_exists('imagecreatefromstring')) {
        return $boardBytes;
    }
    $canvas = imagecreatetruecolor(1200, 1200);
    if ($canvas === false) {
        return $boardBytes;
    }
    $paint = static function (int $r, int $g, int $b) use ($canvas): int {
        return imagecolorallocate($canvas, $r, $g, $b);
    };
    imagefilledrectangle($canvas, 0, 0, 1199, 1199, $paint(248, 250, 252));
    imagefilledrectangle($canvas, 0, 0, 1199, 118, $paint(34, 168, 85));
    $font = __DIR__ . '/../fonts/DejaVuSans.ttf';
    ai_draw_ar($canvas, 28, 40, 76, 'برنامج رتب لشركات الاستقدام', $paint(255, 255, 255), $font, 1120);
    $photo = $personBytes !== '' ? @imagecreatefromstring($personBytes) : false;
    imagefilledrectangle($canvas, 36, 156, 560, 1040, $paint(255, 255, 255));
    if ($photo !== false) {
        imagecopyresampled($canvas, $photo, 48, 168, 0, 0, 500, 760, imagesx($photo), imagesy($photo));
        imagedestroy($photo);
    }
    ai_draw_ar($canvas, 18, 48, 990, 'فريق يشرح البرنامج', $paint(31, 41, 55), $font, 500);
    $board = @imagecreatefromstring($boardBytes);
    if ($board !== false) {
        imagecopyresampled($canvas, $board, 590, 156, 0, 0, 574, 860, imagesx($board), imagesy($board));
        imagedestroy($board);
    }
    ob_start();
    imagepng($canvas);
    $bytes = ob_get_clean();
    imagedestroy($canvas);
    return is_string($bytes) && $bytes !== '' ? $bytes : $boardBytes;
}

function ai_cloudflare_image_bytes(string $body): ?string
{
    if (ai_image_mime($body) !== null) {
        return $body;
    }
    $trimmed = ltrim($body);
    if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
        return null;
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded) || array_key_exists('success', $decoded) && $decoded['success'] !== true) {
        return null;
    }
    $image = is_array($decoded['result'] ?? null) ? ($decoded['result']['image'] ?? null) : null;
    if (!is_string($image) || $image === '') {
        return null;
    }
    if (str_starts_with($image, 'data:')) {
        $comma = strpos($image, ',');
        $image = $comma === false ? '' : substr($image, $comma + 1);
    }
    $bytes = base64_decode($image, true);
    if (!is_string($bytes) || ai_image_mime($bytes) === null) {
        return null;
    }
    return $bytes;
}

function ai_image_store(array $campaign, int $userId, string $jobId, int $usageId, string $bytes, string $mime, string $modelName): void
{
    if (rateb_watermark_required(db(), $userId)) {
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
        $prefix = ai_image_settings()['provider'] === 'cloudflare' ? 'hold-' : '';
        media_store_bytes($campaign, 'image', 'ai', $prefix . 'ratio-' . $ratioName . '-ai-image.' . media_kind_mimes()['image'][$mime], $mime, $bytes);
    } catch (Throwable $error) {
        ai_image_release($userId, $jobId, $usageId, 'failed');
        http_response_code(500);
        echo json_encode(['error' => rateb_ui_error('image_storage'), 'code' => 'image_storage', 'state' => 'failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    ai_image_release($userId, $jobId, $usageId, 'completed');
    echo json_encode([
        'ok' => true,
        'state' => 'ready',
        'model' => $modelName,
        'mime' => $mime,
        'redirect' => '/campaign.php?id=' . (int) $campaign['id'] . '&preview=1#images',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function ai_horde_request(string $method, string $url, ?string $body = null, array $extraHeaders = [], int $timeout = 12): array
{
    $ch = curl_init($url);
    $headers = array_merge(['Accept: application/json, image/webp, image/jpeg, image/png', 'Client-Agent: rateb-ai-campaign:1.0:ai.rateb.sa'], $extraHeaders);
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
        CURLOPT_TIMEOUT => max(1, $timeout),
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
