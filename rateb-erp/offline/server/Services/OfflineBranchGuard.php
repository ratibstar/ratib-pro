<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Services\BranchAccessService;

/** Validates branch_id against authenticated branch permissions. */
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
}
