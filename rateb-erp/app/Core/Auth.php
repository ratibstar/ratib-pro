<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use Rateb\App\Models\User;
use Rateb\App\Services\AccountLockoutService;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\RememberMeService;

final class Auth
{
    public static function attempt(string $email, string $password, string $portal = 'company'): ?array
    {
        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if (!$user || !password_verify($password, (string) $user['password'])) {
            return null;
        }

        if ((string) $user['status'] !== 'active') {
            return null;
        }

        $lockout = new AccountLockoutService();
        if ($lockout->isLocked($user)) {
            return null;
        }

        $isSuper = (int) ($user['is_super_admin'] ?? 0) === 1;
        if ($portal === 'admin' && !$isSuper) {
            return null;
        }
        if ($portal === 'company' && $isSuper) {
            return null;
        }

        if ($portal === 'company') {
            $companyId = (int) ($user['company_id'] ?? 0);
            if ($companyId < 1) {
                return null;
            }
            $company = (new \Rateb\App\Models\Company())->find($companyId);
            if (!$company || (string) ($company['status'] ?? '') !== 'active') {
                return null;
            }
            $limits = new \Rateb\App\Services\PlanLimitService();
            if (!$limits->companyAccessAllowed($companyId)) {
                return null;
            }
        }

        $lockout->clearLock((int) $user['id']);
        self::establishSession($user, $portal);
        return $user;
    }

    public static function attemptAuto(string $email, string $password): ?array
    {
        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if (!$user || !password_verify($password, (string) $user['password'])) {
            return null;
        }

        if ((string) $user['status'] !== 'active') {
            return null;
        }

        $lockout = new AccountLockoutService();
        if ($lockout->isLocked($user)) {
            return null;
        }

        $isSuper = (int) ($user['is_super_admin'] ?? 0) === 1;
        return self::attempt($email, $password, $isSuper ? 'admin' : 'company');
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
        TenantContext::setSuperAdmin($isSuper);
        TenantContext::setCompanyId($user['company_id'] !== null ? (int) $user['company_id'] : null);
    }

    public static function homePath(): string
    {
        if (SessionManager::get('rateb_is_super_admin')) {
            return 'admin';
        }
        if ((int) SessionManager::get('rateb_company_id', 0) > 0) {
            return 'site/portal';
        }
        return 'admin';
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
    }

    public static function bootstrapFromSession(): void
    {
        if (!SessionManager::get('rateb_user_id')) {
            $user = (new RememberMeService())->tryLogin();
            if ($user && self::loginUser($user)) {
                (new User())->updateLastLogin((int) $user['id']);
            }
        }
        TenantContext::setSuperAdmin((bool) SessionManager::get('rateb_is_super_admin'));
        $companyId = SessionManager::get('rateb_company_id');
        TenantContext::setCompanyId($companyId !== null ? (int) $companyId : null);
    }
}
