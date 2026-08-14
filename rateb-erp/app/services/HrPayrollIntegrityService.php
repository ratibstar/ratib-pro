<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\PayrollLine;
use Rateb\App\Models\PayrollPeriod;

/**
 * Phase D/E — read-only payroll reconciliation / state integrity diagnostics.
 *
 * Does NOT mutate payroll, accounting, or transfers.
 * Flag OFF: ops post = status lock only (no GL).
 * Flag ON: may have a DRAFT journal referenced by [HR_PAYROLL_PERIOD:{id}] marker.
 */
final class HrPayrollIntegrityService
{
    /**
     * @return array{
     *   company_id: int,
     *   period_id: int,
     *   period_status: string|null,
     *   found: bool,
     *   line_count: int,
     *   sum_basic: float,
     *   sum_allowances: float,
     *   sum_deductions: float,
     *   sum_net: float,
     *   accounting_flag_enabled: bool,
     *   accounting_journal_id: int|null,
     *   accounting_state: string,
     *   gl_interaction: string,
     *   bank_transfer_interaction: string,
     *   notes: list<string>
     * }
     */
    public function diagnosePeriod(int $companyId, int $periodId): array
    {
        $empty = [
            'company_id' => $companyId,
            'period_id' => $periodId,
            'period_status' => null,
            'found' => false,
            'line_count' => 0,
            'sum_basic' => 0.0,
            'sum_allowances' => 0.0,
            'sum_deductions' => 0.0,
            'sum_net' => 0.0,
            'accounting_flag_enabled' => HrPayrollAccountingConfig::isEnabled(),
            'accounting_journal_id' => null,
            'accounting_state' => 'n/a',
            'gl_interaction' => 'none',
            'bank_transfer_interaction' => 'none',
            'notes' => ['period_not_found_or_wrong_company'],
        ];
        if ($companyId < 1 || $periodId < 1) {
            return $empty;
        }

        $period = (new PayrollPeriod())->queryOne(
            'SELECT id, company_id, status, period_year, period_month
             FROM rateb_payroll_periods
             WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $periodId, 'cid' => $companyId]
        );
        if (!is_array($period)) {
            return $empty;
        }

        $sums = (new PayrollLine())->queryOne(
            'SELECT COUNT(*) AS c,
                    COALESCE(SUM(basic_salary), 0) AS sum_basic,
                    COALESCE(SUM(allowances), 0) AS sum_allowances,
                    COALESCE(SUM(deductions), 0) AS sum_deductions,
                    COALESCE(SUM(net_salary), 0) AS sum_net
             FROM rateb_payroll_lines
             WHERE period_id = :pid AND company_id = :cid',
            ['pid' => $periodId, 'cid' => $companyId]
        );

        $status = (string) ($period['status'] ?? '');
        $flagOn = HrPayrollAccountingConfig::isEnabled();
        $journalId = (new HrPayrollAccountingAdapter())->findExistingJournalId($companyId, $periodId);

        $accountingState = 'none_expected';
        $glInteraction = 'none_expected';
        $notes = [
            'Ops payroll post is a status lock. Ledger GL auto-post is not default.',
            'No ops payroll bank-transfer workflow is wired.',
            'Feature flag HR_PAYROLL_ACCOUNTING_ENABLED default OFF.',
        ];

        if ($flagOn) {
            if ($status === 'posted' && $journalId === null) {
                $accountingState = 'missing_while_flag_on';
                $glInteraction = 'draft_missing';
                $notes[] = 'Payroll posted + flag ON but no draft journal marker found.';
            } elseif ($journalId !== null) {
                $accountingState = 'draft_or_linked';
                $glInteraction = 'draft_journal_present';
                $notes[] = 'Accounting draft/reference found via HR_PAYROLL_PERIOD marker.';
                $notes[] = 'Draft journal ≠ ledger posted; finance must post journal separately.';
            } else {
                $accountingState = 'not_applicable_yet';
                $glInteraction = 'none_yet';
            }
        } else {
            if ($journalId !== null) {
                $accountingState = 'unexpected_journal_while_flag_off';
                $notes[] = 'Journal marker exists while flag OFF — investigate manual/legacy entry.';
            } elseif ($status === 'posted') {
                $notes[] = 'Period is posted (locked). GL missing is EXPECTED while flag OFF.';
            }
            $notes[] = 'Bank transfer missing is EXPECTED.';
        }

        return [
            'company_id' => $companyId,
            'period_id' => $periodId,
            'period_status' => $status !== '' ? $status : null,
            'found' => true,
            'line_count' => (int) ($sums['c'] ?? 0),
            'sum_basic' => round((float) ($sums['sum_basic'] ?? 0), 2),
            'sum_allowances' => round((float) ($sums['sum_allowances'] ?? 0), 2),
            'sum_deductions' => round((float) ($sums['sum_deductions'] ?? 0), 2),
            'sum_net' => round((float) ($sums['sum_net'] ?? 0), 2),
            'accounting_flag_enabled' => $flagOn,
            'accounting_journal_id' => $journalId,
            'accounting_state' => $accountingState,
            'gl_interaction' => $glInteraction,
            'bank_transfer_interaction' => 'none_expected',
            'notes' => $notes,
        ];
    }
}
