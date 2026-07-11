<?php

declare(strict_types=1);

/**
 * Phase P1 ERP Offline Warm Identity test runner.
 *
 *   php offline/tests/run-erp-offline-identity-tests.php
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

require_once __DIR__ . '/ErpOfflineIdentityPhaseP1Test.php';

$runner = new ErpOfflineIdentityPhaseP1Test();
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

echo PHP_EOL . ($failed === 0 ? 'GATE: CLEAR' : 'GATE: BLOCKED') . " ({$failed} failed / " . count($results) . ' total)' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
