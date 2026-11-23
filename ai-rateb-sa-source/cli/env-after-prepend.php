<?php
declare(strict_types=1);

$ini = dirname(__DIR__) . '/.user.ini';
echo "user_ini=" . (is_readable($ini) ? 'yes' : 'no') . PHP_EOL;
if (is_readable($ini)) {
    echo trim((string) file_get_contents($ini)) . PHP_EOL;
    if (preg_match('/^\s*auto_prepend_file\s*=\s*"?([^"\r\n]+?)"?\s*$/m', (string) file_get_contents($ini), $match)) {
        $prepend = trim($match[1]);
        echo 'prepend=' . $prepend . PHP_EOL;
        if (is_readable($prepend)) {
            require $prepend;
            echo "prepend_loaded=yes\n";
        } else {
            echo "prepend_loaded=no\n";
        }
    }
}

foreach ([
    'RATEB_AI_API_KEY',
    'DEEPSEEK_API_KEY',
    'HORDE_API_KEY',
    'SAWTAK_API_KEY',
    'MOYASAR_SECRET_KEY',
    'MOYASAR_PUBLISHABLE_KEY',
    'MOYASAR_WEBHOOK_SECRET',
    'RUNWAY_API_KEY',
] as $key) {
    $value = getenv($key);
    echo $key . '=' . (is_string($value) && trim($value) !== '' ? 'present' : 'absent') . PHP_EOL;
}

$ai = require dirname(__DIR__) . '/config/ai.php';
echo 'resolved.text=' . (trim((string) ($ai['api_key'] ?? '')) !== '' ? 'present' : 'absent') . PHP_EOL;
echo 'resolved.moyasar_secret=' . (trim((string) ($ai['moyasar_secret_key'] ?? '')) !== '' ? 'present' : 'absent') . PHP_EOL;
echo 'resolved.moyasar_webhook=' . (trim((string) ($ai['moyasar_webhook_secret'] ?? '')) !== '' ? 'present' : 'absent') . PHP_EOL;
