<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use PDO;

final class MigrationService
{
    /** @return array<int, string> */
    public function runAll(): array
    {
        $log = [];
        [$pdo, $dbName] = $this->migrationConnection();
        return $this->runAllOnPdo($pdo, $dbName, $log);
    }

    /**
     * Run ERP migrations on an explicit database (agency provisioning from Control Panel).
     *
     * @param array{host?:string,port?:int,user?:string,pass?:string,db:string} $config
     * @return array<int, string>
     */
    public function runAllForDatabase(array $config): array
    {
        Database::clearConnectionOverride();
        Database::useConnectionOverride($config);
        $log = [];
        try {
            [$pdo, $dbName] = $this->migrationConnection();

            return $this->runAllOnPdo($pdo, $dbName, $log, true);
        } finally {
            Database::clearConnectionOverride();
        }
    }

    /** @return array<int, string> */
    private function runAllOnPdo(PDO $pdo, string $dbName, array $log, bool $explicitAgencyTarget = false): array
    {
        $log[] = 'Connected to database: ' . $dbName;
        $this->assertErpTargetDatabase($dbName, $explicitAgencyTarget);
        $this->ensureMigrationsTable($pdo);
        $this->seedLegacyAppliedMigrations($pdo, $log);

        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $files = glob($root . '/migrations/*.sql') ?: [];
        sort($files);

        $ran = 0;
        foreach ($files as $file) {
            $name = basename($file);
            if (!$this->isRunnableMigration($name)) {
                $log[] = 'Skipped ' . $name . ' (manual/full install only)';
                continue;
            }
            if ($this->isApplied($pdo, $name)) {
                $log[] = 'Already applied: ' . $name;
                continue;
            }
            if (!is_file($file)) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                continue;
            }
            $sql = self::normalizeMigrationFileContents($sql);
            $log[] = 'Running ' . $name . '…';
            $this->execSqlFile($pdo, $sql);
            $this->markApplied($pdo, $name);
            $log[] = 'Done: ' . $name;
            $ran++;
        }

        if ($ran === 0) {
            $log[] = 'No new migrations to run.';
        }

        $this->repairBranchOpsSchemaIfNeeded($pdo, $log);
        $this->repairMarketingPlansCanonicalIfNeeded($pdo, $log);

