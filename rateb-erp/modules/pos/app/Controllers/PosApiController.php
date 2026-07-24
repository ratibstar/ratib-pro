<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Pos\Services\PosContextService;
use Rateb\App\Pos\Services\PosHardwareManager;
use Rateb\App\Pos\Services\PosOfflineSyncService;
use Rateb\App\Pos\Services\PosPricingService;
use Rateb\App\Pos\Services\PosSyncValidateService;

final class PosApiController extends PosBaseController
{
    public function context(): void
    {
        $this->bootstrapPos();
        $this->json([
            'ok' => true,
            'context' => (new PosContextService())->snapshot(),
            'hardware' => (new PosHardwareManager())->deviceStatus(),
        ]);
    }

    public function syncStatus(): void
    {
        $this->bootstrapPos();
        $companyId = $this->companyId();
        $service = new PosOfflineSyncService();
        $this->json([
            'ok' => true,
            'status' => $service->status($companyId > 0 ? $companyId : null),
        ]);
    }

    /**
     * Phase 11 — dry-run validate only. No invoice / inventory / accounting / synced mark.
     */
    public function syncValidate(): void
    {
        $this->bootstrapPos();
        $body = $this->jsonBody();
        $payload = is_array($body['payload'] ?? null) ? $body['payload'] : $body;
        if (!is_array($payload)) {
            $payload = [];
        }
        $service = new PosSyncValidateService();
        $result = $service->validate($payload, [
            'company_id' => $this->companyId(),
        ]);
        $this->json([
            'ok' => true,
            'accepted' => (bool) ($result['accepted'] ?? false),
            'conflicts' => $result['conflicts'] ?? [],
            'warnings' => $result['warnings'] ?? [],
            'dry_run' => true,
            'mode' => 'DRY_RUN_ONLY',
            'inventory_deducted' => false,
            'accounting_posted' => false,
            'invoice_created' => false,
            'marked_synced' => false,
            'echo' => $result['echo'] ?? null,
        ]);
    }

    public function syncPush(): void
    {
        $this->bootstrapPos();
        $this->requireSessionCsrfOrAbort();
        $body = $this->jsonBody();
        $items = $body['items'] ?? $body;
        if (!is_array($items)) {
            $items = [];
        }
        if ($items !== [] && !array_is_list($items)) {
            $items = [$items];
        }
        $this->authorizeAndBindSyncItems($items);
        $companyId = $this->companyId();
        $service = new PosOfflineSyncService();
        $result = $service->pushQueue($items, [
            'company_id' => $companyId,
            'terminal_id' => (int) ($body['terminal_id'] ?? 0),
            'branch_id' => (int) ($body['branch_id'] ?? 0),
        ]);
        $ack = (new \Rateb\App\Pos\Services\PosPushAckContract())->evaluate($result);
        $result['clearable_keys'] = $ack['clearable_keys'];
        http_response_code($ack['http_status']);
        $this->json([
            'ok' => $ack['ok'],
            'result' => $result,
        ]);
    }

    public function syncProcess(): void
    {
        $this->bootstrapPos();
        $this->guardPosManage('pos/sync');
        $this->requireSessionCsrfOrAbort();
        $companyId = $this->companyId();
        $service = new PosOfflineSyncService();
        $this->json([
            'ok' => true,
            'result' => $service->processPending($companyId > 0 ? $companyId : null, 50),
        ]);
    }

    public function syncConflicts(): void
    {
        $this->bootstrapPos();
        $companyId = $this->companyId();
        $service = new PosOfflineSyncService();
        $this->json([
            'ok' => true,
            'conflicts' => $service->openConflicts(50, $companyId > 0 ? $companyId : null),
        ]);
    }

    public function syncResolveConflict(int $id): void
    {
        $this->bootstrapPos();
        $this->guardPosManage('pos/sync');
        $this->requireSessionCsrfOrAbort();
        $body = $this->jsonBody();
        $resolution = trim((string) ($body['resolution'] ?? $_POST['resolution'] ?? ''));
        $companyId = $this->companyId();
        $service = new PosOfflineSyncService();
        $this->json($service->resolveConflict(
            $id,
            $resolution,
            $this->userId(),
            $companyId > 0 ? $companyId : null
        ));
    }

    public function pricingPreview(): void
    {
        $this->bootstrapPos();
        $this->json(['ok' => true, 'totals' => (new PosPricingService())->calculate([])]);
    }

    /** @param array<int, mixed> $items */
    private function authorizeAndBindSyncItems(array &$items): void
    {
        $permissionByAction = [
            'checkout' => 'pos.sale.complete',
            'complete_sale' => 'pos.sale.complete',
            'process_return' => 'pos.returns.manage',
            'process_exchange' => 'pos.returns.manage',
            'shift_open' => 'pos.shift.open',
            'shift_close' => 'pos.shift.close',
            'drawer_event' => 'pos.cash_drawer.manage',
            'suspend' => 'pos.register',
            'resume_suspended' => 'pos.register',
        ];
        foreach ($items as &$item) {
            if (!is_array($item)) {
                continue;
            }
            $action = trim((string) ($item['action'] ?? ''));
            $permission = $permissionByAction[$action] ?? 'pos.sync.manage';
            $this->guardPosPermission($permission, 'pos/sync');
            if ($action === 'process_exchange') {
                $this->guardPosPermission('pos.sale.complete', 'pos/sync');
            }
            $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
            $scope = is_array($payload['scope'] ?? null) ? $payload['scope'] : [];
            $scope['user_id'] = $this->userId();
            $payload['scope'] = $scope;
            $item['payload'] = $payload;
        }
        unset($item);
    }
}
