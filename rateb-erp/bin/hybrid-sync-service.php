<?php
declare(strict_types=1);

/**
 * Phase C.1 — Always-On Hybrid Sync Service entrypoint.
 *
 * Branch only:
 *   RATEB_RUNTIME=branch
 *   RATEB_HYBRID_SYNC_ENABLED=1
 *   RATEB_HYBRID_SYNC_SINK=mysql
 *
 * Usage:
 *   php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-sync-service.php
 *   php ... bin/hybrid-sync-service.php --max-cycles=3   # tests
 *   php ... bin/hybrid-sync-service.php --stop            # request graceful stop
 *
 * Orchestrates HybridSyncEngine only — no business logic.
 */

$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\HybridRuntime;
use Rateb\App\Core\HybridSyncDaemon;

$args = array_slice($argv, 1);
if (in_array('--stop', $args, true)) {
    HybridRuntime::ensureBranchStorage();
    $stopFile = HybridRuntime::branchStorageDir() . '/hybrid-sync.stop';
    file_put_contents($stopFile, gmdate('c') . PHP_EOL);
    fwrite(STDOUT, json_encode(['ok' => true, 'stop_file' => $stopFile], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(0);
}

$maxCycles = 0;
foreach ($args as $a) {
    if (str_starts_with($a, '--max-cycles=')) {
        $maxCycles = max(0, (int) substr($a, strlen('--max-cycles=')));
    }
}

$daemon = new HybridSyncDaemon();
$code = $daemon->run([
    'max_cycles' => $maxCycles,
    'pull_entities' => HybridSyncDaemon::defaultPullEntities(),
]);
exit($code);
