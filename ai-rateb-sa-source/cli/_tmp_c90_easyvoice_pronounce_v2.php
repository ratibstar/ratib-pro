<?php
declare(strict_types=1);
/**
 * ONE EasyVoice Free pronunciation retest for Campaign 90.
 * Sends Latin "RATEB AI" through the normalizer; verifies spoken audio via ASR.
 * No approved-copy mutation. No RATEB debit.
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
$source = 'مرحباً بكم في RATEB AI، منصة ذكية لأتمتة أعمالكم.';
$ttsInput = rateb_tts_normalize_pronunciation($source);
$chars = mb_strlen($ttsInput, 'UTF-8');

$bal0 = (int) $pdo->query('SELECT credits_balance FROM users WHERE id=66')->fetchColumn();
$out = [
    'utc_now' => gmdate('c'),
    'campaign_id' => $campaignId,
    'provider' => 'easyvoice',
    'voice' => $voiceId,
    'source_script' => $source,
    'spoken_input' => $ttsInput,
    'chars' => $chars,
    'balance_before' => $bal0,
    'media_272_asr' => 'مرحبا بكم في رتيب إيه آي، منصة ذكية لأتمتة أعمالكم.',
    'media_272_error' => 'رَتِّب heard as رتيب',
];

if ($bal0 !== 40) {
    $out['ok'] = false;
    $out['error'] = 'balance_not_40';
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(2);
}
if ($ttsInput === $source || !str_contains($ttsInput, 'راتب')) {
    $out['ok'] = false;
    $out['error'] = 'normalizer_did_not_apply_undiacritized_brand';
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(2);
}
if (!rateb_easyvoice_configured()) {
    $out['ok'] = false;
    $out['error'] = 'easyvoice_not_ready';
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(2);
}

$usageBefore = rateb_easyvoice_get_usage();
$out['quota_before'] = [
    'plan' => $usageBefore['plan'],
    'used' => $usageBefore['used'],
    'remaining' => $usageBefore['remaining'],
    'limit' => $usageBefore['limit'],
];
if (
    !$usageBefore['ok']
    || (string) ($usageBefore['plan'] ?? '') !== 'free'
    || (int) ($usageBefore['remaining'] ?? 0) < $chars
) {
    $out['ok'] = false;
    $out['error'] = 'quota_block';
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

$pdo->prepare("UPDATE campaign_media SET approval_status='rejected' WHERE campaign_id=? AND kind='audio' AND approval_status='approved'")
    ->execute([$campaignId]);

$speech = rateb_easyvoice_speech($source, $voiceId, 'mp3', 1.0);
$out['spoken_input_sent'] = (string) ($speech['spoken_input'] ?? '');
$out['speech_ok'] = $speech['ok'];
$out['speech_http'] = $speech['http'];
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
    'voiceover-ar-easyvoice-pronounce-v2.mp3',
    $speech['mime'] !== '' ? $speech['mime'] : 'audio/mpeg',
    $speech['bytes']
);
$pdo->prepare("UPDATE campaign_media SET approval_status='approved' WHERE id=? AND campaign_id=?")->execute([$mediaId, $campaignId]);

// ASR verify (Cloudflare Whisper large — diagnostic only, not a TTS provider).
$path = media_absolute_path(
    $pdo->query('SELECT * FROM campaign_media WHERE id=' . (int) $mediaId)->fetch(PDO::FETCH_ASSOC)
);
$ff = '/home/admin/bin/ffmpeg';
$wav = '/tmp/m_pronounce_v2.wav';
passthru(escapeshellarg($ff) . ' -y -i ' . escapeshellarg($path) . ' -ac 1 -ar 16000 -f wav ' . escapeshellarg($wav) . ' 2>/dev/null', $rc);
$acct = trim((string) (getenv('CLOUDFLARE_ACCOUNT_ID') ?: getenv('RATEB_CF_ACCOUNT_ID') ?: ''));
$token = trim((string) (getenv('CLOUDFLARE_API_TOKEN') ?: getenv('RATEB_CF_API_TOKEN') ?: ''));
$transcript = '';
if ($rc === 0 && is_file($wav) && $acct !== '' && $token !== '') {
    $payload = json_encode(['audio' => base64_encode((string) file_get_contents($wav))]);
    $ch = curl_init('https://api.cloudflare.com/client/v4/accounts/' . rawurlencode($acct) . '/ai/run/@cf/openai/whisper-large-v3-turbo');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 120,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    $j = json_decode((string) $body, true);
    $transcript = trim((string) ($j['result']['text'] ?? ''));
}
$out['asr_transcript'] = $transcript;
$heardBrandOk = $transcript !== ''
    && (str_contains($transcript, 'راتب') || str_contains($transcript, 'راتِب'))
    && !str_contains($transcript, 'رتيب');
$out['brand_pronunciation_ok'] = $heardBrandOk;

$usageAfter = rateb_easyvoice_get_usage();
$bal1 = (int) $pdo->query('SELECT credits_balance FROM users WHERE id=66')->fetchColumn();
$out['quota_after'] = [
    'plan' => $usageAfter['plan'],
    'used' => $usageAfter['used'],
    'remaining' => $usageAfter['remaining'],
    'limit' => $usageAfter['limit'],
];
$out['audio_media_id'] = $mediaId;
$out['balance_after'] = $bal1;
$out['campaign_url'] = 'https://ai.rateb.sa/campaign.php?id=90&play=1#voice';
$out['ok'] = ($bal1 === 40 && $mediaId > 0 && $heardBrandOk);
$out['error'] = $out['ok'] ? '' : ($heardBrandOk ? 'balance_or_store' : 'brand_still_mispronounced');
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($out['ok'] ? 0 : 2);
