<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;

interface ReadRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByUuid(string $uuid, LocaleContext $locale): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array;
}
