<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AssetTypeReadRepositoryInterface;

final class MysqlAssetTypeReadRepository extends BaseRepository implements AssetTypeReadRepositoryInterface
{
    protected function table(): string
    {
        return 'asset_types';
    }

    public function findByUuid(string $uuid, LocaleContext $locale): ?array
    {
        $nameSelect = $this->translationSelect('att', 'name');
        $join = $this->translationJoin('at', 'id', 'asset_type_translations', 'att', 'asset_type_id');

        return $this->fetchOne(
            "SELECT at.uuid, at.code, at.category, at.is_system, at.status, {$nameSelect}
             FROM asset_types at
             {$join}
             WHERE at.uuid = :uuid AND at.deleted_at IS NULL LIMIT 1",
            array_merge(['uuid' => $uuid], $this->localeParams($locale))
        );
    }

    public function findByCode(string $code, LocaleContext $locale): ?array
    {
        $nameSelect = $this->translationSelect('att', 'name');
        $join = $this->translationJoin('at', 'id', 'asset_type_translations', 'att', 'asset_type_id');

        return $this->fetchOne(
            "SELECT at.id, at.uuid, at.code, at.category, at.is_system, at.status, {$nameSelect}
             FROM asset_types at
             {$join}
             WHERE at.code = :code AND at.deleted_at IS NULL LIMIT 1",
            array_merge(['code' => $code], $this->localeParams($locale))
        );
    }

    public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        $nameSelect = $this->translationSelect('att', 'name');
        $join = $this->translationJoin('at', 'id', 'asset_type_translations', 'att', 'asset_type_id');

        return $this->fetchAll(
            "SELECT at.uuid, at.code, at.category, at.is_system, at.status, {$nameSelect},
                    COALESCE(att_loc.language_code, att_fb.language_code) AS resolved_language_code
             FROM asset_types at
             {$join}
             WHERE at.deleted_at IS NULL AND at.status = 'active'
             ORDER BY at.code ASC
             LIMIT {$limit} OFFSET {$offset}",
            $this->localeParams($locale)
        );
    }
}
