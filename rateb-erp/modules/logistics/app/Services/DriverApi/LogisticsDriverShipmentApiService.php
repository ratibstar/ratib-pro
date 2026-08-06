<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services\DriverApi;

use Rateb\App\Logistics\Policies\LogisticsStatusPolicy;
use Rateb\App\Logistics\Repositories\LogisticsDeliveryProofRepository;
use Rateb\App\Logistics\Repositories\LogisticsShipmentRepository;
use Rateb\App\Logistics\Repositories\LogisticsTripRepository;
use Rateb\App\Logistics\Services\LogisticsStatusService;
use Rateb\App\Logistics\Services\ShipmentService;

final class LogisticsDriverShipmentApiService
{
    public function __construct(
        private LogisticsShipmentRepository $shipments = new LogisticsShipmentRepository(),
        private LogisticsTripRepository $trips = new LogisticsTripRepository(),
        private LogisticsDeliveryProofRepository $proofs = new LogisticsDeliveryProofRepository(),
        private ShipmentService $shipmentService = new ShipmentService(),
        private LogisticsStatusService $status = new LogisticsStatusService(),
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function deliver(array $context, int $shipmentId, array $payload = []): array
    {
        try {
            $shipment = $this->requireOwnedShipment($context, $shipmentId);
        } catch (\Throwable $e) {
            return [
                'status' => 404,
                'body' => ['success' => false, 'code' => 'shipment_not_found', 'message' => 'Shipment not found for driver'],
            ];
        }

        $companyId = (int) $context['company_id'];
        $current = (string) ($shipment['status'] ?? 'created');
        if ($current === 'delivered') {
            $proofId = $this->upsertProof($companyId, $shipmentId, $payload);

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'code' => 'already_delivered',
                    'data' => [
                        'shipment' => $this->publicShipment($shipment),
                        'proof_id' => $proofId,
                    ],
                ],
            ];
        }

        try {
            $this->advanceToDeliverable($companyId, $shipmentId, $current);
            $this->shipmentService->deliver($shipmentId, $companyId, $this->proofPayload($payload), 'driver_api_deliver');
            $updated = $this->shipments->find($shipmentId, $companyId) ?? $shipment;

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => ['shipment' => $this->publicShipment($updated)],
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'code' => 'deliver_failed',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function uploadPod(array $context, int $shipmentId, array $payload): array
    {
        try {
            $shipment = $this->requireOwnedShipment($context, $shipmentId);
        } catch (\Throwable $e) {
            return [
                'status' => 404,
                'body' => ['success' => false, 'code' => 'shipment_not_found', 'message' => 'Shipment not found for driver'],
            ];
        }

        if ((string) ($shipment['status'] ?? '') !== 'delivered') {
            return $this->deliver($context, $shipmentId, $payload);
        }

        $proofId = $this->upsertProof((int) $context['company_id'], $shipmentId, $payload);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'shipment' => $this->publicShipment($shipment),
                    'proof_id' => $proofId,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function requireOwnedShipment(array $context, int $shipmentId): array
    {
        $companyId = (int) $context['company_id'];
        $driverId = (int) $context['driver_id'];
        $shipment = $this->shipments->find($shipmentId, $companyId);
        if ($shipment === null) {
            throw new \RuntimeException('shipment_not_found');
        }
        $tripId = (int) ($shipment['trip_id'] ?? 0);
        if ($tripId < 1) {
            throw new \RuntimeException('shipment_not_found');
        }
        $trip = $this->trips->find($tripId, $companyId);
        if ($trip === null || (int) ($trip['driver_id'] ?? 0) !== $driverId) {
            throw new \RuntimeException('shipment_not_found');
        }

        return $shipment;
    }

    private function advanceToDeliverable(int $companyId, int $shipmentId, string $current): void
    {
        $chain = [
            'created' => 'picked',
            'picked' => 'packed',
            'packed' => 'shipped',
            'shipped' => 'out_for_delivery',
        ];
        $guard = 0;
        while ($current !== 'out_for_delivery' && $current !== 'delivered' && $guard < 8) {
            ++$guard;
            $next = $chain[$current] ?? null;
            if ($next === null) {
                if ($current === 'failed') {
                    throw new \RuntimeException('shipment_failed');
                }
                break;
            }
            LogisticsStatusPolicy::assertTransition(LogisticsStatusPolicy::ENTITY_SHIPMENT, $current, $next);
            $extra = $next === 'shipped' ? ['dispatched_at' => date('Y-m-d H:i:s')] : [];
            $this->status->transition(
                $companyId,
                LogisticsStatusPolicy::ENTITY_SHIPMENT,
                $shipmentId,
                $next,
                'driver_api_advance',
                $extra
            );
            $current = $next;
        }
    }

    /** @param array<string, mixed> $payload */
    private function upsertProof(int $companyId, int $shipmentId, array $payload): int
    {
        $row = $this->proofPayload($payload);
        $row['shipment_id'] = $shipmentId;
        $row['delivered_at'] = date('Y-m-d H:i:s');
        $existing = $this->proofs->findByShipment($shipmentId, $companyId);
        if ($existing !== null) {
            $this->proofs->update((int) $existing['id'], $companyId, $row);

            return (int) $existing['id'];
        }

        return $this->proofs->create($companyId, $row);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function proofPayload(array $payload): array
    {
        return [
            'receiver_name' => trim((string) ($payload['receiver_name'] ?? '')) ?: null,
            'signature_file' => trim((string) ($payload['signature_file'] ?? '')) ?: null,
            'photo_file' => trim((string) ($payload['photo_file'] ?? '')) ?: null,
            'gps_lat' => isset($payload['gps_lat']) && $payload['gps_lat'] !== '' ? (float) $payload['gps_lat'] : null,
            'gps_long' => isset($payload['gps_long']) && $payload['gps_long'] !== ''
                ? (float) $payload['gps_long']
                : (isset($payload['gps_lng']) && $payload['gps_lng'] !== '' ? (float) $payload['gps_lng'] : null),
            'notes' => trim((string) ($payload['notes'] ?? ($payload['reason'] ?? ''))) ?: null,
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function publicShipment(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'tracking_number' => (string) ($row['tracking_number'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'trip_id' => isset($row['trip_id']) ? (int) $row['trip_id'] : null,
            'pickup_location' => $row['pickup_location'] ?? null,
            'delivery_location' => $row['delivery_location'] ?? null,
            'delivered_at' => $row['delivered_at'] ?? null,
        ];
    }
}
