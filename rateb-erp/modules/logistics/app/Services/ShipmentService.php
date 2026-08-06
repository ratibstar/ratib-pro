<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Logistics\Policies\LogisticsStatusPolicy;
use Rateb\App\Logistics\Repositories\LogisticsDeliveryProofRepository;
use Rateb\App\Logistics\Repositories\LogisticsShipmentRepository;

final class ShipmentService
{
    public function __construct(
        private LogisticsShipmentRepository $shipments = new LogisticsShipmentRepository(),
        private LogisticsDeliveryProofRepository $proofs = new LogisticsDeliveryProofRepository(),
        private DeliveryOrderService $deliveryOrders = new DeliveryOrderService(),
        private TripService $trips = new TripService(),
        private LogisticsStatusService $status = new LogisticsStatusService(),
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0): array
    {
        return $this->shipments->listForCompany($companyId, $limit, $offset);
    }

    public function countForCompany(int $companyId): int
    {
        return $this->shipments->countForCompany($companyId);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $companyId): ?array
    {
        return $this->shipments->find($id, $companyId);
    }

    /** @param array<string, mixed> $data */
    public function create(int $companyId, array $data): int
    {
        $this->assertCompany($companyId);
        TenantContext::setCompanyId($companyId);
        $payload = $this->normalize($companyId, $data, true);
        $payload['created_by'] = $this->userId();

        return $this->shipments->create($companyId, $payload);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, int $companyId, array $data): bool
    {
        $existing = $this->require($id, $companyId);
        if (in_array((string) ($existing['status'] ?? ''), ['delivered', 'failed'], true)) {
            throw new \RuntimeException(__('logistics_shipment_locked'));
        }
        $payload = $this->normalize($companyId, $data, false);
        $payload['updated_by'] = $this->userId();
        unset($payload['status']);

        return $this->shipments->update($id, $companyId, $payload);
    }

    public function transition(int $id, int $companyId, string $toStatus, ?string $reason = null): array
    {
        $this->require($id, $companyId);
        $extra = [];
        if ($toStatus === 'shipped') {
            $extra['dispatched_at'] = date('Y-m-d H:i:s');
        }
        if ($toStatus === 'delivered') {
            $extra['delivered_at'] = date('Y-m-d H:i:s');
        }

        return $this->status->transition(
            $companyId,
            LogisticsStatusPolicy::ENTITY_SHIPMENT,
            $id,
            $toStatus,
            $reason,
            $extra
        );
    }

    /**
     * Mark delivered and optionally store proof-of-delivery fields.
     *
     * @param array<string, mixed> $proof
     * @return array{ok:bool,entity_type:string,entity_id:int,from:string,to:string}
     */
    public function deliver(int $id, int $companyId, array $proof = [], ?string $reason = null): array
    {
        $result = $this->transition($id, $companyId, 'delivered', $reason ?? 'shipment_deliver');
        if ($proof !== []) {
            $existing = $this->proofs->findByShipment($id, $companyId);
            $payload = [
                'shipment_id' => $id,
                'receiver_name' => trim((string) ($proof['receiver_name'] ?? '')) ?: null,
                'signature_file' => trim((string) ($proof['signature_file'] ?? '')) ?: null,
                'photo_file' => trim((string) ($proof['photo_file'] ?? '')) ?: null,
                'gps_lat' => isset($proof['gps_lat']) && $proof['gps_lat'] !== '' ? (float) $proof['gps_lat'] : null,
                'gps_long' => isset($proof['gps_long']) && $proof['gps_long'] !== '' ? (float) $proof['gps_long'] : null,
                'delivered_at' => date('Y-m-d H:i:s'),
                'notes' => trim((string) ($proof['notes'] ?? '')) ?: null,
                'created_by' => $this->userId(),
            ];
            if ($existing !== null) {
                $this->proofs->update((int) $existing['id'], $companyId, $payload);
            } else {
                $this->proofs->create($companyId, $payload);
            }
        }

        return $result;
    }

    public function delete(int $id, int $companyId): bool
    {
        $row = $this->require($id, $companyId);
        if ((string) ($row['status'] ?? '') !== 'created') {
            throw new \RuntimeException(__('logistics_delete_blocked'));
        }

        return $this->shipments->delete($id, $companyId);
    }

    /** @return list<string> */
    public function nextStatuses(int $id, int $companyId): array
    {
        $row = $this->require($id, $companyId);
        $from = (string) ($row['status'] ?? 'created');

        return LogisticsStatusPolicy::allowedTransitions(LogisticsStatusPolicy::ENTITY_SHIPMENT)[$from] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function history(int $id, int $companyId): array
    {
        $this->require($id, $companyId);

        return $this->status->history($companyId, LogisticsStatusPolicy::ENTITY_SHIPMENT, $id);
    }

    /** @return array<string, mixed> */
    private function normalize(int $companyId, array $data, bool $isCreate): array
    {
        $tracking = strtoupper(trim((string) ($data['tracking_number'] ?? '')));
        if ($tracking === '') {
            $tracking = 'TRK-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }

        $doId = (int) ($data['delivery_order_id'] ?? 0);
        if ($doId > 0 && $this->deliveryOrders->find($doId, $companyId) === null) {
            throw new \RuntimeException(__('logistics_delivery_order_invalid'));
        }
        $tripId = (int) ($data['trip_id'] ?? 0);
        if ($tripId > 0 && $this->trips->find($tripId, $companyId) === null) {
            throw new \RuntimeException(__('logistics_trip_invalid'));
        }

        return [
            'company_id' => $companyId,
            'branch_id' => ((int) ($data['branch_id'] ?? 0)) ?: null,
            'customer_id' => ((int) ($data['customer_id'] ?? 0)) ?: null,
            'order_id' => ((int) ($data['order_id'] ?? 0)) ?: null,
            'delivery_order_id' => $doId > 0 ? $doId : null,
            'trip_id' => $tripId > 0 ? $tripId : null,
            'tracking_number' => $tracking,
            'pickup_location' => trim((string) ($data['pickup_location'] ?? '')) ?: null,
            'delivery_location' => trim((string) ($data['delivery_location'] ?? '')) ?: null,
            'status' => $isCreate ? 'created' : (string) ($data['status'] ?? 'created'),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }

    /** @return array<string, mixed> */
    private function require(int $id, int $companyId): array
    {
        $row = $this->find($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException(__('no_records'));
        }

        return $row;
    }

    private function assertCompany(int $companyId): void
    {
        if ($companyId < 1) {
            throw new \RuntimeException(__('select_company_ops'));
        }
    }

    private function userId(): ?int
    {
        $id = (int) (SessionManager::get('rateb_user_id') ?? 0);

        return $id > 0 ? $id : null;
    }
}
