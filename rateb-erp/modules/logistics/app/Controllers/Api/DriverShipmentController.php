<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Controllers\Api;

use Rateb\App\Logistics\Services\DriverApi\LogisticsDriverShipmentApiService;

final class DriverShipmentController extends LogisticsDriverApiController
{
    private LogisticsDriverShipmentApiService $shipments;

    public function __construct(
        ?LogisticsDriverShipmentApiService $shipments = null,
        ?\Rateb\App\Logistics\Services\DriverApi\LogisticsDriverContextService $contextService = null,
        ?\Rateb\App\Logistics\Services\DriverApi\LogisticsIdempotencyService $idempotency = null
    ) {
        parent::__construct($contextService, $idempotency);
        $this->shipments = $shipments ?? new LogisticsDriverShipmentApiService();
    }

    public function deliver(array $params): void
    {
        $context = $this->requireDriverContext();
        if ($context === null) {
            return;
        }
        $payload = $this->jsonBody();
        $shipmentId = (int) ($params['id'] ?? 0);
        $this->respondIdempotent($context, 'shipments.deliver', $payload, function () use ($context, $shipmentId, $payload) {
            return $this->shipments->deliver($context, $shipmentId, $payload);
        });
    }
}
