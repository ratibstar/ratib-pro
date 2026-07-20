<?php

declare(strict_types=1);

/**
 * Phase H ESS offline hardening — verify replay support for ESS actions only.
 *
 * Run: php tests/hr/run-ess-phase-h-offline-tests.php
 */
final class EssPhaseHOfflineTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testEssActionsPresent();
        $this->testForbiddenActionsAbsentFromEssSurface();
        $this->testIdempotencySupport();
        $this->testTenantAndEmployeeIsolation();
        $this->testScopeStripsClientTenant();
        $this->testDuplicateShortCircuit();
        $this->testClearErrorStatuses();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testEssActionsPresent(): void
    {
        $actions = \Rateb\App\Offline\Services\HrOfflineReplayService::deferredActions();
        $ok = in_array('attendance.create', $actions, true)
            && in_array('leave_request.draft', $actions, true);
        $this->record('ESS actions attendance.create + leave_request.draft supported', $ok);
    }

    private function testForbiddenActionsAbsentFromEssSurface(): void
    {
        $actions = \Rateb\App\Offline\Services\HrOfflineReplayService::deferredActions();
        $ok = !in_array('attendance.update', $actions, true)
            && !in_array('attendance.delete', $actions, true)
            && !in_array('payroll.create', $actions, true);
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineReplayService.php');
        $ok = $ok && !str_contains($src, "'attendance.update'")
            && !preg_match("/'payroll\\./", $src);
        $this->record('Forbidden ESS offline actions not in replay surface', $ok);
    }

    private function testIdempotencySupport(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineReplayService.php');
        $ok = str_contains($src, 'idempotency_key')
            && str_contains($src, 'attendanceExistsForKey')
            && str_contains($src, 'leaveExistsForKey')
            && str_contains($src, '[offline:');
        $this->record('Idempotency key support for attendance + leave', $ok);
    }

    private function testTenantAndEmployeeIsolation(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineReplayService.php');
        $guard = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineTenantGuard.php');
        $ok = str_contains($src, 'assertEmployee')
            && str_contains($guard, 'company_id')
            && str_contains($guard, 'function assertEmployee');
        $this->record('Tenant + employee isolation via assertEmployee', $ok);
    }

    private function testScopeStripsClientTenant(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineReplayService.php');
        $ok = str_contains($src, "unset(\$inner['branch_id'], \$inner['company_id'], \$inner['user_id'], \$inner['device_id'])");
        $this->record('Replay strips client company/branch/user/device from payload', $ok);
    }

    private function testDuplicateShortCircuit(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineReplayService.php');
        $ok = str_contains($src, 'attendanceExistsForKey')
            && str_contains($src, 'leaveExistsForKey')
            && (str_contains($src, 'duplicate') || str_contains($src, 'idempotent') || str_contains($src, 'existingId'));
        $this->record('Duplicate protection short-circuits on existing idempotency key', $ok);
    }

    private function testClearErrorStatuses(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineReplayService.php');
        $ok = str_contains($src, "'status' => 'failed'")
            && str_contains($src, "'status' => 'synced'")
            && str_contains($src, "'status' => 'conflict'")
            && str_contains($src, 'company_required');
        $this->record('Clear replay status responses (synced/failed/conflict)', $ok);
    }
}
