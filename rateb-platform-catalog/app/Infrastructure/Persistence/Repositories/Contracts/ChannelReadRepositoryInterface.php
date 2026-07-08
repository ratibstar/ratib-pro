<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;

interface ChannelReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(LocaleContext $locale, int $limit, int $offset): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listForProduct(string $productUuid, LocaleContext $locale): array;

    public function findIdByCode(string $code): ?int;
}
