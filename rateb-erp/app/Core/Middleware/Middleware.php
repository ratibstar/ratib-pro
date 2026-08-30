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

/** Any authenticated ERP user (platform super-admin or company operator). */
final class ErpAuthMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        Auth::bootstrapFromSession();
        if (!Auth::check()) {
            // Plain login URL — avoid ?err=session (historically triggered cookie purge).
            Response::redirect(
                function_exists('rateb_url') ? rateb_url('login') : (RATEB_BASE_URL . '/login')
            );
            return false;
        }
        if (function_exists('rateb_ensure_erp_branch_schema')) {
            rateb_ensure_erp_branch_schema();
        }
        if (function_exists('rateb_ensure_agency_schema_once')) {
            rateb_ensure_agency_schema_once();
        }
        if (SessionManager::get('rateb_is_super_admin')) {
            return true;
        }
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        if ($companyId < 1) {
            $uid = (int) SessionManager::get('rateb_user_id', 0);
            if ($uid > 0 && (new \Rateb\App\Services\AuthorizationService())->userIsPlatformStaff($uid)) {
                return true;
            }
            Response::redirect(
                function_exists('rateb_url') ? rateb_url('login') : (RATEB_BASE_URL . '/login')
            );
            return false;
        }
        if (function_exists('rateb_ensure_agency_access_permissions_once')) {
            rateb_ensure_agency_access_permissions_once();
        }

        return self::enforceActiveCompanyTenant();
    }

    /** Block company operators until the tenant is active and entitled (incl. after profile edits). */
    private static function enforceActiveCompanyTenant(): bool
    {
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        if ($companyId < 1) {
            Response::redirect(
                function_exists('rateb_url') ? rateb_url('login') : (RATEB_BASE_URL . '/login')
            );
            return false;
        }

        $planSvc = new \Rateb\App\Services\PlanLimitService();
        if ($planSvc->companyAccessAllowed($companyId)) {
            \Rateb\App\Core\TenantContext::setCompanyId($companyId);
            return true;
        }

        $company = $planSvc->getCompanyRow($companyId);
        $status = (string) ($company['status'] ?? '');
        $err = $status === 'pending' ? 'pending' : 'access';
        SessionManager::flash(
            'error',
            $status === 'pending' ? __('company_pending_approval_access') : __('company_access_denied')
        );
        Auth::logout();
        Response::redirect(
            function_exists('rateb_list_url')
                ? rateb_list_url('login', ['err' => $err])
                : (function_exists('rateb_url') ? rateb_url('login') : (RATEB_BASE_URL . '/login'))
        );

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
            // Avoid /admin ↔ /admin/settings redirect loops for platform staff.
            $uid = (int) SessionManager::get('rateb_user_id', 0);
            if ($uid > 0
                && !SessionManager::get('rateb_is_super_admin')
                && (new \Rateb\App\Services\AuthorizationService())->userIsPlatformStaff($uid)) {
                SessionManager::flash('error', __('access_denied'));
                Response::redirect(function_exists('rateb_url') ? rateb_url('admin') : (RATEB_BASE_URL . '/admin'));
                return false;
            }
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(function_exists('rateb_url') ? rateb_url('login') : (RATEB_BASE_URL . '/login'));
            return false;
        }
        return true;
    }
}

