<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../plans.php';
require_once __DIR__ . '/../idea.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

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
$gate = rateb_usage_begin(db(), $userId, $id, 'campaign_text');
if (!$gate['ok']) {
    rateb_deny((string) $gate['code']);
}
$decision = rateb_output_language(db(), $campaign);
$resolved = (string) $decision['resolved'];
$facts = rateb_workspace_facts(db(), $campaign, $brief);
$system = 'You are RATEB. Return one JSON object only. Do not invent prices, budgets, dates, locations, audiences, or channels that are not in the facts. If a fact is missing, say that it was not specified in the requested language.';

if ($stage === 'strategy') {
    $shape = $resolved === 'bilingual' ? 'Each value must be an object with keys ar and en. ' : 'Each value must be a plain string. ';
    $prompt = "Write one campaign strategy from these facts only. Return JSON with English keys: positioning, core_message, audience, channels, tone, creative_direction, call_to_action. " . $shape . $decision['instruction'] . "\nFacts:\n" . $facts;
    $result = rateb_model_json($cfg, $system, $prompt);
    $result = rateb_prepare_result($result, $resolved, rateb_strategy_keys());
    if (!is_array($result) || !rateb_strategy_valid($result, $resolved)) {
        $result = rateb_model_json($cfg, $system, $prompt . "\nThe previous answer was invalid. Follow the language shape exactly and do not leave a key empty.");
        $result = rateb_prepare_result($result, $resolved, rateb_strategy_keys());
    }
    if (!is_array($result) || !rateb_strategy_valid($result, $resolved)) {
        rateb_usage_finish(db(), (int) $gate['id'], 'failed');
        $bad = [];
        if (is_array($result)) {
            foreach (rateb_strategy_keys() as $key) {
                if (!rateb_value_fits($result[$key] ?? null, $resolved)) {
                    $bad[] = $key;
                }
            }
        }
        rateb_fail(502, is_array($result) ? 'invalid_output' : 'ai_failed', ['fields' => $bad]);
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
    echo json_encode(['ok' => true, 'redirect' => '/campaign.php?id=' . $id . '#strategy'], JSON_UNESCAPED_UNICODE);
    exit;
}

$only = trim((string) ($_POST['output_type'] ?? ''));
$keys = $only !== '' ? [$only] : rateb_copy_types();
$strategy = rateb_strategy_document((string) $strategyRow['content']);
$shape = $resolved === 'bilingual' ? 'Each value must be an object with keys ar and en. ' : 'Each value must be a plain string. ';
$prompt = 'Write campaign copy from the approved strategy and the facts. Return JSON with English keys: ' . implode(', ', $keys) . '. ' . $shape . $decision['instruction'] . "\nFacts:\n" . $facts . "\nApproved strategy:\n" . json_encode($strategy, JSON_UNESCAPED_UNICODE);
$result = rateb_model_json($cfg, $system, $prompt);
$result = rateb_prepare_result($result, $resolved, $keys);
if (!is_array($result) || !rateb_copy_valid($result, $resolved, $only)) {
    $result = rateb_model_json($cfg, $system, $prompt . "\nThe previous answer was invalid. Follow the language shape exactly and do not leave a key empty.");
    $result = rateb_prepare_result($result, $resolved, $keys);
}
if (!is_array($result) || !rateb_copy_valid($result, $resolved, $only)) {
    rateb_usage_finish(db(), (int) $gate['id'], 'failed');
    $bad = [];
    if (is_array($result)) {
        foreach ($keys as $key) {
            if (!rateb_value_fits($result[$key] ?? null, $resolved)) {
                $bad[] = $key;
            }
        }
    }
    rateb_fail(502, is_array($result) ? 'invalid_output' : 'ai_failed', ['fields' => $bad]);
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
echo json_encode(['ok' => true, 'redirect' => '/campaign.php?id=' . $id . '#copy'], JSON_UNESCAPED_UNICODE);
exit;

function rateb_prepare_result(mixed $result, string $resolved, array $keys): mixed
{
    if (!is_array($result)) {
        return $result;
    }
    foreach ($keys as $key) {
        if (array_key_exists($key, $result)) {
            $result[$key] = rateb_coerce_output($result[$key], $resolved);
        }
    }
    return $result;
}

function rateb_fail(int $status, string $code, array $extra = []): void
{
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

function rateb_model_json(array $cfg, string $system, string $prompt): ?array
{
    $payload = json_encode([
        'model' => $cfg['model'],
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => 0.4,
        'response_format' => ['type' => 'json_object'],
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init(rtrim((string) $cfg['base_url'], '/') . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['api_key']],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 90,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err || $code < 200 || $code >= 300) {
        return null;
    }
    $data = json_decode((string) $raw, true);
    $content = (string) ($data['choices'][0]['message']['content'] ?? '');
    $result = json_decode($content, true);
    if (!is_array($result) && preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $content, $fenced) === 1) {
        $result = json_decode($fenced[1], true);
    }
    if (!is_array($result) && preg_match('/(\{.*\})/s', $content, $braced) === 1) {
        $result = json_decode($braced[1], true);
    }
    return is_array($result) ? $result : null;
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
