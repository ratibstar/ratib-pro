<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
use Rateb\App\Services\BranchAccessService;

/** Validates branch_id against authenticated branch permissions + company ownership. */
final class OfflineBranchGuard
{
    public function __construct(
        private ?BranchAccessService $access = null,
    ) {
    }

    private function access(): BranchAccessService
    {
        return $this->access ??= new BranchAccessService();
    }

    /**
     * Push-time check: caller must be allowed to access the branch.
     *
     * @return array{ok: bool, branch_id: int|null, error?: string}
     */
    public function validate(?int $branchId): array
    {
        $branchId = (int) ($branchId ?? 0);
        if ($branchId < 1) {
            return ['ok' => true, 'branch_id' => null];
        }

        try {
            if (!$this->access()->canAccessBranch($branchId)) {
                return ['ok' => false, 'branch_id' => $branchId, 'error' => 'branch_denied'];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'branch_id' => $branchId, 'error' => 'branch_denied'];
        }

        return ['ok' => true, 'branch_id' => $branchId];
    }

    /**
     * Replay-time check: queue branch must belong to the queue company (no payload trust).
     * Does not depend on the current session user's branch ACL (background workers).
     *
     * @return array{ok: bool, branch_id: int|null, error?: string}
     */
    public function validateOwnedByCompany(?int $branchId, int $companyId): array
    {
        $branchId = (int) ($branchId ?? 0);
        if ($branchId < 1) {
            return ['ok' => true, 'branch_id' => null];
        }
        if ($companyId < 1) {
            return ['ok' => false, 'branch_id' => $branchId, 'error' => 'branch_denied'];
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT id FROM rateb_branches WHERE id = :bid AND company_id = :cid LIMIT 1'
            );
            $stmt->execute(['bid' => $branchId, 'cid' => $companyId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return ['ok' => false, 'branch_id' => $branchId, 'error' => 'branch_denied'];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'branch_id' => $branchId, 'error' => 'branch_denied'];
        }

        return ['ok' => true, 'branch_id' => $branchId];
    }
}
