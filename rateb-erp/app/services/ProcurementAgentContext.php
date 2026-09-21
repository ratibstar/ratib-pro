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
            if (function_exists('rateb_resolve_ops_company_id')) {
                $ops = (int) rateb_resolve_ops_company_id();
                if ($ops > 0) {
                    TenantContext::setCompanyId($ops);
                    $companyId = $ops;
                }
            }
        }
        if ($companyId === null || $companyId < 1) {
            $sessionCompany = (int) SessionManager::get('rateb_company_id', 0);
            if ($sessionCompany > 0) {
                if (function_exists('rateb_adopt_ops_company_id')) {
                    $sessionCompany = (int) rateb_adopt_ops_company_id($sessionCompany);
                }
                if ($sessionCompany > 0) {
                    TenantContext::setCompanyId($sessionCompany);
                    $companyId = $sessionCompany;
                }
            }
        }
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
     * Check if user has a specific permission (honors permission_implies like rateb_can).
     */
    public function can(string $permission): bool
    {
        if ($this->isSuperAdmin) {
            return true;
        }
        if ($permission === '') {
            return true;
        }
        if (in_array($permission, $this->permissions, true)) {
            return true;
        }

        static $implies = null;
        if ($implies === null) {
            $cfgFile = (defined('RATEB_ROOT') ? RATEB_ROOT : '') . '/config/permissions-system.php';
            $cfg = is_file($cfgFile) ? require $cfgFile : [];
            $implies = is_array($cfg['permission_implies'] ?? null) ? $cfg['permission_implies'] : [];
        }
        foreach ($implies as $parent => $children) {
            if (!in_array((string) $parent, $this->permissions, true)) {
                continue;
            }
            foreach ((array) $children as $child) {
                if ((string) $child === $permission) {
                    return true;
                }
            }
        }

        return false;
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

    /**
     * Stable conversation scope key — never reuse across tenants or users.
     */
    public function conversationScopeKey(): string
    {
        return 'procurement_agent:' . $this->companyId . ':' . $this->userId . ':' . $this->sessionId;
    }

    /**
     * Sanitize chat history for LLM: roles/content only, tenant-safe, length-capped.
     * Drops system/tool roles and any injected company/user metadata.
     *
     * @param mixed $history
     * @return list<array{role: string, content: string}>
     */
    public function sanitizeHistory($history, int $maxMessages = 20, int $maxContentChars = 4000): array
    {
        if (!is_array($history)) {
            return [];
        }

        $allowedRoles = ['user' => true, 'assistant' => true];
        $clean = [];
        foreach ($history as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $role = strtolower(trim((string) ($msg['role'] ?? '')));
            if (!isset($allowedRoles[$role])) {
                continue;
            }
            $content = (string) ($msg['content'] ?? '');
            $content = trim($content);
            if ($content === '') {
                continue;
            }
            // Strip accidental cross-tenant / identity injection from client payloads.
            if (preg_match('/^\s*\{.*"(company_id|user_id|tenant_id|session_id)"\s*:/s', $content) === 1) {
                continue;
            }
            // Drop leaked credentials / API secrets from client history payloads.
            if (preg_match('/"(api[_-]?key|access[_-]?token|secret|password|bearer)"\s*:/i', $content) === 1
                || preg_match('/\b(api[_-]?key|access[_-]?token|bearer)\s*[:=]/i', $content) === 1
            ) {
                continue;
            }
            if (preg_match('/\b(company_id|user_id)\s*[:=]\s*\d+/i', $content) === 1
                && preg_match('/\p{Arabic}|[A-Za-z]{3,}/u', preg_replace('/\b(company_id|user_id)\s*[:=]\s*\d+/i', '', $content) ?? '') !== 1
            ) {
                continue;
            }
            // Drop leaked tool/system dumps from prior turns.
            if (str_starts_with($content, 'tool:') || str_starts_with($content, 'SYSTEM:')) {
                continue;
            }
            if (mb_strlen($content) > $maxContentChars) {
                $content = mb_substr($content, 0, $maxContentChars);
            }
            $clean[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        if (count($clean) > $maxMessages) {
            $clean = array_slice($clean, -$maxMessages);
        }

        return array_values($clean);
    }

    /**
     * Normalize locale to ar|en from trusted session context only.
     */
    public function normalizedLocale(): string
    {
        $locale = strtolower(trim($this->locale));
        return $locale === 'ar' ? 'ar' : 'en';
    }
}