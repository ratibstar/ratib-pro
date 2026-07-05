<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

use Rateb\App\Models\Branch;
use Rateb\App\Services\BranchAccessService;
use Rateb\App\Services\BranchIsolationService;

/** Branch isolation bridge — never bypass BranchContext. */
final class PosBranchBridgeService
{
    public function bootstrap(?int $companyId = null): void
    {
        (new BranchAccessService())->bootstrap($companyId);
    }

    public function assertCanAccess(int $branchId): void
    {
        (new BranchIsolationService())->assertCanAccess($branchId);
    }

    /** @return array{id: int, name: string}|null */
    public function label(int $branchId): ?array
    {
        if ($branchId < 1) {
            return null;
        }
        $row = (new Branch())->find($branchId);
        if (!$row) {
            return null;
        }
        return [
            'id' => $branchId,
            'name' => trim((string) ($row['name'] ?? $row['name_ar'] ?? '')),
        ];
    }

    /** @param array<string, mixed> $data */
    public function stampCreate(array $data): array
    {
        return (new BranchIsolationService())->stampCreate($data);
    }
}
