<?php
declare(strict_types=1);

if (!defined('RATEB_ROOT')) {
    $root = realpath(dirname(__DIR__));
    define('RATEB_ROOT', str_replace('\\', '/', $root !== false ? $root : dirname(__DIR__)));
}

define('RATEB_VIEWS_PATH', RATEB_ROOT . DIRECTORY_SEPARATOR . 'views');
define('RATEB_STORAGE_PATH', RATEB_ROOT . '/storage');

define('RATEB_APP_NAME', 'RTAB');
define('RATEB_APP_VERSION', '1.0.0');
define('RATEB_ASSET_BUILD', '20260621-po-show-design');

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
        $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        if (in_array($host, ['rateb.sa', 'www.rateb.sa'], true)) {
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
        if (defined('SITE_URL') && (string) SITE_URL !== '') {
            return rtrim((string) SITE_URL, '/');
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'rateb.sa';
        return $scheme . '://' . $host;
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

if (!function_exists('rateb_url')) {
    /** Always use standalone public URLs (no CP session required). */
    function rateb_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        return rateb_public_url($path !== '' ? $path : 'admin');
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
            'suppliers', 'supplier-comms', 'supplier-evaluations', 'supplier-classifications', 'supplier-kpi',
            'contracts', 'contract-renewals', 'tenders', 'assets',
            'asset-maintenance', 'asset-assignments', 'asset-depreciation',
            'medical-devices', 'device-maintenance', 'device-spare-parts', 'device-warranty',
            'accounting', 'chart-of-accounts', 'journal-entries', 'cash-vouchers', 'fiscal-periods',
            'cost-centers', 'bank-accounts', 'documents', 'workflows', 'notifications', 'profile', 'reports',
            'hr',
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

if (!function_exists('rateb_bootstrap_ops_tenant')) {
    /** Ensure TenantContext has company for ops lookups and CRUD (super admin ?company_id=). */
    function rateb_bootstrap_ops_tenant(): void
    {
        $cid = \Rateb\App\Core\TenantContext::companyId();
        if ($cid !== null && $cid > 0) {
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

if (!function_exists('rateb_init_marketing_locale')) {
    function rateb_init_marketing_locale(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (!empty($_SESSION['rateb_locale']) && in_array((string) $_SESSION['rateb_locale'], RATEB_SUPPORTED_LOCALES, true)) {
            return;
        }
        if (!empty($_COOKIE['rateb_locale'])) {
            $cookieLang = strtolower(trim((string) $_COOKIE['rateb_locale']));
            if (in_array($cookieLang, RATEB_SUPPORTED_LOCALES, true)) {
                $_SESSION['rateb_locale'] = $cookieLang;
            }
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
    }
}

if (!function_exists('rateb_locale_switch_url')) {
    function rateb_locale_switch_url(string $locale): string
    {
        if (!in_array($locale, RATEB_SUPPORTED_LOCALES, true)) {
            $locale = RATEB_DEFAULT_LOCALE;
        }
        return rateb_url_query(rateb_url('locale/' . $locale), [
            'next' => rateb_current_public_path('site'),
        ]);
    }
}

if (!function_exists('rateb_marketing_register_url')) {
    function rateb_marketing_register_url(string $plan = 'gold', int $years = 1, array $extra = []): string
    {
        $query = array_merge(
            [
                'open' => 'register',
                'plan' => $plan,
                'years' => $years,
            ],
            $extra
        );

        return rateb_url_query(rateb_url('site'), $query) . '#register';
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
        $userId = (int) ($_SESSION['rateb_user_id'] ?? 0);
        if ($userId <= 0 || $slug === '') {
            return $slug === '';
        }
        static $cache = [];
        if (!isset($cache[$userId])) {
            $cache[$userId] = (new \Rateb\App\Services\AuthorizationService())->userPermissionSlugs($userId);
        }
        return in_array($slug, $cache[$userId], true);
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
        $classes = ['rateb-cell-clip'];
        $dir = '';
        $mode = 'text';
        $badge = '';

        if ($type === 'status') {
            $statusKey = (string) $value;
            $label = $statusKey;
            if (function_exists('__')) {
                foreach (['depreciation_status_', 'status_', ''] as $prefix) {
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

if (!function_exists('rateb_permission_label')) {
    function rateb_permission_label(array $row): string
    {
        $slug = (string) ($row['slug'] ?? '');
        $nameAr = (string) ($row['name_ar'] ?? '');
        $corrupted = $nameAr === '' || strpos($nameAr, '?') !== false || preg_match('/^\?+$/', $nameAr) === 1;

        if (rateb_locale() === 'ar') {
            if (!$corrupted && $nameAr !== '') {
                return $nameAr;
            }
            static $labels = null;
            if ($labels === null) {
                $file = (defined('RATEB_ROOT') ? RATEB_ROOT : '') . '/config/permission-labels-ar.php';
                $labels = is_file($file) ? require $file : [];
                if (!is_array($labels)) {
                    $labels = [];
                }
            }
            if ($slug !== '' && isset($labels[$slug][0])) {
                return (string) $labels[$slug][0];
            }
        }
        return (string) ($row['name'] ?? '');
    }
}

if (!function_exists('rateb_label')) {
    function rateb_label(string $labelOrKey): string
    {
        $key = strtolower(str_replace([' ', '-'], '_', trim($labelOrKey)));
        $translated = __($key);
        return $translated !== $key ? $translated : $labelOrKey;
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

/** Resolve active company for ops routes (session, then ?company_id=, then ops session). */
if (!function_exists('rateb_ops_company_exists')) {
    function rateb_ops_company_exists(int $companyId): bool
    {
        if ($companyId < 1) {
            return false;
        }
        try {
            $row = (new \Rateb\App\Models\Company())->find($companyId);
            return is_array($row) && (int) ($row['id'] ?? 0) === $companyId;
        } catch (\Throwable $e) {
            return false;
        }
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
    }
}

if (!function_exists('rateb_adopt_ops_company_id')) {
    function rateb_adopt_ops_company_id(int $companyId): int
    {
        if ($companyId < 1 || !rateb_ops_company_exists($companyId)) {
            rateb_clear_ops_company_session();
            return 0;
        }
        \Rateb\App\Core\TenantContext::setCompanyId($companyId);
        return $companyId;
    }
}

if (!function_exists('rateb_resolve_ops_company_id')) {
    function rateb_resolve_ops_company_id(): int
    {
        $sessionCompany = (int) (\Rateb\App\Core\SessionManager::get('rateb_company_id', 0) ?? 0);
        if ($sessionCompany > 0) {
            $valid = rateb_adopt_ops_company_id($sessionCompany);
            if ($valid > 0) {
                return $valid;
            }
        }

        $fromRequest = (int) ($_GET['company_id'] ?? $_POST['company_id'] ?? 0);
        if ($fromRequest > 0) {
            $valid = rateb_adopt_ops_company_id($fromRequest);
            if ($valid > 0) {
                \Rateb\App\Core\SessionManager::set('rateb_ops_company_id', $valid);
                return $valid;
            }
        }

        $opsCompany = (int) (\Rateb\App\Core\SessionManager::get('rateb_ops_company_id', 0) ?? 0);
        if ($opsCompany > 0) {
            $valid = rateb_adopt_ops_company_id($opsCompany);
            if ($valid > 0) {
                return $valid;
            }
        }

        $ctx = \Rateb\App\Core\TenantContext::companyId();
        if ($ctx !== null && $ctx > 0) {
            return rateb_adopt_ops_company_id((int) $ctx);
        }
        return 0;
    }
}

/** Query params preserved across paginated list links (search, filters). */
if (!function_exists('rateb_list_query_except')) {
    /** @return array<string, string> */
    function rateb_list_query_except(array $except = []): array
    {
        $except = array_merge($except, ['page']);
        $keep = ['q', 'company_id', 'status', 'date_from', 'date_to', 'from', 'to'];
        $out = [];
        foreach ($keep as $key) {
            if (in_array($key, $except, true)) {
                continue;
            }
            if (isset($_GET[$key]) && (string) $_GET[$key] !== '') {
                $out[$key] = (string) $_GET[$key];
            }
        }
        return $out;
    }
}

if (!function_exists('rateb_url_query')) {
    /** Append query string to a URL that may already contain ?company_id=… */
    function rateb_url_query(string $url, array $query = []): string
    {
        if ($query === []) {
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
        return $url . (strpos($url, '?') === false ? '?' : '&') . 'company_id=' . $id;
    }
}

/** Company operational route under unified /admin shell (ops/ prefix avoids oversight URL clashes). */
if (!function_exists('rateb_app_route')) {
    function rateb_app_route(string $path): string
    {
        $path = ltrim(preg_replace('#^company/#', '', trim($path)), '/');
        static $conflictRoots = [
            'inventory', 'suppliers', 'assets', 'contracts', 'stock-movements',
            'supplier-evaluations', 'workflows', 'medical-devices', 'reports',
            'notifications', 'accounting', 'chart-of-accounts', 'journal-entries',
            'cost-centers', 'cash-vouchers', 'fiscal-periods', 'bank-accounts',
            'rfq', 'quotations', 'purchase-requests', 'purchase-orders',
            'warehouses', 'warehouse-transfers', 'product-categories',
            'inventory-batches', 'inventory-audits', 'inventory-forecast',
            'supplier-comms', 'supplier-classifications', 'supplier-kpi',
            'contract-renewals', 'tenders', 'asset-maintenance', 'asset-assignments',
            'asset-depreciation', 'device-maintenance', 'device-spare-parts', 'device-warranty',
            'documents', 'profile',
        ];
        $root = explode('/', $path)[0];
        if (in_array($root, $conflictRoots, true)) {
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

if (!function_exists('rateb_oversight_pending_approvals_count')) {
    function rateb_oversight_pending_approvals_count(): int
    {
        if (!rateb_is_super_admin() || !rateb_nav_can('workflows.view')) {
            return 0;
        }
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $cached = (int) ((new \Rateb\App\Services\ApprovalOversightService())->summary(null)['total'] ?? 0);
        } catch (\Throwable $e) {
            $cached = 0;
        }
        return $cached;
    }
}

if (!function_exists('rateb_nav_can')) {
    function rateb_nav_can(string $permission = '', string $module = ''): bool
    {
        if (rateb_is_super_admin()) {
            return $permission === '' || rateb_can($permission);
        }
        if ($permission !== '' && !rateb_can($permission)) {
            return false;
        }
        if ($module === '') {
            return true;
        }
        $companyId = (int) ($_SESSION['rateb_company_id'] ?? 0);
        if ($companyId < 1) {
            return false;
        }
        return (new \Rateb\App\Services\PlanLimitService())->companyHasModule($companyId, $module);
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
        }
        $resource = ltrim(preg_replace('#^(company/|admin/ops/|admin/)#', '', trim($resource)), '/');
        $row = $map[$resource] ?? null;
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
            $file = RATEB_ROOT . '/config/lang/' . $locale . '.php';
            $cache[$locale] = is_file($file) ? require $file : [];
        }
        $text = $cache[$locale][$key] ?? $key;
        foreach ($replace as $k => $v) {
            $text = str_replace(':' . $k, (string) $v, $text);
        }
        return $text;
    }
}
