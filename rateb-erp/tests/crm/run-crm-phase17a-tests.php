<?php

declare(strict_types=1);

define('RATEB_ROOT', dirname(__DIR__, 2));

$bootstrap = RATEB_ROOT . '/app/Core/Bootstrap.php';
if (is_file($bootstrap)) {
    require_once $bootstrap;
    try {
        Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Bootstrap soft-fail: ' . $e->getMessage() . PHP_EOL);
    }
}

require_once __DIR__ . '/CrmPhase17ATest.php';

$results = (new CrmPhase17ATest())->run();
$failed = 0;
foreach ($results as $r) {
    if (!$r['passed']) {
        $failed++;
    }
}
$total = count($results);
echo PHP_EOL . 'GATE: ' . ($failed === 0 ? 'CLEAR' : 'BLOCKED')
    . " ({$failed}/{$total} failed)" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
