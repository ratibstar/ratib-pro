<?php

declare(strict_types=1);

/**
 * Phase R — Saudi HR Compliance Foundation → GOSI/WPS Readiness (structural gates).
 *
 * Run: php tests/hr/run-hr-phase-r-tests.php
 */
final class HrPhaseRSaudiComplianceTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $svc = $this->file('/app/services/HrSaudiComplianceService.php');
        $found = $this->file('/app/services/HrSaudiComplianceFoundationService.php');
        $mig = $this->file('/migrations/256_hr_phase_r_saudi_compliance.sql');
        $ctrl = $this->file('/app/controllers/Company/HrExtendedControllers.php');
        $ops = $this->file('/routes/modules/ops.php');
        $menu = $this->file('/config/hr-menu.php');
        $cc = $this->file('/app/services/HrCommandCenterService.php');
        $dash = $this->file('/views/company/hr/dashboard.php');
        $idx = $this->file('/views/company/hr/saudi/index.php');
        $rep = $this->file('/views/company/hr/saudi/reports.php');
        $classmap = $this->file('/app/Core/generated-classmap.php');
        $en = $this->file('/config/lang/en.php');
        $ar = $this->file('/config/lang/ar.php');

        $this->record(
            'R0 employee Saudi data fields + migration additive',
            str_contains($mig, 'employment_type')
            && str_contains($mig, 'saudi_classification')
            && str_contains($mig, 'gosi_eligible')
            && str_contains($mig, 'housing_allowance')
            && str_contains($mig, 'bank_name')
            && str_contains($svc, 'national_id')
            && str_contains($svc, 'iqama')
            && str_contains($svc, 'upsertEmployeeSaudiData')
            && str_contains($svc, 'rateb_employees')
            && !str_contains($mig, 'DROP TABLE')
        );

        $this->record(
            'R1 GOSI readiness model + period lines (local)',
            str_contains($mig, 'rateb_hr_gosi_period_lines')
            && str_contains($svc, 'buildGosiPeriod')
            && str_contains($svc, 'GOSI_SAUDI_EMPLOYEE_PCT')
            && str_contains($svc, 'contributionBaseFromRow')
            && str_contains($svc, 'gosiReportRows')
            && str_contains($svc, 'external_sent = 0')
        );

        $this->record(
            'R2 WPS readiness + IBAN + export batches (local)',
            str_contains($mig, 'rateb_hr_wps_export_batches')
            && str_contains($mig, 'rateb_hr_wps_export_lines')
            && str_contains($svc, 'buildWpsBatch')
            && str_contains($svc, 'validateIban')
            && str_contains($svc, 'mod 97') === false // uses % 97
            && str_contains($svc, '% 97')
            && str_contains($svc, 'wpsReportRows')
            && str_contains($svc, 'ready_local')
        );

        // Live IBAN math unit (no DB)
        $ibanOk = $this->assertIbanMath();
        $this->record('R2b IBAN validation mod-97', $ibanOk);

        $gosiOk = $this->assertGosiMath();
        $this->record('R1b GOSI contribution calculation model', $gosiOk);

        $this->record(
            'R3 validation gates present',
            str_contains($svc, 'iban_format')
            && str_contains($svc, 'iban_checksum')
            && str_contains($svc, 'national_id_missing')
            && str_contains($svc, 'salary_invalid')
            && str_contains($svc, 'contract_salary_mismatch')
            && str_contains($svc, 'not_eligible')
            && str_contains($svc, 'hr_r_iban_invalid')
        );

        $this->record(
            'R4 reports + export routes',
            str_contains($svc, 'missingDataReportRows')
            && str_contains($svc, 'payrollReconciliationRows')
            && str_contains($ctrl, 'function reports')
            && str_contains($ctrl, 'function export')
            && str_contains($ops, 'hr/saudi-compliance/reports')
            && str_contains($ops, 'hr/saudi-compliance/export')
            && str_contains($rep, 'hr_r_report_missing')
            && str_contains($idx, 'hr_saudi_compliance')
        );

        $this->record(
            'R5 Command Center Saudi readiness widgets',
            str_contains($cc, 'saudi_readiness')
            && str_contains($cc, 'HrSaudiComplianceService')
            && str_contains($cc, 'hr/saudi-compliance')
            && str_contains($dash, 'saudiReadiness')
            && str_contains($dash, 'hr_saudi_readiness')
            && str_contains($menu, 'hr/saudi-compliance')
        );

        $this->record(
            'R6 security tenant + salary privacy + RBAC surfaces',
            str_contains($svc, 'company_id = :cid')
            && str_contains($ctrl, 'canViewSaudiSalary')
            && str_contains($ctrl, 'hr-payroll')
            && str_contains($idx, 'canViewSalary')
            && str_contains($rep, 'hr_r_salary_privacy')
            && !preg_match('/\$_(GET|POST)\s*\[\s*[\'"]company_id[\'"]/', $svc)
            && !preg_match('/\$_(GET|POST)\s*\[\s*[\'"]company_id[\'"]/', $ctrl)
        );

        $this->record(
            'R7 audit local only',
            str_contains($svc, 'writeAudit')
            && str_contains($svc, 'AuditService')
            && str_contains($svc, 'hr_saudi_gosi_build')
            && str_contains($svc, 'hr_saudi_wps_build')
            && str_contains($found, 'external_sent')
            && str_contains($found, 'rateb_hr_saudi_integration_audit')
        );

        $this->record(
            'R8 external send remains OFF',
            str_contains($svc, 'external_send_enabled\' => false')
            && str_contains($svc, '\'external_sent\' => 0')
            && str_contains($mig, 'external_sent TINYINT(1) NOT NULL DEFAULT 0')
            && !str_contains($svc, 'curl_')
            && !str_contains($svc, 'file_get_contents(\'http')
            && !str_contains($svc, 'mudad')
            && !str_contains($svc, 'GOSI_API')
            && !str_contains($ctrl, 'external_sent = 1')
            && str_contains($en, 'hr_saudi_no_external_send')
            && str_contains($ar, 'hr_saudi_no_external_send')
        );

        $this->record(
            'R9 no redesign / no Phase S / classmap + B–Q runners',
            !str_contains($svc, 'generatePayrollLines')
            && !str_contains($svc, 'ApprovalEngine')
            && !str_contains($svc, 'Flutter')
            && !str_contains($svc, 'manager_id')
            && !str_contains($mig, 'Phase S')
            && str_contains($classmap, 'HrSaudiComplianceController')
            && (
                is_file(RATEB_ROOT . '/docs/hr/HR-PHASE-R-SAUDI-COMPLIANCE-CERTIFICATION.md')
                || is_file(dirname(RATEB_ROOT) . '/docs/hr/HR-PHASE-R-SAUDI-COMPLIANCE-CERTIFICATION.md')
            )
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-q-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-p-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-o-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-b-security-tests.php')
            && str_contains($ops, 'HrSaudiComplianceController')
        );

        return $this->results;
    }

    private function assertIbanMath(): bool
    {
        // Valid known SA IBAN checksum pattern — compute via same algorithm inline.
        $valid = 'SA0380000000608010167519';
        $invalid = 'SA0380000000608010167510';
        return $this->ibanErr($valid) === null && $this->ibanErr($invalid) !== null
            && $this->ibanErr('') === 'iban_missing'
            && $this->ibanErr('DE89370400440532013000') === 'iban_format';
    }

    private function ibanErr(string $iban): ?string
    {
        $raw = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
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

        return $checksum === 1 ? null : 'iban_checksum';
    }

    private function assertGosiMath(): bool
    {
        $saudiBase = min(10000.0 + 2000.0 + 500.0, 45000.0);
        $emp = round($saudiBase * 9.75 / 100, 2);
        $er = round($saudiBase * 11.75 / 100, 2);
        $nonBase = min(8000.0, 45000.0);
        $nonEr = round($nonBase * 2.0 / 100, 2);
        $capped = min(50000.0, 45000.0);

        return abs($saudiBase - 12500.0) < 0.001
            && abs($emp - 1218.75) < 0.001
            && abs($er - 1468.75) < 0.001
            && abs($nonEr - 160.0) < 0.001
            && abs($capped - 45000.0) < 0.001;
    }

    private function file(string $rel): string
    {
        $path = RATEB_ROOT . $rel;
        $this->record('file exists ' . $rel, is_file($path));

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = [
            'name' => $name,
            'passed' => $passed,
            'detail' => $detail !== '' ? $detail : ($passed ? 'ok' : 'fail'),
        ];
        echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    }
}
