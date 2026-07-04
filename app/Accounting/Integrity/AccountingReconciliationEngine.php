<?php
declare(strict_types=1);

namespace App\Accounting\Integrity;

use App\Accounting\Drift\DriftReport;
use App\Accounting\Support\AccountingConfig;

/**
 * Decision layer — consumes Phase 4 DriftReport and proposes corrections (never auto-posts).
 */
final class AccountingReconciliationEngine
{
    public function __construct(
        private readonly IntegrityRepository $repository = new IntegrityRepository(),
        private readonly AccountingGoldenLedgerResolver $goldenLedger = new AccountingGoldenLedgerResolver(),
    ) {
    }

    public function isEnabled(): bool
    {
        return AccountingConfig::integrityEnabled();
    }

    /**
     * @param array<string, mixed> $params company_id, branch_id?, period_from, period_to
     */
    public function reconcileFromDrift(DriftReport $drift, array $params): ReconciliationReport
    {
        $companyId = (int) ($params['company_id'] ?? 0);
        $branchId = isset($params['branch_id']) ? (int) $params['branch_id'] : null;
        $periodFrom = (string) ($params['period_from'] ?? date('Y-m-01'));
        $periodTo = (string) ($params['period_to'] ?? date('Y-m-d'));

        $driftItems = $this->classifyDrift($drift, $params);
        $conflicts = $this->goldenLedger->detectConflicts([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
        ]);

        foreach ($conflicts->conflicts as $conflict) {
            $driftItems[] = array_merge($conflict, ['classification' => 'cross_stack_inconsistency']);
        }

        $corrections = $this->proposeCorrections($driftItems, $companyId, $branchId, $periodFrom, $periodTo);
        $riskLevel = $this->assessRisk($driftItems, $corrections);

        $report = new ReconciliationReport(
            companyId: $companyId,
            periodFrom: $periodFrom,
            periodTo: $periodTo,
            driftItems: $driftItems,
            correctionSuggestions: $corrections,
            riskLevel: $riskLevel,
            branchId: $branchId,
            driftReportId: $drift->reportId,
        );

        if ($this->isEnabled()) {
            $reportId = $this->repository->saveReconciliationReport(
                $companyId,
                $periodFrom,
                $periodTo,
                $report->toArray(),
                $riskLevel,
                $branchId,
                $drift->reportId
            );
            if ($reportId !== null) {
                $report = new ReconciliationReport(
                    companyId: $companyId,
                    periodFrom: $periodFrom,
                    periodTo: $periodTo,
                    driftItems: $driftItems,
                    correctionSuggestions: $corrections,
                    riskLevel: $riskLevel,
                    branchId: $branchId,
                    driftReportId: $drift->reportId,
                    reportId: $reportId,
                );
            }
        }

        return $report;
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function classifyDrift(DriftReport $drift, array $params): array
    {
        $items = [];

        foreach ($drift->missingEntries as $entry) {
            $items[] = array_merge($entry, ['classification' => 'missing_entry']);
        }
        foreach ($drift->duplicateEntries as $entry) {
            $items[] = array_merge($entry, ['classification' => 'duplicate_entry']);
        }
        foreach ($drift->mismatchedAmounts as $entry) {
            $items[] = array_merge($entry, ['classification' => 'mismatched_total']);
        }
        foreach ($drift->orphanTransactions as $entry) {
            $items[] = array_merge($entry, ['classification' => 'orphan_transaction']);
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $driftItems
     * @return list<array<string, mixed>>
     */
    private function proposeCorrections(
        array $driftItems,
        int $companyId,
        ?int $branchId,
        string $periodFrom,
        string $periodTo
    ): array {
        $suggestions = [];

        foreach ($driftItems as $i => $item) {
            $classification = (string) ($item['classification'] ?? 'unknown');
            $key = hash('sha256', json_encode([$companyId, $periodFrom, $periodTo, $classification, $item]) ?: '');

            $suggestion = [
                'idempotency_key' => 'recon-' . substr($key, 0, 40),
                'type' => 'none',
                'classification' => $classification,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'requires_approval' => true,
                'auto_post' => false,
                'lines' => [],
                'rationale' => '',
                'source_drift' => $item,
            ];

            switch ($classification) {
                case 'missing_entry':
                    $suggestion['type'] = 'adjustment';
                    $suggestion['rationale'] = 'Post balancing adjustment to canonical rateb ledger for missing event';
                    $suggestion['lines'] = $this->balancingLines(0.01, '3900', 'Reconciliation suspense — missing entry');
                    break;

                case 'duplicate_entry':
                    $suggestion['type'] = 'reversal';
                    $suggestion['rationale'] = 'Reverse duplicate posting in canonical rateb ledger';
                    $suggestion['lines'] = $this->reversalLines($item);
                    break;

                case 'mismatched_total':
                    $delta = (float) ($item['delta'] ?? $item['snapshot_debit'] ?? 0) - (float) ($item['ledger_debit'] ?? 0);
                    $suggestion['type'] = abs($delta) > 0.05 ? 'balancing' : 'none';
                    $suggestion['rationale'] = 'Balancing entry to align canonical ledger with golden view';
                    $suggestion['lines'] = $this->balancingLines(abs($delta), '3900', 'Reconciliation balancing');
                    break;

                case 'cross_stack_inconsistency':
                    $delta = (float) ($item['delta'] ?? 0);
                    $suggestion['type'] = abs($delta) > 0.05 ? 'adjustment' : 'none';
                    $suggestion['rationale'] = 'Align legacy stack variance into rateb canonical truth';
                    $suggestion['lines'] = $this->balancingLines(abs($delta), '3900', 'Cross-stack reconciliation');
                    break;

                case 'orphan_transaction':
                    $suggestion['type'] = 'reversal';
                    $suggestion['rationale'] = 'Reverse orphan/failed event that never posted to ledger';
                    $suggestion['lines'] = $this->reversalLines($item);
                    break;

                default:
                    $suggestion['rationale'] = 'Manual review required';
                    break;
            }

            if ($suggestion['type'] !== 'none' && $suggestion['lines'] !== []) {
                $suggestions[] = $suggestion;
            }
        }

        return $suggestions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function balancingLines(float $amount, string $suspenseCode, string $memo): array
    {
        if ($amount <= 0) {
            return [];
        }

        return [
            ['account_code' => $suspenseCode, 'debit' => round($amount, 2), 'credit' => 0.0, 'memo' => $memo],
            ['account_code' => '3900', 'debit' => 0.0, 'credit' => round($amount, 2), 'memo' => $memo . ' (offset)'],
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array<string, mixed>>
     */
    private function reversalLines(array $item): array
    {
        $amount = (float) ($item['amount'] ?? 1.0);

        return [
            ['account_code' => '3900', 'debit' => 0.0, 'credit' => round($amount, 2), 'memo' => 'Reversal — duplicate/orphan'],
            ['account_code' => '3900', 'debit' => round($amount, 2), 'credit' => 0.0, 'memo' => 'Reversal offset'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $driftItems
     * @param list<array<string, mixed>> $corrections
     */
    private function assessRisk(array $driftItems, array $corrections): string
    {
        $score = count($driftItems) + count($corrections);
        foreach ($driftItems as $item) {
            if (($item['classification'] ?? '') === 'cross_stack_inconsistency') {
                $score += 3;
            }
            if (($item['classification'] ?? '') === 'mismatched_total') {
                $score += 2;
            }
        }

        if ($score >= 8) {
            return 'high';
        }
        if ($score >= 3) {
            return 'medium';
        }

        return 'low';
    }
}
