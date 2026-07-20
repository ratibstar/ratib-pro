<?php

declare(strict_types=1);

/**
 * Phase E ESS leave — envelope, DTO, isolation, duplicate codes.
 *
 * Run: php tests/hr/run-ess-phase-e-leave-tests.php
 */
final class EssPhaseELeaveTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testRoutesRegistered();
        $this->testEnvelopeOkFail();
        $this->testBalanceDtoStripsTenant();
        $this->testRequestDtoStripsTenant();
        $this->testListSqlScoped();
        $this->testOverlapSqlScoped();
        $this->testControllerNeverTrustsEmployeeId();
        $this->testDuplicateAndValidationCodes();
        $this->testOfflineDraftActionUnchanged();
        $this->testInclusiveDaysFormula();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testRoutesRegistered(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/routes/modules/api.php');
        $ok = str_contains($src, "/api/v1/hr/leave/balances")
            && str_contains($src, "/api/v1/hr/leave/requests")
            && str_contains($src, "/api/v1/hr/leave/apply")
            && str_contains($src, "HrEssLeaveController::class, 'apply'");
        $this->record('ESS leave routes registered', $ok);
    }

    private function testEnvelopeOkFail(): void
    {
        $ref = new ReflectionClass(\Rateb\App\Services\HrEssLeaveService::class);
        $okM = $ref->getMethod('ok');
        $okM->setAccessible(true);
        $failM = $ref->getMethod('fail');
        $failM->setAccessible(true);
        $svc = $ref->newInstanceWithoutConstructor();
        $okBody = $okM->invoke($svc, ['items' => []]);
        $failBody = $failM->invoke($svc, 409, 'duplicate_request', 'dup');
        $ok = ($okBody['body']['success'] ?? false) === true
            && array_key_exists('data', $okBody['body'])
            && ($failBody['body']['success'] ?? true) === false
            && ($failBody['body']['code'] ?? '') === 'duplicate_request';
        $this->record('Leave service success/error envelope', $ok);
    }

    private function testBalanceDtoStripsTenant(): void
    {
        $ref = new ReflectionClass(\Rateb\App\Services\HrEssLeaveService::class);
        $m = $ref->getMethod('balanceDto');
        $m->setAccessible(true);
        $svc = $ref->newInstanceWithoutConstructor();
        $dto = $m->invoke($svc, [
            'leave_type_id' => 3,
            'company_id' => 99,
            'employee_id' => 55,
            'leave_type_code' => 'annual',
            'leave_type_name' => 'Annual',
            'balance_year' => 2026,
            'entitled_days' => 21,
            'used_days' => 2,
            'remaining_days' => 19,
        ]);
        $ok = is_array($dto)
            && !isset($dto['company_id'], $dto['employee_id'])
            && ($dto['leave_type_code'] ?? '') === 'annual';
        $this->record('Balance DTO strips tenant identity', $ok);
    }

    private function testRequestDtoStripsTenant(): void
    {
        $ref = new ReflectionClass(\Rateb\App\Services\HrEssLeaveService::class);
        $m = $ref->getMethod('requestDto');
        $m->setAccessible(true);
        $svc = $ref->newInstanceWithoutConstructor();
        $dto = $m->invoke($svc, [
            'id' => 8,
            'company_id' => 1,
            'employee_id' => 2,
            'leave_type_id' => 3,
            'leave_type_code' => 'sick',
            'leave_type_name' => 'Sick',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-02',
            'days' => 2,
            'status' => 'pending',
            'reason' => 'x',
        ]);
        $ok = is_array($dto)
            && !isset($dto['company_id'], $dto['employee_id'])
            && ($dto['id'] ?? 0) === 8;
        $this->record('Request DTO strips tenant identity', $ok);
    }

    private function testListSqlScoped(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrService.php');
        $ok = str_contains($src, 'function listLeaveRequestsForEmployee')
            && str_contains($src, 'lr.company_id = :cid AND lr.employee_id = :eid')
            && str_contains($src, 'function findLeaveRequestForEmployee')
            && str_contains($src, 'lr.company_id = :cid AND lr.employee_id = :eid AND lr.id = :id');
        $this->record('Leave list/detail SQL company+employee scoped', $ok);
    }

    private function testOverlapSqlScoped(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrService.php');
        $ok = str_contains($src, 'function hasOverlappingLeaveRequest')
            && str_contains($src, "status IN ('pending', 'approved')")
            && str_contains($src, 'company_id = :cid AND employee_id = :eid');
        $this->record('Overlap check is tenant+employee scoped', $ok);
    }

    private function testControllerNeverTrustsEmployeeId(): void
    {
        $ctrl = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Api/HrEssLeaveController.php');
        $svc = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssLeaveService.php');
        $ok = !preg_match('/\$this->input\([\'"]employee_id/', $ctrl)
            && str_contains($svc, "unset(\$payload['employee_id']")
            && str_contains($svc, 'HrEssEmployeeResolverService');
        $this->record('Controller/service never trust client employee_id', $ok);
    }

    private function testDuplicateAndValidationCodes(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssLeaveService.php');
        $ok = str_contains($src, 'duplicate_request')
            && str_contains($src, '409')
            && str_contains($src, 'validation_error')
            && str_contains($src, '422');
        $this->record('Apply emits 409 duplicate and 422 validation codes', $ok);
    }

    private function testOfflineDraftActionUnchanged(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineReplayService.php');
        $ok = str_contains($src, "'leave_request.draft'")
            && !str_contains($src, "'leave_request.approve'");
        $this->record('Offline replay keeps leave_request.draft only', $ok);
    }

    private function testInclusiveDaysFormula(): void
    {
        $ref = new ReflectionClass(\Rateb\App\Services\HrEssLeaveService::class);
        $m = $ref->getMethod('inclusiveDays');
        $m->setAccessible(true);
        $svc = $ref->newInstanceWithoutConstructor();
        $days = $m->invoke($svc, '2026-07-01', '2026-07-03');
        $this->record('Inclusive days formula matches Admin/offline', $days === 3, 'days=' . $days);
    }
}
