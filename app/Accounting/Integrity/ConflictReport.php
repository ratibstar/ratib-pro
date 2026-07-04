<?php
declare(strict_types=1);

namespace App\Accounting\Integrity;

/**
 * Cross-stack conflict report vs golden (rateb) ledger.
 */
final class ConflictReport
{
    /**
     * @param list<array<string, mixed>> $conflicts
     */
    public function __construct(
        public readonly int $companyId,
        public readonly string $periodFrom,
        public readonly string $periodTo,
        public readonly array $conflicts,
        public readonly ?int $branchId = null,
    ) {
    }

    public function hasConflicts(): bool
    {
        return $this->conflicts !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'period_from' => $this->periodFrom,
            'period_to' => $this->periodTo,
            'conflicts' => $this->conflicts,
            'conflict_count' => count($this->conflicts),
            'has_conflicts' => $this->hasConflicts(),
        ];
    }
}
