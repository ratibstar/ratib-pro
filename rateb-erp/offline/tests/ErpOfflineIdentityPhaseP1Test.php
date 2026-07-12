<?php

declare(strict_types=1);

/**
 * Phase P1 — Enterprise Offline Warm Identity tests.
 *
 * Run: php offline/tests/run-erp-offline-identity-tests.php
 */

use Rateb\App\Core\SessionManager;
use Rateb\App\Offline\Services\ErpOfflineAuthPolicy;
use Rateb\App\Offline\Services\ErpOfflineIdentityService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflineReplayEngine;

final class ErpOfflineIdentityPhaseP1Test
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testFlagsStillGateAuth();
        $this->testOnlineEnrollmentIssuesSignedIdentity();
        $this->testExpiredIdentityRejected();
        $this->testTenantMismatchRejected();
        $this->testBranchMismatchRejected();
        $this->testSignatureTamperRejected();
        $this->testWrongPinSurfaceInAdapter();
        $this->testLogoutDestroysWarmSurface();
        $this->testLogoutPolicyDefaultsClear();
        $this->testOfflineShellHostsPinUnlock();
        $this->testIdentityEnrollRoute();
        $this->testDeviceActivationOnEnrollService();
        $this->testRbacRestorationHooks();
        $this->testReplayStillRequiresServerAuth();
        $this->testNoFakePhpSession();
        $this->testFoundationFrozen();
        $this->testBundleContainsWarmIdentity();
        $this->testPwaAndCapacitorUntouchedPos();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_READ_CACHE',
            'RATEB_OFFLINE_AUTH_UNLOCK',
            'RATEB_OFFLINE_RBAC_CACHE',
            'RATEB_OFFLINE_AUTH_LOGOUT_VAULT',
            'RATEB_OFFLINE_IDENTITY_SECRET',
            'RATEB_OFFLINE_IDENTITY_TTL_SECONDS',
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
        putenv('RATEB_OFFLINE_IDENTITY_SECRET=p1-test-secret');
        $_ENV['RATEB_OFFLINE_IDENTITY_SECRET'] = 'p1-test-secret';
        $ref = new ReflectionClass(OfflineFeatureFlagService::class);
        if ($ref->hasProperty('config')) {
            $prop = $ref->getProperty('config');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    private function testFlagsStillGateAuth(): void
    {
        $this->clearEnv();
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isAuthUnlockEnabled() === false;
        $this->record('auth.unlock still default OFF', $ok, $ok ? 'ok' : 'on');
    }

    private function testOnlineEnrollmentIssuesSignedIdentity(): void
    {
        $this->enableAuth();
        $svc = new ErpOfflineIdentityService();
        $issued = $svc->issue(10, 2, 99, 'erp-device-p1');
        $ok = ($issued['ok'] ?? false) === true
            && isset($issued['identity']['claims'], $issued['identity']['signature'], $issued['identity']['identity_key'], $issued['identity']['canonical'])
            && (string) ($issued['identity']['claims']['purpose'] ?? '') === ErpOfflineIdentityService::PURPOSE;
        $verify = $svc->verifyPackage($issued['identity'] ?? [], [
            'company_id' => 10,
            'branch_id' => 2,
            'user_id' => 99,
            'device_id' => 'erp-device-p1',
        ]);
        $ok = $ok && ($verify['ok'] ?? false) === true;
        $this->record('online enrollment issues signed identity', $ok, $ok ? 'ok' : json_encode([$issued, $verify]));
    }

    private function testExpiredIdentityRejected(): void
    {
        $this->enableAuth();
        $svc = new ErpOfflineIdentityService();
        $issued = $svc->issue(10, 2, 99, 'erp-device-p1', 3600);
        $pkg = $issued['identity'] ?? [];
        $pkg['claims']['expires_at'] = time() - 10;
        $pkg['canonical'] = $svc->canonical($pkg['claims']);
        $key = base64_decode((string) $pkg['identity_key'], true);
        $pkg['signature'] = base64_encode(hash_hmac('sha256', $pkg['canonical'], (string) $key, true));
        $verify = $svc->verifyPackage($pkg, ['company_id' => 10, 'user_id' => 99]);
        $ok = ($verify['ok'] ?? true) === false && ($verify['error'] ?? '') === 'identity_expired';
        $this->record('expired identity rejected', $ok, $ok ? 'ok' : json_encode($verify));
    }

    private function testTenantMismatchRejected(): void
    {
        $this->enableAuth();
        $svc = new ErpOfflineIdentityService();
        $issued = $svc->issue(10, 2, 99, 'erp-device-p1');
        $verify = $svc->verifyPackage($issued['identity'] ?? [], [
            'company_id' => 11,
            'user_id' => 99,
        ]);
        $ok = ($verify['ok'] ?? true) === false && ($verify['error'] ?? '') === 'tenant_mismatch';
        $this->record('tenant mismatch rejected', $ok, $ok ? 'ok' : json_encode($verify));
    }

    private function testBranchMismatchRejected(): void
    {
        $this->enableAuth();
        $svc = new ErpOfflineIdentityService();
        $issued = $svc->issue(10, 2, 99, 'erp-device-p1');
        $verify = $svc->verifyPackage($issued['identity'] ?? [], [
            'company_id' => 10,
            'branch_id' => 9,
            'user_id' => 99,
        ]);
        $ok = ($verify['ok'] ?? true) === false && ($verify['error'] ?? '') === 'branch_mismatch';
        $this->record('branch mismatch rejected', $ok, $ok ? 'ok' : json_encode($verify));
    }

    private function testSignatureTamperRejected(): void
    {
        $this->enableAuth();
        $svc = new ErpOfflineIdentityService();
        $issued = $svc->issue(10, 2, 99, 'erp-device-p1');
        $pkg = $issued['identity'] ?? [];
        $pkg['signature'] = base64_encode(random_bytes(32));
        $verify = $svc->verifyPackage($pkg, ['company_id' => 10, 'user_id' => 99]);
        $ok = ($verify['ok'] ?? true) === false && ($verify['error'] ?? '') === 'identity_signature';
        $this->record('signature tamper rejected', $ok, $ok ? 'ok' : json_encode($verify));
    }

    private function testWrongPinSurfaceInAdapter(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/auth-lock-adapter.js');
        $ok = str_contains($src, 'pin_denied')
            && str_contains($src, 'identity_expired')
            && str_contains($src, 'AES-GCM')
            && str_contains($src, 'identity_cipher')
            && str_contains($src, 'unsealIdentityPackage');
        $this->record('wrong PIN / identity decrypt surface', $ok, $ok ? 'ok' : 'missing');
    }

    private function testLogoutDestroysWarmSurface(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/auth-lock-adapter.js');
        $ok = str_contains($src, 'destroyWarmSession')
            && str_contains($src, 'deleteVault')
            && str_contains($src, 'erp_rbac')
            && str_contains($src, 'erp_shell_chrome')
            && str_contains($src, 'rateb_erp_offline_scope')
            && str_contains($src, 'destroyWarmSession(tenantScope())');
        $this->record('logout destroys warm identity surface', $ok, $ok ? 'ok' : 'missing wipe');
    }

    private function testLogoutPolicyDefaultsClear(): void
    {
        $this->clearEnv();
        $policy = (new ErpOfflineAuthPolicy())->logoutVaultPolicy();
        $ok = $policy === ErpOfflineAuthPolicy::LOGOUT_CLEAR_VAULT;
        $this->record('logout policy defaults clear_vault', $ok, $policy);
    }

    private function testOfflineShellHostsPinUnlock(): void
    {
        $shell = (string) file_get_contents(RATEB_ROOT . '/public/offline-shell.html');
        $host = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/erp-offline-shell-auth.js');
        $ok = str_contains($shell, 'erp-offline-shell-auth.js')
            && str_contains($host, 'requireUnlockIfNeeded')
            && str_contains($host, 'rateb:offline-unlocked')
            && str_contains($host, 'applyCachedNav')
            && !str_contains($host, 'Open Admin online, unlock with your PIN, then retry offline.');
        $this->record('offline-shell hosts PIN unlock', $ok, $ok ? 'ok' : 'locked-only path');
    }

    private function testIdentityEnrollRoute(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/offline/server/routes/offline-api.php');
        $ctrl = (string) file_get_contents(RATEB_ROOT . '/offline/server/Controllers/ErpOfflineAuthApiController.php');
        $ok = str_contains($routes, '/api/v1/offline/auth/identity/enroll')
            && str_contains($ctrl, 'identityEnroll')
            && str_contains($ctrl, 'ErpOfflineIdentityEnrollService');
        $this->record('identity enroll route additive', $ok, $ok ? 'ok' : 'missing');
    }

    private function testDeviceActivationOnEnrollService(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ErpOfflineIdentityEnrollService.php');
        $ok = str_contains($src, 'STATUS_ACTIVE')
            && str_contains($src, 'activateErpShellDevice')
            && str_contains($src, 'ErpOfflineIdentityService');
        $this->record('identity enroll activates device', $ok, $ok ? 'ok' : 'pending-only');
    }

    private function testRbacRestorationHooks(): void
    {
        $host = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/erp-offline-shell-auth.js');
        $rbac = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/rbac-cache-adapter.js');
        $ok = str_contains($host, 'RatebOfflineRbacCache')
            && str_contains($host, 'applyCachedNav')
            && str_contains($rbac, 'validateForUseAsync')
            && str_contains($rbac, 'server_authz_bypass') === false
                ? str_contains($rbac, 'ui_only') || str_contains((string) file_get_contents(RATEB_ROOT . '/offline/docs/PHASE_12_ERP_OFFLINE_RBAC_REPORT.md'), 'UI')
                : true;
        // Prefer direct evidence from adapter comments / flags.
        $ok = str_contains($host, 'applyCachedNav')
            && str_contains($rbac, 'applyCachedNav')
            && str_contains($rbac, 'erp_rbac');
        $this->record('RBAC restoration after unlock', $ok, $ok ? 'ok' : 'missing');
    }

    private function testReplayStillRequiresServerAuth(): void
    {
        $engine = new OfflineReplayEngine();
        $skip = $engine->replay(['module' => 'documents', 'action' => 'document.create']);
        $api = (string) file_get_contents(RATEB_ROOT . '/offline/server/routes/offline-api.php');
        $ok = ($skip['status'] ?? '') === 'skipped'
            && (str_contains($api, 'OfflineApiAuthMiddleware') || str_contains($api, 'ApiAuthMiddleware'))
            && str_contains($api, '/api/v1/offline/push');
        $this->record('replay still requires authenticated server', $ok, $ok ? 'ok' : json_encode($skip));
    }

    private function testNoFakePhpSession(): void
    {
        $svc = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ErpOfflineIdentityService.php');
        $enroll = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ErpOfflineIdentityEnrollService.php');
        $adapter = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/auth-lock-adapter.js');
        $ok = !str_contains($svc, 'SessionManager::set')
            && !str_contains($enroll, 'SessionManager::set')
            && !str_contains($adapter, 'PHPSESSID')
            && str_contains($adapter, 'Never stores passwords / PHP sessions');
        $this->record('no fake PHP session creation', $ok, $ok ? 'ok' : 'session write found');
    }

    private function testFoundationFrozen(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $bundle = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($schema, 'DB_VERSION = 2')
            && str_contains($bundle, '14.2.0')
            && !str_contains($schema, 'DB_VERSION = 3');
        $this->record('foundation frozen DB_VERSION=2 SDK 14.2.0', $ok, $ok ? 'ok' : 'bumped');
    }

    private function testBundleContainsWarmIdentity(): void
    {
        $bundle = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $boot = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/erp-auth-bootstrap.js');
        $ok = str_contains($bundle, 'destroyWarmSession')
            && str_contains($bundle, 'identity_cipher')
            && str_contains($boot, '/auth/identity/enroll');
        $this->record('bundle + bootstrap warm identity', $ok, $ok ? 'ok' : 'rebuild needed');
    }

    private function testPwaAndCapacitorUntouchedPos(): void
    {
        $posManifest = RATEB_ROOT . '/public/pos-manifest.webmanifest';
        $erpManifest = RATEB_ROOT . '/public/manifest.webmanifest';
        $cap = RATEB_ROOT . '/capacitor/capacitor.config.json';
        $rootCap = dirname(RATEB_ROOT) . '/capacitor.config.json';
        $ok = is_file($posManifest)
            && is_file($erpManifest)
            && is_file($cap)
            && str_contains((string) file_get_contents($posManifest), 'Rateb POS')
            && str_contains((string) file_get_contents($erpManifest), 'RATEB ERP')
            && str_contains((string) file_get_contents($cap), 'sa.rateb.erp');
        if (is_file($rootCap)) {
            $ok = $ok && str_contains((string) file_get_contents($rootCap), 'mobile-app');
        }
        $this->record('PWA/Capacitor POS coexistence', $ok, $ok ? 'ok' : 'conflict');
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
    }
}
