#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * RCC infrastructure monitor runner — schedule via cron (every minute).
 * Usage: php bin/rcc-monitor-runner.php
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

define('RCC_ROOT', dirname(__DIR__));
define('RCC_SKIP_ORCHESTRATOR_BOOT', true);
require RCC_ROOT . '/bootstrap.php';

use Ratib\ContactCenter\App\Application\Services\DisasterRecovery\MonitoringService;

$results = (new MonitoringService())->runAll(null);
$down = array_filter($results, static fn ($r) => ($r['status'] ?? '') === 'down');
echo json_encode(['ok' => true, 'checked' => count($results), 'alerts' => count($down), 'results' => $results], JSON_UNESCAPED_UNICODE) . "\n";
exit(count($down) > 0 ? 1 : 0);
