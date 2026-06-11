<?php
declare(strict_types=1);

/**
 * RATEB ERP cron — run every 5–15 minutes via cPanel/VPS scheduler.
 * Usage: php bin/erp-cron.php
 */
define('RATEB_ROOT', dirname(__DIR__));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once RATEB_ROOT . '/app/services/CronService.php';
require_once RATEB_ROOT . '/app/services/QueueWorkerService.php';
require_once RATEB_ROOT . '/app/services/Logger.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$stats = (new Rateb\App\Services\CronService())->runAll();
foreach ($stats as $key => $val) {
    echo $key . ': ' . $val . PHP_EOL;
}
