<?php
declare(strict_types=1);

if (!defined('RATEB_ROOT')) {
    $root = realpath(dirname(__DIR__));
    define('RATEB_ROOT', str_replace('\\', '/', $root !== false ? $root : dirname(__DIR__)));
}

define('RATEB_VIEWS_PATH', RATEB_ROOT . DIRECTORY_SEPARATOR . 'views');
define('RATEB_STORAGE_PATH', RATEB_ROOT . '/storage');

define('RATEB_APP_NAME', 'RTAB');
define('RATEB_APP_VERSION', '1.0.1');
define('RATEB_ASSET_BUILD', '20260903-addon-lock-status-v1');

if (!function_exists('rateb_erp_deployment_mode')) {
    /** @return 'dedicated'|'saas' */
    function rateb_erp_deployment_mode(): string
    {
        if (defined('RATEB_ERP_DEPLOYMENT_MODE')) {
            $mode = strtolower(trim((string) RATEB_ERP_DEPLOYMENT_MODE));

            return $mode === 'dedicated' ? 'dedicated' : 'saas';
        }
        if (PHP_SAPI !== 'cli' && function_exists('rateb_agency_erp_binding_for_request_host')) {
            $lookupFile = dirname(RATEB_ROOT, 1) . '/config/env/agency_lookup.php';
            if (is_file($lookupFile)) {
                require_once $lookupFile;
                if (rateb_agency_erp_binding_for_request_host() !== null) {
                    return 'dedicated';
                }
            }
        }
        if (defined('RATEB_ERP_AGENCY_RESOLVED') && RATEB_ERP_AGENCY_RESOLVED) {
            return 'dedicated';
        }

        return 'saas';
    }
}

if (!function_exists('rateb_erp_is_dedicated_deployment')) {
    function rateb_erp_is_dedicated_deployment(): bool
    {
        return rateb_erp_deployment_mode() === 'dedicated';
    }
}

if (!function_exists('rateb_is_agency_erp_host')) {
    /** Agency subdomain with its own ERP DB (e.g. test.rateb.sa) — not rateb.sa SaaS. */
    function rateb_is_agency_erp_host(): bool
    {
        if (rateb_erp_is_dedicated_deployment()) {
            return true;
        }
        if (defined('RATEB_ERP_AGENCY_RESOLVED') && RATEB_ERP_AGENCY_RESOLVED) {
            return true;
        }
        if (PHP_SAPI === 'cli') {
            return false;
        }
        $lookupFile = dirname(RATEB_ROOT, 1) . '/config/env/agency_lookup.php';
        if (!is_file($lookupFile)) {
            return false;
        }
        require_once $lookupFile;
        if (!function_exists('rateb_agency_erp_binding_for_request_host')) {
            return false;
        }
        $binding = rateb_agency_erp_binding_for_request_host();

        return is_array($binding) && trim((string) ($binding['db'] ?? '')) !== '';
    }
}

if (!function_exists('rateb_company_access_routes_enabled')) {
    /** Tenant access-control under admin/ops — agency, dedicated, and main SaaS company admins. */
    function rateb_company_access_routes_enabled(): bool
    {
        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            return true;
        }
        if (function_exists('rateb_erp_is_dedicated_deployment') && rateb_erp_is_dedicated_deployment()) {
            return true;
        }

        return function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host();
    }
}

if (!function_exists('rateb_tenant_permission_catalog_locked')) {
    /** Global permission catalog is platform-only; tenants use roles + matrix. */
    function rateb_tenant_permission_catalog_locked(): bool
    {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return false;
        }
        if (!function_exists('rateb_company_access_routes_enabled') || !rateb_company_access_routes_enabled()) {
            return false;
        }

        return \Rateb\App\Services\AuthorizationService::resolveMatrixCompanyId() > 0;
    }
}

if (!function_exists('rateb_ensure_erp_branch_schema')) {
    /** Idempotent branch_id catchup — once per PHP request, once per session/day. Live DB only on web. */
    function rateb_ensure_erp_branch_schema(): void
    {
        static $ran = false;
        if ($ran || !\Rateb\App\Core\Auth::check()) {
            return;
        }
        $ran = true;
        $day = date('Y-m-d');
        if (\Rateb\App\Core\SessionManager::get('rateb_branch_schema_ok') === $day) {
            return;
        }
        // Mark early so concurrent tabs do not all run SHOW COLUMNS / ALTERs.
        \Rateb\App\Core\SessionManager::set('rateb_branch_schema_ok', $day);
        try {
            (new \Rateb\App\Services\MigrationService())->repairBranchOpsSchemaIfNeeded();
        } catch (\Throwable $e) {
            error_log('rateb_ensure_erp_branch_schema: ' . $e->getMessage());
        }
    }
}

if (!function_exists('rateb_ensure_agency_schema_once')) {
    /** Run pending migrations on agency ERP hosts (once per session per day). */
    function rateb_ensure_agency_schema_once(): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;
        if (!rateb_is_agency_erp_host()) {
            return;
        }
        if (!\Rateb\App\Core\Auth::check()) {
            return;
        }
        if (\Rateb\App\Core\SessionManager::get('rateb_agency_schema_synced') === date('Y-m-d')) {
            return;
        }
        try {
            $migration = new \Rateb\App\Services\MigrationService();
            // Never runAll() on a web request — pending migrations can block /admin for minutes.
            if (PHP_SAPI === 'cli' && $migration->hasPending()) {
                $migration->runAll();
            } else {
                $migration->repairMarketingPlansCanonicalIfNeeded();
            }
            \Rateb\App\Core\SessionManager::set('rateb_agency_schema_synced', date('Y-m-d'));
        } catch (\Throwable $e) {
            error_log('rateb_ensure_agency_schema_once: ' . $e->getMessage());
        }
        rateb_ensure_agency_access_permissions_once();
    }
}

if (!function_exists('rateb_ensure_agency_access_permissions_once')) {
    /** Bootstrap tenant roles + access.manage for company admins (agency, dedicated, main SaaS). */
    function rateb_ensure_agency_access_permissions_once(): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;
        $agency = function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host();
        $saasTenant = function_exists('rateb_is_platform_oversight_host')
            && rateb_is_platform_oversight_host()
            && !$agency;
        if (!$agency && !$saasTenant) {
            return;
        }
        if (!\Rateb\App\Core\Auth::check()) {
            return;
        }
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return;
        }
        $sessionKey = $agency ? 'rateb_agency_access_perms_synced_v3' : 'rateb_saas_tenant_access_perms_synced_v3';
        if (\Rateb\App\Core\SessionManager::get($sessionKey) === 1) {
            return;
        }
        try {
            $authz = new \Rateb\App\Services\AuthorizationService();
            $authz->ensureSuggestedRoles();
            $companyId = \Rateb\App\Services\AuthorizationService::resolveMatrixCompanyId();
            if ($companyId > 0) {
                $authz->ensureCompanyRoles($companyId);
                $authz->refreshTenantSelfServicePermissions($companyId);
            }
            if ($agency) {
                $authz->ensureAgencyCompanyAdminRole((int) (\Rateb\App\Core\SessionManager::get('rateb_user_id') ?? 0));
            }
            \Rateb\App\Core\SessionManager::set($sessionKey, 1);
        } catch (\Throwable $e) {
            error_log('rateb_ensure_agency_access_permissions_once: ' . $e->getMessage());
        }
    }
}

if (!function_exists('rateb_is_agency_company_ops_admin')) {
    /** Primary company admin — agency, dedicated, or main SaaS tenant (full-access / access-manager). */
    function rateb_is_agency_company_ops_admin(?int $userId = null): bool
    {
        static $cache = [];
        if (!function_exists('rateb_company_access_routes_enabled') || !rateb_company_access_routes_enabled()) {
            return false;
        }
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return true;
        }
        $userId = $userId ?? (int) ($_SESSION['rateb_user_id'] ?? 0);
        if ($userId < 1) {
            return false;
        }
        if (array_key_exists($userId, $cache)) {
            return $cache[$userId];
        }
        try {
            $user = (new \Rateb\App\Models\User())->find($userId);
            if (!$user || !empty($user['is_super_admin'])) {
                return $cache[$userId] = false;
            }
            $companyId = (int) ($user['company_id'] ?? 0);
            if ($companyId < 1) {
                $email = strtolower(trim((string) ($user['email'] ?? '')));
                $name = strtolower(trim((string) ($user['name'] ?? '')));
                if ($email === 'admin@local' || $name === 'admin' || str_starts_with($email, 'admin+')) {
                    return $cache[$userId] = true;
                }

                return $cache[$userId] = false;
            }
            $row = (new \Rateb\App\Models\Role())->queryOne(
                "SELECT 1 FROM rateb_user_roles ur
                 INNER JOIN rateb_roles r ON r.id = ur.role_id AND r.company_id = :cid
                 WHERE ur.user_id = :uid
                   AND r.slug IN ('company-full-access', 'access-manager', 'hq_admin')
                 LIMIT 1",
                ['uid' => $userId, 'cid' => $companyId]
            );
            if ($row !== null) {
                return $cache[$userId] = true;
            }
        } catch (\Throwable $e) {
            error_log('rateb_is_agency_company_ops_admin: ' . $e->getMessage());
        }

        return $cache[$userId] = false;
    }
}

if (!function_exists('rateb_agency_access_nav_permissions')) {
    /** @return list<string> */
    function rateb_agency_access_nav_permissions(): array
    {
        return ['access.manage', 'settings.manage', 'dashboard.view'];
    }
}

if (!function_exists('rateb_company_branches_nav_enabled')) {
    /**
     * Show الفروع in sidebar on agency / dedicated ERP hosts.
     * Revoke branches.view in the role matrix to hide the whole section.
     */
    function rateb_company_branches_nav_enabled(): bool
    {
        if (!function_exists('rateb_nav_can')) {
            return true;
        }
        $agencyScoped = (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host())
            || (function_exists('rateb_erp_is_dedicated_deployment') && rateb_erp_is_dedicated_deployment());
        if (!$agencyScoped) {
            return true;
        }

        return rateb_nav_can('branches.view', 'branches');
    }
}

if (!function_exists('rateb_is_branch_permission_slug')) {
    /** Branch module slugs gated by branches.view on agency / dedicated hosts. */
    function rateb_is_branch_permission_slug(string $slug): bool
    {
        $slug = trim($slug);
        if ($slug === '' || $slug === 'branches.view') {
            return false;
        }
        static $branchSlugs = null;
        if ($branchSlugs === null) {
            $file = RATEB_ROOT . '/config/permissions-system.php';
            $cfg = is_file($file) ? require $file : [];
            $branchSlugs = array_flip((array) ($cfg['branch_permission_slugs'] ?? []));
        }
        if (isset($branchSlugs[$slug])) {
            return true;
        }

        return str_starts_with($slug, 'branch.');
    }
}

if (!function_exists('rateb_resource_requires_branches_view')) {
    function rateb_resource_requires_branches_view(string $resource): bool
    {
        $resource = trim($resource);
        if ($resource === '' || $resource === 'branches') {
            return $resource === 'branches';
        }
        if (str_starts_with($resource, 'branch-')) {
            return true;
        }
        if (!function_exists('rateb_entity_perms')) {
            return false;
        }

        return (rateb_entity_perms($resource)['module'] ?? '') === 'branches';
    }
}

if (!function_exists('rateb_is_platform_oversight_host')) {
    /** Platform SaaS admin (companies, billing, CMS, agency push) — rateb.sa only, not agency ERP hosts. */
    function rateb_is_platform_oversight_host(): bool
    {
        // Branch Appliance local shell: same platform admin UX as rateb.sa (SQLite offline).
        $runtime = strtolower(trim((string) (getenv('RATEB_RUNTIME') ?: ($_ENV['RATEB_RUNTIME'] ?? ''))));
        if ($runtime === 'branch' && function_exists('rateb_is_local_appliance_host') && rateb_is_local_appliance_host()) {
            return true;
        }
        if (rateb_erp_is_dedicated_deployment()) {
            return false;
        }
        $resolver = dirname(RATEB_ROOT) . '/config/env/erp_agency_resolver.php';
        if (is_file($resolver)) {
            require_once $resolver;
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (function_exists('rateb_erp_is_main_platform_host')) {
            return rateb_erp_is_main_platform_host($host);
        }

        return !rateb_erp_is_dedicated_deployment();
    }
}

if (!function_exists('rateb_is_non_document_request')) {
    /**
     * Soft-nav / prefetch / offline warm / XHR — must not set flash banners or
     * issue cross-origin redirects (CORS on agency hosts like admin.rateb.sa).
     */
    function rateb_is_non_document_request(): bool
    {
        $mode = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '')));
        if ($mode === 'cors' || $mode === 'no-cors' || $mode === 'same-origin') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_RATEB_PREFETCH'])
            || !empty($_SERVER['HTTP_X_RATEB_SHELL_WARM'])
            || !empty($_SERVER['HTTP_X_RATEB_NAV_SWAP'])
            || !empty($_SERVER['HTTP_X_RATEB_CONNECTIVITY'])) {
            return true;
        }
        if (strcasecmp((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0) {
            return true;
        }
        $purpose = strtolower(trim((string) ($_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? '')));
        if ($purpose === 'prefetch' || str_contains($purpose, 'prefetch')) {
            return true;
        }
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        if ($accept !== '' && str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('rateb_flash_access_denied')) {
    /** Session access_denied banner — skipped for soft-nav/prefetch (orphan flash). */
    function rateb_flash_access_denied(): void
    {
        if (function_exists('rateb_is_non_document_request') && rateb_is_non_document_request()) {
            return;
        }
        \Rateb\App\Core\SessionManager::flash('error', __('access_denied'));
    }
}

if (!function_exists('rateb_wants_html_error_page')) {
    /** Browser / SW HTML shell navigations — flash + redirect, never raw JSON. */
    function rateb_wants_html_error_page(): bool
    {
        $mode = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '')));
        if ($mode === 'navigate') {
            return true;
        }
        $dest = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '')));
        if ($dest === 'document' || $dest === 'iframe') {
            return true;
        }
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        if ($accept !== '' && str_contains($accept, 'text/html')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('rateb_prefers_json_error_response')) {
    function rateb_prefers_json_error_response(): bool
    {
        if (function_exists('rateb_wants_html_error_page') && rateb_wants_html_error_page()) {
            return false;
        }

        return function_exists('rateb_is_non_document_request') && rateb_is_non_document_request();
    }
}

if (!function_exists('rateb_sync_platform_company_create_maintenance')) {
    /**
     * Super-admin maintenance unlock for creating companies on the platform host.
     * Toggle: /admin/companies?company_create_maintenance=1 (or =0).
     * Or set RATEB_ALLOW_PLATFORM_COMPANY_CREATE=1 in env.
     */
    function rateb_sync_platform_company_create_maintenance(): void
    {
        if (!function_exists('rateb_is_platform_oversight_host') || !rateb_is_platform_oversight_host()) {
            return;
        }
        if (!function_exists('rateb_is_super_admin') || !rateb_is_super_admin()) {
            return;
        }
        if (!isset($_GET['company_create_maintenance'])) {
            return;
        }
        $raw = strtolower(trim((string) $_GET['company_create_maintenance']));
        if (in_array($raw, ['1', 'true', 'yes', 'on'], true)) {
            \Rateb\App\Core\SessionManager::set('rateb_company_create_maintenance', 1);
        } else {
            \Rateb\App\Core\SessionManager::forget('rateb_company_create_maintenance');
        }
    }
}

