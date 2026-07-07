<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductVersionWriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $snapshot
     */
    public function create(
        int $productId,
        int $versionNumber,
        string $changeType,
        array $snapshot,
        int $entityVersion,
        ?string $changeSummary,
        ?int $actorId
    ): string;
}
