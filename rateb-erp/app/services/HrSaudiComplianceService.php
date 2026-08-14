<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\Employee;

/**
 * Phase R — Saudi HR Compliance readiness (GOSI / WPS).
 *
 * Local calculation + validation + export readiness ONLY.
 * NEVER transmits externally. external_sent is always forced to 0.
 * Uses rateb_employees as SoT; payroll lines as contribution/payment source; Phase K contracts for dates.
 * Does NOT rewrite payroll calculation or accounting.
 */
final class HrSaudiComplianceService
{
    /** Model rates for readiness (not a live GOSI filing connector). */
    public const GOSI_SAUDI_EMPLOYEE_PCT = 9.75;
    public const GOSI_SAUDI_EMPLOYER_PCT = 11.75;
    public const GOSI_NON_SAUDI_EMPLOYER_PCT = 2.00;
    public const GOSI_MAX_MONTHLY_BASE = 45000.00;

    public const CLASS_SAUDI = 'saudi';
    public const CLASS_NON_SAUDI = 'non_saudi';

    public function schemaReady(): bool
    {
        try {
            return Database::tableExists('rateb_hr_saudi_employment_fields')
                && Database::tableExists('rateb_hr_gosi_period_lines')
                && Database::tableExists('rateb_hr_wps_export_batches');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function foundationReady(): bool
    {
        return (new HrSaudiComplianceFoundationService())->schemaReady();
    }

    /**
     * Command Center + hub summary.
     *
     * @return array<string,mixed>
     */
    public function readinessSummary(int $companyId): array
    {
        $base = (new HrSaudiComplianceFoundationService())->foundationAudit($companyId);
        if ($companyId < 1) {
            return array_merge($base, [
                'readiness_pct' => 0,
                'active_employees' => 0,
                'missing_data' => 0,
                'gosi_exceptions' => 0,
                'wps_exceptions' => 0,
                'external_send_enabled' => false,
                'phase' => 'R',
            ]);
        }

        $profile = $this->employeeComplianceProfiles($companyId, 500);
        $active = count($profile);
        $missing = 0;
        $gosiEx = 0;
        $wpsEx = 0;
        $ready = 0;
        foreach ($profile as $row) {
            $issues = is_array($row['issues'] ?? null) ? $row['issues'] : [];
            if ($issues !== []) {
                $missing++;
            }
            if (!empty($row['gosi']['exception'])) {
                $gosiEx++;
            }
            if (!empty($row['wps']['exception'])) {
                $wpsEx++;
            }
            if (empty($row['gosi']['exception']) && empty($row['wps']['exception']) && $issues === []) {
                $ready++;
            }
        }
        $pct = $active > 0 ? (int) round(($ready / $active) * 100) : 0;

        return array_merge($base, [
            'schema_ready_r' => $this->schemaReady(),
            'readiness_pct' => $pct,
            'active_employees' => $active,
            'ready_employees' => $ready,
            'missing_data' => $missing,
            'gosi_exceptions' => $gosiEx,
            'wps_exceptions' => $wpsEx,
            'external_send_enabled' => false,
            'external_sent_default' => 0,
            'gosi_rates' => [
                'saudi_employee_pct' => self::GOSI_SAUDI_EMPLOYEE_PCT,
                'saudi_employer_pct' => self::GOSI_SAUDI_EMPLOYER_PCT,
                'non_saudi_employer_pct' => self::GOSI_NON_SAUDI_EMPLOYER_PCT,
                'max_monthly_base' => self::GOSI_MAX_MONTHLY_BASE,
            ],
            'phase' => 'R',
            'policy' => 'Local GOSI/WPS readiness only — no external transmission.',
        ]);
    }

    /**
     * Validate SA IBAN (mod-97). Returns null if valid, else error code.
     */
    public function validateIban(?string $iban): ?string
    {
        $raw = strtoupper(preg_replace('/\s+/', '', (string) $iban) ?? '');
        if ($raw === '') {
            return 'iban_missing';
        }
        if (!preg_match('/^SA[0-9]{22}$/', $raw)) {
            return 'iban_format';
        }
        $rearranged = substr($raw, 4) . substr($raw, 0, 4);
        $numeric = '';
        $len = strlen($rearranged);
        for ($i = 0; $i < $len; $i++) {
            $ch = $rearranged[$i];
            if ($ch >= 'A' && $ch <= 'Z') {
                $numeric .= (string) (ord($ch) - 55);
            } else {
                $numeric .= $ch;
            }
        }
        $checksum = 0;
        $nlen = strlen($numeric);
        for ($i = 0; $i < $nlen; $i++) {
            $checksum = ($checksum * 10 + (int) $numeric[$i]) % 97;
        }
        if ($checksum !== 1) {
            return 'iban_checksum';
        }

        return null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function employeeComplianceProfiles(int $companyId, int $limit = 300): array
    {
        if ($companyId < 1 || !$this->foundationReady()) {
            return [];
        }
        $limit = max(1, min(1000, $limit));
        $hasExtra = Database::liveTableHasColumn('rateb_hr_saudi_employment_fields', 'employment_type');
        $extraCols = $hasExtra
            ? ', s.employment_type, s.saudi_classification, s.gosi_eligible, s.housing_allowance, s.transport_allowance, s.other_gosi_allowances, s.bank_name'
            : '';
        $sql = "SELECT e.id, e.employee_code, e.name, e.national_id, e.hire_date, e.salary_base, e.status,
                       e.job_title, e.department_id,
                       s.gosi_number, s.gosi_subscription_status, s.wps_iban, s.wps_bank_code,
                       s.nationality_code, s.iqama_number, s.iqama_expiry, s.mol_contract_number
                       {$extraCols},
                       c.start_date AS contract_start, c.end_date AS contract_end, c.salary AS contract_salary, c.status AS contract_status
                FROM rateb_employees e
                LEFT JOIN rateb_hr_saudi_employment_fields s
                  ON s.company_id = e.company_id AND s.employee_id = e.id
                LEFT JOIN rateb_hr_employment_contracts c
                  ON c.company_id = e.company_id AND c.employee_id = e.id AND c.status = 'active'
                WHERE e.company_id = :cid AND e.status = 'active'
                ORDER BY e.name ASC
                LIMIT {$limit}";
        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute(['cid' => $companyId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->buildProfile($row);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function buildProfile(array $row): array
    {
        $classification = $this->resolveClassification($row);
        $ibanErr = $this->validateIban(isset($row['wps_iban']) ? (string) $row['wps_iban'] : null);
        $issues = [];
        $nationalId = trim((string) ($row['national_id'] ?? ''));
        if ($nationalId === '') {
            $issues[] = 'national_id_missing';
        }
        if ($classification === self::CLASS_NON_SAUDI) {
            if (trim((string) ($row['iqama_number'] ?? '')) === '') {
                $issues[] = 'iqama_missing';
            }
            $exp = (string) ($row['iqama_expiry'] ?? '');
            if ($exp !== '' && $exp < date('Y-m-d')) {
                $issues[] = 'iqama_expired';
            }
        }
        if (trim((string) ($row['nationality_code'] ?? '')) === '') {
            $issues[] = 'nationality_missing';
        }
        $salary = (float) ($row['salary_base'] ?? 0);
        if ($salary <= 0) {
            $issues[] = 'salary_invalid';
        }
        if (empty($row['contract_start'])) {
            $issues[] = 'contract_missing';
        } elseif ((float) ($row['contract_salary'] ?? 0) > 0 && abs((float) $row['contract_salary'] - $salary) > 0.009) {
            $issues[] = 'contract_salary_mismatch';
        }

        $gosiEligible = $this->isGosiEligible($row, $classification);
        $gosiIssues = [];
        if ($gosiEligible && trim((string) ($row['gosi_number'] ?? '')) === '') {
            $gosiIssues[] = 'gosi_number_missing';
        }
        if (!$gosiEligible) {
            $gosiIssues[] = 'not_eligible';
        }
        if ($salary <= 0) {
            $gosiIssues[] = 'salary_invalid';
        }

        $wpsIssues = [];
        if ($ibanErr !== null) {
            $wpsIssues[] = $ibanErr;
        }
        if (trim((string) ($row['wps_bank_code'] ?? '')) === '') {
            $wpsIssues[] = 'bank_code_missing';
        }
        if ($nationalId === '') {
            $wpsIssues[] = 'national_id_missing';
        }
        if ($salary <= 0) {
            $wpsIssues[] = 'salary_invalid';
        }

        $base = $this->contributionBaseFromRow($row);
        $rates = $this->gosiRatesFor($classification);
        $employeeAmt = round($base * ($rates['employee_pct'] / 100), 2);
        $employerAmt = round($base * ($rates['employer_pct'] / 100), 2);

        return [
            'employee_id' => (int) ($row['id'] ?? 0),
            'employee_code' => (string) ($row['employee_code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'national_id' => $nationalId,
            'nationality_code' => (string) ($row['nationality_code'] ?? ''),
            'iqama_number' => (string) ($row['iqama_number'] ?? ''),
            'iqama_expiry' => (string) ($row['iqama_expiry'] ?? ''),
            'employment_type' => (string) ($row['employment_type'] ?? ''),
            'saudi_classification' => $classification,
            'hire_date' => (string) ($row['hire_date'] ?? ''),
            'contract_start' => (string) ($row['contract_start'] ?? ''),
            'contract_end' => (string) ($row['contract_end'] ?? ''),
            'salary_base' => $salary,
            'housing_allowance' => (float) ($row['housing_allowance'] ?? 0),
            'transport_allowance' => (float) ($row['transport_allowance'] ?? 0),
            'other_gosi_allowances' => (float) ($row['other_gosi_allowances'] ?? 0),
            'wps_iban' => (string) ($row['wps_iban'] ?? ''),
            'wps_bank_code' => (string) ($row['wps_bank_code'] ?? ''),
            'bank_name' => (string) ($row['bank_name'] ?? ''),
            'gosi_number' => (string) ($row['gosi_number'] ?? ''),
            'issues' => $issues,
            'gosi' => [
                'eligible' => $gosiEligible,
                'contribution_base' => $base,
                'employee_rate' => $rates['employee_pct'],
                'employer_rate' => $rates['employer_pct'],
                'employee_amount' => $employeeAmt,
                'employer_amount' => $employerAmt,
                'exception' => $gosiIssues !== [],
                'issues' => $gosiIssues,
            ],
            'wps' => [
                'iban_ok' => $ibanErr === null,
                'exception' => $wpsIssues !== [],
                'issues' => $wpsIssues,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function resolveClassification(array $row): string
    {
        $explicit = strtolower(trim((string) ($row['saudi_classification'] ?? '')));
        if (in_array($explicit, [self::CLASS_SAUDI, self::CLASS_NON_SAUDI], true)) {
            return $explicit;
        }
        $nat = strtoupper(trim((string) ($row['nationality_code'] ?? '')));
        if ($nat === 'SA' || $nat === 'SAU') {
            return self::CLASS_SAUDI;
        }
        if ($nat !== '') {
            return self::CLASS_NON_SAUDI;
        }
        // Heuristic: 10-digit national id starting with 1 ≈ Saudi; 2 ≈ iqama.
        $nid = trim((string) ($row['national_id'] ?? ''));
        if (preg_match('/^1\d{9}$/', $nid)) {
            return self::CLASS_SAUDI;
        }
        if (preg_match('/^2\d{9}$/', $nid)) {
            return self::CLASS_NON_SAUDI;
        }

        return self::CLASS_NON_SAUDI;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function isGosiEligible(array $row, string $classification): bool
    {
        if (array_key_exists('gosi_eligible', $row) && $row['gosi_eligible'] !== null && $row['gosi_eligible'] !== '') {
            return (int) $row['gosi_eligible'] === 1;
        }
        // Default model: all active employees are potentially GOSI-eligible for readiness.
        return true;
    }

    /**
     * @return array{employee_pct:float,employer_pct:float}
     */
    private function gosiRatesFor(string $classification): array
    {
        if ($classification === self::CLASS_SAUDI) {
            return [
                'employee_pct' => self::GOSI_SAUDI_EMPLOYEE_PCT,
                'employer_pct' => self::GOSI_SAUDI_EMPLOYER_PCT,
            ];
        }

        return [
            'employee_pct' => 0.0,
            'employer_pct' => self::GOSI_NON_SAUDI_EMPLOYER_PCT,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function contributionBaseFromRow(array $row): float
    {
        $basic = (float) ($row['salary_base'] ?? 0);
        $housing = (float) ($row['housing_allowance'] ?? 0);
        $transport = (float) ($row['transport_allowance'] ?? 0);
        $other = (float) ($row['other_gosi_allowances'] ?? 0);
        $base = max(0.0, $basic + $housing + $transport + $other);

        return min($base, self::GOSI_MAX_MONTHLY_BASE);
    }

    /**
     * Build/refresh local GOSI period lines from payroll period (no external send).
     *
     * @return array{lines:int,exceptions:int,external_sent:int}
     */
    public function buildGosiPeriod(int $companyId, int $periodYear, int $periodMonth, int $actorUserId = 0): array
    {
        if (!$this->schemaReady() || $companyId < 1) {
            throw new \RuntimeException(__('db_schema_outdated'));
        }
        $periodYear = max(2000, min(2100, $periodYear));
        $periodMonth = max(1, min(12, $periodMonth));
        $period = $this->findPayrollPeriod($companyId, $periodYear, $periodMonth);
        $periodId = $period ? (int) ($period['id'] ?? 0) : 0;

        $profiles = $this->employeeComplianceProfiles($companyId, 1000);
        $payrollByEmp = $this->payrollAmountsByEmployee($companyId, $periodId);
        $db = Database::connection();
        $lines = 0;
        $exceptions = 0;

        foreach ($profiles as $p) {
            $eid = (int) ($p['employee_id'] ?? 0);
            if ($eid < 1) {
                continue;
            }
            $pay = $payrollByEmp[$eid] ?? null;
            $base = $p['gosi']['contribution_base'];
            if ($pay !== null) {
                $base = min(
                    self::GOSI_MAX_MONTHLY_BASE,
                    max(0.0, (float) $pay['basic_salary'] + (float) $pay['allowances'])
                );
            }
            $rates = $this->gosiRatesFor((string) $p['saudi_classification']);
            $eligible = !empty($p['gosi']['eligible']);
            $gosiIssues = is_array($p['gosi']['issues'] ?? null) ? $p['gosi']['issues'] : [];
            $status = $gosiIssues === [] ? 'ok' : 'exception';
            if ($status === 'exception') {
                $exceptions++;
            }
            $empAmt = $eligible ? round($base * ($rates['employee_pct'] / 100), 2) : 0.0;
            $erAmt = $eligible ? round($base * ($rates['employer_pct'] / 100), 2) : 0.0;

            $stmt = $db->prepare(
                'INSERT INTO rateb_hr_gosi_period_lines
                 (company_id, period_year, period_month, payroll_period_id, employee_id, saudi_classification,
                  contribution_base, employee_rate, employer_rate, employee_amount, employer_amount,
                  eligible, validation_status, validation_notes, external_sent, created_at, updated_at)
                 VALUES
                 (:cid,:yy,:mm,:pid,:eid,:cls,:base,:er,:err,:ea,:era,:el,:vs,:vn,0,:ca,:ua)
                 ON DUPLICATE KEY UPDATE
                  payroll_period_id = VALUES(payroll_period_id),
                  saudi_classification = VALUES(saudi_classification),
                  contribution_base = VALUES(contribution_base),
                  employee_rate = VALUES(employee_rate),
                  employer_rate = VALUES(employer_rate),
                  employee_amount = VALUES(employee_amount),
                  employer_amount = VALUES(employer_amount),
                  eligible = VALUES(eligible),
                  validation_status = VALUES(validation_status),
                  validation_notes = VALUES(validation_notes),
                  external_sent = 0,
                  updated_at = VALUES(updated_at)'
            );
            $now = date('Y-m-d H:i:s');
            $stmt->execute([
                'cid' => $companyId,
                'yy' => $periodYear,
                'mm' => $periodMonth,
                'pid' => $periodId > 0 ? $periodId : null,
                'eid' => $eid,
                'cls' => (string) $p['saudi_classification'],
                'base' => $base,
                'er' => $rates['employee_pct'],
                'err' => $rates['employer_pct'],
                'ea' => $empAmt,
                'era' => $erAmt,
                'el' => $eligible ? 1 : 0,
                'vs' => $status,
                'vn' => $gosiIssues !== [] ? implode(',', $gosiIssues) : null,
                'ca' => $now,
                'ua' => $now,
            ]);
            $lines++;
        }

        (new HrSaudiComplianceFoundationService())->writeAudit(
            $companyId,
            'gosi',
            'build_period_lines',
            'local_ready',
            sprintf('%04d-%02d lines=%d exceptions=%d external_sent=0', $periodYear, $periodMonth, $lines, $exceptions),
            $actorUserId
        );
        (new AuditService())->log('hr_saudi_gosi_build', 'hr_gosi_period', $periodId, [
            'company_id' => $companyId,
            'period_year' => $periodYear,
            'period_month' => $periodMonth,
            'lines' => $lines,
            'exceptions' => $exceptions,
            'external_sent' => 0,
        ]);

        return ['lines' => $lines, 'exceptions' => $exceptions, 'external_sent' => 0];
    }

    /**
     * Build local WPS-ready batch from payroll period (no bank transmission).
     *
     * @return array{batch_id:int,line_count:int,ready_count:int,exception_count:int,external_sent:int}
     */
    public function buildWpsBatch(int $companyId, int $periodYear, int $periodMonth, int $actorUserId = 0): array
    {
        if (!$this->schemaReady() || $companyId < 1) {
            throw new \RuntimeException(__('db_schema_outdated'));
        }
        $periodYear = max(2000, min(2100, $periodYear));
        $periodMonth = max(1, min(12, $periodMonth));
        $period = $this->findPayrollPeriod($companyId, $periodYear, $periodMonth);
        if ($period === null) {
            throw new \RuntimeException(__('hr_r_payroll_period_required'));
        }
        $periodId = (int) ($period['id'] ?? 0);
        $payrollByEmp = $this->payrollAmountsByEmployee($companyId, $periodId);
        if ($payrollByEmp === []) {
            throw new \RuntimeException(__('hr_r_payroll_lines_required'));
        }
        $profiles = [];
        foreach ($this->employeeComplianceProfiles($companyId, 1000) as $p) {
            $profiles[(int) $p['employee_id']] = $p;
        }

        $db = Database::connection();
        $db->prepare(
            'INSERT INTO rateb_hr_wps_export_batches
             (company_id, period_year, period_month, payroll_period_id, status, line_count, ready_count, exception_count, external_sent, created_by, created_at)
             VALUES (:cid,:yy,:mm,:pid,\'ready_local\',0,0,0,0,:uid,:ca)'
        )->execute([
            'cid' => $companyId,
            'yy' => $periodYear,
            'mm' => $periodMonth,
            'pid' => $periodId,
            'uid' => $actorUserId > 0 ? $actorUserId : null,
            'ca' => date('Y-m-d H:i:s'),
        ]);
        $batchId = (int) $db->lastInsertId();
        $ready = 0;
        $exceptions = 0;
        $lineCount = 0;

        foreach ($payrollByEmp as $eid => $pay) {
            $p = $profiles[$eid] ?? null;
            $wpsIssues = is_array($p['wps']['issues'] ?? null) ? $p['wps']['issues'] : ['profile_missing'];
            $isReady = $p !== null && empty($p['wps']['exception']);
            if ($isReady) {
                $ready++;
            } else {
                $exceptions++;
            }
            $db->prepare(
                'INSERT INTO rateb_hr_wps_export_lines
                 (batch_id, company_id, employee_id, employee_code, employee_name, national_id, iban, bank_code,
                  basic_salary, allowances, deductions, net_salary, ready, validation_status, validation_notes, external_sent, created_at)
                 VALUES
                 (:bid,:cid,:eid,:code,:name,:nid,:iban,:bank,:basic,:allw,:ded,:net,:ready,:vs,:vn,0,:ca)'
            )->execute([
                'bid' => $batchId,
                'cid' => $companyId,
                'eid' => $eid,
                'code' => $p['employee_code'] ?? '',
                'name' => $p['name'] ?? '',
                'nid' => $p['national_id'] ?? '',
                'iban' => $p['wps_iban'] ?? '',
                'bank' => $p['wps_bank_code'] ?? '',
                'basic' => (float) $pay['basic_salary'],
                'allw' => (float) $pay['allowances'],
                'ded' => (float) $pay['deductions'],
                'net' => (float) $pay['net_salary'],
                'ready' => $isReady ? 1 : 0,
                'vs' => $isReady ? 'ok' : 'exception',
                'vn' => $isReady ? null : implode(',', $wpsIssues),
                'ca' => date('Y-m-d H:i:s'),
            ]);
            $lineCount++;
        }

        $db->prepare(
            'UPDATE rateb_hr_wps_export_batches
             SET line_count = :lc, ready_count = :rc, exception_count = :ec, external_sent = 0
             WHERE id = :id AND company_id = :cid'
        )->execute([
            'lc' => $lineCount,
            'rc' => $ready,
            'ec' => $exceptions,
            'id' => $batchId,
            'cid' => $companyId,
        ]);

        (new HrSaudiComplianceFoundationService())->writeAudit(
            $companyId,
            'wps',
            'build_export_batch',
            'local_ready',
            sprintf('batch=%d lines=%d ready=%d exceptions=%d external_sent=0', $batchId, $lineCount, $ready, $exceptions),
            $actorUserId
        );
        (new AuditService())->log('hr_saudi_wps_build', 'hr_wps_batch', $batchId, [
            'company_id' => $companyId,
            'period_year' => $periodYear,
            'period_month' => $periodMonth,
            'line_count' => $lineCount,
            'ready_count' => $ready,
            'exception_count' => $exceptions,
            'external_sent' => 0,
        ]);

        return [
            'batch_id' => $batchId,
            'line_count' => $lineCount,
            'ready_count' => $ready,
            'exception_count' => $exceptions,
            'external_sent' => 0,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function gosiReportRows(int $companyId, int $periodYear, int $periodMonth): array
    {
        if (!$this->schemaReady() || $companyId < 1) {
            return [];
        }
        $stmt = Database::connection()->prepare(
            'SELECT g.*, e.name AS employee_name, e.employee_code, e.national_id
             FROM rateb_hr_gosi_period_lines g
             JOIN rateb_employees e ON e.id = g.employee_id AND e.company_id = g.company_id
             WHERE g.company_id = :cid AND g.period_year = :yy AND g.period_month = :mm
             ORDER BY e.name ASC
             LIMIT 2000'
        );
        $stmt->execute(['cid' => $companyId, 'yy' => $periodYear, 'mm' => $periodMonth]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function wpsReportRows(int $companyId, int $batchId): array
    {
        if (!$this->schemaReady() || $companyId < 1 || $batchId < 1) {
            return [];
        }
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_hr_wps_export_lines
             WHERE company_id = :cid AND batch_id = :bid
             ORDER BY id ASC
             LIMIT 2000'
        );
        $stmt->execute(['cid' => $companyId, 'bid' => $batchId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function missingDataReportRows(int $companyId): array
    {
        $rows = [];
        foreach ($this->employeeComplianceProfiles($companyId, 1000) as $p) {
            $all = array_merge(
                is_array($p['issues'] ?? null) ? $p['issues'] : [],
                is_array($p['gosi']['issues'] ?? null) ? $p['gosi']['issues'] : [],
                is_array($p['wps']['issues'] ?? null) ? $p['wps']['issues'] : []
            );
            if ($all === []) {
                continue;
            }
            $rows[] = [
                'employee_code' => $p['employee_code'],
                'name' => $p['name'],
                'national_id' => $p['national_id'],
                'saudi_classification' => $p['saudi_classification'],
                'issues' => implode(', ', array_values(array_unique($all))),
            ];
        }

        return $rows;
    }

    /**
     * Payroll vs GOSI base reconciliation (read-only).
     *
     * @return list<array<string,mixed>>
     */
    public function payrollReconciliationRows(int $companyId, int $periodYear, int $periodMonth, bool $canViewSalary): array
    {
        if (!$canViewSalary || !$this->schemaReady()) {
            return [];
        }
        $gosi = $this->gosiReportRows($companyId, $periodYear, $periodMonth);
        $period = $this->findPayrollPeriod($companyId, $periodYear, $periodMonth);
        $periodId = $period ? (int) ($period['id'] ?? 0) : 0;
        $pay = $this->payrollAmountsByEmployee($companyId, $periodId);
        $out = [];
        foreach ($gosi as $g) {
            $eid = (int) ($g['employee_id'] ?? 0);
            $p = $pay[$eid] ?? ['basic_salary' => 0, 'allowances' => 0, 'net_salary' => 0];
            $payrollGross = (float) $p['basic_salary'] + (float) $p['allowances'];
            $base = (float) ($g['contribution_base'] ?? 0);
            $out[] = [
                'employee_code' => (string) ($g['employee_code'] ?? ''),
                'employee_name' => (string) ($g['employee_name'] ?? ''),
                'payroll_gross' => round($payrollGross, 2),
                'gosi_base' => $base,
                'delta' => round($payrollGross - $base, 2),
                'net_salary' => (float) ($p['net_salary'] ?? 0),
                'validation_status' => (string) ($g['validation_status'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Upsert Saudi side-fields (extends foundation). Never sets external_sent.
     *
     * @param array<string,mixed> $input
     * @return array{id:int,external_sent:int}
     */
    public function upsertEmployeeSaudiData(int $companyId, int $employeeId, array $input, int $actorUserId = 0): array
    {
        $iban = isset($input['wps_iban']) ? strtoupper(preg_replace('/\s+/', '', (string) $input['wps_iban']) ?? '') : null;
        if ($iban !== null && $iban !== '' && $this->validateIban($iban) !== null) {
            throw new \RuntimeException(__('hr_r_iban_invalid'));
        }
        if ($iban !== null) {
            $input['wps_iban'] = $iban;
        }
        $foundation = new HrSaudiComplianceFoundationService();
        $result = $foundation->upsertEmployeeFields($companyId, $employeeId, $input, $actorUserId);
        if (!$this->schemaReady() || !Database::liveTableHasColumn('rateb_hr_saudi_employment_fields', 'employment_type')) {
            return ['id' => (int) ($result['id'] ?? 0), 'external_sent' => 0];
        }
        $cls = strtolower(trim((string) ($input['saudi_classification'] ?? '')));
        if ($cls !== '' && !in_array($cls, [self::CLASS_SAUDI, self::CLASS_NON_SAUDI], true)) {
            $cls = '';
        }
        $stmt = Database::connection()->prepare(
            'UPDATE rateb_hr_saudi_employment_fields SET
                employment_type = :et,
                saudi_classification = :sc,
                gosi_eligible = :ge,
                housing_allowance = :ha,
                transport_allowance = :ta,
                other_gosi_allowances = :oa,
                bank_name = :bn,
                updated_at = :ua
             WHERE company_id = :cid AND employee_id = :eid'
        );
        $stmt->execute([
            'et' => $this->clip($input['employment_type'] ?? null, 32),
            'sc' => $cls !== '' ? $cls : null,
            'ge' => array_key_exists('gosi_eligible', $input)
                ? ((int) (!empty($input['gosi_eligible']) ? 1 : 0))
                : null,
            'ha' => $this->moneyOrNull($input['housing_allowance'] ?? null),
            'ta' => $this->moneyOrNull($input['transport_allowance'] ?? null),
            'oa' => $this->moneyOrNull($input['other_gosi_allowances'] ?? null),
            'bn' => $this->clip($input['bank_name'] ?? null, 120),
            'ua' => date('Y-m-d H:i:s'),
            'cid' => $companyId,
            'eid' => $employeeId,
        ]);
        $foundation->writeAudit($companyId, 'other', 'upsert_phase_r_fields', 'local_saved', 'employee:' . $employeeId, $actorUserId);

        return ['id' => (int) ($result['id'] ?? 0), 'external_sent' => 0];
    }

    /** @return array<string,mixed>|null */
    private function findPayrollPeriod(int $companyId, int $year, int $month): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, period_year, period_month, status FROM rateb_payroll_periods
             WHERE company_id = :cid AND period_year = :yy AND period_month = :mm
             LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'yy' => $year, 'mm' => $month]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array{basic_salary:float,allowances:float,deductions:float,net_salary:float}>
     */
    private function payrollAmountsByEmployee(int $companyId, int $periodId): array
    {
        if ($periodId < 1) {
            return [];
        }
        $stmt = Database::connection()->prepare(
            'SELECT employee_id, basic_salary, allowances, deductions, net_salary
             FROM rateb_payroll_lines
             WHERE company_id = :cid AND period_id = :pid
             LIMIT 5000'
        );
        $stmt->execute(['cid' => $companyId, 'pid' => $periodId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $eid = (int) ($row['employee_id'] ?? 0);
            if ($eid < 1) {
                continue;
            }
            $out[$eid] = [
                'basic_salary' => (float) ($row['basic_salary'] ?? 0),
                'allowances' => (float) ($row['allowances'] ?? 0),
                'deductions' => (float) ($row['deductions'] ?? 0),
                'net_salary' => (float) ($row['net_salary'] ?? 0),
            ];
        }

        return $out;
    }

    private function clip(mixed $v, int $max): ?string
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '') {
            return null;
        }

        return mb_substr($s, 0, $max);
    }

    private function moneyOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return round((float) $v, 2);
    }

    /**
     * Latest WPS batches for company (local only).
     *
     * @return list<array<string,mixed>>
     */
    public function listWpsBatches(int $companyId, int $limit = 20): array
    {
        if (!$this->schemaReady() || $companyId < 1) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $stmt = Database::connection()->prepare(
            "SELECT * FROM rateb_hr_wps_export_batches
             WHERE company_id = :cid
             ORDER BY id DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['cid' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
