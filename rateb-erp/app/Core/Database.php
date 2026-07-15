<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use PDO;
use PDOException;

// Control-panel (and other non-Composer bootstraps) may require Database.php alone.
if (!class_exists(HybridRuntime::class, false)) {
    $hybridRuntimeFile = __DIR__ . DIRECTORY_SEPARATOR . 'HybridRuntime.php';
    if (is_file($hybridRuntimeFile)) {
        require_once $hybridRuntimeFile;
    }
}

final class Database
{
    private static ?PDO $pdo = null;
    private static string $resolvedDbName = '';
    private static string $activeDriver = 'mysql';

    /** @var array<string, bool> */
    private static array $columnCache = [];

    /** @var array{host:string,port:int,user:string,pass:string,db:string}|null */
    private static ?array $connectionOverride = null;

    /** Active PDO driver: mysql | sqlite (HybridRuntime Phase A). */
    public static function activeDriver(): string
    {
        if (self::$pdo instanceof PDO) {
            return self::$activeDriver;
        }

        return HybridRuntime::driver();
    }

    public static function isSqlite(): bool
    {
        return self::activeDriver() === HybridRuntime::DRIVER_SQLITE;
    }

    /**
     * Force the next connection() to a specific database (provisioning / control-panel per-agency).
     *
     * @param array{host?:string,port?:int,user?:string,pass?:string,db:string} $config
     */
    public static function useConnectionOverride(array $config): void
    {
        self::disconnect();
        self::$connectionOverride = [
            'host' => (string) ($config['host'] ?? (defined('RATEB_DB_HOST') ? RATEB_DB_HOST : '127.0.0.1')),
            'port' => (int) ($config['port'] ?? (defined('RATEB_DB_PORT') ? RATEB_DB_PORT : 3306)),
            'user' => (string) ($config['user'] ?? (defined('RATEB_DB_USER') ? RATEB_DB_USER : 'root')),
            'pass' => (string) ($config['pass'] ?? (defined('RATEB_DB_PASS') ? RATEB_DB_PASS : '')),
            'db' => (string) $config['db'],
        ];
    }

    public static function clearConnectionOverride(): void
    {
        self::$connectionOverride = null;
        self::disconnect();
    }

    public static function hasConnectionOverride(): bool
    {
        return self::$connectionOverride !== null;
    }

    public static function resolvedDatabaseName(): string
    {
        if (self::$resolvedDbName !== '') {
            return self::$resolvedDbName;
        }
        return defined('RATEB_DB_NAME') ? (string) RATEB_DB_NAME : '';
    }

    public static function clearColumnCache(): void
    {
        self::$columnCache = [];
    }

    public static function liveDatabaseName(): string
    {
        try {
            $pdo = self::connection();
            if (self::isSqlite()) {
                return self::resolvedDatabaseName() !== ''
                    ? self::resolvedDatabaseName()
                    : 'branch_sqlite';
            }
            $dbRow = $pdo->query('SELECT DATABASE()')->fetch(\PDO::FETCH_NUM);

            return is_array($dbRow) ? (string) ($dbRow[0] ?? '') : '';
        } catch (\Throwable $e) {
            return self::resolvedDatabaseName();
        }
    }

    /**
     * Live schema probe — request-scoped memoization so each table is
     * inspected at most once per PHP request (Phase AI).
     */
    public static function liveTableHasColumn(string $table, string $column): bool
    {
        static $reqColumns = [];
        $safeTable = str_replace('`', '', $table);
        if (!array_key_exists($safeTable, $reqColumns)) {
            try {
                $pdo = self::connection();
                $cols = [];
                if (self::isSqlite()) {
                    $safeIdent = preg_replace('/[^a-zA-Z0-9_]/', '', $safeTable) ?? '';
                    if ($safeIdent === '') {
                        $reqColumns[$safeTable] = [];
                    } else {
                        $stmt = $pdo->query('PRAGMA table_info(' . $safeIdent . ')');
                        foreach ($stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [] as $row) {
                            $name = (string) ($row['name'] ?? '');
                            if ($name !== '') {
                                $cols[strtolower($name)] = true;
                            }
                        }
                        $reqColumns[$safeTable] = $cols;
                    }
                } else {
                    $stmt = $pdo->query('SHOW COLUMNS FROM `' . $safeTable . '`');
                    foreach ($stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [] as $row) {
                        $name = (string) ($row['Field'] ?? '');
                        if ($name !== '') {
                            $cols[$name] = true;
                        }
                    }
                    if ($stmt instanceof \PDOStatement) {
                        $stmt->closeCursor();
                    }
                    $reqColumns[$safeTable] = $cols;
                }
                if (!array_key_exists($safeTable, $reqColumns)) {
                    $reqColumns[$safeTable] = $cols;
                }
            } catch (\Throwable $e) {
                $reqColumns[$safeTable] = [];
            }
        }

        $map = $reqColumns[$safeTable] ?? [];

        return isset($map[$column]) || isset($map[strtolower($column)]);
    }

