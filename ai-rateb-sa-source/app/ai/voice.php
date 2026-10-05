<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../media/library.php';
require_once __DIR__ . '/../plans.php';
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

$campaign = media_owned_campaign((int) ($_POST['campaign_id'] ?? 0));
$gate = rateb_usage_begin(db(), (int) $_SESSION['user_id'], (int) $campaign['id'], 'voice');
if (!$gate['ok']) {
    rateb_deny((string) $gate['code']);
}
$decision = rateb_output_language(db(), $campaign);
$lang = $decision['speech'] === 'ar' ? 'ar' : 'en';

$cfg = require __DIR__ . '/../../config/ai.php';
if (!$cfg['api_key']) {
    http_response_code(503);
    echo json_encode(['error' => 'AI API key is not configured. Set RATEB_AI_API_KEY on the server.']);
    exit;
}

$base = rtrim((string) $cfg['base_url'], '/');
$list = curl_init($base . '/models');
curl_setopt_array($list, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $cfg['api_key']],
    CURLOPT_TIMEOUT => 30,
]);
$modelsRaw = curl_exec($list);
$modelsHttp = (int) curl_getinfo($list, CURLINFO_HTTP_CODE);
curl_close($list);
$models = json_decode((string) $modelsRaw, true);
$ids = [];
foreach (($models['data'] ?? []) as $row) {
    if (isset($row['id'])) {
        $ids[] = (string) $row['id'];
    }
}

if ($lang === 'ar' && in_array('canopylabs/orpheus-arabic-saudi', $ids, true)) {
    $model = 'canopylabs/orpheus-arabic-saudi';
    $voice = 'fahad';
} elseif (in_array('canopylabs/orpheus-v1-english', $ids, true)) {
    $model = 'canopylabs/orpheus-v1-english';
    $voice = 'troy';
    $lang = 'en';
} else {
    http_response_code(503);
    echo json_encode(['error' => 'Voice generation is not available with the configured AI provider.']);
    exit;
}

$stmt = db()->prepare('SELECT content FROM campaign_outputs WHERE campaign_id = ? AND output_type = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([(int) $campaign['id'], 'voiceover']);
$script = trim((string) $stmt->fetchColumn());
if ($script === '') {
    $script = trim((string) $campaign['product_name'] . '. ' . (string) $campaign['title']);
}
if (function_exists('mb_substr')) {
    $script = mb_substr($script, 0, 200);
} else {
    $script = substr($script, 0, 200);
}
$script = trim($script);
if ($script === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Nothing to speak.']);
    exit;
}

$payload = json_encode([
    'model' => $model,
    'input' => $script,
    'voice' => $voice,
    'response_format' => 'wav',
], JSON_UNESCAPED_UNICODE);
$ch = curl_init($base . '/audio/speech');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['api_key']],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 90,
]);
$audio = curl_exec($ch);
$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($audio === false || $http < 200 || $http >= 300 || substr((string) $audio, 0, 4) !== 'RIFF') {
    $decoded = json_decode((string) $audio, true);
    $errorCode = is_array($decoded) ? (string) ($decoded['error']['code'] ?? '') : '';
    rateb_usage_finish(db(), (int) $gate['id'], 'failed');
    if ($errorCode === 'model_terms_required') {
        http_response_code(403);
        echo json_encode(['error' => 'The speech model is available, but its terms have not been accepted for this API key.']);
        exit;
    }
    http_response_code(502);
    echo json_encode(['error' => 'AI voice request failed.']);
    exit;
}

try {
    media_store_bytes(
        $campaign,
        'audio',
        'ai',
        $lang === 'ar' ? 'voiceover-ar.wav' : 'voiceover-en.wav',
        'audio/wav',
        (string) $audio
    );
} catch (Throwable $error) {
    rateb_usage_finish(db(), (int) $gate['id'], 'failed');
    http_response_code(500);
    echo json_encode(['error' => 'Could not save the voice file.']);
    exit;
}
rateb_usage_finish(db(), (int) $gate['id'], 'completed');

echo json_encode(['ok' => true, 'redirect' => '/campaign.php?id=' . (int) $campaign['id']], JSON_UNESCAPED_UNICODE);
