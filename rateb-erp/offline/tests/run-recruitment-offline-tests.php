<?php

declare(strict_types=1);

/**
 * Phase 15B — Recruitment Offline test runner.
 *
 * php offline/tests/run-recruitment-offline-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once RATEB_ROOT . '/offline/OfflineModule.php';
\Rateb\App\Offline\OfflineModule::init();

require_once __DIR__ . '/RecruitmentOfflinePhase15bTest.php';

$results = (new RecruitmentOfflinePhase15bTest())->run();
$passed = count(array_filter($results, static fn ($r) => $r['passed']));
$total = count($results);
echo PHP_EOL . "{$passed}/{$total} passed" . PHP_EOL;
exit($passed === $total ? 0 : 1);
