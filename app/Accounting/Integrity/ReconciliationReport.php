<?php
declare(strict_types=1);

namespace App\Accounting\Integrity;

/**
 * Phase 5 reconciliation output — decision layer over Phase 4 DriftReport.
 */
final class ReconciliationReport
{
    /**
     * @param list<array<string, mixed>> $driftItems
     * @param list<array<string, mixed>> $correctionSuggestions
     */
    public function __construct(
        public readonly int $companyId,
        public readonly string $periodFrom,
        public readonly string $periodTo,
        public readonly array $driftItems,
        public readonly array $correctionSuggestions,
        public readonly string $riskLevel = 'low',
        public readonly ?int $branchId = null,
        public readonly ?int $driftReportId = null,
        public readonly ?int $reportId = null,
    ) {
    }

    public function hasUnresolvedDrift(): bool
    {
        return $this->driftItems !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'report_id' => $this->reportId,
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'period_from' => $this->periodFrom,
            'period_to' => $this->periodTo,
            'drift_report_id' => $this->driftReportId,
            'drift_items' => $this->driftItems,
            'correction_suggestions' => $this->correctionSuggestions,
            'risk_level' => $this->riskLevel,
            'summary' => [
                'drift_count' => count($this->driftItems),
                'correction_count' => count($this->correctionSuggestions),
                'has_unresolved' => $this->hasUnresolvedDrift(),
            ],
        ];
    }
}
