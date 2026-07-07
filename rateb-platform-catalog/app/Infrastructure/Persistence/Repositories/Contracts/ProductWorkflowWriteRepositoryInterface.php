<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductWorkflowWriteRepositoryInterface
{
    /**
     * @param array<string, mixed>|null $versionSnapshot
     * @return array<string, mixed>
     */
    public function transitionStatus(
        string $productUuid,
        string $fromStatus,
        string $toStatus,
        string $action,
        int $lockVersion,
        ?int $actorId,
        ?string $comment,
        ?array $versionSnapshot = null,
        ?string $versionChangeType = null,
        ?string $versionChangeSummary = null
    ): array;
}