/** Block platform oversight routes on agency / non-main hosts (rateb.sa only). */
final class PlatformOversightHostMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        if (function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
            return true;
        }

        // Soft-nav / prefetch / warm: never 302 to rateb.sa (CORS on admin.*.rateb.sa).
        if (function_exists('rateb_is_non_document_request') && rateb_is_non_document_request()) {
            Response::json([
                'ok' => false,
                'error' => function_exists('__')
                    ? (string) __('platform_oversight_host_only')
                    : 'Platform oversight routes are only available on rateb.sa',
                'code' => 'platform_host_only',
            ], 403);
            return false;
        }

        // Do not flash a full-page error banner on agency hosts — soft in-app notification instead.
        $userId = (int) SessionManager::get('rateb_user_id', 0);
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        $throttleKey = 'rateb_platform_host_notice_' . gmdate('Y-m-d');
        if ($userId > 0 && !SessionManager::get($throttleKey)) {
            try {
                if (class_exists(\Rateb\App\Services\NotificationService::class)) {
                    (new \Rateb\App\Services\NotificationService())->notifyUser(
                        $userId,
                        $companyId > 0 ? $companyId : null,
                        (string) __('platform_oversight_notice_title'),
                        (string) __('platform_oversight_host_only'),
                        'warning',
                        'platform_host'
                    );
                }
                SessionManager::set($throttleKey, 1);
            } catch (\Throwable $e) {
                error_log('RATEB platform host notice: ' . $e->getMessage());
            }
        }

        $path = function_exists('rateb_current_erp_route') ? trim((string) rateb_current_erp_route(), '/') : '';
        $target = function_exists('rateb_platform_oversight_public_url')
            ? rateb_platform_oversight_public_url($path !== '' ? $path : 'admin')
            : 'https://rateb.sa/rateb-erp/public/admin';
        Response::redirect($target);
        return false;
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
        $bearer = '';
        if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
            $bearer = $m[1];
        }
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!\Rateb\App\Core\ApiRateLimiter::allowRequest($method, $bearer !== '' ? $bearer : null)) {
            Response::json(['success' => false, 'message' => 'Too many requests'], 429);
            return false;
        }
        if ($bearer === '') {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 401);
            return false;
        }

        $tokenService = new \Rateb\App\Services\ApiTokenService();
        $tokenRow = $tokenService->validateToken($bearer);
        if (!$tokenRow) {
            Response::json(['success' => false, 'message' => 'Invalid token'], 401);
            return false;
        }

        $companyId = (int) ($tokenRow['company_id'] ?? 0);
        if ($companyId < 1 || !(new \Rateb\App\Services\PlanLimitService())->apiBearerCompanyAllowed($companyId)) {
            Response::json(['success' => false, 'message' => 'Company access denied'], 403);
            return false;
        }

        $abilities = json_decode((string) ($tokenRow['abilities'] ?? '[]'), true);
        \Rateb\App\Core\TenantContext::setApiModules(is_array($abilities) ? $abilities : []);
        \Rateb\App\Core\TenantContext::setCompanyId($companyId);
        \Rateb\App\Core\TenantContext::setSuperAdmin(false);
        $userId = (int) ($tokenRow['user_id'] ?? 0);
        $tokenBranchId = isset($tokenRow['branch_id']) ? (int) $tokenRow['branch_id'] : null;
        if ($tokenBranchId !== null && $tokenBranchId < 1) {
            $tokenBranchId = null;
        }
        \Rateb\App\Core\BranchContext::reset();
        \Rateb\App\Core\TenantContext::setApiUserId($userId > 0 ? $userId : null);
        (new \Rateb\App\Services\BranchAccessService())->bootstrapForApi($companyId, $userId, $tokenBranchId);
        // Phase 2 — read-only SubscriptionContext for API bearer tenants (no access change).
        if (class_exists(\Rateb\App\Subscription\SubscriptionBootstrap::class)) {
            \Rateb\App\Subscription\SubscriptionBootstrap::bindForCompany($companyId);
        }
        // Phase 7B — feature-flagged suspension enforcement (default OFF).
        if (class_exists(\Rateb\App\Subscription\SubscriptionEnforcementMiddleware::class)) {
            $ok = (new \Rateb\App\Subscription\SubscriptionEnforcementMiddleware())->handle();
            if (!$ok) {
                return false;
            }
        }
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
            if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
                Response::redirect(function_exists('rateb_url') ? rateb_url('login') : (RATEB_BASE_URL . '/login'));
                return false;
            }
            if (function_exists('rateb_erp_is_dedicated_deployment') && rateb_erp_is_dedicated_deployment()) {
                Response::redirect(function_exists('rateb_url') ? rateb_url('login') : (RATEB_BASE_URL . '/login'));
                return false;
            }
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

