<?php
declare(strict_types=1);

use Rateb\App\Core\Middleware\BranchScopeMiddleware;
use Rateb\App\Core\Middleware\CompanyModuleMiddleware;
use Rateb\App\Core\Middleware\CompanyPermissionMiddleware;
use Rateb\App\Core\Middleware\CompanySaaSMiddleware;
use Rateb\App\Core\Middleware\EntityPermissionMiddleware;
use Rateb\App\Core\Middleware\ErpAuthMiddleware;
use Rateb\App\Core\Middleware\ErpOperatorPortalRedirectMiddleware;
use Rateb\App\Core\Middleware\GuestMiddleware;
use Rateb\App\Core\Middleware\MarketingCompanyAuthMiddleware;
use Rateb\App\Core\Middleware\PlatformOversightHostMiddleware;
use Rateb\App\Core\Middleware\RequirePermissionMiddleware;
use Rateb\App\Core\Middleware\SuperAdminMiddleware;

if (!function_exists('rateb_guest_mw')) {
    function rateb_guest_mw(): array
    {
        return [GuestMiddleware::class];
    }
}

if (!function_exists('rateb_portal_mw')) {
    function rateb_portal_mw(): array
    {
        return [MarketingCompanyAuthMiddleware::class, ErpOperatorPortalRedirectMiddleware::class];
    }
}

if (!function_exists('rateb_admin_mw')) {
    /** Platform oversight routes — super admin + optional permission. */
    function rateb_admin_mw(string $permission = ''): array
    {
        $stack = [ErpAuthMiddleware::class, SuperAdminMiddleware::class];
        if ($permission !== '') {
            $stack[] = [RequirePermissionMiddleware::class, $permission];
        }
        return $stack;
    }
}

if (!function_exists('rateb_platform_oversight_mw')) {
    /** Companies, agency push, CMS, SaaS billing — rateb.sa platform host only. */
    function rateb_platform_oversight_mw(string $permission = ''): array
    {
        return array_merge(rateb_admin_mw($permission), [PlatformOversightHostMiddleware::class]);
    }
}

/** @alias rateb_platform_oversight_mw */
if (!function_exists('rateb_platform_saas_mw')) {
    function rateb_platform_saas_mw(string $permission = ''): array
    {
        return rateb_platform_oversight_mw($permission);
    }
}

if (!function_exists('rateb_module_permission')) {
    function rateb_module_permission(string $module): string
    {
        static $map = null;
        if ($map === null) {
            $file = (defined('RATEB_ROOT') ? RATEB_ROOT : '') . '/config/module-permissions.php';
            $map = is_file($file) ? require $file : [];
        }
        return (string) ($map[$module] ?? '');
    }
}

if (!function_exists('rateb_erp_mw')) {
    /**
     * Unified app middleware: login + plan + entity/view/manage permissions.
     *
     * @param string $module      Plan module slug (procurement, inventory, …)
     * @param string $permission  Explicit permission override (e.g. reports.export, workflows.approve)
     * @param string $resource    Entity resource key from config/entity-permissions.php
     */
    function rateb_erp_mw(string $module = '', string $permission = '', string $resource = ''): array
    {
        $stack = [ErpAuthMiddleware::class, CompanySaaSMiddleware::class, BranchScopeMiddleware::class];
        if ($module !== '') {
            $stack[] = [CompanyModuleMiddleware::class, $module];
        }
        if ($permission !== '') {
            $stack[] = [CompanyPermissionMiddleware::class, $permission . ($module !== '' ? '|' . $module : '')];
        }
        if ($resource !== '') {
            $stack[] = [EntityPermissionMiddleware::class, $resource];
        } elseif ($permission === '' && $module !== '') {
            $perm = rateb_module_permission($module);
            if ($perm !== '') {
                $stack[] = [CompanyPermissionMiddleware::class, $perm . '|' . $module];
            }
        }
        return $stack;
    }
}

if (!function_exists('rateb_company_mw')) {
    /** @deprecated Use rateb_erp_mw() — kept for legacy route aliases. */
    function rateb_company_mw(string $module = '', string $permission = ''): array
    {
        return rateb_erp_mw($module, $permission);
    }
}
