<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use Rateb\App\Models\User;

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

        $isSuper = (int) ($user['is_super_admin'] ?? 0) === 1;
        if ($portal === 'admin' && !$isSuper) {
            return null;
        }
        if ($portal === 'company' && $isSuper) {
            return null;
        }

        SessionManager::set('rateb_user_id', (int) $user['id']);
        SessionManager::set('rateb_company_id', $user['company_id'] !== null ? (int) $user['company_id'] : null);
        SessionManager::set('rateb_is_super_admin', $isSuper);
        SessionManager::set('rateb_portal', $portal);

        TenantContext::setSuperAdmin($isSuper);
        TenantContext::setCompanyId($user['company_id'] !== null ? (int) $user['company_id'] : null);

        return $user;
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
        SessionManager::forget('rateb_user_id');
        SessionManager::forget('rateb_company_id');
        SessionManager::forget('rateb_is_super_admin');
        SessionManager::forget('rateb_portal');
        TenantContext::setCompanyId(null);
        TenantContext::setSuperAdmin(false);
    }

    public static function bootstrapFromSession(): void
    {
        TenantContext::setSuperAdmin((bool) SessionManager::get('rateb_is_super_admin'));
        $companyId = SessionManager::get('rateb_company_id');
        TenantContext::setCompanyId($companyId !== null ? (int) $companyId : null);
    }
}
