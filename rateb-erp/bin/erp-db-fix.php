#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * CLI: run ERP migrations + role dedupe on admin_rateb-erp
 * Usage (cPanel Terminal): php rateb-erp/bin/erp-db-fix.php
 */
$root = dirname(__DIR__);
define('RATEB_ROOT', str_replace('\\', '/', realpath($root) ?: $root));

$cpEnv = dirname($root) . '/control-panel/config/env.php';
if (is_file($cpEnv)) {
    require_once $cpEnv;
}

require_once RATEB_ROOT . '/config/database.php';
require_once RATEB_ROOT . '/app/Core/Database.php';
require_once RATEB_ROOT . '/app/services/MigrationService.php';
require_once RATEB_ROOT . '/app/services/AuthorizationService.php';
require_once RATEB_ROOT . '/app/services/ErpDatabaseService.php';

$svc = new \Rateb\App\Services\ErpDatabaseService();
foreach ($svc->fixErpDatabase() as $line) {
    echo $line, PHP_EOL;
}

$erp = $svc->diagnoseErp();
$cp = $svc->diagnoseControlPanel();
echo PHP_EOL, '=== Summary ===', PHP_EOL;
echo 'ERP ', ($erp['db'] ?? '?'), ': tables=', ($erp['rateb_tables'] ?? 0), ' permissions=', ($erp['permissions'] ?? 0), ' roles=', ($erp['roles'] ?? 0), PHP_EOL;
echo 'CP  ', ($cp['db'] ?? '?'), ': rateb_tables=', ($cp['rateb_tables'] ?? 0), ($cp['warning'] ?? '' ? ' WARNING' : ''), PHP_EOL;
