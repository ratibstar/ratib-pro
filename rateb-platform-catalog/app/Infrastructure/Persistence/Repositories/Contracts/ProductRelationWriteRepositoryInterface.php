<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductRelationWriteRepositoryInterface extends WriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function addRelation(string $productUuid, array $data, ?int $actorId = null): string;

    /**
     * @param list<array<string, mixed>> $relations
     */
    public function replaceForProduct(string $productUuid, array $relations, ?int $actorId = null): void;
}
