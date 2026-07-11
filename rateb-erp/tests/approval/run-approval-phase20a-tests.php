<?php

declare(strict_types=1);

/**
 * Phase 20A — Approval Online test runner.
 *
 * php tests/approval/run-approval-phase20a-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

require_once __DIR__ . '/ApprovalPhase20ATest.php';

$results = (new ApprovalPhase20ATest())->run();
$failed = count(array_filter($results, static fn ($r) => !$r['passed']));
$passed = count($results) - $failed;
echo PHP_EOL . 'GATE: ' . ($failed === 0 ? 'CLEAR' : 'FAIL') . " ({$failed}/" . count($results) . ' failed)' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
