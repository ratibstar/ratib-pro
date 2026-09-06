<?php
declare(strict_types=1);

namespace Rateb\App\Core;

final class Bootstrap
{
    private static bool $booted = false;

    public static function init(string $basePath): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        $basePath = self::resolveRootPath($basePath);
        if (!defined('RATEB_ROOT')) {
            define('RATEB_ROOT', $basePath);
        }

        $branchServeBootstrap = $basePath . '/app/Core/BranchServeEnvBootstrap.php';
        if (is_file($branchServeBootstrap)) {
            require_once $branchServeBootstrap;
            BranchServeEnvBootstrap::apply($basePath);
        }

        if (PHP_VERSION_ID < 70400) {
            http_response_code(500);
            exit('RATEB ERP requires PHP 7.4 or newer.');
        }

        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        date_default_timezone_set('Asia/Riyadh');

        $mbPolyfill = $basePath . '/app/helpers/mbstring-polyfill.php';
        if (is_file($mbPolyfill)) {
            require_once $mbPolyfill;
        }

        self::registerAutoloader($basePath);
        self::registerSiteAppAutoloader();
        /* PERF-P1: Request helper used before route dispatch; keep explicit. */
        $requestHelper = $basePath . '/app/helpers/Request.php';
        if (is_file($requestHelper)) {
            require_once $requestHelper;
        }
        /*
         * PERF-P1 — Stop eager require_once of 100+ controller/model/service bags.
         * Multi-class bags resolve via app/Core/generated-classmap.php;
         * single-class files resolve via PSR-4 autoload below.
         * Business logic unchanged: classes still load on first use.
         */
        $skipSession = (defined('RATEB_ENV_NO_SESSION') && RATEB_ENV_NO_SESSION)
            || (defined('RATEB_HEALTH_PROBE') && RATEB_HEALTH_PROBE);
        self::loadConfig($basePath);
        if (!$skipSession) {
            SessionManager::start();
        }
        // Parent .env / cloud defaults must not override branch serve.env (sync sink, sqlite path).
        if (is_file($branchServeBootstrap)) {
            BranchServeEnvBootstrap::apply($basePath, true);
        }
        if (function_exists('rateb_apply_agency_erp_request_binding')) {
            rateb_apply_agency_erp_request_binding();
        }
        if (class_exists(AgencyCommercialLock::class)) {
            AgencyCommercialLock::enforceHttp();
        }
        self::bootstrapControlPanelSso();
        self::ensureStorage($basePath);
        if (is_file($basePath . '/app/Core/SecurityHeaders.php')) {
            require_once $basePath . '/app/Core/SecurityHeaders.php';
            if (!$skipSession) {
                SecurityHeaders::send();
            }
        }
        if (function_exists('rateb_init_marketing_locale')) {
            rateb_init_marketing_locale();
        }
    }

    /** Control-panel / CLI embed: autoload + config only — never touch PHP session. */
    public static function initMinimal(string $basePath): void
    {
        static $minimalBooted = false;
        if ($minimalBooted) {
            return;
        }
        $minimalBooted = true;

        $basePath = self::resolveRootPath($basePath);
        if (!defined('RATEB_ROOT')) {
            define('RATEB_ROOT', $basePath);
        }
        if (!defined('RATEB_ENV_NO_SESSION')) {
            define('RATEB_ENV_NO_SESSION', true);
        }

        $branchServeBootstrap = $basePath . '/app/Core/BranchServeEnvBootstrap.php';
        if (is_file($branchServeBootstrap)) {
            require_once $branchServeBootstrap;
            BranchServeEnvBootstrap::apply($basePath);
        }

        self::registerAutoloader($basePath);
        self::registerSiteAppAutoloader();
        $entities = $basePath . '/app/models/Entities.php';
        if (is_file($entities)) {
            require_once $entities;
        }
        self::loadConfig($basePath);
        // Parent .env / cloud defaults must not override branch serve.env (sync sink, sqlite path).
        if (is_file($branchServeBootstrap)) {
            BranchServeEnvBootstrap::apply($basePath, true);
        }
    }

    /**
     * Phase WEBSITE-02 — Public website bootstrap (marketing/CMS public only).
     * Does not load ERP ops controllers, POS, Offline, or full enterprise service bundles.
     */
    public static function initWebsite(string $basePath): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        $basePath = self::resolveRootPath($basePath);
        if (!defined('RATEB_ROOT')) {
            define('RATEB_ROOT', $basePath);
        }
        if (!defined('RATEB_WEBSITE_KERNEL')) {
            define('RATEB_WEBSITE_KERNEL', true);
        }

        $branchServeBootstrap = $basePath . '/app/Core/BranchServeEnvBootstrap.php';
        if (is_file($branchServeBootstrap)) {
            require_once $branchServeBootstrap;
            BranchServeEnvBootstrap::apply($basePath);
        }

        if (PHP_VERSION_ID < 70400) {
            http_response_code(500);
            exit('RATEB Website requires PHP 7.4 or newer.');
        }

        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        date_default_timezone_set('Asia/Riyadh');

        $mbPolyfill = $basePath . '/app/helpers/mbstring-polyfill.php';
        if (is_file($mbPolyfill)) {
            require_once $mbPolyfill;
        }

        self::registerAutoloader($basePath);
        self::registerSiteAppAutoloader();

        foreach ([
            '/app/helpers/Request.php',
            '/app/Core/Router.php',
            '/app/Core/View.php',
            '/app/Core/Response.php',
            '/app/Core/Controller.php',
            '/app/Core/Csrf.php',
            '/app/Core/SessionManager.php',
            '/app/Core/Auth.php',
            '/app/Core/Database.php',
            '/app/Core/Model.php',
            '/app/Core/RouteModuleLoader.php',
            '/app/Core/SecurityHeaders.php',
            '/app/Core/Middleware/Middleware.php',
            '/app/models/Entities.php',
            '/app/models/CmsModels.php',
            '/app/services/AuditService.php',
            '/app/services/CmsService.php',
            '/app/services/CmsLeadNotificationService.php',
            '/app/services/CmsArticleTagService.php',
            '/app/services/CmsMediaService.php',
            '/app/services/LegacyHomeContentService.php',
            '/app/services/DatabaseErrorService.php',
            '/app/controllers/Marketing/MarketingController.php',
            '/app/controllers/Marketing/MarketingAuthController.php',
            '/app/controllers/Marketing/CustomerPortalController.php',
            '/app/controllers/Marketing/CareerPortalController.php',
            '/app/controllers/Marketing/CareerCandidateController.php',
            '/app/controllers/Marketing/WebsitePortalController.php',
            '/app/Core/Middleware/CareerPortalAuthMiddleware.php',
            '/app/Core/Middleware/WebsitePortalAuthMiddleware.php',
            '/app/Website/Career/CareerJobService.php',
            '/app/Website/Career/CareerApplicationService.php',
            '/app/Website/Career/CareerPortalAuthService.php',
            '/app/Website/Career/CareerSeoService.php',
            '/app/Website/Career/CareerNotificationService.php',
            '/app/Website/Career/CareerBlockRenderer.php',
            '/app/Website/Portal/PortalAuthService.php',
            '/app/Website/Portal/PortalRequestService.php',
            '/app/Website/Portal/PortalDocumentService.php',
            '/app/Website/Portal/PortalFinanceService.php',
            '/app/Website/Portal/PortalRecruitmentService.php',
            '/app/Website/Portal/PortalSupportService.php',
            '/app/Website/Portal/PortalAppointmentService.php',
            '/app/Website/Portal/PortalWorkflowService.php',
            '/app/Website/Portal/PortalNotificationService.php',
            '/app/Website/Portal/PortalDashboardService.php',
            '/app/Website/Portal/PortalBlockRenderer.php',
            '/app/Website/Portal/PortalContractService.php',
            '/app/Website/Portal/CustomerWorkspaceService.php',
            '/app/Website/Portal/PortalRateLimit.php',
            '/app/Website/Portal/PortalContactService.php',
            '/app/Website/Portal/PortalTimelineService.php',
            '/app/Website/Portal/PortalBookingService.php',
            '/app/Website/Portal/OnlineServiceService.php',
            '/app/controllers/Admin/LocaleController.php',
            '/app/Website/TenantContext.php',
            '/app/Website/WebsiteContext.php',
            '/app/Website/TenantWebsiteRepository.php',
            '/app/Website/TenantThemeService.php',
            '/app/Website/TenantSeoService.php',
            '/app/Website/TenantMenuService.php',
            '/app/Website/TenantBlockService.php',
            '/app/Website/TenantMediaService.php',
            '/app/Website/TenantWebsiteService.php',
            '/app/Website/WebsiteKernel.php',
            '/app/Website/WebsiteBlockRegistry.php',
            '/app/Website/WebsiteBlockRenderer.php',
            '/app/Website/WebsiteFormService.php',
            '/app/Website/WebsiteBuilderService.php',
            '/app/Website/WebsiteVersionService.php',
            '/app/Website/WebsiteSeoEditorService.php',
            '/app/Website/WebsiteThemeEditorService.php',
            '/app/Website/WebsiteMediaManagerService.php',
            '/app/Website/WebsiteMenuBuilderService.php',
            '/app/Website/Theme/ThemeManifest.php',
            '/app/Website/Theme/ThemePackage.php',
            '/app/Website/Theme/ThemeValidator.php',
            '/app/Website/Theme/ThemeCatalogService.php',
            '/app/Website/Theme/ThemeOverrideService.php',
            '/app/Website/Theme/ThemeResolver.php',
            '/app/Website/Theme/ThemeInstaller.php',
            '/app/Website/Theme/ThemeBackupService.php',
            '/app/Website/Theme/ThemeExportService.php',
            '/app/Website/Theme/ThemeImportService.php',
            '/app/Website/Theme/ThemeDemoImportService.php',
            '/app/Website/Theme/ThemeMarketplaceService.php',
            '/app/services/DedicatedTenantPolicy.php',
            '/app/models/Company.php',
        ] as $bundle) {
            $f = $basePath . $bundle;
            if (is_file($f)) {
                require_once $f;
            }
        }

        self::loadConfig($basePath);
        SessionManager::start();
        if (is_file($branchServeBootstrap)) {
            BranchServeEnvBootstrap::apply($basePath, true);
        }
        if (function_exists('rateb_apply_agency_erp_request_binding')) {
            rateb_apply_agency_erp_request_binding();
        }
        self::ensureStorage($basePath);
        if (class_exists(SecurityHeaders::class)) {
            SecurityHeaders::send();
        }
        if (function_exists('rateb_init_marketing_locale')) {
            rateb_init_marketing_locale();
        }
    }

    /**
     * Project-root app/ (App\Accounting\, App\Core\) lives beside rateb-erp/ on production.
     */
    private static function registerSiteAppAutoloader(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }

        $erpRoot = self::erpRootFromBootstrapFile();
        $candidates = [
            dirname($erpRoot) . '/app',
            $erpRoot . '/../app',
        ];
        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate);
            if ($resolved === false) {
                continue;
            }
            $autoloader = $resolved . '/Core/Autoloader.php';
            if (!is_file($autoloader)) {
                continue;
            }
            require_once $autoloader;
            \App\Core\Autoloader::register($resolved);
            if (!defined('RATEB_SITE_APP_ROOT')) {
                define('RATEB_SITE_APP_ROOT', str_replace('\\', '/', $resolved));
            }
            $registered = true;

            return;
        }
    }

    private static function registerAutoloader(string $basePath): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        $classmapFile = $basePath . '/app/Core/generated-classmap.php';
        $classmap = is_file($classmapFile) ? require $classmapFile : [];
        if (!is_array($classmap)) {
            $classmap = [];
        }

        spl_autoload_register(static function (string $class) use ($basePath, $classmap): void {
            if (isset($classmap[$class])) {
                $mapped = $basePath . $classmap[$class];
                if (is_file($mapped)) {
                    require_once $mapped;
                    return;
                }
            }

            $prefix = 'Rateb\\App\\';
            if (strpos($class, $prefix) !== 0) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $path = self::resolveAutoloadPath($basePath, $relative);
            if ($path !== null) {
                require_once $path;
            }
        });
    }

    /** Linux cPanel is case-sensitive: namespace Controllers vs folder controllers. */
    private static function resolveAutoloadPath(string $basePath, string $relative): ?string
    {
        $relative = str_replace('\\', '/', $relative);
        $exact = $basePath . '/app/' . $relative . '.php';
        if (is_file($exact)) {
            return $exact;
        }

        $parts = explode('/', $relative);
        $classFile = array_pop($parts) . '.php';
        $dir = $basePath . '/app';

        foreach ($parts as $segment) {
            $next = self::matchPathSegment($dir, $segment, true);
            if ($next === null) {
                return null;
            }
            $dir = $next;
        }

        return self::matchPathSegment($dir, $classFile, false);
    }

    /** PERF-P1: cache scandir results per directory for the request. */
    private static function matchPathSegment(string $parent, string $name, bool $directory): ?string
    {
        if (!is_dir($parent)) {
            return null;
        }

        static $dirCache = [];
        if (!isset($dirCache[$parent])) {
            $entries = [];
            foreach (scandir($parent) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $entries[strtolower($entry)] = $entry;
            }
            $dirCache[$parent] = $entries;
        }

        $target = strtolower($name);
        if (!isset($dirCache[$parent][$target])) {
            return null;
        }
        $full = $parent . '/' . $dirCache[$parent][$target];
        if ($directory && is_dir($full)) {
            return $full;
        }
        if (!$directory && is_file($full)) {
            return $full;
        }

        return null;
    }

    /** ERP root from this file: {root}/app/Core/Bootstrap.php */
    public static function erpRootFromBootstrapFile(): string
    {
        $root = realpath(dirname(__FILE__, 3));
        if ($root !== false) {
            return str_replace('\\', '/', $root);
        }
        return str_replace('\\', '/', dirname(__FILE__, 3));
    }

    /**
     * cPanel rewrite can pass /rateb-erp (symlink) while __FILE__ stays under public_html.
     * Always anchor to Bootstrap.php real location first.
     */
    public static function resolveRootPath(string $basePath): string
    {
        $anchor = self::erpRootFromBootstrapFile();
        if ($anchor !== '' && is_file($anchor . '/config/database.php')) {
            return $anchor;
        }

        $basePath = str_replace('\\', '/', rtrim($basePath, '/'));
        $candidates = [$basePath];
        $real = realpath($basePath);
        if ($real !== false) {
            $candidates[] = str_replace('\\', '/', $real);
        }

        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path . '/config/database.php')) {
                return $path;
            }
        }

        return $anchor !== '' ? $anchor : $basePath;
    }

    private static function loadConfig(string $basePath): void
    {
        $basePath = self::resolveRootPath($basePath);
        if (!defined('RATEB_ROOT')) {
            define('RATEB_ROOT', $basePath);
        }
        $configRoot = $basePath;
        if (!is_file($configRoot . '/config/database.php')) {
            $configRoot = self::erpRootFromBootstrapFile();
        }
        require_once $configRoot . '/config/app.php';
        if (!function_exists('rateb_current_public_path')) {
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
                return $fallback;
            }
        }
        require_once $configRoot . '/config/database.php';
    }

    private static function ensureStorage(string $basePath): void
    {
        \Rateb\App\Helpers\StorageHelper::ensureStorageTree($basePath);
    }

    /** Auto-login ERP when opened from Control Panel (separate PHP session). */
    private static function bootstrapControlPanelSso(): void
    {
        if (!defined('RATEB_CP_SSO') || !RATEB_CP_SSO || !defined('RATEB_CP_ENTRY')) {
            return;
        }
        if (defined('RATEB_ENV_NO_SESSION') && RATEB_ENV_NO_SESSION) {
            return;
        }
        if (\Rateb\App\Core\Auth::check()) {
            return;
        }

        $userModel = new \Rateb\App\Models\User();
        $user = $userModel->queryOne(
            'SELECT * FROM rateb_users WHERE is_super_admin = 1 AND status = :st ORDER BY id ASC LIMIT 1',
            ['st' => 'active']
        );
        if (!$user) {
            $user = $userModel->queryOne(
                "SELECT u.* FROM rateb_users u
                 INNER JOIN rateb_user_roles ur ON ur.user_id = u.id
                 INNER JOIN rateb_roles r ON r.id = ur.role_id
                 WHERE u.status = :st AND r.slug = 'company-full-access'
                 ORDER BY u.id ASC LIMIT 1",
                ['st' => 'active']
            );
        }
        if (!$user) {
            $user = $userModel->queryOne(
                'SELECT * FROM rateb_users WHERE status = :st ORDER BY id ASC LIMIT 1',
                ['st' => 'active']
            );
        }
        if ($user) {
            \Rateb\App\Core\Auth::loginUser($user);
        }
    }
}
