<?php

declare(strict_types=1);

/**
 * Phase 19B — Assets Offline (Tier 1 drafts) tests.
 *
 * Run: php offline/tests/run-assets-offline-tests.php
 */

use Rateb\App\Offline\OfflineModule;
use Rateb\App\Offline\Services\AssetOfflineMasterDataDirectoryService;
use Rateb\App\Offline\Services\AssetOfflineReplayService;
use Rateb\App\Offline\Services\AssetOfflineTenantGuard;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;

final class AssetsOfflinePhase19bTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testFlagsDefaultOff();
        $this->testRequiresMaster();
        $this->testSubflagsRequireAssets();
        $this->testEntityManifestHasAssets();
        $this->testModulesRegistryActiveAssets();
        $this->testDeferredActionsCoverRequirements();
        $this->testClientAdapterSource();
        $this->testSdkBundleHasAssetsAdapter();
        $this->testReplayUsesExistingDomainOnly();
        $this->testNoDeletePaymentsApprovalsEmailSmsAttachmentsGov();
        $this->testConflictStatusChanged();
        $this->testConflictServerNewerStillWins();
        $this->testConflictAcceptWhenStatusMatches();
        $this->testReplaySkipsWhenFlagOff();
        $this->testReplayEngineDelegatesWhenFlagOn();
        $this->testQueueRejectsWhenFlagOff();
        $this->testQueueRejectsMaintenanceWithoutSubflag();
        $this->testQueueAliases();
        $this->testPayloadSanitizerKeepsAssetsModule();
        $this->testAuthzAllowsAssetsAbility();
        $this->testTenantGuardSource();
        $this->testMasterDataEntitiesRegistered();
        $this->testOpsAllowlistAssets();
        $this->testOpsFormsAssetsHooks();
        $this->testBackgroundReportsAssetsFlag();
        $this->testFoundationUntouchedMarkers();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_ASSETS',
            'RATEB_OFFLINE_ASSETS_MAINTENANCE',
            'RATEB_OFFLINE_ASSETS_WORKFLOW',
            'RATEB_OFFLINE_ASSETS_INSPECTIONS',
            'RATEB_OFFLINE_ASSETS_MASTERDATA',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
    }

    private function enableBase(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_ASSETS=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_ASSETS'] = '1';
    }

    private function enableAll(): void
    {
        $this->enableBase();
        foreach ([
            'RATEB_OFFLINE_ASSETS_MAINTENANCE',
            'RATEB_OFFLINE_ASSETS_WORKFLOW',
            'RATEB_OFFLINE_ASSETS_INSPECTIONS',
            'RATEB_OFFLINE_ASSETS_MASTERDATA',
        ] as $k) {
            putenv($k . '=1');
            $_ENV[$k] = '1';
        }
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testFlagsDefaultOff(): void
    {
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.assets') === false
            && $svc->isAssetsEnabled() === false
            && $svc->isAssetsMaintenanceEnabled() === false
            && $svc->isAssetsWorkflowEnabled() === false
            && $svc->isAssetsInspectionsEnabled() === false
            && $svc->isAssetsMasterDataEnabled() === false;
        $this->record('Assets flags default OFF', $ok);
    }

    private function testRequiresMaster(): void
    {
        putenv('RATEB_OFFLINE_ASSETS=1');
        $_ENV['RATEB_OFFLINE_ASSETS'] = '1';
        putenv('RATEB_OFFLINE_ENABLED');
        unset($_ENV['RATEB_OFFLINE_ENABLED']);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.assets') === true && $svc->isAssetsEnabled() === false;
        $this->record('Assets requires master flag', $ok);
        $this->clearEnv();
    }

    private function testSubflagsRequireAssets(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_ASSETS_MAINTENANCE=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_ASSETS_MAINTENANCE'] = '1';
        putenv('RATEB_OFFLINE_ASSETS');
        unset($_ENV['RATEB_OFFLINE_ASSETS']);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isAssetsMaintenanceEnabled() === false;
        $this->record('maintenance subflag requires assets', $ok);
        $this->clearEnv();
    }

    private function testEntityManifestHasAssets(): void
    {
        $cfg = require RATEB_ROOT . '/offline/config/entity-manifest.php';
        $ok = isset(
            $cfg['assets_asset_create'],
            $cfg['assets_workflow_transition'],
            $cfg['asset_category_directory']
        );
        $this->record('entity manifest has Assets ops + directories', $ok);
    }

    private function testModulesRegistryActiveAssets(): void
    {
        $cfg = require RATEB_ROOT . '/offline/config/modules.php';
        $active = $cfg['active_modules'] ?? [];
        $ops = $cfg['operations'] ?? [];
        $ok = in_array('assets', $active, true)
            && isset($ops['assets.asset.create'], $ops['assets.workflow.transition'], $ops['assets.work_order.create']);
        $this->record('modules registry activates Assets ops', $ok);
    }

    private function testDeferredActionsCoverRequirements(): void
    {
        $a = AssetOfflineReplayService::deferredActions();
        $ok = in_array('asset.create', $a, true)
            && in_array('asset.update', $a, true)
            && in_array('workflow.transition', $a, true)
            && in_array('assignment.create', $a, true)
            && in_array('transfer.create', $a, true)
            && in_array('maintenance_request.create', $a, true)
            && in_array('maintenance_plan.create', $a, true)
            && in_array('work_order.create', $a, true)
            && in_array('inspection.create', $a, true)
            && in_array('checklist.create', $a, true)
            && in_array('meter_reading.create', $a, true)
            && in_array('comment.create', $a, true)
            && in_array('activity.create', $a, true)
            && in_array('note.create', $a, true);
        $this->record('deferred actions cover Tier-1 Assets', $ok, implode(',', $a));
    }

    private function testClientAdapterSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/assets-adapter.js');
        $ok = str_contains($src, 'asset.create')
            && str_contains($src, 'workflow.transition')
            && str_contains($src, "module: 'assets'")
            && str_contains($src, 'enqueue')
            && str_contains($src, 'draft:')
            && str_contains($src, 'retry:')
            && str_contains($src, 'status:')
            && str_contains($src, 'sync:')
            && !preg_match('/enqueue\([\'"][^\'"]*delete/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*payment/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*email/i', $src);
        $this->record('client adapter queues Tier-1 drafts only', $ok);
    }

    private function testSdkBundleHasAssetsAdapter(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($src, 'RatebOfflineAssetsAdapter')
            && str_contains($src, 'isAssetsEnabled')
            && str_contains($src, 'assets: function')
            && str_contains($src, "'offline.assets'")
            && str_contains($src, '14.2.0');
        $this->record('SDK bundle contains Assets adapter', $ok);
    }

    private function testReplayUsesExistingDomainOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/AssetOfflineReplayService.php');
        $ok = str_contains($src, 'AssetService')
            && str_contains($src, 'AssetWorkflowService')
            && str_contains($src, 'MaintenanceRequestService')
            && str_contains($src, 'WorkOrderService')
            && str_contains($src, 'InspectionService')
            && str_contains($src, 'AssetAssignmentService')
            && !str_contains($src, 'INSERT INTO rateb_eam_assets');
        $this->record('replay uses existing Assets domain only', $ok);
    }

    private function testNoDeletePaymentsApprovalsEmailSmsAttachmentsGov(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/AssetOfflineReplayService.php');
        $ok = !preg_match('/softDelete|->delete\(/', $src)
            && !preg_match('/PaymentService|approve|sendEmail|sendSms|move_uploaded_file|Zatca|Government/', $src);
        $this->record('replay excludes delete/payments/approvals/email/sms/attachments/gov', $ok);
    }

    private function testConflictStatusChanged(): void
    {
        $r = (new OfflineConflictResolverService())->resolveAssets(
            ['version' => 2, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'active']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'status_changed';
        $this->record('conflict status_changed', $ok, json_encode($r));
    }

    private function testConflictServerNewerStillWins(): void
    {
        $r = (new OfflineConflictResolverService())->resolveAssets(
            ['version' => 1, 'expected_status' => 'draft'],
            ['version' => 5, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'server_newer';
        $this->record('conflict server_newer still wins', $ok, json_encode($r));
    }

    private function testConflictAcceptWhenStatusMatches(): void
    {
        $r = (new OfflineConflictResolverService())->resolveAssets(
            ['version' => 3, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'accept_client';
        $this->record('conflict accept when status matches', $ok, json_encode($r));
    }

    private function testReplaySkipsWhenFlagOff(): void
    {
        $this->clearEnv();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'assets',
            'action' => 'asset.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'asset.create', 'payload' => ['name' => 'x']]),
        ]);
        $ok = ($out['status'] ?? '') === 'skipped'
            && ($out['error'] ?? '') === 'assets_offline_disabled';
        $this->record('replay skips Assets when flag OFF', $ok, json_encode($out));
    }

    private function testReplayEngineDelegatesWhenFlagOn(): void
    {
        $this->enableAll();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'assets',
            'action' => 'asset.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'asset.create', 'payload' => []]),
        ]);
        $ok = ($out['status'] ?? '') !== 'skipped' || ($out['error'] ?? '') !== 'assets_offline_disabled';
        $ok = $ok && in_array(($out['status'] ?? ''), ['failed', 'synced', 'conflict'], true);
        $this->record('replay engine delegates when flag ON', $ok, json_encode($out));
        $this->clearEnv();
    }

    private function testQueueRejectsWhenFlagOff(): void
    {
        $this->clearEnv();
        putenv('RATEB_OFFLINE_ENABLED=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $result = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'eam-off-' . bin2hex(random_bytes(3)),
            'module' => 'assets',
            'action' => 'asset.create',
            'payload' => ['name' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($result['rejected'] ?? 0) >= 1 && (int) ($result['accepted'] ?? 0) === 0;
        $this->record('queue rejects Assets without flag', $ok, json_encode($result));
        $this->clearEnv();
    }

    private function testQueueRejectsMaintenanceWithoutSubflag(): void
    {
        $this->enableBase();
        putenv('RATEB_OFFLINE_ASSETS_MAINTENANCE');
        unset($_ENV['RATEB_OFFLINE_ASSETS_MAINTENANCE']);
        $result = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'eam-sub-' . bin2hex(random_bytes(3)),
            'module' => 'assets',
            'action' => 'work_order.create',
            'payload' => ['title' => 'x', 'asset_id' => 1],
        ]], ['company_id' => 1]);
        $ok = (int) ($result['rejected'] ?? 0) >= 1;
        $this->record('queue rejects work_order.create without maintenance subflag', $ok, json_encode($result));
        $this->clearEnv();
    }

    private function testQueueAliases(): void
    {
        $this->enableAll();
        $ref = new \ReflectionClass(OfflineQueueService::class);
        $m = $ref->getMethod('normalizeAssetsAction');
        $m->setAccessible(true);
        $svc = new OfflineQueueService();
        $ok = $m->invoke($svc, 'create_asset') === 'asset.create'
            && $m->invoke($svc, 'work_order.create') === 'work_order.create'
            && $m->invoke($svc, 'create_inspection') === 'inspection.create'
            && $m->invoke($svc, 'bogus') === '';
        $this->record('queue Assets action aliases', $ok);
        $this->clearEnv();
    }

    private function testPayloadSanitizerKeepsAssetsModule(): void
    {
        $n = (new OfflinePayloadSanitizer())->normalize([
            'module' => 'assets',
            'action' => 'comment.create',
            'url' => 'http://evil',
            'payload' => ['body' => 't'],
        ]);
        $ok = ($n['module'] ?? '') === 'assets' && !isset($n['url']);
        $this->record('payload sanitizer keeps Assets module', $ok, json_encode($n));
    }

    private function testAuthzAllowsAssetsAbility(): void
    {
        \Rateb\App\Core\TenantContext::setCompanyId(1);
        \Rateb\App\Core\TenantContext::setApiModules(['assets']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === true;
        $this->record('authz allows Assets ability token', $ok);
        \Rateb\App\Core\TenantContext::setApiModules(null);
        \Rateb\App\Core\TenantContext::setCompanyId(null);
    }

    private function testTenantGuardSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/AssetOfflineTenantGuard.php');
        $ok = str_contains($src, 'assertAsset')
            && str_contains($src, 'assertMaintenanceRequest')
            && str_contains($src, 'assertWorkOrder')
            && str_contains($src, 'branch_mismatch')
            && class_exists(AssetOfflineTenantGuard::class);
        $this->record('tenant guard asserts asset/request/WO ownership', $ok);
    }

    private function testMasterDataEntitiesRegistered(): void
    {
        $names = AssetOfflineMasterDataDirectoryService::entityNames();
        $cfg = require RATEB_ROOT . '/offline/config/master-data-entities.php';
        $entities = $cfg['entities'] ?? [];
        $ok = in_array('asset_category_directory', $names, true)
            && in_array('maintenance_plan_directory', $names, true)
            && isset($entities['asset_location_directory'], $entities['asset_model_directory']);
        $this->record('master-data Assets directories registered', $ok);
    }

    private function testOpsAllowlistAssets(): void
    {
        $cfg = OfflineModule::opsPageAllowlist();
        $paths = $cfg['paths'] ?? [];
        $ok = in_array('eam', $paths, true)
            && in_array('eam/assets', $paths, true)
            && in_array('eam/work-orders', $paths, true)
            && in_array('eam/requests', $paths, true)
            && in_array('eam/calendar', $paths, true)
            && in_array('eam/inspections', $paths, true);
        $this->record('ops allowlist includes EAM browse paths', $ok);
    }

    private function testOpsFormsAssetsHooks(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/ops-forms-adapter.js');
        $ok = str_contains($src, 'asset.create')
            && str_contains($src, 'offline.assets')
            && str_contains($src, 'RatebOfflineAssetsAdapter');
        $this->record('ops forms Assets hooks present', $ok);
    }

    private function testBackgroundReportsAssetsFlag(): void
    {
        $this->clearEnv();
        $stats = (new OfflineBackgroundSync())->process(1, 1);
        $ok = array_key_exists('assets_enabled', $stats)
            && ($stats['assets_enabled'] ?? true) === false;
        $this->record('background reports assets_enabled', $ok, json_encode($stats));
    }

    private function testFoundationUntouchedMarkers(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = preg_match('/DB_VERSION\s*=\s*2/', $schema)
            && str_contains($sdk, "version: '14.2.0'");
        $this->record('foundation markers untouched (IDB v2, SDK 14.2.0)', $ok);
    }
}