if (!function_exists('rateb_platform_company_create_maintenance_enabled')) {
    function rateb_platform_company_create_maintenance_enabled(): bool
    {
        $env = getenv('RATEB_ALLOW_PLATFORM_COMPANY_CREATE');
        if ($env === false || $env === '') {
            $env = $_ENV['RATEB_ALLOW_PLATFORM_COMPANY_CREATE'] ?? '';
        }
        if (in_array(strtolower(trim((string) $env)), ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        return (int) \Rateb\App\Core\SessionManager::get('rateb_company_create_maintenance', 0) === 1;
    }
}

if (!function_exists('rateb_platform_company_create_allowed')) {
    /**
     * Create is allowed; dedicated ERP still blocks a 2nd company.
     * Agency↔company link is done on the company form (linked_agency_id).
     */
    function rateb_platform_company_create_allowed(): bool
    {
        if (class_exists(\Rateb\App\Services\DedicatedTenantPolicy::class)) {
            return \Rateb\App\Services\DedicatedTenantPolicy::canCreateCompany();
        }

        return true;
    }
}

if (!function_exists('rateb_assert_platform_company_create_allowed')) {
    function rateb_assert_platform_company_create_allowed(): void
    {
        if (rateb_platform_company_create_allowed()) {
            return;
        }
        throw new \RuntimeException(__('erp_dedicated_single_company'));
    }
}

if (!function_exists('rateb_platform_oversight_public_url')) {
    function rateb_platform_oversight_public_url(string $route = 'admin'): string
    {
        $route = ltrim($route, '/');
        $fromEnv = getenv('RATEB_PLATFORM_ERP_PUBLIC_BASE');
        if ($fromEnv !== false && trim((string) $fromEnv) !== '') {
            return rtrim((string) $fromEnv, '/') . ($route !== '' ? '/' . $route : '');
        }
        if (function_exists('rateb_url') && rateb_is_platform_oversight_host()) {
            return rateb_url($route !== '' ? $route : 'admin');
        }

        return 'https://rateb.sa/rateb-erp/public' . ($route !== '' ? '/' . $route : '');
    }
}

if (!function_exists('rateb_erp_login_bypass_enabled')) {
    /**
     * Temporary open access — auto-login on rateb.sa ERP (no login page).
     * Enable: define('RATEB_ERP_LOGIN_BYPASS', true) in config/env/rateb_sa.php
     * or RATEB_ERP_LOGIN_BYPASS=1 in project-root .env. Disable when restoring login.
     */
    function rateb_erp_login_bypass_enabled(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }
        $on = false;
        if (defined('RATEB_ERP_LOGIN_BYPASS')) {
            $on = (bool) RATEB_ERP_LOGIN_BYPASS;
        } else {
            $env = getenv('RATEB_ERP_LOGIN_BYPASS');
            $on = $env !== false
                && in_array(strtolower(trim((string) $env)), ['1', 'true', 'yes', 'on'], true);
        }
        if (!$on) {
            return false;
        }

        return function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host();
    }
}

if (!function_exists('rateb_platform_branch_manage_enabled')) {
    /** Platform super-admin on rateb.sa — branch CRUD via Control Panel. */
    function rateb_platform_branch_manage_enabled(): bool
    {
        return function_exists('rateb_is_super_admin')
            && rateb_is_super_admin()
            && function_exists('rateb_is_platform_oversight_host')
            && rateb_is_platform_oversight_host();
    }
}

if (!function_exists('rateb_platform_catalog_nav_enabled')) {
    /** Platform catalog admin link — rateb.sa super-admin only, never agency ERP hosts. */
    function rateb_platform_catalog_nav_enabled(): bool
    {
        if (!function_exists('rateb_is_super_admin') || !rateb_is_super_admin()) {
            return false;
        }

        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            return false;
        }

        if (PHP_SAPI !== 'cli') {
            $host = strtolower(trim(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]));
            if ($host !== '' && str_ends_with($host, '.rateb.sa') && !in_array($host, ['rateb.sa', 'www.rateb.sa'], true)) {
                return false;
            }
        }

        return function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host();
    }
}

if (!function_exists('rateb_platform_catalog_admin_url')) {
    /** Canonical catalog admin URL (rateb.sa platform operators / Control Panel). */
    function rateb_platform_catalog_admin_url(): string
    {
        if (function_exists('rateb_platform_oversight_public_url')) {
            $erpBase = rateb_platform_oversight_public_url('');
            if ($erpBase !== '') {
                $parsed = parse_url($erpBase);
                if (is_array($parsed) && !empty($parsed['scheme']) && !empty($parsed['host'])) {
                    return $parsed['scheme'] . '://' . $parsed['host'] . '/rateb-platform-catalog/admin';
                }
            }
        }

        return 'https://rateb.sa/rateb-platform-catalog/admin';
    }
}

if (!function_exists('rateb_platform_catalog_entry_url')) {
    /** Open catalog via ERP SSO handoff (cookie path safe). */
    function rateb_platform_catalog_entry_url(): string
    {
        $return = rateb_platform_catalog_admin_url();

        return rateb_url('platform-catalog/sso') . '?return=' . rawurlencode($return);
    }
}

if (!function_exists('rateb_platform_company_branches_url')) {
    /** ERP-native branch management (platform super-admin on rateb.sa). */
    function rateb_platform_company_branches_url(int $companyId = 0): string
    {
        if ($companyId > 0) {
            return rateb_url('admin/companies/' . $companyId . '/branches');
        }

        return rateb_url('admin/companies/branches');
    }
}

if (!function_exists('rateb_control_panel_agencies_url')) {
    /** Control Panel → إدارة الوكالات (مصدر الشركات في المنصة). */
    function rateb_control_panel_agencies_url(int $countryId = 0): string
    {
        $base = defined('SITE_URL') && trim((string) SITE_URL) !== ''
            ? rtrim((string) SITE_URL, '/')
            : 'https://rateb.sa';
        $url = $base . '/control-panel/pages/control/agencies?control=1';
        if ($countryId > 0) {
            $url .= '&country_id=' . $countryId;
        }

        return $url;
    }
}

if (!function_exists('rateb_rateb_pro_url')) {
    /**
     * Canonical RATEB Pro base URL for opening agency sites.
     * Defaults to RATEB_PRO_URL constant, then SITE_URL, then rateb.sa.
     */
    function rateb_rateb_pro_url(): string
    {
        if (defined('RATEB_PRO_URL') && trim((string) RATEB_PRO_URL) !== '') {
            return rtrim((string) RATEB_PRO_URL, '/');
        }
        if (defined('SITE_URL') && trim((string) SITE_URL) !== '') {
            return rtrim((string) SITE_URL, '/');
        }
        return 'https://rateb.sa';
    }
}

if (!function_exists('rateb_rateb_pro_url_for_site_url')) {
    /**
     * Rewrite a stored agency site_url host to the canonical RATEB Pro host.
     * Keeps the path (usually /{country_slug}) so links open the correct agency.
     */
    function rateb_rateb_pro_url_for_site_url(string $siteUrl): string
    {
        $siteUrl = trim($siteUrl);
        if ($siteUrl === '' || !preg_match('#^https?://#i', $siteUrl)) {
            return $siteUrl;
        }
        $proBase = rateb_rateb_pro_url();
        if ($proBase === '') {
            return $siteUrl;
        }
        $siteParsed = parse_url($siteUrl);
        $proParsed = parse_url($proBase);
        if (empty($siteParsed['host']) || empty($proParsed['host'])) {
            return $siteUrl;
        }
        if (strtolower((string) $siteParsed['host']) === strtolower((string) $proParsed['host'])) {
            return $siteUrl;
        }
        $scheme = !empty($proParsed['scheme']) ? $proParsed['scheme'] : ($siteParsed['scheme'] ?? 'https');
        $port = !empty($proParsed['port']) ? ':' . (int) $proParsed['port'] : '';
        $path = (string) ($siteParsed['path'] ?? '/');
        return $scheme . '://' . $proParsed['host'] . $port . $path;
    }
}

if (!function_exists('rateb_control_panel_branch_manage_url')) {
    /** Control Panel → الشركات والفروع (optional company / agency focus). */
    function rateb_control_panel_branch_manage_url(int $companyId = 0, int $agencyId = 0): string
    {
        $base = defined('SITE_URL') && trim((string) SITE_URL) !== ''
            ? rtrim((string) SITE_URL, '/')
            : 'https://rateb.sa';
        $url = $base . '/control-panel/pages/control/rateb-erp-branches?control=1';

        if ($agencyId > 0) {
            $url .= '&agency_id=' . $agencyId;
        } else {
            $url .= '&platform=1';
        }
        if ($companyId > 0) {
            $url .= '&company_id=' . $companyId . '#company-branches-' . $companyId;
        }

        return $url;
    }
}

if (!function_exists('rateb_erp_login_rate_policy')) {
    /**
     * Login throttling — agency hosts skip shared-IP blocking (many users, one office IP).
     *
     * @return array{email_max:int,email_decay:int,ip_max:int,ip_decay:int,ip_enabled:bool}
     */
    function rateb_erp_login_rate_policy(): array
    {
        $agency = function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host();
        if ($agency) {
            return [
                'email_max' => 20,
                'email_decay' => 300,
                'ip_max' => 20,
                'ip_decay' => 900,
                'ip_enabled' => false,
            ];
        }

        return [
            'email_max' => 5,
            'email_decay' => 300,
            'ip_max' => 20,
            'ip_decay' => 900,
            'ip_enabled' => true,
        ];
    }
}

if (!function_exists('rateb_is_production')) {
    function rateb_is_production(): bool
    {
        $env = strtolower(trim((string) (getenv('RATEB_ENV') ?: getenv('APP_ENV') ?: '')));
        if ($env === 'production' || $env === 'prod') {
            return true;
        }
        if ($env !== '' && !in_array($env, ['production', 'prod'], true)) {
            return false;
        }
        $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        $suffix = '.rateb.sa';
        return $host === 'rateb.sa'
            || ($host !== '' && strlen($host) >= strlen($suffix) && substr($host, -strlen($suffix)) === $suffix);
    }
}

if (!function_exists('rateb_hr_mobile_dev_config')) {
    /**
     * HR Mobile console config (system setting + optional URL env). Never used for HR business rules.
     *
     * @return array{flag_enabled:bool,enabled:bool,web_url:string,api_base:string,build:string,environment:string,app_debug:bool}
     */
    function rateb_hr_mobile_dev_config(): array
    {
        static $cached = null;
        static $bust = 0;
        $token = $GLOBALS['__rateb_hr_mobile_cfg_bust'] ?? 0;
        if ($cached !== null && $bust === $token) {
            return $cached;
        }
        $bust = $token;
        $file = RATEB_ROOT . '/config/hr-mobile-dev.php';
        if (!is_file($file)) {
            $cached = [
                'flag_enabled' => false,
                'enabled' => false,
                'web_url' => '',
                'api_base' => '',
                'build' => 'dev',
                'environment' => 'production',
                'app_debug' => false,
            ];
            return $cached;
        }
        /** @var callable(): array $loader */
        $loader = require $file;
        $cached = is_callable($loader) ? $loader() : [
            'flag_enabled' => false,
            'enabled' => false,
            'web_url' => '',
            'api_base' => '',
            'build' => 'dev',
            'environment' => 'production',
            'app_debug' => false,
        ];
        return $cached;
    }
}

if (!function_exists('rateb_hr_mobile_dev_config_clear_cache')) {
    function rateb_hr_mobile_dev_config_clear_cache(): void
    {
        $GLOBALS['__rateb_hr_mobile_cfg_bust'] = (int) ($GLOBALS['__rateb_hr_mobile_cfg_bust'] ?? 0) + 1;
        $GLOBALS['__rateb_hr_mobile_flag_bust'] = (int) ($GLOBALS['__rateb_hr_mobile_flag_bust'] ?? 0) + 1;
    }
}

if (!function_exists('rateb_hr_mobile_console_flag_enabled')) {
    /**
     * Platform HR Mobile Console launcher flag (default false).
     * Primary: rateb_system_settings.hr_mobile_console_enabled (Admin → Settings → Features).
     * Tenant enablement/branding is separate: rateb_mobile_app_configs (Mobile Apps Management).
     * Legacy OS/FPM env only when DB setting row is missing.
     */
    function rateb_hr_mobile_console_flag_enabled(): bool
    {
        static $cached = null;
        static $bust = 0;
        $token = $GLOBALS['__rateb_hr_mobile_flag_bust'] ?? 0;
        if ($cached !== null && $bust === $token) {
            return $cached;
        }
        $bust = $token;

        $raw = null;
        try {
            if (class_exists(\Rateb\App\Models\SystemSetting::class)) {
                $raw = (new \Rateb\App\Models\SystemSetting())->get('hr_mobile_console_enabled');
            }
        } catch (\Throwable $e) {
            $raw = null;
        }

        if ($raw !== null && $raw !== '') {
            $normalized = strtolower(trim((string) $raw));
            $cached = in_array($normalized, ['1', 'true', 'yes', 'on'], true);
            return $cached;
        }

        // Legacy: OS/FPM env only when DB row is missing (pre-migration).
        $legacy = getenv('HR_MOBILE_CONSOLE_ENABLED');
        if ($legacy === false || $legacy === '') {
            $legacy = getenv('RATEB_HR_MOBILE_CONSOLE_ENABLED');
        }
        if ($legacy !== false && $legacy !== '') {
            $cached = (bool) filter_var((string) $legacy, FILTER_VALIDATE_BOOLEAN);
            return $cached;
        }

        $cached = false;
        return $cached;
    }
}

if (!function_exists('rateb_hr_mobile_console_permission')) {
    /**
     * Existing permission used as production-safe console gate.
     * Catalog has no system.development without a new RBAC migration — settings.manage is the admin-tools equivalent.
     */
    function rateb_hr_mobile_console_permission(): string
    {
        return 'settings.manage';
    }
}

