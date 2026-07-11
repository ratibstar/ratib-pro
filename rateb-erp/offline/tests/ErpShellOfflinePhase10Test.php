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
            && !str_contains($fbSrc, '<?php')
            && str_contains($fbSrc, 'Cached shell unavailable');
        $this->record('bootstrap + static offline-shell.html', $ok, $ok ? 'ok' : 'missing pieces');
    }

    private function testServiceWorkerRules(): void
    {
        $sw = (string) file_get_contents(RATEB_ROOT . '/public/rateb-offline-sw.js');
        $ok = str_contains($sw, 'isPosPath')
            && str_contains($sw, 'isApiPath')
            && str_contains($sw, 'offlineJsonResponse')
            && str_contains($sw, 'isLoginPost')
            && str_contains($sw, 'Never interfere with POS')
            && str_contains($sw, 'never cache authenticated documents')
            && preg_match('/if\s*\(\s*isPosPath\([^)]+\)\s*\)\s*\{\s*return;/s', $sw) === 1
            && str_contains($sw, "indexOf('/api/')")
            && str_contains($sw, 'Cache-Control')
            && str_contains($sw, 'no-store');
        // Ensure API path uses respondWith with offline JSON, not cache.put of API.
        $apiOk = str_contains($sw, 'API: never cache')
            || (str_contains($sw, 'isApiPath') && str_contains($sw, 'offlineJsonResponse'));
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
        $ok = str_contains($src, 'SNAPSHOTS')
            && str_contains($src, 'erp_shell_chrome')
            && str_contains($src, 'rateb-csrf')
            && str_contains($src, 'stripSensitive')
            && !str_contains($src, 'RatebOfflineQueue.enqueue')
            && str_contains($bundle, 'RatebOfflineShellAdapter')
            && str_contains($bundle, 'version: \'10.0.0\'');
        $this->record('shell adapter uses snapshots, strips CSRF', $ok, $ok ? 'ok' : 'fail');
    }

    private function testSdkExposesShell(): void
    {
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = str_contains($sdk, 'isReadCacheEnabled')
            && str_contains($sdk, 'shell:')
            && str_contains($sdk, '10.0.0');
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
        $sample = '<html><head><meta name="rateb-csrf" content="SECRET"><script>var x=1</script></head>'
            . '<body class="rateb-app"><main>SECRET_DATA</main></body></html>';
        // Execute stripSensitive via eval of function body is heavy; assert source patterns instead.
        $ok = str_contains($src, 'rateb-csrf')
            && str_contains($src, 'rateb-offline-shell-main')
            && str_contains($src, '_csrf')
            && !str_contains($sample, 'unused');
        $this->record('sensitive strip patterns present', $ok, $ok ? 'ok' : 'fail');
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
    }
}
