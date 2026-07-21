<?php

declare(strict_types=1);

/**
 * ESS permission requests — routes + identity rules (no client employee_id).
 *
 * Run: php tests/hr/run-ess-permission-request-tests.php
 */
final class EssPermissionRequestTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testRoutes();
        $this->testServiceStripsClientIdentity();
        $this->testControllerUsesTenantContext();
        $this->testValidationRulesPresent();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testRoutes(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/routes/modules/api.php');
        $ok = str_contains($src, '/api/v1/hr/permission-requests')
            && str_contains($src, 'HrEssPermissionRequestsController::class')
            && str_contains($src, "'submit'");
        $this->record('ESS permission-request routes registered', $ok);
    }

    private function testServiceStripsClientIdentity(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssPermissionRequestService.php');
        $ok = str_contains($src, "\$payload['employee_id']")
            && str_contains($src, 'unset(')
            && str_contains($src, 'HrEssEmployeeResolverService')
            && str_contains($src, 'permission_date')
            && str_contains($src, 'time_from')
            && str_contains($src, 'duplicate_request');
        $this->record('Service strips client identity and validates fields', $ok);
    }

    private function testControllerUsesTenantContext(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Api/HrEssPermissionRequestsController.php');
        $ok = str_contains($src, 'TenantContext::apiUserId')
            && str_contains($src, 'TenantContext::companyId')
            && !preg_match('/\$this->input\([\'"]employee_id/', $src);
        $this->record('Controller uses auth tenant context only', $ok);
    }

    private function testValidationRulesPresent(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssPermissionRequestService.php');
        $ok = str_contains($src, 'time_to must be after time_from')
            && str_contains($src, 'YYYY-MM-DD')
            && str_contains($src, "status = 'pending'");
        $this->record('Overlap + date/time validation present', $ok);
    }
}
