<?php
declare(strict_types=1);

namespace Rateb\App\Core\Middleware;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;

interface MiddlewareInterface
{
    public function handle(): bool;
}

final class AdminAuthMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        Auth::bootstrapFromSession();
        if (!Auth::check() || !SessionManager::get('rateb_is_super_admin')) {
            Response::redirect(function_exists('rateb_url') ? rateb_url('admin/login') : (RATEB_BASE_URL . '/admin/login'));
            return false;
        }
        return true;
    }
}

final class CompanyAuthMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        Auth::bootstrapFromSession();
        if (!Auth::check() || SessionManager::get('rateb_is_super_admin')) {
            Response::redirect(function_exists('rateb_url') ? rateb_url('company/login') : (RATEB_BASE_URL . '/company/login'));
            return false;
        }
        if (!SessionManager::get('rateb_company_id')) {
            Response::redirect(function_exists('rateb_url') ? rateb_url('company/login') : (RATEB_BASE_URL . '/company/login'));
            return false;
        }
        return true;
    }
}

final class ApiAuthMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 401);
            return false;
        }

        $tokenService = new \Rateb\App\Services\ApiTokenService();
        $tokenRow = $tokenService->validateToken($m[1]);
        if (!$tokenRow) {
            Response::json(['success' => false, 'message' => 'Invalid token'], 401);
            return false;
        }

        \Rateb\App\Core\TenantContext::setCompanyId((int) $tokenRow['company_id']);
        \Rateb\App\Core\TenantContext::setSuperAdmin((int) ($tokenRow['is_super_admin'] ?? 0) === 1);
        return true;
    }
}

final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        Auth::bootstrapFromSession();
        if (Auth::check()) {
            $portal = SessionManager::get('rateb_portal', 'company');
            Response::redirect(function_exists('rateb_url') ? rateb_url($portal) : (RATEB_BASE_URL . '/' . $portal));
            return false;
        }
        return true;
    }
}

final class RequirePermissionMiddleware implements MiddlewareInterface
{
    private string $permission;

    public function __construct(string $permission = '')
    {
        $this->permission = $permission;
    }

    public function handle(): bool
    {
        if (SessionManager::get('rateb_is_super_admin')) {
            return true;
        }

        $userId = (int) SessionManager::get('rateb_user_id', 0);
        if ($userId < 1 || $this->permission === '') {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(function_exists('rateb_url') ? rateb_url('admin') : (RATEB_BASE_URL . '/admin'));
            return false;
        }

        $authz = new \Rateb\App\Services\AuthorizationService();
        if (!$authz->userHasPermission($userId, $this->permission)) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(function_exists('rateb_url') ? rateb_url('admin') : (RATEB_BASE_URL . '/admin'));
            return false;
        }

        return true;
    }
}

final class CompanyModuleMiddleware implements MiddlewareInterface
{
    private string $module;

    public function __construct(string $module = '')
    {
        $this->module = $module;
    }

    public function handle(): bool
    {
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        if ($companyId < 1 || $this->module === '') {
            SessionManager::flash('error', __('module_not_allowed'));
            Response::redirect(function_exists('rateb_url') ? rateb_url('company') : (RATEB_BASE_URL . '/company'));
            return false;
        }

        $limits = new \Rateb\App\Services\PlanLimitService();
        if (!$limits->companyHasModule($companyId, $this->module)) {
            SessionManager::flash('error', __('module_not_in_plan'));
            Response::redirect(function_exists('rateb_url') ? rateb_url('company') : (RATEB_BASE_URL . '/company'));
            return false;
        }

        return true;
    }
}
