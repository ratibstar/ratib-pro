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

$cfAccount = trim((string) (getenv('CLOUDFLARE_ACCOUNT_ID')
    ?: getenv('RATEB_CF_ACCOUNT_ID')
    ?: ($local['cloudflare_account_id'] ?? '')));
$cfToken = trim((string) (getenv('CLOUDFLARE_API_TOKEN')
    ?: getenv('RATEB_CF_API_TOKEN')
    ?: ($local['cloudflare_api_token'] ?? '')));

return [
    'provider' => $deepseek !== '' ? 'deepseek' : 'openai_compatible',
    'base_url' => $deepseek !== '' ? 'https://api.deepseek.com' : 'https://api.groq.com/openai/v1',
    'model' => $deepseek !== '' ? 'deepseek-chat' : 'openai/gpt-oss-120b',
    'api_key' => $deepseek !== '' ? $deepseek : $legacy,
    'horde_api_key' => trim((string) (getenv('HORDE_API_KEY') ?: ($local['horde_api_key'] ?? ''))),
    'sawtak_api_key' => trim((string) (getenv('SAWTAK_API_KEY') ?: ($local['sawtak_api_key'] ?? ''))),
    'moyasar_secret_key' => trim((string) (getenv('MOYASAR_SECRET_KEY') ?: ($local['moyasar_secret_key'] ?? ''))),
    'moyasar_webhook_secret' => trim((string) (getenv('MOYASAR_WEBHOOK_SECRET') ?: ($local['moyasar_webhook_secret'] ?? ''))),
    // Image providers (failover order). Cloudflare Workers AI Flux is free-tier + fast when configured.
    'cloudflare_account_id' => $cfAccount,
    'cloudflare_api_token' => $cfToken,
    'cloudflare_image_model' => trim((string) (getenv('RATEB_CF_IMAGE_MODEL')
        ?: ($local['cloudflare_image_model'] ?? '@cf/black-forest-labs/flux-1-schnell'))),
    'image_providers' => trim((string) (getenv('RATEB_IMAGE_PROVIDERS')
        ?: ($local['image_providers'] ?? 'cloudflare,pollinations,horde'))),
    // OpenAI Images (provider 4, after Horde). Key ONLY from OPENAI_API_KEY — never from ai.local.php / Git.
    'openai_api_key' => trim((string) (getenv('OPENAI_API_KEY') ?: '')),
    'openai_image_model' => trim((string) (getenv('RATEB_OPENAI_IMAGE_MODEL') ?: 'gpt-image-2.6-flare')),
    // Temporary FREE-plan test gate. Off unless explicitly enabled for the authorized user.
    'openai_image_test' => trim((string) (getenv('RATEB_IMAGE_OPENAI_TEST') ?: '')) === '1',
    'openai_image_test_user_id' => (int) trim((string) (getenv('RATEB_IMAGE_OPENAI_TEST_USER_ID') ?: '66')),
];
