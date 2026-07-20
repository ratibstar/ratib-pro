<?php

declare(strict_types=1);

/**
 * Phase G ESS profile — routes, DTO, isolation, no sensitive fields.
 *
 * Run: php tests/hr/run-ess-phase-g-profile-tests.php
 */
final class EssPhaseGProfileTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testRouteRegistered();
        $this->testEnvelope();
        $this->testDtoShapeAndStripsSensitive();
        $this->testSqlScoped();
        $this->testControllerNeverTrustsClientIds();
        $this->testUsesResolver();
        $this->testNoPutInvented();
        $this->testProfileFeatureKeyExists();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testRouteRegistered(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/routes/modules/api.php');
        $ok = str_contains($src, "/api/v1/hr/profile")
            && str_contains($src, "HrEssProfileController::class, 'show'")
            && str_contains($src, 'ApiAuthMiddleware');
        $this->record('ESS profile route registered with ApiAuthMiddleware stack', $ok);
    }

    private function testEnvelope(): void
    {
        $ref = new ReflectionClass(\Rateb\App\Services\HrEssProfileService::class);
        $okM = $ref->getMethod('ok');
        $okM->setAccessible(true);
        $failM = $ref->getMethod('fail');
        $failM->setAccessible(true);
        $svc = $ref->newInstanceWithoutConstructor();
        $okBody = $okM->invoke($svc, ['profile' => []]);
        $failBody = $failM->invoke($svc, 403, 'forbidden', 'x');
        $ok = ($okBody['body']['success'] ?? false) === true
            && array_key_exists('data', $okBody['body'])
            && ($failBody['body']['code'] ?? '') === 'forbidden';
        $this->record('Profile service success/error envelope', $ok);
    }

    private function testDtoShapeAndStripsSensitive(): void
    {
        $ref = new ReflectionClass(\Rateb\App\Services\HrEssProfileService::class);
        $m = $ref->getMethod('profileDto');
        $m->setAccessible(true);
        $svc = $ref->newInstanceWithoutConstructor();
        $dto = $m->invoke($svc, [
            'id' => 7,
            'employee_code' => 'E-7',
            'name' => 'Ali',
            'email' => 'a@x.com',
            'phone' => '050',
            'status' => 'active',
            'hire_date' => '2024-01-01',
            'user_id' => 0,
            'department_name' => 'HR',
            'job_title_name' => 'Specialist',
            'job_title_text' => '',
            'branch_name' => 'Riyadh',
            'company_id' => 99,
            'salary_base' => 9999,
            'national_id' => 'secret',
            'password' => 'nope',
        ]);
        $required = ['id', 'employee_no', 'full_name', 'photo_url', 'email', 'phone', 'department', 'job_title', 'branch', 'manager', 'join_date', 'status'];
        $ok = is_array($dto);
        foreach ($required as $k) {
            $ok = $ok && array_key_exists($k, $dto);
        }
        $ok = $ok
            && ($dto['employee_no'] ?? '') === 'E-7'
            && ($dto['full_name'] ?? '') === 'Ali'
            && !isset($dto['salary_base'], $dto['national_id'], $dto['password'], $dto['company_id'])
            && !array_key_exists('token', $dto);
        $this->record('Profile DTO fields present; sensitive fields stripped', $ok);
    }

    private function testSqlScoped(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssProfileService.php');
        $ok = str_contains($src, 'e.company_id = :cid AND e.id = :eid')
            && !str_contains($src, 'SELECT *')
            && str_contains($src, 'HrEssEmployeeResolverService');
        $this->record('Profile SQL company+employee scoped without SELECT *', $ok);
    }

    private function testControllerNeverTrustsClientIds(): void
    {
        $ctrl = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Api/HrEssProfileController.php');
        $svc = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssProfileService.php');
        $ok = !preg_match('/\$this->input\([\'"]employee_id/', $ctrl)
            && !preg_match('/\$this->input\([\'"]company_id/', $ctrl)
            && !str_contains($svc, "input('employee_id")
            && str_contains($ctrl, 'TenantContext::apiUserId')
            && str_contains($ctrl, 'TenantContext::companyId');
        $this->record('Controller never trusts client employee_id/company_id', $ok);
    }

    private function testUsesResolver(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssProfileService.php');
        $ok = str_contains($src, 'resolveCurrentEmployee')
            && str_contains($src, 'forbidden');
        $this->record('Uses employee resolver and emits forbidden on tenant mismatch', $ok);
    }

    private function testNoPutInvented(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/routes/modules/api.php');
        $ctrl = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Api/HrEssProfileController.php');
        $ok = !preg_match('/put\([\'"].*hr\/profile/i', $routes)
            && !str_contains($ctrl, 'function update')
            && !str_contains($ctrl, 'function put');
        $this->record('No invented PUT /profile (read-only until ERP self-edit exists)', $ok);
    }

    private function testProfileFeatureKeyExists(): void
    {
        $keys = \Rateb\App\Services\MobileAppConfigService::FEATURE_KEYS;
        $defaults = \Rateb\App\Services\MobileAppConfigService::defaultFeatures();
        $ok = in_array('profile', $keys, true)
            && ($defaults['profile'] ?? false) === true;
        $this->record('MobileConfig features.profile enabled by default', $ok);
    }
}
