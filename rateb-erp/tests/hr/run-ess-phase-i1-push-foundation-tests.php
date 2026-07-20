<?php

declare(strict_types=1);

/**
 * Phase I.1 push foundation test runner.
 *
 * php tests/hr/run-ess-phase-i1-push-foundation-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

require_once __DIR__ . '/ArrayMobileDeviceStore.php';
require_once __DIR__ . '/EssPhaseI1PushFoundationTest.php';

$results = (new EssPhaseI1PushFoundationTest())->run();
$failed = count(array_filter($results, static fn ($r) => !$r['passed']));
echo PHP_EOL . 'GATE: ' . ($failed === 0 ? 'CLEAR' : 'FAIL') . " ({$failed}/" . count($results) . ' failed)' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
