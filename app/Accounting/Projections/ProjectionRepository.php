<?php
declare(strict_types=1);

namespace App\Accounting\Projections;

use App\Accounting\Infrastructure\AccountingConnectionFactory;

/**
 * Persistence for materialized financial snapshot tables (Phase 4).
 * All snapshot rows use: company_id, branch_id, period_from, period_to, payload, created_at.
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

    public function isPeriodHardClosed(int $companyId, string $periodFrom, string $periodTo, ?int $branchId = null): bool
    {
        return $this->periodHasStatus($companyId, $periodFrom, $periodTo, 'hard_closed', $branchId);
    }

    public function isPeriodSoftClosed(int $companyId, string $periodFrom, string $periodTo, ?int $branchId = null): bool
    {
        return $this->periodHasStatus($companyId, $periodFrom, $periodTo, 'soft_closed', $branchId)
            || $this->isPeriodHardClosed($companyId, $periodFrom, $periodTo, $branchId);
    }

    /** @deprecated alias — hard close locks snapshot writes */
    public function isPeriodClosed(int $companyId, string $periodStart, string $periodEnd): bool
    {
        return $this->isPeriodHardClosed($companyId, $periodStart, $periodEnd);
    }

    private function periodHasStatus(int $companyId, string $periodFrom, string $periodTo, string $status, ?int $branchId): bool
    {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists('accounting_period_closures')) {
            return false;
        }

        $sql = 'SELECT 1 FROM accounting_period_closures
                WHERE company_id = :cid AND period_from = :pf AND period_to = :pt AND status = :st';
        $params = ['cid' => $companyId, 'pf' => $periodFrom, 'pt' => $periodTo, 'st' => $status];
        if ($branchId !== null) {
            $sql .= ' AND branch_id = :bid';
            $params['bid'] = $branchId;
        } else {
            $sql .= ' AND branch_id IS NULL';
        }
        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function replaceTrialBalanceSnapshots(int $companyId, ?int $branchId, string $periodFrom, string $periodTo, array $rows): int
    {
        return $this->replacePayloadSnapshots('accounting_trial_balance_snapshots', $companyId, $branchId, $periodFrom, $periodTo, $rows, 'tb');
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function replaceBalanceSheetSnapshots(int $companyId, ?int $branchId, string $periodFrom, string $periodTo, array $rows): int
    {
        return $this->replacePayloadSnapshots('accounting_balance_sheet_snapshots', $companyId, $branchId, $periodFrom, $periodTo, $rows, 'bs');
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function replaceProfitLossSnapshots(int $companyId, ?int $branchId, string $periodFrom, string $periodTo, array $rows): int
    {
        return $this->replacePayloadSnapshots('accounting_profit_loss_snapshots', $companyId, $branchId, $periodFrom, $periodTo, $rows, 'pl');
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function replaceCashflowSnapshots(int $companyId, ?int $branchId, string $periodFrom, string $periodTo, array $rows): int
    {
        return $this->replacePayloadSnapshots('accounting_cashflow_snapshots', $companyId, $branchId, $periodFrom, $periodTo, $rows, 'cf');
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordPeriodClosure(
        int $companyId,
        string $periodFrom,
        string $periodTo,
        array $payload,
        string $status = 'soft_closed',
        ?int $branchId = null
    ): bool {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists('accounting_period_closures')) {
            return false;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO accounting_period_closures (company_id, branch_id, period_from, period_to, status, payload)
             VALUES (:cid, :bid, :pf, :pt, :st, :payload)
             ON DUPLICATE KEY UPDATE status = VALUES(status), payload = VALUES(payload)'
        );

        return $stmt->execute([
            'cid' => $companyId,
            'bid' => $branchId,
            'pf' => $periodFrom,
            'pt' => $periodTo,
            'st' => $status,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * @param array<string, mixed> $findings
     */
    public function saveDriftReport(
        ?int $companyId,
        ?string $periodFrom,
        ?string $periodTo,
        array $findings,
        ?int $branchId = null
    ): ?int {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists('accounting_drift_reports')) {
            return null;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO accounting_drift_reports (company_id, branch_id, period_from, period_to, payload)
             VALUES (:cid, :bid, :pf, :pt, :payload)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'bid' => $branchId,
            'pf' => $periodFrom,
            'pt' => $periodTo,
            'payload' => json_encode($findings, JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchSnapshotPayloads(string $table, int $companyId, string $periodFrom, string $periodTo, ?int $branchId = null): array
    {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists($table)) {
            return [];
        }

        $sql = "SELECT payload FROM {$table} WHERE company_id = :cid AND period_from = :pf AND period_to = :pt";
        $params = ['cid' => $companyId, 'pf' => $periodFrom, 'pt' => $periodTo];
        if ($branchId !== null) {
            $sql .= ' AND branch_id = :bid';
            $params['bid'] = $branchId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $decoded = json_decode((string) ($row['payload'] ?? '{}'), true);
            if (is_array($decoded)) {
                $out[] = $decoded;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function replacePayloadSnapshots(
        string $table,
        int $companyId,
        ?int $branchId,
        string $periodFrom,
        string $periodTo,
        array $rows,
        string $prefix
    ): int {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists($table)) {
            return 0;
        }

        if ($this->isPeriodHardClosed($companyId, $periodFrom, $periodTo, $branchId)) {
            return 0;
        }

        $pdo->beginTransaction();
        try {
            $delSql = "DELETE FROM {$table} WHERE company_id = :cid AND period_from = :pf AND period_to = :pt"
                . ($branchId !== null ? ' AND branch_id = :bid' : ' AND branch_id IS NULL');
            $delParams = ['cid' => $companyId, 'pf' => $periodFrom, 'pt' => $periodTo];
            if ($branchId !== null) {
                $delParams['bid'] = $branchId;
            }
            $pdo->prepare($delSql)->execute($delParams);

            $stmt = $pdo->prepare(
                "INSERT INTO {$table} (company_id, branch_id, period_from, period_to, payload, snapshot_key)
                 VALUES (:cid, :bid, :pf, :pt, :payload, :key)"
            );

            $inserted = 0;
            foreach ($rows as $i => $row) {
                $code = (string) ($row['account_code'] ?? $row['category'] ?? "row{$i}");
                $key = implode('|', [$companyId, $branchId ?? 0, $periodFrom, $periodTo, $prefix, $code, $i]);
                $stmt->execute([
                    'cid' => $companyId,
                    'bid' => $branchId,
                    'pf' => $periodFrom,
                    'pt' => $periodTo,
                    'payload' => json_encode($row, JSON_UNESCAPED_UNICODE),
                    'key' => substr($key, 0, 191),
                ]);
                $inserted++;
            }

            $pdo->commit();

            return $inserted;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log("ProjectionRepository::replacePayloadSnapshots failed: {$e->getMessage()}");

            return 0;
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function insertConsolidatedRows(string $table, int $companyId, ?int $branchId, string $periodFrom, string $periodTo, string $runId, array $rows): int
    {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists($table)) {
            return 0;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO {$table} (company_id, branch_id, period_from, period_to, payload, consolidation_run_id)
             VALUES (:cid, :bid, :pf, :pt, :payload, :run)"
        );

        $n = 0;
        foreach ($rows as $row) {
            $stmt->execute([
                'cid' => $companyId,
                'bid' => $branchId,
                'pf' => $periodFrom,
                'pt' => $periodTo,
                'payload' => json_encode($row, JSON_UNESCAPED_UNICODE),
                'run' => $runId,
            ]);
            $n++;
        }

        return $n;
    }
}
