<?php
declare(strict_types=1);
/**
 * Campaign 90: ONE EasyVoice Free pronunciation test (ar_m1).
 * Does not modify approved campaign copy. No RATEB debit. No Sawtak.
 */
require '/home/admin/.secrets/rateb-ai-env.php';
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/media/library.php';
require_once dirname(__DIR__) . '/app/ai/easyvoice.php';
require_once dirname(__DIR__) . '/app/ai/tts-pronunciation.php';

$pdo = db();
$userId = 66;
$campaignId = 90;
$voiceId = 'ar_m1';
// Exact required test narration (already pronunciation-normalized).
$displayScript = 'مرحباً بكم في رَتِّب إيه آي، منصة ذكية لأتمتة أعمالكم.';
$ttsInput = rateb_tts_normalize_pronunciation($displayScript);
$chars = mb_strlen($ttsInput, 'UTF-8');

$bal0 = (int) $pdo->query('SELECT credits_balance FROM users WHERE id=66')->fetchColumn();
$out = [
    'utc_now' => gmdate('c'),
    'campaign_id' => $campaignId,
    'provider' => 'easyvoice',
    'model' => 'kokoro-82m',
    'voice' => $voiceId,
    'voice_docs' => 'https://easyvoice.ae/voices/ar_m1',
    'display_script' => $displayScript,
    'spoken_input' => $ttsInput,
    'chars' => $chars,
    'balance_before' => $bal0,
    'approved_copy_modified' => false,
];

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
    && (int) ($usageBefore['remaining'] ?? 0) >= $chars;
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

// Soft-unapprove prior audio rows so the new pronunciation test is the active voice clip.
$pdo->prepare("UPDATE campaign_media SET approval_status='rejected' WHERE campaign_id=? AND kind='audio' AND approval_status='approved'")
    ->execute([$campaignId]);

$speech = rateb_easyvoice_speech($ttsInput, $voiceId, 'mp3', 1.0);
$out['speech_http'] = $speech['http'];
$out['speech_ok'] = $speech['ok'];
$out['speech_error'] = $speech['error'];
$out['audio_bytes'] = strlen($speech['bytes']);
$out['spoken_input_sent'] = (string) ($speech['spoken_input'] ?? $ttsInput);
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
    'voiceover-ar-easyvoice-pronounce-c90.mp3',
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
$out['rateb_debit'] = $bal0 - $bal1;
$out['file_url'] = 'https://ai.rateb.sa/app/media/file.php?id=' . $mediaId;
$out['campaign_url'] = 'https://ai.rateb.sa/campaign.php?id=90&play=1#voice';
$out['ok'] = ($bal1 === $bal0 && $mediaId > 0);
$out['proof'] = 'pronunciation layer + easyvoice ar_m1; approved copy untouched; no RATEB debit';
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($out['ok'] ? 0 : 2);
