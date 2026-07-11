<?php

declare(strict_types=1);

/**
 * Phase 14 — Enterprise Daily Ops Offline Pilot smoke tests.
 *
 * Run: php offline/tests/run-erp-offline-phase14-tests.php
 */

use Rateb\App\Offline\OfflineModule;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;

final class ErpOfflinePhase14PilotTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testPilotFlagDefaultOff();
        $this->testPilotOpsPagesRequiresReadCache();
        $this->testOpsAllowlistConfig();
        $this->testQueueMaxEnforcedInSource();
        $this->testOpsFormsAdapterPresent();
        $this->testShellOpsPageCapture();
        $this->testMasterDataPickerApis();
        $this->testSwOpsPageCoexist();
        $this->testLayoutInjection();
        $this->testSdkBundlePhase14();
        $this->testPilotDocExists();
        $this->testNoReplayEngineRedesign();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_READ_CACHE',
            'RATEB_OFFLINE_PILOT_OPS_PAGES',
            'RATEB_OFFLINE_INVENTORY_MOVEMENTS',
            'RATEB_OFFLINE_HR_ATTENDANCE',
            'RATEB_OFFLINE_PROCUREMENT',
            'RATEB_OFFLINE_MASTER_DATA',
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

    private function enable(array $env): void
    {
        $this->clearEnv();
        foreach ($env as $k => $v) {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
        $ref = new ReflectionClass(OfflineFeatureFlagService::class);
        if ($ref->hasProperty('config')) {
            $prop = $ref->getProperty('config');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . "  {$name}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    }

    private function testPilotFlagDefaultOff(): void
    {
        $this->clearEnv();
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.pilot.ops_pages') === false
            && $svc->isPilotOpsPagesEnabled() === false
            && $svc->snapshot()['offline.pilot.ops_pages'] === false;
        $this->record('pilot.ops_pages defaults OFF', $ok, $ok ? 'ok' : 'flag on unexpectedly');
    }

    private function testPilotOpsPagesRequiresReadCache(): void
    {
        $this->enable([
            'RATEB_OFFLINE_ENABLED' => '1',
            'RATEB_OFFLINE_PILOT_OPS_PAGES' => '1',
        ]);
        $svc = new OfflineFeatureFlagService();
        $withoutRead = $svc->isPilotOpsPagesEnabled() === false;

        $this->enable([
            'RATEB_OFFLINE_ENABLED' => '1',
            'RATEB_OFFLINE_READ_CACHE' => '1',
            'RATEB_OFFLINE_PILOT_OPS_PAGES' => '1',
        ]);
        $svc2 = new OfflineFeatureFlagService();
        $withRead = $svc2->isPilotOpsPagesEnabled() === true;
        $ok = $withoutRead && $withRead;
        $this->record('pilot.ops_pages requires master+read_cache', $ok, $ok ? 'ok' : 'gate wrong');
    }

    private function testOpsAllowlistConfig(): void
    {
        $cfg = OfflineModule::opsPageAllowlist();
        $paths = $cfg['paths'] ?? [];
        $hooks = $cfg['form_hooks'] ?? [];
        $joined = implode(',', array_map('strval', $paths));
        $ok = in_array('stock-movements', $paths, true)
            && in_array('hr/attendance', $paths, true)
            && in_array('purchase-requests', $paths, true)
            && !str_contains($joined, 'accounting')
            && !str_contains($joined, 'payroll')
            && !str_contains($joined, 'payment')
            && is_array($hooks)
            && count($hooks) >= 5;
        $this->record('ops allowlist excludes accounting/payroll/payments', $ok, $ok ? 'ok' : 'allowlist bad');
    }

    private function testQueueMaxEnforcedInSource(): void
    {
        $qm = (string) file_get_contents(RATEB_ROOT . '/offline/client/sync/queue-manager.js');
        $policy = OfflineModule::syncPolicy();
        $ok = str_contains($qm, 'client_queue_full')
            && str_contains($qm, 'clientQueueMax')
            && (int) ($policy['client_queue_max'] ?? 0) === 500;
        $this->record('client_queue_max enforced (500)', $ok, $ok ? 'ok' : 'missing enforcement');
    }

    private function testOpsFormsAdapterPresent(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/ops-forms-adapter.js');
        $boot = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/erp-ops-forms-bootstrap.js');
        $ok = str_contains($src, 'RatebOfflineOpsForms')
            && str_contains($src, 'enqueueMovement')
            && str_contains($src, 'enqueueAttendance')
            && str_contains($src, 'enqueuePurchaseRequestDraft')
            && str_contains($boot, 'hydratePickers')
            && str_contains($boot, 'RatebOfflineOpsForms');
        $this->record('ops forms adapter + bootstrap', $ok, $ok ? 'ok' : 'missing hooks');
    }

    private function testShellOpsPageCapture(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/shell-adapter.js');
        $ok = str_contains($src, 'captureOpsPage')
            && str_contains($src, 'stripSensitiveOpsPage')
            && str_contains($src, 'غير متصل')
            && str_contains($src, 'erp_ops_page')
            && str_contains($src, 'CACHE_ERP_OPS_PAGE');
        $this->record('shell ops page capture + offline badge', $ok, $ok ? 'ok' : 'missing capture');
    }

    private function testMasterDataPickerApis(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/master-data-adapter.js');
        $ok = str_contains($src, 'function listCached')
            && str_contains($src, 'function pickerOptions')
            && str_contains($src, 'listCached: listCached')
            && str_contains($src, 'pickerOptions: pickerOptions');
        $this->record('master-data picker APIs', $ok, $ok ? 'ok' : 'missing listCached');
    }

    private function testSwOpsPageCoexist(): void
    {
        $sw = (string) file_get_contents(RATEB_ROOT . '/public/rateb-offline-sw.js');
        $pos = (string) file_get_contents(RATEB_ROOT . '/public/pos-sw.js');
        $ok = str_contains($sw, 'OPS_PAGE_CACHE')
            && str_contains($sw, 'opsPageFallback')
            && !preg_match('/clients\.claim\s*\(/', $sw)
            && str_contains($pos, 'ERP_OPS_PAGE_CACHE')
            && str_contains($pos, 'erpOpsPageFallback')
            && str_contains($pos, 'CACHE_ERP_OPS_PAGE');
        $this->record('SW ops page + POS coexist', $ok, $ok ? 'ok' : 'sw missing ops');
    }

    private function testLayoutInjection(): void
    {
        $layout = (string) file_get_contents(RATEB_ROOT . '/views/layouts/main.php');
        $ok = str_contains($layout, 'ops_page_paths')
            && str_contains($layout, 'client_queue_max')
            && str_contains($layout, 'erp-ops-forms-bootstrap.js')
            && str_contains($layout, 'isPilotOpsPagesEnabled')
            && str_contains($layout, 'rateb-offline-sync-badge') === false; // badge is JS-created
        $shell = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/erp-shell-bootstrap.js');
        $ok = $ok && str_contains($shell, 'rateb-offline-sync-badge')
            && str_contains($shell, 'clientQueueMax');
        $this->record('layout + sync badge wiring', $ok, $ok ? 'ok' : 'injection missing');
    }

    private function testSdkBundlePhase14(): void
    {
        $bundle = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $min = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.min.js');
        $ok = (str_contains($bundle, 'Phase 14.0.0') || str_contains($bundle, 'Phase 14.2.0'))
            && str_contains($bundle, '/* ---- ops-forms-adapter.js ---- */')
            && str_contains($bundle, 'RatebOfflineOpsForms')
            && str_contains($bundle, 'client_queue_full')
            && str_contains($bundle, 'listCached')
            && str_contains($bundle, 'offline.pilot.ops_pages')
            && str_contains($min, 'RatebOfflineOpsForms');
        $this->record('SDK bundle Phase 14', $ok, $ok ? 'ok' : 'bundle stale');
    }

    private function testPilotDocExists(): void
    {
        $doc = RATEB_ROOT . '/offline/docs/PHASE_14_ENTERPRISE_DAILY_OPS_PILOT.md';
        $txt = is_file($doc) ? (string) file_get_contents($doc) : '';
        $ok = $txt !== ''
            && str_contains($txt, 'Pilot matrix')
            && str_contains($txt, 'RATEB_OFFLINE_PILOT_OPS_PAGES')
            && str_contains($txt, 'Soak')
            && str_contains($txt, 'Accounting');
        $this->record('Phase 14 pilot doc', $ok, $ok ? 'ok' : 'doc missing');
    }

    private function testNoReplayEngineRedesign(): void
    {
        // Smoke: ReplayEngine still routes by module flags — not rewritten for Phase 14.
        $engine = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineReplayEngine.php');
        $ok = str_contains($engine, 'offline.inventory.movements')
            && str_contains($engine, 'offline.hr.attendance')
            && str_contains($engine, 'offline.procurement')
            && !str_contains($engine, 'pilot.ops_pages');
        $this->record('ReplayEngine unchanged (no pilot rewrite)', $ok, $ok ? 'ok' : 'engine touched wrongly');
    }
}
