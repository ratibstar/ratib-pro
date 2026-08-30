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
    /** @var array<string, mixed>|null|false false = unset */
    private static $userCache = false;
    private static ?int $userCacheId = null;

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
        if ($isSuper) {
            return self::attemptWithUser($user, 'admin');
        }
        $companyId = (int) ($user['company_id'] ?? 0);
        if ($companyId < 1
            && (new \Rateb\App\Services\AuthorizationService())->userIsPlatformStaff((int) $user['id'])) {
            return self::attemptWithUser($user, 'platform');
        }

        return self::attemptWithUser($user, 'company');
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
        if ($portal === 'platform') {
            if ($isSuper || (int) ($user['company_id'] ?? 0) > 0) {
                self::$lastLoginFailureReason = 'credentials';

                return null;
            }
            if (!(new \Rateb\App\Services\AuthorizationService())->userIsPlatformStaff((int) $user['id'])) {
                self::$lastLoginFailureReason = 'credentials';

                return null;
            }
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
                // Active company with missing/stale subscription (common after SA creates users on SaaS).
                // Do not heal suspended companies — those stay blocked.
                if ((string) ($company['status'] ?? '') === 'active') {
                    try {
                        (new \Rateb\App\Services\DedicatedCompanySeedService())->ensureCompanyLoginReady($companyId);
                    } catch (\Throwable $e) {
                        error_log('Auth subscription heal: ' . $e->getMessage());
                    }
                }
                if (!$limits->companyAccessAllowed($companyId)) {
                    self::$lastLoginFailureReason = 'access';

                    return null;
                }
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

        $userId = (int) ($user['id'] ?? 0);
        // Demote mis-saved SA who already has platform staff roles (permissions must apply).
        if ($userId > 0 && (int) ($user['is_super_admin'] ?? 0) === 1 && (int) ($user['company_id'] ?? 0) < 1) {
            if (self::userHasAssignablePlatformStaffRoles($userId)) {
                try {
                    \Rateb\App\Core\Database::connection()
                        ->prepare('UPDATE rateb_users SET is_super_admin = 0, company_id = NULL WHERE id = :id LIMIT 1')
                        ->execute(['id' => $userId]);
                    $user['is_super_admin'] = 0;
                    $user['company_id'] = null;
                } catch (\Throwable $e) {
                    error_log('Auth loginUser staff heal: ' . $e->getMessage());
                }
            }
        }

        $isSuper = (int) ($user['is_super_admin'] ?? 0) === 1;
        $portal = $isSuper ? 'admin' : 'company';
        if (!$isSuper) {
            $companyId = (int) ($user['company_id'] ?? 0);
            if ($companyId < 1) {
                if (!(new \Rateb\App\Services\AuthorizationService())->userIsPlatformStaff($userId)) {
                    return false;
                }
                $portal = 'platform';
            } else {
                $company = (new \Rateb\App\Models\Company())->find($companyId);
                if (!$company || (string) ($company['status'] ?? '') !== 'active') {
                    return false;
                }
                $limits = new \Rateb\App\Services\PlanLimitService();
                if (!$limits->companyAccessAllowed($companyId)) {
                    if ((string) ($company['status'] ?? '') === 'active') {
                        try {
                            (new \Rateb\App\Services\DedicatedCompanySeedService())->ensureCompanyLoginReady($companyId);
                        } catch (\Throwable $e) {
                            error_log('Auth loginUser subscription heal: ' . $e->getMessage());
                        }
                    }
                    if (!$limits->companyAccessAllowed($companyId)) {
                        return false;
                    }
                }
            }
        }

        self::establishSession($user, $portal);
        return true;
    }

    /** @param array<string, mixed> $user */
    private static function establishSession(array $user, string $portal): void
    {
        SessionManager::regenerate();
        $isSuper = (int) ($user['is_super_admin'] ?? 0) === 1;
        $companyId = $user['company_id'] !== null ? (int) $user['company_id'] : 0;
        SessionManager::set('rateb_user_id', (int) $user['id']);
        SessionManager::set('rateb_company_id', $companyId > 0 ? $companyId : null);
        SessionManager::set('rateb_is_super_admin', $isSuper);
        SessionManager::set('rateb_portal', $portal);
        SessionManager::set('rateb_user_email', (string) ($user['email'] ?? ''));
        self::$userCacheId = (int) $user['id'];
        self::$userCache = $user;
        SessionManager::set('rateb_user_display', (string) ($user['name'] ?? $user['display_name'] ?? ''));
        TenantContext::setSuperAdmin($isSuper);
        TenantContext::setCompanyId($companyId > 0 ? $companyId : null);
        // Super-admin rows often have null company_id — bind operational tenant before ERP shell boot.
        // Platform oversight SA stays in «بدون شركة» unless ops picker chose a company.
        if ($isSuper && $companyId < 1 && function_exists('rateb_resolve_erp_shell_company_id')) {
            if (!(function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host())) {
                $shellCompany = (int) rateb_resolve_erp_shell_company_id();
                if ($shellCompany > 0) {
                    $companyId = $shellCompany;
                    SessionManager::set('rateb_company_id', $companyId);
                    TenantContext::setCompanyId($companyId);
                }
            }
        }
        SessionManager::forget('rateb_agency_access_perms_synced');
        SessionManager::forget('rateb_saas_tenant_access_perms_synced');
        if (function_exists('rateb_ensure_agency_access_permissions_once')) {
            rateb_ensure_agency_access_permissions_once();
        }
        // Phase 2 — read-only bind after tenant identity is established (no enforcement).
        if (class_exists(\Rateb\App\Subscription\SubscriptionBootstrap::class)) {
            \Rateb\App\Subscription\SubscriptionBootstrap::bindForCompany(
                $companyId > 0 ? $companyId : null
            );
        }
        // Pin canonical path only. Do NOT clearAlternatePathCookies() here — expiring path=/
        // in the same response as login races the browser and causes err=session on the next
        // click (e.g. Users) until the user retries several times.
        SessionManager::reissueCanonicalSessionCookie();
        SessionManager::set('_rateb_cookie_pinned', 1);
        if (class_exists(Csrf::class)) {
            Csrf::token();
        }
    }

    public static function homePath(): string
    {
        return 'admin';
    }

    /**
     * Post-login redirect: unified /login opens ERP shell (/admin) for company tenants
     * and platform staff. Marketing customer portal is opt-in only.
     *
     * @param array<string, mixed> $user
     */
    public static function resolvePostLoginUrl(string $next, array $user): string
    {
        $erpHome = function_exists('rateb_url') ? rateb_url('admin') : '/admin';

        $next = trim($next);
        if ($next !== '' && !(self::shouldLandOnErpShell($user) && self::urlIsCustomerPortal($next))) {
            // Never send ERP users (SA / staff / company) to marketing customer login/portal.
            if (self::shouldLandOnErpShell($user) && self::urlIsMarketingCustomerLogin($next)) {
                return $erpHome;
            }

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

        if ((int) ($user['company_id'] ?? 0) > 0) {
            return true;
        }

        // Platform staff: no company, not SA — still ERP /admin (never site/portal).
        $userId = (int) ($user['id'] ?? 0);
        if ($userId > 0
            && (int) ($user['is_super_admin'] ?? 0) !== 1
            && (new \Rateb\App\Services\AuthorizationService())->userIsPlatformStaff($userId)) {
            return true;
        }

        return false;
    }

    public static function urlIsCustomerPortal(string $url): bool
    {
        return preg_match('#/site/portal(?:[/?#]|$)#i', $url) === 1;
    }

    public static function urlIsMarketingCustomerLogin(string $url): bool
    {
        return preg_match('#/site/login(?:[/?#]|$)#i', $url) === 1;
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

        if ((new \Rateb\App\Services\AuthorizationService())->userIsPlatformStaff($userId)) {
            return true;
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
            self::$userCache = null;
            self::$userCacheId = null;
            return null;
        }
        $id = (int) $id;
        if (self::$userCacheId === $id && self::$userCache !== false) {
            return is_array(self::$userCache) ? self::$userCache : null;
        }
        self::$userCacheId = $id;
        $found = (new User())->find($id);
        self::$userCache = is_array($found) ? $found : null;
        return self::$userCache;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function clearUserCache(): void
    {
        self::$userCache = false;
        self::$userCacheId = null;
    }

    public static function logout(): void
    {
        self::clearUserCache();
        $userId = (int) SessionManager::get('rateb_user_id', 0);
        if ($userId > 0) {
            (new RememberMeService())->revokeAllForUser($userId);
            (new AuditService())->log('logout', 'user', $userId);
        }
        SessionManager::destroy();
        Csrf::clearCookie();
        TenantContext::setCompanyId(null);
        TenantContext::setSuperAdmin(false);
        \Rateb\App\Core\BranchContext::reset();
        if (class_exists(\Rateb\App\Subscription\SubscriptionRuntime::class)) {
            \Rateb\App\Subscription\SubscriptionRuntime::reset();
        }
        if (class_exists(\Rateb\App\Subscription\SubscriptionAlertRuntime::class)) {
            \Rateb\App\Subscription\SubscriptionAlertRuntime::reset();
        }
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
                } else {
                    // Keep session SA/company flags in sync with DB (healed staff must not stay SA in cookie).
                    $dbSuper = (int) ($user['is_super_admin'] ?? 0) === 1;
                    SessionManager::set('rateb_is_super_admin', $dbSuper);
                    SessionManager::set(
                        'rateb_user_display',
                        (string) ($user['name'] ?? $user['display_name'] ?? SessionManager::get('rateb_user_display', ''))
                    );
                    if (!$dbSuper) {
                        $dbCid = $user['company_id'] !== null ? (int) $user['company_id'] : 0;
                        SessionManager::set('rateb_company_id', $dbCid > 0 ? $dbCid : null);
                    }
                }
            } catch (\Throwable $e) {
                error_log('RATEB session user lookup: ' . $e->getMessage());
                self::clearSessionIdentity();
            }
        }

        $isSuper = (bool) SessionManager::get('rateb_is_super_admin');
        TenantContext::setSuperAdmin($isSuper);
        $companyId = SessionManager::get('rateb_company_id');
        $companyIdInt = $companyId !== null ? (int) $companyId : 0;
        // Platform Super Admin: tenant follows ops picker only (المنصة بدون شركة = no expiry banner).
        if ($isSuper && function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()
            && function_exists('rateb_resolve_ops_company_id')) {
            $companyIdInt = (int) rateb_resolve_ops_company_id();
            // Drop leftover rateb_company_id from a previous tenant preview so SaaS/middleware
            // cannot re-bind a company while the picker shows «بدون شركة».
            if ($companyIdInt < 1) {
                SessionManager::set('rateb_company_id', null);
            } else {
                SessionManager::set('rateb_company_id', $companyIdInt);
            }
        } elseif ($companyIdInt < 1 && $isSuper && function_exists('rateb_resolve_erp_shell_company_id')) {
            // Agency / dedicated SA — re-bind primary/ops tenant when session company is empty.
            $companyIdInt = (int) rateb_resolve_erp_shell_company_id();
            if ($companyIdInt > 0) {
                SessionManager::set('rateb_company_id', $companyIdInt);
            }
        }
        TenantContext::setCompanyId($companyIdInt > 0 ? $companyIdInt : null);
        $companyId = $companyIdInt > 0 ? $companyIdInt : null;
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

        // Phase 2 — read-only SubscriptionContext bind (no redirects / blocking).
        if (class_exists(\Rateb\App\Subscription\SubscriptionBootstrap::class)) {
            \Rateb\App\Subscription\SubscriptionBootstrap::bindForCompany(
                $companyId !== null ? (int) $companyId : null
            );
        }
    }

    /** True when user has a global platform staff role row (ignores is_super_admin flag). */
    private static function userHasAssignablePlatformStaffRoles(int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        $slugs = \Rateb\App\Services\AuthorizationService::platformRoleSlugs();
        $slugs = array_values(array_filter(
            $slugs,
            static fn (string $s): bool => $s !== '' && $s !== 'super-admin'
        ));
        if ($slugs === []) {
            $slugs = ['access-manager', 'accountant', 'accounting-approver'];
        }
        $named = [];
        $parts = [];
        foreach ($slugs as $i => $slug) {
            $key = 's' . $i;
            $parts[] = ':' . $key;
            $named[$key] = $slug;
        }
        $named['uid'] = $userId;
        $row = (new \Rateb\App\Models\Role())->queryOne(
            'SELECT 1 AS ok
             FROM rateb_user_roles ur
             INNER JOIN rateb_roles r ON r.id = ur.role_id
             WHERE ur.user_id = :uid
               AND r.company_id IS NULL
               AND r.slug IN (' . implode(',', $parts) . ')
             LIMIT 1',
            $named
        );

        return $row !== null;
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
        if (class_exists(\Rateb\App\Subscription\SubscriptionRuntime::class)) {
            \Rateb\App\Subscription\SubscriptionRuntime::reset();
        }
        if (class_exists(\Rateb\App\Subscription\SubscriptionAlertRuntime::class)) {
            \Rateb\App\Subscription\SubscriptionAlertRuntime::reset();
        }
    }
}
