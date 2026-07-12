<?php
declare(strict_types=1);

/**
 * Phase D — Enterprise Branch Appliance installer (cold-start, offline).
 *
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-appliance-install.php
 * php ... bin/hybrid-branch-appliance-install.php --force
 * php ... bin/hybrid-branch-appliance-install.php --sink=mirror   # certification / offline
 */

$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\BranchApplianceInstaller;
use Rateb\App\Core\HybridRuntime;

$force = in_array('--force', $argv, true);
$sink = 'mysql';
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--sink=')) {
        $sink = substr($a, 7);
    }
}

HybridRuntime::ensureBranchStorage();
$result = (new BranchApplianceInstaller())->install(['force' => $force, 'sink' => $sink]);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(!empty($result['ok']) ? 0 : 1);
