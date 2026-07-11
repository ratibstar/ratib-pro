<?php

declare(strict_types=1);

/**
 * Phase P2 — Warm Offline Identity Hardening tests.
 *
 * Run: php offline/tests/run-erp-offline-identity-p2-tests.php
 */

use Rateb\App\Offline\Models\OfflineDevice;
use Rateb\App\Offline\Services\DeviceTrustService;
use Rateb\App\Offline\Services\ErpOfflineIdentityAuditService;
use Rateb\App\Offline\Services\ErpOfflineIdentityService;
use Rateb\App\Offline\Services\ErpOfflineIdentitySessionPolicy;
use Rateb\App\Offline\Services\OfflineDeviceGuard;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;

final class ErpOfflineIdentityPhaseP2Test
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testSessionPolicyDefaults();
        $this->testSessionPolicyEnvOverride();
        $this->testIdentityRenewBumpsVersion();
        $this->testExpiredIdentity();
        $this->testClockSkewFutureIssued();
        $this->testAntiRollback();
        $this->testTamperSignature();
        $this->testDeviceTrustConstants();
        $this->testDeviceGuardSourceRevoke();
        $this->testDeviceTrustServiceApiSurface();
        $this->testAuditEventConstants();
        $this->testAuditServiceSource();
        $this->testRenewServiceSource();
        $this->testDeviceTrustApiRoutes();
        $this->testAdminPageAndRoutes();
        $this->testMigration194();
        $this->testClientTtlAndIdle();
        $this->testClientVaultIntegrityAndTamper();
        $this->testClientRenewHook();
        $this->testShellAuthIdleTouch();
        $this->testLayoutSessionPolicy();
        $this->testLogoutWipeStillPresent();
        $this->testFoundationFrozen();
        $this->testMultiDeviceIsolationSource();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_READ_CACHE',
            'RATEB_OFFLINE_AUTH_UNLOCK',
            'RATEB_OFFLINE_IDENTITY_SECRET',
            'RATEB_OFFLINE_UNLOCK_TTL_MS',
            'RATEB_OFFLINE_IDLE_TIMEOUT_MS',
            'RATEB_OFFLINE_MAX_SESSION_MS',
            'RATEB_OFFLINE_IDENTITY_RENEW_BEFORE',
            'RATEB_OFFLINE_CLOCK_SKEW_SECONDS',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
        $ref = new ReflectionClass(OfflineFeatureFlagService::class);
        if ($ref->hasProperty('config')) {
            $prop = $ref->getProperty('config');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    private function enableAuth(): void
    {
        $this->clearEnv();
        putenv('RATEB_OFFLINE_ENABLED=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        putenv('RATEB_OFFLINE_READ_CACHE=1');
        $_ENV['RATEB_OFFLINE_READ_CACHE'] = '1';
        putenv('RATEB_OFFLINE_AUTH_UNLOCK=1');
        $_ENV['RATEB_OFFLINE_AUTH_UNLOCK'] = '1';
        putenv('RATEB_OFFLINE_IDENTITY_SECRET=p2-test-secret');
        $_ENV['RATEB_OFFLINE_IDENTITY_SECRET'] = 'p2-test-secret';
        $ref = new ReflectionClass(OfflineFeatureFlagService::class);
        if ($ref->hasProperty('config')) {
            $prop = $ref->getProperty('config');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    private function testSessionPolicyDefaults(): void
    {
        $p = (new ErpOfflineIdentitySessionPolicy())->snapshot();
        $ok = ($p['unlock_ttl_ms'] ?? 0) === ErpOfflineIdentitySessionPolicy::DEFAULT_UNLOCK_TTL_MS
            && ($p['idle_timeout_ms'] ?? 0) === ErpOfflineIdentitySessionPolicy::DEFAULT_IDLE_TIMEOUT_MS
            && ($p['max_offline_session_ms'] ?? 0) === ErpOfflineIdentitySessionPolicy::DEFAULT_MAX_OFFLINE_SESSION_MS
            && ($p['renew_before_seconds'] ?? 0) === ErpOfflineIdentitySessionPolicy::DEFAULT_RENEW_BEFORE_SECONDS;
        $this->record('session TTL defaults (8h/15m/72h)', $ok, json_encode($p) ?: '');
    }

    private function testSessionPolicyEnvOverride(): void
    {
        putenv('RATEB_OFFLINE_UNLOCK_TTL_MS=3600000');
        $_ENV['RATEB_OFFLINE_UNLOCK_TTL_MS'] = '3600000';
        putenv('RATEB_OFFLINE_IDLE_TIMEOUT_MS=60000');
        $_ENV['RATEB_OFFLINE_IDLE_TIMEOUT_MS'] = '60000';
        $p = (new ErpOfflineIdentitySessionPolicy())->snapshot();
        $ok = ($p['unlock_ttl_ms'] ?? 0) === 3600000 && ($p['idle_timeout_ms'] ?? 0) === 60000;
        $this->record('session TTL env override', $ok, json_encode($p) ?: '');
        $this->clearEnv();
    }

    private function testIdentityRenewBumpsVersion(): void
    {
        $this->enableAuth();
        $svc = new ErpOfflineIdentityService();
        $first = $svc->issue(10, 1, 5, 'erp-p2-a');
        $claims = $first['identity']['claims'] ?? [];
        $renewed = $svc->renew(10, 1, 5, 'erp-p2-a', $claims);
        $v1 = (int) ($claims['identity_version'] ?? 0);
        $v2 = (int) ($renewed['identity']['claims']['identity_version'] ?? 0);
        $ok = ($first['ok'] ?? false) && ($renewed['ok'] ?? false) && $v2 === ($v1 + 1)
            && ($renewed['identity']['claims']['jti'] ?? '') !== ($claims['jti'] ?? '');
        $this->record('identity renew bumps version + new jti', $ok, "v1={$v1} v2={$v2}");
    }

    private function testExpiredIdentity(): void
    {
        $this->enableAuth();
        $svc = new ErpOfflineIdentityService();
        $issued = $svc->issue(10, 1, 5, 'erp-p2-exp', 3600);
        $pkg = $issued['identity'] ?? [];
        $pkg['claims']['expires_at'] = time() - 5;
        $pkg['canonical'] = $svc->canonical($pkg['claims']);
        $key = base64_decode((string) $pkg['identity_key'], true);
        $pkg['signature'] = base64_encode(hash_hmac('sha256', $pkg['canonical'], (string) $key, true));
        $v = $svc->verifyPackage($pkg, ['company_id' => 10, 'user_id' => 5, 'check_nonce' => false]);
        $ok = ($v['ok'] ?? true) === false && ($v['error'] ?? '') === 'identity_expired';
        $this->record('expired identity rejected', $ok, json_encode($v) ?: '');
    }

    private function testClockSkewFutureIssued(): void
    {
        $this->enableAuth();
        putenv('RATEB_OFFLINE_CLOCK_SKEW_SECONDS=30');
        $_ENV['RATEB_OFFLINE_CLOCK_SKEW_SECONDS'] = '30';
        $svc = new ErpOfflineIdentityService();
        $issued = $svc->issue(10, 1, 5, 'erp-p2-skew');
        $pkg = $issued['identity'] ?? [];
        $pkg['claims']['issued_at'] = time() + 3600;
        $pkg['claims']['not_before'] = time() + 3600;
        $pkg['claims']['anti_rollback'] = time() + 3600;
        $pkg['canonical'] = $svc->canonical($pkg['claims']);
        $key = base64_decode((string) $pkg['identity_key'], true);
        $pkg['signature'] = base64_encode(hash_hmac('sha256', $pkg['canonical'], (string) $key, true));
        $v = $svc->verifyPackage($pkg, ['company_id' => 10, 'user_id' => 5, 'check_nonce' => false]);
        $ok = ($v['ok'] ?? true) === false && ($v['error'] ?? '') === 'identity_clock_skew';
        $this->record('clock rollback / future issued_at rejected', $ok, json_encode($v) ?: '');
        $this->clearEnv();
    }

    private function testAntiRollback(): void
    {
        $this->enableAuth();
        $svc = new ErpOfflineIdentityService();
        $issued = $svc->issue(10, 1, 5, 'erp-p2-rb');
        $pkg = $issued['identity'] ?? [];
        $issuedAt = (int) ($pkg['claims']['issued_at'] ?? 0);
        $v = $svc->verifyPackage($pkg, [
            'company_id' => 10,
            'user_id' => 5,
            'previous_issued_at' => $issuedAt + 100,
            'check_nonce' => false,
        ]);
        $ok = ($v['ok'] ?? true) === false && ($v['error'] ?? '') === 'identity_anti_rollback';
        $this->record('anti-rollback timestamp enforced', $ok, json_encode($v) ?: '');
    }

    private function testTamperSignature(): void
    {
        $this->enableAuth();
        $svc = new ErpOfflineIdentityService();
        $issued = $svc->issue(10, 1, 5, 'erp-p2-tamper');
        $pkg = $issued['identity'] ?? [];
        $pkg['signature'] = base64_encode(random_bytes(32));
        $v = $svc->verifyPackage($pkg, ['company_id' => 10, 'user_id' => 5, 'check_nonce' => false]);
        $ok = ($v['ok'] ?? true) === false && ($v['error'] ?? '') === 'identity_signature';
        $this->record('tamper detection (signature)', $ok, json_encode($v) ?: '');
    }

    private function testDeviceTrustConstants(): void
    {
        $ok = OfflineDevice::TRUST_TRUSTED === 'trusted'
            && OfflineDevice::TRUST_REVOKED === 'revoked'
            && OfflineDevice::TRUST_LOST === 'lost'
            && OfflineDevice::TRUST_DISABLED === 'disabled'
            && DeviceTrustService::TRUSTED === 'trusted';
        $this->record('device trust status constants', $ok, 'ok');
    }

    private function testDeviceGuardSourceRevoke(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineDeviceGuard.php');
        $ok = str_contains($src, 'device_revoked')
            && str_contains($src, 'device_lost')
            && str_contains($src, 'device_disabled')
            && str_contains($src, 'force_logout_at')
            && str_contains($src, 'STATUS_TRUSTED');
        $this->record('device revoke blocks guard/replay gate', $ok, $ok ? 'ok' : 'missing');
    }

    private function testDeviceTrustServiceApiSurface(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/DeviceTrustService.php');
        $ok = str_contains($src, 'function listDevices')
            && str_contains($src, 'function rename')
            && str_contains($src, 'function revoke')
            && str_contains($src, 'function revokeAll')
            && str_contains($src, 'function forceLogout')
            && str_contains($src, 'function isReplayAllowed')
            && str_contains($src, 'function updateFingerprint')
            && str_contains($src, 'touchReplay')
            && str_contains($src, 'touchUnlock');
        $this->record('DeviceTrustService multi-device surface', $ok, $ok ? 'ok' : 'missing');
    }

    private function testAuditEventConstants(): void
    {
        $ok = ErpOfflineIdentityAuditService::EVENT_IDENTITY_ENROLLED === 'identity_enrolled'
            && ErpOfflineIdentityAuditService::EVENT_IDENTITY_RENEWED === 'identity_renewed'
            && ErpOfflineIdentityAuditService::EVENT_UNLOCK_SUCCESS === 'unlock_success'
            && ErpOfflineIdentityAuditService::EVENT_UNLOCK_FAILED === 'unlock_failed'
            && ErpOfflineIdentityAuditService::EVENT_IDENTITY_EXPIRED === 'identity_expired'
            && ErpOfflineIdentityAuditService::EVENT_IDENTITY_REVOKED === 'identity_revoked'
            && ErpOfflineIdentityAuditService::EVENT_LOGOUT_WIPE === 'logout_wipe'
            && ErpOfflineIdentityAuditService::EVENT_DEVICE_REVOKED === 'device_revoked'
            && ErpOfflineIdentityAuditService::EVENT_DEVICE_RENAMED === 'device_renamed'
            && ErpOfflineIdentityAuditService::EVENT_DEVICE_RESTORED === 'device_restored';
        $this->record('audit event catalog', $ok, $ok ? 'ok' : 'missing events');
    }

    private function testAuditServiceSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ErpOfflineIdentityAuditService.php');
        $model = (string) file_get_contents(RATEB_ROOT . '/offline/server/Models/OfflineIdentityAudit.php');
        $ok = str_contains($src, 'function log')
            && str_contains($model, 'rateb_offline_identity_audit');
        $this->record('audit service + model', $ok, $ok ? 'ok' : 'missing');
    }

    private function testRenewServiceSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ErpOfflineIdentityRenewService.php');
        $ok = str_contains($src, 'ErpOfflineIdentityService')
            && str_contains($src, 'renew')
            && str_contains($src, 'EVENT_IDENTITY_RENEWED');
        $this->record('identity rotation renew service', $ok, $ok ? 'ok' : 'missing');
    }

    private function testDeviceTrustApiRoutes(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/offline/server/routes/offline-api.php');
        $ctrl = (string) file_get_contents(RATEB_ROOT . '/offline/server/Controllers/ErpOfflineDeviceTrustApiController.php');
        $ok = str_contains($routes, '/api/v1/offline/devices')
            && str_contains($routes, '/devices/rename')
            && str_contains($routes, '/devices/revoke')
            && str_contains($routes, '/devices/renew')
            && str_contains($routes, '/devices/logout-device')
            && str_contains($routes, '/devices/revoke-all')
            && str_contains($ctrl, 'function devices')
            && str_contains($ctrl, 'function revokeAll');
        $this->record('admin/device trust APIs additive', $ok, $ok ? 'ok' : 'missing');
    }

    private function testAdminPageAndRoutes(): void
    {
        $view = RATEB_ROOT . '/views/company/security/offline-devices.php';
        $ctrl = RATEB_ROOT . '/app/controllers/Company/OfflineDevicesController.php';
        $routes = (string) file_get_contents(RATEB_ROOT . '/routes/company.php');
        $sidebar = (string) file_get_contents(RATEB_ROOT . '/views/partials/sidebar-ops-nav.php');
        $ok = is_file($view) && is_file($ctrl)
            && str_contains($routes, 'security/offline-devices')
            && str_contains($sidebar, 'security/offline-devices')
            && str_contains((string) file_get_contents($view), 'offline_last_unlock');
        $this->record('admin Security Offline Devices page', $ok, $ok ? 'ok' : 'missing');
    }

    private function testMigration194(): void
    {
        $mig = (string) file_get_contents(RATEB_ROOT . '/migrations/194_offline_identity_hardening.sql');
        $ok = str_contains($mig, 'trust_status')
            && str_contains($mig, 'rateb_offline_identity_audit')
            && str_contains($mig, 'rateb_offline_identity_nonces')
            && str_contains($mig, 'offline.devices.manage')
            && str_contains($mig, 'fingerprint');
        $this->record('migration 194 hardening schema', $ok, $ok ? 'ok' : 'missing');
    }

    private function testClientTtlAndIdle(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/auth-lock-adapter.js');
        $ok = str_contains($src, 'sessionPolicy')
            && str_contains($src, 'idle_timeout_ms')
            && str_contains($src, 'max_offline_session_ms')
            && str_contains($src, 'assertSessionTtl')
            && str_contains($src, 'touchIdle')
            && str_contains($src, 'idle_timeout')
            && str_contains($src, 'max_offline_session');
        $this->record('client unlock/idle/max TTL', $ok, $ok ? 'ok' : 'missing');
    }

    private function testClientVaultIntegrityAndTamper(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/auth-lock-adapter.js');
        $ok = str_contains($src, 'vaultIntegrityHash')
            && str_contains($src, 'vault_tamper')
            && str_contains($src, 'clock_rollback')
            && str_contains($src, 'anti_rollback');
        $this->record('client vault integrity + tamper/clock', $ok, $ok ? 'ok' : 'missing');
    }

    private function testClientRenewHook(): void
    {
        $boot = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/erp-auth-bootstrap.js');
        $ok = str_contains($boot, '/devices/renew')
            && str_contains($boot, 'needsIdentityRenewal')
            && str_contains($boot, 'session_policy')
            && str_contains($boot, 'fingerprint');
        $this->record('client renew + fingerprint hook', $ok, $ok ? 'ok' : 'missing');
    }

    private function testShellAuthIdleTouch(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/erp-offline-shell-auth.js');
        $ok = str_contains($src, 'touchIdle')
            && str_contains($src, 'requireUnlockIfNeeded');
        $this->record('offline shell idle touch', $ok, $ok ? 'ok' : 'missing');
    }

    private function testLayoutSessionPolicy(): void
    {
        $layout = (string) file_get_contents(RATEB_ROOT . '/views/layouts/main.php');
        $ok = str_contains($layout, 'session_policy')
            && str_contains($layout, 'ErpOfflineIdentitySessionPolicy');
        $this->record('layout injects session_policy', $ok, $ok ? 'ok' : 'missing');
    }

    private function testLogoutWipeStillPresent(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/auth-lock-adapter.js');
        $ok = str_contains($src, 'destroyWarmSession')
            && str_contains($src, 'erp_rbac')
            && str_contains($src, 'deleteVault');
        $this->record('logout wipe preserved', $ok, $ok ? 'ok' : 'missing');
    }

    private function testFoundationFrozen(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $bundle = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($schema, 'DB_VERSION = 2')
            && str_contains($bundle, '14.2.0')
            && !str_contains($schema, 'DB_VERSION = 3');
        $this->record('foundation frozen SDK 14.2.0 / DB_VERSION 2', $ok, $ok ? 'ok' : 'bumped');
    }

    private function testMultiDeviceIsolationSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/auth-lock-adapter.js');
        $trust = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/DeviceTrustService.php');
        $ok = str_contains($src, 'company_id') && str_contains($src, 'device_id')
            && str_contains($src, 'vaultId')
            && str_contains($trust, 'findByDeviceId')
            && str_contains($trust, 'revokeAll');
        $this->record('multi-device isolation (per device vault/key)', $ok, $ok ? 'ok' : 'missing');
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
    }
}
