<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use PDO;
use PDOException;

/**
 * Phase C — cloud sink for pushed outbox rows.
 * mysql: apply to central MySQL (when configured).
 * mirror: local SQLite cloud-SoT simulator for offline certification / stress.
 */
final class HybridSyncSink
{
    private ?PDO $sink = null;

    public function connection(): PDO
    {
        if ($this->sink instanceof PDO) {
            return $this->sink;
        }
        if (HybridSyncConfig::sinkMode() === 'mysql') {
            $this->sink = $this->openMysql();
        } else {
            $this->sink = $this->openMirror();
        }
        $this->ensureInbox($this->sink);

        return $this->sink;
    }

    public function reset(): void
    {
        $this->sink = null;
    }

    /**
     * Apply one outbox row idempotently.
     *
     * @param array<string, mixed> $row
     * @return array{status:string,reason?:string}
     */
    public function applyRow(array $row, HybridSyncConflictResolver $conflicts): array
    {
        $pdo = $this->connection();
        $idem = (string) ($row['idempotency_key'] ?? '');
        $uuid = (string) ($row['uuid'] ?? '');
        if ($idem === '') {
            return ['status' => 'rejected', 'reason' => 'missing_idempotency'];
        }

        $chk = $pdo->prepare('SELECT 1 FROM rateb_sync_cloud_inbox WHERE idempotency_key = :k LIMIT 1');
        $chk->execute(['k' => $idem]);
        if ($chk->fetchColumn()) {
            return ['status' => 'duplicate'];
        }

        $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);
        if (!is_array($payload)) {
            return ['status' => 'rejected', 'reason' => 'bad_payload'];
        }

        // Verify signature (reject replay / tamper)
        $hash = (string) ($row['payload_hash'] ?? '');
        $sig = (string) ($row['signature'] ?? '');
        if ($hash === '' || !HybridSyncCrypto::verify($hash, $sig, $uuid)) {
            return ['status' => 'rejected', 'reason' => 'bad_signature'];
        }
        if ($hash !== HybridSyncCrypto::hashPayload((string) ($row['payload_json'] ?? ''))) {
            return ['status' => 'rejected', 'reason' => 'hash_mismatch'];
        }

        $entity = (string) ($row['entity_table'] ?? '');
        $clientItem = [
            'version' => (int) ($row['version'] ?? 1),
            'entity' => $entity,
            'payload' => $payload,
        ];
        $serverItem = $this->peekServerVersion($pdo, $entity, $uuid);
        $decision = $conflicts->resolve($entity, $clientItem, $serverItem);
        if (($decision['action'] ?? '') === 'reject_client') {
            // Still record inbox to prevent retry storms / duplicates
            $this->markApplied($pdo, $idem, $uuid, $entity);

            return ['status' => 'conflict', 'reason' => (string) ($decision['reason'] ?? 'server_newer')];
        }

        $sql = (string) ($payload['sql'] ?? '');
        $params = $payload['params'] ?? null;
        if ($sql === '') {
            return ['status' => 'rejected', 'reason' => 'empty_sql'];
        }

