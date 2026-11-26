<?php
declare(strict_types=1);
/**
 * Campaign 90: ONE short EasyVoice Free Arabic TTS test.
 * - Uses server EASYVOICE_API_KEY only (never echoes it).
 * - No Sawtak. No RATEB reservation/debit.
 * - Does not modify approved copy or approved image.
 * - Max 250 Arabic characters. Free plan + remaining quota required before speech.
 */
require '/home/admin/.secrets/rateb-ai-env.php';
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/media/library.php';
require_once dirname(__DIR__) . '/app/ai/easyvoice.php';

$pdo = db();
$userId = 66;
$campaignId = 90;
$voiceId = 'ar_m1';
// Short corporate MSA test narration — independent of approved copy pack.
$narration = 'مرحباً بكم في راتِب للذكاء الاصطناعي. نساعد فرق العمل على إعداد حملات احترافية بسرعة ووضوح.';
$chars = mb_strlen($narration, 'UTF-8');

$bal0 = (int) $pdo->query('SELECT credits_balance FROM users WHERE id=66')->fetchColumn();
$out = [
    'utc_now' => gmdate('c'),
    'campaign_id' => $campaignId,
    'provider' => 'easyvoice',
    'model' => 'kokoro-82m',
    'voice' => $voiceId,
    'chars' => $chars,
    'balance_before' => $bal0,
];

if ($bal0 !== 100) {
    $out['ok'] = false;
    $out['error'] = 'balance_not_100';
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(2);
}
if ($chars < 1 || $chars > 250) {
    $out['ok'] = false;
    $out['error'] = 'narration_length';
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(2);
}
if (!rateb_easyvoice_configured() || !rateb_easyvoice_is_free_voice($voiceId)) {
    $out['ok'] = false;
    $out['error'] = 'easyvoice_not_ready';
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(2);
}

$usageBefore = rateb_easyvoice_get_usage();
$out['quota_before'] = [
    'plan' => $usageBefore['plan'],
    'limit' => $usageBefore['limit'],
    'used' => $usageBefore['used'],
    'remaining' => $usageBefore['remaining'],
];
$covered = $usageBefore['ok']
    && (string) ($usageBefore['plan'] ?? '') === 'free'
    && (int) ($usageBefore['limit'] ?? 0) === 5000
    && (int) ($usageBefore['remaining'] ?? 0) >= $chars
    && $chars <= 5000;
$out['free_plan_cover'] = $covered;
if (!$covered) {
    $out['ok'] = false;
    $out['error'] = 'quota_or_plan_block';
    $out['balance_after'] = (int) $pdo->query('SELECT credits_balance FROM users WHERE id=66')->fetchColumn();
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(2);
}

$camp = $pdo->prepare('SELECT * FROM campaigns WHERE id=? AND user_id=?');
$camp->execute([$campaignId, $userId]);
$campaign = $camp->fetch(PDO::FETCH_ASSOC);
if (!$campaign) {
    $out['ok'] = false;
    $out['error'] = 'campaign_missing';
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(2);
}

$speech = rateb_easyvoice_speech($narration, $voiceId, 'mp3', 1.0);
$out['speech_http'] = $speech['http'];
$out['speech_ok'] = $speech['ok'];
$out['speech_error'] = $speech['error'];
$out['audio_bytes'] = strlen($speech['bytes']);
if (!$speech['ok'] || $speech['bytes'] === '') {
    $out['ok'] = false;
    $out['error'] = 'speech_failed';
    $out['balance_after'] = (int) $pdo->query('SELECT credits_balance FROM users WHERE id=66')->fetchColumn();
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(2);
}

$mediaId = media_store_bytes(
    $campaign,
    'audio',
    'ai',
    'voiceover-ar-easyvoice-c90.mp3',
    $speech['mime'] !== '' ? $speech['mime'] : 'audio/mpeg',
    $speech['bytes']
);
$pdo->prepare("UPDATE campaign_media SET approval_status='approved' WHERE id=? AND campaign_id=?")->execute([$mediaId, $campaignId]);

$usageAfter = rateb_easyvoice_get_usage();
$bal1 = (int) $pdo->query('SELECT credits_balance FROM users WHERE id=66')->fetchColumn();
$out['quota_after'] = [
    'plan' => $usageAfter['plan'],
    'limit' => $usageAfter['limit'],
    'used' => $usageAfter['used'],
    'remaining' => $usageAfter['remaining'],
];
$out['audio_media_id'] = $mediaId;
$out['balance_after'] = $bal1;
$out['file_url'] = 'https://ai.rateb.sa/app/media/file.php?id=' . $mediaId;
$out['campaign_url'] = 'https://ai.rateb.sa/campaign.php?id=90&play=1#voice';
$out['ok'] = ($bal1 === 100 && $mediaId > 0);
$out['proof'] = 'easyvoice free ar_m1 via rateb_easyvoice_speech + media_store_bytes; no RATEB debit';
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($out['ok'] ? 0 : 2);
