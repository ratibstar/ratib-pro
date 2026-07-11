<?php

declare(strict_types=1);

/**
 * Phase 23A — Enterprise Human Resources Platform (ONLINE) gate tests.
 *
 * Run: php tests/hr/run-hr-phase23a-tests.php
 */
final class HrPhase23ATest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testBaselineUntouched();
        $this->testNoOfflineHrmCoupling();
        $this->testMigrationExists();
        $this->testDomainServicesPresent();
        $this->testDistinctFromLegacyHr();
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

    private function testNoOfflineHrmCoupling(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/HumanResourcesDomainServices.php');
        $workflow = (string) file_get_contents(RATEB_ROOT . '/app/services/HumanResourcesWorkflowService.php');
        $ok = !str_contains($domain, 'OfflineQueueService')
            && !str_contains($workflow, 'OfflineQueueService')
            && !str_contains($domain, 'offline.hr')
            && is_file(RATEB_ROOT . '/offline/server/Services/HumanResourcesOfflineReplayService.php')
            && is_file(RATEB_ROOT . '/offline/client/adapters/hr-adapter.js');
        $this->record('23A online layer has no offline coupling (23B replay separate)', $ok);
    }

    private function testMigrationExists(): void
    {
        $path = RATEB_ROOT . '/migrations/189_hr_platform_enterprise.sql';
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $sql !== ''
            && str_contains($sql, 'rateb_hrm_departments')
            && str_contains($sql, 'rateb_hrm_positions')
            && str_contains($sql, 'rateb_hrm_grades')
            && str_contains($sql, 'rateb_hrm_locations')
            && str_contains($sql, 'rateb_hrm_org_units')
            && str_contains($sql, 'rateb_hrm_employee_profiles')
            && str_contains($sql, 'rateb_hrm_employee_documents_meta')
            && str_contains($sql, 'rateb_hrm_employee_contacts')
            && str_contains($sql, 'rateb_hrm_dependents')
            && str_contains($sql, 'rateb_hrm_emergency_contacts')
            && str_contains($sql, 'rateb_hrm_certifications')
            && str_contains($sql, 'rateb_hrm_licenses')
            && str_contains($sql, 'rateb_hrm_skills')
            && str_contains($sql, 'rateb_hrm_languages')
            && str_contains($sql, 'rateb_hrm_training')
            && str_contains($sql, 'rateb_hrm_training_history')
            && str_contains($sql, 'rateb_hrm_performance_reviews')
            && str_contains($sql, 'rateb_hrm_goals')
            && str_contains($sql, 'rateb_hrm_competencies')
            && str_contains($sql, 'rateb_hrm_disciplinary_actions')
            && str_contains($sql, 'rateb_hrm_rewards')
            && str_contains($sql, 'rateb_hrm_transfers')
            && str_contains($sql, 'rateb_hrm_promotions')
            && str_contains($sql, 'rateb_hrm_assignments')
            && str_contains($sql, 'rateb_hrm_notes')
            && str_contains($sql, 'rateb_hrm_comments')
            && str_contains($sql, 'rateb_hrm_timeline')
            && str_contains($sql, 'rateb_hrm_tags')
            && str_contains($sql, 'rateb_hrm_entity_tags')
            && str_contains($sql, 'rateb_hrm_status_history')
            && str_contains($sql, 'hr.training')
            && str_contains($sql, 'hr.performance')
            && str_contains($sql, 'public_uuid')
            && str_contains($sql, 'deleted_at')
            && str_contains($sql, 'version')
            && !str_contains($sql, "\n,    version")
            && !str_contains($sql, 'NULL,,')
            && !str_contains($sql, 'ALTER TABLE rateb_employees')
            && !str_contains($sql, 'ALTER TABLE rateb_hr_')
            && !str_contains($sql, 'ALTER TABLE rateb_attendance')
            && !str_contains($sql, 'ALTER TABLE rateb_payroll');
        $this->record('migration 189 schema + permissions', $ok);
    }

    private function testDomainServicesPresent(): void
    {
        $ok = class_exists(\Rateb\App\Services\HumanResourcesSupport::class)
            && class_exists(\Rateb\App\Services\HumanResourcesWorkflowService::class)
            && class_exists(\Rateb\App\Services\EmployeeTimelineService::class)
            && class_exists(\Rateb\App\Services\HumanResourcesEnterpriseService::class)
            && class_exists(\Rateb\App\Services\EmployeeProfileService::class)
            && class_exists(\Rateb\App\Services\DepartmentService::class)
            && class_exists(\Rateb\App\Services\PositionService::class)
            && class_exists(\Rateb\App\Services\GradeService::class)
            && class_exists(\Rateb\App\Services\OrganizationService::class)
            && class_exists(\Rateb\App\Services\CertificationService::class)
            && class_exists(\Rateb\App\Services\TrainingService::class)
            && class_exists(\Rateb\App\Services\PerformanceReviewService::class)
            && class_exists(\Rateb\App\Services\GoalService::class)
            && class_exists(\Rateb\App\Services\CompetencyService::class)
            && class_exists(\Rateb\App\Services\PromotionService::class)
            && class_exists(\Rateb\App\Services\TransferService::class)
            && class_exists(\Rateb\App\Services\HrmAssignmentService::class)
            && class_exists(\Rateb\App\Services\EmployeeCommentService::class)
            && class_exists(\Rateb\App\Services\EmployeeDocumentMetaService::class)
            && class_exists(\Rateb\App\Models\HrmEmployeeProfile::class)
            && class_exists(\Rateb\App\Models\HrmDepartment::class)
            && class_exists(\Rateb\App\Models\HrmTraining::class);
        $this->record('domain services present', $ok);
    }

    private function testDistinctFromLegacyHr(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/HumanResourcesDomainServices.php');
        $workflow = (string) file_get_contents(RATEB_ROOT . '/app/services/HumanResourcesWorkflowService.php');
        $ok = str_contains($domain, 'rateb_hrm_')
            && !preg_match('/\bFROM\s+rateb_employees\b|\bINTO\s+rateb_employees\b|\bUPDATE\s+rateb_employees\b/i', $domain)
            && !preg_match('/\bFROM\s+rateb_attendance|\bFROM\s+rateb_payroll|\bFROM\s+rateb_leave_/i', $domain)
            && !str_contains($workflow, 'rateb_employees')
            && !str_contains($domain, 'OfflineQueueService')
            && class_exists(\Rateb\App\Services\HrService::class)
            && is_file(RATEB_ROOT . '/app/controllers/Company/HrControllers.php');
        $this->record('HRMS distinct from legacy HR / attendance / payroll', $ok);
    }

    private function testWorkflowMaps(): void
    {
        $emp = \Rateb\App\Services\HumanResourcesWorkflowService::statuses('employee');
        $trn = \Rateb\App\Services\HumanResourcesWorkflowService::statuses('training');
        $perf = \Rateb\App\Services\HumanResourcesWorkflowService::statuses('performance');
        $map = \Rateb\App\Services\HumanResourcesWorkflowService::allowedTransitions('employee');
        $ok = in_array('draft', $emp, true)
            && in_array('registered', $emp, true)
            && in_array('active', $emp, true)
            && in_array('on_leave', $emp, true)
            && in_array('suspended', $emp, true)
            && in_array('terminated', $emp, true)
            && in_array('archived', $emp, true)
            && in_array('planned', $trn, true)
            && in_array('scheduled', $trn, true)
            && in_array('in_progress', $trn, true)
            && in_array('completed', $trn, true)
            && in_array('cancelled', $trn, true)
            && in_array('draft', $perf, true)
            && in_array('submitted', $perf, true)
            && in_array('approved', $perf, true)
            && in_array('closed', $perf, true)
            && in_array('registered', $map['draft'] ?? [], true)
            && in_array('active', $map['registered'] ?? [], true)
            && ($map['archived'] ?? null) === [];
        $this->record('HRMS workflow maps', $ok, implode(',', $emp));
    }

    private function testUuidHelper(): void
    {
        $u = \Rateb\App\Services\HumanResourcesSupport::uuidV4();
        $ok = is_string($u) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $u);
        $this->record('UUID helper', $ok, $u);
    }

    private function testControllersAndViews(): void
    {
        $ok = class_exists(\Rateb\App\Controllers\Company\HrmDashboardController::class)
            && class_exists(\Rateb\App\Controllers\Company\HrmEmployeesController::class)
            && class_exists(\Rateb\App\Controllers\Company\HrmDepartmentsController::class)
            && class_exists(\Rateb\App\Controllers\Company\HrmTrainingController::class)
            && class_exists(\Rateb\App\Controllers\Company\HrmPerformanceController::class)
            && is_file(RATEB_ROOT . '/views/company/hrm/dashboard.php')
            && is_file(RATEB_ROOT . '/views/company/hrm/employees/index.php')
            && is_file(RATEB_ROOT . '/views/company/hrm/employees/show.php')
            && is_file(RATEB_ROOT . '/views/company/hrm/departments/index.php')
            && is_file(RATEB_ROOT . '/views/company/hrm/positions/index.php')
            && is_file(RATEB_ROOT . '/views/company/hrm/organization/index.php')
            && is_file(RATEB_ROOT . '/views/company/hrm/training/index.php')
            && is_file(RATEB_ROOT . '/views/company/hrm/performance/index.php')
            && is_file(RATEB_ROOT . '/views/company/hrm/promotions/index.php')
            && is_file(RATEB_ROOT . '/views/company/hrm/transfers/index.php')
            && is_file(RATEB_ROOT . '/views/company/hrm/goals/index.php')
            && is_file(RATEB_ROOT . '/views/company/hrm/competencies/index.php')
            && is_file(RATEB_ROOT . '/views/company/hrm/reports/index.php');
        $this->record('controllers + views present', $ok);
    }

    private function testRoutesRegistered(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/routes/company.php');
        $ok = str_contains($routes, "hrm/employees")
            && str_contains($routes, "hrm/departments")
            && str_contains($routes, "hrm/training")
            && str_contains($routes, "hrm/performance")
            && str_contains($routes, "hrm/promotions")
            && str_contains($routes, "hrm/transfers")
            && str_contains($routes, "rateb_erp_mw('hr'")
            && str_contains($routes, 'Phase 23A')
            && str_contains($routes, 'mfg/products')
            && str_contains($routes, 'hr/employees');
        $this->record('routes registered (hrm/* + legacy hr preserved)', $ok);
    }

    private function testRbacConfig(): void
    {
        $perms = require RATEB_ROOT . '/config/permissions-system.php';
        $entities = require RATEB_ROOT . '/config/entity-permissions.php';
        $labels = require RATEB_ROOT . '/config/permission-labels-en.php';
        $implies = $perms['permission_implies']['hr.manage'] ?? [];
        $ok = in_array('hr', $perms['company_modules'] ?? [], true)
            && in_array('hr.view', $implies, true)
            && in_array('hr.training', $implies, true)
            && in_array('hr.performance', $implies, true)
            && in_array('hr.promotions', $implies, true)
            && in_array('hr.transfers', $implies, true)
            && isset($entities['hrm'], $entities['hrm-training'], $entities['hrm-performance'])
            && isset($labels['hr.create'], $labels['hr.training'], $labels['hr.admin']);
        $this->record('RBAC module + implies + labels wiring', $ok);
    }

    private function testSidebarAndLang(): void
    {
        $nav = (string) file_get_contents(RATEB_ROOT . '/views/partials/sidebar-ops-nav.php');
        $en = require RATEB_ROOT . '/config/lang/en.php';
        $ar = require RATEB_ROOT . '/config/lang/ar.php';
        $ok = str_contains($nav, "hrm/employees")
            && str_contains($nav, "hrm/training")
            && str_contains($nav, 'hr.view')
            && isset($en['hr_platform'], $en['hrm_employees'], $ar['hr_platform'], $ar['hrm_training']);
        $this->record('sidebar + EN/AR translations', $ok);
    }

    private function testArchitectureDoc(): void
    {
        $path = RATEB_ROOT . '/docs/PHASE_23A_HR_ONLINE.md';
        $doc = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $doc !== ''
            && str_contains($doc, '189_hr_platform_enterprise.sql')
            && str_contains($doc, 'HumanResourcesWorkflowService')
            && str_contains($doc, 'rateb_hrm_')
            && str_contains($doc, 'Enterprise Baseline')
            && str_contains($doc, 'Offline Foundation');
        $this->record('architecture doc present', $ok);
    }

    private function testOfflineReadinessDoc(): void
    {
        $doc = (string) file_get_contents(RATEB_ROOT . '/docs/PHASE_23A_HR_ONLINE.md');
        $ok = str_contains($doc, 'Offline readiness')
            && str_contains($doc, '23B')
            && str_contains($doc, 'Replay-ready');
        $this->record('offline readiness matrix in docs', $ok);
    }
}
