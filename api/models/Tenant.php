<?php
/**
 * EN: Handles API endpoint/business logic in `api/models/Tenant.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/models/Tenant.php`.
 */

// IMPORTANT: do not require includes/config.php from here (risk of recursion when tenant middleware
// is loaded from includes/config.php). Only load env + DB constants.
if (!defined('ENV_LOADED')) {
    require_once __DIR__ . '/../../config/env/load.php';
}
if (!defined('CONTROL_PANEL_DB_NAME')) {
    $_cp = getenv('CONTROL_PANEL_DB_NAME');
    define('CONTROL_PANEL_DB_NAME', ($_cp !== false && $_cp !== '') ? $_cp : 'outratib_control_panel_db');
}
if (!defined('DB_HOST')) {
    // Avoid pulling in config/database.php (it starts sessions) — keep Tenant model bootstrap-light.
    define('DB_HOST', getenv('DB_HOST') !== false && getenv('DB_HOST') !== '' ? getenv('DB_HOST') : 'localhost');
    define('DB_PORT', (int) (getenv('DB_PORT') !== false && getenv('DB_PORT') !== '' ? getenv('DB_PORT') : 3306));
    define('DB_USER', getenv('DB_USER') !== false && getenv('DB_USER') !== '' ? getenv('DB_USER') : '');
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
    define('DB_NAME', getenv('DB_NAME') !== false && getenv('DB_NAME') !== '' ? getenv('DB_NAME') : '');
}

class Tenant
{
    private const ALLOWED_STATUSES = ['provisioning', 'active', 'suspended'];
    private static ?PDO $controlPdo = null;

    public static function findByDomain(string $domain): ?array
    {
        $normalizedDomain = self::normalizeDomain($domain);
        if ($normalizedDomain === '') {
            return null;
        }

        $pdo = self::getControlPdo();
        $stmt = $pdo->prepare(
            "SELECT id, name, domain, database_name, db_host, db_user, status, created_at, updated_at
             FROM tenants
             WHERE domain = :domain
             LIMIT 1"
        );
        $stmt->execute([':domain' => $normalizedDomain]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function createTenant(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $domain = self::normalizeDomain((string) ($data['domain'] ?? ''));

        if ($name === '' || $domain === '') {
            throw new InvalidArgumentException('Tenant name and domain are required.');
        }

        $status = (string) ($data['status'] ?? 'provisioning');
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $status = 'provisioning';
        }

        $pdo = self::getControlPdo();
        $stmt = $pdo->prepare(
            "INSERT INTO tenants (name, domain, database_name, db_host, db_user, db_password, status)
             VALUES (:name, :domain, :database_name, :db_host, :db_user, :db_password, :status)"
        );
        $stmt->execute([
            ':name' => $name,
            ':domain' => $domain,
            ':database_name' => (string) ($data['database_name'] ?? ''),
            ':db_host' => isset($data['db_host']) && $data['db_host'] !== '' ? (string) $data['db_host'] : null,
            ':db_user' => (string) ($data['db_user'] ?? ''),
            // Phase 1 placeholder. Secret-manager reference/encryption will be implemented in later phases.
            ':db_password' => (string) ($data['db_password'] ?? ''),
            ':status' => $status,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function activateTenant(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $pdo = self::getControlPdo();
        $stmt = $pdo->prepare(
            "UPDATE tenants
             SET status = 'active', updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private static function getControlPdo(): PDO
    {
        if (self::$controlPdo instanceof PDO) {
            return self::$controlPdo;
        }

        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $user = defined('DB_USER') ? DB_USER : '';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $dbName = defined('CONTROL_PANEL_DB_NAME') && CONTROL_PANEL_DB_NAME !== ''
            ? CONTROL_PANEL_DB_NAME
            : (defined('DB_NAME') ? DB_NAME : '');

        if ($dbName === '' || $user === '') {
            throw new RuntimeException('Tenant model control DB configuration is missing.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
        self::$controlPdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$controlPdo;
    }

    private static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        // Strip port if provided (e.g., example.com:443).
        $colonPos = strpos($domain, ':');
        if ($colonPos !== false) {
            $domain = substr($domain, 0, $colonPos);
        }

        return $domain;
    }
}