/** ERP operators must never browse the marketing customer portal. */
final class ErpOperatorPortalRedirectMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        Auth::bootstrapFromSession();
        if (!Auth::check()) {
            return true;
        }
        $user = Auth::user();
        if (is_array($user) && Auth::shouldLandOnErpShell($user)) {
            Response::redirect(function_exists('rateb_url') ? rateb_url('admin') : (RATEB_BASE_URL . '/admin'));

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
        // Super Admin: full ERP open — never ceiling-gate by company.modules / plan.
        if (self::isSuperAdminSession()) {
            return true;
        }

        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = (int) rateb_resolve_ops_company_id();
        }
        $jsonOnly = function_exists('rateb_prefers_json_error_response') && rateb_prefers_json_error_response();
        if ($companyId < 1 || $this->module === '') {
            // Platform staff: no tenant company — allow when RBAC permission for the module exists.
            $uid = (int) SessionManager::get('rateb_user_id', 0);
            if ($uid > 0 && $this->module !== ''
                && (new \Rateb\App\Services\AuthorizationService())->userIsPlatformStaff($uid)) {
                $modPerm = function_exists('rateb_module_permission')
                    ? (string) rateb_module_permission($this->module)
                    : '';
                if ($modPerm !== '' && (new \Rateb\App\Services\AuthorizationService())->userHasPermission($uid, $modPerm)) {
                    return true;
                }
            }
            if ($jsonOnly) {
                Response::json(['ok' => false, 'error' => __('module_not_allowed'), 'code' => 'module_not_allowed'], 403);
                return false;
            }
            SessionManager::flash('error', __('module_not_allowed'));
            Response::redirect(function_exists('rateb_url') ? rateb_url('admin') : (RATEB_BASE_URL . '/admin'));
            return false;
        }

        $limits = new \Rateb\App\Services\PlanLimitService();
        if (!$limits->companyHasModule($companyId, $this->module)) {
            $label = function_exists('__') ? __($this->module) : $this->module;
            $msg = __('module_not_in_plan_named', ['module' => $label]);
            if ($jsonOnly) {
                Response::json([
                    'ok' => false,
                    'error' => $msg,
                    'code' => 'module_not_in_plan',
                    'module' => $this->module,
                ], 403);
                return false;
            }
            SessionManager::flash('error', $msg);
            Response::redirect(function_exists('rateb_url') ? rateb_url('admin') : (RATEB_BASE_URL . '/admin'));
            return false;
        }

        return true;
    }

    private static function isSuperAdminSession(): bool
    {
        if (!empty($_SESSION['rateb_is_super_admin'])) {
            return true;
        }
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return true;
        }
        if (SessionManager::get('rateb_is_super_admin')) {
            return true;
        }
        try {
            if (class_exists(\Rateb\App\Core\TenantContext::class)
                && method_exists(\Rateb\App\Core\TenantContext::class, 'isSuperAdmin')
                && \Rateb\App\Core\TenantContext::isSuperAdmin()) {
                return true;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return false;
    }
}

