<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Models\PayrollAudit;
use Rateb\App\Models\PayrollLine;
use Rateb\App\Models\PayrollPeriod;
use Rateb\App\Models\JournalEntry;

/**
 * Phase E — Payroll → Accounting adapter (mapper + orchestrator only).
 *
 * - Does NOT recalculate payroll.
 * - Uses AccountingService::createManualDraft (DRAFT journal — not ledger-posted).
 * - Feature flag OFF → no-op.
 * - Idempotent via description marker [HR_PAYROLL_PERIOD:{id}].
 */
final class HrPayrollAccountingAdapter
{
    public const MARKER_PREFIX = '[HR_PAYROLL_PERIOD:';

    /**
     * Ensure a draft journal exists for a posted payroll period.
     *
     * @return array{
     *   status: 'skipped'|'already_exists'|'draft_created'|'failed',
     *   journal_entry_id: int|null,
     *   reason: string|null,
     *   flag_enabled: bool
     * }
     */
    public function ensureDraftJournal(int $periodId, ?int $expectedCompanyId = null): array
    {
        if (!HrPayrollAccountingConfig::isEnabled()) {
            return [
                'status' => 'skipped',
                'journal_entry_id' => null,
                'reason' => 'flag_off',
                'flag_enabled' => false,
            ];
        }

        $period = (new PayrollPeriod())->findByIdUnscoped($periodId);
        if (!is_array($period)) {
            return $this->fail($periodId, 0, 'period_not_found');
        }
        $companyId = (int) ($period['company_id'] ?? 0);
        if ($companyId < 1) {
            return $this->fail($periodId, 0, 'company_required');
        }
        if ($expectedCompanyId !== null && $expectedCompanyId > 0 && $expectedCompanyId !== $companyId) {
            return $this->fail($periodId, $companyId, 'tenant_mismatch');
        }

        $payrollStatus = (string) ($period['status'] ?? '');
        if ($payrollStatus !== 'posted') {
            // Never create GL while payroll is still draft/approved-only.
            return $this->fail($periodId, $companyId, 'payroll_not_posted', $period);
        }

        $existingId = $this->findExistingJournalId($companyId, $periodId);
        if ($existingId !== null) {
            $this->audit($period, 'payroll_accounting_posted', [
                'accounting_status' => 'already_exists',
                'accounting_reference' => $existingId,
                'idempotent' => true,
            ]);
            return [
                'status' => 'already_exists',
                'journal_entry_id' => $existingId,
                'reason' => null,
                'flag_enabled' => true,
            ];
        }

        $sums = (new PayrollLine())->queryOne(
            'SELECT COALESCE(SUM(basic_salary),0) AS sum_basic,
                    COALESCE(SUM(allowances),0) AS sum_allowances,
                    COALESCE(SUM(deductions),0) AS sum_deductions,
                    COALESCE(SUM(net_salary),0) AS sum_net,
                    COUNT(*) AS line_count
             FROM rateb_payroll_lines
             WHERE period_id = :pid AND company_id = :cid',
            ['pid' => $periodId, 'cid' => $companyId]
        );
        $lineCount = (int) ($sums['line_count'] ?? 0);
        $gross = round((float) ($sums['sum_basic'] ?? 0) + (float) ($sums['sum_allowances'] ?? 0), 2);
        $deductions = round((float) ($sums['sum_deductions'] ?? 0), 2);
        $net = round((float) ($sums['sum_net'] ?? 0), 2);

        if ($lineCount < 1 || $gross <= 0) {
            return $this->fail($periodId, $companyId, 'no_payroll_amounts', $period);
        }
        // Coherence: gross should equal net + deductions (within rounding).
        if (abs($gross - ($net + $deductions)) > 0.05) {
            return $this->fail($periodId, $companyId, 'payroll_totals_incoherent', $period, [
                'gross' => $gross,
                'net' => $net,
                'deductions' => $deductions,
            ]);
        }

        $year = (int) ($period['period_year'] ?? 0);
        $month = (int) ($period['period_month'] ?? 0);
        $entryDate = ($year > 0 && $month > 0)
            ? date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $month)))
            : date('Y-m-d');

        $acct = new AccountingService();
        if ($acct->periodBlocksPosting($companyId, $entryDate)) {
            return $this->fail($periodId, $companyId, 'period_closed', $period, [
                'entry_date' => $entryDate,
            ]);
        }

        $this->audit($period, 'payroll_accounting_attempted', [
            'accounting_status' => 'attempting',
            'gross' => $gross,
            'net' => $net,
            'deductions' => $deductions,
            'entry_date' => $entryDate,
        ]);

        try {
            $expenseId = $acct->accountIdByCode($companyId, HrPayrollAccountingConfig::expenseAccountCode());
            $payableId = $acct->accountIdByCode($companyId, HrPayrollAccountingConfig::payableAccountCode());
            $deductionId = $deductions > 0
                ? $acct->accountIdByCode($companyId, HrPayrollAccountingConfig::deductionAccountCode())
                : null;
            if ($expenseId === null || $payableId === null) {
                return $this->fail($periodId, $companyId, 'accounts_missing', $period);
            }
            if ($deductions > 0 && $deductionId === null) {
                return $this->fail($periodId, $companyId, 'deduction_account_missing', $period);
            }

            $marker = self::marker($periodId);
            $desc = $marker . ' Payroll ' . $year . '/' . $month;
            $descAr = $marker . ' مسير رواتب ' . $year . '/' . $month;

            $lines = [
                [
                    'account_id' => $expenseId,
                    'debit' => $gross,
                    'credit' => 0.0,
                    'memo' => 'Payroll gross expense',
                ],
                [
                    'account_id' => $payableId,
                    'debit' => 0.0,
                    'credit' => $net,
                    'memo' => 'Salaries payable (net)',
                ],
            ];
            if ($deductions > 0 && $deductionId !== null) {
                $lines[] = [
                    'account_id' => $deductionId,
                    'debit' => 0.0,
                    'credit' => $deductions,
                    'memo' => 'Payroll deductions / accrued',
                ];
            }

            $uid = (int) (SessionManager::get('rateb_user_id') ?? 0);
            $branchId = isset($period['branch_id']) ? (int) $period['branch_id'] : null;
            $journalId = $acct->createManualDraft(
                $companyId,
                $entryDate,
                $desc,
                $descAr,
                $lines,
                $uid > 0 ? $uid : null,
                $branchId !== null && $branchId > 0 ? $branchId : null
            );

            // Re-check company on created entry (defense in depth).
            $created = (new JournalEntry())->queryOne(
                'SELECT id, company_id, status FROM rateb_journal_entries WHERE id = :id LIMIT 1',
                ['id' => $journalId]
            );
            if (!is_array($created) || (int) ($created['company_id'] ?? 0) !== $companyId) {
                return $this->fail($periodId, $companyId, 'journal_company_mismatch', $period, [
                    'accounting_reference' => $journalId,
                ]);
            }

            $this->audit($period, 'payroll_accounting_posted', [
                'accounting_status' => 'draft_created',
                'accounting_reference' => $journalId,
                'journal_status' => (string) ($created['status'] ?? 'draft'),
                'gross' => $gross,
                'net' => $net,
                'deductions' => $deductions,
                'gl_ledger_posted' => false,
                'bank_transfer' => false,
            ]);

            return [
                'status' => 'draft_created',
                'journal_entry_id' => (int) $journalId,
                'reason' => null,
                'flag_enabled' => true,
            ];
        } catch (\Throwable $e) {
            return $this->fail($periodId, $companyId, 'accounting_exception', $period, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function marker(int $periodId): string
    {
        return self::MARKER_PREFIX . $periodId . ']';
    }

    public function findExistingJournalId(int $companyId, int $periodId): ?int
    {
        if ($companyId < 1 || $periodId < 1) {
            return null;
        }
        $marker = self::marker($periodId);
        $row = (new JournalEntry())->queryOne(
            "SELECT id FROM rateb_journal_entries
             WHERE company_id = :cid
               AND status != 'void'
               AND description LIKE :m
             ORDER BY id ASC LIMIT 1",
            ['cid' => $companyId, 'm' => '%' . $marker . '%']
        );
        return is_array($row) ? (int) ($row['id'] ?? 0) ?: null : null;
    }

    /**
     * @param array<string, mixed>|null $period
     * @param array<string, mixed> $extra
     * @return array{status:'failed', journal_entry_id:null, reason:string, flag_enabled:bool}
     */
    private function fail(int $periodId, int $companyId, string $reason, ?array $period = null, array $extra = []): array
    {
        if (is_array($period)) {
            $this->audit($period, 'payroll_accounting_failed', array_merge([
                'accounting_status' => 'failed',
                'reason' => $reason,
                'gl_ledger_posted' => false,
                'bank_transfer' => false,
            ], $extra));
        } else {
            try {
                (new AuditService())->log('payroll_accounting_failed', 'hr_payroll_period', $periodId, array_merge([
                    'company_id' => $companyId,
                    'reason' => $reason,
                ], $extra));
            } catch (\Throwable $e) {
                // ignore
            }
        }
        return [
            'status' => 'failed',
            'journal_entry_id' => null,
            'reason' => $reason,
            'flag_enabled' => HrPayrollAccountingConfig::isEnabled(),
        ];
    }

    /**
     * @param array<string, mixed> $period
     * @param array<string, mixed> $payload
     */
    private function audit(array $period, string $action, array $payload): void
    {
        $periodId = (int) ($period['id'] ?? 0);
        $companyId = (int) ($period['company_id'] ?? 0);
        $full = array_merge([
            'period_year' => $period['period_year'] ?? null,
            'period_month' => $period['period_month'] ?? null,
            'source' => 'hr_payroll_accounting_adapter',
            'flag_enabled' => true,
        ], $payload);
        try {
            (new AuditService())->log($action, 'hr_payroll_period', $periodId, $full);
        } catch (\Throwable $e) {
            // ignore
        }
        if ($companyId < 1 || $periodId < 1) {
            return;
        }
        try {
            $uid = (int) (SessionManager::get('rateb_user_id') ?? 0);
            $data = random_bytes(16);
            $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
            $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
            $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
            (new PayrollAudit())->create([
                'public_uuid' => $uuid,
                'company_id' => $companyId,
                'branch_id' => isset($period['branch_id']) ? (int) $period['branch_id'] : null,
                'entity_type' => 'hr_payroll_period',
                'entity_id' => $periodId,
                'action' => $action,
                'payload_json' => json_encode($full, JSON_UNESCAPED_UNICODE),
                'status' => 'active',
                'version' => 1,
                'created_by' => $uid > 0 ? $uid : null,
                'updated_by' => $uid > 0 ? $uid : null,
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
