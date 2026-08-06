<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Logistics\Contracts\CompanyNotifier;
use Rateb\App\Logistics\Contracts\StockMovementRecorder;
use Rateb\App\Logistics\Contracts\StockMovementReferenceLookup;
use Rateb\App\Logistics\Policies\LogisticsStatusPolicy;
use Rateb\App\Logistics\Repositories\LogisticsShipmentRepository;
use Rateb\App\Logistics\Services\Integration\ErpCompanyNotifier;
use Rateb\App\Logistics\Services\Integration\ErpStockMovementRecorder;
use Rateb\App\Logistics\Services\Integration\ErpStockMovementReferenceLookup;

/**
 * Dispatch shipment → StockMovementService (reference_type=logistics_shipment).
 * Idempotent: blocks repeat dispatch for the same shipment.
 */
final class LogisticsDispatchService
{
    public const REFERENCE_TYPE = 'logistics_shipment';

    public function __construct(
        private LogisticsShipmentRepository $shipments = new LogisticsShipmentRepository(),
        private LogisticsStatusService $status = new LogisticsStatusService(),
        private StockMovementRecorder $stock = new ErpStockMovementRecorder(),
        private StockMovementReferenceLookup $movementLookup = new ErpStockMovementReferenceLookup(),
        private LogisticsNotificationService $notifications = new LogisticsNotificationService(),
    ) {
    }

    public function isDispatched(int $companyId, int $shipmentId): bool
    {
        return $this->movementLookup->existsForReference($companyId, self::REFERENCE_TYPE, $shipmentId);
    }

    /**
     * @param array<int, array<string, mixed>> $lines inventory lines: inventory_id, quantity, warehouse_id?
     * @return array{shipment_id:int,movement_ids:list<int>,from:string,to:string}
     */
    public function dispatch(int $companyId, int $shipmentId, array $lines): array
    {
        if ($companyId < 1 || $shipmentId < 1) {
            throw new \InvalidArgumentException('logistics_invalid_context');
        }
        TenantContext::setCompanyId($companyId);

        $shipment = $this->shipments->find($shipmentId, $companyId);
        if ($shipment === null) {
            throw new \RuntimeException(__('no_records'));
        }

        if ($this->isDispatched($companyId, $shipmentId)) {
            throw new \RuntimeException(__('logistics_dispatch_already_done'));
        }

        $status = (string) ($shipment['status'] ?? 'created');
        if (in_array($status, ['out_for_delivery', 'delivered', 'failed'], true)) {
            throw new \RuntimeException(__('logistics_dispatch_status_blocked'));
        }

        $normalizedLines = $this->normalizeLines($lines);
        if ($normalizedLines === []) {
            throw new \RuntimeException(__('logistics_dispatch_lines_required'));
        }

        $tracking = (string) ($shipment['tracking_number'] ?? $shipmentId);
        $movementIds = [];
        foreach ($normalizedLines as $line) {
            $movementIds[] = $this->stock->record([
                'inventory_id' => $line['inventory_id'],
                'movement_type' => 'out',
                'quantity' => $line['quantity'],
                'warehouse_id' => $line['warehouse_id'],
                'reference_type' => self::REFERENCE_TYPE,
                'reference_id' => $shipmentId,
                'notes' => 'Logistics dispatch ' . $tracking,
            ]);
        }

        $from = $status;
        $this->advanceToShipped($companyId, $shipmentId, $status);
        $updated = $this->shipments->find($shipmentId, $companyId) ?? $shipment;
        $to = (string) ($updated['status'] ?? 'shipped');

        $this->notifications->shipmentDispatched($companyId, $updated);

        return [
            'shipment_id' => $shipmentId,
            'movement_ids' => $movementIds,
            'from' => $from,
            'to' => $to,
        ];
    }

    private function advanceToShipped(int $companyId, int $shipmentId, string $current): void
    {
        if ($current === 'shipped') {
            return;
        }

        $chain = [
            'created' => 'picked',
            'picked' => 'packed',
            'packed' => 'shipped',
        ];

        $guard = 0;
        while ($current !== 'shipped' && $guard < 5) {
            ++$guard;
            $next = $chain[$current] ?? null;
            if ($next === null) {
                throw new \RuntimeException(__('logistics_dispatch_status_blocked'));
            }
            LogisticsStatusPolicy::assertTransition(LogisticsStatusPolicy::ENTITY_SHIPMENT, $current, $next);
            $extra = $next === 'shipped' ? ['dispatched_at' => date('Y-m-d H:i:s')] : [];
            $this->status->transition(
                $companyId,
                LogisticsStatusPolicy::ENTITY_SHIPMENT,
                $shipmentId,
                $next,
                'dispatch',
                $extra
            );
            $current = $next;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return list<array{inventory_id:int,quantity:float,warehouse_id:?int}>
     */
    private function normalizeLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $inventoryId = (int) ($line['inventory_id'] ?? 0);
            $quantity = (float) ($line['quantity'] ?? 0);
            if ($inventoryId < 1 || $quantity <= 0) {
                continue;
            }
            $warehouseId = isset($line['warehouse_id']) ? (int) $line['warehouse_id'] : 0;
            $out[] = [
                'inventory_id' => $inventoryId,
                'quantity' => $quantity,
                'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
            ];
        }

        return $out;
    }
}
