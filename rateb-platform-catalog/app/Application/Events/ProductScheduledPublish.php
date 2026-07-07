<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Events;

final class ProductScheduledPublish
{
    public function __construct(
        public readonly string $productUuid,
        public readonly int $versionNumber
    ) {
    }
}
