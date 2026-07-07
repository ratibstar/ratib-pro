<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductSnapshotGraphWriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $snapshot
     */
    public function restoreForProduct(int $productId, string $productUuid, array $snapshot, ?int $actorId): void;
}
