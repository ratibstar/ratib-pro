#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * CLI: check all Rateb databases.
 * Usage: php scripts/check-all-databases.php
 */
$projectRoot = dirname(__DIR__);
$cpEnv = $projectRoot . '/control-panel/config/env.php';
if (is_file($cpEnv)) {
    require_once $cpEnv;
} else {
    require_once $projectRoot . '/config/env/load.php';
}

require_once $projectRoot . '/control-panel/includes/control/check-all-databases-lib.php';

[$report, $allPass] = control_check_all_databases_run();
echo $report;
exit($allPass ? 0 : 1);
