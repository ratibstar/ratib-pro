<?php
declare(strict_types=1);
/** Bootstrap cloud mirror schema and re-drain outbox (ops helper). */
$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);

$serve = $root . '/storage/branch/serve.env';
foreach (file($serve, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    putenv(trim($k) . '=' . trim($v));
    $_ENV[trim($k)] = trim($v);
}

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\Database;
use Rateb\App\Core\HybridRuntime;
use Rateb\App\Core\HybridSyncConfig;
use Rateb\App\Core\HybridSyncEngine;
use Rateb\App\Core\SqliteSchemaBootstrap;

HybridRuntime::reset();
Database::disconnect();

$mirror = HybridSyncConfig::mirrorPath();
echo "mirror={$mirror}\n";
echo "sink=" . HybridSyncConfig::sinkMode() . "\n";

// Ensure mirror DB has ERP schema
$mirrorPdo = new PDO('sqlite:' . $mirror, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$schema = SqliteSchemaBootstrap::ensureErpSchema($mirrorPdo);
echo 'mirror_schema tables≈' . ($schema['tables'] ?? '?') . "\n";

$pdo = Database::connection();
$reset = $pdo->exec(
    "UPDATE rateb_sync_outbox SET status='pending', retry_count=0, last_error='' WHERE status IN ('failed','conflict','syncing')"
);
echo "reset_to_pending={$reset}\n";

$engine = new HybridSyncEngine();
$engine->resumeInterrupted($pdo);
$total = ['accepted' => 0, 'duplicate' => 0, 'failed' => 0, 'conflict' => 0];
for ($i = 0; $i < 200; $i++) {
    $r = $engine->pushPending($pdo, 50);
    if (!empty($r['paused'])) {
        echo json_encode(['paused' => true, 'r' => $r], JSON_UNESCAPED_UNICODE) . "\n";
        break;
    }
    foreach (['accepted', 'duplicate', 'failed', 'conflict'] as $k) {
        $total[$k] += (int) ($r[$k] ?? 0);
    }
    $left = (int) $pdo->query(
        "SELECT COUNT(*) FROM rateb_sync_outbox WHERE status IN ('pending','failed','syncing')"
    )->fetchColumn();
    if ($left === 0) {
        break;
    }
}

$status = $engine->status($pdo);
echo json_encode(['totals' => $total, 'status' => $status], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

// idempotency: cloud inbox unique keys
try {
    $inbox = $mirrorPdo->query('SELECT COUNT(*) FROM rateb_sync_cloud_inbox')->fetchColumn();
    $dup = $mirrorPdo->query(
        "SELECT COUNT(*) FROM (SELECT idempotency_key, COUNT(*) c FROM rateb_sync_cloud_inbox GROUP BY idempotency_key HAVING c>1)"
    )->fetchColumn();
    echo "cloud_inbox={$inbox} duplicate_keys={$dup}\n";
} catch (Throwable $e) {
    echo 'inbox_err=' . $e->getMessage() . "\n";
}
