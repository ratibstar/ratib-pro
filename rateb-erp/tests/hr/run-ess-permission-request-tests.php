<?php

declare(strict_types=1);

/**
 * ESS permission requests test runner.
 *
 * php tests/hr/run-ess-permission-request-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require_once __DIR__ . '/EssPermissionRequestTest.php';

$results = (new EssPermissionRequestTest())->run();
$failed = count(array_filter($results, static fn ($r) => !$r['passed']));
echo PHP_EOL . 'GATE: ' . ($failed === 0 ? 'CLEAR' : 'FAIL') . " ({$failed}/" . count($results) . ' failed)' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
