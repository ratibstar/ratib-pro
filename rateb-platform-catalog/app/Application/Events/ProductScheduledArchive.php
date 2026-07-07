<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class ProductScheduledArchive
{
    public function __construct(
        public readonly string $productUuid
    ) {
    }
}
