<?php

declare(strict_types=1);

/**
 * Enterprise Baseline v1.2 certification runner.
 *
 * php offline/tests/run-enterprise-baseline-v12-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once RATEB_ROOT . '/offline/OfflineModule.php';
\Rateb\App\Offline\OfflineModule::init();

require_once __DIR__ . '/EnterpriseBaselineV12CertificationTest.php';

$results = (new EnterpriseBaselineV12CertificationTest())->run();
$passed = count(array_filter($results, static fn ($r) => $r['passed']));
$total = count($results);
$failed = $total - $passed;
echo PHP_EOL . 'GATE: ' . ($failed === 0 ? 'CLEAR' : 'BLOCKED')
    . " ({$failed}/{$total} failed) — Enterprise Baseline v1.2" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
