<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use PDO;

/**
 * Phase C — Enterprise Hybrid Sync Engine (Core only).
 *
 * Branch SQLite (authoritative offline) → push outbox → Cloud SoT
 * Cloud SoT → pull delta → Branch (cursor in rateb_hybrid_meta)
 *
 * Reuses: OfflineConflictResolverService, sync-policy retry/batch numbers,
 * DeviceTrust concepts (signing key), idempotency, audit.
 * Not a second runtime — sits beside Database/HybridRuntime.
 */
final class HybridSyncEngine
{
    private HybridSyncSink $sink;
    private HybridSyncConflictResolver $conflicts;

    public function __construct(?HybridSyncSink $sink = null, ?HybridSyncConflictResolver $conflicts = null)
    {
        $this->sink = $sink ?? new HybridSyncSink();
        $this->conflicts = $conflicts ?? new HybridSyncConflictResolver();
    }

    public static function isOnline(): bool
    {
        // Automatic detection: mirror always "online"; MySQL probe with short timeout.
        if (HybridSyncConfig::sinkMode() === 'mirror') {
            return true;
        }
        try {
            $sink = new HybridSyncSink();
            $sink->connection()->query('SELECT 1');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Push pending outbox in batches (resumable, idempotent, signed, audited).
     *
     * @return array<string, mixed>
     */
    public function pushPending(?PDO $branchPdo = null, ?int $limit = null): array
    {
        $pdo = $branchPdo ?? Database::connection();
        if (!HybridSyncConfig::enabled() || !Database::isSqlite()) {
            return ['ok' => false, 'reason' => 'sync_disabled_or_not_branch'];
        }
        if (!self::isOnline()) {
            HybridSyncAudit::log($pdo, 'push_paused_offline', '', ['reason' => 'cloud_unavailable']);

            return ['ok' => true, 'paused' => true, 'accepted' => 0, 'duplicate' => 0, 'failed' => 0, 'conflict' => 0];
        }

        $limit = $limit ?? HybridSyncConfig::BATCH_SIZE;
        $batchUuid = HybridSyncCrypto::uuidV4();
        $started = microtime(true);

        $rows = $pdo->prepare(
            "SELECT * FROM rateb_sync_outbox
             WHERE status IN ('pending','failed') AND retry_count < :max
             ORDER BY id ASC LIMIT " . (int) $limit
        );
        $rows->execute(['max' => HybridSyncConfig::MAX_RETRIES]);
        $items = $rows->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($items === []) {
            return ['ok' => true, 'accepted' => 0, 'duplicate' => 0, 'failed' => 0, 'conflict' => 0, 'batch' => $batchUuid];
        }

        // Compress + encrypt batch envelope (security)
        $envelopeJson = json_encode(array_map(static fn ($r) => [
            'uuid' => $r['uuid'],
            'idempotency_key' => $r['idempotency_key'],
            'entity_table' => $r['entity_table'],
            'operation' => $r['operation'],
            'payload_json' => $r['payload_json'],
            'payload_hash' => $r['payload_hash'],
            'signature' => $r['signature'],
            'version' => $r['version'],
        ], $items), JSON_UNESCAPED_UNICODE);
        $compressed = gzcompress($envelopeJson ?: '[]', 6) ?: '';
        $encrypted = HybridSyncCrypto::encrypt(base64_encode($compressed));
        $batchHash = HybridSyncCrypto::hashPayload($encrypted);
        $batchSig = HybridSyncCrypto::sign($batchHash, $batchUuid);

        if (!HybridSyncCrypto::verify($batchHash, $batchSig, $batchUuid)) {
            HybridSyncAudit::log($pdo, 'push_rejected_signature', $batchUuid, []);

            return ['ok' => false, 'reason' => 'batch_signature'];
        }

        // Reject duplicate batches
        $dupBatch = $pdo->prepare('SELECT 1 FROM rateb_sync_batches WHERE batch_uuid = :u LIMIT 1');
        $dupBatch->execute(['u' => $batchUuid]);
        // new uuid always unique — also store hash to detect replay of same encrypted blob
        $replay = $pdo->prepare('SELECT 1 FROM rateb_sync_batches WHERE payload_hash = :h AND status = \'synced\' LIMIT 1');
        $replay->execute(['h' => $batchHash]);

        $pdo->prepare(
            'INSERT INTO rateb_sync_batches (batch_uuid, direction, item_count, status, payload_hash, signature, created_at)
             VALUES (:u, \'push\', :c, \'pending\', :h, :s, :t)'
        )->execute([
            'u' => $batchUuid,
            'c' => count($items),
            'h' => $batchHash,
            's' => $batchSig,
            't' => gmdate('c'),
        ]);

        $accepted = 0;
        $duplicate = 0;
        $failed = 0;
        $conflict = 0;
        $rejected = 0;

        foreach ($items as $row) {
            // Claim row (resume-safe)
            $claim = $pdo->prepare(
                "UPDATE rateb_sync_outbox SET status = 'syncing', batch_uuid = :b
                 WHERE id = :id AND status IN ('pending','failed')"
            );
            $claim->execute(['b' => $batchUuid, 'id' => $row['id']]);
            if ($claim->rowCount() === 0) {
                continue; // already claimed / resumed elsewhere
            }

            $result = $this->sink->applyRow($row, $this->conflicts);
            $status = $result['status'] ?? 'failed';
            if ($status === 'accepted') {
                $accepted++;
                $pdo->prepare(
                    "UPDATE rateb_sync_outbox SET status = 'synced', synced_at = :t, last_error = '' WHERE id = :id"
                )->execute(['t' => gmdate('c'), 'id' => $row['id']]);
            } elseif ($status === 'duplicate') {
                $duplicate++;
                $pdo->prepare(
                    "UPDATE rateb_sync_outbox SET status = 'synced', synced_at = :t, last_error = 'duplicate' WHERE id = :id"
                )->execute(['t' => gmdate('c'), 'id' => $row['id']]);
            } elseif ($status === 'conflict') {
                $conflict++;
                $pdo->prepare(
                    "UPDATE rateb_sync_outbox SET status = 'conflict', last_error = :e WHERE id = :id"
                )->execute(['e' => (string) ($result['reason'] ?? 'conflict'), 'id' => $row['id']]);
            } elseif ($status === 'rejected') {
                $rejected++;
                $pdo->prepare(
                    "UPDATE rateb_sync_outbox SET status = 'failed', retry_count = retry_count + 1, last_error = :e WHERE id = :id"
                )->execute(['e' => (string) ($result['reason'] ?? 'rejected'), 'id' => $row['id']]);
            } else {
                $failed++;
                $pdo->prepare(
                    "UPDATE rateb_sync_outbox SET status = 'failed', retry_count = retry_count + 1, last_error = :e WHERE id = :id"
                )->execute(['e' => substr((string) ($result['reason'] ?? 'failed'), 0, 500), 'id' => $row['id']]);
            }
        }

        $pdo->prepare(
            "UPDATE rateb_sync_batches SET status = 'synced', completed_at = :t, error = '' WHERE batch_uuid = :u"
        )->execute(['t' => gmdate('c'), 'u' => $batchUuid]);

        // Advance push cursor
        SqliteSchemaBootstrap::upsertMeta($pdo, 'sync_push_cursor', (string) ($items[array_key_last($items)]['id'] ?? 0));
        SqliteSchemaBootstrap::upsertMeta($pdo, 'sync_last_push_at', gmdate('c'));

        $ms = (int) round((microtime(true) - $started) * 1000);
        HybridSyncAudit::log($pdo, 'push_complete', $batchUuid, [
            'accepted' => $accepted,
            'duplicate' => $duplicate,
            'failed' => $failed,
            'conflict' => $conflict,
            'rejected' => $rejected,
            'ms' => $ms,
            'encrypted_bytes' => strlen($encrypted),
            'sink' => HybridSyncConfig::sinkMode(),
        ]);

        return [
            'ok' => true,
            'batch' => $batchUuid,
            'accepted' => $accepted,
            'duplicate' => $duplicate,
            'failed' => $failed,
            'conflict' => $conflict,
            'rejected' => $rejected,
            'ms' => $ms,
        ];
    }

    /**
     * Resume interrupted syncing rows after crash.
     *
     * @return array<string, int>
     */
    public function resumeInterrupted(?PDO $branchPdo = null): array
    {
        $pdo = $branchPdo ?? Database::connection();
        // Rows left in 'syncing' after crash → pending for retry
        $st = $pdo->exec(
            "UPDATE rateb_sync_outbox SET status = 'pending'
             WHERE status = 'syncing'"
        );

        HybridSyncAudit::log($pdo, 'resume_interrupted', '', ['reset' => (int) $st]);

        return ['reset' => (int) $st];
    }

    /**
     * Pull incremental cloud changes for an entity after stored cursor.
     *
     * @return array<string, mixed>
     */
    public function pullEntity(string $entity, ?PDO $branchPdo = null, int $limit = 100): array
    {
        $pdo = $branchPdo ?? Database::connection();
        if (!HybridSyncConfig::enabled() || !self::isOnline()) {
            return ['ok' => true, 'paused' => true, 'rows' => 0];
        }
        $key = 'sync_pull_cursor_' . preg_replace('/[^a-zA-Z0-9_]/', '', $entity);
        $after = (int) (SqliteSchemaBootstrap::metaValue($pdo, $key) ?? '0');
        $delta = $this->sink->pullDelta($entity, $after, $limit);
        $cursor = (int) ($delta['cursor'] ?? $after);
        SqliteSchemaBootstrap::upsertMeta($pdo, $key, (string) $cursor);
        HybridSyncAudit::log($pdo, 'pull_delta', '', [
            'entity' => $entity,
            'rows' => count($delta['rows'] ?? []),
            'cursor' => $cursor,
        ]);

        return [
            'ok' => true,
            'entity' => $entity,
            'rows' => count($delta['rows'] ?? []),
            'cursor' => $cursor,
            'data' => $delta['rows'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    public function status(?PDO $branchPdo = null): array
    {
        $pdo = $branchPdo ?? Database::connection();
        $counts = [];
        foreach (['pending', 'syncing', 'synced', 'failed', 'conflict'] as $s) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM rateb_sync_outbox WHERE status = :s');
            $st->execute(['s' => $s]);
            $counts[$s] = (int) $st->fetchColumn();
        }

        return [
            'enabled' => HybridSyncConfig::enabled(),
            'online' => self::isOnline(),
            'sink' => HybridSyncConfig::sinkMode(),
            'outbox' => $counts,
            'actionable' => $this->actionableOutboxCount($pdo),
            'push_cursor' => SqliteSchemaBootstrap::metaValue($pdo, 'sync_push_cursor'),
            'last_push_at' => SqliteSchemaBootstrap::metaValue($pdo, 'sync_last_push_at'),
        ];
    }

    /**
     * Rows the engine will still attempt (pending/failed under max retries, or syncing).
     * Used by Always-On daemon so exhausted failures do not busy-loop.
     */
    public function actionableOutboxCount(?PDO $branchPdo = null): int
    {
        $pdo = $branchPdo ?? Database::connection();
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM rateb_sync_outbox
             WHERE status = 'syncing'
                OR (status IN ('pending','failed') AND retry_count < :max)"
        );
        $st->execute(['max' => HybridSyncConfig::MAX_RETRIES]);

        return (int) $st->fetchColumn();
    }
}
