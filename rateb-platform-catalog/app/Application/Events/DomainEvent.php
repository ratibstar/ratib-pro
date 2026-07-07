<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

interface DomainEvent
{
    public function eventName(): string;

    /** @return array<string, mixed> */
    public function payload(): array;
}