        try {
            $pdo->beginTransaction();
            // Apply translated SQL for SQLite mirror; MySQL gets original dialect SQL.
            $applySql = HybridSyncConfig::sinkMode() === 'mirror'
                ? SqlDialectAdapter::toSqlite($sql)
                : $sql;
            if (is_array($params) && $params !== []) {
                $st = $pdo->prepare($applySql);
                $st->execute($this->normalizeParams($params));
            } else {
                $pdo->exec($applySql);
            }
            $this->markApplied($pdo, $idem, $uuid, $entity);
            $pdo->commit();

            return ['status' => 'accepted'];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Duplicate unique → treat as duplicate (idempotent)
            if (stripos($e->getMessage(), 'UNIQUE') !== false || stripos($e->getMessage(), '1062') !== false) {
                $this->markApplied($pdo, $idem, $uuid, $entity);

                return ['status' => 'duplicate'];
            }

            return ['status' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    /** Pull changes after cursor (incremental). */
    public function pullDelta(string $entity, int $afterId, int $limit = 100): array
    {
        $pdo = $this->connection();
        if (!$this->tableExists($pdo, $entity)) {
            return ['rows' => [], 'cursor' => $afterId];
        }
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT * FROM "' . preg_replace('/[^a-zA-Z0-9_]/', '', $entity) . '" WHERE rowid > :c ORDER BY rowid ASC LIMIT ' . $limit;
        if (HybridSyncConfig::sinkMode() === 'mysql') {
            $sql = 'SELECT * FROM `' . preg_replace('/[^a-zA-Z0-9_]/', '', $entity) . '` WHERE id > :c ORDER BY id ASC LIMIT ' . $limit;
        }
        try {
            $st = $pdo->prepare($sql);
            $st->execute(['c' => $afterId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $cursor = $afterId;
            foreach ($rows as $r) {
                $cursor = max($cursor, (int) ($r['rowid'] ?? $r['id'] ?? 0));
            }

            return ['rows' => $rows, 'cursor' => $cursor];
        } catch (\Throwable $e) {
            return ['rows' => [], 'cursor' => $afterId, 'error' => $e->getMessage()];
        }
    }

    private function markApplied(PDO $pdo, string $idem, string $uuid, string $entity): void
    {
        if (HybridSyncConfig::sinkMode() === 'mysql') {
            $pdo->prepare(
                'INSERT IGNORE INTO rateb_sync_cloud_inbox (idempotency_key, uuid, entity_table, applied_at)
                 VALUES (:k, :u, :t, :a)'
            )->execute(['k' => $idem, 'u' => $uuid, 't' => $entity, 'a' => gmdate('c')]);

            return;
        }
        $pdo->prepare(
            'INSERT OR IGNORE INTO rateb_sync_cloud_inbox (idempotency_key, uuid, entity_table, applied_at)
             VALUES (:k, :u, :t, :a)'
        )->execute(['k' => $idem, 'u' => $uuid, 't' => $entity, 'a' => gmdate('c')]);
    }

    /** @return array<string, mixed>|null */
    private function peekServerVersion(PDO $pdo, string $entity, string $uuid): ?array
    {
        try {
            $st = $pdo->prepare('SELECT uuid FROM rateb_sync_cloud_inbox WHERE uuid = :u LIMIT 1');
            $st->execute(['u' => $uuid]);
            if ($st->fetchColumn()) {
                return ['version' => PHP_INT_MAX, 'uuid' => $uuid];
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    private function ensureInbox(PDO $pdo): void
    {
        if (HybridSyncConfig::sinkMode() === 'mysql') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS rateb_sync_cloud_inbox (
                    idempotency_key VARCHAR(191) NOT NULL PRIMARY KEY,
                    uuid VARCHAR(64) NOT NULL,
                    entity_table VARCHAR(128) NOT NULL,
                    applied_at DATETIME NOT NULL,
                    UNIQUE KEY uq_sync_inbox_uuid (uuid)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );

            return;
        }
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rateb_sync_cloud_inbox (
                idempotency_key TEXT PRIMARY KEY NOT NULL,
                uuid TEXT NOT NULL UNIQUE,
                entity_table TEXT NOT NULL,
                applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }

    private function openMirror(): PDO
    {
        $path = HybridSyncConfig::mirrorPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA busy_timeout=30000');

        return $pdo;
    }

    private function openMysql(): PDO
    {
        if (!HybridSyncConfig::cloudMysqlConfigured()) {
            throw new PDOException('Hybrid sync MySQL sink not configured');
        }
        $host = (string) RATEB_DB_HOST;
        $port = defined('RATEB_DB_PORT') ? (int) RATEB_DB_PORT : 3306;
        $db = (string) RATEB_DB_NAME;
        $user = (string) RATEB_DB_USER;
        $pass = defined('RATEB_DB_PASS') ? (string) RATEB_DB_PASS : '';
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        if ($t === '') {
            return false;
        }
        try {
            if (HybridSyncConfig::sinkMode() === 'mysql') {
                $st = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
                $st->execute([$t]);

                return (bool) $st->fetchColumn();
            }
            $st = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = ? LIMIT 1");
            $st->execute([$t]);

            return (bool) $st->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @param array<int|string, mixed> $params @return array<int|string, mixed> */
    private function normalizeParams(array $params): array
    {
        return $params;
    }
}