    /** Cached per database — uses SHOW COLUMNS on MySQL or PRAGMA on SQLite. */
    public static function tableHasColumn(string $table, string $column): bool
    {
        $pdo = self::connection();
        $safeTable = str_replace('`', '', $table);
        if (self::isSqlite()) {
            $db = self::liveDatabaseName();
            $key = $db . '|' . $safeTable . '.' . $column;
            if (array_key_exists($key, self::$columnCache)) {
                return self::$columnCache[$key];
            }
            self::$columnCache[$key] = self::sqliteTableHasColumn($pdo, $safeTable, $column);

            return self::$columnCache[$key];
        }
        try {
            $dbRow = $pdo->query('SELECT DATABASE()')->fetch(\PDO::FETCH_NUM);
            $db = is_array($dbRow) ? (string) ($dbRow[0] ?? '') : '';
        } catch (\Throwable $e) {
            return false;
        }
        if ($db === '') {
            return false;
        }
        $key = $db . '|' . $safeTable . '.' . $column;
        if (array_key_exists($key, self::$columnCache)) {
            return self::$columnCache[$key];
        }
        try {
            $stmt = $pdo->query(
                'SHOW COLUMNS FROM `' . $safeTable . '` LIKE ' . $pdo->quote($column)
            );
            self::$columnCache[$key] = $stmt !== false && $stmt->fetch() !== false;
            if ($stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }
        } catch (\Throwable $e) {
            self::$columnCache[$key] = false;
        }

        return self::$columnCache[$key];
    }

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        // Explicit MySQL override (provisioning / control-panel) always wins.
        if (self::$connectionOverride !== null) {
            $cfg = self::$connectionOverride;
            self::$pdo = self::openWith(
                (string) $cfg['db'],
                (string) $cfg['host'],
                (int) $cfg['port'],
                (string) $cfg['user'],
                (string) $cfg['pass']
            );
            self::$resolvedDbName = (string) $cfg['db'];
            self::$activeDriver = HybridRuntime::DRIVER_MYSQL;

            return self::$pdo;
        }

        // Hybrid Runtime: branch appliance → local SQLite (Phase A seam).
        if (HybridRuntime::shouldUseSqlite()) {
            self::$pdo = self::openSqlite(HybridRuntime::sqlitePath());
            self::$resolvedDbName = 'branch_sqlite';
            self::$activeDriver = HybridRuntime::DRIVER_SQLITE;

            return self::$pdo;
        }

        if (HybridRuntime::isBranchMode() && !HybridRuntime::sqliteExtensionAvailable()) {
            throw new PDOException(
                'RATEB Hybrid Runtime: branch mode requires PHP pdo_sqlite extension.'
            );
        }

        $agencyBinding = self::resolveAgencyBindingForRequest();
        if ($agencyBinding !== null) {
            self::$pdo = self::openWith(
                (string) $agencyBinding['db'],
                (string) $agencyBinding['host'],
                (int) $agencyBinding['port'],
                (string) $agencyBinding['user'],
                (string) $agencyBinding['pass']
            );
            self::$resolvedDbName = (string) $agencyBinding['db'];
            self::$activeDriver = HybridRuntime::DRIVER_MYSQL;

            return self::$pdo;
        }

        $candidates = function_exists('rateb_erp_database_candidates')
            ? rateb_erp_database_candidates()
            : [defined('RATEB_DB_NAME') ? (string) RATEB_DB_NAME : 'admin_rateb-erp'];

        $dedicatedOnly = self::mustUseAgencyDatabaseOnly();
        $last = null;
        $tried = [];
        foreach ($candidates as $dbName) {
            $tried[] = $dbName;
            try {
                self::$pdo = self::open($dbName);
                self::$resolvedDbName = $dbName;
                self::$activeDriver = HybridRuntime::DRIVER_MYSQL;
                return self::$pdo;
            } catch (PDOException $e) {
                $last = $e;
                $msg = $e->getMessage();
                $isAccessDenied = strpos($msg, '1044') !== false || strpos($msg, '1049') !== false;
                if (!$isAccessDenied || $dedicatedOnly) {
                    throw $e;
                }
            }
        }

