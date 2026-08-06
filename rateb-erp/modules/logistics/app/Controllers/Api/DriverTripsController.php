<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Controllers\Api;

use Rateb\App\Logistics\Services\DriverApi\LogisticsDriverTripApiService;

final class DriverTripsController extends LogisticsDriverApiController
{
    private LogisticsDriverTripApiService $trips;

    public function __construct(
        ?LogisticsDriverTripApiService $trips = null,
        ?\Rateb\App\Logistics\Services\DriverApi\LogisticsDriverContextService $contextService = null,
        ?\Rateb\App\Logistics\Services\DriverApi\LogisticsIdempotencyService $idempotency = null
    ) {
        parent::__construct($contextService, $idempotency);
        $this->trips = $trips ?? new LogisticsDriverTripApiService();
    }

    public function index(): void
    {
        $context = $this->requireDriverContext();
        if ($context === null) {
            return;
        }
        $result = $this->trips->listTrips($context);
        $this->respond($result['body'], (int) $result['status']);
    }

    public function start(array $params): void
    {
        $context = $this->requireDriverContext();
        if ($context === null) {
            return;
        }
        $payload = $this->jsonBody();
        $tripId = (int) ($params['id'] ?? 0);
        $this->respondIdempotent($context, 'trips.start', $payload, function () use ($context, $tripId, $payload) {
            try {
                return $this->trips->startTrip($context, $tripId, isset($payload['reason']) ? (string) $payload['reason'] : null);
            } catch (\Throwable $e) {
                return [
                    'status' => 404,
                    'body' => ['success' => false, 'code' => 'trip_not_found', 'message' => 'Trip not found for driver'],
                ];
            }
        });
    }

    public function complete(array $params): void
    {
        $context = $this->requireDriverContext();
        if ($context === null) {
            return;
        }
        $payload = $this->jsonBody();
        $tripId = (int) ($params['id'] ?? 0);
        $this->respondIdempotent($context, 'trips.complete', $payload, function () use ($context, $tripId, $payload) {
            try {
                return $this->trips->completeTrip($context, $tripId, isset($payload['reason']) ? (string) $payload['reason'] : null);
            } catch (\Throwable $e) {
                return [
                    'status' => 404,
                    'body' => ['success' => false, 'code' => 'trip_not_found', 'message' => 'Trip not found for driver'],
                ];
            }
        });
    }
}
