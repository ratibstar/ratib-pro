<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/core/ProvisioningService.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/core/ProvisioningService.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/EventBus.php';

/**
 * Creates tenant row + database + applies SQL migrations from config/migrations/*.sql
 * Requires MySQL user with CREATE DATABASE privilege.
 */
final class ProvisioningService
{
    /**
     * Normalize PDO to buffered mode on MySQL to avoid HY000/2014
     * when mixed query paths are executed in the same request.
     */
    private static function enableBufferedQueries(PDO $pdo): void
    {
        try {
            if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            }
        } catch (Throwable $e) {
            // Ignore when driver does not support this attribute.
        }
    }

    public static function createTenant(
        PDO $controlPdo,
        string $name,
        string $domain,
        array $options = []
    ): array {
        $domain = strtolower(trim($domain));
        $name = trim($name);
        if ($name === '' || $domain === '') {
            throw new InvalidArgumentException('name and domain are required');
        }

        $dbHost = trim((string) ($options['db_host'] ?? '')) ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
        $dbPort = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $adminUser = defined('DB_USER') ? DB_USER : '';
        $adminPass = defined('DB_PASS') ? DB_PASS : '';

        $dbName = trim((string) ($options['database_name'] ?? ''));
        if ($dbName === '') {
            $slug = preg_replace('/[^a-z0-9_]+/i', '_', $domain) ?? 'tenant';
            $slug = trim((string) $slug, '_');
            if ($slug === '') {
                $slug = 'tenant_' . bin2hex(random_bytes(3));
            }
            $prefix = defined('DB_NAME') ? preg_replace('/[^a-z0-9_]/i', '_', (string) DB_NAME) : 'app';
            $dbName = substr(strtolower($prefix . '_' . $slug), 0, 60);
        }

        $dbUser = trim((string) ($options['db_user'] ?? ''));
        if ($dbUser === '') {
            $dbUser = $adminUser;
        }
        $dbPassword = (string) ($options['db_password'] ?? '');
        if ($dbPassword === '') {
            $dbPassword = bin2hex(random_bytes(8));
        }

        $status = strtolower(trim((string) ($options['status'] ?? 'active')));
        if (!in_array($status, ['active', 'provisioning', 'suspended'], true)) {
            $status = 'active';
        }

        self::enableBufferedQueries($controlPdo);

        $serverDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, $dbPort);
        $tenantDbReady = false;
        $createDbError = null;
        try {
            $serverPdo = new PDO($serverDsn, $adminUser, $adminPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            self::enableBufferedQueries($serverPdo);
            $serverPdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $tenantDbReady = true;
        } catch (Throwable $e) {
            $createDbError = $e->getMessage();
            // Shared hosting may not grant CREATE DATABASE. If DB already exists and credentials work, continue.
            error_log('ProvisioningService create-db fallback for ' . $dbName . ': ' . $createDbError);
        }

        $tenantDsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
        $tenantConnectUser = $dbUser !== '' ? $dbUser : $adminUser;
        $tenantConnectPass = $dbPassword !== '' ? $dbPassword : $adminPass;
        try {
            $tenantPdo = new PDO($tenantDsn, $tenantConnectUser, $tenantConnectPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            self::enableBufferedQueries($tenantPdo);
            $tenantDbReady = true;
        } catch (Throwable $e) {
            if ($tenantDbReady) {
                throw $e;
            }
            throw new RuntimeException('Tenant DB is not accessible. Create DB failed and existing DB connection failed: ' . ($createDbError ?? $e->getMessage()));
        }

        self::applyMigrations($tenantPdo, $dbName);

        $stmt = $controlPdo->prepare(
            "INSERT INTO tenants (name, domain, database_name, db_host, db_user, db_password, status, created_at)
             VALUES (:name, :domain, :database_name, :db_host, :db_user, :db_password, :status, NOW())"
        );
        $stmt->execute([
            ':name' => $name,
            ':domain' => $domain,
            ':database_name' => $dbName,
            ':db_host' => $dbHost !== '' ? $dbHost : null,
            ':db_user' => $dbUser,
            ':db_password' => $dbPassword,
            ':status' => $status,
        ]);
        // Ensure no active cursor remains on control PDO before subsequent queries/events.
        $stmt->closeCursor();
        $newId = (int) $controlPdo->lastInsertId();
        emitEvent('TENANT_CREATED', 'info', 'Tenant created and provisioned', [
            'tenant_id' => $newId,
            'source' => 'provisioning_service',
            'provisioned_db' => $dbName,
        ], $controlPdo);

        return [
            'tenant_id' => $newId,
            'database_name' => $dbName,
            'db_user' => $dbUser,
            'db_password' => $dbPassword,
        ];
    }

    private static function applyMigrations(PDO $tenantPdo, string $dbName): void
    {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'migrations';
        if (!is_dir($dir)) {
            return;
        }
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files);
        foreach ($files as $file) {
            $base = basename($file);
            if (stripos($base, 'admin_control_plane') !== false) {
                continue;
            }
            $sql = (string) file_get_contents($file);
            if (trim($sql) === '') {
                continue;
            }
            $tenantPdo->exec('USE `' . str_replace('`', '``', $dbName) . '`');
            foreach (self::splitSqlStatements($sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '' || strpos($stmt, '--') === 0) {
                    continue;
                }
                try {
                    $tenantPdo->exec($stmt);
                } catch (Throwable $e) {
                    error_log('Provisioning migration skip ' . $base . ': ' . $e->getMessage());
                    emitEvent('TENANT_MIGRATION_WARNING', 'warn', 'Provisioning migration statement skipped', [
                        'source' => 'provisioning_service',
                        'migration_file' => $base,
                        'database' => $dbName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private static function splitSqlStatements(string $sql): array
    {
        $parts = preg_split('/;\s*\n/', $sql) ?: [];
        return array_map('trim', $parts);
    }
}