        if (!$dedicatedOnly) {
            $probed = self::probeErpDatabase($candidates);
            if ($probed !== null && !in_array($probed, $tried, true)) {
                try {
                    self::$pdo = self::open($probed);
                    self::$resolvedDbName = $probed;
                    self::$activeDriver = HybridRuntime::DRIVER_MYSQL;
                    return self::$pdo;
                } catch (PDOException $e) {
                    $last = $e;
                    $tried[] = $probed;
                }
            }
        }

        if ($last instanceof PDOException) {
            $expectedDb = defined('RATEB_ERP_DB_NAME') ? (string) RATEB_ERP_DB_NAME : '';
            $hint = 'Tried: ' . implode(', ', $tried) . '.';
            if ($dedicatedOnly && $expectedDb !== '') {
                $hint .= ' This agency host must use ERP database "' . $expectedDb
                    . '". Grant MySQL ALL on that database in cPanel → MySQL® Databases.';
            } else {
                $hint .= ' Grant ' . RATEB_DB_USER
                    . ' ALL PRIVILEGES on admin_rateb-erp in cPanel → MySQL® Databases.';
            }
            error_log('RATEB ERP DB connection failed: ' . $last->getMessage() . ' — ' . $hint);
            throw new PDOException($last->getMessage() . "\n\n" . $hint, (int) $last->getCode(), $last);
        }

