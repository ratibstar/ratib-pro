<?php

declare(strict_types=1);

/**
 * Phase D ESS attendance — envelope, DTO, state codes, SQL isolation.
 *
 * Run: php tests/hr/run-ess-phase-d-attendance-tests.php
 */
final class EssPhaseDAttendanceTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testRoutesRegistered();
        $this->testServiceEnvelopeOk();
        $this->testServiceEnvelopeFail();
        $this->testDtoNeverExposesSelectStarShape();
        $this->testHistorySqlIsParameterizedAndScoped();
        $this->testTodayLookupSqlScoped();
        $this->testControllerNeverReadsClientEmployeeId();
        $this->testCheckInRejectsDuplicateCode();
        $this->testCheckOutRequiresCheckInCode();
        $this->testOfflineCreateActionUnchanged();

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
        $ok = str_contains($src, "/api/v1/hr/attendance/today")
            && str_contains($src, "/api/v1/hr/attendance/history")
            && str_contains($src, "/api/v1/hr/attendance/check-in")
            && str_contains($src, "/api/v1/hr/attendance/check-out");
        $this->record('ESS attendance routes registered', $ok);
    }

    private function testServiceEnvelopeOk(): void
    {
        $ref = new ReflectionClass(\Rateb\App\Services\HrEssAttendanceService::class);
        $m = $ref->getMethod('ok');
        $m->setAccessible(true);
        $svc = $ref->newInstanceWithoutConstructor();
        $out = $m->invoke($svc, ['attendance' => null]);
        $ok = ($out['status'] ?? 0) === 200
            && ($out['body']['success'] ?? false) === true
            && array_key_exists('data', $out['body'] ?? []);
        $this->record('Success envelope uses success+data', $ok);
    }

    private function testServiceEnvelopeFail(): void
    {
        $ref = new ReflectionClass(\Rateb\App\Services\HrEssAttendanceService::class);
        $m = $ref->getMethod('fail');
        $m->setAccessible(true);
        $svc = $ref->newInstanceWithoutConstructor();
        $out = $m->invoke($svc, 409, 'already_checked_in', 'Already checked in');
        $ok = ($out['status'] ?? 0) === 409
            && ($out['body']['success'] ?? true) === false
            && ($out['body']['code'] ?? '') === 'already_checked_in'
            && ($out['body']['message'] ?? '') !== '';
        $this->record('Error envelope uses success+code+message', $ok);
    }

    private function testDtoNeverExposesSelectStarShape(): void
    {
        $ref = new ReflectionClass(\Rateb\App\Services\HrEssAttendanceService::class);
        $m = $ref->getMethod('toDto');
        $m->setAccessible(true);
        $svc = $ref->newInstanceWithoutConstructor();
        $dto = $m->invoke($svc, [
            'id' => 7,
            'company_id' => 99,
            'employee_id' => 55,
            'attendance_date' => '2026-07-20',
            'check_in' => '09:00:00',
            'check_out' => null,
            'status' => 'present',
            'notes' => 'x',
            'secret_col' => 'leak',
        ]);
        $ok = is_array($dto)
            && !array_key_exists('company_id', $dto)
            && !array_key_exists('employee_id', $dto)
            && !array_key_exists('secret_col', $dto)
            && ($dto['id'] ?? 0) === 7
            && ($dto['check_in'] ?? '') === '09:00:00';
        $this->record('DTO strips tenant identity columns', $ok);
    }

    private function testHistorySqlIsParameterizedAndScoped(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrService.php');
        $ok = str_contains($src, 'function listAttendanceForEmployee')
            && str_contains($src, 'company_id = :cid AND employee_id = :eid')
            && str_contains($src, 'BETWEEN :from_d AND :to_d')
            && !preg_match('/listAttendanceForEmployee[\s\S]{0,400}\$_(GET|POST|REQUEST)/', $src);
        $this->record('History SQL company+employee scoped + bound params', $ok);
    }

    private function testTodayLookupSqlScoped(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrService.php');
        $ok = str_contains($src, 'function findAttendanceByEmployeeDate')
            && str_contains($src, 'company_id = :cid AND employee_id = :eid AND attendance_date = :d');
        $this->record('Today lookup remains tenant+employee scoped', $ok);
    }

    private function testControllerNeverReadsClientEmployeeId(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Api/HrEssAttendanceController.php');
        $svc = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssAttendanceService.php');
        $ok = !preg_match('/\$this->input\([\'"]employee_id/', $src)
            && str_contains($svc, "unset(\$payload['employee_id']")
            && str_contains($svc, 'HrEssEmployeeResolverService');
        $this->record('Controller/service never trust client employee_id', $ok);
    }

    private function testCheckInRejectsDuplicateCode(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssAttendanceService.php');
        $ok = str_contains($src, "already_checked_in")
            && str_contains($src, '409');
        $this->record('Check-in emits 409 already_checked_in', $ok);
    }

    private function testCheckOutRequiresCheckInCode(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssAttendanceService.php');
        $ok = str_contains($src, "invalid_state")
            && str_contains($src, 'Check-in required before check-out')
            && str_contains($src, 'Already checked out');
        $this->record('Check-out emits 422 invalid_state when illegal', $ok);
    }

    private function testOfflineCreateActionUnchanged(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineReplayService.php');
        $ok = str_contains($src, "'attendance.create'")
            && !str_contains($src, "'attendance.update'");
        $this->record('Offline replay keeps attendance.create only (no update action)', $ok);
    }
}
