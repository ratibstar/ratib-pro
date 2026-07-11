<?php

declare(strict_types=1);

/**
 * Phase 24A — Enterprise Payroll Platform (ONLINE) gate tests.
 *
 * Run: php tests/payroll/run-payroll-phase24a-tests.php
 */
final class PayrollPhase24ATest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testBaselineUntouched();
        $this->testNoOfflineCoupling();
        $this->testMigrationExists();
        $this->testDomainServicesPresent();
        $this->testDistinctFromLegacyPayroll();
        $this->testWorkflowMaps();
        $this->testUuidHelper();
        $this->testControllersAndViews();
        $this->testRoutesRegistered();
        $this->testRbacConfig();
        $this->testSidebarAndLang();
        $this->testArchitectureDoc();
        $this->testOfflineReadinessDoc();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testBaselineUntouched(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = preg_match('/DB_VERSION\s*=\s*2/', $schema)
            && str_contains($sdk, "version: '14.2.0'");
        $this->record('Enterprise Baseline / Offline Foundation markers intact', $ok);
    }

    private function testNoOfflineCoupling(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/PayrollDomainServices.php');
        $workflow = (string) file_get_contents(RATEB_ROOT . '/app/services/PayrollWorkflowService.php');
        $ok = !str_contains($domain, 'OfflineQueueService')
            && !str_contains($workflow, 'OfflineQueueService')
            && !str_contains($domain, 'offline.payroll');
        $this->record('24A online layer has no offline coupling (24B deferred)', $ok);
    }

    private function testMigrationExists(): void
    {
        $path = RATEB_ROOT . '/migrations/190_payroll_platform_enterprise.sql';
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $sql !== ''
            && str_contains($sql, 'rateb_payroll_salary_structures')
            && str_contains($sql, 'rateb_payroll_salary_components')
            && str_contains($sql, 'rateb_payroll_earning_types')
            && str_contains($sql, 'rateb_payroll_deduction_types')
            && str_contains($sql, 'rateb_payroll_employee_salary')
            && str_contains($sql, 'rateb_payroll_cycles')
            && str_contains($sql, 'rateb_payroll_run_periods')
            && str_contains($sql, 'rateb_payroll_batches')
            && str_contains($sql, 'rateb_payroll_items')
            && str_contains($sql, 'rateb_payroll_payslips')
            && str_contains($sql, 'rateb_payroll_overtime')
            && str_contains($sql, 'rateb_payroll_bonuses')
            && str_contains($sql, 'rateb_payroll_commissions')
            && str_contains($sql, 'rateb_payroll_loans')
            && str_contains($sql, 'rateb_payroll_loan_installments')
            && str_contains($sql, 'rateb_payroll_advances')
            && str_contains($sql, 'rateb_payroll_reimbursements')
            && str_contains($sql, 'rateb_payroll_settlements')
            && str_contains($sql, 'rateb_payroll_adjustments')
            && str_contains($sql, 'rateb_payroll_notes')
            && str_contains($sql, 'rateb_payroll_comments')
            && str_contains($sql, 'rateb_payroll_timeline')
            && str_contains($sql, 'rateb_payroll_attachments_meta')
            && str_contains($sql, 'rateb_payroll_status_history')
            && str_contains($sql, 'rateb_payroll_assignments')
            && str_contains($sql, 'rateb_payroll_audit')
            && str_contains($sql, 'payroll.view')
            && str_contains($sql, 'payroll.calculate')
            && str_contains($sql, 'public_uuid')
            && str_contains($sql, 'deleted_at')
            && str_contains($sql, 'version')
            && !str_contains($sql, "\n,    version")
            && !str_contains($sql, 'NULL,,')
            && !str_contains($sql, 'ALTER TABLE rateb_payroll_periods')
            && !str_contains($sql, 'ALTER TABLE rateb_payroll_lines')
            && !str_contains($sql, 'ALTER TABLE rateb_hr_payroll');
        $this->record('migration 190 schema + permissions', $ok);
    }

    private function testDomainServicesPresent(): void
    {
        $ok = class_exists(\Rateb\App\Services\PayrollSupport::class)
            && class_exists(\Rateb\App\Services\PayrollWorkflowService::class)
            && class_exists(\Rateb\App\Services\PayrollTimelineService::class)
            && class_exists(\Rateb\App\Services\PayrollEnterpriseService::class)
            && class_exists(\Rateb\App\Services\PayrollStructureService::class)
            && class_exists(\Rateb\App\Services\PayrollComponentService::class)
            && class_exists(\Rateb\App\Services\PayrollCycleService::class)
            && class_exists(\Rateb\App\Services\PayrollBatchService::class)
            && class_exists(\Rateb\App\Services\PayrollCalculationService::class)
            && class_exists(\Rateb\App\Services\PayrollPayslipService::class)
            && class_exists(\Rateb\App\Services\LoanService::class)
            && class_exists(\Rateb\App\Services\AdvanceService::class)
            && class_exists(\Rateb\App\Services\BonusService::class)
            && class_exists(\Rateb\App\Services\OvertimeService::class)
            && class_exists(\Rateb\App\Services\SettlementService::class)
            && class_exists(\Rateb\App\Services\PayrollCommentService::class)
            && class_exists(\Rateb\App\Services\PayrollDocumentMetaService::class)
            && class_exists(\Rateb\App\Models\PayrollBatch::class)
            && class_exists(\Rateb\App\Models\PayrollPayslip::class);
        $this->record('domain services present', $ok);
    }

    private function testDistinctFromLegacyPayroll(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/PayrollDomainServices.php');
        $workflow = (string) file_get_contents(RATEB_ROOT . '/app/services/PayrollWorkflowService.php');
        $ok = str_contains($domain, 'rateb_payroll_')
            && !preg_match('/\bFROM\s+rateb_payroll_periods\b|\bINTO\s+rateb_payroll_periods\b|\bUPDATE\s+rateb_payroll_periods\b/i', $domain)
            && !preg_match('/\bFROM\s+rateb_payroll_lines\b/i', $domain)
            && !preg_match('/\bFROM\s+rateb_hr_payroll_/i', $domain)
            && !str_contains($domain, 'AccountingService')
            && !str_contains($domain, 'postJournal')
            && !str_contains($workflow, 'rateb_payroll_periods')
            && class_exists(\Rateb\App\Services\HrService::class)
            && is_file(RATEB_ROOT . '/app/controllers/Company/HrControllers.php');
        $this->record('payroll platform distinct from legacy HR payroll / accounting GL', $ok);
    }

    private function testWorkflowMaps(): void
    {
        $batch = \Rateb\App\Services\PayrollWorkflowService::statuses('batch');
        $map = \Rateb\App\Services\PayrollWorkflowService::allowedTransitions('batch');
        $ok = in_array('draft', $batch, true)
            && in_array('prepared', $batch, true)
            && in_array('calculated', $batch, true)
            && in_array('reviewed', $batch, true)
            && in_array('approved', $batch, true)
            && in_array('posted', $batch, true)
            && in_array('closed', $batch, true)
            && in_array('archived', $batch, true)
            && in_array('prepared', $map['draft'] ?? [], true)
            && in_array('calculated', $map['prepared'] ?? [], true)
            && in_array('reviewed', $map['calculated'] ?? [], true)
            && in_array('approved', $map['reviewed'] ?? [], true)
            && in_array('posted', $map['approved'] ?? [], true)
            && in_array('closed', $map['posted'] ?? [], true)
            && ($map['archived'] ?? null) === [];
        $this->record('payroll workflow maps', $ok, implode(',', $batch));
    }

    private function testUuidHelper(): void
    {
        $u = \Rateb\App\Services\PayrollSupport::uuidV4();
        $ok = is_string($u) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $u);
        $this->record('UUID helper', $ok, $u);
    }

    private function testControllersAndViews(): void
    {
        $ok = class_exists(\Rateb\App\Controllers\Company\PayrollDashboardController::class)
            && class_exists(\Rateb\App\Controllers\Company\PayrollBatchesController::class)
            && class_exists(\Rateb\App\Controllers\Company\PayrollCyclesController::class)
            && class_exists(\Rateb\App\Controllers\Company\PayrollPayslipsController::class)
            && class_exists(\Rateb\App\Controllers\Company\PayrollLoansController::class)
            && class_exists(\Rateb\App\Controllers\Company\PayrollAdvancesController::class)
            && class_exists(\Rateb\App\Controllers\Company\PayrollOvertimeController::class)
            && class_exists(\Rateb\App\Controllers\Company\PayrollSalaryStructuresController::class)
            && is_file(RATEB_ROOT . '/views/company/payroll/dashboard.php')
            && is_file(RATEB_ROOT . '/views/company/payroll/batches/index.php')
            && is_file(RATEB_ROOT . '/views/company/payroll/batches/show.php')
            && is_file(RATEB_ROOT . '/views/company/payroll/cycles/index.php')
            && is_file(RATEB_ROOT . '/views/company/payroll/payslips/index.php')
            && is_file(RATEB_ROOT . '/views/company/payroll/loans/index.php')
            && is_file(RATEB_ROOT . '/views/company/payroll/advances/index.php')
            && is_file(RATEB_ROOT . '/views/company/payroll/overtime/index.php')
            && is_file(RATEB_ROOT . '/views/company/payroll/salary-structures/index.php')
            && is_file(RATEB_ROOT . '/views/company/payroll/reports/index.php')
            && is_file(RATEB_ROOT . '/views/company/payroll/timeline/index.php');
        $this->record('controllers + views present', $ok);
    }

    private function testRoutesRegistered(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/routes/company.php');
        $ok = str_contains($routes, "payroll-platform")
            && str_contains($routes, "payroll/dashboard")
            && str_contains($routes, "payroll/batches")
            && str_contains($routes, "payroll/cycles")
            && str_contains($routes, "payroll/payslips")
            && str_contains($routes, "payroll/loans")
            && str_contains($routes, "payroll/advances")
            && str_contains($routes, "payroll/overtime")
            && str_contains($routes, "payroll/reports")
            && str_contains($routes, "rateb_erp_mw('payroll'")
            && str_contains($routes, 'Phase 24A')
            && str_contains($routes, 'hr/payroll');
        $this->record('routes registered (payroll/* + legacy hr/payroll preserved)', $ok);
    }

    private function testRbacConfig(): void
    {
        $perms = require RATEB_ROOT . '/config/permissions-system.php';
        $entities = require RATEB_ROOT . '/config/entity-permissions.php';
        $labels = require RATEB_ROOT . '/config/permission-labels-en.php';
        $implies = $perms['permission_implies']['payroll.manage'] ?? [];
        $ok = in_array('payroll', $perms['company_modules'] ?? [], true)
            && in_array('payroll.view', $implies, true)
            && in_array('payroll.calculate', $implies, true)
            && in_array('payroll.approve', $implies, true)
            && in_array('payroll.post', $implies, true)
            && isset($entities['payroll'], $entities['payroll-batches'], $entities['payroll-payslips'])
            && isset($labels['payroll.create'], $labels['payroll.admin'], $labels['payroll.manage']);
        $this->record('RBAC module + implies + labels wiring', $ok);
    }

    private function testSidebarAndLang(): void
    {
        $nav = (string) file_get_contents(RATEB_ROOT . '/views/partials/sidebar-ops-nav.php');
        $en = require RATEB_ROOT . '/config/lang/en.php';
        $ar = require RATEB_ROOT . '/config/lang/ar.php';
        $ok = str_contains($nav, "payroll/batches")
            && str_contains($nav, "payroll/payslips")
            && str_contains($nav, 'payroll.view')
            && isset($en['payroll_platform'], $en['payroll_batches'], $ar['payroll_platform'], $ar['payroll_payslips']);
        $this->record('sidebar + EN/AR translations', $ok);
    }

    private function testArchitectureDoc(): void
    {
        $path = RATEB_ROOT . '/docs/PHASE_24A_PAYROLL_ONLINE.md';
        $doc = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $doc !== ''
            && str_contains($doc, '190_payroll_platform_enterprise.sql')
            && str_contains($doc, 'PayrollWorkflowService')
            && str_contains($doc, 'rateb_payroll_')
            && str_contains($doc, 'Enterprise Baseline')
            && str_contains($doc, 'Offline Foundation');
        $this->record('architecture doc present', $ok);
    }

    private function testOfflineReadinessDoc(): void
    {
        $doc = (string) file_get_contents(RATEB_ROOT . '/docs/PHASE_24A_PAYROLL_ONLINE.md');
        $ok = str_contains($doc, 'Offline readiness')
            && str_contains($doc, '24B')
            && str_contains($doc, 'Replay-ready');
        $this->record('offline readiness matrix in docs', $ok);
    }
}
