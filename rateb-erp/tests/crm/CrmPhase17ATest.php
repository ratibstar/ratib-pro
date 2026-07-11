<?php

declare(strict_types=1);

/**
 * Phase 17A — Enterprise CRM Platform (ONLINE) gate tests.
 *
 * Run: php tests/crm/run-crm-phase17a-tests.php
 */

final class CrmPhase17ATest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testBaselineUntouched();
        $this->testNoOfflineCrm();
        $this->testMigrationExists();
        $this->testDomainServicesPresent();
        $this->testWorkflowTransitionMap();
        $this->testUuidHelper();
        $this->testControllersAndViews();
        $this->testRoutesRegistered();
        $this->testRbacConfig();
        $this->testSidebarAndLang();
        $this->testDistinctFromCmsLeads();
        $this->testArchitectureDoc();

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

    private function testNoOfflineCrm(): void
    {
        // Phase 17A asserted no offline CRM; Phase 17B adds it — this online gate no longer applies.
        // Kept as a soft check that ONLINE domain services remain free of offline queue coupling.
        $lead = (string) file_get_contents(RATEB_ROOT . '/app/services/CrmDomainServices.php');
        $ok = !str_contains($lead, 'OfflineQueueService')
            && !str_contains($lead, 'offline.crm');
        $this->record('CRM Online services free of offline queue coupling', $ok);
    }

    private function testMigrationExists(): void
    {
        $path = RATEB_ROOT . '/migrations/183_crm_platform_enterprise.sql';
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $sql !== ''
            && str_contains($sql, 'rateb_crm_leads')
            && str_contains($sql, 'rateb_crm_opportunities')
            && str_contains($sql, 'rateb_crm_pipelines')
            && str_contains($sql, 'rateb_crm_pipeline_stages')
            && str_contains($sql, 'rateb_crm_contacts')
            && str_contains($sql, 'rateb_crm_companies')
            && str_contains($sql, 'rateb_crm_meetings')
            && str_contains($sql, 'rateb_crm_calls')
            && str_contains($sql, 'rateb_crm_tasks')
            && str_contains($sql, 'rateb_crm_campaigns')
            && str_contains($sql, 'rateb_crm_timeline')
            && str_contains($sql, 'rateb_crm_assignments')
            && str_contains($sql, 'rateb_crm_tags')
            && str_contains($sql, 'rateb_crm_lead_sources')
            && str_contains($sql, 'crm.view')
            && str_contains($sql, 'crm.pipeline')
            && str_contains($sql, 'crm.activities')
            && str_contains($sql, 'crm.campaign')
            && str_contains($sql, 'crm.admin')
            && str_contains($sql, 'public_uuid')
            && str_contains($sql, 'deleted_at');
        $this->record('migration 183 schema + permissions', $ok);
    }

    private function testDomainServicesPresent(): void
    {
        $ok = class_exists(\Rateb\App\Services\CrmSupport::class)
            && class_exists(\Rateb\App\Services\LeadService::class)
            && class_exists(\Rateb\App\Services\OpportunityService::class)
            && class_exists(\Rateb\App\Services\PipelineService::class)
            && class_exists(\Rateb\App\Services\MeetingService::class)
            && class_exists(\Rateb\App\Services\TaskService::class)
            && class_exists(\Rateb\App\Services\CallService::class)
            && class_exists(\Rateb\App\Services\CampaignService::class)
            && class_exists(\Rateb\App\Services\CrmTimelineService::class)
            && class_exists(\Rateb\App\Services\ActivityService::class)
            && class_exists(\Rateb\App\Services\CrmAssignmentService::class)
            && class_exists(\Rateb\App\Services\CrmWorkflowService::class)
            && class_exists(\Rateb\App\Services\ContactService::class)
            && class_exists(\Rateb\App\Services\CrmCompanyService::class)
            && class_exists(\Rateb\App\Services\CrmNoteService::class);
        $this->record('domain services present', $ok);
    }

    private function testWorkflowTransitionMap(): void
    {
        $statuses = \Rateb\App\Services\CrmWorkflowService::statuses();
        $map = \Rateb\App\Services\CrmWorkflowService::allowedTransitions();
        $ok = $statuses === ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost', 'archived']
            && isset($map['new'], $map['won'], $map['archived'])
            && in_array('contacted', $map['new'], true)
            && in_array('won', $map['qualified'], true)
            && $map['archived'] === [];
        $this->record('CRM workflow statuses + transitions', $ok, implode(',', $statuses));
    }

    private function testUuidHelper(): void
    {
        $u = \Rateb\App\Services\CrmSupport::uuidV4();
        $ok = is_string($u) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $u);
        $this->record('UUID helper', $ok, $u);
    }

    private function testControllersAndViews(): void
    {
        $ok = class_exists(\Rateb\App\Controllers\Company\CrmDashboardController::class)
            && class_exists(\Rateb\App\Controllers\Company\CrmLeadsController::class)
            && class_exists(\Rateb\App\Controllers\Company\CrmPipelineController::class)
            && is_file(RATEB_ROOT . '/views/company/crm/dashboard.php')
            && is_file(RATEB_ROOT . '/views/company/crm/leads/index.php')
            && is_file(RATEB_ROOT . '/views/company/crm/leads/board.php')
            && is_file(RATEB_ROOT . '/views/company/crm/leads/show.php')
            && is_file(RATEB_ROOT . '/views/company/crm/pipeline/index.php')
            && is_file(RATEB_ROOT . '/views/company/crm/meetings/index.php')
            && is_file(RATEB_ROOT . '/views/company/crm/tasks/index.php')
            && is_file(RATEB_ROOT . '/views/company/crm/campaigns/index.php')
            && is_file(RATEB_ROOT . '/views/company/crm/customer-profile.php');
        $this->record('controllers + views present', $ok);
    }

    private function testRoutesRegistered(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/routes/company.php');
        $ok = str_contains($routes, "crm/leads")
            && str_contains($routes, "crm/pipeline")
            && str_contains($routes, "crm/opportunities")
            && str_contains($routes, "crm/meetings")
            && str_contains($routes, "crm/tasks")
            && str_contains($routes, "crm/campaigns")
            && str_contains($routes, 'CrmLeadsController')
            && str_contains($routes, "Phase 17A");
        $this->record('routes registered', $ok);
    }

    private function testRbacConfig(): void
    {
        $perms = require RATEB_ROOT . '/config/permissions-system.php';
        $entities = require RATEB_ROOT . '/config/entity-permissions.php';
        $labels = require RATEB_ROOT . '/config/permission-labels-en.php';
        $modules = $perms['company_modules'] ?? [];
        $implies = $perms['permission_implies'] ?? [];
        $ok = in_array('crm', $modules, true)
            && isset($implies['crm.manage'], $implies['crm.admin'])
            && isset($entities['crm'])
            && isset($labels['crm.view'], $labels['crm.pipeline'], $labels['crm.activities'], $labels['crm.campaign']);
        $this->record('RBAC module + implies + labels', $ok);
    }

    private function testSidebarAndLang(): void
    {
        $nav = (string) file_get_contents(RATEB_ROOT . '/views/partials/sidebar-ops-nav.php');
        $en = (string) file_get_contents(RATEB_ROOT . '/config/lang/en.php');
        $ar = (string) file_get_contents(RATEB_ROOT . '/config/lang/ar.php');
        $ok = str_contains($nav, "'crm'")
            && str_contains($nav, 'crm/leads')
            && str_contains($en, "'crm' =>")
            && str_contains($ar, "'crm' =>")
            && str_contains($en, 'crm_lead_board')
            && str_contains($ar, 'crm_lead_board');
        $this->record('sidebar + EN/AR translations', $ok);
    }

    private function testDistinctFromCmsLeads(): void
    {
        $mig = (string) file_get_contents(RATEB_ROOT . '/migrations/183_crm_platform_enterprise.sql');
        $leadSvc = (string) file_get_contents(RATEB_ROOT . '/app/services/CrmDomainServices.php');
        $ok = str_contains($mig, 'rateb_crm_leads')
            && str_contains($mig, 'Distinct from CMS')
            && !preg_match('/CREATE TABLE.*rateb_cms_leads/i', $mig)
            && str_contains($leadSvc, 'rateb_crm_leads')
            && !str_contains($leadSvc, 'rateb_cms_leads');
        $this->record('CRM leads distinct from CMS marketing leads', $ok);
    }

    private function testArchitectureDoc(): void
    {
        $path = RATEB_ROOT . '/docs/PHASE_17A_CRM_ONLINE.md';
        $doc = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $doc !== ''
            && str_contains($doc, 'Phase 17A')
            && str_contains($doc, 'ONLINE')
            && str_contains($doc, '17B')
            && str_contains($doc, 'CrmWorkflowService');
        $this->record('architecture doc present', $ok);
    }
}