if (!function_exists('rateb_hr_mobile_console_accessible')) {
    /**
     * Production-safe access: feature flag + Super Admin + settings.manage.
     * Does not use APP_ENV / APP_DEBUG / rateb_is_production() to hide.
     */
    function rateb_hr_mobile_console_accessible(): bool
    {
        if (!rateb_hr_mobile_console_flag_enabled()) {
            return false;
        }
        if (!function_exists('rateb_is_super_admin') || !rateb_is_super_admin()) {
            return false;
        }
        $perm = rateb_hr_mobile_console_permission();
        if (function_exists('rateb_can') && rateb_can($perm)) {
            return true;
        }
        if (function_exists('rateb_nav_can') && rateb_nav_can($perm)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('rateb_hr_mobile_dev_console_enabled')) {
    /** @deprecated Prefer rateb_hr_mobile_console_accessible() — kept as alias for call sites. */
    function rateb_hr_mobile_dev_console_enabled(): bool
    {
        return rateb_hr_mobile_console_accessible();
    }
}

if (!function_exists('rateb_email_diagnostics_flag_enabled')) {
    /**
     * Temporary email diagnostics page flag (default false).
     * Primary: rateb_system_settings.email_diagnostics_enabled (Admin → Settings → Features).
     * Fallback: env var EMAIL_DIAGNOSTICS_ENABLED.
     */
    function rateb_email_diagnostics_flag_enabled(): bool
    {
        static $cached = null;
        static $bust = 0;
        $token = $GLOBALS['__rateb_email_diagnostics_flag_bust'] ?? 0;
        if ($cached !== null && $bust === $token) {
            return $cached;
        }
        $bust = $token;

        $raw = null;
        try {
            if (class_exists(\Rateb\App\Models\SystemSetting::class)) {
                $raw = (new \Rateb\App\Models\SystemSetting())->get('email_diagnostics_enabled');
            }
        } catch (\Throwable $e) {
            $raw = null;
        }

        if ($raw !== null && $raw !== '') {
            $normalized = strtolower(trim((string) $raw));
            $cached = in_array($normalized, ['1', 'true', 'yes', 'on'], true);
            return $cached;
        }

        $env = getenv('EMAIL_DIAGNOSTICS_ENABLED');
        if ($env !== false && $env !== '') {
            $cached = (bool) filter_var((string) $env, FILTER_VALIDATE_BOOLEAN);
            return $cached;
        }

        $cached = false;
        return $cached;
    }
}

if (!function_exists('rateb_email_diagnostics_accessible')) {
    /**
     * Production-safe access: feature flag + Super Admin + settings.manage.
     * Does not use APP_ENV / APP_DEBUG / rateb_is_production() to hide.
     */
    function rateb_email_diagnostics_accessible(): bool
    {
        if (!rateb_email_diagnostics_flag_enabled()) {
            return false;
        }
        if (!function_exists('rateb_is_super_admin') || !rateb_is_super_admin()) {
            return false;
        }
        $perm = 'settings.manage';
        if (function_exists('rateb_can') && rateb_can($perm)) {
            return true;
        }
        if (function_exists('rateb_nav_can') && rateb_nav_can($perm)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('rateb_is_local_appliance_host')) {
    /** Loopback / Branch Appliance PHP built-in server (not cloud). */
    function rateb_is_local_appliance_host(?string $host = null): bool
    {
        if ($host === null) {
            $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
        }
        $host = strtolower(trim((string) $host));

        return $host === '127.0.0.1'
            || $host === 'localhost'
            || $host === '::1'
            || $host === '0.0.0.0';
    }
}

if (!function_exists('rateb_erp_public_prefix')) {
    /** Marketing/locale URL prefix ('' = domain root on rateb.sa). Override via RATEB_ERP_PUBLIC_PREFIX. */
    function rateb_erp_public_prefix(): string
    {
        static $prefix = null;
        if ($prefix !== null) {
            return $prefix;
        }
        $env = getenv('RATEB_ERP_PUBLIC_PREFIX');
        if ($env !== false && $env !== 'auto') {
            $prefix = rtrim((string) $env, '/');

            return $prefix;
        }
        $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
        if (in_array($host, ['rateb.sa', 'www.rateb.sa'], true)) {
            $prefix = '';

            return $prefix;
        }
        // Branch local serve: document root is public/ → URLs at /assets, /login (not /rateb-erp/public/...).
        if (rateb_is_local_appliance_host($host)) {
            $prefix = '';

            return $prefix;
        }
        $prefix = '/rateb-erp/public';

        return $prefix;
    }
}

if (!function_exists('rateb_erp_app_prefix')) {
    /** App routes (admin, login, api) — stays under /rateb-erp/public when marketing uses domain root. */
    function rateb_erp_app_prefix(): string
    {
        // Local Branch Appliance (php -S -t public): routes and assets at document root.
        if (function_exists('rateb_is_local_appliance_host') && rateb_is_local_appliance_host()) {
            return rateb_erp_public_prefix();
        }
        $prefix = rateb_erp_public_prefix();

        return $prefix === '' ? '/rateb-erp/public' : $prefix;
    }
}

if (!function_exists('rateb_erp_assets_prefix')) {
    function rateb_erp_assets_prefix(): string
    {
        return rateb_erp_app_prefix();
    }
}

if (!function_exists('rateb_erp_path_uses_root_prefix')) {
    function rateb_erp_path_uses_root_prefix(string $path): bool
    {
        $path = ltrim($path, '/');
        if ($path === '' || $path === 'site' || str_starts_with($path, 'site/') || str_starts_with($path, 'locale/')) {
            return true;
        }
        if (str_starts_with($path, 'm/')) {
            return true;
        }

        return false;
    }
}

if (defined('RATEB_CP_ENTRY') && defined('RATEB_CP_APP_URL')) {
    define('RATEB_CP_MODE', true);
    define('RATEB_BASE_URL', (string) RATEB_CP_APP_URL);
} else {
    define('RATEB_CP_MODE', false);
    define('RATEB_BASE_URL', rateb_erp_public_prefix());
}

define('RATEB_DEFAULT_LOCALE', 'ar');
define('RATEB_SUPPORTED_LOCALES', ['en', 'ar']);

if (!function_exists('rateb_site_origin')) {
    function rateb_site_origin(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $httpHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        $host = preg_replace('/:\d+$/', '', $httpHost) ?? '';
        // Branch appliance: keep host:port (PHP -S 127.0.0.1:8099).
        if (rateb_is_local_appliance_host($host) && $httpHost !== '') {
            return $scheme . '://' . $httpHost;
        }
        if ($host !== '' && $host !== 'localhost') {
            if (function_exists('rateb_erp_is_dedicated_deployment') && rateb_erp_is_dedicated_deployment()) {
                return $scheme . '://' . $host;
            }
            if (defined('RATEB_ERP_AGENCY_RESOLVED') && RATEB_ERP_AGENCY_RESOLVED) {
                return $scheme . '://' . $host;
            }
            if (defined('SITE_URL') && (string) SITE_URL !== '') {
                $siteHost = strtolower((string) parse_url((string) SITE_URL, PHP_URL_HOST));
                if ($siteHost === $host) {
                    return rtrim((string) SITE_URL, '/');
                }
            }

            return $scheme . '://' . $host;
        }
        if (defined('SITE_URL') && (string) SITE_URL !== '') {
            return rtrim((string) SITE_URL, '/');
        }

        return $scheme . '://' . ($host !== '' ? $host : 'rateb.sa');
    }
}

if (!function_exists('rateb_phone_digits')) {
    function rateb_phone_digits(string $phone): string
    {
        return preg_replace('/\D+/', '', trim($phone)) ?: '';
    }
}

if (!function_exists('rateb_western_digits')) {
    /** Eastern Arabic / Persian digits → Western 0-9 (form inputs, POST parsing). */
    function rateb_western_digits(string $value): string
    {
        static $from = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        static $to = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($from, $to, $value);
    }
}

if (!function_exists('rateb_phone_display')) {
    function rateb_phone_display(string $phone): string
    {
        $raw = trim($phone);
        if ($raw === '') {
            return '';
        }
        if (str_starts_with($raw, '+')) {
            return $raw;
        }
        $digits = rateb_phone_digits($raw);
        if ($digits === '') {
            return $raw;
        }
        if (str_starts_with($digits, '966') && strlen($digits) >= 12) {
            return '+966 ' . substr($digits, 3);
        }
        if (str_starts_with($digits, '05') && strlen($digits) === 10) {
            return '+966 ' . substr($digits, 1);
        }
        return $raw;
    }
}

if (!function_exists('rateb_phone_markup')) {
  /** Phone link/text with LTR direction for Arabic pages. */
    function rateb_phone_markup(string $phone, bool $asLink = true): string
    {
        $display = rateb_phone_display($phone);
        if ($display === '') {
            return '';
        }
        $digits = rateb_phone_digits($phone);
        $escaped = \Rateb\App\Core\View::escape($display);
        if (!$asLink || $digits === '') {
            return '<span class="rateb-ltr-num" dir="ltr">' . $escaped . '</span>';
        }
        return '<a href="tel:+' . \Rateb\App\Core\View::escape($digits) . '" class="rateb-ltr-num" dir="ltr">' . $escaped . '</a>';
    }
}

if (!function_exists('rateb_public_url')) {
    /** Direct ERP URL — works without Control Panel login. */
    function rateb_public_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        $origin = rateb_site_origin();
        if (rateb_erp_public_prefix() === '' && rateb_erp_path_uses_root_prefix($path)) {
            if ($path === '' || $path === 'site') {
                return $origin . '/';
            }

            return $origin . '/' . $path;
        }
        $prefix = rateb_erp_public_prefix() === '' ? rateb_erp_app_prefix() : rateb_erp_public_prefix();
        if ($path === '') {
            return $origin . $prefix;
        }

        return $origin . $prefix . '/' . $path;
    }
}

if (!function_exists('rateb_asset')) {
    function rateb_asset(string $path): string
    {
        $path = ltrim($path, '/');
        $ver = defined('RATEB_ASSET_BUILD') ? (string) RATEB_ASSET_BUILD : '1';
        $suffix = '?v=' . rawurlencode($ver);

        return rateb_site_origin() . rateb_erp_assets_prefix() . '/assets/' . $path . $suffix;
    }
}

if (!function_exists('rateb_vendor_asset')) {
    /** Self-hosted vendor bundle (Bootstrap, Font Awesome, Chart.js, fonts) — no CDN. */
    function rateb_vendor_asset(string $path): string
    {
        return rateb_asset('vendor/' . ltrim($path, '/'));
    }
}

if (!function_exists('rateb_bootstrap_css')) {
    function rateb_bootstrap_css(): string
    {
        $file = function_exists('rateb_is_rtl') && rateb_is_rtl()
            ? 'bootstrap/5.3.3/bootstrap.rtl.min.css'
            : 'bootstrap/5.3.3/bootstrap.min.css';

        return rateb_vendor_asset($file);
    }
}

if (!function_exists('rateb_bootstrap_js')) {
    function rateb_bootstrap_js(): string
    {
        return rateb_vendor_asset('bootstrap/5.3.3/bootstrap.bundle.min.js');
    }
}

if (!function_exists('rateb_fontawesome_css')) {
    function rateb_fontawesome_css(): string
    {
        /* PERF-P3: shell subset (~5KB) for Admin chrome; full pack optional via rateb_fontawesome_full_css(). */
        return rateb_vendor_asset('fontawesome/6.5.2/css/shell.min.css');
    }
}

if (!function_exists('rateb_fontawesome_full_css')) {
    function rateb_fontawesome_full_css(): string
    {
        return rateb_vendor_asset('fontawesome/6.5.2/css/all.min.css');
    }
}

if (!function_exists('rateb_chartjs')) {
    function rateb_chartjs(string $version = '4.4.3'): string
    {
        $version = in_array($version, ['4.4.1', '4.4.3'], true) ? $version : '4.4.3';

        return rateb_vendor_asset('chartjs/' . $version . '/chart.umd.min.js');
    }
}

if (!function_exists('rateb_tajawal_font_css')) {
    function rateb_tajawal_font_css(): string
    {
        /* PERF-P3: critical weight (400) only; rest via rateb_tajawal_font_rest_css(). */
        return rateb_vendor_asset('fonts/tajawal/tajawal-critical.css');
    }
}

if (!function_exists('rateb_tajawal_font_rest_css')) {
    function rateb_tajawal_font_rest_css(): string
    {
        return rateb_vendor_asset('fonts/tajawal/tajawal-rest.css');
    }
}

if (!function_exists('rateb_pos_fonts_css')) {
    function rateb_pos_fonts_css(): string
    {
        return rateb_vendor_asset('fonts/pos-fonts.css');
    }
}

if (!function_exists('rateb_qrcode_js')) {
    function rateb_qrcode_js(): string
    {
        return rateb_vendor_asset('qrcodejs/qrcode.min.js');
    }
}

if (!function_exists('rateb_html5_qrcode_js')) {
    function rateb_html5_qrcode_js(): string
    {
        return rateb_vendor_asset('html5-qrcode/html5-qrcode.min.js');
    }
}

if (!function_exists('rateb_local_qr_url')) {
    function rateb_local_qr_url(string $payload, int $size = 280, bool $public = true): string
    {
        $route = $public ? 'scan/qr' : 'barcode/qr';

        return rateb_url($route . '?data=' . rawurlencode($payload) . '&size=' . $size);
    }
}

if (!function_exists('rateb_pos_asset')) {
    function rateb_pos_asset(string $path): string
    {
        return rateb_asset('pos/' . ltrim($path, '/'));
    }
}

if (!function_exists('rateb_url')) {
    function rateb_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        if ($path === '') {
            $path = 'admin';
        }
        if (defined('RATEB_CP_MODE') && RATEB_CP_MODE && defined('RATEB_CP_APP_URL')) {
            $base = (string) RATEB_CP_APP_URL;
            $sep = strpos($base, '?') !== false ? '&' : '?';

            return $base . $sep . 'route=' . rawurlencode($path);
        }

        return rateb_public_url($path);
    }
}

if (!function_exists('rateb_csrf_token')) {
    function rateb_csrf_token(): string
    {
        return \Rateb\App\Core\Csrf::token();
    }
}

if (!function_exists('rateb_csrf_field')) {
    /** Hidden CSRF input for Admin forms (BI / payroll / QMS / DMS views). */
    function rateb_csrf_field(string $name = '_csrf'): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?: '_csrf';
        $token = htmlspecialchars(rateb_csrf_token(), ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="' . $name . '" value="' . $token . '">';
    }
}

if (!function_exists('rateb_external_url')) {
    /** Ensure href opens externally (prepend https:// when user omits scheme). */
    function rateb_external_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//#i', $url)) {
            return str_starts_with($url, '//') ? 'https:' . $url : $url;
        }
        if (preg_match('#^(mailto:|tel:)#i', $url)) {
            return $url;
        }
        return 'https://' . ltrim($url, '/');
    }
}

if (!function_exists('rateb_normalize_erp_route')) {
    function rateb_normalize_erp_route(string $route): string
    {
        $route = ltrim($route, '/');
        if (strpos($route, 'public/') === 0) {
            $route = substr($route, strlen('public/'));
        }
        return $route;
    }
}

if (!function_exists('rateb_current_public_path')) {
    /** Path under /public/ for locale switch return (e.g. site, site/faq). */
    function rateb_current_public_path(string $fallback = 'site'): string
    {
        if (class_exists(\Rateb\App\Helpers\Request::class)) {
            $path = ltrim(\Rateb\App\Helpers\Request::resolvePath(), '/');
            if ($path !== '' && strpos($path, 'locale/') !== 0) {
                return $path;
            }
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if (preg_match('#/rateb-erp/public/([^?]+)#', $uri, $m)) {
            $path = ltrim($m[1], '/');
            if ($path !== '' && strpos($path, 'locale/') !== 0) {
                return $path;
            }
        }

        $base = defined('RATEB_BASE_URL') ? rtrim((string) RATEB_BASE_URL, '/') : '/rateb-erp/public';
        foreach ([$base . '/public/', $base . '/', '/rateb-erp/public/'] as $prefix) {
            $pos = strpos($uri, $prefix);
            if ($pos === false) {
                continue;
            }
            $rest = substr($uri, $pos + strlen($prefix));
            $path = ltrim((string) strtok($rest, '?'), '/');
            if ($path !== '' && strpos($path, 'locale/') !== 0) {
                return $path;
            }
        }

        return $fallback;
    }
}

/** Active ERP route (control-panel ?route= or /public/admin/... path). */
if (!function_exists('rateb_current_erp_route')) {
    function rateb_current_erp_route(string $fallback = ''): string
    {
        if (defined('RATEB_CP_ROUTE') && (string) RATEB_CP_ROUTE !== '') {
            return rateb_normalize_erp_route((string) RATEB_CP_ROUTE);
        }
        $route = trim((string) ($_GET['route'] ?? ''), '/');
        if ($route !== '') {
            return rateb_normalize_erp_route($route);
        }
        if (class_exists(\Rateb\App\Helpers\Request::class)) {
            $path = ltrim(\Rateb\App\Helpers\Request::resolvePath(), '/');
            if ($path !== '') {
                return rateb_normalize_erp_route($path);
            }
        }
        $path = rateb_current_public_path($fallback === '' ? '__none__' : $fallback);
        if ($path === $fallback || $path === '__none__') {
            return $fallback;
        }
        return rateb_normalize_erp_route($path);
    }
}

if (!function_exists('rateb_is_ops_route')) {
    /** Company operational paths (tenant data) — includes /admin/ops/* and legacy /admin/{module}. */
    function rateb_ops_route_roots(): array
    {
        static $roots = [
            'purchase-requests', 'purchase-orders', 'rfq', 'quotations',
            'inventory', 'inventory-batches', 'inventory-audits', 'inventory-forecast', 'inventory-codes',
            'warehouses', 'warehouse-transfers', 'stock-movements', 'product-categories',
            'branches',
            'suppliers', 'supplier-comms', 'supplier-evaluations', 'supplier-classifications', 'supplier-kpi',
            'contracts', 'contract-renewals', 'tenders', 'assets',
            'asset-maintenance', 'asset-assignments', 'asset-depreciation',
            'medical-devices', 'device-maintenance', 'device-spare-parts', 'device-warranty',
            'accounting', 'chart-of-accounts', 'journal-entries', 'cash-vouchers', 'fiscal-periods',
            'cost-centers', 'bank-accounts', 'documents', 'workflows', 'notifications', 'profile', 'reports',
            'hr', 'pos', 'recruitment',
        ];
        return $roots;
    }

    function rateb_is_ops_route(?string $route = null): bool
    {
        $route = rateb_normalize_erp_route($route ?? rateb_current_erp_route());
        if ($route === 'admin/ops' || strpos($route, 'admin/ops/') === 0) {
            return true;
        }
        if (strpos($route, 'admin/') !== 0) {
            return false;
        }
        $sub = substr($route, strlen('admin/'));
        $root = explode('/', $sub)[0];
        return in_array($root, rateb_ops_route_roots(), true);
    }
}

if (!function_exists('rateb_is_users_accounts_route')) {
    /** Users list/create/edit under admin or admin/ops. */
    function rateb_is_users_accounts_route(?string $route = null): bool
    {
        $route = function_exists('rateb_normalize_erp_route')
            ? rateb_normalize_erp_route($route ?? rateb_current_erp_route())
            : (string) ($route ?? '');
        if ($route !== '' && (bool) preg_match('#(?:^|/)(?:admin/ops/)?users(?:/|$)#', $route)) {
            return true;
        }
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');

        return (bool) preg_match('#/(?:admin/ops/)?users(?:/|$)#', $path);
    }
}

if (!function_exists('rateb_is_platform_accounts_ui')) {
    /**
     * Platform SA / platform staff account screens — hide ops company picker.
     * Triggered by ?scope=staff|platform or ?for=staff|platform on users routes.
     */
    function rateb_is_platform_accounts_ui(?string $route = null): bool
    {
        if (!rateb_is_users_accounts_route($route)) {
            return false;
        }
        $scope = strtolower(trim((string) ($_GET['scope'] ?? '')));
        $for = strtolower(trim((string) ($_POST['for'] ?? $_GET['for'] ?? '')));

        return in_array($scope, ['staff', 'platform'], true)
            || in_array($for, ['staff', 'platform'], true);
    }
}

if (!function_exists('rateb_bootstrap_ops_tenant')) {
    /** Ensure TenantContext has company for ops lookups and CRUD (super admin ?company_id=). */
    function rateb_bootstrap_ops_tenant(): void
    {
        if (function_exists('rateb_force_single_tenant_ops') && rateb_force_single_tenant_ops()) {
            if (function_exists('rateb_resolve_ops_company_id')) {
                $id = rateb_resolve_ops_company_id();
                if ($id > 0) {
                    \Rateb\App\Core\TenantContext::setCompanyId($id);
                    if (function_exists('rateb_bootstrap_branch_context')) {
                        rateb_bootstrap_branch_context($id);
                    }

                    return;
                }
            }
        }

        $cid = \Rateb\App\Core\TenantContext::companyId();
        if ($cid !== null && $cid > 0) {
            if (function_exists('rateb_bootstrap_branch_context')) {
                rateb_bootstrap_branch_context((int) $cid);
            }
            return;
        }
        $sessionCid = (int) (\Rateb\App\Core\SessionManager::get('rateb_company_id', 0) ?? 0);
        if ($sessionCid > 0 && !rateb_is_super_admin()) {
            \Rateb\App\Core\TenantContext::setCompanyId($sessionCid);
            return;
        }
        if (function_exists('rateb_resolve_ops_company_id')) {
            $id = rateb_resolve_ops_company_id();
            if ($id > 0) {
                \Rateb\App\Core\TenantContext::setCompanyId($id);
            }
        }
        if (function_exists('rateb_bootstrap_branch_context')) {
            rateb_bootstrap_branch_context();
        }
    }
}

if (!function_exists('rateb_ops_module_list_route')) {
    /** Strip /{id}/edit|/create|/show so redirects land on the module list. */
    function rateb_ops_module_list_route(?string $route = null): string
    {
        $route = rateb_normalize_erp_route($route ?? rateb_current_erp_route(''));
        $route = (string) preg_replace('#/\d+/(edit|show|update|delete)(/.*)?$#i', '', $route);
        $route = (string) preg_replace('#/(create|new)(/.*)?$#i', '', $route);
        $route = (string) preg_replace('#/\d+$#', '', $route);

        return rateb_normalize_erp_route($route);
    }
}

if (!function_exists('rateb_ops_company_picker_target_route')) {
    /**
     * Where the ops company picker should navigate after a tenant switch.
     * Stay on create/list; leave /{id}/edit|/show (record re-binds session to its company).
     */
    function rateb_ops_company_picker_target_route(?string $route = null): string
    {
        $route = rateb_normalize_erp_route($route ?? rateb_current_erp_route(''));
        if ($route !== '' && preg_match('#/\d+/(edit|show|update|delete)(/|$)#i', $route)) {
            return rateb_ops_module_list_route($route);
        }
        if ($route !== '' && preg_match('#/\d+$#', $route)) {
            return rateb_ops_module_list_route($route);
        }

        return $route;
    }
}

if (!function_exists('rateb_bootstrap_write_context_from_record')) {
    /** Align ops tenant/branch session with an existing row (edit/update/show). */
    function rateb_bootstrap_write_context_from_record(array $record): void
    {
        $companyId = (int) ($record['company_id'] ?? 0);
        if ($companyId < 1) {
            return;
        }

        // Platform SA company picker on another tenant's edit URL used to "snap back"
        // to the record company (e.g. ddd). Honour the picker: leave the record.
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()
            && array_key_exists('company_id', $_GET)
            && function_exists('rateb_is_platform_oversight_host')
            && rateb_is_platform_oversight_host()) {
            $pickedRaw = trim((string) ($_GET['company_id'] ?? ''));
            $picked = ($pickedRaw === '' || $pickedRaw === '0') ? 0 : (int) $pickedRaw;
            if ($picked !== $companyId) {
                if ($picked > 0 && function_exists('rateb_sync_ops_session_to_company')) {
                    rateb_sync_ops_session_to_company($picked);
                } else {
                    \Rateb\App\Core\SessionManager::set('rateb_ops_company_id', 0);
                }
                if (function_exists('rateb_ops_company_request_state_reset')) {
                    rateb_ops_company_request_state_reset();
                }
                $listRoute = rateb_ops_module_list_route();
                $target = function_exists('rateb_url') ? rateb_url($listRoute) : ('/' . $listRoute);
                $sep = strpos($target, '?') === false ? '?' : '&';
                $qs = 'company_id=' . $picked . '&rateb_live=1';
                header('Location: ' . $target . $sep . $qs, true, 302);
                exit;
            }
        }

        \Rateb\App\Core\TenantContext::setCompanyId($companyId);
        if (function_exists('rateb_adopt_ops_company_id')) {
            rateb_adopt_ops_company_id($companyId);
        }
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            \Rateb\App\Core\SessionManager::set('rateb_ops_company_id', $companyId);
        }
        \Rateb\App\Core\BranchContext::reset();
        if (function_exists('rateb_bootstrap_branch_context')) {
            rateb_bootstrap_branch_context($companyId);
        }
    }
}

