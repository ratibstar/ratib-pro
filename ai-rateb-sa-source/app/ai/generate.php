<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../plans.php';
require_once __DIR__ . '/../idea.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
header('X-Rateb-Generate: 3');

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
    if ($only !== '' && !in_array($only, rateb_copy_types(), true)) {
        rateb_fail(400, 'invalid_output');
    }
    $locked = $only !== '' ? [$only] : rateb_copy_types();
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
$lockKey = 'rateb_text_' . $id . '_' . $stage;
rateb_text_hold($lockKey);
$gate = rateb_usage_begin(db(), $userId, $id, 'campaign_text');
if (!$gate['ok']) {
    rateb_text_release($lockKey);
    rateb_deny((string) $gate['code']);
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
    $prompt = "Write one campaign strategy from these facts only. Keep every value to one or two short sentences. Return JSON with English keys: positioning, core_message, audience, channels, tone, creative_direction, call_to_action. " . $shape . $decision['instruction'] . "\nFacts:\n" . $facts;
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
        rateb_fail(502, 'ai_failed');
    }
    rateb_usage_finish(db(), (int) $gate['id'], 'completed');
    rateb_text_release($lockKey);
    echo json_encode(['ok' => true, 'redirect' => '/campaign.php?id=' . $id . '#strategy'], JSON_UNESCAPED_UNICODE);
    exit;
}

$only = trim((string) ($_POST['output_type'] ?? ''));
$keys = $only !== '' ? [$only] : rateb_copy_types();
$strategy = rateb_strategy_document((string) $strategyRow['content']);
$shape = $resolved === 'bilingual' ? 'Each value must be an object with keys ar and en. ' : 'Each value must be a plain string. ';
$prompt = 'Write short campaign copy from the approved strategy and the facts. Each value is at most two short sentences. Return JSON with English keys: ' . implode(', ', $keys) . '. ' . $shape . $decision['instruction'] . "\nFacts:\n" . $facts . "\nApproved strategy:\n" . json_encode($strategy, JSON_UNESCAPED_UNICODE);
$result = rateb_generate_output($cfg, $system, $prompt, $resolved, $keys);
if (!is_array($result) || !rateb_output_ready($result, $resolved, $keys)) {
    rateb_usage_finish(db(), (int) $gate['id'], 'failed');
    $bad = [];
    if (is_array($result)) {
        foreach ($keys as $key) {
            if (!rateb_value_ready($result[$key] ?? null, $resolved)) {
                $bad[] = $key;
            }
        }
    }
    rateb_fail(is_array($result) ? 422 : 502, is_array($result) ? 'invalid_output' : 'ai_failed', ['fields' => $bad]);
}
$rows = [];
foreach ($keys as $key) {
    $value = rateb_normalize_value($result[$key], $resolved);
    $rows[$key] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
}
try {
    rateb_replace_outputs($id, $rows);
} catch (Throwable $error) {
    rateb_usage_finish(db(), (int) $gate['id'], 'failed');
    rateb_fail(502, 'ai_failed');
}
rateb_usage_finish(db(), (int) $gate['id'], 'completed');
rateb_text_release($lockKey);
echo json_encode(['ok' => true, 'redirect' => '/campaign.php?id=' . $id . '#copy'], JSON_UNESCAPED_UNICODE);
exit;

function rateb_fail(int $status, string $code, array $extra = []): void
{
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
    $raw = rateb_model_json($cfg, $system, $prompt, rateb_output_schema($keys, $resolved));
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
        'reasoning_effort' => 'low',
        'max_tokens' => 1000,
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
        CURLOPT_TIMEOUT => 15,
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
