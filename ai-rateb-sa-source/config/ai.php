<?php
declare(strict_types=1);

$local = [];
$localFile = __DIR__ . '/ai.local.php';
if (is_file($localFile)) {
    $loaded = require $localFile;
    if (is_array($loaded)) {
        $local = $loaded;
    }
}

$deepseek = trim((string) (getenv('DEEPSEEK_API_KEY') ?: ($local['deepseek_api_key'] ?? '')));
$legacy = trim((string) (getenv('RATEB_AI_API_KEY') ?: ($local['api_key'] ?? '')));

return [
    'provider' => $deepseek !== '' ? 'deepseek' : 'openai_compatible',
    'base_url' => $deepseek !== '' ? 'https://api.deepseek.com' : 'https://api.groq.com/openai/v1',
    'model' => $deepseek !== '' ? 'deepseek-chat' : 'openai/gpt-oss-120b',
    'api_key' => $deepseek !== '' ? $deepseek : $legacy,
    'horde_api_key' => trim((string) (getenv('HORDE_API_KEY') ?: ($local['horde_api_key'] ?? ''))),
    'sawtak_api_key' => trim((string) (getenv('SAWTAK_API_KEY') ?: ($local['sawtak_api_key'] ?? ''))),
    'moyasar_secret_key' => trim((string) (getenv('MOYASAR_SECRET_KEY') ?: ($local['moyasar_secret_key'] ?? ''))),
];
