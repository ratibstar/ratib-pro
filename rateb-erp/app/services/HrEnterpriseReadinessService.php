<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;

/**
 * Phase T — HR enterprise production readiness (read-only inventory + checks).
 *
 * Does not mutate SoT, payroll, approvals, or enable external connectors.
 */
final class HrEnterpriseReadinessService
{
    /** @var list<array{phase:string,file:string,tables:list<string>}> */
    public const MIGRATION_INVENTORY = [
        ['phase' => 'B', 'file' => '247_hr_phase_b_ess_user_company_index.sql', 'tables' => []],
        ['phase' => 'G', 'file' => '248_hr_phase_g_approval_matrix.sql', 'tables' => ['rateb_hr_approval_matrices', 'rateb_hr_approval_matrix_stages', 'rateb_hr_approval_progress']],
        ['phase' => 'H2', 'file' => '249_hr_phase_h2_leave_integrity.sql', 'tables' => []],
        ['phase' => 'K', 'file' => '250_hr_phase_k_hirebridge_contracts.sql', 'tables' => ['rateb_hr_employment_contracts']],
        ['phase' => 'L', 'file' => '251_hr_phase_l_letters.sql', 'tables' => []],
        ['phase' => 'M', 'file' => '252_hr_phase_m_decisions.sql', 'tables' => ['rateb_hr_decisions']],
        ['phase' => 'O', 'file' => '253_hr_phase_o_succession.sql', 'tables' => ['rateb_hr_critical_positions', 'rateb_hr_succession_candidates']],
        ['phase' => 'P', 'file' => '254_hr_phase_p_saudi_foundation.sql', 'tables' => ['rateb_hr_saudi_employment_fields', 'rateb_hr_saudi_integration_audit']],
        ['phase' => 'Q', 'file' => '255_hr_phase_q_ops_automation.sql', 'tables' => ['rateb_hr_ops_reminder_ledger', 'rateb_hr_ops_automation_settings']],
        ['phase' => 'R', 'file' => '256_hr_phase_r_saudi_compliance.sql', 'tables' => ['rateb_hr_gosi_period_lines', 'rateb_hr_wps_export_batches', 'rateb_hr_wps_export_lines']],
        ['phase' => 'S', 'file' => '257_hr_phase_s_workforce_intelligence.sql', 'tables' => ['rateb_hr_workforce_plan_targets']],
    ];

    /** @var list<string> */
    public const CRON_JOBS = [
        'hr_employment_contract_status',
        'hr_employment_contract_alerts',
        'hr_ops_automation',
    ];

    /**
     * Full readiness payload for Admin diagnostics page / Command Center.
     *
     * @return array<string,mixed>
     */
    public function snapshot(int $companyId): array
    {
        $migrations = $this->migrationStatus();
        $missing = array_values(array_filter($migrations, static fn ($m) => empty($m['ready'])));
        $integrity = $companyId > 0
            ? (new HrEmployeeIntegrityService())->diagnoseCompany($companyId)
            : ['company_id' => 0, 'duplicates' => [], 'orphans' => [], 'hrms' => [], 'notes' => ['company_id_required']];
        $saudi = $companyId > 0
            ? (new HrSaudiComplianceService())->readinessSummary($companyId)
            : ['external_send_enabled' => false, 'readiness_pct' => 0];

        return [
            'phase' => 'T',
            'company_id' => $companyId,
            'migrations' => $migrations,
            'missing_migrations' => $missing,
            'migrations_ok' => $missing === [],
            'cron_jobs' => self::CRON_JOBS,
            'feature_flags' => $this->featureFlags(),
            'indexes_notes' => [
                'ess_user_company' => '247 — rateb_employees (user_id, company_id) index for ESS binding',
                'ops_reminder_unique' => '255 — unique ledger prevents duplicate automation notifications',
                'gosi_period_unique' => '256 — unique (company, year, month, employee) for GOSI lines',
            ],
            'hotspots' => [
                'command_center' => 'Bounded LIST_LIMIT aggregates; avoid Employee 360 in loops',
                'employee_360' => 'Lazy tab load via loadTab(); shell-only on first paint',
                'approvals_inbox' => 'Oversight listPending + matrix context; company scoped',
                'analytics_workforce' => 'GROUP BY / LIMIT; salary gated',
                'ess_apis' => 'Resolver company + user binding required',
            ],
            'integrity' => $this->compactIntegrity($integrity),
            'saudi' => [
                'readiness_pct' => (int) ($saudi['readiness_pct'] ?? 0),
                'missing_data' => (int) ($saudi['missing_data'] ?? 0),
                'gosi_exceptions' => (int) ($saudi['gosi_exceptions'] ?? 0),
                'wps_exceptions' => (int) ($saudi['wps_exceptions'] ?? 0),
                'external_send_enabled' => false,
                'connectors_off' => true,
            ],
            'required_configuration' => [
                'HR_PAYROLL_ACCOUNTING_ENABLED' => 'default OFF (Phase E adapter)',
                'HR_PAYROLL_EXPENSE_ACCOUNT_CODE' => 'required only if payroll accounting is enabled',
                'HR_PAYROLL_PAYABLE_ACCOUNT_CODE' => 'required only if payroll accounting is enabled',
                'cron_schedule' => 'php bin/erp-cron.php every 5–15 minutes',
            ],
            'deployment_prerequisites' => [
                'Apply migrations 247 through 257 in order (additive; no DROP)',
                'ERP UI only at /public/admin/* — no V2 SPA production frontend',
                'GOSI/WPS external send remains hardcoded OFF',
                'CronService::runAll via bin/erp-cron.php',
            ],
            'production_blockers' => $this->productionBlockers($missing, $saudi),
            'automation' => [
                'idempotency' => 'HrOpsAutomationService::claimReminder unique ledger',
                'retry_safety' => 'Duplicate claim returns false; no domain approve/post',
                'failed_cron_recovery' => 'Next cron run re-attempts unclaimed period keys only',
            ],
            'security' => [
                'tenant_isolation' => 'company_id on all HR queries',
                'salary_privacy' => 'RBAC hr-payroll / hr.manage gated; 360 deny-by-default without helpers',
                'ess_binding' => 'HrEssEmployeeResolverService company+user',
                'external_gosi_wps' => 'OFF — external_sent default 0',
            ],
            'policy' => 'Phase T hardens and documents B–S; no new business engines.',
        ];
    }

