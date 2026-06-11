<?php
declare(strict_types=1);

/**
 * Run RATEB ERP migrations via CLI or browser (protect in production).
 * Usage: php migrations/run.php
 */
define('RATEB_ROOT', dirname(__DIR__));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

require_once RATEB_ROOT . '/app/services/MigrationService.php';
$log = (new Rateb\App\Services\MigrationService())->runAll();
foreach ($log as $line) {
    echo $line . PHP_EOL;
}
