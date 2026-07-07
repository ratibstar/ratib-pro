<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductFileWriteRepositoryInterface extends WriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $metadata
     * @param list<array<string, mixed>> $translations
     */
    public function createForProduct(
        string $productUuid,
        string $fileUuid,
        string $storageKey,
        array $metadata,
        array $translations,
        ?int $actorId = null
    ): string;

    public function removeForProduct(string $productUuid, string $fileUuid, ?int $actorId = null): bool;
}
