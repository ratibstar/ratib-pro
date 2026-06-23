#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * RCC automated backup runner — schedule via cron (daily).
 * Usage: php bin/rcc-backup-runner.php [--tenant=ID] [--type=full|tenant]
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

define('RCC_ROOT', dirname(__DIR__));
define('RCC_SKIP_ORCHESTRATOR_BOOT', true);
require RCC_ROOT . '/bootstrap.php';

use Ratib\ContactCenter\App\Application\Services\DisasterRecovery\BackupRestoreService;

$tenantId = null;
$type = 'full';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--tenant=')) {
        $tenantId = (int) substr($arg, 9);
    }
    if (str_starts_with($arg, '--type=')) {
        $type = substr($arg, 7);
    }
}

$service = new BackupRestoreService();
$result = $service->startBackup($tenantId > 0 ? $tenantId : null, $type, null);
echo json_encode(['ok' => true, 'backup' => $result], JSON_UNESCAPED_UNICODE) . "\n";
