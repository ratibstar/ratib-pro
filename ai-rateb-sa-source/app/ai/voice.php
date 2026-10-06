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
$decision = rateb_output_language(db(), $campaign);
$lang = $decision['speech'] === 'ar' ? 'ar' : 'en';
$script = rateb_voice_script(db(), (int) $campaign['id'], $lang);
if ($script === '') {
    http_response_code(409);
    echo json_encode(['error' => rateb_ui_error('voice_locked'), 'code' => 'voice_locked'], JSON_UNESCAPED_UNICODE);
    exit;
}

$cfg = require __DIR__ . '/../../config/ai.php';
if (!$cfg['api_key']) {
    http_response_code(503);
    echo json_encode(['error' => rateb_ui_error('voice_provider'), 'code' => 'voice_provider'], JSON_UNESCAPED_UNICODE);
    exit;
}

$gate = rateb_usage_begin(db(), (int) $_SESSION['user_id'], (int) $campaign['id'], 'voice');
if (!$gate['ok']) {
    rateb_deny((string) $gate['code']);
}
session_write_close();

$base = rtrim((string) $cfg['base_url'], '/');
if ($lang === 'ar') {
    $model = 'canopylabs/orpheus-arabic-saudi';
    $voice = 'fahad';
} else {
    $model = 'canopylabs/orpheus-v1-english';
    $voice = 'troy';
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
    CURLOPT_TIMEOUT => 40,
    CURLOPT_CONNECTTIMEOUT => 8,
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
        echo json_encode(['error' => rateb_ui_error('voice_terms'), 'code' => 'voice_terms'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(502);
    echo json_encode(['error' => rateb_ui_error('voice_provider'), 'code' => 'voice_provider'], JSON_UNESCAPED_UNICODE);
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
    echo json_encode(['error' => rateb_ui_error('voice_provider'), 'code' => 'voice_provider'], JSON_UNESCAPED_UNICODE);
    exit;
}
rateb_usage_finish(db(), (int) $gate['id'], 'completed');

echo json_encode(['ok' => true, 'redirect' => '/campaign.php?id=' . (int) $campaign['id'] . '#voice'], JSON_UNESCAPED_UNICODE);

function rateb_voice_script(PDO $pdo, int $campaignId, string $speech): string
{
    $stmt = $pdo->prepare('SELECT output_type, content FROM campaign_outputs WHERE campaign_id = ? AND approval_status = ? ORDER BY id DESC');
    $stmt->execute([$campaignId, 'approved']);
    $pieces = [];
    foreach ($stmt as $row) {
        $type = (string) $row['output_type'];
        if (!isset($pieces[$type])) {
            $pieces[$type] = (string) $row['content'];
        }
    }
    $script = '';
    foreach (['ad_copy', 'short_ad', 'headline'] as $type) {
        if (!empty($pieces[$type])) {
            $script = rateb_voice_plain($pieces[$type], $speech);
            if ($script !== '') {
                break;
            }
        }
    }
    if (function_exists('mb_substr')) {
        return trim(mb_substr($script, 0, 280));
    }
    return trim(substr($script, 0, 280));
}

function rateb_voice_plain(string $content, string $speech): string
{
    $decoded = json_decode($content, true);
    if (is_array($decoded)) {
        $content = (string) ($decoded[$speech] ?? $decoded['ar'] ?? $decoded['en'] ?? '');
    }
    return trim(preg_replace('/\s+/u', ' ', $content) ?? '');
}
