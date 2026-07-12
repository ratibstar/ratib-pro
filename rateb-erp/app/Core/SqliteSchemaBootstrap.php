<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use PDO;

/**
 * Phase A — minimal SQLite schema for Hybrid Core Seam.
 * Full ERP table mirror is a later phase; this only creates hybrid infra tables.
 * Does not alter Controllers, Services, or Models.
 */
final class SqliteSchemaBootstrap
{
    /**
     * Ensure Phase A hybrid tables exist on the given SQLite PDO.
     *
     * @return list<string> created or verified table names
     */
    public static function ensureMinimal(PDO $pdo): array
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rateb_hybrid_meta (
                key TEXT PRIMARY KEY NOT NULL,
                value TEXT NOT NULL DEFAULT \'\',
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rateb_sync_outbox (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT NOT NULL UNIQUE,
                entity_table TEXT NOT NULL,
                entity_pk TEXT NOT NULL DEFAULT \'\',
                operation TEXT NOT NULL,
                payload_json TEXT NOT NULL DEFAULT \'\',
                version INTEGER NOT NULL DEFAULT 1,
                idempotency_key TEXT NOT NULL UNIQUE,
                occurred_at TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT \'pending\',
                retry_count INTEGER NOT NULL DEFAULT 0,
                last_error TEXT NOT NULL DEFAULT \'\',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_rateb_sync_outbox_status
             ON rateb_sync_outbox (status, id)'
        );

        $now = gmdate('c');
        $stmt = $pdo->prepare(
            'INSERT INTO rateb_hybrid_meta (key, value, updated_at)
             VALUES (:k, :v, :t)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at'
        );
        $stmt->execute([
            'k' => 'hybrid_phase',
            'v' => 'A',
            't' => $now,
        ]);
        $stmt->execute([
            'k' => 'schema_version',
            'v' => '1',
            't' => $now,
        ]);

        return ['rateb_hybrid_meta', 'rateb_sync_outbox'];
    }
}
