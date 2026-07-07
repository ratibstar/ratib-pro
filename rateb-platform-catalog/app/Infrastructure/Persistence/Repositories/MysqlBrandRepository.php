<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\BrandRepositoryInterface;

final class MysqlBrandRepository extends BaseRepository implements BrandRepositoryInterface
{
    protected function table(): string
    {
        return 'brands';
    }

    public function findByUuid(string $uuid, LocaleContext $locale): ?array
    {
        $sql = 'SELECT b.uuid, b.slug, b.logo_path, b.website, b.country_code, b.status,
                       b.created_at, b.updated_at,
                       ' . $this->translationSelect('bt', 'name') . ',
                       ' . $this->translationSelect('bt', 'description') . ',
                       COALESCE(bt_loc.language_code, bt_fb.language_code) AS resolved_language_code
                FROM brands b
                ' . $this->translationJoin('b', 'id', 'brand_translations', 'bt', 'brand_id') . '
                WHERE b.uuid = :uuid AND ' . $this->notDeletedClause('b') . '
                LIMIT 1';

        return $this->fetchOne($sql, array_merge(['uuid' => $uuid], $this->localeParams($locale)));
    }

    public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $sql = 'SELECT b.uuid, b.slug, b.logo_path, b.website, b.country_code, b.status,
                       ' . $this->translationSelect('bt', 'name') . ',
                       ' . $this->translationSelect('bt', 'description') . ',
                       COALESCE(bt_loc.language_code, bt_fb.language_code) AS resolved_language_code
                FROM brands b
                ' . $this->translationJoin('b', 'id', 'brand_translations', 'bt', 'brand_id') . '
                WHERE ' . $this->notDeletedClause('b') . '
                ORDER BY name ASC, b.id ASC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->fetchAll($sql, $this->localeParams($locale));
    }

    public function create(array $data): string
    {
        throw new \LogicException('Brand write operations are not exposed in Phase 2.2 read APIs.');
    }

    public function update(string $uuid, array $data): bool
    {
        throw new \LogicException('Brand write operations are not exposed in Phase 2.2 read APIs.');
    }

    public function softDelete(string $uuid, ?int $actorId = null): bool
    {
        throw new \LogicException('Brand write operations are not exposed in Phase 2.2 read APIs.');
    }
}
