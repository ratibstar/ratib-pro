<?php

declare(strict_types=1);

/**
 * Phase I.1 Push Foundation — token preserve, push-token API, isolation.
 *
 * Run: php tests/hr/run-ess-phase-i1-push-foundation-tests.php
 */
final class EssPhaseI1PushFoundationTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testMigrationAdditive();
        $this->testPushTokenRoute();
        $this->testRegisterWithoutTokenPreserves();
        $this->testPushTokenCannotChangeTenantUser();
        $this->testRevokedCannotUpdateToken();
        $this->testClientAppValidation();
        $this->testPushTokenNeverInResponse();
        $this->testTenantIsolationOnFindActive();
        $this->testControllerAuthBinding();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function store(): ArrayMobileDeviceStore
    {
        return new ArrayMobileDeviceStore();
    }

    private function registry(ArrayMobileDeviceStore $store): \Rateb\App\Services\MobileDeviceRegistryService
    {
        return new \Rateb\App\Services\MobileDeviceRegistryService($store);
    }

    private function devices(ArrayMobileDeviceStore $store): \Rateb\App\Services\MobileDeviceService
    {
        return new \Rateb\App\Services\MobileDeviceService($store);
    }

    private function testMigrationAdditive(): void
    {
        $path = RATEB_ROOT . '/migrations/207_mobile_devices_push_foundation.sql';
        $src = is_file($path) ? (string) file_get_contents($path) : '';
        $base = (string) file_get_contents(RATEB_ROOT . '/migrations/206_mobile_devices_registry.sql');
        $ok = str_contains($src, 'push_provider')
            && str_contains($src, 'locale')
            && str_contains($src, 'ALTER TABLE rateb_mobile_devices')
            && str_contains($base, 'uq_mobile_device_identity')
            && !str_contains($src, 'DROP')
            && !str_contains($src, 'password');
        $this->record('Migration 207 adds push_provider+locale without breaking unique key', $ok);
    }

    private function testPushTokenRoute(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/routes/modules/api.php');
        $ok = str_contains($src, '/api/v1/mobile/devices/push-token')
            && str_contains($src, 'pushToken');
        $this->record('push-token route registered', $ok);
    }

    private function testRegisterWithoutTokenPreserves(): void
    {
        $store = $this->store();
        $reg = $this->registry($store);
        $r1 = $reg->register(10, 100, [
            'client_app' => 'ess',
            'device_id' => 'dev-a',
            'platform' => 'android',
            'push_token' => 'secret-token-1',
            'push_provider' => 'fcm',
        ]);
        $id = (int) ($r1['body']['data']['device']['id'] ?? 0);
        $r2 = $reg->register(10, 100, [
            'client_app' => 'ess',
            'device_id' => 'dev-a',
            'platform' => 'android',
            'app_version' => '0.1.6',
        ]);
        $row = $store->rows[$id] ?? [];
        $ok = ($r2['status'] ?? 0) === 200
            && ($row['push_token'] ?? null) === 'secret-token-1'
            && ($row['app_version'] ?? '') === '0.1.6'
            && !isset($r2['body']['data']['device']['push_token']);
        $this->record('Register without push_token keeps existing token', $ok);
    }

    private function testPushTokenCannotChangeTenantUser(): void
    {
        $store = $this->store();
        $reg = $this->registry($store);
        $reg->register(10, 100, [
            'client_app' => 'ess',
            'device_id' => 'dev-b',
            'platform' => 'ios',
        ]);
        $svc = $this->devices($store);
        $otherUser = $svc->updatePushToken(99, 100, [
            'client_app' => 'ess',
            'device_id' => 'dev-b',
            'push_token' => 'hijack',
            'user_id' => 10,
            'company_id' => 100,
        ]);
        $otherTenant = $svc->updatePushToken(10, 999, [
            'client_app' => 'ess',
            'device_id' => 'dev-b',
            'push_token' => 'hijack',
            'company_id' => 100,
        ]);
        $ok = ($otherUser['status'] ?? 0) === 404
            && ($otherTenant['status'] ?? 0) === 404
            && ($store->findByIdentity(100, 'ess', 'dev-b')['push_token'] ?? null) === null;
        $this->record('push-token cannot change tenant/user', $ok);
    }

    private function testRevokedCannotUpdateToken(): void
    {
        $store = $this->store();
        $reg = $this->registry($store);
        $r = $reg->register(10, 100, [
            'client_app' => 'manager',
            'device_id' => 'dev-c',
            'platform' => 'android',
            'push_token' => 'tok',
        ]);
        $id = (int) ($r['body']['data']['device']['id'] ?? 0);
        $reg->revoke(10, 100, $id);
        $upd = $this->devices($store)->updatePushToken(10, 100, [
            'client_app' => 'manager',
            'device_id' => 'dev-c',
            'push_token' => 'new-tok',
            'push_provider' => 'fcm',
        ]);
        $row = $store->rows[$id] ?? [];
        $ok = ($upd['status'] ?? 0) === 403
            && ($upd['body']['code'] ?? '') === 'device_revoked'
            && array_key_exists('push_token', $row)
            && $row['push_token'] === null;
        $this->record('Revoked device cannot update push token', $ok);
    }

    private function testClientAppValidation(): void
    {
        $store = $this->store();
        $bad = $this->devices($store)->updatePushToken(1, 1, [
            'client_app' => 'hack',
            'device_id' => 'x',
            'push_token' => 't',
        ]);
        $ok = ($bad['status'] ?? 0) === 422
            && ($bad['body']['code'] ?? '') === 'validation_error';
        $this->record('Invalid client_app rejected on push-token', $ok);
    }

    private function testPushTokenNeverInResponse(): void
    {
        $store = $this->store();
        $reg = $this->registry($store);
        $reg->register(10, 100, [
            'client_app' => 'ess',
            'device_id' => 'dev-d',
            'platform' => 'android',
        ]);
        $res = $this->devices($store)->updatePushToken(10, 100, [
            'client_app' => 'ess',
            'device_id' => 'dev-d',
            'push_token' => 'super-secret',
            'push_provider' => 'fcm',
            'locale' => 'ar',
        ]);
        $device = $res['body']['data']['device'] ?? [];
        $encoded = json_encode($res['body'], JSON_UNESCAPED_UNICODE);
        $ok = ($res['status'] ?? 0) === 200
            && !isset($device['push_token'])
            && !str_contains((string) $encoded, 'super-secret')
            && ($device['push_provider'] ?? '') === 'fcm'
            && ($device['locale'] ?? '') === 'ar';
        $this->record('push_token never appears in API response', $ok);
    }

    private function testTenantIsolationOnFindActive(): void
    {
        $store = $this->store();
        $reg = $this->registry($store);
        $reg->register(10, 100, [
            'client_app' => 'ess',
            'device_id' => 't1',
            'platform' => 'android',
            'push_token' => 'a',
            'push_provider' => 'fcm',
        ]);
        $reg->register(10, 200, [
            'client_app' => 'ess',
            'device_id' => 't1',
            'platform' => 'android',
            'push_token' => 'b',
            'push_provider' => 'fcm',
        ]);
        $reg->register(11, 100, [
            'client_app' => 'ess',
            'device_id' => 't2',
            'platform' => 'android',
            'push_token' => 'c',
            'push_provider' => 'fcm',
        ]);
        $svc = $this->devices($store);
        $mine = $svc->findActivePushDevices(100, 10, 'ess');
        $ok = count($mine) === 1
            && ($mine[0]['push_token'] ?? '') === 'a'
            && count($svc->findActivePushDevices(100, 10, 'manager')) === 0;
        $this->record('findActivePushDevices is tenant+user+client_app isolated', $ok);
    }

    private function testControllerAuthBinding(): void
    {
        $ctrl = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Api/MobileDeviceController.php');
        $ok = str_contains($ctrl, 'function pushToken')
            && str_contains($ctrl, 'TenantContext::apiUserId')
            && str_contains($ctrl, 'TenantContext::companyId')
            && !preg_match('/pushToken[\s\S]*input\([\'"]user_id/', $ctrl);
        $this->record('Controller pushToken binds auth context only', $ok);
    }
}
