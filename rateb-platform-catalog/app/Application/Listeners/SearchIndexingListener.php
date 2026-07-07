<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Listeners;

use Rateb\PlatformCatalog\Application\Events\BarcodeChanged;
use Rateb\PlatformCatalog\Application\Events\DomainEvent;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\ProductCreated;
use Rateb\PlatformCatalog\Application\Events\ProductDeleted;
use Rateb\PlatformCatalog\Application\Events\ProductPublished;
use Rateb\PlatformCatalog\Application\Events\ProductUpdated;
use Rateb\PlatformCatalog\Application\Events\VariantBarcodeChanged;
use Rateb\PlatformCatalog\Application\Events\VariantCreated;
use Rateb\PlatformCatalog\Application\Events\VariantDeleted;
use Rateb\PlatformCatalog\Application\Events\VariantUpdated;
use Rateb\PlatformCatalog\Application\Services\QueueService;
use Rateb\PlatformCatalog\Application\Services\SearchIndexerService;

final class SearchIndexingListener
{
    public function __construct(
        private readonly SearchIndexerService $indexerService,
        private readonly QueueService $queueService
    ) {
    }

    public static function register(EventDispatcher $dispatcher, self $listener): void
    {
        $events = [
            'ProductCreated' => 'onProductChanged',
            'ProductUpdated' => 'onProductChanged',
            'ProductDeleted' => 'onProductDeleted',
            'VariantCreated' => 'onVariantChanged',
            'VariantUpdated' => 'onVariantChanged',
            'VariantDeleted' => 'onVariantDeleted',
            'BarcodeChanged' => 'onBarcodeChanged',
            'VariantBarcodeChanged' => 'onVariantBarcodeChanged',
            'ProductImageUploaded' => 'onProductImageUploaded',
            'ProductPublished' => 'onProductPublished',
        ];

        foreach ($events as $eventName => $method) {
            $dispatcher->listen($eventName, static function (DomainEvent $event) use ($listener, $method): void {
                $listener->{$method}($event);
            });
        }
    }

    public function onProductChanged(DomainEvent $event): void
    {
        $payload = $event->payload();
        $productUuid = (string) $payload['product_uuid'];
        $this->indexerService->enqueueProductEverywhere($productUuid);
        $this->queueService->enqueueSystem('search', 'search_reindex', [
            'product_uuid' => $productUuid,
            'locale' => (string) ($payload['locale'] ?? 'en'),
        ], 'search_reindex:' . $productUuid);
    }

    public function onProductDeleted(DomainEvent $event): void
    {
        $productUuid = (string) $event->payload()['product_uuid'];
        $this->indexerService->deleteProductEverywhere($productUuid);
    }

    public function onVariantChanged(DomainEvent $event): void
    {
        $payload = $event->payload();
        $variantUuid = (string) $payload['variant_uuid'];
        $productUuid = (string) $payload['product_uuid'];
        $this->indexerService->enqueueVariantEverywhere($variantUuid);
        $this->indexerService->enqueueProductEverywhere($productUuid);
        $this->queueService->enqueueSystem('search', 'variant_reindex', [
            'variant_uuid' => $variantUuid,
            'locale' => (string) ($payload['locale'] ?? 'en'),
        ], 'variant_reindex:' . $variantUuid);
    }

    public function onVariantDeleted(DomainEvent $event): void
    {
        $payload = $event->payload();
        $this->indexerService->deleteVariantEverywhere((string) $payload['variant_uuid']);
        $this->indexerService->enqueueProductEverywhere((string) $payload['product_uuid']);
    }

    public function onBarcodeChanged(DomainEvent $event): void
    {
        $productUuid = (string) $event->payload()['product_uuid'];
        $this->indexerService->enqueueProductEverywhere($productUuid);
    }

    public function onVariantBarcodeChanged(DomainEvent $event): void
    {
        $variantUuid = (string) $event->payload()['variant_uuid'];
        $this->indexerService->enqueueVariantEverywhere($variantUuid, 'upsert');
    }

    public function onProductImageUploaded(DomainEvent $event): void
    {
        unset($event);
    }

    public function onProductPublished(DomainEvent $event): void
    {
        $this->onProductChanged($event);
    }
}
