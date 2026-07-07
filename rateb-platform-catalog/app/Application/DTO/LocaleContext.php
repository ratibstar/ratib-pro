<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class LocaleContext
{
    public function __construct(
        public readonly string $locale,
        public readonly string $fallback
    ) {
    }
}
