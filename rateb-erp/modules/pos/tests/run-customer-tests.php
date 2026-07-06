<?php

declare(strict_types=1);

define('RATEB_ROOT', dirname(__DIR__, 3));

spl_autoload_register(static function (string $class): void {
    $prefix = 'Rateb\\App\\Pos\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    $path = RATEB_ROOT . '/modules/pos/app/' . $relative;
    if (is_file($path)) {
        require_once $path;
    }
});

require_once __DIR__ . '/PosV2CustomerTest.php';

$runner = new PosV2CustomerTest();
$results = $runner->run();

$failed = 0;
foreach ($results as $result) {
    $label = ($result['passed'] ? 'PASS' : 'FAIL') . ': ' . $result['name'];
    if (!$result['passed']) {
        $label .= ' — ' . $result['detail'];
        $failed++;
    }
    echo $label . PHP_EOL;
}

echo PHP_EOL . (count($results) - $failed) . '/' . count($results) . ' passed' . PHP_EOL;

exit($failed > 0 ? 1 : 0);