if (!function_exists('rateb_load_tenant_record_for_write')) {
    /**
     * Load a tenant-scoped row for edit/update without company/branch filter mismatch.
     *
     * @return array<string, mixed>|null
     */
    function rateb_load_tenant_record_for_write(\Rateb\App\Core\Model $model, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        if (!$model->isTenantScoped()) {
            return $model->find($id);
        }
        $record = $model->findByIdUnscoped($id);
        if ($record) {
            rateb_bootstrap_write_context_from_record($record);
        }
        return $record;
    }
}

if (!function_exists('rateb_list_order_sql')) {
    /** Standard list sort for operational tables: newest record first. */
    function rateb_list_order_sql(string $alias = '', bool $withCreatedAt = true): string
    {
        $prefix = $alias !== '' ? preg_replace('/[^a-z_]/', '', $alias) . '.' : '';
        if ($withCreatedAt) {
            return "{$prefix}created_at DESC, {$prefix}id DESC";
        }
        return "{$prefix}id DESC";
    }
}

if (!function_exists('rateb_erp_locale_base_url')) {
    /**
     * Locale switch under ERP app prefix so the rateb_erp session cookie is sent.
     * Domain-root /locale/* on rateb.sa does not receive path=/rateb-erp/public cookies,
     * so Admin switches appeared to "fail" (session stayed Arabic).
     */
    function rateb_erp_locale_base_url(string $locale): string
    {
        if (!in_array($locale, RATEB_SUPPORTED_LOCALES, true)) {
            $locale = RATEB_DEFAULT_LOCALE;
        }

        return rateb_site_origin() . rtrim(rateb_erp_app_prefix(), '/') . '/locale/' . $locale;
    }
}

if (!function_exists('rateb_init_marketing_locale')) {
    function rateb_init_marketing_locale(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        // Explicit locale cookie (from /locale/{en|ar}) wins over a stale session value.
        // Domain-root switches used to set the cookie while leaving the ERP session on "ar".
        if (!empty($_COOKIE['rateb_locale'])) {
            $cookieLang = strtolower(trim((string) $_COOKIE['rateb_locale']));
            if (in_array($cookieLang, RATEB_SUPPORTED_LOCALES, true)) {
                if ((string) ($_SESSION['rateb_locale'] ?? '') !== $cookieLang) {
                    $_SESSION['rateb_locale'] = $cookieLang;
                }

                return;
            }
        }
        if (!empty($_SESSION['rateb_locale']) && in_array((string) $_SESSION['rateb_locale'], RATEB_SUPPORTED_LOCALES, true)) {
            return;
        }
    }
}

if (!function_exists('rateb_set_locale_cookie')) {
    function rateb_set_locale_cookie(string $locale): void
    {
        if (!in_array($locale, RATEB_SUPPORTED_LOCALES, true)) {
            return;
        }
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        if (PHP_VERSION_ID >= 70300) {
            setcookie('rateb_locale', $locale, [
                'expires' => time() + 86400 * 365,
                'path' => '/',
                'secure' => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie('rateb_locale', $locale, time() + 86400 * 365, '/', '', $secure, false);
        }
        // Also expose to this request so redirects in the same tick see English/Arabic.
        $_COOKIE['rateb_locale'] = $locale;
    }
}

if (!function_exists('rateb_locale_switch_url')) {
    function rateb_locale_switch_url(string $locale): string
    {
        if (!in_array($locale, RATEB_SUPPORTED_LOCALES, true)) {
            $locale = RATEB_DEFAULT_LOCALE;
        }
        $next = rateb_current_erp_route('site');
        if ($next === '' || $next === '__none__') {
            $next = rateb_current_public_path('site');
        }
        $query = ['next' => $next];
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()
            && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = rateb_resolve_ops_company_id();
            if ($companyId > 0) {
                $query['company_id'] = $companyId;
            }
        }

        return rateb_url_query(rateb_erp_locale_base_url($locale), $query);
    }
}

if (!function_exists('rateb_normalize_marketing_plan_slug')) {
    /** Map legacy Rateb Pro slugs (pro/gold/platinum) to ERP plan slugs. */
    function rateb_normalize_marketing_plan_slug(string $plan): string
    {
        $slug = strtolower(trim($plan));
        if ($slug === '') {
            return '';
        }
        $legacy = [
            'pro' => 'starter',
            'gold' => 'professional',
            'platinum' => 'enterprise',
        ];
        if (isset($legacy[$slug])) {
            $slug = $legacy[$slug];
        }
        $canonical = ['launch', 'starter', 'commerce', 'professional', 'enterprise', 'ultimate'];
        if (class_exists(\Rateb\App\Services\PlanLimitService::class)) {
            $fromConfig = array_keys(\Rateb\App\Services\PlanLimitService::tierDefinitions());
            if ($fromConfig !== []) {
                $canonical = $fromConfig;
            }
        }

        return in_array($slug, $canonical, true) ? $slug : '';
    }
}

if (!function_exists('rateb_erp_plan_to_checkout_slug')) {
    /** ERP marketing plan slug → Rateb Pro checkout slug (pro/gold/platinum). */
    function rateb_erp_plan_to_checkout_slug(string $erpPlan): string
    {
        $slug = strtolower(trim($erpPlan));
        $map = [
            'launch' => 'pro',
            'starter' => 'pro',
            'commerce' => 'gold',
            'professional' => 'gold',
            'enterprise' => 'platinum',
            'ultimate' => 'platinum',
            'pro' => 'pro',
            'gold' => 'gold',
            'platinum' => 'platinum',
        ];

        return $map[$slug] ?? 'gold';
    }
}

if (!function_exists('rateb_marketing_register_url')) {
    /** Pricing page — inline agency registration form replaces plan cards. */
    function rateb_marketing_register_url(string $plan = '', int $years = 1, array $extra = []): string
    {
        $query = array_merge(['register' => '1', 'years' => max(0, min(1, $years))], $extra);
        if ($plan !== '') {
            $query['plan'] = strtolower(trim($plan));
        } else {
            $query['plan'] = class_exists(\Rateb\App\Services\PlanLimitService::class)
                ? \Rateb\App\Services\PlanLimitService::recommendedSlug()
                : 'professional';
        }
        unset($query['open']);

        return rateb_url('site/pricing') . '?' . http_build_query($query) . '#pricing';
    }
}

if (!function_exists('rateb_marketing_partner_login_url')) {
    function rateb_marketing_partner_login_url(): string
    {
        return rateb_site_origin() . '/pages/partner-portal-login';
    }
}

if (!function_exists('rateb_locale')) {
    function rateb_locale(): string
    {
        rateb_init_marketing_locale();
        $locale = $_SESSION['rateb_locale'] ?? RATEB_DEFAULT_LOCALE;
        return in_array($locale, RATEB_SUPPORTED_LOCALES, true) ? $locale : RATEB_DEFAULT_LOCALE;
    }
}

if (!function_exists('rateb_is_rtl')) {
    function rateb_is_rtl(): bool
    {
        return rateb_locale() === 'ar';
    }
}

if (!function_exists('rateb_can')) {
    function rateb_can(string $slug): bool
    {
        if (!empty($_SESSION['rateb_is_super_admin'])) {
            return true;
        }
        if (class_exists(\Rateb\App\Core\SessionManager::class)
            && \Rateb\App\Core\SessionManager::get('rateb_is_super_admin')) {
            return true;
        }
        $userId = (int) ($_SESSION['rateb_user_id'] ?? 0);
        if ($userId <= 0 || $slug === '') {
            return $slug === '';
        }
        static $cache = [];
        $companyId = (int) ($_SESSION['rateb_company_id'] ?? 0);
        $cacheKey = $userId . ':' . $companyId;
        if (!isset($cache[$cacheKey])) {
            $cache[$cacheKey] = (new \Rateb\App\Services\AuthorizationService())->userPermissionSlugs($userId);
        }
        if (in_array($slug, $cache[$cacheKey], true)) {
            return true;
        }
        static $implies = null;
        if ($implies === null) {
            $cfgFile = (defined('RATEB_ROOT') ? RATEB_ROOT : '') . '/config/permissions-system.php';
            $cfg = is_file($cfgFile) ? require $cfgFile : [];
            $implies = is_array($cfg['permission_implies'] ?? null) ? $cfg['permission_implies'] : [];
        }
        foreach ($implies as $parent => $children) {
            if (!in_array($parent, $cache[$cacheKey], true)) {
                continue;
            }
            foreach ((array) $children as $child) {
                if ((string) $child === $slug) {
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists('rateb_html_preview')) {
    function rateb_html_preview(string $html, int $max = 100): string
    {
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        if (mb_strlen($text) > $max) {
            return mb_substr($text, 0, $max) . '…';
        }
        return $text;
    }
}

if (!function_exists('rateb_bidi_cell_text')) {
    /** Keeps {placeholders} readable in RTL/LTR table cells. */
    function rateb_bidi_cell_text(string $text): string
    {
        return preg_replace('/\{[^}]+\}/u', "\u{200E}$0\u{200E}", $text) ?? $text;
    }
}

if (!function_exists('rateb_email_template_sample_vars')) {
    /** @return array<string, string> */
    function rateb_email_template_sample_vars(): array
    {
        static $samples = null;
        if ($samples === null) {
            $file = (defined('RATEB_ROOT') ? RATEB_ROOT : '') . '/config/email-template-samples.php';
            $samples = is_file($file) ? require $file : [];
            if (!is_array($samples)) {
                $samples = [];
            }
        }
        return $samples;
    }
}

if (!function_exists('rateb_email_template_render_preview')) {
    /** @param array<string, string> $vars */
    function rateb_email_template_render_preview(string $text, ?array $vars = null): string
    {
        $vars = $vars ?? rateb_email_template_sample_vars();
        foreach ($vars as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        return $text;
    }
}

if (!function_exists('rateb_email_template_slug_label')) {
    function rateb_email_template_slug_label(string $slug): string
    {
        if ($slug === '') {
            return '';
        }
        $translated = __($slug);
        return $translated !== $slug ? $translated : $slug;
    }
}

if (!function_exists('rateb_date_column_kind')) {
    /**
     * Infer display kind for a column/field name or form input type.
     */
    function rateb_date_column_kind(string $name, string $fieldType = ''): string
    {
        $fieldType = strtolower(trim($fieldType));
        $map = [
            'date' => 'date',
            'datetime-local' => 'datetime',
            'time' => 'time',
            'month' => 'month',
            'week' => 'week',
        ];
        if ($fieldType !== '' && isset($map[$fieldType])) {
            return $map[$fieldType];
        }

        $n = strtolower(trim($name));
        if ($n === '') {
            return '';
        }

        if (preg_match('/_(at)$/', $n) || in_array($n, ['submitted_at', 'approved_at', 'last_run_at', 'next_expected_at'], true)) {
            return 'datetime';
        }
        if (str_ends_with($n, '_time') && $n !== 'comm_time') {
            return 'time';
        }
        if (in_array($n, ['deadline', 'period_start', 'period_end', 'start_date', 'end_date'], true)) {
            return 'date';
        }
        if (str_contains($n, 'date') || str_ends_with($n, '_from') || str_ends_with($n, '_to')) {
            return 'date';
        }

        return '';
    }
}

if (!function_exists('rateb_normalize_sql_datetime')) {
    /**
     * Normalize HTML date/time input for MySQL (empty → NULL).
     *
     * @return string|null
     */
    function rateb_normalize_sql_datetime(string $raw, string $fieldType = 'datetime-local')
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '—' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
            return null;
        }

        $fieldType = strtolower(trim($fieldType));
        if ($fieldType === 'datetime-local' || str_contains($raw, 'T')) {
            $normalized = str_replace('T', ' ', $raw);
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
                $normalized .= ':00';
            }
            $dt = date_create($normalized);

            return $dt ? $dt->format('Y-m-d H:i:s') : null;
        }
        if ($fieldType === 'date') {
            $dt = date_create($raw);

            return $dt ? $dt->format('Y-m-d') : null;
        }
        if ($fieldType === 'time') {
            if (preg_match('/^\d{2}:\d{2}$/', $raw)) {
                return $raw . ':00';
            }

            return preg_match('/^\d{2}:\d{2}:\d{2}$/', $raw) ? $raw : null;
        }

        $dt = date_create($raw);

        return $dt ? $dt->format('Y-m-d H:i:s') : null;
    }
}

if (!function_exists('rateb_looks_like_date_value')) {
    function rateb_looks_like_date_value($value): bool
    {
        $raw = trim((string) $value);
        if ($raw === '' || $raw === '—' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
            return false;
        }

        return (bool) preg_match(
            '/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$|^\d{2}:\d{2}(?::\d{2})?$/',
            $raw
        );
    }
}

if (!function_exists('rateb_format_date_value')) {
    /**
     * Format stored ISO date/time for UI display (DD / MM / YYYY).
     *
     * @param mixed $value
     */
    function rateb_format_date_value($value, string $kind = 'auto'): string
    {
        $raw = trim((string) $value);
        if ($raw === '' || $raw === '—') {
            return $raw === '' ? '' : '—';
        }
        if ($raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
            return '—';
        }

        if ($kind === 'auto') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $raw)) {
                $kind = 'datetime';
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                $kind = 'date';
            } elseif (preg_match('/^\d{2}:\d{2}/', $raw)) {
                $kind = 'time';
            } else {
                return $raw;
            }
        }

        $dt = date_create($raw);
        if ($dt === false) {
            return $raw;
        }

        $formatted = match ($kind) {
            'datetime' => $dt->format('d / m / Y H:i'),
            'time' => $dt->format('H:i'),
            'month' => $dt->format('m / Y'),
            'week' => $raw,
            default => $dt->format('d / m / Y'),
        };
        if (function_exists('rateb_western_digits')) {
            $formatted = rateb_western_digits($formatted);
        }

        return $formatted;
    }
}

if (!function_exists('rateb_date_display')) {
    function rateb_date_display($value, string $kind = 'auto'): string
    {
        return rateb_format_date_value($value, $kind);
    }
}

if (!function_exists('rateb_table_cell_display')) {
    function rateb_table_cell_display($value, int $max = 80): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }
        if (strpos($text, '<') !== false) {
            $text = function_exists('rateb_html_preview') ? rateb_html_preview($text, $max) : strip_tags($text);
        }
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        if (mb_strlen($text) > $max) {
            return mb_substr($text, 0, $max) . '…';
        }
        return $text;
    }
}

