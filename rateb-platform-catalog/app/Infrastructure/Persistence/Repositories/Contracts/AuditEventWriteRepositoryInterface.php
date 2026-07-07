<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface AuditEventWriteRepositoryInterface
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function append(
        string $entityType,
        string $entityUuid,
        ?int $entityVersion,
        string $action,
        ?int $actorId,
        string $actorType = 'platform_user',
        ?array $before = null,
        ?array $after = null,
        ?string $ipAddress = null
    ): string;
}
