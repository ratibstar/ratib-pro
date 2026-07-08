<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface WebhookDeliveryWriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $requestBody
     */
    public function create(int $subscriptionId, string $eventId, array $requestBody): string;

    public function markDelivered(string $deliveryUuid, int $responseStatus, ?string $responseBody): void;

    public function markFailed(string $deliveryUuid, int $responseStatus, ?string $responseBody, int $attempts): void;
}
