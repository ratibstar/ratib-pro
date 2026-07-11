<?php

declare(strict_types=1);

/**
 * Phase 21B — Enterprise Procurement Offline (Tier 1 drafts) tests.
 *
 * Run: php offline/tests/run-procurement-enterprise-offline-tests.php
 */

use Rateb\App\Offline\OfflineModule;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;
use Rateb\App\Offline\Services\ProcurementEnterpriseOfflineMasterDataDirectoryService;
use Rateb\App\Offline\Services\ProcurementEnterpriseOfflineReplayService;
use Rateb\App\Offline\Services\ProcurementEnterpriseOfflineTenantGuard;

final class ProcurementEnterpriseOfflinePhase21bTest
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
        $this->testEntityManifestHasEproc();
        $this->testModulesRegistryActiveEproc();
        $this->testDeferredActionsCoverRequirements();
        $this->testClientAdapterSource();
        $this->testSdkBundleHasEprocAdapter();
        $this->testReplayUsesExistingDomainOnly();
        $this->testNoDeletePaymentsApprovalsEmailSmsGovBinary();
        $this->testConflictStatusChanged();
        $this->testConflictServerNewerStillWins();
        $this->testConflictAcceptWhenStatusMatches();
        $this->testReplaySkipsWhenFlagOff();
        $this->testReplayEngineDelegatesWhenFlagOn();
        $this->testQueueRejectsWhenFlagOff();
        $this->testQueueRejectsSuppliersWithoutSubflag();
        $this->testQueueAliases();
        $this->testPayloadSanitizerKeepsModule();
        $this->testAuthzAllowsAbility();
        $this->testTenantGuardSource();
        $this->testMasterDataEntitiesRegistered();
        $this->testOpsAllowlistEproc();
        $this->testOpsFormsEprocHooks();
        $this->testBackgroundReportsFlag();
        $this->testFoundationUntouchedMarkers();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_PROCUREMENT_ENTERPRISE',
            'RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_SUPPLIERS',
            'RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_TENDERS',
            'RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_CONTRACTS',
            'RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_WORKFLOW',
            'RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_MASTERDATA',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
    }

    private function enableBase(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_PROCUREMENT_ENTERPRISE=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_PROCUREMENT_ENTERPRISE'] = '1';
    }

    private function enableAll(): void
    {
        $this->enableBase();
        foreach ([
            'RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_SUPPLIERS',
            'RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_TENDERS',
            'RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_CONTRACTS',
            'RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_WORKFLOW',
            'RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_MASTERDATA',
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
        $ok = $svc->enabled('offline.procurement_enterprise') === false
            && $svc->isProcurementEnterpriseEnabled() === false
            && $svc->isProcurementEnterpriseSuppliersEnabled() === false
            && $svc->isProcurementEnterpriseTendersEnabled() === false
            && $svc->isProcurementEnterpriseContractsEnabled() === false
            && $svc->isProcurementEnterpriseWorkflowEnabled() === false
            && $svc->isProcurementEnterpriseMasterDataEnabled() === false;
        $this->record('EPROC flags default OFF', $ok);
    }

    private function testRequiresMaster(): void
    {
        putenv('RATEB_OFFLINE_PROCUREMENT_ENTERPRISE=1');
        $_ENV['RATEB_OFFLINE_PROCUREMENT_ENTERPRISE'] = '1';
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.procurement_enterprise') === true
            && $svc->isProcurementEnterpriseEnabled() === false;
        $this->record('EPROC requires offline.enabled', $ok);
        $this->clearEnv();
    }

    private function testSubflagsRequireParent(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_SUPPLIERS=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_SUPPLIERS'] = '1';
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isProcurementEnterpriseSuppliersEnabled() === false;
        $this->record('Subflags require offline.procurement_enterprise', $ok);
        $this->clearEnv();
    }

    private function testEntityManifestHasEproc(): void
    {
        $m = require RATEB_ROOT . '/offline/config/entity-manifest.php';
        $ok = isset($m['eproc_supplier_profile_create'], $m['eproc_workflow_transition'], $m['eproc_supplier_category_directory'])
            && ($m['eproc_supplier_profile_create']['module'] ?? '') === 'procurement_enterprise'
            && ($m['eproc_supplier_profile_create']['replay'] ?? '') === 'delegate_procurement_enterprise';
        $this->record('entity manifest has EPROC', $ok);
    }

    private function testModulesRegistryActiveEproc(): void
    {
        $m = require RATEB_ROOT . '/offline/config/modules.php';
        $ok = in_array('procurement_enterprise', $m['active_modules'] ?? [], true)
            && in_array('procurement_enterprise', $m['tiers']['T1'] ?? [], true)
            && isset($m['operations']['procurement_enterprise.supplier_profile.create'])
            && in_array('procurement', $m['active_modules'] ?? [], true);
        $this->record('modules registry active EPROC (+ legacy procurement)', $ok);
    }

    private function testDeferredActionsCoverRequirements(): void
    {
        $a = ProcurementEnterpriseOfflineReplayService::deferredActions();
        $need = [
            'supplier_profile.create', 'supplier_profile.update',
            'qualification.create', 'qualification.update',
            'risk.create', 'scorecard.create', 'portal_invite.create',
            'tender.create', 'bid.create', 'bid_compare.create', 'contract.create',
            'collaboration.create', 'assignment.create', 'comment.create',
            'workflow.transition', 'note.create',
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
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/procurement-enterprise-adapter.js');
        $ok = str_contains($src, "module: 'procurement_enterprise'")
            && str_contains($src, 'enqueueSupplierProfileCreate')
            && str_contains($src, 'enqueueTenderCreate')
            && str_contains($src, 'enqueueContractCreate')
            && str_contains($src, 'enqueueWorkflowTransition')
            && !preg_match('/enqueue\([\'"]delete/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*payment/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*email/i', $src);
        $this->record('client adapter queues Tier-1 drafts only', $ok);
    }

    private function testSdkBundleHasEprocAdapter(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($src, 'RatebOfflineProcurementEnterpriseAdapter')
            && str_contains($src, 'isProcurementEnterpriseEnabled')
            && str_contains($src, 'procurementEnterprise: function')
            && str_contains($src, "'offline.procurement_enterprise'")
            && str_contains($src, '14.2.0');
        $this->record('SDK bundle contains EPROC adapter', $ok);
    }

    private function testReplayUsesExistingDomainOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ProcurementEnterpriseOfflineReplayService.php');
        $ok = str_contains($src, 'SupplierProfileService')
            && str_contains($src, 'ProcurementWorkflowService')
            && str_contains($src, 'EnterpriseTenderService')
            && str_contains($src, 'EnterpriseContractService')
            && str_contains($src, 'BidComparisonService')
            && str_contains($src, 'SupplierQualificationService')
            && !str_contains($src, 'INSERT INTO rateb_eproc_')
            && !preg_match('/new\s+\\\\?ProcurementService\b/', $src);
        $this->record('replay uses existing EPROC domain only', $ok);
    }

    private function testNoDeletePaymentsApprovalsEmailSmsGovBinary(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ProcurementEnterpriseOfflineReplayService.php');
        $ok = !preg_match('/softDelete|->delete\(/', $src)
            && !preg_match('/PaymentService|NotificationService|sendEmail|sendSms|move_uploaded_file|Zatca|Government/', $src)
            && !preg_match('/ApprovalRequestService|ApprovalWorkflowService/', $src);
        $this->record('replay excludes delete/payments/approvals/email/sms/gov/binary', $ok);
    }

    private function testConflictStatusChanged(): void
    {
        $r = (new OfflineConflictResolverService())->resolveProcurementEnterprise(
            ['version' => 2, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'active']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'status_changed';
        $this->record('conflict status_changed', $ok, json_encode($r));
    }

    private function testConflictServerNewerStillWins(): void
    {
        $r = (new OfflineConflictResolverService())->resolveProcurementEnterprise(
            ['version' => 1, 'expected_status' => 'draft'],
            ['version' => 5, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'server_newer';
        $this->record('conflict server_newer still wins', $ok, json_encode($r));
    }

    private function testConflictAcceptWhenStatusMatches(): void
    {
        $r = (new OfflineConflictResolverService())->resolveProcurementEnterprise(
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
            'module' => 'procurement_enterprise',
            'action' => 'supplier_profile.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'supplier_profile.create', 'payload' => ['name' => 'x']]),
        ]);
        $ok = ($out['status'] ?? '') === 'skipped'
            && ($out['error'] ?? '') === 'procurement_enterprise_offline_disabled';
        $this->record('replay skips EPROC when flag OFF', $ok, json_encode($out));
    }

    private function testReplayEngineDelegatesWhenFlagOn(): void
    {
        $this->enableAll();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'procurement_enterprise',
            'action' => 'supplier_profile.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'supplier_profile.create', 'payload' => []]),
        ]);
        $ok = ($out['status'] ?? '') !== 'skipped'
            || ($out['error'] ?? '') !== 'procurement_enterprise_offline_disabled';
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
            'client_id' => 'eproc-off-' . bin2hex(random_bytes(3)),
            'module' => 'procurement_enterprise',
            'action' => 'supplier_profile.create',
            'payload' => ['name' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($res['rejected'] ?? 0) >= 1 && (int) ($res['accepted'] ?? 0) === 0;
        $this->record('queue rejects EPROC when flag OFF', $ok, json_encode($res));
        $this->clearEnv();
    }

    private function testQueueRejectsSuppliersWithoutSubflag(): void
    {
        $this->enableBase();
        putenv('RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_SUPPLIERS');
        unset($_ENV['RATEB_OFFLINE_PROCUREMENT_ENTERPRISE_SUPPLIERS']);
        $res = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'eproc-sub-' . bin2hex(random_bytes(3)),
            'module' => 'procurement_enterprise',
            'action' => 'supplier_profile.create',
            'payload' => ['name' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($res['rejected'] ?? 0) >= 1;
        $this->record('queue rejects suppliers without subflag', $ok, json_encode($res));
        $this->clearEnv();
    }

    private function testQueueAliases(): void
    {
        $this->enableAll();
        $ref = new \ReflectionClass(OfflineQueueService::class);
        $m = $ref->getMethod('normalizeProcurementEnterpriseAction');
        $m->setAccessible(true);
        $svc = new OfflineQueueService();
        $ok = $m->invoke($svc, 'create_supplier_profile') === 'supplier_profile.create'
            && $m->invoke($svc, 'procurement_enterprise.workflow.transition') === 'workflow.transition'
            && $m->invoke($svc, 'create_bid_compare') === 'bid_compare.create'
            && $m->invoke($svc, 'bogus') === '';
        $this->record('queue aliases normalize', $ok);
        $this->clearEnv();
    }

    private function testPayloadSanitizerKeepsModule(): void
    {
        $n = (new OfflinePayloadSanitizer())->normalize([
            'module' => 'procurement_enterprise',
            'action' => 'supplier_profile.create',
            'url' => 'http://evil',
            'payload' => ['name' => 't'],
        ]);
        $ok = ($n['module'] ?? '') === 'procurement_enterprise' && !isset($n['url']);
        $this->record('payload sanitizer keeps procurement_enterprise module', $ok, json_encode($n));
    }

    private function testAuthzAllowsAbility(): void
    {
        \Rateb\App\Core\TenantContext::setCompanyId(1);
        \Rateb\App\Core\TenantContext::setApiModules(['procurement_enterprise']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === true;
        $this->record('authz allows procurement_enterprise ability', $ok);
        \Rateb\App\Core\TenantContext::setApiModules(null);
        \Rateb\App\Core\TenantContext::setCompanyId(null);
    }

    private function testTenantGuardSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ProcurementEnterpriseOfflineTenantGuard.php');
        $ok = str_contains($src, 'assertProfile')
            && str_contains($src, 'assertTender')
            && str_contains($src, 'assertContract')
            && str_contains($src, 'profileExistsForKey')
            && str_contains($src, 'branch_mismatch')
            && class_exists(ProcurementEnterpriseOfflineTenantGuard::class);
        $this->record('tenant guard source', $ok);
    }

    private function testMasterDataEntitiesRegistered(): void
    {
        $names = ProcurementEnterpriseOfflineMasterDataDirectoryService::entityNames();
        $cfg = require RATEB_ROOT . '/offline/config/master-data-entities.php';
        $entities = $cfg['entities'] ?? [];
        $ok = in_array('eproc_supplier_category_directory', $names, true)
            && in_array('eproc_rfq_template_directory', $names, true)
            && in_array('eproc_tag_directory', $names, true)
            && isset($entities['eproc_supplier_category_directory']);
        $this->record('master-data entities registered', $ok, implode(',', $names));
    }

    private function testOpsAllowlistEproc(): void
    {
        $cfg = OfflineModule::opsPageAllowlist();
        $paths = $cfg['paths'] ?? [];
        $ok = in_array('eproc', $paths, true)
            && in_array('eproc/suppliers', $paths, true)
            && in_array('eproc/tenders', $paths, true)
            && in_array('eproc/contracts', $paths, true);
        $this->record('ops allowlist EPROC', $ok);
    }

    private function testOpsFormsEprocHooks(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/ops-forms-adapter.js');
        $ok = str_contains($src, "module: 'procurement_enterprise'")
            && str_contains($src, 'supplier_profile.create')
            && str_contains($src, 'offline.procurement_enterprise')
            && str_contains($src, 'RatebOfflineProcurementEnterpriseAdapter');
        $this->record('ops forms EPROC hooks', $ok);
    }

    private function testBackgroundReportsFlag(): void
    {
        $this->clearEnv();
        $stats = (new OfflineBackgroundSync())->process(1, 1);
        $ok = array_key_exists('procurement_enterprise_enabled', $stats)
            && ($stats['procurement_enterprise_enabled'] ?? true) === false;
        $this->record('background reports procurement_enterprise_enabled', $ok, json_encode($stats));
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
