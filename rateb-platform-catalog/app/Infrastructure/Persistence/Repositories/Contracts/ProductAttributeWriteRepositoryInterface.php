<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductAttributeWriteRepositoryInterface extends WriteRepositoryInterface
{
    /**
     * @param list<array<string, mixed>> $attributes
     */
    public function replaceForProduct(string $productUuid, array $attributes, ?int $actorId = null): void;
}
