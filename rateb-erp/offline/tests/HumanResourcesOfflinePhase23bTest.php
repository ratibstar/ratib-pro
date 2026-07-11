<?php

declare(strict_types=1);

/**
 * Phase 23B — Enterprise Human Resources Offline (Tier 1 drafts) tests.
 *
 * Run: php offline/tests/run-humanresources-offline-tests.php
 */

use Rateb\App\Offline\OfflineModule;
use Rateb\App\Offline\Services\HumanResourcesOfflineMasterDataDirectoryService;
use Rateb\App\Offline\Services\HumanResourcesOfflineReplayService;
use Rateb\App\Offline\Services\HumanResourcesOfflineTenantGuard;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;

final class HumanResourcesOfflinePhase23bTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testFlagsDefaultOff();
        $this->testRequiresMaster();
        $this->testSubflagsRequireParent();
        $this->testEntityManifestHasHrm();
        $this->testModulesRegistryHasEnterpriseOps();
        $this->testDeferredActionsCoverRequirements();
        $this->testClientAdapterSource();
        $this->testSdkBundleHasHrEnterprise();
        $this->testReplayUsesPhase23aOnly();
        $this->testNoDeleteAttendancePayrollApprovals();
        $this->testConflictStatusChanged();
        $this->testConflictServerNewerStillWins();
        $this->testConflictAcceptWhenStatusMatches();
        $this->testReplaySkipsWhenFlagOff();
        $this->testReplayEngineDelegatesWhenFlagOn();
        $this->testQueueRejectsWhenFlagOff();
        $this->testQueueRejectsEmployeeWithoutSubflag();
        $this->testQueueAliases();
        $this->testPayloadSanitizerKeepsModule();
        $this->testAuthzAllowsAbility();
        $this->testTenantGuardSource();
        $this->testMasterDataEntitiesRegistered();
        $this->testOpsAllowlistHrm();
        $this->testOpsFormsHrmHooks();
        $this->testBackgroundReportsFlag();
        $this->testFoundationUntouchedMarkers();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_HR',
            'RATEB_OFFLINE_HR_EMPLOYEE',
            'RATEB_OFFLINE_HR_TRAINING',
            'RATEB_OFFLINE_HR_PERFORMANCE',
            'RATEB_OFFLINE_HR_WORKFLOW',
            'RATEB_OFFLINE_HR_MASTERDATA',
            'RATEB_OFFLINE_HR_ATTENDANCE',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
    }

    private function enableBase(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_HR=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_HR'] = '1';
    }

    private function enableAll(): void
    {
        $this->enableBase();
        foreach ([
            'RATEB_OFFLINE_HR_EMPLOYEE',
            'RATEB_OFFLINE_HR_TRAINING',
            'RATEB_OFFLINE_HR_PERFORMANCE',
            'RATEB_OFFLINE_HR_WORKFLOW',
            'RATEB_OFFLINE_HR_MASTERDATA',
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
        $ok = $svc->enabled('offline.hr') === false
            && $svc->isHumanResourcesEnabled() === false
            && $svc->isHumanResourcesEmployeeEnabled() === false
            && $svc->isHumanResourcesTrainingEnabled() === false
            && $svc->isHumanResourcesPerformanceEnabled() === false
            && $svc->isHumanResourcesWorkflowEnabled() === false
            && $svc->isHumanResourcesMasterDataEnabled() === false;
        $this->record('HRMS flags default OFF', $ok);
    }

    private function testRequiresMaster(): void
    {
        putenv('RATEB_OFFLINE_HR=1');
        $_ENV['RATEB_OFFLINE_HR'] = '1';
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.hr') === true
            && $svc->isHumanResourcesEnabled() === false;
        $this->record('HRMS requires offline.enabled', $ok);
        $this->clearEnv();
    }

    private function testSubflagsRequireParent(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_HR_EMPLOYEE=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_HR_EMPLOYEE'] = '1';
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isHumanResourcesEmployeeEnabled() === false;
        $this->record('Subflags require offline.hr', $ok);
        $this->clearEnv();
    }

    private function testEntityManifestHasHrm(): void
    {
        $m = require RATEB_ROOT . '/offline/config/entity-manifest.php';
        $ok = isset($m['hrm_employee_create'], $m['hrm_workflow_transition'], $m['hrm_department_directory'])
            && ($m['hrm_employee_create']['module'] ?? '') === 'hr'
            && ($m['hrm_employee_create']['replay'] ?? '') === 'delegate_human_resources';
        $this->record('entity manifest has HRMS', $ok);
    }

    private function testModulesRegistryHasEnterpriseOps(): void
    {
        $m = require RATEB_ROOT . '/offline/config/modules.php';
        $ok = in_array('hr', $m['active_modules'] ?? [], true)
            && isset($m['operations']['hr.employee.create'])
            && isset($m['operations']['hr.attendance'])
            && isset($m['operations']['hr.workflow.transition']);
        $this->record('modules registry HR enterprise (+ Phase 4 attendance)', $ok);
    }

    private function testDeferredActionsCoverRequirements(): void
    {
        $a = HumanResourcesOfflineReplayService::deferredActions();
        $need = [
            'employee.create', 'employee.update',
            'department.create', 'position.create', 'organization.create',
            'training.create', 'performance.create',
            'goal.create', 'competency.create',
            'promotion.create', 'transfer.create',
            'assignment.create', 'workflow.transition',
            'comment.create', 'note.create',
        ];
        $ok = true;
        foreach ($need as $n) {
            if (!in_array($n, $a, true)) {
                $ok = false;
                break;
            }
        }
        $ok = $ok
            && !in_array('delete', $a, true)
            && !in_array('attendance.create', $a, true)
            && !in_array('payment.create', $a, true);
        $this->record('deferred actions cover Tier-1 only', $ok, implode(',', array_slice($a, 0, 8)) . '...');
    }

    private function testClientAdapterSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/hr-adapter.js');
        $ok = str_contains($src, "module: 'hr'")
            && str_contains($src, 'enqueueEmployeeCreate')
            && str_contains($src, 'enqueueTrainingCreate')
            && str_contains($src, 'enqueueWorkflowTransition')
            && str_contains($src, 'offline.hr.attendance')
            && str_contains($src, 'isEnterpriseActive')
            && !preg_match('/enqueue\([\'"]delete/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*payroll/i', $src);
        $this->record('client adapter queues Tier-1 drafts (+ Phase 4 preserved)', $ok);
    }

    private function testSdkBundleHasHrEnterprise(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($src, 'RatebOfflineHrAdapter')
            && str_contains($src, 'isHumanResourcesEnabled')
            && str_contains($src, 'enqueueEmployeeCreate')
            && str_contains($src, "'offline.hr'")
            && str_contains($src, '14.2.0');
        $this->record('SDK bundle contains HR enterprise adapter', $ok);
    }

    private function testReplayUsesPhase23aOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HumanResourcesOfflineReplayService.php');
        $ok = str_contains($src, 'EmployeeProfileService')
            && str_contains($src, 'HumanResourcesWorkflowService')
            && str_contains($src, 'TrainingService')
            && str_contains($src, 'PerformanceReviewService')
            && str_contains($src, 'HrmAssignmentService')
            && !str_contains($src, 'INSERT INTO rateb_hrm_')
            && !preg_match('/new\s+\\\\?AssignmentService\b/', $src)
            && !str_contains($src, 'AttendanceRecord')
            && !str_contains($src, 'Payroll');
        $this->record('replay uses Phase 23A domain only', $ok);
    }

    private function testNoDeleteAttendancePayrollApprovals(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HumanResourcesOfflineReplayService.php');
        $ok = !preg_match('/softDelete|->delete\(/', $src)
            && !preg_match('/PaymentService|NotificationService|sendEmail|sendSms|move_uploaded_file|Zatca|Government/', $src)
            && !preg_match('/ApprovalRequestService|AttendanceRecord|LeaveRequest|Payroll/', $src);
        $this->record('replay excludes delete/attendance/leave/payroll/approvals/email/sms/gov/binary', $ok);
    }

    private function testConflictStatusChanged(): void
    {
        $r = (new OfflineConflictResolverService())->resolveHumanResources(
            ['version' => 2, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'active']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'status_changed';
        $this->record('conflict status_changed', $ok, json_encode($r));
    }

    private function testConflictServerNewerStillWins(): void
    {
        $r = (new OfflineConflictResolverService())->resolveHumanResources(
            ['version' => 1, 'expected_status' => 'draft'],
            ['version' => 5, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'server_newer';
        $this->record('conflict server_newer still wins', $ok, json_encode($r));
    }

    private function testConflictAcceptWhenStatusMatches(): void
    {
        $r = (new OfflineConflictResolverService())->resolveHumanResources(
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
            'module' => 'hr',
            'action' => 'employee.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'employee.create', 'payload' => ['first_name' => 'x']]),
        ]);
        $ok = ($out['status'] ?? '') === 'skipped'
            && ($out['error'] ?? '') === 'human_resources_offline_disabled';
        $this->record('replay skips HRMS when flag OFF', $ok, json_encode($out));
    }

    private function testReplayEngineDelegatesWhenFlagOn(): void
    {
        $this->enableAll();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'hr',
            'action' => 'employee.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'employee.create', 'payload' => []]),
        ]);
        $ok = ($out['status'] ?? '') !== 'skipped'
            || ($out['error'] ?? '') !== 'human_resources_offline_disabled';
        $ok = $ok && in_array(($out['status'] ?? ''), ['failed', 'synced', 'conflict'], true);
        $this->record('replay engine delegates when flag ON', $ok, json_encode($out));
        $this->clearEnv();
    }

    private function testQueueRejectsWhenFlagOff(): void
    {
        $this->clearEnv();
        putenv('RATEB_OFFLINE_ENABLED=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $res = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'hrm-off-' . bin2hex(random_bytes(3)),
            'module' => 'hr',
            'action' => 'employee.create',
            'payload' => ['first_name' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($res['rejected'] ?? 0) >= 1 && (int) ($res['accepted'] ?? 0) === 0;
        $this->record('queue rejects HRMS when flag OFF', $ok, json_encode($res));
        $this->clearEnv();
    }

    private function testQueueRejectsEmployeeWithoutSubflag(): void
    {
        $this->enableBase();
        putenv('RATEB_OFFLINE_HR_EMPLOYEE');
        unset($_ENV['RATEB_OFFLINE_HR_EMPLOYEE']);
        $res = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'hrm-sub-' . bin2hex(random_bytes(3)),
            'module' => 'hr',
            'action' => 'employee.create',
            'payload' => ['first_name' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($res['rejected'] ?? 0) >= 1;
        $this->record('queue rejects employee without subflag', $ok, json_encode($res));
        $this->clearEnv();
    }

    private function testQueueAliases(): void
    {
        $this->enableAll();
        $ref = new \ReflectionClass(OfflineQueueService::class);
        $m = $ref->getMethod('normalizeHumanResourcesAction');
        $m->setAccessible(true);
        $svc = new OfflineQueueService();
        $ok = $m->invoke($svc, 'create_employee') === 'employee.create'
            && $m->invoke($svc, 'hr.workflow.transition') === 'workflow.transition'
            && $m->invoke($svc, 'create_training') === 'training.create'
            && $m->invoke($svc, 'bogus') === '';
        $this->record('queue aliases normalize', $ok);
        $this->clearEnv();
    }

    private function testPayloadSanitizerKeepsModule(): void
    {
        $n = (new OfflinePayloadSanitizer())->normalize([
            'module' => 'hr',
            'action' => 'employee.create',
            'url' => 'http://evil',
            'payload' => ['first_name' => 't'],
        ]);
        $ok = ($n['module'] ?? '') === 'hr' && !isset($n['url']);
        $this->record('payload sanitizer keeps hr module', $ok, json_encode($n));
    }

    private function testAuthzAllowsAbility(): void
    {
        \Rateb\App\Core\TenantContext::setCompanyId(1);
        \Rateb\App\Core\TenantContext::setApiModules(['hr']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === true;
        $this->record('authz allows hr ability', $ok);
        \Rateb\App\Core\TenantContext::setApiModules(null);
        \Rateb\App\Core\TenantContext::setCompanyId(null);
    }

    private function testTenantGuardSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HumanResourcesOfflineTenantGuard.php');
        $ok = str_contains($src, 'assertProfile')
            && str_contains($src, 'assertDepartment')
            && str_contains($src, 'assertTraining')
            && str_contains($src, 'profileExistsForKey')
            && str_contains($src, 'branch_mismatch')
            && class_exists(HumanResourcesOfflineTenantGuard::class);
        $this->record('tenant guard source', $ok);
    }

    private function testMasterDataEntitiesRegistered(): void
    {
        $names = HumanResourcesOfflineMasterDataDirectoryService::entityNames();
        $cfg = require RATEB_ROOT . '/offline/config/master-data-entities.php';
        $entities = $cfg['entities'] ?? [];
        $ok = in_array('hrm_department_directory', $names, true)
            && in_array('hrm_position_directory', $names, true)
            && isset($entities['hrm_department_directory']);
        $this->record('master-data entities registered', $ok, implode(',', $names));
    }

    private function testOpsAllowlistHrm(): void
    {
        $cfg = OfflineModule::opsPageAllowlist();
        $paths = $cfg['paths'] ?? [];
        $ok = in_array('hrm', $paths, true)
            && in_array('hrm/employees', $paths, true)
            && in_array('hrm/training', $paths, true)
            && in_array('hrm/performance', $paths, true);
        $this->record('ops allowlist HRMS', $ok);
    }

    private function testOpsFormsHrmHooks(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/ops-forms-adapter.js');
        $ok = str_contains($src, "module: 'hr'")
            && str_contains($src, 'employee.create')
            && str_contains($src, 'offline.hr')
            && str_contains($src, 'enqueueEmployeeCreate')
            && str_contains($src, 'RatebOfflineHrAdapter');
        $this->record('ops forms HRMS hooks', $ok);
    }

    private function testBackgroundReportsFlag(): void
    {
        $this->clearEnv();
        $stats = (new OfflineBackgroundSync())->process(1, 1);
        $ok = array_key_exists('hr_enterprise_enabled', $stats)
            && ($stats['hr_enterprise_enabled'] ?? true) === false;
        $this->record('background reports hr_enterprise_enabled', $ok, json_encode($stats));
    }

    private function testFoundationUntouchedMarkers(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = preg_match('/DB_VERSION\s*=\s*2/', $schema)
            && str_contains($sdk, "version: '14.2.0'");
        $this->record('Foundation markers intact (DB_VERSION=2, SDK 14.2.0)', $ok);
    }
}
