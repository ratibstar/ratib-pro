<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Logistics\Policies\LogisticsStatusPolicy;
use Rateb\App\Logistics\Repositories\LogisticsDeliveryProofRepository;
use Rateb\App\Logistics\Repositories\LogisticsShipmentRepository;
use Rateb\App\Logistics\Repositories\LogisticsStatusHistoryRepository;

/**
 * Customer portal shipment tracking — tenant-scoped, no Core edits.
 */
final class CustomerTrackingService
{
    public function __construct(
        private LogisticsShipmentRepository $shipments = new LogisticsShipmentRepository(),
        private LogisticsStatusHistoryRepository $history = new LogisticsStatusHistoryRepository(),
        private LogisticsDeliveryProofRepository $proofs = new LogisticsDeliveryProofRepository(),
    ) {
    }

    /**
     * @return array{
     *   found:bool,
     *   shipment?:array<string,mixed>,
     *   timeline?:list<array<string,mixed>>,
     *   proof?:array<string,mixed>|null,
     *   code?:string,
     *   message?:string
     * }
     */
    public function trackByNumber(int $companyId, string $trackingNumber, ?int $customerId = null): array
    {
        if ($companyId < 1) {
            return ['found' => false, 'code' => 'tenant_required', 'message' => 'Company context required'];
        }
        $tracking = strtoupper(trim($trackingNumber));
        if ($tracking === '') {
            return ['found' => false, 'code' => 'tracking_required', 'message' => 'Tracking number is required'];
        }

        TenantContext::setCompanyId($companyId);
        $shipment = $this->findByTracking($companyId, $tracking);
        if ($shipment === null) {
            return ['found' => false, 'code' => 'not_found', 'message' => 'Shipment not found'];
        }

        // Optional customer binding: portal users linked to ERP customer only see their shipments.
        if ($customerId !== null && $customerId > 0) {
            $shipCustomer = (int) ($shipment['customer_id'] ?? 0);
            if ($shipCustomer > 0 && $shipCustomer !== $customerId) {
                return ['found' => false, 'code' => 'not_found', 'message' => 'Shipment not found'];
            }
        }

        $entityId = (int) ($shipment['id'] ?? 0);
        $timeline = [];
        foreach ($this->history->listForEntity($companyId, LogisticsStatusPolicy::ENTITY_SHIPMENT, $entityId, 100) as $row) {
            $timeline[] = [
                'from_status' => (string) ($row['from_status'] ?? ''),
                'to_status' => (string) ($row['to_status'] ?? ''),
                'title' => $this->statusLabel((string) ($row['to_status'] ?? '')),
                'body' => trim((string) ($row['reason'] ?? '')) ?: null,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }
        usort($timeline, static function (array $a, array $b): int {
            return strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
        });

        $proof = $this->proofs->findByShipment($entityId, $companyId);

        return [
            'found' => true,
            'shipment' => $this->publicShipment($shipment),
            'timeline' => $timeline,
            'proof' => $proof !== null ? $this->publicProof($proof) : null,
        ];
    }

    /** @return array<string, mixed>|null */
    public function findByTracking(int $companyId, string $trackingNumber): ?array
    {
        $needle = strtoupper(trim($trackingNumber));
        foreach ($this->shipments->listForCompany($companyId, 500, 0) as $row) {
            if (strtoupper(trim((string) ($row['tracking_number'] ?? ''))) === $needle) {
                return $row;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function publicShipment(array $row): array
    {
        $status = (string) ($row['status'] ?? '');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'tracking_number' => (string) ($row['tracking_number'] ?? ''),
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'pickup_location' => $row['pickup_location'] ?? null,
            'delivery_location' => $row['delivery_location'] ?? null,
            'dispatched_at' => $row['dispatched_at'] ?? null,
            'delivered_at' => $row['delivered_at'] ?? null,
            'notes' => $row['notes'] ?? null,
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function publicProof(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'receiver_name' => $row['receiver_name'] ?? null,
            'signature_file' => $row['signature_file'] ?? null,
            'photo_file' => $row['photo_file'] ?? null,
            'gps_lat' => $row['gps_lat'] ?? null,
            'gps_long' => $row['gps_long'] ?? null,
            'delivered_at' => $row['delivered_at'] ?? null,
            'notes' => $row['notes'] ?? null,
        ];
    }

    private function statusLabel(string $status): string
    {
        if ($status === '') {
            return '';
        }
        $key = 'logistics_status_' . $status;
        $label = __($key);

        return $label === $key ? $status : $label;
    }
}
