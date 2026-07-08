<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use Rateb\App\Models\User;
use Rateb\App\Services\AccountLockoutService;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\RememberMeService;

final class Auth
{
    private static ?string $lastLoginFailureReason = null;

    public static function consumeLoginFailureReason(): ?string
    {
        $reason = self::$lastLoginFailureReason;
        self::$lastLoginFailureReason = null;

        return $reason;
    }

    public static function attempt(string $login, string $password, string $portal = 'company'): ?array
    {
        $user = (new User())->authenticate($login, $password);
        if (!$user) {
            return null;
        }

        return self::attemptWithUser($user, $portal);
    }

    public static function attemptAuto(string $login, string $password): ?array
    {
        self::$lastLoginFailureReason = null;
        $user = (new User())->authenticate($login, $password);
        if (!$user) {
            self::$lastLoginFailureReason = 'credentials';

            return null;
        }

        $isSuper = (int) ($user['is_super_admin'] ?? 0) === 1;

        return self::attemptWithUser($user, $isSuper ? 'admin' : 'company');
    }

    /** @param array<string, mixed> $user */
    private static function attemptWithUser(array $user, string $portal): ?array
    {
        if ((string) ($user['status'] ?? '') !== 'active') {
            self::$lastLoginFailureReason = 'inactive';

            return null;
        }

        $lockout = new AccountLockoutService();
        if ($lockout->isLocked($user)) {
            self::$lastLoginFailureReason = 'locked';

            return null;
        }

        $isSuper = (int) ($user['is_super_admin'] ?? 0) === 1;
        if ($portal === 'admin' && !$isSuper) {
            self::$lastLoginFailureReason = 'credentials';

            return null;
        }
        if ($portal === 'company' && $isSuper) {
            self::$lastLoginFailureReason = 'credentials';

            return null;
        }

        if ($portal === 'company') {
            $companyId = (int) ($user['company_id'] ?? 0);
            if ($companyId < 1) {
                self::$lastLoginFailureReason = 'no_company';

                return null;
            }
            $company = (new \Rateb\App\Models\Company())->find($companyId);
            if (!$company || (string) ($company['status'] ?? '') !== 'active') {
                self::$lastLoginFailureReason = 'company_inactive';

                return null;
            }
            $limits = new \Rateb\App\Services\PlanLimitService();
            if (!$limits->companyAccessAllowed($companyId)) {
                self::$lastLoginFailureReason = 'access';

                return null;
            }
        }

        $lockout->clearLock((int) $user['id']);
        self::establishSession($user, $portal);
        self::$lastLoginFailureReason = null;

        return $user;
    }

    public static function loginUser(array $user): bool
    {
        if ((string) ($user['status'] ?? '') !== 'active') {
            return false;
        }

        $isSuper = (int) ($user['is_super_admin'] ?? 0) === 1;
        if (!$isSuper) {
            $companyId = (int) ($user['company_id'] ?? 0);
            if ($companyId < 1) {
                return false;
            }
            $company = (new \Rateb\App\Models\Company())->find($companyId);
            if (!$company || (string) ($company['status'] ?? '') !== 'active') {
                return false;
            }
            $limits = new \Rateb\App\Services\PlanLimitService();
            if (!$limits->companyAccessAllowed($companyId)) {
                return false;
            }
        }

        self::establishSession($user, $isSuper ? 'admin' : 'company');
        return true;
    }

    /** @param array<string, mixed> $user */
    private static function establishSession(array $user, string $portal): void
    {
        SessionManager::regenerate();
        $isSuper = (int) ($user['is_super_admin'] ?? 0) === 1;
        SessionManager::set('rateb_user_id', (int) $user['id']);
        SessionManager::set('rateb_company_id', $user['company_id'] !== null ? (int) $user['company_id'] : null);
        SessionManager::set('rateb_is_super_admin', $isSuper);
        SessionManager::set('rateb_portal', $portal);
        SessionManager::set('rateb_user_email', (string) ($user['email'] ?? ''));
        SessionManager::set('rateb_user_display', (string) ($user['name'] ?? $user['display_name'] ?? ''));
        TenantContext::setSuperAdmin($isSuper);
        TenantContext::setCompanyId($user['company_id'] !== null ? (int) $user['company_id'] : null);
        SessionManager::forget('rateb_agency_access_perms_synced');
        if (function_exists('rateb_ensure_agency_access_permissions_once')) {
            rateb_ensure_agency_access_permissions_once();
        }
    }

    public static function homePath(): string
    {
        return 'admin';
    }

    /**
     * Post-login redirect: unified /login opens ERP shell (/admin) for company tenants.
     * Marketing customer portal is opt-in navigation only, not the default landing page.
     *
     * @param array<string, mixed> $user
     */
    public static function resolvePostLoginUrl(string $next, array $user): string
    {
        $erpHome = function_exists('rateb_url') ? rateb_url('admin') : '/admin';

        $next = trim($next);
        if ($next !== '' && !(self::shouldLandOnErpShell($user) && self::urlIsCustomerPortal($next))) {
            return $next;
        }

        if (self::shouldLandOnErpShell($user)) {
            return $erpHome;
        }

        return function_exists('rateb_url') ? rateb_url('site/portal') : '/site/portal';
    }

