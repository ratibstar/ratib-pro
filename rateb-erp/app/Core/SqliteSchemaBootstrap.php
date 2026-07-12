<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use PDO;
use PDOException;

/**
 * Phase B — apply full ERP SQLite schema (generated from MySQL) plus Phase A hybrid tables.
 * Does not alter Controllers, Services, or Models.
 */
final class SqliteSchemaBootstrap
{
    public const SCHEMA_VERSION_PHASE_A = '1';
    public const SCHEMA_VERSION_PHASE_B = '2';

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

        self::ensurePhaseCSyncTables($pdo);

        self::upsertMeta($pdo, 'hybrid_phase', 'A');
        self::upsertMeta($pdo, 'schema_version', self::SCHEMA_VERSION_PHASE_A);

        return ['rateb_hybrid_meta', 'rateb_sync_outbox'];
    }

    /**
     * Phase C — extend outbox + audit/batch/cursor tables (idempotent ALTERs).
     */
    public static function ensurePhaseCSyncTables(PDO $pdo): void
    {
        $alters = [
            'tenant_id' => 'INTEGER NOT NULL DEFAULT 0',
            'branch_id' => 'INTEGER NOT NULL DEFAULT 0',
            'payload_hash' => "TEXT NOT NULL DEFAULT ''",
            'signature' => "TEXT NOT NULL DEFAULT ''",
            'batch_uuid' => "TEXT NOT NULL DEFAULT ''",
            'synced_at' => "TEXT NOT NULL DEFAULT ''",
        ];
        foreach ($alters as $col => $def) {
            self::addColumnIfMissing($pdo, 'rateb_sync_outbox', $col, $def);
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rateb_sync_audit (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event TEXT NOT NULL,
                batch_uuid TEXT NOT NULL DEFAULT \'\',
                detail_json TEXT NOT NULL DEFAULT \'\',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rateb_sync_batches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                batch_uuid TEXT NOT NULL UNIQUE,
                direction TEXT NOT NULL,
                item_count INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT \'pending\',
                payload_hash TEXT NOT NULL DEFAULT \'\',
                signature TEXT NOT NULL DEFAULT \'\',
                error TEXT NOT NULL DEFAULT \'\',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                completed_at TEXT NOT NULL DEFAULT \'\'
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rateb_sync_cloud_inbox (
                idempotency_key TEXT PRIMARY KEY NOT NULL,
                uuid TEXT NOT NULL UNIQUE,
                entity_table TEXT NOT NULL,
                applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        self::upsertMeta($pdo, 'hybrid_sync_schema', 'C1');
    }

    private static function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        $want = strtolower($column);
        try {
            $stmt = $pdo->query('PRAGMA table_info(' . $safeTable . ')');
            if ($stmt === false) {
                return;
            }
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (strtolower((string) ($row['name'] ?? '')) === $want) {
                    return;
                }
            }
            $pdo->exec('ALTER TABLE ' . $safeTable . ' ADD COLUMN ' . $column . ' ' . $definition);
        } catch (\Throwable $e) {
            // ignore — best-effort schema evolve
        }
    }

    /**
     * Ensure full Branch ERP schema (Phase B) is applied once.
     *
     * @return array{applied:bool,tables:int,version:string}
     */
    public static function ensureErpSchema(PDO $pdo): array
    {
        self::ensureMinimal($pdo);

        $version = self::metaValue($pdo, 'schema_version');
        $hasCompanies = self::tableExists($pdo, 'rateb_companies');
        if ($hasCompanies && ($version === self::SCHEMA_VERSION_PHASE_B || $version === 'B')) {
            self::upsertMeta($pdo, 'hybrid_phase', 'B');
            return ['applied' => false, 'tables' => self::countUserTables($pdo), 'version' => self::SCHEMA_VERSION_PHASE_B];
        }

        $schemaFile = self::schemaFilePath();
        if (!is_readable($schemaFile)) {
            throw new PDOException(
                'RATEB Branch: SQLite schema missing at ' . $schemaFile
                . ' — run bin/hybrid-phase-b-generate-sqlite-schema.php'
            );
        }

        $sql = (string) file_get_contents($schemaFile);
        $tables = self::execSqliteScript($pdo, $sql);
        self::ensureMinimal($pdo); // re-assert hybrid tables / indexes
        self::upsertMeta($pdo, 'schema_version', self::SCHEMA_VERSION_PHASE_B);
        self::upsertMeta($pdo, 'hybrid_phase', 'B');
        self::upsertMeta($pdo, 'schema_source', basename($schemaFile));

        return ['applied' => true, 'tables' => $tables, 'version' => self::SCHEMA_VERSION_PHASE_B];
    }

    public static function schemaFilePath(): string
    {
        $root = defined('RATEB_ROOT') && (string) RATEB_ROOT !== ''
            ? (string) RATEB_ROOT
            : dirname(__DIR__, 2);

        return str_replace('\\', '/', $root) . '/schema/sqlite/branch-erp-schema.sql';
    }

    /** @return int number of CREATE TABLE statements executed (best-effort) */
    public static function execSqliteScript(PDO $pdo, string $sql): int
    {
        $pdo->exec('PRAGMA foreign_keys=OFF');
        $tables = 0;
        // Split on semicolons at end of lines (DDL statements)
        $chunks = preg_split('/;\s*\n/', $sql) ?: [];
        foreach ($chunks as $chunk) {
            $stmt = trim($chunk);
            if ($stmt === '' || str_starts_with($stmt, '--')) {
                // may still contain only comments
                $stmt = trim(preg_replace('/^--.*$/m', '', $stmt) ?? '');
            }
            if ($stmt === '') {
                continue;
            }
            if (preg_match('/^PRAGMA\s+/i', $stmt)) {
                try {
                    $pdo->exec($stmt);
                } catch (\Throwable $e) {
                    // ignore pragma errors
                }
                continue;
            }
            try {
                $pdo->exec($stmt);
                if (preg_match('/^CREATE\s+TABLE/i', $stmt)) {
                    $tables++;
                }
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                // Idempotent apply
                if (str_contains($msg, 'already exists')) {
                    continue;
                }
                throw new PDOException('SQLite schema apply failed: ' . $msg . ' :: ' . substr($stmt, 0, 120), (int) $e->getCode(), $e);
            }
        }
        $pdo->exec('PRAGMA foreign_keys=ON');

        return $tables;
    }

    public static function tableExists(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type='table' AND name = :n LIMIT 1"
        );
        $st->execute(['n' => $table]);

        return (bool) $st->fetchColumn();
    }

    public static function countUserTables(PDO $pdo): int
    {
        $n = $pdo->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        );

        return $n ? (int) $n->fetchColumn() : 0;
    }

    public static function metaValue(PDO $pdo, string $key): ?string
    {
        try {
            $st = $pdo->prepare('SELECT value FROM rateb_hybrid_meta WHERE key = :k LIMIT 1');
            $st->execute(['k' => $key]);
            $v = $st->fetchColumn();

            return $v === false ? null : (string) $v;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function upsertMeta(PDO $pdo, string $key, string $value): void
    {
        $now = gmdate('c');
        $stmt = $pdo->prepare(
            'INSERT INTO rateb_hybrid_meta (key, value, updated_at)
             VALUES (:k, :v, :t)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at'
        );
        $stmt->execute(['k' => $key, 'v' => $value, 't' => $now]);
    }
}
