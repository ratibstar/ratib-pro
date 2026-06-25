<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;

/** Enforces branch isolation on API v1 create/list payloads. */
final class ApiBranchGuardService
{
    /** @param array<string, mixed> $body */
    public function stampCreate(array $body): array
    {
        return (new BranchIsolationService())->stampCreate($body);
    }

    /**
     * Reject branch_id in body that is outside the caller's allowed branches.
     *
     * @param array<string, mixed> $body
     */
    public function rejectForeignBranchId(array $body): bool
    {
        if (!array_key_exists('branch_id', $body)) {
            return true;
        }
        $branchId = (int) $body['branch_id'];
        if ($branchId < 1) {
            return true;
        }
        $allowed = (new BranchIsolationService())->effectiveBranchIds();
        if ($allowed === []) {
            return true;
        }
        if (!in_array($branchId, $allowed, true)) {
            Response::json(['success' => false, 'message' => 'Branch scope violation'], 403);
            return false;
        }
        return true;
    }

    public function assertCompanyContext(): bool
    {
        $companyId = TenantContext::companyId();
        if ($companyId === null || $companyId < 1) {
            Response::json(['success' => false, 'message' => 'No company context'], 403);
            return false;
        }
        return true;
    }
}
