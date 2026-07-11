<?php

declare(strict_types=1);

/**
 * Phase 20A — Enterprise Approval Workflow Platform (ONLINE) gate tests.
 *
 * Run: php tests/approval/run-approval-phase20a-tests.php
 */
final class ApprovalPhase20ATest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testBaselineUntouched();
        $this->testNoOfflineApproval();
        $this->testMigrationExists();
        $this->testDomainServicesPresent();
        $this->testDistinctFromLegacyApprovals();
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

    private function testNoOfflineApproval(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/ApprovalDomainServices.php');
        $workflow = (string) file_get_contents(RATEB_ROOT . '/app/services/ApprovalWorkflowService.php');
        $ok = !str_contains($domain, 'OfflineQueueService')
            && !str_contains($workflow, 'OfflineQueueService')
            && !str_contains($domain, 'offline.approval')
            && !is_file(RATEB_ROOT . '/offline/client/adapters/approvals-adapter.js');
        $this->record('No Offline Approval in 20A (online foundation only)', $ok);
    }

    private function testMigrationExists(): void
    {
        $path = RATEB_ROOT . '/migrations/186_approval_platform_enterprise.sql';
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $sql !== ''
            && str_contains($sql, 'rateb_eap_templates')
            && str_contains($sql, 'rateb_eap_stages')
            && str_contains($sql, 'rateb_eap_rules')
            && str_contains($sql, 'rateb_eap_chains')
            && str_contains($sql, 'rateb_eap_chain_stages')
            && str_contains($sql, 'rateb_eap_requests')
            && str_contains($sql, 'rateb_eap_actions')
            && str_contains($sql, 'rateb_eap_delegations')
            && str_contains($sql, 'rateb_eap_escalations')
            && str_contains($sql, 'rateb_eap_sla')
            && str_contains($sql, 'rateb_eap_reminders')
            && str_contains($sql, 'rateb_eap_timeline')
            && str_contains($sql, 'rateb_eap_comments')
            && str_contains($sql, 'rateb_eap_audit')
            && str_contains($sql, 'rateb_eap_notification_meta')
            && str_contains($sql, 'rateb_eap_attachment_meta')
            && str_contains($sql, 'rateb_eap_status_history')
            && str_contains($sql, 'approval.view')
            && str_contains($sql, 'approval.submit')
            && str_contains($sql, 'approval.approve')
            && str_contains($sql, 'approval.delegate')
            && str_contains($sql, 'approval.admin')
            && str_contains($sql, 'public_uuid')
            && str_contains($sql, 'deleted_at')
            && str_contains($sql, 'version')
            && !str_contains($sql, 'ALTER TABLE rateb_approval_workflows')
            && !str_contains($sql, 'ALTER TABLE rateb_approval_instances');
        $this->record('migration 186 schema + permissions', $ok);
    }

    private function testDomainServicesPresent(): void
    {
        $ok = class_exists(\Rateb\App\Services\ApprovalSupport::class)
            && class_exists(\Rateb\App\Services\ApprovalService::class)
            && class_exists(\Rateb\App\Services\ApprovalTemplateService::class)
            && class_exists(\Rateb\App\Services\ApprovalStageService::class)
            && class_exists(\Rateb\App\Services\ApprovalRuleService::class)
            && class_exists(\Rateb\App\Services\ApprovalChainService::class)
            && class_exists(\Rateb\App\Services\ApprovalRequestService::class)
            && class_exists(\Rateb\App\Services\ApprovalActionService::class)
            && class_exists(\Rateb\App\Services\ApprovalDelegationService::class)
            && class_exists(\Rateb\App\Services\ApprovalEscalationService::class)
            && class_exists(\Rateb\App\Services\ApprovalTimelineService::class)
            && class_exists(\Rateb\App\Services\ApprovalCommentService::class)
            && class_exists(\Rateb\App\Services\ApprovalAuditService::class)
            && class_exists(\Rateb\App\Services\ApprovalWorkflowService::class)
            && class_exists(\Rateb\App\Services\ApprovalNotificationMetaService::class);
        $this->record('domain services present', $ok);
    }

    private function testDistinctFromLegacyApprovals(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/ApprovalDomainServices.php');
        $workflow = (string) file_get_contents(RATEB_ROOT . '/app/services/ApprovalWorkflowService.php');
        $ok = class_exists(\Rateb\App\Models\EapRequest::class)
            && class_exists(\Rateb\App\Services\WorkflowService::class)
            && is_file(RATEB_ROOT . '/app/services/ApprovalOversightService.php')
            && !preg_match('/\bFROM\s+rateb_approval_|\bINTO\s+rateb_approval_|\bUPDATE\s+rateb_approval_/i', $domain)
            && !preg_match('/\bFROM\s+rateb_approval_|\bINTO\s+rateb_approval_|\bUPDATE\s+rateb_approval_/i', $workflow)
            && str_contains($domain, 'rateb_eap_')
            && str_contains($workflow, 'Distinct from legacy WorkflowService');
        $this->record('EAP distinct from legacy rateb_approval_* / WorkflowService', $ok);
    }

    private function testWorkflowMaps(): void
    {
        $s = \Rateb\App\Services\ApprovalWorkflowService::statuses();
        $m = \Rateb\App\Services\ApprovalWorkflowService::allowedTransitions();
        $ok = $s === ['draft', 'submitted', 'pending', 'approved', 'rejected', 'cancelled', 'archived']
            && in_array('submitted', $m['draft'], true)
            && in_array('pending', $m['submitted'], true)
            && in_array('approved', $m['pending'], true)
            && in_array('rejected', $m['pending'], true)
            && in_array('cancelled', $m['pending'], true)
            && in_array('archived', $m['approved'], true)
            && $m['archived'] === [];
        $this->record('approval workflow maps', $ok, implode(',', $s));
    }

    private function testUuidHelper(): void
    {
        $u = \Rateb\App\Services\ApprovalSupport::uuidV4();
        $ok = is_string($u) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $u);
        $this->record('UUID helper', $ok, $u);
    }

    private function testControllersAndViews(): void
    {
        $ok = class_exists(\Rateb\App\Controllers\Company\ApprovalDashboardController::class)
            && class_exists(\Rateb\App\Controllers\Company\ApprovalRequestsController::class)
            && class_exists(\Rateb\App\Controllers\Company\ApprovalPendingController::class)
            && class_exists(\Rateb\App\Controllers\Company\ApprovalTemplatesController::class)
            && class_exists(\Rateb\App\Controllers\Company\ApprovalChainsController::class)
            && class_exists(\Rateb\App\Controllers\Company\ApprovalRulesController::class)
            && class_exists(\Rateb\App\Controllers\Company\ApprovalHistoryController::class)
            && class_exists(\Rateb\App\Controllers\Company\ApprovalReportsController::class)
            && is_file(RATEB_ROOT . '/views/company/approvals/dashboard.php')
            && is_file(RATEB_ROOT . '/views/company/approvals/requests/index.php')
            && is_file(RATEB_ROOT . '/views/company/approvals/requests/show.php')
            && is_file(RATEB_ROOT . '/views/company/approvals/requests/form.php')
            && is_file(RATEB_ROOT . '/views/company/approvals/pending.php')
            && is_file(RATEB_ROOT . '/views/company/approvals/templates.php')
            && is_file(RATEB_ROOT . '/views/company/approvals/chains.php')
            && is_file(RATEB_ROOT . '/views/company/approvals/rules.php')
            && is_file(RATEB_ROOT . '/views/company/approvals/history.php')
            && is_file(RATEB_ROOT . '/views/company/approvals/reports.php');
        $this->record('controllers + views present', $ok);
    }

    private function testRoutesRegistered(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/routes/company.php');
        $ok = str_contains($routes, "approvals/requests")
            && str_contains($routes, "approvals/pending")
            && str_contains($routes, "approvals/templates")
            && str_contains($routes, "approvals/chains")
            && str_contains($routes, "approvals/rules")
            && str_contains($routes, "approvals/history")
            && str_contains($routes, "approvals/reports")
            && str_contains($routes, 'rateb_erp_mw(\'approval\'')
            && str_contains($routes, 'Phase 20A')
            && str_contains($routes, 'WorkflowService');
        $this->record('routes registered (approvals/* + legacy note)', $ok);
    }

    private function testRbacConfig(): void
    {
        $perms = require RATEB_ROOT . '/config/permissions-system.php';
        $entities = require RATEB_ROOT . '/config/entity-permissions.php';
        $labels = require RATEB_ROOT . '/config/permission-labels-en.php';
        $modules = $perms['company_modules'] ?? [];
        $implies = $perms['permission_implies'] ?? [];
        $ok = in_array('approval', $modules, true)
            && isset($implies['approval.manage'], $implies['approval.admin'])
            && isset($entities['approval'], $entities['approvals'])
            && ($entities['approval']['view'] ?? '') === 'approval.view'
            && isset($labels['approval.view'], $labels['approval.submit'], $labels['approval.approve'], $labels['approval.delegate']);
        $this->record('RBAC module + implies + labels', $ok);
    }

    private function testSidebarAndLang(): void
    {
        $nav = (string) file_get_contents(RATEB_ROOT . '/views/partials/sidebar-ops-nav.php');
        $en = (string) file_get_contents(RATEB_ROOT . '/config/lang/en.php');
        $ar = (string) file_get_contents(RATEB_ROOT . '/config/lang/ar.php');
        $ok = str_contains($nav, "'approvals'")
            && str_contains($nav, 'approvals/pending')
            && str_contains($nav, "'approval'")
            && str_contains($en, "'approval_platform' =>")
            && str_contains($ar, "'approval_platform' =>")
            && str_contains($en, "'approval_request_create' =>")
            && str_contains($ar, "'approval_request_create' =>");
        $this->record('sidebar + EN/AR translations', $ok);
    }

    private function testArchitectureDoc(): void
    {
        $ok = is_file(RATEB_ROOT . '/docs/PHASE_20A_APPROVAL_ONLINE.md');
        $this->record('architecture doc present', $ok);
    }

    private function testOfflineReadinessDoc(): void
    {
        $doc = is_file(RATEB_ROOT . '/docs/PHASE_20A_APPROVAL_ONLINE.md')
            ? (string) file_get_contents(RATEB_ROOT . '/docs/PHASE_20A_APPROVAL_ONLINE.md')
            : '';
        $ok = str_contains($doc, 'Offline readiness')
            && str_contains($doc, 'Replay')
            && str_contains($doc, 'Notification')
            && str_contains($doc, 'ONLINE ONLY');
        $this->record('offline readiness matrix in docs', $ok);
    }
}
