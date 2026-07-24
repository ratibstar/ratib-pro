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

require_once __DIR__ . '/PosSyncValidateTest.php';

$test = new PosSyncValidateTest();
$results = $test->run();
$failed = 0;
foreach ($results as $row) {
    $mark = $row['passed'] ? 'PASS' : 'FAIL';
    echo $mark . ' ' . $row['name'] . ' — ' . $row['detail'] . PHP_EOL;
    if (!$row['passed']) {
        $failed++;
    }
}
exit($failed > 0 ? 1 : 0);
