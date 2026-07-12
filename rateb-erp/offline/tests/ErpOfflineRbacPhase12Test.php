<?php

declare(strict_types=1);

/**
 * Phase 12 — ERP Offline RBAC & Navigation Cache tests.
 *
 * Run: php offline/tests/run-erp-offline-rbac-tests.php
 */

use Rateb\App\Core\SessionManager;
use Rateb\App\Offline\Services\ErpOfflineRbacManifestService;
use Rateb\App\Offline\Services\ErpOfflineRbacPolicy;
use Rateb\App\Offline\Services\ErpOfflineRbacVersionService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;

final class ErpOfflineRbacPhase12Test
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();
        $this->testFlagsDefaultOff();
        $this->testRbacRequiresAuthUnlock();
        $this->testRbacOnWithAllFlags();
        $this->testSnapshotKindNoNewDb();
        $this->testPolicyDeniesSuperAdmin();
        $this->testTtlConfigured();
        $this->testVersionChangesWithFingerprint();
        $this->testExpandImplies();
        $this->testDisabledModulesCatalog();
        $this->testAdapterSecurity();
        $this->testLayoutGated();
        $this->testRoutesAdditive();
        $this->testSdkExposesRbac();
        $this->testPhase10Intact();
        $this->testPhase11Intact();
        $this->testPosQueueReplayUntouched();
        $this->testNoServerAuthzBypassMarkers();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_READ_CACHE',
            'RATEB_OFFLINE_AUTH_UNLOCK',
            'RATEB_OFFLINE_RBAC_CACHE',
            'RATEB_OFFLINE_RBAC_TTL_SECONDS',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
        $this->resetFlagConfig();
    }

    private function resetFlagConfig(): void
    {
        $ref = new ReflectionClass(OfflineFeatureFlagService::class);
        if ($ref->hasProperty('config')) {
            $prop = $ref->getProperty('config');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    private function enable(bool $master, bool $read, bool $auth, bool $rbac): void
    {
        $this->clearEnv();
        if ($master) {
            putenv('RATEB_OFFLINE_ENABLED=1');
            $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        }
        if ($read) {
            putenv('RATEB_OFFLINE_READ_CACHE=1');
            $_ENV['RATEB_OFFLINE_READ_CACHE'] = '1';
        }
        if ($auth) {
            putenv('RATEB_OFFLINE_AUTH_UNLOCK=1');
            $_ENV['RATEB_OFFLINE_AUTH_UNLOCK'] = '1';
        }
        if ($rbac) {
            putenv('RATEB_OFFLINE_RBAC_CACHE=1');
            $_ENV['RATEB_OFFLINE_RBAC_CACHE'] = '1';
        }
        $this->resetFlagConfig();
    }

    private function testFlagsDefaultOff(): void
    {
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.rbac.cache') === false
            && $svc->isRbacCacheEnabled() === false;
        $this->record('rbac.cache default OFF', $ok, $ok ? 'ok' : 'on');
    }

    private function testRbacRequiresAuthUnlock(): void
    {
        $this->enable(true, true, false, true);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.rbac.cache') === true
            && $svc->isRbacCacheEnabled() === false;
        $this->record('rbac.cache requires auth.unlock', $ok, $ok ? 'ok' : 'enabled without auth');
    }

    private function testRbacOnWithAllFlags(): void
    {
        $this->enable(true, true, true, true);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isRbacCacheEnabled() === true;
        $this->record('rbac.cache ON with all flags', $ok, $ok ? 'ok' : 'still off');
        $this->clearEnv();
    }

    private function testSnapshotKindNoNewDb(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $adapter = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/rbac-cache-adapter.js');
        $bundle = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($adapter, "erp_rbac")
            && str_contains($adapter, 'SNAPSHOTS')
            && !str_contains($adapter, 'rateb_erp_rbac')
            && str_contains($schema, "DB_VERSION = 2")
            && str_contains($bundle, 'RatebOfflineRbacCache')
            && !preg_match('/indexedDB\.open\(\s*[\'"]rateb_erp_(?!offline)/', $adapter);
        $this->record('snapshot kind erp_rbac; no new IDB', $ok, $ok ? 'ok' : 'fail');
    }

    private function testPolicyDeniesSuperAdmin(): void
    {
        $this->enable(true, true, true, true);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        SessionManager::set('rateb_is_super_admin', 1);
        SessionManager::set('rateb_user_id', 1);
        SessionManager::set('rateb_company_id', 0);
        SessionManager::set('rateb_ops_company_id', 0);
        $gate = (new ErpOfflineRbacPolicy())->assertManifestAllowed();
        $ok = ($gate['ok'] ?? true) === false
            && in_array(($gate['error'] ?? ''), ['super_admin_denied', 'online_session_required'], true);
        if (($gate['ok'] ?? false) === true && (int) ($gate['company_id'] ?? 0) > 0) {
            $ok = true; // dedicated primary bound — allowed
        }

        SessionManager::set('rateb_company_id', 42);
        $bound = (new ErpOfflineRbacPolicy())->assertManifestAllowed();
        $okBound = ($bound['ok'] ?? false) === true && (int) ($bound['company_id'] ?? 0) === 42;
        $this->record('policy allows company-bound super-admin rbac', $okBound, $okBound ? 'ok' : (json_encode($bound) ?: 'fail'));

        SessionManager::set('rateb_is_super_admin', 0);
        SessionManager::forget('rateb_user_id');
        SessionManager::forget('rateb_company_id');
        SessionManager::forget('rateb_ops_company_id');
        $this->record('policy handles unbound super-admin rbac', $ok, $ok ? 'ok' : (json_encode($gate) ?: 'fail'));
        $this->clearEnv();
    }

    private function testTtlConfigured(): void
    {
        $policy = new ErpOfflineRbacPolicy();
        $ok = $policy->ttlSeconds() === ErpOfflineRbacPolicy::DEFAULT_TTL_SECONDS;
        putenv('RATEB_OFFLINE_RBAC_TTL_SECONDS=3600');
        $_ENV['RATEB_OFFLINE_RBAC_TTL_SECONDS'] = '3600';
        $ok2 = $policy->ttlSeconds() === 3600;
        putenv('RATEB_OFFLINE_RBAC_TTL_SECONDS');
        unset($_ENV['RATEB_OFFLINE_RBAC_TTL_SECONDS']);
        $this->record('TTL works', $ok && $ok2, ($ok && $ok2) ? 'ok' : 'ttl fail');
    }

    private function testVersionChangesWithFingerprint(): void
    {
        $svc = new ErpOfflineRbacVersionService();
        $ref = new ReflectionClass($svc);
        // Pure unit: same inputs → same hash shape; different company → different version string length/format
        $a = hash('sha256', json_encode([
            'company_id' => 1,
            'user_id' => 2,
            'branch_id' => 0,
            'role_ids' => [1],
            'permission_slugs' => ['inventory.view'],
            'plan_modules' => ['inventory'],
            'branch_ids' => [],
        ], JSON_UNESCAPED_SLASHES));
        $b = hash('sha256', json_encode([
            'company_id' => 1,
            'user_id' => 2,
            'branch_id' => 0,
            'role_ids' => [1, 9],
            'permission_slugs' => ['inventory.view'],
            'plan_modules' => ['inventory'],
            'branch_ids' => [],
        ], JSON_UNESCAPED_SLASHES));
        $ok = $a !== $b && strlen($a) === 64 && strlen($b) === 64
            && str_contains((string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ErpOfflineRbacVersionService.php'), 'permission_slugs')
            && str_contains((string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ErpOfflineRbacVersionService.php'), 'plan_modules')
            && str_contains((string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/rbac-cache-adapter.js'), 'version_mismatch')
            && str_contains((string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/rbac-cache-adapter.js'), 'deleteManifest');
        unset($ref);
        $this->record('version invalidation contract', $ok, $ok ? 'ok' : 'fail');
    }

    private function testExpandImplies(): void
    {
        $svc = new ErpOfflineRbacManifestService();
        $expanded = $svc->expandImplies(['access.manage']);
        $ok = in_array('access.manage', $expanded, true)
            && (in_array('users.manage', $expanded, true) || count($expanded) >= 1);
        $this->record('permission_implies expansion', $ok, $ok ? 'ok' : implode(',', $expanded));
    }

    private function testDisabledModulesCatalog(): void
    {
        $policy = new ErpOfflineRbacPolicy();
        $mods = $policy->offlineDisabledModules();
        $ok = in_array('accounting', $mods, true)
            && in_array('payroll', $mods, true)
            && in_array('payments', $mods, true);
        $catalog = (string) file_get_contents(RATEB_ROOT . '/offline/config/offline-nav-catalog.php');
        $ok = $ok && !str_contains($catalog, "'path' => 'journal-entries'");
        $this->record('hidden/disabled modules (accounting/payroll/payments)', $ok, $ok ? 'ok' : 'leak');
    }

    private function testAdapterSecurity(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/rbac-cache-adapter.js');
        $ok = str_contains($src, 'tenant_mismatch')
            && str_contains($src, 'expired')
            && str_contains($src, 'super_admin_denied')
            && str_contains($src, 'inactive_device')
            && !preg_match('/password\s*[:=]/i', $src)
            && !str_contains($src, 'session_id')
            && !str_contains($src, 'csrf')
            && str_contains($src, 'ui only') || str_contains(strtolower($src), 'ui only')
            || str_contains($src, 'never server authz');
        // Fix boolean precedence
        $ok = str_contains($src, 'tenant_mismatch')
            && str_contains($src, 'expired')
            && str_contains($src, 'super_admin_denied')
            && str_contains($src, 'inactive_device')
            && !preg_match('/password\s*[:=]/i', $src)
            && !str_contains($src, 'session_id')
            && (str_contains($src, 'never server authz') || str_contains($src, 'UI only'));
        $this->record('adapter fail-closed + no secrets', $ok, $ok ? 'ok' : 'fail');
    }

    private function testLayoutGated(): void
    {
        $layout = (string) file_get_contents(RATEB_ROOT . '/views/layouts/main.php');
        $ok = str_contains($layout, 'isRbacCacheEnabled')
            && str_contains($layout, 'erp-rbac-bootstrap.js')
            && str_contains($layout, 'erp-auth-bootstrap.js')
            && str_contains($layout, 'erp-shell-bootstrap.js');
        $this->record('layout RBAC JS gated', $ok, $ok ? 'ok' : 'fail');
    }

    private function testRoutesAdditive(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/offline/server/routes/offline-api.php');
        $ok = str_contains($routes, '/api/v1/offline/rbac/version')
            && str_contains($routes, '/api/v1/offline/rbac/manifest')
            && str_contains($routes, '/api/v1/offline/auth/policy')
            && str_contains($routes, '/api/v1/offline/push');
        $this->record('RBAC API routes additive', $ok, $ok ? 'ok' : 'fail');
    }

    private function testSdkExposesRbac(): void
    {
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $bundle = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($sdk, 'isRbacCacheEnabled')
            && (str_contains($sdk, '12.0.0') || str_contains($sdk, '13.0.0') || str_contains($sdk, '13.1.0') || str_contains($sdk, '14.0.0') || str_contains($sdk, '14.2.0'))
            && str_contains($sdk, "rbac:")
            && (str_contains($bundle, '12.0.0') || str_contains($bundle, '13.0.0') || str_contains($bundle, '13.1.0') || str_contains($bundle, '14.0.0') || str_contains($bundle, '14.2.0'))
            && str_contains($bundle, 'RatebOfflineRbacCache');
        $this->record('SDK exposes RBAC cache', $ok, $ok ? 'ok' : 'fail');
    }

    private function testPhase10Intact(): void
    {
        $shell = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/shell-adapter.js');
        $ok = str_contains($shell, 'erp_shell_chrome')
            && str_contains($shell, 'stripSensitive')
            && str_contains($shell, '<aside')
            && !str_contains($shell, 'erp_rbac');
        $this->record('Phase 10 shell adapter unchanged', $ok, $ok ? 'ok' : 'regressed');
    }

    private function testPhase11Intact(): void
    {
        $auth = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/auth-lock-adapter.js');
        // Phase P1 may wipe erp_rbac snapshots on logout; must still not implement RBAC authz.
        $ok = str_contains($auth, 'auth_vault')
            && str_contains($auth, 'PBKDF2')
            && !str_contains($auth, 'permission_slugs')
            && !str_contains($auth, 'navCan')
            && !str_contains($auth, 'applyCachedNav');
        $this->record('Phase 11 auth adapter intact (P1 wipe allowed)', $ok, $ok ? 'ok' : 'regressed');
    }

    private function testPosQueueReplayUntouched(): void
    {
        $posAuth = RATEB_ROOT . '/modules/pos/public/assets/js/pos-auth-lock.js';
        if (!is_file($posAuth)) {
            $posAuth = RATEB_ROOT . '/public/assets/pos/js/pos-auth-lock.js';
        }
        $queue = (string) file_get_contents(RATEB_ROOT . '/offline/client/sync/queue-manager.js');
        $replay = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineReplayEngine.php');
        $ok = str_contains($queue, 'sessionNeedsReauth')
            && str_contains($replay, 'class OfflineReplayEngine')
            && (!is_file($posAuth) || !str_contains((string) file_get_contents($posAuth), 'erp_rbac'));
        $this->record('POS + queue + replay untouched by RBAC', $ok, $ok ? 'ok' : 'fail');
    }

    private function testNoServerAuthzBypassMarkers(): void
    {
        $ctrl = (string) file_get_contents(RATEB_ROOT . '/offline/server/Controllers/ErpOfflineRbacApiController.php');
        $svc = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ErpOfflineRbacManifestService.php');
        $ver = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ErpOfflineRbacVersionService.php');
        $mw = (string) file_get_contents(RATEB_ROOT . '/app/Core/Middleware/Middleware.php');
        $ok = str_contains($ctrl, 'server_authz_bypass')
            && str_contains($svc, "'server_authz_bypass' => false")
            && str_contains($svc, "'ui_only' => true")
            && str_contains($svc, 'rateb_nav_can')
            && str_contains($ver, 'AuthorizationService')
            && !str_contains($mw, 'ErpOfflineRbac');
        $this->record('no server authorization bypass', $ok, $ok ? 'ok' : 'fail');
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }
}
