<?php
declare(strict_types=1);

namespace App\Accounting\Integrity;

use App\Accounting\Normalization\AccountingNormalizer;
use App\Accounting\Reporting\AccountingReportRow;

/**
 * Defines rateb_* as canonical truth and compares all four stacks.
 */
final class AccountingGoldenLedgerResolver
{
    private const CANONICAL = 'rateb-erp';

    /** @var list<string> */
    private const STACKS = ['rateb-erp', 'main-site', 'control-panel', 'ledger'];

    public function __construct(
        private readonly AccountingNormalizer $normalizer = new AccountingNormalizer(),
    ) {
    }

    /**
     * @param array<string, mixed> $params company_id, branch_id?, period_from, period_to
     */
    public function resolve(array $params): GoldenLedgerView
    {
        $companyId = (int) ($params['company_id'] ?? 0);
        $branchId = isset($params['branch_id']) ? (int) $params['branch_id'] : null;
        $periodFrom = (string) ($params['period_from'] ?? date('Y-m-01'));
        $periodTo = (string) ($params['period_to'] ?? date('Y-m-d'));

        $filters = [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'from_date' => $periodFrom,
            'to_date' => $periodTo,
        ];

        $rows = $this->normalizer->fromRatebErp($filters);
        $debit = 0.0;
        $credit = 0.0;
        $normalized = [];
        foreach ($rows as $row) {
            $debit += $row->debit;
            $credit += $row->credit;
            $normalized[] = array_merge($row->toArray(), ['canonical' => true]);
        }

        return new GoldenLedgerView(
            companyId: $companyId,
            periodFrom: $periodFrom,
            periodTo: $periodTo,
            rows: $normalized,
            totalDebit: $debit,
            totalCredit: $credit,
            branchId: $branchId,
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function detectConflicts(array $params): ConflictReport
    {
        $companyId = (int) ($params['company_id'] ?? 0);
        $branchId = isset($params['branch_id']) ? (int) $params['branch_id'] : null;
        $periodFrom = (string) ($params['period_from'] ?? date('Y-m-01'));
        $periodTo = (string) ($params['period_to'] ?? date('Y-m-d'));

        $filters = [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'from_date' => $periodFrom,
            'to_date' => $periodTo,
        ];

        $golden = $this->aggregateByAccount($this->normalizer->fromRatebErp($filters));
        $conflicts = [];

        foreach (self::STACKS as $stack) {
            if ($stack === self::CANONICAL) {
                continue;
            }

            $stackRows = match ($stack) {
                'main-site' => $this->normalizer->fromMainSite($filters),
                'control-panel' => $this->normalizer->fromControlPanel($filters),
                'ledger' => $this->normalizer->fromLedger($filters),
                default => [],
            };

            $stackAgg = $this->aggregateByAccount($stackRows);

            foreach ($stackAgg as $accountCode => $totals) {
                $goldenTotals = $golden[$accountCode] ?? ['debit' => 0.0, 'credit' => 0.0];
                $deltaDebit = abs($totals['debit'] - $goldenTotals['debit']);
                $deltaCredit = abs($totals['credit'] - $goldenTotals['credit']);

                if ($deltaDebit > 0.05 || $deltaCredit > 0.05) {
                    $conflicts[] = [
                        'account_code' => $accountCode,
                        'stack' => $stack,
                        'canonical_debit' => round($goldenTotals['debit'], 2),
                        'canonical_credit' => round($goldenTotals['credit'], 2),
                        'stack_debit' => round($totals['debit'], 2),
                        'stack_credit' => round($totals['credit'], 2),
                        'delta' => round(max($deltaDebit, $deltaCredit), 2),
                        'issue' => 'cross_stack_inconsistency',
                    ];
                }
            }
        }

        return new ConflictReport(
            companyId: $companyId,
            periodFrom: $periodFrom,
            periodTo: $periodTo,
            conflicts: $conflicts,
            branchId: $branchId,
        );
    }

    /**
     * @param list<AccountingReportRow> $rows
     * @return array<string, array{debit:float, credit:float}>
     */
    private function aggregateByAccount(array $rows): array
    {
        $agg = [];
        foreach ($rows as $row) {
            $code = $row->accountCode !== '' ? $row->accountCode : '_unknown';
            if (!isset($agg[$code])) {
                $agg[$code] = ['debit' => 0.0, 'credit' => 0.0];
            }
            $agg[$code]['debit'] += $row->debit;
            $agg[$code]['credit'] += $row->credit;
        }

        return $agg;
    }
}
