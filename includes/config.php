<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/config.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/config.php`.
 */
/**
 * PHP 8.0+ helpers for hosts still on PHP 7.4 (e.g. str_contains / str_starts_with).
 */
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        $len = strlen($needle);

        return substr($haystack, -$len) === $needle;
    }
}

// Compatibility mode: allow temporary operation on older PHP versions.
// Keep this non-blocking until infrastructure is upgraded.
if (PHP_VERSION_ID < 80200) {
    error_log('Ratib Pro compatibility mode active on PHP ' . PHP_VERSION . '. Upgrade target remains PHP 8.2+.');
}

/**
 * Main Configuration File
 * Each link/site uses its own data via config/env/{host}.php — no conflict.
 *
 * IMPORTANT – Different country DBs: We use a separate database per country (e.g. Bangladesh,
 * Kenya, etc.). In SINGLE_URL_MODE the active DB is read from control_agencies: when
 * session agency_id is set (control SSO or multi-agency), that row wins; otherwise
 * session country_id with LIMIT 1. Always use the app connection for country-scoped data:
 *   - mysqli: $GLOBALS['conn'] (set by this file)
 *   - PDO: Database::getInstance()->getConnection() — recreates the PDO when $GLOBALS['agency_db']
 *     changes so APIs use the same tenant DB as mysqli (single source of truth for `users`).
 * Do not assume a single global DB; permissions, users, and all app data live in the current country DB.
 */

// EN: Load environment profile first (host/country-specific overrides).
// AR: تحميل ملف البيئة أولاً (تخصيصات حسب النطاق/الدولة).
require_once __DIR__ . '/../config/env/load.php';

// EN: Central event bus bootstrap (safe no-op if unavailable).
// AR: تهيئة ناقل الأحداث المركزي (لا يؤثر إذا لم يكن متاحاً).
// Central event bus bootstrap (safe no-op if unavailable).
$eventBusFile = __DIR__ . '/../admin/core/EventBus.php';
if (is_file($eventBusFile)) {
    require_once $eventBusFile;
    if (function_exists('getRequestId')) {
        getRequestId();
    }
    if (function_exists('registerGlobalEventExceptionHandler')) {
        registerGlobalEventExceptionHandler();
    }
}

// Agency resolver / partial env files may omit this; HR control isolation and lookups need it.
if (!defined('CONTROL_PANEL_DB_NAME')) {
    $_cp = getenv('CONTROL_PANEL_DB_NAME');
    define('CONTROL_PANEL_DB_NAME', ($_cp !== false && $_cp !== '') ? $_cp : 'outratib_control_panel_db');
}

if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_PORT', 3306);
    define('DB_USER', 'outratib_out');
    define('DB_PASS', '9s%BpMr1]dfb');
    define('DB_NAME', 'outratib_out');
}
if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://bangladesh.out.ratib.sa');
}
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Ratib Program');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}
if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}

// Multi-tenant subdomain mode (set true when sa.out.ratib.sa etc. deployed)
if (!defined('MULTI_TENANT_SUBDOMAIN_ENABLED')) {
    define('MULTI_TENANT_SUBDOMAIN_ENABLED', false);  // true = enabled
}

// Tenant query enforcement mode:
// false = warn only, true = throw when query is missing tenant_id under tenant context.
if (!defined('TENANT_STRICT_MODE')) {
    define('TENANT_STRICT_MODE', false);
}
if (!defined('EVENT_STRICT_MODE')) {
    $eventStrict = getenv('EVENT_STRICT_MODE');
    define('EVENT_STRICT_MODE', in_array(strtolower((string) $eventStrict), ['1', 'true', 'yes', 'on'], true));
}
if (!defined('EVENT_ASYNC_QUEUE')) {
    $eventAsync = getenv('EVENT_ASYNC_QUEUE');
    define('EVENT_ASYNC_QUEUE', !in_array(strtolower((string) $eventAsync), ['0', 'false', 'off', 'no'], true));
}

// Queries that are allowed to bypass tenant_id enforcement (strictly system/control metadata only).
// Keep this list small and explicit.
if (!defined('TENANT_QUERY_ALLOWLIST')) {
    define('TENANT_QUERY_ALLOWLIST', [
        'tables' => [
            'tenants',
            'migrations',
            'schema_migrations',
            'migration_history',
            'system_events',
        ],
        'keywords' => [
            'information_schema',
            'show tables',
            'show columns',
            'describe ',
            'explain ',
            'select 1',
            'health_check',
            'heartbeat',
        ],
    ]);
}

// Optional hard mode: block API requests that use full bootstrap but have no tenant context.
// Keep disabled by default for incremental rollout.
if (!defined('TENANT_ENFORCE_CONTEXT_ON_API')) {
    define('TENANT_ENFORCE_CONTEXT_ON_API', false);
}
if (!defined('TENANT_CONTEXT_GUARD_EXCEPTIONS')) {
    define('TENANT_CONTEXT_GUARD_EXCEPTIONS', [
        '/api/tenants/create',
        '/api/ngenius-health',
        '/api/support-chat-health',
    ]);
}

// EN: Optional domain-based tenant resolver (maps HTTP host to tenant context).
// AR: محلل مستأجر اختياري حسب النطاق (يربط HTTP_HOST بسياق المستأجر).
// SaaS domain tenant resolution (HTTP_HOST -> tenants.domain). Off by default to preserve behavior.
// Enable on server with: DOMAIN_TENANT_RESOLUTION_ENABLED=1
if (file_exists(__DIR__ . '/middleware/TenantResolverMiddleware.php')) {
    require_once __DIR__ . '/middleware/TenantResolverMiddleware.php';
    TenantResolverMiddleware::handle();
}

// EN: Initialize immutable tenant execution context for the full request lifecycle.
// AR: تهيئة سياق تنفيذ المستأجر بشكل مركزي وثابت طوال دورة الطلب.
// Centralized tenant execution context initialization (single source for request identity).
require_once __DIR__ . '/../core/TenantExecutionContext.php';
$ctxTenantIdInit = null;
$ctxResolvedFromLegacy = false;
if (function_exists('ratib_request_context')) {
    $ctx = ratib_request_context();
    if (is_array($ctx) && isset($ctx['tenant_id'])) {
        $tmp = (int) $ctx['tenant_id'];
        if ($tmp > 0) {
            $ctxTenantIdInit = $tmp;
        }
    }
}
if ($ctxTenantIdInit === null && defined('REQUEST_TENANT_ID')) {
    $tmp = (int) REQUEST_TENANT_ID;
    if ($tmp > 0) {
        $ctxTenantIdInit = $tmp;
        $ctxResolvedFromLegacy = true;
    }
}
TenantExecutionContext::initialize($ctxTenantIdInit, false, $ctxResolvedFromLegacy);
TenantExecutionContext::lock();

// EN: Strict API guard to block tenant-required endpoints when tenant context is missing.
// AR: حماية صارمة لواجهات API لمنع الوصول لنقاط النهاية التي تتطلب مستأجر بدون سياق صحيح.
if (TENANT_ENFORCE_CONTEXT_ON_API) {
    $requestUri = strtolower((string) ($_SERVER['REQUEST_URI'] ?? ''));
    $isApiRequest = strpos($requestUri, '/api/') !== false;
    if ($isApiRequest) {
        // Endpoint classification:
        // - SYSTEM_ENDPOINT=true    => bypass tenant context guard
        // - TENANT_REQUIRED=true    => enforce tenant context
        // - default (undefined)     => TENANT_REQUIRED=true (secure default)
        $isSystemEndpoint = defined('SYSTEM_ENDPOINT') && SYSTEM_ENDPOINT === true;
        $tenantRequired = defined('TENANT_REQUIRED') ? (TENANT_REQUIRED === true) : true;

        // Backward-compatible safety net for older endpoints not yet classified.
        if (!$isSystemEndpoint && $tenantRequired && defined('TENANT_CONTEXT_GUARD_EXCEPTIONS') && is_array(TENANT_CONTEXT_GUARD_EXCEPTIONS)) {
            foreach (TENANT_CONTEXT_GUARD_EXCEPTIONS as $allowedPath) {
                $allowedPath = strtolower(trim((string) $allowedPath));
                if ($allowedPath !== '' && strpos($requestUri, $allowedPath) !== false) {
                    $isSystemEndpoint = true;
                    error_log('TENANT_ENDPOINT_CLASSIFICATION_FALLBACK endpoint=' . $requestUri . ' classification=SYSTEM_ENDPOINT source=TENANT_CONTEXT_GUARD_EXCEPTIONS');
                    break;
                }
            }
        }

        // Update classification inside centralized execution context.
        TenantExecutionContext::markSystemContext($isSystemEndpoint);

        if (!$isSystemEndpoint && $tenantRequired) {
            try {
                TenantExecutionContext::requireTenantId();
            } catch (RuntimeException $e) {
                error_log('TENANT_CONTEXT_MISSING endpoint=' . $requestUri . ' action=blocked error=' . $e->getMessage());
                http_response_code(403);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'Tenant context is required for this endpoint.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
        } elseif ($isSystemEndpoint) {
            error_log('TENANT_ENDPOINT_CLASSIFICATION endpoint=' . $requestUri . ' classification=SYSTEM_ENDPOINT');
        }
    }
}

require_once __DIR__ . '/../core/bootstrap.php';

if (file_exists(__DIR__ . '/helpers/get_tenant_db.php')) {
    require_once __DIR__ . '/helpers/get_tenant_db.php';
}

// Helper function to get full URL
if (!function_exists('getBaseUrl')) {
    function getBaseUrl() {
        return defined('BASE_URL') ? BASE_URL : '';
    }
}

// Helper function to get asset URL
if (!function_exists('asset')) {
    function asset($path) {
        $base = getBaseUrl();
        $path = ltrim($path, '/');
        return $base . '/' . $path;
    }
}

// Helper function to get API URL
if (!function_exists('apiUrl')) {
    function apiUrl($endpoint) {
        $base = getBaseUrl();
        $endpoint = ltrim($endpoint, '/');
        return $base . '/api/' . $endpoint;
    }
}

// Helper function to get page URL
if (!function_exists('pageUrl')) {
    function pageUrl($page) {
        $base = getBaseUrl();
        $page = ltrim($page, '/');
        return $base . '/pages/' . $page;
    }
}

// Session Configuration - already set in config/env/load.php before session_start()

// Timezone Configuration
date_default_timezone_set('Asia/Riyadh');

// Error Reporting (Production Settings)
// Log all errors but don't display them to users
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to users
ini_set('log_errors', 1); // Log errors to file
ini_set('error_log', __DIR__ . '/../logs/php-errors.log');
// Temporary: show errors on bangladesh/out subdomains to debug HTTP 500 (remove after fixing)
// Never enable for API requests - they must return JSON only
$isApiRequest = !empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false;
if (!empty($_SERVER['HTTP_HOST']) && !$isApiRequest) {
    $h = $_SERVER['HTTP_HOST'];
    if (strpos($h, 'bangladesh.out.ratib.sa') !== false || strpos($h, 'out.ratib.sa') !== false) {
        ini_set('display_errors', 1);
    }
}

// Production Mode Flag
define('PRODUCTION_MODE', true);
if (!defined('DEBUG_MODE')) {
    $debugEnv = getenv('DEBUG_MODE');
    if ($debugEnv === false || $debugEnv === '') {
        $debugEnv = getenv('APP_DEBUG');
    }
    $debugEnabled = false;
    if ($debugEnv !== false && $debugEnv !== null) {
        $debugEnabled = in_array(strtolower(trim((string) $debugEnv)), ['1', 'true', 'on', 'yes'], true);
    }
    define('DEBUG_MODE', $debugEnabled);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Invalidate legacy passwordless control-bridge sessions (not a row in `users`).
if (!empty($_SESSION['logged_in'])
    && (
        (int) ($_SESSION['user_id'] ?? -1) < 1
        || (isset($_SESSION['username']) && is_string($_SESSION['username'])
            && strncmp($_SESSION['username'], 'Control:', 8) === 0)
    )) {
    foreach ([
        'logged_in', 'user_id', 'username', 'role_id', 'role',
        'user_permissions', 'user_specific_permissions',
        'agency_id', 'agency_name', 'country_id', 'country_name',
    ] as $_rk) {
        unset($_SESSION[$_rk]);
    }
}

/**
 * Ratib Pro program login: must match a real row in `users` (positive user_id, not control-bridge).
 * Use this instead of checking only $_SESSION['logged_in'].
 */
if (!function_exists('ratib_program_session_is_valid_user')) {
    function ratib_program_session_is_valid_user(): bool
    {
        if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return false;
        }
        if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] < 1) {
            return false;
        }
        if (isset($_SESSION['username']) && is_string($_SESSION['username'])
            && strncmp($_SESSION['username'], 'Control:', 8) === 0) {
            return false;
        }
        return true;
    }
}

if (!function_exists('ratib_control_pro_bridge')) {
    function ratib_control_pro_bridge(): bool
    {
        return !empty($_SESSION['logged_in'])
            && (
                (array_key_exists('user_id', $_SESSION) && (int) $_SESSION['user_id'] === 0)
                || (isset($_SESSION['username']) && is_string($_SESSION['username']) && strncmp($_SESSION['username'], 'Control:', 8) === 0)
            );
    }
}

/** Partner agency portal (magic-link / optional password): scoped session, no staff permissions. */
if (!function_exists('ratib_partner_portal_clear')) {
    function ratib_partner_portal_clear(): void
    {
        unset($_SESSION['partner_portal_logged_in'], $_SESSION['partner_portal_agency_id']);
    }
}

if (!function_exists('ratib_partner_portal_session_is_valid')) {
    function ratib_partner_portal_session_is_valid(): bool
    {
        return !empty($_SESSION['partner_portal_logged_in'])
            && (int) ($_SESSION['partner_portal_agency_id'] ?? 0) > 0;
    }
}

if (!function_exists('ratib_partner_portal_agency_id')) {
    function ratib_partner_portal_agency_id(): int
    {
        return ratib_partner_portal_session_is_valid() ? (int) $_SESSION['partner_portal_agency_id'] : 0;
    }
}

if (!function_exists('ratib_absolute_public_base')) {
    /**
     * Site root for fully qualified links (email, partner magic URL). When BASE_URL is empty,
     * uses SITE_URL or the current request host so partners get https://host/... not a path-only URL.
     */
    function ratib_absolute_public_base(): string
    {
        $base = defined('BASE_URL') ? (string) BASE_URL : '';
        $base = rtrim($base, '/');
        if ($base !== '' && preg_match('#^https?://#i', $base)) {
            return $base;
        }
        if (defined('SITE_URL') && SITE_URL !== '') {
            return rtrim((string) SITE_URL, '/');
        }
        if ($base !== '') {
            return $base;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
                && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return '';
        }

        return $scheme . '://' . $host;
    }
}

if (!function_exists('ratib_partner_portal_magic_link_url')) {
    /**
     * Full URL for bookmark / sharing (treat like a password — HTTPS only in production).
     */
    function ratib_partner_portal_magic_link_url(string $token): string
    {
        $root = ratib_absolute_public_base();
        $path = '/pages/partner-portal.php';
        $url = ($root !== '' ? $root : '') . $path . '?token=' . rawurlencode($token);
        if ($root === '') {
            $page = pageUrl('partner-portal.php');
            $sep = strpos($page, '?') !== false ? '&' : '?';

            return $page . $sep . 'token=' . rawurlencode($token);
        }

        return $url;
    }
}

if (!function_exists('ratib_nav_url')) {
    /**
     * Internal page URL; appends ?control=1&agency_id= for control-panel SSO users so APIs and SSO stay aligned.
     *
     * @param string $page e.g. 'dashboard.php' or 'cases/cases-table.php'
     * @param string $extraQuery optional fragment without leading '?' e.g. 'open=register'
     */
    function ratib_nav_url($page, $extraQuery = '')
    {
        $u = pageUrl($page);
        if ($extraQuery !== '') {
            $u .= (strpos($u, '?') !== false ? '&' : '?') . $extraQuery;
        }
        if (!ratib_control_pro_bridge()) {
            return $u;
        }
        $aid = (int) ($_SESSION['agency_id'] ?? 0);
        if ($aid <= 0) {
            return $u;
        }
        $qs = 'control=1&agency_id=' . $aid;
        return $u . (strpos($u, '?') !== false ? '&' : '?') . $qs;
    }
}

if (!function_exists('ratib_logout_url')) {
    function ratib_logout_url()
    {
        $u = pageUrl('logout.php');
        if (ratib_control_pro_bridge()) {
            $u .= (strpos($u, '?') !== false ? '&' : '?') . 'control=1';
        }
        return $u;
    }
}

if (!function_exists('ratib_country_dashboard_url')) {
    /**
     * Canonical dashboard URL with country slug when available.
     * Falls back to /pages/dashboard.php when slug is unavailable.
     */
    function ratib_country_dashboard_url($agencyId = 0)
    {
        $agencyId = (int)$agencyId;
        $defaultUrl = pageUrl('dashboard.php');
        if ($agencyId <= 0) {
            $agencyId = (int)($_SESSION['agency_id'] ?? 0);
        }
        if ($agencyId <= 0) {
            return $defaultUrl;
        }
        try {
            $lookupConn = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
            if (!$lookupConn instanceof mysqli) {
                return $defaultUrl;
            }
            $stmt = $lookupConn->prepare(
                "SELECT c.slug AS country_slug
                 FROM control_agencies a
                 LEFT JOIN control_countries c ON a.country_id = c.id
                 WHERE a.id = ? LIMIT 1"
            );
            if (!$stmt) {
                return $defaultUrl;
            }
            $stmt->bind_param('i', $agencyId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
            $stmt->close();
            $slug = trim((string)($row['country_slug'] ?? ''));
            if ($slug === '') {
                return $defaultUrl;
            }
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
            if ($host === '') {
                return $defaultUrl;
            }
            return $scheme . '://' . $host . '/' . rawurlencode($slug);
        } catch (Throwable $e) {
            return $defaultUrl;
        }
    }
}

if (!function_exists('ratib_set_login_context_cookies')) {
    /**
     * Remember last country/agency for post-logout login (also used when PHP session name mismatches).
     */
    function ratib_set_login_context_cookies($countryId, $agencyId) {
        $countryId = (int)$countryId;
        $agencyId = (int)$agencyId;
        $expires = time() + 86400 * 365;
        $path = '/';
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        if ($countryId > 0) {
            setcookie('ratib_last_country_id', (string)$countryId, $expires, $path, '', $secure, true);
            $_COOKIE['ratib_last_country_id'] = (string)$countryId;
        }
        if ($agencyId > 0) {
            setcookie('ratib_last_agency_id', (string)$agencyId, $expires, $path, '', $secure, true);
            $_COOKIE['ratib_last_agency_id'] = (string)$agencyId;
        }
    }
}

if (!function_exists('ratib_control_panel_try_program_sso')) {
    /**
     * Manage Agencies "Open": same browser session uses session name ratib_control (see config/env/load.php).
     * When ?control=1&agency_id= is present and control_logged_in is set, pick an eligible tenant user row
     * and establish a normal Ratib Pro session so the login form can be skipped.
     */
    function ratib_control_panel_try_program_sso(mysqli $tenantConn, int $effectiveAgencyId, int $agencyCountryId): void
    {
        if (!defined('SINGLE_URL_MODE') || !SINGLE_URL_MODE) {
            return;
        }
        if (empty($_GET['control']) || (string)$_GET['control'] !== '1') {
            return;
        }
        if (!isset($_GET['agency_id']) || !ctype_digit((string)$_GET['agency_id'])
            || (int)$_GET['agency_id'] !== $effectiveAgencyId) {
            return;
        }
        if (empty($_SESSION['control_logged_in'])) {
            return;
        }
        if (function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user()) {
            if ((int)($_SESSION['agency_id'] ?? 0) === $effectiveAgencyId) {
                return;
            }
            return;
        }

        $chk = @$tenantConn->query("SHOW TABLES LIKE 'users'");
        if (!$chk || $chk->num_rows === 0) {
            return;
        }

        $colRes = @$tenantConn->query("SHOW COLUMNS FROM users");
        $cols = [];
        if ($colRes) {
            while ($r = $colRes->fetch_assoc()) {
                $cols[] = (string)($r['Field'] ?? '');
            }
        }
        $idCol = in_array('user_id', $cols, true) ? 'user_id' : (in_array('id', $cols, true) ? 'id' : 'user_id');
        $roleCol = in_array('role_id', $cols, true) ? 'role_id' : null;
        $statusCol = in_array('status', $cols, true) ? 'status' : null;

        $select = "{$idCol} AS user_id, username";
        if ($roleCol) {
            $select .= ", {$roleCol} AS role_id";
        } else {
            $select .= ", 1 AS role_id";
        }
        if ($statusCol) {
            $select .= ", {$statusCol} AS status";
        }
        if (in_array('country_id', $cols, true)) {
            $select .= ", country_id";
        }
        if (in_array('tenant_role', $cols, true)) {
            $select .= ", tenant_role";
        }
        if (in_array('is_active', $cols, true)) {
            $select .= ", is_active";
        }
        if (in_array('agency_id', $cols, true)) {
            $select .= ", agency_id";
        }

        $sql = "SELECT {$select} FROM users";
        $res = @$tenantConn->query($sql);
        if (!$res) {
            error_log('control SSO: users query failed: ' . $tenantConn->error);
            return;
        }

        $agencyCountryId = (int)$agencyCountryId;
        $effectiveAgencyId = (int)$effectiveAgencyId;

        $best = null;
        $bestPrio = 999;
        $bestRid = PHP_INT_MAX;
        $bestUid = PHP_INT_MAX;

        while ($u = $res->fetch_assoc()) {
            $uid = (int)($u['user_id'] ?? 0);
            $un = trim((string)($u['username'] ?? ''));
            if ($uid < 1 || $un === '' || strncmp($un, 'Control:', 8) === 0) {
                continue;
            }
            $st = strtolower(trim((string)($u['status'] ?? '')));
            $statusOk = ($st === 'active' || $st === '1' || $st === 'enabled');
            if (!$statusOk && array_key_exists('is_active', $u)) {
                $statusOk = !empty((int)($u['is_active'] ?? 0));
            }
            if (!$statusOk) {
                continue;
            }
            if (array_key_exists('country_id', $u) && $agencyCountryId > 0) {
                $raw = $u['country_id'] ?? null;
                $userCountryId = ($raw !== null && $raw !== '' && (int)$raw > 0) ? (int)$raw : null;
                $tenantRole = $u['tenant_role'] ?? null;
                if ($tenantRole !== 'super_admin') {
                    if ($userCountryId !== null && $userCountryId !== $agencyCountryId) {
                        continue;
                    }
                }
            }
            if (array_key_exists('agency_id', $u) && $effectiveAgencyId > 0) {
                $raw = $u['agency_id'] ?? null;
                $ua = ($raw !== null && $raw !== '' && (int)$raw > 0) ? (int)$raw : 0;
                if ($ua > 0 && $ua !== $effectiveAgencyId) {
                    continue;
                }
            }

            $tr = strtolower(trim((string)($u['tenant_role'] ?? '')));
            $prio = ($tr === 'super_admin') ? 0 : 1;
            $rid = (int)($u['role_id'] ?? 50);
            if ($prio < $bestPrio
                || ($prio === $bestPrio && $rid < $bestRid)
                || ($prio === $bestPrio && $rid === $bestRid && $uid < $bestUid)) {
                $best = $u;
                $bestPrio = $prio;
                $bestRid = $rid;
                $bestUid = $uid;
            }
        }
        $res->free();

        if ($best === null) {
            error_log('control SSO: no eligible user row for agency_id=' . $effectiveAgencyId);
            return;
        }

        $user = $best;
        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['username'] = (string)$user['username'];
        $_SESSION['role_id'] = (int)($user['role_id'] ?? 1);
        $_SESSION['logged_in'] = true;
        $_SESSION['role'] = 'User';
        $_SESSION['control_program_sso'] = true;

        try {
            $rStmt = $tenantConn->prepare("SELECT role_name FROM roles WHERE role_id = ? LIMIT 1");
            if ($rStmt) {
                $ridBind = (int)$user['role_id'];
                $rStmt->bind_param("i", $ridBind);
                $rStmt->execute();
                $rRes = $rStmt->get_result();
                if ($rRes && $rRes->num_rows > 0 && ($rRow = $rRes->fetch_assoc())) {
                    $_SESSION['role'] = trim($rRow['role_name'] ?? '') ?: 'User';
                }
                $rStmt->close();
            }
        } catch (Throwable $e) { /* ignore */ }

        require_once __DIR__ . '/permissions.php';
        $_SESSION['user_permissions'] = getUserPermissions();

        try {
            $ssoPk = ratib_users_primary_key_column($tenantConn);
            $permStmt = $tenantConn->prepare("SELECT permissions FROM users WHERE `{$ssoPk}` = ?");
            if ($permStmt) {
                $puid = (int)$user['user_id'];
                $permStmt->bind_param("i", $puid);
                $permStmt->execute();
                $permResult = $permStmt->get_result();
                if ($permRow = $permResult->fetch_assoc()) {
                    if (!empty($permRow['permissions'])) {
                        $_SESSION['user_specific_permissions'] = json_decode($permRow['permissions'], true);
                    } else {
                        $_SESSION['user_specific_permissions'] = null;
                    }
                }
                $permStmt->close();
            }
        } catch (Throwable $e) {
            $_SESSION['user_specific_permissions'] = null;
        }

        if (function_exists('ratib_set_login_context_cookies')) {
            ratib_set_login_context_cookies((int)($_SESSION['country_id'] ?? 0), (int)($_SESSION['agency_id'] ?? 0));
        }

        error_log('control SSO: session started as user_id=' . (int)$user['user_id'] . ' agency_id=' . $effectiveAgencyId);

        $reqUri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $isApiReq = strpos($reqUri, '/api/') !== false;
        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if (!$isApiReq && $script === 'login.php') {
            header('Location: ' . ratib_country_dashboard_url((int)($_SESSION['agency_id'] ?? 0)));
            exit;
        }
    }
}

if (!function_exists('ratib_url_matches_agency_site')) {
    /**
     * Ensure current request is under configured agency Site URL.
     * Example: site https://out.ratib.sa/indonesia must match /indonesia/login...
     */
    function ratib_url_matches_agency_site($siteUrl)
    {
        $siteUrl = trim((string)$siteUrl);
        if ($siteUrl === '' || !preg_match('/^https?:\/\//i', $siteUrl)) {
            return false;
        }
        $site = @parse_url($siteUrl);
        if (!is_array($site)) return false;
        $siteHost = strtolower((string)($site['host'] ?? ''));
        $sitePath = rtrim((string)($site['path'] ?? ''), '/');
        if ($siteHost === '') return false;

        $reqHostRaw = (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
        $reqHost = strtolower(trim(explode(',', $reqHostRaw)[0] ?? ''));
        // Remove optional ":port" from host comparison.
        if (strpos($reqHost, ':') !== false) {
            $reqHost = explode(':', $reqHost)[0];
        }
        $reqPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $reqPath = rtrim($reqPath, '/');

        // Be tolerant for reverse-proxy HTTPS offload: compare host + path only.
        if ($reqHost !== $siteHost) return false;
        // Path can be rewritten by server rules (/indonesia/login -> /pages/login.php),
        // so enforce host-level match to avoid false negatives.
        return true;
    }
}

if (!function_exists('ratib_halt_for_agency_db_error')) {
    /**
     * Stop execution when agency DB is required but cannot be used.
     * Avoid silent fallback to default DB in single URL mode.
     */
    function ratib_halt_for_agency_db_error($message)
    {
        if (!function_exists('ratib_control_agencies_has_column')) {
            function ratib_control_agencies_has_column(?mysqli $conn, string $column): bool
            {
                static $cache = [];
                if (!($conn instanceof mysqli) || $column === '') {
                    return false;
                }
                $key = spl_object_hash($conn) . ':' . $column;
                if (isset($cache[$key])) {
                    return $cache[$key];
                }
                try {
                    $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
                    if ($safeCol === '') {
                        return false;
                    }
                    $q = @$conn->query("SHOW COLUMNS FROM control_agencies LIKE '" . $conn->real_escape_string($safeCol) . "'");
                    $ok = $q && $q->num_rows > 0;
                    if ($q instanceof mysqli_result) {
                        $q->free();
                    }
                    $cache[$key] = $ok;
                    return $ok;
                } catch (Throwable $e) {
                    return false;
                }
            }
        }
        if (!function_exists('ratib_prepare_one_week_extension_columns')) {
            function ratib_prepare_one_week_extension_columns(?mysqli $conn): void
            {
                if (!($conn instanceof mysqli)) {
                    return;
                }
                if (!ratib_control_agencies_has_column($conn, 'one_week_extension_used')) {
                    @$conn->query("ALTER TABLE control_agencies ADD COLUMN one_week_extension_used TINYINT(1) NOT NULL DEFAULT 0");
                }
                if (!ratib_control_agencies_has_column($conn, 'extension_active_until')) {
                    @$conn->query("ALTER TABLE control_agencies ADD COLUMN extension_active_until DATETIME NULL");
                }
            }
        }

        $msg = is_string($message) && trim($message) !== '' ? trim($message) : 'Agency database configuration error.';
        error_log('Agency DB strict mode: ' . $msg);
        $reqUri = strtolower((string) ($_SERVER['REQUEST_URI'] ?? ''));
        $scriptName = strtolower((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        // Control Center is control-plane scope and must remain accessible
        // even when an agency is suspended/inactive.
        if (strpos($reqUri, '/admin/control-center.php') !== false || strpos($scriptName, '/admin/control-center.php') !== false) {
            return;
        }
        http_response_code(503);
        $publicMsg = 'Agency database is not configured correctly. Please verify DB host, port, user, password, and database name.';
        $reasonCode = 'DB_CONFIG_INVALID';
        $lower = strtolower($msg);
        if (strpos($lower, 'suspended') !== false) {
            $publicMsg = 'This agency is suspended (non-payment). Mark it paid or unsuspend it from Manage Agencies.';
            $reasonCode = 'AGENCY_SUSPENDED';
        } elseif (strpos($lower, 'inactive') !== false) {
            $publicMsg = 'This agency is inactive. Activate it from Manage Agencies before opening Pro.';
            $reasonCode = 'AGENCY_INACTIVE';
        } elseif (strpos($lower, 'site url mismatch') !== false) {
            $publicMsg = 'Current URL does not match this agency Site URL. Open it from Manage Agencies using the correct link.';
            $reasonCode = 'AGENCY_SITE_URL_MISMATCH';
        } elseif (strpos($lower, 'site url') !== false) {
            $publicMsg = 'This agency does not have a Site URL configured. Add Site URL in Manage Agencies before opening Pro.';
            $reasonCode = 'AGENCY_SITE_URL_MISSING';
        } elseif (strpos($lower, 'not found') !== false || strpos($lower, 'mapping') !== false) {
            $publicMsg = 'This agency is not available for access. Verify agency status and country mapping in Manage Agencies.';
            $reasonCode = 'AGENCY_MAPPING_MISSING';
        } elseif (strpos($lower, 'failed to connect') !== false) {
            $reasonCode = 'DB_CONNECT_FAILED';
        } elseif (strpos($lower, 'table not found') !== false) {
            $reasonCode = 'CONTROL_TABLE_MISSING';
        }
        $isControlContext = !empty($_GET['control']) && (string)$_GET['control'] === '1';
        $requestedAgencyId = isset($_GET['agency_id']) && ctype_digit((string)$_GET['agency_id']) ? (int)$_GET['agency_id'] : 0;
        $isOneWeekRequest = isset($_GET['request_one_week_activation']) && (string)$_GET['request_one_week_activation'] === '1';
        if ($reasonCode === 'AGENCY_SUSPENDED' && $isControlContext && $requestedAgencyId > 0 && $isOneWeekRequest) {
            try {
                $lookupConn = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
                if ($lookupConn instanceof mysqli) {
                    ratib_prepare_one_week_extension_columns($lookupConn);
                    $hasUsedCol = ratib_control_agencies_has_column($lookupConn, 'one_week_extension_used');
                    $hasUntilCol = ratib_control_agencies_has_column($lookupConn, 'extension_active_until');
                    $sql = "SELECT is_suspended, is_active"
                        . ($hasUsedCol ? ", one_week_extension_used" : ", 0 AS one_week_extension_used")
                        . ($hasUntilCol ? ", extension_active_until" : ", NULL AS extension_active_until")
                        . " FROM control_agencies WHERE id = ? LIMIT 1";
                    $st = $lookupConn->prepare($sql);
                    if ($st) {
                        $st->bind_param('i', $requestedAgencyId);
                        $st->execute();
                        $res = $st->get_result();
                        $row = $res ? $res->fetch_assoc() : null;
                        $st->close();
                        $status = 'error';
                        if (is_array($row)) {
                            $used = (int)($row['one_week_extension_used'] ?? 0) === 1;
                            if ($used) {
                                $status = 'used';
                            } else {
                                $updates = "UPDATE control_agencies SET is_active = 1, is_suspended = 0";
                                if ($hasUsedCol) {
                                    $updates .= ", one_week_extension_used = 1";
                                }
                                if ($hasUntilCol) {
                                    $updates .= ", extension_active_until = DATE_ADD(NOW(), INTERVAL 7 DAY)";
                                }
                                $updates .= " WHERE id = ? LIMIT 1";
                                $up = $lookupConn->prepare($updates);
                                if ($up) {
                                    $up->bind_param('i', $requestedAgencyId);
                                    $up->execute();
                                    $status = $up->affected_rows >= 0 ? 'success' : 'error';
                                    $up->close();
                                }
                            }
                        }
                        $redirectParams = $_GET;
                        unset($redirectParams['request_one_week_activation']);
                        $redirectParams['activation_status'] = $status;
                        header('Location: ?' . http_build_query($redirectParams));
                        exit;
                    }
                }
            } catch (Throwable $e) {
                error_log('One-week activation request failed: ' . $e->getMessage());
            }
        }
        $isApiRequest = !empty($_SERVER['REQUEST_URI']) && strpos((string)$_SERVER['REQUEST_URI'], '/api/') !== false;
        $outMsg = $publicMsg . ($isControlContext ? (' [Reason: ' . $reasonCode . ']') : '');
        if ($isApiRequest) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => $outMsg,
            ]);
            exit;
        }
        $showSuspendedActions = ($reasonCode === 'AGENCY_SUSPENDED');
        $safeReasonCode = htmlspecialchars($reasonCode, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($publicMsg, ENT_QUOTES, 'UTF-8');
        $currentHost = (string)($_SERVER['HTTP_HOST'] ?? '');
        $agencyLabel = trim((string)($_GET['agency'] ?? ''));
        $agencyIdLabel = trim((string)($_GET['agency_id'] ?? ''));
        $agencyDescriptor = $agencyLabel !== '' ? $agencyLabel : ('Agency #' . ($agencyIdLabel !== '' ? $agencyIdLabel : 'Unknown'));
        $safeAgencyDescriptor = htmlspecialchars($agencyDescriptor, ENT_QUOTES, 'UTF-8');
        $supportEmail = 'support@ratib.sa';
        $subjectBase = 'Agency support - ' . $agencyDescriptor;
        $renewHref = '/admin/control-center.php';
        if ($agencyIdLabel !== '' && ctype_digit($agencyIdLabel)) {
            $renewHref .= '?control=1&agency_id=' . rawurlencode($agencyIdLabel) . '&open=payments';
        } else {
            $renewHref .= '?open=payments';
        }
        $oneWeekParams = $_GET;
        $oneWeekParams['request_one_week_activation'] = '1';
        $requestExtensionHref = '?' . http_build_query($oneWeekParams);
        $contactHref = 'mailto:' . $supportEmail . '?subject=' . rawurlencode($subjectBase . ' - Contact request');
        $reasonLabelMap = [
            'AGENCY_SUSPENDED' => 'Non-payment',
            'AGENCY_INACTIVE' => 'Agency inactive',
            'AGENCY_SITE_URL_MISMATCH' => 'Site URL mismatch',
            'AGENCY_SITE_URL_MISSING' => 'Missing Site URL',
            'AGENCY_MAPPING_MISSING' => 'Agency mapping missing',
            'DB_CONFIG_INVALID' => 'Database configuration issue',
            'DB_CONNECT_FAILED' => 'Database connectivity issue',
            'CONTROL_TABLE_MISSING' => 'Control table missing',
        ];
        $reasonLabel = $reasonLabelMap[$reasonCode] ?? 'Access policy restriction';
        $safeReasonLabel = htmlspecialchars($reasonLabel, ENT_QUOTES, 'UTF-8');
        $suspendedSinceInput = trim((string)($_GET['suspended_since'] ?? ''));
        $suspendedSinceTs = $suspendedSinceInput !== '' ? strtotime($suspendedSinceInput) : false;
        if ($suspendedSinceTs === false) {
            $suspendedSinceTs = time();
        }
        $suspendedSinceText = date('d M Y', $suspendedSinceTs);
        $safeSuspendedSince = htmlspecialchars($suspendedSinceText, ENT_QUOTES, 'UTF-8');
        $graceDaysRaw = isset($_GET['grace_days_left']) ? (int)$_GET['grace_days_left'] : 0;
        $gracePeriodText = $graceDaysRaw > 0 ? ($graceDaysRaw . ' days left') : 'Expired';
        $safeGracePeriod = htmlspecialchars($gracePeriodText, ENT_QUOTES, 'UTF-8');
        $agencyIdDisplay = ($agencyIdLabel !== '' && ctype_digit($agencyIdLabel)) ? $agencyIdLabel : 'N/A';
        $safeAgencyIdDisplay = htmlspecialchars($agencyIdDisplay, ENT_QUOTES, 'UTF-8');
        $safeCurrentHost = htmlspecialchars($currentHost !== '' ? $currentHost : 'N/A', ENT_QUOTES, 'UTF-8');
        $safeSupportEmail = htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8');
        $activationStatus = trim((string)($_GET['activation_status'] ?? ''));
        $activationNoticeHtml = '';
        if ($activationStatus === 'success') {
            $activationNoticeHtml = '<div class="alert-success">One-week activation is enabled. This agency will be auto-suspended again after 7 days if payment is not completed.</div>';
        } elseif ($activationStatus === 'used') {
            $activationNoticeHtml = '<div class="alert-warning">One-week activation was already used for this agency. Please complete payment or contact support.</div>';
        } elseif ($activationStatus === 'error') {
            $activationNoticeHtml = '<div class="alert-error">Unable to activate one week automatically. Please contact support.</div>';
        }
        $safeRenewHref = htmlspecialchars($renewHref, ENT_QUOTES, 'UTF-8');
        $safeRequestExtensionHref = htmlspecialchars($requestExtensionHref, ENT_QUOTES, 'UTF-8');
        $safeContactHref = htmlspecialchars($contactHref, ENT_QUOTES, 'UTF-8');

        $weekPillsHtml = '';
        if ($showSuspendedActions) {
            for ($i = 1; $i <= 1; $i++) {
                $weekLabel = 'Request ' . $i . ' week' . ($i > 1 ? 's' : '');
                $weekText = $i . ' week' . ($i > 1 ? 's' : '');
                $weekHref = 'mailto:' . $supportEmail
                    . '?subject=' . rawurlencode($subjectBase . ' - Extension request (' . $weekText . ')')
                    . '&body=' . rawurlencode("Hello Support,\n\nPlease grant an extension request for " . $weekText . " for " . $agencyDescriptor . ".\n\nHost: " . $currentHost . "\nReason code: " . $reasonCode . "\n\nThank you.");
                $weekPillsHtml .= '<a data-loading-btn class="extension-pill" href="' . htmlspecialchars($weekHref, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($weekLabel, ENT_QUOTES, 'UTF-8') . '</a>';
            }
        }

        $reasonCodeRow = $isControlContext
            ? '<div class="info-row"><span class="info-label">Reason code</span><span class="info-value badge-soft">' . $safeReasonCode . '</span></div>'
            : '';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        $fallbackBase = $host !== '' ? ($scheme . '://' . $host) : '';
        $siteBaseUrl = rtrim((string)(defined('SITE_URL') ? SITE_URL : $fallbackBase), '/');
        $suspensionCssDisk = __DIR__ . '/../css/pages/agency-suspension.css';
        $suspensionJsDisk = __DIR__ . '/../js/pages/agency-suspension.js';
        $homeCssDisk = __DIR__ . '/../css/pages/home-public.css';
        $suspensionCssUrl = $siteBaseUrl . '/css/pages/agency-suspension.css?v=' . (int)(is_file($suspensionCssDisk) ? filemtime($suspensionCssDisk) : time());
        $suspensionJsUrl = $siteBaseUrl . '/js/pages/agency-suspension.js?v=' . (int)(is_file($suspensionJsDisk) ? filemtime($suspensionJsDisk) : time());
        $homeCssUrl = $siteBaseUrl . '/css/pages/home-public.css?v=' . (int)(is_file($homeCssDisk) ? filemtime($homeCssDisk) : time());

        $viewData = [
            'siteBaseUrl' => $siteBaseUrl,
            'homeCssUrl' => $homeCssUrl,
            'suspensionCssUrl' => $suspensionCssUrl,
            'suspensionJsUrl' => $suspensionJsUrl,
            'activationNoticeHtml' => $activationNoticeHtml,
            'safeAgencyDescriptor' => $safeAgencyDescriptor,
            'safeAgencyIdDisplay' => $safeAgencyIdDisplay,
            'safeCurrentHost' => $safeCurrentHost,
            'safeSupportEmail' => $safeSupportEmail,
            'safeReasonLabel' => $safeReasonLabel,
            'safeSuspendedSince' => $safeSuspendedSince,
            'safeGracePeriod' => $safeGracePeriod,
            'reasonCodeRow' => $reasonCodeRow,
            'safeMessage' => $safeMessage,
            'safeRenewHref' => $safeRenewHref,
            'safeRequestExtensionHref' => $safeRequestExtensionHref,
            'safeContactHref' => $safeContactHref,
            'weekPillsHtml' => $weekPillsHtml,
        ];
        require __DIR__ . '/views/agency-suspension.php';
        exit;
    }
}

require_once __DIR__ . '/control_lookup_conn.php';

// When Control opens a specific agency (?control=1&agency_id=...), validate target agency immediately.
// This prevents old/stale program sessions from bypassing suspension/inactive status.
$reqUriForAgencyGuards = strtolower((string) ($_SERVER['REQUEST_URI'] ?? ''));
$scriptForAgencyGuards = strtolower((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$isAdminControlCenterRequest = (strpos($reqUriForAgencyGuards, '/admin/control-center.php') !== false)
    || (strpos($scriptForAgencyGuards, '/admin/control-center.php') !== false);
if (
    (defined('SINGLE_URL_MODE') && SINGLE_URL_MODE)
    && !$isAdminControlCenterRequest
    && !empty($_GET['control']) && (string)$_GET['control'] === '1'
    && isset($_GET['agency_id']) && ctype_digit((string)$_GET['agency_id'])
) {
    $requestedAgencyId = (int)$_GET['agency_id'];
    if ($requestedAgencyId > 0) {
        try {
            $lookupConn = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
            if ($lookupConn instanceof mysqli) {
                $hasSuspCol = function_exists('ratib_control_agencies_has_is_suspended')
                    ? ratib_control_agencies_has_is_suspended($lookupConn)
                    : false;
                $hasOneWeekUsedCol = function_exists('ratib_control_agencies_has_column')
                    ? ratib_control_agencies_has_column($lookupConn, 'one_week_extension_used')
                    : false;
                $hasExtensionUntilCol = function_exists('ratib_control_agencies_has_column')
                    ? ratib_control_agencies_has_column($lookupConn, 'extension_active_until')
                    : false;
                $statusSql = $hasSuspCol
                    ? "SELECT is_active, is_suspended"
                        . ($hasOneWeekUsedCol ? ", one_week_extension_used" : ", 0 AS one_week_extension_used")
                        . ($hasExtensionUntilCol ? ", extension_active_until" : ", NULL AS extension_active_until")
                        . " FROM control_agencies WHERE id = ? LIMIT 1"
                    : "SELECT is_active, 0 AS is_suspended, 0 AS one_week_extension_used, NULL AS extension_active_until FROM control_agencies WHERE id = ? LIMIT 1";
                $st = $lookupConn->prepare($statusSql);
                if ($st) {
                    $st->bind_param("i", $requestedAgencyId);
                    $st->execute();
                    $rs = $st->get_result();
                    if ($rs && $rs->num_rows > 0) {
                        $row = $rs->fetch_assoc();
                        $isActive = (int)($row['is_active'] ?? 0) === 1;
                        $isSuspended = (int)($row['is_suspended'] ?? 0) === 1;
                        $extensionUntilRaw = trim((string)($row['extension_active_until'] ?? ''));
                        $hasExtensionExpired = false;
                        if ($extensionUntilRaw !== '') {
                            $extensionTs = strtotime($extensionUntilRaw);
                            $hasExtensionExpired = $extensionTs !== false && $extensionTs < time();
                        }
                        $st->close();
                        if ($hasExtensionExpired && !$isSuspended && $hasSuspCol) {
                            $autoSuspendSql = "UPDATE control_agencies SET is_suspended = 1 WHERE id = ? LIMIT 1";
                            $autoSuspendSt = $lookupConn->prepare($autoSuspendSql);
                            if ($autoSuspendSt) {
                                $autoSuspendSt->bind_param("i", $requestedAgencyId);
                                $autoSuspendSt->execute();
                                $autoSuspendSt->close();
                            }
                            ratib_halt_for_agency_db_error('Agency is suspended. One-week activation expired.');
                        }
                        if (!$isActive) {
                            ratib_halt_for_agency_db_error('Agency is inactive.');
                        }
                        if ($isSuspended) {
                            ratib_halt_for_agency_db_error('Agency is suspended.');
                        }
                    } else {
                        $st->close();
                        ratib_halt_for_agency_db_error('No active agency DB mapping found for current session.');
                    }
                }
            }
        } catch (Throwable $e) {
            ratib_halt_for_agency_db_error('Agency status check failed: ' . $e->getMessage());
        }
    }
}

// Enforce agency status for active control-bridge sessions on every request.
// This prevents already-open sessions from continuing after suspension/inactivation.
if (
    (defined('SINGLE_URL_MODE') && SINGLE_URL_MODE)
    && !$isAdminControlCenterRequest
    && function_exists('ratib_control_pro_bridge')
    && ratib_control_pro_bridge()
    && !empty($_SESSION['agency_id'])
) {
    $sessAgencyId = (int)$_SESSION['agency_id'];
    if ($sessAgencyId > 0) {
        try {
            $lookupConn = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
            if ($lookupConn instanceof mysqli) {
                $hasSuspCol = function_exists('ratib_control_agencies_has_is_suspended')
                    ? ratib_control_agencies_has_is_suspended($lookupConn)
                    : false;
                $statusSql = $hasSuspCol
                    ? "SELECT is_active, is_suspended FROM control_agencies WHERE id = ? LIMIT 1"
                    : "SELECT is_active, 0 AS is_suspended FROM control_agencies WHERE id = ? LIMIT 1";
                $st = $lookupConn->prepare($statusSql);
                if ($st) {
                    $st->bind_param("i", $sessAgencyId);
                    $st->execute();
                    $rs = $st->get_result();
                    if ($rs && $rs->num_rows > 0) {
                        $row = $rs->fetch_assoc();
                        $isActive = (int)($row['is_active'] ?? 0) === 1;
                        $isSuspended = (int)($row['is_suspended'] ?? 0) === 1;
                        $st->close();
                        if (!$isActive) {
                            ratib_halt_for_agency_db_error('Agency is inactive.');
                        }
                        if ($isSuspended) {
                            ratib_halt_for_agency_db_error('Agency is suspended.');
                        }
                    } else {
                        $st->close();
                        ratib_halt_for_agency_db_error('No active agency DB mapping found for current session.');
                    }
                }
            }
        } catch (Throwable $e) {
            ratib_halt_for_agency_db_error('Agency status check failed: ' . $e->getMessage());
        }
    }
}

// Control → Ratib Pro: optional SSO (ratib_control_panel_try_program_sso) still requires a real `users` row; no synthetic Control:* sessions.

// Ratib Pro only — single connection (no control panel)
if (!isset($GLOBALS['conn']) || $GLOBALS['conn'] === null) {
    try {
        if (function_exists('mysqli_report') && defined('MYSQLI_REPORT_ERROR') && defined('MYSQLI_REPORT_STRICT')) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        }
        $GLOBALS['conn'] = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $GLOBALS['conn']->set_charset("utf8mb4");
        $conn = $GLOBALS['conn'];

        // Single URL mode: switch to tenant DB from session OR from ?control=1&agency_id= (Control "Open" before login).
        $singleUrlMode = defined('SINGLE_URL_MODE') && SINGLE_URL_MODE;
        $sessionCountryId = isset($_SESSION['country_id']) ? (int)$_SESSION['country_id'] : 0;
        $sessionAgencyId = isset($_SESSION['agency_id']) ? (int)$_SESSION['agency_id'] : 0;
        $sessionLoggedIn = !empty($_SESSION['logged_in']);
        $getControl = !empty($_GET['control']) && (string)$_GET['control'] === '1';
        $getAgencyId = isset($_GET['agency_id']) && ctype_digit((string)$_GET['agency_id']) ? (int)$_GET['agency_id'] : 0;
        $openAgencyContext = $singleUrlMode && $getControl && $getAgencyId > 0;

        // Control "Open" passes ?control=1&agency_id=X — that must win over a stale session from another agency.
        // Otherwise the previous tenant's agency_id stays in $_SESSION and every Open keeps the wrong DB/users.
        if ($openAgencyContext && $getAgencyId > 0) {
            $targetAg = $getAgencyId;
            $prevAg = (int)($_SESSION['agency_id'] ?? 0);
            $programOk = function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user();
            if ($prevAg > 0 && $prevAg !== $targetAg) {
                if ($programOk) {
                    foreach ([
                        'logged_in', 'user_id', 'username', 'role_id', 'role',
                        'user_permissions', 'user_specific_permissions', 'control_program_sso',
                    ] as $_k) {
                        unset($_SESSION[$_k]);
                    }
                }
                unset(
                    $_SESSION['agency_id'],
                    $_SESSION['agency_name'],
                    $_SESSION['country_id'],
                    $_SESSION['country_name']
                );
            }
            $sessionCountryId = isset($_SESSION['country_id']) ? (int)$_SESSION['country_id'] : 0;
            $sessionAgencyId = isset($_SESSION['agency_id']) ? (int)$_SESSION['agency_id'] : 0;
            $sessionLoggedIn = !empty($_SESSION['logged_in']);
        }

        $effectiveAgencyId = ($openAgencyContext && $getAgencyId > 0)
            ? $getAgencyId
            : ($sessionAgencyId > 0 ? $sessionAgencyId : 0);
        $mustUseAgencyDb = $singleUrlMode && (
            ($sessionLoggedIn && ($sessionAgencyId > 0 || $sessionCountryId > 0))
            || ($openAgencyContext && $effectiveAgencyId > 0)
        );
        if ($mustUseAgencyDb && $conn instanceof mysqli) {
            $lookupConn = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
            if (!$lookupConn) {
                $lookupConn = $conn;
            }
            $chk = @$lookupConn->query("SHOW TABLES LIKE 'control_agencies'");
            if ($chk && $chk->num_rows > 0) {
                $row = null;
                $susp = function_exists('ratib_control_agency_active_fragment')
                    ? ratib_control_agency_active_fragment($lookupConn, 'a')
                    : '1=1';
                if ($effectiveAgencyId > 0) {
                    $sqlAg = "SELECT a.id AS agency_row_id, a.name AS agency_name, a.country_id, c.slug AS country_slug, a.db_host, a.db_port, a.db_user, a.db_pass, a.db_name, a.site_url
                              FROM control_agencies a
                              LEFT JOIN control_countries c ON c.id = a.country_id
                              WHERE a.id = ? AND a.is_active = 1 AND {$susp} LIMIT 1";
                    $stmtA = $lookupConn->prepare($sqlAg);
                    if ($stmtA) {
                        $stmtA->bind_param("i", $effectiveAgencyId);
                        $stmtA->execute();
                        $resA = $stmtA->get_result();
                        if ($resA && $resA->num_rows > 0) {
                            $row = $resA->fetch_assoc();
                        }
                        $stmtA->close();
                    }
                }
                if ($row === null && $effectiveAgencyId <= 0 && $sessionCountryId > 0) {
                    $sqlCo = "SELECT a.id AS agency_row_id, a.name AS agency_name, a.country_id, c.slug AS country_slug, a.db_host, a.db_port, a.db_user, a.db_pass, a.db_name, a.site_url
                              FROM control_agencies a
                              LEFT JOIN control_countries c ON c.id = a.country_id
                              WHERE a.country_id = ? AND a.is_active = 1 AND {$susp} LIMIT 1";
                    $stmt = $lookupConn->prepare($sqlCo);
                    if ($stmt) {
                        $stmt->bind_param("i", $sessionCountryId);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        if ($res && $res->num_rows > 0) {
                            $row = $res->fetch_assoc();
                        }
                        $stmt->close();
                    }
                }
                if ($row !== null) {
                    // Enforce Site URL only for page/navigation requests.
                    // Internal API calls (/api/...) should keep working under the active session agency.
                    $reqUri = (string)($_SERVER['REQUEST_URI'] ?? '');
                    $isApiReq = strpos($reqUri, '/api/') !== false;
                    if (!$isApiReq && $effectiveAgencyId > 0 && trim((string)($row['site_url'] ?? '')) === '') {
                        ratib_halt_for_agency_db_error('Agency site URL is missing.');
                    }
                    if (!$isApiReq && $effectiveAgencyId > 0 && !ratib_url_matches_agency_site($row['site_url'] ?? '')) {
                        ratib_halt_for_agency_db_error('Agency site URL mismatch.');
                    }
                    $agencyHelper = __DIR__ . '/../control-panel/api/control/agency-db-helper.php';
                    if (is_readable($agencyHelper)) {
                        require_once $agencyHelper;
                    }
                    $countryIdForHelper = (int)($row['country_id'] ?? 0);
                    if ($countryIdForHelper <= 0 && $sessionCountryId > 0) {
                        $countryIdForHelper = $sessionCountryId;
                    }
                    $acct = function_exists('getAgencyDbConnection')
                        ? getAgencyDbConnection($row, $countryIdForHelper)
                        : null;
                    if (!$acct || empty($acct['conn']) || !($acct['conn'] instanceof mysqli)) {
                        if (!function_exists('getAgencyDbConnection')) {
                            try {
                                $port = (int)($row['db_port'] ?? 3306);
                                $countryConn = new mysqli(
                                    $row['db_host'],
                                    $row['db_user'],
                                    $row['db_pass'],
                                    $row['db_name'],
                                    $port
                                );
                                $countryConn->set_charset("utf8mb4");
                                if (function_exists('ratib_ensure_minimal_ratib_pro_schema')) {
                                    ratib_ensure_minimal_ratib_pro_schema($countryConn);
                                }
                                $conn->close();
                                $GLOBALS['conn'] = $countryConn;
                                $conn = $countryConn;
                                $GLOBALS['agency_db'] = [
                                    'host' => $row['db_host'],
                                    'port' => $port,
                                    'db'   => $row['db_name'],
                                    'user' => $row['db_user'],
                                    'pass' => $row['db_pass'],
                                ];
                            } catch (Exception $e) {
                                ratib_halt_for_agency_db_error('Failed to connect to agency DB: ' . $e->getMessage());
                            }
                        } else {
                            $detail = function_exists('getAgencyDbConnectionLastError')
                                ? trim((string) getAgencyDbConnectionLastError())
                                : '';
                            $msg = $detail !== ''
                                ? ('Failed to connect to agency DB: ' . $detail)
                                : 'Failed to connect to agency DB.';
                            ratib_halt_for_agency_db_error($msg);
                        }
                    } else {
                        $countryConn = $acct['conn'];
                        $conn->close();
                        $GLOBALS['conn'] = $countryConn;
                        $conn = $countryConn;
                        $GLOBALS['agency_db'] = [
                            'host' => $acct['connect_host'] ?? $row['db_host'],
                            'port' => (int)($acct['connect_port'] ?? ($row['db_port'] ?? 3306)),
                            'db'   => $acct['db_name'] ?? $row['db_name'],
                            'user' => $acct['connect_user'] ?? $row['db_user'],
                            'pass' => $acct['connect_pass'] ?? $row['db_pass'],
                        ];
                        if (!empty($acct['use_country_filter'])) {
                            $GLOBALS['agency_db']['use_country_filter'] = true;
                        }
                    }
                    // Remember tenant for login + System Settings (per-agency users) before program session exists.
                    if ($openAgencyContext && !$sessionLoggedIn && $effectiveAgencyId > 0 && is_array($row)) {
                        $_SESSION['agency_id'] = $effectiveAgencyId;
                        $_SESSION['country_id'] = (int)($row['country_id'] ?? 0);
                        $an = trim((string)($row['agency_name'] ?? ''));
                        if ($an !== '') {
                            $_SESSION['agency_name'] = $an;
                        }
                    }
                    if ($openAgencyContext && !$sessionLoggedIn && $effectiveAgencyId > 0 && !empty($_SESSION['control_logged_in'])
                        && $conn instanceof mysqli) {
                        ratib_control_panel_try_program_sso($conn, $effectiveAgencyId, (int)($row['country_id'] ?? 0));
                    }
                } else {
                    if ($effectiveAgencyId > 0) {
                        $hasSuspCol = function_exists('ratib_control_agencies_has_is_suspended')
                            ? ratib_control_agencies_has_is_suspended($lookupConn)
                            : false;
                        $statusSql = $hasSuspCol
                            ? "SELECT is_active, is_suspended FROM control_agencies WHERE id = ? LIMIT 1"
                            : "SELECT is_active, 0 AS is_suspended FROM control_agencies WHERE id = ? LIMIT 1";
                        $rawStmt = $lookupConn->prepare($statusSql);
                        if ($rawStmt) {
                            $rawStmt->bind_param("i", $effectiveAgencyId);
                            $rawStmt->execute();
                            $rawRes = $rawStmt->get_result();
                            if ($rawRes && $rawRes->num_rows > 0) {
                                $raw = $rawRes->fetch_assoc();
                                $isActiveRaw = (int)($raw['is_active'] ?? 0);
                                $isSuspRaw = (int)($raw['is_suspended'] ?? 0);
                                $rawStmt->close();
                                if ($isActiveRaw !== 1) {
                                    ratib_halt_for_agency_db_error('Agency is inactive.');
                                }
                                if ($isSuspRaw === 1) {
                                    ratib_halt_for_agency_db_error('Agency is suspended.');
                                }
                            } else {
                                $rawStmt->close();
                            }
                        }
                    }
                    ratib_halt_for_agency_db_error('No active agency DB mapping found for current session.');
                }
            } else {
                ratib_halt_for_agency_db_error('control_agencies table not found for agency DB resolution.');
            }
        }
    } catch (Throwable $e) {
        error_log("Failed to create database connection in config.php: " . $e->getMessage());
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
}

// Make $conn available globally if it was created
if (isset($GLOBALS['conn']) && $GLOBALS['conn'] !== null) {
    $conn = $GLOBALS['conn'];
}

// Multi-Tenant: set true when ready. Disabled to restore site.
define('MULTI_TENANT_ENABLED', false);
