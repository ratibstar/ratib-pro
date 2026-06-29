<?php
/**
 * Resolve dedicated RATEB ERP database from control_agencies by HTTP_HOST.
 * Safe to call when DB_NAME is already defined (main site + client subdomain ERP).
 */
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'agency_lookup.php';

if (!function_exists('resolve_agency_erp_by_host')) {
    function resolve_agency_erp_by_host(string $host): bool
    {
        if (defined('RATEB_ERP_AGENCY_RESOLVED') && RATEB_ERP_AGENCY_RESOLVED) {
            return defined('RATEB_ERP_DB_NAME') && (string) RATEB_ERP_DB_NAME !== '';
        }
        if (defined('RATEB_ERP_DB_NAME') && (string) RATEB_ERP_DB_NAME !== '' && !defined('RATEB_ERP_AGENCY_RESOLVED')) {
            return false;
        }
        $host = rateb_normalize_http_host($host);
        if ($host === '') {
            return false;
        }
        $row = rateb_lookup_agency_by_host($host);
        if ($row === null) {
            return false;
        }

        return rateb_apply_agency_erp_constants($row);
    }
}

if (!function_exists('resolve_agency_erp_by_id')) {
    function resolve_agency_erp_by_id(int $agencyId): bool
    {
        if ($agencyId < 1) {
            return false;
        }
        $row = rateb_lookup_agency_by_id($agencyId);
        if ($row === null) {
            return false;
        }

        return rateb_apply_agency_erp_constants($row);
    }
}

if (!function_exists('rateb_resolve_agency_erp_from_request')) {
    function rateb_resolve_agency_erp_from_request(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }
        $agencyId = (int) ($_GET['agency_id'] ?? $_POST['agency_id'] ?? 0);
        if ($agencyId > 0 && resolve_agency_erp_by_id($agencyId)) {
            return true;
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');

        return resolve_agency_erp_by_host($host);
    }
}
