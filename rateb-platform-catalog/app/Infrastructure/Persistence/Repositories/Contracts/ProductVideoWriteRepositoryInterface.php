<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductVideoWriteRepositoryInterface extends WriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $metadata
     * @param list<array<string, mixed>> $translations
     */
    public function createForProduct(
        string $productUuid,
        array $metadata,
        array $translations,
        ?int $actorId = null
    ): string;
}