if (!function_exists('rateb_enrich_index_columns')) {
    /**
     * @param array<int, array<string, mixed>> $columns
     * @param array<string, array<string, mixed>> $formFieldsByName
     * @return array<int, array<string, mixed>>
     */
    function rateb_enrich_index_columns(array $columns, array $formFieldsByName = []): array
    {
        $yesNoNames = ['paid', 'is_active', 'is_visible', 'is_recurring', 'is_paid', 'requires_approval'];
        $out = [];
        foreach ($columns as $col) {
            $name = (string) ($col['name'] ?? '');
            $type = (string) ($col['type'] ?? '');
            $lookup = (string) ($col['lookup'] ?? '');
            $field = $formFieldsByName[$name] ?? [];
            if ($lookup === '' && !empty($field['lookup'])) {
                $lookup = (string) $field['lookup'];
            }
            $fieldType = (string) ($field['type'] ?? '');

            if ($type === '' && ($name === 'status' || ($lookup !== '' && str_ends_with($lookup, '_statuses')))) {
                $type = 'status';
            }
            if (in_array($name, $yesNoNames, true) && ($type === '' || $type === 'status' || $type === 'clip')) {
                $type = 'fk';
                $lookup = 'yes_no';
            }
            if ($lookup === 'yes_no' && $type === '') {
                $type = 'fk';
            }
            if ($type === '' && ($fieldType === 'fk' || $lookup !== '')) {
                $type = 'fk';
            }
            if ($type === '' || in_array($type, ['clip', 'text'], true)) {
                $dateKind = rateb_date_column_kind($name, $fieldType);
                if ($dateKind !== '') {
                    $type = $dateKind === 'datetime' ? 'datetime' : $dateKind;
                }
            }

            $merged = $col;
            if ($type !== '') {
                $merged['type'] = $type;
            }
            if ($lookup !== '') {
                $merged['lookup'] = $lookup;
            }
            $out[] = $merged;
        }
        return $out;
    }
}

if (!function_exists('rateb_enum_label')) {
    function rateb_enum_label(string $value): string
    {
        if ($value === '') {
            return '—';
        }
        if (function_exists('rateb_table_cell_meta')) {
            return rateb_table_cell_meta($value, ['name' => 'status', 'type' => 'status'])['display'];
        }
        $t = __($value);
        return $t !== $value ? $t : $value;
    }
}

if (!function_exists('rateb_table_cell_meta')) {
    /**
     * @param mixed $value
     * @param array<string, mixed> $col
     * @return array{display:string,title:string,class:string,dir:string,mode:string,badge:string}
     */
    function rateb_table_cell_meta($value, array $col = []): array
    {
        $type = (string) ($col['type'] ?? '');
        $name = (string) ($col['name'] ?? '');
        if ($type === '' && $name === 'status') {
            $type = 'status';
        }
        $yesNoNames = ['paid', 'is_active', 'is_visible', 'is_recurring', 'is_paid', 'requires_approval'];
        $lookupKey = (string) ($col['lookup'] ?? '');
        if ($lookupKey === 'yes_no' || in_array($name, $yesNoNames, true)) {
            $boolVal = (string) $value;
            if ($boolVal === '1' || $boolVal === '0') {
                $label = $boolVal === '1' ? __('yes') : __('no');
                return [
                    'display' => $label,
                    'title' => $label,
                    'class' => 'rateb-cell-clip',
                    'dir' => '',
                    'mode' => 'text',
                    'badge' => '',
                ];
            }
        }
        $classes = ['rateb-cell-clip'];
        $dir = '';
        $mode = 'text';
        $badge = '';

        if ($type === 'status') {
            $statusKey = (string) $value;
            $label = $statusKey;
            if (function_exists('__')) {
                foreach (['depreciation_status_', 'payment_status_', 'workflow_status_', 'status_', ''] as $prefix) {
                    $key = $prefix . $statusKey;
                    $t = __($key);
                    if ($t !== $key) {
                        $label = $t;
                        break;
                    }
                }
            }
            if (str_starts_with($statusKey, 'eval_tier_')) {
                $tier = substr($statusKey, strlen('eval_tier_'));
                $badge = match ($tier) {
                    'excellent' => 'success',
                    'very_good' => 'primary',
                    'good' => 'info',
                    default => 'warning',
                };
            } elseif (str_starts_with($statusKey, 'manager_approval_')) {
                $approval = substr($statusKey, strlen('manager_approval_'));
                $badge = match ($approval) {
                    'pending' => 'warning',
                    'rejected' => 'danger',
                    default => 'success',
                };
            } elseif (str_starts_with($statusKey, 'comm_status_')) {
                $commSt = substr($statusKey, strlen('comm_status_'));
                $badge = match ($commSt) {
                    'completed' => 'success',
                    'closed' => 'secondary',
                    'follow_up' => 'warning',
                    default => 'info',
                };
            } elseif (in_array($statusKey, ['low', 'medium', 'high', 'urgent'], true)
                || ($name === 'priority' && $statusKey !== '')) {
                // Priority scale: low → urgent
                $badge = match ($statusKey) {
                    'low' => 'success',
                    'medium' => 'info',
                    'high' => 'warning',
                    'urgent' => 'danger',
                    default => 'secondary',
                };
            } elseif (in_array($statusKey, ['open', 'in_progress', 'resolved', 'closed'], true)
                || ($name === 'status' && in_array($statusKey, ['open', 'in_progress', 'resolved', 'closed', 'pending'], true))) {
                // Ticket / workflow status scale
                $badge = match ($statusKey) {
                    'open', 'pending' => 'primary',
                    'in_progress' => 'warning',
                    'resolved' => 'success',
                    'closed' => 'secondary',
                    default => 'info',
                };
            } else {
                $badge = in_array($statusKey, ['draft', 'pending', 'cancelled', 'inactive'], true) ? 'info' : 'success';
                if (in_array($statusKey, ['failed', 'rejected', 'overdue'], true)) {
                    $badge = 'danger';
                }
                if (in_array($statusKey, ['warning', 'partial'], true)) {
                    $badge = 'warning';
                }
            }
            return [
                'display' => $label,
                'title' => $label,
                'class' => '',
                'dir' => '',
                'mode' => 'badge',
                'badge' => $badge,
            ];
        }

        if ($type === 'money' || $type === 'number') {
            $display = number_format((float) $value, 2);
            return [
                'display' => $display,
                'title' => $display,
                'class' => 'rateb-cell-clip rateb-ltr-num rateb-td-money',
                'dir' => 'ltr',
                'mode' => 'text',
                'badge' => '',
            ];
        }

        if ($type === 'id' || $name === 'slug') {
            $display = function_exists('rateb_table_cell_display')
                ? rateb_table_cell_display($value, $type === 'id' ? 32 : 48)
                : (string) $value;
            return [
                'display' => $display,
                'title' => trim((string) $value),
                'class' => 'rateb-cell-clip rateb-ltr-num' . ($name === 'slug' ? ' font-monospace small text-muted' : ''),
                'dir' => 'ltr',
                'mode' => 'text',
                'badge' => '',
            ];
        }

        if ($type === 'html_preview') {
            $display = function_exists('rateb_html_preview') ? rateb_html_preview((string) $value) : (string) $value;
            $title = function_exists('rateb_html_preview') ? rateb_html_preview((string) $value, 200) : (string) $value;
            return [
                'display' => $display,
                'title' => $title,
                'class' => 'rateb-cell-clip rateb-ar-text rateb-bidi-mixed text-muted small',
                'dir' => '',
                'mode' => 'text',
                'badge' => '',
            ];
        }

        if ($type === 'bidi_text') {
            $display = function_exists('rateb_bidi_cell_text') ? rateb_bidi_cell_text((string) $value) : (string) $value;
            return [
                'display' => $display,
                'title' => trim((string) $value),
                'class' => 'rateb-cell-clip rateb-ar-text rateb-bidi-mixed',
                'dir' => '',
                'mode' => 'text',
                'badge' => '',
            ];
        }

        $dateKind = '';
        if (in_array($type, ['date', 'datetime', 'time', 'month', 'week'], true)) {
            $dateKind = $type === 'datetime-local' ? 'datetime' : $type;
        } elseif ($name !== '') {
            $dateKind = rateb_date_column_kind($name);
        }
        if ($dateKind !== '' && rateb_looks_like_date_value($value)) {
            $display = rateb_format_date_value($value, $dateKind);
            $title = trim((string) $value);
            return [
                'display' => $display,
                'title' => $title,
                'class' => 'rateb-cell-clip rateb-ltr-date rateb-ltr-num',
                'dir' => 'ltr',
                'mode' => 'text',
                'badge' => '',
            ];
        }

        $display = function_exists('rateb_table_cell_display') ? rateb_table_cell_display($value) : (string) $value;
        if ($display === '' || $display === null) {
            $display = '—';
        }
        return [
            'display' => (string) $display,
            'title' => trim((string) $value),
            'class' => 'rateb-cell-clip rateb-ar-text',
            'dir' => '',
            'mode' => 'text',
            'badge' => '',
        ];
    }
}

if (!function_exists('rateb_role_label')) {
    function rateb_role_label(array $row): string
    {
        $slug = str_replace('-', '_', trim((string) ($row['slug'] ?? '')));
        if ($slug !== '') {
            $key = 'role_' . $slug;
            $label = __($key);
            if ($label !== $key) {
                return $label;
            }
        }
        return (string) ($row['name'] ?? '');
    }
}

if (!function_exists('rateb_role_description')) {
    function rateb_role_description(array $row): string
    {
        $slug = str_replace('-', '_', trim((string) ($row['slug'] ?? '')));
        if ($slug !== '') {
            $key = 'role_desc_' . $slug;
            $label = __($key);
            if ($label !== $key) {
                return $label;
            }
        }
        return (string) ($row['description'] ?? '');
    }
}

if (!function_exists('rateb_permission_labels_file')) {
    /** @return array<string, array{0: string, 1?: string}> */
    function rateb_permission_labels_file(string $locale): array
    {
        static $cache = [];
        $locale = $locale === 'ar' ? 'ar' : 'en';
        if (isset($cache[$locale])) {
            return $cache[$locale];
        }
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : '';
        $file = $root . '/config/permission-labels-' . $locale . '.php';
        $labels = is_file($file) ? require $file : [];
        $cache[$locale] = is_array($labels) ? $labels : [];
        return $cache[$locale];
    }
}

if (!function_exists('rateb_module_label')) {
    function rateb_module_label(string $module): string
    {
        $module = trim($module);
        if ($module === '') {
            return '';
        }
        $keyed = __('perm_module_' . $module);
        if ($keyed !== 'perm_module_' . $module) {
            return $keyed;
        }
        if ($module === 'hr') {
            $hr = __('human_resources');
            if ($hr !== 'human_resources') {
                return $hr;
            }
        }
        $translated = __($module);
        return $translated !== $module ? $translated : ucfirst(str_replace('_', ' ', $module));
    }
}

if (!function_exists('rateb_permission_label')) {
    function rateb_permission_label(array $row): string
    {
        $slug = (string) ($row['slug'] ?? '');
        $locale = rateb_locale();
        $labels = rateb_permission_labels_file($locale);
        if ($slug !== '' && isset($labels[$slug][0])) {
            return (string) $labels[$slug][0];
        }
        if ($locale === 'ar') {
            $nameAr = (string) ($row['name_ar'] ?? '');
            $corrupted = $nameAr === '' || strpos($nameAr, '?') !== false || preg_match('/^\?+$/', $nameAr) === 1;
            if (!$corrupted && $nameAr !== '' && $nameAr !== (string) ($row['name'] ?? '')) {
                return $nameAr;
            }
        }
        return (string) ($row['name'] ?? $slug);
    }
}

if (!function_exists('rateb_permission_description')) {
    function rateb_permission_description(array $row): string
    {
        $slug = (string) ($row['slug'] ?? '');
        $locale = rateb_locale();
        $labels = rateb_permission_labels_file($locale);
        if ($slug !== '' && isset($labels[$slug][1]) && (string) $labels[$slug][1] !== '') {
            return (string) $labels[$slug][1];
        }
        if ($locale === 'ar') {
            $descAr = (string) ($row['description_ar'] ?? '');
            $corrupted = $descAr === '' || strpos($descAr, '?') !== false;
            if (!$corrupted && $descAr !== '' && $descAr !== (string) ($row['description'] ?? '')) {
                return $descAr;
            }
        }
        return (string) ($row['description'] ?? '');
    }
}

if (!function_exists('rateb_arabic_compound_label')) {
    /**
     * @param list<string> $parts
     * @param list<string> $translated
     */
    function rateb_arabic_compound_label(array $parts, array $translated): ?string
    {
        if (rateb_locale() !== 'ar' || $translated === []) {
            return null;
        }
        $clean = array_values(array_filter($parts, static fn(string $p): bool => $p !== 'id'));
        if ($clean === [] || count($clean) !== count($translated)) {
            return null;
        }
        $suffix = end($clean);
        $head = array_slice($translated, 0, -1);
        $tail = $translated[count($translated) - 1];

        return match ($suffix) {
            'date', 'at' => $tail . ($head !== [] ? ' ' . implode(' ', $head) : ''),
            'value', 'amount', 'total', 'cost', 'tax', 'percent', 'days', 'due' => $tail . ($head !== [] ? ' ' . implode(' ', $head) : ''),
            'name', 'title', 'label' => ($suffix === 'name' ? 'اسم' : ($suffix === 'title' ? 'عنوان' : 'تسمية'))
                . ($head !== [] ? ' ' . implode(' ', $head) : ''),
            'no', 'number' => 'رقم' . ($head !== [] ? ' ' . implode(' ', $head) : ''),
            'path', 'url' => ($suffix === 'path' ? 'مسار' : 'رابط') . ($head !== [] ? ' ' . implode(' ', $head) : ''),
            'count' => 'عدد' . ($head !== [] ? ' ' . implode(' ', $head) : ''),
            'display' => 'عرض' . ($head !== [] ? ' ' . implode(' ', $head) : ''),
            default => null,
        };
    }
}

if (!function_exists('rateb_label')) {
    function rateb_label(string $labelOrKey): string
    {
        static $fieldLabels = null;
        $raw = trim($labelOrKey);
        if ($raw === '' || $raw === '—') {
            return $raw;
        }
        $key = strtolower(str_replace([' ', '-'], '_', $raw));

        $t = __($key);
        if ($t !== $key) {
            return $t;
        }

        if ($fieldLabels === null) {
            $locale = rateb_locale();
            $file = RATEB_ROOT . '/config/field-labels-' . $locale . '.php';
            $fieldLabels = is_file($file) ? require $file : [];
        }
        if (isset($fieldLabels[$key]) && (string) $fieldLabels[$key] !== '') {
            return (string) $fieldLabels[$key];
        }

        // Title-case labels like "Name" → name
        if ($raw !== $key) {
            $t = __($key);
            if ($t !== $key) {
                return $t;
            }
        }

        if (preg_match('/^(.+)_(en|ar)$/', $key, $m)) {
            $baseKey = $m[1];
            $base = __($baseKey);
            if ($base === $baseKey && isset($fieldLabels[$baseKey])) {
                $base = (string) $fieldLabels[$baseKey];
            }
            if ($base !== $baseKey) {
                $langKey = 'lang_' . $m[2];
                $lang = __($langKey);
                if ($lang === $langKey) {
                    $lang = $m[2] === 'en'
                        ? (rateb_locale() === 'ar' ? 'إنجليزي' : 'English')
                        : (rateb_locale() === 'ar' ? 'عربي' : 'Arabic');
                }
                return $base . ' (' . $lang . ')';
            }
        }

        $parts = explode('_', $key);
        if (count($parts) >= 2) {
            $translated = [];
            $ok = true;
            foreach ($parts as $part) {
                if ($part === 'id') {
                    continue;
                }
                $pt = __($part);
                if ($pt === $part && isset($fieldLabels[$part])) {
                    $pt = (string) $fieldLabels[$part];
                }
                if ($pt === $part) {
                    $ok = false;
                    break;
                }
                $translated[] = $pt;
            }
            if ($ok && $translated !== []) {
                $compound = rateb_arabic_compound_label($parts, $translated);
                if ($compound !== null) {
                    return $compound;
                }

                return implode(' ', $translated);
            }
        }

        if (rateb_locale() === 'en') {
            return ucwords(str_replace('_', ' ', $key));
        }
        return $raw;
    }
}

if (!function_exists('rateb_is_super_admin')) {
    function rateb_is_super_admin(): bool
    {
        if (!empty($_SESSION['rateb_is_super_admin'])) {
            return true;
        }
        if (class_exists(\Rateb\App\Core\SessionManager::class)) {
            return (bool) \Rateb\App\Core\SessionManager::get('rateb_is_super_admin');
        }
        return false;
    }
}

if (!function_exists('rateb_branch_portal_url')) {
    function rateb_branch_portal_url(int $branchId, ?array $branchRow = null): string
    {
        if (is_array($branchRow)) {
            $slug = trim((string) ($branchRow['company_slug'] ?? ''));
            $code = trim((string) ($branchRow['code'] ?? ''));
            if ($slug !== '' && $code !== '') {
                return rateb_public_url('login?company=' . rawurlencode($slug) . '&branch=' . rawurlencode($code));
            }
        }
        if ($branchId < 1) {
            return rateb_public_url('login');
        }
        return rateb_public_url('login?branch_id=' . $branchId);
    }
}

