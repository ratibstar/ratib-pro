<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Listeners;

use Rateb\PlatformCatalog\Application\Events\DomainEvent;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\ProductPublished;
use Rateb\PlatformCatalog\Application\Events\ProductUpdated;
use Rateb\PlatformCatalog\Application\Services\IntegrationOutboxService;

final class IntegrationOutboxListener
{
    public function __construct(
        private readonly IntegrationOutboxService $outboxService
    ) {
    }

    public static function register(EventDispatcher $dispatcher, self $listener): void
    {
        $dispatcher->listen('ProductPublished', static function (DomainEvent $event) use ($listener): void {
            $listener->onProductPublished($event);
        });
        $dispatcher->listen('ProductUpdated', static function (DomainEvent $event) use ($listener): void {
            $listener->onProductUpdated($event);
        });
    }

    public function onProductPublished(DomainEvent $event): void
    {
        if (!$event instanceof ProductPublished) {
            return;
        }

        $payload = $event->payload();
        $this->outboxService->recordProductPublished(
            (string) $payload['product_uuid'],
            (int) ($payload['version_number'] ?? 0)
        );
    }

    public function onProductUpdated(DomainEvent $event): void
    {
        if (!$event instanceof ProductUpdated) {
            return;
        }

        $this->outboxService->recordProductUpdated((string) $event->payload()['product_uuid']);
    }
}
