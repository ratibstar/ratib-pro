<?php

declare(strict_types=1);

/**
 * Phase 25A — Enterprise Quality Management Platform (ONLINE) gate tests.
 *
 * Run: php tests/quality/run-quality-phase25a-tests.php
 */
final class QualityPhase25ATest
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
        $this->testDistinctFromMfgAndEam();
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
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/QualityDomainServices.php');
        $workflow = (string) file_get_contents(RATEB_ROOT . '/app/services/QualityWorkflowService.php');
        $ok = !str_contains($domain, 'OfflineQueueService')
            && !str_contains($workflow, 'OfflineQueueService')
            && !str_contains($domain, 'offline.quality')
            && !is_file(RATEB_ROOT . '/offline/server/Services/QualityOfflineReplayService.php')
            && !is_file(RATEB_ROOT . '/offline/client/adapters/quality-adapter.js');
        $this->record('25A online layer has no offline coupling (25B deferred)', $ok);
    }

    private function testMigrationExists(): void
    {
        $path = RATEB_ROOT . '/migrations/191_quality_management_platform.sql';
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $sql !== ''
            && str_contains($sql, 'rateb_qms_programs')
            && str_contains($sql, 'rateb_qms_plans')
            && str_contains($sql, 'rateb_qms_standards')
            && str_contains($sql, 'rateb_qms_checklists')
            && str_contains($sql, 'rateb_qms_checklist_items')
            && str_contains($sql, 'rateb_qms_inspections')
            && str_contains($sql, 'rateb_qms_results')
            && str_contains($sql, 'rateb_qms_defects')
            && str_contains($sql, 'rateb_qms_nonconformities')
            && str_contains($sql, 'rateb_qms_root_causes')
            && str_contains($sql, 'rateb_qms_corrective_actions')
            && str_contains($sql, 'rateb_qms_preventive_actions')
            && str_contains($sql, 'rateb_qms_audits')
            && str_contains($sql, 'rateb_qms_audit_findings')
            && str_contains($sql, 'rateb_qms_complaints')
            && str_contains($sql, 'rateb_qms_supplier_quality')
            && str_contains($sql, 'rateb_qms_training')
            && str_contains($sql, 'rateb_qms_documents_meta')
            && str_contains($sql, 'rateb_qms_comments')
            && str_contains($sql, 'rateb_qms_assignments')
            && str_contains($sql, 'rateb_qms_timeline')
            && str_contains($sql, 'rateb_qms_status_history')
            && str_contains($sql, 'rateb_qms_tags')
            && str_contains($sql, 'quality.view')
            && str_contains($sql, 'quality.inspect')
            && str_contains($sql, 'quality.corrective')
            && str_contains($sql, 'public_uuid')
            && str_contains($sql, 'deleted_at')
            && str_contains($sql, 'version')
            && !str_contains($sql, 'ALTER TABLE rateb_mfg_')
            && !str_contains($sql, 'ALTER TABLE rateb_eam_')
            && !str_contains($sql, 'ALTER TABLE rateb_payroll_');
        $this->record('migration 191 schema + permissions', $ok);
    }

    private function testDomainServicesPresent(): void
    {
        $ok = class_exists(\Rateb\App\Services\QualitySupport::class)
            && class_exists(\Rateb\App\Services\QualityWorkflowService::class)
            && class_exists(\Rateb\App\Services\QualityTimelineService::class)
            && class_exists(\Rateb\App\Services\QualityEnterpriseService::class)
            && class_exists(\Rateb\App\Services\QualityPlanService::class)
            && class_exists(\Rateb\App\Services\QualityStandardService::class)
            && class_exists(\Rateb\App\Services\QualityChecklistService::class)
            && class_exists(\Rateb\App\Services\QualityInspectionService::class)
            && class_exists(\Rateb\App\Services\QualityDefectService::class)
            && class_exists(\Rateb\App\Services\QualityNonconformityService::class)
            && class_exists(\Rateb\App\Services\QmsCorrectiveActionService::class)
            && class_exists(\Rateb\App\Services\QmsPreventiveActionService::class)
            && class_exists(\Rateb\App\Services\QualityAuditService::class)
            && class_exists(\Rateb\App\Services\QualityComplaintService::class)
            && class_exists(\Rateb\App\Services\SupplierQualityService::class)
            && class_exists(\Rateb\App\Models\QmsInspection::class)
            && class_exists(\Rateb\App\Models\QmsCorrectiveAction::class);
        $this->record('domain services present', $ok);
    }

    private function testDistinctFromMfgAndEam(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/QualityDomainServices.php');
        $workflow = (string) file_get_contents(RATEB_ROOT . '/app/services/QualityWorkflowService.php');
        $ok = str_contains($domain, 'rateb_qms_')
            && !preg_match('/\bFROM\s+rateb_mfg_quality_checks\b|\bUPDATE\s+rateb_mfg_quality_checks\b/i', $domain)
            && !preg_match('/\bFROM\s+rateb_eam_inspections\b|\bUPDATE\s+rateb_eam_inspections\b/i', $domain)
            && str_contains($domain, 'QualityInspectionService')
            && !str_contains($domain, 'final class QualityCheckService')
            && class_exists(\Rateb\App\Services\QualityCheckService::class)
            && !str_contains($workflow, 'rateb_mfg_')
            && !str_contains($workflow, 'rateb_eam_');
        $this->record('QMS distinct from MFG QualityCheckService / EAM inspections', $ok);
    }

    private function testWorkflowMaps(): void
    {
        $insp = \Rateb\App\Services\QualityWorkflowService::statuses('inspection');
        $inspMap = \Rateb\App\Services\QualityWorkflowService::allowedTransitions('inspection');
        $ca = \Rateb\App\Services\QualityWorkflowService::statuses('corrective_action');
        $caMap = \Rateb\App\Services\QualityWorkflowService::allowedTransitions('corrective_action');
        $ok = in_array('planned', $insp, true)
            && in_array('scheduled', $insp, true)
            && in_array('in_progress', $insp, true)
            && in_array('completed', $insp, true)
            && in_array('approved', $insp, true)
            && in_array('archived', $insp, true)
            && in_array('scheduled', $inspMap['planned'] ?? [], true)
            && in_array('approved', $inspMap['completed'] ?? [], true)
            && ($inspMap['archived'] ?? null) === []
            && in_array('draft', $ca, true)
            && in_array('assigned', $ca, true)
            && in_array('verified', $ca, true)
            && in_array('closed', $ca, true)
            && in_array('assigned', $caMap['draft'] ?? [], true)
            && in_array('closed', $caMap['verified'] ?? [], true)
            && ($caMap['archived'] ?? null) === [];
        $this->record('quality workflow maps', $ok, implode(',', $insp) . ' | ' . implode(',', $ca));
    }

    private function testUuidHelper(): void
    {
        $u = \Rateb\App\Services\QualitySupport::uuidV4();
        $ok = is_string($u) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $u);
        $this->record('UUID helper', $ok, $u);
    }

    private function testControllersAndViews(): void
    {
        $ok = class_exists(\Rateb\App\Controllers\Company\QualityDashboardController::class)
            && class_exists(\Rateb\App\Controllers\Company\QualityPlansController::class)
            && class_exists(\Rateb\App\Controllers\Company\QualityStandardsController::class)
            && class_exists(\Rateb\App\Controllers\Company\QualityChecklistsController::class)
            && class_exists(\Rateb\App\Controllers\Company\QualityInspectionsController::class)
            && class_exists(\Rateb\App\Controllers\Company\QualityDefectsController::class)
            && class_exists(\Rateb\App\Controllers\Company\QualityNonconformitiesController::class)
            && class_exists(\Rateb\App\Controllers\Company\QualityCorrectiveActionsController::class)
            && class_exists(\Rateb\App\Controllers\Company\QualityPreventiveActionsController::class)
            && class_exists(\Rateb\App\Controllers\Company\QualityAuditsController::class)
            && class_exists(\Rateb\App\Controllers\Company\QualityComplaintsController::class)
            && class_exists(\Rateb\App\Controllers\Company\QualitySupplierQualityController::class)
            && is_file(RATEB_ROOT . '/views/company/qms/dashboard.php')
            && is_file(RATEB_ROOT . '/views/company/qms/plans/index.php')
            && is_file(RATEB_ROOT . '/views/company/qms/standards/index.php')
            && is_file(RATEB_ROOT . '/views/company/qms/checklists/index.php')
            && is_file(RATEB_ROOT . '/views/company/qms/inspections/index.php')
            && is_file(RATEB_ROOT . '/views/company/qms/inspections/show.php')
            && is_file(RATEB_ROOT . '/views/company/qms/defects/index.php')
            && is_file(RATEB_ROOT . '/views/company/qms/nonconformities/index.php')
            && is_file(RATEB_ROOT . '/views/company/qms/corrective-actions/index.php')
            && is_file(RATEB_ROOT . '/views/company/qms/corrective-actions/show.php')
            && is_file(RATEB_ROOT . '/views/company/qms/preventive-actions/index.php')
            && is_file(RATEB_ROOT . '/views/company/qms/audits/index.php')
            && is_file(RATEB_ROOT . '/views/company/qms/complaints/index.php')
            && is_file(RATEB_ROOT . '/views/company/qms/supplier-quality/index.php')
            && is_file(RATEB_ROOT . '/views/company/qms/reports/index.php')
            && is_file(RATEB_ROOT . '/views/company/qms/timeline/index.php');
        $this->record('controllers + views present', $ok);
    }

    private function testRoutesRegistered(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/routes/company.php');
        $ok = str_contains($routes, 'qms-platform')
            && str_contains($routes, 'qms/dashboard')
            && str_contains($routes, 'qms/plans')
            && str_contains($routes, 'qms/inspections')
            && str_contains($routes, 'qms/corrective-actions')
            && str_contains($routes, 'qms/preventive-actions')
            && str_contains($routes, 'qms/audits')
            && str_contains($routes, 'qms/complaints')
            && str_contains($routes, 'qms/supplier-quality')
            && str_contains($routes, 'qms/reports')
            && str_contains($routes, 'qms/timeline')
            && str_contains($routes, "rateb_erp_mw('quality'")
            && str_contains($routes, 'Phase 25A')
            && str_contains($routes, 'mfg/quality');
        $this->record('routes registered (qms/* + MFG quality preserved)', $ok);
    }

    private function testRbacConfig(): void
    {
        $perms = require RATEB_ROOT . '/config/permissions-system.php';
        $entities = require RATEB_ROOT . '/config/entity-permissions.php';
        $labels = require RATEB_ROOT . '/config/permission-labels-en.php';
        $implies = $perms['permission_implies']['quality.manage'] ?? [];
        $ok = in_array('quality', $perms['company_modules'] ?? [], true)
            && in_array('quality.view', $implies, true)
            && in_array('quality.inspect', $implies, true)
            && in_array('quality.audit', $implies, true)
            && in_array('quality.corrective', $implies, true)
            && in_array('quality.preventive', $implies, true)
            && isset($entities['quality'], $entities['quality-inspections'], $entities['quality-corrective'])
            && isset($labels['quality.create'], $labels['quality.admin'], $labels['quality.manage']);
        $this->record('RBAC module + implies + labels wiring', $ok);
    }

    private function testSidebarAndLang(): void
    {
        $nav = (string) file_get_contents(RATEB_ROOT . '/views/partials/sidebar-ops-nav.php');
        $en = require RATEB_ROOT . '/config/lang/en.php';
        $ar = require RATEB_ROOT . '/config/lang/ar.php';
        $ok = str_contains($nav, 'qms/inspections')
            && str_contains($nav, 'qms/corrective-actions')
            && str_contains($nav, 'quality.view')
            && isset($en['quality_platform'], $en['quality_inspections'], $ar['quality_platform'], $ar['quality_audits']);
        $this->record('sidebar + EN/AR translations', $ok);
    }

    private function testArchitectureDoc(): void
    {
        $path = RATEB_ROOT . '/docs/PHASE_25A_QUALITY_ONLINE.md';
        $doc = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $doc !== ''
            && str_contains($doc, '191_quality_management_platform.sql')
            && str_contains($doc, 'QualityWorkflowService')
            && str_contains($doc, 'rateb_qms_')
            && str_contains($doc, 'Enterprise Baseline')
            && str_contains($doc, 'Offline Foundation');
        $this->record('architecture doc present', $ok);
    }

    private function testOfflineReadinessDoc(): void
    {
        $doc = (string) file_get_contents(RATEB_ROOT . '/docs/PHASE_25A_QUALITY_ONLINE.md');
        $ok = str_contains($doc, 'Offline readiness')
            && str_contains($doc, '25B')
            && str_contains($doc, 'Replay-ready');
        $this->record('offline readiness matrix in docs', $ok);
    }
}
