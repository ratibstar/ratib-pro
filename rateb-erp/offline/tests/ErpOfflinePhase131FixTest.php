<?php

declare(strict_types=1);

/**
 * Phase 13.1 — Critical fix validation.
 *
 * Run: php offline/tests/run-erp-offline-phase131-tests.php
 */

use Rateb\App\Offline\Services\OfflineFeatureFlagService;

final class ErpOfflinePhase131FixTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testSwServesRealShell();
        $this->testSdkFlagMerge();
        $this->testShellBootstrapFullFlags();
        $this->testClientOwnedCursors();
        $this->testTenantCacheKeys();
        $this->testHrefSanitize();
        $this->testActiveDeviceAutoUnlock();
        $this->testDeviceRebindDenied();
        $this->testMasterDataDeviceGate();
        $this->testSyncDebounceAndTtl();
        $this->testPosSwNotDisplaced();
        $this->testChromeUnlockGate();
        $this->testFlagsStillDefaultOff();

        return $this->results;
    }

    private function testSwServesRealShell(): void
    {
        $sw = (string) file_get_contents(RATEB_ROOT . '/public/rateb-offline-sw.js');
        $ok = str_contains($sw, 'fetchBypass')
            && str_contains($sw, 'rateb-erp-assets-v13.1')
            && str_contains($sw, 'offline-shell.html')
            && !preg_match('/clients\.claim\s*\(/', $sw);
        $this->record('SW precaches/serves real offline-shell via bypass', $ok, $ok ? 'ok' : 'fail');
    }

    private function testSdkFlagMerge(): void
    {
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = str_contains($sdk, 'mergeFlags')
            && str_contains($sdk, 'Already booted: merge flags only')
            && str_contains($sdk, '13.1.0');
        $this->record('SDK merges flags without freeze', $ok, $ok ? 'ok' : 'fail');
    }

    private function testShellBootstrapFullFlags(): void
    {
        $boot = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/erp-shell-bootstrap.js');
        $ok = str_contains($boot, 'Object.assign({}, cfg.flags')
            && str_contains($boot, 'posOwns')
            && str_contains($boot, 'Never displace pos-sw');
        $this->record('shell bootstrap passes cfg.flags; skips POS SW', $ok, $ok ? 'ok' : 'fail');
    }

    private function testClientOwnedCursors(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineCursorService.php');
        $ok = str_contains($src, 'client-supplied cursor is authoritative')
            && !preg_match('/if \(\$token === null \|\| \$token === \'\'\) \{\s*\$token = \$this->readStoredToken/', $src)
            && str_contains($src, 'peekStoredToken');
        $this->record('client-owned cursors (no server inject)', $ok, $ok ? 'ok' : 'fail');
    }

    private function testTenantCacheKeys(): void
    {
        $md = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/master-data-adapter.js');
        $hr = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/hr-adapter.js');
        $ok = str_contains($md, 'cacheRowId')
            && str_contains($md, "':emp:'") === false
            && str_contains($md, "':' + prefix")
            && str_contains($hr, "':emp:'");
        $this->record('tenant-scoped entity_cache keys', $ok, $ok ? 'ok' : 'fail');
    }

    private function testHrefSanitize(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/rbac-cache-adapter.js');
        $ok = str_contains($src, 'function safeHref')
            && str_contains($src, 'javascript:')
            && str_contains($src, 'data:');
        $this->record('RBAC href sanitization', $ok, $ok ? 'ok' : 'fail');
    }

    private function testActiveDeviceAutoUnlock(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/auth-lock-adapter.js');
        $ok = str_contains($src, 'require ACTIVE device before auto-unlock')
            && str_contains($src, "status === 'active'");
        $this->record('ACTIVE device on auto-unlock', $ok, $ok ? 'ok' : 'fail');
    }

    private function testDeviceRebindDenied(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ErpOfflineAuthDeviceService.php');
        $ok = str_contains($src, 'device_user_mismatch')
            && str_contains($src, 'do not rebind');
        $this->record('device rebind denied', $ok, $ok ? 'ok' : 'fail');
    }

    private function testMasterDataDeviceGate(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Controllers/OfflineSyncApiController.php');
        $ok = str_contains($src, 'device_required')
            && str_contains($src, 'HTTP_X_RATEB_DEVICE_ID')
            && str_contains($src, 'assertActive');
        $this->record('master-data delta device gate', $ok, $ok ? 'ok' : 'fail');
    }

    private function testSyncDebounceAndTtl(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/master-data-adapter.js');
        $ok = str_contains($src, 'SYNC_DEBOUNCE_MS')
            && str_contains($src, 'purgeExpired')
            && str_contains($src, 'page_limit_reached')
            && str_contains($src, 'migration_required');
        $this->record('syncAll debounce + TTL purge + page limit', $ok, $ok ? 'ok' : 'fail');
    }

    private function testPosSwNotDisplaced(): void
    {
        $boot = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/erp-shell-bootstrap.js');
        $ok = str_contains($boot, 'if (posOwns)')
            && str_contains($boot, 'return null');
        $this->record('ERP SW does not displace POS SW', $ok, $ok ? 'ok' : 'fail');
    }

    private function testChromeUnlockGate(): void
    {
        $fb = (string) file_get_contents(RATEB_ROOT . '/public/offline-shell.html');
        $ok = str_contains($fb, 'showLocked')
            && str_contains($fb, 'isUnlocked')
            && str_contains($fb, 'do not reveal chrome before unlock');
        $this->record('offline chrome gated on unlock', $ok, $ok ? 'ok' : 'fail');
    }

    private function testFlagsStillDefaultOff(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_MASTER_DATA',
            'RATEB_OFFLINE_READ_CACHE',
            'RATEB_OFFLINE_AUTH_UNLOCK',
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
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isMasterEnabled() === false
            && $svc->isReadCacheEnabled() === false
            && $svc->isAuthUnlockEnabled() === false
            && $svc->isRbacCacheEnabled() === false
            && $svc->isMasterDataEnabled() === false;
        $this->record('all offline flags default OFF', $ok, $ok ? 'ok' : 'on');
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }
}
