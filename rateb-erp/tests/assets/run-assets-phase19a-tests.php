<?php

declare(strict_types=1);

/**
 * Phase 19A — Assets Online test runner.
 *
 * php tests/assets/run-assets-phase19a-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

require_once __DIR__ . '/AssetsPhase19ATest.php';

$results = (new AssetsPhase19ATest())->run();
$failed = count(array_filter($results, static fn ($r) => !$r['passed']));
$passed = count($results) - $failed;
echo PHP_EOL . 'GATE: ' . ($failed === 0 ? 'CLEAR' : 'FAIL') . " ({$failed}/" . count($results) . ' failed)' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
