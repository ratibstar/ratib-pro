<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AttributeWriteRepositoryInterface;

final class MysqlAttributeWriteRepository extends BaseRepository implements AttributeWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'attributes';
    }

    public function create(array $data): string
    {
        throw new \LogicException('Attribute write operations are not exposed in Phase 2.3 read APIs.');
    }

    public function update(string $uuid, array $data): bool
    {
        throw new \LogicException('Attribute write operations are not exposed in Phase 2.3 read APIs.');
    }

    public function softDelete(string $uuid, ?int $actorId = null): bool
    {
        throw new \LogicException('Attribute write operations are not exposed in Phase 2.3 read APIs.');
    }
}
