<?php

declare(strict_types=1);

/**
 * Phase 13 ERP Offline Master Data test runner.
 *
 *   php offline/tests/run-erp-offline-master-data-tests.php
 */

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

require_once RATEB_ROOT . '/offline/OfflineModule.php';
\Rateb\App\Offline\OfflineModule::init();

require_once __DIR__ . '/ErpOfflineMasterDataPhase13Test.php';

$runner = new ErpOfflineMasterDataPhase13Test();
$results = $runner->run();

$failed = 0;
foreach ($results as $result) {
    if (!$result['passed']) {
        $failed++;
    }
}

$total = count($results);
echo PHP_EOL . 'GATE: ' . ($failed === 0 ? 'CLEAR' : 'BLOCKED')
    . " ({$failed} failed / {$total} total)" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
