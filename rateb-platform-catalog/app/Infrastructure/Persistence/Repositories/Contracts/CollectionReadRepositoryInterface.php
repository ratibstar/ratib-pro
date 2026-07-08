<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;

interface CollectionReadRepositoryInterface
{
    public function findByUuid(string $uuid, LocaleContext $locale): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function list(LocaleContext $locale, int $limit, int $offset): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listProducts(string $collectionUuid, LocaleContext $locale, int $limit, int $offset): array;
}
