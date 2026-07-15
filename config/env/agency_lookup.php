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

if (!function_exists('rateb_agency_host_from_site_url')) {
    function rateb_agency_host_from_site_url(string $siteUrl): string
    {
        $siteUrl = trim($siteUrl);
        if ($siteUrl === '') {
            return '';
        }
        $host = parse_url($siteUrl, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return rateb_normalize_http_host($host);
        }
        $clean = preg_replace('#^https?://#i', '', $siteUrl) ?? $siteUrl;
        $clean = explode('/', $clean, 2)[0];

        return rateb_normalize_http_host($clean);
    }
}

if (!function_exists('rateb_lookup_agency_row_by_host')) {
    /**
     * @return array<string, mixed>|null
     */
    function rateb_lookup_agency_row_by_host(string $host, bool $activeOnly): ?array
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
        $activeSql = $activeOnly ? ' AND is_active = 1' : '';
        $sql = "SELECT {$cols} FROM control_agencies
                WHERE (
                    site_url = ? OR site_url = ? OR site_url = ? OR site_url = ?
                    OR site_url LIKE ? OR site_url LIKE ?
                    OR site_url LIKE ?
                    OR LOWER(TRIM(TRAILING '/' FROM REPLACE(REPLACE(site_url, 'https://', ''), 'http://', ''))) = ?
                    OR LOWER(TRIM(TRAILING '/' FROM REPLACE(REPLACE(site_url, 'https://', ''), 'http://', ''))) LIKE ?
                ){$activeSql}
                ORDER BY id ASC
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
        $hostLike = '%' . $host . '%';
        $hostPathLike = $host . '/%';
        $stmt->bind_param('sssssssss', $https, $httpsSlash, $http, $httpSlash, $httpsLike, $httpLike, $hostLike, $host, $hostPathLike);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $conn->close();

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('rateb_lookup_agency_erp_by_host')) {
    /**
     * ERP host binding — ready agencies with erp_db_name, even when is_active is off.
     *
     * @return array<string, mixed>|null
     */
    function rateb_lookup_agency_erp_by_host(string $host): ?array
    {
        $host = rateb_normalize_http_host($host);
        if ($host === '') {
            return null;
        }
        foreach ([true, false] as $activeOnly) {
            $row = rateb_lookup_agency_row_by_host($host, $activeOnly);
            if ($row === null) {
                continue;
            }
            $erpDb = trim((string) ($row['erp_db_name'] ?? ''));
            $status = strtolower(trim((string) ($row['erp_status'] ?? '')));
            if ($erpDb !== '' && $status === 'ready') {
                return $row;
            }
        }

        return null;
    }
}

if (!function_exists('rateb_agency_erp_binding_for_host')) {
    /**
     * @return array{host:string,port:int,user:string,pass:string,db:string,agency_id:int}|null
     */
    function rateb_agency_erp_binding_for_host(string $host): ?array
    {
        $row = rateb_lookup_agency_erp_by_host($host);
        if ($row === null) {
            return null;
        }
        $db = trim((string) ($row['erp_db_name'] ?? ''));
        if ($db === '') {
            return null;
        }
        $dbHost = trim((string) ($row['erp_db_host'] ?? ''));
        if ($dbHost === '') {
            $dbHost = trim((string) ($row['db_host'] ?? ''));
        }
        if ($dbHost === '') {
            $dbHost = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
        }
        $dbUser = trim((string) ($row['erp_db_user'] ?? ''));
        if ($dbUser === '') {
            $dbUser = trim((string) ($row['db_user'] ?? ''));
        }
        $dbPass = (string) ($row['erp_db_pass'] ?? '');
        if ($dbPass === '' && array_key_exists('db_pass', $row)) {
            $dbPass = (string) ($row['db_pass'] ?? '');
        }
        if ($dbUser === '' && defined('DB_USER')) {
            $dbUser = (string) DB_USER;
        }
        if ($dbPass === '' && defined('DB_PASS')) {
            $dbPass = (string) DB_PASS;
        }

        return [
            'host' => $dbHost !== '' ? $dbHost : 'localhost',
            'port' => (int) ($row['db_port'] ?? 3306),
            'user' => $dbUser,
            'pass' => $dbPass,
            'db' => $db,
            'agency_id' => (int) ($row['id'] ?? 0),
        ];
    }
}

