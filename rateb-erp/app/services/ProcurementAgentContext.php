<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Auth;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\AuthorizationService;
use Rateb\App\Services\PlanLimitService;

/**
 * Procurement Agent Context
 * Builds trusted context from authenticated session only.
 * NEVER trusts request-provided company_id, user_id, or tenant.
 */
final class ProcurementAgentContext
{
    public int $userId;
    public int $companyId;
    public string $locale;
    public string $sessionId;
    public array $permissions;
    public bool $isSuperAdmin;
    public array $enabledModules;

    private function __construct(
        int $userId,
        int $companyId,
        string $locale,
        string $sessionId,
        array $permissions,
        bool $isSuperAdmin,
        array $enabledModules
    ) {
        $this->userId = $userId;
        $this->companyId = $companyId;
        $this->locale = $locale;
        $this->sessionId = $sessionId;
        $this->permissions = $permissions;
        $this->isSuperAdmin = $isSuperAdmin;
        $this->enabledModules = $enabledModules;
    }

    /**
     * Create context from trusted session (Auth bootstrap already ran)
     */
    public static function fromSession(): ?self
    {
        // Auth::bootstrapFromSession() must have been called before this
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        $userId = (int) ($user['id'] ?? 0);
        if ($userId < 1) {
            return null;
        }

        $companyId = TenantContext::companyId();
        if ($companyId === null || $companyId < 1) {
            // No company context — cannot proceed with procurement tools
            return null;
        }

        return self::build(
            $userId,
            (int) $companyId,
            (string) SessionManager::get('rateb_locale', 'en'),
            session_id() ?: 'no-session',
            (bool) TenantContext::isSuperAdmin()
        );
    }

    /**
     * Create context from Bearer API auth (ApiAuthMiddleware already bound tenant + user id).
     * Never accepts request-provided company/user — only TenantContext.
     */
    public static function fromApiUser(int $userId, int $companyId): ?self
    {
        if ($userId < 1 || $companyId < 1) {
            return null;
        }
        if (TenantContext::companyId() !== $companyId) {
            return null;
        }
        $apiUid = TenantContext::apiUserId();
        if ($apiUid === null || $apiUid !== $userId) {
            return null;
        }

        $locale = (string) SessionManager::get('rateb_locale', 'en');
        if ($locale === '') {
            $locale = 'en';
        }

        return self::build(
            $userId,
            $companyId,
            $locale,
            'api:' . $userId,
            (bool) TenantContext::isSuperAdmin()
        );
    }

    private static function build(
        int $userId,
        int $companyId,
        string $locale,
        string $sessionId,
        bool $isSuperAdmin
    ): self {
        $authz = new AuthorizationService();
        $permissions = $authz->userPermissionSlugs($userId);

        $planLimits = new PlanLimitService();
        $enabledModules = [];
        $allModules = ['procurement', 'suppliers', 'inventory', 'hr', 'crm', 'accounting', 'manufacturing', 'assets', 'projects', 'quality', 'branches', 'contracts', 'tenders', 'recruitment', 'dashboard'];
        foreach ($allModules as $module) {
            if ($planLimits->companyHasModule($companyId, $module)) {
                $enabledModules[] = $module;
            }
        }

        return new self($userId, $companyId, $locale, $sessionId, $permissions, $isSuperAdmin, $enabledModules);
    }

    /**
     * Verify that a request-provided company_id matches trusted context
     */
    public function verifyCompanyId(?int $requestCompanyId): bool
    {
        if ($requestCompanyId === null) {
            return true; // Not provided, use trusted
        }
        return $requestCompanyId === $this->companyId;
    }

    /**
     * Check if user has a specific permission
     */
    public function can(string $permission): bool
    {
        if ($this->isSuperAdmin) {
            return true;
        }
        return in_array($permission, $this->permissions, true);
    }

    /**
     * Check if a module is enabled for the company
     */
    public function moduleEnabled(string $module): bool
    {
        return in_array($module, $this->enabledModules, true);
    }

    /**
     * Convert to array for audit logging
     */
    public function toAuditArray(): array
    {
        return [
            'user_id' => $this->userId,
            'company_id' => $this->companyId,
            'locale' => $this->locale,
            'session_id' => $this->sessionId,
            'is_super_admin' => $this->isSuperAdmin,
            'enabled_modules' => $this->enabledModules,
        ];
    }
}