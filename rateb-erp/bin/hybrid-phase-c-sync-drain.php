<?php
declare(strict_types=1);

/**
 * Phase C — drain hybrid outbox (CLI worker / cron).
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-c-sync-drain.php
 */
$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\Database;
use Rateb\App\Core\HybridRuntime;
use Rateb\App\Core\HybridSyncEngine;

if (!HybridRuntime::shouldUseSqlite()) {
    echo "Not in branch SQLite mode — nothing to drain.\n";
    exit(0);
}

$engine = new HybridSyncEngine();
$engine->resumeInterrupted();
$total = ['accepted' => 0, 'duplicate' => 0, 'failed' => 0, 'conflict' => 0];
for ($i = 0; $i < 100; $i++) {
    $r = $engine->pushPending(null, 50);
    if (!empty($r['paused'])) {
        echo json_encode(['paused' => true, 'status' => $engine->status()], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(0);
    }
    foreach (['accepted', 'duplicate', 'failed', 'conflict'] as $k) {
        $total[$k] += (int) ($r[$k] ?? 0);
    }
    $left = (int) Database::connection()->query(
        "SELECT COUNT(*) FROM rateb_sync_outbox WHERE status IN ('pending','failed','syncing')"
    )->fetchColumn();
    if ($left === 0) {
        break;
    }
}
echo json_encode(['ok' => true, 'totals' => $total, 'status' => $engine->status()], JSON_UNESCAPED_UNICODE) . PHP_EOL;
