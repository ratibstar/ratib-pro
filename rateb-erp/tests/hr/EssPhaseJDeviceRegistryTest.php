<?php

declare(strict_types=1);

/**
 * Phase J Mobile Device Registry — isolation, revoke, duplicates, no secrets.
 *
 * Run: php tests/hr/run-ess-phase-j-device-registry-tests.php
 */
final class EssPhaseJDeviceRegistryTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testMigrationPresent();
        $this->testRoutesRegistered();
        $this->testClientAppsAndNoSecrets();
        $this->testDtoStripsSensitive();
        $this->testValidationCodes();
        $this->testSqlScoped();
        $this->testControllerNeverTrustsClientIds();
        $this->testRevokeAndDuplicatePaths();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testMigrationPresent(): void
    {
        $path = RATEB_ROOT . '/migrations/206_mobile_devices_registry.sql';
        $src = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = str_contains($src, 'rateb_mobile_devices')
            && str_contains($src, 'uq_mobile_device_identity')
            && str_contains($src, 'push_token')
            && !str_contains($src, 'password');
        $this->record('Migration defines rateb_mobile_devices without secrets', $ok);
    }

    private function testRoutesRegistered(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/routes/modules/api.php');
        $ok = str_contains($src, '/api/v1/mobile/devices/register')
            && str_contains($src, '/api/v1/mobile/devices/heartbeat')
            && str_contains($src, '/api/v1/mobile/devices/{id}/revoke')
            && str_contains($src, 'MobileDeviceController::class');
        $this->record('Device registry routes registered', $ok);
    }

    private function testClientAppsAndNoSecrets(): void
    {
        $apps = \Rateb\App\Services\MobileDeviceRegistryService::CLIENT_APPS;
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/MobileDeviceRegistryService.php');
        $ok = in_array('ess', $apps, true)
            && in_array('manager', $apps, true)
            && !preg_match('/\bpassword_hash\b|\bjwt\b|\bbearer\b/i', $src)
            && !str_contains($src, "\$password");
        $this->record('Client apps include ess/manager; no credential storage', $ok);
    }

    private function testDtoStripsSensitive(): void
    {
        $ref = new ReflectionClass(\Rateb\App\Services\MobileDeviceRegistryService::class);
        $m = $ref->getMethod('dto');
        $m->setAccessible(true);
        $svc = $ref->newInstanceWithoutConstructor();
        $dto = $m->invoke($svc, [
            'id' => 3,
            'company_id' => 9,
            'user_id' => 4,
            'client_app' => 'ess',
            'platform' => 'android',
            'device_id' => 'dev-1',
            'push_token' => 'tok',
            'app_version' => '1.0',
            'status' => 'active',
            'last_seen_at' => '2026-07-20 10:00:00',
        ]);
        $ok = is_array($dto)
            && ($dto['id'] ?? 0) === 3
            && ($dto['device_id'] ?? '') === 'dev-1'
            && !isset($dto['company_id'], $dto['user_id'], $dto['push_token']);
        $this->record('DTO strips tenant identity and push token', $ok);
    }

    private function testValidationCodes(): void
    {
        $ref = new ReflectionClass(\Rateb\App\Services\MobileDeviceRegistryService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $reg = $ref->getMethod('register');
        $bad = $reg->invoke($svc, 1, 1, ['client_app' => 'hack', 'device_id' => 'x']);
        $ok = ($bad['status'] ?? 0) === 422
            && ($bad['body']['code'] ?? '') === 'validation_error';
        $this->record('Invalid client_app returns 422 validation_error', $ok);
    }

    private function testSqlScoped(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/MobileDeviceRegistryService.php');
        $ok = str_contains($src, 'company_id = :cid AND client_app = :app AND device_id = :did')
            && str_contains($src, 'company_id = :cid AND user_id = :uid AND id = :id')
            && !str_contains($src, 'SELECT *');
        $this->record('SQL is company+user scoped without SELECT *', $ok);
    }

    private function testControllerNeverTrustsClientIds(): void
    {
        $ctrl = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Api/MobileDeviceController.php');
        $ok = str_contains($ctrl, 'TenantContext::apiUserId')
            && str_contains($ctrl, 'TenantContext::companyId')
            && !preg_match('/\$this->input\([\'"]user_id/', $ctrl)
            && !preg_match('/\$this->input\([\'"]company_id/', $ctrl);
        $this->record('Controller never trusts client user_id/company_id', $ok);
    }

    private function testRevokeAndDuplicatePaths(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/MobileDeviceRegistryService.php');
        $ok = str_contains($src, 'STATUS_REVOKED')
            && str_contains($src, 'device_revoked')
            && str_contains($src, 'Device belongs to another user')
            && str_contains($src, 'findByIdentity');
        $this->record('Revoke + duplicate/ownership paths present', $ok);
    }
}
