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

        if (PHP_VERSION_ID < 70400) {
            http_response_code(500);
            exit('RATEB ERP requires PHP 7.4 or newer.');
        }

        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        date_default_timezone_set('Asia/Riyadh');

        self::registerAutoloader($basePath);
        self::registerSiteAppAutoloader();
        $requestHelper = $basePath . '/app/helpers/Request.php';
        if (is_file($requestHelper)) {
            require_once $requestHelper;
        }
        $entities = $basePath . '/app/models/Entities.php';
        if (is_file($entities)) {
            require_once $entities;
        }
        foreach ([
            '/app/controllers/CrudController.php',
            '/app/helpers/LineItems.php',
            '/app/services/AuditService.php',
            '/app/controllers/Admin/AdminControllers.php',
            '/app/controllers/Admin/AutomationControllers.php',
            '/app/controllers/Admin/BusinessControllers.php',
            '/app/controllers/Admin/ExtendedControllers.php',
            '/app/controllers/Admin/AccountingControllers.php',
            '/app/controllers/Admin/CmsControllers.php',
            '/app/controllers/Marketing/MarketingController.php',
            '/app/models/CmsModels.php',
            '/app/models/HrModels.php',
            '/app/models/RecruitmentModels.php',
            '/app/models/AccountingPlatformModels.php',
            '/app/models/CrmModels.php',
            '/app/models/ProjectModels.php',
            '/app/models/EamModels.php',
            '/app/models/ApprovalModels.php',
            '/app/services/HrService.php',
            '/app/services/RecruitmentSupport.php',
            '/app/services/CandidateService.php',
            '/app/services/RecruitmentWorkflowService.php',
            '/app/services/RecruitmentTimelineService.php',
            '/app/services/RecruitmentAgencyService.php',
            '/app/services/VisaService.php',
            '/app/services/MedicalService.php',
            '/app/services/RecruitmentContractService.php',
            '/app/services/InterviewService.php',
            '/app/services/AssignmentService.php',
            '/app/services/AccountingSupport.php',
            '/app/services/AccountingDomainServices.php',
            '/app/services/AccountingEnterpriseServices.php',
            '/app/services/CrmSupport.php',
            '/app/services/CrmWorkflowService.php',
            '/app/services/CrmTimelineService.php',
            '/app/services/CrmDomainServices.php',
            '/app/services/CrmActivityServices.php',
            '/app/services/ProjectSupport.php',
            '/app/services/ProjectWorkflowService.php',
            '/app/services/ProjectTimelineService.php',
            '/app/services/ProjectDomainServices.php',
            '/app/services/ProjectActivityServices.php',
            '/app/services/AssetSupport.php',
            '/app/services/AssetWorkflowService.php',
            '/app/services/AssetTimelineService.php',
            '/app/services/AssetDomainServices.php',
            '/app/services/AssetActivityServices.php',
            '/app/services/ApprovalSupport.php',
            '/app/services/ApprovalWorkflowService.php',
            '/app/services/ApprovalTimelineService.php',
            '/app/services/ApprovalDomainServices.php',
            '/app/controllers/Company/CompanyControllers.php',
            '/app/controllers/Company/HrControllers.php',
            '/app/controllers/Company/HrExtendedControllers.php',
            '/app/controllers/Company/RecruitmentControllers.php',
            '/app/controllers/Company/ExtendedControllers.php',
            '/app/controllers/Company/AccountingControllers.php',
            '/app/controllers/Company/AccountingPlatformControllers.php',
            '/app/controllers/Company/CrmControllers.php',
            '/app/controllers/Company/ProjectControllers.php',
            '/app/controllers/Company/AssetPlatformControllers.php',
            '/app/controllers/Company/ApprovalPlatformControllers.php',
            '/app/controllers/Company/BranchControllers.php',
            '/app/controllers/Company/BusinessControllers.php',
            '/app/controllers/Shared/PasswordResetController.php',
            '/app/controllers/Shared/LoginController.php',
            '/app/controllers/Api/ApiController.php',
            '/app/Core/Router.php',
            '/app/Core/View.php',
            '/app/Core/Response.php',
            '/app/Core/Middleware/Middleware.php',
            '/app/services/MigrationService.php',
            '/app/services/LegacyHomeContentService.php',
            '/app/services/PlanLimitService.php',
            '/app/services/AccountingService.php',
            '/app/services/BillingService.php',
        ] as $bundle) {
            $f = $basePath . $bundle;
            if (is_file($f)) {
                require_once $f;
            }
        }
        $skipSession = (defined('RATEB_ENV_NO_SESSION') && RATEB_ENV_NO_SESSION)
            || (defined('RATEB_HEALTH_PROBE') && RATEB_HEALTH_PROBE);
        if (!$skipSession) {
            SessionManager::start();
        }
        self::loadConfig($basePath);
        if (function_exists('rateb_apply_agency_erp_request_binding')) {
            rateb_apply_agency_erp_request_binding();
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

        self::registerAutoloader($basePath);
        self::registerSiteAppAutoloader();
        $entities = $basePath . '/app/models/Entities.php';
        if (is_file($entities)) {
            require_once $entities;
        }
        self::loadConfig($basePath);
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
        spl_autoload_register(static function (string $class) use ($basePath): void {
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

    private static function matchPathSegment(string $parent, string $name, bool $directory): ?string
    {
        if (!is_dir($parent)) {
            return null;
        }

        $target = strtolower($name);
        foreach (scandir($parent) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (strtolower($entry) !== $target) {
                continue;
            }
            $full = $parent . '/' . $entry;
            if ($directory && is_dir($full)) {
                return $full;
            }
            if (!$directory && is_file($full)) {
                return $full;
            }
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
