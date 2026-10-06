<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../media/library.php';
require_once __DIR__ . '/../plans.php';
require_login();

$voice = (string) ($_GET['voice'] ?? '');
$allowed = [];
foreach (array_merge(rateb_voice_choices('ar'), rateb_voice_choices('en')) as $choice) {
    $allowed[$choice['id']] = $choice;
}
if (!isset($allowed[$voice])) {
    http_response_code(404);
    exit('Not found.');
}

$dir = MEDIA_ROOT . '/voice-samples';
if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
    http_response_code(500);
    exit('Unavailable.');
}
$path = $dir . '/' . $voice . '.wav';
if (!is_file($path) || filesize($path) < 44) {
    session_write_close();
    $cfg = require __DIR__ . '/../../config/ai.php';
    if (empty($cfg['api_key'])) {
        http_response_code(503);
        exit('Unavailable.');
    }
    $arabic = isset(rateb_voice_choices('ar')[0]) && in_array($voice, array_column(rateb_voice_choices('ar'), 'id'), true);
    $model = $arabic ? 'canopylabs/orpheus-arabic-saudi' : 'canopylabs/orpheus-v1-english';
    $text = $arabic ? 'مرحبا، هذا نموذج الصوت قبل التسجيل.' : 'Hello, this is a short voice sample.';
    $payload = json_encode([
        'model' => $model,
        'input' => $text,
        'voice' => $voice,
        'response_format' => 'wav',
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init(rtrim((string) $cfg['base_url'], '/') . '/audio/speech');
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
        http_response_code(502);
        exit('Unavailable.');
    }
    $faster = rateb_preview_faster((string) $audio, 1.08);
    file_put_contents($path, $faster ?? $audio);
}

header('Content-Type: audio/wav');
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: private, max-age=86400');
readfile($path);

function rateb_preview_faster(string $audio, float $tempo): ?string
{
    if ($tempo <= 1 || substr($audio, 0, 4) !== 'RIFF') {
        return null;
    }
    $offset = 12;
    $length = strlen($audio);
    while ($offset + 24 <= $length) {
        $id = substr($audio, $offset, 4);
        $size = unpack('V', substr($audio, $offset + 4, 4))[1];
        if ($id === 'fmt ' && $size >= 16) {
            $rate = unpack('V', substr($audio, $offset + 12, 4))[1];
            $bytes = unpack('V', substr($audio, $offset + 16, 4))[1];
            if ($rate < 8000 || $rate > 96000) {
                return null;
            }
            $audio = substr_replace($audio, pack('V', (int) round($rate * $tempo)), $offset + 12, 4);
            return substr_replace($audio, pack('V', (int) round($bytes * $tempo)), $offset + 16, 4);
        }
        $step = 8 + $size + ($size % 2);
        if ($step < 8) {
            return null;
        }
        $offset += $step;
    }
    return null;
}
