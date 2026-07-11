<?php

declare(strict_types=1);

/**
 * Phase 22B — Enterprise Manufacturing Offline (Tier 1 drafts) tests.
 *
 * Run: php offline/tests/run-manufacturing-offline-tests.php
 */

use Rateb\App\Offline\OfflineModule;
use Rateb\App\Offline\Services\ManufacturingOfflineMasterDataDirectoryService;
use Rateb\App\Offline\Services\ManufacturingOfflineReplayService;
use Rateb\App\Offline\Services\ManufacturingOfflineTenantGuard;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;

final class ManufacturingOfflinePhase22bTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testFlagsDefaultOff();
        $this->testRequiresMaster();
        $this->testSubflagsRequireParent();
        $this->testEntityManifestHasMfg();
        $this->testModulesRegistryActiveMfg();
        $this->testDeferredActionsCoverRequirements();
        $this->testClientAdapterSource();
        $this->testSdkBundleHasMfgAdapter();
        $this->testReplayUsesExistingDomainOnly();
        $this->testNoDeletePaymentsApprovalsEmailSmsGovBinary();
        $this->testConflictStatusChanged();
        $this->testConflictServerNewerStillWins();
        $this->testConflictAcceptWhenStatusMatches();
        $this->testReplaySkipsWhenFlagOff();
        $this->testReplayEngineDelegatesWhenFlagOn();
        $this->testQueueRejectsWhenFlagOff();
        $this->testQueueRejectsProductionWithoutSubflag();
        $this->testQueueAliases();
        $this->testPayloadSanitizerKeepsModule();
        $this->testAuthzAllowsAbility();
        $this->testTenantGuardSource();
        $this->testMasterDataEntitiesRegistered();
        $this->testOpsAllowlistMfg();
        $this->testOpsFormsMfgHooks();
        $this->testBackgroundReportsFlag();
        $this->testFoundationUntouchedMarkers();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_MANUFACTURING',
            'RATEB_OFFLINE_MANUFACTURING_PRODUCTION',
            'RATEB_OFFLINE_MANUFACTURING_WORKFLOW',
            'RATEB_OFFLINE_MANUFACTURING_QUALITY',
            'RATEB_OFFLINE_MANUFACTURING_MASTERDATA',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
    }

    private function enableBase(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_MANUFACTURING=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_MANUFACTURING'] = '1';
    }

    private function enableAll(): void
    {
        $this->enableBase();
        foreach ([
            'RATEB_OFFLINE_MANUFACTURING_PRODUCTION',
            'RATEB_OFFLINE_MANUFACTURING_WORKFLOW',
            'RATEB_OFFLINE_MANUFACTURING_QUALITY',
            'RATEB_OFFLINE_MANUFACTURING_MASTERDATA',
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
        $ok = $svc->enabled('offline.manufacturing') === false
            && $svc->isManufacturingEnabled() === false
            && $svc->isManufacturingProductionEnabled() === false
            && $svc->isManufacturingWorkflowEnabled() === false
            && $svc->isManufacturingQualityEnabled() === false
            && $svc->isManufacturingMasterDataEnabled() === false;
        $this->record('MFG flags default OFF', $ok);
    }

    private function testRequiresMaster(): void
    {
        putenv('RATEB_OFFLINE_MANUFACTURING=1');
        $_ENV['RATEB_OFFLINE_MANUFACTURING'] = '1';
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.manufacturing') === true
            && $svc->isManufacturingEnabled() === false;
        $this->record('MFG requires offline.enabled', $ok);
        $this->clearEnv();
    }

    private function testSubflagsRequireParent(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_MANUFACTURING_PRODUCTION=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_MANUFACTURING_PRODUCTION'] = '1';
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isManufacturingProductionEnabled() === false;
        $this->record('Subflags require offline.manufacturing', $ok);
        $this->clearEnv();
    }

    private function testEntityManifestHasMfg(): void
    {
        $m = require RATEB_ROOT . '/offline/config/entity-manifest.php';
        $ok = isset($m['mfg_bom_create'], $m['mfg_workflow_transition'], $m['mfg_product_directory'])
            && ($m['mfg_bom_create']['module'] ?? '') === 'manufacturing'
            && ($m['mfg_bom_create']['replay'] ?? '') === 'delegate_manufacturing';
        $this->record('entity manifest has MFG', $ok);
    }

    private function testModulesRegistryActiveMfg(): void
    {
        $m = require RATEB_ROOT . '/offline/config/modules.php';
        $ok = in_array('manufacturing', $m['active_modules'] ?? [], true)
            && in_array('manufacturing', $m['tiers']['T1'] ?? [], true)
            && isset($m['operations']['manufacturing.bom.create'])
            && in_array('procurement_enterprise', $m['active_modules'] ?? [], true);
        $this->record('modules registry active MFG (+ EPROC preserved)', $ok);
    }

    private function testDeferredActionsCoverRequirements(): void
    {
        $a = ManufacturingOfflineReplayService::deferredActions();
        $need = [
            'bom.create', 'bom.update',
            'routing.create', 'routing.update',
            'production_order.create', 'production_order.update',
            'work_order.create', 'work_order.update',
            'workflow.transition',
            'material_reservation.create', 'material_consumption.create',
            'finished_goods.create', 'scrap.create',
            'quality_check.create', 'cost.create',
            'assignment.create', 'comment.create', 'note.create',
        ];
        $ok = true;
        foreach ($need as $n) {
            if (!in_array($n, $a, true)) {
                $ok = false;
                break;
            }
        }
        $ok = $ok
            && !in_array('delete', $a, true)
            && !in_array('payment.create', $a, true);
        $this->record('deferred actions cover Tier-1 only', $ok, implode(',', array_slice($a, 0, 8)) . '...');
    }

    private function testClientAdapterSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/manufacturing-adapter.js');
        $ok = str_contains($src, "module: 'manufacturing'")
            && str_contains($src, 'enqueueBomCreate')
            && str_contains($src, 'enqueueProductionOrderCreate')
            && str_contains($src, 'enqueueWorkOrderCreate')
            && str_contains($src, 'enqueueWorkflowTransition')
            && !preg_match('/enqueue\([\'"]delete/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*payment/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*email/i', $src);
        $this->record('client adapter queues Tier-1 drafts only', $ok);
    }

    private function testSdkBundleHasMfgAdapter(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($src, 'RatebOfflineManufacturingAdapter')
            && str_contains($src, 'isManufacturingEnabled')
            && str_contains($src, 'manufacturing: function')
            && str_contains($src, "'offline.manufacturing'")
            && str_contains($src, '14.2.0');
        $this->record('SDK bundle contains MFG adapter', $ok);
    }

    private function testReplayUsesExistingDomainOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ManufacturingOfflineReplayService.php');
        $ok = str_contains($src, 'BomService')
            && str_contains($src, 'ManufacturingWorkflowService')
            && str_contains($src, 'ProductionOrderService')
            && str_contains($src, 'MfgWorkOrderService')
            && str_contains($src, 'MaterialReservationService')
            && str_contains($src, 'QualityCheckService')
            && !str_contains($src, 'INSERT INTO rateb_mfg_')
            && !preg_match('/new\s+\\\\?WorkOrderService\b/', $src);
        $this->record('replay uses Phase 22A domain only', $ok);
    }

    private function testNoDeletePaymentsApprovalsEmailSmsGovBinary(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ManufacturingOfflineReplayService.php');
        $ok = !preg_match('/softDelete|->delete\(/', $src)
            && !preg_match('/PaymentService|NotificationService|sendEmail|sendSms|move_uploaded_file|Zatca|Government/', $src)
            && !preg_match('/ApprovalRequestService|ApprovalWorkflowService/', $src)
            && !preg_match('/StockMovementService|AccountingService/', $src);
        $this->record('replay excludes delete/payments/approvals/email/sms/gov/binary/inv/GL', $ok);
    }

    private function testConflictStatusChanged(): void
    {
        $r = (new OfflineConflictResolverService())->resolveManufacturing(
            ['version' => 2, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'active']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'status_changed';
        $this->record('conflict status_changed', $ok, json_encode($r));
    }

    private function testConflictServerNewerStillWins(): void
    {
        $r = (new OfflineConflictResolverService())->resolveManufacturing(
            ['version' => 1, 'expected_status' => 'draft'],
            ['version' => 5, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'server_newer';
        $this->record('conflict server_newer still wins', $ok, json_encode($r));
    }

    private function testConflictAcceptWhenStatusMatches(): void
    {
        $r = (new OfflineConflictResolverService())->resolveManufacturing(
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
            'module' => 'manufacturing',
            'action' => 'bom.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'bom.create', 'payload' => ['code' => 'x']]),
        ]);
        $ok = ($out['status'] ?? '') === 'skipped'
            && ($out['error'] ?? '') === 'manufacturing_offline_disabled';
        $this->record('replay skips MFG when flag OFF', $ok, json_encode($out));
    }

    private function testReplayEngineDelegatesWhenFlagOn(): void
    {
        $this->enableAll();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'manufacturing',
            'action' => 'bom.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'bom.create', 'payload' => []]),
        ]);
        $ok = ($out['status'] ?? '') !== 'skipped'
            || ($out['error'] ?? '') !== 'manufacturing_offline_disabled';
        $ok = $ok && in_array(($out['status'] ?? ''), ['failed', 'synced', 'conflict'], true);
        $this->record('replay engine delegates when flag ON', $ok, json_encode($out));
        $this->clearEnv();
    }

    private function testQueueRejectsWhenFlagOff(): void
    {
        $this->clearEnv();
        putenv('RATEB_OFFLINE_ENABLED=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $res = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'mfg-off-' . bin2hex(random_bytes(3)),
            'module' => 'manufacturing',
            'action' => 'bom.create',
            'payload' => ['code' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($res['rejected'] ?? 0) >= 1 && (int) ($res['accepted'] ?? 0) === 0;
        $this->record('queue rejects MFG when flag OFF', $ok, json_encode($res));
        $this->clearEnv();
    }

    private function testQueueRejectsProductionWithoutSubflag(): void
    {
        $this->enableBase();
        putenv('RATEB_OFFLINE_MANUFACTURING_PRODUCTION');
        unset($_ENV['RATEB_OFFLINE_MANUFACTURING_PRODUCTION']);
        $res = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'mfg-sub-' . bin2hex(random_bytes(3)),
            'module' => 'manufacturing',
            'action' => 'bom.create',
            'payload' => ['code' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($res['rejected'] ?? 0) >= 1;
        $this->record('queue rejects production without subflag', $ok, json_encode($res));
        $this->clearEnv();
    }

    private function testQueueAliases(): void
    {
        $this->enableAll();
        $ref = new \ReflectionClass(OfflineQueueService::class);
        $m = $ref->getMethod('normalizeManufacturingAction');
        $m->setAccessible(true);
        $svc = new OfflineQueueService();
        $ok = $m->invoke($svc, 'create_bom') === 'bom.create'
            && $m->invoke($svc, 'manufacturing.workflow.transition') === 'workflow.transition'
            && $m->invoke($svc, 'create_production_order') === 'production_order.create'
            && $m->invoke($svc, 'bogus') === '';
        $this->record('queue aliases normalize', $ok);
        $this->clearEnv();
    }

    private function testPayloadSanitizerKeepsModule(): void
    {
        $n = (new OfflinePayloadSanitizer())->normalize([
            'module' => 'manufacturing',
            'action' => 'bom.create',
            'url' => 'http://evil',
            'payload' => ['code' => 't'],
        ]);
        $ok = ($n['module'] ?? '') === 'manufacturing' && !isset($n['url']);
        $this->record('payload sanitizer keeps manufacturing module', $ok, json_encode($n));
    }

    private function testAuthzAllowsAbility(): void
    {
        \Rateb\App\Core\TenantContext::setCompanyId(1);
        \Rateb\App\Core\TenantContext::setApiModules(['manufacturing']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === true;
        $this->record('authz allows manufacturing ability', $ok);
        \Rateb\App\Core\TenantContext::setApiModules(null);
        \Rateb\App\Core\TenantContext::setCompanyId(null);
    }

    private function testTenantGuardSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ManufacturingOfflineTenantGuard.php');
        $ok = str_contains($src, 'assertBom')
            && str_contains($src, 'assertProductionOrder')
            && str_contains($src, 'assertWorkOrder')
            && str_contains($src, 'bomExistsForKey')
            && str_contains($src, 'branch_mismatch')
            && class_exists(ManufacturingOfflineTenantGuard::class);
        $this->record('tenant guard source', $ok);
    }

    private function testMasterDataEntitiesRegistered(): void
    {
        $names = ManufacturingOfflineMasterDataDirectoryService::entityNames();
        $cfg = require RATEB_ROOT . '/offline/config/master-data-entities.php';
        $entities = $cfg['entities'] ?? [];
        $ok = in_array('mfg_product_directory', $names, true)
            && in_array('mfg_work_center_directory', $names, true)
            && isset($entities['mfg_product_directory']);
        $this->record('master-data entities registered', $ok, implode(',', $names));
    }

    private function testOpsAllowlistMfg(): void
    {
        $cfg = OfflineModule::opsPageAllowlist();
        $paths = $cfg['paths'] ?? [];
        $ok = in_array('mfg', $paths, true)
            && in_array('mfg/boms', $paths, true)
            && in_array('mfg/production-orders', $paths, true)
            && in_array('mfg/work-orders', $paths, true);
        $this->record('ops allowlist MFG', $ok);
    }

    private function testOpsFormsMfgHooks(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/ops-forms-adapter.js');
        $ok = str_contains($src, "module: 'manufacturing'")
            && str_contains($src, 'bom.create')
            && str_contains($src, 'offline.manufacturing')
            && str_contains($src, 'RatebOfflineManufacturingAdapter');
        $this->record('ops forms MFG hooks', $ok);
    }

    private function testBackgroundReportsFlag(): void
    {
        $this->clearEnv();
        $stats = (new OfflineBackgroundSync())->process(1, 1);
        $ok = array_key_exists('manufacturing_enabled', $stats)
            && ($stats['manufacturing_enabled'] ?? true) === false;
        $this->record('background reports manufacturing_enabled', $ok, json_encode($stats));
    }

    private function testFoundationUntouchedMarkers(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = preg_match('/DB_VERSION\s*=\s*2/', $schema)
            && str_contains($sdk, "version: '14.2.0'");
        $this->record('Foundation markers intact (DB_VERSION=2, SDK 14.2.0)', $ok);
    }
}
