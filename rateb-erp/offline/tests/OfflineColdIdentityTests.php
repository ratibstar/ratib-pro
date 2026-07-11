<?php

declare(strict_types=1);

/**
 * Enterprise Cold Offline Identity tests.
 *
 * Run: php offline/tests/run-offline-cold-identity-tests.php
 */

use Rateb\App\Offline\Services\ErpOfflineIdentityAuditService;
use Rateb\App\Offline\Services\ErpOfflineIdentityService;
use Rateb\App\Offline\Services\OfflineBootstrapManager;
use Rateb\App\Offline\Services\OfflineColdIdentityService;
use Rateb\App\Offline\Services\OfflineDeviceIdentityService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflineIdentityValidator;
use Rateb\App\Offline\Services\OfflineLocalSessionService;

final class OfflineColdIdentityTests
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testColdFlagDefaultOff();
        $this->testColdRequiresAuthUnlock();
        $this->testColdIssueDisabledFailClosed();
        $this->testWarmIssueWithoutColdClaims();
        $this->testColdCapableValidateOk();
        $this->testColdValidateRejectsBypass();
        $this->testColdValidateRejectsMissingPermissionsField();
        $this->testLocalSessionNeverCreatesPhpSession();
        $this->testBootstrapConfigLocalOnly();
        $this->testDeviceIdentityNormalize();
        $this->testAuditColdEvents();
        $this->testEnrollUsesColdWhenFlagOn();
        $this->testPolicyEndpointExposesCold();
        $this->testClientAdaptersPresent();
        $this->testBundleIncludesColdAdapters();
        $this->testShellBannerCssNotInlineJs();
        $this->testFoundationFrozen();
        $this->testQueueReplayUntouched();
        $this->testWarmPathSourceIntact();
        $this->testNoPhpSessionOfflineDocs();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_READ_CACHE',
            'RATEB_OFFLINE_AUTH_UNLOCK',
            'RATEB_OFFLINE_AUTH_COLD',
            'RATEB_OFFLINE_IDENTITY_SECRET',
            'RATEB_OFFLINE_RBAC_CACHE',
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

    private function enableAuth(bool $cold = false): void
    {
        $this->clearEnv();
        putenv('RATEB_OFFLINE_ENABLED=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        putenv('RATEB_OFFLINE_READ_CACHE=1');
        $_ENV['RATEB_OFFLINE_READ_CACHE'] = '1';
        putenv('RATEB_OFFLINE_AUTH_UNLOCK=1');
        $_ENV['RATEB_OFFLINE_AUTH_UNLOCK'] = '1';
        putenv('RATEB_OFFLINE_IDENTITY_SECRET=cold-test-secret');
        $_ENV['RATEB_OFFLINE_IDENTITY_SECRET'] = 'cold-test-secret';
        if ($cold) {
            putenv('RATEB_OFFLINE_AUTH_COLD=1');
            $_ENV['RATEB_OFFLINE_AUTH_COLD'] = '1';
        }
        $ref = new ReflectionClass(OfflineFeatureFlagService::class);
        if ($ref->hasProperty('config')) {
            $prop = $ref->getProperty('config');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    private function testColdFlagDefaultOff(): void
    {
        $this->clearEnv();
        $flags = new OfflineFeatureFlagService();
        $ok = !$flags->isColdIdentityEnabled();
        $this->record('cold flag default OFF', $ok, $ok ? 'ok' : 'cold unexpectedly on');
    }

    private function testColdRequiresAuthUnlock(): void
    {
        $this->clearEnv();
        putenv('RATEB_OFFLINE_AUTH_COLD=1');
        $_ENV['RATEB_OFFLINE_AUTH_COLD'] = '1';
        $ref = new ReflectionClass(OfflineFeatureFlagService::class);
        if ($ref->hasProperty('config')) {
            $prop = $ref->getProperty('config');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
        $ok = !(new OfflineFeatureFlagService())->isColdIdentityEnabled();
        $this->record('cold requires auth.unlock chain', $ok, $ok ? 'ok' : 'cold without unlock');
    }

    private function testColdIssueDisabledFailClosed(): void
    {
        $this->enableAuth(false);
        $r = (new OfflineColdIdentityService())->issueColdPackage(1, 1, 1, 'dev-cold-1');
        $ok = !($r['ok'] ?? false) && ($r['error'] ?? '') === 'cold_identity_disabled';
        $this->record('cold issue disabled fail-closed', $ok, json_encode($r) ?: '');
    }

    private function testWarmIssueWithoutColdClaims(): void
    {
        $this->enableAuth(false);
        $issued = (new ErpOfflineIdentityService())->issue(10, 2, 7, 'warm-dev-1');
        $claims = is_array($issued['identity']['claims'] ?? null) ? $issued['identity']['claims'] : [];
        $ok = ($issued['ok'] ?? false)
            && empty($claims['cold_capable'])
            && !array_key_exists('permissions', $claims);
        $this->record('warm issue has no cold claims', $ok, json_encode(array_keys($claims)) ?: '');
    }

    private function packageFromIssue(array $issued): array
    {
        $id = $issued['identity'] ?? [];

        return [
            'alg' => $id['alg'] ?? ErpOfflineIdentityService::ALG,
            'claims' => $id['claims'] ?? [],
            'signature' => $id['signature'] ?? '',
            'identity_key' => $id['identity_key'] ?? '',
            'server_attestation' => $id['server_attestation'] ?? '',
        ];
    }

    private function testColdCapableValidateOk(): void
    {
        $this->enableAuth(true);
        $issued = (new ErpOfflineIdentityService())->issue(10, 2, 7, 'cold-dev-1', null, 1, [
            'cold_capable' => true,
            'user_uuid' => '7',
            'roles' => ['ops'],
            'permissions' => ['ops.view'],
            'plan_modules' => ['ops'],
            'offline_policy' => [
                'ui_only' => true,
                'server_authz_bypass' => false,
                'cold_capable' => true,
            ],
        ]);
        $pkg = $this->packageFromIssue($issued);
        $v = (new OfflineIdentityValidator())->validate($pkg, [
            'company_id' => 10,
            'branch_id' => 2,
            'user_id' => 7,
            'device_id' => 'cold-dev-1',
            'check_nonce' => false,
        ]);
        $ok = ($issued['ok'] ?? false) && ($v['ok'] ?? false) && !empty($v['claims']['cold_capable']);
        $this->record('cold-capable package validates', $ok, json_encode($v) ?: '');
    }

    private function testColdValidateRejectsBypass(): void
    {
        $this->enableAuth(true);
        $issued = (new ErpOfflineIdentityService())->issue(10, 2, 7, 'cold-dev-2', null, 1, [
            'cold_capable' => true,
            'permissions' => ['ops.view'],
            'offline_policy' => [
                'ui_only' => true,
                'server_authz_bypass' => true,
            ],
        ]);
        $v = (new OfflineIdentityValidator())->validate($this->packageFromIssue($issued), [
            'check_nonce' => false,
        ]);
        $ok = !($v['ok'] ?? false) && ($v['error'] ?? '') === 'cold_policy_bypass_forbidden';
        $this->record('cold rejects server_authz_bypass', $ok, json_encode($v) ?: '');
    }

    private function testColdValidateRejectsMissingPermissionsField(): void
    {
        $this->enableAuth(true);
        $issued = (new ErpOfflineIdentityService())->issue(10, 2, 7, 'cold-dev-3', null, 1, [
            'cold_capable' => true,
            'offline_policy' => [
                'ui_only' => true,
                'server_authz_bypass' => false,
            ],
        ]);
        $v = (new OfflineIdentityValidator())->validate($this->packageFromIssue($issued), [
            'check_nonce' => false,
        ]);
        $ok = !($v['ok'] ?? false) && ($v['error'] ?? '') === 'cold_permissions_required';
        $this->record('cold requires permissions list', $ok, json_encode($v) ?: '');
    }

    private function testLocalSessionNeverCreatesPhpSession(): void
    {
        $rules = (new OfflineLocalSessionService())->assertLocalSessionRules();
        $ok = ($rules['ok'] ?? false)
            && ($rules['creates_php_session'] ?? true) === false
            && ($rules['server_authz_bypass'] ?? true) === false;
        $this->record('local session policy never PHP session', $ok, json_encode($rules) ?: '');
    }

    private function testBootstrapConfigLocalOnly(): void
    {
        $cfg = (new OfflineBootstrapManager())->coldBootConfig();
        $ok = ($cfg['creates_server_session'] ?? true) === false
            && ($cfg['requires_prior_enroll'] ?? false) === true
            && ($cfg['entry'] ?? '') === 'offline-shell.html'
            && !empty($cfg['restore']['permissions'])
            && !empty($cfg['restore']['offline_banner']);
        $this->record('cold boot config local-only', $ok, json_encode($cfg) ?: '');
    }

    private function testDeviceIdentityNormalize(): void
    {
        $svc = new OfflineDeviceIdentityService();
        $ok = $svc->normalizeDeviceId('abc!@#123') === 'abc123'
            && $svc->normalizeDeviceId('') === '';
        $this->record('device identity normalize', $ok, $svc->normalizeDeviceId('abc!@#123'));
    }

    private function testAuditColdEvents(): void
    {
        $ok = ErpOfflineIdentityAuditService::EVENT_COLD_UNLOCK_SUCCESS === 'cold_unlock_success'
            && ErpOfflineIdentityAuditService::EVENT_COLD_UNLOCK_FAILED === 'cold_unlock_failed'
            && ErpOfflineIdentityAuditService::EVENT_COLD_SESSION_DESTROYED === 'cold_session_destroyed';
        $this->record('cold audit event catalog', $ok, $ok ? 'ok' : 'missing');
    }

    private function testEnrollUsesColdWhenFlagOn(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ErpOfflineIdentityEnrollService.php');
        $renew = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ErpOfflineIdentityRenewService.php');
        $ok = str_contains($src, 'OfflineColdIdentityService')
            && str_contains($src, 'issueColdPackage')
            && str_contains($renew, 'renewColdPackage');
        $this->record('enroll/renew cold branch when flag ON', $ok, $ok ? 'ok' : 'missing');
    }

    private function testPolicyEndpointExposesCold(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Controllers/ErpOfflineAuthApiController.php');
        $ok = str_contains($src, 'cold_identity')
            && str_contains($src, 'cold_boot')
            && str_contains($src, 'OfflineBootstrapManager');
        $this->record('auth policy exposes cold_boot', $ok, $ok ? 'ok' : 'missing');
    }

    private function testClientAdaptersPresent(): void
    {
        $local = RATEB_ROOT . '/offline/client/adapters/offline-local-session-adapter.js';
        $boot = RATEB_ROOT . '/offline/client/adapters/offline-cold-bootstrap-adapter.js';
        $lock = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/auth-lock-adapter.js');
        $ok = is_file($local) && is_file($boot)
            && str_contains((string) file_get_contents($local), 'RatebOfflineLocalSession')
            && str_contains((string) file_get_contents($boot), 'RatebOfflineBootstrapManager')
            && str_contains($lock, 'RatebOfflineLocalSession')
            && str_contains($lock, 'cold:');
        $this->record('client local session + cold bootstrap', $ok, $ok ? 'ok' : 'missing');
    }

    private function testBundleIncludesColdAdapters(): void
    {
        $build = (string) file_get_contents(RATEB_ROOT . '/offline/scripts/build-rateb-offline-bundle.php');
        $bundle = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($build, 'offline-local-session-adapter.js')
            && str_contains($build, 'offline-cold-bootstrap-adapter.js')
            && str_contains($bundle, 'RatebOfflineLocalSession')
            && str_contains($bundle, 'RatebOfflineBootstrapManager')
            && str_contains($bundle, '14.2.0');
        $this->record('bundle includes cold adapters (SDK 14.2.0)', $ok, $ok ? 'ok' : 'rebuild needed');
    }

    private function testShellBannerCssNotInlineJs(): void
    {
        $shell = (string) file_get_contents(RATEB_ROOT . '/public/offline-shell.html');
        $local = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/offline-local-session-adapter.js');
        $ok = str_contains($shell, 'rateb-offline-local-session-banner')
            && str_contains($local, 'rateb-offline-local-session-banner')
            && !str_contains($local, 'style.cssText');
        $this->record('offline banner uses CSS class (no inline JS styles)', $ok, $ok ? 'ok' : 'inline css');
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

    private function testQueueReplayUntouched(): void
    {
        $q = RATEB_ROOT . '/offline/client/sync/queue-manager.js';
        $r = RATEB_ROOT . '/offline/client/sync/replay-scheduler.js';
        $engine = RATEB_ROOT . '/offline/server/Services/OfflineReplayEngine.php';
        $ok = is_file($q) && is_file($r) && is_file($engine);
        $this->record('queue/replay files present (untouched surface)', $ok, $ok ? 'ok' : 'missing');
    }

    private function testWarmPathSourceIntact(): void
    {
        $lock = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/auth-lock-adapter.js');
        $ok = str_contains($lock, 'enrollPin')
            && str_contains($lock, 'unlockWithPin')
            && str_contains($lock, 'destroyWarmSession')
            && str_contains($lock, 'sealIdentityPackage');
        $this->record('warm unlock/enroll path intact', $ok, $ok ? 'ok' : 'broken');
    }

    private function testNoPhpSessionOfflineDocs(): void
    {
        $doc = (string) file_get_contents(RATEB_ROOT . '/offline/docs/PHASE_COLD_OFFLINE_IDENTITY.md');
        $svc = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineLocalSessionService.php');
        $ok = str_contains($doc, 'never creates a PHP session')
            || (str_contains($svc, 'creates_php_session') && str_contains($svc, 'false'));
        $this->record('no PHP session offline (policy/docs)', $ok, $ok ? 'ok' : 'missing');
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
    }
}
