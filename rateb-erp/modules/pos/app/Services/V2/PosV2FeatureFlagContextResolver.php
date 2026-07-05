<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2;

use Rateb\App\Core\BranchContext;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2FeatureFlagContext;
use Rateb\App\Pos\Services\PosSessionService;

/**
 * Builds PosV2FeatureFlagContext from session, branch scope, and request headers.
 */
final class PosV2FeatureFlagContextResolver
{
    public function resolve(): ?PosV2FeatureFlagContext
    {
        $companyId = $this->resolveCompanyId();
        if ($companyId < 1) {
            return null;
        }

        return new PosV2FeatureFlagContext(
            companyId: $companyId,
            branchId: $this->resolveBranchId(),
            terminalId: $this->resolveTerminalId(),
        );
    }

    private function resolveCompanyId(): int
    {
        $fromTenant = TenantContext::companyId();
        if ($fromTenant !== null && $fromTenant > 0) {
            return (int) $fromTenant;
        }

        if (function_exists('rateb_resolve_ops_company_id')) {
            $resolved = rateb_resolve_ops_company_id();
            if ($resolved > 0) {
                return $resolved;
            }
        }

        return (int) SessionManager::get('rateb_company_id', 0);
    }

    private function resolveBranchId(): int
    {
        $filterIds = BranchContext::effectiveFilterIds();
        if (count($filterIds) === 1) {
            return (int) $filterIds[0];
        }

        $portalBranch = (int) SessionManager::get('rateb_portal_branch_id', 0);
        if ($portalBranch > 0) {
            return $portalBranch;
        }

        $activeBranch = (int) SessionManager::get('rateb_active_branch_id', 0);
        if ($activeBranch > 0) {
            return $activeBranch;
        }

        return 0;
    }

    private function resolveTerminalId(): int
    {
        $header = $_SERVER['HTTP_X_RATEB_TERMINAL_ID'] ?? '';
        if ($header !== '' && ctype_digit((string) $header)) {
            return (int) $header;
        }

        $query = $_GET['terminal_id'] ?? $_POST['terminal_id'] ?? null;
        if ($query !== null && $query !== '' && ctype_digit((string) $query)) {
            return (int) $query;
        }

        $session = (new PosSessionService())->snapshot();

        return (int) ($session['terminal_id'] ?? 0);
    }
}
