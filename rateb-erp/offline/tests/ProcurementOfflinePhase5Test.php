<?php

declare(strict_types=1);

/**
 * Phase 5 — Procurement Offline (Tier 1) tests.
 *
 * Run: php offline/tests/run-procurement-offline-tests.php
 */

use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflinePushAckContract;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;
use Rateb\App\Offline\Services\OfflineSyncService;
use Rateb\App\Offline\Services\ProcurementOfflineReplayService;
use Rateb\App\Offline\Services\ProcurementOfflineSupplierDirectoryService;
use Rateb\App\Offline\Services\ProcurementOfflineTenantGuard;

final class ProcurementOfflinePhase5Test
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testFlagDefaultOff();
        $this->testRequiresMaster();
        $this->testEntityManifestHasProcurement();
        $this->testModulesRegistryActiveProcurement();
        $this->testDeferredActionsCoverRequirements();
        $this->testClientAdapterSource();
        $this->testSdkBundleHasProcurementAdapter();
        $this->testReplayUsesExistingDomainOnly();
        $this->testNoApprovalsPaymentsAccountingInReplay();

        $this->testConflictStatusChanged();
        $this->testConflictServerNewerStillWins();
        $this->testConflictAcceptWhenStatusMatches();

        $this->testReplayRejectsEmptyPr();
        $this->testReplayRejectsEmptyRfq();
        $this->testReplayRejectsPoWithoutSupplier();
        $this->testReplaySkipsWhenFlagOff();
        $this->testReplayEngineDelegatesWhenFlagOn();

        $this->testQueueRejectsWhenFlagOff();
        $this->testPayloadSanitizerKeepsProcurementModule();
        $this->testPushAckClearableContract();

        $this->testAuthzAllowsProcurementAbility();
        $this->testAuthzDeniesAccountingOnly();
        $this->testTenantGuardBranchIsolationSource();
        $this->testBackgroundSyncDisabledWhenMasterOff();
        $this->testSupplierDirectoryDisabledWhenFlagOff();
        $this->testQueueProcurementEnqueuePath();
        $this->testSyncStatusExposesProcurementFlag();
        $this->testBackgroundReportsProcurementFlag();

        $this->testStressAckEvaluations();
        $this->testStressProcurementConflictResolver();
        $this->testStressSanitizer();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        putenv('RATEB_OFFLINE_ENABLED');
        putenv('RATEB_OFFLINE_PROCUREMENT');
        unset($_ENV['RATEB_OFFLINE_ENABLED'], $_ENV['RATEB_OFFLINE_PROCUREMENT']);
    }

    private function enableFlags(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_PROCUREMENT=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_PROCUREMENT'] = '1';
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testFlagDefaultOff(): void
    {
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.procurement') === false && $svc->isProcurementEnabled() === false;
        $this->record('procurement flag default OFF', $ok);
    }

    private function testRequiresMaster(): void
    {
        putenv('RATEB_OFFLINE_PROCUREMENT=1');
        $_ENV['RATEB_OFFLINE_PROCUREMENT'] = '1';
        putenv('RATEB_OFFLINE_ENABLED');
        unset($_ENV['RATEB_OFFLINE_ENABLED']);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.procurement') === true && $svc->isProcurementEnabled() === false;
        $this->record('procurement requires master flag', $ok);
        $this->clearEnv();
    }

    private function testEntityManifestHasProcurement(): void
    {
        $cfg = require RATEB_ROOT . '/offline/config/entity-manifest.php';
        $ok = isset($cfg['procurement_purchase_request_draft'], $cfg['procurement_rfq_draft'], $cfg['procurement_purchase_order_draft'], $cfg['supplier_directory']);
        $this->record('entity manifest has procurement drafts + supplier directory', $ok);
    }

    private function testModulesRegistryActiveProcurement(): void
    {
        $cfg = require RATEB_ROOT . '/offline/config/modules.php';
        $active = $cfg['active_modules'] ?? [];
        $ops = $cfg['operations'] ?? [];
        $ok = in_array('procurement', $active, true)
            && isset($ops['procurement.purchase_request.draft'], $ops['procurement.rfq.draft'], $ops['procurement.purchase_order.draft']);
        $this->record('modules registry activates procurement ops', $ok);
    }

    private function testDeferredActionsCoverRequirements(): void
    {
        $a = ProcurementOfflineReplayService::deferredActions();
        $ok = in_array('purchase_request.draft', $a, true)
            && in_array('rfq.draft', $a, true)
            && in_array('purchase_order.draft', $a, true);
        $this->record('deferred actions cover PR/RFQ/PO drafts', $ok, implode(',', $a));
    }

    private function testClientAdapterSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/procurement-adapter.js');
        $ok = str_contains($src, 'purchase_request.draft')
            && str_contains($src, 'rfq.draft')
            && str_contains($src, 'purchase_order.draft')
            && str_contains($src, 'supplier_directory')
            && !preg_match('/enqueue\([\'"][^\'"]*approv/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*payment/i', $src);
        $this->record('client adapter queues drafts only', $ok);
    }

    private function testSdkBundleHasProcurementAdapter(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($src, 'RatebOfflineProcurementAdapter')
            && str_contains($src, 'isProcurementEnabled')
            && str_contains($src, '5.0.0')
            && str_contains($src, "'offline.procurement'");
        $this->record('SDK bundle contains procurement adapter v5', $ok);
    }

    private function testReplayUsesExistingDomainOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ProcurementOfflineReplayService.php');
        $ok = str_contains($src, 'PurchaseRequest')
            && str_contains($src, 'PurchaseOrder')
            && str_contains($src, 'Rfq')
            && str_contains($src, 'LineItems')
            && str_contains($src, 'DocumentCodeService')
            && str_contains($src, "status' => 'draft'");
        $this->record('replay uses existing procurement domain only', $ok);
    }

    private function testNoApprovalsPaymentsAccountingInReplay(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ProcurementOfflineReplayService.php');
        $ok = !preg_match('/->approve/i', $src)
            && !preg_match('/->submit\s*\(/', $src)
            && !preg_match('/JournalEntry|AccountingService|PaymentService|PurchaseInvoiceService/', $src)
            && str_contains($src, "status' => 'draft'");
        $this->record('replay excludes approvals/payments/accounting', $ok);
    }

    private function testConflictStatusChanged(): void
    {
        $r = (new OfflineConflictResolverService())->resolveProcurement(
            ['version' => 2, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'submitted']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'status_changed';
        $this->record('conflict status_changed', $ok, json_encode($r));
    }

    private function testConflictServerNewerStillWins(): void
    {
        $r = (new OfflineConflictResolverService())->resolveProcurement(
            ['version' => 1, 'expected_status' => 'draft'],
            ['version' => 5, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'server_newer';
        $this->record('conflict server_newer still wins', $ok, json_encode($r));
    }

    private function testConflictAcceptWhenStatusMatches(): void
    {
        $r = (new OfflineConflictResolverService())->resolveProcurement(
            ['version' => 3, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'accept_client';
        $this->record('conflict accept when status matches', $ok, json_encode($r));
    }

    private function testReplayRejectsEmptyPr(): void
    {
        $this->enableFlags();
        try {
            (new ProcurementOfflineReplayService())->replay('purchase_request.draft', [
                'company_id' => 1, 'branch_id' => 1, 'user_id' => 1,
            ], [], 'k-pr');
            $this->record('replay rejects empty PR', false, 'no exception');
        } catch (\Throwable $e) {
            $this->record('replay rejects empty PR', $e->getMessage() === 'empty_purchase_request_payload', $e->getMessage());
        }
        $this->clearEnv();
    }

    private function testReplayRejectsEmptyRfq(): void
    {
        $this->enableFlags();
        try {
            (new ProcurementOfflineReplayService())->replay('rfq.draft', [
                'company_id' => 1, 'branch_id' => 1, 'user_id' => 1,
            ], [], 'k-rfq');
            $this->record('replay rejects empty RFQ', false, 'no exception');
        } catch (\Throwable $e) {
            $this->record('replay rejects empty RFQ', $e->getMessage() === 'empty_rfq_payload', $e->getMessage());
        }
        $this->clearEnv();
    }

    private function testReplayRejectsPoWithoutSupplier(): void
    {
        $this->enableFlags();
        try {
            (new ProcurementOfflineReplayService())->replay('purchase_order.draft', [
                'company_id' => 1, 'branch_id' => 1, 'user_id' => 1,
            ], ['supplier_id' => 0], 'k-po');
            $this->record('replay rejects PO without supplier', false, 'no exception');
        } catch (\Throwable $e) {
            $ok = in_array($e->getMessage(), ['invalid_supplier_id', 'supplier_not_found', 'tenant_mismatch'], true);
            $this->record('replay rejects PO without supplier', $ok, $e->getMessage());
        }
        $this->clearEnv();
    }

    private function testReplaySkipsWhenFlagOff(): void
    {
        $this->clearEnv();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'procurement',
            'action' => 'purchase_request.draft',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'purchase_request.draft', 'payload' => ['title' => 'x']]),
        ]);
        $ok = ($out['status'] ?? '') === 'skipped'
            && ($out['error'] ?? '') === 'procurement_offline_disabled';
        $this->record('replay skips procurement when flag OFF', $ok, json_encode($out));
    }

    private function testReplayEngineDelegatesWhenFlagOn(): void
    {
        $this->enableFlags();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'procurement',
            'action' => 'purchase_request.draft',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'purchase_request.draft', 'payload' => []]),
        ]);
        // Flag on → delegates; empty title → failed (not skipped)
        $ok = ($out['status'] ?? '') !== 'skipped' || ($out['error'] ?? '') !== 'procurement_offline_disabled';
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
            'client_id' => 'proc-off-' . bin2hex(random_bytes(3)),
            'module' => 'procurement',
            'action' => 'purchase_request.draft',
            'payload' => ['title' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($result['rejected'] ?? 0) >= 1 && (int) ($result['accepted'] ?? 0) === 0;
        $this->record('queue rejects/blocks procurement without flag', $ok, json_encode($result));
        $this->clearEnv();
    }

    private function testPayloadSanitizerKeepsProcurementModule(): void
    {
        $n = (new OfflinePayloadSanitizer())->normalize([
            'module' => 'procurement',
            'action' => 'rfq.draft',
            'url' => 'http://evil',
            'payload' => ['title' => 't'],
        ]);
        $ok = ($n['module'] ?? '') === 'procurement' && !isset($n['url']);
        $this->record('payload sanitizer keeps procurement module', $ok, json_encode($n));
    }

    private function testPushAckClearableContract(): void
    {
        $ack = (new OfflinePushAckContract())->evaluate([
            'accepted' => 1,
            'duplicate' => 0,
            'conflict' => 1,
            'rejected' => 1,
            'accepted_keys' => ['a'],
            'duplicate_keys' => [],
            'conflict_keys' => ['c'],
            'rejected_keys' => ['r'],
        ]);
        $ok = ($ack['clearable_keys'] ?? null) === ['a'];
        $this->record('push ack clearable excludes conflict/rejected', $ok, json_encode($ack));
    }

    private function testAuthzAllowsProcurementAbility(): void
    {
        TenantContext::setCompanyId(1);
        TenantContext::setApiModules(['procurement']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === true;
        $this->record('authz allows procurement ability token', $ok);
        TenantContext::setApiModules(null);
        TenantContext::setCompanyId(null);
    }

    private function testAuthzDeniesAccountingOnly(): void
    {
        TenantContext::setCompanyId(1);
        TenantContext::setApiModules(['accounting']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === false;
        $this->record('authz denies accounting-only token', $ok);
        TenantContext::setApiModules(null);
        TenantContext::setCompanyId(null);
    }

    private function testTenantGuardBranchIsolationSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ProcurementOfflineTenantGuard.php');
        $ok = str_contains($src, 'branch_mismatch')
            && str_contains($src, 'tenant_mismatch')
            && str_contains($src, 'assertSupplier')
            && str_contains($src, 'assertPurchaseRequest');
        $this->record('tenant guard enforces branch + tenant isolation', $ok);
    }

    private function testBackgroundSyncDisabledWhenMasterOff(): void
    {
        $this->clearEnv();
        $out = (new OfflineBackgroundSync())->process(1, 5);
        $ok = !empty($out['disabled']);
        $this->record('background sync disabled when master OFF', $ok, json_encode($out));
    }

    private function testSupplierDirectoryDisabledWhenFlagOff(): void
    {
        $this->clearEnv();
        $out = (new ProcurementOfflineSupplierDirectoryService())->pull(1, 1, null, 10);
        $ok = !empty($out['disabled']) && ($out['items'] ?? null) === [];
        $this->record('supplier directory disabled when flag OFF', $ok, json_encode($out));
    }

    private function testQueueProcurementEnqueuePath(): void
    {
        $this->enableFlags();
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineQueueService.php');
        $ok = str_contains($src, "module === 'procurement'")
            && str_contains($src, 'normalizeProcurementAction')
            && str_contains($src, "enabled('offline.procurement')");
        $this->record('queue procurement enqueue path (DB optional)', $ok);
        $this->clearEnv();
    }

    private function testSyncStatusExposesProcurementFlag(): void
    {
        $snap = (new OfflineFeatureFlagService())->snapshot();
        $ok = array_key_exists('offline.procurement', $snap) && $snap['offline.procurement'] === false;
        $this->record('sync status exposes procurement flag', $ok, json_encode($snap));
    }

    private function testBackgroundReportsProcurementFlag(): void
    {
        $this->enableFlags();
        $out = (new OfflineBackgroundSync())->process(1, 1);
        $ok = array_key_exists('procurement_enabled', $out) && !empty($out['procurement_enabled']);
        $this->record('background reports procurement flag', $ok, json_encode($out));
        $this->clearEnv();
    }

    private function testStressAckEvaluations(): void
    {
        $ack = new OfflinePushAckContract();
        $n = 3000;
        $t0 = microtime(true);
        for ($i = 0; $i < $n; $i++) {
            $ack->evaluate([
                'accepted' => 1,
                'duplicate' => 0,
                'conflict' => 0,
                'rejected' => 0,
                'accepted_keys' => ['k' . $i],
                'duplicate_keys' => [],
                'conflict_keys' => [],
                'rejected_keys' => [],
            ]);
        }
        $ms = (microtime(true) - $t0) * 1000;
        $this->record('stress ack ' . $n . ' evaluations', $ms < 3000, 'ms=' . round($ms, 2));
    }

    private function testStressProcurementConflictResolver(): void
    {
        $r = new OfflineConflictResolverService();
        $n = 2000;
        $t0 = microtime(true);
        for ($i = 0; $i < $n; $i++) {
            $r->resolveProcurement(
                ['version' => $i + 2, 'expected_status' => 'draft'],
                ['version' => $i, 'status' => ($i % 2 === 0 ? 'draft' : 'submitted')]
            );
        }
        $ms = (microtime(true) - $t0) * 1000;
        $this->record('stress procurement conflict resolver ' . $n, $ms < 3000, 'ms=' . round($ms, 2));
    }

    private function testStressSanitizer(): void
    {
        $s = new OfflinePayloadSanitizer();
        $n = 1000;
        $t0 = microtime(true);
        for ($i = 0; $i < $n; $i++) {
            $s->normalize([
                'module' => 'procurement',
                'action' => 'purchase_order.draft',
                'url' => 'http://x/' . $i,
                'payload' => ['supplier_id' => $i, 'title' => 't' . $i],
            ]);
        }
        $ms = (microtime(true) - $t0) * 1000;
        $this->record('stress sanitizer ' . $n . ' procurement payloads', $ms < 2000, 'ms=' . round($ms, 2));
    }
}
