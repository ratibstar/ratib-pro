<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\PayrollLine;
use Rateb\App\Models\PayrollPeriod;

/**
 * Phase D — read-only payroll reconciliation / state integrity diagnostics.
 *
 * Does NOT mutate payroll, accounting, or transfers.
 * Ops post = status lock only (no GL, no bank transfer).
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
        $notes = [
            'Ops payroll post is a status lock only — not an AccountingService GL post.',
            'No ops payroll bank-transfer workflow is wired.',
            'Enterprise accounting_post_ref (if any) is metadata only.',
        ];
        if ($status === 'posted') {
            $notes[] = 'Period is posted (locked). GL missing is EXPECTED until Phase E adapter.';
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
            'gl_interaction' => 'none_expected',
            'bank_transfer_interaction' => 'none_expected',
            'notes' => $notes,
        ];
    }

    /**
     * Source-level contract: postPayroll must not call AccountingService.
     * Used by Phase D tests (also callable from diagnostics).
     */
    public static function postPayrollCallsAccounting(): bool
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/services/HrService.php');
        if (!preg_match('/function postPayroll\s*\([\s\S]*?\n    public function /', $src, $m)) {
            // Fallback: scan whole file for AccountingService near postPayroll name usage
            return str_contains($src, 'AccountingService') && str_contains($src, 'function postPayroll');
        }
        return str_contains($m[0], 'AccountingService');
    }
}
