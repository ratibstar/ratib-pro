<?php
declare(strict_types=1);

namespace App\Accounting\Integrity;

use App\Accounting\Infrastructure\AccountingConnectionFactory;

/**
 * Phase 5 persistence — separate from Phase 4 ProjectionRepository.
 */
final class IntegrityRepository
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

    /**
     * Read-only access to Phase 4 period closures.
     *
     * @return array{status:string, payload:array<string,mixed>}|null
     */
    public function fetchPeriodClosure(int $companyId, string $periodFrom, string $periodTo, ?int $branchId = null): ?array
    {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists('accounting_period_closures')) {
            return null;
        }

        $sql = 'SELECT status, payload FROM accounting_period_closures
                WHERE company_id = :cid AND period_from = :pf AND period_to = :pt';
        $params = ['cid' => $companyId, 'pf' => $periodFrom, 'pt' => $periodTo];
        if ($branchId !== null) {
            $sql .= ' AND branch_id = :bid';
            $params['bid'] = $branchId;
        } else {
            $sql .= ' AND branch_id IS NULL';
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $payload = json_decode((string) ($row['payload'] ?? '{}'), true);

        return [
            'status' => (string) ($row['status'] ?? 'open'),
            'payload' => is_array($payload) ? $payload : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchLockedPeriods(int $companyId, ?int $branchId = null): array
    {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists('accounting_period_closures')) {
            return [];
        }

        $sql = 'SELECT company_id, branch_id, period_from, period_to, status, payload, created_at
                FROM accounting_period_closures WHERE company_id = :cid';
        $params = ['cid' => $companyId];
        if ($branchId !== null) {
            $sql .= ' AND (branch_id = :bid OR branch_id IS NULL)';
            $params['bid'] = $branchId;
        }
        $sql .= ' ORDER BY period_from DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $payload = json_decode((string) ($row['payload'] ?? '{}'), true);
            $out[] = [
                'company_id' => (int) $row['company_id'],
                'branch_id' => $row['branch_id'] !== null ? (int) $row['branch_id'] : null,
                'period_from' => (string) $row['period_from'],
                'period_to' => (string) $row['period_to'],
                'status' => (string) $row['status'],
                'payload' => is_array($payload) ? $payload : [],
                'created_at' => (string) $row['created_at'],
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveReconciliationReport(
        int $companyId,
        string $periodFrom,
        string $periodTo,
        array $payload,
        string $riskLevel,
        ?int $branchId = null,
        ?int $driftReportId = null
    ): ?int {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists('accounting_reconciliation_reports')) {
            return null;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO accounting_reconciliation_reports
             (company_id, branch_id, period_from, period_to, drift_report_id, risk_level, payload)
             VALUES (:cid, :bid, :pf, :pt, :drift, :risk, :payload)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'bid' => $branchId,
            'pf' => $periodFrom,
            'pt' => $periodTo,
            'drift' => $driftReportId,
            'risk' => $riskLevel,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchReconciliationHistory(int $companyId, string $periodFrom, string $periodTo, ?int $branchId = null): array
    {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists('accounting_reconciliation_reports')) {
            return [];
        }

        $sql = 'SELECT id, drift_report_id, risk_level, payload, created_at
                FROM accounting_reconciliation_reports
                WHERE company_id = :cid AND period_from = :pf AND period_to = :pt';
        $params = ['cid' => $companyId, 'pf' => $periodFrom, 'pt' => $periodTo];
        if ($branchId !== null) {
            $sql .= ' AND branch_id = :bid';
            $params['bid'] = $branchId;
        }
        $sql .= ' ORDER BY id DESC LIMIT 50';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $payload = json_decode((string) ($row['payload'] ?? '{}'), true);
            $out[] = [
                'id' => (int) $row['id'],
                'drift_report_id' => $row['drift_report_id'] !== null ? (int) $row['drift_report_id'] : null,
                'risk_level' => (string) $row['risk_level'],
                'payload' => is_array($payload) ? $payload : [],
                'created_at' => (string) $row['created_at'],
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveCorrectionLog(
        int $companyId,
        string $idempotencyKey,
        array $payload,
        string $status = 'proposed',
        ?int $branchId = null,
        ?int $reconciliationReportId = null,
        ?int $ratebJournalEntryId = null
    ): ?int {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists('accounting_correction_log')) {
            return null;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO accounting_correction_log
             (company_id, branch_id, reconciliation_report_id, idempotency_key, status, payload, rateb_journal_entry_id, executed_at)
             VALUES (:cid, :bid, :recon, :key, :st, :payload, :je, :exec)
             ON DUPLICATE KEY UPDATE
               status = VALUES(status),
               payload = VALUES(payload),
               rateb_journal_entry_id = COALESCE(VALUES(rateb_journal_entry_id), rateb_journal_entry_id),
               executed_at = COALESCE(VALUES(executed_at), executed_at)'
        );
        $executedAt = $status === 'executed' ? date('Y-m-d H:i:s') : null;
        $stmt->execute([
            'cid' => $companyId,
            'bid' => $branchId,
            'recon' => $reconciliationReportId,
            'key' => $idempotencyKey,
            'st' => $status,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'je' => $ratebJournalEntryId,
            'exec' => $executedAt,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function correctionWasExecuted(string $idempotencyKey): bool
    {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists('accounting_correction_log')) {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT 1 FROM accounting_correction_log WHERE idempotency_key = :key AND status = :st LIMIT 1'
        );
        $stmt->execute(['key' => $idempotencyKey, 'st' => 'executed']);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchCorrectionLog(int $companyId, ?string $periodFrom = null, ?string $periodTo = null): array
    {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists('accounting_correction_log')) {
            return [];
        }

        $sql = 'SELECT id, idempotency_key, status, payload, rateb_journal_entry_id, created_at, executed_at
                FROM accounting_correction_log WHERE company_id = :cid';
        $params = ['cid' => $companyId];
        $sql .= ' ORDER BY id DESC LIMIT 100';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $payload = json_decode((string) ($row['payload'] ?? '{}'), true);
            if (!is_array($payload)) {
                $payload = [];
            }
            if ($periodFrom !== null && isset($payload['period_from']) && $payload['period_from'] !== $periodFrom) {
                continue;
            }
            if ($periodTo !== null && isset($payload['period_to']) && $payload['period_to'] !== $periodTo) {
                continue;
            }
            $out[] = [
                'id' => (int) $row['id'],
                'idempotency_key' => (string) $row['idempotency_key'],
                'status' => (string) $row['status'],
                'payload' => $payload,
                'rateb_journal_entry_id' => $row['rateb_journal_entry_id'] !== null ? (int) $row['rateb_journal_entry_id'] : null,
                'created_at' => (string) $row['created_at'],
                'executed_at' => $row['executed_at'] !== null ? (string) $row['executed_at'] : null,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveAuditEvidencePack(
        int $companyId,
        string $periodFrom,
        string $periodTo,
        array $payload,
        string $certificationHash,
        ?int $branchId = null
    ): ?int {
        $pdo = $this->db();
        if ($pdo === null || !$this->tableExists('accounting_audit_evidence_packs')) {
            return null;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO accounting_audit_evidence_packs
             (company_id, branch_id, period_from, period_to, certification_hash, payload)
             VALUES (:cid, :bid, :pf, :pt, :hash, :payload)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'bid' => $branchId,
            'pf' => $periodFrom,
            'pt' => $periodTo,
            'hash' => $certificationHash,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Read-only snapshot payload hash from Phase 4 tables.
     *
     * @return array<string, string>
     */
    public function computeSnapshotHashes(int $companyId, string $periodFrom, string $periodTo, ?int $branchId = null): array
    {
        $tables = [
            'trial_balance' => 'accounting_trial_balance_snapshots',
            'balance_sheet' => 'accounting_balance_sheet_snapshots',
            'profit_loss' => 'accounting_profit_loss_snapshots',
            'cashflow' => 'accounting_cashflow_snapshots',
            'consolidated_tb' => 'accounting_consolidated_trial_balance',
        ];

        $hashes = [];
        $pdo = $this->db();
        if ($pdo === null) {
            return $hashes;
        }

        foreach ($tables as $key => $table) {
            if (!$this->tableExists($table)) {
                $hashes[$key] = '';
                continue;
            }

            $sql = "SELECT payload FROM {$table} WHERE company_id = :cid AND period_from = :pf AND period_to = :pt";
            $params = ['cid' => $companyId, 'pf' => $periodFrom, 'pt' => $periodTo];
            if ($branchId !== null) {
                $sql .= ' AND branch_id = :bid';
                $params['bid'] = $branchId;
            }
            $sql .= ' ORDER BY id';

            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $concat = '';
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $concat .= (string) ($row['payload'] ?? '');
                }
                $hashes[$key] = $concat !== '' ? hash('sha256', $concat) : '';
            } catch (\Throwable) {
                $hashes[$key] = '';
            }
        }

        return $hashes;
    }
}