    /**
     * Compact Command Center integrity widget (read-only COUNTs).
     *
     * @return array<string,mixed>
     */
    public function compactIntegrityForCompany(int $companyId): array
    {
        $integrity = $companyId > 0
            ? (new HrEmployeeIntegrityService())->diagnoseCompany($companyId)
            : ['duplicates' => [], 'orphans' => [], 'hrms' => [], 'notes' => ['company_id_required']];

        return $this->compactIntegrity($integrity);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function migrationStatus(): array
    {
        $out = [];
        foreach (self::MIGRATION_INVENTORY as $row) {
            $fileOk = is_file(dirname(__DIR__, 2) . '/migrations/' . $row['file'])
                || is_file((defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2)) . '/migrations/' . $row['file']);
            $tablesOk = true;
            $missingTables = [];
            foreach ($row['tables'] as $table) {
                try {
                    if (!Database::tableExists($table)) {
                        $tablesOk = false;
                        $missingTables[] = $table;
                    }
                } catch (\Throwable $e) {
                    $tablesOk = false;
                    $missingTables[] = $table;
                }
            }
            $out[] = [
                'phase' => $row['phase'],
                'file' => $row['file'],
                'file_present' => $fileOk,
                'tables' => $row['tables'],
                'missing_tables' => $missingTables,
                'ready' => $fileOk && $tablesOk,
            ];
        }

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    public function featureFlags(): array
    {
        $payrollAcct = class_exists(HrPayrollAccountingConfig::class)
            ? HrPayrollAccountingConfig::isEnabled()
            : false;

        return [
            'HR_PAYROLL_ACCOUNTING_ENABLED' => [
                'enabled' => (bool) $payrollAcct,
                'default' => false,
                'note' => 'Phase E — payroll GL posting adapter; OFF by default',
            ],
            'gosi_wps_external_send' => [
                'enabled' => false,
                'default' => false,
                'note' => 'Phase R/T — must remain OFF',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $integrity
     * @return array<string,mixed>
     */
    private function compactIntegrity(array $integrity): array
    {
        $dup = is_array($integrity['duplicates'] ?? null) ? $integrity['duplicates'] : [];
        $dupCount = 0;
        foreach ($dup as $list) {
            $dupCount += is_array($list) ? count($list) : 0;
        }
        $orphans = is_array($integrity['orphans'] ?? null) ? $integrity['orphans'] : [];
        $orphanTotal = 0;
        foreach ($orphans as $n) {
            $orphanTotal += (int) $n;
        }

        return [
            'duplicate_groups' => $dupCount,
            'orphan_total' => $orphanTotal,
            'orphans' => $orphans,
            'hrms' => is_array($integrity['hrms'] ?? null) ? $integrity['hrms'] : [],
            'contracts' => is_array($integrity['contracts'] ?? null) ? $integrity['contracts'] : [],
            'salary' => is_array($integrity['salary'] ?? null) ? $integrity['salary'] : [],
            'notes' => is_array($integrity['notes'] ?? null) ? $integrity['notes'] : [],
            'auto_repair' => false,
        ];
    }

    /**
     * @param list<array<string,mixed>> $missing
     * @param array<string,mixed> $saudi
     * @return list<string>
     */
    private function productionBlockers(array $missing, array $saudi): array
    {
        $out = [];
        if ($missing !== []) {
            $names = [];
            foreach ($missing as $row) {
                $names[] = (string) ($row['file'] ?? '');
            }
            $out[] = 'HR migrations 247–257 incomplete: ' . implode(', ', array_filter($names));
        }
        if (!empty($saudi['external_send_enabled'])) {
            $out[] = 'GOSI/WPS external send must remain OFF';
        }
        $flags = $this->featureFlags();
        if (!empty($flags['gosi_wps_external_send']['enabled'])) {
            $out[] = 'gosi_wps_external_send feature flag is ON — production forbidden';
        }

        return $out;
    }
}
