<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AuditEventWriteRepositoryInterface;

final class AuditEventService
{
    public function __construct(
        private readonly AuditEventWriteRepositoryInterface $writeRepository
    ) {
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function record(
        string $entityType,
        string $entityUuid,
        string $action,
        ?int $entityVersion = null,
        ?int $actorId = null,
        ?array $before = null,
        ?array $after = null
    ): string {
        return $this->writeRepository->append(
            $entityType,
            $entityUuid,
            $entityVersion,
            $action,
            $actorId,
            $actorId !== null ? 'platform_user' : 'system',
            $before,
            $after
        );
    }
}
