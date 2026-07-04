<?php
declare(strict_types=1);

namespace App\Accounting\Projections;

use App\Accounting\Infrastructure\AccountingConnectionFactory;

/**
 * Persistence for materialized financial snapshot tables (Phase 4).
 */
final class ProjectionRepository
{
    public function __construct(
        private readonly ?\PDO $pdo = null,
    ) {
    }

    private function db(): ?\PDO
    {
        return $this->pdo ?? AccountingConnectionFactory::pdo();
    }

    public function tableExists(string $table): bool
    {
        $pdo = $this->db();
        if ($pdo === null) {
            return false;
        }
        try {
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    public function isPeriodClosed(int $companyId, string $periodStart, string $periodEnd): bool
    {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists('accounting_period_closures')) {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT 1 FROM accounting_period_closures
             WHERE company_id = :cid AND period_start = :ps AND period_end = :pe AND status = \'closed\' LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'ps' => $periodStart, 'pe' => $periodEnd]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function replaceTrialBalanceSnapshots(
        int $companyId,
        ?int $branchId,
        string $periodStart,
        string $periodEnd,
        array $rows
    ): int {
        return $this->replaceSnapshotBatch(
            'accounting_trial_balance_snapshots',
            $companyId,
            $branchId,
            $periodStart,
            $periodEnd,
            $rows,
            static function (array $r) use ($companyId, $branchId, $periodStart, $periodEnd): array {
                $code = (string) ($r['account_code'] ?? '');
                $key = implode('|', [$companyId, $branchId ?? 0, $periodStart, $periodEnd, 'tb', $code]);

                return [
                    'snapshot_key' => $key,
                    'account_code' => $code,
                    'account_name' => $r['account_name'] ?? null,
                    'debit_total' => $r['debit'] ?? $r['debit_total'] ?? 0,
                    'credit_total' => $r['credit'] ?? $r['credit_total'] ?? 0,
                    'balance' => $r['balance'] ?? (($r['debit'] ?? 0) - ($r['credit'] ?? 0)),
                    'source_systems' => json_encode($r['source_systems'] ?? []),
                ];
            }
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function replaceBalanceSheetSnapshots(
        int $companyId,
        ?int $branchId,
        string $periodStart,
        string $periodEnd,
        array $rows
    ): int {
        return $this->replaceSnapshotBatch(
            'accounting_balance_sheet_snapshots',
            $companyId,
            $branchId,
            $periodStart,
            $periodEnd,
            $rows,
            static function (array $r) use ($companyId, $branchId, $periodStart, $periodEnd): array {
                $code = (string) ($r['account_code'] ?? '');
                $section = (string) ($r['section'] ?? 'asset');
                $key = implode('|', [$companyId, $branchId ?? 0, $periodStart, $periodEnd, 'bs', $section, $code]);

                return [
                    'snapshot_key' => $key,
                    'section' => $section,
                    'account_code' => $code,
                    'account_name' => $r['account_name'] ?? null,
                    'amount' => $r['amount'] ?? 0,
                    'source_systems' => json_encode($r['source_systems'] ?? []),
                ];
            }
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function replaceProfitLossSnapshots(
        int $companyId,
        ?int $branchId,
        string $periodStart,
        string $periodEnd,
        array $rows
    ): int {
        return $this->replaceSnapshotBatch(
            'accounting_profit_loss_snapshots',
            $companyId,
            $branchId,
            $periodStart,
            $periodEnd,
            $rows,
            static function (array $r) use ($companyId, $branchId, $periodStart, $periodEnd): array {
                $code = (string) ($r['account_code'] ?? '');
                $cat = (string) ($r['category'] ?? 'expense');
                $key = implode('|', [$companyId, $branchId ?? 0, $periodStart, $periodEnd, 'pl', $cat, $code]);

                return [
                    'snapshot_key' => $key,
                    'category' => $cat,
                    'account_code' => $code,
                    'account_name' => $r['account_name'] ?? null,
                    'amount' => $r['amount'] ?? 0,
                    'source_systems' => json_encode($r['source_systems'] ?? []),
                ];
            }
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function replaceCashflowSnapshots(
        int $companyId,
        ?int $branchId,
        string $periodStart,
        string $periodEnd,
        array $rows
    ): int {
        return $this->replaceSnapshotBatch(
            'accounting_cashflow_snapshots',
            $companyId,
            $branchId,
            $periodStart,
            $periodEnd,
            $rows,
            static function (array $r) use ($companyId, $branchId, $periodStart, $periodEnd): array {
                $cat = (string) ($r['category'] ?? 'operating');
                $code = (string) ($r['account_code'] ?? $cat);
                $key = implode('|', [$companyId, $branchId ?? 0, $periodStart, $periodEnd, 'cf', $cat, $code]);

                return [
                    'snapshot_key' => $key,
                    'category' => $cat,
                    'account_code' => $r['account_code'] ?? null,
                    'description' => $r['description'] ?? null,
                    'amount' => $r['amount'] ?? 0,
                    'source_systems' => json_encode($r['source_systems'] ?? []),
                ];
            }
        );
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function recordPeriodClosure(
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $meta,
        string $status = 'closed'
    ): bool {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists('accounting_period_closures')) {
            return false;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO accounting_period_closures (company_id, period_start, period_end, status, snapshot_meta)
             VALUES (:cid, :ps, :pe, :st, :meta)
             ON DUPLICATE KEY UPDATE status = VALUES(status), snapshot_meta = VALUES(snapshot_meta)'
        );

        return $stmt->execute([
            'cid' => $companyId,
            'ps' => $periodStart,
            'pe' => $periodEnd,
            'st' => $status,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * @param array<string, mixed> $findings
     */
    public function saveDriftReport(
        ?int $companyId,
        ?string $periodStart,
        ?string $periodEnd,
        array $findings
    ): ?int {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists('accounting_drift_reports')) {
            return null;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO accounting_drift_reports
            (company_id, period_start, period_end, status, missing_entries, duplicate_entries, mismatched_amounts, orphan_transactions, summary)
            VALUES (:cid, :ps, :pe, :st, :miss, :dup, :mis, :orph, :sum)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'ps' => $periodStart,
            'pe' => $periodEnd,
            'st' => 'completed',
            'miss' => json_encode($findings['missing_entries'] ?? []),
            'dup' => json_encode($findings['duplicate_entries'] ?? []),
            'mis' => json_encode($findings['mismatched_amounts'] ?? []),
            'orph' => json_encode($findings['orphan_transactions'] ?? []),
            'sum' => json_encode($findings['summary'] ?? []),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param callable(array): array $mapRow
     */
    private function replaceSnapshotBatch(
        string $table,
        int $companyId,
        ?int $branchId,
        string $periodStart,
        string $periodEnd,
        array $rows,
        callable $mapRow
    ): int {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists($table)) {
            return 0;
        }

        if ($this->isPeriodClosed($companyId, $periodStart, $periodEnd)) {
            return 0;
        }

        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare(
                "DELETE FROM {$table} WHERE company_id = :cid AND period_start = :ps AND period_end = :pe"
                . ($branchId !== null ? ' AND branch_id = :bid' : ' AND branch_id IS NULL')
            );
            $delParams = ['cid' => $companyId, 'ps' => $periodStart, 'pe' => $periodEnd];
            if ($branchId !== null) {
                $delParams['bid'] = $branchId;
            }
            $del->execute($delParams);

            $inserted = 0;
            foreach ($rows as $row) {
                $mapped = $mapRow($row);
                $cols = array_merge([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                ], $mapped);

                $fields = array_keys($cols);
                $placeholders = array_map(static fn (string $f): string => ':' . $f, $fields);
                $sql = "INSERT INTO {$table} (" . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($cols);
                $inserted++;
            }

            $pdo->commit();

            return $inserted;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log("ProjectionRepository::replaceSnapshotBatch failed: {$e->getMessage()}");

            return 0;
        }
    }
}
