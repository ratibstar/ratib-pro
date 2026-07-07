<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductImageWriteRepositoryInterface extends WriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $metadata
     * @param list<array<string, mixed>> $translations
     */
    public function createForProduct(
        string $productUuid,
        string $imageUuid,
        string $storageKey,
        array $metadata,
        array $translations,
        ?int $actorId = null
    ): string;

    public function removeForProduct(string $productUuid, string $imageUuid, ?int $actorId = null): bool;
}