        return $log;
    }

    /** @return list<string> */
    public function repairMarketingPlansCanonicalIfNeeded(?PDO $pdo = null, ?array &$log = null): array
    {
        $localLog = [];
        if ($log === null) {
            $log = &$localLog;
        }
        try {
            if ($pdo === null) {
                [$pdo, ] = $this->migrationConnection();
            }
        } catch (\Throwable $e) {
            $log[] = 'Plans canonical repair skipped: ' . $e->getMessage();
            return $localLog;
        }

        try {
            $stmt = $pdo->query(
                "SELECT slug, max_users, max_branches, price_monthly FROM rateb_plans
                 WHERE slug IN ('starter','professional','enterprise')"
            );
            $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            if ($stmt) {
                $this->drainStatement($stmt);
            }
            if ($rows === []) {
                return $localLog;
            }
            foreach ($rows as $row) {
                $slug = (string) ($row['slug'] ?? '');
                $log[] = 'Plans verify: ' . $slug
                    . ' users=' . (int) ($row['max_users'] ?? 0)
                    . ' branches=' . (int) ($row['max_branches'] ?? 0)
                    . ' monthly=' . (string) ($row['price_monthly'] ?? '');
            }
        } catch (\Throwable $e) {
            $log[] = 'Plans verify skipped: ' . $e->getMessage();
            return $localLog;
        }

        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $catchup = $root . '/migrations/148_marketing_plans_canonical.sql';
        if (!is_file($catchup)) {
            return $localLog;
        }
        $sql = file_get_contents($catchup);
        if ($sql === false || trim($sql) === '') {
            return $localLog;
        }
        $log[] = 'Applying marketing plans canonical catchup (148)…';
        $this->execSqlFile($pdo, $sql);
        if (!$this->isApplied($pdo, '148_marketing_plans_canonical.sql')) {
            $this->markApplied($pdo, '148_marketing_plans_canonical.sql');
        }
        $log[] = 'Marketing plans catchup: limits + modules synced (enterprise → 100 users).';

        return $localLog;
    }

    /** @return list<string> */
    public function repairBranchOpsSchemaIfNeeded(?PDO $pdo = null, ?array &$log = null): array
    {
        $localLog = [];
        if ($log === null) {
            $log = &$localLog;
        }

        if ($pdo instanceof PDO) {
            $this->repairBranchOpsOnPdo($pdo, $log);
            Database::clearColumnCache();
            return $localLog;
        }

        $checked = [];
        try {
            $live = Database::connection();
            $liveDb = (string) ($live->query('SELECT DATABASE()')->fetchColumn() ?: '');
            if ($liveDb !== '') {
                $checked[] = $liveDb;
                $log[] = 'Branch schema check (live ERP connection): ' . $liveDb;
                $this->repairBranchOpsOnPdo($live, $log);
            }
        } catch (\Throwable $e) {
            $log[] = 'Branch schema live check skipped: ' . $e->getMessage();
        }

        $candidates = function_exists('rateb_erp_database_candidates')
            ? rateb_erp_database_candidates()
            : [defined('RATEB_DB_NAME') ? (string) \RATEB_DB_NAME : 'admin_rateb-erp'];

        foreach ($candidates as $dbName) {
            if (in_array($dbName, $checked, true)) {
                continue;
            }
            try {
                $pdo = $this->openMigrationPdo($dbName);
                $checked[] = $dbName;
                $log[] = 'Branch schema check: ' . $dbName;
                $this->repairBranchOpsOnPdo($pdo, $log);
            } catch (\Throwable $e) {
                $log[] = 'Branch schema repair skipped for ' . $dbName . ': ' . $e->getMessage();
            }
        }

        Database::clearColumnCache();

        return $localLog;
    }

    /** @return array<string, string> */
    private function branchOpsColumnAlters(): array
    {
        return [
            'rateb_purchase_requests' => 'ALTER TABLE rateb_purchase_requests ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id',
            'rateb_purchase_orders' => 'ALTER TABLE rateb_purchase_orders ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id',
            'rateb_suppliers' => 'ALTER TABLE rateb_suppliers ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id',
            'rateb_inventory' => 'ALTER TABLE rateb_inventory ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id',
            'rateb_rfq' => 'ALTER TABLE rateb_rfq ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id',
            'rateb_contracts' => 'ALTER TABLE rateb_contracts ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id',
            'rateb_assets' => 'ALTER TABLE rateb_assets ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id',
            'rateb_tenders' => 'ALTER TABLE rateb_tenders ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id',
            'rateb_stock_movements' => 'ALTER TABLE rateb_stock_movements ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id',
        ];
    }

    /** @return array<string, string> */
    private function branchOpsIndexAlters(): array
    {
        return [
            'rateb_purchase_requests' => 'ALTER TABLE rateb_purchase_requests ADD INDEX idx_pr_branch (branch_id)',
            'rateb_purchase_orders' => 'ALTER TABLE rateb_purchase_orders ADD INDEX idx_po_branch (branch_id)',
            'rateb_suppliers' => 'ALTER TABLE rateb_suppliers ADD INDEX idx_sup_branch (branch_id)',
            'rateb_inventory' => 'ALTER TABLE rateb_inventory ADD INDEX idx_inv_branch (branch_id)',
            'rateb_rfq' => 'ALTER TABLE rateb_rfq ADD INDEX idx_rfq_branch (branch_id)',
            'rateb_contracts' => 'ALTER TABLE rateb_contracts ADD INDEX idx_contract_branch (branch_id)',
            'rateb_assets' => 'ALTER TABLE rateb_assets ADD INDEX idx_asset_branch (branch_id)',
            'rateb_tenders' => 'ALTER TABLE rateb_tenders ADD INDEX idx_tender_branch (branch_id)',
            'rateb_stock_movements' => 'ALTER TABLE rateb_stock_movements ADD INDEX idx_sm_branch (branch_id)',
        ];
    }

    /** @param list<string> $log */
    private function repairBranchOpsOnPdo(PDO $pdo, array &$log): void
    {
        $dbRow = $pdo->query('SELECT DATABASE()')->fetch(\PDO::FETCH_NUM);
        $dbName = is_array($dbRow) ? (string) ($dbRow[0] ?? '') : '';
        if ($dbName === '') {
            $log[] = 'Branch schema repair skipped: no database selected';
            return;
        }

        $missing = [];
        foreach (array_keys($this->branchOpsColumnAlters()) as $table) {
            if (!$this->pdoColumnExists($pdo, $table, 'branch_id')) {
                $missing[] = $table . '.branch_id';
            }
        }
        if ($missing === []) {
            return;
        }

        $log[] = 'Branch schema repair on ' . $dbName . ': missing ' . implode(', ', $missing);

        foreach ($this->branchOpsColumnAlters() as $table => $alterSql) {
            if ($this->pdoColumnExists($pdo, $table, 'branch_id')) {
                continue;
            }
            if ($this->execBenignAlter($pdo, $alterSql)) {
                $log[] = 'Added ' . $table . '.branch_id on ' . $dbName;
            }
        }

        foreach ($this->branchOpsIndexAlters() as $table => $indexSql) {
            if (!$this->pdoColumnExists($pdo, $table, 'branch_id')) {
                continue;
            }
            $this->execBenignAlter($pdo, $indexSql);
        }

        $stillMissing = [];
        foreach (['rateb_purchase_orders', 'rateb_purchase_requests'] as $table) {
            if (!$this->pdoColumnExists($pdo, $table, 'branch_id')) {
                $stillMissing[] = $table . '.branch_id';
            }
        }
        if ($stillMissing === []) {
            $this->ensureMigrationsTable($pdo);
            if (!$this->isApplied($pdo, '146_branch_ops_branch_id_catchup.sql')) {
                $this->markApplied($pdo, '146_branch_ops_branch_id_catchup.sql');
            }
            return;
        }

        $log[] = 'Branch schema repair: still missing on ' . $dbName . ' — ' . implode(', ', $stillMissing);
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $catchup = $root . '/migrations/146_branch_ops_branch_id_catchup.sql';
        if (is_file($catchup)) {
            $sql = file_get_contents($catchup);
            if ($sql !== false && trim($sql) !== '') {
                $log[] = 'Applying SQL catchup 146 on ' . $dbName . '…';
                $this->execSqlFile($pdo, $sql);
                if (!$this->isApplied($pdo, '146_branch_ops_branch_id_catchup.sql')) {
                    $this->markApplied($pdo, '146_branch_ops_branch_id_catchup.sql');
                }
            }
        }
    }

    private function execBenignAlter(PDO $pdo, string $sql): bool
    {
        try {
            $pdo->exec($sql);
            return true;
        } catch (\PDOException $e) {
            if ($this->isBenignMigrationError($e->getMessage())) {
                return false;
            }
            throw $e;
        }
    }

    private function pdoColumnExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $safeTable = str_replace('`', '', $table);
            $stmt = $pdo->query(
                'SHOW COLUMNS FROM `' . $safeTable . '` LIKE ' . $pdo->quote($column)
            );
            $exists = $stmt !== false && $stmt->fetch() !== false;
            if ($stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }

            return $exists;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function ensureMigrationsTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rateb_migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_migration_filename (filename)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function isApplied(PDO $pdo, string $filename): bool
    {
        $stmt = $pdo->prepare('SELECT id FROM rateb_migrations WHERE filename = :f LIMIT 1');
        $stmt->execute(['f' => $filename]);
        $applied = (bool) $stmt->fetch();
        $this->drainStatement($stmt);
        return $applied;
    }

    private function markApplied(PDO $pdo, string $filename): void
    {
        $stmt = $pdo->prepare('INSERT IGNORE INTO rateb_migrations (filename) VALUES (:f)');
        $stmt->execute(['f' => $filename]);
    }

    /** Existing DBs installed before rateb_migrations tracking — skip re-running 001–012. */
    private function seedLegacyAppliedMigrations(PDO $pdo, array &$log): void
    {
        $check = $pdo->query("SHOW TABLES LIKE 'rateb_companies'");
        $hasSchema = $check !== false && $check->fetch() !== false;
        if ($check instanceof \PDOStatement) {
            $check->closeCursor();
        }
        if (!$hasSchema) {
            return;
        }

        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $files = glob($root . '/migrations/*.sql') ?: [];
        sort($files);
        $marked = 0;
        foreach ($files as $file) {
            $name = basename($file);
            if (!$this->isRunnableMigration($name)) {
                continue;
            }
            if (!preg_match('/^(\d{3})_/', $name, $m)) {
                continue;
            }
            if ((int) $m[1] >= 13) {
                continue;
            }
            if ($this->isApplied($pdo, $name)) {
                continue;
            }
            $this->markApplied($pdo, $name);
            $marked++;
        }
        if ($marked > 0) {
            $log[] = 'Legacy schema: marked migrations 001–012 as already applied (' . $marked . ' new rows).';
        }
    }

    private function isRunnableMigration(string $basename): bool
    {
        if (!preg_match('/^\d{3}_[A-Za-z0-9_\-]+\.sql$/', $basename)) {
            return false;
        }
        if (strncmp($basename, '000_', 4) === 0) {
            return false;
        }
        if (str_contains($basename, '_diagnose')) {
            return false;
        }
        return true;
    }

    private function execSqlFile(PDO $pdo, string $sql): void
    {
        $sql = $this->normalizeMigrationFileContents($sql);
        $sql = preg_replace('/^\s*USE\s+`[^`]+`\s*;\s*/mi', '', $sql) ?? $sql;
        $this->bootstrapMigrationCharset($pdo);

        foreach ($this->splitStatements($sql) as $statement) {
            if ($statement === '') {
                continue;
            }
            try {
                $this->execStatement($pdo, $statement);
            } catch (\PDOException $e) {
                if ($this->isBenignMigrationError($e->getMessage())) {
                    continue;
                }
                if (strpos($e->getMessage(), '1366') !== false) {
                    $this->convertTablesFromCharsetError($pdo, $e->getMessage());
                    try {
                        $this->execStatement($pdo, $statement);
                        continue;
                    } catch (\PDOException $retryAfterConvert) {
                        $e = $retryAfterConvert;
                    }
                }
                $fallback = $this->permissionInsertWithoutArabic($statement);
                if ($fallback === null || $fallback === $statement) {
                    $fallback = $this->stripNonAsciiStringLiterals($statement);
                }
                if ($fallback !== null && $fallback !== $statement) {
                    try {
                        $this->execStatement($pdo, $fallback);
                        continue;
                    } catch (\PDOException $retry) {
                        if ($this->isBenignMigrationError($retry->getMessage())) {
                            continue;
                        }
                        throw $retry;
                    }
                }
                throw $e;
            }
        }
    }

    private function execStatement(PDO $pdo, string $statement): void
    {
        $stmt = $pdo->query($statement);
        if ($stmt === false) {
            return;
        }
        $this->drainStatement($stmt);
    }

    private function drainStatement(\PDOStatement $stmt): void
    {
        do {
            $stmt->fetchAll();
        } while ($stmt->nextRowset());
        $stmt->closeCursor();
    }

    private function normalizeMigrationFileContents(string $sql): string
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        if ($sql === '') {
            return '';
        }

        return str_replace("\r\n", "\n", str_replace("\r", "\n", $sql));
    }

    private function bootstrapMigrationCharset(PDO $pdo): void
    {
        foreach ([
            'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
            'SET CHARACTER SET utf8mb4',
            'SET collation_connection = utf8mb4_unicode_ci',
            'SET collation_database = utf8mb4_unicode_ci',
        ] as $statement) {
            try {
                $pdo->exec($statement);
            } catch (\PDOException $e) {
                // non-fatal on older MySQL builds
            }
        }
    }

    /** @return array<int, string> */
    private function splitStatements(string $sql): array
    {
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;
        // Strip whole-line -- comments before parsing (avoids RTL/BOM breaking str_starts_with('--')).
        $sql = preg_replace('/^\s*--[^\r\n]*\r?\n/m', '', $sql) ?? $sql;
        $statements = [];
        $buffer = '';
        foreach (preg_split('/\R/', $sql) ?: [] as $line) {
            $line = str_replace("\r", '', $line);
            $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
            $line = preg_replace('/^[\x{200E}\x{200F}\x{FEFF}]+/u', '', $line) ?? $line;
            $trimmed = trim($line);
            if ($trimmed === '' || preg_match('/^\s*--/', $trimmed) === 1) {
                continue;
            }
            $buffer .= $line . "\n";
            if (preg_match('/;\s*$/', $trimmed)) {
                $stmt = trim($buffer);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $buffer = '';
            }
        }
        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }
        return $this->expandPrepareChains($statements);
    }

    /** @param array<int, string> $statements */
    /** @return array<int, string> */
    private function expandPrepareChains(array $statements): array
    {
        $out = [];
        foreach ($statements as $statement) {
            $trimmed = trim($statement);
            if (preg_match(
                '/^PREPARE\s+(\w+)\s+FROM\s+@sql\s*;\s*EXECUTE\s+(\w+)\s*;\s*DEALLOCATE\s+PREPARE\s+(\w+)\s*;?\s*$/is',
                $trimmed,
                $m
            ) === 1) {
                $out[] = 'PREPARE ' . $m[1] . ' FROM @sql';
                $out[] = 'EXECUTE ' . $m[2];
                $out[] = 'DEALLOCATE PREPARE ' . $m[3];
                continue;
            }
            $out[] = $statement;
        }
        return $out;
    }

    private function isBenignMigrationError(string $message): bool
    {
        foreach (['1050', '1060', '1061', '1072', '1091', '1826', '1825', '1062'] as $code) {
            if (strpos($message, $code) !== false) {
                return true;
            }
        }
        return false;
    }

    /** Strip Arabic literals from rateb_permissions bulk INSERT (cPanel/latin1 upload safe). */
    private function permissionInsertWithoutArabic(string $statement): ?string
    {
        if (!preg_match('/INSERT\s+INTO\s+`?rateb_permissions`?\s/i', $statement)) {
            return null;
        }
        if (!preg_match('/name_ar/i', $statement)) {
            return null;
        }

        $converted = preg_replace(
            '/INSERT\s+INTO\s+`?rateb_permissions`?\s*\(\s*name\s*,\s*name_ar\s*,\s*slug\s*,\s*module\s*,\s*description\s*,\s*description_ar\s*\)/i',
            'INSERT INTO rateb_permissions (name, slug, module, description)',
            $statement,
            1,
            $count
        );
        if ($count < 1 || !is_string($converted)) {
            return null;
        }

        $converted = preg_replace_callback(
            "/\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'(?:[^'\\\\]|\\\\.)*'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'(?:[^'\\\\]|\\\\.)*'\s*\)/",
            static function (array $m): string {
                return "('" . $m[1] . "', '" . $m[2] . "', '" . $m[3] . "', '" . $m[4] . "')";
            },
            $converted
        );
        if (!is_string($converted)) {
            return null;
        }

        $converted = preg_replace(
            '/ON\s+DUPLICATE\s+KEY\s+UPDATE\s+.*$/is',
            'ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description)',
            $converted
        );

        return is_string($converted) ? $converted : null;
    }

    private function convertTablesFromCharsetError(PDO $pdo, string $message): void
    {
        if (preg_match("/for column '[^']+'\\.'([^']+)'\\.'([^']+)'/i", $message, $m) === 1) {
            $this->convertTableToUtf8mb4($pdo, $m[1]);
            return;
        }
        if (preg_match("/for column '([^']+)'/i", $message, $m) === 1) {
            $this->convertTableToUtf8mb4($pdo, $m[1]);
        }
    }

    private function convertTableToUtf8mb4(PDO $pdo, string $table): void
    {
        $table = str_replace('`', '', trim($table));
        if ($table === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return;
        }
        try {
            $pdo->exec(
                'ALTER TABLE `' . $table . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
        } catch (\PDOException $e) {
            error_log('MigrationService utf8mb4 convert for ' . $table . ': ' . $e->getMessage());
        }
    }

    /** Last-resort: replace non-ASCII SQL string literals on charset 1366 errors. */
    private function stripNonAsciiStringLiterals(string $statement): ?string
    {
        if (stripos($statement, 'INSERT') === false && stripos($statement, 'UPDATE') === false) {
            return null;
        }
        $converted = preg_replace_callback(
            "/'((?:[^'\\\\]|\\\\.)*)'/",
            static function (array $m): string {
                if (!preg_match('/[^\x00-\x7F]/', $m[1])) {
                    return "'" . $m[1] . "'";
                }
                $ascii = trim((string) preg_replace('/[^\x00-\x7F]/', ' ', $m[1]));
                $ascii = preg_replace('/\s+/', ' ', $ascii) ?? '';
                if ($ascii === '') {
                    $ascii = 'label';
                }

                return "'" . str_replace("'", "''", $ascii) . "'";
            },
            $statement
        );

        return is_string($converted) && $converted !== $statement ? $converted : null;
    }

    private function assertErpTargetDatabase(string $dbName, bool $explicitAgencyTarget = false): void
    {
        $lower = strtolower(trim($dbName));
        if ($lower === 'admin_rateb' || $lower === 'admin_control_panel_db') {
            throw new \RuntimeException(
                'Refusing ERP migrations on ' . $dbName
                . ' — set RATEB_ERP_DB_NAME=admin_rateb-erp in server .env and grant MySQL access.'
            );
        }
        if (!$explicitAgencyTarget && strpos($lower, 'erp') === false) {
            throw new \RuntimeException(
                'Refusing ERP migrations on ' . $dbName
                . ' — expected database admin_rateb-erp (RATEB_ERP_DB_NAME).'
            );
        }
    }

    /** @return array{0:PDO,1:string} */
    private function migrationConnection(): array
    {
        Database::disconnect();
        if (Database::hasConnectionOverride()) {
            $pdo = Database::connection();
            $dbName = Database::resolvedDatabaseName();
            if ($dbName === '') {
                throw new \PDOException('Provisioning migration database name is missing.');
            }

            return [$pdo, $dbName];
        }

        $candidates = function_exists('rateb_erp_database_candidates')
            ? rateb_erp_database_candidates()
            : [defined('RATEB_DB_NAME') ? (string) \RATEB_DB_NAME : 'admin_rateb-erp'];
        $last = null;
        foreach ($candidates as $dbName) {
            try {
                return [$this->openMigrationPdo($dbName), $dbName];
            } catch (\PDOException $e) {
                $last = $e;
            }
        }
        if ($last instanceof \PDOException) {
            throw $last;
        }
        throw new \PDOException('RATEB ERP migration database connection failed.');
    }

    private function openMigrationPdo(string $dbName): PDO
    {
        $host = defined('RATEB_DB_HOST') ? (string) \RATEB_DB_HOST : '127.0.0.1';
        $port = defined('RATEB_DB_PORT') ? (int) \RATEB_DB_PORT : 3306;
        $user = defined('RATEB_DB_USER') ? (string) \RATEB_DB_USER : 'root';
        $pass = defined('RATEB_DB_PASS') ? (string) \RATEB_DB_PASS : '';
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $dbName
        );
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $options[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        }
        if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            $options[\PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
        }
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
        return new \PDO($dsn, $user, $pass, $options);
    }

    public function isSchemaReady(): bool
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->query("SHOW TABLES LIKE 'rateb_companies'");
            $row = $stmt !== false ? $stmt->fetch() : false;
            if ($stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }
            return $row !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return list<string> */
    public function pendingFilenames(): array
    {
        try {
            [$pdo, $dbName] = $this->migrationConnection();
            $seedLog = [];
            $this->ensureMigrationsTable($pdo);
            $this->seedLegacyAppliedMigrations($pdo, $seedLog);
            $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
            $files = glob($root . '/migrations/*.sql') ?: [];
            sort($files);
            $pending = [];
            foreach ($files as $file) {
                $name = basename($file);
                if (!$this->isRunnableMigration($name)) {
                    continue;
                }
                if (!$this->isApplied($pdo, $name)) {
                    $pending[] = $name;
                }
            }

            return $pending;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function hasPending(): bool
    {
        return $this->pendingFilenames() !== [];
    }

    /** @return array<int, string> */
    public function rollbackLast(): array
    {
        $log = [];
        [$pdo, $dbName] = $this->migrationConnection();
        $log[] = 'Connected to database: ' . $dbName;
        $this->ensureMigrationsTable($pdo);

        $stmt = $pdo->query('SELECT filename FROM rateb_migrations ORDER BY id DESC LIMIT 1');
        $row = $stmt !== false ? $stmt->fetch() : false;
        if (!$row || empty($row['filename'])) {
            $log[] = 'No migration to rollback.';
            return $log;
        }
        $filename = (string) $row['filename'];
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $downFile = $root . '/migrations/' . preg_replace('/\.sql$/', '.down.sql', $filename);
        if (!is_file($downFile)) {
            $log[] = 'Rollback file missing: ' . basename($downFile);
            return $log;
        }
        $sql = file_get_contents($downFile);
        if ($sql === false || trim($sql) === '') {
            $log[] = 'Rollback file empty.';
            return $log;
        }
        $log[] = 'Rolling back ' . $filename . '…';
        $this->execSqlFile($pdo, $sql);
        $pdo->prepare('DELETE FROM rateb_migrations WHERE filename = :f')->execute(['f' => $filename]);
        $log[] = 'Rolled back: ' . $filename;
        return $log;
    }
}
