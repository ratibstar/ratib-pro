<?php
declare(strict_types=1);

/**
 * Restore platform super-admin login on an agency ERP database (e.g. test.rateb.sa).
 * Password reset to bootstrap value: "password"
 */
require_once __DIR__ . '/../config/env/agency_lookup.php';

if (!function_exists('rateb_agency_erp_pdo')) {
    /** @param array<string, mixed> $agency */
    function rateb_agency_erp_pdo(array $agency): PDO
    {
        $dbName = trim((string) ($agency['erp_db_name'] ?? ''));
        if ($dbName === '') {
            $dbName = trim((string) ($agency['db_name'] ?? ''));
        }
        if ($dbName === '') {
            throw new RuntimeException('Agency has no ERP database name');
        }
        $host = trim((string) ($agency['erp_db_host'] ?? ''));
        if ($host === '') {
            $host = trim((string) ($agency['db_host'] ?? 'localhost'));
        }
        if ($host === '') {
            $host = 'localhost';
        }
        $port = (int) ($agency['db_port'] ?? 3306);
        if ($port < 1) {
            $port = 3306;
        }
        $user = trim((string) ($agency['erp_db_user'] ?? ''));
        if ($user === '') {
            $user = trim((string) ($agency['db_user'] ?? ''));
        }
        $pass = (string) ($agency['erp_db_pass'] ?? '');
        if ($pass === '') {
            $pass = (string) ($agency['db_pass'] ?? '');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdo;
    }
}

if (!function_exists('rateb_agency_restore_super_admin')) {
    /**
     * @return array<string, mixed>
     */
    function rateb_agency_restore_super_admin(int $agencyId, bool $resetPassword = true): array
    {
        if ($agencyId < 1) {
            throw new InvalidArgumentException('Invalid agency id');
        }
        if (!function_exists('rateb_lookup_agency_by_id')) {
            throw new RuntimeException('Agency lookup unavailable');
        }
        $agency = rateb_lookup_agency_by_id($agencyId);
        if ($agency === null) {
            throw new RuntimeException('Agency #' . $agencyId . ' not found');
        }

        $erpRoot = dirname(__DIR__) . '/rateb-erp';
        $runnerFile = $erpRoot . '/bin/SuperAdminRestoreRunner.php';
        if (!is_file($runnerFile)) {
            throw new RuntimeException('SuperAdminRestoreRunner missing');
        }
        require_once $runnerFile;

        $pdo = rateb_agency_erp_pdo($agency);
        $runner = new SuperAdminRestoreRunner($pdo);
        $report = $runner->restore($resetPassword);
        $report['agency_id'] = $agencyId;
        $report['agency_name'] = trim((string) ($agency['name'] ?? ''));
        $report['site_url'] = trim((string) ($agency['site_url'] ?? ''));

        return $report;
    }
}

if (!function_exists('rateb_agency_restore_super_admin_for_host')) {
    /**
     * @return array<string, mixed>
     */
    function rateb_agency_restore_super_admin_for_host(string $host, bool $resetPassword = true): array
    {
        $host = function_exists('rateb_normalize_http_host')
            ? rateb_normalize_http_host($host)
            : strtolower(trim($host));
        if ($host === '') {
            throw new InvalidArgumentException('Host required');
        }
        if (!function_exists('rateb_lookup_agency_by_host')) {
            throw new RuntimeException('Agency host lookup unavailable');
        }
        $agency = rateb_lookup_agency_by_host($host);
        if ($agency === null) {
            throw new RuntimeException('No agency for host: ' . $host);
        }

        return rateb_agency_restore_super_admin((int) ($agency['id'] ?? 0), $resetPassword);
    }
}
