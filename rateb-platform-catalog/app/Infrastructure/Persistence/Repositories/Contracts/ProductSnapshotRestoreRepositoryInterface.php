<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductSnapshotRestoreRepositoryInterface
{
    /**
     * @param array<string, mixed> $snapshot
     * @return array{version_number: int, lock_version: int, product_id: int, version_uuid: string}
     */
    public function restore(
        string $productUuid,
        array $snapshot,
        int $expectedLockVersion,
        ?int $actorId,
        string $changeSummary
    ): array;
}
