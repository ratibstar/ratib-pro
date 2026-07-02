<?php
/**
 * Shared control_agencies lookup (Pro DB + ERP DB credentials per host/agency id).
 */
declare(strict_types=1);

if (!function_exists('rateb_agency_lookup_connection')) {
    function rateb_agency_lookup_connection(): ?mysqli
    {
        $lookupFile = __DIR__ . DIRECTORY_SEPARATOR . 'control_db_for_lookup.php';
        if (!is_readable($lookupFile)) {
            error_log('agency_lookup: control_db_for_lookup.php missing');

            return null;
        }
        require_once $lookupFile;
        try {
            $conn = new mysqli(
                CONTROL_DB_HOST,
                CONTROL_DB_USER,
                CONTROL_DB_PASS,
                CONTROL_DB_NAME,
                CONTROL_DB_PORT
            );
            $conn->set_charset('utf8mb4');

            return $conn;
        } catch (Throwable $e) {
            error_log('agency_lookup: control DB failed — ' . $e->getMessage());

            return null;
        }
    }
}

if (!function_exists('rateb_agency_lookup_has_erp_columns')) {
    function rateb_agency_lookup_has_erp_columns(mysqli $conn): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $res = @$conn->query("SHOW COLUMNS FROM control_agencies LIKE 'erp_db_name'");
        $cache = $res !== false && $res->num_rows > 0;

        return $cache;
    }
}

if (!function_exists('rateb_agency_lookup_select_columns')) {
    function rateb_agency_lookup_select_columns(mysqli $conn): string
    {
        $base = 'id, name, slug, db_host, db_port, db_user, db_pass, db_name, site_url, is_active, tenant_id';
        if (!rateb_agency_lookup_has_erp_columns($conn)) {
            return $base;
        }

        $base .= ', erp_db_name, erp_db_host, erp_db_user, erp_db_pass, erp_status, erp_provisioned_at, erp_plan_slug';
        $erpCo = @$conn->query("SHOW COLUMNS FROM control_agencies LIKE 'erp_company_id'");
        if ($erpCo && $erpCo->num_rows > 0) {
            $base .= ', erp_company_id';
        }

        return $base;
    }
}

if (!function_exists('rateb_normalize_http_host')) {
    function rateb_normalize_http_host(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host !== '' && strpos($host, ':') !== false) {
            $host = explode(':', $host, 2)[0];
        }

        return $host;
    }
}

if (!function_exists('rateb_lookup_agency_by_host')) {
    /**
     * @return array<string, mixed>|null
     */
    function rateb_lookup_agency_by_host(string $host): ?array
    {
        $host = rateb_normalize_http_host($host);
        if ($host === '') {
            return null;
        }
        $conn = rateb_agency_lookup_connection();
        if (!$conn) {
            return null;
        }
        $chk = @$conn->query("SHOW TABLES LIKE 'control_agencies'");
        if (!$chk || $chk->num_rows === 0) {
            $conn->close();

            return null;
        }
        $cols = rateb_agency_lookup_select_columns($conn);
        $sql = "SELECT {$cols} FROM control_agencies
                WHERE (site_url = ? OR site_url = ? OR site_url = ? OR site_url = ? OR site_url LIKE ? OR site_url LIKE ?)
                  AND is_active = 1
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $conn->close();

            return null;
        }
        $https = 'https://' . $host;
        $httpsSlash = 'https://' . $host . '/';
        $http = 'http://' . $host;
        $httpSlash = 'http://' . $host . '/';
        $httpsLike = 'https://' . $host . '/%';
        $httpLike = 'http://' . $host . '/%';
        $stmt->bind_param('ssssss', $https, $httpsSlash, $http, $httpSlash, $httpsLike, $httpLike);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $conn->close();

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('rateb_list_agencies_with_erp')) {
    /**
     * Agencies that have a dedicated ERP database (for migration push from platform admin).
     *
     * @return list<array<string, mixed>>
     */
    function rateb_list_agencies_with_erp(bool $subscribedOnly = false): array
    {
        $conn = rateb_agency_lookup_connection();
        if (!$conn) {
            return [];
        }
        $chk = @$conn->query("SHOW TABLES LIKE 'control_agencies'");
        if (!$chk || $chk->num_rows === 0) {
            $conn->close();

            return [];
        }
        if (!rateb_agency_lookup_has_erp_columns($conn)) {
            $conn->close();

            return [];
        }
        $hasSusp = false;
        $suspChk = @$conn->query("SHOW COLUMNS FROM control_agencies LIKE 'is_suspended'");
        if ($suspChk && $suspChk->num_rows > 0) {
            $hasSusp = true;
        }
        $cols = rateb_agency_lookup_select_columns($conn);
        $sql = "SELECT {$cols} FROM control_agencies WHERE TRIM(COALESCE(erp_db_name, '')) <> ''";
        if ($subscribedOnly) {
            $sql .= ' AND is_active = 1';
            if ($hasSusp) {
                $sql .= ' AND COALESCE(is_suspended, 0) = 0';
            }
            $sql .= " AND LOWER(COALESCE(erp_status, 'none')) = 'ready'";
        }
        $sql .= ' ORDER BY name ASC';
        $res = $conn->query($sql);
        if (!$res) {
            $conn->close();

            return [];
        }
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        $conn->close();

        return $rows;
    }
}

