<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\Support\CorrelationIdContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IntegrationOutboxReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IntegrationOutboxWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WebhookSubscriptionReadRepositoryInterface;
use Rateb\PlatformCatalog\Support\Uuid;

final class IntegrationOutboxService
{
    public function __construct(
        private readonly IntegrationOutboxWriteRepositoryInterface $writeRepository,
        private readonly IntegrationOutboxReadRepositoryInterface $readRepository,
        private readonly WebhookSubscriptionReadRepositoryInterface $subscriptionReadRepository,
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly QueueService $queueService,
        private readonly LocaleResolverService $localeResolver
    ) {
    }

    public function recordProductPublished(string $productUuid, int $versionNumber, ?int $erpCompanyId = null): void
    {
        $this->insertEvent('product.published', 'product', $productUuid, [
            'product_uuid' => $productUuid,
            'version_number' => $versionNumber,
            'event' => 'product.published',
        ], $erpCompanyId);
    }

    public function recordProductUpdated(string $productUuid, ?int $erpCompanyId = null): void
    {
        $this->insertEvent('product.updated', 'product', $productUuid, [
            'product_uuid' => $productUuid,
            'event' => 'product.updated',
        ], $erpCompanyId);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertEvent(
        string $eventType,
        string $entityType,
        string $entityUuid,
        array $payload,
        ?int $erpCompanyId
    ): void {
        $locale = $this->localeResolver->resolveFromRequest();
        $product = $this->productReadRepository->findByUuid($entityUuid, $locale);
        if ($product !== null) {
            $payload['sku'] = $product['sku'] ?? null;
            $payload['status'] = $product['status'] ?? null;
        }

        $correlationId = CorrelationIdContext::get();
        if ($correlationId !== null) {
            $payload['correlation_id'] = $correlationId;
        }

        $this->writeRepository->insert(Uuid::v4(), $eventType, $entityType, $entityUuid, $payload, $erpCompanyId);
    }

    public function dispatchPending(int $limit = 50): int
    {
        $events = $this->readRepository->fetchPending($limit);
        $enqueued = 0;

        foreach ($events as $event) {
            $eventType = (string) ($event['event_type'] ?? '');
            $eventId = (string) ($event['event_id'] ?? '');
            $erpCompanyId = isset($event['erp_company_id']) ? (int) $event['erp_company_id'] : null;
            $subscriptions = $this->subscriptionReadRepository->findActiveForEvent($eventType, $erpCompanyId);

            $this->writeRepository->markDispatched($eventId);

            foreach ($subscriptions as $subscription) {
                $this->queueService->enqueueSystem('integration', 'webhook_dispatch', [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'subscription_id' => (int) $subscription['id'],
                    'subscription_uuid' => (string) $subscription['uuid'],
                    'url' => (string) $subscription['url'],
                    'secret_encrypted' => (string) $subscription['secret_encrypted'],
                    'payload' => $event['payload'] ?? [],
                ], 'webhook_dispatch:' . $eventId . ':' . $subscription['uuid']);
                $enqueued++;
            }
        }

        return $enqueued;
    }
}
