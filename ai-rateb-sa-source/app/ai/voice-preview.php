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
if (empty($voice['preview_url'])) {
    http_response_code(404);
    exit('No preview.');
}

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
if ($code === 404 || !is_string($audio) || $code < 200 || $code >= 300 || !str_starts_with($audio, 'RIFF')) {
    http_response_code(404);
    exit('No preview.');
}
header('Content-Type: ' . (str_contains($type, 'audio/') ? $type : 'audio/wav'));
header('Content-Length: ' . (string) strlen($audio));
header('Cache-Control: private, max-age=3600');
echo $audio;
