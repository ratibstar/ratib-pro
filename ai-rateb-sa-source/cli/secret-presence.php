<?php
declare(strict_types=1);

echo 'ai_local=' . (is_file(dirname(__DIR__) . '/config/ai.local.php') ? 'yes' : 'no') . PHP_EOL;
$secretFile = '/home/admin/.secrets/rateb-ai-env.php';
echo 'secrets_file=' . (is_file($secretFile) ? 'yes' : 'no') . PHP_EOL;

$show = static function (string $label, mixed $value): void {
    $ok = is_string($value) ? trim($value) !== '' : !empty($value);
    echo $label . '=' . ($ok ? 'present' : 'absent') . PHP_EOL;
};

$localFile = dirname(__DIR__) . '/config/ai.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        echo 'ai_local_keys=' . implode(',', array_keys($local)) . PHP_EOL;
        foreach ($local as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $show('ai_local.' . (string) $key, (string) $value);
            }
        }
    } else {
        echo "ai_local_shape=not_array\n";
    }
}

if (is_file($secretFile)) {
    $raw = (string) file_get_contents($secretFile);
    echo 'secrets_bytes=' . strlen($raw) . PHP_EOL;
    echo 'secrets_starts_with_php=' . (str_starts_with(ltrim($raw), '<?php') ? 'yes' : 'no') . PHP_EOL;
    foreach ([
        'DEEPSEEK_API_KEY', 'RATEB_AI_API_KEY', 'HORDE_API_KEY', 'SAWTAK_API_KEY',
        'MOYASAR_SECRET_KEY', 'MOYASAR_WEBHOOK_SECRET', 'RUNWAY_API_KEY',
        'deepseek_api_key', 'api_key', 'horde_api_key', 'sawtak_api_key',
        'moyasar_secret_key', 'moyasar_webhook_secret', 'runway_api_key',
    ] as $needle) {
        echo 'secrets_mentions.' . $needle . '=' . (str_contains($raw, $needle) ? 'yes' : 'no') . PHP_EOL;
    }
    // Detect putenv/$_ENV style without printing values.
    echo 'secrets_has_putenv=' . (str_contains($raw, 'putenv') ? 'yes' : 'no') . PHP_EOL;
    echo 'secrets_has_return=' . (preg_match('/\breturn\b/', $raw) === 1 ? 'yes' : 'no') . PHP_EOL;
}

$ai = require dirname(__DIR__) . '/config/ai.php';
$show('resolved.text_api_key', $ai['api_key'] ?? '');
$show('resolved.horde', $ai['horde_api_key'] ?? '');
$show('resolved.sawtak', $ai['sawtak_api_key'] ?? '');
$show('resolved.moyasar_secret', $ai['moyasar_secret_key'] ?? '');
$show('resolved.moyasar_webhook', $ai['moyasar_webhook_secret'] ?? '');
echo 'resolved.text_provider=' . (string) ($ai['provider'] ?? '') . PHP_EOL;
echo 'resolved.text_base=' . (string) ($ai['base_url'] ?? '') . PHP_EOL;

require dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/admin/lib.php';
foreach (admin_provider_snapshot() as $row) {
    $id = (string) ($row['label'] ?? $row['id'] ?? 'row');
    echo 'admin_provider.' . $id . '=' . (!empty($row['configured']) ? 'present' : 'absent') . ';selected=' . (!empty($row['selected']) ? '1' : '0') . PHP_EOL;
}
