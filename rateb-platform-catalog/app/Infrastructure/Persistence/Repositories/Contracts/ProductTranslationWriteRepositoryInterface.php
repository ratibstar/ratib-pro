<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductTranslationWriteRepositoryInterface
{
    /**
     * @param list<array<string, mixed>> $translations
     */
    public function upsertForProduct(int $productId, array $translations, ?int $actorId = null): void;
}
