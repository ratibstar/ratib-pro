<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$ai = require dirname(__DIR__, 2) . '/config/ai.php';
$provider = strtolower(trim((string) ($ai['image_provider'] ?? 'horde')));
if (!in_array($provider, ['horde', 'cloudflare'], true)) {
    $provider = 'horde';
}
$model = trim((string) ($ai['image_model'] ?? ''));
$expectedModel = '@cf/black-forest-labs/flux-1-schnell';
$lines = [
    'RATEB_IMAGE_PROVIDER=' . $provider,
    'CLOUDFLARE_ACCOUNT_ID=' . (((string) ($ai['cloudflare_account_id'] ?? '')) !== '' ? 'SET' : 'NOT_SET'),
    'CLOUDFLARE_API_TOKEN=' . (((string) ($ai['cloudflare_api_token'] ?? '')) !== '' ? 'SET' : 'NOT_SET'),
    'RATEB_IMAGE_MODEL=' . ($model === $expectedModel ? 'EXPECTED' : ($model !== '' ? 'OTHER' : 'NOT_SET')),
];
echo implode(PHP_EOL, $lines), PHP_EOL;