final class CompanySaaSMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        if (SessionManager::get('rateb_is_super_admin')) {
            // Honour ops company picker — never re-bind leftover rateb_company_id while
            // platform SA is in «المنصة بدون شركة» (that desynced suppliers lists/nav).
            $opsCompanyId = function_exists('rateb_resolve_ops_company_id')
                ? (int) rateb_resolve_ops_company_id()
                : 0;
            if ($opsCompanyId > 0) {
                \Rateb\App\Core\TenantContext::setCompanyId($opsCompanyId);
                if (class_exists(\Rateb\App\Subscription\SubscriptionEnforcementMiddleware::class)) {
                    return (new \Rateb\App\Subscription\SubscriptionEnforcementMiddleware())->handle();
                }
            } else {
                \Rateb\App\Core\TenantContext::setCompanyId(null);
            }
            return true;
        }

        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        if ($companyId < 1) {
            $uid = (int) SessionManager::get('rateb_user_id', 0);
            if ($uid > 0 && (new \Rateb\App\Services\AuthorizationService())->userIsPlatformStaff($uid)) {
                return true;
            }
            Response::redirect(function_exists('rateb_url') ? rateb_url('login') : (RATEB_BASE_URL . '/login'));
            return false;
        }

        $limits = new \Rateb\App\Services\PlanLimitService();
        if (!$limits->companyAccessAllowed($companyId)) {
            $company = $limits->getCompanyRow($companyId);
            $status = (string) ($company['status'] ?? '');
            SessionManager::flash(
                'error',
                $status === 'pending' ? __('company_pending_approval_access') : __('company_access_denied')
            );
            Auth::logout();
            Response::redirect(function_exists('rateb_url') ? rateb_url('login') : (RATEB_BASE_URL . '/login'));
            return false;
        }

        \Rateb\App\Core\TenantContext::setCompanyId($companyId);
        // Phase 7B — feature-flagged suspension enforcement (default OFF).
        if (class_exists(\Rateb\App\Subscription\SubscriptionEnforcementMiddleware::class)) {
            return (new \Rateb\App\Subscription\SubscriptionEnforcementMiddleware())->handle();
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

        // Respect role matrix for all tenant admins (no ops-admin bypass).

        $userId = (int) SessionManager::get('rateb_user_id', 0);
        if ($userId < 1 || $this->permission === '') {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(function_exists('rateb_url') ? rateb_url('admin') : (RATEB_BASE_URL . '/admin'));
            return false;
        }

        if ($this->permission !== ''
            && function_exists('rateb_is_branch_permission_slug')
            && rateb_is_branch_permission_slug($this->permission)
            && function_exists('rateb_company_branches_nav_enabled')
            && !rateb_company_branches_nav_enabled()) {
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

        if (function_exists('rateb_resource_requires_branches_view')
            && rateb_resource_requires_branches_view($this->resource)
            && function_exists('rateb_company_branches_nav_enabled')
            && !rateb_company_branches_nav_enabled()) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect(function_exists('rateb_url') ? rateb_url('admin') : (RATEB_BASE_URL . '/admin'));
            return false;
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $isMutation = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        if ($isMutation) {
            $allowed = rateb_can_manage_entity($this->resource);
            if (!$allowed && $this->isApproveAction()) {
                $allowed = function_exists('rateb_can_approve_entity')
                    && rateb_can_approve_entity($this->resource);
            }
            if (!$allowed && $this->isAccountingPostAction()) {
                $allowed = rateb_can_post_entity($this->resource);
            }
        } else {
            $allowed = rateb_can_view_entity($this->resource);
        }

        if ($allowed) {
            return true;
        }

        self::denyEntityAccess();
        return false;
    }

    private static function denyEntityAccess(): void
    {
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $wantsJson = str_contains($path, '/api/')
            || str_contains($accept, 'application/json')
            || isset($_SERVER['HTTP_X_CSRF_TOKEN']);
        if ($wantsJson) {
            Response::json(['ok' => false, 'error' => __('access_denied')], 403);
            return;
        }

        SessionManager::flash('error', __('access_denied'));
        Response::redirect(function_exists('rateb_url') ? rateb_url('admin') : (RATEB_BASE_URL . '/admin'));
    }

    private function isApproveAction(): bool
    {
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        if (preg_match('#/(post|void|reject|bulk-approve|bulk-void|bulk-reject)(/|$)#', $path) === 1) {
            return true;
        }
        return preg_match('#/warehouse-transfers/\d+/approve$#', $path) === 1;
    }

    private function isAccountingPostAction(): bool
    {
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        return preg_match('#/(close|reopen)(/|$)#', $path) === 1
            || preg_match('#/accounting/sync$#', $path) === 1
            || preg_match('#/shifts/open$#', $path) === 1;
    }
}

/** Bootstrap branch context and optional HQ branch switcher on every ops route. */
final class BranchScopeMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        if (SessionManager::get('rateb_is_super_admin')) {
            rateb_resolve_ops_company_id();
        }

        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = rateb_resolve_ops_company_id();
        }

        if (function_exists('rateb_bootstrap_branch_context')) {
            rateb_bootstrap_branch_context($companyId > 0 ? $companyId : null);
        }

        $filterParam = $_GET['active_branch_id'] ?? $_POST['active_branch_id'] ?? null;
        if ($filterParam === 'all' || $filterParam === '0' || (string) ($_GET['branch_filter'] ?? '') === 'all') {
            (new \Rateb\App\Services\BranchAccessService())->clearActiveBranchFilter();
            if (function_exists('rateb_bootstrap_branch_context')) {
                \Rateb\App\Core\BranchContext::reset();
                rateb_bootstrap_branch_context($companyId > 0 ? $companyId : null);
            }
            return true;
        }

        if ($filterParam !== null && $filterParam !== '') {
            $branchId = (int) $filterParam;
            if ($branchId > 0) {
                $ok = (new \Rateb\App\Services\BranchAccessService())->setActiveBranchFilter($branchId);
                if (!$ok) {
                    SessionManager::flash('error', __('branch_access_denied'));
                }
            }
        }

        return true;
    }
}
