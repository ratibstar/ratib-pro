<?php
declare(strict_types=1);

/**
 * Run RATEB ERP migrations via CLI only.
 * Usage: php migrations/run.php
 */
if (PHP_SAPI !== 'cli' && !defined('RATEB_MIGRATE_ALLOWED')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Forbidden. Run via CLI: php migrations/run.php');
}

define('RATEB_ROOT', dirname(__DIR__));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

require_once RATEB_ROOT . '/app/services/MigrationService.php';
$log = (new Rateb\App\Services\MigrationService())->runAll();
foreach ($log as $line) {
    echo $line . PHP_EOL;
}