    /** @param array<string, mixed> $user */
    public static function shouldLandOnErpShell(array $user): bool
    {
        if (self::shouldUseErpDashboard($user)) {
            return true;
        }

        return (int) ($user['company_id'] ?? 0) > 0;
    }

    public static function urlIsCustomerPortal(string $url): bool
    {
        return preg_match('#/site/portal(?:[/?#]|$)#i', $url) === 1;
    }

    /** @param array<string, mixed> $user */
    public static function shouldUseErpDashboard(array $user): bool
    {
        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            return true;
        }
        if (function_exists('rateb_erp_is_dedicated_deployment') && rateb_erp_is_dedicated_deployment()) {
            return true;
        }
        if ((int) ($user['is_super_admin'] ?? 0) === 1) {
            return true;
        }

        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $name = strtolower(trim((string) ($user['name'] ?? '')));
        if ($email === 'admin@local' || $name === 'admin' || strpos($email, 'admin+') === 0) {
            return true;
        }

        $userId = (int) ($user['id'] ?? 0);
        if ($userId < 1) {
            return false;
        }

        $row = (new \Rateb\App\Models\Role())->queryOne(
            "SELECT 1 FROM rateb_user_roles ur
             INNER JOIN rateb_roles r ON r.id = ur.role_id
             WHERE ur.user_id = :uid
               AND r.slug IN ('company-full-access', 'hq_admin', 'hq_manager', 'branch_manager', 'branch_user')
             LIMIT 1",
            ['uid' => $userId]
        );

        return $row !== null;
    }

    public static function user(): ?array
    {
        $id = SessionManager::get('rateb_user_id');
        if (!$id) {
            return null;
        }
        return (new User())->find((int) $id);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function logout(): void
    {
        $userId = (int) SessionManager::get('rateb_user_id', 0);
        if ($userId > 0) {
            (new RememberMeService())->revokeAllForUser($userId);
            (new AuditService())->log('logout', 'user', $userId);
        }
        SessionManager::destroy();
        TenantContext::setCompanyId(null);
        TenantContext::setSuperAdmin(false);
        \Rateb\App\Core\BranchContext::reset();
    }

    /** Auto-login first active super admin when RATEB_ERP_LOGIN_BYPASS is enabled. */
    public static function applyLoginBypassIfEnabled(): void
    {
        if (!function_exists('rateb_erp_login_bypass_enabled') || !rateb_erp_login_bypass_enabled()) {
            return;
        }
        if (SessionManager::get('rateb_user_id')) {
            return;
        }
        try {
            $user = (new User())->queryOne(
                "SELECT * FROM rateb_users WHERE is_super_admin = 1 AND status = 'active' ORDER BY id ASC LIMIT 1"
            );
            if ($user && self::loginUser($user)) {
                (new User())->updateLastLogin((int) $user['id']);
            }
        } catch (\Throwable $e) {
            error_log('RATEB login bypass: ' . $e->getMessage());
        }
    }

    public static function bootstrapFromSession(): void
    {
        try {
            if (!SessionManager::get('rateb_user_id')) {
                $user = (new RememberMeService())->tryLogin();
                if ($user && self::loginUser($user)) {
                    (new User())->updateLastLogin((int) $user['id']);
                }
            }
        } catch (\Throwable $e) {
            error_log('RATEB remember-me bootstrap: ' . $e->getMessage());
            self::clearSessionIdentity();
        }

        self::applyLoginBypassIfEnabled();

        $userId = (int) SessionManager::get('rateb_user_id', 0);
        if ($userId > 0) {
            try {
                $user = (new User())->find($userId);
                if (!$user || (string) ($user['status'] ?? '') !== 'active') {
                    self::clearSessionIdentity();
                }
            } catch (\Throwable $e) {
                error_log('RATEB session user lookup: ' . $e->getMessage());
                self::clearSessionIdentity();
            }
        }

        TenantContext::setSuperAdmin((bool) SessionManager::get('rateb_is_super_admin'));
        $companyId = SessionManager::get('rateb_company_id');
        TenantContext::setCompanyId($companyId !== null ? (int) $companyId : null);
        try {
            if (function_exists('rateb_bootstrap_portal_branch_from_request')) {
                rateb_bootstrap_portal_branch_from_request();
            }
            if (function_exists('rateb_bootstrap_branch_context')) {
                rateb_bootstrap_branch_context($companyId !== null ? (int) $companyId : null);
            }
        } catch (\Throwable $e) {
            error_log('RATEB branch bootstrap: ' . $e->getMessage());
            SessionManager::forget('rateb_portal_branch_id');
            \Rateb\App\Core\BranchContext::reset();
        }
    }

    /** Clear stale ERP session keys without audit/remember-me side effects (safe during bootstrap). */
    private static function clearSessionIdentity(): void
    {
        SessionManager::forget('rateb_user_id');
        SessionManager::forget('rateb_is_super_admin');
        SessionManager::forget('rateb_company_id');
        SessionManager::forget('rateb_portal_branch_id');
        SessionManager::forget('rateb_portal');
        TenantContext::setSuperAdmin(false);
        TenantContext::setCompanyId(null);
        \Rateb\App\Core\BranchContext::reset();
    }
}
