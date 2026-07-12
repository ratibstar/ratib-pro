<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Middleware;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Middleware\MiddlewareInterface;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;

/**
 * Auth for /api/v1/offline/* — Bearer API token OR ERP browser session.
 * Browser shell connectivity/probe uses credentials: same-origin (no Bearer).
 */
final class OfflineApiAuthMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        $bearer = '';
        if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
            $bearer = $m[1];
        }
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!\Rateb\App\Core\ApiRateLimiter::allowRequest($method, $bearer !== '' ? $bearer : null)) {
            Response::json(['success' => false, 'message' => 'Too many requests'], 429);

            return false;
        }

        if ($bearer !== '') {
            return $this->authenticateBearer($bearer);
        }

        return $this->authenticateSession();
    }

    private function authenticateBearer(string $bearer): bool
    {
        $tokenService = new \Rateb\App\Services\ApiTokenService();
        $tokenRow = $tokenService->validateToken($bearer);
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
        TenantContext::setApiModules(is_array($abilities) ? $abilities : []);
        TenantContext::setCompanyId($companyId);
        TenantContext::setSuperAdmin(false);
        $userId = (int) ($tokenRow['user_id'] ?? 0);
        $tokenBranchId = isset($tokenRow['branch_id']) ? (int) $tokenRow['branch_id'] : null;
        if ($tokenBranchId !== null && $tokenBranchId < 1) {
            $tokenBranchId = null;
        }
        \Rateb\App\Core\BranchContext::reset();
        TenantContext::setApiUserId($userId > 0 ? $userId : null);
        (new \Rateb\App\Services\BranchAccessService())->bootstrapForApi($companyId, $userId, $tokenBranchId);

        return true;
    }

    private function authenticateSession(): bool
    {
        Auth::bootstrapFromSession();
        if (!Auth::check()) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 401);

            return false;
        }

        $isSuper = (bool) SessionManager::get('rateb_is_super_admin');
        TenantContext::setSuperAdmin($isSuper);

        $companyId = 0;
        if (function_exists('rateb_resolve_erp_shell_company_id')) {
            $companyId = (int) rateb_resolve_erp_shell_company_id();
        }
        if ($companyId < 1) {
            $companyId = (int) (SessionManager::get('rateb_company_id', 0) ?? 0);
        }
        if ($companyId < 1) {
            $companyId = (int) (SessionManager::get('rateb_ops_company_id', 0) ?? 0);
        }
        if ($companyId < 1 && !$isSuper) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 401);

            return false;
        }
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
            if (function_exists('rateb_adopt_ops_company_id')) {
                rateb_adopt_ops_company_id($companyId);
            }
            if (function_exists('rateb_sync_ops_session_to_company')) {
                rateb_sync_ops_session_to_company($companyId);
            }
        }

        $userId = (int) (SessionManager::get('rateb_user_id', 0) ?? 0);
        TenantContext::setApiUserId($userId > 0 ? $userId : null);
        TenantContext::setApiModules(null);

        return true;
    }
}
