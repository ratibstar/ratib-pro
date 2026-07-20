<?php

declare(strict_types=1);

/**
 * Phase F ESS payslips + documents — envelope, DTO, isolation.
 *
 * Run: php tests/hr/run-ess-phase-f-payslip-document-tests.php
 */
final class EssPhaseFPayslipDocumentTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testRoutesRegistered();
        $this->testEnvelopeOkFail();
        $this->testPayslipDtoShape();
        $this->testDocumentDtoNoInternalPath();
        $this->testSqlScopedToCompanyAndEmployee();
        $this->testControllersNeverTrustEmployeeId();
        $this->testErrorCodes();
        $this->testPayslipsFeatureKey();
        $this->testNoOfflinePayrollActions();

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
        $ok = str_contains($src, "/api/v1/hr/payslips")
            && str_contains($src, "/api/v1/hr/payslips/{id}")
            && str_contains($src, "/api/v1/hr/documents")
            && str_contains($src, "/api/v1/hr/documents/{id}")
            && str_contains($src, "HrEssPayslipController::class")
            && str_contains($src, "HrEssDocumentController::class");
        $this->record('ESS payslip/document routes registered', $ok);
    }

    private function testEnvelopeOkFail(): void
    {
        $ref = new ReflectionClass(\Rateb\App\Services\HrEssPayslipDocumentService::class);
        $okM = $ref->getMethod('ok');
        $okM->setAccessible(true);
        $failM = $ref->getMethod('fail');
        $failM->setAccessible(true);
        $svc = $ref->newInstanceWithoutConstructor();
        $okBody = $okM->invoke($svc, ['items' => []]);
        $failBody = $failM->invoke($svc, 404, 'not_found', 'missing');
        $ok = ($okBody['body']['success'] ?? false) === true
            && array_key_exists('data', $okBody['body'])
            && ($failBody['body']['success'] ?? true) === false
            && ($failBody['body']['code'] ?? '') === 'not_found';
        $this->record('Payslip/document service success/error envelope', $ok);
    }

    private function testPayslipDtoShape(): void
    {
        $ref = new ReflectionClass(\Rateb\App\Services\HrEssPayslipDocumentService::class);
        $m = $ref->getMethod('payslipDto');
        $m->setAccessible(true);
        $svc = $ref->newInstanceWithoutConstructor();
        $dto = $m->invoke($svc, [
            'id' => 12,
            'company_id' => 9,
            'employee_id' => 4,
            'basic_salary' => 5000,
            'allowances' => 500,
            'deductions' => 200,
            'net_salary' => 5300,
            'period_year' => 2026,
            'period_month' => 7,
            'period_status' => 'posted',
        ], 'legacy');
        $ok = is_array($dto)
            && ($dto['id'] ?? '') === 'l-12'
            && ($dto['month'] ?? 0) === 7
            && ($dto['year'] ?? 0) === 2026
            && isset($dto['gross_amount'], $dto['net_amount'], $dto['status'], $dto['download_url'])
            && !isset($dto['company_id'], $dto['employee_id'], $dto['basic_salary']);
        $this->record('Payslip DTO shape strips tenant + payroll internals', $ok);
    }

    private function testDocumentDtoNoInternalPath(): void
    {
        $ref = new ReflectionClass(\Rateb\App\Services\HrEssPayslipDocumentService::class);
        $m = $ref->getMethod('documentDto');
        $m->setAccessible(true);
        $svc = $ref->newInstanceWithoutConstructor();
        $dto = $m->invoke($svc, [
            'id' => 5,
            'company_id' => 1,
            'entity_type' => 'hr_employees',
            'entity_id' => 2,
            'title' => 'ID Card',
            'file_name' => 'id.pdf',
            'file_path' => 'uploads/secret/path.pdf',
            'created_at' => '2026-07-01 10:00:00',
        ], 'file');
        $ok = is_array($dto)
            && ($dto['id'] ?? '') === 'f-5'
            && ($dto['title'] ?? '') === 'ID Card'
            && ($dto['file_url'] ?? '') === '/api/v1/hr/documents/f-5/file'
            && !isset($dto['file_path'], $dto['company_id'], $dto['entity_id']);
        $this->record('Document DTO metadata only (no storage path)', $ok);
    }

    private function testSqlScopedToCompanyAndEmployee(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssPayslipDocumentService.php');
        $ok = str_contains($src, 'pl.company_id = :cid AND pl.employee_id = :eid')
            && str_contains($src, 'company_id = :cid AND legacy_employee_id = :eid')
            && str_contains($src, 'company_id = :cid AND entity_id = :eid')
            && str_contains($src, 'company_id = :cid AND employee_id = :eid')
            && str_contains($src, 'HrEssEmployeeResolverService')
            && !str_contains($src, 'SELECT *');
        $this->record('SQL is company+employee scoped without SELECT *', $ok);
    }

    private function testControllersNeverTrustEmployeeId(): void
    {
        $pay = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Api/HrEssPayslipController.php');
        $doc = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Api/HrEssDocumentController.php');
        $svc = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssPayslipDocumentService.php');
        $ok = !preg_match('/\$this->input\([\'"]employee_id/', $pay)
            && !preg_match('/\$this->input\([\'"]employee_id/', $doc)
            && !str_contains($svc, "input('employee_id")
            && str_contains($svc, 'HrEssEmployeeResolverService');
        $this->record('Controllers/service never trust client employee_id', $ok);
    }

    private function testErrorCodes(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssPayslipDocumentService.php');
        $ok = str_contains($src, 'not_found')
            && str_contains($src, 'validation_error')
            && str_contains($src, '422')
            && str_contains($src, '404');
        $this->record('Emits 404 not_found and 422 validation_error', $ok);
    }

    private function testPayslipsFeatureKey(): void
    {
        $svc = new \Rateb\App\Services\MobileAppConfigService();
        $fromPayroll = $svc->normalizeFeatures(['payroll' => true]);
        $fromPayslips = $svc->normalizeFeatures(['payslips' => true]);
        $keys = \Rateb\App\Services\MobileAppConfigService::FEATURE_KEYS;
        $ok = in_array('payslips', $keys, true)
            && ($fromPayroll['payslips'] ?? false) === true
            && ($fromPayslips['payroll'] ?? false) === true;
        $this->record('MobileConfig payslips feature aliases payroll', $ok);
    }

    private function testNoOfflinePayrollActions(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineReplayService.php');
        $ok = !str_contains($src, 'payslip')
            && !str_contains($src, 'payroll.')
            && !str_contains($src, 'document.upload');
        $this->record('Offline replay has no payroll/document sync actions', $ok);
    }
}
