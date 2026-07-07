<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class EventDispatcher
{
    /** @var array<string, list<callable(DomainEvent): void>> */
    private array $listeners = [];

    public function listen(string $eventName, callable $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    public function dispatch(DomainEvent $event): void
    {
        $name = $event->eventName();
        foreach ($this->listeners[$name] ?? [] as $listener) {
            $listener($event);
        }
    }
}
