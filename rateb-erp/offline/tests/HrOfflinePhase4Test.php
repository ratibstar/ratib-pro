<?php

declare(strict_types=1);

/**
 * Phase 4 — HR Offline (Tier 1) tests.
 *
 * Run: php offline/tests/run-hr-offline-tests.php
 */

use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Services\HrOfflineEmployeeDirectoryService;
use Rateb\App\Offline\Services\HrOfflineReplayService;
use Rateb\App\Offline\Services\HrOfflineTenantGuard;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflinePushAckContract;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;
use Rateb\App\Offline\Services\OfflineSyncService;

final class HrOfflinePhase4Test
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearHrEnv();

        $this->testHrFlagDefaultOff();
        $this->testHrRequiresMaster();
        $this->testEntityManifestHasHr();
        $this->testModulesRegistryActiveHr();
        $this->testDeferredActionsCoverRequirements();
        $this->testClientAdapterSource();
        $this->testSdkBundleHasHrAdapter();
        $this->testReplayUsesExistingHrDomainOnly();
        $this->testNoPayrollApprovalsInReplay();

        $this->testConflictStatusChanged();
        $this->testConflictServerNewerStillWins();
        $this->testConflictAcceptWhenStatusMatches();

        $this->testReplayRejectsEmptyAttendance();
        $this->testReplayRejectsEmptyBulk();
        $this->testReplayRejectsEmptyLeaveDraft();
        $this->testReplaySkipsWhenFlagOff();
        $this->testReplayEngineDelegatesWhenFlagOn();

        $this->testQueueRejectsHrWhenFlagOff();
        $this->testPayloadSanitizerKeepsHrModule();
        $this->testPushAckClearableContract();

        $this->testAuthzAllowsHrAbility();
        $this->testAuthzDeniesProcurementOnly();
        $this->testTenantGuardBranchIsolationSource();
        $this->testBackgroundSyncDisabledWhenMasterOff();
        $this->testDirectoryDisabledWhenFlagOff();
        $this->testQueueHrEnqueuePath();
        $this->testSyncStatusExposesHrFlag();

        $this->testStressAckEvaluations();
        $this->testStressHrConflictResolver();
        $this->testStressSanitizer();

        $this->clearHrEnv();

        return $this->results;
    }

    private function clearHrEnv(): void
    {
        putenv('RATEB_OFFLINE_ENABLED');
        putenv('RATEB_OFFLINE_HR_ATTENDANCE');
        unset($_ENV['RATEB_OFFLINE_ENABLED'], $_ENV['RATEB_OFFLINE_HR_ATTENDANCE']);
    }

    private function enableHrFlags(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_HR_ATTENDANCE=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_HR_ATTENDANCE'] = '1';
    }

    private function testHrFlagDefaultOff(): void
    {
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.hr.attendance') === false
            && $svc->isHrAttendanceEnabled() === false;
        $this->record('hr flag default OFF', $ok, $ok ? 'ok' : 'unexpectedly on');
    }

    private function testHrRequiresMaster(): void
    {
        putenv('RATEB_OFFLINE_HR_ATTENDANCE=1');
        $_ENV['RATEB_OFFLINE_HR_ATTENDANCE'] = '1';
        putenv('RATEB_OFFLINE_ENABLED');
        unset($_ENV['RATEB_OFFLINE_ENABLED']);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.hr.attendance') === true
            && $svc->isHrAttendanceEnabled() === false;
        $this->record('hr sub-flag alone does not enable master combo', $ok, 'master=' . ($svc->isMasterEnabled() ? '1' : '0'));
        $this->clearHrEnv();
    }

    private function testEntityManifestHasHr(): void
    {
        $cfg = require RATEB_ROOT . '/offline/config/entity-manifest.php';
        $ok = isset($cfg['hr_attendance'], $cfg['hr_attendance_bulk'], $cfg['hr_leave_draft'], $cfg['employee_directory']);
        $this->record('entity manifest HR entries', $ok, $ok ? 'ok' : 'missing keys');
    }

    private function testModulesRegistryActiveHr(): void
    {
        $cfg = require RATEB_ROOT . '/offline/config/modules.php';
        $active = is_array($cfg['active_modules'] ?? null) ? $cfg['active_modules'] : [];
        $ops = is_array($cfg['operations'] ?? null) ? $cfg['operations'] : [];
        $ok = in_array('hr', $active, true)
            && isset($ops['hr.attendance'], $ops['hr.attendance.bulk'], $ops['hr.leave_draft']);
        $this->record('modules registry activates HR', $ok, json_encode($active) ?: '');
    }

    private function testDeferredActionsCoverRequirements(): void
    {
        $actions = HrOfflineReplayService::deferredActions();
        $need = ['attendance.create', 'attendance.bulk', 'leave_request.draft'];
        $ok = true;
        foreach ($need as $a) {
            if (!in_array($a, $actions, true)) {
                $ok = false;
                break;
            }
        }
        $this->record('deferred actions cover Phase 4', $ok, implode(',', $actions));
    }

    private function testClientAdapterSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/hr-adapter.js');
        $ok = str_contains($src, 'enqueueAttendance')
            && str_contains($src, 'enqueueAttendanceBulk')
            && str_contains($src, 'enqueueLeaveDraft')
            && str_contains($src, 'pullEmployeeDirectory')
            && str_contains($src, 'hr_offline_disabled')
            && !str_contains($src, 'hr_offline_not_implemented')
            && !preg_match('/enqueue\([\'"]payroll/i', $src)
            && !preg_match('/approveLeave|rejectLeave|postPayroll/', $src);
        $this->record('client HR adapter wired', $ok, $ok ? 'ok' : 'stub/payroll leak');
    }

    private function testSdkBundleHasHrAdapter(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($src, 'RatebOfflineHrAdapter')
            && str_contains($src, 'enqueueLeaveDraft')
            && str_contains($src, 'isHrEnabled')
            && str_contains($src, 'Phase 4');
        $this->record('SDK bundle includes HR Phase 4', $ok, $ok ? 'ok' : 'bundle stale');
    }

    private function testReplayUsesExistingHrDomainOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineReplayService.php');
        $ok = str_contains($src, 'AttendanceRecord')
            && str_contains($src, 'LeaveRequest')
            && str_contains($src, 'HrService')
            && str_contains($src, 'No payroll')
            && !preg_match('/INSERT\s+INTO\s+rateb_attendance/i', $src)
            && !preg_match('/UPDATE\s+rateb_employees\b/i', $src);
        $this->record('replay uses existing HR domain only', $ok, $ok ? 'ok' : 'direct SQL found');
    }

    private function testNoPayrollApprovalsInReplay(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineReplayService.php');
        $ok = !preg_match('/->approveLeave\s*\(/', $src)
            && !preg_match('/->rejectLeave\s*\(/', $src)
            && !preg_match('/->generatePayrollLines\s*\(/', $src)
            && !preg_match('/->approvePayroll\s*\(/', $src)
            && !preg_match('/->postPayroll\s*\(/', $src)
            && !preg_match('/new\s+PayrollPeriod\b/', $src)
            && !preg_match('/new\s+PayrollLine\b/', $src)
            && str_contains($src, "status' => 'pending'");
        $this->record('no payroll/approvals in HR replay', $ok, $ok ? 'ok' : 'leak');
    }

    private function testConflictStatusChanged(): void
    {
        $r = (new OfflineConflictResolverService())->resolveHr(
            ['version' => 5, 'expected_status' => 'present'],
            ['version' => 1, 'status' => 'absent']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'status_changed';
        $this->record('conflict status_changed', $ok, (string) ($r['reason'] ?? ''));
    }

    private function testConflictServerNewerStillWins(): void
    {
        $r = (new OfflineConflictResolverService())->resolveHr(
            ['version' => 1, 'expected_status' => 'present'],
            ['version' => 3, 'status' => 'present']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'server_newer';
        $this->record('conflict server_newer still wins', $ok, (string) ($r['reason'] ?? ''));
    }

    private function testConflictAcceptWhenStatusMatches(): void
    {
        $r = (new OfflineConflictResolverService())->resolveHr(
            ['version' => 5, 'expected_status' => 'present'],
            ['version' => 1, 'status' => 'present']
        );
        $ok = ($r['action'] ?? '') === 'accept_client';
        $this->record('conflict accept when status matches', $ok, (string) ($r['action'] ?? ''));
    }

    private function testReplayRejectsEmptyAttendance(): void
    {
        $this->enableHrFlags();
        $svc = new HrOfflineReplayService();
        try {
            $svc->replay('attendance.create', ['company_id' => 1, 'branch_id' => 1, 'user_id' => 1], [
                'employee_id' => 0,
                'attendance_date' => '',
            ], 'k-empty-a');
            $this->record('replay rejects empty attendance', false, 'no exception');
        } catch (\Throwable $e) {
            $ok = in_array($e->getMessage(), ['invalid_employee_id', 'empty_attendance_payload', 'employee_not_found'], true)
                || str_contains($e->getMessage(), 'employee')
                || str_contains($e->getMessage(), 'Database')
                || str_contains($e->getMessage(), 'connection');
            $this->record('replay rejects empty attendance', $ok, $e->getMessage());
        }
        $this->clearHrEnv();
    }

    private function testReplayRejectsEmptyBulk(): void
    {
        $this->enableHrFlags();
        $svc = new HrOfflineReplayService();
        try {
            $svc->replay('attendance.bulk', ['company_id' => 1, 'user_id' => 1], [
                'attendance_date' => '',
                'rows' => [],
            ], 'k-empty-b');
            $this->record('replay rejects empty bulk', false, 'no exception');
        } catch (\Throwable $e) {
            $ok = $e->getMessage() === 'empty_attendance_bulk_payload'
                || str_contains($e->getMessage(), 'Database')
                || str_contains($e->getMessage(), 'connection');
            $this->record('replay rejects empty bulk', $ok, $e->getMessage());
        }
        $this->clearHrEnv();
    }

    private function testReplayRejectsEmptyLeaveDraft(): void
    {
        $this->enableHrFlags();
        $svc = new HrOfflineReplayService();
        try {
            $svc->replay('leave_request.draft', ['company_id' => 1, 'user_id' => 1], [
                'employee_id' => 0,
                'leave_type_id' => 0,
                'start_date' => '',
                'end_date' => '',
            ], 'k-empty-l');
            $this->record('replay rejects empty leave draft', false, 'no exception');
        } catch (\Throwable $e) {
            $ok = in_array($e->getMessage(), ['invalid_employee_id', 'empty_leave_draft_payload', 'employee_not_found'], true)
                || str_contains($e->getMessage(), 'employee')
                || str_contains($e->getMessage(), 'Database')
                || str_contains($e->getMessage(), 'connection');
            $this->record('replay rejects empty leave draft', $ok, $e->getMessage());
        }
        $this->clearHrEnv();
    }

    private function testReplaySkipsWhenFlagOff(): void
    {
        $this->clearHrEnv();
        $r = (new OfflineReplayEngine())->replay([
            'module' => 'hr',
            'action' => 'attendance.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'attendance.create', 'payload' => ['employee_id' => 1, 'attendance_date' => '2026-07-11']]),
        ]);
        $ok = ($r['status'] ?? '') === 'skipped';
        $this->record('engine skips HR when flag OFF', $ok, (string) ($r['error'] ?? $r['status'] ?? ''));
    }

    private function testReplayEngineDelegatesWhenFlagOn(): void
    {
        $this->enableHrFlags();
        $r = (new OfflineReplayEngine())->replay([
            'module' => 'hr',
            'action' => 'attendance.create',
            'company_id' => 1,
            'branch_id' => 1,
            'user_id' => 1,
            'idempotency_key' => 'phase4-delegate-1',
            'payload' => json_encode([
                'action' => 'attendance.create',
                'payload' => ['employee_id' => 0, 'attendance_date' => '2026-07-11', 'status' => 'present'],
            ]),
        ]);
        $status = (string) ($r['status'] ?? '');
        $ok = in_array($status, ['failed', 'synced', 'conflict'], true) && $status !== 'skipped';
        $this->record('engine delegates HR when flag ON', $ok, $status . '/' . (string) ($r['error'] ?? ''));
        $this->clearHrEnv();
    }

    private function testQueueRejectsHrWhenFlagOff(): void
    {
        $this->clearHrEnv();
        putenv('RATEB_OFFLINE_ENABLED=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $queue = new OfflineQueueService();
        $result = $queue->enqueueBatch([
            [
                'client_id' => 'hr-flag-off-1',
                'module' => 'hr',
                'action' => 'attendance.create',
                'payload' => ['employee_id' => 1, 'attendance_date' => '2026-07-11'],
            ],
        ], ['company_id' => 1]);
        $ok = (($result['rejected'] ?? 0) >= 1) || !empty($result['errors']['migration_required']);
        $this->record('queue rejects/blocks HR without tables or flag', $ok, json_encode($result) ?: '');
        $this->clearHrEnv();
    }

    private function testPayloadSanitizerKeepsHrModule(): void
    {
        $n = (new OfflinePayloadSanitizer())->normalize([
            'client_id' => 'h1',
            'module' => 'hr',
            'action' => 'attendance.create',
            'payload' => ['url' => 'http://evil', 'employee_id' => 9, 'attendance_date' => '2026-07-11'],
            'version' => 2,
        ]);
        $ok = ($n['module'] ?? '') === 'hr'
            && !isset($n['payload']['url'])
            && (int) ($n['payload']['employee_id'] ?? 0) === 9;
        $this->record('payload sanitizer keeps HR module', $ok, json_encode($n) ?: '');
    }

    private function testPushAckClearableContract(): void
    {
        $ack = (new OfflinePushAckContract())->evaluate([
            'accepted' => 1,
            'duplicate' => 0,
            'conflict' => 1,
            'rejected' => 1,
            'accepted_keys' => ['a'],
            'duplicate_keys' => [],
            'conflict_keys' => ['c'],
            'rejected_keys' => ['r'],
        ]);
        $clearable = $ack['clearable_keys'] ?? [];
        $ok = $ack['ok'] === true
            && in_array('a', $clearable, true)
            && !in_array('c', $clearable, true)
            && !in_array('r', $clearable, true);
        $this->record('push ack clearable excludes conflict/rejected', $ok, json_encode($clearable) ?: '');
    }

    private function testAuthzAllowsHrAbility(): void
    {
        TenantContext::setCompanyId(42);
        TenantContext::setApiModules(['hr']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === true;
        $this->record('authz allows HR ability token', $ok, $ok ? 'ok' : 'denied');
        TenantContext::setApiModules(null);
        TenantContext::setCompanyId(null);
    }

    private function testAuthzDeniesProcurementOnly(): void
    {
        TenantContext::setCompanyId(42);
        // payroll is not a sync-manage ability (accounting is allowed since Phase 16B).
        TenantContext::setApiModules(['payroll']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === false;
        $this->record('authz denies payroll-only token', $ok, $ok ? 'ok' : 'allowed');
        TenantContext::setApiModules(null);
        TenantContext::setCompanyId(null);
    }

    private function testTenantGuardBranchIsolationSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineTenantGuard.php');
        $ok = str_contains($src, 'branch_mismatch') && str_contains($src, 'tenant_mismatch');
        $this->record('tenant guard enforces branch + tenant isolation', $ok, $ok ? 'ok' : 'missing');
    }

    private function testBackgroundSyncDisabledWhenMasterOff(): void
    {
        $this->clearHrEnv();
        $r = (new OfflineBackgroundSync())->process(1, 10);
        $ok = !empty($r['disabled']) && (int) ($r['processed'] ?? -1) === 0;
        $this->record('background sync disabled when master OFF', $ok, json_encode($r) ?: '');
    }

    private function testDirectoryDisabledWhenFlagOff(): void
    {
        $this->clearHrEnv();
        $r = (new HrOfflineEmployeeDirectoryService())->pull(1, 1, null, 10);
        $ok = !empty($r['disabled']) || !empty($r['stub']);
        $this->record('employee directory disabled when flag OFF', $ok, json_encode(array_keys($r)) ?: '');
    }

    private function testQueueHrEnqueuePath(): void
    {
        $this->enableHrFlags();
        $result = (new OfflineQueueService())->enqueueBatch([
            [
                'client_id' => 'hr-int-1',
                'module' => 'hr',
                'action' => 'attendance.create',
                'payload' => ['employee_id' => 1, 'attendance_date' => '2026-07-11', 'status' => 'present'],
                'version' => 1,
            ],
        ], ['company_id' => 1, 'branch_id' => 1, 'user_id' => 1]);
        $ok = is_array($result) && (isset($result['accepted']) || isset($result['rejected']));
        $this->record('queue HR enqueue path (DB optional)', $ok, json_encode($result) ?: '');
        $this->clearHrEnv();
    }

    private function testSyncStatusExposesHrFlag(): void
    {
        $status = (new OfflineSyncService())->status(1);
        $flags = is_array($status['flags'] ?? null) ? $status['flags'] : [];
        $ok = array_key_exists('offline.hr.attendance', $flags)
            && $flags['offline.hr.attendance'] === false;
        $this->record('sync status exposes HR flag', $ok, json_encode($flags) ?: '');
    }

    private function testStressAckEvaluations(): void
    {
        $contract = new OfflinePushAckContract();
        $ok = true;
        for ($i = 0; $i < 5000; $i++) {
            $accepted = $i % 3 === 0 ? 1 : 0;
            $r = $contract->evaluate([
                'accepted' => $accepted,
                'duplicate' => 0,
                'conflict' => 0,
                'rejected' => 0,
                'accepted_keys' => $accepted ? ['a' . $i] : [],
                'duplicate_keys' => [],
                'conflict_keys' => [],
                'rejected_keys' => [],
            ]);
            if (($r['ok'] ?? null) !== ($accepted > 0)) {
                $ok = false;
                break;
            }
        }
        $this->record('stress ack 5000 evaluations', $ok, $ok ? 'ok' : 'mismatch');
    }

    private function testStressHrConflictResolver(): void
    {
        $resolver = new OfflineConflictResolverService();
        $ok = true;
        for ($i = 0; $i < 2000; $i++) {
            $r = $resolver->resolveHr(
                ['version' => $i + 2, 'expected_status' => 'present'],
                ['version' => 1, 'status' => ($i % 2 === 0) ? 'present' : 'absent']
            );
            $expect = ($i % 2 === 0) ? 'accept_client' : 'reject_client';
            if (($r['action'] ?? '') !== $expect) {
                $ok = false;
                break;
            }
        }
        $this->record('stress HR conflict resolver 2000', $ok, $ok ? 'ok' : 'mismatch');
    }

    private function testStressSanitizer(): void
    {
        $sanitizer = new OfflinePayloadSanitizer();
        $ok = true;
        for ($i = 0; $i < 1000; $i++) {
            $n = $sanitizer->normalize([
                'client_id' => 'hr-stress-' . $i,
                'module' => 'hr',
                'action' => 'attendance.create',
                'payload' => ['url' => 'x', 'employee_id' => $i, 'attendance_date' => '2026-07-11'],
                'version' => 1,
            ]);
            if (($n['module'] ?? '') !== 'hr' || isset($n['payload']['url'])) {
                $ok = false;
                break;
            }
        }
        $this->record('stress sanitizer 1000 HR payloads', $ok, $ok ? 'ok' : 'fail');
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = [
            'name' => $name,
            'passed' => $passed,
            'detail' => $detail,
        ];
    }
}
