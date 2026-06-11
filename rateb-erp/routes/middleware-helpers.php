<?php
declare(strict_types=1);

use Rateb\App\Core\Middleware\AdminAuthMiddleware;
use Rateb\App\Core\Middleware\CompanyAuthMiddleware;
use Rateb\App\Core\Middleware\CompanyModuleMiddleware;
use Rateb\App\Core\Middleware\CompanyPermissionMiddleware;
use Rateb\App\Core\Middleware\CompanySaaSMiddleware;
use Rateb\App\Core\Middleware\GuestMiddleware;
use Rateb\App\Core\Middleware\RequirePermissionMiddleware;

if (!function_exists('rateb_guest_mw')) {
    function rateb_guest_mw(): array
    {
        return [GuestMiddleware::class];
    }
}

if (!function_exists('rateb_admin_mw')) {
    function rateb_admin_mw(string $permission = ''): array
    {
        $stack = [AdminAuthMiddleware::class];
        if ($permission !== '') {
            $stack[] = [RequirePermissionMiddleware::class, $permission];
        }
        return $stack;
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

if (!function_exists('rateb_company_mw')) {
    /** @param string $module Plan module slug. @param string $permission Optional permission override. */
    function rateb_company_mw(string $module = '', string $permission = ''): array
    {
        $stack = [CompanyAuthMiddleware::class, CompanySaaSMiddleware::class];
        if ($module !== '') {
            $stack[] = [CompanyModuleMiddleware::class, $module];
            $perm = $permission !== '' ? $permission : rateb_module_permission($module);
            if ($perm !== '') {
                $stack[] = [CompanyPermissionMiddleware::class, $perm . '|' . $module];
            }
        }
        return $stack;
    }
}
