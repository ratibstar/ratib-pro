<?php
declare(strict_types=1);

namespace App\Accounting\Drift;

/**
 * Audit-grade drift findings between event store and database state.
 */
final class DriftReport
{
    /**
     * @param list<array<string, mixed>> $missingEntries
     * @param list<array<string, mixed>> $duplicateEntries
     * @param list<array<string, mixed>> $mismatchedAmounts
     * @param list<array<string, mixed>> $orphanTransactions
     */
    public function __construct(
        public readonly array $missingEntries = [],
        public readonly array $duplicateEntries = [],
        public readonly array $mismatchedAmounts = [],
        public readonly array $orphanTransactions = [],
        public readonly ?int $reportId = null,
    ) {
    }

    public function hasDrift(): bool
    {
        return $this->missingEntries !== []
            || $this->duplicateEntries !== []
            || $this->mismatchedAmounts !== []
            || $this->orphanTransactions !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'report_id' => $this->reportId,
            'missing_entries' => $this->missingEntries,
            'duplicate_entries' => $this->duplicateEntries,
            'mismatched_amounts' => $this->mismatchedAmounts,
            'orphan_transactions' => $this->orphanTransactions,
            'summary' => [
                'missing' => count($this->missingEntries),
                'duplicate' => count($this->duplicateEntries),
                'mismatched' => count($this->mismatchedAmounts),
                'orphan' => count($this->orphanTransactions),
                'has_drift' => $this->hasDrift(),
            ],
        ];
    }
}
