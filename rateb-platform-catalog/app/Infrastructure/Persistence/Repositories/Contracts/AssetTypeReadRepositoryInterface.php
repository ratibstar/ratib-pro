<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;

interface AssetTypeReadRepositoryInterface extends ReadRepositoryInterface
{
    public function findByCode(string $code, LocaleContext $locale): ?array;
}
