<?php

declare(strict_types=1);

/**
 * Phase 18B — Projects Offline (Tier 1 drafts) tests.
 *
 * Run: php offline/tests/run-projects-offline-tests.php
 */

use Rateb\App\Offline\OfflineModule;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;
use Rateb\App\Offline\Services\ProjectOfflineMasterDataDirectoryService;
use Rateb\App\Offline\Services\ProjectOfflineReplayService;
use Rateb\App\Offline\Services\ProjectOfflineTenantGuard;

final class ProjectsOfflinePhase18bTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testFlagsDefaultOff();
        $this->testRequiresMaster();
        $this->testSubflagsRequireProjects();
        $this->testEntityManifestHasProjects();
        $this->testModulesRegistryActiveProjects();
        $this->testDeferredActionsCoverRequirements();
        $this->testClientAdapterSource();
        $this->testSdkBundleHasProjectsAdapter();
        $this->testReplayUsesExistingDomainOnly();
        $this->testNoDeletePaymentsApprovalsEmailSmsAttachmentsGov();
        $this->testConflictStatusChanged();
        $this->testConflictServerNewerStillWins();
        $this->testConflictAcceptWhenStatusMatches();
        $this->testReplaySkipsWhenFlagOff();
        $this->testReplayEngineDelegatesWhenFlagOn();
        $this->testQueueRejectsWhenFlagOff();
        $this->testQueueRejectsTasksWithoutSubflag();
        $this->testQueueAliases();
        $this->testPayloadSanitizerKeepsProjectsModule();
        $this->testAuthzAllowsProjectsAbility();
        $this->testTenantGuardSource();
        $this->testMasterDataEntitiesRegistered();
        $this->testOpsAllowlistProjects();
        $this->testOpsFormsProjectsHooks();
        $this->testBackgroundReportsProjectsFlag();
        $this->testFoundationUntouchedMarkers();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_PROJECTS',
            'RATEB_OFFLINE_PROJECTS_TASKS',
            'RATEB_OFFLINE_PROJECTS_WORKFLOW',
            'RATEB_OFFLINE_PROJECTS_TIMESHEETS',
            'RATEB_OFFLINE_PROJECTS_MASTERDATA',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
    }

    private function enableBase(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_PROJECTS=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_PROJECTS'] = '1';
    }

    private function enableAll(): void
    {
        $this->enableBase();
        foreach ([
            'RATEB_OFFLINE_PROJECTS_TASKS',
            'RATEB_OFFLINE_PROJECTS_WORKFLOW',
            'RATEB_OFFLINE_PROJECTS_TIMESHEETS',
            'RATEB_OFFLINE_PROJECTS_MASTERDATA',
        ] as $k) {
            putenv($k . '=1');
            $_ENV[$k] = '1';
        }
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testFlagsDefaultOff(): void
    {
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.projects') === false
            && $svc->isProjectsEnabled() === false
            && $svc->isProjectsTasksEnabled() === false
            && $svc->isProjectsWorkflowEnabled() === false
            && $svc->isProjectsTimesheetsEnabled() === false
            && $svc->isProjectsMasterDataEnabled() === false;
        $this->record('Projects flags default OFF', $ok);
    }

    private function testRequiresMaster(): void
    {
        putenv('RATEB_OFFLINE_PROJECTS=1');
        $_ENV['RATEB_OFFLINE_PROJECTS'] = '1';
        putenv('RATEB_OFFLINE_ENABLED');
        unset($_ENV['RATEB_OFFLINE_ENABLED']);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.projects') === true && $svc->isProjectsEnabled() === false;
        $this->record('Projects requires master flag', $ok);
        $this->clearEnv();
    }

    private function testSubflagsRequireProjects(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_PROJECTS_TASKS=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_PROJECTS_TASKS'] = '1';
        putenv('RATEB_OFFLINE_PROJECTS');
        unset($_ENV['RATEB_OFFLINE_PROJECTS']);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isProjectsTasksEnabled() === false;
        $this->record('tasks subflag requires projects', $ok);
        $this->clearEnv();
    }

    private function testEntityManifestHasProjects(): void
    {
        $cfg = require RATEB_ROOT . '/offline/config/entity-manifest.php';
        $ok = isset(
            $cfg['projects_project_create'],
            $cfg['projects_workflow_transition'],
            $cfg['project_tag_directory']
        );
        $this->record('entity manifest has Projects ops + directories', $ok);
    }

    private function testModulesRegistryActiveProjects(): void
    {
        $cfg = require RATEB_ROOT . '/offline/config/modules.php';
        $active = $cfg['active_modules'] ?? [];
        $ops = $cfg['operations'] ?? [];
        $ok = in_array('projects', $active, true)
            && isset($ops['projects.project.create'], $ops['projects.workflow.transition'], $ops['projects.task.create']);
        $this->record('modules registry activates Projects ops', $ok);
    }

    private function testDeferredActionsCoverRequirements(): void
    {
        $a = ProjectOfflineReplayService::deferredActions();
        $ok = in_array('project.create', $a, true)
            && in_array('project.update', $a, true)
            && in_array('task.create', $a, true)
            && in_array('task.update', $a, true)
            && in_array('workflow.transition', $a, true)
            && in_array('milestone.create', $a, true)
            && in_array('phase.create', $a, true)
            && in_array('comment.create', $a, true)
            && in_array('assignment.create', $a, true)
            && in_array('timesheet.create', $a, true)
            && in_array('issue.create', $a, true)
            && in_array('risk.create', $a, true)
            && in_array('budget.create', $a, true)
            && in_array('activity.create', $a, true);
        $this->record('deferred actions cover Tier-1 Projects', $ok, implode(',', $a));
    }

    private function testClientAdapterSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/projects-adapter.js');
        $ok = str_contains($src, 'project.create')
            && str_contains($src, 'workflow.transition')
            && str_contains($src, "module: 'projects'")
            && str_contains($src, 'enqueue')
            && str_contains($src, 'draft:')
            && str_contains($src, 'retry:')
            && str_contains($src, 'status:')
            && str_contains($src, 'sync:')
            && !preg_match('/enqueue\([\'"][^\'"]*delete/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*payment/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*email/i', $src);
        $this->record('client adapter queues Tier-1 drafts only', $ok);
    }

    private function testSdkBundleHasProjectsAdapter(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($src, 'RatebOfflineProjectsAdapter')
            && str_contains($src, 'isProjectsEnabled')
            && str_contains($src, 'projects: function')
            && str_contains($src, "'offline.projects'")
            && str_contains($src, '14.2.0');
        $this->record('SDK bundle contains Projects adapter', $ok);
    }

    private function testReplayUsesExistingDomainOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ProjectOfflineReplayService.php');
        $ok = str_contains($src, 'ProjectService')
            && str_contains($src, 'ProjectWorkflowService')
            && str_contains($src, 'ProjectTaskService')
            && str_contains($src, 'ProjectMilestoneService')
            && str_contains($src, 'ProjectTimesheetService')
            && str_contains($src, 'ProjectAssignmentService')
            && !str_contains($src, 'INSERT INTO rateb_projects');
        $this->record('replay uses existing Projects domain only', $ok);
    }

    private function testNoDeletePaymentsApprovalsEmailSmsAttachmentsGov(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ProjectOfflineReplayService.php');
        $ok = !preg_match('/softDelete|->delete\(/', $src)
            && !preg_match('/PaymentService|approve|sendEmail|sendSms|move_uploaded_file|Zatca|Government/', $src);
        $this->record('replay excludes delete/payments/approvals/email/sms/attachments/gov', $ok);
    }

    private function testConflictStatusChanged(): void
    {
        $r = (new OfflineConflictResolverService())->resolveProjects(
            ['version' => 2, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'active']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'status_changed';
        $this->record('conflict status_changed', $ok, json_encode($r));
    }

    private function testConflictServerNewerStillWins(): void
    {
        $r = (new OfflineConflictResolverService())->resolveProjects(
            ['version' => 1, 'expected_status' => 'draft'],
            ['version' => 5, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'server_newer';
        $this->record('conflict server_newer still wins', $ok, json_encode($r));
    }

    private function testConflictAcceptWhenStatusMatches(): void
    {
        $r = (new OfflineConflictResolverService())->resolveProjects(
            ['version' => 3, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'accept_client';
        $this->record('conflict accept when status matches', $ok, json_encode($r));
    }

    private function testReplaySkipsWhenFlagOff(): void
    {
        $this->clearEnv();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'projects',
            'action' => 'project.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'project.create', 'payload' => ['name' => 'x']]),
        ]);
        $ok = ($out['status'] ?? '') === 'skipped'
            && ($out['error'] ?? '') === 'projects_offline_disabled';
        $this->record('replay skips Projects when flag OFF', $ok, json_encode($out));
    }

    private function testReplayEngineDelegatesWhenFlagOn(): void
    {
        $this->enableAll();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'projects',
            'action' => 'project.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'project.create', 'payload' => []]),
        ]);
        $ok = ($out['status'] ?? '') !== 'skipped' || ($out['error'] ?? '') !== 'projects_offline_disabled';
        $ok = $ok && in_array(($out['status'] ?? ''), ['failed', 'synced', 'conflict'], true);
        $this->record('replay engine delegates when flag ON', $ok, json_encode($out));
        $this->clearEnv();
    }

    private function testQueueRejectsWhenFlagOff(): void
    {
        $this->clearEnv();
        putenv('RATEB_OFFLINE_ENABLED=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $result = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'prj-off-' . bin2hex(random_bytes(3)),
            'module' => 'projects',
            'action' => 'project.create',
            'payload' => ['name' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($result['rejected'] ?? 0) >= 1 && (int) ($result['accepted'] ?? 0) === 0;
        $this->record('queue rejects Projects without flag', $ok, json_encode($result));
        $this->clearEnv();
    }

    private function testQueueRejectsTasksWithoutSubflag(): void
    {
        $this->enableBase();
        putenv('RATEB_OFFLINE_PROJECTS_TASKS');
        unset($_ENV['RATEB_OFFLINE_PROJECTS_TASKS']);
        $result = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'prj-sub-' . bin2hex(random_bytes(3)),
            'module' => 'projects',
            'action' => 'task.create',
            'payload' => ['title' => 'x', 'project_id' => 1],
        ]], ['company_id' => 1]);
        $ok = (int) ($result['rejected'] ?? 0) >= 1;
        $this->record('queue rejects task.create without tasks subflag', $ok, json_encode($result));
        $this->clearEnv();
    }

    private function testQueueAliases(): void
    {
        $this->enableAll();
        $ref = new \ReflectionClass(OfflineQueueService::class);
        $m = $ref->getMethod('normalizeProjectsAction');
        $m->setAccessible(true);
        $svc = new OfflineQueueService();
        $ok = $m->invoke($svc, 'create_project') === 'project.create'
            && $m->invoke($svc, 'task.update') === 'task.update'
            && $m->invoke($svc, 'create_timesheet') === 'timesheet.create'
            && $m->invoke($svc, 'bogus') === '';
        $this->record('queue Projects action aliases', $ok);
        $this->clearEnv();
    }

    private function testPayloadSanitizerKeepsProjectsModule(): void
    {
        $n = (new OfflinePayloadSanitizer())->normalize([
            'module' => 'projects',
            'action' => 'comment.create',
            'url' => 'http://evil',
            'payload' => ['body' => 't'],
        ]);
        $ok = ($n['module'] ?? '') === 'projects' && !isset($n['url']);
        $this->record('payload sanitizer keeps Projects module', $ok, json_encode($n));
    }

    private function testAuthzAllowsProjectsAbility(): void
    {
        \Rateb\App\Core\TenantContext::setCompanyId(1);
        \Rateb\App\Core\TenantContext::setApiModules(['projects']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === true;
        $this->record('authz allows Projects ability token', $ok);
        \Rateb\App\Core\TenantContext::setApiModules(null);
        \Rateb\App\Core\TenantContext::setCompanyId(null);
    }

    private function testTenantGuardSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ProjectOfflineTenantGuard.php');
        $ok = str_contains($src, 'assertProject')
            && str_contains($src, 'assertTask')
            && str_contains($src, 'branch_mismatch')
            && class_exists(ProjectOfflineTenantGuard::class);
        $this->record('tenant guard asserts project/task ownership', $ok);
    }

    private function testMasterDataEntitiesRegistered(): void
    {
        $names = ProjectOfflineMasterDataDirectoryService::entityNames();
        $cfg = require RATEB_ROOT . '/offline/config/master-data-entities.php';
        $entities = $cfg['entities'] ?? [];
        $ok = in_array('project_tag_directory', $names, true)
            && in_array('task_status_directory', $names, true)
            && isset($entities['project_role_directory'], $entities['risk_level_directory']);
        $this->record('master-data Projects directories registered', $ok);
    }

    private function testOpsAllowlistProjects(): void
    {
        $cfg = OfflineModule::opsPageAllowlist();
        $paths = $cfg['paths'] ?? [];
        $ok = in_array('projects', $paths, true)
            && in_array('projects/tasks', $paths, true)
            && in_array('projects/tasks/kanban', $paths, true)
            && in_array('projects/tasks/gantt', $paths, true)
            && in_array('projects/milestones', $paths, true)
            && in_array('projects/timesheets', $paths, true);
        $this->record('ops allowlist includes Projects browse paths', $ok);
    }

    private function testOpsFormsProjectsHooks(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/ops-forms-adapter.js');
        $ok = str_contains($src, 'project.create')
            && str_contains($src, 'offline.projects')
            && str_contains($src, 'RatebOfflineProjectsAdapter');
        $this->record('ops forms Projects hooks present', $ok);
    }

    private function testBackgroundReportsProjectsFlag(): void
    {
        $this->clearEnv();
        $stats = (new OfflineBackgroundSync())->process(1, 1);
        $ok = array_key_exists('projects_enabled', $stats)
            && ($stats['projects_enabled'] ?? true) === false;
        $this->record('background reports projects_enabled', $ok, json_encode($stats));
    }

    private function testFoundationUntouchedMarkers(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = preg_match('/DB_VERSION\s*=\s*2/', $schema)
            && str_contains($sdk, "version: '14.2.0'");
        $this->record('foundation markers untouched (IDB v2, SDK 14.2.0)', $ok);
    }
}
