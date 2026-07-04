<?php
declare(strict_types=1);

namespace App\Accounting\Integrity;

/**
 * Canonical truth view — rateb_* normalized as primary ledger.
 */
final class GoldenLedgerView
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(
        public readonly int $companyId,
        public readonly string $periodFrom,
        public readonly string $periodTo,
        public readonly array $rows,
        public readonly float $totalDebit,
        public readonly float $totalCredit,
        public readonly ?int $branchId = null,
    ) {
    }

    public function isBalanced(float $tolerance = 0.05): bool
    {
        return abs($this->totalDebit - $this->totalCredit) <= $tolerance;
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
            'canonical_source' => 'rateb-erp',
            'rows' => $this->rows,
            'totals' => ['debit' => $this->totalDebit, 'credit' => $this->totalCredit],
            'balanced' => $this->isBalanced(),
        ];
    }
}
