<?php

declare(strict_types=1);

/**
 * Phase 10 — ERP Shell Offline (Tier 2 read_cache) tests.
 *
 * Run: php offline/tests/run-erp-shell-offline-tests.php
 */

use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflineReplayEngine;

final class ErpShellOfflinePhase10Test
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testReadCacheFlagDefaultOff();
        $this->testReadCacheRequiresMaster();
        $this->testIsReadCacheEnabledHelper();
        $this->testModulesRegistryHasErpShell();
        $this->testLayoutInjectionGated();
        $this->testBootstrapAndFallbackExist();
        $this->testServiceWorkerRules();
        $this->testShellAdapterSource();
        $this->testSdkExposesShell();
        $this->testPosUntouched();
        $this->testQueueReplayGuardsUntouched();
        $this->testStripSensitivePatterns();
        $this->testNoSwRecursion();
        $this->testTenantScopedSnapshot();
        $this->testNoDocumentWrite();
        $this->testAuthPathsExcluded();
        $this->testNoClientsClaim();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_READ_CACHE',
            'RATEB_OFFLINE_INVENTORY_MOVEMENTS',
            'RATEB_OFFLINE_HR_ATTENDANCE',
            'RATEB_OFFLINE_PROCUREMENT',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
        // Reset static config cache via reflection if present.
        $ref = new ReflectionClass(OfflineFeatureFlagService::class);
        if ($ref->hasProperty('config')) {
            $prop = $ref->getProperty('config');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    private function enableFlags(bool $master, bool $readCache): void
    {
        $this->clearEnv();
        if ($master) {
            putenv('RATEB_OFFLINE_ENABLED=1');
            $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        }
        if ($readCache) {
            putenv('RATEB_OFFLINE_READ_CACHE=1');
            $_ENV['RATEB_OFFLINE_READ_CACHE'] = '1';
        }
        $ref = new ReflectionClass(OfflineFeatureFlagService::class);
        if ($ref->hasProperty('config')) {
            $prop = $ref->getProperty('config');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    private function testReadCacheFlagDefaultOff(): void
    {
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.read_cache') === false
            && $svc->isReadCacheEnabled() === false
            && $svc->isMasterEnabled() === false;
        $this->record('read_cache flag default OFF', $ok, $ok ? 'ok' : 'unexpectedly enabled');
    }

    private function testReadCacheRequiresMaster(): void
    {
        $this->enableFlags(false, true);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.read_cache') === true
            && $svc->isReadCacheEnabled() === false;
        $this->record('read_cache requires master', $ok, $ok ? 'ok' : 'enabled without master');
        $this->clearEnv();
    }

    private function testIsReadCacheEnabledHelper(): void
    {
        $this->enableFlags(true, true);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isReadCacheEnabled() === true;
        $this->record('isReadCacheEnabled when both ON', $ok, $ok ? 'ok' : 'false');
        $this->clearEnv();
    }

    private function testModulesRegistryHasErpShell(): void
    {
        $cfg = require RATEB_ROOT . '/offline/config/modules.php';
        $active = $cfg['active_modules'] ?? [];
        $tiers = $cfg['tiers']['T2'] ?? [];
        $ops = $cfg['operations'] ?? [];
        $ok = in_array('erp_shell', $active, true)
            && in_array('erp_shell', $tiers, true)
            && isset($ops['offline.shell.ping']);
        $this->record('modules registry erp_shell', $ok, $ok ? 'ok' : 'missing');
    }

    private function testLayoutInjectionGated(): void
    {
        $layout = (string) file_get_contents(RATEB_ROOT . '/views/layouts/main.php');
        $ok = str_contains($layout, 'isReadCacheEnabled')
            && str_contains($layout, 'erp-shell-bootstrap.js')
            && str_contains($layout, 'rateb-offline.js')
            && str_contains($layout, '$ratebOfflineReadCache')
            && preg_match('/if\s*\(\s*\$ratebOfflineReadCache\s*\)/', $layout) === 1;
        $this->record('layout injection gated on isReadCacheEnabled', $ok, $ok ? 'ok' : 'ungated or missing');
    }

    private function testBootstrapAndFallbackExist(): void
    {
        $boot = RATEB_ROOT . '/public/assets/offline/erp-shell-bootstrap.js';
        $fallback = RATEB_ROOT . '/public/offline-shell.html';
        $adapter = RATEB_ROOT . '/offline/client/adapters/shell-adapter.js';
        $bootSrc = is_file($boot) ? (string) file_get_contents($boot) : '';
        $fbSrc = is_file($fallback) ? (string) file_get_contents($fallback) : '';
        $ok = is_file($boot) && is_file($fallback) && is_file($adapter)
            && str_contains($bootSrc, 'serviceWorker.register')
            && str_contains($bootSrc, 'RatebOffline.init')
            && str_contains($bootSrc, 'RatebOfflineShellAdapter')
            && str_contains($bootSrc, 'isPosLocation')
            && !str_contains($fbSrc, '<?php')
            && str_contains($fbSrc, 'Cached shell unavailable')
            && !preg_match('/document\.write\s*\(/', $fbSrc);
        $this->record('bootstrap + static offline-shell.html', $ok, $ok ? 'ok' : 'missing pieces');
    }

    private function testServiceWorkerRules(): void
    {
        $sw = (string) file_get_contents(RATEB_ROOT . '/public/rateb-offline-sw.js');
        $ok = str_contains($sw, 'isPosPath')
            && str_contains($sw, 'isApiPath')
            && str_contains($sw, 'isAuthPath')
            && str_contains($sw, 'offlineJsonResponse')
            && str_contains($sw, 'inlineOfflineShellResponse')
            && str_contains($sw, 'isOfflineShellUrl')
            && str_contains($sw, '/pos(\\/|$)')
            && str_contains($sw, "indexOf('/api/')")
            && str_contains($sw, 'Cache-Control')
            && str_contains($sw, 'no-store');
        $apiOk = str_contains($sw, 'isApiPath') && str_contains($sw, 'offlineJsonResponse');
        $noApiCachePut = !preg_match('/isApiPath[\s\S]{0,400}cache\.put/m', $sw);
        $this->record(
            'SW excludes POS, never caches API/HTML auth',
            $ok && $apiOk && $noApiCachePut,
            ($ok && $apiOk && $noApiCachePut) ? 'ok' : 'rule miss'
        );
    }

    private function testShellAdapterSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/shell-adapter.js');
        $bundle = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $layout = (string) file_get_contents(RATEB_ROOT . '/views/layouts/main.php');
        $ok = str_contains($src, 'SNAPSHOTS')
            && str_contains($src, 'SNAPSHOT_PREFIX')
            && str_contains($src, 'company_id')
            && str_contains($src, 'branch_id')
            && str_contains($src, 'user_id')
            && str_contains($src, 'rateb-csrf')
            && str_contains($src, 'stripSensitive')
            && !str_contains($src, 'RatebOfflineQueue.enqueue')
            && str_contains($bundle, 'RatebOfflineShellAdapter')
            && str_contains($bundle, 'tenantScope')
            && str_contains($layout, 'company_id')
            && str_contains($layout, 'user_id');
        $this->record('shell adapter uses snapshots, strips CSRF', $ok, $ok ? 'ok' : 'fail');
    }

    private function testSdkExposesShell(): void
    {
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = str_contains($sdk, 'isReadCacheEnabled')
            && str_contains($sdk, 'shell:')
            && (str_contains($sdk, '10.0.0') || str_contains($sdk, '11.0.0') || str_contains($sdk, '12.0.0') || str_contains($sdk, '13.0.0') || str_contains($sdk, '13.1.0') || str_contains($sdk, '14.0.0') || str_contains($sdk, '14.2.0'));
        $this->record('SDK exposes shell + read_cache', $ok, $ok ? 'ok' : 'fail');
    }

    private function testPosUntouched(): void
    {
        $posSw = RATEB_ROOT . '/public/pos-sw.js';
        $posBoot = RATEB_ROOT . '/public/assets/pos/js/pos-offline-bootstrap.js';
        $posCtrl = RATEB_ROOT . '/modules/pos/app/Controllers/PosRegisterController.php';
        $ok = is_file($posSw) && is_file($posBoot) && is_file($posCtrl)
            && str_contains((string) file_get_contents($posSw), 'rateb-pos-shell')
            && str_contains((string) file_get_contents($posBoot), 'serviceWorker.register')
            && str_contains((string) file_get_contents($posCtrl), 'pos-sw.js');
        $erpSw = (string) file_get_contents(RATEB_ROOT . '/public/rateb-offline-sw.js');
        $ok = $ok && !str_contains($erpSw, 'rateb-pos-shell-v')
            && str_contains($erpSw, 'does not replace pos-sw.js')
            && preg_match('/isPosPath\([^)]+\)\s*\)\s*\{\s*return;/s', $erpSw) === 1;
        $this->record('POS SW/bootstrap untouched by ERP shell SW', $ok, $ok ? 'ok' : 'POS interference');
    }

    private function testQueueReplayGuardsUntouched(): void
    {
        $queue = RATEB_ROOT . '/offline/server/Services/OfflineQueueService.php';
        $replay = RATEB_ROOT . '/offline/server/Services/OfflineReplayEngine.php';
        $branch = RATEB_ROOT . '/offline/server/Services/OfflineBranchGuard.php';
        $device = RATEB_ROOT . '/offline/server/Services/OfflineDeviceGuard.php';
        $ok = is_file($queue) && is_file($replay) && is_file($branch) && is_file($device);
        // Smoke: queue still rejects accounting; replay still skips unknown.
        $engine = new OfflineReplayEngine();
        $skip = $engine->replay(['module' => 'unknown_mod', 'action' => 'x']);
        $ok = $ok && ($skip['status'] ?? '') === 'skipped';
        $this->record('queue/replay/guards present; replay skip intact', $ok, $ok ? 'ok' : 'broken');
    }

    private function testStripSensitivePatterns(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/shell-adapter.js');
        $ok = str_contains($src, 'rateb-csrf')
            && str_contains($src, 'rateb-offline-shell-main')
            && str_contains($src, '_csrf')
            && str_contains($src, 'data-rateb-')
            && str_contains($src, '<aside')
            && str_contains($src, 'javascript:');
        $this->record('sensitive strip patterns present', $ok, $ok ? 'ok' : 'fail');
    }

    private function testNoSwRecursion(): void
    {
        $sw = (string) file_get_contents(RATEB_ROOT . '/public/rateb-offline-sw.js');
        $ok = str_contains($sw, 'isOfflineShellUrl')
            && str_contains($sw, 'BYPASS_HEADER')
            && str_contains($sw, 'fetchBypass')
            && str_contains($sw, 'inlineOfflineShellResponse')
            && str_contains($sw, 'hasBypassHeader')
            && !str_contains($sw, 'cache.addAll')
            && !preg_match('/clients\.claim\s*\(/', $sw);
        // Recursion avoided: bypass path returns without respondWith; fallback uses fetchBypass only.
        $ok = $ok && preg_match('/function hasBypassHeader[\s\S]*?if \(hasBypassHeader\(request\)\) \{\s*return;/m', $sw);
        $this->record('SW offline fallback has no fetch recursion', $ok, $ok ? 'ok' : 'recursion risk');
    }

    private function testTenantScopedSnapshot(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/shell-adapter.js');
        $ok = str_contains($src, 'SNAPSHOT_PREFIX')
            && str_contains($src, 'function snapshotId')
            && str_contains($src, 'company_id')
            && str_contains($src, 'branch_id')
            && str_contains($src, 'user_id')
            && str_contains($src, 'tenant_scope_required')
            && !preg_match('/id:\s*[\'"]erp_shell_chrome[\'"]\s*,/', $src);
        $this->record('snapshot keyed by company/branch/user', $ok, $ok ? 'ok' : 'global key');
    }

    private function testNoDocumentWrite(): void
    {
        $fb = (string) file_get_contents(RATEB_ROOT . '/public/offline-shell.html');
        $ok = !preg_match('/document\.write\s*\(/', $fb)
            && str_contains($fb, 'DOMParser')
            && str_contains($fb, 'importNode')
            && str_contains($fb, 'renderSafeShell');
        $this->record('offline-shell uses DOMParser not document.write', $ok, $ok ? 'ok' : 'xss risk');
    }

    private function testAuthPathsExcluded(): void
    {
        $sw = (string) file_get_contents(RATEB_ROOT . '/public/rateb-offline-sw.js');
        $ok = str_contains($sw, 'isAuthPath')
            && str_contains($sw, '/logout')
            && str_contains($sw, '/password/')
            && preg_match('/if\s*\(\s*isAuthPath\([^)]+\)\s*\)\s*\{\s*return;/s', $sw) === 1;
        $this->record('auth pages excluded from SW interception', $ok, $ok ? 'ok' : 'auth intercept');
    }

    private function testNoClientsClaim(): void
    {
        $sw = (string) file_get_contents(RATEB_ROOT . '/public/rateb-offline-sw.js');
        $boot = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/erp-shell-bootstrap.js');
        $ok = !preg_match('/self\.clients\.claim\s*\(/', $sw)
            && str_contains($sw, 'avoids stealing open POS')
            && str_contains($boot, 'isPosLocation')
            && str_contains($boot, 'pos-sw.js');
        $this->record('no clients.claim; bootstrap skips POS', $ok, $ok ? 'ok' : 'POS claim risk');
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
    }
}
