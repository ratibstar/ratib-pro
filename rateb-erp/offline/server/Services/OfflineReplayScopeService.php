<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * Builds replay scope from authenticated queue row only (Phase 7.1 / H-BRANCH-001).
 * Never reads company_id / branch_id / user_id / device_id from client payload.
 */
final class OfflineReplayScopeService
{
    /**
     * @param array<string, mixed> $queueRow
     * @return array{company_id: int, branch_id: int, user_id: int, device_id: string}
     */
    public function fromQueueRow(array $queueRow): array
    {
        $companyId = (int) ($queueRow['company_id'] ?? 0);
        $branchId = (int) ($queueRow['branch_id'] ?? 0);

        if ($branchId > 0) {
            $check = (new OfflineBranchGuard())->validateOwnedByCompany($branchId, $companyId);
            if (!$check['ok']) {
                throw new \RuntimeException('branch_denied');
            }
        }

        return [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'user_id' => (int) ($queueRow['user_id'] ?? 0),
            'device_id' => (string) ($queueRow['device_id'] ?? ''),
        ];
    }
}
