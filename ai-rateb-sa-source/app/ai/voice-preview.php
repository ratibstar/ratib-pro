<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/sawtak.php';

$id = (string) ($_GET['voice'] ?? '');
$voice = rateb_sawtak_find($id);
if ($voice === null) {
    http_response_code(rateb_sawtak_configured() ? 404 : 503);
    exit(rateb_sawtak_configured() ? 'Not found.' : 'Not configured.');
}
$cacheDir = rtrim(defined('MEDIA_ROOT') ? MEDIA_ROOT : sys_get_temp_dir(), '/\\') . '/sawtak-preview';
$cacheFile = $cacheDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $id) . '.wav';
if (is_file($cacheFile)) {
    $cached = (string) file_get_contents($cacheFile);
    if (str_starts_with($cached, 'RIFF')) {
        header('Content-Type: audio/wav');
        header('Content-Length: ' . (string) strlen($cached));
        header('Cache-Control: public, max-age=86400');
        echo $cached;
        exit;
    }
}

$audio = false;
$code = 0;
$type = '';
if (!empty($voice['preview_url'])) {
    $handle = curl_init('https://api.sawtakarabi.ai/v1/voices/' . rawurlencode($id) . '/preview');
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20,
    ]);
    $audio = curl_exec($handle);
    $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $type = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
    curl_close($handle);
}
$key = trim((string) getenv('SAWTAK_API_KEY'));
if (($code === 404 || !is_string($audio) || $code < 200 || $code >= 300 || !str_starts_with((string) $audio, 'RIFF')) && $key !== '') {
    $line = trim((string) ($voice['preview_text'] ?? ''));
    if ($line === '') {
        $line = 'مرحبا بك';
    }
    if (function_exists('mb_substr')) {
        $line = mb_substr($line, 0, 80);
    } else {
        $line = substr($line, 0, 80);
    }
    $handle = curl_init('https://api.sawtakarabi.ai/v1/audio/speech');
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 55,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json', 'Accept: audio/wav'],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'arabic-tts-1',
            'input' => $line,
            'voice' => $id,
            'response_format' => 'wav',
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $audio = curl_exec($handle);
    $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $type = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
    curl_close($handle);
}
if (!is_string($audio) || $code < 200 || $code >= 300 || !str_starts_with($audio, 'RIFF')) {
    http_response_code(404);
    exit('No preview.');
}
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0700, true);
}
@file_put_contents($cacheFile, $audio);
header('Content-Type: ' . (str_contains($type, 'audio/') ? $type : 'audio/wav'));
header('Content-Length: ' . (string) strlen($audio));
header('Cache-Control: private, max-age=3600');
echo $audio;
