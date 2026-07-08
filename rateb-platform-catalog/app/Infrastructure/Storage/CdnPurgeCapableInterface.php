<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Storage;

interface CdnPurgeCapableInterface
{
    public function purgeCdn(string $relativePath): void;
}
