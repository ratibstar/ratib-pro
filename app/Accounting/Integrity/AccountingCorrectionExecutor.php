<?php
declare(strict_types=1);

namespace App\Accounting\Integrity;

use App\Accounting\Infrastructure\AccountingConnectionFactory;
use App\Accounting\Support\AccountingConfig;

/**
 * Executes approved reconciliation fixes — writes ONLY to rateb_* (canonical).
 * Auto-fix disabled by default; dry-run default true.
 */
final class AccountingCorrectionExecutor
{
    public function __construct(
        private readonly IntegrityRepository $repository = new IntegrityRepository(),
        private readonly ?\PDO $pdo = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return AccountingConfig::correctionExecutorEnabled();
    }

    /**
     * @param array<string, mixed> $proposal correction suggestion from ReconciliationReport
     * @param array<string, mixed> $options dry_run, approved, auto_fix
     * @return array{ok:bool, message:string, dry_run:bool, journal_entry_id?:int, log_id?:int}
     */
    public function execute(array $proposal, array $options = []): array
    {
        $dryRun = array_key_exists('dry_run', $options) ? (bool) $options['dry_run'] : true;
        $approved = !empty($options['approved']);
        $autoFix = !empty($options['auto_fix']) && AccountingConfig::correctionAutoFixEnabled();

        if (!$this->isEnabled()) {
            return ['ok' => false, 'message' => 'Correction executor disabled', 'dry_run' => $dryRun];
        }

        if (!$autoFix && !$approved) {
            return ['ok' => false, 'message' => 'Approval required — auto_fix disabled by default', 'dry_run' => $dryRun];
        }

        $idempotencyKey = (string) ($proposal['idempotency_key'] ?? '');
        if ($idempotencyKey === '') {
            return ['ok' => false, 'message' => 'Missing idempotency_key', 'dry_run' => $dryRun];
        }

        if ($this->repository->correctionWasExecuted($idempotencyKey)) {
            return ['ok' => true, 'message' => 'Already executed (idempotent skip)', 'dry_run' => false];
        }

        $companyId = (int) ($proposal['company_id'] ?? 0);
        $lines = $proposal['lines'] ?? [];
        if ($companyId < 1 || !is_array($lines) || $lines === []) {
            return ['ok' => false, 'message' => 'Invalid proposal — no lines', 'dry_run' => $dryRun];
        }

        $status = $dryRun ? 'dry_run' : 'proposed';
        $logId = $this->repository->saveCorrectionLog(
            $companyId,
            $idempotencyKey,
            $proposal,
            $status,
            isset($proposal['branch_id']) ? (int) $proposal['branch_id'] : null
        );

        if ($dryRun) {
            return [
                'ok' => true,
                'message' => 'Dry-run — no rateb write performed',
                'dry_run' => true,
                'log_id' => $logId,
            ];
        }

        $journalEntryId = $this->postToRateb($companyId, $proposal);
        if ($journalEntryId === null) {
            return ['ok' => false, 'message' => 'Failed to post correction to rateb', 'dry_run' => false, 'log_id' => $logId];
        }

        $this->repository->saveCorrectionLog(
            $companyId,
            $idempotencyKey,
            array_merge($proposal, ['rateb_journal_entry_id' => $journalEntryId]),
            'executed',
            isset($proposal['branch_id']) ? (int) $proposal['branch_id'] : null,
            null,
            $journalEntryId
        );

        return [
            'ok' => true,
            'message' => 'Correction posted to rateb canonical ledger',
            'dry_run' => false,
            'journal_entry_id' => $journalEntryId,
            'log_id' => $logId,
        ];
    }

    /**
     * @param array<string, mixed> $proposal
     */
    private function postToRateb(int $companyId, array $proposal): ?int
    {
        $pdo = $this->pdo ?? AccountingConnectionFactory::pdo();
        if ($pdo === null || !$this->tableExists($pdo, 'rateb_journal_entries')) {
            return null;
        }

        $lines = $proposal['lines'] ?? [];
        if (!is_array($lines) || $lines === []) {
            return null;
        }

        $entryDate = (string) ($proposal['period_to'] ?? date('Y-m-d'));
        $description = 'Integrity correction: ' . (string) ($proposal['type'] ?? 'adjustment');
        $entryNo = 'RECON-' . date('YmdHis') . '-' . substr((string) ($proposal['idempotency_key'] ?? 'x'), 0, 8);

        $integrity = dirname(__DIR__) . '/Support/post_accounting_integrity.php';
        if (is_file($integrity)) {
            require_once $integrity;
            if (function_exists('accounting_enforce_ledger_mutable')) {
                accounting_enforce_ledger_mutable($companyId, $entryDate, null, 'create');
            }
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO rateb_journal_entries
                 (company_id, entry_no, entry_date, description, source_type, status, posted_at, created_at)
                 VALUES (:cid, :no, :dt, :desc, :src, :st, NOW(), NOW())'
            );
            $stmt->execute([
                'cid' => $companyId,
                'no' => substr($entryNo, 0, 50),
                'dt' => $entryDate,
                'desc' => substr($description, 0, 500),
                'src' => 'manual',
                'st' => 'posted',
            ]);
            $entryId = (int) $pdo->lastInsertId();

            $lineStmt = $pdo->prepare(
                'INSERT INTO rateb_journal_lines (journal_entry_id, account_id, debit, credit, memo)
                 VALUES (:je, :acct, :dr, :cr, :memo)'
            );

            foreach ($lines as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $accountId = $this->resolveAccountId($pdo, $companyId, (string) ($line['account_code'] ?? ''));
                if ($accountId === null) {
                    $pdo->rollBack();

                    return null;
                }
                $lineStmt->execute([
                    'je' => $entryId,
                    'acct' => $accountId,
                    'dr' => (float) ($line['debit'] ?? 0),
                    'cr' => (float) ($line['credit'] ?? 0),
                    'memo' => substr((string) ($line['memo'] ?? ''), 0, 255),
                ]);
            }

            $pdo->commit();

            return $entryId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('AccountingCorrectionExecutor::postToRateb: ' . $e->getMessage());

            return null;
        }
    }

    private function resolveAccountId(\PDO $pdo, int $companyId, string $accountCode): ?int
    {
        if ($accountCode === '' || !$this->tableExists($pdo, 'rateb_chart_of_accounts')) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT id FROM rateb_chart_of_accounts WHERE company_id = :cid AND code = :code LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'code' => $accountCode]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function tableExists(\PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
