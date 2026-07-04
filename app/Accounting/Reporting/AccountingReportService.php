<?php
declare(strict_types=1);

namespace App\Accounting\Reporting;

use App\Accounting\Normalization\AccountingNormalizer;

/**
 * Unified READ-ONLY cross-system financial reporting.
 * Never writes to source databases.
 */
final class AccountingReportService
{
    public function __construct(
        private readonly AccountingNormalizer $normalizer = new AccountingNormalizer(),
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{rows:list<array<string,mixed>>, totals:array{debit:float,credit:float}, by_source:array<string,array{debit:float,credit:float}>}
     */
    public function trialBalance(array $filters = []): array
    {
        $rows = $this->normalizer->normalizeAll($filters);
        $aggregated = $this->aggregateByAccount($rows);

        return [
            'rows' => array_map(static fn (AccountingReportRow $r) => $r->toArray(), $aggregated),
            'totals' => $this->sumTotals($aggregated),
            'by_source' => $this->sumBySource($rows),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{revenue:float, expense:float, net:float, rows:list<array<string,mixed>>}
     */
    public function profitAndLoss(array $filters = []): array
    {
        $rows = $this->normalizer->normalizeAll($filters);
        $revenue = 0.0;
        $expense = 0.0;
        $detail = [];

        foreach ($rows as $row) {
            $code = $row->accountCode;
            if ($this->isRevenueAccount($code)) {
                $revenue += $row->credit - $row->debit;
                $detail[] = $row->toArray();
            } elseif ($this->isExpenseAccount($code)) {
                $expense += $row->debit - $row->credit;
                $detail[] = $row->toArray();
            }
        }

        return [
            'revenue' => round($revenue, 2),
            'expense' => round($expense, 2),
            'net' => round($revenue - $expense, 2),
            'rows' => $detail,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{assets:float, liabilities:float, equity:float, rows:list<array<string,mixed>>}
     */
    public function balanceSheet(array $filters = []): array
    {
        $rows = $this->normalizer->normalizeAll($filters);
        $assets = 0.0;
        $liabilities = 0.0;
        $equity = 0.0;
        $detail = [];

        foreach ($rows as $row) {
            $type = $this->accountTypeFromCode($row->accountCode);
            $net = $row->debit - $row->credit;
            match ($type) {
                'asset' => $assets += $net,
                'liability' => $liabilities += -$net,
                'equity' => $equity += -$net,
                default => null,
            };
            if ($type !== 'unknown') {
                $detail[] = $row->toArray();
            }
        }

        return [
            'assets' => round($assets, 2),
            'liabilities' => round($liabilities, 2),
            'equity' => round($equity, 2),
            'rows' => $detail,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{operating:float, investing:float, financing:float, net:float, rows:list<array<string,mixed>>}
     */
    public function cashFlow(array $filters = []): array
    {
        $rows = $this->normalizer->normalizeAll($filters);
        $operating = 0.0;
        $investing = 0.0;
        $financing = 0.0;
        $detail = [];

        foreach ($rows as $row) {
            if (!$this->isCashAccount($row->accountCode)) {
                continue;
            }
            $net = $row->debit - $row->credit;
            $category = (string) ($row->referenceType ?? 'operating');
            match (true) {
                strpos($category, 'invest') !== false => $investing += $net,
                strpos($category, 'finance') !== false, strpos($category, 'loan') !== false => $financing += $net,
                default => $operating += $net,
            };
            $detail[] = $row->toArray();
        }

        return [
            'operating' => round($operating, 2),
            'investing' => round($investing, 2),
            'financing' => round($financing, 2),
            'net' => round($operating + $investing + $financing, 2),
            'rows' => $detail,
        ];
    }

    /**
     * @param list<AccountingReportRow> $rows
     * @return list<AccountingReportRow>
     */
    private function aggregateByAccount(array $rows): array
    {
        /** @var array<string, AccountingReportRow> $map */
        $map = [];
        foreach ($rows as $row) {
            $key = $row->sourceSystem . '|' . $row->accountCode . '|' . ($row->companyId ?? 0);
            if (!isset($map[$key])) {
                $map[$key] = new AccountingReportRow(
                    $row->accountCode,
                    $row->accountName,
                    0.0,
                    0.0,
                    $row->sourceSystem,
                    $row->companyId,
                    $row->branchId,
                );
            }
            $existing = $map[$key];
            $map[$key] = new AccountingReportRow(
                $existing->accountCode,
                $existing->accountName,
                $existing->debit + $row->debit,
                $existing->credit + $row->credit,
                $existing->sourceSystem,
                $existing->companyId,
                $existing->branchId,
            );
        }

        return array_values($map);
    }

    /**
     * @param list<AccountingReportRow> $rows
     * @return array{debit:float, credit:float}
     */
    private function sumTotals(array $rows): array
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach ($rows as $row) {
            $debit += $row->debit;
            $credit += $row->credit;
        }

        return ['debit' => round($debit, 2), 'credit' => round($credit, 2)];
    }

    /**
     * @param list<AccountingReportRow> $rows
     * @return array<string, array{debit:float, credit:float}>
     */
    private function sumBySource(array $rows): array
    {
        $bySource = [];
        foreach ($rows as $row) {
            if (!isset($bySource[$row->sourceSystem])) {
                $bySource[$row->sourceSystem] = ['debit' => 0.0, 'credit' => 0.0];
            }
            $bySource[$row->sourceSystem]['debit'] += $row->debit;
            $bySource[$row->sourceSystem]['credit'] += $row->credit;
        }

        foreach ($bySource as $source => $totals) {
            $bySource[$source]['debit'] = round($totals['debit'], 2);
            $bySource[$source]['credit'] = round($totals['credit'], 2);
        }

        return $bySource;
    }

    private function isRevenueAccount(string $code): bool
    {
        return strncmp($code, '4', 1) === 0 || strncmp($code, '41', 2) === 0;
    }

    private function isExpenseAccount(string $code): bool
    {
        return strncmp($code, '5', 1) === 0 || strncmp($code, '6', 1) === 0;
    }

    private function isCashAccount(string $code): bool
    {
        return strncmp($code, '10', 2) === 0
            || strncmp($code, '11', 2) === 0
            || stripos($code, 'cash') !== false;
    }

    private function accountTypeFromCode(string $code): string
    {
        if (strncmp($code, '1', 1) === 0) {
            return 'asset';
        }
        if (strncmp($code, '2', 1) === 0) {
            return 'liability';
        }
        if (strncmp($code, '3', 1) === 0) {
            return 'equity';
        }

        return 'unknown';
    }
}