if (!function_exists('rateb_branch_portal_url_by_code')) {
    function rateb_branch_portal_url_by_code(string $companySlug, string $branchCode): string
    {
        $companySlug = trim($companySlug);
        $branchCode = trim($branchCode);
        if ($companySlug === '' || $branchCode === '') {
            return rateb_public_url('login');
        }
        return rateb_public_url('login?company=' . rawurlencode($companySlug) . '&branch=' . rawurlencode($branchCode));
    }
}

if (!function_exists('rateb_portal_branch_id')) {
    function rateb_portal_branch_id(): int
    {
        return (int) (\Rateb\App\Core\SessionManager::get('rateb_portal_branch_id', 0) ?? 0);
    }
}

if (!function_exists('rateb_is_portal_branch_session')) {
    function rateb_is_portal_branch_session(): bool
    {
        return rateb_portal_branch_id() > 0;
    }
}

if (!function_exists('rateb_resolve_create_branch_id')) {
    /** Branch id to stamp on new records when the session is branch-scoped. */
    function rateb_resolve_create_branch_id(): int
    {
        rateb_bootstrap_branch_context();
        $filter = \Rateb\App\Core\BranchContext::activeFilterBranchId();
        if ($filter !== null && $filter > 0) {
            return $filter;
        }
        if (!\Rateb\App\Core\BranchContext::accessAll()) {
            $ids = \Rateb\App\Core\BranchContext::allowedIds();
            return $ids[0] ?? 0;
        }
        $companyId = \Rateb\App\Core\BranchContext::companyId();
        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = rateb_resolve_ops_company_id();
        }
        if ($companyId > 0) {
            return (new \Rateb\App\Services\BranchService())->defaultBranchId($companyId);
        }
        return 0;
    }
}

if (!function_exists('rateb_portal_branch_label')) {
    function rateb_portal_branch_label(): string
    {
        $id = rateb_portal_branch_id();
        if ($id < 1) {
            return '';
        }
        $row = (new \Rateb\App\Models\Branch())->queryOne(
            'SELECT name, code FROM rateb_branches WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        if (!$row) {
            return '';
        }
        $name = trim((string) ($row['name'] ?? ''));
        $code = trim((string) ($row['code'] ?? ''));
        if ($name === '') {
            return $code;
        }
        return $code !== '' ? $name . ' (' . $code . ')' : $name;
    }
}

if (!function_exists('rateb_bootstrap_portal_branch_from_request')) {
    /** When URL has ?company=&branch= or ?branch_id=, lock session to that branch (even if already logged in). */
    function rateb_bootstrap_portal_branch_from_request(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $svc = new \Rateb\App\Services\BranchService();
        $branchId = $svc->resolvePortalBranchIdFromRequest();
        if ($branchId < 1) {
            return;
        }
        $branch = $svc->findActiveForPortal($branchId);
        if (!$branch) {
            return;
        }
        $userId = (int) (\Rateb\App\Core\SessionManager::get('rateb_user_id', 0) ?? 0);
        if ($userId > 0) {
            $isSuper = (bool) (\Rateb\App\Core\SessionManager::get('rateb_is_super_admin', false) ?? false);
            if ($isSuper) {
                $companyId = (int) ($branch['company_id'] ?? 0);
                if ($companyId > 0) {
                    \Rateb\App\Core\SessionManager::set('rateb_ops_company_id', $companyId);
                    \Rateb\App\Core\TenantContext::setCompanyId($companyId);
                }
            } else {
                $companyId = (int) (\Rateb\App\Core\SessionManager::get('rateb_company_id', 0) ?? 0);
                if (!$svc->userMayUsePortalBranch($userId, $branchId, $companyId)) {
                    return;
                }
            }
        }
        \Rateb\App\Core\SessionManager::set('rateb_portal_branch_id', $branchId);
        \Rateb\App\Core\BranchContext::reset();
    }
}

if (!function_exists('rateb_branch_strict_assignment')) {
    /**
     * P7-1: enforce branch assignment for branch-restricted roles (branch_manager, branch_user).
     * Reads RATEB_BRANCH_STRICT_ASSIGNMENT only here. Default false = legacy behavior.
     */
    function rateb_branch_strict_assignment(): bool
    {
        if (array_key_exists('RATEB_BRANCH_STRICT_ASSIGNMENT', $_ENV)) {
            $v = $_ENV['RATEB_BRANCH_STRICT_ASSIGNMENT'];
        } else {
            $v = getenv('RATEB_BRANCH_STRICT_ASSIGNMENT');
        }
        if ($v === false || $v === null || trim((string) $v) === '') {
            return false;
        }

        return !in_array(strtolower(trim((string) $v)), ['0', 'false', 'off', 'no'], true);
    }
}

if (!function_exists('rateb_bootstrap_branch_context')) {
    function rateb_bootstrap_branch_context(?int $companyId = null): void
    {
        (new \Rateb\App\Services\BranchAccessService())->bootstrap($companyId);
    }
}

if (!function_exists('rateb_branch_access_all')) {
    function rateb_branch_access_all(): bool
    {
        rateb_bootstrap_branch_context();
        return \Rateb\App\Core\BranchContext::accessAll();
    }
}

if (!function_exists('rateb_allowed_branch_ids')) {
    /** @return array<int, int> */
    function rateb_allowed_branch_ids(): array
    {
        return (new \Rateb\App\Services\BranchAccessService())->allowedBranchIds();
    }
}

if (!function_exists('rateb_can_access_branch')) {
    function rateb_can_access_branch(int $branchId): bool
    {
        return (new \Rateb\App\Services\BranchAccessService())->canAccessBranch($branchId);
    }
}

if (!function_exists('rateb_can_manage_all_branches')) {
    function rateb_can_manage_all_branches(): bool
    {
        return (new \Rateb\App\Services\BranchAccessService())->canManageAllBranches();
    }
}

if (!function_exists('rateb_branch_filter_sql')) {
    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    function rateb_branch_filter_sql(string $alias = '', string $column = 'branch_id'): array
    {
        rateb_bootstrap_branch_context();
        $ids = \Rateb\App\Core\BranchContext::effectiveFilterIds();
        if ($ids === []) {
            if (!\Rateb\App\Core\BranchContext::accessAll()) {
                return [' AND 1=0', []];
            }
            return ['', []];
        }
        $safeAlias = preg_replace('/[^a-z_0-9]/', '', strtolower($alias)) ?? '';
        static $keywords = ['where', 'join', 'left', 'right', 'inner', 'outer', 'cross', 'on', 'using', 'group', 'order', 'limit', 'having', 'union'];
        if ($safeAlias === '' || in_array($safeAlias, $keywords, true)) {
            $safeAlias = '';
        }
        $col = ($safeAlias !== '' ? $safeAlias . '.' : '') . preg_replace('/[^a-z_0-9]/', '', $column);
        $parts = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $key = 'bf_' . $i;
            $parts[] = ':' . $key;
            $params[$key] = $id;
        }
        // Include NULL branch rows (ESS / oversight-created HR often omit branch_id).
        // Without this, approved leave/attendance disappears under any branch filter.
        return [
            ' AND (' . $col . ' IN (' . implode(',', $parts) . ') OR ' . $col . ' IS NULL)',
            $params,
        ];
    }
}

if (!function_exists('rateb_active_branch_filter_id')) {
    function rateb_active_branch_filter_id(): int
    {
        rateb_bootstrap_branch_context();
        return (int) (\Rateb\App\Core\BranchContext::activeFilterBranchId() ?? 0);
    }
}

if (!function_exists('rateb_branch_filter_label')) {
    function rateb_branch_filter_label(): string
    {
        $id = rateb_active_branch_filter_id();
        if ($id < 1) {
            return __('branch_filter_all');
        }
        $row = (new \Rateb\App\Models\Branch())->queryOne(
            'SELECT name, code FROM rateb_branches WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        if (!$row) {
            return (string) $id;
        }
        $name = trim((string) ($row['name'] ?? ''));
        $code = trim((string) ($row['code'] ?? ''));
        if ($name === '') {
            return $code;
        }
        return $code !== '' ? $name . ' (' . $code . ')' : $name;
    }
}

/** Agency ERP host or dedicated DB — ops must use the single primary tenant (not stale platform session). */
if (!function_exists('rateb_agency_erp_binding_active')) {
    function rateb_agency_erp_binding_active(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }
        $lookupFile = dirname(RATEB_ROOT, 1) . '/config/env/agency_lookup.php';
        if (!is_file($lookupFile)) {
            return false;
        }
        require_once $lookupFile;

        return function_exists('rateb_agency_erp_binding_for_request_host')
            && rateb_agency_erp_binding_for_request_host() !== null;
    }
}

if (!function_exists('rateb_force_single_tenant_ops')) {
    function rateb_force_single_tenant_ops(): bool
    {
        if (function_exists('rateb_erp_is_dedicated_deployment') && rateb_erp_is_dedicated_deployment()) {
            return true;
        }
        if (rateb_agency_erp_binding_active()) {
            return true;
        }
        if (function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
            return false;
        }
        if (PHP_SAPI !== 'cli') {
            $lookupFile = dirname(RATEB_ROOT, 1) . '/config/env/agency_lookup.php';
            if (is_file($lookupFile)) {
                require_once $lookupFile;
                $host = function_exists('rateb_normalize_http_host')
                    ? rateb_normalize_http_host((string) ($_SERVER['HTTP_HOST'] ?? ''))
                    : strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
                if ($host !== '' && function_exists('rateb_lookup_agency_erp_by_host')
                    && rateb_lookup_agency_erp_by_host($host) !== null) {
                    return true;
                }
            }
        }

        return \Rateb\App\Services\DedicatedTenantPolicy::companyCount() === 1;
    }
}

if (!function_exists('rateb_sync_ops_session_to_company')) {
    function rateb_sync_ops_session_to_company(int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }
        $ops = (int) (\Rateb\App\Core\SessionManager::get('rateb_ops_company_id', 0) ?? 0);
        $sess = (int) (\Rateb\App\Core\SessionManager::get('rateb_company_id', 0) ?? 0);
        if ($ops !== $companyId) {
            \Rateb\App\Core\SessionManager::set('rateb_ops_company_id', $companyId);
        }
        if ($sess !== $companyId) {
            \Rateb\App\Core\SessionManager::set('rateb_company_id', $companyId);
        }
    }
}

/** Request-scoped ops-company memo (exists + rows + resolved id). Not a cross-request cache. */
if (!function_exists('rateb_ops_company_request_state')) {
    /**
     * @return array{exists: array<int, bool>, rows: array<int, ?array>, resolved: ?int, resolved_set: bool}
     */
    function &rateb_ops_company_request_state(): array
    {
        static $state = [
            'exists' => [],
            'rows' => [],
            'resolved' => null,
            'resolved_set' => false,
        ];

        return $state;
    }
}

if (!function_exists('rateb_ops_company_request_state_reset')) {
    function rateb_ops_company_request_state_reset(): void
    {
        $state = &rateb_ops_company_request_state();
        $state['exists'] = [];
        $state['rows'] = [];
        $state['resolved'] = null;
        $state['resolved_set'] = false;
    }
}

/** Resolve active company for ops routes (session, then ?company_id=, then ops session). */
if (!function_exists('rateb_ops_company_exists')) {
    function rateb_ops_company_exists(int $companyId): bool
    {
        if ($companyId < 1) {
            return false;
        }
        $state = &rateb_ops_company_request_state();
        if (array_key_exists($companyId, $state['exists'])) {
            return $state['exists'][$companyId];
        }
        try {
            $row = (new \Rateb\App\Models\Company())->find($companyId);
            $ok = is_array($row) && (int) ($row['id'] ?? 0) === $companyId;
        } catch (\Throwable $e) {
            $ok = false;
        }
        $state['exists'][$companyId] = $ok;

        return $ok;
    }
}

if (!function_exists('rateb_clear_ops_company_session')) {
    function rateb_clear_ops_company_session(): void
    {
        \Rateb\App\Core\SessionManager::set('rateb_ops_company_id', 0);
        if ((int) (\Rateb\App\Core\SessionManager::get('rateb_company_id', 0) ?? 0) > 0) {
            \Rateb\App\Core\SessionManager::set('rateb_company_id', 0);
        }
        \Rateb\App\Core\TenantContext::setCompanyId(null);
        \Rateb\App\Core\BranchContext::reset();
        if (function_exists('rateb_ops_company_request_state_reset')) {
            rateb_ops_company_request_state_reset();
        }
    }
}

if (!function_exists('rateb_adopt_ops_company_id')) {
    function rateb_adopt_ops_company_id(int $companyId): int
    {
        if ($companyId < 1 || !rateb_ops_company_exists($companyId)) {
            rateb_clear_ops_company_session();
            return 0;
        }
        $prevCtx = \Rateb\App\Core\TenantContext::companyId();
        $prevId = $prevCtx !== null ? (int) $prevCtx : 0;
        if ($prevId > 0 && $prevId !== $companyId) {
            (new \Rateb\App\Services\BranchAccessService())->clearActiveBranchFilter();
            $portalBranch = (int) (\Rateb\App\Core\SessionManager::get('rateb_portal_branch_id', 0) ?? 0);
            if ($portalBranch > 0) {
                $row = (new \Rateb\App\Models\Branch())->queryOne(
                    'SELECT id FROM rateb_branches WHERE id = :id AND company_id = :cid LIMIT 1',
                    ['id' => $portalBranch, 'cid' => $companyId]
                );
                if (!$row) {
                    \Rateb\App\Core\SessionManager::forget('rateb_portal_branch_id');
                    \Rateb\App\Core\BranchContext::reset();
                }
            }
        }
        \Rateb\App\Core\TenantContext::setCompanyId($companyId);
        $state = &rateb_ops_company_request_state();
        $state['resolved'] = $companyId;
        $state['resolved_set'] = true;

        return $companyId;
    }
}

if (!function_exists('rateb_request_company_id')) {
    /**
     * First non-zero company_id from request (handles duplicate ?company_id=a&company_id=b —
     * PHP $_GET keeps the last scalar; also scan QUERY_STRING for an explicit last value).
     */
    function rateb_request_company_id(): int
    {
        $fromGet = (int) ($_GET['company_id'] ?? 0);
        $fromPost = (int) ($_POST['company_id'] ?? 0);
        if ($fromPost > 0) {
            return $fromPost;
        }
        if ($fromGet > 0) {
            return $fromGet;
        }
        $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
        if ($qs !== '' && preg_match_all('/(?:^|&)company_id=(\d+)/', $qs, $m) && $m[1] !== []) {
            return (int) end($m[1]);
        }

        return 0;
    }
}

if (!function_exists('rateb_resolve_ops_company_id')) {
    function rateb_resolve_ops_company_id(): int
    {
        $state = &rateb_ops_company_request_state();
        if ($state['resolved_set']) {
            return (int) ($state['resolved'] ?? 0);
        }

        if (function_exists('rateb_force_single_tenant_ops') && rateb_force_single_tenant_ops()) {
            $primary = \Rateb\App\Services\DedicatedTenantPolicy::primaryCompanyId();
            if ($primary > 0) {
                rateb_sync_ops_session_to_company($primary);

                return rateb_adopt_ops_company_id($primary);
            }
        }

        $isSuper = function_exists('rateb_is_super_admin') && rateb_is_super_admin();
        $fromRequest = function_exists('rateb_request_company_id')
            ? rateb_request_company_id()
            : (int) ($_GET['company_id'] ?? $_POST['company_id'] ?? 0);

        // Platform SA: picker can clear ops tenant (?company_id=0 / -0) without destroying identity.
        // Never call rateb_clear_ops_company_session() here — it zeroed rateb_company_id and
        // combined with login?err=session cookie purges caused logout on Users navigation.
        if ($isSuper && array_key_exists('company_id', $_GET)) {
            $rawPicker = trim((string) ($_GET['company_id'] ?? ''));
            // Treat 0, -0, empty, non-numeric as platform mode (default for Super Admin).
            if ($rawPicker === '' || (int) $rawPicker < 1) {
                \Rateb\App\Core\SessionManager::set('rateb_ops_company_id', 0);
                \Rateb\App\Core\SessionManager::set('rateb_ops_company_explicit', 0);
                if (function_exists('rateb_ops_company_request_state_reset')) {
                    rateb_ops_company_request_state_reset();
                }
                $state = &rateb_ops_company_request_state();
                $state['resolved'] = 0;
                $state['resolved_set'] = true;

                return 0;
            }
        }

        // Platform super-admin: honour ops company picker (?company_id=) over any leftover
        // rateb_company_id from a previous tenant preview (fixes ddd/22 ignored for 228).
        if ($isSuper && $fromRequest > 0) {
            $valid = rateb_adopt_ops_company_id($fromRequest);
            if ($valid > 0) {
                rateb_sync_ops_session_to_company($valid);
                \Rateb\App\Core\SessionManager::set('rateb_ops_company_explicit', 1);

                return $valid;
            }
        }

        if (!$isSuper) {
            $sessionCompany = (int) (\Rateb\App\Core\SessionManager::get('rateb_company_id', 0) ?? 0);
            if ($sessionCompany > 0) {
                $valid = rateb_adopt_ops_company_id($sessionCompany);
                if ($valid > 0) {
                    return $valid;
                }
            }
        }

        if (!$isSuper && $fromRequest > 0) {
            $valid = rateb_adopt_ops_company_id($fromRequest);
            if ($valid > 0) {
                \Rateb\App\Core\SessionManager::set('rateb_ops_company_id', $valid);

                return $valid;
            }
        }

        $opsCompany = (int) (\Rateb\App\Core\SessionManager::get('rateb_ops_company_id', 0) ?? 0);
        $opsExplicit = (int) (\Rateb\App\Core\SessionManager::get('rateb_ops_company_explicit', 0) ?? 0) === 1;
        // Platform SA: leftover ops company without an explicit picker choice → المنصة (بدون شركة).
        if ($isSuper && function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()
            && !$opsExplicit) {
            \Rateb\App\Core\SessionManager::set('rateb_ops_company_id', 0);
            $state['resolved'] = 0;
            $state['resolved_set'] = true;

            return 0;
        }
        if ($opsCompany > 0) {
            $valid = rateb_adopt_ops_company_id($opsCompany);
            if ($valid > 0) {
                return $valid;
            }
        }

        // Platform Super Admin default: المنصة (بدون شركة).
        if ($isSuper && function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
            \Rateb\App\Core\SessionManager::set('rateb_ops_company_id', 0);
            $state['resolved'] = 0;
            $state['resolved_set'] = true;

            return 0;
        }

        $ctx = \Rateb\App\Core\TenantContext::companyId();
        if ($ctx !== null && $ctx > 0) {
            return rateb_adopt_ops_company_id((int) $ctx);
        }

        $state['resolved'] = 0;
        $state['resolved_set'] = true;

        return 0;
    }
}

