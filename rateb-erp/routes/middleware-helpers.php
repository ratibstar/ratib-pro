<?php
declare(strict_types=1);

use Rateb\App\Core\Middleware\AdminAuthMiddleware;
use Rateb\App\Core\Middleware\CompanyAuthMiddleware;
use Rateb\App\Core\Middleware\CompanyModuleMiddleware;
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

if (!function_exists('rateb_company_mw')) {
    function rateb_company_mw(string $module = ''): array
    {
        $stack = [CompanyAuthMiddleware::class];
        if ($module !== '') {
            $stack[] = [CompanyModuleMiddleware::class, $module];
        }
        return $stack;
    }
}