if (!function_exists('rateb_lookup_agency_by_erp_company_id')) {
    /**
     * @return array<string, mixed>|null
     */
    function rateb_lookup_agency_by_erp_company_id(int $companyId): ?array
    {
        if ($companyId < 1) {
            return null;
        }
        $conn = rateb_agency_lookup_connection();
        if (!$conn || !rateb_agency_lookup_has_erp_columns($conn)) {
            if ($conn) {
                $conn->close();
            }

            return null;
        }
        $chk = @$conn->query("SHOW COLUMNS FROM control_agencies LIKE 'erp_company_id'");
        if (!$chk || $chk->num_rows === 0) {
            $conn->close();

            return null;
        }
        $cols = rateb_agency_lookup_select_columns($conn);
        $stmt = $conn->prepare("SELECT {$cols} FROM control_agencies WHERE erp_company_id = ? LIMIT 1");
        if (!$stmt) {
            $conn->close();

            return null;
        }
        $stmt->bind_param('i', $companyId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $conn->close();

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('rateb_save_agency_erp_company_link')) {
    function rateb_save_agency_erp_company_link(int $agencyId, int $companyId): bool
    {
        if ($agencyId < 1) {
            return false;
        }
        $conn = rateb_agency_lookup_connection();
        if (!$conn) {
            return false;
        }
        $bridge = dirname(__DIR__, 2) . '/control-panel/includes/control/ErpProvisioningService.php';
        if (is_file($bridge)) {
            require_once $bridge;
            try {
                ErpProvisioningService::saveAgencyErpCompanyId($conn, $agencyId, $companyId);
                $conn->close();

                return true;
            } catch (Throwable $e) {
                error_log('rateb_save_agency_erp_company_link: ' . $e->getMessage());
                $conn->close();

                return false;
            }
        }
        $conn->close();

        return false;
    }
}

if (!function_exists('rateb_lookup_agency_by_id')) {
    /**
     * @return array<string, mixed>|null
     */
    function rateb_lookup_agency_by_id(int $agencyId): ?array
    {
        if ($agencyId < 1) {
            return null;
        }
        $conn = rateb_agency_lookup_connection();
        if (!$conn) {
            return null;
        }
        $chk = @$conn->query("SHOW TABLES LIKE 'control_agencies'");
        if (!$chk || $chk->num_rows === 0) {
            $conn->close();

            return null;
        }
        $cols = rateb_agency_lookup_select_columns($conn);
        $stmt = $conn->prepare("SELECT {$cols} FROM control_agencies WHERE id = ? LIMIT 1");
        if (!$stmt) {
            $conn->close();

            return null;
        }
        $stmt->bind_param('i', $agencyId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $conn->close();

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('rateb_apply_agency_pro_constants')) {
    /** @param array<string, mixed> $row */
    function rateb_apply_agency_pro_constants(array $row, string $host): void
    {
        if (!defined('DB_HOST')) {
            define('DB_HOST', (string) ($row['db_host'] ?? 'localhost'));
        }
        if (!defined('DB_PORT')) {
            define('DB_PORT', (int) ($row['db_port'] ?? 3306));
        }
        if (!defined('DB_USER')) {
            define('DB_USER', (string) ($row['db_user'] ?? ''));
        }
        if (!defined('DB_PASS')) {
            define('DB_PASS', (string) ($row['db_pass'] ?? ''));
        }
        if (!defined('DB_NAME')) {
            define('DB_NAME', (string) ($row['db_name'] ?? ''));
        }
        if (!defined('CONTROL_PANEL_DB_NAME')) {
            $_cp = getenv('CONTROL_PANEL_DB_NAME');
            define(
                'CONTROL_PANEL_DB_NAME',
                ($_cp !== false && $_cp !== '') ? $_cp : (function_exists('rateb_control_panel_database') ? rateb_control_panel_database() : 'admin_control_panel_db')
            );
        }
        if (!defined('SITE_URL')) {
            $site = rtrim(trim((string) ($row['site_url'] ?? '')), '/');
            define('SITE_URL', $site !== '' ? $site : ('https://' . $host));
        }
        if (!defined('APP_NAME')) {
            define('APP_NAME', 'RATEB');
        }
        if (!defined('APP_VERSION')) {
            define('APP_VERSION', '1.0.0');
        }
        if (!defined('BASE_URL')) {
            define('BASE_URL', '');
        }
        if (!defined('NO_BANGLA')) {
            define('NO_BANGLA', true);
        }
        // Dedicated agency domains: tenant DB is already resolved by host; still need control lookup for login/Open.
        if (!defined('SINGLE_URL_MODE')) {
            define('SINGLE_URL_MODE', true);
        }
    }
}

if (!function_exists('rateb_apply_agency_erp_constants')) {
    /** @param array<string, mixed> $row */
    function rateb_apply_agency_erp_constants(array $row): bool
    {
        $erpDb = trim((string) ($row['erp_db_name'] ?? ''));
        if ($erpDb === '') {
            return false;
        }
        $erpHost = trim((string) ($row['erp_db_host'] ?? ''));
        if ($erpHost === '') {
            $erpHost = trim((string) ($row['db_host'] ?? ''));
        }
        if ($erpHost === '') {
            $erpHost = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
        }
        $erpUser = trim((string) ($row['erp_db_user'] ?? ''));
        if ($erpUser === '') {
            $erpUser = trim((string) ($row['db_user'] ?? ''));
        }
        $erpPass = (string) ($row['erp_db_pass'] ?? '');
        if ($erpPass === '' && array_key_exists('db_pass', $row)) {
            $erpPass = (string) ($row['db_pass'] ?? '');
        }
        if ($erpUser === '' && defined('DB_USER')) {
            $erpUser = (string) DB_USER;
        }
        if ($erpPass === '' && defined('DB_PASS')) {
            $erpPass = (string) DB_PASS;
        }

        if (!defined('RATEB_ERP_DB_NAME')) {
            define('RATEB_ERP_DB_NAME', $erpDb);
        }
        if (!defined('RATEB_ERP_DB_HOST')) {
            define('RATEB_ERP_DB_HOST', $erpHost);
        }
        if (!defined('RATEB_ERP_DB_USER')) {
            define('RATEB_ERP_DB_USER', $erpUser);
        }
        if (!defined('RATEB_ERP_DB_PASS')) {
            define('RATEB_ERP_DB_PASS', $erpPass);
        }
        if (!defined('RATEB_ERP_DEPLOYMENT_MODE')) {
            define('RATEB_ERP_DEPLOYMENT_MODE', 'dedicated');
        }
        if (!defined('RATEB_ERP_AGENCY_ID')) {
            define('RATEB_ERP_AGENCY_ID', (int) ($row['id'] ?? 0));
        }
        if (!defined('RATEB_ERP_AGENCY_RESOLVED')) {
            define('RATEB_ERP_AGENCY_RESOLVED', true);
        }
        if (!defined('SITE_URL')) {
            $site = rtrim(trim((string) ($row['site_url'] ?? '')), '/');
            $host = function_exists('rateb_normalize_http_host')
                ? rateb_normalize_http_host((string) ($_SERVER['HTTP_HOST'] ?? ''))
                : strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
            if ($site === '' && $host !== '') {
                $site = 'https://' . $host;
            }
            if ($site !== '') {
                define('SITE_URL', $site);
            }
        }

        return true;
    }
}

if (!function_exists('rateb_suggested_erp_db_name')) {
    function rateb_suggested_erp_db_name(string $slug): string
    {
        $slug = preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($slug)));
        $slug = trim((string) $slug, '_');
        if ($slug === '') {
            $slug = 'client';
        }
        $prefix = function_exists('rateb_db_prefix') ? rateb_db_prefix() : 'admin';

        return substr(strtolower($prefix . '_rateb_erp_' . $slug), 0, 64);
    }
}
