<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/TenantDatabaseManager.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/TenantDatabaseManager.php`.
 */
/**
 * TenantDatabaseManager
 *
 * Opens a PDO connection to a tenant database using credentials stored in the control DB
 * (table `tenants`). Same driver stack as api/core/Database.php (mysql PDO).
 *
 * Per-request cache only (static properties reset each PHP process request).
 * No tenant switching mid-request. No fallback database.
 *
 * Bootstrap: works if either (a) includes/config.php already defined DB_* and
 * CONTROL_PANEL_DB_NAME, or (b) environment variables DB_HOST, DB_USER, DB_PASS,
 * DB_PORT (optional), CONTROL_PANEL_DB_NAME (optional, default outratib_control_panel_db).
 *
 * Test (CLI), after exporting DB_* and a valid tenant id:
 *   php -r "require 'api/core/TenantDatabaseManager.php'; var_dump(TenantDatabaseManager::pdoForTenantId(1)->query('SELECT 1')->fetchColumn());"
 */
final class TenantDatabaseManager
{
    /** @var PDO|null */
    private static $controlPdo = null;

    /** @var array<int, PDO> */
    private static $pdoByTenantId = array();

    /** @var int|null */
    private static $lockedTenantId = null;

    /**
     * @return PDO Connection to the tenant's MySQL database
     */
    public static function pdoForTenantId($tenantId)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            throw new InvalidArgumentException('tenant_id must be a positive integer.');
        }

        if (self::$lockedTenantId === null) {
            self::$lockedTenantId = $tenantId;
        } elseif ((int) self::$lockedTenantId !== $tenantId) {
            throw new RuntimeException('TenantDatabaseManager: tenant switching is not allowed within the same request.');
        }

        if (isset(self::$pdoByTenantId[$tenantId])) {
            return self::$pdoByTenantId[$tenantId];
        }

        $row = self::fetchTenantRow($tenantId);
        if ($row === null || empty($row['id'])) {
            throw new RuntimeException('TenantDatabaseManager: tenant not found.');
        }

        $status = strtolower(trim((string) (isset($row['status']) ? $row['status'] : '')));
        if ($status !== 'active') {
            throw new RuntimeException('TenantDatabaseManager: tenant is not active.');
        }

        $dbName = trim((string) (isset($row['database_name']) ? $row['database_name'] : ''));
        $dbUser = trim((string) (isset($row['db_user']) ? $row['db_user'] : ''));
        if ($dbName === '' || $dbUser === '') {
            throw new RuntimeException('TenantDatabaseManager: tenant DB credentials are incomplete.');
        }

        $dbHost = trim((string) (isset($row['db_host']) ? $row['db_host'] : ''));
        if ($dbHost === '') {
            $dbHost = self::cfgHost();
        }

        $dbPort = self::cfgPort();
        $dbPass = (string) (isset($row['db_password']) ? $row['db_password'] : '');

        $dsn = 'mysql:host=' . $dbHost . ';port=' . (string) $dbPort . ';dbname=' . $dbName . ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ));
        } catch (Throwable $e) {
            error_log('TenantDatabaseManager: connect failed for tenant_id=' . (string) $tenantId . ' — ' . $e->getMessage());
            throw new RuntimeException('TenantDatabaseManager: database connection failed.');
        }

        self::$pdoByTenantId[$tenantId] = $pdo;
        return $pdo;
    }

    /**
     * Resolve tenant DB strictly from TenantExecutionContext.
     * No fallback tenant resolution is performed here.
     *
     * @return PDO
     */
    public static function pdoForCurrentContext()
    {
        if (!class_exists('TenantExecutionContext', false)) {
            require_once __DIR__ . '/../../core/TenantExecutionContext.php';
        }
        $tenantId = TenantExecutionContext::requireTenantId();
        return self::pdoForTenantId((int) $tenantId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fetchTenantRow($tenantId)
    {
        $pdo = self::getControlPdo();
        $stmt = $pdo->prepare(
            'SELECT id, name, domain, database_name, db_host, db_user, db_password, status, created_at, updated_at
             FROM tenants
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(array(':id' => (int) $tenantId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * @return PDO
     */
    private static function getControlPdo()
    {
        if (self::$controlPdo instanceof PDO) {
            return self::$controlPdo;
        }

        $host = self::cfgHost();
        $port = self::cfgPort();
        $user = self::cfgUser();
        $pass = self::cfgPass();
        $dbName = self::cfgControlPanelDbName();

        if ($user === '') {
            throw new RuntimeException('TenantDatabaseManager: DB user is not configured.');
        }

        $dsn = 'mysql:host=' . $host . ';port=' . (string) $port . ';dbname=' . $dbName . ';charset=utf8mb4';

        try {
            self::$controlPdo = new PDO($dsn, $user, $pass, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ));
        } catch (Throwable $e) {
            error_log('TenantDatabaseManager: control DB connect failed — ' . $e->getMessage());
            throw new RuntimeException('TenantDatabaseManager: control database connection failed.');
        }

        return self::$controlPdo;
    }

    /**
     * @return string
     */
    private static function cfgHost()
    {
        if (defined('DB_HOST')) {
            return (string) DB_HOST;
        }
        $e = getenv('DB_HOST');
        return ($e !== false && $e !== '') ? (string) $e : 'localhost';
    }

    /**
     * @return int
     */
    private static function cfgPort()
    {
        if (defined('DB_PORT')) {
            return (int) DB_PORT;
        }
        $e = getenv('DB_PORT');
        if ($e !== false && $e !== '') {
            return (int) $e;
        }
        return 3306;
    }

    /**
     * @return string
     */
    private static function cfgUser()
    {
        if (defined('DB_USER')) {
            return (string) DB_USER;
        }
        $e = getenv('DB_USER');
        return ($e !== false && $e !== '') ? (string) $e : '';
    }

    /**
     * @return string
     */
    private static function cfgPass()
    {
        if (defined('DB_PASS')) {
            return (string) DB_PASS;
        }
        $e = getenv('DB_PASS');
        return $e !== false ? (string) $e : '';
    }

    /**
     * @return string
     */
    private static function cfgControlPanelDbName()
    {
        if (defined('CONTROL_PANEL_DB_NAME') && CONTROL_PANEL_DB_NAME !== '') {
            return (string) CONTROL_PANEL_DB_NAME;
        }
        $e = getenv('CONTROL_PANEL_DB_NAME');
        if ($e !== false && $e !== '') {
            return (string) $e;
        }
        return 'outratib_control_panel_db';
    }
}
