<?php

declare(strict_types=1);

/**
 * Phase 13 — Enterprise Master Data Delta Sync tests.
 *
 * Run: php offline/tests/run-erp-offline-master-data-tests.php
 */

use Rateb\App\Offline\Services\ErpOfflineMasterDataPolicy;
use Rateb\App\Offline\Services\OfflineDeltaCursorCodec;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflineReplayEngine;

final class ErpOfflineMasterDataPhase13Test
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();
        $this->testFlagsDefaultOff();
        $this->testMasterDataRequiresMaster();
        $this->testMasterDataOn();
        $this->testCursorNormalization();
        $this->testLegacyCursorParse();
        $this->testSoftDeleteHelpers();
        $this->testAllowlist();
        $this->testUnknownEntityRejectedInCursorService();
        $this->testFieldRedactionSource();
        $this->testClientCursorPersistence();
        $this->testAdapterNoSecrets();
        $this->testLayoutGated();
        $this->testManifestEntries();
        $this->testSdkExposesMasterData();
        $this->testNoReplayQueueChanges();
        $this->testPhase10to12Intact();
        $this->testPosUntouched();
        $this->testWarehouseMigrationPresent();

        return $this->results;
    }

    private function clearEnv(): void
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

    private function enable(bool $master, bool $md): void
    {
        $this->clearEnv();
        if ($master) {
            putenv('RATEB_OFFLINE_ENABLED=1');
            $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        }
        if ($md) {
            putenv('RATEB_OFFLINE_MASTER_DATA=1');
            $_ENV['RATEB_OFFLINE_MASTER_DATA'] = '1';
        }
        $this->resetFlagConfig();
    }

    private function testFlagsDefaultOff(): void
    {
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.master_data') === false
            && $svc->isMasterDataEnabled() === false;
        $this->record('master_data default OFF', $ok, $ok ? 'ok' : 'on');
    }

    private function testMasterDataRequiresMaster(): void
    {
        $this->enable(false, true);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.master_data') === true
            && $svc->isMasterDataEnabled() === false;
        $this->record('master_data requires offline.enabled', $ok, $ok ? 'ok' : 'fail');
    }

    private function testMasterDataOn(): void
    {
        $this->enable(true, true);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isMasterDataEnabled() === true;
        $this->record('master_data ON with master', $ok, $ok ? 'ok' : 'off');
        $this->clearEnv();
    }

    private function testCursorNormalization(): void
    {
        $enc = OfflineDeltaCursorCodec::encode(42, '2026-07-11 08:00:00');
        $ok = $enc === '2026-07-11 08:00:00|42';
        [$id, $u] = OfflineDeltaCursorCodec::parse($enc);
        $ok = $ok && $id === 42 && $u === '2026-07-11 08:00:00';
        $this->record('cursor normalization updated_at|id', $ok, $ok ? $enc : 'fail');
    }

    private function testLegacyCursorParse(): void
    {
        [$id, $u] = OfflineDeltaCursorCodec::parse('42|2026-07-11 08:00:00');
        $ok = $id === 42 && $u === '2026-07-11 08:00:00';
        [$id2, $u2] = OfflineDeltaCursorCodec::parse('99');
        $ok = $ok && $id2 === 99 && $u2 === '';
        $this->record('legacy cursor parse supported', $ok, $ok ? 'ok' : 'fail');
    }

    private function testSoftDeleteHelpers(): void
    {
        $ok = OfflineDeltaCursorCodec::isInactiveStatus('inactive') === true
            && OfflineDeltaCursorCodec::isInactiveStatus('active') === false
            && OfflineDeltaCursorCodec::isInactiveStatus('', 0) === true
            && OfflineDeltaCursorCodec::isInactiveStatus('', 1) === false;
        $this->record('soft delete / restore status helpers', $ok, $ok ? 'ok' : 'fail');
    }

    private function testAllowlist(): void
    {
        $p = new ErpOfflineMasterDataPolicy();
        $ok = $p->resolveCanonical('customers') === 'customer_directory'
            && $p->resolveCanonical('branches') === 'branch_directory'
            && $p->resolveCanonical('warehouses') === 'warehouse_directory'
            && $p->resolveCanonical('brands') === null
            && $p->resolveCanonical('tax') === null;
        $this->record('entity allowlist', $ok, $ok ? 'ok' : 'fail');
    }

    private function testUnknownEntityRejectedInCursorService(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineCursorService.php');
        $ctrl = (string) file_get_contents(RATEB_ROOT . '/offline/server/Controllers/OfflineSyncApiController.php');
        $ok = str_contains($src, 'entity_not_allowed')
            && str_contains($ctrl, 'entity_not_allowed')
            && str_contains($ctrl, 'master_data_disabled');
        $this->record('unknown entity rejected', $ok, $ok ? 'ok' : 'fail');
    }

    private function testFieldRedactionSource(): void
    {
        $cust = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/CustomerOfflineDirectoryService.php');
        $emp = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineEmployeeDirectoryService.php');
        $sup = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ProcurementOfflineSupplierDirectoryService.php');
        $ok = !preg_match("/'salary'/", $emp)
            && !preg_match("/'bank/", $sup)
            && !preg_match("/'iban'/", $sup)
            && !preg_match("/'password'/", $cust)
            && str_contains($emp, "'deleted'")
            && str_contains($sup, 'OfflineDeltaCursorCodec::encode')
            && str_contains($sup, "'deleted'");
        $this->record('field redaction + soft-delete markers', $ok, $ok ? 'ok' : 'fail');
    }

    private function testClientCursorPersistence(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/master-data-adapter.js');
        $ok = str_contains($src, 'CURSORS')
            && str_contains($src, 'writeClientCursor')
            && str_contains($src, 'readClientCursor')
            && str_contains($src, 'md:')
            && str_contains($src, 'ENTITY_CACHE')
            && str_contains($src, 'has_more');
        $this->record('client cursor persistence', $ok, $ok ? 'ok' : 'fail');
    }

    private function testAdapterNoSecrets(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/master-data-adapter.js');
        $ok = str_contains($src, 'Read-only')
            && !str_contains($src, 'SYNC_QUEUE')
            && !preg_match('/password\s*[:=]/i', $src);
        $this->record('adapter read-only no secrets', $ok, $ok ? 'ok' : 'fail');
    }

    private function testLayoutGated(): void
    {
        $layout = (string) file_get_contents(RATEB_ROOT . '/views/layouts/main.php');
        $ok = str_contains($layout, 'isMasterDataEnabled')
            && str_contains($layout, 'erp-master-data-bootstrap.js');
        $this->record('layout master-data JS gated', $ok, $ok ? 'ok' : 'fail');
    }

    private function testManifestEntries(): void
    {
        $m = (string) file_get_contents(RATEB_ROOT . '/offline/config/entity-manifest.php');
        $ok = str_contains($m, 'customer_directory')
            && str_contains($m, 'branch_directory')
            && str_contains($m, 'warehouse_directory')
            && str_contains($m, "'replay' => 'delta_pull'");
        $this->record('entity-manifest master-data entries', $ok, $ok ? 'ok' : 'fail');
    }

    private function testSdkExposesMasterData(): void
    {
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $bundle = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($sdk, 'isMasterDataEnabled')
            && str_contains($sdk, '13.0.0')
            && str_contains($sdk, 'masterData:')
            && str_contains($bundle, 'RatebOfflineMasterData')
            && str_contains($bundle, '13.0.0');
        $this->record('SDK exposes master data', $ok, $ok ? 'ok' : 'fail');
    }

    private function testNoReplayQueueChanges(): void
    {
        $replay = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineReplayEngine.php');
        $queue = (string) file_get_contents(RATEB_ROOT . '/offline/client/sync/queue-manager.js');
        $ok = !str_contains($replay, 'customer_directory')
            && !str_contains($replay, 'MasterData')
            && str_contains($queue, 'sessionNeedsReauth')
            && class_exists(OfflineReplayEngine::class);
        $this->record('no replay/queue write changes', $ok, $ok ? 'ok' : 'fail');
    }

    private function testPhase10to12Intact(): void
    {
        $shell = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/shell-adapter.js');
        $auth = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/auth-lock-adapter.js');
        $rbac = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/rbac-cache-adapter.js');
        $ok = str_contains($shell, 'erp_shell_chrome')
            && str_contains($auth, 'auth_vault')
            && str_contains($rbac, 'erp_rbac')
            && !str_contains($shell, 'master_data')
            && !str_contains($auth, 'customer_directory')
            && !str_contains($rbac, 'customerEntity');
        $this->record('Phase 10-12 adapters intact', $ok, $ok ? 'ok' : 'regressed');
    }

    private function testPosUntouched(): void
    {
        $pos = RATEB_ROOT . '/public/assets/pos/js/pos-offline-sync.js';
        $ok = is_file($pos) && !str_contains((string) file_get_contents($pos), 'customer_directory');
        $this->record('POS unchanged', $ok, $ok ? 'ok' : 'fail');
    }

    private function testWarehouseMigrationPresent(): void
    {
        $m1 = RATEB_ROOT . '/offline/migrations/004_warehouses_updated_at_for_delta.sql';
        $m2 = RATEB_ROOT . '/migrations/180_offline_warehouses_updated_at.sql';
        $ok = is_file($m1) && is_file($m2)
            && str_contains((string) file_get_contents($m1), 'updated_at');
        $this->record('warehouse updated_at migration', $ok, $ok ? 'ok' : 'missing');
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }
}
