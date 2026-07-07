<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Events\DomainEvent;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;

catalog_test('EventDispatcher invokes registered listener', static function (): void {
    $dispatcher = new EventDispatcher();
    $received = null;

    $dispatcher->listen('CatalogBootstrapped', static function (DomainEvent $event) use (&$received): void {
        $received = $event->payload();
    });

    $dispatcher->dispatch(new class implements DomainEvent {
        public function eventName(): string
        {
            return 'CatalogBootstrapped';
        }

        public function payload(): array
        {
            return ['phase' => '2.1'];
        }
    });

    catalog_assert_same(['phase' => '2.1'], $received);
});
