<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

final class ProductVersionConflictException extends \RuntimeException
{
    public function __construct(
        public readonly int $currentLockVersion
    ) {
        parent::__construct('version_conflict', 409);
    }
}
