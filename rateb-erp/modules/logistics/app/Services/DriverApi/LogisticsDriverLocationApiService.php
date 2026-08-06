<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services\DriverApi;

use Rateb\App\Logistics\Repositories\LogisticsDriverLocationRepository;
use Rateb\App\Logistics\Repositories\LogisticsShipmentRepository;
use Rateb\App\Logistics\Repositories\LogisticsTripRepository;

final class LogisticsDriverLocationApiService
{
    public function __construct(
        private LogisticsDriverLocationRepository $locations = new LogisticsDriverLocationRepository(),
        private LogisticsTripRepository $trips = new LogisticsTripRepository(),
        private LogisticsShipmentRepository $shipments = new LogisticsShipmentRepository(),
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function updateLocation(array $context, array $payload): array
    {
        $companyId = (int) $context['company_id'];
        $driverId = (int) $context['driver_id'];
        $lat = isset($payload['gps_lat']) ? (float) $payload['gps_lat'] : null;
        $lng = isset($payload['gps_long']) ? (float) $payload['gps_long'] : (isset($payload['gps_lng']) ? (float) $payload['gps_lng'] : null);
        if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'code' => 'invalid_coordinates',
                    'message' => 'gps_lat and gps_long are required',
                ],
            ];
        }

        $tripId = (int) ($payload['trip_id'] ?? 0);
        $shipmentId = (int) ($payload['shipment_id'] ?? 0);
        if ($tripId > 0) {
            $trip = $this->trips->find($tripId, $companyId);
            if ($trip === null || (int) ($trip['driver_id'] ?? 0) !== $driverId) {
                return [
                    'status' => 404,
                    'body' => ['success' => false, 'code' => 'trip_not_found', 'message' => 'Trip not found for driver'],
                ];
            }
        }
        if ($shipmentId > 0) {
            $shipment = $this->shipments->find($shipmentId, $companyId);
            if ($shipment === null) {
                return [
                    'status' => 404,
                    'body' => ['success' => false, 'code' => 'shipment_not_found', 'message' => 'Shipment not found'],
                ];
            }
            $shipTripId = (int) ($shipment['trip_id'] ?? 0);
            if ($shipTripId > 0) {
                $trip = $this->trips->find($shipTripId, $companyId);
                if ($trip === null || (int) ($trip['driver_id'] ?? 0) !== $driverId) {
                    return [
                        'status' => 403,
                        'body' => ['success' => false, 'code' => 'shipment_not_owned', 'message' => 'Shipment is not assigned to this driver'],
                    ];
                }
            }
        }

        $clientTs = trim((string) ($payload['client_timestamp'] ?? ''));
        $recordedAt = $clientTs !== '' && strtotime($clientTs) !== false
            ? date('Y-m-d H:i:s', strtotime($clientTs))
            : date('Y-m-d H:i:s');

        $id = $this->locations->create($companyId, [
            'driver_id' => $driverId,
            'trip_id' => $tripId > 0 ? $tripId : null,
            'shipment_id' => $shipmentId > 0 ? $shipmentId : null,
            'gps_lat' => $lat,
            'gps_long' => $lng,
            'recorded_at' => $recordedAt,
            'client_timestamp' => $clientTs !== '' ? $clientTs : null,
        ]);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'location_id' => $id,
                    'gps_lat' => $lat,
                    'gps_long' => $lng,
                    'recorded_at' => $recordedAt,
                    'client_timestamp' => $clientTs !== '' ? $clientTs : null,
                ],
            ],
        ];
    }
}
