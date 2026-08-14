<?php

declare(strict_types=1);

/**
 * Phase I Employee Master 360 test runner.
 *
 * php tests/hr/run-hr-phase-i-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

require_once __DIR__ . '/HrPhaseIEmployee360Test.php';

$results = (new HrPhaseIEmployee360Test())->run();
$failed = count(array_filter($results, static fn ($r) => !$r['passed']));
echo PHP_EOL . 'GATE: ' . ($failed === 0 ? 'CLEAR' : 'FAIL') . " ({$failed}/" . count($results) . ' failed)' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