if (!function_exists('rateb_agency_erp_binding_for_request_host')) {
    /**
     * @return array{host:string,port:int,user:string,pass:string,db:string,agency_id:int}|null
     */
    function rateb_agency_erp_binding_for_request_host(): ?array
    {
        if (PHP_SAPI === 'cli') {
            return null;
        }
        $resolver = __DIR__ . DIRECTORY_SEPARATOR . 'erp_agency_resolver.php';
        if (is_file($resolver)) {
            require_once $resolver;
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $host = rateb_normalize_http_host($host);
        if ($host === '' || (function_exists('rateb_erp_is_main_platform_host') && rateb_erp_is_main_platform_host($host))) {
            return null;
        }

        return rateb_agency_erp_binding_for_host($host);
    }
}

if (!function_exists('rateb_lookup_agency_by_host')) {
    /**
     * @return array<string, mixed>|null
     */
    function rateb_lookup_agency_by_host(string $host): ?array
    {
        return rateb_lookup_agency_row_by_host($host, true);
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

if (!function_exists('rateb_list_control_agencies')) {
    /**
     * All Control Panel agencies (وكالة = الشركة). Used by platform ERP companies list.
     *
     * @return list<array<string, mixed>>
     */
    function rateb_list_control_agencies(bool $activeOnly = false): array
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
        $hasSusp = false;
        $suspChk = @$conn->query("SHOW COLUMNS FROM control_agencies LIKE 'is_suspended'");
        if ($suspChk && $suspChk->num_rows > 0) {
            $hasSusp = true;
        }
        $cols = rateb_agency_lookup_select_columns($conn);
        $sql = "SELECT {$cols} FROM control_agencies WHERE 1=1";
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
            if ($hasSusp) {
                $sql .= ' AND COALESCE(is_suspended, 0) = 0';
            }
        }
        $sql .= ' ORDER BY name ASC, id ASC';
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

if (!function_exists('rateb_deactivate_control_agency')) {
    /** Soft-remove agency so it no longer mirrors into ERP companies list. */
    function rateb_deactivate_control_agency(int $agencyId): bool
    {
        if ($agencyId < 1) {
            return false;
        }
        $conn = rateb_agency_lookup_connection();
        if (!$conn) {
            return false;
        }
        $chk = @$conn->query("SHOW TABLES LIKE 'control_agencies'");
        if (!$chk || $chk->num_rows === 0) {
            $conn->close();

            return false;
        }
        $hasSusp = false;
        $suspChk = @$conn->query("SHOW COLUMNS FROM control_agencies LIKE 'is_suspended'");
        if ($suspChk && $suspChk->num_rows > 0) {
            $hasSusp = true;
        }
        $hasErpCo = false;
        $erpChk = @$conn->query("SHOW COLUMNS FROM control_agencies LIKE 'erp_company_id'");
        if ($erpChk && $erpChk->num_rows > 0) {
            $hasErpCo = true;
        }
        if ($hasSusp && $hasErpCo) {
            $stmt = $conn->prepare('UPDATE control_agencies SET is_active = 0, is_suspended = 1, erp_company_id = NULL WHERE id = ?');
        } elseif ($hasErpCo) {
            $stmt = $conn->prepare('UPDATE control_agencies SET is_active = 0, erp_company_id = NULL WHERE id = ?');
        } elseif ($hasSusp) {
            $stmt = $conn->prepare('UPDATE control_agencies SET is_active = 0, is_suspended = 1 WHERE id = ?');
        } else {
            $stmt = $conn->prepare('UPDATE control_agencies SET is_active = 0 WHERE id = ?');
        }
        if (!$stmt) {
            $conn->close();

            return false;
        }
        $stmt->bind_param('i', $agencyId);
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();

        return (bool) $ok;
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
