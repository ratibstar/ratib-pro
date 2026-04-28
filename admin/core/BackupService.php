<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/core/BackupService.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/core/BackupService.php`.
 */
declare(strict_types=1);

/**
 * Tenant DB backup/restore. Uses mysqldump/mysql CLI when available and allowed.
 */
final class BackupService
{
    public static function backup(int $tenantId, PDO $controlPdo, string $backupDir): string
    {
        if (!defined('CONTROL_CENTER_BACKUP_ENABLED') || CONTROL_CENTER_BACKUP_ENABLED !== true) {
            throw new RuntimeException('Backup is disabled. Set CONTROL_CENTER_BACKUP_ENABLED=true in env.');
        }
        $tenant = self::loadTenant($controlPdo, $tenantId);
        $host = trim((string) ($tenant['db_host'] ?? '')) ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $db = (string) ($tenant['database_name'] ?? '');
        $user = (string) ($tenant['db_user'] ?? '');
        $pass = (string) ($tenant['db_password'] ?? '');
        if ($db === '' || $user === '') {
            throw new RuntimeException('Tenant DB credentials incomplete.');
        }
        if (!is_dir($backupDir) && !@mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
            throw new RuntimeException('Backup directory not writable.');
        }
        $file = rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tenant_' . $tenantId . '_' . date('Ymd_His') . '.sql';
        $mysqldump = self::findBinary('mysqldump');
        if ($mysqldump === null) {
            throw new RuntimeException('mysqldump not found on server PATH.');
        }
        $cmd = sprintf(
            '%s --protocol=tcp -h %s -P %d -u %s %s %s > %s 2>&1',
            escapeshellarg($mysqldump),
            escapeshellarg($host),
            $port,
            escapeshellarg($user),
            $pass !== '' ? '-p' . escapeshellarg($pass) : '',
            escapeshellarg($db),
            escapeshellarg($file)
        );
        exec($cmd, $out, $code);
        if ($code !== 0 || !is_file($file) || filesize($file) < 10) {
            throw new RuntimeException('Backup failed.');
        }
        return $file;
    }

    public static function restore(int $tenantId, PDO $controlPdo, string $sqlFile): void
    {
        if (!defined('CONTROL_CENTER_BACKUP_ENABLED') || CONTROL_CENTER_BACKUP_ENABLED !== true) {
            throw new RuntimeException('Restore is disabled. Set CONTROL_CENTER_BACKUP_ENABLED=true in env.');
        }
        if (!is_readable($sqlFile)) {
            throw new RuntimeException('Backup file not readable.');
        }
        $tenant = self::loadTenant($controlPdo, $tenantId);
        $host = trim((string) ($tenant['db_host'] ?? '')) ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $db = (string) ($tenant['database_name'] ?? '');
        $user = (string) ($tenant['db_user'] ?? '');
        $pass = (string) ($tenant['db_password'] ?? '');
        $mysql = self::findBinary('mysql');
        if ($mysql === null) {
            throw new RuntimeException('mysql client not found on server PATH.');
        }
        $cmd = sprintf(
            '%s --protocol=tcp -h %s -P %d -u %s %s %s < %s 2>&1',
            escapeshellarg($mysql),
            escapeshellarg($host),
            $port,
            escapeshellarg($user),
            $pass !== '' ? '-p' . escapeshellarg($pass) : '',
            escapeshellarg($db),
            escapeshellarg($sqlFile)
        );
        exec($cmd, $out, $code);
        if ($code !== 0) {
            throw new RuntimeException('Restore failed.');
        }
    }

    private static function loadTenant(PDO $controlPdo, int $tenantId): array
    {
        $st = $controlPdo->prepare('SELECT * FROM tenants WHERE id = :id LIMIT 1');
        $st->execute([':id' => $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Tenant not found.');
        }
        return $row;
    }

    private static function findBinary(string $name): ?string
    {
        $paths = ['C:\\xampp\\mysql\\bin\\' . $name . '.exe', '/usr/bin/' . $name, '/usr/local/bin/' . $name];
        foreach ($paths as $p) {
            if (is_executable($p)) {
                return $p;
            }
        }
        $which = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        exec($which . ' ' . escapeshellarg($name) . ' 2>NUL', $o, $c);
        if ($c === 0 && !empty($o[0]) && is_executable($o[0])) {
            return $o[0];
        }
        return null;
    }
}
