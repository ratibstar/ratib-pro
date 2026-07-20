<?php

declare(strict_types=1);

/**
 * Phase I.2 push delivery test runner.
 *
 * php tests/hr/run-ess-phase-i2-push-delivery-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

require_once __DIR__ . '/ArrayMobileDeviceStore.php';
require_once __DIR__ . '/ArrayMobilePushOutboxStore.php';
require_once __DIR__ . '/RecordingFcmPushProvider.php';
require_once __DIR__ . '/EssPhaseI2PushDeliveryTest.php';

$results = (new EssPhaseI2PushDeliveryTest())->run();
$failed = count(array_filter($results, static fn ($r) => !$r['passed']));
echo PHP_EOL . 'GATE: ' . ($failed === 0 ? 'CLEAR' : 'FAIL') . " ({$failed}/" . count($results) . ' failed)' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
