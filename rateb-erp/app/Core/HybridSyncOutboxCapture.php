<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use PDO;

/**
 * Phase C — transparent outbox capture after successful SQLite mutations.
 *
 * - Never writes outbox on the primary connection during a statement (preserves lastInsertId).
 * - Never writes outbox on a second connection while a write txn is open (avoids WAL lock waits).
 * - Buffers events during transactions; flushes after COMMIT via a dedicated writer connection.
 */
final class HybridSyncOutboxCapture
{
    private static bool $reentrant = false;

    private static ?PDO $outboxPdo = null;

    private static ?string $outboxPath = null;

    /** @var list<array<string, mixed>> */
    private static array $buffer = [];

    /** @var list<string> */
    private const SKIP_TABLES = [
        'rateb_sync_outbox',
        'rateb_sync_audit',
        'rateb_sync_batches',
        'rateb_sync_cloud_inbox',
        'rateb_hybrid_meta',
        'sqlite_sequence',
        'sqlite_master',
    ];

    public static function resetConnection(): void
    {
        self::$outboxPdo = null;
        self::$outboxPath = null;
        self::$buffer = [];
    }

    public static function registerShutdownFlush(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;
        register_shutdown_function(static function (): void {
            try {
                self::flushBuffered();
            } catch (\Throwable $e) {
            }
        });
    }

    public static function afterMutate(PDO $pdo, string $originalSql, ?array $boundParams = null): void
    {
        if (!HybridSyncConfig::captureEnabled() || self::$reentrant) {
            return;
        }
        $op = self::detectOperation($originalSql);
        if ($op === null) {
            return;
        }
        $table = self::detectTable($originalSql, $op);
        if ($table === null || self::shouldSkip($table)) {
            return;
        }

        $event = self::buildEvent($originalSql, $boundParams, $table, $op);
        if ($pdo->inTransaction()) {
            self::$buffer[] = $event;

            return;
        }
        self::persistEvent($event);
    }

    /** Flush buffered events after COMMIT (lock released). */
    public static function flushBuffered(): void
    {
        if (self::$buffer === []) {
            return;
        }
        $events = self::$buffer;
        self::$buffer = [];
        foreach ($events as $event) {
            self::persistEvent($event);
        }
    }

    /** Discard buffer on ROLLBACK. */
    public static function discardBuffered(): void
    {
        self::$buffer = [];
    }

    /** @param array<string, mixed>|null $boundParams @return array<string, mixed> */
    private static function buildEvent(string $originalSql, ?array $boundParams, string $table, string $op): array
    {
        $payload = [
            'sql' => $originalSql,
            'params' => $boundParams,
            'driver' => 'sqlite',
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $hash = HybridSyncCrypto::hashPayload($json);
        $uuid = HybridSyncCrypto::uuidV4();
        $tenant = 0;
        if (class_exists(TenantContext::class)) {
            try {
                $tenant = (int) (TenantContext::companyId() ?? 0);
            } catch (\Throwable $e) {
                $tenant = 0;
            }
        }

        return [
            'uuid' => $uuid,
            'table' => $table,
            'op' => $op,
            'json' => $json,
            'hash' => $hash,
            'idem' => 'obx_' . substr($hash, 0, 24) . '_' . substr(hash('sha256', $uuid), 0, 16),
            'sig' => HybridSyncCrypto::sign($hash, $uuid),
            'tenant' => $tenant,
            'now' => gmdate('c'),
        ];
    }

    /** @param array<string, mixed> $event */
    private static function persistEvent(array $event): void
    {
        self::$reentrant = true;
        try {
            $writer = self::writerPdo();
            $stmt = $writer->prepare(
                'INSERT OR IGNORE INTO rateb_sync_outbox
                (uuid, entity_table, entity_pk, operation, payload_json, version, idempotency_key,
                 occurred_at, status, retry_count, last_error, created_at,
                 tenant_id, branch_id, payload_hash, signature, batch_uuid, synced_at)
                 VALUES
                (:uuid, :tbl, :pk, :op, :payload, 1, :idem,
                 :occ, \'pending\', 0, \'\', :created,
                 :tenant, 0, :hash, :sig, \'\', \'\')'
            );
            $stmt->execute([
                'uuid' => $event['uuid'],
                'tbl' => $event['table'],
                'pk' => '',
                'op' => $event['op'],
                'payload' => $event['json'],
                'idem' => $event['idem'],
                'occ' => $event['now'],
                'created' => $event['now'],
                'tenant' => $event['tenant'],
                'hash' => $event['hash'],
                'sig' => $event['sig'],
            ]);
        } catch (\Throwable $e) {
            // never break ERP
        } finally {
            self::$reentrant = false;
        }
    }

    private static function writerPdo(): PDO
    {
        $path = HybridRuntime::sqlitePath();
        if (self::$outboxPdo instanceof PDO && self::$outboxPath === $path) {
            return self::$outboxPdo;
        }
        $writer = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $writer->exec('PRAGMA busy_timeout=30000');
        self::$outboxPdo = $writer;
        self::$outboxPath = $path;

        return $writer;
    }

    private static function shouldSkip(string $table): bool
    {
        $t = strtolower($table);
        if (in_array($t, self::SKIP_TABLES, true)) {
            return true;
        }
        if (str_starts_with($t, 'sqlite_') || str_starts_with($t, 'rateb_sync_')) {
            return true;
        }

        return false;
    }

    private static function detectOperation(string $sql): ?string
    {
        if (preg_match('/^\s*(INSERT\s+OR\s+REPLACE|INSERT\s+OR\s+IGNORE|REPLACE|INSERT)\b/i', $sql)) {
            return 'INSERT';
        }
        if (preg_match('/^\s*UPDATE\b/i', $sql)) {
            return 'UPDATE';
        }
        if (preg_match('/^\s*DELETE\b/i', $sql)) {
            return 'DELETE';
        }

        return null;
    }

    private static function detectTable(string $sql, string $op): ?string
    {
        if ($op === 'INSERT' && preg_match('/\bINTO\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $sql, $m)) {
            return $m[1];
        }
        if ($op === 'UPDATE' && preg_match('/^\s*UPDATE\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $sql, $m)) {
            return $m[1];
        }
        if ($op === 'DELETE') {
            if (preg_match('/^\s*DELETE\s+FROM\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $sql, $m)) {
                return $m[1];
            }
            if (preg_match('/^\s*DELETE\s+[a-zA-Z_][\w]*\s+FROM\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $sql, $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
