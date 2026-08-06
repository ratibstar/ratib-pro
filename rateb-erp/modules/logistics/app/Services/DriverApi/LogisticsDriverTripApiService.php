<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services\DriverApi;

use Rateb\App\Logistics\Repositories\LogisticsTripRepository;
use Rateb\App\Logistics\Services\TripService;

final class LogisticsDriverTripApiService
{
    public function __construct(
        private LogisticsTripRepository $trips = new LogisticsTripRepository(),
        private TripService $tripService = new TripService(),
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array{status:int,body:array<string,mixed>}
     */
    public function listTrips(array $context): array
    {
        $companyId = (int) $context['company_id'];
        $driverId = (int) $context['driver_id'];
        $rows = [];
        foreach ($this->trips->listForCompany($companyId, 200, 0) as $row) {
            if ((int) ($row['driver_id'] ?? 0) !== $driverId) {
                continue;
            }
            if (in_array((string) ($row['status'] ?? ''), ['cancelled'], true)) {
                continue;
            }
            $rows[] = $this->publicTrip($row);
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'trips' => $rows,
                    'count' => count($rows),
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array{status:int,body:array<string,mixed>}
     */
    public function startTrip(array $context, int $tripId, ?string $reason = null): array
    {
        $trip = $this->requireOwnedTrip($context, $tripId);
        $companyId = (int) $context['company_id'];
        $status = (string) ($trip['status'] ?? '');
        try {
            if ($status === 'draft') {
                $this->tripService->assign($tripId, $companyId, $reason ?? 'driver_api_assign');
                $status = 'assigned';
            }
            if ($status === 'started') {
                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'data' => ['trip' => $this->publicTrip($this->trips->find($tripId, $companyId) ?? $trip)],
                        'code' => 'already_started',
                    ],
                ];
            }
            $this->tripService->start($tripId, $companyId, $reason ?? 'driver_api_start');
            $updated = $this->trips->find($tripId, $companyId) ?? $trip;

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => ['trip' => $this->publicTrip($updated)],
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'code' => 'trip_start_failed',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array{status:int,body:array<string,mixed>}
     */
    public function completeTrip(array $context, int $tripId, ?string $reason = null): array
    {
        $trip = $this->requireOwnedTrip($context, $tripId);
        $companyId = (int) $context['company_id'];
        $status = (string) ($trip['status'] ?? '');
        try {
            if ($status === 'completed') {
                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'data' => ['trip' => $this->publicTrip($trip)],
                        'code' => 'already_completed',
                    ],
                ];
            }
            $this->tripService->complete($tripId, $companyId, $reason ?? 'driver_api_complete');
            $updated = $this->trips->find($tripId, $companyId) ?? $trip;

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => ['trip' => $this->publicTrip($updated)],
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'code' => 'trip_complete_failed',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function requireOwnedTrip(array $context, int $tripId): array
    {
        $companyId = (int) $context['company_id'];
        $driverId = (int) $context['driver_id'];
        $trip = $this->trips->find($tripId, $companyId);
        if ($trip === null || (int) ($trip['driver_id'] ?? 0) !== $driverId) {
            throw new \RuntimeException('trip_not_found');
        }

        return $trip;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function publicTrip(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'status' => (string) ($row['status'] ?? ''),
            'origin' => $row['origin'] ?? null,
            'destination' => $row['destination'] ?? null,
            'planned_date' => $row['planned_date'] ?? null,
            'start_time' => $row['start_time'] ?? null,
            'end_time' => $row['end_time'] ?? null,
            'vehicle_id' => isset($row['vehicle_id']) ? (int) $row['vehicle_id'] : null,
            'route_id' => isset($row['route_id']) ? (int) $row['route_id'] : null,
        ];
    }
}
