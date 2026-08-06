<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Logistics\Policies\LogisticsStatusPolicy;
use Rateb\App\Logistics\Repositories\LogisticsDeliveryOrderRepository;
use Rateb\App\Logistics\Repositories\LogisticsDriverRepository;
use Rateb\App\Logistics\Repositories\LogisticsExpenseRepository;
use Rateb\App\Logistics\Repositories\LogisticsRouteRepository;
use Rateb\App\Logistics\Repositories\LogisticsShipmentRepository;
use Rateb\App\Logistics\Repositories\LogisticsStatusHistoryRepository;
use Rateb\App\Logistics\Repositories\LogisticsTripRepository;
use Rateb\App\Logistics\Repositories\LogisticsVehicleRepository;

final class LogisticsStatusService
{
    public function __construct(
        private LogisticsStatusHistoryRepository $history = new LogisticsStatusHistoryRepository(),
        private LogisticsVehicleRepository $vehicles = new LogisticsVehicleRepository(),
        private LogisticsDriverRepository $drivers = new LogisticsDriverRepository(),
        private LogisticsRouteRepository $routes = new LogisticsRouteRepository(),
        private LogisticsDeliveryOrderRepository $deliveryOrders = new LogisticsDeliveryOrderRepository(),
        private LogisticsTripRepository $trips = new LogisticsTripRepository(),
        private LogisticsShipmentRepository $shipments = new LogisticsShipmentRepository(),
        private LogisticsExpenseRepository $expenses = new LogisticsExpenseRepository(),
    ) {
    }

    /**
     * @param array<string, mixed> $extraUpdate
     * @return array{ok:bool,entity_type:string,entity_id:int,from:string,to:string}
     */
    public function transition(
        int $companyId,
        string $entityType,
        int $entityId,
        string $toStatus,
        ?string $reason = null,
        array $extraUpdate = []
    ): array {
        if ($companyId < 1 || $entityId < 1) {
            throw new \InvalidArgumentException('logistics_invalid_context');
        }
        TenantContext::setCompanyId($companyId);

        $row = $this->findEntity($companyId, $entityType, $entityId);
        if ($row === null) {
            throw new \RuntimeException('logistics_entity_not_found');
        }

        $from = (string) ($row['status'] ?? '');
        $to = trim($toStatus);
        LogisticsStatusPolicy::assertTransition($entityType, $from, $to);

        $update = array_merge($extraUpdate, [
            'status' => $to,
            'updated_by' => $this->userId(),
        ]);
        $this->updateEntity($companyId, $entityType, $entityId, $update);

        $this->history->create($companyId, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'created_by' => $this->userId(),
        ]);

        return [
            'ok' => true,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'from' => $from,
            'to' => $to,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function history(int $companyId, string $entityType, int $entityId): array
    {
        return $this->history->listForEntity($companyId, $entityType, $entityId);
    }

    /** @return array<string, mixed>|null */
    private function findEntity(int $companyId, string $entityType, int $entityId): ?array
    {
        return match ($entityType) {
            LogisticsStatusPolicy::ENTITY_VEHICLE => $this->vehicles->find($entityId, $companyId),
            LogisticsStatusPolicy::ENTITY_DRIVER => $this->drivers->find($entityId, $companyId),
            LogisticsStatusPolicy::ENTITY_ROUTE => $this->routes->find($entityId, $companyId),
            LogisticsStatusPolicy::ENTITY_DELIVERY_ORDER => $this->deliveryOrders->find($entityId, $companyId),
            LogisticsStatusPolicy::ENTITY_TRIP => $this->trips->find($entityId, $companyId),
            LogisticsStatusPolicy::ENTITY_SHIPMENT => $this->shipments->find($entityId, $companyId),
            LogisticsStatusPolicy::ENTITY_EXPENSE => $this->expenses->find($entityId, $companyId),
            default => null,
        };
    }

    /** @param array<string, mixed> $update */
    private function updateEntity(int $companyId, string $entityType, int $entityId, array $update): void
    {
        $ok = match ($entityType) {
            LogisticsStatusPolicy::ENTITY_VEHICLE => $this->vehicles->update($entityId, $companyId, $update),
            LogisticsStatusPolicy::ENTITY_DRIVER => $this->drivers->update($entityId, $companyId, $update),
            LogisticsStatusPolicy::ENTITY_ROUTE => $this->routes->update($entityId, $companyId, $update),
            LogisticsStatusPolicy::ENTITY_DELIVERY_ORDER => $this->deliveryOrders->update($entityId, $companyId, $update),
            LogisticsStatusPolicy::ENTITY_TRIP => $this->trips->update($entityId, $companyId, $update),
            LogisticsStatusPolicy::ENTITY_SHIPMENT => $this->shipments->update($entityId, $companyId, $update),
            LogisticsStatusPolicy::ENTITY_EXPENSE => $this->expenses->update($entityId, $companyId, $update),
            default => false,
        };
        if (!$ok) {
            throw new \RuntimeException('logistics_status_update_failed');
        }
    }

    private function userId(): ?int
    {
        $id = (int) (SessionManager::get('rateb_user_id') ?? 0);

        return $id > 0 ? $id : null;
    }
}