        throw new PDOException('RATEB ERP database connection failed.');
    }

    /** @return array{host:string,port:int,user:string,pass:string,db:string,agency_id?:int}|null */
    private static function resolveAgencyBindingForRequest(): ?array
    {
        if (PHP_SAPI === 'cli') {
            return null;
        }
        $lookupFile = dirname(__DIR__, 3) . '/config/env/agency_lookup.php';
        if (is_file($lookupFile)) {
            require_once $lookupFile;
            if (function_exists('rateb_agency_erp_binding_for_request_host')) {
                $binding = rateb_agency_erp_binding_for_request_host();
                if (is_array($binding) && trim((string) ($binding['db'] ?? '')) !== '') {
                    return $binding;
                }
            }
        }

        return self::agencyBindingFromConstants();
    }

    /** @return array{host:string,port:int,user:string,pass:string,db:string}|null */
    private static function agencyBindingFromConstants(): ?array
    {
        if (!(defined('RATEB_ERP_AGENCY_RESOLVED') && RATEB_ERP_AGENCY_RESOLVED)) {
            return null;
        }
        if (!defined('RATEB_ERP_DB_NAME') || trim((string) RATEB_ERP_DB_NAME) === '') {
            return null;
        }
        $host = defined('RATEB_ERP_DB_HOST') && (string) RATEB_ERP_DB_HOST !== ''
            ? (string) RATEB_ERP_DB_HOST
            : (defined('RATEB_DB_HOST') ? (string) RATEB_DB_HOST : '127.0.0.1');
        $port = defined('RATEB_DB_PORT') ? (int) RATEB_DB_PORT : 3306;
        $user = defined('RATEB_ERP_DB_USER') && (string) RATEB_ERP_DB_USER !== ''
            ? (string) RATEB_ERP_DB_USER
            : (defined('RATEB_DB_USER') ? (string) RATEB_DB_USER : 'root');
        $pass = defined('RATEB_ERP_DB_PASS')
            ? (string) RATEB_ERP_DB_PASS
            : (defined('RATEB_DB_PASS') ? (string) RATEB_DB_PASS : '');

        return [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'pass' => $pass,
            'db' => (string) RATEB_ERP_DB_NAME,
        ];
    }

    private static function mustUseAgencyDatabaseOnly(): bool
    {
        if (defined('RATEB_ERP_AGENCY_RESOLVED') && RATEB_ERP_AGENCY_RESOLVED) {
            return true;
        }
        if (function_exists('rateb_erp_is_dedicated_deployment') && rateb_erp_is_dedicated_deployment()) {
            return true;
        }
        if (PHP_SAPI !== 'cli' && function_exists('rateb_erp_is_main_platform_host')) {
            $host = function_exists('rateb_normalize_http_host')
                ? rateb_normalize_http_host((string) ($_SERVER['HTTP_HOST'] ?? ''))
                : strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
            if ($host !== '' && !rateb_erp_is_main_platform_host($host)) {
                return true;
            }
        }

        return false;
    }

    private static function probeErpDatabase(array $preferred): ?string
    {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', RATEB_DB_HOST, RATEB_DB_PORT);
            $pdo = new PDO($dsn, RATEB_DB_USER, RATEB_DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $stmt = $pdo->query("SHOW DATABASES LIKE '%rateb%erp%'");
            if ($stmt === false) {
                return null;
            }
            $found = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($preferred as $name) {
                if (in_array($name, $found, true)) {
                    return $name;
                }
            }
            return isset($found[0]) ? (string) $found[0] : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    private static function open(string $dbName): PDO
    {
        return self::openWith(
            $dbName,
            RATEB_DB_HOST,
            RATEB_DB_PORT,
            RATEB_DB_USER,
            RATEB_DB_PASS
        );
    }

    private static function openWith(string $dbName, string $host, int $port, string $user, string $pass): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $dbName
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        }
        if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
        }
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        return new PDO($dsn, $user, $pass, $options);
    }

    /**
     * Open local branch SQLite with WAL + durability pragmas (Phase A).
     * Controllers/Services/Models remain unaware of the driver.
     */
    private static function openSqlite(string $path): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new PDOException('RATEB Hybrid Runtime: pdo_sqlite extension is not loaded.');
        }

        HybridRuntime::ensureBranchStorage();
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new PDOException('RATEB Hybrid Runtime: cannot create SQLite directory: ' . $dir);
        }

        $dsn = 'sqlite:' . $path;
        $pdo = new SqliteCompatPdo($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Enterprise local durability (rule 17).
        // busy_timeout=30000: Branch appliance concurrency (POS / inventory / transfers).
        // Writers serialize under WAL; wait instead of failing under short bursts.
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA synchronous=NORMAL');
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('PRAGMA busy_timeout=30000');
        $pdo->exec('PRAGMA temp_store=MEMORY');

        HybridSyncConfig::suppressCapture(true);
        try {
            // Phase B: ensure full ERP schema on first branch open (idempotent).
            // Phase A hybrid tables are included. Cloud MySQL path is untouched.
            if (!defined('RATEB_SQLITE_SKIP_SCHEMA_BOOTSTRAP') || !RATEB_SQLITE_SKIP_SCHEMA_BOOTSTRAP) {
                SqliteSchemaBootstrap::ensureErpSchema($pdo);
            } else {
                // Tests that skip ERP DDL still need Phase A/C sync tables.
                SqliteSchemaBootstrap::ensureMinimal($pdo);
            }
        } finally {
            HybridSyncConfig::suppressCapture(false);
        }

        // Phase C: resume any rows left mid-flight after unexpected shutdown.
        if (HybridSyncConfig::enabled()) {
            try {
                (new HybridSyncEngine())->resumeInterrupted($pdo);
            } catch (\Throwable $e) {
                // never block open
            }
        }

        return $pdo;
    }

    private static function sqliteTableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        if ($safeTable === '') {
            return false;
        }
        try {
            $stmt = $pdo->query('PRAGMA table_info(' . $safeTable . ')');
            if ($stmt === false) {
                return false;
            }
            $want = strtolower($column);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (strtolower((string) ($row['name'] ?? '')) === $want) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    public static function disconnect(): void
    {
        self::$pdo = null;
        self::$resolvedDbName = '';
        self::$columnCache = [];
        self::$activeDriver = class_exists(HybridRuntime::class, false)
            ? HybridRuntime::DRIVER_MYSQL
            : 'mysql';
        // Guard: cloud fast-deploys may not include every hybrid Core file yet.
        if (class_exists(HybridSyncOutboxCapture::class, true)) {
            HybridSyncOutboxCapture::resetConnection();
        }
    }

    /** @param array<string, mixed> $params @return array<int, array<string, mixed>> */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::connection()->prepare($sql);
        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            throw \Rateb\App\Services\DatabaseErrorService::toRuntimeException($e);
        }
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** @param array<string, mixed> $params @return array<string, mixed>|null */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::connection()->prepare($sql);
        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            throw \Rateb\App\Services\DatabaseErrorService::toRuntimeException($e);
        }
        $row = $stmt->fetch();

        return $row ?: null;
    }
}