/**
 * Resolve company_id for ERP shell / offline warm payload.
 * Prefer explicit ops selection; for super-admin without a bound company, use the same
 * primary-company default as Admin dashboard (DedicatedTenantPolicy::primaryCompanyId).
 * Syncs session when a valid company is adopted — never invents an unverified id.
 */
if (!function_exists('rateb_resolve_erp_shell_company_id')) {
    function rateb_resolve_erp_shell_company_id(): int
    {
        $isPlatformSa = (bool) \Rateb\App\Core\SessionManager::get('rateb_is_super_admin')
            && function_exists('rateb_is_platform_oversight_host')
            && rateb_is_platform_oversight_host();

        $resolved = 0;
        if (function_exists('rateb_resolve_ops_company_id')) {
            $resolved = (int) rateb_resolve_ops_company_id();
        }

        // Platform Super Admin «بدون شركة»: never inherit leftover rateb_company_id / primary.
        if ($isPlatformSa) {
            if ($resolved > 0 && function_exists('rateb_sync_ops_session_to_company')) {
                rateb_sync_ops_session_to_company($resolved);
            }

            return $resolved > 0 ? $resolved : 0;
        }

        if ($resolved < 1) {
            $sessionCompany = (int) (\Rateb\App\Core\SessionManager::get('rateb_company_id', 0) ?? 0);
            if ($sessionCompany > 0 && function_exists('rateb_adopt_ops_company_id')) {
                $resolved = (int) rateb_adopt_ops_company_id($sessionCompany);
            }
        }
        if ($resolved < 1 && (bool) \Rateb\App\Core\SessionManager::get('rateb_is_super_admin')) {
            if (class_exists(\Rateb\App\Services\DedicatedTenantPolicy::class)) {
                $primary = (int) \Rateb\App\Services\DedicatedTenantPolicy::primaryCompanyId();
                if ($primary > 0 && function_exists('rateb_adopt_ops_company_id')) {
                    $resolved = (int) rateb_adopt_ops_company_id($primary);
                } elseif ($primary > 0) {
                    $resolved = $primary;
                }
            }
        }
        if ($resolved > 0 && function_exists('rateb_sync_ops_session_to_company')) {
            rateb_sync_ops_session_to_company($resolved);
        }

        return $resolved > 0 ? $resolved : 0;
    }
}

/** Query params preserved across paginated list links (search, filters). */
if (!function_exists('rateb_list_default_per_page')) {
    function rateb_list_default_per_page(): int
    {
        return 5;
    }
}

if (!function_exists('rateb_list_visible_rows')) {
    /** Visible tbody rows before vertical scroll inside the table wrapper. */
    function rateb_list_visible_rows(): int
    {
        return 5;
    }
}

if (!function_exists('rateb_list_per_page_options')) {
    /** @return list<int> */
    function rateb_list_per_page_options(): array
    {
        return [5, 10, 25, 50, 100];
    }
}

if (!function_exists('rateb_list_per_page')) {
    function rateb_list_per_page(): int
    {
        $default = rateb_list_default_per_page();
        $allowed = rateb_list_per_page_options();
        $raw = isset($_GET['per_page']) ? (int) $_GET['per_page'] : $default;
        return in_array($raw, $allowed, true) ? $raw : $default;
    }
}

if (!function_exists('rateb_list_query_except')) {
    /** @return array<string, string> */
    function rateb_list_query_except(array $except = []): array
    {
        $except = array_merge($except, ['page']);
        $keep = ['q', 'company_id', 'status', 'date_from', 'date_to', 'from', 'to', 'per_page'];
        $out = [];
        foreach ($keep as $key) {
            if (in_array($key, $except, true)) {
                continue;
            }
            if (isset($_GET[$key]) && (string) $_GET[$key] !== '') {
                $out[$key] = (string) $_GET[$key];
            }
        }
        foreach ($_GET as $key => $val) {
            $key = (string) $key;
            if (in_array($key, $except, true) || isset($out[$key])) {
                continue;
            }
            if (preg_match('/_(page|per_page|q)$/', $key) && (string) $val !== '') {
                $out[$key] = (string) $val;
            }
        }
        return $out;
    }
}

if (!function_exists('rateb_url_query')) {
    /** Merge query params into a URL without duplicating keys (fixes company_id=228&company_id=22). */
    function rateb_url_query(string $url, array $query = []): string
    {
        if ($query === []) {
            return $url;
        }
        if (function_exists('rateb_url_set_query_param')) {
            foreach ($query as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                $url = rateb_url_set_query_param($url, (string) $key, (string) $value);
            }

            return $url;
        }
        $sep = strpos($url, '?') !== false ? '&' : '?';

        return $url . $sep . http_build_query($query);
    }
}

if (!function_exists('rateb_list_url')) {
    function rateb_list_url(string $path, array $query = []): string
    {
        return rateb_url_query(rateb_url($path), $query);
    }
}

if (!function_exists('rateb_require_ops_company')) {
    function rateb_require_ops_company(): int
    {
        $id = rateb_resolve_ops_company_id();
        if ($id < 1) {
            \Rateb\App\Core\SessionManager::flash('error', __('select_company_ops'));
            \Rateb\App\Core\Response::redirect(rateb_app_url('accounting'));
            exit;
        }
        return $id;
    }
}

/** @deprecated alias */
if (!function_exists('rateb_resolve_company_id')) {
    function rateb_resolve_company_id(): int
    {
        return rateb_resolve_ops_company_id();
    }
}

if (!function_exists('rateb_url_set_query_param')) {
    /** Set or replace a single query param without duplicating keys. */
    function rateb_url_set_query_param(string $url, string $key, string $value): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }
        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }
        $query[$key] = $value;
        $base = '';
        if (isset($parts['scheme'])) {
            $base .= $parts['scheme'] . '://';
        }
        if (isset($parts['host'])) {
            $base .= $parts['host'];
            if (isset($parts['port'])) {
                $base .= ':' . $parts['port'];
            }
        }
        $base .= $parts['path'] ?? '';
        $qs = http_build_query($query);
        $out = $qs !== '' ? ($base . '?' . $qs) : $base;
        if (!empty($parts['fragment'])) {
            $out .= '#' . $parts['fragment'];
        }

        return $out;
    }
}

if (!function_exists('rateb_url_with_ops_company')) {
    function rateb_url_with_ops_company(string $path): string
    {
        $url = rateb_url($path);
        if (!rateb_is_super_admin()) {
            return $url;
        }
        $id = rateb_resolve_ops_company_id();
        if ($id < 1) {
            return $url;
        }
        if (function_exists('rateb_url_set_query_param')) {
            return rateb_url_set_query_param($url, 'company_id', (string) $id);
        }

        return $url . (strpos($url, '?') === false ? '?' : '&') . 'company_id=' . $id;
    }
}

/** Company operational route under unified /admin shell (ops/ prefix avoids oversight URL clashes). */
if (!function_exists('rateb_app_route')) {
    function rateb_app_route(string $path): string
    {
        $path = ltrim(preg_replace('#^company/#', '', trim($path)), '/');

        // Phase AG: build conflict-root lookup once per process (never grow / re-merge).
        static $conflictLookup = null;
        if ($conflictLookup === null) {
            $conflictRoots = [
                'inventory', 'suppliers', 'assets', 'contracts', 'stock-movements',
                'supplier-evaluations', 'workflows', 'medical-devices', 'reports',
                'notifications', 'accounting', 'chart-of-accounts', 'journal-entries',
                'cost-centers', 'cash-vouchers', 'fiscal-periods', 'bank-accounts',
                'rfq', 'quotations', 'purchase-requests', 'purchase-orders',
                'warehouses', 'warehouse-transfers', 'product-categories',
                'branches', 'branch-dashboard', 'branch-financial', 'branch-transfers',
                'inventory-batches', 'inventory-audits', 'inventory-forecast',
                'supplier-comms', 'supplier-classifications', 'supplier-kpi',
                'contract-renewals', 'tenders', 'asset-maintenance', 'asset-assignments',
                'asset-depreciation', 'device-maintenance', 'device-spare-parts', 'device-warranty',
                'documents', 'profile', 'pos', 'guest-menu', 'logistics',
            ];
            if (function_exists('rateb_company_access_routes_enabled') && rateb_company_access_routes_enabled()) {
                $conflictRoots = array_merge($conflictRoots, [
                    'access-control', 'users', 'roles', 'permissions',
                    'audit-logs', 'support-tickets', 'email-templates', 'sms-templates',
                ]);
            }
            $conflictLookup = array_fill_keys($conflictRoots, true);
        }

        $root = explode('/', $path)[0];
        if (isset($conflictLookup[$root])) {
            return 'admin/ops/' . $path;
        }
        return 'admin/' . $path;
    }
}

if (!function_exists('rateb_app_url')) {
    function rateb_app_url(string $path): string
    {
        return rateb_url_with_ops_company(rateb_app_route($path));
    }
}

if (!function_exists('rateb_nav_counts_allow_cold')) {
    /**
     * Cold COUNT storms must not block HTML first paint.
     * Oversight pages used to allow cold COUNT and felt "everything is slow" —
     * badges use session/stale; pages that need live totals compute them once and warm the session.
     */
    function rateb_nav_counts_allow_cold(): bool
    {
        return false;
    }
}

if (!function_exists('rateb_oversight_pending_approvals_count')) {
    function rateb_oversight_pending_approvals_count(): int
    {
        return rateb_oversight_menu_counts()['total'] ?? 0;
    }
}

if (!function_exists('rateb_oversight_menu_counts')) {
    /** @return array{approvals:int,procurement:int,rfq:int,inventory:int,supplier_evaluations:int,total:int} */
    function rateb_oversight_menu_counts(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $empty = [
            'approvals' => 0,
            'hr' => 0,
            'procurement' => 0,
            'rfq' => 0,
            'inventory' => 0,
            'supplier_evaluations' => 0,
            'company_pending' => 0,
            'total' => 0,
        ];
        if (!rateb_is_super_admin()) {
            $cached = $empty;
            return $cached;
        }
        $sessionKey = 'rateb_oversight_menu_counts_v2';
        $raw = \Rateb\App\Core\SessionManager::get($sessionKey);
        if (is_array($raw) && is_array($raw['data'] ?? null) && (int) ($raw['exp'] ?? 0) > time()) {
            $cached = $raw['data'];
            return $cached;
        }
        // Prefer stale session over a cold COUNT storm (HTML paint / soft-nav / dashboard).
        if (is_array($raw) && is_array($raw['data'] ?? null)) {
            $cached = $raw['data'];
            return $cached;
        }
        $softNav = (isset($_SERVER['HTTP_X_RATEB_NAV_SWAP']) && (string) $_SERVER['HTTP_X_RATEB_NAV_SWAP'] === '1')
            || (isset($_SERVER['HTTP_X_RATEB_PREFETCH']) && (string) $_SERVER['HTTP_X_RATEB_PREFETCH'] === '1');
        if ($softNav || !rateb_nav_counts_allow_cold()) {
            $cached = $empty;
            return $cached;
        }
        try {
            $cached = (new \Rateb\App\Services\ApprovalOversightService())->menuCounts(null);
        } catch (\Throwable $e) {
            $cached = $empty;
        }
        \Rateb\App\Core\SessionManager::set($sessionKey, ['exp' => time() + 300, 'data' => $cached]);
        return $cached;
    }
}

if (!function_exists('rateb_oversight_menu_badge')) {
    function rateb_oversight_menu_badge(string $route): int
    {
        $map = [
            'admin/oversight/approvals' => 'approvals',
            'admin/oversight/hr-approvals' => 'hr',
            'admin/oversight/companies-approvals' => 'company_pending',
            'admin/oversight/procurement' => 'procurement',
            'admin/oversight/rfq' => 'rfq',
            'admin/oversight/inventory' => 'inventory',
            'admin/oversight/supplier-evaluations' => 'supplier_evaluations',
        ];
        $route = ltrim($route, '/');
        $key = $map[$route] ?? '';
        if ($key === '') {
            return 0;
        }
        $counts = rateb_oversight_menu_counts();
        return (int) ($counts[$key] ?? 0);
    }
}

if (!function_exists('rateb_cms_new_leads_count')) {
    function rateb_cms_new_leads_count(): int
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        if (!rateb_is_super_admin() && !rateb_nav_can('cms.leads', 'cms')) {
            $cached = 0;
            return $cached;
        }
        try {
            $cached = (new \Rateb\App\Models\CmsLead())->countNew();
        } catch (\Throwable $e) {
            $cached = 0;
        }
        return $cached;
    }
}

if (!function_exists('rateb_oversight_approve_only')) {
    /** Company ops users cannot approve locally — oversight only. */
    function rateb_oversight_approve_only(): bool
    {
        return true;
    }
}

if (!function_exists('rateb_ops_nav_counts')) {
    /** @return array<string, int> */
    function rateb_ops_nav_counts(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $companyFilter = null;
        if (!rateb_is_super_admin()) {
            $cid = (int) (\Rateb\App\Core\SessionManager::get('rateb_company_id', 0) ?? 0);
            if ($cid < 1) {
                $cached = [];
                return $cached;
            }
            $companyFilter = $cid;
        } elseif (function_exists('rateb_resolve_ops_company_id')) {
            $cid = rateb_resolve_ops_company_id();
            if ($cid > 0) {
                $companyFilter = $cid;
            }
        }
        $sessionKey = 'rateb_ops_nav_counts_' . ($companyFilter ?? 'all');
        $raw = \Rateb\App\Core\SessionManager::get($sessionKey);
        if (is_array($raw) && is_array($raw['data'] ?? null) && (int) ($raw['exp'] ?? 0) > time()) {
            $cached = $raw['data'];
            return $cached;
        }
        // Prefer stale over cold COUNT (same policy as oversight badges).
        if (is_array($raw) && is_array($raw['data'] ?? null)) {
            $cached = $raw['data'];
            return $cached;
        }
        $softNav = (isset($_SERVER['HTTP_X_RATEB_NAV_SWAP']) && (string) $_SERVER['HTTP_X_RATEB_NAV_SWAP'] === '1')
            || (isset($_SERVER['HTTP_X_RATEB_PREFETCH']) && (string) $_SERVER['HTTP_X_RATEB_PREFETCH'] === '1');
        if ($softNav || !rateb_nav_counts_allow_cold()) {
            $cached = [];
            return $cached;
        }
        try {
            $cached = (new \Rateb\App\Services\ApprovalOversightService())->opsNavCounts($companyFilter);
        } catch (\Throwable $e) {
            $cached = [];
        }
        \Rateb\App\Core\SessionManager::set($sessionKey, ['exp' => time() + 300, 'data' => $cached]);
        return $cached;
    }
}

if (!function_exists('rateb_ops_nav_pending_badge')) {
    function rateb_ops_nav_pending_badge(string $resourcePath): int
    {
        $path = ltrim($resourcePath, '/');
        $counts = rateb_ops_nav_counts();
        $direct = (int) ($counts[$path] ?? 0);
        if ($path === 'hr/approvals-inbox') {
            return $direct
                + (int) ($counts['hr/leaves'] ?? 0)
                + (int) ($counts['hr/permission-requests'] ?? 0)
                + (int) ($counts['hr/requests'] ?? 0)
                + (int) ($counts['hr/decisions'] ?? 0)
                + (int) ($counts['hr/payroll'] ?? 0);
        }
        if ($path === 'accounting') {
            return $direct
                + (int) ($counts['journal-entries'] ?? 0)
                + (int) ($counts['cash-vouchers'] ?? 0);
        }
        return $direct;
    }
}

