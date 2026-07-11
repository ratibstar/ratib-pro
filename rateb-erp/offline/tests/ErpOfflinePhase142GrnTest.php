<?php

declare(strict_types=1);

/**
 * Phase 14.2 — Enterprise Transaction Completion (Goods Receipt / GRN).
 *
 * Run: php offline/tests/run-erp-offline-phase142-tests.php
 */

use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflineReplayEngine;
use Rateb\App\Offline\Services\ProcurementOfflineReplayService;

final class ErpOfflinePhase142GrnTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testGrnFlagDefaultOff();
        $this->testGrnRequiresProcurementAndMaster();
        $this->testDeferredActionsIncludeGrn();
        $this->testReplayDelegatesToProcurementService();
        $this->testNoDuplicatedReceiveLogic();
        $this->testClientAdapterEnqueueGoodsReceipt();
        $this->testOpsFormsReceivePathHook();
        $this->testEntityManifestAndModules();
        $this->testQueueAliasesIncludeGrn();
        $this->testReplaySkipsWhenGrnFlagOff();
        $this->testInventoryAdjustmentAlreadyCovered();
        $this->testUpdateDeleteAttachmentsStopped();
        $this->testSdkBundleHasGrn();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_PROCUREMENT',
            'RATEB_OFFLINE_PROCUREMENT_GRN',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testGrnFlagDefaultOff(): void
    {
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.procurement.goods_receipt') === false
            && $svc->isProcurementGoodsReceiptEnabled() === false;
        $this->record('GRN flag default OFF', $ok);
    }

    private function testGrnRequiresProcurementAndMaster(): void
    {
        putenv('RATEB_OFFLINE_PROCUREMENT_GRN=1');
        $_ENV['RATEB_OFFLINE_PROCUREMENT_GRN'] = '1';
        putenv('RATEB_OFFLINE_PROCUREMENT=1');
        $_ENV['RATEB_OFFLINE_PROCUREMENT'] = '1';
        putenv('RATEB_OFFLINE_ENABLED');
        unset($_ENV['RATEB_OFFLINE_ENABLED']);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.procurement.goods_receipt') === true
            && $svc->isProcurementGoodsReceiptEnabled() === false;
        $this->record('GRN requires master + procurement', $ok);
        $this->clearEnv();
    }

    private function testDeferredActionsIncludeGrn(): void
    {
        $a = ProcurementOfflineReplayService::deferredActions();
        $ok = in_array('goods_receipt.receive', $a, true)
            && in_array('purchase_order.draft', $a, true);
        $this->record('deferred actions include goods_receipt.receive', $ok, implode(',', $a));
    }

    private function testReplayDelegatesToProcurementService(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ProcurementOfflineReplayService.php');
        $ok = str_contains($src, 'ProcurementService')
            && str_contains($src, 'receiveOrder')
            && str_contains($src, 'goodsReceiptReceive')
            && str_contains($src, 'stampGoodsReceiptMovements');
        $this->record('replay delegates to ProcurementService::receiveOrder', $ok);
    }

    private function testNoDuplicatedReceiveLogic(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ProcurementOfflineReplayService.php');
        $ok = !preg_match('/movement_type\'\s*=>\s*\'in\'/', $src)
            && !preg_match('/delivered_qty/', $src)
            && !str_contains($src, 'autoPostPurchaseOrder');
        $this->record('no duplicated GRN stock/accounting logic in replay', $ok);
    }

    private function testClientAdapterEnqueueGoodsReceipt(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/procurement-adapter.js');
        $ok = str_contains($src, 'enqueueGoodsReceipt')
            && str_contains($src, 'goods_receipt.receive')
            && str_contains($src, 'offline.procurement.goods_receipt')
            && str_contains($src, 'isGoodsReceiptActive');
        $this->record('client adapter exposes enqueueGoodsReceipt', $ok);
    }

    private function testOpsFormsReceivePathHook(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/ops-forms-adapter.js');
        $ok = str_contains($src, 'goods_receipt.receive')
            && str_contains($src, 'isPurchaseOrderReceivePath')
            && str_contains($src, 'buildGoodsReceipt')
            && str_contains($src, 'receive_qty');
        $this->record('ops forms maps PO /receive to GRN', $ok);
    }

    private function testEntityManifestAndModules(): void
    {
        $manifest = require RATEB_ROOT . '/offline/config/entity-manifest.php';
        $modules = require RATEB_ROOT . '/offline/config/modules.php';
        $ok = isset($manifest['procurement_goods_receipt'])
            && ($manifest['procurement_goods_receipt']['action'] ?? '') === 'goods_receipt.receive'
            && isset($modules['operations']['procurement.goods_receipt.receive']);
        $this->record('entity-manifest + modules register GRN', $ok);
    }

    private function testQueueAliasesIncludeGrn(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineQueueService.php');
        $ok = str_contains($src, 'offline.procurement.goods_receipt')
            && str_contains($src, 'goodsReceiptActions')
            && str_contains($src, 'receive_goods');
        $this->record('queue service gates + aliases GRN', $ok);
    }

    private function testReplaySkipsWhenGrnFlagOff(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_PROCUREMENT=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_PROCUREMENT'] = '1';
        putenv('RATEB_OFFLINE_PROCUREMENT_GRN');
        unset($_ENV['RATEB_OFFLINE_PROCUREMENT_GRN']);

        $out = (new OfflineReplayEngine())->replay([
            'module' => 'procurement',
            'action' => 'goods_receipt.receive',
            'idempotency_key' => 'grn-test-skip',
            'company_id' => 1,
            'payload' => json_encode([
                'action' => 'goods_receipt.receive',
                'payload' => ['purchase_order_id' => 1, 'receive_qty' => [1 => 1]],
            ], JSON_UNESCAPED_UNICODE),
        ]);
        $ok = ($out['status'] ?? '') === 'skipped'
            && ($out['error'] ?? '') === 'procurement_grn_offline_disabled';
        $this->record('replay skips GRN when flag OFF', $ok, json_encode($out));
        $this->clearEnv();
    }

    private function testInventoryAdjustmentAlreadyCovered(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/InventoryOfflineReplayService.php');
        $adapter = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/inventory-adapter.js');
        $ok = str_contains($src, 'stock_movement')
            && (str_contains($adapter, 'movement_type') || str_contains($adapter, 'enqueueMovement'));
        $this->record('inventory adjustment already covered via stock_movement', $ok);
    }

    private function testUpdateDeleteAttachmentsStopped(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ProcurementOfflineReplayService.php');
        $ok = !preg_match('/purchase_order\.update|purchase_order\.delete|attachment\.queue/i', $src)
            && !str_contains($src, 'soft_delete');
        $this->record('update/delete/attachments not invented', $ok);
    }

    private function testSdkBundleHasGrn(): void
    {
        $bundle = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($bundle, 'enqueueGoodsReceipt')
            && str_contains($bundle, 'goods_receipt.receive')
            && str_contains($bundle, 'offline.procurement.goods_receipt')
            && (str_contains($bundle, '14.2.0') || str_contains($bundle, 'Phase 14.2'));
        $this->record('SDK bundle includes Phase 14.2 GRN', $ok);
    }
}
