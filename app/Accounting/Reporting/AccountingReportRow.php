<?php
declare(strict_types=1);

namespace App\Accounting\Reporting;

final class AccountingReportRow
{
    public function __construct(
        public readonly string $accountCode,
        public readonly string $accountName,
        public readonly float $debit,
        public readonly float $credit,
        public readonly string $sourceSystem,
        public readonly ?int $companyId = null,
        public readonly ?int $branchId = null,
        public readonly ?string $entryDate = null,
        public readonly ?string $referenceType = null,
        public readonly int|string|null $referenceId = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'account_code' => $this->accountCode,
            'account_name' => $this->accountName,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'source_system' => $this->sourceSystem,
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'entry_date' => $this->entryDate,
            'reference_type' => $this->referenceType,
            'reference_id' => $this->referenceId,
        ];
    }
}