if (!function_exists('rateb_is_branch_appliance_runtime')) {
    /** Local Branch Appliance (SQLite) — not cloud MySQL. */
    function rateb_is_branch_appliance_runtime(): bool
    {
        $runtime = strtolower(trim((string) (getenv('RATEB_RUNTIME') ?: ($_ENV['RATEB_RUNTIME'] ?? ''))));

        return $runtime === 'branch';
    }
}

if (!function_exists('rateb_platform_tenant_nav_company_id')) {
    /**
     * Platform rateb.sa only: honour company.modules when super-admin explicitly
     * passes ?company_id= (tenant preview). Never infer from session shell company.
     */
    function rateb_platform_tenant_nav_company_id(): int
    {
        if (!function_exists('rateb_is_platform_oversight_host') || !rateb_is_platform_oversight_host()) {
            return 0;
        }
        $fromRequest = (int) ($_GET['company_id'] ?? $_POST['company_id'] ?? 0);
        if ($fromRequest < 1) {
            return 0;
        }
        if (function_exists('rateb_ops_company_exists') && !rateb_ops_company_exists($fromRequest)) {
            return 0;
        }

        return $fromRequest;
    }
}

if (!function_exists('rateb_nav_tenant_company_id_for_gate')) {
    /** Company id used for nav module gating on this host. */
    function rateb_nav_tenant_company_id_for_gate(): int
    {
        if (function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
            return rateb_platform_tenant_nav_company_id();
        }

        return rateb_nav_module_company_id();
    }
}

if (!function_exists('rateb_nav_enforce_company_modules')) {
    /**
     * Whether company.modules ceilings apply to navigation / module middleware.
     * Super Admin is never gated (full ERP open). Company users / agency tenants
     * remain limited by their package checkboxes.
     */
    function rateb_nav_enforce_company_modules(): bool
    {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return false;
        }
        if (function_exists('rateb_is_branch_appliance_runtime') && rateb_is_branch_appliance_runtime()) {
            return true;
        }
        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            return true;
        }
        if (function_exists('rateb_erp_is_dedicated_deployment') && rateb_erp_is_dedicated_deployment()) {
            return true;
        }
        if (function_exists('rateb_is_platform_oversight_host') && !rateb_is_platform_oversight_host()) {
            return true;
        }

        return false;
    }
}

if (!function_exists('rateb_nav_module_company_id')) {
    /** Resolve tenant company for nav module gating (agency / dedicated / ops context). */
    function rateb_nav_module_company_id(): int
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        if (function_exists('rateb_resolve_ops_company_id')) {
            $resolved = (int) rateb_resolve_ops_company_id();
            if ($resolved > 0) {
                return $resolved;
            }
        }
        if (class_exists(\Rateb\App\Services\DedicatedTenantPolicy::class)) {
            $primary = (int) \Rateb\App\Services\DedicatedTenantPolicy::primaryCompanyId();
            if ($primary > 0
                && (!function_exists('rateb_ops_company_exists') || rateb_ops_company_exists($primary))) {
                if (function_exists('rateb_sync_ops_session_to_company')) {
                    rateb_sync_ops_session_to_company($primary);
                }

                return $primary;
            }
        }
        $sessionCid = (int) (\Rateb\App\Core\SessionManager::get('rateb_company_id', 0) ?? 0);
        if ($sessionCid > 0
            && (!function_exists('rateb_ops_company_exists') || rateb_ops_company_exists($sessionCid))) {
            return $sessionCid;
        }

        return 0;
    }
}

if (!function_exists('rateb_nav_can')) {
    function rateb_nav_can(string $permission = '', string $module = ''): bool
    {
        // Agency ops-admin must respect the role matrix (access.manage / settings.manage / …).
        // Do not bypass — unchecked permissions must hide nav and block routes.
        if (rateb_is_super_admin()) {
            // Super Admin: full system open (nav + modules). Ops ?company_id= only scopes data.
            return true;
        }
        // Company module pack alone must NOT open nav — require an explicit permission slug.
        if ($permission === '') {
            return $module === '';
        }
        if (!rateb_can($permission)) {
            return false;
        }
        if ($module === '') {
            return true;
        }
        $companyId = rateb_nav_tenant_company_id_for_gate();
        if ($companyId < 1) {
            // Platform staff (no tenant): permission RBAC only — skip company module packs.
            $uid = (int) ($_SESSION['rateb_user_id'] ?? 0);
            if ($uid > 0 && class_exists(\Rateb\App\Services\AuthorizationService::class)) {
                return (new \Rateb\App\Services\AuthorizationService())->userIsPlatformStaff($uid);
            }

            return false;
        }
        static $moduleGate = [];
        $gateKey = $companyId . ':' . $module;
        if (array_key_exists($gateKey, $moduleGate)) {
            return $moduleGate[$gateKey];
        }
        $moduleGate[$gateKey] = (new \Rateb\App\Services\PlanLimitService())->companyHasModule($companyId, $module);

        return $moduleGate[$gateKey];
    }
}

if (!function_exists('rateb_entity_perms')) {
    /** @return array{module:string,view:string,manage:string,export:string} */
    function rateb_entity_perms(string $resource): array
    {
        static $map = null;
        if ($map === null) {
            $file = RATEB_ROOT . '/config/entity-permissions.php';
            $map = is_file($file) ? require $file : [];
            if (class_exists(\Rateb\App\Pos\PosModule::class)) {
                $map = array_merge($map, \Rateb\App\Pos\PosModule::entityPermissions());
            }
            if (class_exists(\Rateb\App\GuestMenu\GuestMenuModule::class)) {
                $map = array_merge($map, \Rateb\App\GuestMenu\GuestMenuModule::entityPermissions());
            }
            if (class_exists(\Rateb\App\Logistics\LogisticsModule::class)) {
                $map = array_merge($map, \Rateb\App\Logistics\LogisticsModule::entityPermissions());
            }
            if (class_exists(\Rateb\App\Marketplace\MarketplaceModule::class)) {
                $map = array_merge($map, \Rateb\App\Marketplace\MarketplaceModule::entityPermissions());
            }
        }
        $resource = ltrim(preg_replace('#^(company/|admin/ops/|admin/)#', '', trim($resource)), '/');
        $candidates = [$resource];
        if ($resource !== '' && str_contains($resource, '/')) {
            $candidates[] = str_replace('/', '-', $resource);
        }
        if ($resource !== '' && str_contains($resource, '-')) {
            $candidates[] = str_replace('-', '/', $resource);
        }
        $row = null;
        foreach ($candidates as $key) {
            if ($key !== '' && isset($map[$key]) && is_array($map[$key])) {
                $row = $map[$key];
                break;
            }
        }
        if (!is_array($row)) {
            return ['module' => '', 'view' => '', 'manage' => '', 'export' => 'reports.export', 'post' => '', 'approve' => ''];
        }
        return [
            'module' => (string) ($row['module'] ?? ''),
            'view' => (string) ($row['view'] ?? ''),
            'manage' => (string) ($row['manage'] ?? ($row['view'] ?? '')),
            'export' => (string) ($row['export'] ?? 'reports.export'),
            'post' => (string) ($row['post'] ?? ''),
            'approve' => (string) ($row['approve'] ?? ''),
        ];
    }
}

if (!function_exists('rateb_entity_view_slugs')) {
    /** @return array<int, string> */
    function rateb_entity_view_slugs(string $resource): array
    {
        $p = rateb_entity_perms($resource);
        return array_values(array_unique(array_filter([
            $p['view'],
            $p['manage'],
            $p['approve'],
            $p['post'],
        ])));
    }
}

if (!function_exists('rateb_user_has_perm')) {
    function rateb_user_has_perm(string $slug): bool
    {
        if ($slug === '' || rateb_is_super_admin()) {
            return true;
        }
        return rateb_can($slug);
    }
}

if (!function_exists('rateb_user_has_any_perm')) {
    /** @param array<int, string> $slugs */
    function rateb_user_has_any_perm(array $slugs): bool
    {
        if (rateb_is_super_admin()) {
            return true;
        }
        foreach ($slugs as $slug) {
            if ($slug !== '' && rateb_can($slug)) {
                return true;
            }
        }
        return $slugs === [];
    }
}

if (!function_exists('rateb_can_view_entity')) {
    function rateb_can_view_entity(string $resource): bool
    {
        if (rateb_is_super_admin()) {
            return true;
        }
        $slugs = rateb_entity_view_slugs($resource);
        if ($slugs === []) {
            return true;
        }
        return rateb_user_has_any_perm($slugs);
    }
}

if (!function_exists('rateb_can_manage_entity')) {
    function rateb_can_manage_entity(string $resource): bool
    {
        if (rateb_is_super_admin()) {
            return true;
        }
        $manage = rateb_entity_perms($resource)['manage'];
        return $manage === '' || rateb_can($manage);
    }
}

if (!function_exists('rateb_can_export_entity')) {
    function rateb_can_export_entity(string $resource): bool
    {
        if (rateb_is_super_admin()) {
            return true;
        }
        $export = rateb_entity_perms($resource)['export'];
        return $export === '' || rateb_can($export);
    }
}

if (!function_exists('rateb_can_post_entity')) {
    function rateb_can_post_entity(string $resource): bool
    {
        if (rateb_is_super_admin()) {
            return true;
        }
        $post = rateb_entity_perms($resource)['post'];
        if ($post !== '') {
            return rateb_can($post);
        }
        return rateb_can_manage_entity($resource);
    }
}

if (!function_exists('rateb_can_approve_entity')) {
    function rateb_can_approve_entity(string $resource): bool
    {
        if (rateb_is_super_admin()) {
            return true;
        }
        $approve = rateb_entity_perms($resource)['approve'];
        if ($approve !== '' && rateb_can($approve)) {
            return true;
        }
        $post = rateb_entity_perms($resource)['post'];
        if ($post !== '' && rateb_can($post)) {
            return true;
        }
        return false;
    }
}

/** Final accounting post always goes through management oversight (company UI never posts directly). */
if (!function_exists('rateb_accounting_final_post_oversight_only')) {
    function rateb_accounting_final_post_oversight_only(): bool
    {
        return true;
    }
}

if (!function_exists('rateb_require_approve')) {
    function rateb_require_approve(string $resource): void
    {
        if (rateb_can_approve_entity($resource)) {
            return;
        }
        \Rateb\App\Core\SessionManager::flash('error', __('access_denied_approve'));
        \Rateb\App\Core\Response::redirect(rateb_app_url($resource));
    }
}

/** Redirect to entity list when manage permission is missing. */
if (!function_exists('rateb_require_manage')) {
    function rateb_require_manage(string $resource): void
    {
        if (rateb_can_manage_entity($resource)) {
            return;
        }
        \Rateb\App\Core\SessionManager::flash('error', __('access_denied'));
        \Rateb\App\Core\Response::redirect(rateb_app_url($resource));
    }
}

if (!function_exists('rateb_require_post')) {
    function rateb_require_post(string $resource): void
    {
        if (rateb_can_post_entity($resource)) {
            return;
        }
        \Rateb\App\Core\SessionManager::flash('error', __('access_denied'));
        \Rateb\App\Core\Response::redirect(rateb_app_url($resource));
    }
}

if (!function_exists('__')) {
    function __(string $key, array $replace = []): string
    {
        static $cache = [];
        $locale = rateb_locale();
        if (!isset($cache[$locale])) {
            $mainFile = RATEB_ROOT . '/config/lang/' . $locale . '.php';
            $fieldFile = RATEB_ROOT . '/config/field-labels-' . $locale . '.php';
            $main = is_file($mainFile) ? require $mainFile : [];
            $fields = is_file($fieldFile) ? require $fieldFile : [];
            $cache[$locale] = array_merge($fields, $main);
        }
        $text = $cache[$locale][$key] ?? $key;
        if ($text === $key && class_exists(\Rateb\App\Pos\PosModule::class)) {
            $posText = \Rateb\App\Pos\PosModule::translate($key, $replace);
            if ($posText !== null) {
                return $posText;
            }
        }
        if ($text === $key && class_exists(\Rateb\App\GuestMenu\GuestMenuModule::class)) {
            $gmText = \Rateb\App\GuestMenu\GuestMenuModule::translate($key, $replace);
            if ($gmText !== null) {
                return $gmText;
            }
        }
        if ($text === $key && class_exists(\Rateb\App\Logistics\LogisticsModule::class)) {
            $logisticsText = \Rateb\App\Logistics\LogisticsModule::translate($key, $replace);
            if ($logisticsText !== null) {
                return $logisticsText;
            }
        }
        if ($text === $key && class_exists(\Rateb\App\Marketplace\MarketplaceModule::class)) {
            $mpText = \Rateb\App\Marketplace\MarketplaceModule::translate($key, $replace);
            if ($mpText !== null) {
                return $mpText;
            }
        }
        foreach ($replace as $k => $v) {
            $text = str_replace(':' . $k, (string) $v, $text);
        }
        // Never paint English prose in a non-English UI when the key was missing.
        if ($text === $key && $locale !== 'en'
            && str_contains($key, ' ')
            && preg_match('/[A-Za-z]{4,}/', $key)
            && !preg_match('/\p{Arabic}/u', $key)
        ) {
            $generic = $cache[$locale]['error'] ?? '';
            if (is_string($generic) && $generic !== '' && $generic !== 'error') {
                return $generic;
            }
        }
        return $text;
    }
}

if (!function_exists('rateb_error_message')) {
    /** User-facing error: translate by code; never leak English into Arabic UI. */
    function rateb_error_message(string $code, string $fallback = ''): string
    {
        $code = trim($code);
        if ($code !== '') {
            $translated = __($code);
            if ($translated !== $code) {
                return $translated;
            }
        }
        $locale = function_exists('rateb_locale') ? rateb_locale() : 'ar';
        if ($locale !== 'en') {
            if ($fallback !== '' && preg_match('/\p{Arabic}/u', $fallback)) {
                return $fallback;
            }
            $generic = __('error');
            return $generic !== 'error' ? $generic : ($code !== '' ? $code : '');
        }
        if ($fallback !== '') {
            return $fallback;
        }
        return $code !== '' ? $code : __('error');
    }
}

if (!function_exists('rateb_status_label')) {
    function rateb_status_label(string $status): string
    {
        $status = trim($status);
        if ($status === '') {
            return '';
        }
        $translated = __($status);
        return $translated !== $status ? $translated : $status;
    }
}

/** Super-admin ops company picker list (cached). */
if (!function_exists('rateb_ops_companies_list')) {
    /** @return list<array<string, mixed>> */
    function rateb_ops_companies_list(int $limit = 200): array
    {
        static $memory = [];
        if (isset($memory[$limit])) {
            return $memory[$limit];
        }
        if (!rateb_is_super_admin()) {
            $memory[$limit] = [];
            return $memory[$limit];
        }
        $key = 'rateb_ops_companies_' . $limit;
        $raw = \Rateb\App\Core\SessionManager::get($key);
        if (is_array($raw) && is_array($raw['data'] ?? null) && (int) ($raw['exp'] ?? 0) > time()) {
            $memory[$limit] = $raw['data'];
            return $memory[$limit];
        }
        try {
            $data = (new \Rateb\App\Models\Company())->all($limit, 0);
        } catch (\Throwable $e) {
            $data = [];
        }
        \Rateb\App\Core\SessionManager::set($key, ['exp' => time() + 120, 'data' => $data]);
        $memory[$limit] = $data;
        return $data;
    }
}

/** Module list-page KPI strip (real DB calculations). */
if (!function_exists('rateb_company_branches_cached')) {
    /** @return list<array<string, mixed>> */
    function rateb_company_branches_cached(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        $key = 'rateb_nav_branches_' . $companyId;
        $raw = \Rateb\App\Core\SessionManager::get($key);
        if (is_array($raw) && is_array($raw['data'] ?? null) && (int) ($raw['exp'] ?? 0) > time()) {
            return $raw['data'];
        }
        try {
            $data = (new \Rateb\App\Services\BranchService())->listForCompany($companyId);
        } catch (\Throwable $e) {
            $data = [];
        }
        \Rateb\App\Core\SessionManager::set($key, ['exp' => time() + 120, 'data' => $data]);
        return $data;
    }
}

/** Module list-page KPI strip (real DB calculations). */
if (!function_exists('rateb_module_page_metrics')) {
    /** @return array<int, array{label: string, value: string, tone?: string, trend?: string}> */
    function rateb_module_page_metrics(?string $route = null): array
    {
        if (!class_exists(\Rateb\App\Services\ModulePageStatsService::class)) {
            return [];
        }
        $route = $route ?? rateb_current_erp_route();
        if ($route === '') {
            return [];
        }
        $companyId = function_exists('rateb_resolve_ops_company_id') ? (int) rateb_resolve_ops_company_id() : 0;
        $cacheKey = 'rateb_module_metrics_' . md5($route . '|' . $companyId);
        $cached = \Rateb\App\Core\SessionManager::get($cacheKey);
        if (is_array($cached) && is_array($cached['data'] ?? null) && (int) ($cached['exp'] ?? 0) > time()) {
            return $cached['data'];
        }
        try {
            $data = (new \Rateb\App\Services\ModulePageStatsService())->forRoute($route);
        } catch (\Throwable $e) {
            error_log('rateb_module_page_metrics: ' . $e->getMessage());
            $data = [];
        }
        \Rateb\App\Core\SessionManager::set($cacheKey, ['exp' => time() + 45, 'data' => $data]);
        return $data;
    }
}
