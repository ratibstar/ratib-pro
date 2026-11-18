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

set_time_limit(60);
$campaign = media_owned_campaign((int) ($_POST['campaign_id'] ?? 0));
if (isset($_FILES['poster']) && (int) ($_FILES['poster']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $bytes = (string) file_get_contents((string) $_FILES['poster']['tmp_name']);
    $mime = ai_image_mime($bytes);
    if ($mime === null || strlen($bytes) < 32 || strlen($bytes) > media_max_bytes('image')) {
        http_response_code(422);
        echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $ratioName = str_replace(':', 'x', in_array((string) ($_POST['ratio'] ?? '1:1'), ['1:1', '4:5', '9:16'], true) ? (string) $_POST['ratio'] : '1:1');
    $mediaId = media_store_bytes($campaign, 'image', 'ai', 'ratio-' . $ratioName . '-ad-poster.png', $mime, $bytes);
    echo json_encode([
        'ok' => true,
        'state' => 'ready',
        'media_id' => $mediaId,
        'redirect' => '/campaign.php?id=' . (int) $campaign['id'] . '#images',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
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
    '9:16' => [320, 576],
    default => [512, 512],
};
if (!in_array($ratio, ['1:1', '4:5', '9:16'], true)) {
    $ratio = '1:1';
}
$ratioLead = 'ratio ' . $ratio . '. ';
$prompt = $ratioLead . ai_campaign_image_prompt($campaign, 400 - mb_strlen($ratioLead));
if ($prompt === $ratioLead) {
    http_response_code(422);
    echo json_encode(['error' => rateb_ui_error('brief_missing'), 'code' => 'brief_missing'], JSON_UNESCAPED_UNICODE);
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

$models = ai_horde_request('GET', 'https://aihorde.net/api/v2/status/models?type=image', null, 8);
$modelList = json_decode($models['body'], true);
$workers = 0;
$imageModel = '';
$preferred = ['Deliberate', 'stable_diffusion', 'Realistic Vision'];
$counts = [];
if (is_array($modelList)) {
    foreach ($modelList as $row) {
        if (is_array($row) && is_string($row['name'] ?? null)) {
            $counts[$row['name']] = (int) ($row['count'] ?? 0);
        }
    }
}
foreach ($preferred as $name) {
    if (($counts[$name] ?? 0) > 0) {
        $imageModel = $name;
        $workers = $counts[$name];
        break;
    }
}
if ($models['http'] === 200 && $workers < 1) {
    ai_image_release($userId, '', $usageId, 'failed');
    http_response_code(503);
    echo json_encode(['error' => rateb_ui_error('image_workers'), 'code' => 'image_workers'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($imageModel === '') {
    $imageModel = 'Deliberate';
}

$payload = json_encode([
    'prompt' => $prompt,
    'params' => ['width' => $ratioSize[0], 'height' => $ratioSize[1], 'steps' => 15, 'n' => 1],
    'nsfw' => false,
    'censor_nsfw' => false,
    'r2' => true,
    'models' => [$imageModel],
], JSON_UNESCAPED_UNICODE);

$submit = ai_horde_request('POST', 'https://aihorde.net/api/v2/generate/async', $payload);
if ($submit['http'] !== 202) {
    $payload = json_encode([
        'prompt' => $prompt,
        'params' => ['width' => $ratioSize[0], 'height' => $ratioSize[1], 'steps' => 15, 'n' => 1],
        'nsfw' => false,
        'censor_nsfw' => false,
        'r2' => true,
        'models' => ['Deliberate'],
    ], JSON_UNESCAPED_UNICODE);
    $submit = ai_horde_request('POST', 'https://aihorde.net/api/v2/generate/async', $payload, 20, true);
}
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
    $generation = [];
    if (is_array($result) && is_array($result['generations'][0] ?? null)) {
        $generation = $result['generations'][0];
        $imageUrl = (string) ($generation['img'] ?? '');
        $modelName = (string) ($generation['model'] ?? '');
    }
    if (!empty($generation['censored'])) {
        ai_image_release($userId, $jobId, $usageId, 'failed');
        http_response_code(502);
        echo json_encode(['error' => rateb_ui_error('image_provider'), 'code' => 'image_provider'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!ai_image_url_allowed($imageUrl)) {
        ai_image_pending($jobId, 3);
    }

    $download = ai_horde_request('GET', $imageUrl, null, 25);
    if ($download['http'] !== 200 || !ai_image_url_allowed($download['effective'] !== '' ? $download['effective'] : $imageUrl)) {
        ai_image_pending($jobId, 3);
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
        $mediaId = media_store_bytes($campaign, 'image', 'ai', 'ratio-' . $ratioName . '-ai-image.' . media_kind_mimes()['image'][$mime], $mime, $bytes);
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
        'media_id' => $mediaId,
        'redirect' => '/campaign.php?id=' . (int) $campaign['id'] . '#images',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function ai_campaign_image_prompt(array $campaign, int $limit = 400): string
{
    $rows = db()->prepare('SELECT output_type, content, approval_status FROM campaign_outputs WHERE campaign_id = ? ORDER BY id DESC');
    $rows->execute([(int) $campaign['id']]);
    $strategy = null;
    $copy = [];
    foreach ($rows as $row) {
        $type = (string) $row['output_type'];
        if ($type === 'strategy' && $strategy === null && (string) $row['approval_status'] === 'approved') {
            $document = rateb_strategy_document((string) $row['content']);
            if ($document !== null) {
                $strategy = $document;
            }
        } elseif (in_array($type, rateb_copy_types(), true) && (string) $row['approval_status'] === 'approved' && !isset($copy[$type])) {
            $copy[$type] = trim((string) $row['content']);
        }
    }
    if ($strategy === null || $copy === []) {
        http_response_code(409);
        echo json_encode(['error' => rateb_ui_error('image_locked'), 'code' => 'image_locked'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $copyLine = '';
    foreach (array_merge(['headline'], rateb_copy_types()) as $type) {
        if (empty($copy[$type])) {
            continue;
        }
        $copyLine = rateb_prompt_plain($copy[$type], 'ar');
        if ($copyLine !== '') {
            break;
        }
    }
    $product = trim((string) ($campaign['product_name'] ?? ''));
    if ($product === '') {
        $product = trim((string) ($campaign['title'] ?? ''));
    }
    $direction = rateb_prompt_plain($strategy['creative_direction'] ?? '', 'ar');
    $message = rateb_prompt_plain($strategy['core_message'] ?? '', 'ar');
    $idea = trim($product . "\n" . $copyLine . "\n" . $direction . "\n" . $message . "\n" . (string) ($campaign['title'] ?? '') . "\n" . (string) ($campaign['description'] ?? ''));
    $scene = ai_image_from_copy($idea);
    if ($scene === '') {
        $scene = ai_image_scene($idea);
    }
    $prompt = $scene . ' ### user interface, hud, target, reticle, sci-fi, neon, purple room, landscape, hills, wallpaper, closed phone, person, face, text, letters, logo';
    if (mb_strlen($prompt) > $limit) {
        $prompt = rtrim(mb_substr($prompt, 0, $limit));
    }
    return trim($prompt);
}

function ai_image_from_copy(string $idea): string
{
    $idea = trim($idea);
    if ($idea === '') {
        return '';
    }
    $cfg = require __DIR__ . '/../../config/ai.php';
    $key = trim((string) ($cfg['api_key'] ?? ''));
    if ($key === '') {
        return '';
    }
    $body = json_encode([
        'model' => (string) ($cfg['model'] ?? 'deepseek-chat'),
        'messages' => [
            ['role' => 'system', 'content' => 'Return JSON {"scene":"..."} only. scene is one English camera description of 35 words. It must depict the exact offer in the brief as a real advertisement a customer would recognize. Name the product. Show the result the customer gets, not a generic computer and not a control panel. No people, no readable text, no logo, no neon, no science-fiction interface.'],
            ['role' => 'user', 'content' => $idea],
        ],
        'temperature' => 0.2,
        'max_tokens' => 180,
        'response_format' => ['type' => 'json_object'],
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init(rtrim((string) ($cfg['base_url'] ?? ''), '/') . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return '';
    }
    $data = json_decode((string) $raw, true);
    $content = is_array($data) ? (string) ($data['choices'][0]['message']['content'] ?? '') : '';
    $parsed = json_decode($content, true);
    $scene = is_array($parsed) ? trim((string) ($parsed['scene'] ?? '')) : '';
    if ($scene === '' || preg_match('/\p{Arabic}/u', $scene) === 1 || mb_strlen($scene) < 20) {
        return '';
    }
    return $scene;
}

function ai_image_scene(string $idea): string
{
    $text = mb_strtolower($idea);
    $brand = 'the advertised product';
    if (preg_match('/rateb\\s*ai|rateb/iu', $idea, $brandMatch) === 1) {
        $brand = strtoupper(trim($brandMatch[0]));
        if (!str_contains($brand, 'AI')) {
            $brand .= ' AI';
        }
    }
    if (preg_match('/فيديو|سناب|ستوري|ريلز|إعلان|اعلان|advertis|video|story/u', $text) === 1) {
        return $brand . ' advertisement for a platform that creates advertising videos, the whole frame is a smartphone screen playing a vertical video ad, a laptop corner shows a video editing timeline, not a nature wallpaper, white desk, daylight, photorealistic';
    }
    if (preg_match('/تمر|قهو|عطر|عطور|طعام|أكل|وجبه|بيتزا|حلويات/u', $text) === 1) {
        return 'close-up photograph of the physical product from the offer on a clean table, daylight, commercial food or goods photo, no people, no letters, no packaging text';
    }
    return 'close-up photograph of the offered product on a white table, daylight, commercial still, no people, no letters';
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

function ai_image_url_allowed(string $url): bool
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if (parse_url($url, PHP_URL_SCHEME) !== 'https' || $host === '' || $host === 'localhost') {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return false;
    }
    return !str_ends_with($host, '.local');
}

function ai_horde_request(string $method, string $url, ?string $body = null, int $timeout = 20, bool $anonymous = false): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json, image/webp, image/jpeg, image/png', 'Client-Agent: rateb-ai-campaign:1.0:ai.rateb.sa'];
    if (strtolower((string) parse_url($url, PHP_URL_HOST)) === 'aihorde.net') {
        $ai = require __DIR__ . '/../../config/ai.php';
        $hordeKey = $anonymous ? '' : trim((string) ($ai['horde_api_key'] ?? ''));
        if ($hordeKey === '') {
            $hordeKey = '0000000000';
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
        CURLOPT_TIMEOUT => max(5, $timeout),
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
