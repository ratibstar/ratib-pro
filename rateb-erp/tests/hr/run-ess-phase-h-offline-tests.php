<?php

declare(strict_types=1);

/**
 * Phase H ESS offline hardening test runner.
 *
 * php tests/hr/run-ess-phase-h-offline-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require RATEB_ROOT . '/app/Core/Bootstrap.php';
try {
    Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
} catch (Throwable $e) {
    fwrite(STDERR, 'Bootstrap soft-fail: ' . $e->getMessage() . PHP_EOL);
}

require_once RATEB_ROOT . '/offline/OfflineModule.php';
\Rateb\App\Offline\OfflineModule::init();

require_once __DIR__ . '/EssPhaseHOfflineTest.php';

$results = (new EssPhaseHOfflineTest())->run();
$failed = count(array_filter($results, static fn ($r) => !$r['passed']));
echo PHP_EOL . 'GATE: ' . ($failed === 0 ? 'CLEAR' : 'FAIL') . " ({$failed}/" . count($results) . ' failed)' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
