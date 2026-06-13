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
            Response::redirect(function_exists('rateb_url') ? rateb_url('login') : (RATEB_BASE_URL . '/login'));
            return false;
        }
        return true;
    }
}

/** Any authenticated ERP user (platform admin or company user). */
final class ErpAuthMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        Auth::bootstrapFromSession();
        if (!Auth::check()) {
            Response::redirect(function_exists('rateb_url') ? rateb_url('login') : (RATEB_BASE_URL . '/login'));
            return false;
        }
        if (SessionManager::get('rateb_is_super_admin')) {
            return true;
        }
        if ((int) SessionManager::get('rateb_company_id', 0) < 1) {
            Response::redirect(function_exists('rateb_url') ? rateb_url('login') : (RATEB_BASE_URL . '/login'));
            return false;
        }
        SessionManager::flash('error', __('portal_erp_blocked'));
        Response::redirect(function_exists('rateb_url') ? rateb_url('site/portal') : (RATEB_BASE_URL . '/site/portal'));
        return false;
    }
}

/** Platform super-admin only (companies, plans, global settings). */
final class SuperAdminMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        Auth::bootstrapFromSession();
        if (!Auth::check() || !SessionManager::get('rateb_is_super_admin')) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(function_exists('rateb_url') ? rateb_url('admin') : (RATEB_BASE_URL . '/admin'));
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
            Response::redirect(function_exists('rateb_url') ? rateb_url('login') : (RATEB_BASE_URL . '/login'));
            return false;
        }
        if (!SessionManager::get('rateb_company_id')) {
            Response::redirect(function_exists('rateb_url') ? rateb_url('login') : (RATEB_BASE_URL . '/login'));
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

        $companyId = (int) ($tokenRow['company_id'] ?? 0);
        if ($companyId < 1 || !(new \Rateb\App\Services\PlanLimitService())->companyAccessAllowed($companyId)) {
            Response::json(['success' => false, 'message' => 'Company access denied'], 403);
            return false;
        }

        $abilities = json_decode((string) ($tokenRow['abilities'] ?? '[]'), true);
        \Rateb\App\Core\TenantContext::setApiModules(is_array($abilities) ? $abilities : []);
        \Rateb\App\Core\TenantContext::setCompanyId($companyId);
        \Rateb\App\Core\TenantContext::setSuperAdmin(false);
        return true;
    }
}

final class ApiModuleMiddleware implements MiddlewareInterface
{
    private string $module;

    public function __construct(string $module = '')
    {
        $this->module = $module;
    }

    public function handle(): bool
    {
        $companyId = \Rateb\App\Core\TenantContext::companyId();
        if ($companyId === null || $companyId < 1 || $this->module === '') {
            Response::json(['success' => false, 'message' => 'Forbidden'], 403);
            return false;
        }

        $limits = new \Rateb\App\Services\PlanLimitService();
        if (!$limits->companyHasModule($companyId, $this->module)) {
            Response::json(['success' => false, 'message' => 'Module not in plan'], 403);
            return false;
        }

        $tokenModules = \Rateb\App\Core\TenantContext::apiModules();
        if ($tokenModules !== null && $tokenModules !== [] && !in_array($this->module, $tokenModules, true)) {
            Response::json(['success' => false, 'message' => 'Token not authorized for module'], 403);
            return false;
        }

        return true;
    }
}

final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        Auth::bootstrapFromSession();
        if (Auth::check()) {
            $portal = Auth::homePath();
            Response::redirect(function_exists('rateb_url') ? rateb_url($portal) : (RATEB_BASE_URL . '/' . $portal));
            return false;
        }
        return true;
    }
}

/** Company customer portal — marketing site area for logged-in tenants. */
final class MarketingCompanyAuthMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        Auth::bootstrapFromSession();
        if (!Auth::check() || SessionManager::get('rateb_is_super_admin')) {
            $next = 'site/portal';
            Response::redirect(function_exists('rateb_url')
                ? rateb_url('site/login?next=' . rawurlencode($next))
                : (RATEB_BASE_URL . '/site/login'));
            return false;
        }
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        if ($companyId < 1) {
            Response::redirect(function_exists('rateb_url') ? rateb_url('site/login') : (RATEB_BASE_URL . '/site/login'));
            return false;
        }
        if (!(new \Rateb\App\Services\PlanLimitService())->companyAccessAllowed($companyId)) {
            SessionManager::flash('error', __('subscription_expired'));
            Response::redirect(function_exists('rateb_url') ? rateb_url('site/pricing') : (RATEB_BASE_URL . '/site/pricing'));
            return false;
        }
        \Rateb\App\Core\TenantContext::setCompanyId($companyId);
        \Rateb\App\Core\TenantContext::setSuperAdmin(false);
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
        if (SessionManager::get('rateb_is_super_admin')) {
            return true;
        }

        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        if ($companyId < 1 || $this->module === '') {
            SessionManager::flash('error', __('module_not_allowed'));
            Response::redirect(function_exists('rateb_url') ? rateb_url('admin') : (RATEB_BASE_URL . '/admin'));
            return false;
        }

        $limits = new \Rateb\App\Services\PlanLimitService();
        if (!$limits->companyHasModule($companyId, $this->module)) {
            $label = function_exists('__') ? __($this->module) : $this->module;
            SessionManager::flash('error', __('module_not_in_plan_named', ['module' => $label]));
            Response::redirect(function_exists('rateb_url') ? rateb_url('admin') : (RATEB_BASE_URL . '/admin'));
            return false;
        }

        return true;
    }
}

final class CompanySaaSMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        if (SessionManager::get('rateb_is_super_admin')) {
            return true;
        }

        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        if ($companyId < 1) {
            Response::redirect(function_exists('rateb_url') ? rateb_url('login') : (RATEB_BASE_URL . '/login'));
            return false;
        }

        $limits = new \Rateb\App\Services\PlanLimitService();
        if (!$limits->companyAccessAllowed($companyId)) {
            SessionManager::flash('error', __('company_access_denied'));
            Auth::logout();
            Response::redirect(function_exists('rateb_url') ? rateb_url('login') : (RATEB_BASE_URL . '/login'));
            return false;
        }

        return true;
    }
}

final class CompanyPermissionMiddleware implements MiddlewareInterface
{
    private string $permission;
    private string $module;

    public function __construct(string $permission = '', string $module = '')
    {
        $parts = explode('|', $permission, 2);
        $this->permission = $parts[0];
        $this->module = $module !== '' ? $module : ($parts[1] ?? '');
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
        if (!$authz->companyUserCan($userId, $this->permission, $this->module)) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(function_exists('rateb_url') ? rateb_url('admin') : (RATEB_BASE_URL . '/admin'));
            return false;
        }

        return true;
    }
}

/** Entity RBAC: view on GET, manage on mutations (from permission matrix). */
final class EntityPermissionMiddleware implements MiddlewareInterface
{
    private string $resource;

    public function __construct(string $resource = '')
    {
        $this->resource = $resource;
    }

    public function handle(): bool
    {
        if (SessionManager::get('rateb_is_super_admin')) {
            return true;
        }

        if ($this->resource === '' || !function_exists('rateb_entity_perms')) {
            return true;
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $isMutation = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $allowed = $isMutation
            ? rateb_can_manage_entity($this->resource)
            : rateb_can_view_entity($this->resource);

        if ($allowed) {
            return true;
        }

        SessionManager::flash('error', __('access_denied'));
        Response::redirect(function_exists('rateb_url') ? rateb_url('admin') : (RATEB_BASE_URL . '/admin'));
        return false;
    }
}
