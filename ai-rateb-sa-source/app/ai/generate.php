<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../plans.php';
require_once __DIR__ . '/../idea.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
header('X-Rateb-Generate: 3');

ignore_user_abort(true);
set_time_limit(120);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $watchId = (int) ($_GET['campaign_id'] ?? 0);
    $watchStage = (string) ($_GET['stage'] ?? '');
    $watchUser = (int) $_SESSION['user_id'];
    $watch = db()->prepare('SELECT id FROM campaigns WHERE id = ? AND user_id = ?');
    $watch->execute([$watchId, $watchUser]);
    if (!$watch->fetch()) {
        http_response_code(404);
        echo json_encode(['ready' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $watchToken = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($_GET['watch'] ?? '')) ?? '';
    $jobKey = $watchToken !== '' ? ('rateb_job_' . $watchId . '_' . $watchStage . '_' . $watchToken) : '';
    $state = $jobKey !== '' ? (string) ($_SESSION[$jobKey] ?? '') : '';
    session_write_close();
    echo json_encode(['ready' => $state === 'done', 'failed' => $state === 'fail'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rateb_fail(405, 'invalid_output');
}
$csrf = (string) ($_POST['csrf'] ?? $_POST['csrf_token'] ?? '');
$expected = (string) ($_SESSION['csrf_token'] ?? $_SESSION['csrf'] ?? '');
if ($expected === '' || !hash_equals($expected, $csrf)) {
    http_response_code(419);
    echo json_encode(['error' => ui_language() === 'ar' ? 'الطلب غير صالح.' : 'The request is not valid.', 'code' => 'csrf'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int) ($_POST['campaign_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$stmt = db()->prepare('SELECT * FROM campaigns WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);
$campaign = $stmt->fetch();
if (!$campaign) {
    http_response_code(404);
    echo json_encode(['error' => ui_language() === 'ar' ? 'الحملة غير موجودة.' : 'Campaign not found.', 'code' => 'missing'], JSON_UNESCAPED_UNICODE);
    exit;
}
$briefStmt = db()->prepare('SELECT * FROM campaign_briefs WHERE campaign_id = ? AND user_id = ? LIMIT 1');
$briefStmt->execute([$id, $userId]);
$brief = $briefStmt->fetch() ?: [];
$stage = (string) ($_POST['stage'] ?? '');
$confirm = (string) ($_POST['confirm'] ?? '') === '1';

if ($stage === 'edit') {
    $note = trim((string) ($_POST['note'] ?? ''));
    if ($note === '') {
        rateb_fail(422, 'edit_empty');
    }
    if (mb_strlen($note) > 2000) {
        $note = mb_substr($note, 0, 2000);
    }
    $changes = rateb_edit_changes($note, $brief);
    if ($changes === []) {
        rateb_fail(422, 'edit_empty');
    }
    if (is_array($changes)) {
        rateb_store_edit($userId, $id, $brief, $changes);
        echo json_encode(['ok' => true, 'redirect' => '/campaign.php?id=' . $id . '#idea'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $cfg = rateb_ai_config();
    $gate = rateb_usage_begin(db(), $userId, $id, 'campaign_text');
    if (!$gate['ok']) {
        rateb_deny((string) $gate['code']);
    }
    $prompt = "The user wants to change a campaign brief. Return JSON with English keys title, product, objective, audience, location, channels, brand_tone, description, campaign_language. Use null for every field the user did not explicitly change. Never invent a value. Keep the user's wording. Current facts:\n" . rateb_workspace_facts(db(), $campaign, $brief) . "\nUser request: " . $note;
    $result = rateb_model_json($cfg, 'You are RATEB. Return one JSON object only. Do not invent facts.', $prompt);
    $allowed = ['title', 'product', 'objective', 'audience', 'location', 'channels', 'brand_tone', 'description', 'campaign_language'];
    $clean = [];
    if (is_array($result)) {
        foreach ($allowed as $key) {
            if (!isset($result[$key]) || !is_string($result[$key])) {
                continue;
            }
            $value = trim($result[$key]);
            if ($value === '' || strcasecmp($value, 'null') === 0 || $value === $note) {
                continue;
            }
            if ($key === 'campaign_language' && !in_array($value, rateb_language_values(), true)) {
                continue;
            }
            if (mb_stripos($note, $value) === false) {
                continue;
            }
            $clean[$key] = $value;
        }
    }
    if ($clean === []) {
        rateb_usage_finish(db(), (int) $gate['id'], 'failed');
        rateb_fail(422, 'edit_unclear');
    }
    rateb_store_edit($userId, $id, $brief, $clean);
    rateb_usage_finish(db(), (int) $gate['id'], 'completed');
    echo json_encode(['ok' => true, 'redirect' => '/campaign.php?id=' . $id . '#idea'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($stage !== 'strategy' && $stage !== 'copy') {
    rateb_fail(400, 'invalid_output');
}
$briefReady = trim((string) ($brief['product'] ?? $campaign['product_name'] ?? '')) !== ''
    || trim((string) ($brief['description'] ?? $campaign['description'] ?? '')) !== '';
if (!$briefReady) {
    rateb_fail(422, 'brief_missing');
}

$strategyRow = rateb_output_row($id, 'strategy');
if ($stage === 'strategy' && $strategyRow && (string) $strategyRow['approval_status'] === 'approved' && !$confirm) {
    rateb_fail(409, 'strategy_locked');
}
if ($stage === 'copy') {
    $document = $strategyRow ? rateb_strategy_document((string) $strategyRow['content']) : null;
    if (!$strategyRow || (string) $strategyRow['approval_status'] !== 'approved' || $document === null) {
        rateb_fail(409, 'copy_locked');
    }
    $only = trim((string) ($_POST['output_type'] ?? ''));
    $platformRequest = in_array($only, ['snapchat', 'instagram', 'x', 'google_ads'], true);
    if ($only !== '' && !$platformRequest && !in_array($only, rateb_copy_types(), true)) {
        rateb_fail(400, 'invalid_output');
    }
    $locked = $platformRequest || $only === '' || $only === 'social_posts' ? ['social_posts'] : [$only];
    if ($only === '') {
        $locked = rateb_copy_types();
    }
    if (!$confirm) {
        foreach ($locked as $type) {
            $row = rateb_output_row($id, $type);
            if ($row && (string) $row['approval_status'] === 'approved') {
                rateb_fail(409, 'output_locked');
            }
        }
    }
}

$cfg = rateb_ai_config();
$watchToken = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($_POST['watch'] ?? '')) ?? '';
$GLOBALS['rateb_job_key'] = $watchToken !== '' ? ('rateb_job_' . $id . '_' . $stage . '_' . $watchToken) : '';
if ($GLOBALS['rateb_job_key'] !== '') {
    $_SESSION[$GLOBALS['rateb_job_key']] = 'run';
}
$lockKey = 'rateb_text_' . $id . '_' . $stage;
rateb_text_hold($lockKey);
$gate = rateb_usage_begin(db(), $userId, $id, 'campaign_text');
if (!$gate['ok']) {
    rateb_text_release($lockKey);
    rateb_job_mark('fail');
    rateb_deny((string) $gate['code']);
}
$fullText = ($stage === 'strategy' || ($stage === 'copy' && trim((string) ($_POST['output_type'] ?? '')) === ''))
    && !rateb_campaign_feature_paid($userId, 'text_campaign', $id);
$charged = false;
if ($fullText && !rateb_check_and_deduct_credits($userId, 'text_campaign', $id)) {
    rateb_text_release($lockKey);
    rateb_job_mark('fail');
    rateb_credit_stop();
}
if ($fullText) {
    $charged = true;
}
$decision = rateb_output_language(db(), $campaign);
$resolved = (string) $decision['resolved'];
$facts = rateb_workspace_facts(db(), $campaign, $brief);
if ($resolved === 'ar') {
    $system = 'أنت RATEB. أعد كائن JSON واحدًا فقط. كل قيمة جملة أو جملتان بالعربية. لا تخترع سعرًا أو تاريخًا أو مدينة أو جمهورًا أو قناة غير مذكورة في الحقائق. إذا نقصت معلومة فاكتب: لم يُحدد.';
} elseif ($resolved === 'en') {
    $system = 'You are RATEB. Return one JSON object only. Each value is one or two short English sentences. Do not invent a price, date, place, audience, or channel that is not in the facts. If a fact is missing, write: Not specified.';
} else {
    $system = 'You are RATEB. Return one JSON object only. Each value is an object with ar and en. ar is Arabic only and en is English only. Do not invent a price, date, place, audience, or channel that is not in the facts.';
}

if ($stage === 'strategy') {
    $shape = $resolved === 'bilingual' ? 'Each value must be an object with keys ar and en. ' : 'Each value must be a plain string. ';
    $goalLine = rateb_goal_instruction((string) ($brief['objective'] ?? $campaign['objective'] ?? ''));
    $prompt = "Write one campaign strategy from these facts only. Keep every value to one or two short sentences. Return JSON with English keys: positioning, core_message, audience, channels, tone, creative_direction, call_to_action. " . $shape . $goalLine . $decision['instruction'] . "\nFacts:\n" . $facts;
    $result = rateb_generate_output($cfg, $system, $prompt, $resolved, rateb_strategy_keys());
    if (!is_array($result) || !rateb_output_ready($result, $resolved, rateb_strategy_keys())) {
        rateb_usage_finish(db(), (int) $gate['id'], 'failed');
        $bad = [];
        if (is_array($result)) {
            foreach (rateb_strategy_keys() as $key) {
                if (!rateb_value_ready($result[$key] ?? null, $resolved)) {
                    $bad[] = $key;
                }
            }
        }
        if ($charged) {
            rateb_refund_credits($userId, 'text_campaign', $id);
        }
        rateb_fail(is_array($result) ? 422 : 502, is_array($result) ? 'invalid_output' : 'ai_failed', ['fields' => $bad]);
    }
    $clean = [];
    foreach (rateb_strategy_keys() as $key) {
        $clean[$key] = rateb_normalize_value($result[$key], $resolved);
    }
    try {
        rateb_replace_outputs($id, ['strategy' => json_encode($clean, JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable $error) {
        rateb_usage_finish(db(), (int) $gate['id'], 'failed');
        if ($charged) {
            rateb_refund_credits($userId, 'text_campaign', $id);
        }
        rateb_fail(502, 'ai_failed');
    }
    rateb_usage_finish(db(), (int) $gate['id'], 'completed');
    rateb_job_mark('done');
    rateb_text_release($lockKey);
    echo json_encode(['ok' => true, 'redirect' => '/campaign.php?id=' . $id . '#strategy'], JSON_UNESCAPED_UNICODE);
    exit;
}

$only = trim((string) ($_POST['output_type'] ?? ''));
$platforms = ['snapchat', 'instagram', 'x', 'google_ads'];
$platformOnly = in_array($only, $platforms, true) ? $only : '';
$keys = $platformOnly !== '' ? [] : ($only !== '' ? [$only] : rateb_copy_types());
if ($keys === [] && $platformOnly === '') {
    rateb_text_release($lockKey);
    rateb_usage_finish(db(), (int) $gate['id'], 'failed');
    if ($charged) {
        rateb_refund_credits($userId, 'text_campaign', $id);
    }
    rateb_fail(400, 'invalid_output');
}
$wantSocial = $platformOnly !== '' || in_array('social_posts', $keys, true);
$stringKeys = array_values(array_filter($keys, static fn (string $key): bool => $key !== 'social_posts'));
$strategy = rateb_strategy_document((string) $strategyRow['content']);
$shape = $resolved === 'bilingual' ? 'Each value must be an object with keys ar and en. ' : 'Each value must be a plain string. ';
$socialShape = $platformOnly !== '' ? $platformOnly : 'snapchat, instagram, x, google_ads';
$prompt = 'Write short campaign copy from the approved strategy and the facts. Each plain value is at most two short sentences. Return JSON with English keys: ' . implode(', ', $keys === [] ? ['social_posts'] : $keys) . '. ' . $shape . $decision['instruction'];
if ($wantSocial) {
    $prompt .= ' The social_posts value must be an object, not a string, using Saudi marketing tone. Include only these keys: ' . $socialShape . '. snapchat is one punchy line under 80 characters and has no hashtags. instagram is a caption, a line break, then 5 to 8 relevant Saudi hashtags. x is under 240 characters with at most 2 hashtags. google_ads is an object with headlines (exactly 3 strings, each at most 30 characters) and descriptions (exactly 2 strings, each at most 90 characters) and no hashtags.';
}
$prompt .= "\nFacts:\n" . $facts . "\nApproved strategy:\n" . json_encode($strategy, JSON_UNESCAPED_UNICODE);
if ($wantSocial) {
    $raw = rateb_model_json($cfg, $system, $prompt);
    $result = is_array($raw) ? $raw : null;
    if (is_array($result) && $stringKeys !== []) {
        $prepared = rateb_prepare_output($raw, $resolved, $stringKeys);
        if (is_array($prepared)) {
            foreach ($stringKeys as $key) {
                $result[$key] = $prepared[$key] ?? ($result[$key] ?? null);
            }
        }
    }
} else {
    $result = rateb_generate_output($cfg, $system, $prompt, $resolved, $stringKeys);
}
if ($stringKeys !== [] && (!is_array($result) || !rateb_output_ready($result, $resolved, $stringKeys))) {
    rateb_usage_finish(db(), (int) $gate['id'], 'failed');
    $bad = [];
    if (is_array($result)) {
        foreach ($stringKeys as $key) {
            if (!rateb_value_ready($result[$key] ?? null, $resolved)) {
                $bad[] = $key;
            }
        }
    }
    if ($charged) {
        rateb_refund_credits($userId, 'text_campaign', $id);
    }
    rateb_fail(is_array($result) ? 422 : 502, is_array($result) ? 'invalid_output' : 'ai_failed', ['fields' => $bad]);
}
if ($wantSocial && !is_array($result)) {
    rateb_usage_finish(db(), (int) $gate['id'], 'failed');
    if ($charged) {
        rateb_refund_credits($userId, 'text_campaign', $id);
    }
    rateb_fail(502, 'ai_failed');
}
$rows = [];
foreach ($stringKeys as $key) {
    $value = rateb_normalize_value($result[$key], $resolved);
    $rows[$key] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
}
if ($wantSocial) {
    $fresh = rateb_social_pack(is_array($result['social_posts'] ?? null) ? $result['social_posts'] : $result, $platformOnly);
    if ($fresh === []) {
        rateb_usage_finish(db(), (int) $gate['id'], 'failed');
        if ($charged) {
            rateb_refund_credits($userId, 'text_campaign', $id);
        }
        rateb_fail(422, 'invalid_output', ['fields' => ['social_posts']]);
    }
    $existing = [];
    if ($platformOnly !== '') {
        $current = rateb_output_row($id, 'social_posts');
        $decoded = $current ? json_decode((string) $current['content'], true) : null;
        if (is_array($decoded)) {
            $existing = $decoded;
        }
    }
    $rows['social_posts'] = json_encode(array_merge($existing, $fresh), JSON_UNESCAPED_UNICODE);
}
try {
    rateb_replace_outputs($id, $rows);
} catch (Throwable $error) {
    rateb_usage_finish(db(), (int) $gate['id'], 'failed');
    if ($charged) {
        rateb_refund_credits($userId, 'text_campaign', $id);
    }
    rateb_fail(502, 'ai_failed');
}
rateb_usage_finish(db(), (int) $gate['id'], 'completed');
rateb_job_mark('done');
rateb_text_release($lockKey);
echo json_encode(['ok' => true, 'redirect' => '/campaign.php?id=' . $id . '#copy'], JSON_UNESCAPED_UNICODE);
exit;

function rateb_goal_instruction(string $goal): string
{
    $goal = strtolower(trim($goal));
    if (!in_array($goal, ['conversion', 'awareness', 'traffic', 'leads'], true)) {
        $goal = (string) (rateb_goal_from_text($goal) ?? '');
    }
    if ($goal === 'conversion') {
        return 'Objective is conversion. Focus on one offer, the value of that offer, and one urgent action. ';
    }
    if ($goal === 'awareness') {
        return 'Objective is awareness. Focus on brand story, feeling, and recall. Do not use aggressive sales language. ';
    }
    if ($goal === 'traffic') {
        return 'Objective is traffic. Focus on the reward of opening the link. ';
    }
    if ($goal === 'leads') {
        return 'Objective is leads. Focus on the benefit of submitting contact details. ';
    }
    return '';
}

function rateb_social_clip(string $text, int $limit): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if (mb_strlen($text) <= $limit) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $limit));
}

function rateb_social_pack(array $input, string $only): array
{
    $source = $input;
    if (isset($input['social_posts']) && is_array($input['social_posts'])) {
        $source = $input['social_posts'];
    }
    $keys = $only !== '' ? [$only] : ['snapchat', 'instagram', 'x', 'google_ads'];
    $pack = [];
    foreach ($keys as $key) {
        $value = $source[$key] ?? null;
        if ($key === 'google_ads' && is_array($value)) {
            $headlines = [];
            $descriptions = [];
            foreach ((array) ($value['headlines'] ?? []) as $line) {
                $line = rateb_social_clip((string) $line, 30);
                if ($line !== '') {
                    $headlines[] = $line;
                }
            }
            foreach ((array) ($value['descriptions'] ?? []) as $line) {
                $line = rateb_social_clip((string) $line, 90);
                if ($line !== '' && !str_contains($line, '#')) {
                    $descriptions[] = $line;
                }
            }
            $headlines = array_slice($headlines, 0, 3);
            $descriptions = array_slice($descriptions, 0, 2);
            if (count($headlines) === 3 && count($descriptions) === 2) {
                $pack[$key] = ['headlines' => $headlines, 'descriptions' => $descriptions];
            }
            continue;
        }
        $text = is_string($value) ? trim($value) : '';
        if ($text === '') {
            continue;
        }
        if ($key === 'snapchat') {
            $text = rateb_social_clip(str_replace('#', '', $text), 80);
        } elseif ($key === 'x') {
            $text = rateb_social_clip($text, 240);
        } elseif ($key === 'instagram') {
            $text = trim($text);
        }
        if ($text !== '') {
            $pack[$key] = $text;
        }
    }
    return $pack;
}

function rateb_job_mark(string $state): void
{
    $key = (string) ($GLOBALS['rateb_job_key'] ?? '');
    if ($key === '') {
        return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION[$key] = $state;
    session_write_close();
}

function rateb_fail(int $status, string $code, array $extra = []): void
{
    rateb_job_mark('fail');
    if (isset($GLOBALS['rateb_text_lock']) && is_string($GLOBALS['rateb_text_lock'])) {
        $lockKey = $GLOBALS['rateb_text_lock'];
        unset($GLOBALS['rateb_text_lock']);
        rateb_text_release($lockKey);
    }
    http_response_code($status);
    echo json_encode(['error' => rateb_ui_error($code), 'code' => $code] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

function rateb_ai_config(): array
{
    $cfg = require __DIR__ . '/../../config/ai.php';
    if (empty($cfg['api_key'])) {
        rateb_fail(503, 'ai_unconfigured');
    }
    return $cfg;
}

function rateb_output_row(int $campaignId, string $type): ?array
{
    $stmt = db()->prepare('SELECT * FROM campaign_outputs WHERE campaign_id = ? AND output_type = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$campaignId, $type]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function rateb_text_hold(string $lockKey): void
{
    $held = (int) ($_SESSION[$lockKey] ?? 0);
    if ($held > 0 && (time() - $held) < 20) {
        rateb_fail(429, 'write_busy');
    }
    $_SESSION[$lockKey] = time();
    $GLOBALS['rateb_text_lock'] = $lockKey;
    session_write_close();
}

function rateb_text_release(string $lockKey): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    unset($_SESSION[$lockKey]);
    session_write_close();
}

function rateb_generate_output(array $cfg, string $system, string $prompt, string $resolved, array $keys): mixed
{
    $raw = rateb_model_json($cfg, $system, $prompt, null);
    return rateb_prepare_output($raw, $resolved, $keys);
}

function rateb_model_json(array $cfg, string $system, string $prompt, ?array $schema = null): ?array
{
    static $skipSchema = false;
    $useSchema = is_array($schema) && !$skipSchema;
    $attempt = rateb_model_json_once($cfg, $system, $prompt, $useSchema ? $schema : null);
    if ($attempt['status'] === 400 && $useSchema) {
        $skipSchema = true;
        $attempt = rateb_model_json_once($cfg, $system, $prompt, null);
    }
    return $attempt['json'];
}

function rateb_model_json_once(array $cfg, string $system, string $prompt, ?array $schema): array
{
    $body = [
        'model' => $cfg['model'],
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => 0.4,
        'max_tokens' => 2400,
        'response_format' => is_array($schema)
            ? ['type' => 'json_schema', 'json_schema' => $schema]
            : ['type' => 'json_object'],
    ];
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
    $ch = curl_init(rtrim((string) $cfg['base_url'], '/') . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['api_key']],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 55,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err || $code < 200 || $code >= 300) {
        return ['status' => $code, 'json' => null];
    }
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        return ['status' => $code, 'json' => null];
    }
    $message = $data['choices'][0]['message'] ?? [];
    $result = rateb_json_object((string) ($message['content'] ?? ''));
    if (!is_array($result)) {
        $result = rateb_json_object((string) ($message['reasoning'] ?? ''));
    }
    return ['status' => $code, 'json' => is_array($result) ? $result : null];
}

function rateb_replace_outputs(int $campaignId, array $rows): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $delete = $pdo->prepare('DELETE FROM campaign_outputs WHERE campaign_id = ? AND output_type = ?');
        $insert = $pdo->prepare("INSERT INTO campaign_outputs (campaign_id, output_type, content, approval_status) VALUES (?, ?, ?, 'draft')");
        foreach ($rows as $type => $content) {
            $delete->execute([$campaignId, $type]);
            $insert->execute([$campaignId, mb_substr((string) $type, 0, 64), $content]);
        }
        $pdo->prepare("UPDATE campaigns SET status = IF(status = 'draft', 'in_progress', status), updated_at = NOW() WHERE id = ?")->execute([$campaignId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
