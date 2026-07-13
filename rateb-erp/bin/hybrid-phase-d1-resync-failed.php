<?php
declare(strict_types=1);
/** Re-sign failed outbox rows with current sync key, then drain. */
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
use Rateb\App\Core\HybridSyncCrypto;
use Rateb\App\Core\HybridSyncEngine;
use Rateb\App\Core\SqliteSchemaBootstrap;

HybridRuntime::reset();
Database::disconnect();

$pdo = Database::connection();
$failed = $pdo->query("SELECT id, uuid, payload_json, payload_hash, signature FROM rateb_sync_outbox WHERE status='failed'")->fetchAll(PDO::FETCH_ASSOC);
$upd = $pdo->prepare("UPDATE rateb_sync_outbox SET signature=:s, status='pending', retry_count=0, last_error='' WHERE id=:id");
$n = 0;
foreach ($failed as $row) {
    $hash = (string) $row['payload_hash'];
    if ($hash === '' || $hash !== HybridSyncCrypto::hashPayload((string) $row['payload_json'])) {
        $hash = HybridSyncCrypto::hashPayload((string) $row['payload_json']);
        $pdo->prepare('UPDATE rateb_sync_outbox SET payload_hash=:h WHERE id=:id')->execute(['h' => $hash, 'id' => $row['id']]);
    }
    $sig = HybridSyncCrypto::sign($hash, (string) $row['uuid']);
    $upd->execute(['s' => $sig, 'id' => $row['id']]);
    $n++;
}
echo "re_signed={$n}\n";

$mirror = HybridSyncConfig::mirrorPath();
$mirrorPdo = new PDO('sqlite:' . $mirror, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
SqliteSchemaBootstrap::ensureErpSchema($mirrorPdo);

$engine = new HybridSyncEngine();
$engine->resumeInterrupted($pdo);
$total = ['accepted' => 0, 'duplicate' => 0, 'failed' => 0, 'conflict' => 0];
for ($i = 0; $i < 50; $i++) {
    $r = $engine->pushPending($pdo, 50);
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
$inbox = (int) $mirrorPdo->query('SELECT COUNT(*) FROM rateb_sync_cloud_inbox')->fetchColumn();
$dup = (int) $mirrorPdo->query(
    "SELECT COUNT(*) FROM (SELECT idempotency_key, COUNT(*) c FROM rateb_sync_cloud_inbox GROUP BY idempotency_key HAVING c>1)"
)->fetchColumn();

// second drain — must be all duplicates / no new pending
$r2 = $engine->pushPending($pdo, 50);

echo json_encode([
    'totals' => $total,
    'status' => $status,
    'cloud_inbox' => $inbox,
    'duplicate_keys' => $dup,
    'second_push' => $r2,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
