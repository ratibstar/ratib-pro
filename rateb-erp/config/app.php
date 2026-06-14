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
define('RATEB_ASSET_BUILD', '20260614-acctfix2');

if (defined('RATEB_CP_ENTRY') && defined('RATEB_CP_APP_URL')) {
    define('RATEB_CP_MODE', true);
    define('RATEB_BASE_URL', (string) RATEB_CP_APP_URL);
} else {
    define('RATEB_CP_MODE', false);
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = rtrim(str_replace('/public/index.php', '', $scriptName), '/');
    define('RATEB_BASE_URL', $basePath !== '' ? $basePath : '/rateb-erp/public');
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
        $host = $_SERVER['HTTP_HOST'] ?? 'out.ratib.sa';
        return $scheme . '://' . $host;
    }
}

if (!function_exists('rateb_public_url')) {
    /** Direct ERP URL — works without Control Panel login. */
    function rateb_public_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        $base = rateb_site_origin() . '/rateb-erp/public';
        return $path === '' ? $base : $base . '/' . $path;
    }
}

if (!function_exists('rateb_asset')) {
    function rateb_asset(string $path): string
    {
        $path = ltrim($path, '/');
        $ver = defined('RATEB_ASSET_BUILD') ? (string) RATEB_ASSET_BUILD : '1';
        $suffix = '?v=' . rawurlencode($ver);
        return rateb_public_url('assets/' . $path) . $suffix;
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

if (!function_exists('rateb_current_public_path')) {
    /** Path under /public/ for locale switch return (e.g. site, site/faq). */
    function rateb_current_public_path(string $fallback = 'site'): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $base = defined('RATEB_BASE_URL') ? rtrim((string) RATEB_BASE_URL, '/') : '/rateb-erp/public';
        $prefix = $base . '/';
        $pos = strpos($uri, $prefix);
        if ($pos === false) {
            return $fallback;
        }
        $rest = substr($uri, $pos + strlen($prefix));
        $path = strtok($rest, '?') ?: '';
        $path = ltrim((string) $path, '/');
        if ($path === '' || strpos($path, 'locale/') === 0) {
            return $fallback;
        }
        return $path;
    }
}

if (!function_exists('rateb_locale')) {
    function rateb_locale(): string
    {
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

if (!function_exists('rateb_permission_label')) {
    function rateb_permission_label(array $row): string
    {
        if (rateb_locale() === 'ar' && !empty($row['name_ar'])) {
            return (string) $row['name_ar'];
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
        return !empty($_SESSION['rateb_is_super_admin']);
    }
}

/** Resolve active company for ops routes (session, then ?company_id=, then ops session). */
if (!function_exists('rateb_resolve_ops_company_id')) {
    function rateb_resolve_ops_company_id(): int
    {
        $sessionCompany = (int) (\Rateb\App\Core\SessionManager::get('rateb_company_id', 0) ?? 0);
        if ($sessionCompany > 0) {
            \Rateb\App\Core\TenantContext::setCompanyId($sessionCompany);
            return $sessionCompany;
        }

        $fromRequest = (int) ($_GET['company_id'] ?? $_POST['company_id'] ?? 0);
        if ($fromRequest > 0) {
            \Rateb\App\Core\SessionManager::set('rateb_ops_company_id', $fromRequest);
            \Rateb\App\Core\TenantContext::setCompanyId($fromRequest);
            return $fromRequest;
        }

        $opsCompany = (int) (\Rateb\App\Core\SessionManager::get('rateb_ops_company_id', 0) ?? 0);
        if ($opsCompany > 0) {
            \Rateb\App\Core\TenantContext::setCompanyId($opsCompany);
            return $opsCompany;
        }

        $ctx = \Rateb\App\Core\TenantContext::companyId();
        return $ctx !== null && $ctx > 0 ? (int) $ctx : 0;
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
            'rfq', 'quotations',
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
        $p = rateb_entity_perms($resource);
        if ($p['view'] === '' && $p['manage'] === '') {
            return true;
        }
        return rateb_user_has_any_perm(array_values(array_unique(array_filter([$p['view'], $p['manage']]))));
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
