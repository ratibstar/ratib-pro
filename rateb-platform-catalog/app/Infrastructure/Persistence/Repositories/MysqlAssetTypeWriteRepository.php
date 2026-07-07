<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AssetTypeWriteRepositoryInterface;

final class MysqlAssetTypeWriteRepository extends BaseRepository implements AssetTypeWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'asset_types';
    }

    public function create(array $data): string
    {
        throw new \LogicException('Asset type writes are seeded in migration only');
    }

    public function update(string $uuid, array $data): bool
    {
        throw new \LogicException('Asset type writes are not exposed in Phase 2.6');
    }

    public function softDelete(string $uuid, ?int $actorId = null): bool
    {
        throw new \LogicException('Asset type writes are not exposed in Phase 2.6');
    }
}
